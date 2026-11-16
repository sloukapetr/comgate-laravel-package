# Release Notes: v1.4.0

## Summary

Minor release for webhook safety and a second Comgate shop. The webhook confirms the payment through the status API before it dispatches a typed event, and the secret no longer leaves the server.

## Highlights

- `comgate.webhook.verify_status` is on by default. `ComgatePaymentPaid`, `ComgatePaymentFailed` and `ComgatePaymentAuthorized` follow the verified status and carry `verifiedStatus`.
- A status API outage returns HTTP 503 with no typed event, so Comgate retries the notification.
- `handleWebhook()` strips `secret`. `ComgateWebhookNotification::$secret` is deprecated and always `null`.
- `comgate.merchants` and `forMerchant()` serve another shop. A webhook `merchant` selects that shop's secret and status client.
- `comgate.force_ipv4` (`COMGATE_FORCE_IPV4`, default `false`) forces IPv4 for API calls on a dual-stack server.
- `comgate.webhook.verify_ip` (`COMGATE_WEBHOOK_VERIFY_IP`, default `false`) checks the sender against the Comgate IPv4 list. A failed download skips the check.
- Timeouts and HTTP 5xx throw `ComgateConnectionException` or a retryable `ComgateRequestException` (`isRetryable()`).
- `Comgate::fake()` can assert payments, refunds and status checks, and can build a webhook payload with `willReturnStatus()` and `webhookPayload()`.

## Upgrade Notes

- `webhook.verify_status` now defaults to `true`. Application tests need `Comgate::fake()` or `Http::fake()` for the status call. Set `COMGATE_WEBHOOK_VERIFY_STATUS=false` to keep the old notification-only behavior.
- `force_ipv4` defaults to `false`. Existing installs keep the previous DNS behavior.
- `merchants` defaults to `[]`. A single-shop install does not need it.
- `webhook.verify_ip` defaults to `false`. Enable it only after trusted proxies are set, otherwise `$request->ip()` may be the proxy address.
- Do not read `ComgateWebhookNotification::$secret`. It is deprecated and always `null`.

## Validation

- `composer validate --strict`
- `composer test`
