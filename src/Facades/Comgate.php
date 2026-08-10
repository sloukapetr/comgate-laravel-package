<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Facades;

use Illuminate\Support\Facades\Facade;
use Sloukapetr\ComgateLaravelPackage\ComgateService;

/**
 * @method static array<string, mixed> createPayment(array<string, mixed> $payload)
 * @method static array<string, mixed> createPaymentChecked(array<string, mixed> $payload, array<int, string> $requiredMethods = [])
 * @method static array<string, mixed> recurringPayment(array<string, mixed> $payload)
 * @method static array<string, mixed> capture(array<string, mixed> $payload)
 * @method static array<string, mixed> cancel(array<string, mixed> $payload)
 * @method static array<string, mixed> cancelPayment(array<string, mixed> $payload)
 * @method static array<string, mixed> cancelPreauth(array<string, mixed> $payload)
 * @method static array<string, mixed> refund(array<string, mixed> $payload)
 * @method static array<string, mixed> status(array<string, mixed> $payload)
 * @method static array<string, mixed> methods(array<string, scalar|null> $filters = [])
 * @method static array<string, mixed> gatewayHealth()
 * @method static array<int, array<string, mixed>> outages()
 * @method static array<string, mixed> paymentAvailability(array<string, scalar|null> $filters = [], array<int, string> $requiredMethods = [])
 * @method static \Sloukapetr\ComgateLaravelPackage\Data\ComgatePaymentAvailability paymentAvailabilityReport(array<string, scalar|null> $filters = [], array<int, string> $requiredMethods = [])
 * @method static array<string, mixed> createInitialRecurringPayment(array<string, mixed> $payload)
 * @method static array<string, mixed> handleWebhook(array<string, mixed> $payload, ?string $providedSecret = null)
 * @method static bool verifyWebhookSignature(array<string, mixed> $payload, ?string $providedSecret = null)
 * @method static string|null redirectUrl(array<string, mixed> $response)
 *
 * @see ComgateService
 */
class Comgate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ComgateService::class;
    }
}
