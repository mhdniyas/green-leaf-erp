# Stage 1 Completion Summary — Central Read-Only Salary Availability Service (Corrected & Verified)

**Project:** Green Leaf ERP  
**Stage:** Stage 1  
**Status:** Review corrections implemented; focused verification passed; awaiting user sign-off  
**Date:** 2026-09-05  

---

## 1. Corrections Implemented in Stage 1

1. **Unified Single & Batch Calculation Engine**:
   - Both `calculate()` and `calculateForShop()` share the identical in-memory calculation method `computeAvailability()`.
   - Attendance summarization expands multi-day leaves, respects paid leave types and consumes the employee-wide allowance in calendar-date order before selecting a shop. The two-shop regression proves the allowance is not restarted per shop.
   - Complete DTO equality is tested between single and batch calculations for the covered attendance, payment, recovery and conflicting/unlinked-record fixtures. This is fixture-based evidence, not exhaustive parity proof.

2. **Company Payment Shop Attribution**:
   - `analyzePayouts()` attributes `PayrollPayment` (Company Cash / Bank payouts) to both employee-wide totals and the assigned shop (`shop_id` or `advanceRequest->shop_id`).
   - Ensures an employee working across shops does not have their remaining shop salary entitlement overstated.

3. **Payroll Month Attribution & Safety on Unlinked Records**:
   - Payroll item, direct payroll run and advance-request months are compared for both payment models. Disagreeing months set conflicting-link status and require HR review; agreeing links establish the month.
   - Unlinked payments require HR review regardless of payment date. An unresolved historical payment can therefore block manager decisions until reconciled; cash date is never used to guess attribution.
   - Both payment models eagerly load every attribution relationship. The populated batch regression compares fresh 1-employee and 20-employee batches, verifies equal query counts bounded at 15 and confirms full DTO equality. The previous sparse-fixture claim of at most 10 queries is withdrawn.

4. **Conflicting Links & Multi-Shop Recovery Safeguards**:
   - Advance requests linked to both a shop staff payment and a company payment set `hasConflictingLinks = true` and `dataQualityStatus = 'unknown'`.
   - Unallocated shop recovery (`openingRecovery > 0` with `shopAllocatedRecovery === null`) sets `dataQualityStatus = 'unknown'` with `"recovery_allocation_unknown"`, preventing auto-approval of ambiguous balances.

5. **Expanded Unit and Feature Test Suites**:
   - [`SalaryAvailabilityArithmeticTest.php`](file:///Users/niyas/Sites/green-leaf-erp/tests/Unit/HR/SalaryAvailabilityArithmeticTest.php): 6 tests exercising `evaluateDecision()` directly (valid/excess advance, valid/excess salary, non-finite amounts `NaN`/`INF`, non-positive amounts, invalid payment types, unknown data quality, and conflicting links).
   - `SalaryAvailabilityServiceTest.php`: 11 tests covering the benchmark, daily wage, attendance weights, multi-shop leave cap, company attribution, conflicting payout/month links, unlinked shop payments across cash dates, complete single/batch equality, populated query-growth checks and read-only calculation. The company-payment schema requires payroll run and item links; orphan database fixtures therefore cover shop payments.

---

## 2. Verification Results

| Test Suite | Total Tests | Passed | Failed | Status |
| --- | --- | --- | --- | --- |
| `SalaryAvailabilityArithmeticTest.php` | 6 | 6 | 0 | **PASSED** |
| `SalaryAvailabilityServiceTest.php` | 11 | 11 | 0 | **PASSED** |
| **Combined Stage 1 Tests** | **17** | **17** | **0** | **PASSED (260 assertions)** |

The new leave-cap, conflicting-month and unlinked-payment regressions failed before the corresponding service fixes. Focused tests then passed on isolated SQLite memory storage, protected by the project's database safety guard. Formatting passed. Populated batch calculation queries are asserted to be SELECT-only; single calculation retains its zero-write check. The full application suite was not run.

---

## 3. Files Created / Modified

- [`app/DTOs/HR/SalaryAvailabilityData.php`](file:///Users/niyas/Sites/green-leaf-erp/app/DTOs/HR/SalaryAvailabilityData.php)
- [`app/DTOs/HR/SalaryPaymentDecision.php`](file:///Users/niyas/Sites/green-leaf-erp/app/DTOs/HR/SalaryPaymentDecision.php)
- [`app/Services/HR/SalaryAvailabilityService.php`](file:///Users/niyas/Sites/green-leaf-erp/app/Services/HR/SalaryAvailabilityService.php)
- [`tests/Unit/HR/SalaryAvailabilityArithmeticTest.php`](file:///Users/niyas/Sites/green-leaf-erp/tests/Unit/HR/SalaryAvailabilityArithmeticTest.php)
- [`tests/Feature/HR/SalaryAvailabilityServiceTest.php`](file:///Users/niyas/Sites/green-leaf-erp/tests/Feature/HR/SalaryAvailabilityServiceTest.php)
- [`stage_1_done.md`](file:///Users/niyas/Sites/green-leaf-erp/stage_1_done.md)

---

## 4. Next Step

The four reported review issues are addressed. Await user sign-off before Stage 2; this is not an exhaustive payroll audit or production deployment approval.

This correction changed only the salary availability service, its feature tests and this report. Existing payment callers, authorization flows, cashbook posting, settings, migrations and production data are unchanged. Unknown historical attribution still needs HR reconciliation. Concurrency, idempotency and payment integration remain for separately approved stages.
