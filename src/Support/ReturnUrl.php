<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Support;

use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidPaymentPayloadException;

final class ReturnUrl
{
    /**
     * Comgate replaces ${id} and ${refId} on the return URL. The placeholders must stay literal.
     */
    public static function withPlaceholders(string $absoluteUrl): string
    {
        $absoluteUrl = trim($absoluteUrl);

        if ($absoluteUrl === '' || filter_var($absoluteUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidPaymentPayloadException('Return URL must be an absolute URL.');
        }

        if (str_contains($absoluteUrl, '${id}') || str_contains($absoluteUrl, '${refId}')) {
            return $absoluteUrl;
        }

        $separator = str_contains($absoluteUrl, '?') ? '&' : '?';

        return $absoluteUrl.$separator.'id=${id}&refId=${refId}';
    }
}
