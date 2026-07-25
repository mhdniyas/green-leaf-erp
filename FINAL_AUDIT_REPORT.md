# Final Audit Report - Shop Invoice Payment and Cash Journal Flow

Generated: 2026-07-23

## Scope

This report is a focused final audit of the shop invoice payment, owned-shop payment-to-company, and cash journal workflow after the owned-shop duplicate journal posting fix.

The broad production checklist in `Audit.md` was used as the audit framework, but a complete whole-application infrastructure audit was not possible from this local workspace alone. Production server, firewall, TLS, backup jobs, queue workers, scheduler supervision, real `.env`, and live monitoring were not available and are marked not verified.

## Evidence Reviewed

- `app/Services/ShopInvoices/ShopInvoiceService.php`
- `app/Services/Finance/JournalService.php`
- `app/Http/Controllers/Web/Admin/AdminAccountingController.php`
- `app/Http/Controllers/Web/ShopOwnerController.php`
- Relevant Form Requests under `app/Http/Requests/Web/Admin` and `app/Http/Requests/Web/ShopOwner`
- `routes/web.php` accounting and shop-owner payment endpoints
- `tests/Feature/ShopStaffMoneyFlowTest.php`
- `tests/Feature/DailySalesInvoiceActionsTest.php`
- `composer.json`, `composer.lock`, `.env.example`
- `php artisan about`, `php artisan route:list -v --path=accounting --except-vendor`
- `composer validate --no-check-publish`
- `composer audit --format=plain`
- `npm audit --audit-level=low --json`

## A. Executive Summary

- Overall scoped security rating: 6.5 / 10
- Production-readiness rating: 5 / 10
- Architecture-quality rating for audited flow: 7 / 10
- Findings: Critical 1, High 0, Medium 3, Low 1, Informational 2
- Emergency action required: Yes, if production uses the current Composer lock file, because `composer audit` reports a critical `phpoffice/phpspreadsheet` advisory.
- Public accessibility: The audited accounting fix is acceptable after tests passed, but production should not be considered fully cleared until dependency advisories and production config are remediated/verified.

Top immediate risks:

1. Critical and high dependency advisories reported by Composer.
2. Regular shop payment approval uses `accounting.dashboard.view`, which appears too broad for a cash-posting action.
3. Payment approval flows check pending status but do not explicitly lock the payment request/company payment row before state transition.
4. Production configuration could not be verified; local workspace is `APP_ENV=local` and debug enabled.
5. Full route/model/security audit is incomplete outside this payment surface.

## B. Architecture and Attack-Surface Map

Entry points reviewed:

- Shop owner regular shop payment request: `POST /shop-owner/accounting/payment-requests`
- Shop owner owned-shop payment to company: `POST /shop-owner/finance/payments`
- Admin regular shop payment request review: `PATCH /admin/accounting/shop-invoice-payment-requests/{paymentRequest}/review`
- Admin regular shop direct/manual invoice payment: `PATCH /admin/accounting/shop-invoices/{invoice}/payment`
- Admin owned-shop daily bill payment: `PATCH /admin/accounting/owned-shops/{shop:code}/daily-bills/{invoice}/payment`
- Admin owned-shop company payment review: `PATCH /admin/accounting/owned-shops/{shop:code}/company-payments/{credit}/review`

Trust boundaries:

- Shop users may submit payment requests only for their current shop.
- Owned shops are blocked from regular invoice payment requests and must use payment-to-company.
- Admin/accounting users approve or reject financial state transitions.
- Journal entries are created only from approved non-owned shop invoice payments after the fix.

Sensitive workflows:

- Regular shop invoice payment approval: cash IN journal entry.
- Owned shop daily bill payment: invoice/payment state only, no cash journal.
- Owned shop company payment approval: owned shop cash balance movement; cash flow reporting source is `shop_credits`.

## C. Detailed Findings

### F-001 - Composer Dependency Advisories Include Critical Spreadsheet Vulnerability

- Severity: Critical
- Category: Supply chain / dependency security
- Affected files: `composer.lock`
- Exploitability: Moderate
- Impact: Critical
- Confidence: Confirmed
- Status: Vulnerable if production uses this lock file

Problem:

`composer audit --format=plain` reported 18 advisories affecting 8 packages. The highest risk is `phpoffice/phpspreadsheet` with a critical advisory. It also reported high/medium advisories in `spatie/laravel-medialibrary`, `laravel/framework`, `guzzlehttp/guzzle`, `guzzlehttp/psr7`, and Symfony packages.

Evidence:

- `composer audit --format=plain` exited with code 1.
- Critical: `phpoffice/phpspreadsheet`, CVE-2026-45034.
- High: `spatie/laravel-medialibrary`, CVE-2026-48557.
- Medium: `laravel/framework` affected by Temporary Signed URL Path Confusion for `>=13.0.0,<13.12.0`.

Business impact:

For an ERP with financial and operational records, exploitable dependency vulnerabilities can lead to file-processing compromise, SSRF, URL/signature confusion, data disclosure, or denial of service depending on reachable package usage.

Recommended fix:

Run dependency upgrades in a controlled branch, prioritizing:

- `laravel/framework` to at least `13.12.0`.
- `phpoffice/phpspreadsheet` to a non-affected version.
- `spatie/laravel-medialibrary` to at least `11.23.0`.
- `guzzlehttp/guzzle`, `guzzlehttp/psr7`, and affected Symfony packages to non-affected versions.

Required test:

- `composer audit --format=plain`
- `php artisan test --compact`
- Any spreadsheet import/export and media upload tests.

Verification:

`composer audit` must return no security advisories or only explicitly risk-accepted advisories.

### F-002 - Owned Shop Invoice Payment No Longer Posts Duplicate Cash IN Journal

- Severity: Medium before fix, now resolved
- Category: Financial business logic
- Affected file: `app/Services/ShopInvoices/ShopInvoiceService.php`
- Affected methods: `reviewPaymentRequest`, `recordAdminPaymentReceived`, `approvePayment`
- Routes: owned daily bill payment, shop payment request review, regular direct invoice payment
- Exploitability: Easy before fix
- Impact: High financial reporting impact
- Confidence: Confirmed
- Status: Fixed

Problem:

Owned shops already return cash through the payment-to-company flow. Before the fix, invoice payment approval could also call `JournalService::recordShopInvoicePayment()`, causing an extra cash `IN` journal entry for an owned shop.

Evidence:

- Journal posting now passes through `shouldPostPaymentToJournal()` in `ShopInvoiceService`.
- `shouldPostPaymentToJournal()` loads the invoice shop and returns false for `isOwnedAccountingEnabled()`.
- Regular shops still post journal entries.

Business impact:

Duplicate `IN` journal rows inflate cash and sales income, causing false cash flow and incorrect accounting reports.

Corrected code:

The corrected behavior is implemented in `ShopInvoiceService::shouldPostPaymentToJournal()` and used before all shop invoice payment journal calls.

Required test:

`tests/Feature/ShopStaffMoneyFlowTest.php::test_admin_owned_shop_daily_bill_payment_does_not_post_shop_invoice_journal`

Verification:

Passed:

```bash
php artisan test --compact --filter='owned_shop_daily_bill_payment_does_not_post|owned_shop_finance_payments_record_company_cash_out_and_regular_shops_use_invoice_payment_requests|regular_shop_overpayment_allocates' tests/Feature/ShopStaffMoneyFlowTest.php
php artisan test --compact tests/Feature/DailySalesInvoiceActionsTest.php
```

### F-003 - Regular Shop Payment Approval Permission Appears Too Broad

- Severity: Medium
- Category: Authorization / financial approval
- Affected files:
  - `app/Http/Requests/Web/Admin/ReviewShopInvoicePaymentRequest.php`
  - `app/Http/Requests/Web/Admin/ApproveShopInvoicePaymentRequest.php`
- Affected methods: `authorize`
- Routes:
  - `PATCH /admin/accounting/shop-invoice-payment-requests/{paymentRequest}/review`
  - `PATCH /admin/accounting/shop-invoices/{invoice}/payment`
- Exploitability: Moderate
- Impact: High
- Confidence: Medium
- Status: Potentially vulnerable

Problem:

Both request classes authorize using `AccountingAccess::canViewDashboard()`. These endpoints approve payment and can create cash journal entries. A read/dashboard permission is usually too broad for a money-posting action.

Evidence:

`ReviewShopInvoicePaymentRequest::authorize()` returns `AccountingAccess::canViewDashboard($this->user())`.

Business impact:

Any user with dashboard view permission may be able to approve/reject regular shop payments and post cash journal entries, depending on role/permission seeding.

Recommended fix:

Use a dedicated financial approval permission, likely `AccountingAccess::InvoiceApprove`, or introduce `accounting.payment.approve`. Align the Form Requests and route middleware with that permission.

Required test:

Add a feature test proving a user with only `accounting.dashboard.view` cannot approve shop invoice payments, while a user with the approval permission can.

### F-004 - Payment Approval State Transitions Lack Explicit Row Locks

- Severity: Medium
- Category: Financial race condition
- Affected files:
  - `app/Services/ShopInvoices/ShopInvoiceService.php`
  - `app/Http/Controllers/Web/Admin/AdminAccountingController.php`
- Affected methods: `reviewPaymentRequest`, `recordAdminPaymentReceived`, `reviewCompanyPayment`
- Exploitability: Difficult to Moderate
- Impact: Medium to High
- Confidence: Medium
- Status: Potentially vulnerable

Problem:

The journal service has a source identity unique constraint and uses `lockForUpdate()` while checking existing journal entries, which is good. However, payment request/company payment state is checked as `pending` before approval without an explicit lock on the request row. Concurrent approvals could race through allocation/status updates even if duplicate journal entries are partially constrained.

Evidence:

- `ShopInvoiceService::reviewPaymentRequest()` checks `$paymentRequest->status !== 'pending'` inside a transaction but does not reload the row with `lockForUpdate()`.
- `AdminAccountingController::reviewCompanyPayment()` checks `$credit->status !== 'pending'` and then updates without a transaction/row lock around the whole approval and balance sync.

Business impact:

Duplicate approval attempts can cause duplicate allocations, stale balance decisions, confusing audit logs, or inconsistent cash balance updates.

Recommended fix:

Inside the transaction, reload the payment request or shop credit with `lockForUpdate()` before checking status. Keep balance calculation and final update in the same transaction.

Required test:

Add a concurrency/idempotency test or service-level test that calls approval twice and asserts one state transition, one set of allocations, and one financial effect.

### F-005 - Production Environment Not Verified From Local Workspace

- Severity: Low
- Category: Production configuration
- Affected files: `.env.example`, live `.env` not inspected
- Exploitability: Not verified
- Impact: Medium
- Confidence: Confirmed for local only
- Status: Not verified

Problem:

The local environment reports `Environment local` and `Debug Mode ENABLED`. This is acceptable locally, but production values were not available. `.env.example` also defaults to local/debug values, which is common for development templates but not proof of production safety.

Evidence:

`php artisan about --only=environment` reported:

- Laravel Version: 13.11.2
- PHP Version: 8.4.17
- Environment: local
- Debug Mode: ENABLED
- URL: green-leaf-erp.test

Recommended fix:

Verify production has:

- `APP_ENV=production`
- `APP_DEBUG=false`
- valid `APP_KEY`
- HTTPS URL
- secure session cookies
- queue workers and scheduler supervised
- backups and restore tests

### F-006 - NPM Audit Passed

- Severity: Informational
- Category: Supply chain
- Status: Secure for npm audit scope

Evidence:

`npm audit --audit-level=low --json` reported zero vulnerabilities.

### F-007 - Composer Manifest Validation Passed

- Severity: Informational
- Category: Dependency hygiene
- Status: Secure for manifest validity

Evidence:

`composer validate --no-check-publish` reported `./composer.json is valid`.

## D. Route Security Matrix - Audited Payment Surface

| Method | Route | Action | Auth | Authorization | Ownership check | CSRF | Risk | Status |
|---|---|---|---|---|---|---|---|---|
| POST | `/shop-owner/accounting/payment-requests` | `ShopOwnerController@storePaymentRequest` | Yes | `sales.order.create` + shop role request auth | Query constrains `shop_id`; owned shops aborted | Yes | Regular shop payment request | Passed |
| POST | `/shop-owner/finance/payments` | `ShopOwnerController@storeCompanyPayment` | Yes | `sales.order.create` + shop role request auth | Uses current owned accounting shop | Yes | Owned shop cash paid to company | Passed with race hardening recommended |
| PATCH | `/admin/accounting/shop-invoice-payment-requests/{paymentRequest}/review` | `AdminAccountingController@reviewShopInvoicePaymentRequest` | Yes | `accounting.dashboard.view` | Payment request owns invoice via service, no tenant issue found | Yes | Cash journal posting | Needs stricter permission |
| PATCH | `/admin/accounting/shop-invoices/{invoice}/payment` | `AdminAccountingController@updateShopInvoicePayment` | Yes | `accounting.dashboard.view` | Global admin accounting scope | Yes | Cash journal posting | Needs stricter permission |
| PATCH | `/admin/accounting/owned-shops/{shop:code}/daily-bills/{invoice}/payment` | `AdminAccountingController@updateDailyBillPayment` | Yes | `accounting.owned-shop.manage` | Confirms invoice shop matches route shop | Yes | Owned bill payment state | Passed |
| PATCH | `/admin/accounting/owned-shops/{shop:code}/company-payments/{credit}/review` | `AdminAccountingController@reviewCompanyPayment` | Yes | `accounting.owned-shop.manage` | Confirms credit shop and type | Yes | Owned company cash payment | Passed with race hardening recommended |

## E. Model Security Matrix - Audited Models

| Model | Sensitive fields | Ownership field | Fillable status | Main risk |
|---|---|---|---|---|
| `ShopInvoice` | `paid_amount`, `balance_amount`, `payment_status`, `discount_total`, `payment_approved_by` | `shop_id` | Explicit fillable | Financial state changes must stay service-controlled |
| `ShopInvoicePaymentRequest` | `requested_amount`, `approved_amount`, `applied_amount`, `credit_amount`, `status`, `reviewed_by` | `shop_id` | Explicit fillable | Needs row-lock hardening for approval |
| `ShopCredit` | `type`, `amount`, `status`, `is_petty_cash`, `reviewed_by` | `shop_id` | Explicit fillable observed indirectly | Needs transaction/row-lock hardening for approval |
| `JournalEntry` | `source_type`, `source_id`, `source_event`, `created_by` | source identity | Explicit fillable not fully audited | Source identity unique constraint is present |

## F. Production Configuration Report

| Item | Status | Evidence |
|---|---|---|
| `APP_ENV` | Not verified for production | Local reports `local` |
| `APP_DEBUG` | Not verified for production | Local reports enabled |
| `APP_KEY` | Not verified | `.env.example` intentionally blank |
| HTTPS / HSTS | Not verified | Server config unavailable |
| Session security | Partially verified | Session driver local: database; production cookie flags not verified |
| CORS | Not verified | Full config not audited |
| Trusted proxies/hosts | Not verified | Server/proxy config unavailable |
| Queue | Partially verified | Local `about` reports database queue; `.env.example` says Redis |
| Scheduler | Not verified | Process supervision unavailable |
| Backups | Not verified | No backup config reviewed |
| Mail | Partially verified | Local mail driver log |
| Storage | Partially verified | Local filesystem driver |
| Database | Partially verified | Local driver MySQL |
| Composer dependencies | Failed | `composer audit` reported advisories |
| NPM dependencies | Passed | `npm audit` reported zero vulnerabilities |

## G. Prioritized Remediation Plan

### Emergency

| Finding | Action | Complexity | Regression risk | Testing | Downtime |
|---|---|---|---|---|---|
| F-001 | Upgrade vulnerable Composer packages and rerun audit/tests | Medium | Medium | Full test suite, import/export/media tests | Usually no |

### Within 24 Hours

| Finding | Action | Complexity | Regression risk | Testing | Downtime |
|---|---|---|---|---|---|
| F-003 | Replace dashboard-view authorization on payment approval with a payment/invoice approval permission | Small | Low to Medium | Authorization feature tests | No |
| F-005 | Verify production `.env`, HTTPS, queue, scheduler, backups | Small | Low | Config check and smoke tests | No |

### Within 7 Days

| Finding | Action | Complexity | Regression risk | Testing | Downtime |
|---|---|---|---|---|---|
| F-004 | Add `lockForUpdate()` around payment request and company payment approval rows | Medium | Medium | Duplicate approval/race tests | No |

### Within 30 Days

| Finding | Action | Complexity | Regression risk | Testing | Downtime |
|---|---|---|---|---|---|
| Full audit gap | Complete whole-app route/model/security audit from `Audit.md` | Large | Low | Full suite plus manual security checks | No |

## H. Final Production Checklist

| Item | Status |
|---|---|
| Owned-shop invoice approval does not create cash `IN` journal | Passed |
| Regular-shop payment approval still creates cash journal | Passed |
| Shop owner cannot use regular payment request flow for owned shop | Passed |
| Owned-shop company payment approval checks shop ownership and type | Passed |
| Journal source identity uniqueness exists | Passed |
| Focused tests passed | Passed |
| PHP formatting passed | Passed |
| Composer manifest valid | Passed |
| Composer dependency audit clean | Failed |
| NPM dependency audit clean | Passed |
| Production `.env` safe | Not verified |
| Production TLS/security headers | Not verified |
| Production backups and restore tests | Not verified |
| Queue/scheduler supervision | Not verified |
| Full application route matrix | Not verified |
| Full model security matrix | Not verified |

## Verification Commands Run

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter='owned_shop_daily_bill_payment_does_not_post|owned_shop_finance_payments_record_company_cash_out_and_regular_shops_use_invoice_payment_requests|regular_shop_overpayment_allocates' tests/Feature/ShopStaffMoneyFlowTest.php
php artisan test --compact tests/Feature/DailySalesInvoiceActionsTest.php
php artisan route:list -v --path=accounting --except-vendor
php artisan about --only=environment
composer validate --no-check-publish
composer audit --format=plain
npm audit --audit-level=low --json
```

