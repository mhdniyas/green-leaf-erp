# REPOSITORY PROTOCOL

**Green Leaf ERP — Repository Layer Standards**
**Version**: 1.0.0

> Repositories are the ONLY place database queries happen.
> They talk to Eloquent. Everyone else talks to repositories.

---

## WHAT A REPOSITORY IS

```
Repository = Database Access + Query Building + Data Return
```

It is **not** business logic. It is **not** aware of HTTP. It is the data gateway.

---

## CANONICAL REPOSITORY

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Models\Product;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Product::class;
    }

    // Simple lookup
    public function findBySku(string $sku): ?Product
    {
        return $this->query()->where('sku', $sku)->first();
    }

    // Paginated with eager loading
    public function findActive(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->where('is_active', true)
            ->with(['category'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    // Collection for operations
    public function findLowStock(int $threshold = 10): Collection
    {
        return $this->query()
            ->where('stock_quantity', '<=', $threshold)
            ->where('is_active', true)
            ->with(['category'])
            ->orderBy('stock_quantity')
            ->get();
    }

    // Search
    public function search(string $term, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%");
            })
            ->where('is_active', true)
            ->paginate($perPage);
    }

    // Bulk operation
    public function updateStockQuantity(int $productId, int $quantity): bool
    {
        return (bool) $this->query()
            ->where('id', $productId)
            ->update(['stock_quantity' => $quantity]);
    }
}
```

---

## INHERITED FROM BaseRepository

```php
// These are available without override:
$this->query()                  // Fresh Eloquent\Builder
$this->all()                    // Collection of all records
$this->paginate(15)             // LengthAwarePaginator
$this->create($data)            // Create and return model
$this->update($model, $data)    // Update and return fresh model
$this->delete($model)           // Soft-delete or hard-delete
$this->find($id)                // Find by ID or null
$this->findOrFail($id)          // Find by ID or throw ModelNotFoundException
```

---

## RULES

### ✅ MUST

- Extend `BaseRepository`
- Declare `getModel(): string` returning model class
- Use `$this->query()` for all queries
- Eager-load relationships when they will be used
- Return typed models (`?Product`, `Collection`, `LengthAwarePaginator`)
- Use `declare(strict_types=1)`

### ❌ MUST NOT

- Contain business logic (calculations, conditionals on business rules)
- Dispatch events
- Log activity
- Throw business exceptions (only `ModelNotFoundException`)
- Access `Request` or HTTP objects
- Use `Model::where(...)` directly (use `$this->query()`)
- Return raw arrays from Eloquent (return models/collections)

---

## QUERY OPTIMIZATION RULES

```php
// ✅ Always eager-load relationships you know you'll use
public function findActive(): Collection
{
    return $this->query()
        ->with(['category', 'supplier'])  // Eager-load to prevent N+1
        ->where('is_active', true)
        ->get();
}

// ✅ Select only needed columns for list views
public function findForDropdown(): Collection
{
    return $this->query()
        ->select(['id', 'name', 'sku'])
        ->where('is_active', true)
        ->orderBy('name')
        ->get();
}

// ✅ Use indexes in WHERE clauses (see INDEXING_GUIDE.md)
public function findBySku(string $sku): ?Product
{
    // 'sku' column must have an index in migration
    return $this->query()->where('sku', $sku)->first();
}

// ✅ Use chunk for large dataset processing
public function processAllProducts(callable $callback): void
{
    $this->query()->chunk(100, $callback);
}

// ❌ Never: Load all then filter in PHP
$all = $this->all();
$active = $all->filter(fn ($p) => $p->is_active); // BAD — use WHERE in query
```

---

## RETURNING SOFT-DELETED RECORDS

```php
// Include trashed (soft-deleted)
public function findWithTrashed(int $id): ?Product
{
    return $this->query()->withTrashed()->find($id);
}

// Only trashed
public function findTrashed(): Collection
{
    return $this->query()->onlyTrashed()->get();
}
```

---

## REPOSITORY NAMING BY MODULE

```
app/Repositories/
├── Inventory/
│   ├── ProductRepository.php
│   ├── CategoryRepository.php
│   └── StockMovementRepository.php
├── Sales/
│   ├── OrderRepository.php
│   ├── OrderItemRepository.php
│   └── InvoiceRepository.php
├── Purchasing/
│   ├── PurchaseOrderRepository.php
│   └── SupplierRepository.php
├── Accounting/
│   └── LedgerRepository.php
├── HR/
│   ├── EmployeeRepository.php
│   └── PayrollRepository.php
└── Auth/
    └── UserRepository.php
```

---

**Owner**: Engineering Team | **Project**: Green Leaf ERP
