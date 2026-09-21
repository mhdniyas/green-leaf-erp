# Purchaser Daily and Bulk Purchase Performance Audit

Date: 2026-09-21  
Scope: Purchaser → Daily → Bulk Purchase → Vendor carts → Submit  
Stack verified: PHP 8.4, Laravel 13.11.2, MySQL  
Mode: Audit only. No application code, schema, business rules, or dependencies changed.

## Executive conclusion

The main problem is not one slow query. The current flow repeatedly loads and renders datasets before the user asks for them, and several write paths still perform database work per purchase line.

The highest-impact findings are:

1. Daily renders both Pending and Completed tabs, including detailed shop splits, then serializes the same detailed summary into JavaScript.
2. Bulk Purchase loads Pending, Fulfilled, and the complete add-on catalog. The complete add-on catalog is injected into JavaScript.
3. Vendors loads Draft, Pending, Completed, and Cancelled data in one request, eagerly loads deep receipt relations, loads the complete product catalog and complete authorized supplier list, and calculates price hints for every cart.
4. Vendors has code-confirmed per-cart query growth: receipt-note lookup issues one query per cart, and price hints issue up to three queries per cart.
5. `bulkStoreCart()` and `updateCartItems()` call `remainingApprovedQuantityForProduct()` inside loops. That helper performs two aggregate queries per product.
6. Each cart-item save invokes `PurchaserCacheInvalidationObserver`, which synchronously runs `DailyPurchaserPriceSyncService`. This adds more per-line aggregate/approval queries before cache invalidation.
7. `submitCart()` groups approval reads correctly, but still updates each cart item, creates PO/GRN rows per line, records activity per model, synchronizes vendor prices per line, and invalidates cache repeatedly.
8. The deadline alert on Daily/Bulk can load all unresolved historical carts with relations, even when the alert only needs counts and a small preview.
9. The existing 45-second Redis cache helps Daily-summary warm reads, but it does not fix hidden HTML/JSON payload, uncached cart/deadline reads, Vendors page over-fetching, or mutation query slopes.
10. Existing telemetry is strong for safe purchaser GET requests, but disabled by configuration and incomplete for the three critical POST paths.

At the current database size (326 products, 113 suppliers), these patterns may appear acceptable. At 10,000 products, 5,000 suppliers, and 200 purchase lines, they become structurally unbounded.

## Evidence and limits

Verified live aggregate row counts:

| Table | Current rows |
|---|---:|
| products | 326 |
| suppliers | 113 |
| shop_orders | 1,013 |
| shop_order_items | 100,806 |
| purchaser_carts | 2,117 |
| purchaser_cart_items | 7,309 |
| purchase_orders | 4,270 |
| purchase_order_items | 44,222 |
| goods_received | 4,246 |
| goods_received_items | 14,163 |
| purchase_invoices | 2,086 |
| product_supplier | 1,014 |

Query counts below are either:

- exact structural counts where the code unconditionally runs a query in a loop;
- bounded query groups where Eloquent eager-loading creates a stable number of queries; or
- estimates explicitly marked as such because runtime branches, observers, cache hits, and Spatie activity logging affect the final count.

No production-like 10k/5k/200-line fixture exists, so end-to-end latency, peak memory, payload bytes, and final query totals at those scales cannot be honestly claimed yet.

## 1. Current architecture map

```text
auth middleware
  → PurchaserDashboardController::ensurePurchaser()
    → PurchaserBusinessDayService::operational date/selectability
      → PurchaserDashboardController
        ├─ daily()
        │   ├─ frequentProductIds()
        │   ├─ buildDailySummary() / buildGradeBPurchaseCatalog()
        │   │   └─ PurchaserReadCacheService (Redis, 45s)
        │   ├─ draftCartsForDate()
        │   └─ buildDeadlineAlert()
        │       ├─ overdueCartsForUser()
        │       └─ PurchaserCartBatchStateResolver
        │
        ├─ bulkBuy()
        │   ├─ compact daily summary
        │   ├─ all add-on products
        │   └─ deadline alert
        │
        ├─ bulkBuyDetails()
        │   ├─ full daily summary (not selected-only)
        │   ├─ selected products + units
        │   ├─ all draft carts for date
        │   └─ VendorPriceService per distinct supplier
        │
        ├─ vendors()
        │   ├─ all carts for date + deep relations
        │   ├─ all four tab datasets
        │   ├─ per-cart receipt-note lookups
        │   ├─ per-cart vendor-price lookups
        │   ├─ full active product catalog
        │   ├─ full authorized supplier catalog
        │   └─ deadline alert
        │
        ├─ bulkStoreCart() / updateCartItems()
        │   └─ per-line approval aggregates, price resolution, save, observer sync
        │
        └─ submitCart() [transaction]
            ├─ lock cart + load items/products
            ├─ per-line cart-item update + observer sync
            ├─ grouped approved/submitted quantities
            ├─ PO + GRN + line rows
            ├─ invoice + cart state
            ├─ journal/payment path
            ├─ per-line VendorPriceService::syncMany()
            └─ purchaser credit
```

Primary routes are in `routes/web.php:585-676`. They are inside the authenticated route group (`routes/web.php:243`) and role authorization is repeated in `ensurePurchaser()` (`PurchaserDashboardController.php:5533`). `SubmitPurchaserCartRequest` provides role authorization and validation for submit.

## 2. Daily page loading sequence

`daily()` (`PurchaserDashboardController.php:298`) currently performs:

1. purchaser authorization and business-date resolution;
2. frequent-product aggregation over the last 14 days;
3. full detailed daily summary for every approved product/date group;
4. all draft carts for the user/date with supplier, items, product/category, and GRN;
5. quick-filter categories when user-scoped;
6. fulfillment totals calculated from the full collection;
7. deadline alert, including same-day carts and unbounded unresolved historical carts with relations;
8. Blade rendering of both Pending and Completed sections;
9. duplication of detailed demand data into `window.purchaserDailyDemandData`.

The page therefore renders hidden Completed content and hidden per-product detail before either is opened.

## 3. Bulk Purchase loading sequence

`bulkBuy()` (`PurchaserDashboardController.php:674`) performs:

1. frequent-product aggregation;
2. compact daily summary for all approved products;
3. full active add-on product catalog excluding summary products;
4. PHP filtering into Pending and Fulfilled collections;
5. deadline alert;
6. Blade rendering of Pending and Fulfilled rows;
7. JSON serialization of every add-on product to `window.bulkBuyAddOnProducts`.

`bulkBuyDetails()` (`PurchaserDashboardController.php:729`) then rebuilds the full detailed daily summary even when only a few product IDs were selected, loads the selected products/units, loads every draft cart for the day with deep relations, and requests price hints once per distinct supplier plus a fallback query set.

## 4. Initial-load section audit

| Section | Current loading method | Query effect | Rows potentially loaded | Initial? | Lazy-load? | Recommendation |
|---|---|---:|---:|---|---|---|
| Daily header totals | Derived from full detailed summary | No extra SQL after summary | All summary rows in PHP | Yes | No | Replace detailed source with grouped summary query/cache |
| Daily Pending | Full detailed summary; Blade loop | Cold summary is roughly 10–12 query groups including eager loads | All approved order items plus cart items | Yes | Paginate/segment | Load summary rows only; load detail on expand |
| Daily Completed | Same collection, hidden by CSS | Shares full summary cost | All completed rows and detail HTML | No | Yes | Click-to-load endpoint; return a Blade fragment |
| Daily per-product shop detail | Precomputed for every product and duplicated into JSON | No extra SQL, high row/serialization cost | Every shop order item | No | Yes | Fetch one product/date detail on expand |
| Active carts card | `draftCartsForDate()` with 4 nested relation groups | Stable eager-load group, but over-shaped | All draft items/products | Useful summary only | Yes/detail | Query counts/totals only; fetch cart detail on click |
| Deadline alert | Same-day carts + all historical unresolved carts + batch state | Multiple eager-load groups; unbounded rows | All older carts and items for user | Summary only | Yes/detail | Aggregate counts; limit preview; detail endpoint |
| Bulk Pending | Full compact summary rendered | Summary query groups | All pending products | Yes | Paginate | Server-filtered 20–50 rows/page |
| Bulk Fulfilled | Full compact summary rendered hidden | Same summary + HTML | All fulfilled products | No | Yes | Click-to-load |
| Bulk Add-ons | Full product models mapped into JSON | Product + category queries; JSON/heap grows O(products) | Up to all active products | No | Yes | Server-side search; recent/frequent seed only |
| Bulk Details daily context | Rebuilds entire detailed summary | Roughly 10–12 cold query groups | All approved/order/cart items | No; selected products only | N/A | Scope all aggregates to selected IDs |
| Vendor Draft tab | All carts loaded, then split in PHP | Included in broad cart eager load | All carts/items for date | Active by default | Yes for other tabs | Query only draft carts |
| Vendor Pending/Completed/Cancelled | All loaded before active tab is resolved | Includes cancelled count/page and deep relations | All statuses for date | Usually no | Yes | One tab endpoint/query per click |
| Vendor product picker | `Product::active()->...->get()` | 2 query groups with category | Every active product | Modal closed | Yes | Search endpoint, 20–30 rows |
| Vendor supplier picker | `scopedSuppliersForUser()->get()` | 1+ queries depending visibility relation | Every authorized supplier | Modal closed | Yes | Search endpoint, 20 rows, retain selected supplier |
| Vendor price hints | `previousPricesForSupplier()` per cart | Up to 3 × cart count | Prices/history for every cart | Only selected cart needs it | Yes | Fetch per cart/modal; grouped multi-supplier query if truly needed |

Collection/memory impact is O(approved order items + all date carts/items + overdue historical carts) on Daily, O(all relevant products) on Bulk, and O(all date carts/items/receipts + all products + all suppliers) on Vendors. Eloquent model hydration and duplicated HTML/JSON make the memory multiplier materially larger than raw row size.

## 5. Card and tab classification

| UI area | Class | Reason |
|---|---|---|
| Daily four-number fulfillment strip | A — immediate | Above-fold operating state; should come from aggregates |
| Daily Pending list first page | A — immediate | Primary task |
| Daily Completed | C — click only | Hidden on first paint |
| Daily demand split/shop details | C — click only | Per-product drill-down |
| Active carts count/total | A/E — immediate cached summary | Useful, but raw items are not required |
| Active cart item details | C — click only | Separate operational drill-down |
| Deadline totals | A/E — cached/aggregate summary | Warning is useful; raw historical carts are not |
| Deadline cart list | B/C — after render or click | Potentially unbounded |
| Bulk Fulfilled | C — click only | Hidden tab |
| Bulk Add-ons catalog | C — search/click only | Large catalog risk |
| Vendor Draft | A if default | Query only active tab |
| Vendor Pending/Completed/Cancelled | C — click only | Mutually exclusive tabs |
| Supplier selector contents | C — open/search only | 5,000-vendor risk |
| Product selector contents | C — open/search only | 10,000-product risk |
| Repeated cart totals/status cards | D/E candidate | Derive from one grouped cart summary; keep detailed view separate |

No card should be removed in Phase 1. The recommendation is to change when and at what grain its data is loaded.

## 6. Confirmed loop-query and loop-write growth

### `remainingApprovedQuantityForProduct()`

The helper (`PurchaserDashboardController.php:3894`) always runs:

1. one aggregate over approved `shop_order_items`;
2. one aggregate over already-submitted `purchaser_cart_items`.

It is called inside `bulkStoreCart()` and `updateCartItems()` loops.

| Lines | Helper queries alone |
|---:|---:|
| 10 | 20 |
| 50 | 100 |
| 100 | 200 |
| 200 | 400 |

### `bulkStoreCart()` code-derived lower bound

Per selected product the primary loop performs two approval aggregates, one grade-price lookup, one existing-item lookup, and one write: at least 5 SQL operations per line before observer work and validation behavior. The cart-item observer then runs `DailyPurchaserPriceSyncService`, normally adding an aggregate and current/previous approval lookups. A realistic code-path estimate is therefore 7–8+ SQL operations per line.

| Lines | Primary lower bound | Likely with synchronous observer sync |
|---:|---:|---:|
| 10 | 50 | 70–80+ |
| 50 | 250 | 350–400+ |
| 100 | 500 | 700–800+ |
| 200 | 1,000 | 1,400–1,600+ |

### `updateCartItems()`

Each line performs the two approval aggregates and an update, then triggers the same observer sync. Static lower bound is 3 SQL operations per line; likely total is 5–6+ per line, before invoice recalculation for submitted carts.

### Vendors GET

- `relatedReceiptNotesForCarts()` calls `relatedGoodsReceiptsForCart()` once per cart: **N cart queries** even though receipt relations were already eager-loaded.
- `vendorPriceHintsByCart` calls `previousPricesForSupplier()` once per cart: fallback product price query + supplier pivot query + optional historical query, **up to 3N queries**.
- Combined confirmed slope: up to **4N additional queries** for N carts, before fixed eager-load/tab/catalog queries.

### `submitCart()`

Good: approved and submitted quantities are already grouped into two queries.

Still linear:

- one cart-item update per item, each triggering synchronous purchaser-price synchronization;
- one PO-item create and one GRN-item create per document line;
- Spatie activity logging for PO/GRN line models;
- a product update and product-supplier `updateOrInsert` per item in `VendorPriceService::syncMany()`;
- cache invalidation scheduled per item rather than once per mutation scope.

One cart line may split into regular and add-on document lines, so PO/GRN line writes can approach 4N base writes for N straddling lines, before activity rows.

## 7. Product scalability

All products are not loaded on Grade-A Daily itself; it is demand-driven. However:

- Bulk Add-ons loads all active purchasable products not already in the summary and serializes them into JavaScript (`bulk_buy.blade.php:2-16`).
- Vendors loads the entire active purchasable product catalog for a closed/unused picker (`vendors():1196-1201`).
- Grade-B catalog explicitly loads every active purchasable product (`buildGradeBPurchaseCatalog():3515-3520`).
- Purchaser Products and share-presets also use unpaginated `get()`, outside the core Daily initial route but relevant to purchaser scalability.

At 10,000 products, Bulk/Vendors/Grade-B risk tens of thousands of hydrated model/relationship objects, multi-megabyte HTML/JSON, long Blade loops, browser parse time, DOM memory, and client-side O(N) filtering. Redis cannot solve that payload/DOM cost.

Target architecture:

```text
Bulk shell
  → frequent/recent + today's required + already selected (small seed)
  → search after 2 characters, debounce 300 ms
  → GET /purchaser/product-search?q=&date=&grade=&cursor=
  → authorized/category-scoped query
  → 20–30 minimal rows: id, name, sku, unit, category, required/remaining if needed
```

Use cursor pagination or a stable `(sortable_sku, id)` continuation. Do not use `%term%` over 10,000 rows without an `EXPLAIN`; SKU prefix and normalized name-prefix searches are B-tree-friendly, while infix name search may need a separately approved search/index strategy.

## 8. Vendor scalability

All authorized suppliers are loaded on Vendors through `scopedSuppliersForUser()->orderBy('name')->get()` and rendered as modal buttons. With `vendor_visibility=all`, this is effectively every supplier.

At 5,000 suppliers this creates a large model collection and thousands of hidden DOM nodes before the supplier modal opens. Client-side `filterVendors()` then scans the DOM.

Target architecture:

```text
Open supplier selector
  → no catalog preload
  → type 2–3 characters
  → GET /purchaser/supplier-search?q=&cart=
  → enforce scopedSuppliersQuery() authorization
  → return 20 minimal rows: id, name, mobile suffix/display fields only as required
  → retain current selection separately
```

The existing `suppliers.name` index can support prefix ordering/search better than `%term%`; confirm the final query with `EXPLAIN`.

## 9. Blade and frontend payload findings

1. Daily renders both hidden tabs (`daily.blade.php:107-137`).
2. Daily renders hidden per-product detail blocks (`daily_item.blade.php:51-120`).
3. Daily serializes the detailed collection again into JavaScript (`daily.blade.php:426-450`).
4. Bulk renders both Pending and Fulfilled rows (`bulk_buy.blade.php:110-170`).
5. Bulk serializes the full add-on catalog (`bulk_buy.blade.php:2-16`).
6. Bulk Details renders every selected row and serializes price maps for every draft cart (`bulk_buy_details.blade.php:392-393`).
7. Vendors renders all four tab bodies and the full supplier modal list.
8. The four principal Blade source files alone are ~210 KB before generated per-record markup. Runtime response size grows with every row.

Recommended response targets:

- Daily initial compressed transfer: <150 KB; uncompressed HTML preferably <300 KB.
- Bulk initial compressed transfer: <150 KB; no full catalog JSON.
- Search response: <25 KB and ≤30 records.
- Vendor tab fragment: <200 KB with server pagination.

## 10. Cache audit

`PurchaserReadCacheService`:

- store: `redis`;
- prefix: `purchaser:v1`;
- key dimensions: tenant, user/all, date/all, grade/all, dataset, filter hash, compound scope versions;
- version scopes: orders, carts, products, settings, prices;
- Daily detailed/compact and Grade-B catalog TTL: 45 seconds;
- invalidation: observer `saved/deleted/restored`, version increment after commit;
- Redis failure: log throttled and execute callback directly;
- request-local memoization: scope-version reads only.

Findings:

- Good: user/date/grade/filter isolation and immediate version invalidation are tested.
- Good: Redis failure degrades to fresh reads rather than broken pages.
- Risk: no stampede protection; simultaneous expiry/miss can rebuild the same large summary.
- Risk: mutation loops schedule repeated invalidations and repeated price syncs.
- Gap: hit/miss and rebuild duration are not recorded.
- Gap: Vendors catalogs/tabs, deadline summary, frequent products, and static metadata are uncached.
- Risk: default tenant scope is `single_company`; safe only while deployment truly remains single-company.

Safe cache classes:

| Dataset | Freshness class | Recommendation |
|---|---|---|
| Categories, units, purchaser-visible product metadata | Longer | Versioned cache, 5–30 minutes |
| Frequent product IDs | 30–60 seconds | User-scoped; invalidate on submitted/cart activity |
| Daily summary totals | 30–45 seconds with immediate invalidation | Existing pattern; add stampede protection |
| Deadline counts | 15–30 seconds | User/date scoped; details remain on demand |
| Vendor search results | 30–60 seconds optional | Scope by user/visibility/query prefix |
| Remaining approved quantity | Must be transactionally fresh | Do not cache; group-query inside transaction |
| Invoice/payment/journal/stock balances | Must be fresh | Do not use stale read cache for decisions |

## 11. Database and index findings

Existing useful indexes include:

- `shop_orders(business_date, state)`;
- `shop_order_items(product_grade, product_id)` and `(shop_order_id, updated_at)`;
- `purchaser_carts(user_id, business_date, purchase_grade, status)`;
- `purchaser_cart_items(purchaser_cart_id, product_id, grade)` unique and `(product_id, grade)`;
- `products(show_in_purchaser_order, is_active, category_id)`;
- `suppliers(name)`;
- `purchase_orders(supplier_id, status)` and `(status, order_date)`.

Live `EXPLAIN` observations:

1. Approved Daily join uses `shop_orders_date_state_idx`, then looks up items by `shop_order_items_order_updated_idx`; `product_grade` is filtered after the item lookup. Test `(shop_order_id, product_grade, product_id)` only after production-shape `EXPLAIN ANALYZE`.
2. Submitted quantity aggregation uses `purchaser_carts(business_date,status)` and cart-item unique index, then creates a temporary table for `GROUP BY product_id`. This is expected for the grouping but must be benchmarked at 200 lines/many carts.
3. Product catalog uses `products_purchaser_active_cat_idx` but performs a sort by SKU. Eliminating full-catalog load is higher value than adding an index first.
4. Draft-cart sample query used `purchaser_carts(business_date,status)` then filtered user and filesorted by `updated_at`. Push `purchase_grade` into SQL so the existing user/date/grade/status index can compete; then `EXPLAIN ANALYZE` before proposing another index.
5. Historical vendor prices scan/sort many purchase lines for a supplier. Prefer `product_supplier` as source of truth and rewrite the fallback to select latest-per-product in SQL; test the exact query before considering `(supplier_id, order_date, id)` or item-side changes.

Required pre-index checks:

- `EXPLAIN ANALYZE` grouped approved quantities for 10/50/100/200 product IDs;
- `EXPLAIN ANALYZE` submitted quantities joined from date/status carts;
- `EXPLAIN ANALYZE` active-tab cart query with user/date/grade/status and pagination;
- `EXPLAIN ANALYZE` product and supplier search with representative prefixes;
- `EXPLAIN ANALYZE` vendor historical price fallback;
- capture `rows examined`, temporary tables, filesort, chosen key, and actual loops.

## 12. `submitCart()` critical path

| Stage | Current work | Query shape | Transaction | Must precede response? | After-commit candidate | Risk |
|---|---|---|---|---|---|---|
| Validation | FormRequest exists/date/items | Fixed + validation presence checks | Before | Yes | No | Low |
| Initial cart read | cart + items/products/supplier/invoice | Fixed eager-load group | Before | Yes | No | Low |
| Duplicate bill check | normalized supplier+bill lookup | 0/1 | Before and repeated under lock | Yes | No | High correctness |
| Cart lock/idempotency | locked cart + relations + invoice lookup | Fixed group | Yes | Yes | No | Critical |
| Line price update | one update per cart item | N plus observer queries | Yes | Yes | Coalesce observer side effects only | High |
| Approval allocation | two grouped aggregate queries | 2 | Yes | Yes | No | Critical business result |
| PO/GRN headers | 2 creates per regular/add-on group + number existence checks | Fixed per group | Yes | Yes | No | Critical |
| PO/GRN lines | PO item + GRN item per document line | 2N base; activity rows add writes | Yes | Yes | No without event parity | Inventory/audit |
| Invoice | one create | Fixed + activity/cache observer | Yes | Yes | No | Finance critical |
| Cart final state | one update | Fixed + observer sync | Yes | Yes | No | Idempotency critical |
| Journal/payment | service calls when paid | Service-dependent | Yes | Yes | No without proven ledger idempotency | Finance critical |
| Vendor prices | per-line product update + pivot updateOrInsert | O(N) | Yes | Value may be deferred only after durable source exists | Possibly after commit | Medium/high |
| Purchaser credit | one create | Fixed | Yes | Yes | No | Finance critical |
| Cache invalidation | repeated after-commit versions | O(N) Redis operations | After DB commit | No | Coalesce to once/scope | Low if freshness preserved |
| Redirect | route construction | 0 | After | Yes | N/A | Low |

Nothing finance-, inventory-, audit-, or idempotency-critical should be queued in the first optimization phase. First remove duplicate reads, coalesce side effects, and preserve model-event behavior with explicit tests.

## 13. Top 10 bottlenecks

### P0. Vendors loads every tab and deep relations

FILE: `app/Http/Controllers/Web/Purchasing/PurchaserDashboardController.php`  
METHOD: `vendors()`  
CURRENT BEHAVIOR: Loads all carts/statuses and deep GRN/PO/invoice relations, then splits tabs in PHP.  
WHY IT IS SLOW: Work and HTML scale with all tabs, not the active tab.  
CURRENT QUERY/PAYLOAD EFFECT: Fixed eager-load group plus all cart/item/receipt rows; all four Blade sections rendered.  
RECOMMENDED CHANGE: Resolve active tab first; query and paginate only that status; return tab fragments.  
BUSINESS LOGIC RISK: Low if status predicates and counts are parity-tested.  
EXPECTED IMPACT: Largest Vendors GET latency, memory, and payload reduction.

### P0. Full product and supplier catalogs are preloaded

FILE: controller `bulkBuy()`, `vendors()`; views `bulk_buy.blade.php`, `vendors.blade.php`  
METHOD: catalog/modal preparation  
CURRENT BEHAVIOR: Full active product/add-on catalog and all authorized suppliers are loaded before search/modal open.  
WHY IT IS SLOW: O(products + suppliers) hydration, JSON, DOM, and client filtering.  
CURRENT QUERY/PAYLOAD EFFECT: Query count is small but rows/payload are unbounded.  
RECOMMENDED CHANGE: Authorized server search, debounce 300 ms, 20–30 results.  
BUSINESS LOGIC RISK: Low/medium; must preserve category and vendor visibility scopes.  
EXPECTED IMPACT: Makes 10k/5k scale feasible.

### P0. Bulk cart store/update executes approval queries per product

FILE: `PurchaserDashboardController.php`  
METHOD: `bulkStoreCart()`, `updateCartItems()`, `remainingApprovedQuantityForProduct()`  
CURRENT BEHAVIOR: Two aggregates per product inside loops.  
WHY IT IS SLOW: At 200 lines the helper alone executes 400 queries.  
CURRENT QUERY/PAYLOAD EFFECT: Overall bulk-store lower bound ≥1,000 SQL operations at 200 lines before observers.  
RECOMMENDED CHANGE: Query approved/submitted totals once for all product IDs, keyed by product+grade.  
BUSINESS LOGIC RISK: Medium; parity and concurrent-submit tests required.  
EXPECTED IMPACT: Converts the dominant read slope from O(N) queries to O(1) query groups.

### P0. Cart-item observer performs synchronous price synchronization per save

FILE: `app/Observers/PurchaserCacheInvalidationObserver.php`, `DailyPurchaserPriceSyncService.php`  
METHOD: `saved()`, `syncForBusinessDate()`  
CURRENT BEHAVIOR: Every line save recalculates purchaser price state and schedules invalidation.  
WHY IT IS SLOW: Multiplies bulk-store, update, and submit query counts.  
CURRENT QUERY/PAYLOAD EFFECT: Typically 2–3+ extra SQL queries per line plus repeated Redis work.  
RECOMMENDED CHANGE: Introduce an explicit bulk mutation boundary that collects product IDs, preserves per-product calculation, synchronizes once after lines are durable, and invalidates each scope once.  
BUSINESS LOGIC RISK: High; observer/event parity and rollback behavior must be proven.  
EXPECTED IMPACT: Hundreds of queries removed at 200 lines.

### P0. `submitCart()` writes and side effects scale linearly

FILE: `PurchaserDashboardController.php`, `VendorPriceService.php`  
METHOD: `submitCart()`, `createPurchaseDocumentsFromLines()`, `syncMany()`  
CURRENT BEHAVIOR: Per-item updates, PO/GRN creates, activity logs, product/pivot writes, invalidations.  
WHY IT IS SLOW: Multiple database/Redis operations per line inside the transaction.  
CURRENT QUERY/PAYLOAD EFFECT: O(N), potentially higher when lines split regular/add-on.  
RECOMMENDED CHANGE: First coalesce reads, price sync, and invalidation; only then evaluate bulk inserts with explicit UUID/activity/event preservation.  
BUSINESS LOGIC RISK: Very high.  
EXPECTED IMPACT: Major submit latency and lock-duration reduction.

### P1. Daily duplicates detailed data in hidden HTML and JavaScript

FILE: `daily.blade.php`, `partials/daily_item.blade.php`  
METHOD: Blade render  
CURRENT BEHAVIOR: Pending/Completed plus all detail blocks are rendered and summary is serialized again.  
WHY IT IS SLOW: CPU, response bytes, DOM, and browser parse scale with shop-detail rows.  
CURRENT QUERY/PAYLOAD EFFECT: No extra SQL but significant duplicate payload/memory.  
RECOMMENDED CHANGE: summary rows only; detail endpoint on expand; Completed click-to-load.  
BUSINESS LOGIC RISK: Low.  
EXPECTED IMPACT: Faster first paint and lower browser memory.

### P1. Deadline alert loads unbounded historical carts

FILE: `PurchaserDashboardController.php`  
METHOD: `buildDeadlineAlert()`, `overdueCartsForUser()`  
CURRENT BEHAVIOR: Loads every older user cart with item/product/category/GRN/invoice relations.  
WHY IT IS SLOW: Growth is tied to purchase history, not current work.  
CURRENT QUERY/PAYLOAD EFFECT: Stable eager query count but unbounded rows/models and expensive batch resolver.  
RECOMMENDED CHANGE: grouped unresolved counts and a limited preview; fetch detail separately.  
BUSINESS LOGIC RISK: Medium; unresolved-state parity required.  
EXPECTED IMPACT: Prevents gradual Daily/Bulk degradation over time.

### P1. Vendors contains per-cart receipt and price N+1 patterns

FILE: controller + `VendorPriceService.php`  
METHOD: `relatedReceiptNotesForCarts()`, `vendorPriceHintsByCart`  
CURRENT BEHAVIOR: One receipt query and up to three price queries per cart.  
WHY IT IS SLOW: Query count grows up to 4N.  
CURRENT QUERY/PAYLOAD EFFECT: 50 carts can add ~200 queries.  
RECOMMENDED CHANGE: use already-loaded/grouped receipts; load hints only for focused cart or group by supplier/product.  
BUSINESS LOGIC RISK: Low/medium.  
EXPECTED IMPACT: Large query-count reduction.

### P1. Bulk Details rebuilds all-product detail for selected products

FILE: controller  
METHOD: `bulkBuyDetails()`  
CURRENT BEHAVIOR: Calls full detailed daily summary then selects IDs in PHP.  
WHY IT IS SLOW: Selected cardinality does not bound database rows or PHP work.  
CURRENT QUERY/PAYLOAD EFFECT: Full-day order/cart hydration for a small selection.  
RECOMMENDED CHANGE: pass selected IDs into grouped summary queries and load only selected products/units.  
BUSINESS LOGIC RISK: Medium.  
EXPECTED IMPACT: Bulk Details becomes proportional to selected rows.

### P2. Performance telemetry is disabled and misses critical POST stages

FILE: `LogSlowPathPerformance.php`, `PerformanceProbe.php`, logging config  
METHOD: middleware/probes  
CURRENT BEHAVIOR: Safe GET telemetry exists but config is disabled; stage probes exist only for Vendors; POST bulk-store/update/submit lack full instrumentation.  
WHY IT IS SLOW: Not causal, but prevents regression detection and reliable prioritization.  
CURRENT QUERY/PAYLOAD EFFECT: Unknown in production unless enabled.  
RECOMMENDED CHANGE: sampled/redacted telemetry with stage timings, query count/time, response bytes, item count, cache hits, and peak memory.  
BUSINESS LOGIC RISK: Low if bindings and sensitive values remain excluded.  
EXPECTED IMPACT: Makes performance targets enforceable.

## 14. Target lazy-tab architecture

Keep server-rendered Laravel; no SPA is required.

Suggested endpoints:

```text
GET /purchaser/daily?date=...                       shell + totals + Pending page 1
GET /purchaser/daily/tabs/completed?date=...        completed Blade fragment
GET /purchaser/daily/products/{product}/detail?...  one demand-detail fragment/JSON

GET /purchaser/bulk-buy?date=...                    shell + Pending seed
GET /purchaser/bulk-buy/tabs/fulfilled?...          fulfilled fragment
GET /purchaser/product-search?q=...                 20–30 minimal JSON rows
GET /purchaser/supplier-search?q=...                20 minimal scoped JSON rows

GET /purchaser/vendors?date=...&tab=draft           shell + Draft only
GET /purchaser/vendors/tabs/pending?...             pending fragment
GET /purchaser/vendors/tabs/completed?...           completed fragment
GET /purchaser/vendors/tabs/cancelled?...           cancelled fragment
GET /purchaser/carts/{cart}/price-hints             focused cart only
```

Laravel 13 Blade fragments (`view(...)->fragment(...)`) are suitable. Cache loaded fragments in the browser for the current date/tab until a mutation succeeds; then invalidate only affected tab keys.

## 15. Phased implementation plan

### Phase 1 — Highest impact, lowest business risk

1. Active-tab-only Vendors query and Blade fragment endpoints.
2. Click-to-load Daily Completed and product detail.
3. Remove full product/supplier modal catalogs from initial requests; add scoped search endpoints.
4. Replace `remainingApprovedQuantityForProduct()` loop calls with two grouped reads.
5. Remove per-cart receipt-note query by grouping already-loaded receipt data; load price hints only for focused cart.
6. Aggregate/limit deadline alert data.

Acceptance: exact same visible values/actions after opening each section; hidden tabs execute zero DB queries on initial request.

### Phase 2 — Bulk Purchase

1. Product search with 20–30 results and cursor/prefix pagination.
2. Supplier search with visibility scoping and 20 results.
3. Scope Bulk Details calculations to selected product IDs.
4. Group grade-price resolution for selected IDs.
5. Bulk-store/update inside transaction with cart lock and grouped approved/submitted quantities.
6. Add query-slope tests at 10/50/100/200 lines.

### Phase 3 — Submit performance

1. Instrument all submit stages before altering behavior.
2. Avoid re-saving unchanged cart-item prices.
3. Batch purchaser-price synchronization after durable line updates while preserving rollback behavior.
4. Coalesce cache invalidation to one increment per scope after commit.
5. Evaluate bulk PO/GRN line writes only with explicit preservation of timestamps, soft-delete defaults, UUIDs, Spatie activity records, observers, and model events.
6. Keep journal, credit, invoice, PO, GRN, and allocation work in the transaction unless a separately designed outbox/idempotency proof exists.

### Phase 4 — Database

Only after production-shaped `EXPLAIN ANALYZE`:

- adjust item join/index order for date/state/grade/product aggregates;
- validate cart active-tab index selection;
- validate latest vendor-price fallback plan;
- choose product/supplier search strategy and indexes.

### Phase 5 — Regression protection

Create PHPUnit feature benchmarks for small/current/future fixtures and assert:

- inactive tabs issue zero queries;
- product/supplier response count is bounded;
- bulk-store/update query count has a small constant slope, not 5–8 queries/line;
- submit query slope is explicitly budgeted;
- payload bytes and peak memory remain bounded;
- cold/warm cache parity and tenant/user/date/grade isolation remain correct.

## 16. Measurable targets

| Operation | Target |
|---|---|
| Daily warm | p95 <1.0s; ≤15 SQL queries; <300 KB HTML; <64 MB peak incremental memory |
| Daily cold | p95 <2.0s; ≤30 SQL queries; no hidden-tab/detail rows |
| Bulk shell | p95 <1.0s warm / <2.0s cold; ≤25 SQL; no full catalog JSON |
| Product search | p95 <200 ms; ≤5 SQL; ≤30 rows; <25 KB |
| Supplier search | p95 <200 ms; ≤5 SQL; ≤20 rows; <20 KB |
| Vendor active tab | p95 <1.0s; query count independent of other-tab rows; page size ≤25 carts |
| Bulk store/update 200 lines | no per-line aggregate reads; target ≤25 non-audit SQL plus explicitly budgeted writes |
| Submit 200 lines | no per-line read queries; transaction p95 target <2s where hardware permits; query slope dominated only by required durable/audit writes |
| Cache | hit/miss/rebuild duration recorded; one invalidation per scope per mutation |

The write targets must be split into read-query budget and required-write/audit budget; a flat query threshold would incorrectly encourage bypassing audit behavior.

## 17. Benchmark matrix

| Scenario | Products | Vendors | Lines |
|---|---:|---:|---:|
| Small | 100 | 50 | 10 |
| Current realistic | 1,000 | 500 | 50–100 |
| Future | 10,000 | 5,000 | 200 |

For each, capture cold/warm Daily, Bulk open, product/vendor search, save/update, submit, total duration, controller stages, SQL count/time, slowest normalized SQL, rows examined, Redis hit/miss, payload bytes, and peak memory. Never record bindings, free-text financial notes, bank details, phone numbers, or invoice contents.

## 18. Direct answers

1. **Why is Purchaser Daily currently slow?** It builds a detailed all-product summary, loads draft and historical deadline carts with relations, renders hidden Completed/detail HTML, and serializes the same details to JavaScript. Warm Redis only removes part of this work.
2. **Why is Bulk Purchase currently slow?** It builds the full summary, loads/render hidden Fulfilled data, loads the complete add-on catalog, and Bulk Details rebuilds all-product detail instead of selected-only detail.
3. **What is loaded before needed?** Completed/Fulfilled/Cancelled tabs, per-product demand detail, complete product and supplier catalogs, price hints for every cart, deep GRN relations, and historical deadline cart details.
4. **Which tabs/cards should become click-to-load?** Daily Completed and demand details; Bulk Fulfilled/Add-ons; Vendors Pending/Completed/Cancelled when inactive; supplier/product selectors; cart price hints; deadline detail.
5. **Are all products currently loaded?** Not on Grade-A Daily, but yes or effectively yes on Bulk Add-ons, Vendors product picker, Grade-B catalog, Products, and share-preset catalog.
6. **Are all vendors currently loaded?** Vendors loads every supplier allowed by `scopedSuppliersQuery`; for users with `all` visibility, that is the complete supplier table.
7. **What happens at 10,000 products?** Multi-megabyte model/JSON/HTML/DOM growth, slow Blade/browser parsing, high memory, and client-side search stalls.
8. **What happens at 5,000 vendors?** The closed vendor modal still hydrates and renders thousands of suppliers; browser DOM filtering becomes visibly slow.
9. **What happens with 200 purchase lines?** Approval helper alone issues 400 queries; bulk store is estimated at 1,400–1,600+ queries with observer sync; update and submit retain large linear write/side-effect slopes.
10. **First five highest-impact changes?** Active-tab-only Vendors; server product search; server supplier search; grouped approval/submitted reads; coalesced purchaser-price sync/cache invalidation.
11. **Safest first changes?** Lazy hidden tabs/details, remove catalog preload, on-demand price hints, use already-loaded receipt data, and add instrumentation/query-slope tests. These do not change purchasing calculations.
12. **Targets?** Enforce the table in section 16, especially zero inactive-tab queries, bounded search result counts, Daily/Bulk latency/payload budgets, and no per-line aggregate reads.

## Validation performed

- Laravel Boost application/package inspection.
- Laravel 13 documentation search for Blade fragments, query telemetry, cache behavior, transactions/locks, and upserts.
- Existing Graphify repository graph queried, then all critical paths verified directly in source.
- Live schema/index inspection and aggregate row counts through read-only database queries.
- Live `EXPLAIN` checks for Daily aggregates, submitted quantities, product catalog, draft carts, and vendor price history.
- `php artisan route:list --name=purchaser --except-vendor` completed.
- `php artisan test --compact tests/Feature/Purchasing/PurchaserRouteCachingPhase3Test.php tests/Feature/PurchaserPerformanceTelemetryTest.php`: 17 passed, 46 assertions.

## Remaining risk

This is a static and read-only database audit. Production p95 latency, Redis hit ratio, response size distribution, browser rendering time, and 200-line final query totals remain unmeasured because performance logging is disabled and scaled benchmark fixtures do not yet exist. Those measurements are the first implementation prerequisite, not a reason to delay the low-risk load-on-demand fixes.
