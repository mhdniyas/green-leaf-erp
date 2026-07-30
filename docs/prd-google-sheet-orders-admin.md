# PRD: Google Sheet Orders Sync and Separate Admin Dashboard

## 1. Feature Summary

Build a read-only Laravel admin dashboard that receives live shop order quantities from a Google Sheet, stores validated quantities in the ERP database, and displays the current order matrix by product and shop. Admin users can review sync health, search/filter rows, and export the daily demand as Excel or a print/PDF view.

This feature is a bridge from the existing Google Sheet workflow into Green Leaf ERP. Phase 1 must not replace the existing ERP shop-order flow, create purchase orders automatically, or write back to Google Sheets.

## 2. Goals

- Receive Google Sheet edits through a secure Laravel webhook.
- Store one quantity per order date, product, shop, and source sheet location.
- Show a separate admin dashboard at `/admin/google-sheet-orders`.
- Display products as rows, shops as columns, and Laravel-calculated totals.
- Surface mapping and validation errors without creating products or shops automatically.
- Allow admin users to export Excel and browser-printable PDF.
- Keep the page read-only in Phase 1.

## 3. Non-Goals

- No shop-owner UI changes.
- No admin quantity editing.
- No approval workflow.
- No automatic supplier purchase order creation.
- No Google Sheet write-back or two-way sync.
- No automatic product/shop creation from Google Sheet data.
- No replacement of existing `ShopOrder`, `PurchaserCart`, GRN, or invoice workflows.

## 4. Users and Permissions

Primary user: Admin.

Permissions:

- `google-sheet-orders.view`: open dashboard and read sync data.
- `google-sheet-orders.export`: download Excel and open PDF/print view.

Role assignment:

- Add both permissions to the `admin` role in `database/seeders/RolePermissionSeeder.php`.
- Do not grant to `purchase`, `purchaser`, `shop`, or `warehouse_receiver` in Phase 1 unless explicitly approved later.

## 5. Navigation

Add a separate admin dashboard menu entry:

```text
Admin
└── Purchase
    └── Google Sheet Orders
```

Routes:

```text
GET  /admin/google-sheet-orders
GET  /admin/google-sheet-orders/export/excel
GET  /admin/google-sheet-orders/export/pdf
POST /api/integrations/google-sheet-orders
POST /api/integrations/google-sheet-orders/bulk
GET  /api/integrations/google-sheet-orders/health
```

Route names:

```text
admin.google-sheet-orders.index
admin.google-sheet-orders.export.excel
admin.google-sheet-orders.export.pdf
api.integrations.google-sheet-orders.store
api.integrations.google-sheet-orders.bulk
api.integrations.google-sheet-orders.health
```

## 6. Existing ERP Model Contracts

Product mapping must use the existing `products.sku` column, not `products.code`.

```php
Product::query()->where('sku', $productSku)->first();
```

Shop mapping should prefer `shops.code`, then exact `shops.name` as fallback.

```php
Shop::query()->where('code', $shopCode)->first();
Shop::query()->where('name', $shopName)->first();
```

Rules:

- Do not create missing products.
- Do not create missing shops.
- Do not write into `shop_orders` or purchaser carts in Phase 1.
- Include inactive products/shops in mapping if they exist, but mark them with a warning status in the dashboard.

## 7. Data Model

Create two tables: one for current valid quantities and one for sync attempts/errors.

### 7.1 `google_sheet_order_items`

Stores normalized valid order quantities.

Fields:

```text
id
order_date date
product_id foreign id, constrained products
shop_id foreign id, constrained shops
quantity decimal(12, 3), default 0
source_product_sku string(100)
source_shop_key string(150), nullable
source_shop_name string(150), nullable
sheet_id string(150), nullable
sheet_name string(150), nullable
sheet_row unsigned integer, nullable
sheet_column unsigned integer, nullable
source_revision string(100), nullable
last_payload_hash string(64), nullable
synced_at timestamp
created_at
updated_at
```

Indexes:

```text
unique(order_date, product_id, shop_id)
index(order_date, product_id)
index(order_date, shop_id)
index(synced_at)
```

Notes:

- Quantity must be stored as decimal, not float.
- A blank sheet cell should be treated as `0`.
- A valid payload with quantity `0` must update the existing row to zero, not delete it.

### 7.2 `google_sheet_order_sync_logs`

Stores every webhook request summary and row-level errors for support/debugging.

Fields:

```text
id
request_id uuid, unique
status enum: success, partial_success, failed
order_date date, nullable
product_sku string(100), nullable
shop_key string(150), nullable
shop_name string(150), nullable
quantity_raw string(100), nullable
sheet_id string(150), nullable
sheet_name string(150), nullable
sheet_row unsigned integer, nullable
sheet_column unsigned integer, nullable
error_code string(80), nullable
error_message text, nullable
payload json, nullable
processed_count unsigned integer, default 0
error_count unsigned integer, default 0
received_at timestamp
created_at
updated_at
```

Common `error_code` values:

```text
invalid_token
invalid_date
invalid_quantity
negative_quantity
missing_product_sku
product_not_found
missing_shop
shop_not_found
invalid_payload
server_error
```

## 8. Webhook Security

The Google Apps Script webhook is not authenticated by Laravel session login.

Security challenge:

- The webhook is internet-facing and can be called by anything that knows the URL.
- A leaked sheet token would allow unauthorized order quantity changes.
- Google Apps Script retries or duplicate triggers can replay the same update.
- Large paste operations can generate high request volume.
- Bad payloads can create noisy sync errors or try to exhaust storage.
- Product/shop names and sheet metadata are user-controlled input.
- Logs can accidentally expose sensitive payloads or secrets.
- Admin exports can leak operational order demand if permissions are too broad.
- Formula injection can occur if exported Excel cells begin with `=`, `+`, `-`, or `@`.

Use a shared secret header:

```text
X-Greenleaf-Sheet-Token: <secret>
```

Environment variable:

```env
GOOGLE_SHEET_SYNC_TOKEN=your-long-random-secret
```

Validation:

- Reject missing or incorrect token with HTTP `401`.
- Compare secrets with `hash_equals`.
- Apply rate limiting, for example `throttle:google-sheet-sync`.
- Accept only JSON requests.
- Log rejected requests without storing the secret.
- Never include the configured token in logs, responses, or UI.

Required mitigations:

- Serve the webhook only over HTTPS in production.
- Store `GOOGLE_SHEET_SYNC_TOKEN` only in `.env` and Google Apps Script properties, never in code or the sheet body.
- Rotate the token immediately if it appears in chat, logs, browser history, screenshots, or committed files.
- Validate `Content-Type: application/json`.
- Enforce maximum payload size and item count before processing bulk rows.
- Use idempotency with `request_id` and the database unique key to make retries safe.
- Do not trust sheet row, column, product name, shop name, or total cells for business truth.
- Escape all dashboard output through Blade defaults.
- Sanitize Excel-exported text values to prevent formula injection.
- Store only truncated/safe payload snapshots in `google_sheet_order_sync_logs`; never store request headers or secrets.
- Return generic server-error responses for unexpected exceptions while keeping detailed diagnostics in Laravel logs.
- Restrict dashboard and export routes with Laravel auth plus permissions.
- Add monitoring for repeated failed-token attempts and abnormal bulk volume.

Recommended token rotation process:

1. Generate a new random token with at least 32 bytes of entropy.
2. Update `GOOGLE_SHEET_SYNC_TOKEN` in Laravel production environment.
3. Update the matching Apps Script property.
4. Send a health check request.
5. Remove the previous token immediately after the sheet confirms successful sync.

Open security decision:

- Phase 1 uses a shared bearer token because it is simple for Apps Script. If this feed becomes business-critical or externally shared, move to signed requests using an HMAC header over timestamp + request body, with a short replay window.

## 9. Webhook Payload Contract

### 9.1 Single-cell update

Endpoint:

```text
POST /api/integrations/google-sheet-orders
```

Payload:

```json
{
  "request_id": "018f4e5d-77e2-7cd5-bbe7-561996ea23a4",
  "order_date": "2026-07-29",
  "product_sku": "TOM-H",
  "shop_code": "CASIO",
  "shop_name": "Casio",
  "quantity": "15",
  "sheet_id": "google-sheet-id",
  "sheet_name": "29 Jul",
  "sheet_row": 2,
  "sheet_column": 3,
  "source_revision": "optional-edit-id"
}
```

Required fields:

- `order_date`
- `product_sku`
- Either `shop_code` or `shop_name`
- `quantity`

Optional but recommended:

- `request_id`
- `sheet_id`
- `sheet_name`
- `sheet_row`
- `sheet_column`
- `source_revision`

Response success:

```json
{
  "success": true,
  "status": "success",
  "message": "Order quantity synced.",
  "data": {
    "order_date": "2026-07-29",
    "product_sku": "TOM-H",
    "shop_code": "CASIO",
    "quantity": "15.000",
    "synced_at": "2026-07-29T21:45:00+05:30"
  }
}
```

Response validation/mapping error:

```json
{
  "success": false,
  "status": "failed",
  "error_code": "product_not_found",
  "message": "Product SKU TOM-H was not found."
}
```

### 9.2 Bulk sheet update

Endpoint:

```text
POST /api/integrations/google-sheet-orders/bulk
```

Use this for initial import, manual refresh from Apps Script, and paste-heavy sheet edits.

Payload:

```json
{
  "request_id": "018f4e5d-77e2-7cd5-bbe7-561996ea23a4",
  "order_date": "2026-07-29",
  "sheet_id": "google-sheet-id",
  "sheet_name": "29 Jul",
  "items": [
    {
      "product_sku": "TOM-H",
      "shop_code": "CASIO",
      "shop_name": "Casio",
      "quantity": "15",
      "sheet_row": 2,
      "sheet_column": 3
    }
  ]
}
```

Bulk response:

```json
{
  "success": true,
  "status": "partial_success",
  "processed_count": 248,
  "error_count": 2,
  "errors": [
    {
      "product_sku": "BAD-SKU",
      "shop_code": "CASIO",
      "sheet_row": 27,
      "sheet_column": 3,
      "error_code": "product_not_found",
      "message": "Product SKU BAD-SKU was not found."
    }
  ]
}
```

Bulk behavior:

- Process valid rows even if some rows fail.
- Return `partial_success` when at least one row succeeds and at least one row fails.
- Store one `google_sheet_order_sync_logs` parent summary row, plus enough row-level failure detail to show errors in the admin page.
- Limit bulk payload size in config, default 5,000 items.

## 10. Validation and Sync Logic

For each incoming item:

1. Validate token.
2. Validate date format `Y-m-d`.
3. Normalize `product_sku` with `trim`.
4. Normalize `shop_code` and `shop_name` with `trim`.
5. Convert blank quantity to `0`.
6. Validate quantity is numeric and `>= 0`.
7. Find `Product` by `sku`.
8. Find `Shop` by `code`, then exact `name`.
9. Upsert by unique key `order_date + product_id + shop_id`.
10. Store sheet metadata and `synced_at`.
11. Store sync log status.
12. Return JSON result.

Idempotency:

- If `request_id` already exists in sync logs, return the previous result when possible.
- If the same product/shop/date arrives repeatedly, update the existing `google_sheet_order_items` row.
- Do not create duplicates during retries.

Total calculation:

- Never trust a total cell from Google Sheets.
- Laravel must calculate row totals from stored shop quantities.

```php
GoogleSheetOrderItem::query()
    ->whereDate('order_date', $date)
    ->where('product_id', $productId)
    ->sum('quantity');
```

## 11. Admin Dashboard

URL:

```text
/admin/google-sheet-orders
```

Default state:

- `Order Date`: current business/calendar date unless the app has a dedicated purchasing business date service available for admin views.
- `Only Items With Quantity`: enabled.
- `Product Search`: empty.
- `Shop Filter`: all active shops.

Header:

```text
Google Sheet Orders
Order Date: 29 July 2026
Last Synced: 09:45 PM
Status: Live
```

Header actions:

- Refresh
- Export Excel
- Export PDF

Status cards:

- Total products with quantity
- Total shops with quantity
- Grand total quantity
- Latest successful sync time
- Latest sync error count for selected date

Filters:

- Order date
- Product search by SKU or name
- Shop filter
- Only items with quantity
- Show rows with sync errors

Main matrix:

```text
Product SKU | Product Name | Unit | Shop 1 | Shop 2 | ... | Total | Sync Status
```

UI behavior:

- Product identity columns stay sticky on horizontal scroll.
- Total column remains visible.
- Shop quantity columns scroll horizontally.
- Zero values display lighter.
- Unknown product/shop errors appear in an error panel because they cannot be attached to a valid matrix row.
- Rows with warnings/errors show a visible status badge.
- Mobile uses horizontal scrolling; no special mobile rewrite is required in Phase 1.

Sorting:

- Default product order should match `Product::ordered()` if practical.
- Shop columns should sort by active shops first, then shop name/code.
- Totals should be sortable descending/ascending.

Empty states:

- No synced rows for date: show "No Google Sheet orders synced for this date."
- No rows after filters: show "No rows match the selected filters."
- Sync error only: show the error summary with no fake product rows.

## 12. Sync Status Rules

Status for selected date:

```text
Live: latest successful sync within 10 minutes and no newer failed request
No recent sync: no successful sync in the last 10 minutes
Sync error: latest request failed or selected date has unresolved row errors
Never synced: no successful sync exists for selected date
```

Display:

- Badge: `Live`, `No recent sync`, `Sync error`, or `Never synced`.
- Latest successful sync timestamp.
- Latest failed sync timestamp, if any.
- Last 10 sync errors for the selected date.

The 10-minute threshold should live in config:

```env
GOOGLE_SHEET_SYNC_LIVE_MINUTES=10
```

## 13. Exports

### 13.1 Excel

Route:

```text
GET /admin/google-sheet-orders/export/excel?date=2026-07-29
```

Permission:

```text
google-sheet-orders.export
```

Filename:

```text
green-leaf-google-sheet-orders-2026-07-29.xlsx
```

Columns:

```text
Order Date
Product SKU
Product Name
Unit
One column per shop
Total Quantity
Sync Status
Last Synced At
```

Rules:

- Excel must use the same filters as the dashboard, except pagination.
- Shop matrix should be included in Excel.
- Totals are calculated by Laravel.
- Include a second sheet named `Sync Errors` with selected-date errors.

### 13.2 PDF / Print View

Route:

```text
GET /admin/google-sheet-orders/export/pdf?date=2026-07-29
```

The project currently uses browser-printable PDF views in several places, so Phase 1 can follow that pattern instead of introducing a server-side PDF renderer.

Header:

```text
Green Leaf
Google Sheet Daily Order Demand
Date: 29 July 2026
Generated: 29 July 2026 09:50 PM
```

PDF content:

- Product SKU
- Product name
- Unit
- Total quantity
- Optional compact shop breakdown if it fits landscape cleanly
- Sync error summary

## 14. Configuration

Add config under `config/services.php` or a dedicated `config/google-sheet-orders.php`.

Environment variables:

```env
GOOGLE_SHEET_SYNC_TOKEN=
GOOGLE_SHEET_SYNC_LIVE_MINUTES=10
GOOGLE_SHEET_SYNC_BULK_LIMIT=5000
```

Do not commit real token values.

## 15. Apps Script Requirements

Apps Script must:

- Send JSON payloads.
- Include `X-Greenleaf-Sheet-Token`.
- Use `product_sku` from the ERP product SKU column.
- Use `shop_code` where available; otherwise send exact `shop_name`.
- Send `order_date` as `YYYY-MM-DD`.
- Send blank cells as `0` or an empty string that Laravel normalizes to `0`.
- Trigger bulk sync after manual setup or large paste actions.
- Avoid sending Google Sheet total cells as authoritative data.

Recommended sheet layout:

```text
Column A: Product SKU
Column B: Product Name for humans only
Columns C onward: Shop columns, header contains shop_code or exact shop_name
```

## 16. Error Handling

Admin page must show:

- Unknown product SKU.
- Unknown shop code/name.
- Invalid quantity.
- Negative quantity.
- Invalid date.
- Latest failed request timestamp.

Webhook must:

- Return clear JSON errors.
- Store failed request context in `google_sheet_order_sync_logs`.
- Continue processing valid rows during bulk sync.
- Avoid exposing stack traces.

## 17. Observability and Support

Add:

- Feature-specific log channel context: `google_sheet_orders`.
- Sync logs table view data in dashboard.
- Health endpoint that confirms configuration status without exposing the token.

Health response example:

```json
{
  "success": true,
  "configured": true,
  "latest_successful_sync_at": "2026-07-29T21:45:00+05:30",
  "latest_failed_sync_at": null
}
```

## 18. Implementation Plan

1. Add permissions to `RolePermissionSeeder`.
2. Add migrations for `google_sheet_order_items` and `google_sheet_order_sync_logs`.
3. Add Eloquent models and relationships.
4. Add webhook token middleware or controller-level validator.
5. Add request validators for single and bulk payloads.
6. Add a sync service that validates mappings and performs idempotent upserts.
7. Add API routes outside Sanctum-protected API groups, protected by token middleware and throttling.
8. Add admin web controller with dashboard filters and matrix query builder.
9. Add Blade dashboard view in the admin area.
10. Add Excel export using existing `Maatwebsite\Excel` pattern.
11. Add browser-printable PDF view using existing project style.
12. Add menu link to admin layout.
13. Add feature tests for webhook, dashboard, permissions, exports, and error states.
14. Add short Apps Script integration instructions.

## 19. Acceptance Criteria

Functional:

- Admin can open `/admin/google-sheet-orders` with `google-sheet-orders.view`.
- Unauthorized users cannot open the dashboard.
- Dashboard defaults to today and "only items with quantity".
- Products appear as rows.
- Shops appear as columns.
- Totals are calculated by Laravel from stored quantities.
- Admin can search by product SKU/name.
- Admin can filter by shop and date.
- Admin can include/exclude zero-only rows.
- Admin can refresh the dashboard.
- Admin can export Excel.
- Admin can open PDF/print view.

Sync:

- Apps Script can POST a valid single quantity and receive success JSON.
- Apps Script can POST a valid bulk payload and receive processed/error counts.
- Existing rows update without duplication.
- Quantity `0` updates correctly.
- Blank quantity is normalized to `0`.
- Negative and non-numeric quantities fail validation.
- Unknown product SKU is logged and visible in admin errors.
- Unknown shop code/name is logged and visible in admin errors.
- Invalid token returns `401`.
- Missing token returns `401`.
- Sync status changes based on latest success/failure and live threshold.

Security:

- Webhook requests without HTTPS are blocked at infrastructure level in production.
- Webhook rejects non-JSON payloads.
- Bulk endpoint rejects payloads above configured item and request-size limits.
- Token comparison uses constant-time comparison.
- Failed-token attempts are logged without storing the submitted token.
- Sync logs do not store request headers or secret values.
- Duplicate `request_id` retries do not create duplicate rows.
- Dashboard text output is escaped.
- Excel export sanitizes text cells that could be interpreted as formulas.
- Export routes require `google-sheet-orders.export`, separate from `google-sheet-orders.view`.
- Health endpoint never exposes the configured token.

Data:

- Unique key prevents duplicate `order_date + product_id + shop_id` records.
- `product_sku` maps to `products.sku`.
- `shop_code` maps to `shops.code`; exact `shop_name` fallback works.
- Missing mappings never create products or shops.

Testing:

- Feature tests cover successful single sync.
- Feature tests cover bulk partial success.
- Feature tests cover invalid token.
- Feature tests cover product not found.
- Feature tests cover shop not found.
- Feature tests cover duplicate update/idempotency.
- Feature tests cover dashboard permission access.
- Feature tests cover Excel route permission.
- Feature tests cover PDF route permission.

## 20. Phase 2 Candidates

- Admin quantity editing with audit trail.
- Approval step to convert synced demand into purchase workflow records.
- Supplier purchase order generation.
- Sheet write-back for sync status.
- Shop-owner replacement flow.
- Per-shop locked order windows.
- Conflict detection between ERP edits and Google Sheet edits.
