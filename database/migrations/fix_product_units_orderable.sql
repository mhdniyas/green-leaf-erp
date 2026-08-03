-- ============================================================================
-- PRODUCTION FIX: Product Unit is_orderable Flag
-- ============================================================================
-- Issue: 43 products have base units marked as is_orderable = 0
-- Impact: Cannot invoice or order these products (e.g., "Fuji Apple cannot be invoiced in kg")
-- URL: https://glfresh.com/purchasing/prices?date=2026-08-03
-- Date: 2026-08-03
-- ============================================================================

-- Step 1: Backup current state (optional but recommended)
CREATE TABLE IF NOT EXISTS product_units_backup_20260803 AS 
SELECT * FROM product_units;

-- Step 2: Show affected products BEFORE fix
SELECT 
    'BEFORE FIX - Affected Products:' as status,
    COUNT(DISTINCT p.id) as total_products,
    COUNT(*) as total_units
FROM products p
JOIN product_units pu ON p.id = pu.product_id
WHERE pu.is_base = 1 
  AND pu.is_orderable = 0;

-- Step 3: Show detailed list of affected products
SELECT 
    p.id,
    p.name,
    pu.unit,
    pu.conversion_to_base,
    pu.is_base,
    pu.is_orderable as current_is_orderable,
    'Will be set to 1' as action
FROM products p
JOIN product_units pu ON p.id = pu.product_id
WHERE pu.is_base = 1 
  AND pu.is_orderable = 0
ORDER BY p.name
LIMIT 20;

-- Step 4: Apply the fix
-- Set all base units to be orderable
UPDATE product_units 
SET 
    is_orderable = 1,
    updated_at = NOW()
WHERE is_base = 1 
  AND is_orderable = 0;

-- Step 5: Verify the fix
SELECT 
    'AFTER FIX - Remaining Issues:' as status,
    COUNT(DISTINCT p.id) as products_still_affected,
    COUNT(*) as units_still_affected
FROM products p
JOIN product_units pu ON p.id = pu.product_id
WHERE pu.is_base = 1 
  AND pu.is_orderable = 0;

-- Step 6: Verify Fuji Apple specifically
SELECT 
    'FUJI APPLE VERIFICATION:' as status,
    p.id,
    p.name,
    pu.unit,
    pu.label,
    pu.conversion_to_base,
    pu.is_base,
    pu.is_orderable
FROM products p
JOIN product_units pu ON p.id = pu.product_id
WHERE p.name = 'Fuji Apple'
ORDER BY pu.sort_order;

-- Step 7: Show all Apple products status
SELECT 
    'ALL APPLE PRODUCTS STATUS:' as status,
    p.id,
    p.name,
    pu.unit,
    pu.is_orderable
FROM products p
JOIN product_units pu ON p.id = pu.product_id
WHERE p.name LIKE '%Apple%'
ORDER BY p.name, pu.unit;

-- ============================================================================
-- ROLLBACK (if needed)
-- ============================================================================
-- Uncomment and run ONLY if you need to rollback:
-- UPDATE product_units pu
-- JOIN product_units_backup_20260803 pub ON pu.id = pub.id
-- SET pu.is_orderable = pub.is_orderable
-- WHERE pu.is_orderable != pub.is_orderable;
-- ============================================================================
