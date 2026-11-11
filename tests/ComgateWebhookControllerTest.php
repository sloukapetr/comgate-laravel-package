<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Tests;

use Illuminate\Support\Facades\Event;
use Sloukapetr\ComgateLaravelPackage\Data\ComgateWebhookNotification;
use Sloukapetr\ComgateLaravelPackage\Enums\PaymentStatus;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentFailed;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentPaid;
use Sloukapetr\ComgateLaravelPackage\Events\ComgateWebhookReceived;

class ComgateWebhookControllerTest extends TestCase
{
    public function test_webhook_dispatches_typed_notification(): void
    {
        Event::fake([ComgateWebhookReceived::class]);

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
                && $event->notification->refId === '1001';
        });
    }

    public function test_webhook_dispatches_paid_event_for_paid_status(): void
    {
        Event::fake([ComgatePaymentPaid::class]);

        $this->post('/comgate/webhook', [
            'transId' => 'TX-001',
            'status' => 'PAID',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ])->assertOk();

        Event::assertDispatched(ComgatePaymentPaid::class, function (ComgatePaymentPaid $event): bool {
            return $event->notification->isPaid() && $event->notification->transId === 'TX-001';
        });
    }

    public function test_webhook_dispatches_failed_event_for_cancelled_status(): void
    {
        Event::fake([ComgatePaymentFailed::class]);

        $this->post('/comgate/webhook', [
            'transId' => 'TX-002',
            'status' => 'CANCELLED',
            'secret' => 'secret-xyz',
        ], [
            'X-Comgate-Secret' => 'secret-xyz',
        ])->assertOk();

        Event::assertDispatched(ComgatePaymentFailed::class, function (ComgatePaymentFailed $event): bool {
            return $event->notification->isCancelled() && $event->notification->transId === 'TX-002';
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

    public function test_webhook_notification_formats_price_in_main_unit(): void
    {
        $notification = ComgateWebhookNotification::fromArray([
            'price' => 12345,
            'curr' => 'CZK',
            'status' => 'PAID',
        ]);

        $this->assertSame(123.45, $notification->getPriceInMainUnit());
        $this->assertSame('123.45', $notification->getFormattedPriceAttribute());
        $this->assertTrue($notification->matchesAmount(12345, 'CZK'));
        $this->assertFalse($notification->matchesAmount(12345, 'EUR'));
        $this->assertSame(PaymentStatus::Paid, $notification->paymentStatus());
    }
}