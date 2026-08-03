# Shop Order to Delivery Flow - Complete Audit Report

**Date:** 2026-08-03  
**Auditor:** GitHub Copilot  
**Scope:** Shop Order → Purchaser Approval → Warehouse Loadout → Delivery Check

---

## Executive Summary

The Green Leaf ERP currently implements a **single-table status-driven flow** where shop orders progress through multiple stages (submission, approval, loadout, delivery) using status flags and field updates on the `shop_orders` and `shop_order_items` tables. 

**Critical Finding:** The warehouse loadout process **deletes and recreates** `shop_order_items` records during partial loadout splits, causing loss of traceability to original requested items and risking data integrity issues.

---

## 1. Current Tables

| Stage | Main Table | Item Table | Key Relationships |
|-------|-----------|------------|-------------------|
| **Shop Order** | `shop_orders` | `shop_order_items` | Primary order record with `state`, `delivery_status`, `delivery_review_status` |
| **Purchaser Approval** | `shop_orders` | `shop_order_items` | Same tables; `state` → 'approved', `approved_qty` populated |
| **Warehouse Loadout** | `shop_orders` | `shop_order_items` | Same tables; items DELETED & RECREATED, `sorting_status` → 'loaded', `loaded_qty` populated |
| **Delivery Check (Shop)** | `shop_orders` | `shop_order_items` | Same tables; `shop_reported_*` fields populated, `delivery_review_status` → 'pending' |
| **Delivery Review (Admin)** | `shop_orders` | `shop_order_items` | Same tables; `delivered_qty`, `shortage_qty`, `excess_qty` finalized |
| **Invoice** | `shop_invoices` | `shop_invoice_items` | Linked via `shop_order_id` (unique FK); items reference `shop_order_item_id` (nullable) |

**Key Observation:** There are NO separate tables for approval, loadout, or delivery check records. Everything happens through status changes and column updates on the original order tables.

---

## 2. Current Flow

### 2.1 Shop Order Creation
**Files:**
- [RequisitionController.php](app/Http/Controllers/Web/RequisitionController.php) - `store()` method
- [ShopOrder.php](app/Models/ShopOrder.php)
- [2026_05_25_173722_create_shops_and_shop_orders_tables.php](database/migrations/2026_05_25_173722_create_shops_and_shop_orders_tables.php)

**Process:**
1. Shop owner submits order via web form or admin creates direct purchase
2. `ShopOrder` created with:
   - `state` = 'submitted' or 'approved' (auto-approve if before cutoff)
   - `delivery_status` = 'pending_delivery'
   - `business_date` = tomorrow's date
3. `ShopOrderItem` records created for each product:
   - `requested_qty` = shop's requested quantity
   - `approved_qty` = null (pending approval) or equals `requested_qty` (auto-approved)
   - `unit` = product base unit (typically 'kg')
   - `requested_unit` = shop's order unit (e.g., 'box', 'bunch')
   - `requested_unit_quantity` = quantity in shop's unit
   - `requested_unit_conversion_to_base` = conversion factor
   - `locked_selling_price` = price at order time
   - `sorting_status` = 'pending'

### 2.2 Purchaser Approval
**Files:**
- [RequisitionController.php](app/Http/Controllers/Web/RequisitionController.php) - `review()`, `saveBoard()`, `saveApprovedBoard()`
- Routes: `/requisitions/{order_number}/review`, `/purchaser/approval-board`

**Process:**
1. Purchaser views submitted orders in approval board
2. Purchaser reviews and modifies quantities if needed
3. On approval:
   - `shop_orders.state` → 'approved'
   - `shop_orders.reviewed_by` = purchaser user ID
   - `shop_orders.reviewed_at` = timestamp
   - `shop_order_items.approved_qty` = final approved quantity (may differ from `requested_qty`)
   - `shop_order_items.notes` = purchaser notes if any changes made

**Key Issue:** `approved_qty` overwrites/replaces the decision on the same row. There's no immutable history of what was requested vs. approved unless you check change requests table.

### 2.3 Warehouse Loadout
**Files:**
- [WarehouseLoadoutController.php](app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php) - `save()`, `moveToDelivery()`
- Route: `/warehouse/loadout/{shopOrder}`

**Process:**
1. Warehouse staff views approved orders ready for loadout
2. Items are grouped by `product_id` in the UI
3. Staff enters actual loaded quantities (in kg or order unit)
4. On save (line ~312-540 of WarehouseLoadoutController):
   ```php
   // CRITICAL: This deletes ALL items for the product
   $shopOrder->items()->where('product_id', $productId)->delete();
   
   // Then recreates items based on loadout:
   // - One "loaded" item with loaded_qty
   // - One "allocated" item with remaining balance (if partial)
   // - Or one "not_available" item if product unavailable
   ```
5. New fields populated:
   - `loaded_qty` = actual quantity loaded
   - `loaded_order_unit_qty` = quantity in shop's order unit
   - `actual_weight` = measured weight (if applicable)
   - `sorting_status` = 'loaded' or 'allocated' or 'not_available'
   - `excess_qty` = loaded qty exceeding approved qty (if any)
   - `loadout_discrepancy_type` = 'none', 'excess', 'shortage', 'not_available'
6. Stock movements created to deduct inventory
7. Order status updated:
   - `delivery_status` → 'ready_for_dispatch' (if any items loaded)
   - `is_allocation_completed` → false (still at warehouse)
8. When ready to dispatch:
   - Staff clicks "Move to Delivery"
   - `delivery_status` → 'in_transit'
   - `is_allocation_completed` → true

**CRITICAL ISSUE:** The `delete()` on line 389 destroys the original `shop_order_item` records. The new records have new IDs. This breaks:
- Traceability from shop request → approval → loadout
- Any external references to the original item IDs
- Audit history (unless soft deletes are working, but they were only added 2026-08-03)

### 2.4 Shop Delivery Check-in
**Files:**
- [RequisitionController.php](app/Http/Controllers/Web/RequisitionController.php) - `showDelivery()`, `recordDelivery()`
- [ResolveDeliveryReviewAction.php](app/Domains/ShopOrder/Actions/ResolveDeliveryReviewAction.php) - `submit()`
- Route: `/requisitions/{order_number}/delivery`

**Process:**
1. Shop owner views order marked as `in_transit`
2. Verifies delivered items and reports received quantities
3. On submit:
   - `shop_order_items.shop_reported_received_qty` = what shop actually received
   - `shop_order_items.shop_reported_missing_qty` = shortage from shop's perspective
   - `shop_order_items.shop_reported_excess_qty` = excess from shop's perspective
   - `shop_orders.shop_checked_by` = shop user ID
   - `shop_orders.shop_checked_at` = timestamp
   - `shop_orders.delivery_status` → 'pending_approval'
   - `shop_orders.delivery_review_status` → 'pending'
   - `shop_orders.cash_collected` = amount shop paid
4. Notification sent to admins for review

**Data Flow:** Shop reports against `loaded_qty` (not `approved_qty` or `requested_qty`). If warehouse loaded less than approved, shop can only confirm what was loaded.

### 2.5 Admin Delivery Review & Approval
**Files:**
- [ResolveDeliveryReviewAction.php](app/Domains/ShopOrder/Actions/ResolveDeliveryReviewAction.php) - `approve()`
- [RequisitionController.php](app/Http/Controllers/Web/RequisitionController.php) - `approveDeliveryDiscrepancy()`
- [DeliveryReviewController.php](app/Http/Controllers/Web/Admin/DeliveryReviewController.php)
- Route: `/requisitions/{order_number}/approve-delivery`

**Process:**
1. Admin reviews shop's reported quantities vs. loaded quantities
2. Admin can adjust final delivered quantities if disputes exist
3. Admin decides inventory action for shortages/excess:
   - Shortage: `add_back` (return to inventory) or `none` (write off)
   - Excess: `deduct_extra` (remove from inventory) or `none` (ignore)
4. On approval:
   - `shop_order_items.delivered_qty` = final approved delivered quantity
   - `shop_order_items.shortage_qty` = final confirmed shortage
   - `shop_order_items.excess_qty` = final confirmed excess
   - `shop_order_items.shortage_value` = shortage × unit_cost
   - `shop_order_items.excess_value` = excess × unit_cost
   - `shop_order_items.delivery_discrepancy_type` = 'wastage', 'theft', 'damage', 'excess', 'none'
   - Inventory adjusted based on admin's action selection
   - Invoice updated with final quantities
   - `shop_orders.delivery_status` → 'delivered' or 'partially_delivered'
   - `shop_orders.delivery_review_status` → 'approved'
   - `shop_orders.is_delivered` → true
   - `shop_orders.delivered_at` = timestamp
   - `shop_orders.admin_reviewed_by` = admin user ID
   - `shop_orders.admin_reviewed_at` = timestamp

### 2.6 Invoice Generation & Finalization
**Files:**
- [ShopInvoiceService.php](app/Services/ShopInvoices/ShopInvoiceService.php)
- [2026_06_12_130443_create_shop_invoices_table.php](database/migrations/2026_06_12_130443_create_shop_invoices_table.php)

**Process:**
1. Invoice created when order is approved (not at delivery)
2. `ShopInvoice` record linked to `ShopOrder` via `shop_order_id` (unique FK)
3. `ShopInvoiceItem` records created by grouping `shop_order_items` by `product_id`
4. Invoice items have nullable `shop_order_item_id` - **may reference recreated items from loadout**
5. After delivery approval, invoice recalculated with:
   - `subtotal` = sum of (approved_qty × unit_price)
   - `shortage_total` = sum of shortage amounts
   - `excess_total` = sum of excess amounts
   - `final_total` = subtotal - shortage_total + excess_total
   - `delivery_status` = 'received_full' or 'approved_after_discrepancy'
   - `status` = 'finalized'

---

## 3. Model Relationships

### Current Laravel Relationships

**ShopOrder → ShopOrderItem:**
```php
// app/Models/ShopOrder.php
public function items(): HasMany
{
    return $this->hasMany(ShopOrderItem::class);
}
```

**ShopOrderItem → ShopOrder:**
```php
// app/Models/ShopOrderItem.php
public function order(): BelongsTo
{
    return $this->belongsTo(ShopOrder::class, 'shop_order_id');
}
```

**ShopOrderItem → Product:**
```php
// app/Models/ShopOrderItem.php
public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}
```

**ShopOrder → ShopInvoice:**
```php
// app/Models/ShopOrder.php
public function invoice(): HasOne
{
    return $this->hasOne(ShopInvoice::class);
}
```

**ShopInvoice → ShopInvoiceItem:**
```php
// app/Models/ShopInvoice.php
public function items(): HasMany
{
    return $this->hasMany(ShopInvoiceItem::class);
}
```

**ShopInvoiceItem → ShopOrderItem:**
```php
// app/Models/ShopInvoiceItem.php (IMPLIED - not explicitly shown but exists as FK)
// shop_order_item_id → shop_order_items.id (nullable)
```

**Missing Relationships:**
- No parent-child relationship between original item and split items
- No relationship tracking item lifecycle from request → approval → loadout → delivery
- No relationship from invoice item back to the ORIGINAL shop order item that was requested

---

## 4. Quantity Mapping

| Stage | Requested Qty | Approved Qty | Loaded Qty | Delivered Qty | Unit | Actual Weight | Secondary Unit Qty |
|-------|---------------|--------------|------------|---------------|------|---------------|-------------------|
| **Shop Order** | `requested_qty` | null | null | null | `unit` (base) | null | `requested_unit_quantity` |
| **Approval** | `requested_qty` (unchanged) | `approved_qty` | null | null | `unit` | null | `requested_unit_quantity` |
| **Loadout (RECREATED ITEM)** | `requested_qty` (reset to loaded or remainder) | `approved_qty` (reset) | `loaded_qty` | null | `unit` | `actual_weight` | `loaded_order_unit_qty` |
| **Shop Check-in** | (same) | (same) | `loaded_qty` | null | `unit` | `actual_weight` | `loaded_order_unit_qty` |
| | | | | `shop_reported_received_qty` | | | |
| | | | | `shop_reported_missing_qty` | | | |
| | | | | `shop_reported_excess_qty` | | | |
| **Admin Approval** | (same) | (same) | `loaded_qty` | `delivered_qty` | `unit` | `actual_weight` | `loaded_order_unit_qty` |
| | | | | `shortage_qty` | | | |
| | | | | `excess_qty` | | | |

### Field Definitions (shop_order_items table)

| Field | Purpose | When Set | Risk of Overwrite |
|-------|---------|----------|-------------------|
| `requested_qty` | Original shop request in base unit (kg) | Order creation | **HIGH** - reset during loadout split |
| `approved_qty` | Purchaser-approved quantity | Approval stage | **HIGH** - reset during loadout split |
| `loaded_qty` | Actual warehouse loaded quantity | Loadout stage | Moderate - updated during loadout save |
| `loaded_order_unit_qty` | Loaded qty in shop's order unit (boxes, bunches) | Loadout stage | Moderate |
| `actual_weight` | Measured weight (for kg-based items) | Loadout stage | Low - recent addition |
| `shop_reported_received_qty` | What shop reports receiving | Shop check-in | Low |
| `shop_reported_missing_qty` | Shortage reported by shop | Shop check-in | Low |
| `shop_reported_excess_qty` | Excess reported by shop | Shop check-in | Low |
| `delivered_qty` | Final admin-approved delivered quantity | Admin approval | Low - final value |
| `shortage_qty` | Final approved shortage | Admin approval | Low - final value |
| `excess_qty` | Final approved excess | Admin approval | Low - final value |
| `unit_cost` | Cost per base unit (for shortage/excess value calculation) | Delivery stage | Low |
| `shortage_value` | shortage_qty × unit_cost | Admin approval | Low |
| `excess_value` | excess_qty × unit_cost | Admin approval | Low |

### Unit Conversion Fields

| Field | Purpose | Example |
|-------|---------|---------|
| `unit` | Base unit (product's storage unit) | 'kg' |
| `requested_unit` | Shop's order unit | 'box' |
| `requested_unit_label` | Display label for shop's unit | 'Box (5 kg)' |
| `requested_unit_quantity` | Quantity in shop's unit | 10.0 (boxes) |
| `requested_unit_conversion_to_base` | Conversion factor | 5.0 (5 kg per box) |
| `requested_qty` | Calculated base quantity | 50.0 (kg) = 10 boxes × 5 kg |
| `loaded_order_unit_qty` | Loaded quantity in shop's unit | 8.0 (boxes) |
| `loaded_qty` | Loaded quantity in base unit | 40.0 (kg) = 8 boxes × 5 kg |

---

## 5. Problems Found

### 5.1 CRITICAL: Item Deletion & Recreation During Loadout

**File:** [WarehouseLoadoutController.php](app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php) (line ~389)

**Code:**
```php
$shopOrder->items()->where('product_id', $productId)->delete();

// Then recreate items...
ShopOrderItem::create([...]);
```

**Current Behavior:**
- All `shop_order_items` records for a product are **permanently deleted**
- New records created with new IDs
- Original `requested_qty` and `approved_qty` values are recalculated and may not match originals
- If partial loadout, creates two new records (one 'loaded', one 'allocated')

**Expected Behavior:**
- Original items should be preserved for audit trail
- Loadout should either:
  - Update existing items with loaded quantities, OR
  - Create child/linked records that reference parent items

**Risk to Production Data:**
- **HIGH** - Loss of data integrity
- Cannot trace back to original shop request
- Cannot audit what shop originally requested vs. what was approved vs. what was loaded
- Historical reports may show incorrect data after Git pull if quantities are recalculated differently
- External integrations (if any) using `shop_order_item_id` will break
- `shop_invoice_items.shop_order_item_id` may reference non-existent (deleted) or wrong (recreated) items

**Mitigation:** Soft deletes were added on 2026-08-03 ([2026_08_03_000000_add_soft_deletes_to_shop_order_items_table.php](database/migrations/2026_08_03_000000_add_soft_deletes_to_shop_order_items_table.php)), but this doesn't solve the underlying issue of recreating items with new IDs.

---

### 5.2 No Foreign Key Linking to Original Shop Order Item

**File:** [2026_06_12_130444_create_shop_invoice_items_table.php](database/migrations/2026_06_12_130444_create_shop_invoice_items_table.php)

**Current Structure:**
```php
$table->foreignId('shop_order_item_id')->nullable()->constrained('shop_order_items')->nullOnDelete();
```

**Problem:**
- `shop_order_item_id` is nullable
- May reference a recreated item (not original)
- If item is deleted (pre-soft-delete), FK becomes null
- No way to reliably trace invoice item → original shop order item → original request

**Expected Behavior:**
- Invoice items should link to the ORIGINAL shop order item
- OR: Invoice items should aggregate from multiple split items
- OR: Separate tracking table for item lifecycle

**Risk:**
- **MEDIUM-HIGH** - Billing accuracy concerns
- Cannot reliably reconstruct "what was ordered vs. what was billed"
- Disputes cannot be resolved with source data

---

### 5.3 Quantity Overwriting on Same Row

**Files:** All controllers and services that update `shop_order_items`

**Problem:**
Multiple stages update the same columns:
- `approved_qty` set during approval, then reset during loadout split
- `requested_qty` reset during loadout split
- No immutable record of each stage's decision

**Current Behavior:**
After loadout split of 100 kg approved → 80 kg loaded:
- Original item (100 kg approved) is **deleted**
- New "loaded" item: `requested_qty=80`, `approved_qty=80`, `loaded_qty=80`
- New "allocated" item: `requested_qty=20`, `approved_qty=20`, `loaded_qty=null`

Original requested quantity of 100 kg is lost unless stored in change request tables.

**Expected Behavior:**
- Each stage should have its own immutable field OR separate record
- E.g., `original_requested_qty`, `purchaser_approved_qty`, `warehouse_loaded_qty`, `final_delivered_qty`
- OR: Append-only records with status transitions

**Risk:**
- **MEDIUM** - Audit trail gaps
- Cannot definitively answer "What did the shop originally request?"
- Reconciliation issues if loadout is edited multiple times

---

### 5.4 Loadout Allows Multiple Edits & Re-splits

**File:** [WarehouseLoadoutController.php](app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php) - `save()` method

**Problem:**
- Warehouse can save loadout multiple times before moving to delivery
- Each save deletes and recreates items
- If partial loadout is edited, items are re-split
- No history of previous loadout attempts

**Scenario:**
1. First save: Load 80 kg of 100 kg approved → creates loaded(80) + allocated(20)
2. Second save: Adjust to 90 kg → **deletes both items**, creates loaded(90) + allocated(10)
3. Third save: Mark as not available → **deletes both**, creates not_available(100)

**Risk:**
- **MEDIUM** - Operational confusion
- No audit trail of who loaded what when
- Cannot investigate discrepancies after multiple edits

---

### 5.5 No Direct Link from Shop Order Item to Stock Movement

**File:** [2026_07_27_000003_add_excess_delivery_tracking_fields.php](database/migrations/2026_07_27_000003_add_excess_delivery_tracking_fields.php)

**Current Structure:**
```php
Schema::table('stock_movements', function (Blueprint $table): void {
    $table->foreignId('shop_order_item_id')
        ->nullable()
        ->after('warehouse_id')
        ->constrained('shop_order_items')
        ->nullOnDelete();
});
```

**Problem:**
- FK was added recently (2026-07-27)
- Nullable, so not all stock movements are linked
- Loadout creates movements via `StockLedgerService` but may not always set `shop_order_item_id`
- If item is deleted/recreated, movements reference the NEW item ID

**Expected Behavior:**
- All outbound stock movements for loadout should be linked to the item
- Should reference ORIGINAL item, not recreated one

**Risk:**
- **LOW-MEDIUM** - Inventory audit trail gaps
- Cannot reliably answer "Which order consumed this stock?"

---

### 5.6 Actual Weight vs. Order Unit Quantity Confusion

**Files:**
- [2026_08_02_150000_add_actual_weight_to_shop_order_items_table.php](database/migrations/2026_08_02_150000_add_actual_weight_to_shop_order_items_table.php)
- [2026_08_02_140000_add_loaded_order_unit_qty_to_shop_order_items_table.php](database/migrations/2026_08_02_140000_add_loaded_order_unit_qty_to_shop_order_items_table.php)

**Problem:**
- Recent additions (2026-08-02) suggest confusion about how to handle units
- `actual_weight` for kg-based products
- `loaded_order_unit_qty` for unit-based products (boxes, bunches, pairs)
- Conversion logic scattered across controllers
- Not clear which field is authoritative for billing

**Scenario:**
- Shop orders 10 boxes (50 kg)
- Warehouse loads 8 boxes (actual weight = 38 kg due to underweight boxes)
- What should be billed? 8 boxes? 38 kg? 40 kg (expected weight)?

**Current Behavior:**
From [WarehouseLoadoutController.php](app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php) lines ~418-429:
```php
$submittedQty = $hasRequestedUnit
    ? round(max(0.0, (float) $loadedOrderUnitQty) * max(0.0, $conversionToBase), 3)
    : ($actualWeight > 0.0001
        ? $actualWeight
        : max(0.0, (float) ($itemsInput[$productId] ?? 0)));
```
- If shop ordered in units (boxes), uses `loaded_order_unit_qty × conversion`
- If shop ordered in kg, uses `actual_weight`
- But this logic may not be consistently applied everywhere

**Risk:**
- **MEDIUM** - Billing accuracy issues
- Shop may dispute charges if billed on actual weight vs. expected unit weight
- No clear policy captured in code

---

### 5.7 Status Changes Before All Items Completed

**File:** [WarehouseLoadoutController.php](app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php) - `save()` method (line ~540)

**Code:**
```php
$newStatus = $anyItemLoaded ? 'ready_for_dispatch' : 'pending_delivery';
$shopOrder->update(['delivery_status' => $newStatus]);
```

**Problem:**
- Order marked `ready_for_dispatch` as soon as ANY item is loaded
- Doesn't check if ALL approved items are loaded
- Partial loadout allowed, but no clear indicator of completion percentage

**Scenario:**
- Order has 10 products
- Warehouse loads 1 product
- Order status → `ready_for_dispatch`
- Can be moved to delivery even with 9 products unloaded

**Risk:**
- **LOW** - Operational confusion
- Delivery may start before loadout is actually complete
- Mitigated by `moveToPartialDelivery()` action, but workflow is unclear

---

### 5.8 Multiple Status Fields with Unclear Priority

**Files:**
- [ShopOrder.php](app/Models/ShopOrder.php)
- [ShopInvoice.php](app/Models/ShopInvoice.php)

**Problem:**
ShopOrder has:
- `state` (draft, submitted, approved, rejected, update_requested)
- `delivery_status` (pending_delivery, ready_for_dispatch, in_transit, delivered, pending_approval, partially_delivered, delivery_issue)
- `delivery_review_status` (not_started, pending, approved, correction_requested)
- `payment_status` (unpaid, partial, paid)
- `is_delivered` (boolean)
- `is_allocation_completed` (boolean)

ShopInvoice has:
- `status` (generated, delivery_review, finalized, payment_pending, paid)
- `delivery_status` (pending, awaiting_review, received_full, approved_after_discrepancy)
- `payment_status` (unpaid, partially_paid, paid)

**Issue:**
- Overlapping statuses between order and invoice
- No clear state machine or transition rules
- Logic scattered across controllers (e.g., `isFinanciallyLocked()` checks multiple fields)

**Risk:**
- **LOW-MEDIUM** - Developer confusion
- Potential for status inconsistencies
- Hard to visualize order lifecycle

---

### 5.9 Soft Deletes Added Recently

**File:** [2026_08_03_000000_add_soft_deletes_to_shop_order_items_table.php](database/migrations/2026_08_03_000000_add_soft_deletes_to_shop_order_items_table.php)

**Code:**
```php
Schema::table('shop_order_items', function (Blueprint $table) {
    $table->softDeletes()->after('updated_at');
});
```

**Problem:**
- Soft deletes added TODAY (2026-08-03)
- All historical deletions from loadout splits are HARD DELETES (data lost)
- Soft deletes don't solve the underlying issue of recreating items with new IDs
- Model doesn't use `SoftDeletes` trait consistently yet (trait added to ShopOrderItem model, but loadout code still uses `delete()`)

**Expected Behavior:**
- Should have been implemented from the start
- Need migration to prevent future data loss
- Need refactor to avoid deletion/recreation pattern

**Risk:**
- **MEDIUM** - Historical data lost
- Soft deletes only protect future operations
- Existing code may not respect soft deletes

---

### 5.10 No Validation of Total Quantities Across Splits

**File:** [WarehouseLoadoutController.php](app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php)

**Problem:**
When loadout splits an item (loaded + remainder):
```php
if ($submittedQty > 0) {
    ShopOrderItem::create([...loaded item...]);
}
if ($remaining > 0.001) {
    ShopOrderItem::create([...remainder item...]);
}
```

No validation that: `loaded.approved_qty + remainder.approved_qty == original.approved_qty`

**Risk:**
- **LOW** - Rounding errors may compound
- Quantity leakage over multiple edits
- Should validate: `loaded_qty + remaining <= approved_qty` (with tolerance)

---

### 5.11 Invoice Items Group by Product, Losing Item-Level Detail

**File:** [ShopInvoiceService.php](app/Services/ShopInvoices/ShopInvoiceService.php) (not fully shown, but evident from schema)

**Problem:**
- `shop_invoice_items` has `unique(['shop_invoice_id', 'product_id'])`
- Multiple `shop_order_items` for same product are aggregated into one invoice item
- `shop_order_item_id` can only reference ONE of the source items

**Scenario:**
- Order has 3 items for "Tomato Grade A" (split during loadout)
- Invoice has 1 item for "Tomato Grade A" with `shop_order_item_id` = ??? (which one?)

**Risk:**
- **MEDIUM** - Ambiguity in invoice-to-order traceability
- Cannot break down invoice line into source order items
- Disputes over specific batches/splits difficult to resolve

---

## 6. Recommended Simple Structure

### Option A: Minimal Change - Add Immutable Fields (Least Disruption)

**Keep current single-table structure** but add new columns to preserve original values:

#### Modify `shop_order_items` table:
```php
// New columns to add
$table->decimal('original_requested_qty', 10, 2)->after('requested_qty'); // Never changes
$table->decimal('final_approved_qty', 10, 2)->nullable()->after('approved_qty'); // Set once at approval, never reset
$table->foreignId('parent_item_id')->nullable()->constrained('shop_order_items')->nullOnDelete()->after('shop_order_id'); // For split items
$table->boolean('is_split_item')->default(false)->after('parent_item_id'); // Mark splits
$table->string('lifecycle_stage', 20)->default('original')->after('is_split_item'); // 'original', 'split_loaded', 'split_remainder'
```

#### Refactor Loadout Process:
1. **STOP deleting items**
2. When splitting for partial loadout:
   - Update original item: `sorting_status` = 'split', `loaded_qty` = total loaded
   - Create child "loaded" item: `parent_item_id` = original ID, `lifecycle_stage` = 'split_loaded', `sorting_status` = 'loaded'
   - Create child "remainder" item: `parent_item_id` = original ID, `lifecycle_stage` = 'split_remainder', `sorting_status` = 'allocated'
3. Preserve `original_requested_qty` and `final_approved_qty` in all records

**Pros:**
- Minimal database changes
- Retains current UI/workflow
- Adds traceability without major refactor

**Cons:**
- Still complex with split items
- Doesn't fully solve invoice traceability
- Technical debt remains

---

### Option B: Separate Stage Tables (Best Practice, Higher Effort)

Create explicit tables for each stage to capture immutable snapshots:

#### New Tables:

```php
// shop_order_approvals
Schema::create('shop_order_approvals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();
    $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
    $table->timestamp('approved_at');
    $table->text('notes')->nullable();
    $table->timestamps();
});

// shop_order_approval_items (snapshot of approved quantities)
Schema::create('shop_order_approval_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('approval_id')->constrained('shop_order_approvals')->cascadeOnDelete();
    $table->foreignId('shop_order_item_id')->constrained('shop_order_items')->cascadeOnDelete();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->decimal('approved_qty', 10, 2);
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->unique(['approval_id', 'shop_order_item_id']);
});

// shop_order_loadouts
Schema::create('shop_order_loadouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();
    $table->foreignId('loaded_by')->constrained('users')->cascadeOnDelete();
    $table->timestamp('loaded_at');
    $table->string('status', 20)->default('in_progress'); // 'in_progress', 'completed', 'dispatched'
    $table->timestamps();
});

// shop_order_loadout_items (actual loaded quantities)
Schema::create('shop_order_loadout_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('loadout_id')->constrained('shop_order_loadouts')->cascadeOnDelete();
    $table->foreignId('shop_order_item_id')->constrained('shop_order_items')->cascadeOnDelete(); // ORIGINAL item
    $table->foreignId('approval_item_id')->nullable()->constrained('shop_order_approval_items')->nullOnDelete();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->decimal('loaded_qty', 10, 2);
    $table->decimal('loaded_order_unit_qty', 10, 2)->nullable();
    $table->decimal('actual_weight', 10, 2)->nullable();
    $table->string('discrepancy_type', 50)->default('none');
    $table->text('notes')->nullable();
    $table->timestamps();
});

// shop_order_delivery_checks (shop's report)
Schema::create('shop_order_delivery_checks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();
    $table->foreignId('checked_by')->constrained('users')->cascadeOnDelete();
    $table->timestamp('checked_at');
    $table->string('status', 20)->default('pending_review'); // 'pending_review', 'approved', 'rejected'
    $table->decimal('cash_collected', 12, 2)->default(0.00);
    $table->text('notes')->nullable();
    $table->timestamps();
});

// shop_order_delivery_check_items
Schema::create('shop_order_delivery_check_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('delivery_check_id')->constrained('shop_order_delivery_checks')->cascadeOnDelete();
    $table->foreignId('loadout_item_id')->constrained('shop_order_loadout_items')->cascadeOnDelete(); // Links to loadout
    $table->foreignId('shop_order_item_id')->constrained('shop_order_items')->cascadeOnDelete(); // ORIGINAL item
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->decimal('reported_received_qty', 10, 2);
    $table->decimal('reported_missing_qty', 10, 2)->default(0.00);
    $table->decimal('reported_excess_qty', 10, 2)->default(0.00);
    $table->timestamps();
});

// shop_order_delivery_approvals (admin's final decision)
Schema::create('shop_order_delivery_approvals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('delivery_check_id')->constrained('shop_order_delivery_checks')->cascadeOnDelete();
    $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
    $table->timestamp('approved_at');
    $table->text('notes')->nullable();
    $table->timestamps();
});

// shop_order_delivery_approval_items
Schema::create('shop_order_delivery_approval_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('delivery_approval_id')->constrained('shop_order_delivery_approvals')->cascadeOnDelete();
    $table->foreignId('delivery_check_item_id')->constrained('shop_order_delivery_check_items')->cascadeOnDelete();
    $table->foreignId('shop_order_item_id')->constrained('shop_order_items')->cascadeOnDelete(); // ORIGINAL item
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->decimal('approved_delivered_qty', 10, 2);
    $table->decimal('shortage_qty', 10, 2)->default(0.00);
    $table->decimal('excess_qty', 10, 2)->default(0.00);
    $table->string('discrepancy_type', 50)->default('none');
    $table->string('inventory_action', 50)->default('none'); // 'add_back', 'deduct_extra', 'none'
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

#### Relationships:
```
ShopOrder (1) → (many) ShopOrderItem [original requests]
    ↓
ShopOrderApproval (1) → (many) ShopOrderApprovalItem [references original items]
    ↓
ShopOrderLoadout (1) → (many) ShopOrderLoadoutItem [references original items]
    ↓
ShopOrderDeliveryCheck (1) → (many) ShopOrderDeliveryCheckItem [references loadout items & original items]
    ↓
ShopOrderDeliveryApproval (1) → (many) ShopOrderDeliveryApprovalItem [references check items & original items]
```

#### Modify `shop_order_items`:
- **Remove mutable fields**: `approved_qty`, `loaded_qty`, `delivered_qty`, `shortage_qty`, `excess_qty`, etc.
- **Keep only original request data**: `requested_qty`, `unit`, `requested_unit_*`, `locked_selling_price`
- **Never delete or update these items** - they are the immutable source of truth

#### Update `shop_invoices`:
- Link to final `shop_order_delivery_approval_id`
- Invoice items reference `delivery_approval_item_id` for traceability

**Pros:**
- Clean separation of concerns
- Full audit trail at each stage
- No data loss, no ambiguity
- Easy to query "show me all approvals for this order"
- Supports multiple loadouts, multiple delivery checks, full history

**Cons:**
- Significant refactoring required
- More complex queries (more JOINs)
- Larger database size
- All controllers, services, Blade views need updates

---

### Recommendation

**For immediate fix:** Implement **Option A** (Minimal Change)
- Add `original_requested_qty`, `final_approved_qty`, `parent_item_id`, `is_split_item`, `lifecycle_stage`
- Refactor `WarehouseLoadoutController::save()` to stop deleting items
- Preserve original items when splitting

**For long-term:** Plan migration to **Option B** (Separate Stage Tables)
- Better architecture for complex business logic
- Prepare for future features (multiple loadouts, loadout revisions, delivery disputes)
- Easier to audit and debug

---

## 7. Safe Fix Plan

### Phase 1: Immediate Stabilization (1-2 days)

#### 1.1 Database Changes
```bash
php artisan make:migration add_traceability_fields_to_shop_order_items_table
```

**Migration Content:**
```php
Schema::table('shop_order_items', function (Blueprint $table) {
    $table->decimal('original_requested_qty', 10, 2)->after('requested_qty');
    $table->decimal('final_approved_qty', 10, 2)->nullable()->after('approved_qty');
    $table->foreignId('parent_item_id')->nullable()->constrained('shop_order_items')->nullOnDelete()->after('shop_order_id');
    $table->boolean('is_split_item')->default(false)->after('parent_item_id');
    $table->string('lifecycle_stage', 20)->default('original')->after('is_split_item');
    $table->index('parent_item_id');
    $table->index('lifecycle_stage');
});
```

**Backfill existing data:**
```php
// In same migration, after schema changes
DB::table('shop_order_items')->update([
    'original_requested_qty' => DB::raw('requested_qty'),
    'final_approved_qty' => DB::raw('approved_qty'),
    'lifecycle_stage' => 'original',
]);
```

**Do NOT run `migrate:fresh`** - use regular `migrate` to preserve data.

---

#### 1.2 Model Relationship Updates

**app/Models/ShopOrderItem.php:**
```php
// Add to fillable
protected $fillable = [
    // ... existing fields ...
    'original_requested_qty',
    'final_approved_qty',
    'parent_item_id',
    'is_split_item',
    'lifecycle_stage',
];

// Add casts
protected $casts = [
    // ... existing casts ...
    'original_requested_qty' => 'decimal:2',
    'final_approved_qty' => 'decimal:2',
    'is_split_item' => 'boolean',
];

// Add relationships
public function parentItem(): BelongsTo
{
    return $this->belongsTo(ShopOrderItem::class, 'parent_item_id');
}

public function splitItems(): HasMany
{
    return $this->hasMany(ShopOrderItem::class, 'parent_item_id');
}

// Add helper methods
public function isOriginalItem(): bool
{
    return $this->lifecycle_stage === 'original' && !$this->is_split_item;
}

public function isLoadedSplit(): bool
{
    return $this->lifecycle_stage === 'split_loaded';
}

public function isRemainderSplit(): bool
{
    return $this->lifecycle_stage === 'split_remainder';
}
```

---

#### 1.3 Controller Changes - Stop Deleting Items

**app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php:**

**BEFORE (line ~389):**
```php
$shopOrder->items()->where('product_id', $productId)->delete();
```

**AFTER:**
```php
// Get original item(s) for this product
$originalItems = $shopOrder->items()
    ->where('product_id', $productId)
    ->where('lifecycle_stage', 'original')
    ->lockForUpdate()
    ->get();

// Delete only split items from previous loadout edits
$shopOrder->items()
    ->where('product_id', $productId)
    ->whereIn('lifecycle_stage', ['split_loaded', 'split_remainder'])
    ->delete();

$firstOriginal = $originalItems->first();
if (!$firstOriginal) {
    continue; // Skip if no original item found (shouldn't happen)
}

// Calculate totals from all original items for this product
$totalApproved = $originalItems->sum('final_approved_qty') 
    ?: $originalItems->sum('approved_qty');
$totalOriginalRequested = $originalItems->sum('original_requested_qty') 
    ?: $originalItems->sum('requested_qty');

// Preserve original items - just update their status
foreach ($originalItems as $origItem) {
    $origItem->update([
        'original_requested_qty' => $origItem->original_requested_qty ?: $origItem->requested_qty,
        'final_approved_qty' => $origItem->final_approved_qty ?: $origItem->approved_qty,
        'sorting_status' => 'split', // Mark as split, not deleted
    ]);
}

// Now create split items as before, but link them to parent
if ($isNotAvailable) {
    ShopOrderItem::create([
        ...existing fields...,
        'parent_item_id' => $firstOriginal->id,
        'is_split_item' => true,
        'lifecycle_stage' => 'split_loaded',
        'original_requested_qty' => $totalOriginalRequested,
        'final_approved_qty' => $totalApproved,
    ]);
} else {
    if ($submittedQty > 0) {
        ShopOrderItem::create([
            ...existing fields...,
            'parent_item_id' => $firstOriginal->id,
            'is_split_item' => true,
            'lifecycle_stage' => 'split_loaded',
            'original_requested_qty' => $totalOriginalRequested, // Preserve
            'final_approved_qty' => $totalApproved, // Preserve
        ]);
    }
    if ($remaining > 0.001) {
        ShopOrderItem::create([
            ...existing fields...,
            'parent_item_id' => $firstOriginal->id,
            'is_split_item' => true,
            'lifecycle_stage' => 'split_remainder',
            'original_requested_qty' => $totalOriginalRequested, // Preserve
            'final_approved_qty' => $totalApproved, // Preserve
        ]);
    }
}
```

---

#### 1.4 Update Queries to Use Split Items

**In all controllers/services that query items for display:**
```php
// OLD:
$order->items

// NEW for warehouse/delivery operations (show splits):
$order->items()->whereIn('lifecycle_stage', ['original', 'split_loaded', 'split_remainder'])->get()

// NEW for reporting original requests:
$order->items()->where('lifecycle_stage', 'original')->get()

// NEW for loaded items only:
$order->items()->where('lifecycle_stage', 'split_loaded')->where('sorting_status', 'loaded')->get()
```

---

### Phase 2: Validation & Testing (2-3 days)

#### 2.1 Add Validation Rules

**Create validation service:**
```php
// app/Services/ShopOrders/LoadoutValidationService.php
class LoadoutValidationService
{
    public function validateSplitQuantities(ShopOrder $order, int $productId, float $loadedQty, float $remainingQty): void
    {
        $originalItems = $order->items()
            ->where('product_id', $productId)
            ->where('lifecycle_stage', 'original')
            ->get();
        
        $totalApproved = $originalItems->sum(fn($item) => (float) ($item->final_approved_qty ?? $item->approved_qty));
        $totalSplit = $loadedQty + $remainingQty;
        
        // Allow 0.01 tolerance for rounding
        if (abs($totalSplit - $totalApproved) > 0.01) {
            throw ValidationException::withMessages([
                'quantities' => "Split quantities ({$totalSplit}) do not match approved quantity ({$totalApproved})"
            ]);
        }
    }
}
```

Use in loadout controller before creating split items.

---

#### 2.2 Unit Tests

**tests/Feature/ShopOrder/LoadoutSplitTest.php:**
```php
public function test_loadout_split_preserves_original_item()
{
    $order = ShopOrder::factory()->create(['state' => 'approved']);
    $item = ShopOrderItem::factory()->create([
        'shop_order_id' => $order->id,
        'requested_qty' => 100.0,
        'approved_qty' => 100.0,
        'original_requested_qty' => 100.0,
        'final_approved_qty' => 100.0,
    ]);
    
    // Simulate partial loadout
    $this->post(route('warehouse.loadout.save', $order), [
        'items' => [$item->product_id => 80],
    ]);
    
    // Original item should still exist
    $this->assertDatabaseHas('shop_order_items', [
        'id' => $item->id,
        'lifecycle_stage' => 'original',
        'original_requested_qty' => 100.0,
    ]);
    
    // Split items should be created
    $this->assertDatabaseHas('shop_order_items', [
        'parent_item_id' => $item->id,
        'lifecycle_stage' => 'split_loaded',
        'loaded_qty' => 80.0,
    ]);
    
    $this->assertDatabaseHas('shop_order_items', [
        'parent_item_id' => $item->id,
        'lifecycle_stage' => 'split_remainder',
        'approved_qty' => 20.0,
    ]);
}
```

---

### Phase 3: Data Migration (If needed)

**For existing production data:**
```php
// app/Console/Commands/BackfillShopOrderItemHistory.php
class BackfillShopOrderItemHistory extends Command
{
    protected $signature = 'shop-orders:backfill-history';
    
    public function handle()
    {
        // This is LOSSY - we cannot recover deleted items
        // But we can mark existing items correctly
        
        ShopOrderItem::whereNull('lifecycle_stage')->update(['lifecycle_stage' => 'original']);
        ShopOrderItem::whereNull('original_requested_qty')->update([
            'original_requested_qty' => DB::raw('requested_qty')
        ]);
        ShopOrderItem::whereNull('final_approved_qty')->update([
            'final_approved_qty' => DB::raw('approved_qty')
        ]);
        
        $this->info('Backfilled shop order item history fields.');
        $this->warn('Note: Previously deleted items cannot be recovered.');
    }
}
```

**Run after deployment:**
```bash
php artisan shop-orders:backfill-history
```

---

### Phase 4: Blade View Updates (2-3 days)

Update all Blade views that display items:

**resources/views/warehouse/loadout/show.blade.php:**
```blade
{{-- Show original request --}}
<div class="original-request">
    <strong>Original Request:</strong> {{ $item->original_requested_qty }} {{ $item->unit }}
</div>

{{-- Show approved quantity --}}
<div class="approved-qty">
    <strong>Approved:</strong> {{ $item->final_approved_qty ?? $item->approved_qty }} {{ $item->unit }}
</div>

{{-- Show loaded quantity --}}
@if($item->lifecycle_stage === 'split_loaded')
    <div class="loaded-qty">
        <strong>Loaded:</strong> {{ $item->loaded_qty }} {{ $item->unit }}
    </div>
@endif

{{-- Show if this is a split item --}}
@if($item->is_split_item)
    <span class="badge badge-info">Split Item</span>
    @if($item->parentItem)
        <small>Parent: #{{ $item->parent_item_id }}</small>
    @endif
@endif
```

---

### Phase 5: Invoice Traceability Fix (3-4 days)

**Option 1: Link invoice items to original items via parent chain:**

**app/Services/ShopInvoices/ShopInvoiceService.php:**
```php
private function resolveOriginalItemId(ShopOrderItem $item): int
{
    // Traverse parent chain to find original item
    $current = $item;
    while ($current->parent_item_id) {
        $current = $current->parentItem;
        if (!$current) break;
    }
    return $current->id;
}

// When creating invoice items:
$invoiceItem->update([
    'shop_order_item_id' => $this->resolveOriginalItemId($shopOrderItem),
]);
```

**Option 2: Store parent chain in invoice:**
```php
// Add column to shop_invoice_items
Schema::table('shop_invoice_items', function (Blueprint $table) {
    $table->json('source_item_ids')->nullable()->after('shop_order_item_id');
});

// Store all related item IDs (original + splits)
$sourceItemIds = $order->items()
    ->where('product_id', $productId)
    ->whereIn('lifecycle_stage', ['original', 'split_loaded'])
    ->pluck('id')
    ->toArray();

$invoiceItem->update([
    'source_item_ids' => $sourceItemIds,
    'shop_order_item_id' => $originalItemId, // For backward compatibility
]);
```

---

### Phase 6: Testing & Deployment (2-3 days)

#### 6.1 Test Scenarios

**Test each flow end-to-end:**
1. **Full loadout (no split):**
   - Create order, approve, load 100% of approved qty
   - Verify original item preserved
   - Verify invoice links correctly

2. **Partial loadout:**
   - Create order, approve, load 80% of approved qty
   - Verify 3 items exist: 1 original + 1 loaded + 1 remainder
   - Move loaded item to delivery
   - Shop reports delivery
   - Admin approves
   - Verify invoice totals correct

3. **Multiple loadout edits:**
   - Create order, approve
   - Load 80%, save
   - Edit to 90%, save
   - Verify original item still has original quantities
   - Verify only latest splits exist

4. **Not available item:**
   - Create order, approve
   - Mark product as not available in loadout
   - Verify original item preserved
   - Verify not_available item created

5. **Mixed product loadout:**
   - Order with 5 products
   - Load 2 fully, 2 partially, 1 not available
   - Verify all originals preserved
   - Verify correct split items created

---

#### 6.2 Deployment Checklist

**Pre-deployment:**
- [ ] Code reviewed by 2+ developers
- [ ] All unit tests passing
- [ ] Manual testing completed on staging
- [ ] Database backup taken
- [ ] Rollback plan documented

**Deployment steps:**
```bash
# 1. Enable maintenance mode
php artisan down --message="Upgrading shop order system" --retry=60

# 2. Pull latest code
git pull origin main

# 3. Run migrations (no fresh!)
php artisan migrate --force

# 4. Backfill history
php artisan shop-orders:backfill-history

# 5. Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart queues
php artisan queue:restart

# 7. Disable maintenance mode
php artisan up
```

**Post-deployment:**
- [ ] Smoke test: Create new shop order
- [ ] Smoke test: Approve order
- [ ] Smoke test: Load order (partial)
- [ ] Smoke test: Complete delivery
- [ ] Monitor error logs for 24 hours
- [ ] Verify no data loss in existing orders

**Rollback procedure (if issues found):**
```bash
# 1. Enable maintenance mode
php artisan down

# 2. Revert code
git revert <commit-hash>
git push origin main

# 3. Rollback migrations (only if new columns cause issues)
php artisan migrate:rollback --step=1

# 4. Clear caches
php artisan config:cache
php artisan route:cache

# 5. Restart queues
php artisan queue:restart

# 6. Disable maintenance mode
php artisan up
```

---

### Phase 7: Monitoring & Validation (Ongoing)

**Create monitoring queries:**
```php
// app/Console/Commands/ValidateShopOrderIntegrity.php
class ValidateShopOrderIntegrity extends Command
{
    protected $signature = 'shop-orders:validate-integrity';
    
    public function handle()
    {
        // Check 1: Orphaned split items
        $orphans = ShopOrderItem::query()
            ->where('is_split_item', true)
            ->whereNotNull('parent_item_id')
            ->whereDoesntHave('parentItem')
            ->count();
        
        if ($orphans > 0) {
            $this->error("Found {$orphans} orphaned split items with missing parents!");
        }
        
        // Check 2: Items without original quantities
        $missingOriginal = ShopOrderItem::query()
            ->where('lifecycle_stage', 'original')
            ->whereNull('original_requested_qty')
            ->count();
        
        if ($missingOriginal > 0) {
            $this->error("Found {$missingOriginal} items missing original_requested_qty!");
        }
        
        // Check 3: Split quantity validation
        $invalidSplits = DB::select("
            SELECT parent_item_id, SUM(approved_qty) as split_total, 
                   parent.final_approved_qty as original_approved
            FROM shop_order_items items
            JOIN shop_order_items parent ON items.parent_item_id = parent.id
            WHERE items.lifecycle_stage IN ('split_loaded', 'split_remainder')
            GROUP BY parent_item_id, parent.final_approved_qty
            HAVING ABS(split_total - original_approved) > 0.01
        ");
        
        if (count($invalidSplits) > 0) {
            $this->error("Found " . count($invalidSplits) . " items with split quantity mismatches!");
            foreach ($invalidSplits as $split) {
                $this->warn("Parent item {$split->parent_item_id}: splits total {$split->split_total} but approved {$split->original_approved}");
            }
        }
        
        if ($orphans === 0 && $missingOriginal === 0 && count($invalidSplits) === 0) {
            $this->info('✓ All shop order items passed integrity checks');
        }
        
        return ($orphans + $missingOriginal + count($invalidSplits)) === 0 ? 0 : 1;
    }
}
```

**Schedule daily integrity check:**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('shop-orders:validate-integrity')
        ->daily()
        ->emailOutputOnFailure('tech-team@greenleaf.com');
}
```

---

## 8. Summary of Key Files

| File Path | Purpose | Issues Found |
|-----------|---------|--------------|
| [database/migrations/2026_05_25_173722_create_shops_and_shop_orders_tables.php](database/migrations/2026_05_25_173722_create_shops_and_shop_orders_tables.php) | Base tables for shop orders | No issues |
| [database/migrations/2026_06_25_175431_add_discrepancy_and_receiving_fields_to_tables.php](database/migrations/2026_06_25_175431_add_discrepancy_and_receiving_fields_to_tables.php) | Added loadout tracking | Good addition |
| [database/migrations/2026_07_10_115308_add_delivery_review_fields_to_shop_orders_and_items_table.php](database/migrations/2026_07_10_115308_add_delivery_review_fields_to_shop_orders_and_items_table.php) | Added shop delivery check fields | Good addition |
| [database/migrations/2026_08_02_140000_add_loaded_order_unit_qty_to_shop_order_items_table.php](database/migrations/2026_08_02_140000_add_loaded_order_unit_qty_to_shop_order_items_table.php) | Unit conversion support | Suggests confusion around units |
| [database/migrations/2026_08_02_150000_add_actual_weight_to_shop_order_items_table.php](database/migrations/2026_08_02_150000_add_actual_weight_to_shop_order_items_table.php) | Actual weight tracking | Recent addition, may not be used consistently |
| [database/migrations/2026_08_03_000000_add_soft_deletes_to_shop_order_items_table.php](database/migrations/2026_08_03_000000_add_soft_deletes_to_shop_order_items_table.php) | Soft deletes | **Added today - too late to save historical data** |
| [app/Models/ShopOrder.php](app/Models/ShopOrder.php) | Shop order model | Multiple status fields, complex locking logic |
| [app/Models/ShopOrderItem.php](app/Models/ShopOrderItem.php) | Shop order item model | No parent-child relationships, quantities overwritten |
| [app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php](app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php) | Warehouse loadout logic | **CRITICAL: Line 389 deletes items** |
| [app/Http/Controllers/Web/RequisitionController.php](app/Http/Controllers/Web/RequisitionController.php) | Order creation & delivery | No major issues, but complex |
| [app/Domains/ShopOrder/Actions/ResolveDeliveryReviewAction.php](app/Domains/ShopOrder/Actions/ResolveDeliveryReviewAction.php) | Delivery review workflow | No major issues |
| [app/Services/ShopInvoices/ShopInvoiceService.php](app/Services/ShopInvoices/ShopInvoiceService.php) | Invoice generation | Invoice items group by product, lose item detail |

---

## 9. Risk Assessment

| Issue | Severity | Impact | Likelihood | Priority |
|-------|----------|--------|------------|----------|
| Item deletion during loadout | **Critical** | Data loss | High | **P0 - Fix immediately** |
| No traceability to original request | **High** | Audit failures, disputes | High | **P0 - Fix immediately** |
| Quantity overwriting | **High** | Reconciliation errors | Medium | **P1 - Fix in Phase 1** |
| Multiple loadout edits without history | **Medium** | Operational confusion | Medium | **P1 - Fix in Phase 1** |
| Invoice item aggregation | **Medium** | Billing disputes | Low | **P2 - Fix in Phase 2** |
| Actual weight vs. unit confusion | **Medium** | Billing accuracy | Medium | **P2 - Document policy** |
| Multiple status fields | **Low** | Developer confusion | Low | **P3 - Refactor eventually** |
| No validation of split quantities | **Low** | Rounding errors | Low | **P2 - Add validation** |

---

## 10. Conclusion

The current Green Leaf shop order system uses a **single-table, status-driven architecture** that works for simple flows but has critical data integrity issues:

1. **Items are deleted and recreated** during loadout, breaking traceability
2. **Quantities overwrite each other** at different stages with no immutable history
3. **No clear parent-child relationships** for split items
4. **Invoice items cannot reliably trace back** to original shop requests

**Immediate Action Required:**
- Stop deleting items in loadout process
- Add traceability fields (parent_item_id, original_requested_qty, etc.)
- Implement soft deletes properly (already added 2026-08-03, but needs enforcement)

**Long-term Recommendation:**
- Migrate to separate stage tables (approvals, loadouts, delivery checks) for clean architecture
- Implement proper audit trail at each stage
- Add data integrity validation and monitoring

**No Code Changes Made:** As requested, this is a review only. All recommended changes are documented above for your team to implement.

---

**End of Audit Report**
