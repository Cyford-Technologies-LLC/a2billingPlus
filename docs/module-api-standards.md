# A2BillingPlus Module And API Standards

## Module Shape

New business logic should live under `src/Module/<Domain>` and use this shape:

- Repository: database reads/writes, safe column allowlists, no HTTP concerns.
- Service: validation, ownership checks, transaction boundaries, audit events, and result arrays/DTOs.
- Controller: request parsing, authentication context, response envelopes, no business SQL.
- Tests: service tests for rules, controller tests for auth and response behavior.

Sensitive fields such as passwords, SIP secrets, card data, provider secrets, and API keys must never be returned by repository or service read methods unless a narrowly-scoped credential rotation workflow explicitly requires it.

## API Shape

New API endpoints should use:

- `ApiResponder` envelopes for JSON success/error responses.
- `limit` and `offset` for pagination, with `limit` constrained to `1..100`.
- Explicit filter validation before service calls.
- Service-key auth for admin/system workflows.
- Signed customer or signed agent context headers for scoped self-service workflows.
- OpenAPI coverage in `docs/openapi.yaml` before a legacy UI screen is replaced.

## Audit And Transactions

Sensitive writes should emit `AuditLogRepository` events with an actor, action, entity type, entity id, and compact metadata. Balance-impacting or provisioning workflows should use a single transaction boundary, preferably through `TransactionRunner`, with provider rollback/retry behavior handled at the integration boundary.

## Compatibility Flags

Legacy behavior that must remain during migration should be guarded by `FeatureFlags`. Flag names map to environment variables as `A2BP_FEATURE_<NAME>`, for example `legacy_direct_card_flow` maps to `A2BP_FEATURE_LEGACY_DIRECT_CARD_FLOW`.
