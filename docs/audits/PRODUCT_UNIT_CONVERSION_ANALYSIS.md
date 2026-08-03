# Product Unit Conversion Issue - Root Cause Analysis

**Date:** 2026-08-03  
**Issue:** "Fuji Apple cannot be invoiced in kg. Add a valid conversion in inventory units."  
**URL:** https://glfresh.com/purchasing/prices?date=2026-08-03

---

## 🔴 ROOT CAUSE

The seeder file has **43 products** with base units marked as `is_orderable = 0`, which prevents the invoicing system from finding valid unit conversions.

### Example: Fuji Apple (Product ID: 167)

**Seeder Configuration (Line 338):**
```
Fuji Apple|kg|kg:KG:1:1:0:0;box:BOX 10 KG:10:0:0:1
```

**Format:** `unit:label:conversion_to_base:is_base:is_orderable:sort_order`

**Current Database State:**
| Unit | Conversion | is_base | is_orderable | Problem |
|------|-----------|---------|--------------|---------|
| kg   | 1.0000    | 1       | **0** ❌     | Base unit not orderable! |
| box  | 10.0000   | 0       | **0** ❌     | Secondary unit not orderable! |

---

## 📍 WHERE `conversion_to_base` APPLIES

### 1. **Invoice Calculation** (Primary Issue Location)
**File:** [app/Services/ShopInvoices/ShopInvoiceService.php](app/Services/ShopInvoices/ShopInvoiceService.php#L1108-L1148)

```php
private function priceQuantityFor(?Product $product, float $baseQuantity, 
    string $priceUnit, ?Collection $orderItems = null): float
{
    // Step 1: Try to get conversion from order items
    $conversionToBase = $orderItems
        ->first(fn (ShopOrderItem $item) => 
            ProductUnit::normalizeUnit($item->requested_unit) === $normalizedPriceUnit
            && (float) ($item->requested_unit_conversion_to_base ?? 0) > 0
        )
        ?->requested_unit_conversion_to_base;

    // Step 2: Fallback to product->orderUnits
    if ($conversionToBase === null || (float) $conversionToBase <= 0) {
        $product->loadMissing('orderUnits');
        $conversionToBase = $product->orderUnits
            ->first(fn (ProductUnit $unit) => 
                ProductUnit::normalizeUnit($unit->unit) === $normalizedPriceUnit
            )
            ?->conversion_to_base;  // ← Looks for conversion here
    }

    // Step 3: If still no valid conversion → ERROR
    if ($conversionToBase === null || (float) $conversionToBase <= 0) {
        throw ValidationException::withMessages([
            'prices' => "{$product->name} cannot be invoiced in {$priceUnit}. 
                        Add a valid conversion in inventory units.",
        ]);
    }

    return round($baseQuantity / (float) $conversionToBase, 4);
}
```

**Issue:** The code finds the kg unit with conversion_to_base = 1.0, but other parts of the system expect orderable units.

### 2. **Shop Order Item Sync**
**File:** [app/Services/Requisition/ShopOrderItemSyncService.php](app/Services/Requisition/ShopOrderItemSyncService.php#L85-L98)

```php
$conversionToBase = $selectedMeasure
    ? ($selectedMeasure->conversion_to_base !== null 
        ? (float) $selectedMeasure->conversion_to_base 
        : null)
    : null;

// Stores in requested_unit_conversion_to_base
'requested_unit_conversion_to_base' => $conversionToBase,
```

**Uses:** Orderable units filtered by `is_orderable = true` (line 190)

### 3. **Warehouse Loadout**
**File:** [app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php](app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php#L159)

```php
'requested_unit_conversion_to_base' => (float) ($firstItem->requested_unit_conversion_to_base ?? 1.0)
```

### 4. **Product Management**
**File:** [app/Models/Product.php](app/Models/Product.php#L206)

```php
public function conversionToBase(string $unit): ?float
{
    $matchedUnit = $this->units->first(fn ($u) => 
        ProductUnit::normalizeUnit($u->unit) === ProductUnit::normalizeUnit($unit)
    );
    return $matchedUnit 
        ? ($matchedUnit->conversion_to_base !== null 
            ? (float) $matchedUnit->conversion_to_base 
            : null) 
        : 1.0;
}
```

### 5. **Quantity Corrections**
**File:** [app/Http/Controllers/Web/Inventory/ShopOrderQuantityCorrectionController.php](app/Http/Controllers/Web/Inventory/ShopOrderQuantityCorrectionController.php#L67)

```php
$conv = (float) ($item->requested_unit_conversion_to_base ?? 1.0);
```

### 6. **Frontend Order Creation**
**File:** [resources/js/shop-owner/orders/create-order.js](resources/js/shop-owner/orders/create-order.js#L111)

```javascript
const conversion = Number.parseFloat(
    String(unitDefinition?.conversion_to_base ?? '1')
);
```

---

## 🔧 PRODUCTION FIX (IMMEDIATE)

### Step 1: Update Affected Products

```sql
-- Fix all base units that are not orderable
UPDATE product_units 
SET is_orderable = 1 
WHERE is_base = 1 
  AND is_orderable = 0;

-- Affected: 43 products
```

### Step 2: Verify Fuji Apple Fix

```sql
SELECT 
    p.id, 
    p.name, 
    pu.unit, 
    pu.conversion_to_base, 
    pu.is_base, 
    pu.is_orderable 
FROM products p
JOIN product_units pu ON p.id = pu.product_id
WHERE p.name = 'Fuji Apple'
ORDER BY pu.sort_order;
```

**Expected Result:**
| id  | name       | unit | conversion_to_base | is_base | is_orderable |
|-----|------------|------|-------------------|---------|--------------|
| 167 | Fuji Apple | kg   | 1.0000            | 1       | **1** ✅     |
| 167 | Fuji Apple | box  | 10.0000           | 0       | 0            |

### Step 3: Test Invoice Generation

Navigate to: https://glfresh.com/purchasing/prices?date=2026-08-03

The "cannot be invoiced in kg" error should now be resolved.

---

## 🔧 SEEDER FIX (FUTURE DEPLOYMENTS)

### Update ProductSeeder.php

**File:** [database/seeders_backup/ProductSeeder.php](database/seeders_backup/ProductSeeder.php#L337-L345)

**Change all base kg units from `0` to `1` for is_orderable:**

```diff
- Irani Apple|kg|kg:KG:1:1:0:0;box:BOX 10 KG:10:0:0:1
+ Irani Apple|kg|kg:KG:1:1:1:0;box:BOX 10 KG:10:0:0:1

- Fuji Apple|kg|kg:KG:1:1:0:0;box:BOX 10 KG:10:0:0:1
+ Fuji Apple|kg|kg:KG:1:1:1:0;box:BOX 10 KG:10:0:0:1

- Green Apple|kg|kg:KG:1:1:0:0;box:BOX 18 KG:18:0:0:1
+ Green Apple|kg|kg:KG:1:1:1:0;box:BOX 18 KG:18:0:0:1

- Apple Pink lady|kg|kg:KG:1:1:0:0;box:BOX 18 KG:18:0:0:1
+ Apple Pink lady|kg|kg:KG:1:1:1:0;box:BOX 18 KG:18:0:0:1

- Red Apple|kg|kg:KG:1:1:0:0;box:BOX 14 KG:14:0:1:1;box:BOX 18 KG:18:0:1:2
+ Red Apple|kg|kg:KG:1:1:1:0;box:BOX 14 KG:14:0:1:1;box:BOX 18 KG:18:0:1:2

- Indian Apple|kg|kg:KG:1:1:0:0;box:BOX 20 KG:20:0:0:1
+ Indian Apple|kg|kg:KG:1:1:1:0;box:BOX 20 KG:20:0:0:1

- Washington Apple|kg|kg:KG:1:1:0:0;box:BOX 11 KG:11:0:1:1
+ Washington Apple|kg|kg:KG:1:1:1:0;box:BOX 11 KG:11:0:1:1

- Pineapple|kg|kg:KG:1:1:0:0;piece:PIECE:1:0:0:2
+ Pineapple|kg|kg:KG:1:1:1:0;piece:PIECE:1:0:0:2
```

**Pattern:** Change `kg:KG:1:1:0:0` → `kg:KG:1:1:1:0`  
(5th field: is_orderable from 0 to 1)

---

## 📊 AFFECTED PRODUCTS (43 Total)

All products with base units marked as non-orderable need the fix.

**Query to find all affected products:**
```sql
SELECT 
    p.id,
    p.name,
    pu.unit,
    pu.conversion_to_base,
    pu.is_base,
    pu.is_orderable
FROM products p
JOIN product_units pu ON p.id = pu.product_id
WHERE pu.is_base = 1 
  AND pu.is_orderable = 0
ORDER BY p.name;
```

---

## ✅ VALIDATION CHECKLIST

After applying the production fix:

- [ ] Run SQL update to set `is_orderable = 1` for base units
- [ ] Verify Fuji Apple shows both units as orderable
- [ ] Test invoice generation at /purchasing/prices
- [ ] Test shop order creation with kg unit
- [ ] Test warehouse loadout with kg unit
- [ ] Update seeder file for future deployments
- [ ] Clear any cached product data

---

## 🎯 SUMMARY

**Problem:** Base units (kg) marked as `is_orderable = 0` in seeder  
**Impact:** 43 products cannot be invoiced or ordered properly  
**Solution:** Update `is_orderable = 1` for all base units  
**Timeline:** Immediate SQL update in production + seeder fix for future

**Root Cause Location:**  
[database/seeders_backup/ProductSeeder.php](database/seeders_backup/ProductSeeder.php#L337-L345) - Lines with `kg:KG:1:1:0:0` pattern
