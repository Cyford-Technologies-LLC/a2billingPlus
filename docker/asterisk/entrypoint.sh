#!/usr/bin/env bash
set -euo pipefail

AMI_USER="${ASTERISK_AMI_USER:-a2billing}"
AMI_PASSWORD="${ASTERISK_AMI_PASSWORD:-a2billing-ami}"
ARI_USER="${ASTERISK_ARI_USER:-a2billing}"
ARI_PASSWORD="${ASTERISK_ARI_PASSWORD:-a2billing-ari}"

sed -i "s/__AMI_USER__/${AMI_USER}/g; s/__AMI_PASSWORD__/${AMI_PASSWORD}/g" /etc/asterisk/manager.conf
sed -i "s/__ARI_USER__/${ARI_USER}/g; s/__ARI_PASSWORD__/${ARI_PASSWORD}/g" /etc/asterisk/ari.conf

exec "$@"
