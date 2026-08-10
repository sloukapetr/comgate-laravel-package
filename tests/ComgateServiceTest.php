<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Sloukapetr\ComgateLaravelPackage\Facades\Comgate;
use Sloukapetr\ComgateLaravelPackage\ComgateService;
use Sloukapetr\ComgateLaravelPackage\Exceptions\MissingParameterException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\PaymentNotFoundException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\PaymentUnavailableException;

class ComgateServiceTest extends TestCase
{
    public function test_create_payment_uses_expected_endpoint_and_basic_auth(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/payment.json' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'transId' => 'TX-001',
                'redirect' => 'https://payments.comgate.cz/client/instructions/index?id=TX-001',
            ], 200),
        ]);

        $response = $this->app->make(ComgateService::class)->createPayment([
            'price' => 1000,
            'curr' => 'CZK',
            'label' => 'ORDER-1001',
            'refId' => '1001',
            'method' => 'ALL',
        ]);

        $this->assertSame('TX-001', $response['transId']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://payments.comgate.cz/v2.0/payment.json'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('merchant-123:secret-xyz'))
                && $request['test'] === true
                && $request['price'] === 1000
                && $request['method'] === 'ALL';
        });
    }

    public function test_payment_redirect_uses_expected_endpoint(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/paymentRedirect/merchant/merchant-123' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'transId' => 'TX-REDIRECT',
            ], 200),
        ]);

        $response = $this->app->make(ComgateService::class)->paymentRedirect([
            'price' => 1000,
            'curr' => 'CZK',
            'label' => 'ORDER-1001',
            'refId' => '1001',
            'method' => 'ALL',
        ]);

        $this->assertSame('TX-REDIRECT', $response['transId']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://payments.comgate.cz/v2.0/paymentRedirect/merchant/merchant-123'
                && $request['test'] === true;
        });
    }

    public function test_methods_uses_method_endpoint_with_filters(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/method.json*' => Http::response([
                'methods' => [
                    ['id' => 'CARD_CZ_CSOB_2', 'name' => 'Card payment'],
                ],
            ], 200),
        ]);

        $response = $this->app->make(ComgateService::class)->methods([
            'country' => 'CZ',
            'curr' => 'CZK',
            'price' => 1000,
            'initRecurring' => true,
        ]);

        $this->assertCount(1, $response['methods']);

        Http::assertSent(function (Request $request): bool {
            $url = $request->url();

            return $request->method() === 'GET'
                && str_starts_with($url, 'https://payments.comgate.cz/v2.0/method.json?')
                && str_contains($url, 'country=CZ')
                && str_contains($url, 'curr=CZK')
                && str_contains($url, 'price=1000')
                && str_contains($url, 'initRecurring=1');
        });
    }

    public function test_transfer_list_uses_expected_endpoint(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/transferList/date/2025-04-25.json?test=1' => Http::response([
                [
                    'transferId' => 1234567,
                    'transferDate' => '2025-04-25',
                    'accountCounterparty' => '0/0000',
                    'accountOutgoing' => '123456789/0000',
                    'variableSymbol' => '12345678',
                ],
            ], 200),
        ]);

        $response = $this->app->make(ComgateService::class)->transferList('2025-04-25', true);

        $this->assertCount(1, $response);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://payments.comgate.cz/v2.0/transferList/date/2025-04-25.json?test=1';
        });
    }

    public function test_comgate_fake_records_calls_and_returns_configured_response(): void
    {
        $fake = Comgate::fake([
            'createPayment' => [
                'code' => 0,
                'message' => 'OK',
                'transId' => 'TX-FAKE',
            ],
        ]);

        $response = Comgate::createPayment([
            'price' => 1000,
            'curr' => 'CZK',
            'label' => 'ORDER-1001',
            'refId' => '1001',
            'method' => 'ALL',
        ]);

        $this->assertSame('TX-FAKE', $response['transId']);
        $this->assertCount(1, $fake->calls('createPayment'));
        $this->assertSame('createPayment', $fake->calls('createPayment')[0]['operation']);
    }

    public function test_refund_maps_missing_parameter_exception(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/refund.json' => Http::response([
                'code' => 1400,
                'message' => 'Missing parameter amount',
            ], 400),
        ]);

        $this->expectException(MissingParameterException::class);

        $this->app->make(ComgateService::class)->refund([
            'transId' => 'TX-REFUND',
        ]);
    }

    public function test_status_maps_payment_not_found_exception(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/payment/transId/TX-404.json' => Http::response([
                'code' => 1400,
                'message' => 'Payment not found',
            ], 404),
        ]);

        $this->expectException(PaymentNotFoundException::class);

        $this->app->make(ComgateService::class)->status([
            'transId' => 'TX-404',
        ]);
    }

    public function test_gateway_health_uses_status_api_endpoint(): void
    {
        Http::fake([
            'https://status.comgate.cz/health' => Http::response([
                'gateway' => 'OK',
                'date' => '2025-06-04T07:05:54Z',
            ], 200),
        ]);

        $response = $this->app->make(ComgateService::class)->gatewayHealth();

        $this->assertSame('OK', $response['gateway']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://status.comgate.cz/health';
        });
    }

    public function test_payment_availability_reports_outage_for_required_method(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/method.json*' => Http::response([
                'methods' => [
                    ['id' => 'CARD_CZ_CSOB_2', 'name' => 'Card payment'],
                ],
            ], 200),
            'https://status.comgate.cz/health' => Http::response([
                'gateway' => 'OK',
                'date' => '2025-06-04T07:05:54Z',
            ], 200),
            'https://status.comgate.cz/outages' => Http::response([
                [
                    'id' => 123,
                    'name_en' => 'Card payment',
                    'detail_en' => 'Card payment unavailable.',
                    'methods' => ['CARD_CZ_CSOB_2'],
                    'type' => 'COMPLETE',
                    'scheduled' => true,
                ],
            ], 200),
        ]);

        $response = $this->app->make(ComgateService::class)->paymentAvailability(
            ['country' => 'CZ', 'curr' => 'CZK'],
            ['CARD_CZ_CSOB_2']
        );

        $this->assertFalse($response['available']);
        $this->assertSame([], $response['usableRequiredMethods']);
        $this->assertSame(['CARD_CZ_CSOB_2'], $response['availableMethods']);
        $this->assertSame(['CARD_CZ_CSOB_2'], array_values($response['outages'][0]['methods']));
        $this->assertStringContainsString('outage', strtolower($response['summary']));
    }

    public function test_payment_availability_returns_true_when_alternative_method_is_usable(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/method.json*' => Http::response([
                'methods' => [
                    ['id' => 'CARD_CZ_CSOB_2', 'name' => 'Card payment'],
                    ['id' => 'BANK_CZ_KB', 'name' => 'Bank transfer'],
                ],
            ], 200),
            'https://status.comgate.cz/health' => Http::response([
                'gateway' => 'OK',
                'date' => '2025-06-04T07:05:54Z',
            ], 200),
            'https://status.comgate.cz/outages' => Http::response([
                [
                    'id' => 123,
                    'name_en' => 'Card payment',
                    'detail_en' => 'Card payment unavailable.',
                    'methods' => ['CARD_CZ_CSOB_2'],
                    'type' => 'COMPLETE',
                    'scheduled' => false,
                ],
            ], 200),
        ]);

        $response = $this->app->make(ComgateService::class)->paymentAvailability([
            'country' => 'CZ',
            'curr' => 'CZK',
        ]);

        $this->assertTrue($response['available']);
        $this->assertSame(['BANK_CZ_KB'], $response['usableMethods']);
        $this->assertSame([], $response['reasonCodes']);
    }

    public function test_payment_availability_report_returns_typed_object_with_localized_messages(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/method.json*' => Http::response([
                'methods' => [],
            ], 200),
            'https://status.comgate.cz/health' => Http::response([
                'gateway' => 'OUTAGE',
                'date' => '2025-06-04T07:05:54Z',
            ], 200),
            'https://status.comgate.cz/outages' => Http::response([], 200),
        ]);

        $report = $this->app->make(ComgateService::class)->paymentAvailabilityReport([
            'country' => 'CZ',
            'curr' => 'CZK',
        ]);

        $this->assertFalse($report->isAvailable());
        $this->assertTrue($report->hasReasonCode('availability.gateway_outage'));
        $this->assertSame('availability.gateway_outage', $report->summaryKey);
        $this->assertStringContainsString('gateway', strtolower($report->summaryFor('en')));
        $this->assertStringContainsString('gateway', strtolower($report->summaryFor('cs')));
    }

    public function test_create_payment_checked_blocks_unavailable_method_before_create_call(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/method.json*' => Http::response([
                'methods' => [
                    ['id' => 'CARD_CZ_CSOB_2', 'name' => 'Card payment'],
                ],
            ], 200),
            'https://status.comgate.cz/health' => Http::response([
                'gateway' => 'OK',
                'date' => '2025-06-04T07:05:54Z',
            ], 200),
            'https://status.comgate.cz/outages' => Http::response([
                [
                    'id' => 123,
                    'name_en' => 'Card payment',
                    'detail_en' => 'Card payment unavailable.',
                    'methods' => ['CARD_CZ_CSOB_2'],
                    'type' => 'COMPLETE',
                    'scheduled' => true,
                ],
            ], 200),
            'https://payments.comgate.cz/v2.0/payment.json' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'transId' => 'SHOULD-NOT-HAPPEN',
            ], 200),
        ]);

        try {
            $this->app->make(ComgateService::class)->createPaymentChecked([
                'price' => 1000,
                'curr' => 'CZK',
                'country' => 'CZ',
                'label' => 'ORDER-1001',
                'refId' => '1001',
                'method' => 'CARD_CZ_CSOB_2',
            ]);

            $this->fail('Expected PaymentUnavailableException was not thrown.');
        } catch (PaymentUnavailableException $exception) {
            $this->assertTrue($exception->availability()->hasReasonCode('availability.required_methods_outage'));
        }

        Http::assertSentCount(3);
    }
}