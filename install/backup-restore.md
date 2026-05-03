# Backup and Restore

Use this workflow before upgrades, migrations, provider changes, or production
maintenance.

## What To Back Up

- MariaDB database.
- `.env` and `a2billing.conf`.
- Asterisk voicemail, recordings, and spool volumes if enabled.
- Uploaded files or custom customer assets if enabled.
- Migration snapshots that are required for rollback or audit.

Keep backups outside the Compose volumes and outside the repository.

## MariaDB Backup

PowerShell:

```powershell
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
docker compose exec -T db mariadb-dump -uroot -p"$env:MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers mya2billing > "backup-mya2billing-$timestamp.sql"
```

Bash:

```bash
timestamp="$(date +%Y%m%d-%H%M%S)"
docker compose exec -T db mariadb-dump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers mya2billing > "backup-mya2billing-$timestamp.sql"
```

If shell environment variables are not populated, use the values from `.env`.
Do not commit backup files.

## Config Backup

PowerShell:

```powershell
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
New-Item -ItemType Directory -Force "backups\$timestamp"
Copy-Item .env "backups\$timestamp\.env"
Copy-Item a2billing.conf "backups\$timestamp\a2billing.conf"
```

Bash:

```bash
timestamp="$(date +%Y%m%d-%H%M%S)"
mkdir -p "backups/$timestamp"
cp .env "backups/$timestamp/.env"
cp a2billing.conf "backups/$timestamp/a2billing.conf"
```

## Restore MariaDB

Stop application writes first:

```powershell
docker compose stop app app84 worker
```

Restore:

```powershell
docker compose exec -T db mariadb -uroot -p"$env:MYSQL_ROOT_PASSWORD" mya2billing < backup-mya2billing.sql
```

Restart:

```powershell
docker compose up -d app
```

For Bash, replace `$env:MYSQL_ROOT_PASSWORD` with `$MYSQL_ROOT_PASSWORD`.

## Restore Test

Before trusting a backup process:

1. Start a fresh sandbox database.
2. Restore the backup into the sandbox.
3. Confirm the admin login works.
4. Run `bin/verify-build.ps1`.
5. Confirm provider status and rate preview still work.

## Retention

Minimum production baseline:

- Hourly or daily database backups, depending on traffic and balance activity.
- At least 7 daily restore points.
- At least 4 weekly restore points.
- Restore test after any backup tooling change.
