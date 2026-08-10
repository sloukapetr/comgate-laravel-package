# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog.

## [Unreleased]

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