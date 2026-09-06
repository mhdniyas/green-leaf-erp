# Stage 2 Completion Summary — Audit and Idempotency Foundation

**Project:** Green Leaf ERP  
**Stage:** Stage 2  
**Status:** Completed & Verified; awaiting user sign-off  
**Date:** 2026-09-05  

---

## 1. Work Accomplished in Stage 2

1. **Mandatory Duplicate Preflight**:
   - Added a reusable read-only `SalaryStage2PreflightService` covering duplicate shop links, review-only company instalments, cross-table payouts, and conflicting forward/reverse links.
   - Regression tests prove the preflight reports seeded problems without changing records. Company instalments are reported separately and are not blockers by themselves.
   - Confirmed 0 duplicates in local database.
   - Enforced rule: If duplicates exist in any staging/production environment, stop and report immediately without mutations.

2. **Additive Database Migrations**:
   - [`2026_09_05_195201_add_idempotency_and_approval_fields_to_employee_advance_requests_table.php`](file:///Users/niyas/Sites/green-leaf-erp/database/migrations/2026_09_05_195201_add_idempotency_and_approval_fields_to_employee_advance_requests_table.php):
     - `request_uuid`: UUID nullable, unique.
     - `approved_fund_source`: String(30), nullable.
     - `approved_company_account_id`: ForeignId nullable, constrained to `cashbook_company_accounts`, `nullOnDelete()`.
     - `review_snapshot`: JSON nullable.
     - Index: `[payroll_month, status, shop_id]`.
   - [`2026_09_05_195202_add_idempotency_and_unique_request_to_shop_staff_payments_table.php`](file:///Users/niyas/Sites/green-leaf-erp/database/migrations/2026_09_05_195202_add_idempotency_and_unique_request_to_shop_staff_payments_table.php):
     - `request_uuid`: UUID nullable, unique.
     - Unique nullable constraint on `employee_advance_request_id`.
   - [`2026_09_05_195204_add_recovery_fields_to_payroll_run_items_table.php`](file:///Users/niyas/Sites/green-leaf-erp/database/migrations/2026_09_05_195204_add_recovery_fields_to_payroll_run_items_table.php):
     - `opening_recovery_amount`: Decimal(12, 2), nullable (`NULL = unknown`, `0.00 = verified zero`).
     - `closing_recovery_amount`: Decimal(12, 2), nullable.
   - **Deferred Company Constraint**: Preserved company advance instalment payments by keeping `payroll_payments.employee_advance_request_id` non-unique in Stage 2.

3. **Eloquent Model Updates**:
   - [`EmployeeAdvanceRequest.php`](file:///Users/niyas/Sites/green-leaf-erp/app/Models/EmployeeAdvanceRequest.php):
     - `$fillable`: Added `request_uuid`, `approved_fund_source`, `approved_company_account_id`, `review_snapshot`.
     - `$casts`: Added `'review_snapshot' => 'array'`.
     - Relationship: Added `approvedCompanyAccount(): BelongsTo` targeting `App\Models\Cashbook\CompanyAccount`.
   - [`ShopStaffPayment.php`](file:///Users/niyas/Sites/green-leaf-erp/app/Models/ShopStaffPayment.php):
     - `$fillable`: Added `request_uuid`.
   - [`PayrollRunItem.php`](file:///Users/niyas/Sites/green-leaf-erp/app/Models/PayrollRunItem.php):
     - `$fillable`: Added `opening_recovery_amount`, `closing_recovery_amount`.
     - `$casts`: Added `'opening_recovery_amount' => 'decimal:2'`, `'closing_recovery_amount' => 'decimal:2'`.

4. **Automated Feature Verification**:
   - `SalaryStage2MigrationTest.php`: 13 feature tests covering:
     - Factory compatibility with null new fields.
     - Multiple historical null UUID coexistence.
     - Unique non-null `request_uuid` rejection.
     - Unique `shop_staff_payments.employee_advance_request_id` rejection.
     - Company advance instalment capability (multiple company payments permitted per request).
     - Foreign key integrity and `nullOnDelete()` on `cashbook_company_accounts`.
     - Full `review_snapshot` contract serialization.
     - Recovery balance decimal casts and `NULL` vs `0.00` semantics.
     - Existing company-payment UUID uniqueness.
     - Read-only preflight detection, including duplicate shop links and both link directions.
     - Exact Stage 2 migration up/down reversibility without touching unrelated migration batches.
     - Preservation of distinctive historical values and `NULL` new fields across migration application.

5. **Formatting & Zero Regressions**:
   - Formatted modified PHP files with Laravel Pint.
   - Re-ran Stage 1 tests: all 17 tests (260 assertions) passed with zero regressions.

---

## 2. Verification Results

| Test Suite | Tests | Passed | Failed | Assertions | Status |
| --- | --- | --- | --- | --- | --- |
| `SalaryStage2MigrationTest.php` | 13 | 13 | 0 | 73 | **PASSED** |
| `SalaryAvailabilityArithmeticTest.php` | 6 | 6 | 0 | 18 | **PASSED** |
| `SalaryAvailabilityServiceTest.php` | 11 | 11 | 0 | 242 | **PASSED** |
| **Combined Stages 1 & 2 Tests** | **30** | **30** | **0** | **333** | **PASSED** |

---

## 3. Files Created / Modified

- [`database/migrations/2026_09_05_195201_add_idempotency_and_approval_fields_to_employee_advance_requests_table.php`](file:///Users/niyas/Sites/green-leaf-erp/database/migrations/2026_09_05_195201_add_idempotency_and_approval_fields_to_employee_advance_requests_table.php)
- [`database/migrations/2026_09_05_195202_add_idempotency_and_unique_request_to_shop_staff_payments_table.php`](file:///Users/niyas/Sites/green-leaf-erp/database/migrations/2026_09_05_195202_add_idempotency_and_unique_request_to_shop_staff_payments_table.php)
- [`database/migrations/2026_09_05_195204_add_recovery_fields_to_payroll_run_items_table.php`](file:///Users/niyas/Sites/green-leaf-erp/database/migrations/2026_09_05_195204_add_recovery_fields_to_payroll_run_items_table.php)
- [`app/Models/EmployeeAdvanceRequest.php`](file:///Users/niyas/Sites/green-leaf-erp/app/Models/EmployeeAdvanceRequest.php)
- [`app/Models/ShopStaffPayment.php`](file:///Users/niyas/Sites/green-leaf-erp/app/Models/ShopStaffPayment.php)
- [`app/Models/PayrollRunItem.php`](file:///Users/niyas/Sites/green-leaf-erp/app/Models/PayrollRunItem.php)
- [`tests/Feature/HR/SalaryStage2MigrationTest.php`](file:///Users/niyas/Sites/green-leaf-erp/tests/Feature/HR/SalaryStage2MigrationTest.php)
- [`app/Services/HR/SalaryStage2PreflightService.php`](file:///Users/niyas/Sites/green-leaf-erp/app/Services/HR/SalaryStage2PreflightService.php)
- [`stage_2_done.md`](file:///Users/niyas/Sites/green-leaf-erp/stage_2_done.md)

---

## 4. Next Step

Stage 2 is complete, verified, and formatted. Awaiting user sign-off to proceed to **Stage 3 (Cashbook Staff Settings)**.
