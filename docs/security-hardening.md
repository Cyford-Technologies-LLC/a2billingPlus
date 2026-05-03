# Security and Toll-Fraud Hardening

Security work is last in the roadmap because the legacy file layout is still
changing. These controls define the release sweep.

## Patch Matrix

| Item | Risk | A2BillingPlus action |
| --- | --- | --- |
| A2Billing 1.9.4 multiple XSS/SQL injection reports | Reflected/stored XSS and SQL injection in older legacy pages | Audit legacy admin/customer/agent pages before public release. Source: <https://sysdream.com/a2billing-1-9-4-multiple/> |
| CVE-2015-1875 | SQL injection in `a2billing/customer/iridium_threed.php` as bundled in Elastix 2.5.0 and earlier | Keep Iridium/direct-card legacy flows disabled and remove raw SQL paths before enabling any equivalent flow. Source: <https://cvefeed.io/vuln/detail/CVE-2015-1875> |
| Legacy direct-card payment flows | Raw card/CVV handling | Use `PaymentSensitiveDataGuard`; keep PlugnPay and Iridium disabled. |
| Default credentials | Unauthorized access and toll fraud | Use `CredentialPolicy`; block `root / changepassword` and other known defaults. |
| Login/provider/API abuse | Credential stuffing and provider setup abuse | Use `RateLimitPolicy` for login, provider registration, and provider API endpoints. |

## Legacy Page Sweep

Audit admin, customer, and agent pages for:

- reflected XSS from query parameters
- stored XSS from customer, rate, provider, DID, ticket, and invoice fields
- state-changing GET requests
- missing CSRF tokens on POST forms
- raw SQL built from request values
- diagnostic pages that expose runtime configuration

Use `CsrfTokenService` when adapting state-changing legacy forms into
module-backed workflows.

## Session and Cookie Settings

Production PHP settings should use:

```text
session.cookie_secure=1
session.cookie_httponly=1
session.cookie_samesite=Lax
session.use_strict_mode=1
```

## Firewall Baseline

Expose only required ports:

- HTTP/HTTPS through the reverse proxy
- SIP signaling only from trusted carrier/customer networks where possible
- RTP range limited to the configured Asterisk range
- AMI and ARI only on a private management network
- MariaDB and Redis only on private app networks

## Fail2ban Baseline

Add jails for:

- Asterisk SIP/PJSIP registration failures
- admin login failures
- customer login failures
- provider registration/API abuse

## Audit Logging

Use `AuditLogRepository` for balance-impacting actions:

- manual balance adjustments
- refunds
- payment reconciliation corrections
- rate import apply runs
- DID assignment or release
- provider credential rotation
