# VectaVoIP Customer Provisioning Checkpoints - 2026-05-11

## Goal

ZeroAI CRM and other approved customer applications must be able to create a
VectaVoIP customer account and receive usable phone service without an admin
manually wiring caller ID, DID assignment, SIP/PJSIP records, call plans,
ratecards, trunks, or SMS routing after each signup.

This list is the delivery checkpoint for that flow. A checkpoint is complete
only when the API path, database state, admin UI state, and Asterisk behavior
all agree.

## Required End-to-End Flow

- Customer app stores VectaVoIP base URL, service key, and app provisioning
  token.
- Customer app calls an app-scoped provisioning endpoint with `external_id`
  such as `crm_{org_id}`.
- VectaVoIP validates the app token and limits provisioning to that app scope.
- VectaVoIP creates or finds the `cc_card` customer by `external_id`.
- VectaVoIP assigns the default provider package, call plan, ratecard, trunk,
  and outbound CID policy.
- VectaVoIP creates SIP/PJSIP credentials and exposes them to the customer app.
- Customer app can search, purchase, assign, release, and list DIDs.
- DID assignment automatically enables inbound voice, inbound SMS, outbound SMS,
  and valid outbound caller ID for that customer.
- Outbound voice calls rate, route, bridge through the selected trunk, and write
  CDR/billing rows.
- Inbound voice reaches the DID destination or customer routing policy.
- Inbound SMS is stored locally and delivered to the customer app webhook.
- Outbound SMS is authorized only from assigned DIDs and is recorded with the
  upstream provider reference.

## Security And Ownership

- [ ] Create app-scoped provisioning credentials, separate from the global
  service key.
- [ ] Store app permissions in the database, with env values only as bootstrap
  or break-glass fallback.
- [ ] Define permissions for `customer:create`, `customer:read`,
  `did:purchase`, `did:assign`, `did:release`, `sms:send`,
  `sip:credential:read`, and `billing:read`.
- [ ] Require every provisioning request to include `external_id` and app id.
- [ ] Enforce idempotency on `(app_id, external_id)` so repeated CRM saves do
  not create duplicate customers.
- [ ] Prevent one app from reading or modifying a customer provisioned by
  another app.
- [ ] Audit every app action with app id, customer id, external id, source IP,
  and request id.
- [ ] Add clear API errors for invalid token, missing permission, duplicate
  external id, and cross-app access.

## Account Provisioning

- [x] Add `cc_card.external_id` with a unique lookup path.
- [x] Support `GET /api/v1/customers.php?external_id=...`.
- [ ] Add a first-class provisioning endpoint that creates the customer, not
  just the legacy customer REST row.
- [ ] Choose provider package defaults from database settings, not hardcoded
  PHP constants.
- [ ] Assign `cc_card.tariff` to the default call plan when the customer is
  created.
- [ ] Set safe defaults for customer fields that fail under MariaDB strict mode.
- [ ] Return a single provisioning response containing `customer_id`,
  `external_id`, `account_number`, `sip_username`, `sip_password`,
  `call_plan_id`, `ratecard_id`, and assigned DIDs.
- [ ] Expose an account recovery endpoint for customer apps that lost their
  local `vt_customer_id`.
- [ ] Add API tests for create, duplicate create, lookup by external id,
  permission denied, and cross-app denied.

## SIP And PJSIP

- [ ] Create the SIP account automatically when the customer is provisioned.
- [ ] Mirror SIP account data to PJSIP realtime when the active channel driver
  is PJSIP.
- [ ] Ensure PJSIP `accountcode` is the A2Billing account number, not the
  endpoint id.
- [ ] Preserve admin-configured defaults from `cc_config` and SIP/PJSIP form
  defaults for context, codecs, NAT, direct media, RTP, DTMF, and qualify.
- [ ] Return SIP/PJSIP credential details to the customer app through a
  permissioned API.
- [ ] Do not expose SIP secrets after initial provisioning unless the app has
  `sip:credential:read`.
- [ ] Add a credential rotation endpoint that updates SIP and PJSIP together.
- [ ] Verify registration works after provisioning without manual DB edits.

## Rates, Call Plans, And Trunks

- [x] Twilio outbound rate import can create or update a ratecard and call
  plan.
- [x] Imported Twilio rates can bind rows to a selected trunk.
- [x] AGI honors `cc_trunk.providertech` instead of forcing PJSIP.
- [x] Provider setup defaults Twilio to Elastic SIP Trunking and creates or
  updates the matching A2Billing trunk from the selected routing mode.
- [x] Provider setup syncs the matching PJSIP realtime trunk endpoint after
  saving or importing Twilio settings, so Asterisk dials the selected Elastic
  termination host without manual `ps_*` table edits.
- [ ] Provider setup must create provider, trunk, ratecard, call plan, and
  `cc_tariffgroup_plan` bindings in one operation.
- [ ] Provider setup must save a reusable outbound trunk template that can use
  A2Billing placeholders such as `%dialingnumber%` and `%cardnumber%` where the
  selected upstream requires dynamic SIP URI construction.
- [ ] Rate import must normalize prefixes consistently for AGI lookup
  (`+E.164`, `1NXXNXXXXXX`, and stripped NANP forms).
- [ ] Rate import must include useful destinations where upstream pricing
  exposes only coarse routes.
- [ ] Rate import must validate that every imported rate row has `id_trunk`,
  `idtariffplan`, `rateinitial`, `buyrate`, `initblock`, `billingblock`,
  `startdate`, and strict-mode-safe defaults.
- [ ] Customer provisioning must fail loudly if no active call plan/rate/trunk
  can route outbound calls.
- [ ] Add an API/admin health check that shows whether the default provider
  package can rate and route a test number.

## DID Inventory, Purchase, And Assignment

- [ ] Provider sync must import owned DIDs into `cc_vectavoip_did_inventory`
  and project usable numbers into legacy `cc_did`.
- [ ] DID search must show provider, country, region, capabilities, monthly
  cost, setup cost, and availability.
- [ ] DID purchase must record upstream references and set voice/SMS URLs at
  the provider.
- [ ] DID assignment must create or update `cc_did_destination` for inbound
  voice routing.
- [ ] DID assignment must mark inventory assigned and prevent another customer
  from using it.
- [ ] DID release must remove or disable destination rows, mark inventory
  available, and update upstream routing when applicable.
- [ ] DID billing must be attached to the customer package/subscription rules.
- [ ] API responses must identify exactly why purchase or assignment failed:
  upstream auth, number unavailable, customer missing, no route, or DB failure.

## Outbound Caller ID

- [x] Strict-mode defaults are fixed for `cc_outbound_cid_group` and
  `cc_outbound_cid_list`.
- [x] The outbound CID form requires a CID group before insert.
- [ ] Customer provisioning must create a customer-owned outbound CID group.
- [ ] DID assignment must automatically add the assigned DID to that outbound
  CID group.
- [ ] Ratecard or call-plan setup must bind the correct `id_outbound_cidgroup`
  so AGI can select an allowed caller ID without manual admin changes.
- [ ] New-account provisioning must bind the customer's default outbound route
  to the customer-owned CID group, so every outbound call can present a
  customer-assigned DID dynamically instead of requiring a Twilio Bin per number.
- [ ] Provisioning must preserve the A2Billing AGI caller ID controls
  (`auto_setcallerid`, `force_callerid`, and `cid_sanitize`) and only override
  them through database-backed provider/package policy.
- [ ] Outbound voice must use an assigned DID as caller ID when the provider
  requires verified caller ID.
- [ ] Removing a DID must remove or deactivate that caller ID for the customer.
- [ ] Add tests for outbound CID group create, DID-to-CID add, duplicate DID,
  and release cleanup.

## SMS

- [ ] Outbound SMS must require the `from` number to be an assigned,
  SMS-enabled DID for that customer.
- [ ] Inbound SMS must resolve the DID to the owning customer and customer app.
- [ ] Store inbound and outbound SMS in `cc_sms_message` with provider
  reference, status, direction, customer id, from, to, body, and timestamps.
- [ ] Deliver inbound SMS to the customer app webhook with signature headers.
- [ ] Retry webhook delivery and expose failed delivery status in admin/API.
- [ ] Support customer app webhook secret rotation.
- [ ] Add provider-specific SMS senders for VectaVoIP upstream and Twilio.
- [ ] Add tests for unauthorized sender, inbound mapping, outbound send,
  provider failure, and webhook failure.

## ZeroAI CRM Integration

- [x] CRM stores VectaVoIP base URL and service key in `phone_text_settings`.
- [x] CRM uses `external_id=crm_{org_id}` and can recover `vt_customer_id`.
- [x] CRM auto-calls provisioning after saving credentials.
- [ ] CRM should store and send an app provisioning token separate from the
  general service key.
- [ ] CRM should not ask the user for manual VectaVoIP customer id.
- [ ] CRM DID assignment should pass voice URL, SMS webhook URL, webhook
  secret, routing mode, and desired extension/user mapping.
- [ ] CRM should display SIP credentials or provisioning status after account
  creation.
- [ ] CRM should surface actionable VectaVoIP errors, not generic "failed"
  messages.
- [ ] CRM release flow should call VectaVoIP release and remove local DID to
  org mapping only after VectaVoIP succeeds.
- [ ] CRM should have a re-provision action that is idempotent and safe.

## Admin UI And Provider Setup

- [ ] Provider credentials, unlock state, Twilio/Vecta settings, default trunk,
  default call plan, default ratecard, markup, default caller ID behavior, and
  webhook defaults must be database-backed.
- [ ] `.env` may provide initial defaults but must not be the only durable
  source for provider setup.
- [x] Twilio setup must expose the selected carrier path clearly: Elastic SIP
  Trunking, SIP Domain/TwiML, or BYOC, with Elastic selected by default for
  A2Billing.
- [ ] Provider setup should have one "Validate and Apply" path that checks
  credentials, syncs provider data, imports rates, creates trunk, binds call
  plan, and reports every created/updated id.
- [ ] Provider setup must not remove existing configured values during deploy
  or rebuild.
- [ ] Admin status should show missing pieces: provider auth, ratecard,
  call plan, trunk, DID inventory, SMS webhook, outbound CID group, and AGI
  route test.

## Deployment And Migration

- [ ] VectaVoIP runtime env path stays under `/etc/cyford/vectavoip`.
- [ ] Open-source A2BillingPlus runtime env path stays under `/etc/cyford/a2bp`.
- [ ] Docker compose must mount the org-specific `/etc/cyford/...` path and
  make legacy in-container paths point to it when required.
- [ ] MariaDB strict-mode migrations must cover existing legacy tables used by
  provider setup, customer edit, rate import, DID assignment, SIP/PJSIP, and
  outbound CID.
- [ ] Rebuild/update scripts must pull parent repo and submodule repo when the
  deployment flow expects that behavior.
- [ ] Asterisk logs must be mounted where fail2ban reads them.
- [ ] Asterisk AGI wrapper must have PHP CLI, execute permissions, and writable
  AGI logs.

## Acceptance Tests

- [ ] Fresh CRM org saves credentials and receives a VectaVoIP customer id.
- [ ] Re-saving CRM settings returns the same customer id.
- [ ] CRM can recover the same customer by external id after local settings lose
  `vt_customer_id`.
- [ ] Provisioned customer can register SIP/PJSIP without manual admin edits.
- [ ] Provider setup can import rates and route a known outbound number.
- [ ] Provisioned customer can place an outbound call through the provider trunk.
- [ ] Purchased DID appears in CRM numbers list and A2Billing DID tables.
- [ ] Inbound call to assigned DID reaches the configured CRM/customer route.
- [ ] Outbound call presents an assigned DID as caller ID.
- [ ] Inbound SMS reaches CRM webhook and appears in CRM messages.
- [ ] Outbound SMS from CRM is accepted only from assigned DIDs.
- [ ] DID release removes customer routing and caller ID authorization.
- [ ] All flows produce audit records and actionable API errors.

## Current Highest-Risk Gaps

- App-scoped provisioning authorization is not complete.
- Provider setup still needs a single atomic "make this provider usable" flow.
- DID assignment needs to automatically create inbound voice destination and
  outbound caller ID linkage.
- SMS authorization must enforce customer-owned sender numbers.
- Strict-mode cleanup should be broadened across the legacy tables touched by
  automated provisioning.
- End-to-end tests need to cover CRM save through live outbound/inbound
  voice/SMS behavior.
