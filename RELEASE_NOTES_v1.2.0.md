# Release Notes: v1.2.0

## Summary

Small follow-up release focused on edge cases: typed webhook price helpers, explicit handling of HTTP 200 responses that still carry Comgate error codes, and webhook listener idempotence guidance.

## Highlights

- `ComgateWebhookNotification` now exposes price helpers for display in the main currency unit.
- The test suite explicitly covers REST responses where HTTP status is 200 but `code` is non-zero.
- The webhook documentation now calls out idempotent listener handling for retry deliveries.

## Upgrade Notes

- Existing API usage remains compatible.
- If you format prices from webhook payloads, use the new helpers instead of manual division.
- Keep webhook listeners idempotent because Comgate may retry deliveries.

## Validation

- `composer validate --strict`
- `composer test`