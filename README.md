# comgate-laravel-package

Clean, reusable Laravel package for Comgate REST API v2.0 integration with Laravel 11, 12 and 13+.

## Features

- One-off payments (`POST /v2.0/create`)
- Recurring payments (`initRecurring=true`, `POST /v2.0/recurringPayment`)
- Pre-authorization capture/cancel (`POST /v2.0/capture`, `POST /v2.0/cancel`)
- Refunds and cancellations (`POST /v2.0/refund`, `POST /v2.0/cancel`)
- Payment status verification (`POST /v2.0/status`)
- Webhook endpoint with secret verification and required `code=0&message=OK` response

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
    'initTransId' => $init['transId'] ?? null,
    'price' => 1000,
    'curr' => 'CZK',
    'label' => 'SUBSCRIPTION-RENEW',
]);
```

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

$cancel = $comgate->cancel([
    'transId' => $preauth['transId'] ?? null,
]);
```

### Refund and status

```php
$refund = $comgate->refund([
    'transId' => 'YOUR_TRANSACTION_ID',
    'amount' => 500,
]);

$status = $comgate->status([
    'transId' => 'YOUR_TRANSACTION_ID',
]);
```

## Webhooks

When enabled, the package registers a `POST` webhook route (`/comgate/webhook` by default).

- Secret is validated from `X-Comgate-Secret` header or `secret` payload field.
- On success, the package dispatches `Sloukapetr\ComgateLaravelPackage\Events\ComgateWebhookReceived`.
- Response is always `code=0&message=OK` for valid webhook calls.

## Facade

```php
use Comgate;

$response = Comgate::status(['transId' => 'YOUR_TRANSACTION_ID']);
```

## Official API Reference

- https://apidoc.comgate.cz/api/rest/
