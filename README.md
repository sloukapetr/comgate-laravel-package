# comgate-laravel-package

Clean, reusable Laravel package for Comgate Merchant REST API v2.0 integration with Laravel 11, 12 and 13.

## Features

- One-off payments (`POST /v2.0/payment.json`)
- Recurring payments (`initRecurring=true`, `POST /v2.0/payment.json`, `POST /v2.0/recurring.json`)
- Pre-authorization capture/cancel (`PUT /v2.0/preauth/transId/{transId}.json`, `DELETE /v2.0/preauth/transId/{transId}.json`)
- Refunds and payment cancellations (`POST /v2.0/refund.json`, `DELETE /v2.0/payment/transId/{transId}.json`)
- Payment status verification (`GET /v2.0/payment/transId/{transId}.json`)
- Payment method discovery (`GET /v2.0/method.json`)
- Comgate Status API integration (`GET https://status.comgate.cz/health`, `GET https://status.comgate.cz/outages`)
- Aggregated payment availability feedback for gateway health, allowed methods and outages
- Typed availability report and guarded payment creation helper
- HTTP Basic authentication with merchant ID and secret
- Webhook endpoint with secret verification and required `code=0&message=OK` response
- Operation-specific exceptions for common Comgate API failures

## Installation

```bash
composer require sloukapetr/comgate-laravel-package
```

For local package linking:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../comgate-laravel-package"
    }
  ]
}
```

Laravel package discovery is enabled automatically.

## Compatibility

- PHP: `^8.2`
- Laravel: `11`, `12`, `13`
- Package test matrix: `orchestra/testbench:^9|^10|^11`

The Testbench range is intentional. It keeps package development aligned with the corresponding Laravel major versions instead of pinning local tests to only one framework branch.

## Configuration

Publish the package config:

```bash
php artisan vendor:publish --tag=comgate-config
```

Configure `.env` values:

```dotenv
COMGATE_MERCHANT_ID=...
COMGATE_SECRET=...
COMGATE_TEST=true
COMGATE_BASE_URL=https://payments.comgate.cz/v2.0
COMGATE_TIMEOUT=30
COMGATE_STATUS_BASE_URL=https://status.comgate.cz
COMGATE_STATUS_TIMEOUT=30
COMGATE_WEBHOOK_ENABLED=true
COMGATE_WEBHOOK_PATH=/comgate/webhook
COMGATE_WEBHOOK_ROUTE_NAME=comgate.webhook
```

## Usage

```php
use Sloukapetr\ComgateLaravelPackage\ComgateService;

$comgate = app(ComgateService::class);

$response = $comgate->createPayment([
    'price' => 1000,
    'curr' => 'CZK',
    'label' => 'ORDER-1001',
    'refId' => '1001',
    'method' => 'ALL',
]);

$redirectUrl = $comgate->redirectUrl($response);
```

### Initial recurring payment

```php
$init = $comgate->createInitialRecurringPayment([
    'price' => 1000,
    'curr' => 'CZK',
    'label' => 'SUBSCRIPTION-INIT',
    'refId' => 'SUB-42',
    'method' => 'CARD_CZ_CS',
]);
```

### Subsequent recurring charge

```php
$charge = $comgate->recurringPayment([
    'initRecurringId' => $init['transId'] ?? null,
    'price' => 1000,
    'curr' => 'CZK',
    'label' => 'SUBSCRIPTION-RENEW',
    'refId' => 'SUB-42-2026-09',
]);
```

For backward compatibility, the service also accepts `initTransId` and maps it to `initRecurringId`.

### Pre-authorization

```php
$preauth = $comgate->createPayment([
    'price' => 1500,
    'curr' => 'CZK',
    'preauth' => true,
    'label' => 'PREAUTH-ORDER-1',
]);

$capture = $comgate->capture([
    'transId' => $preauth['transId'] ?? null,
]);

$cancel = $comgate->cancelPreauth([
    'transId' => $preauth['transId'] ?? null,
]);
```

### Refund, cancellation and status

```php
$refund = $comgate->refund([
    'transId' => 'YOUR_TRANSACTION_ID',
    'amount' => 500,
]);

$cancelPayment = $comgate->cancelPayment([
    'transId' => 'YOUR_PENDING_TRANSACTION_ID',
]);

$status = $comgate->status([
    'transId' => 'YOUR_TRANSACTION_ID',
]);
```

### Available payment methods

```php
$methods = $comgate->methods([
    'country' => 'CZ',
    'curr' => 'CZK',
    'price' => 1000,
    'lang' => 'cs',
    'initRecurring' => true,
]);
```

### Gateway health and outages

```php
$health = $comgate->gatewayHealth();
$outages = $comgate->outages();
```

### Payment availability feedback

Use this when you want a higher-level answer than raw `/methods` or raw status data.

```php
$availability = $comgate->paymentAvailability(
    [
        'country' => 'CZ',
        'curr' => 'CZK',
        'price' => 1000,
        'initRecurring' => true,
    ],
    ['CARD_CZ_CSOB_2', 'BANK_CZ_KB']
);

if (! $availability['available']) {
    $summary = $availability['summary'];
    $reasons = $availability['reasons'];
    $usableMethods = $availability['usableMethods'];
}
```

Returned report includes:

- `available`: final yes/no decision
- `summary`: short human-readable outcome
- `gateway`: raw health payload from Status API
- `availableMethods`: methods allowed by Merchant API filters
- `usableMethods`: methods still usable after outage filtering
- `missingRequiredMethods`: requested methods not currently available for the provided filters
- `outages`: only outages relevant to the checked methods
- `checks` and `reasons`: machine-friendly and human-friendly diagnostics

For stricter application flow, you can use the typed report or block payment creation up front.

```php
$report = $comgate->paymentAvailabilityReport(
    ['country' => 'CZ', 'curr' => 'CZK', 'price' => 1000],
    ['CARD_CZ_CSOB_2']
);

if (! $report->isAvailable()) {
    $summaryKey = $report->summaryKey;
    $localizedSummary = $report->summaryFor('cs');
    $reasonCodes = $report->reasonCodes;
    $reasonMessages = $report->reasonMessages('cs');
}
```

```php
use Sloukapetr\ComgateLaravelPackage\Exceptions\PaymentUnavailableException;

try {
    $response = $comgate->createPaymentChecked([
        'country' => 'CZ',
        'price' => 1000,
        'curr' => 'CZK',
        'label' => 'ORDER-1001',
        'refId' => '1001',
        'method' => 'CARD_CZ_CSOB_2',
    ]);
} catch (PaymentUnavailableException $exception) {
    $availability = $exception->availability();
}
```

`cancel()` remains available as a backward-compatible alias for pre-authorization cancellation.

## Exceptions

The package throws `ComgateRequestException` by default and narrows a few common API cases into dedicated exceptions:

- `MissingParameterException` for API code `1400` on create, recurring, refund and preauth calls
- `PaymentNotFoundException` for API code `1400` on status lookups
- `PreauthFailedException` for API code `1401` on pre-authorization capture/cancel calls

## Subscription management

Comgate handles the payment tokenization flow for recurring card charges, but the subscription lifecycle remains in your Laravel application.

Typical Laravel design:

- store the initial recurring `transId` per user after the first successful payment
- schedule renewals in your app and call `recurringPayment()` with a new `refId`
- process webhook updates to mark payments as paid, cancelled or failed
- issue `refund()` only for paid transactions and `cancelPayment()` only for pending transactions

## Webhooks

When enabled, the package registers a `POST` webhook route (`/comgate/webhook` by default).

- Secret is validated from `X-Comgate-Secret` header or `secret` payload field.
- On success, the package dispatches `Sloukapetr\ComgateLaravelPackage\Events\ComgateWebhookReceived`.
- The event exposes both raw payload and a typed `notification` DTO with helpers like `isPaid()` and `isCancelled()`, plus typed status fields such as `cardNumber`, `appliedFeeType`, `threeDSPreference` and `threeDSApplied`.
- Response is always `code=0&message=OK` for valid webhook calls.

```php
use Sloukapetr\ComgateLaravelPackage\Events\ComgateWebhookReceived;

class HandleComgateWebhook
{
    public function handle(ComgateWebhookReceived $event): void
    {
        if ($event->notification->isPaid()) {
            $transactionId = $event->notification->transId;
            $referenceId = $event->notification->refId;

            // mark order or subscription renewal as paid
        }
    }
}
```

## Facade

```php
use Comgate;

$response = Comgate::status(['transId' => 'YOUR_TRANSACTION_ID']);
```

## Official API Reference

- https://apidoc.comgate.cz/en/api/rest/

## Testing

```bash
composer test
```

The package includes HTTP-layer tests using Laravel `Http::fake()` and Testbench for webhook route coverage.

## Release

See [RELEASE_CHECKLIST.md](c:/Users/slouka/comgate-laravel-package/RELEASE_CHECKLIST.md) for the package release checklist.
