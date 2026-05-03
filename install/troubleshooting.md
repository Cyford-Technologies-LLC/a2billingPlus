# Troubleshooting

## Forbidden At `http://localhost:8080`

Use the admin path directly:

```text
http://localhost:8080/admin/Public/index.php
```

The root URL may not be the application entry point.

## Database Is Not Ready

Check services:

```powershell
docker compose ps
docker compose logs db
```

Check MariaDB manually:

```powershell
docker compose exec -T db mariadb -ua2billinguser -pa2billing mya2billing -e "SELECT VERSION();"
```

## Changed `.env` But Credentials Did Not Change

MariaDB initialization variables only apply when the database volume is first created.

For a sandbox reset:

```powershell
docker compose down -v
docker compose up -d --build
```

Do not use `down -v` on production unless you intend to delete the database volume.

## PHP Warnings Or Fatal Errors

Run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File development\tools\php8-static-scan.ps1 -MaxFindings 80
docker compose exec -T app php -l admin/Public/index.php
```

Then crawl the portals:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File development\tools\crawl-all-portals.ps1 -BaseUrl http://localhost:8080 -AgentLogin crawlagent -AgentPassword crawlpass -CustomerLogin crawlcustomer@example.test -CustomerPassword crawlpass
```

## Port Already In Use

Change the relevant `.env` port:

```text
A2BP_HTTP_PORT=8081
A2BP_DB_PORT=3308
```

Then restart:

```powershell
docker compose up -d
```

## Rebuild PHP Containers

```powershell
docker compose build app
docker compose up -d app
```

For PHP 8.4:

```powershell
docker compose --profile php84 build app84
docker compose --profile php84 up -d app84
```

## Build Verification

Run the project verification script before pushing or releasing a level:

```powershell
powershell -ExecutionPolicy Bypass -File bin/verify-build.ps1
```

Use `-SkipPhp84` when the optional PHP 8.4 container is not running:

```powershell
powershell -ExecutionPolicy Bypass -File bin/verify-build.ps1 -SkipPhp84
```
