# Shop Owner Workflow Audit

Date: 2026-07-10

## Scope

Audit-first review of the existing shop-owner workflow before broader changes. The baseline below was captured before code edits.

## Baseline Commands

- `php artisan optimize:clear`: passed
- `php artisan migrate:status`: all listed migrations ran successfully
- `php artisan route:list --except-vendor`: 339 routes
- `php artisan test --compact`: 515 tests, 504 passed, 11 failed

### Pre-existing failing tests from full suite

1. `Tests\Feature\Admin\AdminAccountingDashboardTest::test_admin_can_view_accounting_dashboard_and_non_admin_users_cannot`
2. `Tests\Feature\Admin\AdminAccountingDashboardTest::test_admin_can_store_single_daily_entry_for_shop_and_standard_shops_are_rejected`
3. `Tests\Feature\Admin\PurchaserCreditTest::test_admin_can_view_purchasers_ledger_index_and_details`
4. `Tests\Feature\Admin\UserManagementTest::test_authorized_user_can_update_user`
5. `Tests\Feature\Purchasing\PurchaseOrderTest::test_authorized_user_can_view_purchase_orders_list`
6. `Tests\Feature\Web\WarehouseTest::test_loadout_propagates_warehouse_id_to_outgoing_movements`
7-11. Five additional pre-existing failures were present in the same baseline run, but the captured output truncated before their detailed names were returned.

### Focused test snapshots

- `--filter=ShopOwner`: 31 passed
- `--filter=Delivery`: failed due to pre-existing `ShopInvoiceFlowTest` delivery-status expectations
- `--filter=Invoice`: failed due to pre-existing `ShopInvoiceFlowTest` delivery-review expectations
- `--filter=Discrepancy`: failed due to the same pre-existing `ShopInvoiceFlowTest` delivery-review expectations
- `--filter=Accounting`: 24 passed, 2 failed in `AdminAccountingDashboardTest`
- `--filter=Warehouse`: 78 passed, 1 failed in `WarehouseTest`
- `tests/Feature/Web/RequisitionTest.php`: 55 passed
- `tests/Feature/Web/ShopOwnerModulesTest.php`: 16 passed
- `tests/Feature/ShopOwnerActiveShopFlowTest.php`: 3 passed

## Files Inspected

- Routes: `routes/web.php`
- Shop-owner controllers: `app/Http/Controllers/Web/ShopOwnerController.php`, `app/Http/Controllers/Web/ShopOwnerStaffController.php`
- Requisition flow: `app/Http/Controllers/Web/RequisitionController.php`, `app/Services/Requisition/ShopOrderRevisionService.php`
- Delivery / discrepancy: `app/Http/Controllers/Web/Admin/DiscrepancyReportController.php`, `app/Http/Requests/Web/Purchasing/ReviewDeliveryDiscrepancyRequest.php`
- Warehouse / delivery dashboards: `app/Http/Controllers/Web/Inventory/DeliveryDashboardController.php`, `app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php`
- Invoice / finance: `app/Services/ShopInvoices/ShopInvoiceService.php`, `app/Services/Finance/OwnedShopAccountingService.php`
- Models: `app/Models/ShopOrder.php`, `app/Models/ShopOrderItem.php`, `app/Models/ShopInvoice.php`, `app/Models/ShopOwnerAssignment.php`, `app/Models/User.php`, `app/Models/Shop.php`
- Migrations: `database/migrations/2026_05_25_173722_create_shops_and_shop_orders_tables.php`, `database/migrations/2026_06_25_175431_add_discrepancy_and_receiving_fields_to_tables.php`, `database/migrations/2026_07_02_174716_create_shop_owner_assignments_table.php`
- Views / navigation: `resources/views/shop-owner/**`
- Existing tests: `tests/Feature/Web/RequisitionTest.php`, `tests/Feature/Web/RequisitionDeliveryTest.php`, `tests/Feature/Web/ShopOwnerModulesTest.php`, `tests/Feature/ShopInvoiceFlowTest.php`, `tests/Feature/ShopOwnerStaffAccessTest.php`

## Requirement Matrix

| Requirement | Existing implementation | Status | Files involved | Risk | Required action |
| --- | --- | --- | --- | --- | --- |
| Multiple shops per user | Staff module already uses `shop_owner_assignments`; core shop-owner dashboard/orders/deliveries/finance/accounting originally used only `users.shop_id`. | Partial | `app/Http/Controllers/Web/ShopOwnerStaffController.php`, `app/Http/Controllers/Web/ShopOwnerController.php`, `app/Http/Controllers/Web/RequisitionController.php`, `app/Models/ShopOwnerAssignment.php` | High | Fix |
| Order submission | Shop owners can submit requisitions and item rows are persisted correctly. | Complete | `RequisitionController`, `ShopOrder`, `ShopOrderItem`, `tests/Feature/Web/RequisitionTest.php` | Medium | Keep |
| 9:30 PM cutoff | Cutoff existed, but it used app/server-local `now()` and `Carbon::today()` instead of explicit `Asia/Kolkata`. | Partial | `RequisitionController`, `ShopOrder` | Medium | Fix |
| Late request | Late orders are accepted and flagged with `is_late`; manager notifications exist. Late-state modeling is boolean-based rather than a dedicated status/history model. | Partial | `RequisitionController`, `ShopOrder`, `LateRequisitionSubmittedNotification` | Medium | Keep/Fix |
| Multiple orders per same business date | Schema already allows multiple orders because only `order_number` is unique, but controller logic previously deleted same-day orders before creating a new one. | Partial | `create_shops_and_shop_orders_tables`, `RequisitionController` | High | Fix |
| Order update approval | Revisions exist and approved-order amendments use `shop_order_revisions`. Non-approved post-submission updates still overwrite live order items for some paths. | Partial | `ShopOrderRevisionService`, `RequisitionController`, `ShopOrderRevision*` models | High | Fix |
| Delivery checking | Shop owner delivery check-in exists with delivered quantity, cash, and notes. Missing/damaged/returned split fields and attachment handling are absent. | Partial | `RequisitionController`, `ShopOrderItem`, `ShopInvoiceService`, `tests/Feature/Web/RequisitionDeliveryTest.php` | High | Fix |
| Discrepancy approval | Manager approval / rejection flow exists, with shortage handling and status transitions. Admin-specific queue UX and richer item-level override notes are limited. | Partial | `RequisitionController`, `DiscrepancyReportController`, `ReviewDeliveryDiscrepancyRequest`, `ShopInvoiceFlowTest.php` | High | Fix |
| Invoice generation | One invoice per order exists, invoice number generation already supports multiple orders per shop/date via suffixes. | Complete | `ShopInvoiceService`, `ShopInvoice`, `ShopInvoiceItem` | Medium | Keep |
| Received quantity calculation | Current billing logic is driven by `delivered_qty` and `shortage_qty`. There is no separate persisted `reported_received_quantity` vs `final_received_quantity` pair. | Partial | `ShopOrderItem`, `ShopInvoiceItem`, `ShopInvoiceService` | High | Fix |
| Owned-shop accounting | Owned-shop accounting, categories, entries, and admin review exist and are guarded by shop/accounting flags. | Complete | `OwnedShopAccountingService`, `AdminAccountingController`, `ShopOwnerController`, accounting migrations/tests | Medium | Keep |
| Staff salary | Owned-shop staff attendance and leave flows exist; payroll/admin salary flows exist outside the shop-owner portal. | Partial | `ShopOwnerStaffController`, admin staff/payroll controllers, `ShopOwnerStaffAccessTest.php` | Medium | Keep/Fix |

## Current Architectural Findings

### What is already working

- Requisition storage, review, revision records, and approval history are already present.
- Delivery check-in and discrepancy approval already connect into shop invoice recalculation.
- Owned-shop accounting and staff attendance modules already exist.
- `shop_owner_assignments` already provides a valid multi-shop authorization source.
- Invoice numbering already tolerates multiple orders per shop and business date.

### Incomplete or conflicting behavior

- Core shop-owner pages were inconsistent with staff pages: staff used assignment-based shop scoping while dashboard/orders/deliveries/finance/accounting used `users.shop_id`.
- Order creation logic contradicted the confirmed multiple-order requirement by deleting existing orders for the same shop and business date.
- Cutoff logic did not explicitly anchor to `Asia/Kolkata`.
- Delivery records model only `delivered_qty` and shortage math, not the fuller received/missing/damaged/returned lifecycle from the PRD.
- Discrepancy handling lives in manager-oriented requisition flow and a report page, not yet a dedicated admin queue experience.
- Some invoice/delivery expectations are already failing in baseline tests, indicating existing workflow drift in invoice status transitions.

## Safe Enhancements Completed In This Pass

1. Added shared active-shop resolution for shop-owner workflow using `shop_owner_assignments` plus `users.shop_id` fallback.
2. Applied active-shop scoping to shop-owner dashboard, orders, deliveries, finance, accounting, requisitions, presets, and delivery dashboard access paths.
3. Added a shop switcher in the shop-owner header for users with more than one authorized shop.
4. Stopped requisition creation from deleting same-day orders, allowing multiple orders for the same business date.
5. Switched requisition cutoff calculation to explicit `Asia/Kolkata`.
6. Added regression coverage for active-shop switching, unauthorized shop selection, and multiple same-day orders.

## Recommended Next Safe Slice

1. Move all post-submission order changes onto revision records so submitted values are never overwritten before approval.
2. Expand delivery item schema and UI for received/missing/damaged/returned quantities plus proof attachment.
3. Introduce a dedicated admin discrepancy queue with row-locking / current-status protection.
4. Separate shop-reported received quantity from admin-finalized billable quantity.

## Delivery Review Slice Completed

- Focused baseline before edits:
  - `tests/Feature/ShopInvoiceFlowTest.php`: 3 failures in delivery/invoice finalization expectations.
  - `tests/Feature/Web/RequisitionDeliveryTest.php`: passed.
  - `tests/Feature/Web/RequisitionTest.php`: passed.
  - `tests/Feature/Web/ShopOwnerModulesTest.php`: passed.
  - `tests/Feature/ShopOwnerActiveShopFlowTest.php`: passed.
  - `--filter=Warehouse`: 1 pre-existing failure in `WarehouseTest`.
  - `--filter=Accounting`: 2 pre-existing failures in `AdminAccountingDashboardTest`.

- This pass changed the delivery workflow to:
  1. Warehouse dispatch leaves the order in `in_transit`.
  2. Shop owner submits reported received quantities.
  3. Reported quantities are stored separately on `shop_order_items.shop_reported_*`.
  4. Order moves to `delivery_status = pending_approval` and `delivery_review_status = pending`.
  5. Invoice moves to `delivery_status = awaiting_review` without final shortage recalculation.
  6. Admin review writes final `delivered_qty` / `shortage_qty`, recalculates invoice totals, and marks the order delivered or partially delivered.
  7. Rejection clears reported quantities and reopens shop check-in.

- Billable quantity rule used in this implementation:
  - `shop_reported_received_qty` is provisional and never billed directly.
  - Final billing uses admin-approved `shop_order_items.delivered_qty`.
  - Existing `shop_invoices` / `shop_invoice_items` remain the final approved finance layer.

- Added review metadata:
  - `shop_orders.delivery_review_status`
  - `shop_orders.shop_checked_at`
  - `shop_orders.shop_checked_by`
  - `shop_orders.admin_reviewed_at`
  - `shop_orders.admin_reviewed_by`
  - `shop_orders.admin_review_note`
  - `shop_order_items.shop_reported_received_qty`
  - `shop_order_items.shop_reported_missing_qty`
  - `shop_order_items.shop_reported_damaged_qty`
  - `shop_order_items.shop_reported_returned_qty`

- Added admin queue:
  - `/admin/delivery-reviews`

- Focused verification after edits:
  - `tests/Feature/Web/RequisitionDeliveryTest.php`: passed.
  - `tests/Feature/ShopInvoiceFlowTest.php`: passed.
  - `tests/Feature/Admin/DeliveryReviewTest.php`: passed.
  - `tests/Feature/Web/ShopOwnerModulesTest.php`: passed.
