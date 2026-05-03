# A2BillingPlus Security Patch Matrix

Last reviewed: 2026-05-03.

This matrix tracks known public A2Billing-family advisories against the current
A2BillingPlus tree. It is not a full penetration test; it is a patch-status
review for known legacy issues before UI replacement work.

## Public Advisory Coverage

| Advisory | Legacy issue | Current status | Evidence |
| --- | --- | --- | --- |
| SysDream, "A2Billing <= 1.9.4 Multiple vulnerabilities" | Reflected XSS in `customer/checkout_payment.php` payment error output. | Patched in current tree. | `customer/checkout_payment.php` renders both error title and message with `tep_output_string_protected()`. |
| SysDream, "A2Billing <= 1.9.4 Multiple vulnerabilities" | Missing CSRF protection on legacy form actions. | Partially patched, still treated as high-risk on legacy UI. | `common/lib/Form/Class.FormHandler.inc.php` enables CSRF token generation and validation by default. Legacy pages that bypass `FormHandler` still need review before production exposure. |
| SysDream, "A2Billing <= 1.9.4 Multiple vulnerabilities" | SQL injection path through export CSV/session query handling. | Needs targeted follow-up before legacy export UI is production-approved. | Static search found legacy export/query code in admin/contrib paths. New module APIs use repositories and bound parameters, but old export surfaces remain a UI hardening concern. |
| CVE-2015-1875 / Elastix A2Billing Iridium 3-D Secure SQL injection | SQL injection in `a2billing/customer/iridium_threed.php` in Elastix packaging. | Main customer path not present; contrib copy remains isolated. | No `customer/iridium_threed.php` exists in the main customer portal. A contrib copy exists under `addons/contrib/epayment-iridium/customer/iridium_threed.php`; do not deploy contrib gateways without review. |

## Production Gate Notes

- Keep legacy direct-card flows disabled unless a hosted/tokenized provider is
  used. The checkout callback now blocks direct card payloads by default and
  redacts card fields in callback logs.
- Treat legacy admin, agent, and customer pages as compatibility surfaces until
  their workflows are replaced by module APIs and redesigned screens.
- Do not enable contrib payment gateways in production without a focused review
  of SQL construction, callback authentication, and secret/card-data handling.

## Sources

- SysDream: https://sysdream.com/a2billing-1-9-4-multiple/
- CVE-2015-1875 summary: https://vulners.com/cve/CVE-2015-1875
