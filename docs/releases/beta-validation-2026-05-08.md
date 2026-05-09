# Beta Validation - 2026-05-08

## Asterisk/Twilio Inbound SIP Fixes

Validated and corrected the Docker sandbox Asterisk runtime so inbound provider
calls now reach the `from-pstn` dialplan and can execute the legacy A2Billing
AGI under PHP 8.3.

Summary of changes:

- Recreated the Asterisk container with the current Compose `asterisk` profile
  so the runtime config mount is present.
- Restored and verified `[from-pstn]` in the live dialplan.
- Added PHP CLI and required runtime extensions to the Asterisk image so
  `AGI/a2billing.php` can execute inside the telephony container.
- Mounted the repo root into the Asterisk container so the AGI can resolve
  `vendor/autoload.php`.
- Generated `/etc/a2billing.conf` inside the Asterisk container and corrected
  the internal database port to `3306` for Docker-network access to the `db`
  service.
- Switched the inbound dialplan to the absolute AGI path
  `/opt/a2billingplus/AGI/a2billing.php`.
- Fixed AGI bootstrap issues by defining `FSROOT` before library loads and
  converting duplicate `include` calls to `include_once`.

Key verification:

```text
dialplan show from-pstn
  s => NoOp -> AGI(/opt/a2billingplus/AGI/a2billing.php,1,did) -> Hangup

php /opt/a2billingplus/AGI/a2billing.php --version
  A2Billing - v2.2.0

/etc/a2billing.conf
  hostname = db
  port = 3306
```

Result: the prior failure chain of `404 extension not found`, AGI execution
denial, missing PHP runtime, missing Composer autoload path, and incorrect
container-local DB port is resolved in the sandbox.

## PJSIP Realtime Trunk Validation

Validated that the sandbox can now materialize and load realtime PJSIP trunk
state for provider-facing inbound routing.

Commands used:

```powershell
docker compose exec -T app php -r "require '/var/www/html/vendor/autoload.php'; ..."
docker compose exec -T app php bin/backfill-pjsip-trunks.php
docker exec a2billingplus-asterisk-1 asterisk -rx "pjsip reload"
docker exec a2billingplus-asterisk-1 asterisk -rx "pjsip show endpoints"
```

Observed result after local provisioning/backfill:

```text
Endpoint: trunk-vectavoip  Unavailable  0 of inf
Aor: trunk-vectavoip
Contact: sip:sip.vectavoip.com
Identify match: 74.208.7.156/32
```

Result: realtime `ps_*` tables are loading into Asterisk correctly in the
sandbox after provisioning and reload.

## Remaining Provider Follow-up

- Materialize the live Twilio BYOC/inbound trunk in the sandbox with the
  correct identify match values from production signaling.
- Run a fresh inbound call test and record the first post-AGI application
  failure, if any.
- Publish the exact provider provisioning steps used for Twilio/VectaVoIP
  sandbox mirroring.
