<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Support;

use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidPaymentPayloadException;

final class PaymentLabel
{
    public const MAX_LENGTH = 16;

    public static function fromProduct(string $product): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $product));

        if ($normalized === '') {
            throw new InvalidPaymentPayloadException('Product name cannot be empty.');
        }

        if (mb_strlen($normalized) <= self::MAX_LENGTH) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, self::MAX_LENGTH));
    }
}
