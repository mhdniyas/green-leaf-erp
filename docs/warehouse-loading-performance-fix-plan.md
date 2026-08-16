# Warehouse Loading Performance Fix Plan

Date: 2026-08-16

## Implementation Status (updated 2026-08-16)

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Extract queries into repositories | ✅ Complete (both Loadout + Receive) |
| 2 | Explicit selects + eager loads | ✅ Complete (loadout list; withCount replaces item load) |
| 3 | Remove N+1 queries | ✅ Complete (bulk stock-availability + bulk latest-activity) |
| 4 | Stored counters (`items_count`, `loaded_items_count`) | ⬜ Pending |
| 5 | Pagination | ⬜ Pending |
| 6 | Index migrations | ⬜ Pending |
| 7 | API Resources + ETag | 🔄 Partial (ETag + PerformanceProbe in API controller) |
| 8 | Monitoring | 🔄 Partial (PerformanceProbe logging in API controller) |

### Files Changed (uncommitted)
- `app/Services/Inventory/StockLedgerService.php` — added `availableSortedStockForProducts()` bulk method
- `app/Repositories/Warehouse/ShopOrderLoadoutRepository.php` — **[NEW]** loadout query with `withCount` for progress counters
- `app/Repositories/Warehouse/WarehouseReceiveRepository.php` — **[NEW]** receive queries + `latestActivityByStockLevel()` bulk N+1 fix
- `app/Http/Requests/Web/Warehouse/LoadoutIndexRequest.php` — **[NEW]**
- `app/Http/Requests/Web/Warehouse/ReceiveIndexRequest.php` — **[NEW]**
- `app/Http/Controllers/Web/Warehouse/WarehouseLoadoutController.php` — uses repository + FormRequest; bulk stock availability
- `app/Http/Controllers/Api/Warehouse/ApiWarehouseLoadoutController.php` — uses repository + FormRequest; bulk stock availability
- `app/Http/Controllers/Web/Warehouse/WarehouseReceiverController.php` — uses repository + FormRequest; N+1 loop replaced

---


Fix performance issues in the warehouse receive and loadout flow without changing the current user workflow, screen behavior, billing calculations, stock balances, or approval rules.

This plan is documentation only. It does not change application code.

## Scope
Pages and endpoints in scope:

1. Warehouse receive checklist:
- `warehouse.receiver.checklist`
- `App\Http\Controllers\Web\Warehouse\WarehouseReceiverController@index`
- `resources/views/warehouse-receiver/checklist.blade.php`

2. Warehouse GRN receive form:
- `warehouse.receiver.receive-grn`
- `App\Http\Controllers\Web\Warehouse\WarehouseReceiverController@receiveGrnForm`
- `resources/views/warehouse-receiver/receive_grn.blade.php`

3. Warehouse loadout list:
- `warehouse.loadout.index`
- `App\Http\Controllers\Web\Warehouse\WarehouseLoadoutController@index`
- `api.v1.warehouse.loadout.index`
- `App\Http\Controllers\Api\Warehouse\ApiWarehouseLoadoutController@index`
- `resources/views/warehouse/loadout/index.blade.php`

4. Warehouse loadout detail:
- `warehouse.loadout.show`
- `App\Http\Controllers\Web\Warehouse\WarehouseLoadoutController@show`
- `api.v1.warehouse.loadout.show`
- `App\Http\Controllers\Api\Warehouse\ApiWarehouseLoadoutController@show`
- `resources/views/warehouse/loadout/show.blade.php`

5. Closely related high-traffic pages to verify after warehouse fixes:
- `inventory.sorting.checklist`
- `inventory.sorting.shop-orders`
- `inventory.sorting.shop-sorting`
- `purchaser.daily`
- `purchaser.shop-orders.index`

## Non-Goals
Do not change:

1. Any billing-critical value calculation.
2. Stock ledger balance rules.
3. Invoice totals, shortage, excess, discount, paid amount, or balance logic.
4. Existing loadout save, move-to-delivery, partial delivery, receive GRN, or direct purchase receive behavior.
5. Existing API response shape on live v1 endpoints unless a new versioned endpoint is added.
6. Current permissions, roles, approval states, or route names.

## Problems Found

### 1. Full Collection Loads
Several warehouse pages use `get()` for data that can grow:

- pending GRNs
- pending stock batches
- confirmed stock batches
- approved shop orders
- all shop orders for a date
- loadout order list

Risk:
As rows grow, page load time and memory usage grow directly. The UI can freeze even without an N+1 query.

Fix direction:
Use `cursorPaginate()` for API list endpoints and `simplePaginate()` or cursor pagination for Blade pages where next/previous navigation is enough.

### 2. Repeated Live Counts
Loadout list calculates loaded item count and total item count from loaded `items` collections.

Risk:
The page loads all child rows just to show progress counters.

Fix direction:
Add stored counters on `shop_orders`:

- `items_count`
- `loaded_items_count`

Maintain them with a `ShopOrderItemObserver`. This keeps the list query as a normal column read.

### 3. N+1 Stock Activity Queries
Warehouse receive stock levels calculate latest activity inside a loop:

- one query per unsorted stock row against `stock_batches`
- one query per sorted stock row against `stock_movements`

Risk:
Query count grows with number of stock-level rows.

Fix direction:
Replace per-row lookups with grouped aggregate queries:

- latest pending batch by `product_id`
- latest movement by `product_id + grade`

Then attach timestamps in memory.

### 4. N+1 Stock Availability Queries
Loadout detail calculates available stock per product group by calling `availableSortedStockForProduct()` for every product.

Risk:
An order with many products triggers one stock lot aggregate query per product.

Fix direction:
Add a bulk stock availability method, for example:

- `availableSortedStockForProducts(array $productIds, ?int $warehouseId = null): Collection`

Use it once per loadout detail request.

### 5. Select-Star Hydration
Several root and eager-loaded model queries hydrate all columns.

Risk:
Unneeded text, JSON, audit, note, and metadata fields are pulled into memory.

Fix direction:
Add explicit `select()` columns on root models and eager-loaded relations. Always include primary keys and foreign keys needed for relation matching.

### 6. Inline Query Construction in Controllers
Warehouse receive and loadout list query logic is built directly in controllers.

Risk:
Filtering, select columns, eager loads, and indexes are harder to test and easier to regress.

Fix direction:
Move list query construction into repository/query classes:

- `ShopOrderLoadoutRepository`
- `WarehouseReceiveRepository`
- `GoodsReceivedRepository` if GRN-specific logic needs isolation

### 7. Request Validation Mixed Into Controllers
Filters are validated inline in controller methods.

Risk:
Filter behavior is harder to audit and reuse between Blade and API controllers.

Fix direction:
Add FormRequest classes:

- `LoadoutIndexRequest`
- `WarehouseReceiveIndexRequest`

Use `$request->validated()` only.

### 8. API Payload Size
The loadout list API returns all matching orders plus reference lists.

Risk:
Payload size grows with the business day and repeated polling reloads unchanged reference data.

Fix direction:
Use API Resources for response contracts and add ETag support for reference/list metadata only. Do not cache billing-critical or live stock values.

## Safe Redesign Order

### Phase 1: Extract Queries Without Behavior Change
Create repository/query classes and move the existing query logic as-is.

Deliverables:

1. `ShopOrderLoadoutRepository`
2. `WarehouseReceiveRepository`
3. Feature tests proving existing pages still return the same visible data.

Rules:

1. Keep route names unchanged.
2. Keep Blade variable names unchanged.
3. Keep API v1 response shape unchanged.
4. No pagination changes in this phase.
5. No index changes in this phase.

Rollback:
Controllers can be pointed back to the previous inline query logic if needed.

### Phase 2: Add Explicit Selects and Eager Loads
Update repository queries to select only used columns.

Deliverables:

1. Explicit `select()` on root list queries.
2. Explicit column lists on eager-loaded relations.
3. Tests for Blade pages and API responses.

Example direction:

```php
ShopOrder::query()
    ->select([
        'id',
        'shop_id',
        'order_number',
        'business_date',
        'delivery_status',
        'order_source',
        'created_at',
        'updated_at',
    ])
    ->with([
        'shop:id,name,code,warehouse_tag',
        'items:id,shop_order_id,product_id,sorting_status,approved_qty,requested_qty,loaded_qty',
        'items.product:id,category_id,name,sku,unit,default_warehouse_id',
        'items.product.category:id,name',
    ]);
```

Rules:

1. Include relation keys.
2. Do not remove fields used by existing Blade or API responses.
3. Do not change calculations.

### Phase 3: Remove N+1 Queries
Fix the two confirmed N+1 patterns.

Deliverables:

1. Bulk latest stock activity query for warehouse receive.
2. Bulk available sorted stock query for loadout detail.
3. Query-count tests using `DB::listen()` around representative page requests.

Expected result:

- Receive page query count becomes fixed instead of `base + stock row count`.
- Loadout detail query count becomes fixed instead of `base + product group count`.

### Phase 4: Stored Counters for Loadout Progress
Add denormalized counters to `shop_orders`.

Suggested columns:

```php
$table->unsignedInteger('items_count')->default(0);
$table->unsignedInteger('loaded_items_count')->default(0);
```

Update with:

- `ShopOrderItemObserver::created`
- `ShopOrderItemObserver::updated`
- `ShopOrderItemObserver::deleted`
- `ShopOrderItemObserver::restored`
- `ShopOrderItemObserver::forceDeleted`

Backfill:

```php
ShopOrder::query()
    ->select('id')
    ->chunkById(500, function ($orders): void {
        foreach ($orders as $order) {
            // Recalculate from shop_order_items.
        }
    });
```

Rules:

1. Counters are display optimization only.
2. Do not use these counters for billing, stock, or accounting decisions.
3. If counters are missing or wrong, detail pages must still show live item data.

### Phase 5: Pagination
Add pagination after query extraction and counters are stable.

Blade pages:

- Prefer `simplePaginate()` if the UI only needs next/previous.
- Use `cursorPaginate()` if stable cursor ordering is practical.
- Keep filters as normal GET query parameters.

API endpoints:

- Do not change existing v1 response shape in place.
- Add a versioned route or opt-in parameter if the response changes from flat list to paginated payload.

Stable order requirements:

- Loadout list: `business_date desc`, `created_at asc`, `id asc`
- Receive queues: `created_at asc`, `id asc`
- Movement history: `created_at desc`, `id desc`

### Phase 6: Index Validation
Add indexes only after repository SQL is final.

Candidate indexes:

```php
Schema::table('shop_orders', function (Blueprint $table): void {
    $table->index(['business_date', 'delivery_status', 'order_source', 'created_at', 'id'], 'shop_orders_loadout_list_idx');
    $table->index(['business_date', 'state', 'delivery_status', 'order_source', 'is_allocation_completed'], 'shop_orders_receive_direct_idx');
});

Schema::table('goods_received', function (Blueprint $table): void {
    $table->index(['status', 'received_at', 'created_at', 'id'], 'goods_received_receive_queue_idx');
    $table->index(['received_at', 'status', 'purchaser_cart_id'], 'goods_received_direct_lookup_idx');
});

Schema::table('stock_batches', function (Blueprint $table): void {
    $table->index(['warehouse_receive_pending', 'received_at', 'created_at', 'id'], 'stock_batches_receive_queue_idx');
    $table->index(['product_id', 'status', 'warehouse_id', 'created_at'], 'stock_batches_latest_activity_idx');
});

Schema::table('stock_movements', function (Blueprint $table): void {
    $table->index(['product_id', 'grade', 'warehouse_id', 'created_at'], 'stock_movements_latest_activity_idx');
    $table->index(['product_id', 'batch_id', 'grade', 'type'], 'stock_movements_stock_lots_idx');
});
```

Validation:

1. Run `EXPLAIN ANALYZE` before adding each index.
2. Add one index migration at a time.
3. Run `EXPLAIN ANALYZE` after.
4. Keep only indexes used by real query plans.

### Phase 7: API Resources and ETag
Move API response mapping into Resources.

Suggested resources:

- `ShopOrderLoadoutResource`
- `LoadoutShopResource`
- `LoadoutCategoryResource`
- `LoadoutProductGroupResource`

ETag rules:

1. ETag is allowed for list/reference metadata.
2. ETag version must be based on live `MAX(updated_at)` or equivalent row version queries.
3. Do not Redis-cache stock counts, invoice totals, balances, shortages, excess amounts, discounts, paid amounts, or any billing-critical value.
4. Reference data such as warehouses, categories, and product master data can use Redis cache with TTL and explicit invalidation.

### Phase 8: Monitoring
Add lightweight production query metrics.

Log per request:

- route name
- request method
- query count
- total query time
- max query time
- response time
- authenticated user id

Rules:

1. Do not log full SQL in production by default.
2. Add route-level thresholds for warehouse pages.
3. Alert or log warning when thresholds are exceeded.

Suggested initial thresholds:

- warehouse receive checklist: more than 40 queries or more than 500 ms DB time
- loadout list: more than 15 queries or more than 250 ms DB time
- loadout detail: more than 20 queries or more than 350 ms DB time

## Test Plan

### Query Tests
1. Use `DB::listen()` in feature tests for:
- warehouse receive checklist
- loadout list
- loadout detail
- API loadout index
- API loadout show

2. Seed enough rows to prove query count does not grow per row:
- 20 products
- 20 stock levels
- 20 shop order items
- multiple warehouses

3. Assert query count stays within agreed limits.

### Functional Regression Tests
1. Receive checklist still shows pending GRNs.
2. Receive checklist still shows pending direct purchases.
3. Receive checklist still shows pending batches.
4. Loadout list still groups orders by waiting, partial, ready, transit, and delivered.
5. Loadout detail still shows product groups, available stock, duplicates, add-ons, and invoice preview.
6. API loadout show still returns existing v1 fields.
7. ETag `If-None-Match` still returns `304` only when live row versions are unchanged.

### Counter Tests
1. Creating an order item increments `items_count`.
2. Marking an item loaded increments `loaded_items_count`.
3. Moving loaded item back to allocated decrements `loaded_items_count`.
4. Deleting/restoring item keeps counters correct.
5. Backfill command recalculates counters correctly.

### Index Tests
1. Capture `EXPLAIN ANALYZE` before index migration.
2. Capture `EXPLAIN ANALYZE` after index migration.
3. Confirm expected index is used.
4. Confirm write-heavy flows are not slowed noticeably.

## Acceptance Criteria
1. No existing route name changes.
2. No existing Blade workflow changes.
3. No existing v1 API response shape change without a new versioned endpoint.
4. No billing, balance, stock, invoice, shortage, excess, or discount value is cached.
5. Receive checklist query count no longer grows per stock-level row.
6. Loadout detail query count no longer grows per product group.
7. Loadout list does not load all child item rows only to display progress counters.
8. All new indexes are backed by `EXPLAIN ANALYZE` evidence.
9. Production query metrics can detect regressions after deployment.

## Rollout Plan
1. Ship Phase 1 only and verify no visible change.
2. Ship Phase 2 and Phase 3 together if tests prove query count improvement.
3. Ship Phase 4 counters with backfill and observer tests.
4. Ship Phase 5 pagination behind a UI-safe release.
5. Ship Phase 6 indexes one migration at a time.
6. Ship Phase 7 API resources/ETag without changing v1 shape.
7. Ship Phase 8 monitoring and watch warehouse routes for seven business days.
