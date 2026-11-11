<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Data;

use Sloukapetr\ComgateLaravelPackage\Enums\PaymentStatus;
use Sloukapetr\ComgateLaravelPackage\Exceptions\ComgateRequestException;

final class ComgatePaymentStatus
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly ?int $code,
        public readonly ?string $message,
        public readonly ?bool $test,
        public readonly ?int $price,
        public readonly ?string $currency,
        public readonly ?string $label,
        public readonly ?string $refId,
        public readonly ?string $method,
        public readonly ?string $email,
        public readonly string $transId,
        public readonly PaymentStatus $status,
        public readonly ?string $payerName,
        public readonly ?string $payerAccount,
        public readonly ?string $fee,
        public readonly ?string $variableSymbol,
        public readonly ?string $paymentErrorReason,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $transId = self::toString($payload['transId'] ?? null);
        $status = PaymentStatus::tryFrom((string) self::toString($payload['status'] ?? null));

        if ($transId === null || $transId === '' || $status === null) {
            throw new ComgateRequestException(
                'Comgate status response did not include a known transId and status.',
                0,
                self::encode($payload)
            );
        }

        return new self(
            payload: $payload,
            code: self::toInt($payload['code'] ?? null),
            message: self::toString($payload['message'] ?? null),
            test: self::toBool($payload['test'] ?? null),
            price: self::toInt($payload['price'] ?? null),
            currency: self::toString($payload['curr'] ?? null),
            label: self::toString($payload['label'] ?? null),
            refId: self::toString($payload['refId'] ?? null),
            method: self::toString($payload['method'] ?? null),
            email: self::toString($payload['email'] ?? null),
            transId: $transId,
            status: $status,
            payerName: self::toString($payload['payerName'] ?? null),
            payerAccount: self::toString($payload['payerAcc'] ?? null),
            fee: self::toString($payload['fee'] ?? null),
            variableSymbol: self::toString($payload['vs'] ?? null),
            paymentErrorReason: self::toString($payload['paymentErrorReason'] ?? null),
        );
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function isCancelled(): bool
    {
        return $this->status === PaymentStatus::Cancelled;
    }

    public function isAuthorized(): bool
    {
        return $this->status === PaymentStatus::Authorized;
    }

    public function matchesAmount(int $price, string $currency): bool
    {
        return $this->price === $price
            && strtoupper((string) $this->currency) === strtoupper($currency);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encode(array $payload): string
    {
        $encoded = json_encode($payload);

        return is_string($encoded) ? $encoded : '';
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
