# Product Requirements Document

## Staff Salary, Advance Approval and Cashbook Integration

**Project:** Green Leaf ERP  
**Status:** Ready for staged implementation  
**Implementation environment:** Local first  
**Production server:** `139.59.40.78`  
**Production deployment:** Only after explicit approval  
**Production data policy:** Do not delete, rewrite, backfill, or create test financial transactions in production.

---

# 1. Required skill routing

Do not activate every skill for every stage. Use only the skills assigned below.

| Stage   | Required skills                                                         | Purpose                                        |
| ------- | ----------------------------------------------------------------------- | ---------------------------------------------- |
| Stage 0 | `$graphify`, `$lean-build`                                              | Confirm existing connections and freeze scope  |
| Stage 1 | `$laravel-best-practices`, `$lean-build`                                | Centralize salary calculations                 |
| Stage 2 | `$migration`, `$laravel-best-practices`                                 | Add reversible data and idempotency structures |
| Stage 3 | `$ui-ux-pro-max`, `$laravel-best-practices`                             | Design and implement cashbook settings         |
| Stage 4 | `$ui-ux-pro-max`, `$tailwindcss-development`, `$laravel-best-practices` | Build the shop-owner salary interface          |
| Stage 5 | `$surgical-patch`, `$laravel-best-practices`                            | Safely process normal manager payments         |
| Stage 6 | `$surgical-patch`, `$laravel-best-practices`                            | Implement HR exception approval and accounting |
| Stage 7 | `$laravel-best-practices`, `$migration` if required                     | Connect payroll recovery and carry-forward     |
| Stage 8 | `$laravel-security-audit`, `$verify-and-stop`                           | Security, reconciliation and acceptance review |
| Stage 9 | `$migration`, `$verify-and-stop`                                        | Safe production deployment and verification    |

Before making Laravel code changes, use Laravel Boost `search-docs` for version-specific documentation.

---

# 2. Product summary

Shop managers need to:

- See how much salary an employee has earned to date.
- Give an advance of no more than 50% of earned salary.
- Record monthly or partial salary payments.
- Choose whether shop-funded payments came from sales cash or petty cash.
- Request HR approval when a payment exceeds the normal permitted amount.

HR/admin users need to:

- Review exception advance requests.
- See both the request-time calculation and the current calculation.
- Approve, reduce, or reject the request.
- Select the final funding source.
- Select a company cash/bank account when the company pays.
- Produce exactly one accounting movement for the approved payment.

The cashbook must:

- Record the expense on the actual payment date.
- Reduce the correct shop or company money source.
- Avoid duplicate accounting entries.
- Keep salary, payroll, advance and cashbook records linked for auditing.

---

# 3. Pages in scope

## Admin advance approval

`/admin/staff/advance-payments?payroll_month=2026-09`

Purpose:

- Review pending advance exceptions.
- View approved and rejected requests.
- Recalculate eligibility before approval.
- Select the final funding source.
- Select a company account where applicable.

## Cashbook settings

`/admin/cashbook/settings`

Purpose:

- Configure global staff salary and staff advance categories.
- Configure the default manager funding source.
- Ensure settings apply to every eligible owned/client shop.

## Shop-owner staff salary

`/shop-owner/staff?shop=AV_ASHIRWAD&date=2026-09-05&tab=salary`

Purpose:

- Display employee salary availability.
- Give an advance.
- Record salary paid.
- Select sales cash or petty cash.
- Submit an HR approval request when necessary.

---

# 4. Current implementation summary

The existing application already contains most of the required building blocks:

```text
Shop-owner salary page
 ├── Salary payment form
 ├── Advance request form
 └── ShopOwnerStaffController
      └── EmployeeAdvanceService
           ├── PayrollService
           ├── ShopStaffPayment
           ├── EmployeeAdvanceRequest
           └── OwnedShopAccountingService
                └── Shop cashbook entry
```

The company-funded payroll path is separate:

```text
PayrollPaymentService
 ├── PayrollPayment
 ├── CompanyAccount
 ├── JournalEntry
 └── CompanyAccountStatementEntry
```

Current gaps:

- Advance eligibility uses present days, while payroll uses weighted payable attendance.
- A configured minimum-present-days rule can block the five-day example.
- The salary and advance forms hide the funding source as petty cash.
- Salary payments and advances are not displayed separately in all calculations.
- Previous negative balances are not clearly carried into the next month.
- Admin approval does not currently recalculate and lock the complete eligibility state.
- Admin cannot select a final source or company account from the approval form.
- Shop staff payment submission does not have complete request UUID protection.
- Eligibility can be calculated before the transaction, allowing concurrent submissions to exceed the limit.
- The cashbook line supports a funding source, but the staff posting currently does not populate it.
- Staff accounting categories and the newer cashbook settings system are not exposed through one simple settings interface.

---

# 5. Product goals

## Primary goals

1. Enforce the 50% earned-salary advance rule.
2. Support the five-day example.
3. Account for advances already paid.
4. account for previous recoverable balances.
5. Allow shop managers to record salary paid.
6. Allow visible sales cash or petty cash selection.
7. Route exceptions to HR/admin.
8. Allow admin to select a company cash/bank account.
9. Update the correct cashbook/account on the payment date.
10. Prevent duplicate requests, approvals, payments and accounting entries.
11. Preserve a complete audit trail.
12. Preserve all existing production records.

## Non-goals

The following are outside this implementation:

- Redesigning the entire cashbook architecture.
- Editing historical salary or cashbook entries automatically.
- Adding employee loans or loan-interest calculations.
- Calculating statutory deductions, tax, provident fund or insurance.
- Initiating real bank transfers.
- Automatically correcting existing duplicate financial data.
- Replacing the existing payroll system.
- Changing unrelated attendance rules.
- Adding a separate mobile application.
- Adding a new dependency or frontend framework.

---

# 6. Actors and permissions

## Shop manager/shop owner

May:

- View employees assigned to an authorized shop.
- View salary availability for that shop.
- Give an advance within the normal permitted amount.
- Record salary paid up to the remaining earned amount.
- Choose sales cash or petty cash.
- Create an HR approval request when outside the normal rule.

May not:

- Access another shop through a changed URL or employee ID.
- Select a company account.
- Directly pay an amount that requires HR approval.
- Modify an approved or paid request.
- Delete a financial payment.
- Override the central calculation.

## HR/admin approver

May:

- View pending requests within the permitted administration scope.
- Approve, reduce or reject a pending request.
- Select the final funding source.
- Select a company account when using company cash or bank.
- Approve an exception above the normal manager limit.
- View the audit calculation and request history.

May not:

- Approve the same request twice.
- Approve a request that is no longer pending.
- Use a disabled or unauthorized company account.
- create both a shop-funded and company-funded payment for the same request.

## Cashbook administrator

May:

- Configure the global salary and advance categories.
- Change the default shop funding source.
- Activate or deactivate category availability for future entries.

Settings changes must not rewrite historical entries.

---

# 7. Salary calculation contract

All screens and payment actions must use one server-side calculation service.

## Calendar divisor

For monthly employees:

```text
Daily rate = monthly salary ÷ actual number of days in payroll month
```

Examples:

- February 2025: divide by 28.
- February 2024: divide by 29.
- April: divide by 30.
- January: divide by 31.

For daily-wage employees:

```text
Daily rate = configured daily wage
```

## Payable attendance units

The proposed unified rule is:

| Attendance type     | Payable unit |
| ------------------- | -----------: |
| Present             |         1.00 |
| Half-day            |         0.50 |
| Approved paid leave |         1.00 |
| Unpaid leave        |         0.00 |
| Absent              |         0.00 |

This matches payroll behavior and prevents the advance screen and payroll screen from calculating different earned amounts.

## Earned salary

```text
Earned salary to date =
    daily rate × payable attendance units through calculation date
```

Do not round the daily rate before multiplication. Round the resulting monetary values to two decimal places.

## Normal manager advance ceiling

```text
Manager advance ceiling =
    earned salary to date × 50%
```

## Available manager advance

```text
Available manager advance =
    manager advance ceiling
    − advances already paid for the payroll month
    − opening recoverable balance
```

The displayed available amount is never negative:

```text
Displayed available advance = maximum of ₹0 or calculated availability
```

The signed internal balance must still be retained so HR can see why approval is required.

## Remaining salary payable

```text
Remaining salary payable =
    earned salary to date
    − opening recoverable balance
    − advances already paid
    − salary payments already made
```

The manager cannot directly pay more than the positive remaining amount.

## Required example

```text
Monthly salary:                    ₹15,000
Calendar days:                           30
Daily rate:                            ₹500
Payable attendance:                       5
Earned salary:                       ₹2,500
Normal advance ceiling:              ₹1,250
Previous advance this month:             ₹0
Opening recoverable balance:             ₹0
Available manager advance:           ₹1,250
```

## Previous-month recovery

At month close:

```text
Signed closing salary balance =
    earned/payable salary
    − opening recoverable balance
    − advances
    − salary payments
```

If the result is negative:

```text
Next month opening recoverable balance =
    absolute value of negative closing balance
```

Example:

```text
Previous month payable salary:       ₹3,000
Previous month total paid:           ₹4,000
Closing balance:                    -₹1,000
Next month opening recovery:         ₹1,000
```

The ₹1,000 must be recovered only once.

---

# 8. Funding and accounting contract

## Sales cash

When the source is sales cash:

```text
Payment
 └── ShopStaffPayment
      └── Shop cashbook expense
           ├── payment date = cashbook business date
           ├── funding source = sales
           ├── P/L expense decreases by amount
           └── shop settlement/sales cash decreases by amount
```

Company cash/bank must not be reduced.

## Petty cash

When the source is petty cash:

```text
Payment
 └── ShopStaffPayment
      └── Shop cashbook expense
           ├── payment date = cashbook business date
           ├── funding source = petty
           ├── P/L expense decreases by amount
           └── petty cash decreases by amount
```

Company cash/bank must not be reduced.

## Company cash or bank

When the company pays:

```text
Approved request
 └── PayrollPayment
      ├── Selected CompanyAccount
      ├── Balanced JournalEntry
      └── Outbound CompanyAccountStatementEntry
```

The shop’s sales cash and petty cash must not change.

Company-funded payment is visible through the company cashbook/account flow. It must not also create a cash-effect shop entry.

## Accounting entries

### Salary payment from company account

At the payment stage:

```text
Debit:  Salary Payable
Credit: Selected Company Cash/Bank
```

Salary expense should already have been recognized during payroll finalization.

### Advance from company account

At the advance-payment stage:

```text
Debit:  Employee Advance/Receivable
Credit: Selected Company Cash/Bank
```

At payroll recovery:

```text
Debit:  Salary Payable
Credit: Employee Advance/Receivable
```

## Non-negotiable invariant

```text
One real payout
→ one payment record
→ one authoritative cash movement
→ one linked accounting path
```

A payout must never create both:

- A shop cash reduction, and
- A company account reduction.

---

# 9. Advance request lifecycle

## Normal manager advance

```text
Manager submits
 ├── Authorize shop and employee
 ├── Validate request UUID and fields
 ├── Start database transaction
 ├── Lock employee/month payment records
 ├── Recalculate eligibility
 ├── Confirm amount is within available limit
 ├── Create approved advance request
 ├── Create one ShopStaffPayment
 ├── Create one shop cashbook line
 ├── Link all records
 └── Commit
```

Result:

- Request status: `approved`
- Payment created immediately.
- Cashbook updated immediately.

## Exception request

```text
Manager submits
 ├── Recalculate eligibility
 ├── Amount exceeds normal availability
 ├── Create pending request
 └── Do not create a payment or cashbook entry
```

Result:

- Request status: `pending`
- No money movement.
- No salary paid total update.
- HR/admin review required.

## Admin approval

```text
Pending request
 ├── Lock request
 ├── Confirm request is still pending
 ├── Lock employee/month payment records
 ├── Recalculate current eligibility
 ├── Validate approved amount
 ├── Validate final funding source
 ├── Validate company account when applicable
 ├── Store review snapshot
 ├── Create one payment through correct path
 ├── Link request and payment
 └── Commit
```

## Rejection

```text
Pending request
 ├── Review note required
 ├── Status becomes rejected
 ├── Reviewer and review time stored
 └── No payment or accounting movement
```

Only these transitions are allowed:

```text
pending → approved
pending → rejected
```

Approved and rejected requests cannot be reviewed again.

---

# 10. Salary payment lifecycle

Managers may record a partial or final salary payment.

```text
Manager submits salary payment
 ├── Verify authorized shop
 ├── Verify employee assignment on payment date
 ├── Validate payment date
 ├── Validate funding source
 ├── Start transaction
 ├── Lock employee/month records
 ├── Recalculate remaining salary
 ├── Reject amount above remaining salary
 ├── Create ShopStaffPayment with type salary
 ├── Create one cashbook expense
 ├── Link payroll item and cashbook line
 └── Commit
```

Salary and advances must remain separate:

```text
Salary paid total  = payment_type salary
Advance paid total = payment_type advance
```

The UI must never combine these into one unexplained “paid” amount.

---

# 11. Proposed data changes

All changes must use new additive migrations. Never edit a migration already deployed to production.

## Employee advance requests

Retain existing fields and links.

Proposed additions:

| Field                         | Purpose                                                                                  |
| ----------------------------- | ---------------------------------------------------------------------------------------- |
| `request_uuid`                | Prevent duplicate manager submissions                                                    |
| `approved_fund_source`        | Preserve the administrator’s final source separately from the manager’s requested source |
| `approved_company_account_id` | Link the selected company account                                                        |
| `review_snapshot`             | Store current calculation and decision facts at approval time                            |

Existing `fund_source` remains the manager’s requested source.

Recommended constraints:

- Unique nullable `request_uuid`.
- Index on payroll month, status and shop.
- Foreign key for approved company account.
- Only one payment may be linked to an advance request.

Before adding a unique payment-link constraint, run a read-only production preflight to identify any existing duplicates. If duplicates exist, stop and report them; do not delete or merge them automatically.

## Shop staff payments

Proposed addition:

| Field          | Purpose                                                |
| -------------- | ------------------------------------------------------ |
| `request_uuid` | Prevent duplicate salary or advance payment submission |

Recommended constraints:

- Unique nullable `request_uuid`.
- Unique nullable `employee_advance_request_id`, after duplicate preflight.
- Preserve existing salary, advance, fund source and status fields.

## Payroll run items

Proposed additive fields:

| Field                     | Purpose                                                |
| ------------------------- | ------------------------------------------------------ |
| `opening_recovery_amount` | Prior recoverable balance applied to the payroll month |
| `closing_recovery_amount` | Remaining recoverable amount carried to the next month |

Both fields should initially be nullable for compatibility. Application logic treats null as zero.

Do not backfill historical payroll rows during the schema migration.

For the first new payroll month, the service may calculate opening recovery from existing payroll and payment records and store it when the new payroll item is generated or finalized.

## Cashbook lines

No new funding-source column is required because one already exists.

Required behavior change:

- Populate the existing `funding_source`.
- Preserve the existing source type, source ID and source event links.
- Keep cash effect consistent with the selected source.
- Use existing source uniqueness to avoid duplicate lines.

---

# 12. Stage-by-stage implementation

## Stage 0 — Rule and architecture freeze

### Skills

- `$graphify`
- `$lean-build`

### Objective

Create the final implementation map and confirm every business rule before code changes.

### Work

- Trace shop-owner forms to their controllers and services.
- Trace advance approval to payment creation.
- Trace shop-funded cashbook posting.
- Trace company-funded payroll posting.
- Confirm which attendance statuses produce payable units.
- Confirm that the five-day example must pass.
- Confirm source behavior.
- Run read-only checks for duplicate payment links.
- Record existing focused tests that will be extended.

### Deliverables

- Approved calculation table.
- Approved source-effect table.
- Approved data migration design.
- Current-to-proposed dependency tree.
- Exact file list for Stage 1.
- Production preflight query list.

### Acceptance gate

No unresolved calculation or funding-source decision remains.

### Excluded

- Code changes.
- Migrations.
- Database writes.
- Production deployment.

---

## Stage 1 — Central salary availability service

### Skills

- `$laravel-best-practices`
- `$lean-build`

### Objective

Create one authoritative source for all employee salary calculations.

### Proposed responsibility

`SalaryAvailabilityService` or an equally suitable existing service seam will calculate:

- Payroll month.
- Calculation date.
- Calendar days.
- Daily rate.
- Attendance breakdown.
- Payable attendance units.
- Earned salary to date.
- Opening recoverable balance.
- Advances paid.
- Salary paid.
- Normal advance ceiling.
- Available advance.
- Remaining salary.
- Whether HR approval is required.
- Human-readable approval reasons.

### Proposed output contract

```text
employee_id
shop_id
payroll_month
calculation_date
salary_type
monthly_salary
daily_rate
calendar_days
present_days
half_days
paid_leave_days
payable_units
earned_to_date
opening_recovery
advance_ceiling
advances_paid
salary_paid
available_advance
remaining_salary
approval_required
approval_reasons
```

### Implementation constraints

- No calculation in Blade templates.
- No duplicate calculation in controllers.
- Server output is authoritative.
- Use existing payroll attendance rules.
- Batch calculations for the shop employee list.
- Avoid per-employee repeated rule and aggregate queries.
- Keep controllers thin.

### Tests

- 28-day month.
- 29-day leap-year month.
- 30-day month.
- 31-day month.
- Five-day/₹1,250 example.
- Half-days.
- Paid leave.
- Absent and unpaid leave.
- Daily-wage employee.
- Existing advance.
- Existing salary payment.
- Opening recoverable balance.
- Zero attendance.
- Rounding edge cases.
- Multiple employees without excessive query growth.

### Acceptance gate

Every salary and advance number can be produced by this service, and no affected screen needs its own formula.

---

## Stage 2 — Audit and idempotency foundation

### Skills

- `$migration`
- `$laravel-best-practices`

### Objective

Add the minimum compatibility-safe structures needed before changing write behavior.

### Work

- Run read-only duplicate preflight.
- Generate new focused migrations.
- Add request UUID fields.
- Add final approval-source fields.
- Add approval calculation snapshot.
- Add recoverable-balance fields.
- Add safe indexes and foreign keys.
- Add unique constraints only when existing data permits.
- Update model fillable/casts/relationships/default behavior.
- Keep old readers and writers working during the transition.

### Migration rules

- One schema concern per migration.
- No DML backfill inside the schema migration.
- Reversible `down()` methods.
- No modification of deployed migrations.
- New nullable fields first.
- Old code must continue working while new columns are empty.
- No production migration until Stage 9.

### Tests

- Migrate forward.
- Roll back.
- Migrate forward again.
- Existing factories still work.
- Existing records load with null new fields.
- Duplicate UUID is rejected.
- Duplicate request payment is rejected.
- Deleting a company account follows approved foreign-key behavior without deleting a request.

### Acceptance gate

The schema supports safe new behavior while preserving all historical rows.

---

## Stage 3 — Cashbook staff settings

### Skills

- `$ui-ux-pro-max`
- `$laravel-best-practices`

### Objective

Add a simple staff-payment configuration section to the existing cashbook settings page.

### Interface

Section title:

```text
Staff Salary & Advances
```

Fields:

- Salary expense category name.
- Salary category active/inactive.
- Advance expense category name.
- Advance category active/inactive.
- Default manager source: petty cash or sales cash.
- Advance percentage, displayed as 50%.
- Minimum payable attendance units, displayed as zero unless Stage 0 decides otherwise.

### Behavior

- Global categories have no shop ID and apply to all eligible owned/client shops.
- A shop-specific category overrides the global category if one already exists.
- Saving settings affects only future postings.
- Historical cashbook lines retain their original category and source.
- Saving settings creates no financial transaction.

### UX requirements

- Use visible labels, not placeholders only.
- Provide inline validation errors.
- Preserve entered values after failure.
- Disable the submit button while saving.
- Show clear success or failure feedback.
- Explain that changes apply to future salary/advance entries.
- Do not expose unrelated cashbook internals.

### Tests

- Authorized admin can view and update.
- Unauthorized users receive 403.
- Global category applies to eligible shops.
- Existing shop override wins.
- Inactive category blocks a new payment with a clear error.
- Historical entries remain unchanged.
- Settings submission creates no cashbook entry.

### Acceptance gate

The administrator can configure staff categories without touching accounting data.

---

## Stage 4 — Shop-owner salary interface

### Skills

- `$ui-ux-pro-max`
- `$tailwindcss-development`
- `$laravel-best-practices`

### Objective

Give the shop manager one clear place to understand and pay employee salary.

### Employee summary card

Display:

- Employee name and role.
- Monthly salary.
- Daily salary rate.
- Payable attendance.
- Earned salary to date.
- Previous recoverable balance.
- Advance paid.
- Salary paid.
- Available advance.
- Remaining salary.
- Approval status where applicable.

### Actions

Two primary actions:

```text
Give Advance
Record Salary Paid
```

### Advance form

Fields:

- Employee.
- Payroll month.
- Payment/request date.
- Available advance.
- Requested amount.
- Funding source: sales cash or petty cash.
- Optional note.
- Request UUID hidden/generated by the client.

Behavior:

- Show whether the amount can be paid directly.
- If over the limit, clearly state that HR approval will be requested.
- Do not pretend the request is paid until a payment exists.

### Salary form

Fields:

- Employee.
- Payroll month.
- Payment date.
- Remaining salary.
- Amount paid.
- Funding source: sales cash or petty cash.
- Optional note.
- Request UUID.

### UX requirements

- Mobile-first responsive layout.
- Full labels for every control.
- Amount helper text showing the maximum.
- Loading state while submitting.
- Disable repeated submission.
- Inline errors linked to their fields.
- Focusable error summary for multiple errors.
- Success confirmation after payment/request.
- Do not rely on color alone.
- Stable layout when errors appear.
- No hidden funding-source field.
- No query or calculation in Blade.

The UI/UX search produced verified guidance for validation feedback and accessible error summaries. No specific HTML/Tailwind stack result was found, so implementation should follow the project’s existing Tailwind component conventions and general accessible-form guidance.

### Tests

- Correct employee totals displayed.
- Salary and advance totals shown separately.
- Sales and petty options visible.
- Company source not available to managers.
- Old values preserved after validation error.
- Cross-shop employee ID rejected.
- Unassigned employee rejected.
- Duplicate click creates one request/payment.
- Mobile markup does not hide required information.

### Acceptance gate

The shop manager can understand the calculation before submitting and cannot bypass server rules.

---

## Stage 5 — Normal manager payment engine

### Skills

- `$surgical-patch`
- `$laravel-best-practices`

### Objective

Safely process manager salary payments and advances that are within the normal limit.

### Transaction boundary

The following must happen in one transaction:

1. Validate UUID.
2. Lock employee/payroll-month records.
3. Lock relevant advance/payment rows.
4. Recalculate availability.
5. Verify shop assignment.
6. Verify permitted funding source.
7. Create payment.
8. Create cashbook entry.
9. Save links.
10. Commit.

Use a deadlock retry count consistent with project conventions.

### Advance behavior

If amount is within availability:

- Create approved request.
- Create one advance payment.
- Create one shop cashbook entry.
- Update displayed availability.

If amount is above availability:

- Create a pending request only.
- Do not create payment.
- Do not create cashbook entry.

### Salary behavior

- Reject amounts above remaining salary.
- Create one salary payment.
- Create one shop cashbook entry.
- Recalculate remaining salary after payment.

### Funding mapping

```text
sales_income → sales funding source
petty_cash   → petty funding source
```

The cashbook line must store the mapped funding source.

### Failure behavior

If any step fails:

- No request should be incorrectly approved.
- No payment should remain.
- No cashbook line should remain.
- No balance should change.

### Tests

- Valid advance.
- Valid salary payment.
- Advance exactly at limit.
- One paise above limit creates pending request.
- Salary exactly at remaining amount.
- Salary above remaining amount rejected.
- Closed accounting period rolls back everything.
- Missing category rolls back everything.
- Concurrent requests cannot exceed availability.
- Same UUID cannot create a duplicate.
- Correct date and funding source stored.

### Acceptance gate

Normal manager payments are atomic, idempotent and correctly reflected in the shop cashbook.

---

## Stage 6 — HR/admin exception approval

### Skills

- `$surgical-patch`
- `$laravel-best-practices`

### Objective

Allow HR to safely approve exceptional advances and select the final funding source.

### Approval page information

For each request, display:

- Employee.
- Shop.
- Payroll month.
- Requested date.
- Requested amount.
- Manager-requested source.
- Request-time earned salary.
- Request-time advance ceiling.
- Request-time advances already paid.
- Request-time available amount.
- Current earned salary.
- Current advances already paid.
- Current salary already paid.
- Current recoverable balance.
- Current available amount.
- Difference between request-time and current eligibility.
- Request note.
- Approval reason.

### Approval fields

- Decision: approve or reject.
- Approved amount.
- Final funding source.
- Company account when final source is company cash/bank.
- Review note.

### Validation

- Only pending requests may be reviewed.
- Approved amount must be greater than zero.
- Approved amount must not exceed the requested amount unless a separately approved business rule allows it.
- Company account is required for company funding.
- Company account must be active and compatible with cash/bank method.
- Shop source must not accept a company account.
- Rejection requires a note.
- The latest server calculation must be shown and saved.

### Shop-funded approval

Creates:

- One ShopStaffPayment.
- One shop cashbook expense.
- One request-to-payment link.

### Company-funded approval

Creates:

- One PayrollPayment.
- One balanced journal.
- One outbound company account statement.
- One request-to-payment link.

It does not create a shop cash-effect payment.

### Audit snapshot

Store:

- Approver.
- Approval timestamp.
- Requested source.
- Final source.
- Selected company account.
- Approved amount.
- Current attendance.
- Earned salary.
- Existing advances.
- Existing salary payments.
- Opening recovery.
- Normal available amount.
- Reason the payment was exceptional.

### Tests

- Approve with sales cash.
- Approve with petty cash.
- Approve with company cash.
- Approve with company bank.
- Reduce requested amount.
- Reject with note.
- Reject without note fails.
- Disabled company account fails.
- Source/account mismatch fails.
- Attendance changed after request.
- Another advance paid after request.
- Same request approved twice.
- Concurrent approval creates one payment.
- No double posting between shop and company paths.

### Acceptance gate

Every approval produces one auditable decision and no more than one payout.

---

## Stage 7 — Payroll recovery and month close

### Skills

- `$laravel-best-practices`
- `$migration` only if Stage 2’s approved schema requires adjustment

### Objective

Recover advances and prior negative balances exactly once during payroll.

### Payroll calculation

```text
Gross/payable salary
− opening recoverable balance
− advances already paid
− salary payments already made
= remaining salary payment
```

### Month close

Calculate:

- Opening recovery.
- Payable salary.
- Advances.
- Salary payments.
- Signed closing balance.
- Closing recovery.

Store the recovery snapshot on the payroll run item when payroll is finalized.

### Company advance clearing

When company-funded advances are recovered:

```text
Debit:  Salary Payable
Credit: Employee Advance/Receivable
```

Do not record another cash movement during recovery because the cash left the company when the advance was originally paid.

### Compatibility

- Keep existing clamped “remaining amount” behavior available where older screens depend on it.
- Add a separately named signed balance for recovery calculations.
- Do not silently change historical finalized payroll totals.
- New finalized runs use the new recovery calculation.

### Tests

- Advance smaller than salary.
- Advance equal to salary.
- Advance greater than salary.
- Recovery spanning more than one month.
- Multiple shop and company advances.
- Salary already partially paid.
- Recovery applied once.
- Regenerating draft payroll does not duplicate recovery.
- Finalized payroll cannot be silently recalculated.

### Acceptance gate

Payroll, shop salary and admin approval show reconciled values, and carry-forward occurs exactly once.

---

## Stage 8 — Security, reconciliation and acceptance

### Skills

- `$laravel-security-audit`
- `$verify-and-stop`

### Objective

Prove the completed feature is safe before production deployment.

### Security checks

- Manager cannot change shop ID to another shop.
- Manager cannot submit an employee from another shop.
- Manager cannot select company funding.
- Non-HR user cannot approve.
- HR cannot use an unauthorized company account.
- Validated data only is passed to services.
- Notes and employee data are escaped.
- No salary information is exposed to an unauthorized user.
- Request UUID cannot be reused for a different payment.
- Status transition cannot be replayed.

### Reconciliation checks

For each payment:

```text
Payment amount
=
Linked authoritative cash movement amount
```

System-wide:

```text
Shop-funded staff payments
=
Linked shop cashbook lines
```

```text
Company-funded staff payments
=
Linked company account statement entries
```

Required zero-count conditions:

- Zero approved requests without a payment link.
- Zero payments with two accounting paths.
- Zero duplicate cashbook source links.
- Zero duplicate payments for one advance request.
- Zero company-funded payments reducing shop cash.
- Zero shop-funded payments reducing company cash.

### Test suite

Run:

1. New focused calculation tests.
2. New request/payment feature tests.
3. Existing HR tests affected by the change.
4. Existing cashbook HR finance tests.
5. Existing payroll tests.
6. Authorization and tenant-scope tests.
7. Migration tests.
8. Formatter on changed PHP files.
9. Frontend production build if frontend assets changed.

### Acceptance gate

All required tests pass and the security audit has no unresolved high-severity issue.

---

## Stage 9 — Production deployment

### Skills

- `$migration`
- `$verify-and-stop`

### Objective

Deploy the completed and tested feature without altering existing production financial records.

### Pre-deployment

- Confirm clean local test results.
- Confirm exact deployment commit.
- Confirm production branch and current revision.
- Take a database backup.
- Confirm backup is readable.
- Run read-only duplicate preflight queries.
- Confirm migrations are additive.
- Confirm no migration performs historical DML.
- Record rollback commands.
- Check available disk space and application health.

### Deployment

1. Connect to `139.59.40.78`.
2. Confirm the correct project directory.
3. Fetch the approved commit.
4. Install dependencies only if the lock file requires it.
5. Put the application in maintenance mode only if necessary.
6. Run additive migrations.
7. Clear and rebuild application caches.
8. Build frontend assets if required.
9. Restore normal application access.
10. Check recent application errors.

### Production smoke tests

Read-only checks first:

- Open cashbook settings.
- Open admin advance approvals.
- Open shop-owner salary page using an authorized account.
- Verify existing employee values render.
- Verify available sources display correctly.
- Verify no existing totals changed.
- Verify no new payment or cashbook row was created by viewing pages.

Do not submit a test salary or advance using a real production employee.

### Rollback

If application behavior fails:

- Restore the previous application commit.
- Rebuild caches.
- Leave additive nullable columns in place unless their rollback is proven safe.
- Do not run destructive down-migrations after real new payments have been created.
- Restore the database only if a confirmed deployment operation corrupted data.

### Acceptance gate

Production pages operate correctly, existing balances remain unchanged, and no new financial record was created during smoke testing.

---

# 13. Acceptance criteria

The feature is complete only when all conditions below pass.

## Calculation

- ₹15,000 ÷ 30 × 5 = ₹2,500 earned.
- Maximum normal advance is ₹1,250.
- Actual month length is used.
- Attendance uses the agreed payable-unit rules.
- Existing advances reduce availability.
- Previous recovery reduces availability.
- Salary payments reduce remaining salary.

## Manager workflow

- Funding source is visible.
- Manager may choose only sales or petty cash.
- Valid advances are paid and posted once.
- Invalid advances become pending without money movement.
- Salary cannot exceed remaining earned salary.
- Duplicate submission does not create duplicate records.

## Admin workflow

- Admin sees request-time and current calculations.
- Admin can approve, reduce or reject.
- Admin chooses the final source.
- Company funding requires an account.
- Repeated approval cannot create a second payment.
- Every decision is audited.

## Accounting

- Sales cash reduces the correct shop sales/settlement balance.
- Petty cash reduces the correct petty balance.
- Company funding reduces the selected company account.
- Cashbook date equals payment date.
- Salary and advance categories remain distinct.
- One payout has one accounting path.
- Closed periods reject the complete operation.

## Data safety

- No historical record is deleted.
- No deployment migration silently rewrites financial records.
- New constraints are preceded by duplicate preflight.
- All new writes are transactional.
- All new write paths are idempotent.
- Every payment can be traced to its request, actor and accounting entry.

---

# 14. Success metrics

After deployment:

- 100% of new staff payments have one linked accounting movement.
- 0 duplicate payments caused by repeated submission.
- 0 advance approvals paid twice.
- 0 cross-shop authorization failures resulting in data mutation.
- 0 company-funded payments reducing shop cash.
- 0 shop-funded payments reducing company accounts.
- 100% of new requests retain request-time and approval-time calculation evidence.
- Salary, advance, payroll and cashbook totals reconcile for each employee/month.

---

# 15. Main implementation risks

| Risk                                               | Severity | Mitigation                                                            |
| -------------------------------------------------- | -------- | --------------------------------------------------------------------- |
| Double shop/company posting                        | Critical | One authoritative posting branch selected inside one transaction      |
| Concurrent advances exceeding limit                | Critical | Lock and recalculate inside the transaction                           |
| Duplicate manager submissions                      | High     | Request UUID plus unique database index                               |
| Duplicate admin approval                           | Critical | Lock request and enforce pending-only transition                      |
| Historical duplicate links blocking a unique index | High     | Read-only preflight; stop instead of deleting records                 |
| Attendance changes after request                   | High     | Recalculate at approval and preserve both snapshots                   |
| Settings-system mismatch                           | High     | Add a narrow staff settings bridge; do not redesign the full cashbook |
| Prior balance recovered twice                      | Critical | Persist month recovery snapshot and test month-to-month transition    |
| Closed-period partial write                        | Critical | Check inside the same transaction and roll back all records           |
| Multi-employee page query growth                   | Medium   | Batch attendance and payment aggregates; query-count test             |
| Production migration risk                          | High     | Additive nullable migrations, backup and application rollback         |

---

# 16. Stage execution protocol

Before starting each stage, provide:

```text
Stage name
Objective
Skills being activated
Files expected to change
Database impact
Business rules
Accounting impact
Tests to add
Explicit exclusions
Rollback approach
```

Wait for approval.

After completing each stage, provide:

```text
Files changed
Rules implemented
Migration status
Focused tests run
Test results
Formatting/build results
Known risks
Commit hash
Next stage proposal
```

Do not automatically begin the next stage.

Do not connect to or deploy production until Stage 9 receives explicit approval.
