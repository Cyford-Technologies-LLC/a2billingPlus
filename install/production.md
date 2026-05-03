# Production Install

This is a production-oriented checklist. Do not run production with the sandbox defaults.

## Minimum Requirements

- Linux host with Docker Engine and Compose v2
- TLS termination through a reverse proxy or load balancer
- Persistent volume backup strategy
- External monitoring for app, database, disk, and queue health
- Firewall rules for exposed HTTP, SIP, RTP, AMI, and ARI ports

## Required Changes Before Production

Edit `.env` and change:

```text
MYSQL_ROOT_PASSWORD
MYSQL_PASSWORD
A2BP_DB_PASSWORD
ASTERISK_AMI_PASSWORD
ASTERISK_ARI_PASSWORD
```

Also change the default admin password after first login.

## Recommended Production Layout

Use separate hosts or managed services where possible:

- Web app containers
- MariaDB primary database
- Redis
- Asterisk
- Reverse proxy with TLS
- Backup storage

For a single-host production deployment, keep MariaDB and app data on durable volumes and back them up regularly.

## Start Production Containers

For the base web and database stack:

```bash
docker compose up -d --build
```

For Asterisk:

```bash
docker compose --profile asterisk up -d --build asterisk
```

## Database Backups

Use the full [Backup and Restore](backup-restore.md) workflow before production
maintenance, migrations, or upgrades.

Create a quick database backup outside the container volume:

```bash
docker compose exec -T db mariadb-dump -uroot -p"$MYSQL_ROOT_PASSWORD" mya2billing > backup-mya2billing.sql
```

Restore:

```bash
docker compose exec -T db mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" mya2billing < backup-mya2billing.sql
```

## Security Checklist

- Do not expose MariaDB to the public internet.
- Do not expose Redis to the public internet.
- Restrict AMI and ARI access by network.
- Use TLS for all web access.
- Rotate default passwords.
- Keep payment credentials out of the repository.
- Keep customer exports and migration snapshots out of public artifacts.
- Run regular backups and restore tests.

## Upgrade Checklist

Use the full [Upgrade Workflow](upgrade.md) for staged upgrades and rollback.

Before upgrading containers or code:

```bash
docker compose ps
docker compose exec -T app php -v
docker compose exec -T db mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT VERSION();"
```

Then:

1. Back up MariaDB.
2. Pull or deploy the new code.
3. Rebuild containers.
4. Run PHP lint/tests/crawls in a staging environment.
5. Promote to production.
