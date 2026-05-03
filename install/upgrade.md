# Upgrade Workflow

Use this workflow when moving an existing A2BillingPlus install to newer code.

## 1. Review Release Notes

Before deploying:

- Check new SQL migrations in `install/migrations`.
- Check `.env.example` for new required settings.
- Check provider API changes in `install/provider-registration.md`.
- Check PHP version support in `development/documents/php-support-policy.md`
  when available locally.

## 2. Back Up

Follow [Backup and Restore](backup-restore.md) before changing code or
containers.

At minimum, back up:

- MariaDB.
- `.env`.
- `a2billing.conf`.
- Asterisk runtime data if enabled.

## 3. Stage The Upgrade

In a staging checkout:

```powershell
git fetch origin
git checkout develop
git pull --ff-only origin develop
docker compose --profile php84 up -d --build
powershell -ExecutionPolicy Bypass -File bin\verify-build.ps1
```

Confirm:

- Installer page loads.
- Admin login works.
- Provider status works.
- Provider rate preview works.
- Migration dry-run works.

## 4. Deploy Code

On the target host:

```powershell
git fetch origin
git checkout develop
git pull --ff-only origin develop
docker compose up -d --build
```

Start optional services as needed:

```powershell
docker compose --profile asterisk up -d --build asterisk
docker compose --profile php84 up -d --build app84
```

## 5. Apply Migrations

The web installer applies files from `install/migrations` after the base schema
exists. To run through the installer:

```text
http://localhost:8080/install.php
```

Keep `install.lock` in place for production after the upgrade is complete.

## 6. Verify

Run:

```powershell
powershell -ExecutionPolicy Bypass -File bin\verify-build.ps1
```

For production where PHP 8.4 is not running:

```powershell
powershell -ExecutionPolicy Bypass -File bin\verify-build.ps1 -SkipPhp84
```

## 7. Roll Back

If the upgrade fails:

1. Stop app containers.
2. Restore the database backup.
3. Restore `.env` and `a2billing.conf`.
4. Check out the previous known-good commit.
5. Rebuild and restart containers.
6. Run a targeted smoke check before reopening traffic.

Do not roll back code without considering SQL migrations. Some migrations may
need explicit rollback SQL depending on the change.
