# LARAVEL ARCHITECTURE

**Green Leaf Traders — Laravel Architecture Reference**
**Version**: 1.0.0 | **Stack**: Laravel 13 + PHP 8.4 + MySQL + Redis

> This document defines HOW code must be structured in this project.
> Every architectural decision here has been approved. Do not deviate without logging a decision.

---

## 1. ARCHITECTURE PHILOSOPHY

Green Leaf Traders follows **Layered Architecture** with **SOLID principles** enforced at every layer.

### Core Principles

| Principle | Meaning | How We Enforce It |
|---|---|---|
| **Single Responsibility** | Each class does one thing | Controllers only handle HTTP; Services only handle business logic |
| **Open/Closed** | Open for extension, closed for modification | Base classes extended, not modified |
| **Liskov Substitution** | Subtypes must be substitutable for base types | Repositories implement `BaseRepositoryContract` |
| **Interface Segregation** | Small, specific interfaces | Each contract is domain-focused |
| **Dependency Inversion** | Depend on abstractions | Services depend on `RepositoryContract`, not concrete classes |

---

## 2. THE LAYER STACK

```
┌────────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                          │
│  FormRequest (validate) → Controller (delegate) → Resource     │
│  Never: business logic, DB queries, authorization decisions    │
├────────────────────────────────────────────────────────────────┤
│                    APPLICATION LAYER                           │
│  Service (orchestrate) ↔ Action (one-off + transactions)       │
│  Never: direct DB access, HTTP concerns                        │
├────────────────────────────────────────────────────────────────┤
│                    DOMAIN LAYER                                │
│  Repository (data access) + Model (relationships + casts)      │
│  Never: business rules, HTTP response formatting              │
├────────────────────────────────────────────────────────────────┤
│                   INFRASTRUCTURE LAYER                         │
│  Database (MySQL) + Cache (Redis) + Queue (Redis)              │
└────────────────────────────────────────────────────────────────┘
```

### Layer Communication Rules

- ✅ Controller → Service (via method call)
- ✅ Controller → Action (for complex operations)
- ✅ Service → Repository (via injected dependency)
- ✅ Repository → Model (via Eloquent)
- ❌ Controller → Repository (skip service layer — FORBIDDEN)
- ❌ Controller → Model (direct Eloquent — FORBIDDEN)
- ❌ Service → HTTP/Request (no request objects in service)
- ❌ Model → Service (circular — FORBIDDEN)

---

## 3. DIRECTORY ARCHITECTURE

```
app/
├── Actions/                        # One-off operations with transactions
│   ├── BaseAction.php              # Abstract base: transaction(), execute()
│   └── {Module}/
│       └── {Verb}{Noun}Action.php  # e.g., ProcessInventoryTransactionAction
│
├── Contracts/                      # Interfaces only
│   ├── BaseRepositoryContract.php  # Base CRUD interface
│   └── {Module}/
│       └── {Noun}RepositoryContract.php
│
├── DTOs/                           # Data Transfer Objects
│   ├── BaseDTO.php                 # toArray(), toJson(), fromArray()
│   └── {Module}/
│       └── {Noun}Data.php          # e.g., ProductData, OrderData
│
├── Domains/                        # Domain-specific logic (grouping by domain)
│   └── {Domain}/
│       ├── Events/
│       ├── Listeners/
│       └── Notifications/
│
├── Enums/                          # PHP 8.1+ backed enums
│   └── {Noun}{Context}.php        # e.g., OrderStatus, PaymentMethod
│
├── Exceptions/                     # Custom exceptions
│   ├── ActionException.php
│   ├── ModelNotFoundException.php
│   └── {Domain}Exception.php
│
├── Helpers/                        # Pure functions, no state
│   └── {noun}_helpers.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── BaseApiController.php
│   │   │   └── {Module}/
│   │   │       └── {Noun}Controller.php
│   │   └── Web/
│   │       └── {Module}/
│   │           └── {Noun}Controller.php
│   │
│   ├── Middleware/
│   │   ├── ApiVersionMiddleware.php
│   │   └── SecureHeaders.php
│   │
│   ├── Requests/
│   │   ├── Api/
│   │   │   └── {Module}/
│   │   │       ├── Store{Noun}Request.php
│   │   │       └── Update{Noun}Request.php
│   │   └── Web/
│   │       └── {Module}/
│   │           └── Store{Noun}Request.php
│   │
│   └── Resources/
│       ├── BaseResource.php
│       └── {Module}/
│           ├── {Noun}Resource.php
│           └── {Noun}Collection.php
│
├── Models/                         # Eloquent models ONLY
│   └── {Noun}.php                  # Relationships, casts, fillable, scopes
│
├── Providers/                      # Service providers
│   └── AppServiceProvider.php
│
├── Queries/                        # Complex, reusable query builders
│   └── {Module}/
│       └── {Noun}Query.php
│
├── Repositories/                   # Database access ONLY
│   ├── BaseRepository.php
│   └── {Module}/
│       └── {Noun}Repository.php
│
├── Services/                       # Business logic ONLY
│   ├── BaseService.php
│   └── {Module}/
│       └── {Noun}Service.php
│
├── Support/                        # Internal utilities
│   └── ApiResponse.php
│
├── Traits/                         # Shared behaviors (mixins)
│   └── Has{Behavior}.php          # e.g., HasSoftDeletes, HasAuditLog
│
└── ValueObjects/                   # Immutable value types
    └── {Noun}.php                  # e.g., Money, SKU, Email
```

---

## 4. BASE CLASSES — HOW TO USE THEM

### 4.1 BaseRepository

**File**: `app/Repositories/BaseRepository.php`

```php
// Provided methods (don't re-implement):
$this->query()          // Returns Eloquent\Builder
$this->all()            // Get all records
$this->paginate(15)     // Paginate with 15 per page
$this->create($data)    // Create and return model
$this->update($model, $data) // Update and return fresh model
$this->delete($model)   // Delete, returns bool
$this->find($id)        // Find by ID, returns null if missing
$this->findOrFail($id)  // Find by ID, throws ModelNotFoundException

// How to extend:
class ProductRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Product::class;
    }

    // Add domain-specific methods here:
    public function findBySku(string $sku): ?Product
    {
        return $this->query()->where('sku', $sku)->first();
    }
}
```

### 4.2 BaseService

**File**: `app/Services/BaseService.php`

```php
// Provided methods:
$this->logActivity(string $action, Model $model, array $properties = [])

// How to extend:
class ProductService extends BaseService
{
    public function __construct(
        private readonly ProductRepository $repository
    ) {}

    public function create(ProductData $data): Product
    {
        $product = $this->repository->create($data->toArray());
        $this->logActivity('product.created', $product);

        return $product;
    }
}
```

### 4.3 BaseAction

**File**: `app/Actions/BaseAction.php`

```php
// Provided methods:
$this->transaction(callable $callback) // DB transaction with ActionException on failure

// How to extend:
class ProcessInventoryTransactionAction extends BaseAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly LedgerService $ledgerService
    ) {}

    public function execute(TransactionData $data): InventoryTransaction
    {
        return $this->transaction(function () use ($data) {
            $transaction = $this->inventoryService->deductStock($data);
            $this->ledgerService->recordMovement($transaction);

            return $transaction;
        });
    }
}
```

### 4.4 BaseApiController

**File**: `app/Http/Controllers/Api/BaseApiController.php`

```php
// Properties available:
protected string $resource = '';      // e.g., ProductResource::class
protected int $defaultPerPage = 15;
protected array $allowedSorts = [];
protected array $allowedFilters = [];

// How to extend:
class ProductController extends BaseApiController
{
    public function __construct(
        private readonly ProductService $service
    ) {}

    public function index(): JsonResponse
    {
        $products = $this->service->paginate();

        return ApiResponse::success(ProductResource::collection($products));
    }
}
```

### 4.5 BaseDTO

**File**: `app/DTOs/BaseDTO.php`

```php
// Provided methods:
$dto->toArray()                // Convert to array
$dto->toJson()                 // Convert to JSON
BaseDTO::fromArray($data)      // Create from array

// How to extend:
class ProductData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $sku,
        public readonly float $price,
        public readonly int $stock = 0,
    ) {}

    public static function fromRequest(StoreProductRequest $request): self
    {
        return new self(
            name: $request->string('name'),
            sku:  $request->string('sku'),
            price: $request->float('price'),
            stock: $request->integer('stock', 0),
        );
    }
}
```

---

## 5. API RESPONSE FORMAT

**All API responses MUST use `ApiResponse`:**

```php
// Success
ApiResponse::success($data, 'Message', 200);

// Created
ApiResponse::created($data, 'Resource created');

// Updated
ApiResponse::updated($data, 'Resource updated');

// Deleted
ApiResponse::deleted('Resource deleted');

// Error
ApiResponse::error('Something failed', 422);

// Validation error
ApiResponse::validationError($errors, 'Validation failed');

// Not found
ApiResponse::notFound('Resource not found');

// Unauthorized
ApiResponse::unauthorized('Access denied');

// Paginated
ApiResponse::paginated($paginatedResult, ProductResource::class);
```

**Response envelope**:
```json
{
    "success": true,
    "message": "Products retrieved successfully",
    "data": [...],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 100,
        "last_page": 7
    }
}
```

---

## 6. MODULE ORGANIZATION

Each ERP module follows this pattern:

```
# Example: Inventory Module

app/Http/Controllers/Api/Inventory/
    ProductController.php
    CategoryController.php
    StockMovementController.php

app/Http/Requests/Api/Inventory/
    StoreProductRequest.php
    UpdateProductRequest.php

app/Http/Resources/Inventory/
    ProductResource.php
    ProductCollection.php

app/Services/Inventory/
    ProductService.php
    StockService.php

app/Repositories/Inventory/
    ProductRepository.php
    CategoryRepository.php

app/DTOs/Inventory/
    ProductData.php
    StockMovementData.php

app/Actions/Inventory/
    ProcessStockMovementAction.php

app/Models/
    Product.php
    Category.php
    StockMovement.php
```

---

## 7. DEPENDENCY INJECTION RULES

```php
// ✅ Correct: Constructor injection with readonly
public function __construct(
    private readonly ProductService $productService,
    private readonly CategoryRepository $categoryRepository,
) {}

// ✅ Correct: Type-hinted method injection
public function store(StoreProductRequest $request): JsonResponse
{
    // $request is injected by Laravel
}

// ❌ Wrong: app() helper inside methods (avoid)
$service = app(ProductService::class); // Only in edge cases

// ❌ Wrong: new keyword (avoids DI container)
$service = new ProductService(); // FORBIDDEN
```

---

## 8. ELOQUENT MODEL RULES

Models may contain **ONLY**:

```php
class Product extends Model
{
    // ✅ Allowed
    use HasFactory, SoftDeletes, LogsActivity, Auditable;

    protected $fillable = [...];        // Mass assignment protection
    protected $casts = [...];           // Type casting
    protected $hidden = [...];          // Hide from serialization
    protected $appends = [...];         // Computed attributes

    // Relationships
    public function category(): BelongsTo { ... }
    public function stockMovements(): HasMany { ... }

    // Scopes (query constraints, not business logic)
    public function scopeActive(Builder $query): Builder { ... }

    // Accessors/Mutators (simple formatting)
    public function getPriceFormattedAttribute(): string { ... }
}
```

Models must **NOT** contain:

- Business logic (→ Service)
- Complex calculations (→ Service)
- HTTP concerns (→ Controller)
- DB queries beyond relationships (→ Repository)
- Validation rules (→ FormRequest)

---

## 9. TESTING ARCHITECTURE

```
tests/
├── Feature/            # Full HTTP stack tests (slow, realistic)
│   └── {Module}/
│       └── {Noun}Test.php
│
├── Unit/               # Class-level tests in isolation (fast)
│   └── {Module}/
│       └── {Noun}ServiceTest.php
│
├── Integration/        # Cross-service / external API tests
│   └── {Module}/
│
├── Architecture/       # PHPStan / architecture constraints
│
└── Security/           # Auth/authz tests
```

**Test naming convention**: `test_{subject}_{action}_{expected_result}`

```php
public function test_authenticated_admin_can_create_product(): void {}
public function test_guest_cannot_access_products(): void {}
public function test_product_sku_must_be_unique(): void {}
```

---

## 10. CODE QUALITY STANDARDS

### Required on every PHP file
```php
<?php

declare(strict_types=1);
```

### Required on every class
- Constructor property promotion for injected dependencies
- Return types on all methods
- Type hints on all parameters
- PHPDoc on complex methods
- No `mixed` type unless unavoidable

### Required before commit
```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

---

**Owner**: Engineering Architecture Team
**Project**: Green Leaf Traders
**Laravel Version**: 13 | **PHP Version**: 8.4
