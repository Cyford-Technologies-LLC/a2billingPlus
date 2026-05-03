# Telephony and Asterisk

This is the launch baseline for A2BillingPlus telephony integration.

## Supported Asterisk Versions

Use Asterisk 20 LTS or Asterisk 22 LTS for launch installs. The official
Asterisk versions page lists Asterisk 20 LTS as fully supported until
2026-10-19 and security-maintained until 2027-10-19, and Asterisk 22 LTS as
fully supported until 2028-10-16 and security-maintained until 2029-10-16:
<https://docs.asterisk.org/About-the-Project/Asterisk-Versions/>

Asterisk 23 is a Standard release, not the launch target. Keep it for lab work
until the A2BillingPlus test matrix explicitly includes it.

## Required Runtime Checks

`AsteriskConfigCheckService` validates the operator-facing telephony baseline:

- Asterisk major version is 20 or 22.
- AMI and ARI credentials are present, non-default, and at least 16 characters.
- PJSIP is selected for new installs.
- Asterisk Realtime is enabled before scaling beyond a single static sandbox.

## PJSIP-First Trunk Example

```ini
[vectavoip]
type=endpoint
transport=transport-udp
context=from-vectavoip
disallow=all
allow=ulaw,alaw
outbound_auth=vectavoip-auth
aors=vectavoip

[vectavoip-auth]
type=auth
auth_type=userpass
username=VECTAVOIP_SIP_USERNAME
password=VECTAVOIP_SIP_PASSWORD

[vectavoip]
type=aor
contact=sip:sip.vectavoip.com
```

## Multi-Server Layout

For production scale, split these roles:

- Web/API nodes: PHP app, admin/customer portals, REST API.
- Database node: MariaDB primary with backups and private-network-only access.
- Telephony nodes: Asterisk/PJSIP, RTP, AMI/ARI restricted to app/private hosts.
- Redis/cache node: app-internal only.

Asterisk Realtime should read customer, trunk, route, and DID data from the
database so telephony nodes do not need a reload for every operational change.

## VectaVoIP Trunks and DIDs

`VectaVoIPProvisioningService` already creates the local provider row, default
`cc_trunk` row, default `VectaVoIP Retail` tariff plan, and staged DID inventory.

The launch workflow is:

1. Register the install with VectaVoIP.
2. Provision default provider/trunk/ratecard rows.
3. Sync available DIDs into `cc_vectavoip_did_inventory`.
4. Assign a DID to a customer in the A2BillingPlus database.
5. Route inbound PJSIP DID traffic to the customer context.

Customer-facing DID assignment and inbound route editing still need a dedicated
admin workflow, but the database staging path is in place.
