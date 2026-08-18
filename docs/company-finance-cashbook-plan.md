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
