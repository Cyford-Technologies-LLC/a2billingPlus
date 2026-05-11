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
PJSIP_REALM="${A2BP_ASTERISK_REALM:-sip.vectavoip.com}"
PJSIP_USER_AGENT="${A2BP_ASTERISK_USER_AGENT:-A2BillingPlus Sandbox}"
PJSIP_IDENTIFIER_ORDER="${A2BP_ASTERISK_IDENTIFIER_ORDER:-auth_username,username,ip,anonymous}"
ODBC_DRIVER_PATH="${A2BP_ODBC_DRIVER_PATH:-}"
PROJECT_ROOT="${A2BP_PROJECT_ROOT:-/opt/a2billingplus}"
AGI_WRAPPER_PATH="/var/lib/asterisk/agi-bin/a2billingplus-agi"
LEGACY_DID_AGI_WRAPPER_PATH="/var/lib/asterisk/agi-bin/a2billingplus-did"

DB_HOST="${RAW_DB_HOST}"
if [[ "${RAW_DB_HOST}" == *:* ]]; then
  DB_HOST="${RAW_DB_HOST%%:*}"
  DB_PORT="${RAW_DB_HOST##*:}"
fi

mkdir -p "${ASTERISK_RUNTIME_CONFIG_DIR}" /etc/asterisk
mkdir -p "$(dirname "${AGI_WRAPPER_PATH}")"

cat >"${AGI_WRAPPER_PATH}" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

project_root="${A2BP_PROJECT_ROOT:-/opt/a2billingplus}"
php_bin="${A2BP_PHP_BIN:-/usr/bin/php}"
script="${A2BP_AGI_SCRIPT:-${project_root}/AGI/a2billing.php}"
log_file="${A2BP_AGI_ERROR_LOG:-/var/log/a2billing/a2billing_agi_error.log}"

mkdir -p "$(dirname "${log_file}")"

if [[ ! -x "${php_bin}" ]]; then
  printf '[%s] PHP binary not executable: %s\n' "$(date -Is)" "${php_bin}" >>"${log_file}"
  exit 1
fi

if [[ ! -r "${script}" ]]; then
  printf '[%s] AGI script not readable: %s\n' "$(date -Is)" "${script}" >>"${log_file}"
  exit 1
fi

exec "${php_bin}" -d display_errors=stderr -d log_errors=1 -d error_log="${log_file}" "${script}" "$@" 2>>"${log_file}"
EOF
chmod 0755 "${AGI_WRAPPER_PATH}"
ln -sf "$(basename "${AGI_WRAPPER_PATH}")" "${LEGACY_DID_AGI_WRAPPER_PATH}"

if [[ -z "${ODBC_DRIVER_PATH}" ]]; then
  for candidate in \
    /usr/lib/x86_64-linux-gnu/odbc/libmaodbc.so \
    /usr/lib/aarch64-linux-gnu/odbc/libmaodbc.so \
    /usr/lib/odbc/libmaodbc.so; do
    if [[ -f "${candidate}" ]]; then
      ODBC_DRIVER_PATH="${candidate}"
      break
    fi
  done
fi

if [[ -z "${ODBC_DRIVER_PATH}" ]]; then
  ODBC_DRIVER_PATH="MariaDB Unicode"
  echo "WARNING: MariaDB ODBC driver file was not found; falling back to '${ODBC_DRIVER_PATH}'." >&2
else
  cat >"${ASTERISK_RUNTIME_CONFIG_DIR}/odbcinst.ini" <<EOF
[MariaDB Unicode]
Description=MariaDB ODBC Driver
Driver=${ODBC_DRIVER_PATH}
Setup=${ODBC_DRIVER_PATH}
UsageCount=1
EOF
  cp "${ASTERISK_RUNTIME_CONFIG_DIR}/odbcinst.ini" /etc/odbcinst.ini
fi

export ODBCSYSINI=/etc
export ODBCINI=/etc/odbc.ini

for source in "${DEFAULT_CONFIG_DIR}"/*.conf; do
  target="${ASTERISK_RUNTIME_CONFIG_DIR}/$(basename "${source}")"
  if [[ -f "${source}" && ! -f "${target}" ]]; then
    cp "${source}" "${target}"
  fi
done

cp "${ASTERISK_RUNTIME_CONFIG_DIR}"/*.conf /etc/asterisk/

if [[ -f "${ASTERISK_RUNTIME_CONFIG_DIR}/logger.conf" ]]; then
  if ! grep -Eq '^[[:space:]]*security\.log[[:space:]]*=>' "${ASTERISK_RUNTIME_CONFIG_DIR}/logger.conf"; then
    if grep -Eq '^[[:space:]]*\[logfiles\]' "${ASTERISK_RUNTIME_CONFIG_DIR}/logger.conf"; then
      sed -i '/^[[:space:]]*\[logfiles\]/a security.log => security' "${ASTERISK_RUNTIME_CONFIG_DIR}/logger.conf"
    else
      cat >>"${ASTERISK_RUNTIME_CONFIG_DIR}/logger.conf" <<'EOF'

[logfiles]
security.log => security
EOF
    fi
  fi
  cp "${ASTERISK_RUNTIME_CONFIG_DIR}/logger.conf" /etc/asterisk/logger.conf
fi

if [[ -f "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf" ]]; then
  sed -i \
    -e 's#AGI(a2billing/a2billing\.php#AGI(/var/lib/asterisk/agi-bin/a2billingplus-agi#g' \
    -e 's#AGI(/usr/share/asterisk/agi-bin/a2billing/a2billing\.php#AGI(/var/lib/asterisk/agi-bin/a2billingplus-agi#g' \
    -e 's#AGI(/opt/a2billingplus/AGI/a2billing\.php#AGI(/var/lib/asterisk/agi-bin/a2billingplus-agi#g' \
    -e 's#AGI(/var/lib/asterisk/agi-bin/a2billingplus-did#AGI(/var/lib/asterisk/agi-bin/a2billingplus-agi#g' \
    -e 's#^exten => _+X\.,1,Goto(a2billing-tenant,${EXTEN},1)#exten => _+X.,1,Goto(a2billing-tenant,${EXTEN:1},1)#' \
    "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf"
  sed -i '/^exten => _+X\.,1,NoOp(Tenant outbound E\.164:/,/^$/ {
    s#^ same => n,AGI(/var/lib/asterisk/agi-bin/a2billingplus-agi,1)# same => n,Goto(a2billing-tenant,${EXTEN:1},1)#
    /^ same => n,Hangup()$/d
  }' "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf"
  cp "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf" /etc/asterisk/extensions.conf
fi

if [[ -f "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf" ]] && ! grep -q '^\[from-pstn\]' "${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf"; then
  cat >>"${ASTERISK_RUNTIME_CONFIG_DIR}/extensions.conf" <<'EOF'

[from-pstn]
exten => s,1,NoOp(Inbound PSTN call without URI user)
 same => n,AGI(/var/lib/asterisk/agi-bin/a2billingplus-agi,1,did)
 same => n,Hangup()

exten => _+X.,1,NoOp(Inbound PSTN DID call to ${EXTEN})
 same => n,AGI(/var/lib/asterisk/agi-bin/a2billingplus-agi,1,did)
 same => n,Hangup()

exten => _X.,1,NoOp(Inbound PSTN DID call to ${EXTEN})
 same => n,AGI(/var/lib/asterisk/agi-bin/a2billingplus-agi,1,did)
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
Driver=${ODBC_DRIVER_PATH}
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
