# FILE CREATION PROTOCOL

**Green Leaf ERP — How to Create Every File Type**
**Version**: 1.0.0 | **Stack**: Laravel 13 + PHP 8.4

> This document answers: "I need to create X — what command do I run and what does the file look like?"
> Always use `php artisan make:` commands. Never create files manually unless `make:` doesn't support it.

---

## ⚠️ RULES BEFORE CREATING ANY FILE

1. **Check if it exists** — search the codebase first
2. **Use `make:` commands** — not manual file creation
3. **Always pass `--no-interaction`** — non-interactive mode
4. **Check sibling files** — look at existing files in the same directory for the pattern
5. **Run Pint after** — `vendor/bin/pint --dirty --format agent`

---

## 1. MODELS

### Command
```bash
# Model only
php artisan make:model Product --no-interaction

# Model + migration + factory (PREFERRED — always include these)
php artisan make:model Product -mf --no-interaction

# Model + migration + factory + seeder + policy
php artisan make:model Product -mfsp --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions; // v5 namespace
use Spatie\Activitylog\Traits\LogsActivity;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Product extends Model implements AuditableContract
{
    use Auditable, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'price',
        'stock_quantity',
        'category_id',
        'is_active',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active'     => 'boolean',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    protected $hidden = [];

    // Activity log configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
```

---

## 2. MIGRATIONS

### Command
```bash
# Create table
php artisan make:migration create_products_table --no-interaction

# Add column
php artisan make:migration add_barcode_to_products_table --no-interaction

# Modify column
php artisan make:migration change_price_in_products_table --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['category_id', 'is_active']);
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

**Rules**:
- Always include `$table->timestamps()`
- Add `$table->softDeletes()` for any domain entity (see SOFT_DELETE_POLICY.md)
- Add indexes on columns used in `WHERE`, `ORDER BY`, `JOIN`
- Use `foreignId()->constrained()` for all foreign keys
- Always implement `down()` to reverse the migration

---

## 3. FACTORIES

### Command
```bash
php artisan make:factory ProductFactory --model=Product --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id'    => Category::factory(),
            'name'           => $this->faker->words(3, true),
            'sku'            => strtoupper($this->faker->unique()->bothify('???-####')),
            'description'    => $this->faker->sentence(),
            'price'          => $this->faker->randomFloat(2, 1, 1000),
            'stock_quantity' => $this->faker->numberBetween(0, 500),
            'is_active'      => true,
        ];
    }

    // States
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock_quantity' => 0]);
    }
}
```

---

## 4. REPOSITORIES

### Command
```bash
php artisan make:class App/Repositories/Inventory/ProductRepository --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Models\Product;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Product::class;
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->query()->where('sku', $sku)->first();
    }

    public function findActive(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->where('is_active', true)
            ->with('category')
            ->paginate($perPage);
    }

    public function findLowStock(int $threshold = 10): Collection
    {
        return $this->query()
            ->where('stock_quantity', '<=', $threshold)
            ->where('is_active', true)
            ->get();
    }
}
```

---

## 5. SERVICES

### Command
```bash
php artisan make:class App/Services/Inventory/ProductService --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Models\Product;
use App\Repositories\Inventory\ProductRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}
```

---

## 6. ACTIONS

### Command
```bash
php artisan make:class App/Actions/Inventory/ProcessStockMovementAction --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\BaseAction;
use App\DTOs\Inventory\StockMovementData;
use App\Models\StockMovement;
use App\Services\Inventory\StockService;
use App\Services\Accounting\LedgerService;

class ProcessStockMovementAction extends BaseAction
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly LedgerService $ledgerService,
    ) {}

    public function execute(StockMovementData $data): StockMovement
    {
        return $this->transaction(function () use ($data): StockMovement {
            $movement = $this->stockService->recordMovement($data);
            $this->ledgerService->recordEntry($movement);

            return $movement;
        });
    }
}
```

---

## 7. FORM REQUESTS

### Command
```bash
php artisan make:request Api/Inventory/StoreProductRequest --no-interaction
php artisan make:request Api/Inventory/UpdateProductRequest --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'sku'            => ['required', 'string', 'max:100', 'unique:products,sku'],
            'description'    => ['nullable', 'string', 'max:2000'],
            'price'          => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'category_id'    => ['required', 'integer', 'exists:categories,id'],
            'is_active'      => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'This SKU is already assigned to another product.',
        ];
    }
}
```

---

## 8. CONTROLLERS

### Command
```bash
php artisan make:controller Api/Inventory/ProductController --no-interaction
```

### Canonical Pattern
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

        return ApiResponse::created(new ProductResource($product), 'Product created');
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

        return ApiResponse::success(new ProductResource($updated), 'Product updated');
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

## 9. API RESOURCES

### Command
```bash
php artisan make:resource Inventory/ProductResource --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ProductResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'sku'            => $this->sku,
            'description'    => $this->description,
            'price'          => $this->price,
            'stock_quantity' => $this->stock_quantity,
            'is_active'      => $this->is_active,
            'category'       => new CategoryResource($this->whenLoaded('category')),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

---

## 10. POLICIES

### Command
```bash
php artisan make:policy ProductPolicy --model=Product --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('product.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('product.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('product.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('product.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('product.delete');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('product.restore');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->hasRole('super-admin');
    }
}
```

---

## 11. DTOs

### Command
```bash
php artisan make:class App/DTOs/Inventory/ProductData --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

use App\DTOs\BaseDTO;
use App\Http\Requests\Api\Inventory\StoreProductRequest;

class ProductData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $sku,
        public readonly float $price,
        public readonly int $categoryId,
        public readonly ?string $description = null,
        public readonly int $stockQuantity = 0,
        public readonly bool $isActive = true,
    ) {}

    public static function fromRequest(StoreProductRequest $request): self
    {
        return new self(
            name:          $request->string('name')->toString(),
            sku:           $request->string('sku')->toString(),
            price:         $request->float('price'),
            categoryId:    $request->integer('category_id'),
            description:   $request->string('description')->toString() ?: null,
            stockQuantity: $request->integer('stock_quantity', 0),
            isActive:      $request->boolean('is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'name'           => $this->name,
            'sku'            => $this->sku,
            'price'          => $this->price,
            'category_id'    => $this->categoryId,
            'description'    => $this->description,
            'stock_quantity' => $this->stockQuantity,
            'is_active'      => $this->isActive,
        ];
    }
}
```

---

## 12. TESTS

### Command
```bash
# Feature test (default)
php artisan make:test Feature/Inventory/ProductTest --no-interaction

# Unit test
php artisan make:test Unit/Inventory/ProductServiceTest --unit --no-interaction
```

### Canonical Pattern (Feature Test)
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_authenticated_admin_can_list_products(): void
    {
        Product::factory(5)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/inventory/products');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'name', 'sku', 'price']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_authenticated_admin_can_create_product(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/inventory/products', [
                'name'        => 'Test Product',
                'sku'         => 'TST-001',
                'price'       => 99.99,
                'category_id' => $category->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'TST-001');

        $this->assertDatabaseHas('products', ['sku' => 'TST-001']);
    }

    public function test_guest_cannot_access_products(): void
    {
        $this->getJson('/api/v1/inventory/products')
            ->assertUnauthorized();
    }

    public function test_product_sku_must_be_unique(): void
    {
        $existing = Product::factory()->create(['sku' => 'DUP-001']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/inventory/products', [
                'name'  => 'Duplicate SKU',
                'sku'   => 'DUP-001',
                'price' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }
}
```

---

## 13. ENUMS

### Command
```bash
php artisan make:enum Enums/OrderStatus --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Shipped   = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded  = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Shipped   => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Refunded  => 'Refunded',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled, self::Refunded]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

---

## 14. EVENTS & LISTENERS

### Command
```bash
php artisan make:event Inventory/ProductCreated --no-interaction
php artisan make:listener Inventory/NotifyLowStock --event=Inventory/ProductCreated --no-interaction
```

### Canonical Pattern (Event)
```php
<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Events;

use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Product $product,
    ) {}
}
```

### Canonical Pattern (Listener)
```php
<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Listeners;

use App\Domains\Inventory\Events\ProductCreated;

class SendProductCreatedNotification
{
    public function handle(ProductCreated $event): void
    {
        // Send notification
    }
}
```

---

## 15. JOBS

### Command
```bash
php artisan make:job Inventory/SyncInventoryFromSupplier --no-interaction
```

### Canonical Pattern
```php
<?php

declare(strict_types=1);

namespace App\Jobs\Inventory;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncInventoryFromSupplier implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(
        public readonly int $supplierId,
    ) {}

    public function handle(): void
    {
        // Job logic here
    }

    public function failed(\Throwable $exception): void
    {
        // Handle failure
    }
}
```

---

## POST-CREATION CHECKLIST

After creating any file:

- [ ] Run `vendor/bin/pint --dirty --format agent`
- [ ] Add `declare(strict_types=1);` at top if PHP file
- [ ] Register routes if it's a controller
- [ ] Register policy in AppServiceProvider if it's a policy
- [ ] Write tests immediately
- [ ] Run `php artisan test --compact --filter={ClassName}`

---

**Owner**: Engineering Team
**Project**: Green Leaf ERP
