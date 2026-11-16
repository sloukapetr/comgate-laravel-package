<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Tests;

use ArrayObject;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sloukapetr\ComgateLaravelPackage\Data\ComgateWebhookNotification;
use Sloukapetr\ComgateLaravelPackage\Enums\PaymentStatus;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentAuthorized;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentFailed;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentPaid;
use Sloukapetr\ComgateLaravelPackage\Events\ComgateWebhookReceived;

class ComgateWebhookControllerTest extends TestCase
{
    public function test_webhook_dispatches_typed_notification(): void
    {
        Event::fake([ComgateWebhookReceived::class]);
        $this->fakePaymentStatus('TX-001', 'PAID');

        $response = $this->post('/comgate/webhook', [
            'code' => 0,
            'message' => 'OK',
            'merchant' => 'merchant-123',
            'test' => 'true',
            'price' => '1000',
            'curr' => 'CZK',
            'label' => 'ORDER-1001',
            'refId' => '1001',
            'transId' => 'TX-001',
            'status' => 'PAID',
            'cardValid' => '12/30',
            'cardNumber' => '****1111',
            'appliedFee' => 0,
            'appliedFeeType' => 'EU_CONSUMER',
            'threeDSPreference' => 'AUTO',
            'threeDSApplied' => 'true',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ]);

        $response->assertOk();
        $this->assertSame('code=0&message=OK', $response->getContent());

        Event::assertDispatched(ComgateWebhookReceived::class, function (ComgateWebhookReceived $event): bool {
            return $event->notification->isPaid()
                && $event->notification->test === true
                && $event->notification->price === 1000
                && $event->notification->cardNumber === '****1111'
                && $event->notification->appliedFeeType === 'EU_CONSUMER'
                && $event->notification->threeDSPreference === 'AUTO'
                && $event->notification->threeDSApplied === true
                && $event->notification->refId === '1001'
                && $event->notification->secret === null
                && $event->verifiedStatus?->isPaid() === true
                && $this->payloadHasNoSecret($event->payload)
                && $this->payloadHasNoSecret($event->notification->toArray());
        });
    }

    public function test_webhook_dispatches_paid_event_for_verified_paid_status(): void
    {
        Event::fake([ComgatePaymentPaid::class]);
        $this->fakePaymentStatus('TX-001', 'PAID');

        $this->post('/comgate/webhook', [
            'transId' => 'TX-001',
            'status' => 'PAID',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ])->assertOk();

        Event::assertDispatched(ComgatePaymentPaid::class, function (ComgatePaymentPaid $event): bool {
            return $event->notification->isPaid()
                && $event->notification->transId === 'TX-001'
                && $event->verifiedStatus?->status === PaymentStatus::Paid
                && $event->verifiedStatus->transId === 'TX-001'
                && $this->payloadHasNoSecret($event->payload);
        });
    }

    public function test_paid_notification_with_pending_status_api_dispatches_no_typed_event(): void
    {
        Event::fake([
            ComgatePaymentPaid::class,
            ComgatePaymentFailed::class,
            ComgatePaymentAuthorized::class,
        ]);
        $warnings = $this->captureWarnings();
        $this->fakePaymentStatus('TX-001', 'PENDING');

        $this->post('/comgate/webhook', [
            'transId' => 'TX-001',
            'status' => 'PAID',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ])->assertOk();

        Event::assertNotDispatched(ComgatePaymentPaid::class);
        Event::assertNotDispatched(ComgatePaymentFailed::class);
        Event::assertNotDispatched(ComgatePaymentAuthorized::class);
        $this->assertCount(1, $warnings);
        $this->assertSame('PENDING', $warnings[0]->context['verifiedStatus'] ?? null);
        $this->assertStringNotContainsString('secret-xyz', (string) json_encode($warnings[0]->context));
    }

    public function test_webhook_dispatches_failed_event_for_verified_cancelled_status(): void
    {
        Event::fake([ComgatePaymentFailed::class]);
        $this->fakePaymentStatus('TX-002', 'CANCELLED');

        $this->post('/comgate/webhook', [
            'transId' => 'TX-002',
            'status' => 'CANCELLED',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ])->assertOk();

        Event::assertDispatched(ComgatePaymentFailed::class, function (ComgatePaymentFailed $event): bool {
            return $event->notification->isCancelled()
                && $event->notification->transId === 'TX-002'
                && $event->verifiedStatus?->status === PaymentStatus::Cancelled;
        });
    }

    public function test_verified_authorized_status_dispatches_authorized_event(): void
    {
        Event::fake([ComgatePaymentAuthorized::class]);
        $this->fakePaymentStatus('TX-003', 'AUTHORIZED');

        $this->post('/comgate/webhook', [
            'transId' => 'TX-003',
            'status' => 'PENDING',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ])->assertOk();

        Event::assertDispatched(ComgatePaymentAuthorized::class, function (ComgatePaymentAuthorized $event): bool {
            return $event->verifiedStatus?->status === PaymentStatus::Authorized
                && $event->verifiedStatus->transId === 'TX-003';
        });
    }

    public function test_status_api_http_error_returns_503_without_typed_events(): void
    {
        Event::fake([
            ComgateWebhookReceived::class,
            ComgatePaymentPaid::class,
            ComgatePaymentFailed::class,
            ComgatePaymentAuthorized::class,
        ]);
        Http::fake([
            'https://payments.comgate.cz/v2.0/payment/transId/TX-500.json' => Http::response('secret-should-not-leak', 500),
        ]);

        $response = $this->post('/comgate/webhook', [
            'transId' => 'TX-500',
            'status' => 'PAID',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ]);

        $response->assertStatus(503);
        $this->assertStringNotContainsString('secret-should-not-leak', (string) $response->getContent());
        $this->assertStringNotContainsString('secret-xyz', (string) $response->getContent());
        Event::assertNotDispatched(ComgateWebhookReceived::class);
        Event::assertNotDispatched(ComgatePaymentPaid::class);
        Event::assertNotDispatched(ComgatePaymentFailed::class);
        Event::assertNotDispatched(ComgatePaymentAuthorized::class);
    }

    public function test_missing_payment_returns_ok_without_typed_events(): void
    {
        Event::fake([
            ComgatePaymentPaid::class,
            ComgatePaymentFailed::class,
            ComgatePaymentAuthorized::class,
        ]);
        $warnings = $this->captureWarnings();
        Http::fake([
            'https://payments.comgate.cz/v2.0/payment/transId/TX-404.json' => Http::response([
                'code' => 1400,
                'message' => 'Payment not found',
            ], 404),
        ]);

        $this->post('/comgate/webhook', [
            'transId' => 'TX-404',
            'status' => 'PAID',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ])->assertOk();

        Event::assertNotDispatched(ComgatePaymentPaid::class);
        Event::assertNotDispatched(ComgatePaymentFailed::class);
        Event::assertNotDispatched(ComgatePaymentAuthorized::class);
        $this->assertCount(1, $warnings);
        $this->assertStringNotContainsString('secret-xyz', (string) json_encode($warnings[0]->context));
    }

    public function test_verify_status_can_be_disabled(): void
    {
        config(['comgate.webhook.verify_status' => false]);
        Event::fake([ComgatePaymentPaid::class]);
        Http::fake();

        $this->post('/comgate/webhook', [
            'transId' => 'TX-001',
            'status' => 'PAID',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ])->assertOk();

        Http::assertNothingSent();
        Event::assertDispatched(ComgatePaymentPaid::class, function (ComgatePaymentPaid $event): bool {
            return $event->notification->isPaid()
                && $event->verifiedStatus === null
                && $this->payloadHasNoSecret($event->payload);
        });
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $response = $this->post('/comgate/webhook', [
            'transId' => 'TX-002',
            'status' => 'CANCELLED',
            'secret' => 'wrong-secret',
        ], [
            'X-Comgate-Secret' => 'wrong-secret',
        ]);

        $response->assertUnauthorized();
        $this->assertSame('code=1&message=Invalid+secret', $response->getContent());
    }

    public function test_webhook_accepts_a_configured_second_merchant_secret(): void
    {
        config([
            'comgate.merchants' => [
                'merchant-456' => [
                    'secret' => 'secret-other',
                    'test' => null,
                ],
            ],
        ]);
        Event::fake([ComgatePaymentPaid::class]);
        $this->fakePaymentStatus('TX-009', 'PAID');

        $this->post('/comgate/webhook', [
            'merchant' => 'merchant-456',
            'transId' => 'TX-009',
            'status' => 'PAID',
            'secret' => 'secret-other',
        ], [
            'X-Comgate-Secret' => 'secret-other',
        ])->assertOk();

        Event::assertDispatched(ComgatePaymentPaid::class);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://payments.comgate.cz/v2.0/payment/transId/TX-009.json'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('merchant-456:secret-other'));
        });
    }

    public function test_webhook_rejects_the_default_secret_for_a_second_merchant(): void
    {
        config([
            'comgate.merchants' => [
                'merchant-456' => [
                    'secret' => 'secret-other',
                ],
            ],
        ]);
        Http::fake();

        $this->post('/comgate/webhook', [
            'merchant' => 'merchant-456',
            'transId' => 'TX-009',
            'status' => 'PAID',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_webhook_allows_an_ip_from_the_comgate_list(): void
    {
        config(['comgate.webhook.verify_ip' => true]);
        Cache::flush();
        Event::fake([ComgatePaymentPaid::class]);
        Http::fake([
            'https://payments.comgate.cz/ips-v4' => Http::response("10.0.0.0/8\n# comment\n", 200),
            'https://payments.comgate.cz/v2.0/payment/transId/TX-001.json' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'transId' => 'TX-001',
                'status' => 'PAID',
                'price' => 1000,
                'curr' => 'CZK',
            ], 200),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->post('/comgate/webhook', [
                'transId' => 'TX-001',
                'status' => 'PAID',
                'secret' => 'secret-xyz',
            ], [
                'X-Comgate-Secret' => 'secret-xyz',
            ])->assertOk();

        Event::assertDispatched(ComgatePaymentPaid::class);
    }

    public function test_webhook_rejects_an_ip_outside_the_comgate_list(): void
    {
        config(['comgate.webhook.verify_ip' => true]);
        Cache::flush();
        Event::fake([ComgatePaymentPaid::class]);
        Http::fake([
            'https://payments.comgate.cz/ips-v4' => Http::response("89.185.236.55\n", 200),
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->post('/comgate/webhook', [
                'transId' => 'TX-001',
                'status' => 'PAID',
                'secret' => 'secret-xyz',
            ], [
                'X-Comgate-Secret' => 'secret-xyz',
            ]);

        $response->assertForbidden();
        $this->assertSame('code=1&message=Forbidden', $response->getContent());
        Event::assertNotDispatched(ComgatePaymentPaid::class);
        Http::assertNotSent(function (Request $request): bool {
            return str_contains($request->url(), '/payment/transId/');
        });
    }

    public function test_webhook_skips_ip_check_when_the_list_cannot_be_downloaded(): void
    {
        config(['comgate.webhook.verify_ip' => true]);
        Cache::flush();
        $warnings = $this->captureWarnings();
        Event::fake([ComgatePaymentPaid::class]);
        Http::fake([
            'https://payments.comgate.cz/ips-v4' => Http::response('down', 503),
            'https://payments.comgate.cz/v2.0/payment/transId/TX-001.json' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'transId' => 'TX-001',
                'status' => 'PAID',
                'price' => 1000,
                'curr' => 'CZK',
            ], 200),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->post('/comgate/webhook', [
                'transId' => 'TX-001',
                'status' => 'PAID',
                'secret' => 'secret-xyz',
            ], [
                'X-Comgate-Secret' => 'secret-xyz',
            ])->assertOk();

        Event::assertDispatched(ComgatePaymentPaid::class);
        $this->assertCount(1, $warnings);
        $this->assertSame('Comgate webhook IP list could not be downloaded.', $warnings[0]->message);
    }

    public function test_webhook_notification_formats_price_in_main_unit(): void
    {
        $notification = ComgateWebhookNotification::fromArray([
            'price' => 12345,
            'curr' => 'CZK',
            'status' => 'PAID',
            'secret' => 'secret-xyz',
        ]);

        $this->assertSame(123.45, $notification->getPriceInMainUnit());
        $this->assertSame('123.45', $notification->getFormattedPriceAttribute());
        $this->assertTrue($notification->matchesAmount(12345, 'CZK'));
        $this->assertFalse($notification->matchesAmount(12345, 'EUR'));
        $this->assertSame(PaymentStatus::Paid, $notification->paymentStatus());
        $this->assertNull($notification->secret);
        $this->assertArrayNotHasKey('secret', $notification->toArray());
    }

    private function fakePaymentStatus(string $transId, string $status): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/payment/transId/'.$transId.'.json' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'transId' => $transId,
                'status' => $status,
                'price' => 1000,
                'curr' => 'CZK',
            ], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadHasNoSecret(array $payload): bool
    {
        return ! array_key_exists('secret', $payload);
    }

    /**
     * @return ArrayObject<int, MessageLogged>
     */
    private function captureWarnings(): ArrayObject
    {
        $warnings = new ArrayObject();

        Log::listen(function (MessageLogged $event) use ($warnings): void {
            if ($event->level === 'warning') {
                $warnings->append($event);
            }
        });

        return $warnings;
    }
}
