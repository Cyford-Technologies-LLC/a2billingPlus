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
PROJECT_ROOT="${A2BP_PROJECT_ROOT:-/opt/a2billingplus}"
AGI_WRAPPER_PATH="/var/lib/asterisk/agi-bin/a2billingplus-did"

DB_HOST="${RAW_DB_HOST}"
if [[ "${RAW_DB_HOST}" == *:* ]]; then
  DB_HOST="${RAW_DB_HOST%%:*}"
  DB_PORT="${RAW_DB_HOST##*:}"
fi

DB_CONFIG_PORT="${A2BP_DB_CONFIG_PORT:-${DB_PORT}}"
if [[ -z "${A2BP_DB_CONFIG_PORT+x}" && "${RAW_DB_HOST}" == "db" ]]; then
  DB_CONFIG_PORT="3306"
fi

ODBC_DRIVER_PATH="${A2BP_ODBC_DRIVER_PATH:-}"
if [[ -z "${ODBC_DRIVER_PATH}" ]]; then
  ODBC_DRIVER_PATH="$(find /usr -type f -name 'libmaodbc.so' 2>/dev/null | head -n 1 || true)"
fi
if [[ -z "${ODBC_DRIVER_PATH}" ]]; then
  ODBC_DRIVER_PATH="libmaodbc.so"
fi

mkdir -p "${ASTERISK_RUNTIME_CONFIG_DIR}" /etc/asterisk
mkdir -p "$(dirname "${AGI_WRAPPER_PATH}")"

cat >"${AGI_WRAPPER_PATH}" <<EOF
#!/usr/bin/env bash
exec /usr/bin/php "${PROJECT_ROOT}/AGI/a2billing.php" "\$@"
EOF
chmod 0755 "${AGI_WRAPPER_PATH}"

for source in "${DEFAULT_CONFIG_DIR}"/*.conf; do
  target="${ASTERISK_RUNTIME_CONFIG_DIR}/$(basename "${source}")"
  if [[ -f "${source}" && ! -f "${target}" ]]; then
    cp "${source}" "${target}"
  fi
done

cp "${ASTERISK_RUNTIME_CONFIG_DIR}"/*.conf /etc/asterisk/

if [[ -f "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf" ]]; then
  sed -i 's#AGI(a2billing/a2billing\.php,1,did)#AGI(/opt/a2billingplus/AGI/a2billing.php,1,did)#g' \
    "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf"
  sed -i 's#AGI(/opt/a2billingplus/AGI/a2billing\.php,1,did)#AGI(/var/lib/asterisk/agi-bin/a2billingplus-did,1,did)#g' \
    "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf"
  cp "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf" /etc/asterisk/extensions.conf
fi

mkdir -p /var/log/a2billing /var/run/a2billing /var/log/asterisk/cdr-csv

if [[ -f "${PROJECT_ROOT}/a2billing.conf" ]]; then
  cp "${PROJECT_ROOT}/a2billing.conf" /etc/a2billing.conf
  sed -i \
    -e "s/^hostname = .*/hostname = ${RAW_DB_HOST}/" \
    -e "s/^port = .*/port = ${DB_CONFIG_PORT}/" \
    -e "s/^user = .*/user = ${DB_USER}/" \
    -e "s/^password = .*/password = ${DB_PASSWORD}/" \
    -e "s/^dbname = .*/dbname = ${DB_NAME}/" \
    -e "s/^dbtype = .*/dbtype = mysql/" \
    /etc/a2billing.conf
fi

if [[ -f "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf" ]] && ! grep -q '^\[from-pstn\]' "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf"; then
  cat >>"${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf" <<'EOF'

[from-pstn]
exten => s,1,NoOp(Inbound PSTN call without URI user)
 same => n,AGI(/var/lib/asterisk/agi-bin/a2billingplus-did,1,did)
 same => n,Hangup()

exten => _+X.,1,NoOp(Inbound PSTN DID call to ${EXTEN})
 same => n,AGI(/var/lib/asterisk/agi-bin/a2billingplus-did,1,did)
 same => n,Hangup()

exten => _X.,1,NoOp(Inbound PSTN DID call to ${EXTEN})
 same => n,AGI(/var/lib/asterisk/agi-bin/a2billingplus-did,1,did)
 same => n,Hangup()
EOF
  cp "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf" /etc/asterisk/extensions.conf
fi

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
Port=${DB_CONFIG_PORT}
User=${DB_USER}
Password=${DB_PASSWORD}
OPTION=3
EOF
cp "${ASTERISK_RUNTIME_CONFIG_DIR}/odbc.ini" /etc/odbc.ini

cat >/etc/odbcinst.ini <<EOF
[MariaDB Unicode]
Driver=${ODBC_DRIVER_PATH}
Description=MariaDB Connector/ODBC(Unicode)
Threading=0
UsageCount=1
EOF

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
ps_endpoint_id_ips => odbc,asterisk
ps_registrations => odbc,asterisk
EOF
  cat >"${ASTERISK_RUNTIME_CONFIG_DIR}/sorcery.conf" <<'EOF'
[res_pjsip]
endpoint=realtime,ps_endpoints
auth=realtime,ps_auths
aor=realtime,ps_aors

[res_pjsip_endpoint_identifier_ip]
identify=realtime,ps_endpoint_id_ips

[res_pjsip_outbound_registration]
registration=realtime,ps_registrations
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
