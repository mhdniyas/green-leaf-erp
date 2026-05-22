# TESTING PROTOCOL

**Green Leaf ERP — Testing Standards**
**Version**: 1.0.0 | **Framework**: PHPUnit 12

> Tests are not optional. Every endpoint, every service method, every policy needs tests.
> Untested code is broken code you just haven't discovered yet.

---

## TEST PHILOSOPHY

- **Feature tests** — test the full HTTP stack (most common)
- **Unit tests** — test a single class in isolation
- **Integration tests** — test cross-service workflows
- **Security tests** — test authentication and authorization boundaries

**Rule**: Write the test before you consider the feature done.

---

## TEST STRUCTURE

```
tests/
├── Feature/            # Full HTTP stack — realistic tests
│   ├── Auth/
│   │   └── AuthTest.php
│   └── Inventory/
│       ├── ProductTest.php
│       └── StockMovementTest.php
│
├── Unit/               # Single class in isolation
│   ├── Services/
│   │   └── Inventory/
│   │       └── ProductServiceTest.php
│   └── DTOs/
│       └── Inventory/
│           └── ProductDataTest.php
│
├── Integration/        # Cross-service workflows
│   └── Inventory/
│       └── StockMovementFlowTest.php
│
├── Architecture/       # Code structure constraints
│   └── ArchitectureTest.php
│
└── Security/           # Auth/authz boundary tests
    └── SecurityTest.php
```

---

## CANONICAL FEATURE TEST

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles/permissions first
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin  = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('viewer');
    }

    // Happy path
    public function test_admin_can_list_products(): void
    {
        Product::factory(5)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/inventory/products');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    ['id', 'name', 'sku', 'price', 'stock_quantity'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_admin_can_create_product(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/inventory/products', [
                'name'           => 'Green Tea Premium',
                'sku'            => 'GTP-001',
                'price'          => 29.99,
                'stock_quantity' => 100,
                'category_id'   => $category->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku', 'GTP-001');

        $this->assertDatabaseHas('products', [
            'sku'   => 'GTP-001',
            'price' => 29.99,
        ]);
    }

    public function test_admin_can_update_product(): void
    {
        $product = Product::factory()->create(['price' => 10.00]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/inventory/products/{$product->id}", [
                'name'  => $product->name,
                'price' => 25.00,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.price', 25.00);
    }

    public function test_admin_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/inventory/products/{$product->id}")
            ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    // Authorization boundaries
    public function test_guest_cannot_list_products(): void
    {
        $this->getJson('/api/v1/inventory/products')
            ->assertUnauthorized();
    }

    public function test_viewer_cannot_create_product(): void
    {
        $this->actingAs($this->viewer, 'sanctum')
            ->postJson('/api/v1/inventory/products', [
                'name'  => 'Test',
                'sku'   => 'TST-001',
                'price' => 10,
            ])
            ->assertForbidden();
    }

    // Validation
    public function test_product_sku_must_be_unique(): void
    {
        $existing = Product::factory()->create(['sku' => 'DUP-001']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/inventory/products', [
                'name'  => 'Another Product',
                'sku'   => 'DUP-001',
                'price' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    public function test_product_price_must_be_positive(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/inventory/products', [
                'name'  => 'Test',
                'sku'   => 'TST-001',
                'price' => -5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price');
    }
}
```

---

## CANONICAL UNIT TEST

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Inventory\ProductService;
use Mockery;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    private ProductRepository $repository;
    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ProductRepository::class);
        $this->service    = new ProductService($this->repository);
    }

    public function test_create_creates_product_and_logs_activity(): void
    {
        $data = new ProductData(
            name:       'Green Tea',
            sku:        'GT-001',
            price:      9.99,
            categoryId: 1,
        );

        $product = Product::factory()->make(['sku' => 'GT-001']);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with($data->toArray())
            ->andReturn($product);

        $result = $this->service->create($data);

        $this->assertEquals('GT-001', $result->sku);
    }

    public function test_deduct_stock_throws_exception_when_insufficient(): void
    {
        $product = Product::factory()->make(['stock_quantity' => 5]);

        $this->expectException(InsufficientStockException::class);

        $this->service->deductStock($product, 10); // Request more than available
    }
}
```

---

## TEST HELPERS

### Common Assertions

```php
// Response structure
$response->assertOk();                          // 200
$response->assertCreated();                     // 201
$response->assertNoContent();                   // 204
$response->assertUnauthorized();                // 401
$response->assertForbidden();                   // 403
$response->assertNotFound();                    // 404
$response->assertUnprocessable();               // 422
$response->assertJsonPath('data.id', 1);        // Exact value
$response->assertJsonPath('success', true);
$response->assertJsonStructure(['data' => ['id', 'name']]);
$response->assertJsonValidationErrors('email'); // Validation error

// Database
$this->assertDatabaseHas('products', ['sku' => 'ABC']);
$this->assertDatabaseMissing('products', ['sku' => 'ABC']);
$this->assertSoftDeleted('products', ['id' => $id]);
$this->assertModelExists($product);
$this->assertModelMissing($product);
```

---

## RUNNING TESTS

```bash
# Run all tests
php artisan test --compact

# Run a specific test file
php artisan test --compact tests/Feature/Inventory/ProductTest.php

# Run a specific test method
php artisan test --compact --filter=test_admin_can_create_product

# Run a test class
php artisan test --compact --filter=ProductTest

# With coverage (requires pcov or xdebug)
php artisan test --coverage
```

---

## WHAT TESTS TO WRITE (Minimum Requirements)

For every controller:
- [ ] Happy path for each method (index, show, store, update, destroy)
- [ ] Unauthorized (no auth) returns 401
- [ ] Forbidden (wrong role) returns 403
- [ ] Validation error returns 422 (at least 2-3 rules)
- [ ] Not found returns 404

For every service method with business rules:
- [ ] Success case
- [ ] Failure case (wrong input, insufficient stock, etc.)

---

**Owner**: Engineering Team | **Project**: Green Leaf ERP
