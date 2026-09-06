# Stage 6 Completion Summary: HR Exception Approval & Accounting

**Completed At:** 2026-09-05T20:32:55+05:30  
**Phase:** Stage 6 (HR Exception Approval & Accounting)

---

## 1. Accomplished Work

1. **HR Exception Approval Workflow & Concurrency:**
   - Injected `PayrollPaymentService` and `SalaryAvailabilityService` into `EmployeeAdvanceService`.
   - Upgraded `EmployeeAdvanceService::review()` with pessimistic row locking (`lockForUpdate()`) and deadlock retry attempts (3).
   - Enforced strict state check: only `pending` requests can be reviewed. Attempting to review an already approved or rejected request throws a `ValidationException`.
   - Disallowed empty rejection notes: rejecting an advance request strictly requires a `review_note`.
   - Validated that approved amounts must be greater than zero and cannot exceed requested amounts without explicit policy override.

2. **Dual-Rail Approval Payouts:**
   - **Shop-Funded Approval (`sales_income` or `petty_cash`):**
     - Creates exactly one `ShopStaffPayment`.
     - Automatically posts exactly one line to the shop cashbook with the mapped funding source (`sales` or `petty`).
     - Links `shop_staff_payment_id` on the request.
     - Rejects requests that attempt to specify a company account for shop-funded approvals.
   - **Company-Funded Approval (`company_cash` or `company_bank`):**
     - Strictly requires an active, compatible `cashbook_company_accounts` record (`cash` for `company_cash`, `bank` for `company_bank`).
     - Invokes `PayrollPaymentService::record` to generate a `PayrollPayment` (`payment_type = 'advance'`).
     - Creates balanced double-entry `JournalEntry` (Debit: Employee Advances asset, Credit: Cash/Bank asset).
     - Generates outbound company statement entry and reconciles it.
     - Links `payroll_payment_id` and `approved_company_account_id` on the request.
     - Generates ZERO shop cashbook entries or payments (no dual-leakage).

3. **Audit & Calculation Snapshot:**
   - Recalculates latest availability at time of approval using `SalaryAvailabilityService::calculate()`.
   - Stores authoritative `review_snapshot` JSON on `employee_advance_requests` recording approver ID, name, timestamp, requested source, approved source, approved company account, approved amount, current attendance, earned salary, existing advances, existing salary payments, opening recovery, and normal available amount.

4. **Form Request & Controller Actions:**
   - Updated `ReviewEmployeeAdvanceRequest` to validate `fund_source` in `['sales_income', 'petty_cash', 'company_cash', 'company_bank']`, `company_account_id` required for company funding, and `review_note` required on rejection.
   - Updated `StaffManagementController::reviewEmployeeAdvance` to pass approved funding source and company account.
   - Updated `StaffManagementController::advancePayments` to pass enabled `companyAccounts` to view.

5. **Automated Verification:**
   - Implemented `tests/Feature/Admin/HRExceptionApprovalTest.php`:
     - `test_approve_with_shop_sales_cash`: PASSED
     - `test_approve_with_company_cash_creates_payroll_payment_and_journal_with_no_shop_cashbook`: PASSED
     - `test_approve_with_company_bank`: PASSED
     - `test_reduce_requested_amount_on_approval`: PASSED
     - `test_reject_with_note`: PASSED
     - `test_reject_without_note_fails`: PASSED
     - `test_disabled_company_account_fails`: PASSED
     - `test_source_account_mismatch_fails`: PASSED
     - `test_double_approval_fails`: PASSED
   - Clean Pint styling: 0 errors.

---

## 2. Test Results

```
Tests: 9 passed (42 assertions)
Duration: 1.53s
Pint: Passed (0 styling errors)
```
