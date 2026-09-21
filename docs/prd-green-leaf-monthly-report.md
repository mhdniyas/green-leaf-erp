# PRD: Green Leaf Monthly Report

## 1. Document Control

- Product: Green Leaf ERP
- Feature: Green Leaf Monthly Report
- Area: Admin Cashbook
- Status: Implementation-ready specification
- Intended users: Main Admin, Admin, and Finance/Accounts users
- Default currency: INR
- Business timezone: `Asia/Kolkata`

## 2. Feature Summary

Build a consolidated, read-only financial reporting workspace under Cashbook. It must combine client/owned-shop figures, direct-shop sales, direct company sales, warehouse sales, product-category purchase costs, shop expenses, purchaser expenses, company expenses, and purchaser cash positions into one reconciled reporting product.

The existing Cashbook Payments Report is the bridge for all client/owned-shop data. Every shop-related amount must first be assigned to a standard final-report heading in Cashbook Payment Settings. The consolidated report must consume that normalized output instead of interpreting shop ledger categories independently.

All sources outside the shop cashbook bridge must be fetched from their authoritative records:

- Direct-shop GL bills from shop invoices.
- Direct company sales from direct company sale bills.
- Warehouse sales from confirmed warehouse sales.
- Product purchase costs from purchase invoices and items.
- Product grouping from Purchase Product Filters.
- Purchaser funding and utilization from the purchaser finance ledger.
- Purchaser expenses from procurement and other expense records.
- Residual company expenses from finalized company accounting entries.

The feature contains one settings area and four report pages:

```text
Cashbook Settings
└── Final Report Settings

Cashbook
├── Overview
├── Green Leaf Monthly Report
├── Monthly Sale Split
├── Other Expense
└── Expense Report
```

## 3. Locked Business Requirements

1. Client/owned shops must be shown separately, with client and shop detail available from every applicable total.
2. Cashbook Payments Report is the bridge for all client/owned-shop sales, purchases, and operating expenses.
3. Direct-shop sales come from GL bills for shops that are not client/owned shops.
4. Direct company sale bills are an additional direct-sales source.
5. Confirmed warehouse sales are included in All Other Sales.
6. Total Sales is split into Client Sales and All Other Sales.
7. Product report groups are Fruits, Vegetables, and Stationery.
8. Product membership is controlled through the existing Purchase Product Filters area.
9. Purchases are recognized as expenses on the purchase business date, including unpaid credit purchases.
10. Operating expense headings are Salary, Rent, Vehicle/Fuel, Food/Mess, and Others.
11. Purchaser reporting must show purchases, purchaser expenses, cash given, cash returned, and whether the purchaser has a positive or negative cash position.
12. Admin and Finance/Accounts users can view reports.
13. Main Admin and Admin can manage Final Report Settings. Finance/Accounts users have read-only access to settings readiness.
14. All visible totals, drilldowns, and exports must reconcile for the selected date range.

## 4. Problem Statement

Financial data currently exists across shop cashbooks, GL bills, direct sales, warehouse sales, purchaser purchases, purchaser cash movements, procurement expenses, and company accounting. Users cannot view one daily and monthly report that answers:

- How much did client/owned shops sell?
- How much came from direct shops, direct company sales, and warehouse sales?
- How much revenue and cost belong to Fruits, Vegetables, and Stationery?
- What operating expenses occurred by date and category?
- How much money was given to each purchaser, how much was spent, and is the purchaser holding cash or in deficit?
- Do all category totals reconcile to the company overview?

The report must solve this without creating a second ledger or changing the accounting meaning of existing records.

## 5. Goals

- Provide one trusted monthly overview of Sales, Expenses, and Balance.
- Show a daily split between Client Sales and All Other Sales.
- Show Fruits, Vegetables, and Stationery sales and purchase expenses side by side.
- Show operating expenses by normalized heading and by original category.
- Show client, shop, source document, product, purchaser, and vendor drilldowns.
- Show purchaser cash accountability for the selected period.
- Reuse Cashbook Payment Settings for shop-category normalization.
- Reuse Purchase Product Filters for product normalization.
- Prevent double-counting between source records and their accounting mirror entries.
- Export the same figures shown on screen to Excel, CSV, and PDF/print.

## 6. Non-Goals

- The reports do not edit, approve, reverse, settle, or delete financial transactions.
- The reports do not replace the shop Sales Report, purchase reports, GL Bills report, or warehouse sales screens.
- The reports do not calculate inventory valuation, gross margin by stock batch, or FIFO cost of goods sold.
- The reports do not treat cash transfers, settlements, advances, reimbursements, or account movements as revenue or expense.
- The reports do not change vendor balances or purchaser ledger postings.
- The reports do not create products, filters, shops, clients, purchasers, or expense categories.
- The reports do not introduce a new currency or multi-currency conversion.

## 7. Users, Permissions, and Authorization

### 7.1 Permissions

Add explicit permissions:

```text
cashbook.monthly-report.view
cashbook.monthly-report.export
cashbook.monthly-report.settings.view
cashbook.monthly-report.settings.manage
```

Assignment:

| User group | View reports | Export | View settings | Manage settings |
|---|---:|---:|---:|---:|
| Main Admin | Yes | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes | Yes |
| Accounts/Accountant/Account/Finance permission holder | Yes | Yes | Yes | No |
| Purchaser | No | No | No | No |
| Shop owner | No | No | No | No |
| Warehouse user | No | No | No | No |

Existing compatible permissions such as `accounting.report.view` and `finance.dashboard.view` may grant report viewing during migration, but settings mutation must require the new manage permission or Main Admin/Admin status.

### 7.2 Scope Rules

- Every source query must enforce the same authorized company scope as the existing Admin Cashbook reports.
- Shop drilldowns must resolve records through the authorized shop set.
- Warehouse drilldowns must resolve records through authorized company warehouses.
- Record IDs supplied through drilldown requests must be re-scoped server-side; never trust a client-provided shop, client, warehouse, purchaser, invoice, or transaction ID.
- Unauthorized users receive HTTP `403`; inaccessible scoped records return HTTP `404`.

## 8. Information Architecture

### 8.1 Cashbook Settings

Add **Final Report Settings** under Cashbook Settings.

The page contains:

1. Report readiness summary.
2. Product group assignments.
3. Shop heading readiness by client and shop.
4. Purchaser-expense category mapping.
5. Company-expense category mapping.
6. Unmapped and conflicting records.
7. Calculation preview for a selected day.

### 8.2 Cashbook Reports Sidebar

Under the existing Cashbook Overview section, add:

1. Green Leaf Monthly Report
2. Monthly Sale Split
3. Other Expense
4. Expense Report

The active state must cover the page, its drilldowns, and its export view.

## 9. Shared Reporting Period

All four pages use the same filter contract as the existing Shop Sales Report.

### 9.1 Modes

- Month: default; first through last calendar day of the selected month.
- Day: one business date.
- Custom: inclusive start and end dates.
- Previous and next period navigation.
- Default on first load: current month in `Asia/Kolkata`.

### 9.2 Query Parameters

```text
period_mode=month|day|custom
month=YYYY-MM
date=YYYY-MM-DD
from=YYYY-MM-DD
to=YYYY-MM-DD
```

The server must normalize these into:

```text
start_date
end_date
formatted_range
period_label
previous_period
next_period
```

### 9.3 Validation

- `to` must be on or after `from`.
- Custom ranges are limited to 366 inclusive days.
- Invalid dates return validation errors and preserve valid filter values.
- Every source uses its business date, not `created_at`.
- Start and end dates are inclusive.
- Dates with no activity remain visible as zero-value rows in daily summary tables.

## 10. Final Report Settings

### 10.1 Standard Report Heading Dictionary

Use stable machine codes and editable display labels.

| Group | Code | Default label |
|---|---|---|
| Sales | `fruits_sale` | Fruits Sale |
| Sales | `vegetables_sale` | Vegetables Sale |
| Sales | `stationery_sale` | Stationery Sale |
| Sales | `other_sale` | Other Sale |
| Product expense | `fruits_expense` | Fruits Expense |
| Product expense | `vegetables_expense` | Vegetables Expense |
| Product expense | `stationery_expense` | Stationery Expense |
| Product expense | `other_product_expense` | Other Product Expense |
| Operating expense | `salary` | Salary |
| Operating expense | `rent` | Rent |
| Operating expense | `vehicle_fuel` | Vehicle/Fuel |
| Operating expense | `food_mess` | Food/Mess |
| Operating expense | `other_expense` | Others |
| Exclusion | `ignore` | Exclude from Final Report |

Codes are fixed after release. Labels may be changed without changing calculations.

### 10.2 Shop Category Mapping

In Cashbook Payment Settings, every enabled `ShopLedgerEntrySetting` must have a **Final Report Heading** selector.

Requirements:

- One shop category maps to exactly one heading.
- Transfer and settlement categories default to `ignore`.
- Existing shop Sales Report behavior remains unchanged; use a dedicated monthly-report mapping field rather than changing `sales_report_bucket` semantics.
- Disabled category settings do not contribute.
- Void, voided, and reversed transactions do not contribute.
- The mapping must be visible on the consolidated Final Report Settings page.
- A bulk-copy action may copy mappings from one shop to another, but the user must review and save the target shop explicitly.
- A readiness badge shows Ready, Warning, or Blocked per shop.

Required storage change:

```text
shop_ledger_entry_settings.monthly_report_bucket nullable string(40), indexed
```

Allowed values are the heading codes above. Null means unmapped and must be surfaced.

### 10.3 Product Group Mapping

The existing Purchase Product Filters page remains the source for product grouping.

Add a **Monthly Report Group** field to a saved product filter:

```text
fruits
vegetables
stationery
none
```

Required storage change:

```text
purchase_product_filters.monthly_report_group nullable string(30), unique for non-null active filters
```

Rules:

- At most one active filter can represent Fruits, one Vegetables, and one Stationery.
- A product may belong to only one active monthly-report filter.
- Settings cannot be marked Ready while active filter memberships overlap.
- Products found in report-period sales or purchases without a mapped group are shown as Unmapped.
- Unmapped amounts remain in Total Sales or Total Expenses and appear in reconciliation warnings; they are never silently discarded.
- Deleting or deactivating a mapped filter first requires assigning or clearing its monthly-report group.

### 10.4 Operating Expense Mapping

Default mappings:

| Source category | Final heading |
|---|---|
| Procurement Expense: Vehicle | Vehicle/Fuel |
| Procurement Expense: Fuel | Vehicle/Fuel |
| Procurement Expense: Toll/Parking | Vehicle/Fuel |
| Procurement Expense: Food | Food/Mess |
| Procurement Expense: Labour | Others |
| Procurement Expense: Other | Others |
| Other Expense: all existing categories | Others |

Company accounting expense categories must be assignable to Salary, Rent, Vehicle/Fuel, Food/Mess, Others, or Ignore.

Required mapping table:

```text
cashbook_monthly_report_expense_mappings
id
source_type string(80)
source_key string(120)
report_bucket string(40)
created_by foreign id users
updated_by foreign id users nullable
timestamps
unique(source_type, source_key)
index(report_bucket)
```

Examples of `source_type` are `procurement_expense_category`, `other_expense_category`, and `company_accounting_category`.

### 10.5 Readiness Rules

Settings status is **Blocked** when:

- Fruits, Vegetables, or Stationery has no assigned active product filter.
- One product appears in more than one assigned product filter.
- A client/owned shop has an active report category with no monthly heading.
- A required source category has an invalid heading.

Settings status is **Warning** when:

- Report-period products are unmapped.
- Report-period company expense categories are unmapped.
- A client/owned shop has no activity or no sales heading.
- Legacy direct company sales have no item detail.

Reports remain accessible in Warning state and display a visible reconciliation warning. Blocked settings prevent activation/save of the invalid mapping, but do not make historical reports unavailable.

### 10.6 Audit

Every settings mutation must record:

- Actor
- Timestamp
- Shop/filter/category affected
- Previous value
- New value

Settings changes must use existing application activity logging conventions.

## 11. Authoritative Data Source Contract

### 11.1 Source Precedence

The report uses source records as business truth. Journal entries and mirrored company accounting entries provide audit links but are not added again when their source record is already counted.

| Metric | Authoritative source | Included state | Date | Amount |
|---|---|---|---|---|
| Client/owned-shop sales and expenses | Cashbook Payments Report normalized from `ShopLedgerTransaction` | Active; not void/voided/reversed | `business_date` | Mapped transaction amount |
| Direct-shop GL bill sales | `ShopInvoice` and items for direct shops | Status not cancelled | `business_date` | Invoice final total; allocated to items |
| Direct company sales | `DirectCompanySale` and items | `sale_status=confirmed` | `business_date` | `amount`; item `line_total` for split |
| Warehouse sales | `WarehouseSale` and items | `status=confirmed` | `business_date` | `total_amount`; item `line_total` for split |
| Product purchases | `PurchaseInvoice`, purchaser cart, and cart items | Not deleted; status not cancelled | Cart/invoice business date | Net item allocation |
| Purchaser funding | `PurchaserCredit` | Posted/non-reversed movements | `business_date` | In/out movement amount |
| Procurement expenses | `ProcurementExpense` | Existing record | `expense_date` | `amount` |
| Other purchaser expenses | `OtherExpense` | Existing record | `expense_date` | `amount` |
| Residual company expenses | `CompanyAccountingEntry` | `type=expense`, `status=final` | `business_date` | `amount` |

### 11.2 Client/Owned-Shop Classification

- A client/owned shop is a shop with a non-null client relationship.
- Client/owned-shop report amounts come only from the Cashbook Payments Report bridge.
- GL bills issued to client/owned shops are not added to Total Sales because that would double-count internal/owned-shop activity.
- Client drilldown groups by client, then shop, then report heading, then transaction.

### 11.3 All Other Sales Classification

```text
All Other Sales =
Direct-shop GL Bill Sales
+ Direct Company Sales
+ Warehouse Sales
```

- Direct-shop GL bills are shop invoices where the shop has no client relationship.
- Direct company sales use confirmed `DirectCompanySale` records.
- Warehouse sales use confirmed `WarehouseSale` records.
- A document represented in one source must never be synthesized again from its journal or statement entry.

### 11.4 Expense Precedence and De-duplication

Count in this order:

1. Product purchases from purchase invoice items.
2. Standalone client/owned-shop product expenses from Cashbook Payments headings.
3. Client/owned-shop operating expenses from Cashbook Payments headings.
4. Procurement and other purchaser expenses from their source tables.
5. Residual finalized company accounting expenses that are not mirrors of sources already counted.

De-duplication rules:

- A Shop Ledger Transaction linked through `reference_type/reference_id` to a purchase invoice already counted by the purchase source is excluded from a second count.
- A `CompanyAccountingEntry` linked to `ProcurementExpense` rows is excluded from residual company expenses.
- A `CompanyAccountingEntry` linked to `OtherExpense` rows is excluded from residual company expenses.
- Journal transactions and company statement movements are never independently added to revenue or expense totals.
- Reversals negate or exclude their original according to the existing source status; do not count both sides as new activity.

## 12. Report 1: Green Leaf Monthly Report

### 12.1 Page Purpose

Provide the company-level overview and daily financial position for the selected period.

### 12.2 Overview Cards

Display:

| Total Sales | Expenses | Balance |
|---:|---:|---:|

Each card is interactive and opens a calculation breakdown.

### 12.3 Overview Formulas

```text
Client Sales =
Sum of client/owned-shop headings:
fruits_sale + vegetables_sale + stationery_sale + other_sale

All Other Sales =
Direct-shop GL Bill Sales
+ Direct Company Sales
+ Warehouse Sales

Total Sales = Client Sales + All Other Sales

Product Expenses =
Fruits Expense
+ Vegetables Expense
+ Stationery Expense
+ Other Product Expense

Operating Expenses =
Salary + Rent + Vehicle/Fuel + Food/Mess + Others

Expenses = Product Expenses + Operating Expenses

Balance = Total Sales - Expenses
```

### 12.4 Daily Table

| Date | Client Sales | All Other Sales | Expenses | Balance |
|---|---:|---:|---:|---:|

Requirements:

- One row per calendar date in the inclusive range.
- Client Sales opens client → shop → heading → transaction detail.
- All Other Sales opens Direct Shop GL Bills, Direct Company Sales, and Warehouse Sales sections.
- Expenses opens Product Expenses and Operating Expenses sections.
- Balance uses the same row values displayed in the table.
- The last row is a sticky/visible period total.
- Negative balance is shown with a minus sign and a non-color status label.

### 12.5 Client Detail

| Client | Shop | Sales | Product Expense | Operating Expense | Balance |
|---|---|---:|---:|---:|---:|

The client total equals the sum of its shops. A client with multiple shops must not appear as one unexplained aggregate.

## 13. Report 2: Monthly Sale Split

### 13.1 Page Purpose

Compare sales and product purchase expenses for Fruits, Vegetables, and Stationery by day, while showing total operating expenses.

### 13.2 Main Table

| Date | Fruits Sale | Fruits Expense | Veg Sale | Veg Expense | Stationery Sale | Stationery Expense | Other Expenses | Total Sales | Total Expenses | Balance |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|

Desktop uses grouped headers:

```text
Fruits           Vegetables       Stationery       Other
Sale | Expense   Sale | Expense   Sale | Expense   Expenses
```

Mobile uses one expandable card per date with the same values.

### 13.3 Category Sales Formulas

For category `C`:

```text
Category Sales(C) =
Client/owned-shop Cashbook Payment headings mapped to C Sale
+ Direct-shop GL Bill item sales whose product is in C filter
+ Direct Company Sale item sales whose product is in C filter
+ Warehouse Sale item sales whose product is in C filter
```

### 13.4 Category Expense Formulas

For category `C`:

```text
Category Expense(C) =
Purchase Invoice item net cost whose product is in C filter
+ standalone client/owned-shop Cashbook Payment headings mapped to C Expense
```

Credit purchases are included at full net purchase value on the purchase business date, even when unpaid.

### 13.5 Other Expenses Formula

```text
Other Expenses =
Salary + Rent + Vehicle/Fuel + Food/Mess + Others
```

`Other Product Expense` is included in Total Expenses and shown in the detail/reconciliation area. It must not be silently merged into an operating category.

### 13.6 Reconciliation

```text
Displayed Category Sales
+ Unmapped/Other Product Sales
= Overview Total Sales

Displayed Category Expenses
+ Other Product Expense
+ Operating Expenses
= Overview Expenses

Row Balance = Row Total Sales - Row Total Expenses
```

If the category display does not reconcile because of unmapped products or shop headings, show:

- Unmapped sales amount
- Unmapped expense amount
- A link to Final Report Settings
- A detail list of affected products/categories

### 13.7 Purchaser Position Section

Below the sale split table, show purchaser accountability for the same period.

| Purchaser | Opening Position | Cash Given | Cash Purchases | Purchaser Expenses | Cash Returned | Closing Position | Credit Purchases | Total Purchases |
|---|---:|---:|---:|---:|---:|---:|---:|---:|

Definitions:

```text
Opening Position =
All purchaser cash in before start date
- all purchaser cash out before start date
- purchaser expenses before start date that consumed purchaser cash

Purchaser Expenses =
ProcurementExpense amount
+ OtherExpense amount
for that purchaser and period

Closing Position =
Opening Position
+ Cash Given
- Cash Purchases paid from purchaser advance
- Purchaser Expenses paid from purchaser advance
- Cash Returned

Total Purchases = Cash Purchases + Credit Purchases
```

Rules:

- Positive closing position means cash remains with the purchaser.
- Zero means fully accounted.
- Negative means the purchaser spent above the available position.
- Credit purchases appear for operational context but do not reduce purchaser cash until paid from purchaser funds.
- Cash given is a balance-sheet movement and is not added to Expenses.
- The purchaser row opens funding, purchase, expense, return, and running-balance details.

Current purchaser expense records do not carry a complete funding-source distinction. Phase 1 report behavior is therefore:

- Purchaser-entered `ProcurementExpense` and `OtherExpense` reduce purchaser position.
- Their linked `CompanyAccountingEntry` is accounting evidence and is not counted again.
- A future direct-company-paid expense must carry a funding source before it can be excluded from purchaser position.

Required storage extension for new expense records:

```text
procurement_expenses.funding_source string(30) default purchaser_advance
other_expenses.funding_source string(30) default purchaser_advance
```

Allowed values:

```text
purchaser_advance
company_cash
company_bank
```

Only `purchaser_advance` reduces purchaser position. Existing rows are backfilled as `purchaser_advance` to match the locked business requirement, with the backfill count reported during deployment.

## 14. Report 3: Other Expense

### 14.1 Page Purpose

Show original category-wise operating expense details for the selected period.

### 14.2 Summary

| Original Category | Normalized Heading | Transactions | Amount | Percentage of Operating Expenses |
|---|---|---:|---:|---:|

### 14.3 Detailed Table

| Date | Category | Final Heading | Source | Client/Shop/Purchaser | Description | Reference | Amount |
|---|---|---|---|---|---|---|---:|

### 14.4 Filters

- Shared period filter
- Final heading
- Original category
- Source type
- Client
- Shop
- Purchaser
- Search by description/reference

### 14.5 Source Labels

Use clear source labels:

- Shop Cashbook
- Procurement Expense
- Purchaser Other Expense
- Company Expense
- Payroll/Salary source when available

### 14.6 Behavior

- Default sort is date descending, then source record ID descending.
- Paginate at 50 rows per page.
- Summary totals use the full filtered result, not only the current page.
- Clicking a source reference opens the existing authorized source detail where available.
- Exports contain the full filtered result.

## 15. Report 4: Expense Report

### 15.1 Page Purpose

Provide the daily normalized operating-expense matrix.

### 15.2 Main Table

| Date | Salary | Rent | Vehicle/Fuel | Food/Mess | Others | Total |
|---|---:|---:|---:|---:|---:|---:|

### 15.3 Formula

```text
Daily Operating Expense Total =
Salary + Rent + Vehicle/Fuel + Food/Mess + Others
```

### 15.4 Rules

- Product purchase expenses are not repeated in this matrix.
- Every value opens the source records for that date and heading.
- The final row contains period totals.
- The period total equals Monthly Sale Split → Other Expenses.
- Original categories remain visible in drilldown even though the table uses normalized headings.

## 16. Amount Allocation and Rounding

### 16.1 Shop Invoices/GL Bills

The header total is authoritative for Total Sales. To split an invoice by product group:

```text
Allocated Item Sale =
Invoice Final Total × Item Gross Line Total / Sum of Invoice Gross Line Totals
```

If there is no valid gross total, allocate using stored line totals. Assign any final ₹0.01 rounding residual to the largest line so item allocations equal the invoice final total exactly.

### 16.2 Direct Company Sales

- Header `amount` is authoritative.
- Item `line_total` values provide the category split.
- Allocate any difference between item sum and header amount proportionally.
- Legacy header-only sales are included in Total Sales and shown as Unmapped/Legacy in sale split.

### 16.3 Warehouse Sales

- Header `total_amount` is authoritative.
- Item `line_total` values provide the category split.
- Allocate header-level discount proportionally so category totals equal `total_amount`.

### 16.4 Purchases

Reuse the net item-allocation logic in `PurchaseReportingService`:

- Start from item gross value.
- Allocate invoice net amount after discount proportionally to items.
- Include cash and credit purchases.
- Exclude deleted/cancelled invoices.

### 16.5 Numeric Rules

- Calculate using decimal values; do not use binary floating point for persisted financial values.
- Round displayed and exported currency to two decimal places.
- Quantity may retain three decimal places in drilldowns.
- Compare reconciliation differences with a tolerance of ₹0.01.

## 17. Drilldown Contract

Every drilldown response must contain:

```text
period
metric code
metric label
total
grouped subtotals
source rows
reconciliation status
```

Common source-row fields:

```text
business date
source type
source ID/public UUID
client
shop or warehouse
purchaser
vendor/customer
document number/reference
product
quantity and unit where applicable
original category
final report heading
amount
status
```

Drilldowns are read-only and paginated. Their total must equal the clicked amount independently of pagination.

## 18. Export Requirements

Each report page provides:

- Excel XLSX
- CSV
- PDF/print

Exports must:

- Use the same normalized date range and filters as the screen.
- Use the same calculation service as the screen.
- Include generated timestamp, period, user, and readiness/reconciliation status.
- Include summary totals and detailed rows appropriate to the page.
- Include client and source breakdowns where applicable.
- Preserve negative signs and two-decimal currency values.
- Neutralize spreadsheet-formula prefixes in exported text values.
- Stream large CSV/Excel datasets rather than loading all rows into memory.
- Never expose internal notes or sensitive account data beyond what the authorized on-screen drilldown displays.

## 19. UI and Interaction Requirements

- Reuse the existing Cashbook layout, date controls, card style, table components, and responsive behavior.
- Do not introduce a separate design system or dependency.
- Use semantic table headers and grouped column headers.
- Tables must remain usable at 375px, 768px, 1024px, and desktop widths.
- On narrow screens, Monthly Sale Split becomes expandable daily cards rather than an unreadable horizontal table.
- Interactive amounts must be keyboard accessible and expose an accessible name.
- Do not rely on green/red alone; show text such as Positive, Balanced, or Deficit.
- Loading, empty, warning, and error states must preserve the selected period.
- A configuration warning must identify the unmapped amount and provide a settings link for authorized users.
- Currency formatting uses `₹` and Indian grouping where supported by existing helpers.

## 20. Proposed Routes

Settings:

```text
GET  /admin/cashbook/settings/final-report
PUT  /admin/cashbook/settings/final-report/shop-headings
PUT  /admin/cashbook/settings/final-report/product-groups
PUT  /admin/cashbook/settings/final-report/expense-mappings
GET  /admin/cashbook/settings/final-report/readiness
```

Reports:

```text
GET /admin/cashbook/reports/monthly
GET /admin/cashbook/reports/monthly/sale-split
GET /admin/cashbook/reports/monthly/other-expenses
GET /admin/cashbook/reports/monthly/expense-report
GET /admin/cashbook/reports/monthly/drilldown
```

Exports:

```text
GET /admin/cashbook/reports/monthly/{report}/export/csv
GET /admin/cashbook/reports/monthly/{report}/export/excel
GET /admin/cashbook/reports/monthly/{report}/export/pdf
```

Suggested route names:

```text
admin.cashbook.settings.final-report.index
admin.cashbook.settings.final-report.shop-headings.update
admin.cashbook.settings.final-report.product-groups.update
admin.cashbook.settings.final-report.expense-mappings.update
admin.cashbook.settings.final-report.readiness

admin.cashbook.monthly-report.overview
admin.cashbook.monthly-report.sale-split
admin.cashbook.monthly-report.other-expenses
admin.cashbook.monthly-report.expense-report
admin.cashbook.monthly-report.drilldown
admin.cashbook.monthly-report.export.csv
admin.cashbook.monthly-report.export.excel
admin.cashbook.monthly-report.export.pdf
```

## 21. Proposed Laravel Architecture

### 21.1 Controllers

```text
Admin/FinalReportSettingsController
Admin/GreenLeafMonthlyReportController
Admin/GreenLeafMonthlyReportExportController
```

Controllers validate input, authorize, call services, and return views/downloads. Financial calculations must not live in controllers or Blade templates.

### 21.2 Services

```text
Cashbook/MonthlyReport/ReportPeriodResolver
Cashbook/MonthlyReport/ShopReportBridge
Cashbook/MonthlyReport/SalesAggregationService
Cashbook/MonthlyReport/PurchaseAggregationService
Cashbook/MonthlyReport/OperatingExpenseAggregationService
Cashbook/MonthlyReport/PurchaserPositionService
Cashbook/MonthlyReport/MonthlyReportReconciliationService
Cashbook/MonthlyReport/GreenLeafMonthlyReportService
Cashbook/MonthlyReport/FinalReportSettingsService
```

Responsibilities:

- `ShopReportBridge`: consume Cashbook Payments Report configuration and return standardized shop rows.
- `SalesAggregationService`: combine client bridge sales with direct GL bills, direct company sales, and warehouse sales.
- `PurchaseAggregationService`: reuse purchase item net-allocation and product filters.
- `OperatingExpenseAggregationService`: normalize shop, purchaser, and residual company expenses while de-duplicating mirrors.
- `PurchaserPositionService`: calculate opening, movements, purchases, expenses, returns, and closing position.
- `MonthlyReportReconciliationService`: prove page and source totals agree.
- `GreenLeafMonthlyReportService`: compose immutable report arrays for views and exports.

### 21.3 Requests

```text
MonthlyReportPeriodRequest
UpdateFinalReportShopHeadingsRequest
UpdateFinalReportProductGroupsRequest
UpdateFinalReportExpenseMappingsRequest
MonthlyReportDrilldownRequest
```

Use validated input only.

### 21.4 Reuse Requirements

- Reuse `ShopPaymentsReportConfigService` calculations through the bridge.
- Reuse `PurchaseReportingService` item allocation rather than creating a competing purchase formula.
- Reuse existing client/shop, warehouse-sale, and purchaser-finance relationships.
- Reuse existing export infrastructure and Cashbook layout components.
- Do not query in Blade templates.

## 22. Performance Requirements

- Summary pages should complete within 2 seconds for a normal one-month range on production-sized data.
- Custom one-year reports should complete within 8 seconds or use an asynchronous export path if the synchronous limit is exceeded.
- Aggregate with database grouping and conditional sums; do not load all source models into PHP when SQL aggregation is possible.
- Select only required columns.
- Eager-load relationships used by paginated drilldowns.
- Paginate detailed tables.
- Add or confirm indexes on source status/date and mapping columns used by the report.
- Cache read-only aggregate results by period plus settings-update timestamp when justified.
- Invalidate cached results when source records or report mappings change.

Recommended index review:

```text
shop_invoices(shop_id, business_date, status)
warehouse_sales(status, business_date)
direct_company_sales(sale_status, business_date)
purchase_invoices(status, purchaser_cart_id)
purchaser_carts(business_date, user_id)
purchaser_credits(purchaser_id, business_date, type)
procurement_expenses(user_id, expense_date)
other_expenses(user_id, expense_date)
company_accounting_entries(type, status, business_date)
shop_ledger_entry_settings(shop_id, monthly_report_bucket)
purchase_product_filters(monthly_report_group)
```

Only add missing indexes after checking the actual schema.

## 23. Empty, Warning, and Error States

### Empty

- Show zero cards and date rows for a valid period with no activity.
- Show “No records for this filter” in detail tables.
- Exports still contain period metadata and zero totals.

### Warning

- Unmapped product
- Unmapped shop category
- Overlapping product filter membership
- Legacy direct sale without item detail
- Source total and category split differ by more than ₹0.01
- Purchaser position is negative

Warnings display affected amount and a drilldown. They must not be hidden in logs only.

### Error

- Invalid period
- Unauthorized source access
- Missing required report configuration
- Calculation failure

Unexpected errors are logged with source IDs and metric codes without logging sensitive financial notes or personal details.

## 24. Reconciliation and Audit Requirements

For every period, the service must calculate and expose:

```text
overview_sales_difference = overview total sales - sum daily total sales
overview_expense_difference = overview expenses - sum daily expenses
sale_split_difference = overview total sales - sale split total sales
expense_split_difference = overview expenses - sale split total expenses
operating_expense_difference = sale split other expenses - expense report total
client_difference = client sales - sum client/shop sales
other_sales_difference = all other sales - sum direct GL + direct company + warehouse
```

All differences must be within ₹0.01. A non-zero result outside tolerance makes the report status `Needs Review` and exposes the affected source breakdown.

The report footer and exports show:

- Reconciled / Needs Review
- Settings readiness
- Generated timestamp
- Generated by
- Period

## 25. Security and Data Protection

- Use server-side authorization on every report, settings, drilldown, and export route.
- Validate and scope all filter IDs.
- Escape Blade output.
- Never expose bank account numbers, secrets, private employee information, or unrestricted transaction notes.
- Salary drilldowns show financial entries required for the report, not employee HR records.
- Export authorization is checked at download time.
- Protect settings updates with CSRF and Form Requests.
- Use database transactions for settings updates that change multiple mappings.
- Log settings changes, not report views containing sensitive row data.

## 26. Test Plan

All tests are PHPUnit feature/unit tests and run with the smallest relevant test command.

### 26.1 Settings Tests

- Admin can view and update Final Report Settings.
- Finance can view readiness but cannot mutate settings.
- Unauthorized roles receive `403`.
- A shop category maps to one valid heading.
- Transfer/settlement defaults to Ignore.
- Product group requires an active Purchase Product Filter.
- Duplicate monthly groups are rejected.
- Overlapping filter products block readiness.
- Unmapped active shop categories appear in readiness warnings.
- Settings changes are audited.

### 26.2 Overview Tests

- Client sales come from Cashbook Payments bridge headings.
- Client GL bills are excluded from Total Sales.
- Direct-shop GL bills are included in All Other Sales.
- Confirmed direct company sales are included.
- Confirmed warehouse sales are included.
- Draft/cancelled warehouse sales are excluded.
- Cancelled GL bills and direct sales are excluded.
- Total Sales, Expenses, and Balance formulas are exact.
- Empty dates appear with zero values.
- Client drilldown totals match client card/table values.

### 26.3 Monthly Sale Split Tests

- Fruits, Vegetables, and Stationery sales use the selected filters.
- Purchase invoice discount is allocated to items and reconciles to invoice net.
- Warehouse discount is allocated and reconciles to warehouse total.
- Direct sale items reconcile to the direct sale header.
- Cash and credit purchases are both included as category expenses.
- Unpaid credit purchase does not reduce purchaser cash position.
- Unmapped products are visible and retained in totals.
- Overlapping products are not double-counted.
- Daily and period totals reconcile with Overview.

### 26.4 Expense Tests

- Salary, Rent, Vehicle/Fuel, Food/Mess, and Others map correctly.
- Procurement vehicle/fuel/toll categories map to Vehicle/Fuel.
- Procurement food maps to Food/Mess.
- Product purchases do not appear again in Expense Report.
- Linked company accounting mirrors are not double-counted.
- Reversed company entries are excluded.
- Other Expense summary equals its filtered detail.
- Expense Report total equals Monthly Sale Split Other Expenses.

### 26.5 Purchaser Position Tests

- Opening position includes activity before the report start date.
- Cash given increases position.
- Cash purchase utilization decreases position.
- Purchaser-funded procurement and other expenses decrease position.
- Company-paid expenses do not decrease purchaser position.
- Cash returned decreases cash held and is displayed separately.
- Credit purchases appear in totals but not cash utilization.
- Negative positions receive Deficit status.
- Purchaser detail running balance equals closing position.

### 26.6 Export Tests

- Screen and each export use identical totals.
- Period and filters are preserved.
- CSV and Excel escape formula-like text.
- Unauthorized export returns `403`.
- Large exports stream without excessive memory use.

### 26.7 Regression Tests

- Existing Shop Sales Report totals remain unchanged.
- Existing Payment Settings behavior remains unchanged outside the new field.
- Existing GL Bills, purchase reports, purchaser finance, and warehouse sales pages remain functional.
- Existing shop and warehouse authorization remains enforced.

## 27. Acceptance Criteria

The feature is accepted when all conditions below are true:

1. Final Report Settings exists under Cashbook Settings.
2. Every client/owned-shop payment category can be assigned to one standard final-report heading.
3. Fruits, Vegetables, and Stationery are assigned through Purchase Product Filters with overlap validation.
4. The Cashbook sidebar contains the four agreed report entries.
5. Green Leaf Monthly Report shows Total Sales, Expenses, and Balance.
6. Its daily table shows Date, Client Sales, All Other Sales, Expenses, and Balance.
7. Client Sales drilldown shows each client and shop separately.
8. All Other Sales separates direct-shop GL bills, direct company sales, and warehouse sales.
9. Monthly Sale Split shows Fruits, Vegetables, and Stationery Sale/Expense columns plus Other Expenses.
10. Every amount has an authorized detail view and the table has a total row.
11. Other Expense provides category-wise details with period and source filters.
12. Expense Report shows Date, Salary, Rent, Vehicle/Fuel, Food/Mess, Others, and Total.
13. Purchaser Position shows cash given, purchases, purchaser expenses, returns, and positive/negative closing position.
14. Purchases are recognized as expenses regardless of payment status.
15. Cash given, transfers, and settlements are not treated as expenses.
16. Mirrored accounting entries are not double-counted.
17. All page, drilldown, and export totals reconcile within ₹0.01.
18. Admin and Finance can view; only authorized Admin users can change settings.
19. Excel, CSV, and PDF/print exports match the screen.
20. Required PHPUnit tests pass and existing related reports do not regress.

## 28. Implementation Phases

### Phase 1: Configuration Foundation

- Add report heading dictionary.
- Add shop monthly heading field and UI in Cashbook Payments settings.
- Add Purchase Product Filter monthly group.
- Add expense mapping table and Final Report Settings page.
- Add permissions and readiness validation.

### Phase 2: Normalized Source Services

- Build Shop Report Bridge.
- Extract/reuse sales and purchase item allocation.
- Build operating-expense de-duplication.
- Build purchaser-position calculation.
- Add reconciliation service.

### Phase 3: Overview

- Add Green Leaf Monthly Report route, navigation, cards, daily table, and drilldowns.
- Validate client versus other-sales source separation.

### Phase 4: Detail Reports

- Add Monthly Sale Split.
- Add purchaser position section.
- Add Other Expense.
- Add Expense Report.

### Phase 5: Exports and Hardening

- Add Excel, CSV, and PDF/print exports.
- Add performance indexes only where schema inspection proves they are missing.
- Complete authorization, accessibility, empty-state, and error-state checks.

### Phase 6: Verification and Rollout

- Run targeted tests after every phase.
- Run all related Cashbook, Finance, Purchasing, and Warehouse tests.
- Run the full suite if approved after targeted tests pass.
- Compare one known month manually against existing source reports.
- Release first to Admin/Main Admin, then enable Finance access after reconciliation sign-off.

## 29. Rollout and Backfill

1. Deploy schema and permissions.
2. Backfill expense `funding_source` to `purchaser_advance` and report the affected row count.
3. Configure Fruits, Vegetables, and Stationery filters.
4. Map every active client/owned-shop payment category.
5. Map company and purchaser expense categories.
6. Resolve all Blocked readiness items.
7. Generate a preview for a closed historical month.
8. Compare totals with Shop Sales Reports, GL Bills, Purchase Reports, Warehouse Sales, and purchaser finance.
9. Record reconciliation sign-off.
10. Enable Finance users.

No destructive historical transaction rewrite is permitted. Mapping/backfill migrations must be reversible. If the report cannot reconcile, it must show Needs Review rather than silently publishing a false Reconciled status.

## 30. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Product appears in multiple filters | Block settings readiness and report overlap explicitly |
| Shop category is unmapped | Keep amount visible as Unmapped and link to settings |
| Client GL bills double-count client shop activity | Exclude client-shop GL bills from Total Sales by source contract |
| Purchase or expense mirrored into accounting | Count source record once and exclude linked mirror |
| Header discounts break category totals | Allocate header net proportionally and apply rounding residual |
| Purchaser expense funding source is ambiguous | Add funding source; backfill existing rows to agreed purchaser-advance rule |
| Historical settings changes alter category splits | Audit every mapping change and show generated/settings timestamp; closed-month snapshotting may be added later if required |
| Large custom ranges are slow | Limit to 366 days, aggregate in SQL, paginate detail, stream exports |
| Negative purchaser balance is misunderstood | Label as Deficit and expose full running-balance drilldown |

## 31. Definition of Done

- All acceptance criteria pass.
- All mappings required for production are Ready or have explicitly accepted warnings.
- Targeted and regression tests pass.
- PHP files are formatted with Pint.
- No dependency or environment configuration was changed without approval.
- No existing uncommitted user work was overwritten.
- One representative month reconciles against all authoritative source reports.
- Report and export totals match.
- Permissions are verified for Main Admin, Admin, Finance/Accounts, Purchaser, Shop Owner, and Warehouse roles.
- Remaining known risks are documented in the release handoff.
