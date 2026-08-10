# Agent Instructions for comgate-laravel-package

## Purpose
This repository is a Laravel package for Comgate Merchant REST API v2.0 integration.

## Source of Truth
- `src/ComgateService.php` is the main transport and orchestration layer.
- `src/Http/Controllers/ComgateWebhookController.php` handles inbound webhook requests.
- `src/Data/ComgateWebhookNotification.php` is the typed webhook DTO.
- `src/Fakes/ComgateFake.php` is the test double for `Comgate::fake()`.
- `tests/` is the contract for supported behavior.

## Supported Flows
- One-off payments and redirect-based payments.
- Recurring payments and preauth capture/cancel.
- Refunds, cancellation, payment status, method discovery.
- Status API health and outage checks.
- Transfer reporting and payout export helpers.
- Webhook handling for paid/cancelled status changes.

## Working Rules
- Prefer the existing service API over adding parallel abstractions.
- Keep webhook handling idempotent in application code.
- Treat Comgate emails as informational only; do not use them as business truth.
- Check Comgate API `code` values even when HTTP status is 200.
- Preserve public API compatibility unless a change is clearly required.

## Testing Rules
- Run `composer test` after code changes.
- Use `Http::fake()` for HTTP-layer behavior.
- Use `Comgate::fake()` for unit tests that should avoid real HTTP calls.
- Add a focused test for any new public method or edge case.

## Laravel App Guidance
- Store payment, order, or subscription state in the application database.
- Use webhooks and explicit status checks to drive state changes.
- Add an admin overview for refunds, cancellations, captures, and reconciliation.
- Keep listeners and jobs retry-safe and idempotent.

## Do Not
- Do not hardcode Comgate responses into application logic.
- Do not rely on emails to confirm payment state.
- Do not add new public methods without a clear API mapping and tests.
- Do not rewrite stable behavior just to match naming preferences.
