#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p /var/log/a2billing /var/run/a2billing

if [ -f a2billing.conf ]; then
  cp a2billing.conf /etc/a2billing.conf
  sed -i \
    -e "s/^hostname = .*/hostname = ${A2BP_DB_HOST:-db}/" \
    -e "s/^port = .*/port = 3306/" \
    -e "s/^user = .*/user = ${A2BP_DB_USER:-a2billinguser}/" \
    -e "s/^password = .*/password = ${A2BP_DB_PASSWORD:-a2billing}/" \
    -e "s/^dbname = .*/dbname = ${A2BP_DB_NAME:-mya2billing}/" \
    -e "s/^dbtype = .*/dbtype = mysql/" \
    /etc/a2billing.conf
fi

if [ -f composer.json ] && [ ! -d vendor ]; then
  echo "Installing Composer dependencies for container runtime..."
  composer install --no-interaction --prefer-dist --ignore-platform-req=php || \
    composer install --no-interaction --prefer-dist --ignore-platform-reqs
fi

exec "$@"
