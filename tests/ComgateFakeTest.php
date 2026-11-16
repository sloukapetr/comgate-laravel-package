<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use Sloukapetr\ComgateLaravelPackage\Enums\PaymentStatus;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentPaid;
use Sloukapetr\ComgateLaravelPackage\Facades\Comgate;

class ComgateFakeTest extends TestCase
{
    public function test_assertions_pass_for_recorded_calls(): void
    {
        $fake = Comgate::fake();

        $fake->assertNothingSent();

        Comgate::createPayment([
            'price' => 19900,
            'curr' => 'CZK',
            'label' => 'ORDER-1001',
            'refId' => '1001',
            'method' => 'ALL',
        ]);
        Comgate::recurringPayment([
            'initRecurringId' => 'TX-001',
            'price' => 19900,
            'refId' => '1001-2',
        ]);
        Comgate::refund([
            'transId' => 'TX-001',
            'amount' => 5000,
        ]);
        Comgate::cancelPayment([
            'transId' => 'TX-001',
        ]);
        $fake->willReturnStatus('TX-001', PaymentStatus::Pending, 19900);
        Comgate::findPayment('TX-001');

        $fake->assertPaymentCreated(fn (array $payload): bool => ($payload['refId'] ?? null) === '1001');
        $fake->assertRecurringCharged(fn (array $payload): bool => ($payload['initRecurringId'] ?? null) === 'TX-001');
        $fake->assertRefunded('TX-001', 5000);
        $fake->assertPaymentCancelled('TX-001');
        $fake->assertStatusChecked('TX-001');
    }

    public function test_assert_payment_created_fails_when_callback_does_not_match(): void
    {
        $fake = Comgate::fake();
        Comgate::createPayment(['refId' => '1001', 'price' => 1000]);

        $this->expectException(AssertionFailedError::class);

        $fake->assertPaymentCreated(fn (array $payload): bool => ($payload['refId'] ?? null) === 'other');
    }

    public function test_assert_recurring_charged_fails_without_a_charge(): void
    {
        $fake = Comgate::fake();

        $this->expectException(AssertionFailedError::class);

        $fake->assertRecurringCharged();
    }

    public function test_assert_refunded_fails_for_a_different_amount(): void
    {
        $fake = Comgate::fake();
        Comgate::refund(['transId' => 'TX-001', 'amount' => 1000]);

        $this->expectException(AssertionFailedError::class);

        $fake->assertRefunded('TX-001', 5000);
    }

    public function test_assert_payment_cancelled_fails_for_another_transaction(): void
    {
        $fake = Comgate::fake();
        Comgate::cancelPayment(['transId' => 'TX-001']);

        $this->expectException(AssertionFailedError::class);

        $fake->assertPaymentCancelled('TX-002');
    }

    public function test_assert_status_checked_fails_without_a_status_call(): void
    {
        $fake = Comgate::fake();

        $this->expectException(AssertionFailedError::class);

        $fake->assertStatusChecked('TX-001');
    }

    public function test_assert_nothing_sent_fails_after_a_call(): void
    {
        $fake = Comgate::fake();
        Comgate::createPayment(['price' => 1000]);

        $this->expectException(AssertionFailedError::class);

        $fake->assertNothingSent();
    }

    public function test_fake_webhook_flow_marks_the_payment_paid_without_http(): void
    {
        Http::preventStrayRequests();

        $paid = [];
        Event::listen(ComgatePaymentPaid::class, function (ComgatePaymentPaid $event) use (&$paid): void {
            if ($event->verifiedStatus?->isPaid() === true) {
                $paid[] = $event->verifiedStatus->transId;
            }
        });

        $fake = Comgate::fake([
            'createPayment' => [
                'code' => 0,
                'message' => 'OK',
                'transId' => 'TX-FLOW',
                'redirect' => 'https://payments.comgate.cz/tx',
            ],
        ]);

        $payment = Comgate::createPayment([
            'price' => 19900,
            'curr' => 'CZK',
            'label' => 'ORDER-1001',
            'refId' => '1001',
            'method' => 'ALL',
        ]);

        $fake->willReturnStatus($payment['transId'], PaymentStatus::Paid, 19900);

        $payload = $fake->webhookPayload($payment['transId'], PaymentStatus::Paid, [
            'price' => 19900,
            'curr' => 'CZK',
            'refId' => '1001',
        ]);

        $this->assertSame('secret-xyz', $payload['secret']);
        $this->assertSame('PAID', $payload['status']);

        $this->post(route('comgate.webhook'), $payload)->assertOk();

        $fake->assertPaymentCreated(fn (array $body): bool => ($body['refId'] ?? null) === '1001');
        $fake->assertStatusChecked('TX-FLOW');
        $this->assertSame(['TX-FLOW'], $paid);
    }
}
