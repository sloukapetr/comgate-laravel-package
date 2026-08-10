<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage;

use Sloukapetr\ComgateLaravelPackage\Data\ComgatePaymentAvailability;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Sloukapetr\ComgateLaravelPackage\Exceptions\ComgateRequestException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidWebhookSignatureException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\MissingParameterException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\PaymentNotFoundException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\PaymentUnavailableException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\PreauthFailedException;

use function config;

class ComgateService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload): array
    {
        return $this->request('createPayment', 'POST', 'payment.json', $this->withDefaultTestFlag($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $requiredMethods
     * @return array<string, mixed>
     */
    public function createPaymentChecked(array $payload, array $requiredMethods = []): array
    {
        $availability = $this->paymentAvailabilityReport(
            $this->extractAvailabilityFilters($payload),
            $requiredMethods !== [] ? $requiredMethods : $this->extractRequiredMethodsFromPayload($payload)
        );

        if (! $availability->isAvailable()) {
            throw new PaymentUnavailableException(
                $availability->summary,
                0,
                '',
                $availability
            );
        }

        return $this->createPayment($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function recurringPayment(array $payload): array
    {
        if (isset($payload['initTransId']) && ! isset($payload['initRecurringId'])) {
            $payload['initRecurringId'] = $payload['initTransId'];
            unset($payload['initTransId']);
        }

        return $this->request('recurringPayment', 'POST', 'recurring.json', $this->withDefaultTestFlag($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function capture(array $payload): array
    {
        $transId = $this->requireTransId($payload);

        return $this->request('capturePreauth', 'PUT', sprintf('preauth/transId/%s.json', rawurlencode($transId)), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancel(array $payload): array
    {
        return $this->cancelPreauth($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancelPayment(array $payload): array
    {
        $transId = $this->requireTransId($payload);

        return $this->request('cancelPayment', 'DELETE', sprintf('payment/transId/%s.json', rawurlencode($transId)), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancelPreauth(array $payload): array
    {
        $transId = $this->requireTransId($payload);

        return $this->request('cancelPreauth', 'DELETE', sprintf('preauth/transId/%s.json', rawurlencode($transId)), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refund(array $payload): array
    {
        return $this->request('refund', 'POST', 'refund.json', $this->withDefaultTestFlag($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function status(array $payload): array
    {
        $transId = $this->requireTransId($payload);

        return $this->request('status', 'GET', sprintf('payment/transId/%s.json', rawurlencode($transId)));
    }

    /**
     * @param  array<string, scalar|null>  $filters
     * @return array<string, mixed>
     */
    public function methods(array $filters = []): array
    {
        return $this->request('methods', 'GET', 'method.json', [], $this->withoutNulls($filters));
    }

    /**
     * @return array<string, mixed>
     */
    public function gatewayHealth(): array
    {
        return $this->statusRequest('gatewayHealth', 'health');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function outages(): array
    {
        $response = $this->statusRequest('outages', 'outages');

        return array_values(array_filter($response, 'is_array'));
    }

    /**
     * @param  array<string, scalar|null>  $filters
     * @param  array<int, string>  $requiredMethods
     * @return array<string, mixed>
     */
    public function paymentAvailability(array $filters = [], array $requiredMethods = []): array
    {
        return $this->paymentAvailabilityReport($filters, $requiredMethods)->toArray();
    }

    /**
     * @param  array<string, scalar|null>  $filters
     * @param  array<int, string>  $requiredMethods
     */
    public function paymentAvailabilityReport(array $filters = [], array $requiredMethods = []): ComgatePaymentAvailability
    {
        $sanitizedFilters = $this->withoutNulls($filters);
        $methodsResponse = $this->methods($sanitizedFilters);
        $availableMethods = $this->extractMethodIds($methodsResponse);
        $normalizedRequiredMethods = $this->normalizeMethods($requiredMethods);
        $health = $this->gatewayHealth();
        $outages = $this->outages();

        $requestedMethods = $normalizedRequiredMethods !== [] ? $normalizedRequiredMethods : $availableMethods;
        $affectedOutages = $this->filterOutagesByMethods($outages, $requestedMethods);
        $affectedMethodIds = $this->extractOutageMethodIds($affectedOutages);
        $usableMethods = array_values(array_diff($availableMethods, $affectedMethodIds));
        $missingRequiredMethods = array_values(array_diff($normalizedRequiredMethods, $availableMethods));
        $usableRequiredMethods = $normalizedRequiredMethods === []
            ? $usableMethods
            : array_values(array_diff(array_values(array_intersect($normalizedRequiredMethods, $availableMethods)), $affectedMethodIds));

        $gatewayOk = ($health['gateway'] ?? null) === 'OK';
        $hasConfiguredMethods = $availableMethods !== [];
        $hasUsableMethod = $normalizedRequiredMethods === []
            ? $usableMethods !== []
            : $usableRequiredMethods !== [];
        $available = $gatewayOk && $hasConfiguredMethods && $hasUsableMethod;
        $reasonCodes = $this->buildAvailabilityReasonCodes(
            $gatewayOk,
            $hasConfiguredMethods,
            $normalizedRequiredMethods,
            $missingRequiredMethods,
            $usableMethods,
            $usableRequiredMethods,
            $affectedOutages
        );
        $summaryKey = $this->buildAvailabilitySummaryKey($available, $gatewayOk, $reasonCodes);

        return new ComgatePaymentAvailability(
            available: $available,
            summary: $this->translateAvailabilityKey($summaryKey, 'en'),
            summaryKey: $summaryKey,
            gateway: $health,
            filters: $sanitizedFilters,
            requiredMethods: $normalizedRequiredMethods,
            availableMethods: $availableMethods,
            usableMethods: $usableMethods,
            usableRequiredMethods: $usableRequiredMethods,
            missingRequiredMethods: $missingRequiredMethods,
            outages: $affectedOutages,
            reasonCodes: $reasonCodes,
            checks: [
                'gatewayOk' => $gatewayOk,
                'hasConfiguredMethods' => $hasConfiguredMethods,
                'hasUsableMethod' => $hasUsableMethod,
            ],
        );
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
    protected function request(string $operation, string $method, string $path, array $payload = [], array $query = []): array
    {
        $request = $this->http()
            ->acceptJson();

        $response = match (strtoupper($method)) {
            'GET' => $request->get($this->url($path), $query),
            'POST' => $request->post($this->url($path), $payload),
            'PUT' => $request->put($this->url($path), $this->withoutTransId($payload)),
            'DELETE' => $request->delete($this->url($path)),
            default => throw new ComgateRequestException(
                sprintf('Unsupported Comgate HTTP method [%s].', $method),
                0,
                ''
            ),
        };

        return $this->parseResponse($operation, $response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function statusRequest(string $operation, string $path, array $query = []): array
    {
        $response = $this->statusHttp()
            ->acceptJson()
            ->get($this->statusUrl($path), $query);

        return $this->parseResponse($operation, $response);
    }

    protected function http(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) config('comgate.merchant_id', ''),
            (string) config('comgate.secret', '')
        )
            ->asJson()
            ->timeout((int) config('comgate.timeout', 30));
    }

    protected function statusHttp(): PendingRequest
    {
        return Http::timeout((int) config('comgate.status.timeout', (int) config('comgate.timeout', 30)));
    }

    protected function url(string $endpoint): string
    {
        return rtrim((string) config('comgate.base_url', ''), '/').'/'.ltrim($endpoint, '/');
    }

    protected function statusUrl(string $endpoint): string
    {
        return rtrim((string) config('comgate.status.base_url', 'https://status.comgate.cz'), '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseResponse(string $operation, Response $response): array
    {
        if ($response->status() === 204 || trim($response->body()) === '') {
            return [];
        }

        $decoded = $response->json();
        if (is_array($decoded)) {
            $this->guardAgainstApiError($operation, $decoded, $response);

            if (! $response->successful()) {
                throw $this->makeTransportException($response);
            }

            return $decoded;
        }

        parse_str($response->body(), $parsed);
        if (is_array($parsed) && $parsed !== []) {
            $this->guardAgainstApiError($operation, $parsed, $response);

            if (! $response->successful()) {
                throw $this->makeTransportException($response);
            }

            return $parsed;
        }

        if (! $response->successful()) {
            throw $this->makeTransportException($response);
        }

        return ['raw' => $response->body()];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function requireTransId(array $payload): string
    {
        $transId = $payload['transId'] ?? null;

        if (! is_string($transId) || trim($transId) === '') {
            throw new ComgateRequestException('Comgate request requires a non-empty transId.', 0, '');
        }

        return $transId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withoutTransId(array $payload): array
    {
        unset($payload['transId']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withDefaultTestFlag(array $payload): array
    {
        if (! array_key_exists('test', $payload)) {
            $payload['test'] = (bool) config('comgate.test', true);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function guardAgainstApiError(string $operation, array $payload, Response $response): void
    {
        $code = $payload['code'] ?? null;

        if (! is_numeric($code) || (int) $code === 0) {
            return;
        }

        $message = isset($payload['message']) && is_string($payload['message'])
            ? $payload['message']
            : 'Comgate API returned an error.';

        throw $this->mapApiException($operation, (int) $code, $message, $response);
    }

    /**
     * @param  array<string, scalar|null>  $payload
     * @return array<string, scalar>
     */
    protected function withoutNulls(array $payload): array
    {
        return array_filter($payload, static fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $methodsResponse
     * @return array<int, string>
     */
    protected function extractMethodIds(array $methodsResponse): array
    {
        $methods = $methodsResponse['methods'] ?? [];

        if (! is_array($methods)) {
            return [];
        }

        $ids = [];

        foreach ($methods as $method) {
            if (is_array($method) && isset($method['id']) && is_string($method['id']) && trim($method['id']) !== '') {
                $ids[] = $method['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, array<string, mixed>>  $outages
     * @param  array<int, string>  $methods
     * @return array<int, array<string, mixed>>
     */
    protected function filterOutagesByMethods(array $outages, array $methods): array
    {
        if ($methods === []) {
            return [];
        }

        return array_values(array_filter($outages, function (array $outage) use ($methods): bool {
            $outageMethods = $outage['methods'] ?? [];

            if (! is_array($outageMethods)) {
                return false;
            }

            return array_intersect($this->normalizeMethods($outageMethods), $methods) !== [];
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $outages
     * @return array<int, string>
     */
    protected function extractOutageMethodIds(array $outages): array
    {
        $methodIds = [];

        foreach ($outages as $outage) {
            $outageMethods = $outage['methods'] ?? [];

            if (! is_array($outageMethods)) {
                continue;
            }

            array_push($methodIds, ...$this->normalizeMethods($outageMethods));
        }

        return array_values(array_unique($methodIds));
    }

    /**
     * @param  array<int, mixed>  $methods
     * @return array<int, string>
     */
    protected function normalizeMethods(array $methods): array
    {
        $normalized = [];

        foreach ($methods as $method) {
            if (is_string($method) && trim($method) !== '') {
                $normalized[] = $method;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, string>  $requiredMethods
     * @param  array<int, string>  $missingRequiredMethods
     * @param  array<int, string>  $usableMethods
     * @param  array<int, string>  $usableRequiredMethods
     * @param  array<int, array<string, mixed>>  $affectedOutages
     * @return array<int, string>
     */
    protected function buildAvailabilityReasonCodes(
        bool $gatewayOk,
        bool $hasConfiguredMethods,
        array $requiredMethods,
        array $missingRequiredMethods,
        array $usableMethods,
        array $usableRequiredMethods,
        array $affectedOutages
    ): array {
        $reasonCodes = [];

        if (! $gatewayOk) {
            $reasonCodes[] = 'availability.gateway_outage';
        }

        if (! $hasConfiguredMethods) {
            $reasonCodes[] = 'availability.no_methods';
        }

        if ($requiredMethods !== [] && $missingRequiredMethods !== []) {
            $reasonCodes[] = 'availability.required_methods_missing';
        }

        if ($affectedOutages !== [] && $requiredMethods === [] && $usableMethods === []) {
            $reasonCodes[] = 'availability.all_methods_outage';
        }

        if ($affectedOutages !== [] && $requiredMethods !== [] && $usableRequiredMethods === []) {
            $reasonCodes[] = 'availability.required_methods_outage';
        }

        return array_values(array_unique($reasonCodes));
    }

    /**
     * @param  array<int, string>  $reasonCodes
     */
    protected function buildAvailabilitySummaryKey(bool $available, bool $gatewayOk, array $reasonCodes): string
    {
        if ($available) {
            return 'availability.ok';
        }

        if (! $gatewayOk) {
            return 'availability.gateway_outage';
        }

        if ($reasonCodes !== []) {
            return $reasonCodes[0];
        }

        return 'availability.unavailable';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, scalar|null>
     */
    protected function extractAvailabilityFilters(array $payload): array
    {
        $keys = [
            'country',
            'curr',
            'price',
            'lang',
            'initRecurring',
            'verification',
            'preauth',
            'expirationTime',
            'embedded',
            'chargeUnregulatedCardFees',
            'enableApplePayGooglePay',
            'threeDSPreference',
        ];

        $filters = [];

        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_scalar($value) || $value === null) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function extractRequiredMethodsFromPayload(array $payload): array
    {
        $method = $payload['method'] ?? null;

        if (! is_string($method) || trim($method) === '' || $method === 'ALL') {
            return [];
        }

        preg_match_all('/[A-Z0-9_]+/', $method, $matches);

        return $this->normalizeMethods($matches[0] ?? []);
    }

    protected function translateAvailabilityKey(string $key, string $locale): string
    {
        return (new ComgatePaymentAvailability(
            available: false,
            summary: '',
            summaryKey: '',
            gateway: [],
            filters: [],
            requiredMethods: [],
            availableMethods: [],
            usableMethods: [],
            usableRequiredMethods: [],
            missingRequiredMethods: [],
            outages: [],
            reasonCodes: [],
            checks: [],
        ))->summaryFor($locale === 'cs' ? 'cs' : 'en') === '' ? $key : (function () use ($key, $locale): string {
            $dummy = new ComgatePaymentAvailability(false, '', $key, [], [], [], [], [], [], [], [], [], []);
            return $dummy->summaryFor($locale);
        })();
    }

    protected function makeTransportException(Response $response): ComgateRequestException
    {
        return new ComgateRequestException(
            sprintf('Comgate request failed with HTTP status %d.', $response->status()),
            $response->status(),
            $response->body()
        );
    }

    protected function mapApiException(string $operation, int $apiCode, string $message, Response $response): ComgateRequestException
    {
        $exceptionClass = match ($operation) {
            'status' => $apiCode === 1400 ? PaymentNotFoundException::class : ComgateRequestException::class,
            'createPayment', 'recurringPayment', 'refund' => $apiCode === 1400 ? MissingParameterException::class : ComgateRequestException::class,
            'capturePreauth', 'cancelPreauth' => match ($apiCode) {
                1400 => MissingParameterException::class,
                1401 => PreauthFailedException::class,
                default => ComgateRequestException::class,
            },
            default => ComgateRequestException::class,
        };

        return new $exceptionClass(
            sprintf('Comgate API error %d on %s: %s', $apiCode, $operation, $message),
            $response->status(),
            $response->body(),
            $apiCode
        );
    }
}
