# Payments

The payment launch path is module-first and tokenized. Do not enable legacy
direct-card payment pages in production.

## Gateway Inventory

| Gateway | Status | Notes |
| --- | --- | --- |
| Stripe | modern | Launch target for hosted checkout or tokenized payments. |
| Braintree | optional | Keep available only when a customer requires it. |
| PayPal legacy | deprecated | Rebuild behind the payment module before use. |
| Moneybookers/Skrill legacy | deprecated | Dated legacy flow; not a launch target. |
| PlugnPay legacy | unsafe disabled | Accepts raw card and CVV values. |
| Iridium legacy | unsafe disabled | Legacy code reads raw card/CVV fields from `cc_epayment_log`. |
| Authorize.Net legacy | deprecated | Requires a new hosted/tokenized implementation. |

## Configuration

Stripe:

```text
STRIPE_SECRET_KEY=sk_live_or_test_value
STRIPE_WEBHOOK_SECRET=whsec_value
PAYMENT_CURRENCY=USD
```

Braintree is optional and should stay disabled unless needed:

```text
BRAINTREE_MERCHANT_ID=
BRAINTREE_PUBLIC_KEY=
BRAINTREE_PRIVATE_KEY=
PAYMENT_CURRENCY=USD
```

## Webhook Verification

`StripeWebhookVerifier` verifies the raw request body against the
`Stripe-Signature` header using the endpoint secret. Stripe documents this
raw-body requirement and timestamped signature model here:
<https://docs.stripe.com/webhooks/signatures>

## Storage Rules

`PaymentSensitiveDataGuard` rejects payload keys that would store raw card
numbers or CVV values. Payment modules may store provider tokens, provider event
IDs, payment IDs, amounts, currency, customer IDs, and reconciliation metadata.
They must not store card numbers, CVV/CVC values, or direct bank account
numbers.

## Reconciliation

`PaymentReconciliationService` compares `cc_logpayment.payment` against
`cc_logpayment.added_refill` for a date window. Differences should be reviewed
before posting final customer balance reports.
