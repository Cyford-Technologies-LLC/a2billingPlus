# A2BillingPlus

A2BillingPlus is a modernization fork of A2Billing focused on keeping the
proven VoIP billing model while moving high-risk and high-change workflows into
tested, modular PHP services and documented APIs.

The project is maintained by Cyford Technologies for VectaVoIP-backed VoIP
billing, rating, customer management, provider automation, payments, reporting,
and Asterisk/PJSIP operations.

For VoIP termination, visit <https://vectavoip.com/>.
<img width="2042" height="989" alt="image" src="https://github.com/user-attachments/assets/5ac0ca75-88f7-4b0a-b384-8ed4cd0d2338" />

## Goals

- Preserve the operational concepts that made A2Billing useful for calling-card
  and VoIP billing environments.
- Modernize the codebase around PHP 8.2/8.4 compatibility, Composer autoloading,
  namespaced modules, PHPUnit coverage, and repeatable Docker-based validation.
- Move billing, customer, rating, reporting, payment, provider, telephony, and
  migration logic out of legacy page scripts and into reusable services.
- Provide REST APIs and OpenAPI documentation for workflows that need
  integration, automation, or modern UI replacement.
- Replace legacy UI screens workflow by workflow using modular themes and
  navigation, without removing working operator paths before replacements are
  tested.
- Improve security posture around secrets, hosted/tokenized payments, webhook
  verification, default credentials, production hardening, and toll-fraud
  controls.

## Current Focus

- VectaVoIP provider registration, status, rate preview/import, credential
  rotation, webhooks, and provisioning workflows.
- Stripe hosted PaymentIntent support with signed webhook verification and
  replay protection. Unsafe direct-card legacy flows remain disabled by default.
- PJSIP-first telephony provisioning, trunk and DID workflows, AMI/ARI health
  checks, and Asterisk 20 sandbox validation.
- Migration tooling for existing A2Billing-style data, including dry-run reports
  and rollback guidance.
- Modular admin UI foundations, including selectable themes, a classic
  A2Billing-style theme, modular navigation, and the first payment workspace.

## Project Status

This repository is under active modernization. Legacy admin, agent, and customer
surfaces remain available while module-backed replacements are introduced and
verified. Do not assume every legacy A2Billing gateway, diagnostic page, or
configuration editor is production-ready in this fork.

Before production use, review the release readiness, installation, and security
hardening documentation linked below.

## Documentation

- [Install guides](install/README.md)
- [Module architecture](docs/module-architecture.md)
- [REST API contract](docs/openapi.yaml)
- [Billing, rating, and reporting](docs/billing-rating-reporting.md)
- [Telephony and Asterisk baseline](docs/telephony-asterisk.md)
- [Payments](docs/payments.md)
- [UI and admin experience](docs/ui-admin-plan.md)
- [Modular UI architecture](docs/ui-module-architecture.md)
- [Migration](docs/migration.md)
- [Release readiness](docs/release-readiness.md)
- [Security hardening](docs/security-hardening.md)

## Local Install

See [install/README.md](install/README.md) for sandbox and production install
guides. In the local Docker sandbox, the web installer is available at:

```text
http://localhost:8080/install.php
```

## Security

This is a public repository. Do not commit `.env` files, API credentials,
payment secrets, database passwords, SIP secrets, private keys, or customer
data. Use local environment files or deployment secret stores for runtime
credentials.

Report security issues privately to the project maintainers rather than opening
public issues with exploit details or sensitive deployment information.

## License

This platform includes AGPL-licensed software components. A2BillingPlus is
distributed under the GNU Affero General Public License v3 unless a file states
otherwise.
