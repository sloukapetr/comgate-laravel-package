# Release Notes: v1.2.1

## Summary

Patch release focused on documentation and implementation guidance for teams integrating Comgate in Laravel applications.

## Highlights

- Added repository-level AI agent guidance in `AGENTS.md`.
- Expanded README with practical Laravel integration patterns.
- Added decision guidance for gateway display modes: `REDIRECT`, `INLINE`, and `Checkout SDK`.
- Clarified app-level responsibilities for idempotent webhook processing and payment state ownership.

## Upgrade Notes

- No public API changes.
- No breaking changes.
- Existing integrations continue to work without code modifications.

## Validation

- `composer validate --strict`
- `composer test`
