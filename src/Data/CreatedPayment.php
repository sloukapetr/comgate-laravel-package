<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Data;

use Sloukapetr\ComgateLaravelPackage\Exceptions\ComgateRequestException;

final class CreatedPayment
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $transId,
        public readonly ?string $redirect,
        public readonly int $code,
        public readonly string $message,
        public readonly array $payload,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $transId = $payload['transId'] ?? null;

        if (! is_string($transId) || trim($transId) === '') {
            throw new ComgateRequestException('Comgate response did not include transId.', 0, self::encode($payload));
        }

        $redirect = $payload['redirect'] ?? $payload['redirectUrl'] ?? $payload['url'] ?? null;

        return new self(
            transId: $transId,
            redirect: is_string($redirect) && $redirect !== '' ? $redirect : null,
            code: is_numeric($payload['code'] ?? null) ? (int) $payload['code'] : 0,
            message: is_string($payload['message'] ?? null) ? $payload['message'] : 'OK',
            payload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encode(array $payload): string
    {
        $encoded = json_encode($payload);

        return is_string($encoded) ? $encoded : '';
    }
}
