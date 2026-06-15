# SERVICE LAYER PROTOCOL

**Green Leaf Traders — Service Layer Standards**
**Version**: 1.0.0

> Services are the business logic brain of this application.
> They orchestrate repositories, enforce business rules, and log activity.

---

## WHAT A SERVICE IS

```
Service = Business Logic + Orchestration + Activity Logging
```

A service knows HOW to do things. It does not know about HTTP, responses, or requests.

---

## WHAT GOES IN A SERVICE

| ✅ Belongs in Service | ❌ Does NOT belong |
|---|---|
| Business rules | HTTP Request/Response |
| Validation beyond form rules | Eloquent Model queries directly |
| Calculations and transformations | Authorization (belongs in Policy/FormRequest) |
| Calling repositories | Return `JsonResponse` |
| Activity logging | DB transactions (use Actions) |
| Cross-repository coordination | Route information |
| Cache management | |
| Dispatching events | |

---

## CANONICAL SERVICE

```php
<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Events\Inventory\ProductCreated;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Repositories\Inventory\ProductRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ProductService extends BaseService
{
    public function __construct(
        private readonly ProductRepository $repository,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->findActive($perPage);
    }

    public function create(ProductData $data): Product
    {
        $product = $this->repository->create($data->toArray());

        $this->logActivity('product.created', $product);
        Cache::forget('products.active.count');
        ProductCreated::dispatch($product);

        return $product;
    }

    public function update(Product $product, ProductData $data): Product
    {
        $updated = $this->repository->update($product, $data->toArray());

        $this->logActivity('product.updated', $updated);

        return $updated;
    }

    public function delete(Product $product): void
    {
        $this->logActivity('product.deleted', $product);
        $this->repository->delete($product);
    }

    /**
     * @throws InsufficientStockException
     */
    public function deductStock(Product $product, int $quantity): void
    {
        if ($product->stock_quantity < $quantity) {
            throw new InsufficientStockException(
                "Insufficient stock for SKU {$product->sku}. Available: {$product->stock_quantity}, requested: {$quantity}"
            );
        }

        $this->repository->update($product, [
            'stock_quantity' => $product->stock_quantity - $quantity,
        ]);

        $this->logActivity('stock.deducted', $product, ['quantity' => $quantity]);
    }
}
```

---

## SERVICE RULES

### ✅ MUST

- Extend `BaseService`
- Use `declare(strict_types=1)`
- Use `readonly` for injected dependencies
- Accept DTOs as parameters (not raw arrays from requests)
- Log activity for every data mutation
- Throw domain exceptions on business rule violations
- Dispatch events for significant state changes

### ❌ MUST NOT

- Query the database directly (`Product::where(...)`)
- Access `Request` objects
- Return `JsonResponse`
- Import HTTP namespaces (`Illuminate\Http\*`)
- Handle HTTP errors (404, 401) — let repositories/exceptions handle
- Duplicate business logic already in other services

---

## ACTIVITY LOGGING IN SERVICES

```php
// From BaseService — always use this:
$this->logActivity(string $action, Model $model, array $properties = [])

// Examples:
$this->logActivity('product.created', $product);
$this->logActivity('stock.adjusted', $product, [
    'old_quantity' => $oldQty,
    'new_quantity' => $newQty,
    'reason'       => $reason,
]);
$this->logActivity('order.status_changed', $order, [
    'from' => $oldStatus,
    'to'   => $newStatus,
]);
```

---

## EXCEPTION HANDLING

Services should throw meaningful domain exceptions:

```php
// ✅ Throw business domain exceptions
throw new InsufficientStockException("Not enough stock for SKU: {$sku}");
throw new DuplicateSkuException("SKU {$sku} already exists");
throw new OrderCannotBeCancelledException("Order {$order->id} is already shipped");

// These extend App\Exceptions\DomainException (create as needed)
// The exception handler converts them to appropriate HTTP responses
```

---

## WHEN TO USE SERVICE vs ACTION

Use **Service** when:
- The operation is reusable across multiple controllers
- It's a standard CRUD with business rules
- No cross-service transactions needed

Use **Action** when:
- Multiple services need to coordinate
- You need a DB transaction across multiple operations
- It's a one-off complex workflow

```
// ✅ Service: single domain, reusable
$this->productService->create($data);

// ✅ Action: multi-domain, transactional
$this->processStockMovementAction->execute($data);
// Internally: deducts stock + records ledger entry in one transaction
```

---

## SERVICE NAMING BY MODULE

```
app/Services/
├── Inventory/
│   ├── ProductService.php
│   ├── CategoryService.php
│   └── StockService.php
├── Sales/
│   ├── OrderService.php
│   ├── InvoiceService.php
│   └── PaymentService.php
├── Purchasing/
│   ├── PurchaseOrderService.php
│   └── SupplierService.php
├── Accounting/
│   ├── LedgerService.php
│   └── ReportService.php
├── HR/
│   ├── EmployeeService.php
│   └── PayrollService.php
└── Auth/
    ├── AuthService.php
    └── TokenService.php
```

---

**Owner**: Engineering Team | **Project**: Green Leaf Traders
