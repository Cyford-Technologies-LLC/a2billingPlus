# A2BillingPlus Roadmap Checklist

This checklist is the operational plan for the A2BillingPlus modernization fork
and the VectaVoIP provider launch path. Strategic background remains in
`development/documents/`; this file tracks concrete work.

Status key:

- `[x]` Done enough to build on
- `[ ]` Not done

## Current Position - 2026-05-03

- [x] Alpha checklist is complete enough to build on.
- [x] `bin/verify-build.ps1` passes on the current Docker sandbox.
- [x] Seeded portal crawl passes for admin, agent, and customer portals:
      admin 365 URLs, agent 49 URLs, customer 34 URLs, 0 crawl errors.
- [x] VectaVoIP provider setup is module-backed and crawl-clean.
- [x] Provider setup now supports provider-aware flows with DIDWW credential
      validation and provider lock policy hooks for company/licensed access.
- [x] DIDWW is now positioned as a restricted provider path for internal company
      use and paid licensed admins, with UI and API enforcement.
- [x] VectaVoIP registration is now opt-in from the provider setup screen using
      username/password, with server-issued install credentials and returned
      account/IP metadata.
- [x] A2BillingPlus branding is present on login and admin intro surfaces.
- [x] Modular UI theme foundation, classic theme option, and admin theme
      selector are in place for new module-backed screens.
- [x] The first modular admin replacement screen, payment workspace, is present
      and wired into the shared navigation.
- [x] Modular admin pages now share a reusable page shell for theme, navigation,
      alerts, and common layout chrome.
- [x] Theme manager is reachable from admin intro and system settings, supports
      package upload/install, and has passed end-to-end sandbox activation.
- [x] Modular customer workspace and customer detail screens are present for
      list/search, account review, status changes, and core contact updates.
- [x] Menu style system is present with side-rail, topbar, compact, and split
      menu layouts, theme defaults, and admin override support.
- [x] Modular rate workspace is present for rate row search, tariff plan/group
      review, and destination coverage lookup.
- [x] Modular telephony workspace is present for DID, trunk, SIP/IAX account
      review, and Asterisk readiness checks.
- [x] First modular telephony write flows are present for DID assign/release,
      basic DID routing, trunk updates, and SIP/IAX account edits.

## Next Beta Work

- [x] Run Stripe webhook verification against a real Stripe sandbox fixture and
      record the exact command/output in release notes.
- [x] Test migration apply against a sanitized legacy snapshot, not only dry-run
      migration.
- [x] Run and document an Asterisk 20 or 22 PJSIP sandbox call path.
- [x] Run backup and restore end to end against the Docker sandbox database.
- [x] Review the known A2Billing security patch matrix against the current
      legacy admin, agent, and customer surfaces.
- [x] Prepare alpha release notes and tag candidate after the beta blockers are
      separated from alpha limitations.
- [ ] Build the modular UI admin shell out across the next workflow screens so
      provider setup and payment workspace are no longer standalone exceptions.
- [x] Add theme package install/upload support for operators so new modular
      themes can be added without manual file copies on the server.
- [ ] Define the first production-ready multi-tenant architecture so branding,
      themes, settings, credentials, routing, and customer data can be safely
      partitioned by tenant.

## Module/API Completion Checklist Before Major UI Rewrites

Goal: each major workflow should have tested service/API behavior before the
legacy Smarty/PHP page is replaced. A UI screen is ready for redesign only when
its business rules live outside the page script.

### Cross-Cutting Platform Work

- [x] Define the standard module shape for repositories, services, request DTOs,
      result DTOs, validators, and tests.
- [x] Define the standard API shape for pagination, filters, sorting, field
      validation, error responses, and authorization failures.
- [x] Add shared API authorization helpers for admin, agent, customer, and
      service-key contexts.
- [x] Add a shared audit event writer for create, update, delete, balance,
      payment, provider, DID, and trunk operations.
- [x] Add a shared transaction boundary pattern for balance-impacting and
      provisioning workflows.
- [x] Add feature flags or compatibility switches where legacy behavior must
      remain available during migration.
- [x] Add OpenAPI coverage for every new endpoint before replacing its legacy UI.

### Customer And Account Module

- [x] Extract customer search/list logic from legacy admin pages into a customer
      module service.
- [x] Add a customer account repository for safe list/search fields, balance,
      status, group, and contact fields.
- [x] Add admin API endpoint support for customer list with search and status
      filters.
- [x] Add customer detail, create, update,
      activate/block, and group assignment.
- [x] Add customer self-service API endpoints for profile, balance, account
      status, and contact updates.
- [x] Add validation for unique username/useralias/email rules and required
      billing fields.
- [x] Add tests for customer create/update, status changes, and API
      authorization boundaries.
- [x] Mark customer admin/customer UI screens as ready for redesign after the
      above endpoints pass crawl and API tests.

### Rates And Rating Module

- [x] Extract tariff group, tariff plan, ratecard, destination, and package
      queries into rate repositories.
- [x] Add API endpoints for ratecard list/detail/search and destination lookup.
- [x] Add API endpoints for tariff group and tariff plan read/write workflows.
- [x] Add rate import apply endpoints with dry-run, duplicate handling, and audit
      logging.
- [x] Add tests for prefix matching, increments, minimum charges, connection
      charges, currency, and timezone-sensitive reports.
- [x] Mark ratecard/rates UI screens as ready for redesign after API and rating
      regression coverage passes.

### CDR, Reporting, And Reconciliation Module

- [x] Extract CDR search filters, pagination, and export logic from legacy pages.
- [x] Add API endpoints for CDR search, CDR detail, and date-windowed export.
- [x] Add reporting API endpoints for ASR, ALOC, revenue, provider cost, margin,
      and customer billing summaries.
- [x] Add reconciliation API endpoints for provider cost versus customer billing
      and payment versus refill totals.
- [x] Add tests for date windows, timezone handling, pagination stability, and
      export redaction rules.
- [x] Mark CDR/reporting UI screens as ready for redesign after seeded report
      fixtures pass.

### Payments Module

- [x] Add hosted/tokenized payment intent creation for Stripe.
- [x] Add Stripe webhook receive endpoint that verifies raw payload signatures in
      sandbox and rejects invalid signatures.
- [x] Add payment ledger repository for payment, refill, refund, webhook event,
      and reconciliation records.
- [x] Add admin API endpoints for payment history, payment detail, refunds where
      supported, and reconciliation reports.
- [x] Add customer API endpoints for hosted payment entry, payment history, and
      receipt/invoice references.
- [x] Keep legacy direct-card flows disabled unless replaced by hosted/tokenized
      provider modules.
- [x] Add tests for webhook replay protection, invalid signatures, no raw card
      storage, and balance update audit logging.
- [x] Mark payment UI screens as ready for redesign only after hosted sandbox
      payment and webhook tests pass.

### Invoices And Receipts Module

- [x] Extract invoice, receipt, and tax summary queries from legacy pages.
- [x] Add API endpoints for invoice list/detail/download metadata and receipt
      list/detail.
- [x] Add customer-scoped invoice and receipt endpoints with strict ownership
      checks.
- [x] Add tests for invoice visibility, currency formatting, date windows, and
      customer isolation.
- [x] Mark invoice/receipt UI screens as ready for redesign after API ownership
      tests pass.

### Telephony, Trunks, SIP/PJSIP, And DID Module

- [x] Extract SIP/IAX/PJSIP account queries and writes into telephony services.
      SIP/IAX account reads/writes are module-backed; PJSIP remains in the
      provisioning milestone below.
- [x] Add trunk repository and API endpoints for trunk list/detail/create/update.
- [x] Add DID inventory, assignment, release, and routing services.
- [x] Add API endpoints for DID list/detail/assign/release/routing updates.
- [x] Add Phone & Text schema, SMS message repository, VectaVoIP SMS gateway,
      DID assignment API, message API, and module tests.
- [x] Add PJSIP-first provisioning service for new customer devices and trunks.
- [x] Add Asterisk AMI/ARI health and configuration validation endpoints.
- [x] Add tests for DID ownership, trunk validation, SIP secret handling, and
      provisioning audit events.
- [x] Mark trunk, SIP/PJSIP, and DID UI screens as ready for redesign after
      sandbox Asterisk 20/22 call-path verification.

### Provider Module

- [x] Move VectaVoIP provider setup to module-backed services.
- [x] Add provider registration, status, rate preview, rate import, webhooks,
      credential rotation, and provisioning services.
- [x] Add production deployment validation against live `api.vectavoip.com`.
- [x] Add provider API contract tests for live-compatible status, rates,
      credentials, provisioning, and webhook event handling.
- [x] Add rollback and retry behavior for partial provider provisioning.
- [x] Expand VectaVoIP post-registration package selection into package-specific
      provisioning forms after the plan is chosen.
- [x] Add DIDWW provider registration in the provider registry with API-key
      connection testing and locked-access enforcement.
- [x] Add provider access policy support so selected providers can be locked to
      company admins and licensed individuals.
- [x] Add licensed-admin management for locked providers so paid access can be
      granted and revoked without editing environment variables by hand.
- [ ] Finish DIDWW operational support beyond credential validation. Owned DID,
      inbound trunk, order, available-DID search, DID ordering, inbound trunk
      creation, local inventory synchronization, and completed-order auto-sync
      are now in the provider UI; provider/trunk/order inventory context is now
      visible in the modular telephony workspace. Remaining work is
      trunk-group/deeper provisioning and write-side DIDWW telephony actions.
- [x] Add a first-pass Twilio provider integration covering credential testing,
      owned-number snapshot, available-number search, number purchase, SIP trunk
      creation, and local inventory synchronization.
- [x] Add Twilio BYOC trunk support so operators can persist a preferred trunk
      SID, link an existing BYOC trunk locally, and auto-attach purchased
      numbers to that trunk.
- [x] Align Twilio BYOC handling with Twilio's Voice BYOC Trunking API so
      BY-prefixed trunk SIDs resolve through the correct endpoint and remain
      case-safe in the UI.

### Agent And Reseller Module

- [x] Decide whether agent/reseller support remains in the beta scope.
- [x] If retained, extract agent list/detail, commission, payment, and customer
      ownership logic into an agent module.
- [x] Add agent-scoped API endpoints with strict customer ownership boundaries.
- [x] Add tests for commission calculations, customer visibility, and agent
      authorization failures.
- [x] Mark agent UI screens as ready for redesign only after the retention
      decision and API tests are complete.

### Admin Configuration Module

- [x] Inventory configuration pages required for beta and production operation.
- [x] Extract safe configuration reads/writes into typed services with allowlists.
- [x] Add API endpoints for safe settings only; keep raw config editors disabled
      or restricted in production.
- [x] Add tests for forbidden setting writes, default credential warnings, and
      production hardening checks.
- [x] Mark configuration UI screens as ready for redesign after unsafe editors
      are removed, isolated, or explicitly production-disabled.

### UI Readiness Gate

- [x] A workflow has module services and repositories.
- [x] A workflow has API endpoints or controller methods where the UI needs
      asynchronous or decoupled access.
- [x] A workflow has PHPUnit coverage for success, validation failure,
      authorization failure, and high-risk edge cases.
- [x] A workflow has OpenAPI documentation.
- [x] A workflow emits audit events for sensitive or balance-impacting changes.
- [x] A workflow passes seeded admin, agent, and/or customer crawl checks.
- [x] Only then replace the legacy UI screen for that workflow.

## 1. Foundation Already Completed

- [x] Fork A2Billing into the A2BillingPlus codebase.
- [x] Add VectaVoIP project branding and basic repository documentation.
- [x] Add Composer autoloading for new namespaced PHP modules under `src/`.
- [x] Make the active code paths compatible with PHP 8.2 and PHP 8.4.
- [x] Add automated build verification through `bin/verify-build.ps1`.
- [x] Add PHPUnit coverage for new provider, migration, factory, and API code.
- [x] Add a Docker-based sandbox stack for local development.
- [x] Add public install documentation under `install/`.
- [x] Add web installer support for environment/database setup.
- [x] Add migration support for customer and VoIP settings.

## 2. Installer and Deployment

- [x] Add install guide entry points.
- [x] Add web installer documentation.
- [x] Add sandbox and production install documentation.
- [x] Add `.env.example` for common runtime settings.
- [x] Complete installer validation for PHP extensions, writable paths, database
      connectivity, and default credential warnings.
- [x] Add one-command sandbox setup documentation from a clean checkout.
- [x] Add production hardening checklist to the installer finish screen.
- [x] Add backup and restore workflow documentation.
- [x] Add upgrade workflow documentation for existing A2BillingPlus installs.
- [x] Add install-time health checks for Apache/PHP, database, Redis, and
      optional Asterisk.

## 3. Modular Architecture

- [x] Start namespaced modules under `src/Module`.
- [x] Add provider module interfaces and registry.
- [x] Add rate import service module.
- [x] Add migration service module.
- [x] Define module boundaries for billing, rating, customer management,
      provider integrations, telephony, payments, reporting, and admin UI.
- [x] Create a shared module configuration pattern.
- [x] Move new API/controller code behind module services instead of legacy page
      scripts.
- [x] Add dependency injection or factory conventions for module wiring.
- [x] Define extension points for provider modules so VectaVoIP can be shipped as
      a first-class provider without hardcoding future providers everywhere.
- [x] Add module-level tests for registration, rate import, payments, and
      migration flows.

## 4. VectaVoIP Provider Module

- [x] Add VectaVoIP connector class.
- [x] Add VectaVoIP registration request/result/client classes.
- [x] Add VectaVoIP rate importer.
- [x] Add provider registry factory with VectaVoIP registered by default.
- [x] Add local provider API endpoint at `api/v1/providers.php`.
- [x] Add sandbox provider registration endpoint.
- [x] Add sandbox provider rate preview endpoint.
- [x] Add admin provider setup page at `admin/Public/A2B_provider_setup.php`.
- [x] Make the admin provider setup page provider-aware instead of
      VectaVoIP-only, with DIDWW credential test/save support.
- [x] Add provider rate preview, dry-run import, and write import flow.
- [x] Add provider import audit table migration.
- [x] Add provider registration documentation in
      `install/provider-registration.md`.
- [x] Decide final production API hostname casing and canonical URL for
      VectaVoIP.
- [x] Build the real VectaVoIP production registration API.
- [x] Define the production registration contract, authentication model, and
      error codes.
- [x] Store provider credentials outside `.env` when a deployment platform secret
      store is available.
- [x] Add credential rotation for VectaVoIP API keys and secrets.
- [x] Add signed provider webhook support for rate updates and account events.
- [x] Add provider status checks that call the real provider API, not only local
      structural validation.
- [x] Add provider provisioning steps for trunks, SIP credentials, DID inventory,
      and default ratecards.
- [x] Add an installer screen that offers "Register with VectaVoIP" during setup.
- [x] Add admin UI help text for sandbox vs production registration.

## 5. REST API and External Integrations

- [x] Add an initial provider API controller path.
- [x] Define API versioning rules and response/error format.
- [x] Add authenticated REST endpoints for customers, balances, rates, payments,
      CDRs, providers, and invoices.
- [x] Add OpenAPI documentation for the new API.
- [x] Add API authentication using short-lived tokens or signed service keys.
- [x] Add API tests for authorization and validation failures.
- [x] Add webhook delivery for payment, balance, registration, and provider
      events.

## 6. Billing, Rating, and Reporting Accuracy

- [x] Create regression fixtures for the rating engine.
- [x] Validate call rating against known A2Billing scenarios.
- [x] Audit rounding, billing increments, connection charges, and minimum charges.
- [x] Verify ASR and ALOC reports against live and archived CDR data.
- [x] Add tests around duplicate rate imports and rate updates.
- [x] Add reporting checks for timezone and currency handling.
- [x] Add reconciliation reports for provider cost vs customer billing.

## 7. Telephony and Asterisk

- [x] Verify Asterisk Realtime behavior for SIP/PJSIP and IAX where still
      supported.
- [x] Document supported Asterisk versions.
- [x] Add AMI and ARI configuration checks.
- [x] Add PJSIP-first configuration examples.
- [x] Add multi-server design for web, database, and telephony nodes.
- [x] Add trunk provisioning workflow for VectaVoIP.
- [x] Add DID assignment and routing workflow for VectaVoIP.
- [x] Evaluate WebRTC support after core SIP/PJSIP flows are stable.

## 8. Payments

- [x] Inventory legacy payment gateways and mark deprecated providers.
- [x] Add Stripe payment module.
- [x] Add Braintree payment module if needed.
- [x] Keep PayPal out of beta unless it is rebuilt as a hosted/tokenized module
      with signed webhook verification.
- [x] Remove or disable unsafe legacy payment flows by default.
- [x] Add payment provider configuration validation.
- [x] Add payment webhook verification.
- [x] Add payment reconciliation reporting.
- [x] Ensure no raw card numbers or CVV values are stored.

## 9. UI and Admin Experience

- [x] Identify the minimum admin screens that must remain legacy for launch.
- [x] Modernize the provider setup workflow first.
- [x] Add responsive customer portal priorities.
- [x] Improve install and setup screens for non-technical operators.
- [x] Add clear production warnings for default credentials, exposed services,
      and sandbox provider mode.
- [x] Create a long-term UI replacement plan for admin, agent, and customer
      portals.
- [x] Add a modular theme foundation for new admin screens.
- [x] Add a classic theme option for operators who prefer legacy visual density.
- [x] Add modular navigation and a persistent admin theme selector.
- [x] Replace the first admin legacy workflow entrypoint with the payment
      workspace.
- [x] Make theme manager reachable from legacy admin intro and system settings
      entrypoints during the migration period.
- [ ] Expand the modular navigation registry to cover every workflow being
      actively replaced, including customer, telephony, rates, reporting,
      invoices, configuration, and agent/reseller surfaces.
- [x] Add a reusable admin page shell so new screens share the same header,
      navigation, alerts, filter bar, metrics, and table/layout patterns.
- [ ] Move the admin intro/dashboard screen behind the modular shell instead of
      leaving it as a mostly standalone landing surface.
- [x] Replace the first customer admin list/detail/edit flows with modular
      workspace and detail screens backed by the customer module services.
- [ ] Expand modular customer screens to cover create, group workflows, VoIP
      settings, caller IDs, notifications, speed dial, history, and other
      remaining legacy customer-side admin tasks.
- [x] Start replacing DID inventory, trunk, and VoIP account review with a
      modular telephony workspace.
- [x] Add first write workflows for DID assignment/routing, trunk updates, and
      VoIP account changes in the modular telephony screens.
- [ ] Expand modular telephony write flows to full DID destination management,
      PJSIP provisioning, bulk changes, and audit-friendly edit history.
- [x] Start replacing ratecard, rates, tariff plan, and destination workflows
      with a modular rate workspace for search and review.
- [ ] Add write workflows to modular pricing screens for tariff/rate edits,
      import apply, preview, and audit context.
- [ ] Replace CDR, reconciliation, and summary reporting pages with modular
      reporting screens built around shared filter and export components.
- [ ] Replace invoice and receipt admin/customer views with modular document
      screens and ownership-aware download actions.
- [ ] Replace safe configuration pages with modular settings screens while
      keeping raw or risky editors unavailable in production.
- [ ] Replace retained agent/reseller workflows with modular screens after the
      ownership and commission boundaries remain verified.
- [ ] Build customer portal modular surfaces for profile, balance, invoices,
      receipts, payments, DIDs, SMS, and support-oriented account actions.
- [ ] Build agent portal modular surfaces only for the retained beta scope.
- [ ] Keep legacy menus available only where no modular replacement exists, and
      remove duplicated entrypoints once replacement screens are crawl-clean.
- [ ] Add modular screen smoke coverage to the seeded crawler so each new UI
      replacement is validated in CI before a legacy page is retired.
- [ ] Add visual regression capture for the built-in themes on modular screens.
- [ ] Define a stable theme asset contract for colors, typography, spacing,
      panels, alerts, forms, tables, metrics, and navigation states.
- [x] Add an admin theme management screen to list installed themes, preview
      metadata, switch active theme, and warn about invalid or incomplete theme
      packages.
- [x] Add theme package upload/install support for zip or directory bundles with
      manifest validation, safe extraction, and rollback on install failure.
- [ ] Add theme removal/disable support that blocks deletion of the active or
      built-in fallback theme.
- [x] Add theme compatibility/version checks so installs fail cleanly when a
      package targets an unsupported UI contract.
- [x] Add theme asset integrity checks and permissions validation before a theme
      is activated.
- [x] Verify uploaded theme install and activation end to end in the local
      sandbox, including persistence of the active theme setting.
- [x] Add menu style selection independent of theme selection, with each theme
      able to declare a default menu style.
- [x] Add four menu layouts for modular admin pages and legacy menu chrome:
      side-rail, topbar, compact, and split.
- [x] Persist admin login sessions across container rebuilds with workspace-
      backed session storage, and return expired admin sessions to the original
      requested page after login.
- [ ] Document the operator workflow for installing, switching, validating, and
      removing custom themes.
- [ ] Decide whether theme installation stays filesystem-based, becomes module-
      registered, or supports both paths with a single manifest contract.
- [ ] Continue modular UI replacement across all remaining launch modules:
      customer subflows, telephony write flows, rate write/import flows,
      reports, invoices, configuration, agent/reseller, and customer portal
      surfaces.
- [x] Keep provider setup inside the modular admin shell while expanding it to
      multiple providers and locked-provider policies.

## 10. Multi-Tenant Platform Support

Multi-tenant support is now a first-class platform requirement. The current
codebase can support tenant branding/theme directionally, but tenant isolation
is not yet a completed runtime capability.

- [ ] Decide the tenancy model: single database with tenant scoping, separate
      schemas/databases per tenant, or a hybrid model for larger operators.
- [ ] Define the tenant boundary for authentication, authorization, customer
      ownership, rates, invoices, payments, DIDs, trunks, provider credentials,
      webhook secrets, and audit logs.
- [ ] Add a tenant entity and tenant-aware configuration model for branding,
      active theme, locale, currency, support contacts, and feature flags.
- [ ] Add tenant resolution rules for admin, agent, customer, API, and webhook
      entrypoints.
- [ ] Add tenant scoping to repositories, services, and queries before modular
      UI replacements depend on cross-tenant-safe data access.
- [ ] Add tenant-aware secret storage so provider/payment credentials do not
      leak across tenants.
- [ ] Add tenant-aware theme selection and branding so each tenant can use its
      own modular theme without affecting other tenants.
- [ ] Add tests for tenant isolation across customer visibility, balances,
      invoices, CDRs, DIDs, trunks, and provider/payment configuration.
- [ ] Add migration tooling for mapping legacy single-tenant installs into the
      chosen tenant model.
- [ ] Decide whether the first public multi-tenant release includes reseller/
      agent delegation or treats it as a later phase.

## 11. Migration from Existing A2Billing Installs

- [x] Add early migration strategy documentation.
- [x] Add database inspection tooling.
- [x] Add redacted JSONL export tooling.
- [x] Add snapshot validation tooling.
- [x] Add customer migration CLI.
- [x] Add VoIP settings migration support.
- [x] Add import support into the final A2BillingPlus schema.
- [x] Add date-windowed CDR migration for large installations.
- [x] Add migration dry-run reports suitable for operators.
- [x] Add rollback guidance for failed migrations.
- [x] Add tests using sanitized legacy database snapshots.

## 12. Quality Gates and Release Readiness

- [x] Run PHP syntax checks on changed runtime entrypoints.
- [x] Run PHPUnit on PHP 8.2 and PHP 8.4 in build verification.
- [x] Add static scan for common PHP 8 compatibility issues.
- [x] Add crawl checks for admin, agent, and customer portals to CI.
- [x] Fix development crawler safety issues before using it against non-sandbox
      data.
- [x] Add security scan checklist before public release.
- [x] Add release notes template.
- [x] Add versioning policy.
- [x] Define alpha, beta, and production-ready acceptance criteria.

## 13. Security and Toll-Fraud Hardening

Security is last in this roadmap because the legacy files will continue changing
during modularization, provider setup, UI work, and migration work. Do not skip
obvious dangerous issues while editing, but do the full hardening sweep after
the major file movement settles.

- [x] Review known A2Billing 1.x vulnerabilities and create a patch matrix.
- [x] Audit legacy admin/customer/agent pages for stored and reflected XSS.
- [x] Add CSRF protection to state-changing legacy forms.
- [x] Harden session cookie settings for production.
- [x] Replace default credentials during install and block production use of
      `root / changepassword`.
- [x] Add install checks for exposed database ports and weak database passwords.
- [x] Document firewall rules for SIP, RTP, AMI, ARI, web, and database access.
- [x] Add fail2ban or equivalent guidance for SIP registration and web login
      abuse.
- [x] Add rate limiting for login, provider registration, and provider API calls.
- [x] Add audit logging for balance-impacting admin actions.
- [x] Review and disable risky diagnostic pages in production, including PHP info
      and system info pages.

## Immediate Next Work

- [x] Fix development tooling review findings in `development/tools`.
- [x] Finish installer validation and production warnings.
- [x] Move VectaVoIP provider setup into a cleaner module-backed workflow.
- [x] Build the real VectaVoIP production registration API.
- [x] Define alpha release criteria for a VectaVoIP-backed sandbox install.
- [x] Turn the modular UI foundation into a reusable admin shell that multiple
      workflow screens can share.
- [ ] Add the next modular admin replacements in this order: customers,
      telephony, rates/reporting, invoices, configuration.
- [x] Start the customer replacement step with modular customer workspace and
      customer detail screens.
- [x] Start the rates replacement step with a modular rate workspace for
      ratecards, tariff plans, tariff groups, and destinations.
- [x] Start the telephony replacement step with a modular telephony workspace
      for DID, trunk, and VoIP account review.
- [x] Build theme management and theme package upload/install support for custom
      modular themes.
- [ ] Define and start implementing the tenant model before customer, payment,
      provider, and telephony UI replacements grow around single-tenant
      assumptions.
- [ ] Continue module-by-module UI replacement after the current telephony
      slice: DID/trunk write flows, VoIP settings edits, rate write/import
      flows, reporting, invoices, configuration, agent/reseller, and customer
      portal.

## Alpha Release Criteria

The alpha release is a sandbox-first build that proves A2BillingPlus can install,
register with the VectaVoIP provider flow, preview/import provider rates, and
migrate basic legacy data without breaking PHP 8.2 or PHP 8.4 compatibility.

- [x] Clean checkout can install dependencies and run `bin/verify-build.ps1`.
- [x] PHP 8.2 and PHP 8.4 test suites pass.
- [x] Web installer validates PHP extensions, writable files, database settings,
      and default credential warnings.
- [x] Web installer displays a production hardening checklist after a successful
      run.
- [x] Sandbox VectaVoIP registration endpoint returns deterministic credentials.
- [x] Production-compatible VectaVoIP registration endpoint persists install
      records and generated API credentials.
- [x] Production-compatible VectaVoIP status and rate preview endpoints validate
      signed API credentials.
- [x] Local provider API can report VectaVoIP status.
- [x] Local provider API can preview sandbox VectaVoIP rates.
- [x] Local provider API can dry-run provider rate imports.
- [x] Provider setup admin page uses the module-backed provider setup workflow.
- [x] Customer migration CLI can run a dry-run migration.
- [x] A clean sandbox install from a fresh clone is documented step by step.
- [x] Admin portal crawl passes against seeded sandbox accounts.
- [x] Customer portal crawl passes against seeded sandbox accounts.
- [x] Agent portal crawl passes against seeded sandbox accounts.
- [x] Known alpha limitations are documented before tagging.

## Alpha Limitations

- The VectaVoIP production-compatible API path is implemented locally at
  `/api/vectavoip`; deployment to `api.vectavoip.com` is still required.
- Provider status validation can call the signed production-compatible status
  endpoint when API key and secret are present.
- VectaVoIP trunk, SIP credential, DID inventory, and default ratecard
  provisioning are automated locally; production deployment wiring still needs
  live-provider validation.
- Payment modernization is not included in alpha.
- Admin, agent, and customer portals are still legacy UI surfaces.
- Security hardening is intentionally scheduled after the main modularization
  and provider workflows settle.
- Large legacy CDR migrations still need date-windowed export/import.
- Production secret storage still depends on `.env` unless the deployment
  platform provides an external secret store.
