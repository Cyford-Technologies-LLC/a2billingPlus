# Migration

A2BillingPlus migration is source-to-target and defaults to dry-run.

## Supported Import Scopes

```powershell
docker compose exec app php bin/migrate-a2billing.php --scope=customers
docker compose exec app php bin/migrate-a2billing.php --scope=voip
docker compose exec app php bin/migrate-a2billing.php --scope=cdrs --from=2026-05-01 --to=2026-06-01
```

Customer imports upsert `cc_card` by `id` or `username`.

VoIP imports upsert `cc_sip_buddies` and `cc_iax_buddies` by `id` or `name`.

CDR imports upsert `cc_call` by `id` or `uniqueid`. Use `--from` and `--to` for
large installations so archived calls move in controlled windows.

Only columns that exist in both the source and target schemas are copied. This
lets the importer work against the final A2BillingPlus schema without assuming
every legacy column still exists.

## Operator Dry-Run Reports

Every run emits JSON:

```json
{
  "success": true,
  "dry_run": true,
  "scope": "cdrs",
  "scanned_rows": 1000,
  "inserted_rows": 998,
  "updated_rows": 2,
  "skipped_rows": 0,
  "results": {}
}
```

Operators should save dry-run output with the matching database snapshot before
running with `--apply`.

## Rollback Guidance

Before every apply run:

1. Back up the target MariaDB database.
2. Record the exact command, scope, date window, git commit, and dry-run JSON.
3. Confirm the target database is not receiving live traffic.
4. Apply one scope or one CDR date window at a time.
5. Run reconciliation checks after each window.

Rollback is restore-first: restore the target database backup taken immediately
before the failed apply run. Do not try to reverse a partial migration by hand
unless a DBA has reviewed the affected rows and constraints.

## Snapshot Tests

The PHPUnit migration tests use sanitized in-memory source and target snapshots.
Add new snapshot cases whenever a legacy table shape or final-schema table shape
changes.
