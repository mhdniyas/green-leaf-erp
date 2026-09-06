# Stage 7 Completed: Payroll Recovery & Month Close

## Summary of Accomplishments

1. **Signed Salary & Recovery Calculations on `PayrollRunItem`:**
   - Added `signedSalaryRemaining(): float` to `PayrollRunItem` calculating `final_amount - opening_recovery_amount - paidAmount()`.
   - Updated `PayrollService::finalize()` to calculate and persist `opening_recovery_amount`, `closing_recovery_amount`, and complete audit details into `rule_snapshot`:
     - Prior month's closing recovery amount is automatically loaded as this month's `opening_recovery_amount`.
     - Advances paid (shop + company) and salary paid are accounted for.
     - Signed closing balance is calculated as `final_amount - openingRecovery - totalAdvances - totalSalaryPaid`.
     - If `signedClosingBalance < 0`, the negative amount is saved to `closing_recovery_amount` for automatic recovery in subsequent periods.

2. **Company Advance Clearing Journal Integration:**
   - Implemented `recordCompanyAdvanceClearing(PayrollRun $payrollRun, float $amount, int $userId): ?JournalEntry` in `PayrollService`.
   - When company advances were paid during the payroll period (`totalCompanyAdvances > 0`), finalizing the run automatically creates a non-cash advance clearing journal entry:
     - **Debit:** Account `2300` (Salary Payable)
     - **Credit:** Account `1600` (Employee Advances)
     - `sourceType: PayrollRun`, `sourceEvent: 'advance_clearing'`.
   - Verified that company advance clearing affects only ledger accounts, with zero cash/bank movements.

3. **Immutability of Finalized Runs:**
   - Safeguarded `PayrollService::generate()` to detect existing finalized runs for the same period and throw a `RuntimeException` ('Finalized payroll runs cannot be regenerated.').

4. **Programmatic Verification:**
   - Created `tests/Feature/HR/PayrollRecoveryMonthCloseTest.php` covering:
     - Advance smaller than salary (remaining salary reduced, closing recovery = 0).
     - Advance equal to salary (remaining salary = 0, closing recovery = 0).
     - Advance greater than salary (signed balance < 0, closing recovery > 0 stored on item).
     - Recovery carryover to next month: next month's finalized run picks up previous month's `closing_recovery_amount` as `opening_recovery_amount`.
     - Company advance clearing journal entry (`Debit: 2300, Credit: 1600`) created with NO cash movement.
     - Finalized payroll run throws exception if `generate()` is called.
   - Result: 6 tests, 23 assertions, 100% passing.
