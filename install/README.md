# VectaVoIP A2BillingPlus Install Guide

This directory contains public installation guides for A2BillingPlus.

Start here:

- [Web Installer](web-installer.md): browser-based `install.php` setup.
- [VectaVoIP Provider Registration](provider-registration.md): automatic provider signup contract and sandbox endpoint.
- [Sandbox Install](sandbox.md): local Docker setup for development and testing.
- [Production Install](production.md): production-oriented container deployment checklist.
- [Environment Reference](environment.md): required and optional `.env` variables.
- [Troubleshooting](troubleshooting.md): common install and runtime problems.

Migration CLI:

```powershell
docker compose exec app php bin/migrate-a2billing.php --scope=all --limit=100
```

## Current Container Layout

The default Docker stack provides:

- PHP/Apache app on `http://localhost:8080`
- Optional PHP 8.4 app on `http://localhost:8084`
- MariaDB on host port `3307`
- Redis on host port `6379`
- Optional Asterisk container with AMI, ARI, SIP, and RTP ports
- Optional Mailpit test mail server

Default sandbox database values:

- Database: `mya2billing`
- User: `a2billinguser`
- Password: `a2billing`
- Root password: `a2billing-root`

Default admin login:

- Username: `root`
- Password: `changepassword`

Change all defaults before using anything beyond a local sandbox.

VectaVoIP branding is project branding. A2Billing upstream copyright and license
notices must remain intact in source distributions.
