<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Support;

use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidPaymentPayloadException;

final class PaymentAmount
{
    /**
     * Amounts are minor units (haléře, cents). Limits follow the Comgate create-payment rules.
     *
     * @var array<string, array{min: int, max: int|null, multiple: int}>
     */
    private const LIMITS = [
        'CZK' => ['min' => 100, 'max' => 100_000_000, 'multiple' => 1],
        'EUR' => ['min' => 10, 'max' => 4_000_000, 'multiple' => 1],
        'PLN' => ['min' => 100, 'max' => 17_000_000, 'multiple' => 1],
        'HUF' => ['min' => 10_000, 'max' => 1_250_000_000, 'multiple' => 100],
        'USD' => ['min' => 100, 'max' => 4_500_000, 'multiple' => 1],
        'GBP' => ['min' => 100, 'max' => 3_500_000, 'multiple' => 1],
        'RON' => ['min' => 500, 'max' => 19_000_000, 'multiple' => 1],
        'HRK' => ['min' => 100, 'max' => null, 'multiple' => 1],
        'NOK' => ['min' => 50, 'max' => 40_000_000, 'multiple' => 1],
        'SEK' => ['min' => 50, 'max' => 39_000_000, 'multiple' => 1],
    ];

    public static function assert(string $currency, int $amount, bool $recurring = false): void
    {
        $currency = strtoupper($currency);
        $limits = self::LIMITS[$currency] ?? null;

        if ($limits === null) {
            throw new InvalidPaymentPayloadException(sprintf('Currency [%s] is not supported by Comgate.', $currency));
        }

        if ($amount < 1) {
            throw new InvalidPaymentPayloadException('Price must be a positive amount in minor units.');
        }

        $minimum = $limits['min'];

        if ($recurring && $currency === 'CZK') {
            $minimum = 1_000;
        }

        if ($amount < $minimum) {
            throw new InvalidPaymentPayloadException(sprintf(
                'Price %d %s is below the Comgate minimum of %d minor units.',
                $amount,
                $currency,
                $minimum
            ));
        }

        if (! $recurring && $limits['max'] !== null && $amount > $limits['max']) {
            throw new InvalidPaymentPayloadException(sprintf(
                'Price %d %s is above the Comgate maximum of %d minor units.',
                $amount,
                $currency,
                $limits['max']
            ));
        }

        if ($limits['multiple'] > 1 && $amount % $limits['multiple'] !== 0) {
            throw new InvalidPaymentPayloadException(sprintf(
                'Price for %s must be divisible by %d.',
                $currency,
                $limits['multiple']
            ));
        }
    }

    public static function assertRefund(string $currency, int $amount): void
    {
        $currency = strtoupper($currency);
        $limits = self::LIMITS[$currency] ?? null;

        if ($limits === null) {
            throw new InvalidPaymentPayloadException(sprintf('Currency [%s] is not supported by Comgate.', $currency));
        }

        if ($amount < 1) {
            throw new InvalidPaymentPayloadException('Refund amount must be a positive number of minor units.');
        }

        if ($limits['multiple'] > 1 && $amount % $limits['multiple'] !== 0) {
            throw new InvalidPaymentPayloadException(sprintf(
                'Refund amount for %s must be divisible by %d.',
                $currency,
                $limits['multiple']
            ));
        }
    }
}
