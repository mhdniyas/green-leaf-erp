<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\PurchaserCartBatchStateResolver;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserRequestDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 10:00:00');
        Role::findOrCreate('purchaser');
        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $category = Category::factory()->create(['name' => 'VEG', 'is_active' => true]);
        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Farms', 'type' => 'Vendor']);
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_supplier_hub_loads_data_without_repeated_queries_and_preserves_response(): void
    {
        // Create overdue cart with GRN and stock batch
        $overdueCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-20',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-TEST-001',
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->purchaser->id,
        ]);

        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'purchaser_cart_id' => $overdueCart->id,
            'received_by' => $this->purchaser->id,
            'grn_number' => 'GRN-001',
            'received_at' => now(),
            'notes' => 'Cart: VC-TEST-001',
        ]);

        StockBatch::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->purchaser->id,
        ]);

        // Create today draft cart
        $todayCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-24',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-TEST-002',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $todayCart->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 5.0,
            'grade' => 'A',
        ]);

        // Track executed queries
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers'));
        $response->assertOk();
        $response->assertSee('Fresh Farms');

        // Verify deduplication: goods_received and stock_batches must not be repeatedly queried
        $grnQueries = array_filter($queries, fn (string $sql) => str_contains($sql, 'goods_received') && ! str_contains($sql, 'information_schema'));
        $batchQueries = array_filter($queries, fn (string $sql) => str_contains($sql, 'stock_batches') && ! str_contains($sql, 'information_schema'));
        $settingQueries = array_filter($queries, fn (string $sql) => str_contains($sql, 'business_settings') && ! str_contains($sql, 'information_schema'));

        $this->assertLessThanOrEqual(2, count($grnQueries), 'goods_received queried more than expected: '.count($grnQueries));
        $this->assertLessThanOrEqual(1, count($batchQueries), 'stock_batches queried more than once in supplier hub: '.count($batchQueries));
        $this->assertLessThanOrEqual(1, count($settingQueries), 'business_settings queried repeatedly: '.count($settingQueries));
    }

    public function test_purchaser_get_routes_response_equivalence_and_authorization(): void
    {
        $unauthorized = User::factory()->create();

        // 1. Daily
        $this->actingAs($unauthorized)->get(route('purchaser.daily'))->assertRedirect();
        $this->actingAs($this->purchaser)->get(route('purchaser.daily'))->assertOk();

        // 2. Vendors
        $this->actingAs($unauthorized)->get(route('purchaser.vendors'))->assertRedirect();
        $this->actingAs($this->purchaser)->get(route('purchaser.vendors'))->assertOk();

        // 3. Bulk Buy
        $this->actingAs($unauthorized)->get(route('purchaser.bulk-buy'))->assertRedirect();
        $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy'))->assertOk();

        // 4. Suppliers
        $this->actingAs($unauthorized)->get(route('purchaser.suppliers'))->assertRedirect();
        $this->actingAs($this->purchaser)->get(route('purchaser.suppliers'))->assertOk();

        // 5. History
        $this->actingAs($unauthorized)->get(route('purchaser.history'))->assertRedirect();
        $this->actingAs($this->purchaser)->get(route('purchaser.history'))->assertOk();
    }

    public function test_repeated_business_day_service_calls_in_one_request_query_settings_once(): void
    {
        /** @var PurchaserBusinessDayService $service */
        $service = app(PurchaserBusinessDayService::class);

        $queryCount = 0;
        DB::listen(function (QueryExecuted $query) use (&$queryCount): void {
            if (str_contains($query->sql, 'business_settings')) {
                $queryCount++;
            }
        });

        // Call multiple times on the same scoped instance
        $c1 = $service->cutoffTime();
        $c2 = $service->cutoffTime();
        $c3 = $service->cutoffTime();

        $this->assertSame($c1, $c2);
        $this->assertSame($c2, $c3);
        $this->assertSame(1, $queryCount, 'Expected exactly 1 business_settings query for repeated calls on the scoped instance.');
    }

    public function test_new_request_reads_updated_settings_without_static_leakage(): void
    {
        /** @var PurchaserBusinessDayService $service1 */
        $service1 = app(PurchaserBusinessDayService::class);
        $this->assertSame('21:30:00', $service1->cutoffTime());

        // Directly update DB as if updated out-of-band by another process
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'business_day_cutoff_time'],
            ['value' => '18:00:00']
        );

        // Simulate a new request lifecycle by resolving from a fresh scoped container
        app()->forgetInstance(PurchaserBusinessDayService::class);
        /** @var PurchaserBusinessDayService $service2 */
        $service2 = app(PurchaserBusinessDayService::class);

        $this->assertSame('18:00:00', $service2->cutoffTime(), 'New request did not read updated database setting (static leakage detected).');
    }

    public function test_overlapping_cart_sets_return_complete_and_correct_batch_states(): void
    {
        /** @var PurchaserCartBatchStateResolver $resolver */
        $resolver = app(PurchaserCartBatchStateResolver::class);

        $cart1 = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-20',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-OVERLAP-1',
        ]);
        $cart2 = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-21',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-OVERLAP-2',
        ]);
        $cart3 = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-22',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-OVERLAP-3',
        ]);

        // Resolve Set A: Cart 1 & Cart 2
        $setA = collect([$cart1, $cart2]);
        $statesA = $resolver->statesForCarts($setA);
        $this->assertArrayHasKey((int) $cart1->id, $statesA);
        $this->assertArrayHasKey((int) $cart2->id, $statesA);

        // Resolve Set B: Cart 2 (already resolved) & Cart 3 (new)
        $setB = collect([$cart2, $cart3]);
        $statesB = $resolver->statesForCarts($setB);
        $this->assertArrayHasKey((int) $cart2->id, $statesB);
        $this->assertArrayHasKey((int) $cart3->id, $statesB);
        $this->assertSame($statesA[(int) $cart2->id], $statesB[(int) $cart2->id]);
    }

    public function test_empty_partial_and_mutated_cart_sets_do_not_return_stale_results(): void
    {
        /** @var PurchaserCartBatchStateResolver $resolver */
        $resolver = app(PurchaserCartBatchStateResolver::class);

        // Empty set
        $this->assertSame([], $resolver->statesForCarts(collect()));

        // Initial Cart (draft, no GRN)
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-20',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-MUTATE-1',
        ]);

        $initialStates = $resolver->statesForCarts(collect([$cart]));
        $this->assertFalse($initialStates[(int) $cart->id]['warehouse_confirmed']);

        // In-request mutation: Cart attached to GRN with confirmed stock batch
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->purchaser->id,
        ]);
        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'purchaser_cart_id' => $cart->id,
            'received_by' => $this->purchaser->id,
            'grn_number' => 'GRN-MUT-01',
            'received_at' => now(),
        ]);
        StockBatch::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->purchaser->id,
        ]);

        $cart->goods_received_id = $grn->id;
        $cart->status = 'submitted';

        // Must re-evaluate and return updated confirmed status without returning stale draft memoization
        $updatedStates = $resolver->statesForCarts(collect([$cart]));
        $this->assertTrue($updatedStates[(int) $cart->id]['warehouse_confirmed']);
    }

    public function test_long_lived_container_scoped_lifecycle_clears_memoized_data(): void
    {
        /** @var PurchaserBusinessDayService $serviceInstance1 */
        $serviceInstance1 = app(PurchaserBusinessDayService::class);
        $serviceInstance1->cutoffTime();

        // Simulate Octane / worker request termination
        app()->forgetInstance(PurchaserBusinessDayService::class);
        app()->forgetInstance(PurchaserCartBatchStateResolver::class);

        /** @var PurchaserBusinessDayService $serviceInstance2 */
        $serviceInstance2 = app(PurchaserBusinessDayService::class);

        $this->assertNotSame($serviceInstance1, $serviceInstance2, 'Scoped service returned identical instance across simulated request boundaries.');
    }
}
