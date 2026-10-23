<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Warehouse\AutoLoadAllService;
use App\Services\Warehouse\WarehouseScopedLoadoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutoLoadAllMultiGradeSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Shop $shop;

    private Product $tomato;

    private Product $potato;

    private Carbon $businessDate;

    private AutoLoadAllService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessDate = Carbon::parse('2026-10-21');
        $this->mock(PurchaserBusinessDayService::class, function ($mock): void {
            $mock->shouldReceive('operationalDate')->andReturn($this->businessDate);
        });

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'warehouse_receiver', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Hub', 'code' => 'WH-MAIN']);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Hybrid',
            'sku' => 'VEG-TOM-01',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);

        $this->potato = Product::factory()->create([
            'name' => 'Potato Russet',
            'sku' => 'VEG-POT-01',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);

        $priceGroup = ShopPriceGroup::firstOrCreate(
            ['name' => 'A'],
            ['code' => 'A', 'is_active' => true]
        );

        $this->shop = Shop::factory()->create([
            'name' => 'Fresh Mart',
            'code' => 'SH-FRESH',
            'shop_price_group_id' => $priceGroup->id,
            'status' => 'active',
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        BusinessSetting::updateOrCreate(
            ['key' => 'allow_historical_invoice_repricing'],
            ['value' => '1']
        );

        // Seed DailyPriceApprovals for testing invoice repricing
        foreach ([$this->tomato, $this->potato] as $p) {
            foreach (['A', 'B', 'C'] as $g) {
                DailyPriceApproval::create([
                    'product_id' => $p->id,
                    'business_date' => $this->businessDate,
                    'grade' => $g,
                    'purchase_price' => 20.0,
                    'price_unit' => 'KG',
                    'price_a' => 50.0,
                    'price_b' => 45.0,
                    'price_c' => 40.0,
                    'price_d' => 35.0,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $this->admin->id,
                ]);
            }
        }

        // Seed sorted stock batches with distinct grades
        $this->createStockBatch($this->tomato, ProductGrade::GradeA, 100.0);
        $this->createStockBatch($this->tomato, ProductGrade::GradeB, 50.0);
        $this->createStockBatch($this->potato, ProductGrade::GradeC, 30.0);

        $this->service = app(AutoLoadAllService::class);
    }

    private function createStockBatch(Product $product, ProductGrade $grade, float $qty): StockBatch
    {
        static $seq = 1;
        $batch = StockBatch::create([
            'reference' => 'BATCH-TEST-'.($seq++),
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => BatchStatus::Sorted->value,
            'purchase_grade' => $grade->value,
            'sorted_at' => now(),
            'sorted_by' => $this->admin->id,
            'received_at' => now(),
            'created_by' => $this->admin->id,
            'cost_per_kg' => 20.0,
            'initial_weight' => $qty,
            'total_sorted_weight' => $qty,
            'total_kg' => $qty,
            'warehouse_receive_pending' => false,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'created_by' => $this->admin->id,
            'grade' => $grade->value,
            'type' => StockMovementType::In->value,
            'quantity' => $qty,
            'cost_per_unit' => 20.0,
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Initial sorted yield',
        ]);

        return $batch;
    }

    /**
     * Test 1: Grade A-only orders continue working and load fully.
     */
    public function test_01_grade_a_only_orders_continue_working_and_load_fully(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
            'line_total' => 750.0,
        ]);

        $result = $this->service->loadOrder($order, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['loaded_items_count']);

        $item->refresh();
        $this->assertSame('15.00', (string) $item->loaded_qty);
        $this->assertSame('loaded', $item->sorting_status);
        $this->assertSame('A', (string) $item->product_grade);

        $order->refresh();
        $this->assertSame('ready_for_dispatch', $order->delivery_status);

        $movement = StockMovement::where('shop_order_item_id', $item->id)->firstOrFail();
        $this->assertSame('A', $movement->grade->value);
        $this->assertSame('15.000', (string) $movement->quantity);
    }

    /**
     * Test 2: One product ordered in Grade A and B creates two distinct loadout lines.
     */
    public function test_02_one_product_ordered_in_grade_a_and_b_creates_distinct_lines(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
            'line_total' => 1000.0,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 45.0,
            'line_total' => 450.0,
        ]);

        $result = $this->service->loadOrder($order, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['loaded_items_count']);

        $itemA->refresh();
        $itemB->refresh();

        // Ensure both lines remain intact with their respective identities and grades
        $this->assertSame('20.00', (string) $itemA->loaded_qty);
        $this->assertSame('A', (string) $itemA->product_grade);
        $this->assertSame('loaded', $itemA->sorting_status);

        $this->assertSame('10.00', (string) $itemB->loaded_qty);
        $this->assertSame('B', (string) $itemB->product_grade);
        $this->assertSame('loaded', $itemB->sorting_status);

        $this->assertSame(2, $order->items()->count());

        $movementA = StockMovement::where('shop_order_item_id', $itemA->id)->firstOrFail();
        $this->assertSame('A', $movementA->grade->value);
        $this->assertSame('20.000', (string) $movementA->quantity);

        $movementB = StockMovement::where('shop_order_item_id', $itemB->id)->firstOrFail();
        $this->assertSame('B', $movementB->grade->value);
        $this->assertSame('10.000', (string) $movementB->quantity);
    }

    /**
     * Test 3: Grade C stock is deducted from Grade C only.
     */
    public function test_03_grade_c_stock_is_deducted_from_grade_c_only(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $itemC = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->potato->id,
            'product_grade' => 'C',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 40.0,
            'line_total' => 200.0,
        ]);

        $result = $this->service->loadOrder($order, $this->admin);

        $this->assertTrue($result['success']);

        $itemC->refresh();
        $this->assertSame('5.00', (string) $itemC->loaded_qty);
        $this->assertSame('C', (string) $itemC->product_grade);

        $movement = StockMovement::where('shop_order_item_id', $itemC->id)->firstOrFail();
        $this->assertSame('C', $movement->grade->value);
        $this->assertSame('5.000', (string) $movement->quantity);
    }

    /**
     * Test 4: Two different products with different grades remain separate.
     */
    public function test_04_two_different_products_with_different_grades_remain_separate(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $itemTomB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 8.0,
            'approved_qty' => 8.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 45.0,
            'line_total' => 360.0,
        ]);

        $itemPotC = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->potato->id,
            'product_grade' => 'C',
            'requested_qty' => 12.0,
            'approved_qty' => 12.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 40.0,
            'line_total' => 480.0,
        ]);

        $result = $this->service->loadOrder($order, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['loaded_items_count']);

        $itemTomB->refresh();
        $itemPotC->refresh();

        $this->assertSame('8.00', (string) $itemTomB->loaded_qty);
        $this->assertSame($this->tomato->id, $itemTomB->product_id);
        $this->assertSame('B', (string) $itemTomB->product_grade);

        $this->assertSame('12.00', (string) $itemPotC->loaded_qty);
        $this->assertSame($this->potato->id, $itemPotC->product_id);
        $this->assertSame('C', (string) $itemPotC->product_grade);
    }

    /**
     * Test 5: Unapproved orders are excluded.
     */
    public function test_05_unapproved_orders_are_excluded(): void
    {
        $order = ShopOrder::factory()->create([
            'state' => 'submitted', // Not approved
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => null,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
        ]);

        $eligible = $this->service->eligibleOrders($this->businessDate->toDateString());
        $this->assertFalse($eligible->contains('id', $order->id));

        $result = $this->service->loadOrder($order, $this->admin);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not approved', $result['message']);
    }

    /**
     * Test 6: Cancelled or unapproved items are excluded.
     */
    public function test_06_cancelled_and_unapproved_items_are_excluded(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $approvedItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
        ]);

        $cancelledItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->potato->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 0.0, // Cancelled / rejected
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
        ]);

        $result = $this->service->loadOrder($order, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['loaded_items_count']);
        $this->assertSame(1, $result['skipped_items_count']);

        $approvedItem->refresh();
        $cancelledItem->refresh();

        $this->assertSame('10.00', (string) $approvedItem->loaded_qty);
        $this->assertSame('loaded', $approvedItem->sorting_status);

        $this->assertNull($cancelledItem->loaded_qty);
        $this->assertSame('allocated', $cancelledItem->sorting_status);
        $this->assertFalse(StockMovement::where('shop_order_item_id', $cancelledItem->id)->exists());
    }

    /**
     * Test 7: Manual and scheduled paths produce equivalent results.
     */
    public function test_07_manual_and_scheduled_paths_produce_equivalent_results(): void
    {
        // 1. Order for scheduled execution
        $orderScheduled = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $scheduledItem = ShopOrderItem::create([
            'shop_order_id' => $orderScheduled->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 45.0,
            'line_total' => 450.0,
        ]);

        // Run scheduled command
        $this->artisan('greenleaf:auto-load-all', ['--force' => true])
            ->assertExitCode(0);

        $scheduledItem->refresh();
        $this->assertSame('10.00', (string) $scheduledItem->loaded_qty);
        $this->assertSame('loaded', $scheduledItem->sorting_status);

        // 2. Order for manual UI execution (use distinct shop to satisfy shop_daily_order_key)
        $shopManual = Shop::factory()->create([
            'name' => 'Manual Shop',
            'code' => 'SH-MANUAL',
            'shop_price_group_id' => $this->shop->shop_price_group_id,
            'status' => 'active',
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $orderManual = ShopOrder::factory()->approved()->create([
            'shop_id' => $shopManual->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $manualItem = ShopOrderItem::create([
            'shop_order_id' => $orderManual->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 45.0,
            'line_total' => 450.0,
        ]);

        // Post to manual load endpoint
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.auto-load-all.api.load-order', ['shopOrder' => $orderManual->order_number]));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $manualItem->refresh();
        $this->assertSame('10.00', (string) $manualItem->loaded_qty);
        $this->assertSame('loaded', $manualItem->sorting_status);

        // Assert identical state transitions
        $orderScheduled->refresh();
        $orderManual->refresh();
        $this->assertSame('ready_for_dispatch', $orderScheduled->delivery_status);
        $this->assertSame('ready_for_dispatch', $orderManual->delivery_status);
    }

    /**
     * Test 8: A second execution creates no duplicate stock movements (idempotency).
     */
    public function test_08_second_execution_creates_no_duplicate_stock_movements(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 12.0,
            'approved_qty' => 12.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
        ]);

        // First run
        $res1 = $this->service->loadOrder($order, $this->admin);
        $this->assertTrue($res1['success']);
        $this->assertSame(1, $res1['loaded_items_count']);

        $movementCountFirst = StockMovement::where('shop_order_item_id', $item->id)->count();
        $this->assertSame(1, $movementCountFirst);

        // Second run
        $res2 = $this->service->loadOrder($order, $this->admin);
        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['skipped']);
        $this->assertSame(0, $res2['loaded_items_count']);
        $this->assertSame(1, $res2['already_loaded_items_count']);

        // Stock movements must remain identical (no duplicate deductions)
        $movementCountSecond = StockMovement::where('shop_order_item_id', $item->id)->count();
        $this->assertSame(1, $movementCountSecond);
    }

    /**
     * Test 9: A partial loadout is not silently overwritten.
     */
    public function test_09_partial_loadout_is_not_silently_overwritten(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        // Warehouse worker already partially loaded 8 kg out of 20 kg
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'loaded_qty' => 8.0,
            'unit' => 'KG',
            'sorting_status' => 'loaded',
            'locked_selling_price' => 50.0,
        ]);

        $result = $this->service->loadOrder($order, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['loaded_items_count']);
        $this->assertSame(1, $result['already_loaded_items_count']);

        $item->refresh();
        // Quantity must remain 8.00 as set by worker, not overwritten to 20.00
        $this->assertSame('8.00', (string) $item->loaded_qty);
    }

    /**
     * Test 10: Concurrent execution cannot double-load an order.
     */
    public function test_10_concurrent_execution_cannot_double_load_order(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
        ]);

        // Simulate another process holding the cache lock
        $lockKey = "auto_load_all_order_{$order->id}";
        $lock = Cache::lock($lockKey, 60);
        $this->assertTrue($lock->get());

        try {
            // Attempting to load while lock is held must cleanly skip and protect against concurrency
            $result = $this->service->loadOrder($order, $this->admin);
            $this->assertFalse($result['success']);
            $this->assertTrue($result['skipped']);
            $this->assertStringContainsString('locked by another process', $result['message']);
        } finally {
            $lock->release();
        }
    }

    /**
     * Test 11: Invoice items retain original quality grades.
     */
    public function test_11_invoice_items_retain_original_quality_grades(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 45.0,
        ]);

        $result = $this->service->loadOrder($order, $this->admin);
        $this->assertTrue($result['success']);

        $invoice = ShopInvoice::where('shop_order_id', $order->id)->firstOrFail();
        $invoiceItems = $invoice->items;

        $this->assertCount(2, $invoiceItems);

        $invItemA = $invoiceItems->firstWhere('product_grade', 'A');
        $invItemB = $invoiceItems->firstWhere('product_grade', 'B');

        $this->assertNotNull($invItemA);
        $this->assertNotNull($invItemB);

        $this->assertSame('10.00', (string) $invItemA->delivered_qty);
        $this->assertSame('5.00', (string) $invItemB->delivered_qty);
    }

    /**
     * Test 12: Invoice quantities remain consistent with loadout.
     */
    public function test_12_invoice_quantities_remain_consistent_with_loadout(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->potato->id,
            'product_grade' => 'C',
            'requested_qty' => 14.0,
            'approved_qty' => 14.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 40.0,
        ]);

        $this->service->loadOrder($order, $this->admin);

        $invoice = ShopInvoice::where('shop_order_id', $order->id)->firstOrFail();
        $invoiceItem = $invoice->items->first();

        $this->assertSame('14.00', (string) $invoiceItem->approved_qty);
        $this->assertSame('14.00', (string) $invoiceItem->delivered_qty);
        $this->assertSame('0.00', (string) $invoiceItem->shortage_qty);
        $this->assertSame('0.00', (string) $invoiceItem->excess_qty);
        $this->assertSame('C', $invoiceItem->product_grade);
    }

    /**
     * Test 13: Finalized invoices remain unchanged.
     */
    public function test_13_finalized_invoices_remain_unchanged(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
        ]);

        // Pre-create finalized invoice
        $invoice = ShopInvoice::create([
            'shop_order_id' => $order->id,
            'shop_id' => $this->shop->id,
            'invoice_number' => 'INV-LOCKED-001',
            'business_date' => $this->businessDate,
            'status' => 'finalized', // Final locked
            'delivery_status' => 'delivered',
            'final_total' => 999.00,
        ]);

        $this->service->loadOrder($order, $this->admin);

        $invoice->refresh();
        $this->assertSame('999.00', (string) $invoice->final_total);
        $this->assertSame('finalized', $invoice->status);
    }

    /**
     * Test 14: Shop permissions and business dates remain correct.
     */
    public function test_14_shop_permissions_and_business_dates_remain_correct(): void
    {
        // Shop that only permits Grade A
        $restrictedShop = Shop::factory()->create([
            'name' => 'Grade A Only Shop',
            'code' => 'SH-RESTRICTED',
            'shop_price_group_id' => $this->shop->shop_price_group_id,
            'allowed_product_grades' => ['A'],
            'status' => 'active',
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $restrictedShop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
        ]);

        $itemC = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'C', // Ineligible for this shop
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 40.0,
        ]);

        $result = $this->service->loadOrder($order, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['loaded_items_count']);
        $this->assertSame(1, $result['skipped_items_count']);

        $itemA->refresh();
        $itemC->refresh();

        $this->assertSame('10.00', (string) $itemA->loaded_qty);
        $this->assertSame('loaded', $itemA->sorting_status);

        // Grade C item was skipped because shop does not have Grade C permissions
        $this->assertNull($itemC->loaded_qty);
        $this->assertSame('allocated', $itemC->sorting_status);
    }

    /**
     * Test 15: Legacy Grade A workflows remain compatible.
     */
    public function test_15_legacy_grade_a_workflows_remain_compatible(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            // Schema default is 'A' for legacy records
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
        ]);

        $result = $this->service->loadOrder($order, $this->admin);

        $this->assertTrue($result['success']);
        $item->refresh();

        $this->assertSame('10.00', (string) $item->loaded_qty);
        $movement = StockMovement::where('shop_order_item_id', $item->id)->firstOrFail();
        $this->assertSame('A', $movement->grade->value);
    }

    /**
     * Test 16: Existing Flutter API behavior does not regress (blocks legacy clients without header).
     */
    public function test_16_existing_flutter_api_behavior_does_not_regress(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
        ]);

        $receiver = User::factory()->create();
        $receiver->assignRole('warehouse_receiver');
        $receiver->warehouses()->attach($this->warehouse->id);

        // Mobile endpoint without multi-grade header must still reject with 422 to protect legacy mobile apps
        $response = $this->actingAs($receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/load-all");

        $response->assertStatus(422)
            ->assertJsonPath('code', 'MULTI_GRADE_BULK_LOAD_RESTRICTED');
    }

    /**
     * Test 17: WarehouseScopedLoadoutService reports grade-specific available stock,
     * and API with X-Client-Supports-Multi-Grade succeeds.
     */
    public function test_17_grade_specific_stock_and_flutter_multi_grade_header(): void
    {
        $receiver = User::factory()->create();
        $receiver->assignRole('warehouse_receiver');
        $receiver->warehouses()->attach($this->warehouse->id);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 45.0,
        ]);

        // 1. Check WarehouseScopedLoadoutService::detail returns grade-specific stock
        $scopedService = app(WarehouseScopedLoadoutService::class);
        $detail = $scopedService->detail($receiver, $this->warehouse, $order);

        $itemMap = collect($detail['items'])->keyBy('requisition_item_id');
        $this->assertEquals(100.0, $itemMap[$itemA->id]['available_stock']); // Grade A has 100 kg
        $this->assertEquals(50.0, $itemMap[$itemB->id]['available_stock']);  // Grade B has 50 kg, NOT 100 kg!

        // 2. Mobile API call with X-Client-Supports-Multi-Grade header succeeds
        $response = $this->actingAs($receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/load-all");

        $response->assertOk()
            ->assertJsonPath('success', true);

        // 3. Verify both lines loaded
        $itemA->refresh();
        $itemB->refresh();
        $this->assertSame('10.00', (string) $itemA->loaded_qty);
        $this->assertSame('5.00', (string) $itemB->loaded_qty);
    }

    public function test_18_order_details_api_contract_and_authorization(): void
    {
        $receiver = User::factory()->create();
        $receiver->assignRole('warehouse_receiver');
        $receiver->warehouses()->attach($this->warehouse->id);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 50.0,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 8.0,
            'approved_qty' => 8.0,
            'unit' => 'KG',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 45.0,
        ]);

        // Unauthenticated access fails
        $unauthResponse = $this->getJson("/api/v1/warehouse/loadout/{$order->order_number}");
        $unauthResponse->assertStatus(401);

        // Authenticated access returns complete multi-grade contract
        $response = $this->actingAs($receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->getJson("/api/v1/warehouse/loadout/{$order->order_number}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.order_number', $order->order_number)
            ->assertJsonPath('can_edit', true);

        $productGroups = collect($response->json('product_groups'));
        $this->assertCount(2, $productGroups);

        $groupA = $productGroups->firstWhere('product_grade', 'A');
        $groupB = $productGroups->firstWhere('product_grade', 'B');

        $this->assertNotNull($groupA);
        $this->assertNotNull($groupB);
        $this->assertSame($itemA->id, $groupA['shop_order_item_id']);
        $this->assertSame($itemB->id, $groupB['shop_order_item_id']);
        $this->assertSame('A', $groupA['product_grade']);
        $this->assertSame('B', $groupB['product_grade']);
        $this->assertEquals(100.0, $groupA['available_stock']);
        $this->assertEquals(50.0, $groupB['available_stock']);
        $this->assertIsBool($groupA['has_secondary_unit']);
        $this->assertIsBool($groupA['is_fully_loaded']);
    }
}
