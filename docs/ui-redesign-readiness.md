# UI Redesign Readiness

Last reviewed: 2026-05-03.

This document records which legacy UI areas can move into redesign without
changing business logic in page scripts. "Ready" means the workflow has module
services, API/controller access where needed, PHPUnit coverage, OpenAPI coverage
where public API exists, and the seeded portal crawl loads.

## Ready For Redesign

| Area | Status | Basis |
| --- | --- | --- |
| Customer admin/customer account workflows | Ready | Customer module services and REST/customer-context APIs cover list/detail, create/update, status changes, balance/profile reads, ownership checks, and API authorization boundaries. |
| Ratecard/rates workflows | Ready | Rate repositories cover tariff plans, tariff groups, ratecards, destinations, packages, imports, duplicate/update behavior, and rating regression tests for prefix, increments, charges, currency, and timezone-sensitive reports. |
| CDR/reporting workflows | Ready | Billing/reporting services cover CDR filters, detail, redacted export, ASR/ALOC, timezone grouping, and provider reconciliation. |
| Invoice/receipt workflows | Ready | Invoice and receipt services cover list/detail, customer ownership, download metadata, and invoice tax summaries from `cc_invoice_item`. |
| Agent workflows | Ready for alpha redesign | Agent module/API coverage exists for list/detail, customer visibility, commission calculations, and authorization failures. Reseller/agent screens should be retained for alpha rather than retired. |

## Not Ready Yet

| Area | Blocker |
| --- | --- |
| Payment UI | Requires real Stripe sandbox hosted payment and webhook verification before UI replacement. |
| Trunk, SIP/PJSIP, and DID UI | Requires documented Asterisk 20 or 22 PJSIP sandbox call path before UI replacement. |
| Broad legacy configuration editors | Production-disabled unless `A2BP_FEATURE_LEGACY_CONFIG_EDITORS` is explicitly enabled. Redesign should target the safe allowlisted settings surface only. |

## Redesign Rule

Replace one workflow at a time. The replacement page should call module
services/controllers, keep secrets hidden, preserve validation failure states,
and remain covered by the seeded crawl before the legacy screen is retired.
