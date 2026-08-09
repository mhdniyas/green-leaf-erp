# Direct Purchase Company-Default Plan

Date: 2026-08-09

## Goal
Make purchaser direct buying run as company-funded by default, and make warehouse intake explicit and consistent for direct purchase flow.

Secondary goal: make a single default purchaser operator at user/config level (not product-level logic).

## Current Flow Check (What Is Happening Now)
1. Admin direct purchase creates an approved admin order (`order_source=admin_direct_purchase`) from accounting screen:
- [app/Http/Controllers/Web/RequisitionController.php](app/Http/Controllers/Web/RequisitionController.php#L354)
- [resources/views/admin/accounting/purchasers/direct-purchase.blade.php](resources/views/admin/accounting/purchasers/direct-purchase.blade.php)

2. Purchaser daily/cart supports mixed demand and stamps cart `purchase_source` as `shop_order|green_leaf_direct_purchase|mixed`:
- [resources/views/purchasing/purchaser/partials/daily_item.blade.php](resources/views/purchasing/purchaser/partials/daily_item.blade.php#L1)
- [app/Http/Controllers/Web/Purchasing/PurchaserDashboardController.php](app/Http/Controllers/Web/Purchasing/PurchaserDashboardController.php#L1948)

3. On bill submit, payment owner is hard-defaulted to purchaser for non-credit bills:
- [app/Http/Controllers/Web/Purchasing/PurchaserDashboardController.php](app/Http/Controllers/Web/Purchasing/PurchaserDashboardController.php#L1846)

4. Journal posting already has company direct-purchase path, but it triggers only in specific conditions:
- [app/Http/Controllers/Web/Purchasing/PurchaserDashboardController.php](app/Http/Controllers/Web/Purchasing/PurchaserDashboardController.php#L1907)
- [app/Services/Finance/JournalService.php](app/Services/Finance/JournalService.php#L349)

5. Warehouse can receive admin direct purchase orders separately and move stock directly into inventory:
- [app/Http/Controllers/Web/Warehouse/WarehouseReceiverController.php](app/Http/Controllers/Web/Warehouse/WarehouseReceiverController.php#L343)

## Clarity Problems Found
1. Payment owner is implicit and inconsistent:
- UI does not clearly show who is paying (company vs purchaser) before submit.
- Controller defaults non-credit to purchaser.

2. Two direct paths exist without one canonical rule:
- Admin direct purchase order path.
- Purchaser cart submit path creating PO/GRN/invoice directly.

3. Purchase source and payment owner are not centrally governed:
- `purchase_source` can become `mixed`, but payment ownership logic is separate.

4. Authorization for admin direct purchase is strict and surprising:
- Admin must also have purchaser role.
- [app/Http/Controllers/Web/RequisitionController.php](app/Http/Controllers/Web/RequisitionController.php#L2113)

## Target Operating Rule (Proposed)
1. If source contains direct purchase (`green_leaf_direct_purchase` or `mixed` with direct component), default payment owner to company.
2. Purchaser cash (`PurchaserCredit`) should be touched only when payment owner is purchaser.
3. Warehouse intake remains mandatory and visible for direct purchase items before dispatch.
4. UI must display a single clear badge: `Paid By: Company` or `Paid By: Purchaser` before final submit.

## User Clarifications (Locked Requirements)
1. Direct purchase payments are company-owned by default.
2. If direct purchase is submitted as Credit, it must still be shown under company ownership (not purchaser-owned).
3. If cash is paid on direct purchase, cash must be deducted and the company journal must be updated.
4. Admin should not disappear from purchaser operations when acting as default purchaser.
5. Default purchaser assignment must be operator-level, not product-level.

## Default Purchaser Model (Not Product)
1. Introduce a single operational setting: `default_purchaser_user_id`.
2. Store in business settings and resolve once per request via service.
3. Allowed values:
- any active user with role `purchaser`
- or admin user explicitly marked as `allow_default_purchaser=true`
4. Use this default only for operational ownership and fallback list visibility.
5. Do not derive purchaser ownership from product/category mappings.

## Payment Ownership Matrix (Final)
1. Direct purchase + Cash/Online/GPay:
- `payment_paid_by = company`
- if `paid_amount > 0`, post direct purchase journal entry via company cash account.
2. Direct purchase + Credit:
- `payment_paid_by = company`
- `payment_status = credit_pending_approval` until paid.
- settlement later should use company payment path.
3. Non-direct purchase:
- keep current purchaser-owned behavior unless manually overridden by admin.

## Implementation Plan

### Phase 0: Data and Config Foundation
1. Add setting key `default_purchaser_user_id` in business settings.
2. Add optional user flag `allow_default_purchaser` (boolean).
3. Add resolver service method: `resolveDefaultPurchaserUser()`.
4. Add guardrails:
- block saving invalid users
- fallback to first active purchaser if configured user becomes inactive.

### Phase 1: Domain Rules (Single Source of Truth)
1. Add a resolver method for payment owner by source and payment method.
2. Place resolver in service layer (preferred: `PurchaseInvoiceService` or dedicated policy/helper service).
3. Rule matrix:
- Direct purchase source + any payment method => `company`
- Non-direct + Credit unpaid => `vendor_credit`
- Else => `purchaser`

### Phase 2: Submission Flow Wiring
1. Update purchaser bill submit flow to use resolver instead of hardcoded default:
- [app/Http/Controllers/Web/Purchasing/PurchaserDashboardController.php](app/Http/Controllers/Web/Purchasing/PurchaserDashboardController.php#L1727)
2. Ensure invoice `payment_paid_by` and cart fields stay aligned.
3. For direct purchase with paid amount, always call company direct purchase journal posting and skip purchaser credit debit.
4. For direct purchase credit, keep company ownership and route final settlement through company payment journal.
5. Set purchaser owner from `resolveDefaultPurchaserUser()` when actor is admin fallback flow.

### Phase 3: Warehouse-First Clarity
1. Add explicit status step for direct purchase: `Pending Warehouse Receive`.
2. Block dispatch visibility until `receiveDirectPurchase` completes for direct orders.
3. Show counts in purchaser/admin screens for direct orders pending warehouse receive.

### Phase 4: UI Clarity Updates
1. Bill modal:
- Add read-only `Paid By` indicator.
- Add source badge: `Shop Demand`, `Direct Purchase`, `Mixed`.
2. Purchaser vendor/history lists:
- Display payment owner column/badge.
3. Admin accounting direct-purchase page:
- Add text: "Default payment owner for direct purchase is Company".

### Phase 5: Authorization and Role Cleanup
1. Decide policy for admin direct purchase access:
- Option A: keep admin+purchaser requirement.
- Option B (recommended): allow admin role directly and impersonate purchaser only when needed.
2. Apply consistently in:
- [app/Http/Controllers/Web/RequisitionController.php](app/Http/Controllers/Web/RequisitionController.php#L2113)
- [app/Http/Controllers/Web/Admin/AdminAccountingController.php](app/Http/Controllers/Web/Admin/AdminAccountingController.php#L1820)
3. Purchaser list issue:
- keep operational list as role `purchaser`, but add `Default Purchasing Operator` config support for admin fallback.
- show configured admin operator in purchaser screens even if not in role-filtered list.

### Phase 5.1: Purchaser List and Screen Consistency
1. In purchaser list APIs/queries, include configured default purchaser user even when not returned by role-only filter.
2. Show badge on configured operator: `Default Purchaser`.
3. In admin accounting purchaser pages, always show selected default purchaser in filter dropdown and summary cards.
4. In submit/create flows, log both:
- `acted_by_user_id` (actual logged-in user)
- `purchaser_owner_user_id` (default purchaser owner)


### Phase 6: Reporting and Audit
1. Add dashboard counters split by `payment_paid_by` and `purchase_source`.
2. Add activity log entries when default owner is auto-set to company.
3. Add exception report for mismatches (direct source but payer is purchaser).

## Testing Plan
1. Feature test: configured default purchaser appears in purchaser dropdown/list even if admin fallback user.
2. Feature test: admin fallback flow creates records under `purchaser_owner_user_id = default_purchaser_user_id`.
3. Feature test: direct purchase submit defaults payer to company.
4. Feature test: direct purchase credit keeps `payment_paid_by=company` and `payment_status=credit_pending_approval`.
5. Feature test: direct purchase paid cash posts company direct purchase journal and does not create purchaser credit out.
6. Feature test: warehouse direct receive transitions order to ready_for_dispatch.
7. Regression test: purchaser credit out entry created only when payer is purchaser.
8. Feature test: default purchaser fallback when configured user becomes inactive.

## Final Delivery Sequence
1. Implement Phase 0 and Phase 1 together.
2. Implement Phase 2 with journal and purchaser-credit branching.
3. Implement Phase 5 and Phase 5.1 to fix admin/default purchaser visibility.
4. Implement Phase 3 and Phase 4 for workflow and UI clarity.
5. Execute full testing plan and fix regressions.
6. Roll out with 7-day monitoring dashboard for payer/source mismatches.

## Rollout Plan
1. Release in two steps:
- Step 1: resolver + backend behavior + tests.
- Step 2: UI labels and workflow messaging.
2. Backfill script for recent inconsistent records (optional, last 30-60 days).
3. Add temporary admin warning banner for mismatched historical records.

## Acceptance Criteria
1. For direct purchase, default payer is company without manual override.
2. If discount is zero, payable = final total exactly.
3. Warehouse receive status is visible and mandatory before dispatch for direct purchase.
4. Purchaser ledger no longer decreases for company-paid direct purchases.
5. Admin and purchaser screens show consistent source and payer labels.
