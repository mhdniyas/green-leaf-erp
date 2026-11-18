<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoadoutPerformanceBoundedQueriesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Shop $shop;

    private ShopPriceGroup $priceGroup;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00'));

        $this->user = User::factory()->create();
        $this->user->assignRole('warehouse_receiver');
        $this->user->givePermissionTo([
            'warehouse.loadout.all',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'VEG-WH',
            'is_active' => true,
        ]);
        $this->user->warehouses()->attach($this->warehouse->id);

        $this->priceGroup = ShopPriceGroup::create([
            'name' => 'A',
            'description' => 'Retail Group A',
            'is_active' => true,
        ]);

        $this->shop = Shop::create([
            'name' => 'Green Leaf Fresh Mart',
            'code' => 'GL-001',
            'price_group_id' => $this->priceGroup->id,
            'warehouse_tag' => 'VEG',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);
    }

    public function test_loadout_show_endpoint_executes_within_bounded_queries_regardless_of_pending_batch_count(): void
    {
        // 1. Create 5 distinct products with prices
        $products = [];
        for ($i = 1; $i <= 5; $i++) {
            $product = Product::create([
                'name' => "Product {$i}",
                'sku' => "SKU-00{$i}",
                'category_id' => $this->category->id,
                'default_warehouse_id' => $this->warehouse->id,
                'unit' => 'KG',
                'is_active' => true,
            ]);

            DailyPriceApproval::create([
                'product_id' => $product->id,
                'business_date' => '2026-11-18',
                'grade' => ProductGrade::GradeA->value,
                'price_a' => 50.00,
                'price_b' => 45.00,
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $this->user->id,
            ]);

            $products[] = $product;
        }

        // 2. Create a shop order with 5 items
        $shopOrder = ShopOrder::create([
            'order_number' => 'RQ-PERF-TEST-001',
            'shop_id' => $this->shop->id,
            'business_date' => '2026-11-18',
            'delivery_status' => 'pending_delivery',
            'state' => 'approved',
            'order_source' => 'shop_owner',
            'created_by' => $this->user->id,
        ]);

        foreach ($products as $product) {
            ShopOrderItem::create([
                'shop_order_id' => $shopOrder->id,
                'product_id' => $product->id,
                'product_grade' => 'A',
                'requested_qty' => 10.0,
                'approved_qty' => 10.0,
                'loaded_qty' => 0.0,
                'unit' => 'KG',
                'requested_unit' => 'KG',
                'sorting_status' => 'pending',
            ]);
        }

        // 3. Seed 50 historical pending batches across multiple dates with wastage and movements
        // (This simulates an accumulated backlog of batches that previously triggered 4,707 queries)
        for ($b = 1; $b <= 50; $b++) {
            $product = $products[$b % count($products)];
            $batchDate = Carbon::parse('2026-11-01')->addDays($b % 15)->toDateString();

            $batch = StockBatch::create([
                'reference' => "BATCH-HIST-{$b}",
                'product_id' => $product->id,
                'warehouse_id' => $this->warehouse->id,
                'purchase_grade' => $b % 2 === 0 ? 'A' : 'U',
                'total_kg' => 100.0,
                'cost_per_kg' => 30.0,
                'status' => BatchStatus::Pending,
                'warehouse_receive_pending' => false,
                'received_at' => $batchDate,
                'created_by' => $this->user->id,
            ]);

            if ($b % 3 === 0) {
                WastageEntry::create([
                    'batch_id' => $batch->id,
                    'product_id' => $product->id,
                    'grade' => 'U',
                    'quantity' => 5.0,
                    'cost_per_kg' => 30.0,
                    'reason' => WastageReason::TransitDamage,
                    'wastage_date' => $batchDate,
                    'recorded_by' => $this->user->id,
                ]);
            }

            if ($b % 2 === 0) {
                StockMovement::create([
                    'batch_id' => $batch->id,
                    'product_id' => $product->id,
                    'type' => StockMovementType::Out->value,
                    'grade' => 'U',
                    'quantity' => 10.0,
                    'cost_per_unit' => 30.0,
                    'created_by' => $this->user->id,
                ]);
            }
        }

        // 4. Execute request and measure query count
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/warehouse/loadout/{$shopOrder->order_number}");

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'order' => [
                'order_number' => 'RQ-PERF-TEST-001',
            ],
        ]);

        $data = $response->json();
        $this->assertCount(5, $data['product_groups']);
        $this->assertFalse($data['pricing_diagnostics']['has_unpriced']);

        // Assert query count is strictly bounded (<= 30 queries) regardless of batch count
        $this->assertLessThanOrEqual(
            30,
            $queryCount,
            "Expected loadout show to run in <= 30 queries, but it executed {$queryCount} queries."
        );

        // 5. Add 50 more historical batches and assert query count remains exactly O(1)
        for ($b = 51; $b <= 100; $b++) {
            $product = $products[$b % count($products)];
            $batchDate = Carbon::parse('2026-11-01')->addDays($b % 15)->toDateString();

            StockBatch::create([
                'reference' => "BATCH-HIST-{$b}",
                'product_id' => $product->id,
                'warehouse_id' => $this->warehouse->id,
                'purchase_grade' => $b % 2 === 0 ? 'A' : 'U',
                'total_kg' => 100.0,
                'cost_per_kg' => 30.0,
                'status' => BatchStatus::Pending,
                'warehouse_receive_pending' => false,
                'received_at' => $batchDate,
                'created_by' => $this->user->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $secondResponse = $this->actingAs($this->user)
            ->getJson("/api/v1/warehouse/loadout/{$shopOrder->order_number}");

        $secondQueries = DB::getQueryLog();
        $secondQueryCount = count($secondQueries);

        $secondResponse->assertOk();
        $this->assertLessThanOrEqual(
            30,
            $secondQueryCount,
            "Query count should remain bounded (<= 30) despite adding 50 more historical batches, but got {$secondQueryCount}."
        );
    }
}
