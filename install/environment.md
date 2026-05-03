# Environment Reference

The Docker stack reads `.env` from the repository root.

Create it from the example:

```powershell
Copy-Item .env.example .env
```

## Web Ports

```text
A2BP_HTTP_PORT=8080
A2BP_HTTP_84_PORT=8084
```

`A2BP_HTTP_PORT` exposes the default PHP app.

`A2BP_HTTP_84_PORT` exposes the optional PHP 8.4 app when the `php84` profile is enabled.

## Database

```text
MYSQL_DATABASE=mya2billing
MYSQL_USER=a2billinguser
MYSQL_PASSWORD=a2billing
MYSQL_ROOT_PASSWORD=a2billing-root

A2BP_DB_HOST=db
A2BP_DB_NAME=mya2billing
A2BP_DB_USER=a2billinguser
A2BP_DB_PASSWORD=a2billing
A2BP_DB_PORT=3307
A2BP_SECRET_DIR=
```

`MYSQL_*` values initialize the MariaDB container.

`A2BP_DB_*` values are used by application containers.

For the bundled database container, app containers should use `A2BP_DB_HOST=db`
and internal database port `3306`. The host port is controlled by
`A2BP_DB_PORT`.

If you change database credentials after the first run, recreate the database volume or update MariaDB users manually. Docker only applies `MYSQL_*` initialization variables to a new empty volume.

`A2BP_SECRET_DIR` is optional. When it points at a writable directory, provider
setup stores sensitive provider credentials in files and writes `*_FILE`
references to `.env` instead of storing API secrets directly in `.env`.

## REST API

```text
A2BP_API_SERVICE_KEY=
A2BP_API_SERVICE_KEY_FILE=
```

The `/api/v1/*` REST endpoints use `Authorization: Bearer <service-key>`.
Set `A2BP_API_SERVICE_KEY` directly for local testing, or set
`A2BP_API_SERVICE_KEY_FILE` when the key is managed by a deployment secret store.
Responses use the v1 envelope:

```json
{
  "api_version": "v1",
  "success": true,
  "data": {}
}
```

Errors use `success: false` and an `error.code` value that clients can match
without parsing human-readable text.

## Schema Migrations

The web installer applies SQL files from `install/migrations` after the base
A2BillingPlus schema exists. Applied migration filenames are recorded in
`cc_schema_migrations`.

## VectaVoIP Provider Registration

```text
VECTAVOIP_API_BASE_URL=https://api.vectavoip.com
VECTAVOIP_INSTALL_KEY=
VECTAVOIP_INSTALLATION_ID=
VECTAVOIP_API_KEY=
VECTAVOIP_API_SECRET=
VECTAVOIP_API_KEY_FILE=
VECTAVOIP_API_SECRET_FILE=
VECTAVOIP_WEBHOOK_SECRET=
VECTAVOIP_WEBHOOK_SECRET_FILE=
```

`install.php` writes these values when automatic VectaVoIP registration
succeeds. The install key is generated locally, and the API key/secret are
returned by the VectaVoIP registration server.

When `VECTAVOIP_API_KEY_FILE` or `VECTAVOIP_API_SECRET_FILE` is set, runtime code
reads credentials from those files. Direct environment values are used when file
references are not configured.

`VECTAVOIP_WEBHOOK_SECRET` signs provider webhooks. Use
`VECTAVOIP_WEBHOOK_SECRET_FILE` when the secret is managed by a deployment secret
store.

## Payments

```text
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
BRAINTREE_MERCHANT_ID=
BRAINTREE_PUBLIC_KEY=
BRAINTREE_PRIVATE_KEY=
PAYMENT_CURRENCY=USD
```

Stripe is the launch target. Braintree stays optional and disabled unless a
deployment specifically needs it. Legacy direct-card gateways must not be
enabled in production because older flows accepted raw card and CVV values.

## Redis

```text
A2BP_REDIS_PORT=6379
```

Redis is intended for app-internal services. Do not expose it publicly in production.

## Asterisk

```text
A2BP_ASTERISK_AMI_PORT=5038
A2BP_ASTERISK_ARI_PORT=8088
A2BP_ASTERISK_SIP_PORT=5060
A2BP_ASTERISK_RTP_START=10000
A2BP_ASTERISK_RTP_END=10020

ASTERISK_AMI_USER=a2billing
ASTERISK_AMI_PASSWORD=a2billing-ami
ASTERISK_ARI_USER=a2billing
ASTERISK_ARI_PASSWORD=a2billing-ari
```

Use strong AMI and ARI credentials before exposing Asterisk beyond localhost or a private network.

## Migration Source

The app containers expose source migration environment values:

```text
A2BP_SOURCE_DSN=mysql:host=db;dbname=mya2billing;charset=utf8mb4
A2BP_SOURCE_USER=a2billinguser
A2BP_SOURCE_PASSWORD=a2billing
```

For migration from an existing A2Billing database, these should point at the old source database while `A2BP_DB_*` points at the A2BillingPlus target.

Run a customer migration dry-run:

```powershell
docker compose exec app php bin/migrate-a2billing.php --limit=100
```

Apply the migration:

```powershell
docker compose exec app php bin/migrate-a2billing.php --apply
```

Choose a scope when needed:

```powershell
docker compose exec app php bin/migrate-a2billing.php --scope=customers
docker compose exec app php bin/migrate-a2billing.php --scope=voip
docker compose exec app php bin/migrate-a2billing.php --scope=all
docker compose exec app php bin/migrate-a2billing.php --scope=cdrs --from=2026-05-01 --to=2026-06-01
```

The migration foundation copies/upserts `cc_card`, `cc_sip_buddies`, and
`cc_iax_buddies` rows using common columns between the source and target
schemas. It preserves source `id` where possible and updates an existing target
row when the same `id` or natural key already exists. For customers the natural
key is `username`; for SIP/IAX settings it is `name`.

CDR migration copies/upserts `cc_call` by `id` or `uniqueid` and supports
date-windowed runs with `--from` and `--to`.
