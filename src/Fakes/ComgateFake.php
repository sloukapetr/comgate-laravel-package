<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Fakes;

use ArrayObject;
use PHPUnit\Framework\Assert;
use Sloukapetr\ComgateLaravelPackage\ComgateService;
use Sloukapetr\ComgateLaravelPackage\Enums\PaymentStatus;

use function config;

class ComgateFake extends ComgateService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $responses;

    /**
     * @var ArrayObject<int, array<string, mixed>>
     */
    protected ArrayObject $calls;

    /**
     * @param  array<string, array<string, mixed>>  $responses
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
        $this->calls = new ArrayObject();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function setResponse(string $operation, array $response): self
    {
        $this->responses[$operation] = $response;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function calls(?string $operation = null): array
    {
        $calls = $this->calls->getArrayCopy();

        if ($operation === null) {
            return $calls;
        }

        return array_values(array_filter(
            $calls,
            static fn (array $call): bool => $call['operation'] === $operation
        ));
    }

    public function forMerchant(string $merchantId): static
    {
        $defaultId = trim((string) config('comgate.merchant_id', ''));

        if ($defaultId !== ''
            && trim($merchantId) === $defaultId
            && $this->credentialMerchantId === null
            && $this->credentialSecret === null
        ) {
            return $this;
        }

        return parent::forMerchant($merchantId);
    }

    /**
     * @param  (callable(array<string, mixed>): bool)|null  $callback
     */
    public function assertPaymentCreated(?callable $callback = null): void
    {
        $this->assertMatchingCall('createPayment', $callback, 'A Comgate payment was not created.');
    }

    /**
     * @param  (callable(array<string, mixed>): bool)|null  $callback
     */
    public function assertRecurringCharged(?callable $callback = null): void
    {
        $this->assertMatchingCall('recurringPayment', $callback, 'A Comgate recurring payment was not charged.');
    }

    public function assertRefunded(?string $transId = null, ?int $amount = null): void
    {
        $this->assertMatchingCall('refund', function (array $payload) use ($transId, $amount): bool {
            if ($transId !== null && ($payload['transId'] ?? null) !== $transId) {
                return false;
            }

            if ($amount !== null && ($payload['amount'] ?? null) !== $amount) {
                return false;
            }

            return true;
        }, 'A Comgate payment was not refunded.');
    }

    public function assertPaymentCancelled(?string $transId = null): void
    {
        $this->assertMatchingCall('cancelPayment', function (array $payload) use ($transId): bool {
            return $transId === null || ($payload['transId'] ?? null) === $transId;
        }, 'A Comgate payment was not cancelled.');
    }

    public function assertStatusChecked(?string $transId = null): void
    {
        $this->assertMatchingCall('status', function (array $payload) use ($transId): bool {
            return $transId === null || ($payload['transId'] ?? null) === $transId;
        }, 'A Comgate payment status was not checked.');
    }

    public function assertNothingSent(): void
    {
        Assert::assertCount(0, $this->calls, 'Comgate requests were sent.');
    }

    public function willReturnStatus(string $transId, PaymentStatus $status, int $price, string $curr = 'CZK'): self
    {
        return $this->setResponse('status', [
            'code' => 0,
            'message' => 'OK',
            'transId' => $transId,
            'status' => $status->value,
            'price' => $price,
            'curr' => $curr,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function webhookPayload(string $transId, PaymentStatus $status, array $overrides = []): array
    {
        return array_replace([
            'merchant' => (string) config('comgate.merchant_id', ''),
            'transId' => $transId,
            'status' => $status->value,
            'price' => 0,
            'curr' => 'CZK',
            'secret' => (string) config('comgate.secret', ''),
        ], $overrides);
    }

    /**
     * @param  (callable(array<string, mixed>): bool)|null  $callback
     */
    private function assertMatchingCall(string $operation, ?callable $callback, string $message): void
    {
        foreach ($this->calls($operation) as $call) {
            $payload = is_array($call['payload'] ?? null) ? $call['payload'] : [];

            if ($callback === null || $callback($payload) === true) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail($message);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload): array
    {
        return $this->fakeCall('createPayment', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function paymentRedirect(array $payload): array
    {
        return $this->fakeCall('paymentRedirect', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $requiredMethods
     * @return array<string, mixed>
     */
    public function createPaymentChecked(array $payload, array $requiredMethods = []): array
    {
        return $this->fakeCall('createPaymentChecked', ['payload' => $payload, 'requiredMethods' => $requiredMethods]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function recurringPayment(array $payload): array
    {
        return $this->fakeCall('recurringPayment', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function capture(array $payload): array
    {
        return $this->fakeCall('capture', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancel(array $payload): array
    {
        return $this->fakeCall('cancel', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancelPayment(array $payload): array
    {
        return $this->fakeCall('cancelPayment', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancelPreauth(array $payload): array
    {
        return $this->fakeCall('cancelPreauth', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refund(array $payload): array
    {
        return $this->fakeCall('refund', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refundPos(array $payload): array
    {
        return $this->fakeCall('refundPos', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function status(array $payload): array
    {
        return $this->fakeCall('status', $payload);
    }

    /**
     * @param  array<string, scalar|null>  $filters
     * @return array<string, mixed>
     */
    public function methods(array $filters = []): array
    {
        return $this->fakeCall('methods', $filters);
    }

    /**
     * @param  string  $date
     * @return array<int, array<string, mixed>>
     */
    public function transferList(string $date, bool $test = false): array
    {
        return $this->fakeCall('transferList', ['date' => $date, 'test' => $test]);
    }

    /**
     * @param  string  $transferId
     * @return array<string, mixed>
     */
    public function singleTransfer(string $transferId, bool $test = false): array
    {
        return $this->fakeCall('singleTransfer', ['transferId' => $transferId, 'test' => $test]);
    }

    /**
     * @param  string  $transferId
     * @return array<string, mixed>
     */
    public function csvSingleTransfer(string $transferId, bool $download = false, bool $test = false): array
    {
        return $this->fakeCall('csvSingleTransfer', ['transferId' => $transferId, 'download' => $download, 'test' => $test]);
    }

    /**
     * @param  string  $transferId
     * @return array<string, mixed>
     */
    public function aboSingleTransfer(string $transferId, bool $download = false, string $type = 'v1', string $encoding = 'utf8', bool $test = false): array
    {
        return $this->fakeCall('aboSingleTransfer', [
            'transferId' => $transferId,
            'download' => $download,
            'type' => $type,
            'encoding' => $encoding,
            'test' => $test,
        ]);
    }

    /**
     * @param  string  $date
     * @return array<string, mixed>
     */
    public function csvDownload(string $date, bool $test = false): array
    {
        return $this->fakeCall('csvDownload', ['date' => $date, 'test' => $test]);
    }

    /**
     * @param  string  $date
     * @return array<string, mixed>
     */
    public function aboDownload(string $date, string $type = 'v1', string $encoding = 'utf8', bool $test = false): array
    {
        return $this->fakeCall('aboDownload', [
            'date' => $date,
            'type' => $type,
            'encoding' => $encoding,
            'test' => $test,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function config(array $payload): array
    {
        return $this->fakeCall('config', $payload);
    }

    /**
     * @param  string|null  $currency
     * @return array<string, mixed>
     */
    public function appleDomainAssociation(?string $currency = null): array
    {
        return $this->fakeCall('appleDomainAssociation', ['currency' => $currency]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createInitialRecurringPayment(array $payload): array
    {
        return $this->fakeCall('createInitialRecurringPayment', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string|null  $providedSecret
     * @return array<string, mixed>
     */
    public function handleWebhook(array $payload, ?string $providedSecret = null): array
    {
        return parent::handleWebhook($payload, $providedSecret);
    }

    protected function request(string $operation, string $method, string $path, array $payload = [], array $query = []): array
    {
        return $this->recordCall($operation, $method, $path, $payload, $query);
    }

    protected function statusRequest(string $operation, string $path, array $query = []): array
    {
        return $this->recordCall($operation, 'GET', $path, [], $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function fakeCall(string $operation, array $payload): array
    {
        $this->calls[] = [
            'operation' => $operation,
            'payload' => $payload,
        ];

        return $this->responses[$operation] ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function recordCall(string $operation, string $method, string $path, array $payload, array $query): array
    {
        $this->calls[] = [
            'operation' => $operation,
            'method' => $method,
            'path' => $path,
            'payload' => $payload,
            'query' => $query,
        ];

        return $this->responses[$operation] ?? [];
    }
}