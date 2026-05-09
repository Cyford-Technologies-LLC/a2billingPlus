# VectaVoIP API Deployment

This package deploys the VectaVoIP provider API at `https://api.vectavoip.com`.
It serves only:

- `/v1/*`
- `/api/vectavoip/*`
- `/health.php`

All other paths return `404` from the Apache vhost.

## Requirements

- Linux host with Docker Engine and Compose v2.
- DNS `A` or `CNAME` for `api.vectavoip.com` pointing at the host or load balancer.
- TLS termination at a reverse proxy, load balancer, or CDN.
- Production secrets outside git.

## Files

- `.env.production.example`: copy to `.env.production` on the server.
- `docker-compose.vectavoip-api.yml`: API-only app and MariaDB.
- `apache-vhost.conf`: restricts the domain to VectaVoIP API paths and maps the public `/v1/*` contract to the packaged PHP entrypoints.
- `smoke-vectavoip-api.ps1`: registration/status/rate/rotation/account/DID/SMS smoke test.

The provider API supports a configurable upstream DID provider. Set
`VECTAVOIP_DEFAULT_UPSTREAM_PROVIDER=twilio` to make `/v1/dids/purchase` buy
numbers from Twilio by default, or call `POST /v1/upstream/default` with
`{"provider":"twilio"}` after authenticating with the VectaVoIP API key and
secret. A DID purchase request can also override the default with
`upstream_provider`.

Twilio DID purchasing requires:

```text
TWILIO_ACCOUNT_SID
TWILIO_API_KEY
TWILIO_API_SECRET
```

`TWILIO_AUTH_TOKEN` may be used instead of `TWILIO_API_SECRET` for deployments
that authenticate with the account SID plus auth token. Optional defaults:

```text
TWILIO_DEFAULT_VOICE_URL
TWILIO_DEFAULT_SMS_URL
TWILIO_BYOC_TRUNK_SID
```

## Deploy

```bash
cd deploy/vectavoip-api
cp .env.production.example .env.production
vi .env.production
docker compose -f docker-compose.vectavoip-api.yml up -d --build
docker compose -f docker-compose.vectavoip-api.yml exec -T app php bin/apply-install-migrations.php
```

The migration command can bootstrap the database when `.env.production`
contains admin credentials:

```text
A2BP_DB_ADMIN_USER=root
A2BP_DB_ADMIN_PASSWORD=...
A2BP_DB_ADMIN_DSN=mysql:host=db;charset=utf8mb4
A2BP_DB_USER_HOST=%
```

With those values set, it creates `A2BP_DB_NAME`, creates or updates
`A2BP_DB_USER`, grants privileges, and then applies the A2BillingPlus
tables. If the admin variables are omitted, it behaves like a normal
migration runner and expects the application database/user to already exist.

Put HTTPS in front of the app service. The app listens on
`A2BP_HTTP_PORT`, default `8080`.

## Reverse Proxy Example

```nginx
server {
    listen 443 ssl http2;
    server_name api.vectavoip.com;

    ssl_certificate /etc/letsencrypt/live/api.vectavoip.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.vectavoip.com/privkey.pem;

    location / {
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_pass http://127.0.0.1:8080;
    }
}
```

## Smoke Test

From a workstation with PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\vectavoip-api\smoke-vectavoip-api.ps1 -BaseUrl https://api.vectavoip.com
```

For local server testing before DNS/TLS:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\vectavoip-api\smoke-vectavoip-api.ps1 -BaseUrl http://SERVER_IP:8080
```

The smoke test now requires at least one available DID in
`cc_vectavoip_did_inventory`; if none are available, sync or seed provider DID
inventory before treating the deployment as sellable.

## DNS/TLS Checklist

- `api.vectavoip.com` resolves to the deployment edge.
- Port `443/tcp` is open to the internet.
- Port `8080/tcp` is private or restricted to the reverse proxy.
- MariaDB is not exposed publicly.
- `.env.production` is not committed.
- Secrets use file-backed values where possible.
