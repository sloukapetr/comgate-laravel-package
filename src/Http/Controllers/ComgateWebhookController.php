<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sloukapetr\ComgateLaravelPackage\ComgateService;
use Sloukapetr\ComgateLaravelPackage\Data\ComgatePaymentStatus;
use Sloukapetr\ComgateLaravelPackage\Data\ComgateWebhookNotification;
use Sloukapetr\ComgateLaravelPackage\Enums\PaymentStatus;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentAuthorized;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentFailed;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentPaid;
use Sloukapetr\ComgateLaravelPackage\Events\ComgateWebhookReceived;
use Sloukapetr\ComgateLaravelPackage\Exceptions\ComgateRequestException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidWebhookSignatureException;
use Sloukapetr\ComgateLaravelPackage\Exceptions\PaymentNotFoundException;
use Symfony\Component\HttpFoundation\IpUtils;

use function config;
use function event;
use function response;

class ComgateWebhookController
{
    public function __invoke(Request $request, ComgateService $comgate): Response
    {
        if ($this->rejectsIp($request)) {
            return $this->plain('code=1&message=Forbidden', Response::HTTP_FORBIDDEN);
        }

        $payload = $request->all();

        try {
            $validatedPayload = $comgate->handleWebhook($payload, $request->header('X-Comgate-Secret'));
        } catch (InvalidWebhookSignatureException) {
            return $this->plain('code=1&message=Invalid+secret', Response::HTTP_UNAUTHORIZED);
        }

        $notification = ComgateWebhookNotification::fromArray($validatedPayload);
        $verifiedStatus = null;

        if ($this->shouldVerifyStatus($notification)) {
            try {
                $verifiedStatus = $this->clientForNotification($comgate, $notification)
                    ->findPayment((string) $notification->transId);
            } catch (PaymentNotFoundException) {
                Log::warning('Comgate webhook payment was not found in the status API.', [
                    'transId' => $notification->transId,
                ]);

                return $this->plain('code=0&message=OK', Response::HTTP_OK);
            } catch (ComgateRequestException $exception) {
                Log::warning('Comgate webhook status check failed.', [
                    'transId' => $notification->transId,
                    'statusCode' => $exception->statusCode(),
                ]);

                return $this->plain('code=1&message=Unavailable', Response::HTTP_SERVICE_UNAVAILABLE);
            }

            if ($notification->status !== $verifiedStatus->status->value) {
                Log::warning('Comgate webhook status differs from the status API.', [
                    'transId' => $notification->transId,
                    'notificationStatus' => $notification->status,
                    'verifiedStatus' => $verifiedStatus->status->value,
                ]);
            }
        }

        event(new ComgateWebhookReceived(
            $validatedPayload,
            $notification,
            $request,
            $verifiedStatus,
        ));

        $this->dispatchStatusEvent(
            $verifiedStatus?->status ?? $notification->paymentStatus(),
            $validatedPayload,
            $notification,
            $request,
            $verifiedStatus,
        );

        return $this->plain('code=0&message=OK', Response::HTTP_OK);
    }

    private function shouldVerifyStatus(ComgateWebhookNotification $notification): bool
    {
        return (bool) config('comgate.webhook.verify_status', true)
            && is_string($notification->transId)
            && $notification->transId !== '';
    }

    private function clientForNotification(ComgateService $comgate, ComgateWebhookNotification $notification): ComgateService
    {
        if (! is_string($notification->merchant) || $notification->merchant === '') {
            return $comgate;
        }

        return $comgate->forMerchant($notification->merchant);
    }

    private function rejectsIp(Request $request): bool
    {
        if (! (bool) config('comgate.webhook.verify_ip', false)) {
            return false;
        }

        $allowed = $this->allowedIps();

        if ($allowed === null) {
            return false;
        }

        $ip = (string) $request->ip();

        if ($ip !== '' && IpUtils::checkIp($ip, $allowed)) {
            return false;
        }

        Log::warning('Comgate webhook IP is not in the published list.', [
            'ip' => $ip,
        ]);

        return true;
    }

    /**
     * @return array<int, string>|null
     */
    private function allowedIps(): ?array
    {
        $cacheKey = 'comgate.webhook.allowed_ips';
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            return array_values(array_filter($cached, 'is_string'));
        }

        $url = (string) config('comgate.webhook.ips_url', 'https://payments.comgate.cz/ips-v4');

        try {
            $response = $this->ipListRequest()->get($url);
        } catch (ConnectionException) {
            $this->logUnavailableIpList();

            return null;
        }

        if (! $response->successful()) {
            $this->logUnavailableIpList();

            return null;
        }

        $ips = $this->parseIpList($response->body());

        if ($ips === []) {
            $this->logUnavailableIpList();

            return null;
        }

        Cache::put($cacheKey, $ips, now()->addHours(24));

        return $ips;
    }

    private function ipListRequest(): PendingRequest
    {
        $request = Http::timeout((int) config('comgate.timeout', 30));

        if (! (bool) config('comgate.force_ipv4', false)) {
            return $request;
        }

        return $request->withOptions([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function parseIpList(string $body): array
    {
        $ips = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $ips[] = $line;
        }

        return $ips;
    }

    private function logUnavailableIpList(): void
    {
        Log::warning('Comgate webhook IP list could not be downloaded.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchStatusEvent(
        ?PaymentStatus $status,
        array $payload,
        ComgateWebhookNotification $notification,
        Request $request,
        ?ComgatePaymentStatus $verifiedStatus,
    ): void {
        $event = match ($status) {
            PaymentStatus::Paid => new ComgatePaymentPaid($payload, $notification, $request, $verifiedStatus),
            PaymentStatus::Cancelled => new ComgatePaymentFailed($payload, $notification, $request, $verifiedStatus),
            PaymentStatus::Authorized => $verifiedStatus === null
                ? null
                : new ComgatePaymentAuthorized($payload, $notification, $request, $verifiedStatus),
            default => null,
        };

        if ($event !== null) {
            event($event);
        }
    }

    private function plain(string $body, int $status): Response
    {
        return response($body, $status)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
