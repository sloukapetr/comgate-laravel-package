# Release Notes: v1.1.0

## Summary

Minor release that expands the package into a broader Comgate integration surface with transfer/reporting helpers, fake-based testing support and typed payment-state webhook events.

## Highlights

- Merchant REST helpers for transfer lists, single transfers and file exports.
- Redirect-based payment helper for the Merchant API redirect flow.
- Additional REST coverage for `refundPos`, `config` and Apple Pay domain association.
- `Comgate::fake()` for fast unit tests with recorded calls and configurable stub responses.
- Dedicated webhook events for `PAID` and `CANCELLED` notifications.
- Documentation updates for the new API surface.

## Upgrade Notes

- Existing code using the facade or service remains compatible.
- Webhook listeners can keep using `ComgateWebhookReceived`, or opt into the new state-specific events.
- If you need transfer exports, use the new helpers instead of calling the REST endpoints manually.

## Validation

- `composer validate --strict`
- `composer test`
