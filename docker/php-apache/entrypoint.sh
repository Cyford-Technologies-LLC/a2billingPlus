#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

load_env_file_if_unset() {
  local file="$1"
  local line key value
  [ -f "$file" ] || return 0

  while IFS= read -r line || [ -n "$line" ]; do
    line="${line%$'\r'}"
    case "$line" in
      ''|\#*) continue ;;
      *=*)
        key="${line%%=*}"
        value="${line#*=}"
        case "$key" in
          ''|*[!A-Za-z0-9_]*|[0-9]*) continue ;;
        esac
        if [ -z "${!key+x}" ]; then
          value="${value%"${value##*[![:space:]]}"}"
          value="${value%\"}"
          value="${value#\"}"
          value="${value%\'}"
          value="${value#\'}"
          export "$key=$value"
        fi
        ;;
    esac
  done < "$file"
}

load_env_file_if_unset .env
load_env_file_if_unset .env.local
load_env_file_if_unset .env.stripe

mkdir -p /var/log/a2billing /var/run/a2billing

if [ -f a2billing.conf ]; then
  cp a2billing.conf /etc/a2billing.conf
  sed -i \
    -e "s/^hostname = .*/hostname = ${A2BP_DB_HOST:-db}/" \
    -e "s/^port = .*/port = ${A2BP_DB_PORT:-3306}/" \
    -e "s/^user = .*/user = ${A2BP_DB_USER:-a2billinguser}/" \
    -e "s/^password = .*/password = ${A2BP_DB_PASSWORD:-a2billing}/" \
    -e "s/^dbname = .*/dbname = ${A2BP_DB_NAME:-mya2billing}/" \
    -e "s/^dbtype = .*/dbtype = mysql/" \
    /etc/a2billing.conf
fi

if [ -f composer.json ] && [ ! -f vendor/autoload.php ]; then
  mkdir -p /tmp/a2billingplus
  (
    flock 200
    if [ ! -f vendor/autoload.php ]; then
      echo "Installing Composer dependencies for container runtime..."
      composer install --no-interaction --prefer-dist --ignore-platform-req=php || \
        composer install --no-interaction --prefer-dist --ignore-platform-reqs
    fi
  ) 200>/tmp/a2billingplus/composer-install.lock
fi

if [ "${A2BP_SANDBOX_BOOTSTRAP:-0}" = "1" ] && [ -f bin/sandbox-bootstrap.php ]; then
  php bin/sandbox-bootstrap.php || true
fi

exec "$@"
