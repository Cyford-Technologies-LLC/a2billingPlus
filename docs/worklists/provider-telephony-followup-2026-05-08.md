# Provider Telephony Follow-up - 2026-05-08

## Completed

- Asterisk sandbox now mounts runtime config and loads `from-pstn`.
- Asterisk image now contains PHP CLI and AGI runtime dependencies.
- AGI scripts now start cleanly under the telephony container.
- Asterisk-local database config now uses `db:3306`.
- Legacy/provider trunk backfill into PJSIP realtime tables is working again.

## Next

- Provision the Twilio inbound/BYOC trunk used by the live provider account.
- Confirm the PJSIP identify rule matches the actual Twilio source address or
  supported source range.
- Retest live inbound SIP to confirm calls enter `from-pstn` and stay in the
  A2Billing DID path.
- Record any remaining A2Billing routing or billing-layer failure after AGI
  startup.

## Files Touched In This Work

- `docker/asterisk/Dockerfile`
- `docker/asterisk/entrypoint.sh`
- `docker/asterisk/config/extensions.conf`
- `docker-compose.yml`
- `AGI/a2billing.php`
- `AGI/a2billing_monitoring.php`
