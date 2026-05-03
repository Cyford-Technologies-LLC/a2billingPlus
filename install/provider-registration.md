# VectaVoIP Provider Registration

The installer can automatically register a new A2BillingPlus install with
VectaVoIP. Manual API signup is not required when this option is enabled.

## Production Endpoint

```text
POST https://api.VectaVoIP.com/v1/installations/register
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

## Sandbox Endpoint

For local testing before `api.VectaVoIP.com` is live, set the installer API base
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
