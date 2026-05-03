# Security Scan Checklist

This checklist is the release-readiness scan. The deeper toll-fraud and legacy
page hardening work remains in the security roadmap section.

Before a public release:

- run `bin/verify-build.ps1`
- run `bin/crawl-portals.ps1` against seeded sandbox accounts
- confirm `.env` is not committed
- confirm provider and payment secrets use secret-file support where available
- review exposed ports for web, SIP, RTP, AMI, ARI, database, and Redis
- verify Stripe and VectaVoIP webhook signatures reject bad signatures
- confirm legacy direct-card payment flows remain disabled
- scan for raw card number and CVV storage paths
- confirm default database, AMI, ARI, admin, and customer passwords are changed
- review new state-changing endpoints for authentication and validation
