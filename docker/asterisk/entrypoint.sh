#!/usr/bin/env bash
set -euo pipefail

AMI_USER="${ASTERISK_AMI_USER:-a2billing}"
AMI_PASSWORD="${ASTERISK_AMI_PASSWORD:-a2billing-ami}"
ARI_USER="${ASTERISK_ARI_USER:-a2billing}"
ARI_PASSWORD="${ASTERISK_ARI_PASSWORD:-a2billing-ari}"
RAW_DB_HOST="${A2BP_DB_HOST:-db}"
DB_PORT="${A2BP_DB_PORT:-3306}"
DB_NAME="${A2BP_DB_NAME:-mya2billing}"
DB_USER="${A2BP_DB_USER:-a2billinguser}"
DB_PASSWORD="${A2BP_DB_PASSWORD:-a2billing}"
REALTIME_ENABLED="${A2BP_ASTERISK_REALTIME:-yes}"

DB_HOST="${RAW_DB_HOST}"
if [[ "${RAW_DB_HOST}" == *:* ]]; then
  DB_HOST="${RAW_DB_HOST%%:*}"
  DB_PORT="${RAW_DB_HOST##*:}"
fi

sed -i "s/__AMI_USER__/${AMI_USER}/g; s/__AMI_PASSWORD__/${AMI_PASSWORD}/g" /etc/asterisk/manager.conf
sed -i "s/__ARI_USER__/${ARI_USER}/g; s/__ARI_PASSWORD__/${ARI_PASSWORD}/g" /etc/asterisk/ari.conf

cat >/etc/odbc.ini <<EOF
[asterisk]
Driver=MariaDB Unicode
Server=${DB_HOST}
Database=${DB_NAME}
Port=${DB_PORT}
User=${DB_USER}
Password=${DB_PASSWORD}
OPTION=3
EOF

cat >/etc/asterisk/res_odbc.conf <<EOF
[asterisk]
enabled => yes
pre-connect => yes
dsn => asterisk
username => ${DB_USER}
password => ${DB_PASSWORD}
EOF

if [[ "${REALTIME_ENABLED,,}" =~ ^(1|yes|true|on)$ ]]; then
  cat >/etc/asterisk/extconfig.conf <<'EOF'
[settings]
ps_endpoints => odbc,asterisk
ps_auths => odbc,asterisk
ps_aors => odbc,asterisk
EOF
  cat >/etc/asterisk/sorcery.conf <<'EOF'
[res_pjsip]
endpoint=realtime,ps_endpoints
auth=realtime,ps_auths
aor=realtime,ps_aors
EOF
else
  cat >/etc/asterisk/extconfig.conf <<'EOF'
[settings]
EOF
  cat >/etc/asterisk/sorcery.conf <<'EOF'
EOF
fi

exec "$@"
