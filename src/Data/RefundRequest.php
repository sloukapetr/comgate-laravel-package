<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Data;

use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidPaymentPayloadException;
use Sloukapetr\ComgateLaravelPackage\Support\PaymentAmount;

final class RefundRequest
{
    public readonly string $transId;

    public readonly ?string $refId;

    public readonly ?string $currency;

    public function __construct(
        string $transId,
        public readonly int $amount,
        ?string $refId = null,
        public readonly ?bool $test = null,
        ?string $currency = null,
    ) {
        $this->transId = trim($transId);
        $this->refId = $refId !== null && trim($refId) !== '' ? trim($refId) : null;
        $this->currency = $currency !== null && trim($currency) !== '' ? strtoupper(trim($currency)) : null;

        if ($this->transId === '') {
            throw new InvalidPaymentPayloadException('Refund requires transId.');
        }

        if ($this->amount < 1) {
            throw new InvalidPaymentPayloadException('Refund amount must be a positive number of minor units.');
        }

        if ($this->currency !== null) {
            PaymentAmount::assertRefund($this->currency, $this->amount);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'transId' => $this->transId,
            'amount' => $this->amount,
        ];

        if ($this->refId !== null) {
            $payload['refId'] = $this->refId;
        }

        if ($this->test !== null) {
            $payload['test'] = $this->test;
        }

        return $payload;
    }
}
