# Sandbox Install

Use this guide for local development and compatibility testing.

## Requirements

- Docker Desktop or Docker Engine with Compose v2
- Git
- At least 4 GB free RAM for the full stack
- Ports available: `8080`, `3307`, `6379`

Optional ports:

- PHP 8.4 app: `8084`
- Asterisk AMI: `5038`
- Asterisk ARI: `8088`
- SIP UDP: `5060`
- RTP UDP: `10000-10020`
- Mailpit: `8025`, `1025`

## First Run

From the repository root:

```powershell
Copy-Item .env.example .env
docker compose up -d --build
```

Open:

```text
http://localhost:8080/admin/Public/index.php
```

Or run the web installer first:

```text
http://localhost:8080/install.php
```

In the installer, choose **Make a new MariaDB container** for the normal sandbox
path. Choose **Use an existing database** only when connecting to a database that
is not managed by this Compose stack.

Default admin login:

```text
root / changepassword
```

## Clean Sandbox From A Fresh Clone

From a new checkout, this is the shortest sandbox path:

```powershell
if (!(Test-Path .env)) { Copy-Item .env.example .env }; docker compose --profile php84 up -d --build; powershell -ExecutionPolicy Bypass -File bin\verify-build.ps1
```

That command:

- Creates `.env` from `.env.example` if needed.
- Builds and starts MariaDB, Redis, the PHP 8.2 app, and the PHP 8.4 app.
- Loads the bundled MariaDB schema into the sandbox database.
- Runs syntax checks, PHPUnit, the PHP 8 static scan, provider API smoke tests,
  provider rate dry-run import, and migration dry-run smoke tests.

Open the installer after the containers are healthy:

```text
http://localhost:8080/install.php
```

For a full portal crawl, seed sandbox agent/customer accounts first:

```powershell
powershell -ExecutionPolicy Bypass -File development\tools\seed-crawl-accounts.ps1
powershell -ExecutionPolicy Bypass -File development\tools\crawl-all-portals.ps1 -BaseUrl http://localhost:8080 -AgentLogin crawlagent -AgentPassword crawlpass -CustomerLogin crawlcustomer@example.test -CustomerPassword crawlpass
```

## Start Optional PHP 8.4 Runtime

```powershell
docker compose --profile php84 up -d --build app84
```

Open:

```text
http://localhost:8084/admin/Public/index.php
```

## Start Optional Asterisk Container

```powershell
docker compose --profile asterisk up -d --build asterisk
```

## Start Optional Mailpit

```powershell
docker compose --profile tools up -d mailpit
```

Open Mailpit:

```text
http://localhost:8025
```

## Verify The Stack

```powershell
docker compose ps
docker compose exec -T app php -v
docker compose exec -T app php -m
docker compose exec -T db mariadb -ua2billinguser -pa2billing mya2billing -e "SELECT VERSION();"
```

Expected:

- `app` is healthy
- `db` is healthy
- PHP includes `mysqli` and `pdo_mysql`
- MariaDB responds with a version

## Run Compatibility Checks

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File development\tools\php8-static-scan.ps1 -MaxFindings 40
powershell -NoProfile -ExecutionPolicy Bypass -File development\tools\crawl-all-portals.ps1 -BaseUrl http://localhost:8080 -AgentLogin crawlagent -AgentPassword crawlpass -CustomerLogin crawlcustomer@example.test -CustomerPassword crawlpass
docker compose exec -T app vendor/bin/phpunit
```

For PHP 8.4:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File development\tools\crawl-all-portals.ps1 -BaseUrl http://localhost:8084 -AgentLogin crawlagent -AgentPassword crawlpass -CustomerLogin crawlcustomer@example.test -CustomerPassword crawlpass
docker compose exec -T app84 vendor/bin/phpunit
```

## Reset Sandbox Database

This deletes the local MariaDB volume.

```powershell
docker compose down -v
docker compose up -d --build
```
