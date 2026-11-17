<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Purchasing\PurchaserReadCacheService;
use App\Services\Purchasing\PurchaserWarehouseBalanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaserOptimizationCacheValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $admin;

    private Warehouse $warehouse;

    private Category $category;

    private Product $product;

    private PurchaserReadCacheService $cacheService;

    private PurchaserWarehouseBalanceService $balanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::create([
            'name' => 'Central Warehouse',
            'code' => 'WH-VAL',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Tomato Hybrid',
            'sku' => 'TOM-HYB-VAL',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $this->cacheService = app(PurchaserReadCacheService::class);
        $this->balanceService = app(PurchaserWarehouseBalanceService::class);
    }

    public function test_cache_keys_segregate_by_business_date_and_warehouse(): void
    {
        $keyDate1 = $this->cacheService->buildKey(
            scopes: ['products'],
            dataset: 'stock_test',
            businessDate: '2026-11-18',
            filters: ['warehouse_id' => 1]
        );

        $keyDate2 = $this->cacheService->buildKey(
            scopes: ['products'],
            dataset: 'stock_test',
            businessDate: '2026-11-19',
            filters: ['warehouse_id' => 1]
        );

        $keyWh2 = $this->cacheService->buildKey(
            scopes: ['products'],
            dataset: 'stock_test',
            businessDate: '2026-11-18',
            filters: ['warehouse_id' => 2]
        );

        $this->assertNotEquals($keyDate1, $keyDate2, 'Different business dates must have distinct cache keys');
        $this->assertNotEquals($keyDate1, $keyWh2, 'Different warehouses must have distinct cache keys');
    }

    public function test_stock_movement_creation_and_deletion_invalidates_products_scope(): void
    {
        $vInitial = $this->cacheService->getScopeVersion('products');

        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'reference' => 'BAT-VAL-01',
            'total_kg' => 50.0,
            'available_kg' => 50.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted->value,
            'received_date' => '2026-11-18',
            'received_at' => now(),
            'created_by' => $this->admin->id,
            'warehouse_receive_pending' => false,
        ]);

        $vAfterBatch = $this->cacheService->getScopeVersion('products');
        $this->assertGreaterThan($vInitial, $vAfterBatch, 'Batch creation must increment products scope version');

        $sm = StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_id' => $batch->id,
            'type' => StockMovementType::In->value,
            'quantity' => 25.0,
            'cost_per_unit' => 20.0,
            'grade' => 'A',
            'created_by' => $this->admin->id,
        ]);

        $vAfterSm = $this->cacheService->getScopeVersion('products');
        $this->assertGreaterThan($vAfterBatch, $vAfterSm, 'StockMovement creation must increment products scope version');

        $sm->delete();
        $vAfterDelete = $this->cacheService->getScopeVersion('products');
        $this->assertGreaterThan($vAfterSm, $vAfterDelete, 'StockMovement deletion must increment products scope version');
    }

    public function test_stock_adjustment_and_wastage_invalidates_cache(): void
    {
        $vInitial = $this->cacheService->getScopeVersion('products');

        $adj = StockAdjustment::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'A',
            'created_by' => $this->admin->id,
            'business_date' => '2026-11-18',
            'system_qty' => 10.0,
            'counted_qty' => 15.0,
            'variance_qty' => 5.0,
            'category' => 'physical_count',
            'notes' => 'Test adjustment',
        ]);

        $vAfterAdj = $this->cacheService->getScopeVersion('products');
        $this->assertGreaterThan($vInitial, $vAfterAdj, 'StockAdjustment must increment products scope version');

        $wastage = WastageEntry::create([
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 2.0,
            'cost_per_kg' => 20.0,
            'recorded_by' => $this->admin->id,
            'wastage_date' => '2026-11-18',
            'reason' => WastageReason::Rotten->value,
        ]);

        $vAfterWastage = $this->cacheService->getScopeVersion('products');
        $this->assertGreaterThan($vAfterAdj, $vAfterWastage, 'WastageEntry must increment products scope version');
    }

    public function test_purchaser_cart_and_cart_items_invalidate_carts_scope(): void
    {
        $vInitial = $this->cacheService->getScopeVersion('carts');

        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'cart_number' => 'CART-TEST-01',
            'business_date' => '2026-11-18',
            'status' => 'draft',
            'purchase_grade' => 'A',
        ]);

        $vAfterCart = $this->cacheService->getScopeVersion('carts');
        $this->assertGreaterThan($vInitial, $vAfterCart, 'PurchaserCart must increment carts scope version');

        $item = PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10.0,
            'unit_price' => 25.0,
            'line_total' => 250.0,
        ]);

        $vAfterItem = $this->cacheService->getScopeVersion('carts');
        $this->assertGreaterThan($vAfterCart, $vAfterItem, 'PurchaserCartItem must increment carts scope version');
    }

    public function test_transaction_rollback_does_not_fire_after_commit_cache_invalidation(): void
    {
        $vInitial = $this->cacheService->getScopeVersion('products');

        try {
            DB::transaction(function () {
                StockAdjustment::create([
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'grade' => 'A',
                    'created_by' => $this->admin->id,
                    'business_date' => '2026-11-18',
                    'system_qty' => 10.0,
                    'counted_qty' => 999.0,
                    'variance_qty' => 989.0,
                    'category' => 'physical_count',
                ]);
                throw new \RuntimeException('Simulated transaction rollback');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $vAfterRollback = $this->cacheService->getScopeVersion('products');
        $this->assertSame($vInitial, $vAfterRollback, 'Transaction rollback must NOT increment cache version');
    }

    public function test_cache_service_gracefully_falls_back_when_store_throws_exception(): void
    {
        $brokenService = new PurchaserReadCacheService(store: 'non_existent_redis_driver_store');

        $executed = false;
        $result = $brokenService->remember(
            scopes: ['products'],
            dataset: 'test_fallback',
            ttlSeconds: 60,
            callback: function () use (&$executed): string {
                $executed = true;

                return 'direct_callback_value';
            }
        );

        $this->assertTrue($executed, 'Callback must execute directly when cache store fails');
        $this->assertSame('direct_callback_value', $result);
    }
}
