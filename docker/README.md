# A2BillingPlus Docker Stack

## Start

Copy the example environment file once:

```bash
cp .env.example .env
```

Start the core web/database/cache stack:

```bash
docker compose up -d --build app db redis
```

Run the PHP 8.4 compatibility web container:

```bash
docker compose --profile php84 up -d --build app84
```

Start the Asterisk sandbox too:

```bash
docker compose --profile asterisk up -d --build
```

Optional tools:

```bash
docker compose --profile tools up -d mailpit
```

## URLs and Ports

- Web app: http://localhost:8080
- PHP 8.4 compatibility web app: http://localhost:8084
- MariaDB: `127.0.0.1:3307`
- Redis: `127.0.0.1:6379`
- Asterisk AMI: `127.0.0.1:5038`
- Asterisk ARI: http://localhost:8088/asterisk/ari

## Default Credentials

Database:

- Database: `mya2billing`
- User: `a2billinguser`
- Password: `a2billing`

Asterisk sandbox:

- AMI user: `a2billing`
- AMI password: `a2billing-ami`
- ARI user: `a2billing`
- ARI password: `a2billing-ari`
- PJSIP endpoint: `1001`
- PJSIP password: `1001`

## Useful Commands

```bash
docker compose ps
docker compose logs -f app
docker compose exec app php -v
docker compose exec app php development/migration/a2bp-migrate.php inspect --report development/migration/reports/sandbox-inspect.json
docker compose exec db mariadb -ua2billinguser -pa2billing mya2billing
docker compose exec asterisk asterisk -rx "core show version"
```

## Reset Local Data

This removes local database and Asterisk volumes:

```bash
docker compose down -v
```
