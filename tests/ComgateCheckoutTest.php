<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Sloukapetr\ComgateLaravelPackage\ComgateService;
use Sloukapetr\ComgateLaravelPackage\Data\CreatePayment;
use Sloukapetr\ComgateLaravelPackage\Data\RecurringCharge;
use Sloukapetr\ComgateLaravelPackage\Data\RefundRequest;
use Sloukapetr\ComgateLaravelPackage\Enums\PaymentStatus;
use Sloukapetr\ComgateLaravelPackage\Exceptions\ComgateRequestException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidPaymentPayloadException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\RecurringPaymentException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\RefundRejectedException;
use Sloukapetr\ComgateLaravelPackage\Facades\Comgate;
use Sloukapetr\ComgateLaravelPackage\Support\ReturnUrl;

class ComgateCheckoutTest extends TestCase
{
    public function test_create_checkout_sends_digital_payment_and_keeps_return_placeholders(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/payment.json' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'transId' => 'AB12-CD34-EF56',
                'redirect' => 'https://payments.comgate.cz/client/instructions/index?id=AB12-CD34-EF56',
            ], 200),
        ]);

        $created = $this->app->make(ComgateService::class)->createCheckout(CreatePayment::digital(
            price: 19900,
            refId: 'pay-1001',
            product: 'Kurz revizí a zkoušek',
            email: 'platce@email.com',
            fullName: 'Jan Novák',
            urlPaid: 'https://it-elektro.test/platba/navrat',
            urlCancelled: 'https://it-elektro.test/platba/zruseno',
            urlPending: 'https://it-elektro.test/platba/ceka',
        ));

        $this->assertSame('AB12-CD34-EF56', $created->transId);
        $this->assertSame(
            'https://payments.comgate.cz/client/instructions/index?id=AB12-CD34-EF56',
            $created->redirect
        );

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://payments.comgate.cz/v2.0/payment.json'
                && $request['price'] === 19900
                && $request['curr'] === 'CZK'
                && $request['refId'] === 'pay-1001'
                && $request['label'] === 'Kurz revizí a zk'
                && $request['name'] === 'Kurz revizí a zkoušek'
                && $request['category'] === 'OTHER'
                && $request['delivery'] === 'ELECTRONIC_DELIVERY'
                && $request['country'] === 'CZ'
                && $request['lang'] === 'cs'
                && $request['email'] === 'platce@email.com'
                && $request['fullName'] === 'Jan Novák'
                && $request['url_paid'] === 'https://it-elektro.test/platba/navrat?id=${id}&refId=${refId}'
                && $request['test'] === true
                && ! array_key_exists('initRecurring', $request->data());
        });
    }

    public function test_create_checkout_rejects_payment_without_contact_before_http(): void
    {
        Http::fake();

        try {
            CreatePayment::digital(
                price: 10000,
                refId: 'pay-1',
                product: 'Tarif',
            );
            $this->fail('A payment without a contact was accepted.');
        } catch (InvalidPaymentPayloadException) {
            Http::assertNothingSent();
        }
    }

    public function test_create_checkout_rejects_amount_below_currency_minimum(): void
    {
        $this->expectException(InvalidPaymentPayloadException::class);

        new CreatePayment(
            price: 50,
            refId: 'pay-1',
            product: 'Tarif',
            email: 'platce@email.com',
        );
    }

    public function test_recurring_charge_uses_new_reference_and_initial_transaction(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/recurring.json' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'transId' => 'RE-01',
            ], 200),
        ]);

        $created = $this->app->make(ComgateService::class)->chargeRecurring(new RecurringCharge(
            initRecurringId: 'AB12-CD34-EF56',
            price: 1000,
            refId: 'sub-42-2026-12',
            label: 'Tarif ŘídímTo',
        ));

        $this->assertSame('RE-01', $created->transId);
        $this->assertNull($created->redirect);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://payments.comgate.cz/v2.0/recurring.json'
                && $request['initRecurringId'] === 'AB12-CD34-EF56'
                && $request['refId'] === 'sub-42-2026-12'
                && $request['price'] === 1000
                && $request['label'] === 'Tarif ŘídímTo';
        });
    }

    public function test_find_payment_parses_status_and_amount(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/payment/transId/AB12-CD34-EF56.json' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'test' => 'true',
                'price' => '19900',
                'curr' => 'CZK',
                'refId' => 'pay-1001',
                'transId' => 'AB12-CD34-EF56',
                'status' => 'PAID',
                'vs' => '1001',
                'fee' => 'unknown',
            ], 200),
        ]);

        $status = $this->app->make(ComgateService::class)->findPayment('AB12-CD34-EF56');

        $this->assertSame(PaymentStatus::Paid, $status->status);
        $this->assertTrue($status->isPaid());
        $this->assertTrue($status->status->allowsRefund());
        $this->assertTrue($status->matchesAmount(19900, 'czk'));
        $this->assertSame('1001', $status->variableSymbol);
        $this->assertTrue($status->test);
    }

    public function test_with_credentials_uses_the_other_merchant_and_leaves_the_default_client(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/payment.json' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'transId' => 'TX-UNIT',
                'redirect' => 'https://payments.comgate.cz/tx',
            ], 200),
        ]);

        $service = $this->app->make(ComgateService::class);
        $unit = $service->withCredentials('unit-merchant', 'unit-secret');

        $unit->createPayment([
            'price' => 1000,
            'curr' => 'CZK',
            'label' => 'FA-1',
            'refId' => '1001',
            'method' => 'ALL',
            'email' => 'platce@email.com',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Basic '.base64_encode('unit-merchant:unit-secret'));
        });

        $service->createPayment([
            'price' => 1000,
            'curr' => 'CZK',
            'label' => 'FA-2',
            'refId' => '1002',
            'method' => 'ALL',
            'email' => 'platce@email.com',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Basic '.base64_encode('merchant-123:secret-xyz'))
                && $request['refId'] === '1002';
        });
    }

    public function test_refund_over_the_paid_amount_is_a_dedicated_exception(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/refund.json' => Http::response([
                'code' => 1402,
                'message' => 'Refund amount is higher than allowed',
            ], 400),
        ]);

        $this->expectException(RefundRejectedException::class);

        $this->app->make(ComgateService::class)->refundPayment(new RefundRequest(
            transId: 'AB12-CD34-EF56',
            amount: 50000,
            currency: 'CZK',
        ));
    }

    public function test_missing_initial_recurring_payment_is_a_dedicated_exception(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/recurring.json' => Http::response([
                'code' => 1318,
                'message' => 'Initial payment not found',
            ], 400),
        ]);

        $this->expectException(RecurringPaymentException::class);

        $this->app->make(ComgateService::class)->chargeRecurring(new RecurringCharge(
            initRecurringId: 'MISSING',
            price: 1000,
            refId: 'renew-1',
        ));
    }

    public function test_checkout_without_redirect_url_fails(): void
    {
        Http::fake([
            'https://payments.comgate.cz/v2.0/payment.json' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'transId' => 'TX-NO-REDIRECT',
            ], 200),
        ]);

        $this->expectException(ComgateRequestException::class);

        $this->app->make(ComgateService::class)->createCheckout(CreatePayment::digital(
            price: 10000,
            refId: 'pay-2',
            product: 'Kurz',
            email: 'platce@email.com',
        ));
    }

    public function test_fake_checkout_records_the_underlying_create_call(): void
    {
        $fake = Comgate::fake([
            'createPayment' => [
                'code' => 0,
                'message' => 'OK',
                'transId' => 'TX-FAKE',
                'redirect' => 'https://payments.comgate.cz/tx-fake',
            ],
        ]);

        $created = $fake->createCheckout(CreatePayment::digital(
            price: 10000,
            refId: 'pay-fake',
            product: 'Kurz',
            email: 'platce@email.com',
        ));

        $this->assertSame('TX-FAKE', $created->transId);
        $this->assertCount(1, $fake->calls('createPayment'));
        $this->assertSame('pay-fake', $fake->calls('createPayment')[0]['payload']['refId']);
    }

    public function test_return_url_keeps_existing_placeholders_literal(): void
    {
        $this->assertSame(
            'https://example.test/return?id=${id}&refId=${refId}',
            ReturnUrl::withPlaceholders('https://example.test/return')
        );

        $this->assertSame(
            'https://example.test/return?order=1&id=${id}&refId=${refId}',
            ReturnUrl::withPlaceholders('https://example.test/return?order=1')
        );

        $this->assertSame(
            'https://example.test/return?id=${id}',
            ReturnUrl::withPlaceholders('https://example.test/return?id=${id}')
        );
    }
}
