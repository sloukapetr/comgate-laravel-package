<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Facades;

use Illuminate\Support\Facades\Facade;
use Sloukapetr\ComgateLaravelPackage\ComgateService;
use Sloukapetr\ComgateLaravelPackage\Fakes\ComgateFake;

/**
 * @method static array<string, mixed> createPayment(array<string, mixed> $payload)
 * @method static array<string, mixed> paymentRedirect(array<string, mixed> $payload)
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
 * @method static array<int, array<string, mixed>> transferList(string $date, bool $test = false)
 * @method static array<string, mixed> singleTransfer(string $transferId, bool $test = false)
 * @method static array<string, mixed> csvSingleTransfer(string $transferId, bool $download = false, bool $test = false)
 * @method static array<string, mixed> aboSingleTransfer(string $transferId, bool $download = false, string $type = 'v1', string $encoding = 'utf8', bool $test = false)
 * @method static array<string, mixed> csvDownload(string $date, bool $test = false)
 * @method static array<string, mixed> aboDownload(string $date, string $type = 'v1', string $encoding = 'utf8', bool $test = false)
 * @method static array<string, mixed> refundPos(array<string, mixed> $payload)
 * @method static array<string, mixed> config(array<string, mixed> $payload)
 * @method static array<string, mixed> appleDomainAssociation(?string $currency = null)
 * @method static array<string, mixed> createInitialRecurringPayment(array<string, mixed> $payload)
 * @method static array<string, mixed> handleWebhook(array<string, mixed> $payload, ?string $providedSecret = null)
 * @method static bool verifyWebhookSignature(array<string, mixed> $payload, ?string $providedSecret = null)
 * @method static string|null redirectUrl(array<string, mixed> $response)
 * @method static ComgateFake fake(array<string, array<string, mixed>> $responses = [])
 *
 * @see ComgateService
 */
class Comgate extends Facade
{
    public static function fake(array $responses = []): ComgateFake
    {
        $fake = new ComgateFake($responses);

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ComgateService::class;
    }
}
