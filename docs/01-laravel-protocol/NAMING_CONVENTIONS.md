# NAMING CONVENTIONS

**Green Leaf Traders — Naming Standards Reference**
**Version**: 1.0.0 | **Enforced by**: Pint + Code Review

> Every name in this codebase must follow these rules. Inconsistent naming is a bug.
> When in doubt: be descriptive, be explicit, avoid abbreviations.

---

## 1. PHP CLASSES

| Type | Convention | Example |
|---|---|---|
| Model | `PascalCase` singular | `Product`, `SalesOrder`, `StockMovement` |
| Controller | `PascalCase` + `Controller` | `ProductController`, `SalesOrderController` |
| Service | `PascalCase` + `Service` | `ProductService`, `InventoryService` |
| Repository | `PascalCase` + `Repository` | `ProductRepository`, `OrderRepository` |
| Action | `Verb` + `Noun` + `Action` | `ProcessStockMovementAction`, `SendInvoiceAction` |
| FormRequest | `Store/Update` + `Noun` + `Request` | `StoreProductRequest`, `UpdateOrderRequest` |
| Resource | `PascalCase` + `Resource` | `ProductResource`, `OrderResource` |
| Collection | `PascalCase` + `Collection` | `ProductCollection`, `OrderCollection` |
| DTO | `PascalCase` + `Data` | `ProductData`, `StockMovementData` |
| Policy | `PascalCase` + `Policy` | `ProductPolicy`, `OrderPolicy` |
| Event | Past-tense `Noun` + `Verb`-ed | `ProductCreated`, `OrderShipped`, `StockDepleted` |
| Listener | Action-based | `SendLowStockAlert`, `UpdateInventoryOnOrder` |
| Job | Verb-based | `ProcessInventorySync`, `GenerateMonthlyReport` |
| Middleware | Descriptive + `Middleware` suffix optional | `EnsureTenantActive`, `ApiVersionMiddleware` |
| Enum | `PascalCase` + context | `OrderStatus`, `PaymentMethod`, `StockMovementType` |
| Exception | Descriptive + `Exception` | `InsufficientStockException`, `DuplicateSkuException` |
| Trait | `Has` + `Behavior` | `HasAuditLog`, `HasSoftDeletes`, `BelongsToTenant` |
| Interface/Contract | `PascalCase` + `Contract` | `ProductRepositoryContract` |
| ValueObject | Noun only | `Money`, `SKU`, `Email`, `PhoneNumber` |
| Seeder | `PascalCase` + `Seeder` | `RoleSeeder`, `ProductSeeder` |

---

## 2. METHODS

### General Rules
- Use **camelCase**
- Be **descriptive** — never abbreviate
- Use **verb + noun** pattern

| Type | Convention | Good Example | Bad Example |
|---|---|---|---|
| Boolean returns | `is`, `has`, `can`, `should` | `isActive()`, `hasStock()`, `canBeDeleted()` | `active()`, `stock()`, `deletable()` |
| Query methods | `find`, `get`, `fetch`, `search` | `findBySku()`, `getActiveProducts()` | `product()`, `active()` |
| Mutating methods | Verb + noun | `createProduct()`, `updateStock()`, `processOrder()` | `product()`, `stock()`, `order()` |
| Scopes | `scope` + `PascalCase` | `scopeActive()`, `scopeInStock()` | `scope_active()` |
| Relationships | noun (relationship type implied) | `category()`, `stockMovements()` | `getCategory()`, `getStockMovements()` |
| Accessors | `get` + `Attribute` + `Attribute` | `getPriceFormattedAttribute()` | `price()` |
| Event dispatch | `dispatch()` on event class | `ProductCreated::dispatch($product)` | `event(new ProductCreated(...))` (both work, prefer first) |

### Examples
```php
// ✅ Good method names
public function findBySkuOrFail(string $sku): Product {}
public function isInStock(): bool {}
public function hasEnoughStock(int $quantity): bool {}
public function canBeShipped(): bool {}
public function getFormattedPriceAttribute(): string {}
public function calculateTotalWithTax(float $taxRate): float {}

// ❌ Bad method names
public function get(): Product {}
public function check(): bool {}
public function calc(float $t): float {}
public function proc(): void {}
```

---

## 3. PROPERTIES AND VARIABLES

| Type | Convention | Example |
|---|---|---|
| Class property | `camelCase` | `$productService`, `$defaultPerPage` |
| Local variable | `camelCase` descriptive | `$activeProducts`, `$totalRevenue` |
| Boolean variable | `is/has/can` prefix | `$isActive`, `$hasPermission` |
| Collection variable | Plural noun | `$products`, `$orders`, `$users` |
| Single model | Singular noun | `$product`, `$order`, `$user` |
| Constants | `SCREAMING_SNAKE_CASE` | `MAX_STOCK_QUANTITY`, `TAX_RATE` |

```php
// ✅ Good
$isRegisteredForDiscounts = $user->hasRole('vip');
$activeProducts = $this->repository->findActive();
$totalQuantityInStock = $product->stock_quantity;

// ❌ Bad
$flag = $user->hasRole('vip');
$data = $this->repository->findActive();
$qty = $product->stock_quantity;
```

---

## 4. DATABASE TABLES & COLUMNS

### Tables

| Convention | Example |
|---|---|
| Lowercase `snake_case` | `products`, `sales_orders` |
| Plural of model name | `products` for `Product` model |
| Pivot table: both model names alphabetical | `order_product`, `role_user` |
| Module prefix for clarity (optional, see TABLE_NAMING.md) | `inv_products`, `sales_orders` |

### Columns

| Type | Convention | Example |
|---|---|---|
| Primary key | `id` | `id` |
| Foreign key | `{model}_id` | `product_id`, `category_id`, `user_id` |
| Boolean | `is_` or `has_` prefix | `is_active`, `has_vat`, `is_deleted` |
| Timestamps | `created_at`, `updated_at`, `deleted_at` | (Laravel standards) |
| Status | `_status` suffix | `order_status`, `payment_status` |
| Amounts/Money | `_amount`, `_price`, `_cost` | `unit_price`, `total_amount`, `tax_amount` |
| Dates | `_at` suffix for timestamps, `_date` for dates | `shipped_at`, `delivery_date` |
| Strings | Descriptive noun | `name`, `description`, `sku`, `barcode` |
| Text | Descriptive noun | `notes`, `remarks`, `address` |
| Counts | `_count` suffix | `items_count`, `views_count` |
| JSON | `_data` or `_metadata` suffix | `settings_data`, `shipping_metadata` |

---

## 5. ROUTES

### URL Naming
- Lowercase, hyphenated slugs: `sales-orders`, not `salesOrders` or `sales_orders`
- Resource routes use plural nouns: `/products`, `/orders`
- Nested resources: `/orders/{order}/items`
- Action routes use verbs: `/orders/{order}/ship`, `/invoices/{invoice}/send`

```php
// ✅ Good route URLs
GET  /api/v1/inventory/products
GET  /api/v1/inventory/products/{product}
POST /api/v1/inventory/products
PUT  /api/v1/inventory/products/{product}
DELETE /api/v1/inventory/products/{product}

POST /api/v1/sales/orders/{order}/confirm
POST /api/v1/sales/orders/{order}/ship
POST /api/v1/sales/orders/{order}/cancel

// ❌ Bad route URLs
GET /api/v1/getProducts
GET /api/v1/ProductList
POST /api/v1/createProduct
```

### Route Names
Convention: `{version}.{module}.{resource}.{action}`

```php
// ✅ Good route names
Route::get('/products', ...)->name('api.v1.inventory.products.index');
Route::post('/products', ...)->name('api.v1.inventory.products.store');
Route::get('/products/{product}', ...)->name('api.v1.inventory.products.show');
Route::put('/products/{product}', ...)->name('api.v1.inventory.products.update');
Route::delete('/products/{product}', ...)->name('api.v1.inventory.products.destroy');
```

---

## 6. ENUMS

```php
// ✅ Correct: PascalCase keys
enum OrderStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Shipped   = 'shipped';
}

// ❌ Wrong: SCREAMING_SNAKE in enum keys
enum OrderStatus: string
{
    case PENDING = 'pending';   // WRONG for PHP 8.1+ enums
}
```

---

## 7. FILES AND DIRECTORIES

| Type | Convention | Example |
|---|---|---|
| PHP class file | Matches class name exactly | `ProductService.php` |
| Config file | `kebab-case` | `inventory-settings.php` |
| Blade view | `kebab-case` | `product-list.blade.php` |
| Test file | Matches class being tested | `ProductServiceTest.php` |
| Migration | `snake_case` descriptive | `create_products_table.php` |
| Directory | `PascalCase` (under `app/`) | `app/Services/Inventory/` |
| Docs file | `SCREAMING_SNAKE_CASE.md` | `LARAVEL_ARCHITECTURE.md` |

---

## 8. CONSTANTS AND CONFIGURATION

```php
// ✅ Class constants: SCREAMING_SNAKE_CASE
class InventoryService
{
    public const MAX_BULK_IMPORT_SIZE = 1000;
    public const LOW_STOCK_THRESHOLD  = 10;
    public const DEFAULT_CURRENCY     = 'MYR';
}

// ✅ Config keys: snake_case
// config/green-leaf.php
return [
    'default_currency'   => env('DEFAULT_CURRENCY', 'MYR'),
    'low_stock_threshold' => env('LOW_STOCK_THRESHOLD', 10),
    'company_name'       => env('COMPANY_NAME', 'Green Leaf'),
];

// ✅ Environment variables: SCREAMING_SNAKE_CASE
// .env
DEFAULT_CURRENCY=MYR
LOW_STOCK_THRESHOLD=10
COMPANY_NAME="Green Leaf Traders"
```

---

## 9. TESTS

| Type | Convention | Example |
|---|---|---|
| Test method | `test_` + `{subject}_{action}_{expectation}` | `test_admin_can_create_product` |
| Setup method | `setUp` (always) | `protected function setUp(): void {}` |
| Provider method | `{noun}Provider` | `productDataProvider()` |

```php
// ✅ Good test method names
public function test_authenticated_admin_can_create_product(): void {}
public function test_guest_cannot_access_products(): void {}
public function test_product_sku_must_be_unique(): void {}
public function test_product_price_must_be_positive(): void {}
public function test_soft_deleted_product_excluded_from_index(): void {}

// ❌ Bad test method names
public function testCreate(): void {}
public function it_creates(): void {}   // Pest style — not used here
public function test1(): void {}
```

---

## 10. PERMISSIONS (Spatie Permission)

Convention: `{domain}.{resource}.{action}` — all lowercase dotted notation

```php
// ✅ Permission names
'inventory.product.view'
'inventory.product.create'
'inventory.product.update'
'inventory.product.delete'
'inventory.product.restore'
'inventory.stock.adjust'

'sales.order.view'
'sales.order.create'
'sales.order.confirm'
'sales.order.ship'
'sales.order.cancel'

'accounting.ledger.view'
'accounting.report.export'

'hr.employee.view'
'hr.payroll.process'
```

---

## 11. EVENTS AND JOBS

### Events — Past tense (something that happened)
```
ProductCreated      OrderShipped
ProductUpdated      OrderCancelled
StockDepleted       InvoiceSent
UserRegistered      PaymentReceived
```

### Jobs — Verb phrase (something to do)
```
SyncInventoryFromSupplier
SendMonthlyInvoices
GenerateSalesReport
ProcessPaymentRefund
SendLowStockAlert
```

### Listeners — Action phrase (responds to event)
```
SendProductCreatedNotification
UpdateStockOnOrderShipped
NotifyManagerOnLowStock
RecordAuditTrailOnUserLogin
```

---

## ENFORCEMENT

- **Pint** enforces PHP code style automatically
- **Code review** enforces naming before merge
- **Naming violations** are treated as bugs, not style preferences

---

**Owner**: Engineering Standards Team
**Project**: Green Leaf Traders
