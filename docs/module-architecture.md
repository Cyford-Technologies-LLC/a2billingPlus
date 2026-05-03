# Module Architecture

A2BillingPlus is moving legacy page scripts toward small modules under `src/`.
Legacy admin, agent, and customer pages can remain as entrypoints while new
behavior moves into module services that are easier to test and reuse.

## Current Module Boundaries

- `Config`: shared runtime configuration access, environment reads, and common
  derived values such as database DSNs.
- `Http`: framework-free JSON request/response helpers for local API endpoints.
- `Module\Provider`: provider registry, provider connectors, provider setup
  workflow, provider API credentials, rate preview/import contracts, and import
  logging.
- `Module\Provider\VectaVoIP`: VectaVoIP registration, status checks, local
  production-compatible provider API, credential rotation, provisioning, DID
  inventory staging, and rate preview.
- `Module\Rate`: mapping provider rows into A2Billing ratecard rows and handling
  duplicate/update behavior.
- `Module\Migration`: migration dry-run/apply services for legacy A2Billing
  customer and VoIP data.

## Planned Module Boundaries

- `Module\Billing`: balances, invoices, payments, receipts, commissions, and
  balance-impacting audit events.
- `Module\Customer`: customer records, caller IDs, portal account state, and
  customer-facing profile workflows.
- `Module\Telephony`: Asterisk/PJSIP/IAX settings, trunks, DIDs, routes, AMI,
  ARI, and provisioning workflows.
- `Module\Reporting`: CDR summaries, ASR, ALOC, revenue, cost, and reconciliation
  reports.
- `Module\Payment`: Stripe, Braintree, webhook validation, and reconciliation.
- `Module\AdminUi`: form/page adapters that let legacy admin pages call module
  services instead of owning workflow logic.

## Extension Pattern

Provider integrations should implement `ProviderModuleInterface` and return one
or more `ProviderConnectorInterface` instances. `ProviderRegistryFactory` builds
the registry from provider modules, so adding another provider should not require
rewriting the registry.

Shared configuration should use `AppConfig` instead of direct `getenv()` calls in
new module code. Legacy entrypoints can still bridge environment values into
module services while the system is being modernized.
