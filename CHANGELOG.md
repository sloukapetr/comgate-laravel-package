# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog.

## [Unreleased]

## [1.3.0] - 2026-11-11

### Added

- Checkout helpers for the flows the application will use: `CreatePayment::digital()`, `createCheckout()`, `chargeRecurring()`, `refundPayment()` and `findPayment()`.
- `PaymentStatus` as the single Comgate status mapping, with amount checks on status and webhook notifications.
- `withCredentials()` for a second Comgate merchant without replacing the configured default client.
- Validation of price, contact, label length and return URLs before the HTTP call. Return URLs keep the literal `${id}` and `${refId}` placeholders.
- `RefundRejectedException` for refund codes 1401 and 1402, and `RecurringPaymentException` for recurring codes 1316, 1317, 1318 and 1320.

## [1.2.1] - 2026-08-10

### Added

- Repository-level `AGENTS.md` guidance for AI coding agents working with this package.
- Expanded README guidance for Laravel payment workflows, admin payment overview patterns, and integration-mode selection (`REDIRECT`, `INLINE`, `Checkout SDK`).

### Changed

- Improved implementation recommendations for app-level idempotence, payment state ownership, and operational support flows.

## [1.2.0] - 2026-08-10

### Added

- Price conversion helpers on the typed webhook DTO for main-currency display.
- Explicit documentation and test coverage for non-zero REST API `code` responses returned with HTTP 200.
- Webhook listener guidance for idempotent processing.

### Changed

- Extended the webhook DTO and tests for price formatting and retry-safe listener flows.

## [1.1.0] - 2026-08-10

### Added

- Transfer/reporting API helpers for daily transfer lists and single transfer exports.
- Additional Merchant REST helpers for `refundPos`, `config`, `csvDownload`, `aboDownload`, `csvSingleTransfer`, `aboSingleTransfer` and Apple Pay domain association.
- Redirect-based payment helper for the `paymentRedirect` Merchant API flow.
- `Comgate::fake()` testing helper with call recording and configurable stub responses.
- Dedicated payment state events for `PAID` and `CANCELLED` webhook notifications.

### Changed

- Webhook controller now dispatches typed state events alongside the generic webhook event.
- Documentation expanded for the fake transport and newly exposed methods.

## [1.0.0] - 2026-08-10

### Added

- Merchant REST API support for one-off payments, recurring payments, pre-authorization capture/cancel, refunds, payment cancellation and payment status lookup.
- Comgate Status API support for gateway health and outages.
- Aggregated availability reporting with typed decision helpers and guarded payment creation.
- Typed webhook notification DTO and webhook event integration.
- Package test suite using Orchestra Testbench and Laravel `Http::fake()`.
- Release checklist and CI workflow for Laravel 11, 12 and 13 compatibility testing.

### Changed

- Updated the Comgate transport layer to use HTTP Basic authentication and current REST endpoint mappings.
- Improved package documentation and publication metadata.