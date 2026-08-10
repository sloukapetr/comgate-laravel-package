<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Data;

class ComgateWebhookNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly ?int $code,
        public readonly ?string $message,
        public readonly ?string $merchant,
        public readonly ?bool $test,
        public readonly ?int $price,
        public readonly ?string $curr,
        public readonly ?string $label,
        public readonly ?string $refId,
        public readonly ?string $payerId,
        public readonly ?string $method,
        public readonly ?string $account,
        public readonly ?string $email,
        public readonly ?string $name,
        public readonly ?string $phone,
        public readonly ?string $transId,
        public readonly ?string $status,
        public readonly ?string $payerName,
        public readonly ?string $payerAcc,
        public readonly ?string $fee,
        public readonly ?string $vs,
        public readonly ?string $cardValid,
        public readonly ?string $cardNumber,
        public readonly ?int $appliedFee,
        public readonly ?string $appliedFeeType,
        public readonly ?string $paymentErrorReason,
        public readonly ?string $threeDSPreference,
        public readonly ?bool $threeDSApplied,
        public readonly ?string $secret,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            payload: $payload,
            code: self::toInt($payload['code'] ?? null),
            message: self::toString($payload['message'] ?? null),
            merchant: self::toString($payload['merchant'] ?? null),
            test: self::toBool($payload['test'] ?? null),
            price: self::toInt($payload['price'] ?? null),
            curr: self::toString($payload['curr'] ?? null),
            label: self::toString($payload['label'] ?? null),
            refId: self::toString($payload['refId'] ?? null),
            payerId: self::toString($payload['payerId'] ?? null),
            method: self::toString($payload['method'] ?? null),
            account: self::toString($payload['account'] ?? null),
            email: self::toString($payload['email'] ?? null),
            name: self::toString($payload['name'] ?? null),
            phone: self::toString($payload['phone'] ?? null),
            transId: self::toString($payload['transId'] ?? null),
            status: self::toString($payload['status'] ?? null),
            payerName: self::toString($payload['payerName'] ?? null),
            payerAcc: self::toString($payload['payerAcc'] ?? null),
            fee: self::toString($payload['fee'] ?? null),
            vs: self::toString($payload['vs'] ?? null),
            cardValid: self::toString($payload['cardValid'] ?? null),
            cardNumber: self::toString($payload['cardNumber'] ?? null),
            appliedFee: self::toInt($payload['appliedFee'] ?? null),
            appliedFeeType: self::toString($payload['appliedFeeType'] ?? null),
            paymentErrorReason: self::toString($payload['paymentErrorReason'] ?? null),
            threeDSPreference: self::toString($payload['threeDSPreference'] ?? null),
            threeDSApplied: self::toBool($payload['threeDSApplied'] ?? null),
            secret: self::toString($payload['secret'] ?? null),
        );
    }

    public function isPaid(): bool
    {
        return $this->status === 'PAID';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    public function isAuthorized(): bool
    {
        return $this->status === 'AUTHORIZED';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    private static function toString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private static function toInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function toBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', '1' => true,
                'false', '0' => false,
                default => null,
            };
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        return null;
    }
}