# API PROTOCOL

**Green Leaf ERP — API Design Standards**
**Version**: 1.0.0 | **Base URL**: `/api/v1`

> All API design follows REST principles with consistent response formatting.
> No exceptions to the response envelope format.

---

## 1. API VERSIONING

### URL Structure
```
https://erp.greenleaf.com/api/v1/{module}/{resource}
```

### Current Version
```
/api/v1/  ← Active version
/api/v2/  ← Future (when breaking changes needed)
```

### Version Strategy
- Breaking changes → new version (`/api/v2/`)
- Non-breaking additions → same version
- Deprecation → add `Deprecation` response header, keep 6 months minimum

---

## 2. RESPONSE ENVELOPE

**Every API response MUST use this envelope:**

### Success Response
```json
{
    "success": true,
    "message": "Products retrieved successfully",
    "data": { ... },
    "meta": {}
}
```

### Paginated Response
```json
{
    "success": true,
    "message": "Products retrieved successfully",
    "data": [
        { "id": 1, "name": "Green Tea", "sku": "GT-001" }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 150,
        "last_page": 10,
        "from": 1,
        "to": 15
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "sku": ["The SKU is already taken."],
        "price": ["The price must be a positive number."]
    },
    "meta": {}
}
```

---

## 3. HTTP STATUS CODES

| Code | Meaning | When to Use |
|---|---|---|
| `200 OK` | Success | GET, PUT, PATCH, DELETE success |
| `201 Created` | Resource created | POST success |
| `204 No Content` | Success, no body | DELETE (when no body needed) |
| `400 Bad Request` | Malformed request | Request parsing error |
| `401 Unauthorized` | Not authenticated | No token or invalid token |
| `403 Forbidden` | Not authorized | Token valid, permission denied |
| `404 Not Found` | Resource missing | Record not found |
| `422 Unprocessable` | Validation error | Form validation failed |
| `429 Too Many Requests` | Rate limit | Rate limiter triggered |
| `500 Internal Server` | Server error | Unexpected exception |

---

## 4. REST ENDPOINT DESIGN

### Resource Endpoints (standard)
```
GET    /api/v1/inventory/products           ← List all (paginated)
POST   /api/v1/inventory/products           ← Create new
GET    /api/v1/inventory/products/{id}      ← Get one
PUT    /api/v1/inventory/products/{id}      ← Replace (full update)
PATCH  /api/v1/inventory/products/{id}      ← Partial update
DELETE /api/v1/inventory/products/{id}      ← Delete
```

### Action Endpoints (non-CRUD)
```
POST /api/v1/sales/orders/{order}/confirm   ← Confirm an order
POST /api/v1/sales/orders/{order}/ship      ← Mark as shipped
POST /api/v1/sales/orders/{order}/cancel    ← Cancel order
POST /api/v1/inventory/products/{product}/restock  ← Trigger restock
```

### Nested Resources
```
GET  /api/v1/sales/orders/{order}/items     ← Order's line items
POST /api/v1/sales/orders/{order}/items     ← Add item to order
DELETE /api/v1/sales/orders/{order}/items/{item} ← Remove item
```

---

## 5. API ROUTE REGISTRATION

```php
// routes/api.php

Route::prefix('v1')->middleware('api')->name('api.v1.')->group(function () {

    // Public routes (no auth)
    Route::get('/health', fn () => ApiResponse::success(['status' => 'ok']));

    // Auth routes (public but rate limited)
    Route::prefix('auth')->name('auth.')->middleware('throttle:5,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login',    [AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password',  [AuthController::class, 'resetPassword']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/auth/me',     [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // Inventory Module
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::apiResource('products',   Inventory\ProductController::class);
            Route::apiResource('categories', Inventory\CategoryController::class);
            Route::apiResource('stock-movements', Inventory\StockMovementController::class)->only(['index', 'store']);
        });

        // Sales Module
        Route::prefix('sales')->name('sales.')->group(function () {
            Route::apiResource('orders', Sales\OrderController::class);
            Route::post('orders/{order}/confirm', [Sales\OrderController::class, 'confirm'])->name('orders.confirm');
            Route::post('orders/{order}/ship',    [Sales\OrderController::class, 'ship'])->name('orders.ship');
        });

        // Admin Routes
        Route::prefix('admin')->middleware('role:admin|super-admin')->name('admin.')->group(function () {
            Route::apiResource('users',       Admin\UserController::class);
            Route::apiResource('roles',       Admin\RoleController::class);
            Route::apiResource('permissions', Admin\PermissionController::class);
        });
    });
});
```

---

## 6. FILTERING AND SORTING

```
# Filtering
GET /api/v1/inventory/products?filter[is_active]=1
GET /api/v1/inventory/products?filter[category_id]=5
GET /api/v1/inventory/products?filter[price_min]=10&filter[price_max]=100

# Searching
GET /api/v1/inventory/products?search=green+tea

# Sorting
GET /api/v1/inventory/products?sort=name          ← Ascending
GET /api/v1/inventory/products?sort=-price        ← Descending (leading minus)

# Pagination
GET /api/v1/inventory/products?page=2&per_page=25

# Including relationships
GET /api/v1/inventory/products?include=category,supplier
```

---

## 7. AUTHENTICATION HEADERS

```
Authorization: Bearer {sanctum-token}
Content-Type: application/json
Accept: application/json
X-API-Version: 1
```

---

## 8. ENDPOINT DOCUMENTATION FORMAT

Document every endpoint:

```markdown
### GET /api/v1/inventory/products

Retrieve a paginated list of active products.

**Auth**: Required (Bearer token)
**Permission**: `inventory.product.view`
**Rate Limit**: 60/minute

**Query Parameters**:
| Param | Type | Required | Description |
|---|---|---|---|
| page | integer | No | Page number (default: 1) |
| per_page | integer | No | Results per page (default: 15, max: 100) |
| search | string | No | Search by name or SKU |
| filter[category_id] | integer | No | Filter by category |
| sort | string | No | Sort field. Prefix with `-` for DESC |

**Response 200**:
```json
{
    "success": true,
    "data": [{"id": 1, "name": "Green Tea", "sku": "GT-001", "price": "9.99"}],
    "meta": {"current_page": 1, "per_page": 15, "total": 150}
}
```

**Response 401**: Not authenticated
**Response 403**: No permission
```

---

**Owner**: Engineering Team | **Project**: Green Leaf ERP
