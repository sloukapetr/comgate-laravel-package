# Release Notes: v1.3.0

## Summary

Minor release that adds a typed checkout for digital goods, so an application can create a payment, read its status, charge a renewal, and refund it without assembling raw Comgate payloads.

## Highlights

- `CreatePayment::digital()` and `createCheckout()` validate the amount, contact, 16-character label, and return URLs before the HTTP call.
- Return URLs keep the literal `${id}` and `${refId}` placeholders.
- `findPayment()` returns `PaymentStatus`, and webhook notifications can confirm the amount with `matchesAmount()`.
- `chargeRecurring()` and `refundPayment()` cover subscription renewals and refunds.
- `withCredentials()` talks to a second Comgate merchant. The shared client keeps using `COMGATE_MERCHANT_ID`.
- Refund codes 1401 and 1402 throw `RefundRejectedException`. Recurring codes 1316, 1317, 1318, and 1320 throw `RecurringPaymentException`.

## Upgrade Notes

- Existing array-based methods stay compatible.
- New checkout defaults live under `comgate.defaults` (`CZ`, `CZK`, `cs`, `ALL`, `OTHER`, `ELECTRONIC_DELIVERY`).
- Prices are minor units. A CZK payment below 100 haléřů is rejected locally.

## Validation

- `composer validate --strict`
- `composer test`
