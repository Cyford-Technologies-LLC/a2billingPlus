# Web Installer

The root `install.php` file provides a browser-based installer for VectaVoIP
A2BillingPlus.

Open:

```text
http://localhost:8080/install.php
```

The installer can:

- Check PHP extensions and filesystem permissions.
- Ask whether to use the bundled MariaDB container or an existing database.
- Test MariaDB connectivity.
- Initialize the MariaDB schema when the target database is empty.
- Write `.env`.
- Update `a2billing.conf`.
- Start Docker Compose when the installer process has Docker CLI access.
- Set or create the first admin user so the default password is not left active.
- Generate an install key and register this install with VectaVoIP.
- Store returned VectaVoIP provider API credentials in `.env`.
- Create `install.lock`.

## VectaVoIP Registration

Automatic provider registration is optional. When enabled, the installer
generates a local install key and posts registration metadata to:

```text
https://api.VectaVoIP.com/v1/installations/register
```

The VectaVoIP server should return the installation ID, API key, and optional
API secret. Users should not need to manually sign up for provider API access.

When registration is enabled, the installer asks for provider registration
details: company name, domain, contact name, contact email, contact phone, and
optional install notes. The local admin details are used only as fallback values
when the provider contact fields are left blank.

For sandbox testing without the production VectaVoIP API, set the API base URL
to:

```text
http://localhost/api/sandbox
```

## Option 1: New MariaDB Container

Choose **Make a new MariaDB container** to use the bundled Compose `db` service.

The app containers should use:

```text
Database host: db
Database port: 3306
```

The host machine can reach that same database through:

```text
Database host: 127.0.0.1
Database port: 3307
```

Default sandbox values:

```text
Database name: mya2billing
Database user: a2billinguser
Database password: a2billing
```

The installer writes these values to `.env`. If Docker is available to PHP, it
can run `docker compose up -d --build`. In a normal containerized web app, PHP
usually cannot control Docker, so the installer will show the command to run on
the host.

## Option 2: Existing Database

Choose **Use an existing database** when MariaDB already exists outside this
Compose stack.

Provide:

```text
Database host
Database port
Database name
Database user
Database password
```

The database user must have enough permissions to create tables if schema
initialization is selected.

## After Install

1. Restart containers if `.env` or `a2billing.conf` changed.
2. Open `admin/Public/index.php`.
3. Log in with the admin user created by the installer.
4. Remove, rename, or block `install.php` before production use.

## Admin Password

The installer stores admin passwords using the current A2BillingPlus admin
password format: Whirlpool hash in `cc_ui_authen.pwd_encoded`.

Use a unique password of at least 12 characters.

## Reinstalling

If `install.lock` exists, review it before rerunning installation. For local
sandbox resets only:

```powershell
docker compose down -v
docker compose up -d --build
```

Do not delete production volumes unless you intend to remove the database.
