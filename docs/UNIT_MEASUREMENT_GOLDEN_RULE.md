# Unit Measurement System - Golden Rule

## Overview

This document describes the **Golden Rule** for unit measurement across the Green Leaf ERP system. This approach follows professional ERP best practices where inventory is always consistent in one base unit, while purchasing, packing, and billing remain flexible.

## The Golden Rule

### Module-wise Unit Strategy

| Module | Unit Type | Description | Example |
|--------|-----------|-------------|---------|
| **1. Purchase Order (PO)** | Supplier Unit | What you ordered from supplier | 10 BOX Tomato |
| **2. Purchase / GRN** | Supplier Unit + Base Unit | What you actually received | 10 BOX = 198 KG |
| **3. Inventory** | Base Unit Only | Current stock | 198 KG |
| **4. Loadout (Warehouse)** | Display Unit + Base Unit | What was packed for the shop | 20 BUNCH = 5.4 KG |
| **5. Delivery** | Same as Loadout | What the shop actually received | 20 BUNCH = 5.4 KG |
| **6. Sales Invoice** | Billing Unit | What the customer is billed for | 20 BUNCH × ₹15 OR 5.4 KG × ₹100 |

### Complete Flow Example

#### Purchasing Flow
```
Supplier sells:
    10 BOX Tomato

Purchase receives (GRN):
    10 BOX
    Actual Weight = 198 KG

Inventory becomes:
    +198 KG (base unit only)
```

#### Sales Flow
```
Shop orders:
    20 BUNCH Coriander

Warehouse loadout:
    20 BUNCH (display unit for warehouse staff)
    Actual Weight = 5.4 KG (measured at warehouse)

Inventory reduces:
    -5.4 KG (base unit only)

Invoice generated (flexible billing):
    Option A: 20 BUNCH × ₹15 = ₹300
    Option B: 5.4 KG × ₹100 = ₹540
    (depends on customer's pricing agreement)
```

## Implementation Details

### Core Principles

1. **Single Source of Truth**: All quantities are stored in base units (KG or PCS)
2. **Display Units**: Calculated on-the-fly from base units using conversion factors
3. **Historical Accuracy**: Conversion factors are snapshotted at transaction time
4. **Flexible Billing**: Invoice unit is independent of order/inventory units

### Database Schema

#### Purchase Order Items
```php
purchase_order_items:
    - purchase_unit (string)         // "box", "bag", "kg"
    - packet_qty (decimal)           // 10 boxes
    - weight_per_packet (decimal)    // 19.8 kg per box
    - actual_weight (decimal)        // 198 kg (if measured)
    - quantity (decimal)             // 198 kg (ALWAYS base unit)
    - unit_price (decimal)           // Price per unit/kg
    - price_basis (string)           // "per_unit" or "per_kg"
```

#### Goods Received Items
```php
goods_received_items:
    - received_unit (string)                  // "box", "bag", "kg"
    - received_packet_qty (decimal)           // 10 boxes
    - received_weight_per_packet (decimal)    // 19.8 kg per box
    - received_qty (decimal)                  // 198 kg (ALWAYS base unit)
    - variance (decimal)                      // Difference from PO
```

#### Stock Batches (Inventory)
```php
stock_batches:
    - total_kg (decimal)             // 198 kg (ALWAYS base unit)
    - cost_per_kg (decimal)          // Cost per base unit
```

#### Stock Movements (Inventory)
```php
stock_movements:
    - quantity (decimal)             // ALWAYS in base unit (kg)
    - type (enum)                    // "in", "out", "transfer"
```

#### Shop Order Items
```php
shop_order_items:
    - unit (string)                              // Base unit: "kg"
    - requested_product_unit_id (FK)             // Link to product_units
    - requested_unit_conversion_to_base (decimal) // Snapshot: 1.8
    - requested_qty (decimal)                    // 36 kg (ALWAYS base)
    - approved_qty (decimal)                     // 36 kg (ALWAYS base)
    - loaded_qty (decimal)                       // 35.4 kg (ALWAYS base)
    - actual_weight (decimal)                    // 35.4 kg (weighed at warehouse)
    - delivered_qty (decimal)                    // 35.4 kg (ALWAYS base)
```

#### Shop Invoice Items
```php
shop_invoice_items:
    - unit (string)                      // Base unit: "kg"
    - price_unit (string)                // Billing unit: "kg" or "bunch"
    - approved_qty (decimal)             // 36 kg (base)
    - price_quantity (decimal)           // 20 bunches (billing)
    - delivered_qty (decimal)            // 35.4 kg (base)
    - delivered_price_quantity (decimal) // 19.67 bunches (billing)
    - unit_price (decimal)               // ₹15 per bunch
```

### Model Helper Methods

#### ShopOrderItem
```php
// Calculate display quantities on-the-fly
public function requestedQuantityInOrderUnit(): ?float
public function approvedQuantityInOrderUnit(): ?float
public function loadedQuantityInOrderUnit(): ?float
public function orderUnitLabel(): string
public function effectiveBaseQuantity(): float
```

#### PurchaseOrderItem
```php
// Calculate effective weights and costs
public function getEffectiveWeightAttribute(): float
public function getPricedUnitCountAttribute(): float
public function costPerKgForReceivedQuantity(float $receivedQuantity): float
```

#### ShopInvoiceItem
```php
// Flexible billing unit methods
public function getApprovedBaseQuantity(): float
public function getDeliveredBaseQuantity(): float
public function getBillingQuantity(): float
public function getBillingUnit(): string
```

### Product Units Configuration

```php
products:
    - unit (string)             // Base unit: "kg"

product_units:
    - product_id (FK)
    - unit (string)             // "bunch", "box", "crate"
    - label (string)            // "BUNCH", "BOX", "CRATE"
    - conversion_to_base (decimal) // 1.8 kg per bunch
    - is_base (boolean)         // false
    - is_orderable (boolean)    // true
    - sort_order (integer)      // Display order
```

## Benefits of This Approach

### 1. **Inventory Consistency**
- Inventory is ALWAYS in base units
- No confusion about "how much do we have"
- Stock reports are straightforward

### 2. **Purchasing Flexibility**
- Can order in supplier's preferred units
- Tracks both packets and actual weight
- Accurate cost calculation

### 3. **Warehouse Efficiency**
- Warehouse staff see familiar units (BUNCH, BOX)
- Can weigh items for accuracy
- Reduces picking errors

### 4. **Billing Flexibility**
- Can bill in customer's preferred unit
- Supports different price agreements
- Price can be per KG or per unit

### 5. **Audit Trail**
- Conversion factors are snapshotted
- Historical accuracy maintained
- Can recalculate any past transaction

### 6. **Simplified Logic**
- No synchronization issues
- Derived values calculated on demand
- Fewer database columns

## Migration Strategy

### Phase 1: Add Helper Methods ✅ (Completed)
- Added calculation methods to all models
- No database changes
- Backwards compatible

### Phase 2: Update Views (In Progress)
- Replace direct column access with helper methods
- Example: `$item->requested_unit_quantity` → `$item->requestedQuantityInOrderUnit()`

### Phase 3: Update Controllers
- Stop writing calculated values to database
- Let helpers compute on-the-fly
- Maintain conversion factors only

### Phase 4: Add Tests
- Unit tests for all helper methods
- Integration tests for complete flows
- Verify calculations match stored values

### Phase 5: Remove Redundant Columns (Future)
- Remove `requested_unit_quantity` (calculated)
- Remove `loaded_order_unit_qty` (calculated)
- Remove `requested_unit` (use FK)
- Remove `requested_unit_label` (use FK)

## Code Examples

### Creating a Shop Order
```php
// Shop wants to order 20 bunches of coriander
$productUnit = ProductUnit::where('unit', 'bunch')->first();
$conversionToBase = $productUnit->conversion_to_base; // 1.8 kg

ShopOrderItem::create([
    'product_id' => $product->id,
    'unit' => 'kg',  // Base unit
    'requested_product_unit_id' => $productUnit->id,
    'requested_unit_conversion_to_base' => $conversionToBase,
    'requested_qty' => 20 * $conversionToBase,  // 36 kg (base)
    'approved_qty' => 20 * $conversionToBase,   // 36 kg (base)
]);

// Display: "20 BUNCH (36 KG)"
echo $item->requestedQuantityInOrderUnit(); // 20
echo $item->orderUnitLabel(); // "BUNCH"
echo $item->requested_qty; // 36
```

### Warehouse Loadout
```php
// Warehouse loads 20 bunches, but weighs them for accuracy
$actualWeight = 35.4; // kg (measured)

$item->update([
    'loaded_qty' => $actualWeight,  // 35.4 kg (base)
    'actual_weight' => $actualWeight,  // 35.4 kg (override)
    'sorting_status' => 'loaded',
]);

// Display: "20 BUNCH (35.4 KG actual)"
echo $item->loadedQuantityInOrderUnit(); // 19.67 bunches
echo $item->effectiveBaseQuantity(); // 35.4 kg
```

### Invoice Generation
```php
// Can bill in either KG or BUNCH
$dailyPrice = [
    'price' => 15.00,
    'price_unit' => 'bunch',  // Billing unit
];

// Invoice calculates billing quantity
$priceQuantity = $baseQuantity / $conversionToBase; // 35.4 / 1.8 = 19.67

ShopInvoiceItem::create([
    'unit' => 'kg',  // Base unit
    'price_unit' => 'bunch',  // Billing unit
    'approved_qty' => 36.0,  // Base (kg)
    'price_quantity' => 20.0,  // Billing (bunches)
    'delivered_qty' => 35.4,  // Base (kg)
    'delivered_price_quantity' => 19.67,  // Billing (bunches)
    'unit_price' => 15.00,  // Per bunch
    'final_line_total' => 19.67 * 15.00,  // ₹295.05
]);
```

## Best Practices

### DO ✅
- Always store quantities in base units
- Calculate display units on-the-fly
- Snapshot conversion factors at transaction time
- Use helper methods for unit conversions
- Document which unit system each column uses

### DON'T ❌
- Store the same quantity in multiple units
- Update display unit columns manually
- Assume conversions never change
- Mix unit systems in calculations
- Rely on product_units for historical data

## Related Files

### Models
- `app/Models/Product.php`
- `app/Models/ProductUnit.php`
- `app/Models/PurchaseOrderItem.php`
- `app/Models/GoodsReceivedItem.php`
- `app/Models/StockBatch.php`
- `app/Models/StockMovement.php`
- `app/Models/ShopOrderItem.php`
- `app/Models/ShopInvoiceItem.php`

### Services
- `app/Services/ShopInvoices/ShopInvoiceService.php`
- `app/Services/Inventory/StockLedgerService.php`

### Migrations
- `database/migrations/2026_07_27_230750_add_public_uuid_units_and_order_unit_fields_to_products.php`
- `database/migrations/2026_07_28_010000_support_multiple_product_unit_measures.php`
- `database/migrations/2026_08_02_120000_add_price_units_to_daily_price_approvals_and_shop_invoice_items.php`

## Conclusion

This Golden Rule approach ensures:
- **Consistency**: Inventory is always accurate
- **Flexibility**: Can handle any unit for buying/selling
- **Simplicity**: One source of truth for quantities
- **Scalability**: Easy to add new units
- **Auditability**: Full history of transactions

This is the standard approach used in professional ERP systems like SAP, Oracle, and NetSuite.
