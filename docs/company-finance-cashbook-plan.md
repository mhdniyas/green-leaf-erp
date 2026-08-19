# Company Finance Cashbook Plan

## Direction
Extend the existing Admin Cashbook and shop payment approval system. Do not replace the current shop cashbook, payable configuration, invoice payment request table, or shop reports.

## Source Of Truth
Shop payable-to-company balances must come from existing shop cashbook configuration:

- `shop_ledger_entry_settings.include_in_payable`
- `shop_ledger_entry_settings.payable_direction`
- `shop_ledger_entry_settings.company_pending_behavior`
- `shop_ledger_entry_settings.settlement_behavior`
- linked shop invoice and payment records

## Target Flow
1. Shop owner or staff submits a payment.
2. Shop owner page shows the payment as pending or floating.
3. Admin opens Company Finance reconciliation.
4. Admin matches the payment with a bank, wallet, cheque, or liquid cash statement entry.
5. Admin can partially reconcile the payment.
6. Only reconciled and approved amount reduces the shop payable-to-company balance.
7. Any overpayment becomes advance balance for that same shop.
8. Admin can track which account received the money, which statement entry matched it, and who reconciled it.
9. Bank/cash account statement shows reconciliation status and linked payment.
10. Shop owner reports show pending, reconciled, cleared, floating, and advance details.

## Partial Reconciliation
Partial reconciliation is allowed.

Example:

- Shop payment submitted: Rs. 10,000
- Bank statement matched now: Rs. 6,000
- Cleared/reconciled now: Rs. 6,000
- Floating balance: Rs. 4,000

The remaining amount stays floating until another reconciliation or admin adjustment.

## Difference Handling
Admin can handle differences during reconciliation:

- Keep as floating
- Adjust as shop expense
- Adjust as shop income

Every difference must keep a trace:

- original shop payment
- reconciled amount
- difference amount
- adjustment type
- reason/note
- admin user
- date/time
- linked bank/cash statement transaction
- linked shop ledger transaction when an adjustment is posted

## Default Reconciliation Categories
Create default ledger entry types:

- `reconciliation_adjustment`
- `bank_charges`
- `short_receipt`
- `excess_receipt`

These make reconciliation usable immediately. Later, admin can choose a different configured cashbook category.

## First Implementation Phase
1. Add company account statement entries.
2. Add payment reconciliation records.
3. Add reconciliation tracking fields to shop payment requests.
4. Add model relations for statement entries and reconciliations.
5. Add an admin-only reconciliation page in Admin Cashbook.
6. Add controller actions to create statement entries and approve partial reconciliations.
7. Add seed data for default reconciliation categories.
8. Add targeted feature tests for partial reconciliation and advance balance tracking.

## Later Phases
1. Connect final payable reduction directly to payable-config clearing rules.
2. Add selectable bill and expense allocation per shop.
3. Add PDF/manual bank statement import.
4. Add full Company Finance Dashboard cards.
5. Add account ledger statement drill-down.
6. Add selected-user finance access controls.

## Bug Fix Plan: Shop Owner Payment Without Positive Closing Balance
1. Keep the existing owned-shop payment flow.
2. Stop blocking a shop payment only because raw closing balance is not positive.
3. If shop owner enters a positive amount, create the payment request as floating for admin reconciliation.
4. Keep blocking only when both calculated payable balance and entered payment amount are not positive.
5. Cover the behavior with a focused feature test.

## UI Update Plan: Responsive Admin Cashbook Finance
1. Move the Finance section near the top of the Admin Cashbook sidebar so company finance, reconciliation, and bank accounts are immediately visible.
2. Improve the Company Finance page layout for mobile and desktop: summary cards, pending payment reconciliation forms, statement-entry form, and reconciliation table must stack cleanly and avoid overflow.
3. Keep bank account creation and account management on the current bank accounts screen, but make its header/actions and accounts matrix responsive.
4. Add a bank account details page showing the account identity, balances, recent statement activity, and recent reconciliations.
5. Add a separate bank account statement page that lists statement entries for only that account, with matched/floating status and reconciliation links.
6. Link every bank account row to Details and Statement pages so admin can trace where money went from the account level.

## Shop Payment Finish Plan: Bank Submit, Reconcile, Journal
1. Keep this phase focused on shop payments only; other company payments will connect after this flow is stable.
2. Add a daily cheque-to-bank list so admin can see how many cheque payments must be submitted to bank for a selected date.
3. Add a print-friendly bank submission form with company account details and cheque/payment rows.
4. Let admin reconcile a bank transfer or statement entry against shop payment transactions and keep the trace visible from both payment and bank statement sides.
5. Add a finance journal page that lists all shop-payment finance transactions with account, shop, amount, status, and date.
6. Add a journal detail page so clicking any row shows payment request, statement entry, reconciliation, account, and admin notes.
7. Show clear totals for current company balance, floating balance, pending payment amount, and open statement amount.

## Accountability Fix Plan: Every Reconciliation Must Hit Statement
1. When admin approves reconciliation with an existing bank/cash statement row, link and match that row as before.
2. When admin approves reconciliation without selecting a statement row, automatically create an incoming statement row for the selected account.
3. Mark the linked or created statement row as reconciled or partially matched in the same approval transaction.
4. Keep account balance movement tied to the statement amount so the account statement, reconciliation record, and account balance all show the same money flow.
5. If bank amount and cleared amount differ, force the difference to be recorded with an action so no rupee disappears from the trace.

## Cash In Hand Plan
1. Treat Cash in Hand as a company account type, same as a bank account.
2. Cash in Hand must have its own account details page and statement page.
3. Manual cash-in-hand statement rows must update the cash account balance immediately.
4. Reconciliation must link to an existing cash statement row or auto-create one for the selected cash account.
5. Account balance must come from statement movement, not hidden reconciliation-only balance changes.

## Loader Fix Plan
1. Keep the cashbook loader hidden after the page is ready.
2. Show the loader only for real page navigation and form submission.
3. Hide the loader again on `DOMContentLoaded`, `load`, `pageshow`, cancelled navigation, and print return.
4. Avoid showing the loader for same-page links, new-tab links, downloads, anchors, JavaScript links, or button-only interactions.

## Accept Payment Redesign QA Plan
1. Replace the current mixed settlement screen with a simple shop-card index.
2. Each shop card must show current-month total received, floating amount, pending amount, payable balance, approved amount, and after-approved balance.
3. Clicking a shop opens a redesigned shop payment detail page.
4. The shop detail page must focus first on recording cash, cheque, bank transfer, UPI, or other payment details received from that shop.
5. Monthly data is the default and only period shown on this page; remove period switching from this flow.
6. Cash received by admin is approved directly and added to the selected Cash in Hand statement at the same time.
7. Cheque, bank transfer, UPI, and other non-cash payments must move to finance reconciliation as floating money until cleared.
8. Shop owner payable balance must reduce only after admin reconciliation approval, or immediately for direct cash approval.
9. After approval, the approved shop balance can be applied against shop relations: bills, expenses, company payable rows, and configured payable categories.
10. Admin must be able to manually select which bills or expense relations are cleared by the approved balance.
11. Bill and expense rows must show oldest first, with search, manual date filters, select-all, and links back to existing detailed cashbook split pages where available.
12. Show grouped totals for same entry/category rows so admin can understand large volumes quickly before opening the detailed split.
13. Every selected clearing action must keep a trace to the original payment, reconciliation, shop, bill or expense row, account, admin, and date.
14. Cheque payments must remain floating until bank submission and bank reconciliation confirms clearance.
15. Existing payable configuration remains the source for what should be payable to the company.
16. Any overpayment after payable clearing becomes advance balance for that shop.
17. The first implementation should finish shop payments only; other payment types connect after this is stable.

## Shop Owner Daily Payable Selection Fix Plan
1. Keep the existing shop-owner daily payable balance list.
2. Make selected daily rows recalculate the payable payment amount immediately.
3. Show selected count and selected total directly below the daily payable table, not only in the top banner.
4. Add visible column totals for collected, received, and net balance below the table.
5. Keep select-all working for the currently visible page of daily rows.

## Shop Owner Daily Payable Total Rebuild Plan
1. Remove the previous selected-total getter calculations from the daily payable Alpine block.
2. Store selected count, selected collected, selected received, selected balance, and visible totals as plain numeric fields.
3. Recalculate all totals through one function after every checkbox or select-all change.
4. Keep the selected balance synced into the payment amount input.
5. Show the below-table totals even before selection, with zero selected values until rows are picked.

## Statement First Reconciliation Plan
1. Make reconciliation start from company account statement rows, because bank/cash statement is the final money proof.
2. Admin selects account and month, then sees only unmatched or partially matched incoming statement rows.
3. Admin opens one statement row at a time to avoid clutter.
4. For the selected statement row, show possible shop payment matches by amount, date range, reference, and payment method.
5. Default matching window is statement date plus or minus 10 days, but admin can change it.
6. Admin can approve one payment against the selected statement row, including partial matching.
7. If statement amount and cleared amount differ, admin must choose how to account for the difference.
8. After approval, update payment, statement, account balance, and journal trace through the existing reconciliation service.
9. Accept Payment should only record received payment details; non-cash payments should move to the reconciliation queue.
10. Cash payments remain direct approval into Cash in Hand statement.
11. Journal and reconciliation links should avoid exposing raw numeric IDs where the model has a stable secure key available.

## Bank Statement Import Plan
1. Add PDF import to each bank account statement page, with selected month and optional PDF password for locked statements.
2. Treat the uploaded PDF only as bank statement data; ignore any text that is not statement rows.
3. Import monthly rows only, based on the admin-selected statement month.
4. Extract statement rows into account statement entries and update account balance only for new confirmed rows.
5. Detect exact duplicates using account, date, direction, amount, reference, and narration fingerprint, including duplicates against already-imported rows.
6. Detect possible duplicates against manual entries by matching account, date, direction, and amount, then show them as flagged rows without applying balance until admin clears the flag.
7. Let admin manually clear a duplicate flag from the statement page; clearing turns the row into a normal open statement entry and applies its balance movement once.
8. Keep statement lists monthly by default, with duplicate flags visible so admin can clean imported data before reconciliation.

## PDF Import Extractor Fix Plan
1. Keep Poppler pdftotext support when a server has it installed or configured.
2. Add Python pypdf extraction fallback for password-protected PDFs when pdftotext is missing.
3. Auto-detect common Python paths, including the local Codex bundled runtime path.
4. Show a clearer import error only if both extraction methods fail.

## Production Seeder Safety Plan
1. Audit the default `DatabaseSeeder` path and list every cashbook seeder it runs.
2. Keep only safe catalogue/permission seeders in the default production seed flow.
3. Remove bank/company account seed data from default seeding because real balances and real bank accounts must be entered/imported by admin.
4. Keep optional setup/demo seeders as direct manual seeders so they can be run intentionally in local or staging only.
5. Update seeder comments so the production-safe default is clear to the next developer.

## Purchaser Bulk Qty Double Fix Plan
1. Keep the purchaser bill and bulk purchase flow unchanged for normal use.
2. Add a one-time submission key to the bulk purchase details form so browser resubmits do not add the same quantities again.
3. Disable the bulk add button immediately after a valid submit to stop mobile double taps.
4. Filter approved demand by purchase grade when building the purchaser daily summary.
5. Leave purchase document creation unchanged because it already uses the saved cart quantities directly.

## Sort Sheet Excel Code Visibility Plan
1. Keep product code visible by default in admin sort sheet and Excel export.
2. Add a Show Code / Hide Code radio option to the sort sheet filter form.
3. Preserve the selected code visibility when generating, exporting Excel/PDF, and using print links.
4. Update the Excel export so hiding code removes the Code column and shifts the item/shop columns cleanly.
5. Leave code sorting unchanged unless admin separately changes the existing Code Sort option.
