<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Sloukapetr\ComgateLaravelPackage\Exceptions\ComgateRequestException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidWebhookSignatureException;

class ComgateService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload): array
    {
        return $this->request('create', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function recurringPayment(array $payload): array
    {
        return $this->request('recurringPayment', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function capture(array $payload): array
    {
        return $this->request('capture', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancel(array $payload): array
    {
        return $this->request('cancel', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refund(array $payload): array
    {
        return $this->request('refund', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function status(array $payload): array
    {
        return $this->request('status', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createInitialRecurringPayment(array $payload): array
    {
        $payload['initRecurring'] = true;

        return $this->createPayment($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string|null  $providedSecret
     * @return array<string, mixed>
     */
    public function handleWebhook(array $payload, ?string $providedSecret = null): array
    {
        if (! $this->verifyWebhookSignature($payload, $providedSecret)) {
            throw new InvalidWebhookSignatureException('Invalid Comgate webhook secret.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload, ?string $providedSecret = null): bool
    {
        $expected = (string) config('comgate.secret', '');
        $actual = $providedSecret ?? (isset($payload['secret']) ? (string) $payload['secret'] : '');

        if ($expected === '' || $actual === '') {
            return false;
        }

        return hash_equals($expected, $actual);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function redirectUrl(array $response): ?string
    {
        foreach (['redirect', 'redirectUrl', 'url'] as $field) {
            if (isset($response[$field]) && is_string($response[$field])) {
                return $response[$field];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $endpoint, array $payload): array
    {
        $requestPayload = array_merge($this->credentials(), $payload);

        if (! array_key_exists('test', $requestPayload)) {
            $requestPayload['test'] = (bool) config('comgate.test', true);
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout((int) config('comgate.timeout', 30))
            ->post($this->url($endpoint), $requestPayload);

        return $this->parseResponse($response);
    }

    /**
     * @return array{merchant: string, secret: string}
     */
    protected function credentials(): array
    {
        return [
            'merchant' => (string) config('comgate.merchant_id', ''),
            'secret' => (string) config('comgate.secret', ''),
        ];
    }

    protected function url(string $endpoint): string
    {
        return rtrim((string) config('comgate.base_url', ''), '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseResponse(Response $response): array
    {
        if (! $response->successful()) {
            throw new ComgateRequestException(
                sprintf('Comgate request failed with HTTP status %d.', $response->status()),
                $response->status(),
                $response->body()
            );
        }

        $decoded = $response->json();
        if (is_array($decoded)) {
            return $decoded;
        }

        parse_str($response->body(), $parsed);
        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }

        return ['raw' => $response->body()];
    }
}
