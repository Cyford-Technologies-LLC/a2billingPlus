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
