# AGENT WORKFLOW

**Green Leaf ERP — Agent Operating Manual**
**Version**: 2.0.0 | **Updated**: 2026-05-22 | **Status**: ACTIVE

> This is the **first document** every agent must read before touching any code.
> Not reading this causes broken architecture. Non-negotiable.

---

## ⚡ MANDATORY STARTUP SEQUENCE

Every agent session MUST follow this exact order:

```
1. Read this file (AGENT_WORKFLOW.md)
2. Read PROJECT_STATUS.md          → What exists
3. Read CURRENT_SPRINT.md          → What to build next
4. Read docs/01-laravel-protocol/LARAVEL_ARCHITECTURE.md  → How to build it
5. Read docs/01-laravel-protocol/FILE_CREATION_PROTOCOL.md → Where to put it
6. THEN start coding
```

**Skipping any step = broken code. No exceptions.**

---

## 🏗️ ARCHITECTURE IN ONE DIAGRAM

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────────────────────┐
│  FormRequest (Validate + Authorize)                      │
└─────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────┐
│  Controller (Thin — receive, delegate, respond)          │
│  extends BaseApiController                               │
└─────────────────────────────────────────────────────────┘
     │
     ├──── Simple CRUD ──────────────────────────────────▶ Service
     │
     └──── Complex / Multi-step ────────────────────────▶ Action
                                                              │
                                                              ▼
┌─────────────────────────────────────────────────────────┐
│  Service (Business Logic — reusable across endpoints)    │
│  extends BaseService                                     │
└─────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────┐
│  Repository (Database access ONLY)                       │
│  extends BaseRepository                                  │
└─────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────┐
│  Model (Data + Relationships ONLY)                       │
└─────────────────────────────────────────────────────────┘
     │
     ▼
  Database (MySQL via Eloquent)
```

**Golden Rule**: Data flows DOWN. Never skip a layer. Never query the DB from a controller.

---

## 📁 WHERE EVERY FILE BELONGS

| What you're building | Where it goes |
|---|---|
| API endpoint handler | `app/Http/Controllers/Api/{Module}/` |
| Web page handler | `app/Http/Controllers/Web/{Module}/` |
| Request validation | `app/Http/Requests/Api/{Module}/` |
| API response transformer | `app/Http/Resources/{Module}/` |
| Business logic (reusable) | `app/Services/{Module}/` |
| One-off operation (transaction) | `app/Actions/{Module}/` |
| Database query | `app/Repositories/{Module}/` |
| Data transfer object | `app/DTOs/{Module}/` |
| Type-safe constant | `app/Enums/` |
| Custom exception | `app/Exceptions/` |
| Interface / Contract | `app/Contracts/` |
| Shared trait | `app/Traits/` |
| Helper function | `app/Helpers/` |
| Complex query builder | `app/Queries/` |
| Immutable value | `app/ValueObjects/` |
| Domain concern | `app/Domains/{Domain}/` |
| Migration | `database/migrations/` |
| Factory | `database/factories/` |
| Seeder | `database/seeders/` |
| Feature test | `tests/Feature/{Module}/` |
| Unit test | `tests/Unit/{Module}/` |

---

## 🛠️ HOW TO BUILD A NEW FEATURE

### Step 1: Create the Model + Migration + Factory

```bash
php artisan make:model Product -mf --no-interaction
```

This creates:
- `app/Models/Product.php`
- `database/migrations/xxxx_create_products_table.php`
- `database/factories/ProductFactory.php`

### Step 2: Create the Repository

```bash
php artisan make:class App/Repositories/Inventory/ProductRepository --no-interaction
```

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Models\Product;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Product::class;
    }

    public function findActiveProducts(): Collection
    {
        return $this->query()->where('is_active', true)->get();
    }
}
```

### Step 3: Create the Service

```bash
php artisan make:class App/Services/Inventory/ProductService --no-interaction
```

```php
<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Models\Product;
use App\Repositories\Inventory\ProductRepository;
use App\Services\BaseService;

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

### Step 4: Create the FormRequest

```bash
php artisan make:request Api/Inventory/StoreProductRequest --no-interaction
```

### Step 5: Create the Controller

```bash
php artisan make:controller Api/Inventory/ProductController --no-interaction
```

Extend `BaseApiController`. Inject service. Delegate. Return response.

### Step 6: Register the Route

In `routes/api.php`, inside the appropriate version group.

### Step 7: Format Code

```bash
vendor/bin/pint --dirty --format agent
```

### Step 8: Write Tests

```bash
php artisan make:test Feature/Inventory/ProductTest --no-interaction
```

### Step 9: Run Tests

```bash
php artisan test --compact --filter=ProductTest
```

---

## ✅ PRE-COMMIT CHECKLIST

Before finalizing any code, verify ALL of these:

- [ ] **Thin controller** — no queries, no business logic
- [ ] **FormRequest used** — never `$request->all()` directly
- [ ] **Policy/Gate checked** — never skip authorization
- [ ] **Repository used** — never `Model::query()` in controller or service
- [ ] **Activity logged** — if data is mutated
- [ ] **No secrets in code** — use `.env`
- [ ] **Strict types declared** — `declare(strict_types=1);`
- [ ] **Type hints on all methods** — parameters and return types
- [ ] **PHPDoc on complex methods** — `@param`, `@return`, `@throws`
- [ ] **Pint formatted** — `vendor/bin/pint --dirty --format agent`
- [ ] **Tests written** — feature test for every endpoint
- [ ] **Tests passing** — `php artisan test --compact`

---

## 🚫 THINGS THAT ARE NEVER ALLOWED

```php
// ❌ NEVER: Direct query in controller
public function index(): JsonResponse
{
    $products = Product::all(); // FORBIDDEN
}

// ❌ NEVER: $request->all() without validation
$product = Product::create($request->all()); // FORBIDDEN

// ❌ NEVER: Business logic in a model
class Product extends Model
{
    public function processOrder(): void
    {
        // FORBIDDEN — business logic belongs in a service
    }
}

// ❌ NEVER: Skip authorization
public function update(Request $request, Product $product): JsonResponse
{
    // Where is the Gate check? FORBIDDEN
    $product->update($request->validated());
}

// ❌ NEVER: N+1 queries
foreach ($orders as $order) {
    echo $order->customer->name; // N+1 if customer not eager-loaded
}

// ❌ NEVER: return array from controller (use ApiResponse)
return ['status' => 'ok']; // FORBIDDEN — use ApiResponse::success()
```

---

## ✅ CANONICAL PATTERNS

### Controller (correct)
```php
public function store(StoreProductRequest $request): JsonResponse
{
    $product = $this->productService->create(
        ProductData::fromRequest($request)
    );

    return ApiResponse::created(
        new ProductResource($product),
        'Product created successfully'
    );
}
```

### Service (correct)
```php
public function create(ProductData $data): Product
{
    $product = $this->repository->create($data->toArray());
    $this->logActivity('product.created', $product);

    return $product;
}
```

### Repository (correct)
```php
public function findBySku(string $sku): ?Product
{
    return $this->query()->where('sku', $sku)->first();
}
```

### Test (correct)
```php
public function test_authenticated_user_can_create_product(): void
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/inventory/products', [
            'name' => 'Test Product',
            'sku'  => 'TST-001',
            'price' => 99.99,
        ]);

    $response->assertCreated();
    $this->assertDatabaseHas('products', ['sku' => 'TST-001']);
}
```

---

## 🔧 ARTISAN QUICK REFERENCE

| Task | Command |
|---|---|
| Create model + migration + factory | `php artisan make:model Name -mf` |
| Create controller | `php artisan make:controller Api/Module/NameController` |
| Create request | `php artisan make:request Api/Module/StoreNameRequest` |
| Create policy | `php artisan make:policy NamePolicy --model=Name` |
| Create test | `php artisan make:test Feature/Module/NameTest` |
| Create migration | `php artisan make:migration create_table_name` |
| Create seeder | `php artisan make:seeder NameSeeder` |
| Create job | `php artisan make:job ProcessName` |
| Create event | `php artisan make:event NameCreated` |
| Create listener | `php artisan make:listener HandleNameCreated` |
| Run migrations | `php artisan migrate` |
| Rollback last | `php artisan migrate:rollback` |
| List routes | `php artisan route:list --except-vendor` |
| Format code | `vendor/bin/pint --dirty --format agent` |
| Run all tests | `php artisan test --compact` |
| Run one test file | `php artisan test tests/Feature/SomeTest.php` |
| Run filtered test | `php artisan test --filter=test_name` |
| View logs | `php artisan pail` |
| Tinker | `php artisan tinker --execute 'Model::count();'` |

---

## 📚 DOCUMENTATION READING ORDER

When starting a task, read these docs in order:

1. **`docs/00-operating-system/AGENT_WORKFLOW.md`** — This file
2. **`docs/00-operating-system/PROJECT_STATUS.md`** — What is built
3. **`docs/00-operating-system/CURRENT_SPRINT.md`** — What to build next
4. **`docs/01-laravel-protocol/LARAVEL_ARCHITECTURE.md`** — Architecture rules
5. **`docs/01-laravel-protocol/FILE_CREATION_PROTOCOL.md`** — Where files go
6. **`docs/01-laravel-protocol/NAMING_CONVENTIONS.md`** — Naming rules
7. **`docs/02-security/SECURITY_CHECKLIST.md`** — Security rules
8. **`docs/05-green-leaf/BUSINESS_FLOW.md`** — ERP business logic

---

## 🔄 GIT WORKFLOW

```bash
# 1. Create feature branch
git checkout -b feature/inventory-product-management

# 2. Build feature (follow 9-step workflow above)

# 3. Format code
vendor/bin/pint --dirty --format agent

# 4. Run tests
php artisan test --compact

# 5. Commit with conventional message
git add .
git commit -m "feat(inventory): add product management endpoints"

# 6. Push
git push origin feature/inventory-product-management
```

**Commit message types**: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`

---

**Maintained by**: Senior Engineering Team
**Project**: Green Leaf ERP
**Stack**: Laravel 13 + PHP 8.4 + MySQL + Redis
