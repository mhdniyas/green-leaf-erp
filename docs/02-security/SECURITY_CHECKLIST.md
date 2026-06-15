# SECURITY CHECKLIST

**Green Leaf Traders — Pre-Deployment Security Checklist**
**Version**: 1.0.0 | **Classification**: MANDATORY

> Run through this checklist before every sprint release and production deployment.
> A missed security item is a potential ERP breach.

---

## ✅ AUTHENTICATION

- [ ] All API routes that return data require `auth:sanctum` middleware
- [ ] Login endpoint has rate limiting (`throttle:5,1`)
- [ ] Password reset tokens expire (24 hours)
- [ ] Tokens have expiration set in `config/sanctum.php`
- [ ] Logout properly deletes the current token
- [ ] Failed login attempts are logged
- [ ] No plain-text password stored anywhere (use `Hash::make()`)

---

## ✅ AUTHORIZATION

- [ ] Every FormRequest has `authorize()` method that checks permissions
- [ ] Every controller action that modifies data uses `$this->authorize()` or FormRequest
- [ ] Policy exists for every Eloquent model
- [ ] Policies are registered in `AppServiceProvider`
- [ ] No route is accessible without appropriate role middleware
- [ ] Super-admin role is the ONLY role with `forceDelete` permission

---

## ✅ INPUT VALIDATION

- [ ] Every POST/PUT/PATCH endpoint uses a FormRequest class
- [ ] No `$request->all()` passed directly to `Model::create()`
- [ ] All string fields have `max:255` or appropriate max length
- [ ] Foreign keys validated with `exists:table,id`
- [ ] Enum values validated with `Rule::enum(EnumClass::class)`
- [ ] File uploads validated by MIME type AND size
- [ ] No `Rule::in([...])` with user-controlled values without whitelist

---

## ✅ MASS ASSIGNMENT

- [ ] All models have explicit `$fillable` array
- [ ] No model has `$guarded = []`
- [ ] `role`, `is_admin`, `balance`, `email_verified_at` are NOT in `$fillable`
- [ ] User model does not expose password through API Resource

---

## ✅ SQL INJECTION

- [ ] No raw DB queries with string interpolation (`"WHERE id = {$id}"`)
- [ ] All raw queries use parameter binding (`?` placeholders)
- [ ] Eloquent used for all standard queries (parameterized automatically)
- [ ] `whereRaw()` and `selectRaw()` use parameter arrays, not interpolation

---

## ✅ XSS

- [ ] Blade templates use `{{ }}` not `{!! !!}` for user content
- [ ] JSON data in Blade uses `@json($data)` directive
- [ ] No user content inserted as raw HTML without sanitization
- [ ] Content-Security-Policy header configured (or planned)

---

## ✅ CSRF

- [ ] All web form routes POST to CSRF-protected endpoints
- [ ] `@csrf` directive in all Blade forms
- [ ] API routes use Sanctum token (not session CSRF)
- [ ] CSRF middleware is NOT disabled globally

---

## ✅ SECURITY HEADERS

The `SecureHeaders` middleware handles these — verify it's registered:

- [ ] `X-Content-Type-Options: nosniff` — ✅ (SecureHeaders middleware)
- [ ] `X-Frame-Options: DENY` — ✅ (SecureHeaders middleware)
- [ ] `X-XSS-Protection: 1; mode=block` — ✅ (SecureHeaders middleware)
- [ ] `Strict-Transport-Security` — ✅ (SecureHeaders middleware)
- [ ] `Referrer-Policy` — ✅ (SecureHeaders middleware)
- [ ] `Permissions-Policy` — ✅ (SecureHeaders middleware)

---

## ✅ DATA PROTECTION

- [ ] Sensitive fields in model `$hidden`: `password`, `remember_token`, `two_factor_secret`
- [ ] API Resources exclude sensitive fields
- [ ] No secrets logged (passwords, API keys, tokens)
- [ ] Audit log captures all data mutations
- [ ] Financial data changes are always audited

---

## ✅ FILE UPLOADS

- [ ] File type validated by MIME (`getMimeType()`), not by extension
- [ ] File size limits enforced in FormRequest rules
- [ ] Uploaded files stored outside `public/` when sensitive
- [ ] File names sanitized before storage
- [ ] Spatie MediaLibrary used for all media management

---

## ✅ ENVIRONMENT

- [ ] `.env` is in `.gitignore` — never committed
- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` in production
- [ ] `APP_KEY` is set and kept secret
- [ ] Database password is strong and unique
- [ ] All third-party API keys are least-privilege
- [ ] No `APP_KEY` or database credentials in version control

---

## ✅ RATE LIMITING

- [ ] Auth endpoints rate limited: `throttle:5,1` (5/min)
- [ ] API endpoints rate limited: `throttle:60,1` (60/min)
- [ ] Password reset: `throttle:3,1`
- [ ] Rate limiting keyed by user ID (authenticated) or IP (unauthenticated)

---

## ✅ AUDIT TRAIL

- [ ] All models with financial data implement `Auditable`
- [ ] All models implement `LogsActivity`
- [ ] Audit logs cannot be deleted by non-super-admin
- [ ] Audit logs record: who, what, when, before, after

---

## ✅ PRODUCTION DEPLOYMENT

Before going live:

- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] `composer install --optimize-autoloader --no-dev`
- [ ] HTTPS enforced (not just HSTS header)
- [ ] Database backups configured and tested
- [ ] Error monitoring set up (Sentry or equivalent)
- [ ] `composer audit` passes (no known vulnerable packages)
- [ ] `php artisan test --compact` passes 100%

---

**Owner**: Engineering Lead + Security Team
**Project**: Green Leaf Traders
**Review Cycle**: Before every sprint release
