# CONTROLLER PROTOCOL

**Green Leaf Traders — Controller Standards**
**Version**: 1.0.0

> Controllers are thin. They receive HTTP input, delegate to services, and return responses.
> If your controller has more than 10 lines per method, it's too fat.

---

## THE CONTROLLER CONTRACT

A controller method must do EXACTLY these things:

1. **Receive** — typed via route model binding or FormRequest injection
2. **Authorize** — via `FormRequest::authorize()` or `$this->authorize()`
3. **Delegate** — call service or action
4. **Respond** — return `ApiResponse` result

Nothing else.

---

## CANONICAL CONTROLLER

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Inventory\StoreProductRequest;
use App\Http\Requests\Api\Inventory\UpdateProductRequest;
use App\Http\Resources\Inventory\ProductResource;
use App\Models\Product;
use App\Services\Inventory\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProductController extends BaseApiController
{
    public function __construct(
        private readonly ProductService $service,
    ) {}

    public function index(): JsonResponse
    {
        $products = $this->service->paginate($this->defaultPerPage);

        return ApiResponse::success(ProductResource::collection($products));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->service->create(
            ProductData::fromRequest($request)
        );

        return ApiResponse::created(
            new ProductResource($product),
            'Product created successfully'
        );
    }

    public function show(Product $product): JsonResponse
    {
        return ApiResponse::success(new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $updated = $this->service->update(
            $product,
            ProductData::fromRequest($request)
        );

        return ApiResponse::success(
            new ProductResource($updated),
            'Product updated successfully'
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $this->service->delete($product);

        return ApiResponse::deleted('Product deleted');
    }
}
```

---

## RULES

### ✅ Controllers MUST

- Extend `BaseApiController`
- Use `declare(strict_types=1)`
- Use constructor injection with `readonly`
- Use `FormRequest` for all write operations
- Return `ApiResponse` helpers
- Use Route Model Binding for model parameters
- Keep each method under 10 lines

### ❌ Controllers MUST NOT

- Query the database directly (`Product::all()`, `DB::select(...)`)
- Contain business logic (calculations, conditionals, loops)
- Import Eloquent models for querying (only for type hints on model binding)
- Use `$request->all()` without going through `$request->validated()`
- Contain `try/catch` blocks (let exceptions propagate to Handler)
- Call `$request->validate([...])` — use FormRequest instead
- Duplicate logic across methods

---

## ROUTE MODEL BINDING

```php
// ✅ Correct: Laravel resolves model automatically
public function show(Product $product): JsonResponse
{
    return ApiResponse::success(new ProductResource($product));
}
// Route: GET /products/{product}
// If not found: 404 automatically

// ❌ Wrong: Manual lookup
public function show(int $id): JsonResponse
{
    $product = $this->service->find($id); // Skip route model binding
}
```

---

## RESPONSE HELPERS

Always use `ApiResponse` static methods:

```php
ApiResponse::success($data)                    // 200 OK
ApiResponse::success($data, 'message')         // 200 OK with message
ApiResponse::created($data, 'message')         // 201 Created
ApiResponse::updated($data, 'message')         // 200 OK (update)
ApiResponse::deleted('message')                // 200 OK (delete)
ApiResponse::noContent()                       // 204 No Content
ApiResponse::error('message', 422)             // Error response
ApiResponse::notFound('message')               // 404
ApiResponse::unauthorized()                    // 401
ApiResponse::forbidden()                       // 403
ApiResponse::validationError($errors)          // 422 with errors
```

---

## PAGINATION

```php
public function index(): JsonResponse
{
    $perPage = $this->defaultPerPage; // 15, from BaseApiController

    $products = $this->service->paginate($perPage);

    return ApiResponse::success(ProductResource::collection($products));
    // ApiResponse automatically detects pagination and adds meta
}
```

---

## AUTHORIZATION PLACEMENT

```
FormRequest::authorize()    → Use for: "can this user do this action at all?"
$this->authorize()          → Use for: "can this user act on THIS specific model?"
```

```php
// FormRequest handles general permission:
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class); // General
    }
}

// Controller handles model-specific:
public function destroy(Product $product): JsonResponse
{
    $this->authorize('delete', $product); // Specific model check
    $this->service->delete($product);
    return ApiResponse::deleted();
}
```

---

## CONTROLLER NAMING BY MODULE

```
app/Http/Controllers/Api/
├── Auth/
│   └── AuthController.php
├── Inventory/
│   ├── ProductController.php
│   ├── CategoryController.php
│   └── StockMovementController.php
├── Sales/
│   ├── OrderController.php
│   ├── OrderItemController.php
│   └── InvoiceController.php
├── Purchasing/
│   ├── PurchaseOrderController.php
│   └── SupplierController.php
├── Accounting/
│   ├── LedgerController.php
│   └── ReportController.php
├── HR/
│   ├── EmployeeController.php
│   └── PayrollController.php
└── Admin/
    ├── UserController.php
    ├── RoleController.php
    └── PermissionController.php
```

---

**Owner**: Engineering Team | **Project**: Green Leaf Traders
