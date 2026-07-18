# Production Security Checklist

This file is the pre-production checklist for Green Leaf ERP. Complete every item before switching the production environment live.

## Current Decision

- Keep this file in the repo root as the active production/security checklist.
- Remove the old `docs/` tree from the app source before production.
- Do not remove the test suite from source control. Exclude `tests/` from the deployment artifact instead.
- Base seeding must be production-safe and must not create demo orders, demo users, or test bills.
- Set `BASE_ROLE_USER_PASSWORD` only during the initial production seed if base role/shop users need immediate login access, then rotate each account password after first login.

## Production Base Seeders

Approved in `DatabaseSeeder`:

- `RolePermissionSeeder`
- `ChartOfAccountsSeeder`
- `EmployeeCategorySeeder`
- `CategorySeeder`
- `ProductSeeder`
- `WarehouseSeeder`
- `ShopAccountingCategorySeeder`
- `EssentialUserSeeder`
- `AdminOwnPurchasePurchaserSeeder`

Removed from production source:

- `CustomerSeeder`
- `DemoUserSeeder`
- `JuneStaffAttendanceSeeder`
- `JulyFourteenDailySalesSeeder`
- `JulyFourteenShopOwnerOrderSeeder`
- `JulySeventeenShopOwnerPurchaseOrderSeeder`
- `OwnedShopDemoSeeder`
- `PriceBoardSeeder`
- `PurchaseOrderSeeder`
- `PurchaserDemoSeeder`
- `PurchaserRoleTestSeeder`
- `ShopEmployeeSeeder`
- `ShopOwnerBillTestSeeder`
- `SupplierSeeder`
- `WarehouseReceiverSeeder`
- `WarehouseWorkflowSeeder`
- `WebsiteEnquirySeeder`

Production command:

```bash
php artisan migrate --force
php artisan db:seed --force
```

## Environment

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` is set and not shared.
- `APP_URL` is the final HTTPS domain.
- HTTPS is enforced at the web server or proxy.
- Cookies are secure in production.
- Database user has only required privileges.
- Queue, cache, session, and mail drivers are production services, not local defaults.
- Logs do not expose secrets, passwords, tokens, or customer financial data.
- `.env`, storage backups, database dumps, and logs are not web-accessible.

## Role Protection

Verify every protected surface with feature tests or manual role-login checks:

- `admin`: full system access only for trusted admin users.
- `shop`: only assigned/owned shop data, orders, deliveries, finance, staff attendance, leave, advance requests.
- `purchase`: purchase approvals, prices, GRN, sort sheet, purchase dashboards.
- `purchaser`: direct purchase and assigned purchasing workflow only.
- `warehouse_receiver`: warehouse receive, checklist, sorting, wastage, sort sheet.
- `hr_manager`: employees, assignments, attendance, leave, payroll, payments, advance payments.

Required checks:

- Every `POST`, `PUT`, `PATCH`, and `DELETE` route uses a Form Request or explicit authorization.
- Every admin page has a gate, policy, permission, or role check.
- Every shop-owner page scopes records to `ownedShopAssignments`, not only `user.shop_id`.
- Every HR assignment/payment/attendance action is server-side scoped, not only hidden in Blade.
- Every accounting approval/payment action requires admin/accounting permission.
- Every purchasing approval action rejects shop users and ordinary purchasers unless explicitly allowed.
- Every warehouse action rejects shop users and unrelated staff.
- Removed routes such as old sales orders return 404 or are absent from `route:list`.

## Permission Matrix Audit

Use this command to inspect current permissions:

```bash
php artisan tinker --execute 'Spatie\Permission\Models\Role::with("permissions")->get()->each(fn ($role) => dump($role->name, $role->permissions->pluck("name")->values()->all()));'
```

Audit questions:

- Does `shop` have only shop-safe permissions?
- Does `hr_manager` have no accounting/admin-user permissions?
- Does `purchase` have no HR payroll or admin-user permissions?
- Does `warehouse_receiver` have no finance, HR, or admin-user permissions?
- Are legacy permissions from removed modules still unused or removed?

## Route Audit

Run before release:

```bash
php artisan route:list --except-vendor
```

Check:

- All sensitive routes are inside `auth` middleware.
- Public routes are limited to login/logout/password/public website flows.
- API routes use authentication and authorization.
- No debug, test, demo, docs, seed, or local-only routes are exposed.

## Data Ownership

Check these flows with two users from different shops:

- Shop owner cannot view another shop order.
- Shop owner cannot view another shop invoice.
- Shop owner cannot view another shop accounting entry.
- Shop owner cannot mark attendance for unrelated employees.
- Shop owner cannot request advance/salary for unrelated employees.
- HR can assign employees only to approved owned/partnership shops.
- Admin accounting can view company-level accounting; shop users cannot.

## Financial Safety

- Purchaser credits cannot be created by non-admin users.
- Shop invoice payments cannot exceed allowed balances.
- Shop staff salary/advance cannot exceed payroll remaining rules.
- Cashbook entries preserve approval history.
- Approved records are locked or changes are tracked.
- Journal posting behavior is intentional and tested where relevant.

## Input Validation

- All request classes use `authorize()`.
- All money fields validate numeric minimums and maximums.
- All dates validate expected ranges.
- All IDs validate existence and ownership scope.
- File uploads, if enabled later, validate MIME type, extension, and size.

## Frontend / Blade

- Sensitive buttons are hidden by permission and protected server-side.
- Blade output uses escaped `{{ }}` unless HTML is intentionally trusted.
- No secrets or internal stack traces are rendered in views.
- Mobile navigation exposes the same permissions as desktop navigation.

## Production Build

Run:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Do not deploy:

- `docs/`
- `tests/`
- `.env`
- `.git/`
- `node_modules/`
- local database files
- screenshots, pasted attachments, or temporary exports

## Release Verification

Minimum commands before release:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
php artisan route:list --except-vendor
php artisan view:cache
```

Manual smoke test:

- Login as admin.
- Login as shop owner.
- Login as purchase manager.
- Login as purchaser.
- Login as warehouse receiver.
- Login as HR manager.
- Confirm each role sees only its expected sidebar sections and cannot open another role's URLs directly.
