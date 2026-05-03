# VectaVoIP Provider Registration

The installer can automatically register a new A2BillingPlus install with
VectaVoIP. Manual API signup is not required when this option is enabled.

## Production Endpoint

```text
POST https://api.vectavoip.com/v1/installations/register
Content-Type: application/json
Accept: application/json
```

Request body:

```json
{
  "install_key": "a2bp_generated_local_key",
  "company_name": "Customer Company",
  "company_domain": "example.com",
  "contact_name": "Jane Admin",
  "contact_email": "jane@example.com",
  "contact_phone": "+15551234567",
  "details": "Sandbox install migrating from A2Billing",
  "app_name": "A2BillingPlus",
  "app_version": "0.1.0-alpha"
}
```

Successful response:

```json
{
  "message": "Registration completed.",
  "installation_id": "inst_123",
  "api_key": "provider_api_key",
  "api_secret": "optional_provider_api_secret",
  "metadata": {
    "region": "us"
  }
}
```

The installer stores the returned values in `.env` as:

```text
VECTAVOIP_INSTALL_KEY=
VECTAVOIP_INSTALLATION_ID=
VECTAVOIP_API_KEY=
VECTAVOIP_API_SECRET=
```

Production-compatible endpoints are implemented in this repository under:

```text
POST /api/vectavoip/v1/installations/register.php
GET  /api/vectavoip/v1/installations/status.php
POST /api/vectavoip/v1/credentials/rotate.php
GET  /api/vectavoip/v1/rates/preview.php
```

Use this local base URL to exercise the production-compatible provider API
before deploying `api.vectavoip.com`:

```text
http://localhost:8080/api/vectavoip
```

Registration records are stored in `cc_vectavoip_installations`.

Authenticated provider requests use:

```text
Authorization: Bearer <api_key>
X-VectaVoIP-Secret: <api_secret>
```

Credential rotation returns the same API key and a new API secret. The old
secret stops working immediately after rotation.

## Sandbox Endpoint

For local testing before `api.vectavoip.com` is live, set the installer API base
URL to this value when the installer runs inside the Docker app container:

```text
http://localhost/api/sandbox
```

From the host machine or browser, the same sandbox endpoint is reachable at:

```text
http://localhost:8080/api/sandbox
```

The sandbox endpoint is:

```text
POST http://localhost:8080/api/sandbox/v1/installations/register
```

It returns deterministic sandbox credentials based on the install key, company
name, and contact email. Do not use sandbox credentials for production provider
access.

## Local Provider API Actions

The local A2BillingPlus provider API can also register an install. This is the
backend path intended for future admin UI screens.

Provider status:

```text
POST /api/v1/providers.php
```

```json
{
  "action": "provider_status",
  "provider": "vectavoip"
}
```

Register install:

```json
{
  "action": "register_install",
  "provider": "vectavoip",
  "base_url": "http://localhost/api/sandbox",
  "install_key": "a2bp_optional_existing_key",
  "company_name": "Customer Company",
  "company_domain": "example.com",
  "contact_name": "Jane Admin",
  "contact_email": "jane@example.com",
  "contact_phone": "+15551234567",
  "details": "Sandbox install",
  "app_name": "A2BillingPlus",
  "app_version": "0.1.0-alpha"
}
```

If `install_key` is omitted, the API generates one.

Preview provider rates through the local API:

```json
{
  "action": "preview_rates",
  "provider": "vectavoip",
  "base_url": "http://localhost/api/sandbox",
  "api_key": "sandbox_key",
  "rate_deck": "retail",
  "currency": "USD",
  "filters": {
    "destination": "US"
  }
}
```

The sandbox provider endpoint behind this action is:

```text
GET /api/sandbox/v1/rates/preview?rate_deck=retail&currency=USD
```

Dry-run import of the previewed rows into an A2BillingPlus ratecard:

```json
{
  "action": "import_preview_rates",
  "provider": "vectavoip",
  "base_url": "http://localhost/api/sandbox",
  "api_key": "sandbox_key",
  "target_ratecard_id": "5",
  "rate_deck": "retail",
  "currency": "USD",
  "dry_run": "1",
  "update_existing": "0"
}
```

Set `"dry_run": "0"` to write the rows into `cc_ratecard`. The first import
mapping writes provider `prefix`, `rate`, `buyrate`, `increment`, and
`destination` into the existing A2BillingPlus ratecard fields and tags rows as
`VectaVoIP:<rate_deck>`.

Repeated imports skip existing rows with the same ratecard, prefix, and
VectaVoIP tag by default. Set `"update_existing": "1"` to update those rows
instead.

Each dry-run or write import records an audit row in `cc_provider_import_log`.
The Provider Setup admin page shows the most recent import records.

Migration file:

```text
install/migrations/20260503_provider_import_log.sql
```

## Signed Provider Webhooks

The local A2BillingPlus provider webhook receiver is:

```text
POST /api/v1/provider-webhooks.php?provider=vectavoip
```

Required headers:

```text
X-VectaVoIP-Timestamp: <unix_timestamp>
X-VectaVoIP-Signature: <hex_hmac_sha256>
```

The signature is:

```text
hex_hmac_sha256(timestamp + "." + raw_json_body, VECTAVOIP_WEBHOOK_SECRET)
```

Supported event types currently recorded:

- `rate_deck.updated`
- `account.updated`

Webhook events are stored in `cc_provider_webhook_event` and deduplicated by
`provider + event_id`.

Migration file:

```text
install/migrations/20260503_provider_webhook_event.sql
```

## Provider Provisioning

A2BillingPlus includes a VectaVoIP provisioning service for local database setup.
The service can:

- Ensure a `cc_provider` row for VectaVoIP.
- Ensure a default `cc_trunk` row using `SIP` and `sip.vectavoip.com`.
- Ensure a default `cc_tariffplan` named `VectaVoIP Retail`.
- Sync provider DID inventory into `cc_vectavoip_did_inventory`.

The DID inventory table is provider-owned staging data. Customer assignment and
routing should be added as a separate explicit workflow.

Migration file:

```text
install/migrations/20260503_vectavoip_did_inventory.sql
```
