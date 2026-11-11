<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Data;

use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidPaymentPayloadException;
use Sloukapetr\ComgateLaravelPackage\Support\PaymentAmount;
use Sloukapetr\ComgateLaravelPackage\Support\PaymentLabel;

final class RecurringCharge
{
    public readonly string $initRecurringId;

    public readonly string $refId;

    public readonly string $currency;

    public readonly ?string $label;

    public readonly ?string $name;

    public function __construct(
        string $initRecurringId,
        public readonly int $price,
        string $refId,
        string $currency = 'CZK',
        ?string $label = null,
        ?string $name = null,
        public readonly ?string $account = null,
        public readonly ?bool $test = null,
    ) {
        $this->initRecurringId = trim($initRecurringId);
        $this->refId = trim($refId);
        $this->currency = strtoupper(trim($currency));
        $this->name = $name !== null && trim($name) !== '' ? trim($name) : null;
        $this->label = $label !== null && trim($label) !== ''
            ? PaymentLabel::fromProduct($label)
            : ($this->name !== null ? PaymentLabel::fromProduct($this->name) : null);

        if ($this->initRecurringId === '') {
            throw new InvalidPaymentPayloadException('initRecurringId is required for a recurring charge.');
        }

        if ($this->refId === '') {
            throw new InvalidPaymentPayloadException('refId is required for a recurring charge. Use a new id for every renewal.');
        }

        PaymentAmount::assert($this->currency, $this->price, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'initRecurringId' => $this->initRecurringId,
            'price' => $this->price,
            'curr' => $this->currency,
            'refId' => $this->refId,
        ];

        if ($this->label !== null) {
            $payload['label'] = $this->label;
        }

        if ($this->name !== null) {
            $payload['name'] = $this->name;
        }

        if ($this->account !== null && $this->account !== '') {
            $payload['account'] = $this->account;
        }

        if ($this->test !== null) {
            $payload['test'] = $this->test;
        }

        return $payload;
    }
}
