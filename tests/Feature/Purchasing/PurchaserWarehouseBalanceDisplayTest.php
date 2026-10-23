<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\PurchaserReadCacheService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaserWarehouseBalanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $admin;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Category $category;

    private Product $tomatoH;

    private Product $tomatoN;

    private Shop $shop;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouseA = Warehouse::create([
            'name' => 'Central Warehouse A',
            'code' => 'WH-A',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Secondary Warehouse B',
            'code' => 'WH-B',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Supply',
            'is_active' => true,
        ]);

        $this->tomatoH = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Tomato H',
            'sku' => 'TOM-H',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouseA->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $this->tomatoN = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Tomato N',
            'sku' => 'TOM-N',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouseA->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $this->shop = Shop::create([
            'name' => 'Casio Shop',
            'code' => 'SH-CASIO',
            'is_active' => true,
        ]);

        $this->date = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();
    }

    private function createStock(Product $product, Warehouse $warehouse, float $qtyKg, string $grade = 'A', ?string $date = null): StockBatch
    {
        $movementDate = $date ?? $this->date;

        $batch = StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'reference' => 'BAT-'.Str::upper(Str::random(6)),
            'total_kg' => $qtyKg,
            'available_kg' => $qtyKg,
            'cost_per_kg' => 25.0,
            'status' => 'sorted',
            'received_date' => $movementDate,
            'received_at' => $movementDate,
            'created_at' => Carbon::parse($movementDate)->setTime(9, 0, 0),
            'created_by' => $this->admin->id,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => Carbon::parse($movementDate)->setTime(9, 0, 0),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'grade' => $grade,
            'type' => StockMovementType::In->value,
            'quantity' => $qtyKg,
            'unit' => 'kg',
            'cost_per_unit' => 25.0,
            'created_by' => $this->admin->id,
            'created_at' => Carbon::parse($movementDate)->setTime(9, 0, 0),
        ]);

        return $batch;
    }

    private function createOrderDemand(Product $product, float $qty, string $grade = 'A', ?string $date = null): ShopOrderItem
    {
        $orderDate = $date ?? $this->date;
        $dailyKey = ShopOrder::dailyOrderKey((int) $this->shop->id, Carbon::parse($orderDate)->toDateString());
        $order = ShopOrder::firstOrCreate(
            [
                'shop_daily_order_key' => $dailyKey,
            ],
            [
                'shop_id' => $this->shop->id,
                'business_date' => $orderDate,
                'order_number' => 'ORD-'.Str::upper(Str::random(8)),
                'state' => 'approved',
                'total_amount' => 1000.0,
                'created_by' => $this->admin->id,
                'created_at' => Carbon::parse($orderDate)->setTime(8, 0, 0),
            ]
        );

        return ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => $grade,
            'requested_qty' => $qty,
            'approved_qty' => $qty,
            'unit' => 'kg',
            'price' => 30.0,
        ]);
    }

    public function test_positive_and_zero_warehouse_balance_rendered_on_daily_and_bulk_buy(): void
    {
        // Tomato H has 40.0 kg warehouse stock (Warehouse A: 25 kg Grade A, 15 kg Grade B)
        $this->createStock($this->tomatoH, $this->warehouseA, 25.0, 'A');
        $this->createStock($this->tomatoH, $this->warehouseA, 15.0, 'B');

        // Tomato N has zero stock
        // Create demand for both
        $this->createOrderDemand($this->tomatoH, 170.0, 'A');
        $this->createOrderDemand($this->tomatoN, 50.0, 'A');

        app(PurchaserReadCacheService::class)->invalidate(['orders', 'carts', 'products', 'settings']);

        // 1. Check /purchaser/daily
        $dailyRes = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
        ]));
        $dailyRes->assertOk();
        $dailyRes->assertSee('Stock:');
        $dailyRes->assertSee('40.0 kg');
        $dailyRes->assertSee('0.0 kg');
        $dailyRes->assertSee('Tomato H');
        $dailyRes->assertSee('Tomato N');

        // 2. Check /purchaser/bulk-buy
        $bulkRes = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
        ]));
        $bulkRes->assertOk();
        $bulkRes->assertSee('Stock:');
        $bulkRes->assertSee('40.0 kg');
        $bulkRes->assertSee('0.0 kg');
        $bulkRes->assertSee('Tomato H');
        $bulkRes->assertSee('Tomato N');
    }

    public function test_multi_grade_breakdown_displays_correct_grade_level_warehouse_balance(): void
    {
        // Tomato H has multi-grade demand: Grade A 100 kg, Grade B 70 kg = 170 kg
        $this->createOrderDemand($this->tomatoH, 100.0, 'A');
        $this->createOrderDemand($this->tomatoH, 70.0, 'B');

        // Stock: Grade A 25 kg, Grade B 15 kg = 40 kg total
        $this->createStock($this->tomatoH, $this->warehouseA, 25.0, 'A');
        $this->createStock($this->tomatoH, $this->warehouseA, 15.0, 'B');

        app(PurchaserReadCacheService::class)->invalidate(['orders', 'carts', 'products', 'settings']);

        // Daily page
        $dailyRes = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
        ]));
        $dailyRes->assertOk();
        $dailyRes->assertSee('Stock:');
        $dailyRes->assertSee('40.0 kg');
        // Grade breakdown shows individual grade balances
        $dailyRes->assertSee('Whse 25.0 kg');
        $dailyRes->assertSee('Whse 15.0 kg');

        // Bulk Buy page
        $bulkRes = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
        ]));
        $bulkRes->assertOk();
        $bulkRes->assertSee('Stock:');
        $bulkRes->assertSee('40.0 kg');
        $bulkRes->assertSee('Whse Stock');
        $bulkRes->assertSee('25.0 kg');
        $bulkRes->assertSee('15.0 kg');
    }

    public function test_multiple_warehouse_stock_aggregation_and_scoping(): void
    {
        // Warehouse A: 25 kg, Warehouse B: 15 kg = 40 kg total
        $this->createStock($this->tomatoH, $this->warehouseA, 25.0, 'A');
        $this->createStock($this->tomatoH, $this->warehouseB, 15.0, 'A');

        $this->createOrderDemand($this->tomatoH, 100.0, 'A');

        app(PurchaserReadCacheService::class)->invalidate(['orders', 'carts', 'products', 'settings']);

        // Without warehouse_id: aggregates all warehouses (40.0 kg)
        $resAll = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
        ]));
        $resAll->assertOk();
        $resAll->assertSee('40.0 kg');

        // Scoped to Warehouse A: only 25.0 kg
        $resWhA = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
            'warehouse_id' => $this->warehouseA->id,
        ]));
        $resWhA->assertOk();
        $resWhA->assertSee('25.0 kg');

        // Matching result on bulk-buy with warehouse scoping
        $bulkWhA = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
            'warehouse_id' => $this->warehouseA->id,
        ]));
        $bulkWhA->assertOk();
        $bulkWhA->assertSee('25.0 kg');
    }

    public function test_pending_receipts_are_excluded_until_warehouse_confirmed(): void
    {
        $this->createOrderDemand($this->tomatoH, 100.0, 'A');

        // Create a batch that is NOT yet warehouse confirmed (pending receipt)
        StockBatch::create([
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouseA->id,
            'reference' => 'BAT-PENDING-01',
            'total_kg' => 50.0,
            'available_kg' => 50.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending->value,
            'received_date' => $this->date,
            'received_at' => $this->date,
            'created_at' => Carbon::parse($this->date)->setTime(8, 0, 0),
            'created_by' => $this->admin->id,
            'warehouse_receive_pending' => true, // Receiver has NOT confirmed
        ]);

        app(PurchaserReadCacheService::class)->invalidate(['orders', 'carts', 'products', 'settings']);

        // Balance must be 0.0 kg because warehouse has not confirmed the receipt
        $res = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
        ]));
        $res->assertOk();
        $res->assertSee('0.0 kg');

        // Confirm warehouse receipt
        StockBatch::where('reference', 'BAT-PENDING-01')->update([
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => Carbon::parse($this->date)->setTime(10, 0, 0),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);

        $resConfirmed = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
        ]));
        $resConfirmed->assertOk();
        $resConfirmed->assertSee('50.0 kg');
    }

    public function test_historical_dates_do_not_reflect_future_stock(): void
    {
        $pastDate = Carbon::parse($this->date)->subDays(3)->toDateString();

        // Order on past date
        $this->createOrderDemand($this->tomatoH, 100.0, 'A', $pastDate);

        // Stock received TODAY (future relative to pastDate)
        $this->createStock($this->tomatoH, $this->warehouseA, 50.0, 'A', $this->date);

        app(PurchaserReadCacheService::class)->invalidate(['orders', 'carts', 'products', 'settings']);

        // Admin can view historical dates
        $resPast = $this->actingAs($this->admin)->get(route('purchaser.daily', [
            'date' => $pastDate,
            'purchase_grade' => 'ALL',
        ]));
        $resPast->assertOk();
        // On past date, stock was 0.0 kg
        $resPast->assertSee('0.0 kg');
        $resPast->assertDontSee('50.0 kg');

        // On current date, stock is 50.0 kg
        $this->createOrderDemand($this->tomatoH, 100.0, 'A', $this->date);
        $resCurrent = $this->actingAs($this->admin)->get(route('purchaser.daily', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
        ]));
        $resCurrent->assertOk();
        $resCurrent->assertSee('50.0 kg');
    }

    public function test_warehouse_balance_is_informational_only_and_does_not_reduce_need_or_left(): void
    {
        // Demand: Need = 170.0 kg, Bought = 0, Left = 170.0 kg
        $this->createOrderDemand($this->tomatoH, 170.0, 'A');

        // Stock in warehouse = 40.0 kg
        $this->createStock($this->tomatoH, $this->warehouseA, 40.0, 'A');

        app(PurchaserReadCacheService::class)->invalidate(['orders', 'carts', 'products', 'settings']);

        $res = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
        ]));
        $res->assertOk();

        // Stock displays 40.0 kg
        $res->assertSee('Stock:');
        $res->assertSee('40.0 kg');

        // Need and Left remain 170.00 kg (NOT reduced by 40 kg to 130 kg)
        $res->assertSee('Need 170.00 kg');
        $res->assertSee('Left 170.00 kg');

        // On bulk buy as well
        $bulkRes = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', [
            'date' => $this->date,
            'purchase_grade' => 'ALL',
        ]));
        $bulkRes->assertOk();
        $bulkRes->assertSee('Stock:');
        $bulkRes->assertSee('40.0 kg');
        $bulkRes->assertSee('Need: 170.0 kg');
        $bulkRes->assertSee('Left: 170.0');
    }
}
