# Release Readiness

## Alpha

Alpha is sandbox-first. It is acceptable for legacy admin, customer, and agent
portals to remain in place.

Required:

- clean checkout runs `bin/verify-build.ps1`
- PHP 8.2 and PHP 8.4 tests pass
- installer validates database, filesystem, PHP extensions, and service checks
- VectaVoIP sandbox and production-compatible registration flows work
- provider rate preview and dry-run import work
- migration dry-run works for customers, VoIP settings, and CDR date windows
- known limitations are documented

## Beta

Beta is operator-preview.

Required:

- portal crawl checks pass against seeded sandbox accounts
- payment module can verify Stripe webhooks in sandbox
- migration apply has been tested against sanitized snapshots
- Asterisk 20 or 22 PJSIP sandbox call path is documented and tested
- backup and rollback procedure has been run end to end
- security patch matrix exists for known A2Billing vulnerabilities

## Production Ready

Production-ready means VectaVoIP-backed installs can be operated for real
customers.

Required:

- security hardening checklist is complete
- default credentials are blocked or rotated
- toll-fraud controls are documented and tested
- payment flows use hosted/tokenized providers only
- live provider credential rotation and webhooks have been tested
- CDR, payment, and provider reconciliation reports are reviewed
- release notes and upgrade instructions are published
