# Release Notes: v1.0.0

## Summary

First stable release of the Laravel Comgate package with current Merchant REST API integration, Status API coverage, typed availability reporting and webhook support.

## Highlights

- Merchant REST API support for create payment, recurring payment, refund, cancel payment, status and preauth capture/cancel.
- Status API support for gateway health, outages and higher-level payment availability checks.
- Typed availability report with localized summary helpers and guarded `createPaymentChecked()` flow.
- Typed webhook notification object for easier Laravel listener handling.
- Test coverage using Testbench and `Http::fake()`.
- CI matrix for Laravel 11, 12 and 13.

## Upgrade Notes

- This is the first tagged stable release.
- Use the redirect URL returned by Comgate directly; do not reconstruct payment URLs manually.
- For recurring payments, prefer `initRecurringId` for subsequent charges.
- `cancel()` remains as a backward-compatible alias for preauthorization cancellation.

## Validation

- `composer validate --strict`
- `composer test`