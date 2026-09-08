# Shop Accounting: Paid, Payable, and Dynamic Settlement Categories

Study date: 8 September 2026  
Scope: current shop accounting, daily entries, settlement settings, and shop payments.  
Status: flow study and proposed business behavior. Implementation planning comes next.

## 1. Main Finding

The application has useful foundations: configurable category groups, settlement formulas, company bank mappings, verified receipts, and payment allocations. However, the complete flow requested here is not implemented.

**The missing connection is a mandatory Paid/Payable choice on each expense, linked to a dynamically configured account or settlement bucket, with the same result used by daily accounting and Payments.**

The latest clarification is included: existing categories currently predefined as payable or paid from Company must become configurable. Cash, Petty, and Company are examples of configured choices, not the complete permanent list.

Three facts must remain distinguishable for every entry:

| Fact | Example | Purpose |
| --- | --- | --- |
| Expense category | Fuel | Explains what the expense was for |
| Payment state | Paid or Payable | Explains whether money has moved |
| Selected account or settlement bucket | Shop Cash, Petty, configured Company category | Explains where money came from or where the unpaid amount belongs |

The grouping can remain familiar. For example, Fuel may appear under Cash Purchase when that is the configured group. Its amount must be recorded once, even when both the group total and the account total display it.

## 2. What the Current Application Does

### Category Settings and Groups

Shop settings already define enabled categories, display names, groups, default funding, allowed funding sources, company account mappings, and effects on sales, expenses, payable, petty, and company pending balances.

Groups can also supply a cash-flow mode, such as Shop Cash, Petty, Company, or a company account. The owner sees the enabled groups and enters amounts under them.

The limitation is that funding behavior still resolves through fixed values such as `sales`, `petty`, `company`, and `company_later`. Configurable names and groups do not yet create a general dynamic account-selection system.

Evidence: [CashFlowResolutionService.php](/Users/niyas/Sites/green-leaf-erp/app/Services/Cashbook/CashFlowResolutionService.php:16), [FundingSource.php](/Users/niyas/Sites/green-leaf-erp/app/Enums/Cashbook/FundingSource.php:7), and [ShopOwnerController.php](/Users/niyas/Sites/green-leaf-erp/app/Http/Controllers/Web/ShopOwnerController.php:1050).

### Daily Entry

The current owner form primarily captures an amount and an optional or required note. Its save request sends the funding source already resolved from settings. It does not capture a separate Paid/Payable choice and a dynamic account selected for that particular entry.

The server accepts a nullable funding source. The transaction generator falls back to category defaults. The transaction's existing status describes its workflow, such as Posted or Approved; it does not independently describe whether the expense is Paid or Payable.

The database schema confirms that the current shop transaction has funding and balance-effect fields, but no separate expense payment-state field. A `relation_id` column exists, but the reviewed owner entry request and transaction creation do not use it as a required per-entry account selection.

Evidence: [header-entry.blade.php](/Users/niyas/Sites/green-leaf-erp/resources/views/shop-owner/cashbook/partials/modals/header-entry.blade.php:38), [scripts.blade.php](/Users/niyas/Sites/green-leaf-erp/resources/views/shop-owner/cashbook/partials/scripts.blade.php:1068), [ShopOwnerController.php](/Users/niyas/Sites/green-leaf-erp/app/Http/Controllers/Web/ShopOwnerController.php:1610), and [TransactionGenerator.php](/Users/niyas/Sites/green-leaf-erp/app/Services/Cashbook/TransactionGenerator.php:33).

### Balance Effects

Without explicit category overrides, the current expense resolver behaves as follows:

| Current funding source | Effect for a Rs. 100 expense |
| --- | --- |
| Sales / Shop Cash | Expense +100; shop settlement balance -100 |
| Petty | Expense +100; petty balance -100 |
| Company, Bank, or External | Expense +100; no shop settlement, petty, or company-pending change in this resolver |
| Company Later | Expense +100; company pending +100 |

These are effects of the expense resolver, not a claim that it also performs an actual bank debit. Separate payment and statement services handle company account movements.

Explicit settlement, petty, or company-pending settings override those defaults. Therefore, a new account choice alone would not guarantee the intended balance change unless those existing overrides are reconciled with it.

Evidence: [FundingSourceEffectResolver.php](/Users/niyas/Sites/green-leaf-erp/app/Services/Cashbook/FundingSourceEffectResolver.php:24).

### Settlement Formulas

Configurable settlement definitions and the shared formula calculator support additions and deductions using categories, header groups, tagged-product totals, and other settlements. A settlement can be designated as Company Payable, and another can supply the displayed net balance. Support in the definition does not mean every report supplies all of these inputs.

The dedicated Company Payable calculation sums configured category amounts and subtracts active `shop_paid_company` transactions. Its amount preparation is narrower than the broader settlement summary. It also groups expense amounts by category, without distinguishing a new Paid/Payable state or dynamic selected account.

The operational day summary uses a separate calculation based on collections, adjustments, deductions, and verified receipts. Payment allocation obtains its daily expected payable from that operational summary. The monthly summary has another path: it totals income-side entries flagged as payable, while calculating ordinary expense deductions separately without subtracting them from that expected-payable figure.

| Existing calculation | Main basis |
| --- | --- |
| Configured Company Payable | Saved formula, less qualifying shop-to-company payment transactions |
| Operational daily payable | Collections plus adjustments minus funding-based deductions |
| Monthly daily-row payable | Income-side payable flags and direction; ordinary expense deductions displayed separately |
| Owner Payments | Payable category totals, settlement amounts, and reconciled payment requests |

These paths do not all call the same formula. Changing a settlement definition alone therefore cannot be assumed to update every payable screen. The reviewed code also uses different direction strings in different places, including `minus` and `subtract`; the plan must align their meaning.

Evidence: [ShopSettlementService.php](/Users/niyas/Sites/green-leaf-erp/app/Services/Cashbook/ShopSettlementService.php:304), [CompanyMoneyPositionService.php](/Users/niyas/Sites/green-leaf-erp/app/Services/Cashbook/CompanyMoneyPositionService.php:640), [monthly payable calculation](/Users/niyas/Sites/green-leaf-erp/app/Services/Cashbook/CompanyMoneyPositionService.php:896), and [ShopPaymentLedgerReconciliationService.php](/Users/niyas/Sites/green-leaf-erp/app/Services/Cashbook/ShopPaymentLedgerReconciliationService.php:54).

### Payments and Bank Connections

The admin flow already distinguishes submitting a payment, confirming receipt, and allocating it to shop obligations. Verified receipts can be recorded against an enabled company Cash/Bank account. Pending cheques remain pending. Confirmed receipts can be allocated partially, and the receipt must not be counted again when allocated.

The owner Payments page still contains a separate path using `include_in_payable`, category amount sums, and payment matching based partly on notes or dates. Its top cards use the current calendar month even when the detail list uses another date range. It also reads older accounting-entry records for company payable lines.

This means updating only the daily-entry form would leave the Payments screen incompletely connected to the requested dynamic behavior.

Evidence: [CashbookController.php](/Users/niyas/Sites/green-leaf-erp/app/Http/Controllers/Web/Admin/CashbookController.php:2195), [ShopPaymentLedgerReconciliationService.php](/Users/niyas/Sites/green-leaf-erp/app/Services/Cashbook/ShopPaymentLedgerReconciliationService.php:146), and [ShopOwnerController.php](/Users/niyas/Sites/green-leaf-erp/app/Http/Controllers/Web/ShopOwnerController.php:610).

## 3. Required Flow

### Configuration

The existing shop settings and settlements should define:

1. Which groups and categories appear to the manager.
2. Which configured accounts or buckets each category can use when Paid.
3. Which configured accounts or buckets each category can use when Payable.
4. The balance effect of each permitted combination.
5. Any linked company Cash/Bank account used to verify actual payments.
6. Which formula supplies the shop's amount payable to Company and the displayed net position.

Display names must not determine accounting behavior. Renaming a category or adding a new permitted account should not require changing application code.

Not every settlement formula represents money that can be spent. A summary such as Total Expenses or Net Balance should not automatically become a Paid From option. Configuration needs to identify which choices are valid funding accounts, payable buckets, or calculation-only summaries.

### Manager Entry

1. Open a configured group and select the category, such as Fuel.
2. Enter the amount.
3. Choose **Paid** or **Payable**. This is mandatory for manual expense entries.
4. For Paid, select **Paid From** from that category's permitted configured accounts.
5. For Payable, select **Payable From** from that category's permitted configured buckets.
6. Review the resulting effect, such as "Shop Cash decreases by Rs. 100" or "Company payable bucket increases by Rs. 100."
7. Save the entry and update the relevant balances and payment breakdown.

A configured default can be suggested visibly, but the saved entry must have an explicit valid payment state and account. Missing or invalid selections must be rejected on the server as well as in the form.

Only enabled choices permitted for the current shop and actor should be available. Renaming or disabling a choice must preserve its identity on historical entries. Closed or confirmed transactions need the existing controlled correction process; a backdated correction must update affected later balances consistently.

This binary choice applies to expenses and outgoing payment obligations. Sales receipts and transfers need their own corresponding rules; automatically generated invoice and staff rows should inherit their source record's payment information rather than requiring duplicate manager entry.

### Fuel Examples

Each row below is a separate hypothetical Rs. 100 expense:

| Manager selection | Immediate money effect | Outstanding effect |
| --- | --- | --- |
| Fuel / Paid / Cash | Shop Cash decreases by 100 | No unpaid fuel amount; company remittance changes if the configured formula permits this deduction |
| Fuel / Paid / Petty | Petty decreases by 100 | No unpaid fuel amount; do not also deduct Shop Cash |
| Fuel / Payable / configured Company bucket | No cash or bank deduction yet | 100 remains outstanding in that configured bucket |
| Fuel / Paid / configured Company Bank | A verified linked payment represents the bank debit | No unpaid amount for the covered expense; do not debit the bank again when linking it |
| Fuel / Payable / another enabled bucket | No immediate money movement | Outstanding follows that bucket's configured responsibility |

Cash Purchase can be the visible group in the first example if that is how the shop is configured. Fuel and Cash Purchase must not become two independently counted expenses for the same payment.

## 4. Payable Must Have a Clear Direction

The word Company currently appears in several different meanings:

| Meaning | What it represents |
| --- | --- |
| Payable to Company | Money the shop must send to Company |
| Payable by Company | An unpaid expense Company is responsible for paying |
| Receivable from Company | Money Company owes back to the shop, for example an agreed reimbursement |
| Paid by Company | A payment Company has already made |

The request to make categories dynamic is clear. It does not establish that every Company-related category has the same direction or balance effect.

The existing `company_later` resolver describes reimbursement owed by Company. That is not sufficient evidence to treat all future Payable From Company entries as reimbursements.

**Recommended rule:** store the responsibility and effect in configuration. The manager selects the configured category/account; the system applies its defined direction. A supplier bill payable by Company must not silently become a debt the shop owes Company, or a reimbursement Company owes the shop.

Whether a particular unpaid Company expense should offset the shop's remittance is a category-level business rule to establish during planning. This study does not change that rule.

## 5. How the Payments Page Should Work

The shop Payments page should use the same configured settlement calculation as daily accounting and show:

| Item | Meaning |
| --- | --- |
| Opening payable | Outstanding carried into the selected period |
| Period obligation | Additions and deductions from the configured settlement rules |
| Paid to Company | Actual confirmed receipts attributable to this shop, including eligible linked bank receipts |
| Remaining payable | Obligation still outstanding after confirmed payments |
| Awaiting confirmation | Submitted payments, unmatched bank items, or uncleared cheques |
| Confirmed but unallocated | Received money not yet assigned to a specific day/category |
| Other payable buckets | Unpaid expenses grouped by the dynamically selected responsibility/account |

The page needs a breakdown by date, category, selected account, original amount, paid amount, and remaining amount. A manager should be able to open an unpaid item and record or link its later payment, including the actual source, date, method, reference, and notes where required.

Company bank mapping alone is not proof of payment. A configured destination can identify where a receipt is expected; the existing confirmation evidence determines whether it counts as paid. A confirmed receipt must appear once even if represented by a bank statement, payment record, and allocation.

Confirmation status and Paid/Payable are separate concepts. For example, a manager can report that money was sent while Company receipt is still awaiting confirmation. Show that difference clearly without removing the mandatory Paid/Payable entry choice.

The later accounts-admin integration can reuse these records and references. Shop entry should not require a separate manually entered copy of the expense in that future module.

## 6. Balance Rules and Worked Example

The screen should distinguish money available from money owed:

```text
Closing Cash = Opening Cash + actual cash receipts
               - payments from Cash - cash transfers out

Closing Petty = Opening Petty + petty funding
                - payments from Petty - petty transfers out

Remaining Expense Payable = Original Obligation - confirmed linked payments

Closing Payable to Company = Opening Payable
                            + configured period additions
                            - configured period deductions
                            - confirmed payments applied once
```

These equations describe the proposed product behavior. Individual category effects still come from shop configuration. If a configured formula already subtracts company payments, the result must not subtract those payments a second time. Overpayments should remain visible as credit rather than disappearing when a balance reaches zero.

### Example: Cash Expense and Company Remittance

Assume opening cash and opening payable are zero, cash sales are entered as gross receipts, and the settlement rule permits fuel paid from Shop Cash to reduce the remittance:

| Activity | Shop Cash | Payable to Company |
| --- | ---: | ---: |
| Cash sales received: Rs. 1,000 | 1,000 | 1,000 |
| Fuel paid from Cash: Rs. 100 | 900 | 900 |
| Cash handed to Company and receipt confirmed: Rs. 600 | 300 | 300 |

Record that last event once. Its appearance in Payments, the company cash statement, and a daily allocation must not create additional Rs. 600 deductions.

If the Rs. 100 fuel was Payable instead, cash would remain Rs. 1,000 until an actual cash payment. Its impact on the Company settlement would follow the selected payable bucket's rule.

The current owner form describes Cash as "Remaining cash in shop." Before implementation, confirm whether managers currently enter gross cash receipts or already-net cash. Subtracting expenses from an already-net figure would count those expenses twice.

Evidence for the current label: [header-entry.blade.php](/Users/niyas/Sites/green-leaf-erp/resources/views/shop-owner/cashbook/partials/modals/header-entry.blade.php:55).

## 7. Later Payment and Mixed Entries

For an expense of Rs. 100 entered as Payable:

| Event | Expense total | Paid amount | Remaining payable | Display status |
| --- | ---: | ---: | ---: | --- |
| Create payable | 100 | 0 | 100 | Payable |
| Allocate confirmed payment of 40 | 100 | 40 | 60 | Partially Paid |
| Allocate confirmed remaining payment of 60 | 100 | 100 | 0 | Paid with a tick mark |

The payable status must update immediately after an allocation. Every confirmed allocation increases the paid amount and subtracts the same amount from the outstanding payable. When the outstanding amount reaches zero, show **Paid** with a clear tick mark. The later payment clears the obligation; it does not create another expense or reduce the original Rs. 100 expense total.

```text
Paid Amount = Sum of active confirmed allocations
Outstanding Payable = Original Payable Amount - Paid Amount

Outstanding > 0 and Paid Amount = 0  -> Payable
Outstanding > 0 and Paid Amount > 0  -> Partially Paid
Outstanding = 0                       -> Paid ✓
```

If an allocation is reversed, subtract it from the paid amount, restore it to the outstanding payable, and return the status to Partially Paid or Payable as appropriate. Pending or unconfirmed payments must not change the paid amount or outstanding payable.

One category must support multiple entries on the same day, such as Fuel Paid from Cash, Fuel Paid from Petty, and Fuel Payable from Company. The current bulk save finds an existing manual transaction by date and category and updates that record. A form toggle alone cannot preserve these separate states and sources.

If a payment uses a different permitted source from the original payable selection, preserve both the original responsibility and the actual paid-from source. Reversals must restore the appropriate unpaid amount while keeping the history.

Evidence: [ShopOwnerController.php](/Users/niyas/Sites/green-leaf-erp/app/Http/Controllers/Web/ShopOwnerController.php:1732). Existing company-expense coverage and reversal behavior offers a starting point: [CompanyExpenseAllocationService.php](/Users/niyas/Sites/green-leaf-erp/app/Services/Cashbook/CompanyExpenseAllocationService.php:115).

## 8. Gaps to Carry into the Implementation Plan

| Gap | Required outcome |
| --- | --- |
| Funding choices use a fixed enum | Account/bucket choices come from shop configuration |
| No explicit expense payment state | Each manual expense stores Paid or Payable separately from approval status |
| Funding selection inherits category defaults | Each entry records a valid selected source or payable bucket |
| One daily amount per category in bulk entry | Separate entries or splits preserve different payment states and sources |
| Formulas aggregate full category amounts | Formulas include the appropriate Paid/Payable and account effects |
| Several payable calculation paths | Owner, admin, report, and payment views agree for the same scope and date |
| Some payment attribution uses notes or dates | Link payments explicitly to their obligations and configured buckets |
| Company expense coverage uses fixed company-pending semantics | Dynamic payable buckets support later full or partial payment |
| Bank mapping and confirmation are distinct | Paid totals use confirmed money once; pending items remain visible |
| Existing history lacks the new distinction | Establish an explicit history-mapping rule and flag ambiguous entries |

Adding a category named Company is not enough to assign a financial effect. The configuration must express what it means and how it participates in each relevant balance.

## 9. Decisions for the Next Planning Step

The implementation plan should settle these points using the existing shop configuration:

1. The allowed Paid From and Payable From choices for each category, including whether a group fixes a choice or permits an override.
2. The direction and settlement effect of each Company-related payable category.
3. Whether Cash entry means gross receipts or remaining cash after expenses.
4. How to display multiple entries and partial payments while keeping the manager form simple.
5. Which existing confirmation path supplies Paid totals until the later accounts-admin connection.
6. How existing entries will be interpreted when there is no reliable evidence of their payment state.

The planning boundary should remain shop accounting, its settings, and Payments. Warehouse purchasing rules, staff payment ownership, and the broader accounts-admin redesign are separate work unless explicitly included later.

## 10. Validation and Study Limits

The study traced current source files, inspected the relevant database schema through Laravel Boost, used the existing Graphify repository index for localization, and ran existing tests against the isolated test configuration.

| Focused checks | Tests | Assertions | Result |
| --- | ---: | ---: | --- |
| Dynamic Company Payable, settlement settings, and category-to-bank integration | 34 | 192 | Passed |
| Partial allocation, direct bank receipt deduplication, payment double-deduction protection, and payable category direction | 5 | 40 | Passed |
| Owner bulk entry and current calculation parity | 2 | 6 | Passed |
| Total | 41 | 238 | Passed |

These checks validate the selected existing behavior. They do not prove the new dynamic Paid/Payable flow, which has not been implemented, and they are not a full-suite or live-browser validation.

No live transaction amounts or customer data were used. Existing uncommitted application changes were preserved. No application code, dependencies, environment settings, or business rules were changed.

Files changed: this report only. Rules added: none to the application; proposed rules are documented above. Skills used: repository exploration/Graphify to locate the flow, and Laravel Best Practices to review the relevant models, validation, and service boundaries. Remaining risk: ambiguous historical payment states, several existing balance calculations, and category-specific meanings must be resolved before implementation.
