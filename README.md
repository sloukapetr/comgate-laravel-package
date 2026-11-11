# comgate-laravel-package

Clean, reusable Laravel package for Comgate Merchant REST API v2.0 integration with Laravel 11, 12 and 13.

## Features

- One-off payments (`POST /v2.0/payment.json`)
- Typed checkout for digital goods: validated price, 16-character label, contact, and return URLs (`CreatePayment`, `createCheckout()`)
- Redirect-based one-off payments (`POST /v2.0/paymentRedirect/merchant/{merchant_id}`)
- Recurring payments (`initRecurring=true`, `POST /v2.0/payment.json`, `POST /v2.0/recurring.json`)
- Pre-authorization capture/cancel (`PUT /v2.0/preauth/transId/{transId}.json`, `DELETE /v2.0/preauth/transId/{transId}.json`)
- Refunds and payment cancellations (`POST /v2.0/refund.json`, `DELETE /v2.0/payment/transId/{transId}.json`)
- Payment status verification (`GET /v2.0/payment/transId/{transId}.json`)
- Payment method discovery (`GET /v2.0/method.json`)
- Transfer reporting and payout export helpers (`GET /v2.0/transferList/date/{date}.json`, `GET /v2.0/singleTransfer/transferId/{transferId}.json`, `GET /v2.0/csvSingleTransfer/transferId/{transferId}.json`, `GET /v2.0/aboSingleTransfer/transferId/{transferId}.json`, `GET /v2.0/csvDownload/date/{date}`, `GET /v2.0/aboDownload/date/{date}`)
- Extra Merchant API helpers for `refundPos`, `config` and Apple Pay domain association
- Comgate Status API integration (`GET https://status.comgate.cz/health`, `GET https://status.comgate.cz/outages`)
- Aggregated payment availability feedback for gateway health, allowed methods and outages
- Typed availability report and guarded payment creation helper
- Typed webhook notification DTO and payment-state events for `PAID` and `CANCELLED`
- HTTP Basic authentication with merchant ID and secret
- Webhook endpoint with secret verification and required `code=0&message=OK` response
- `Comgate::fake()` test double with recorded calls and configurable responses
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
COMGATE_COUNTRY=CZ
COMGATE_CURRENCY=CZK
COMGATE_LANG=cs
COMGATE_METHOD=ALL
COMGATE_CATEGORY=OTHER
COMGATE_DELIVERY=ELECTRONIC_DELIVERY
```

## Recommended Laravel Use Cases

This package is a good fit when your Laravel app needs to own the payment lifecycle, not just send the customer to Comgate.

- Classic e-shop checkout with one-off payments and status tracking.
- Subscription or membership billing with an initial payment followed by recurring charges.
- Reservation or deposit flows where you first authorize a card payment and later capture or cancel it.
- Admin back office for refunds, cancellations, payment lookup and payout reconciliation.
- Internal payment links or order-payment screens where a merchant wants to control the business workflow in Laravel.

Typical pattern in the app:

- create an internal payment record first
- call Comgate to create the external payment
- store `transId`, `refId`, method and expected amount
- process webhook events idempotently
- update your order/subscription state from your own domain model
- expose admin actions for refund, cancel, capture and status refresh

## Displaying the Payment Gateway

Comgate supports three main frontend integration styles. The best choice depends on how much control you want over the checkout UX.

### REDIRECT

Use redirect when you want the simplest and most robust implementation.

- Customer is redirected from your app to Comgate and then back again.
- Lowest frontend complexity.
- Usually the safest choice for standard Laravel e-shops.
- Best when you want to keep payment UI logic outside your app.

Laravel recommendation:

- create the payment in your controller or service
- store the returned redirect URL
- send the user to that URL
- handle the return page as a UI-only confirmation
- rely on webhook + `status()` for authoritative payment state

### INLINE

Use inline when you want the gateway embedded in your checkout page.

- Gateway is rendered inside your cart or checkout page via iframe or popup.
- Better UX continuity than redirect.
- More frontend work and more browser/UI edge cases.
- Good if you need a more seamless checkout but still want the standard gateway flow.

Laravel recommendation:

- keep the payment orchestration in PHP
- let the frontend open the gateway container
- use the backend for payment creation and state updates
- keep webhook handling identical to redirect mode

### Checkout SDK

Use Checkout SDK if you want the deepest checkout integration and the most polished in-app card/Apple Pay/Google Pay experience.

- Best for custom checkout experiences.
- Supports card payments, Apple Pay and Google Pay.
- Gives you the most control, but also the most implementation work.
- Well suited for products where the gateway should feel like part of the app rather than an external step.

Laravel recommendation:

- keep your Laravel app as the source of truth for payment state
- use the backend to create and verify payments
- use the frontend SDK only for payment UI and tokenization flow
- still process payment confirmation through webhook and status checks

### Choosing Between Them

- Choose `REDIRECT` if you want the fastest and least risky implementation.
- Choose `INLINE` if you want a branded checkout without a full SDK rollout.
- Choose `Checkout SDK` if you need the best UX and are willing to invest more frontend effort.

For most Laravel teams, the practical default is `REDIRECT` first, then `INLINE`, and only later `Checkout SDK` if you need the extra UX control.

## Usage

Digital goods (a course, a plan, a subscription) should go through `CreatePayment::digital()`. The price is in minor units, `refId` is your payment id, and the product name is shortened to the 16-character Comgate label. Store `transId` and the returned `redirect` unchanged.

```php
use Sloukapetr\ComgateLaravelPackage\Data\CreatePayment;
use Sloukapetr\ComgateLaravelPackage\Data\RecurringCharge;
use Sloukapetr\ComgateLaravelPackage\Data\RefundRequest;
use Sloukapetr\ComgateLaravelPackage\Support\ReturnUrl;

$created = $comgate->createCheckout(CreatePayment::digital(
    price: 19900,
    refId: 'pay-1001',
    product: 'Kurz revizí a zkoušek',
    email: 'platce@email.com',
    fullName: 'Jan Novák',
    urlPaid: ReturnUrl::withPlaceholders('https://example.test/platba/navrat'),
    urlCancelled: ReturnUrl::withPlaceholders('https://example.test/platba/zruseno'),
    urlPending: ReturnUrl::withPlaceholders('https://example.test/platba/ceka'),
));

$redirectUrl = $created->redirect;
$transId = $created->transId;

$status = $comgate->findPayment($transId);

if ($status->isPaid() && $status->matchesAmount(19900, 'CZK')) {
    // fulfill the order
}

$renewal = $comgate->chargeRecurring(new RecurringCharge(
    initRecurringId: $transId,
    price: 19900,
    refId: 'pay-1001-2026-12',
    label: 'Tarif',
));

$comgate->refundPayment(new RefundRequest(
    transId: $transId,
    amount: 19900,
    currency: 'CZK',
));

$unitGateway = $comgate->withCredentials($unitMerchantId, $unitSecret);
```

`withCredentials()` returns a separate client. The shared `Comgate` service keeps using `COMGATE_MERCHANT_ID`.

The first recurring payment is a normal checkout with `initRecurring: true` and a card method. Later charges use `chargeRecurring()` and a new `refId` each time. In tests, the payer e-mail `recurring.cancelled@comgate.cz` makes every follow-up charge `CANCELLED`.

Low-level calls remain available when you already have a payload:

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

$redirect = $comgate->paymentRedirect([
    'price' => 1000,
    'curr' => 'CZK',
    'label' => 'ORDER-1001',
    'refId' => '1001',
    'method' => 'ALL',
]);
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

$transferList = $comgate->transferList('2025-04-25');
$singleTransfer = $comgate->singleTransfer('1234567');
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

## Admin Payment Overview

If you are building an admin panel, keep a local payment overview table or screen. That makes refunds, cancellations and subscription maintenance much easier.

Useful columns and fields:

- `transId`, `refId`, `method`, `curr`, `price`, `status`
- relation to order, customer or subscription
- timestamps for created, paid, cancelled, refunded and last webhook
- internal processing flags such as `refunded`, `captured`, `processed_at`

Useful admin actions:

- view payment detail and raw webhook history
- refresh status from Comgate
- create refund for paid payments
- cancel pending payments
- capture authorized preauth payments
- navigate to related order or subscription detail

Recommended admin rules:

- only allow refund/cancel actions when the current state matches the Comgate rules
- write every admin action to an audit log
- treat webhook events as the trigger for state changes, not the email notifications
- make all listeners idempotent so retries do not duplicate side effects

## Webhooks

When enabled, the package registers a `POST` webhook route (`/comgate/webhook` by default).

- Secret is validated from `X-Comgate-Secret` header or `secret` payload field.
- On success, the package dispatches `Sloukapetr\ComgateLaravelPackage\Events\ComgateWebhookReceived`.
- For `PAID` notifications, it also dispatches `Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentPaid`.
- For `CANCELLED` notifications, it also dispatches `Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentFailed`.
- The event exposes both raw payload and a typed `notification` DTO with helpers like `isPaid()` and `isCancelled()`, plus typed status fields such as `cardNumber`, `appliedFeeType`, `threeDSPreference` and `threeDSApplied`.
- The DTO also exposes `getPriceInMainUnit()` and `getFormattedPriceAttribute()` to convert Comgate cents/haléře into the main currency unit.
- Response is always `code=0&message=OK` for valid webhook calls.

```php
use Sloukapetr\ComgateLaravelPackage\Events\ComgateWebhookReceived;

class HandleComgateWebhook
{
    public function handle(ComgateWebhookReceived $event): void
    {
        // Keep this listener idempotent: Comgate can retry webhook delivery.
        if ($this->alreadyProcessed($event->notification->transId)) {
            return;
        }

        if ($event->notification->isPaid()) {
            $transactionId = $event->notification->transId;
            $referenceId = $event->notification->refId;

            // mark order or subscription renewal as paid
        }
    }

    private function alreadyProcessed(?string $transactionId): bool
    {
        return false;
    }
}
```

## Facade

```php
use Comgate;

$response = Comgate::status(['transId' => 'YOUR_TRANSACTION_ID']);

$fake = Comgate::fake([
    'status' => ['code' => 0, 'message' => 'OK', 'transId' => 'TX-001', 'status' => 'PAID'],
]);
```

## Official API Reference

- https://apidoc.comgate.cz/en/api/rest/

## Testing

```bash
composer test
```

The package includes HTTP-layer tests using Laravel `Http::fake()` and Testbench for webhook route coverage.
The facade fake helper can be used in application tests when you want to avoid HTTP calls and assert recorded Comgate operations.

## Release

See [RELEASE_CHECKLIST.md](c:/Users/slouka/comgate-laravel-package/RELEASE_CHECKLIST.md) for the package release checklist.
