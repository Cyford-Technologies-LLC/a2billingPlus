#!/usr/bin/env bash
set -euo pipefail

AMI_USER="${ASTERISK_AMI_USER:-a2billing}"
AMI_PASSWORD="${ASTERISK_AMI_PASSWORD:-a2billing-ami}"
ARI_USER="${ASTERISK_ARI_USER:-a2billing}"
ARI_PASSWORD="${ASTERISK_ARI_PASSWORD:-a2billing-ari}"
ASTERISK_RUNTIME_CONFIG_DIR="${ASTERISK_RUNTIME_CONFIG_DIR:-/etc/cyford/asterisk}"
DEFAULT_CONFIG_DIR="/opt/a2bp-asterisk-defaults"
RAW_DB_HOST="${A2BP_DB_HOST:-db}"
DB_PORT="${A2BP_DB_PORT:-3306}"
DB_NAME="${A2BP_DB_NAME:-mya2billing}"
DB_USER="${A2BP_DB_USER:-a2billinguser}"
DB_PASSWORD="${A2BP_DB_PASSWORD:-a2billing}"
REALTIME_ENABLED="${A2BP_ASTERISK_REALTIME:-yes}"
PJSIP_REALM="${A2BP_ASTERISK_REALM:-asterisk}"
PJSIP_USER_AGENT="${A2BP_ASTERISK_USER_AGENT:-A2BillingPlus Sandbox}"
PJSIP_IDENTIFIER_ORDER="${A2BP_ASTERISK_IDENTIFIER_ORDER:-auth_username,username,ip,anonymous}"

DB_HOST="${RAW_DB_HOST}"
if [[ "${RAW_DB_HOST}" == *:* ]]; then
  DB_HOST="${RAW_DB_HOST%%:*}"
  DB_PORT="${RAW_DB_HOST##*:}"
fi

mkdir -p "${ASTERISK_RUNTIME_CONFIG_DIR}" /etc/asterisk

for source in "${DEFAULT_CONFIG_DIR}"/*.conf; do
  target="${ASTERISK_RUNTIME_CONFIG_DIR}/$(basename "${source}")"
  if [[ -f "${source}" && ! -f "${target}" ]]; then
    cp "${source}" "${target}"
  fi
done

cp "${ASTERISK_RUNTIME_CONFIG_DIR}"/*.conf /etc/asterisk/

sed -i "s/__AMI_USER__/${AMI_USER}/g; s/__AMI_PASSWORD__/${AMI_PASSWORD}/g" /etc/asterisk/manager.conf
sed -i "s/__ARI_USER__/${ARI_USER}/g; s/__ARI_PASSWORD__/${ARI_PASSWORD}/g" /etc/asterisk/ari.conf

if [[ -f "${ASTERISK_RUNTIME_CONFIG_DIR}/pjsip.conf" ]]; then
  sed -i \
    -e "s/^user_agent=.*/user_agent=${PJSIP_USER_AGENT}/" \
    -e "s/^default_realm=.*/default_realm=${PJSIP_REALM}/" \
    -e "s/^endpoint_identifier_order=.*/endpoint_identifier_order=${PJSIP_IDENTIFIER_ORDER}/" \
    "${ASTERISK_RUNTIME_CONFIG_DIR}/pjsip.conf"
  if ! grep -q '^default_realm=' "${ASTERISK_RUNTIME_CONFIG_DIR}/pjsip.conf"; then
    sed -i '/^\[global\]/a default_realm='"${PJSIP_REALM}" "${ASTERISK_RUNTIME_CONFIG_DIR}/pjsip.conf"
  fi
  cp "${ASTERISK_RUNTIME_CONFIG_DIR}/pjsip.conf" /etc/asterisk/pjsip.conf
fi

cat >"${ASTERISK_RUNTIME_CONFIG_DIR}/odbc.ini" <<EOF
[asterisk]
Driver=MariaDB Unicode
Server=${DB_HOST}
Database=${DB_NAME}
Port=${DB_PORT}
User=${DB_USER}
Password=${DB_PASSWORD}
OPTION=3
EOF
cp "${ASTERISK_RUNTIME_CONFIG_DIR}/odbc.ini" /etc/odbc.ini

cat >"${ASTERISK_RUNTIME_CONFIG_DIR}/res_odbc.conf" <<EOF
[asterisk]
enabled => yes
pre-connect => yes
dsn => asterisk
username => ${DB_USER}
password => ${DB_PASSWORD}
EOF

if [[ "${REALTIME_ENABLED,,}" =~ ^(1|yes|true|on)$ ]]; then
  cat >"${ASTERISK_RUNTIME_CONFIG_DIR}/extconfig.conf" <<'EOF'
[settings]
ps_endpoints => odbc,asterisk
ps_auths => odbc,asterisk
ps_aors => odbc,asterisk
EOF
  cat >"${ASTERISK_RUNTIME_CONFIG_DIR}/sorcery.conf" <<'EOF'
[res_pjsip]
endpoint=realtime,ps_endpoints
auth=realtime,ps_auths
aor=realtime,ps_aors
EOF
else
  cat >"${ASTERISK_RUNTIME_CONFIG_DIR}/extconfig.conf" <<'EOF'
[settings]
EOF
  cat >"${ASTERISK_RUNTIME_CONFIG_DIR}/sorcery.conf" <<'EOF'
EOF
fi

cp "${ASTERISK_RUNTIME_CONFIG_DIR}/res_odbc.conf" /etc/asterisk/res_odbc.conf
cp "${ASTERISK_RUNTIME_CONFIG_DIR}/extconfig.conf" /etc/asterisk/extconfig.conf
cp "${ASTERISK_RUNTIME_CONFIG_DIR}/sorcery.conf" /etc/asterisk/sorcery.conf

exec "$@"
