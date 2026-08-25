---
name: laravel-security-audit
description: Review Laravel security-sensitive changes or perform an explicit security audit. Do not load for routine Laravel work.
---

# Laravel Security Audit

Review only; do not modify application code unless separately requested.

Check relevant boundaries:

- Authentication, authorization, policies/gates, Spatie roles and permissions, Sanctum abilities and rate limits.
- IDOR and cross-shop or cross-warehouse access; authorize before record lookup or mutation.
- Form Request validation, mass assignment, raw SQL/bindings, XSS/escaping, and CSRF.
- File/PDF uploads: type, size, storage visibility, filename handling, and download authorization.
- Purchases, invoices, payments, and stock movements: actor authority, immutable audit trail, transaction boundaries, locking, idempotency, and balance/quantity consistency.
- Audit-log integrity; secrets and sensitive data in code, configuration, logs, errors, exports, and API responses.

Use route, policy, request, model, query, test, and schema evidence. For every finding state severity, evidence, exploit scenario, and recommended fix. Report uncertainties and test gaps; do not infer protections without evidence.
