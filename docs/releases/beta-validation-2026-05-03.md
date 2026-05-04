# Beta Validation - 2026-05-03

## Migration Apply Against Sanitized Legacy Snapshot

Validated `bin/migrate-a2billing.php --apply` against temporary MariaDB databases
inside the Docker sandbox:

- Source: `a2bp_legacy_snapshot_apply`
- Target: `a2bp_target_snapshot_apply`
- Dataset: sanitized customer, SIP, IAX, and CDR rows with redacted account
  identifiers and secrets.
- Date window for CDR apply: `2026-05-01 00:00:00` to `2026-05-03 00:00:00`.

Commands used:

```powershell
docker compose exec -T -e A2BP_SOURCE_DSN='mysql:host=db;dbname=a2bp_legacy_snapshot_apply;charset=utf8mb4' -e A2BP_SOURCE_USER=root -e A2BP_SOURCE_PASSWORD=*** -e A2BP_DB_DSN='mysql:host=db;dbname=a2bp_target_snapshot_apply;charset=utf8mb4' -e A2BP_DB_USER=root -e A2BP_DB_PASSWORD=*** app php bin/migrate-a2billing.php --scope=customers --apply
docker compose exec -T -e A2BP_SOURCE_DSN='mysql:host=db;dbname=a2bp_legacy_snapshot_apply;charset=utf8mb4' -e A2BP_SOURCE_USER=root -e A2BP_SOURCE_PASSWORD=*** -e A2BP_DB_DSN='mysql:host=db;dbname=a2bp_target_snapshot_apply;charset=utf8mb4' -e A2BP_DB_USER=root -e A2BP_DB_PASSWORD=*** app php bin/migrate-a2billing.php --scope=voip --apply
docker compose exec -T -e A2BP_SOURCE_DSN='mysql:host=db;dbname=a2bp_legacy_snapshot_apply;charset=utf8mb4' -e A2BP_SOURCE_USER=root -e A2BP_SOURCE_PASSWORD=*** -e A2BP_DB_DSN='mysql:host=db;dbname=a2bp_target_snapshot_apply;charset=utf8mb4' -e A2BP_DB_USER=root -e A2BP_DB_PASSWORD=*** app php bin/migrate-a2billing.php --scope=cdrs --from='2026-05-01 00:00:00' --to='2026-05-03 00:00:00' --apply
```

Observed output:

```json
{"success":true,"dry_run":false,"scope":"customers","scanned_rows":3,"inserted_rows":1,"updated_rows":1,"skipped_rows":1}
{"success":true,"dry_run":false,"scope":"voip","scanned_rows":2,"inserted_rows":2,"updated_rows":0,"skipped_rows":0}
{"success":true,"dry_run":false,"scope":"cdrs","scanned_rows":1,"inserted_rows":1,"updated_rows":0,"skipped_rows":0,"date_window":{"from":"2026-05-01 00:00:00","to":"2026-05-03 00:00:00"}}
```

Target verification after apply:

```text
cc_card          2
cc_sip_buddies   1
cc_iax_buddies   1
cc_call          1
```

Result: migration apply is validated against a sanitized legacy-style snapshot
for customer, VoIP, and date-windowed CDR scopes.

## Asterisk 20 PJSIP Sandbox Call Path

Corrected the Docker sandbox PJSIP endpoint so a normal SIP user can register as
`1001`. The sandbox AOR now uses `remove_existing=yes` so repeated local client
tests replace the previous temporary contact cleanly.

Commands used:

```powershell
docker compose build asterisk
docker compose up -d asterisk
docker compose exec -T asterisk asterisk -rx 'pjsip show endpoints'
docker compose exec -T asterisk asterisk -rx 'pjsip show aor 1001'
```

Temporary client command run inside the Asterisk container after installing
`baresip`:

```bash
baresip -s -f /tmp/a2bp-baresip -e '/dial sip:1000@127.0.0.1' -v
```

Observed SIP path:

```text
REGISTER sip:127.0.0.1 -> 401 challenge -> REGISTER with digest -> 200 OK
INVITE sip:1000@127.0.0.1 -> 401 challenge -> INVITE with digest
INVITE result -> 100 Trying -> 200 OK with SDP
Client ACK sent
Client BYE sent -> 200 OK
```

Observed Asterisk path:

```text
Endpoint 1001 is now Reachable
Executing [1000@a2billingplus-sandbox:1] Answer("PJSIP/1001-00000000", "") in new stack
```

Result: the Asterisk 20 sandbox now has a verified PJSIP registration and
authenticated call path into the sandbox dialplan. Media device playback is not
validated inside the headless container; the client closed the call after SIP
setup because no local audio sink exists.

## Stripe Sandbox Webhook Verification

Validated the Stripe webhook endpoint against a Stripe CLI-generated sandbox
event stream and verified duplicate replay protection with a locally signed
event. Secrets are redacted below.

Readiness after loading `.env.stripe` and restarting the sandbox app:

```json
[
  {"check":"local_env_overlay","passed":true},
  {"check":"app_container_running","passed":true},
  {"check":"container_webhook_secret","passed":true},
  {"check":"app_health","passed":true},
  {"check":"webhook_endpoint_reachable","passed":true}
]
```

Command used to validate local signature handling and replay protection:

```powershell
powershell -ExecutionPolicy Bypass -File bin\verify-stripe-webhook-sandbox.ps1 -VerifyDuplicate
```

Observed output:

```json
{
  "success": true,
  "duplicate": false,
  "provider": "stripe",
  "event_type": "payment_intent.succeeded",
  "message": "Stripe payment intent success accepted."
}
{
  "success": true,
  "duplicate": true,
  "message": "Stripe webhook already processed."
}
```

Command used for Stripe-generated sandbox events:

```powershell
docker run --rm -e STRIPE_API_KEY=sk_test_[REDACTED] stripe/stripe-cli trigger payment_intent.succeeded
```

Observed Stripe listener summary:

```text
Trigger succeeded! Check dashboard for event details.
--> payment_intent.succeeded [evt_...]
<-- [202] POST http://host.docker.internal:8080/api/v1/payment-webhooks.php?provider=stripe [evt_...]
--> payment_intent.created [evt_...]
<-- [202] POST http://host.docker.internal:8080/api/v1/payment-webhooks.php?provider=stripe [evt_...]
```

Result: Stripe webhook signature verification, event recording, and replay
handling are validated in the local sandbox.

## Stripe Hosted Payment Intent Validation

Validated hosted/tokenized Stripe PaymentIntent creation against the Stripe
sandbox API from the PHP app container. No raw card data was submitted or
stored.

Observed output:

```json
{
  "success": true,
  "status_code": 200,
  "provider": "stripe",
  "payment_intent_id_prefix": "pi_",
  "client_secret_present": true,
  "status": "requires_payment_method",
  "message": "Stripe payment intent created."
}
```

Result: hosted Stripe payment intent creation and webhook verification are both
validated for the beta payment gate.
