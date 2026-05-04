# UI and Admin Experience

The launch UI plan keeps the legacy portals available while moving high-risk
and high-frequency workflows behind tested module services.

## Legacy Screens That Stay for Launch

Keep these legacy areas available for alpha/beta while module-backed workflows
are added around them:

- admin login and session shell
- customer list and customer detail
- ratecard list and rate import history
- CDR search
- payment/refill history
- invoice list
- trunk list
- DID list
- agent list where resellers are still used
- system configuration pages needed by existing operators

Do not do broad styling-only rewrites before these workflows have tests or
module adapters. Prioritize workflow correctness and operator safety.

## Modernized First

The provider setup workflow is the first modernized admin surface. It already
uses module-backed services for VectaVoIP registration, status checks, rate
preview/import, credential storage, and provider provisioning.

Payment screens are now ready for redesign after sandbox validation of hosted
Stripe PaymentIntent creation, webhook signature verification, duplicate replay
handling, raw-card rejection, and reconciliation services.

Trunk, SIP/PJSIP, and DID screens are now ready for workflow-by-workflow
redesign after PJSIP-first provisioning, DID assignment/routing services, trunk
APIs, AMI/ARI health checks, and the Asterisk 20 sandbox call path passed
validation.

## Responsive Customer Portal Priorities

For the customer portal, build the first responsive replacement around repeat
customer tasks:

- current balance and account status
- recent calls/CDRs
- payment/refill history
- hosted payment entry point
- invoice list and invoice download
- SIP credentials and registered device state
- DID list and basic routing state
- profile/contact settings

Avoid exposing raw telephony internals in the customer portal. Keep the first
replacement narrow and reliable.

## Long-Term Replacement Plan

1. Keep legacy pages as entrypoints while new module services become stable.
2. Add crawl checks for admin, customer, and agent portals against seeded
   sandbox data.
3. Replace one workflow at a time with module-backed controllers and focused
   templates.
4. Move shared layout, alerts, tables, forms, and pagination into reusable
   components.
5. Replace admin workflows first where operational mistakes can cost money:
   provider setup, rates, payments, CDRs, customers, and DIDs.
6. Replace customer self-service workflows after payment and portal auth rules
   are stable.
7. Replace or retire agent/reseller screens after launch requirements are clear.

## Acceptance Bar

A modernized screen is not complete until:

- it calls module services instead of owning business logic in the page script
- PHP 8.2 and PHP 8.4 tests cover the workflow logic
- form validation has clear failure states
- sensitive values are hidden or stored through secret-file support
- seeded sandbox crawl can load the page without fatal errors
