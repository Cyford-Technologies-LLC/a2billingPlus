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

For local sandbox work, put real Stripe test secrets in `.env.stripe` instead of
`.env`. Docker Compose loads `.env`, then optional `.env.local`, then optional
`.env.stripe` for the PHP app containers. Use `.env.stripe.example` as the
template and keep `.env.stripe` uncommitted.

A2BillingPlus also accepts Cyford-style mode-specific names:

```text
MODE=test
STRIPE_TEST_SECRET_KEY=sk_test_value
STRIPE_TEST_WEBHOOK_SECRET=whsec_value
```

For `MODE=live`, use `STRIPE_LIVE_SECRET_KEY` and
`STRIPE_LIVE_WEBHOOK_SECRET`. Do not use a Stripe API secret key as the webhook
secret; Stripe webhook secrets are separate values that start with `whsec_`.

Braintree is optional and should stay disabled unless needed:

```text
BRAINTREE_MERCHANT_ID=
BRAINTREE_PUBLIC_KEY=
BRAINTREE_PRIVATE_KEY=
PAYMENT_CURRENCY=USD
```

PayPal is not a beta payment gateway. The old A2Billing PayPal form-post flow is
classified as deprecated and must stay disabled until a new hosted PayPal module
exists with tokenized order creation, signed webhook verification, replay
protection, and ledger reconciliation tests.

## Webhook Verification

`StripeWebhookVerifier` verifies the raw request body against the
`Stripe-Signature` header using the endpoint secret. Stripe documents this
raw-body requirement and timestamped signature model here:
<https://docs.stripe.com/webhooks/signatures>

Local sandbox verification helper:

```powershell
$env:STRIPE_WEBHOOK_SECRET = "whsec_test_value"
docker compose up -d app
powershell -ExecutionPolicy Bypass -File bin\verify-stripe-webhook-sandbox.ps1 -VerifyDuplicate
```

For the beta gate, use the same script with a payload captured from a real Stripe
sandbox fixture:

```powershell
powershell -ExecutionPolicy Bypass -File bin\verify-stripe-webhook-sandbox.ps1 -PayloadPath .\stripe-payment-intent-succeeded.json -VerifyDuplicate
```

The secret in the shell must match `STRIPE_WEBHOOK_SECRET` in `.env`, and the
`app` container must be restarted after `.env` changes.

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
