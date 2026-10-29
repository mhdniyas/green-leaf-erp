<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Purchasing\PendingWarehouseBillsManagementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PendingWarehouseBillsManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaserUser;

    private Warehouse $warehouse;

    private Category $category;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow('2026-10-25 10:00:00');

        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@greenleaf.test']);
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->purchaserUser = User::factory()->create(['name' => 'John Purchaser', 'email' => 'john@greenleaf.test']);
        $purchaserRole = Role::firstOrCreate(['name' => 'purchaser']);
        $this->purchaserUser->assignRole($purchaserRole);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Vegetable Warehouse',
            'code' => 'VEG-WH',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'code' => 'VEG',
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Farm Supplies']);

        $this->product = Product::create([
            'name' => 'Carrot Fresh',
            'sku' => 'CAR-001',
            'category_id' => $this->category->id,
            'unit' => 'kg',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pending_warehouse_bills_page_renders_with_metrics_and_table(): void
    {
        // Create an unreceived purchaser bill
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-101',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => 120.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 120.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-TEST-101',
            'cost_per_kg' => 25.0,
        ]);
        $invoice = PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-PEND-101',
            'amount' => 3000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.pending-bills'));

        $response->assertOk();
        $response->assertSee('Pending Warehouse Bills Management');
        $response->assertSee('Total Pending Bills');
        $response->assertSee('Outstanding Weight');
        $response->assertSee('Advance Matchable');
        $response->assertSee('Awaiting Physical Receive');
        $response->assertSee('Cleared Today');
        $response->assertSee('BILL-PEND-101');
        $response->assertSee('John Purchaser');
        $response->assertSee('Fresh Farm Supplies');
        $response->assertSee('120.0 kg');
    }

    public function test_pending_warehouse_bills_filters_by_search_status_and_purchaser(): void
    {
        // Bill 1: for John Purchaser
        $grn1 = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-102',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn1->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn1->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 50.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-TEST-102',
            'cost_per_kg' => 25.0,
        ]);
        $inv1 = PurchaseInvoice::create([
            'goods_received_id' => $grn1->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SEARCH-APPLE',
            'amount' => 1250.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Bill 2: for Other Purchaser
        $otherPurchaser = User::factory()->create(['name' => 'Alice Purchaser']);
        $grn2 = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-103',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn2->id,
            'product_id' => $this->product->id,
            'received_qty' => 80.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn2->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 80.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-TEST-103',
            'cost_per_kg' => 25.0,
        ]);
        $inv2 = PurchaseInvoice::create([
            'goods_received_id' => $grn2->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SEARCH-BANANA',
            'amount' => 2000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $otherPurchaser->id,
        ]);

        // Filter by keyword APPLE
        $res = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.pending-bills', ['search' => 'APPLE']));
        $res->assertOk();
        $res->assertSee('BILL-SEARCH-APPLE');
        $res->assertDontSee('BILL-SEARCH-BANANA');

        // Filter by purchaser John Purchaser
        $resPurchaser = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.pending-bills', ['purchaser_id' => $this->purchaserUser->id]));
        $resPurchaser->assertOk();
        $resPurchaser->assertSee('BILL-SEARCH-APPLE');
        $resPurchaser->assertDontSee('BILL-SEARCH-BANANA');
    }

    public function test_pending_warehouse_bills_shows_cleared_history(): void
    {
        // Cleared bill: warehouse_receive_pending = false
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-CLEARED-104',
            'receipt_type' => 'purchaser',
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => 75.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 75.0,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-CLEARED-104',
            'cost_per_kg' => 20.0,
        ]);
        $inv = PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-CLEARED-104',
            'amount' => 1500.0,
            'status' => 'approved',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Default 'pending' view does not show cleared bill
        $resPending = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.pending-bills', ['status' => 'pending']));
        $resPending->assertOk();
        $resPending->assertDontSee('BILL-CLEARED-104');

        // 'cleared' status view displays the cleared bill
        $resCleared = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.pending-bills', ['status' => 'cleared']));
        $resCleared->assertOk();
        $resCleared->assertSee('BILL-CLEARED-104');
        $resCleared->assertSee('Cleared');
    }

    public function test_pending_bill_details_endpoint_returns_json_breakdown(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-DETAIL-105',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => 65.0,
            'cost_per_unit' => 30.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 65.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-DETAIL-105',
            'cost_per_kg' => 30.0,
        ]);
        $inv = PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-DETAIL-105',
            'amount' => 1950.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.cashbook.reports-v1.pending-bills.details', $inv));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'invoice_number' => 'BILL-DETAIL-105',
                'supplier_name' => 'Fresh Farm Supplies',
                'purchaser_name' => 'John Purchaser',
                'items' => [
                    [
                        'product_name' => 'Carrot Fresh',
                        'received_qty' => 65.0,
                        'batch_pending' => true,
                    ],
                ],
            ],
        ]);
    }

    public function test_match_advance_reconciles_bill_and_clears_cache(): void
    {
        Cache::put('purchaser_bills_not_received_summary', ['dummy'], 60);

        // Advance receipt: 100 kg physically received earlier today
        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-201',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        $advItem = GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 100.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-ADV-201',
            'cost_per_kg' => 20.0,
        ]);

        // Purchaser bill: 100 kg on the same date
        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-BILL-201',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        $billItem = GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 100.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-BILL-201',
            'cost_per_kg' => 20.0,
        ]);
        $inv = PurchaseInvoice::create([
            'goods_received_id' => $billGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-MATCH-201',
            'amount' => 2000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Execute match
        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-advance', $inv),
            [
                'warehouse_id' => $this->warehouse->id,
            ]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Advance match was recorded
        $this->assertDatabaseHas('advance_receive_matches', [
            'advance_goods_received_id' => $advGrn->id,
            'bill_goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'matched_qty' => 100.0,
        ]);

        // Duplicate bill stock was zeroed out (preserving advance stock without double counting)
        $this->assertDatabaseHas('stock_batches', [
            'goods_received_id' => $billGrn->id,
            'total_kg' => 0.0,
            'warehouse_receive_pending' => false,
        ]);

        // Cache was flushed
        $this->assertFalse(Cache::has('purchaser_bills_not_received_summary'));
    }

    public function test_receive_goods_confirms_physical_warehouse_inventory_and_clears_cache(): void
    {
        Cache::put('purchaser_bills_not_received_summary', ['dummy'], 60);

        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-RECEIVE-301',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 90.0,
            'variance' => 0.0,
        ]);
        $batch = StockBatch::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 90.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-RECEIVE-301',
            'cost_per_kg' => 20.0,
        ]);
        $inv = PurchaseInvoice::create([
            'goods_received_id' => $billGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-RECEIVE-301',
            'amount' => 1800.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Confirm physical receive
        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.receive-goods', $inv),
            ['warehouse_id' => $this->warehouse->id]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Batch is confirmed
        $batch->refresh();
        $this->assertFalse($batch->warehouse_receive_pending);
        $this->assertNotNull($batch->warehouse_confirmed_at);
        $this->assertEquals(90.0, (float) $batch->total_kg);

        // Cache was flushed
        $this->assertFalse(Cache::has('purchaser_bills_not_received_summary'));
    }

    public function test_bulk_match_preview_and_execution(): void
    {
        // Advance receipt: 50 kg available
        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-401',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 50.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-ADV-401',
            'cost_per_kg' => 20.0,
        ]);

        // Bill 1: Has matching advance
        $grn1 = GoodsReceived::create([
            'grn_number' => 'GRN-BULK-401',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn1->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn1->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 50.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-BULK-401',
            'cost_per_kg' => 20.0,
        ]);
        $inv1 = PurchaseInvoice::create([
            'goods_received_id' => $grn1->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-BULK-401',
            'amount' => 1000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Bill 2: Different product with NO advance
        $otherProduct = Product::create([
            'name' => 'Tomato Fresh',
            'sku' => 'TOM-001',
            'category_id' => $this->category->id,
            'unit' => 'kg',
            'status' => 'active',
        ]);
        $grn2 = GoodsReceived::create([
            'grn_number' => 'GRN-BULK-402',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn2->id,
            'product_id' => $otherProduct->id,
            'received_qty' => 30.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn2->id,
            'product_id' => $otherProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 30.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-BULK-402',
            'cost_per_kg' => 20.0,
        ]);
        $inv2 = PurchaseInvoice::create([
            'goods_received_id' => $grn2->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-BULK-402',
            'amount' => 600.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // 1. Preview
        $previewRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.bulk-match-preview'),
            ['invoice_ids' => [$inv1->id, $inv2->id]]
        );
        $previewRes->assertOk();
        $previewRes->assertJson([
            'success' => true,
            'data' => [
                'matchable_count' => 1,
                'skipped_count' => 1,
                'total_matchable_qty' => 50.0,
            ],
        ]);

        // 2. Execute Confirm
        $execRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.bulk-match-confirm'),
            ['invoice_ids' => [$inv1->id, $inv2->id]]
        );
        $execRes->assertOk();
        $execRes->assertJson([
            'success' => true,
            'data' => [
                'processed_count' => 1,
            ],
        ]);

        $this->assertDatabaseHas('advance_receive_matches', [
            'bill_goods_received_id' => $grn1->id,
            'matched_qty' => 50.0,
        ]);
    }

    public function test_bulk_receive_execution(): void
    {
        $grn1 = GoodsReceived::create([
            'grn_number' => 'GRN-BULK-REC-501',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn1->id,
            'product_id' => $this->product->id,
            'received_qty' => 45.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn1->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 45.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-BULK-REC-501',
            'cost_per_kg' => 20.0,
        ]);
        $inv1 = PurchaseInvoice::create([
            'goods_received_id' => $grn1->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-BULK-REC-501',
            'amount' => 900.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.bulk-receive-confirm'),
            [
                'invoice_ids' => [$inv1->id],
                'warehouse_id' => (string) $this->warehouse->id,
            ]
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'received_count' => 1,
            ],
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'goods_received_id' => $grn1->id,
            'warehouse_receive_pending' => false,
        ]);
    }

    public function test_match_and_receive_balance_handles_split_reconciliation_safely(): void
    {
        // Advance receipt has 40 kg
        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-601',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 40.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 40.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-ADV-601',
            'cost_per_kg' => 20.0,
        ]);

        // Bill has 100 kg (40 kg will match advance, 60 kg remainder will be received)
        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SPLIT-601',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        $batch = StockBatch::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 100.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SPLIT-601',
            'cost_per_kg' => 20.0,
        ]);
        $inv = PurchaseInvoice::create([
            'goods_received_id' => $billGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SPLIT-601',
            'amount' => 2000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-receive-balance', $inv),
            ['warehouse_id' => $this->warehouse->id]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Advance match created for 40 kg
        $this->assertDatabaseHas('advance_receive_matches', [
            'advance_goods_received_id' => $advGrn->id,
            'bill_goods_received_id' => $billGrn->id,
            'matched_qty' => 40.0,
        ]);

        // Remainder batch confirmed for 60 kg without pending flag
        $batch->refresh();
        $this->assertFalse($batch->warehouse_receive_pending);
        $this->assertEquals(60.0, (float) $batch->total_kg);
    }

    public function test_unauthorized_user_cannot_access_or_execute_actions(): void
    {
        $unauthorizedUser = User::factory()->create();

        // View page is forbidden for JSON requests (or redirects for standard web)
        $viewRes = $this->actingAs($unauthorizedUser)->getJson(route('admin.cashbook.reports-v1.pending-bills'));
        $viewRes->assertForbidden();

        // Action is forbidden
        $dummyGrn = GoodsReceived::create([
            'grn_number' => 'GRN-UNAUTH-01',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);

        $dummyInv = PurchaseInvoice::create([
            'goods_received_id' => $dummyGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-UNAUTH',
            'amount' => 100.0,
            'status' => 'pending',
        ]);

        $postRes = $this->actingAs($unauthorizedUser)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.receive-goods', $dummyInv),
            []
        );
        $postRes->assertForbidden();
    }

    private function recordStockMovement(
        StockBatch $batch,
        StockMovementType $type,
        float $quantity,
        string $date,
        string $grade = 'A'
    ): StockMovement {
        return StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $batch->product_id,
            'warehouse_id' => $batch->warehouse_id,
            'created_by' => $this->admin->id,
            'grade' => ProductGrade::tryResolve($grade) ?? ProductGrade::A,
            'type' => $type,
            'quantity' => $quantity,
            'cost_per_unit' => $batch->cost_per_kg,
            'created_at' => Carbon::parse($date)->setHour(12),
        ]);
    }

    public function test_scenario_01_advance_100kg_loadout_80kg_bill_100kg_match_preserves_20kg_inventory(): void
    {
        $date = now()->toDateString();
        $stockRepo = app(StockMovementRepository::class);

        // 1. Warehouse advance receives 100 KG
        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN1-ADV',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        $advBatch = StockBatch::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 100.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN1-ADV',
            'cost_per_kg' => 20.0,
        ]);
        $this->recordStockMovement($advBatch, StockMovementType::In, 100.0, $date);

        // Physical inventory is 100 KG
        $stockBefore = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->sum('current_stock');
        $this->assertEquals(100.0, $stockBefore);

        // 2. Shop loadout dispatches 80 KG
        $this->recordStockMovement($advBatch, StockMovementType::Out, 80.0, $date);

        // Physical inventory becomes 20 KG
        $stockAfterLoadout = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->sum('current_stock');
        $this->assertEquals(20.0, $stockAfterLoadout);

        // 3. Purchase bill for 100 KG arrives later
        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN1-BILL',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        $billBatch = StockBatch::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 100.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN1-BILL',
            'cost_per_kg' => 20.0,
        ]);
        $billInvoice = PurchaseInvoice::create([
            'goods_received_id' => $billGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN1-100',
            'amount' => 2000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Unreceived bill does not affect physical warehouse stock
        $this->assertEquals(20.0, (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouse->id)->where('product_id', $this->product->id)->sum('current_stock'));

        // 4. Bill is matched/cleared against advance receiving
        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-advance', $billInvoice),
            ['warehouse_id' => $this->warehouse->id]
        );
        $response->assertOk();

        // 5. Inventory must remain exactly 20 KG!
        $stockFinal = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->sum('current_stock');
        $this->assertEquals(20.0, $stockFinal);

        // Bill duplicate batch is zeroed and marked processed without creating In movements
        $billBatch->refresh();
        $this->assertEquals(0.0, (float) $billBatch->total_kg);
        $this->assertFalse($billBatch->warehouse_receive_pending);
    }

    public function test_scenario_02_advance_100kg_loadout_100kg_bill_100kg_match_preserves_0kg_inventory(): void
    {
        $date = now()->toDateString();
        $stockRepo = app(StockMovementRepository::class);

        // Advance 100 KG
        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN2-ADV',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        $advBatch = StockBatch::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 100.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN2-ADV',
            'cost_per_kg' => 20.0,
        ]);
        $this->recordStockMovement($advBatch, StockMovementType::In, 100.0, $date);

        // Loadout 100 KG (all stock consumed)
        $this->recordStockMovement($advBatch, StockMovementType::Out, 100.0, $date);
        $this->assertEquals(0.0, (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouse->id)->where('product_id', $this->product->id)->sum('current_stock'));

        // Bill arrives for 100 KG
        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN2-BILL',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 100.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN2-BILL',
            'cost_per_kg' => 20.0,
        ]);
        $billInvoice = PurchaseInvoice::create([
            'goods_received_id' => $billGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN2-100',
            'amount' => 2000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Match bill
        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-advance', $billInvoice),
            ['warehouse_id' => $this->warehouse->id]
        );
        $response->assertOk();

        // Inventory must remain exactly 0 KG (no stock resurrection)
        $stockFinal = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->sum('current_stock');
        $this->assertEquals(0.0, $stockFinal);
    }

    public function test_scenario_03_advance_100kg_bill_120kg_match_and_receive_yields_120kg_total(): void
    {
        $date = now()->toDateString();
        $stockRepo = app(StockMovementRepository::class);

        // Advance 100 KG
        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN3-ADV',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        $advBatch = StockBatch::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 100.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN3-ADV',
            'cost_per_kg' => 20.0,
        ]);
        $this->recordStockMovement($advBatch, StockMovementType::In, 100.0, $date);

        // Bill arrives for 120 KG (100 kg advance + 20 kg newly delivered)
        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN3-BILL',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 120.0,
            'variance' => 0.0,
        ]);
        $billBatch = StockBatch::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 120.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'reference' => 'BAT-SCEN3-BILL',
            'cost_per_kg' => 20.0,
        ]);
        $billInvoice = PurchaseInvoice::create([
            'goods_received_id' => $billGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN3-120',
            'amount' => 2400.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Execute match advance (100 kg) and receive remainder (20 kg)
        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-receive-balance', $billInvoice),
            ['warehouse_id' => $this->warehouse->id]
        );
        $response->assertOk();

        // Total inventory in warehouse is exactly 120 KG before any other stock movements
        $stockFinal = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->sum('current_stock');
        $this->assertEquals(120.0, $stockFinal);

        // Remainder batch is confirmed for 20 KG without pending flag
        $billBatch->refresh();
        $this->assertEquals(20.0, (float) $billBatch->total_kg);
        $this->assertFalse($billBatch->warehouse_receive_pending);
    }

    public function test_scenario_04_repeated_matching_and_receiving_calls_do_not_duplicate_stock(): void
    {
        $date = now()->toDateString();
        $stockRepo = app(StockMovementRepository::class);

        // 1. Advance receipt 50 kg
        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN4-ADV',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
        ]);
        $advBatch = StockBatch::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 50.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN4-ADV',
            'cost_per_kg' => 20.0,
        ]);
        $this->recordStockMovement($advBatch, StockMovementType::In, 50.0, $date);

        // Bill A (50 kg)
        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN4-BILLA',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 50.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN4-BILLA',
            'cost_per_kg' => 20.0,
        ]);
        $invA = PurchaseInvoice::create([
            'goods_received_id' => $billGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN4-A',
            'amount' => 1000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // First match succeeds
        $res1 = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-advance', $invA),
            ['warehouse_id' => $this->warehouse->id]
        );
        $res1->assertOk();

        // Repeated match call for already reconciled bill rejects
        $res2 = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-advance', $invA),
            ['warehouse_id' => $this->warehouse->id]
        );
        $res2->assertStatus(422);

        // Exactly 1 match record exists
        $this->assertEquals(1, AdvanceReceiveMatch::where('bill_goods_received_id', $billGrn->id)->count());

        // 2. Physical receive idempotency: Bill B (30 kg)
        $billGrnB = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN4-BILLB',
            'receipt_type' => 'purchaser',
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billGrnB->id,
            'product_id' => $this->product->id,
            'received_qty' => 30.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $billGrnB->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 30.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'reference' => 'BAT-SCEN4-BILLB',
            'cost_per_kg' => 20.0,
        ]);
        $invB = PurchaseInvoice::create([
            'goods_received_id' => $billGrnB->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN4-B',
            'amount' => 600.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // First receive confirms batch
        $rec1 = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.receive-goods', $invB),
            ['warehouse_id' => $this->warehouse->id]
        );
        $rec1->assertOk();

        // Second receive rejects as already cleared/received
        $rec2 = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.receive-goods', $invB),
            ['warehouse_id' => $this->warehouse->id]
        );
        $rec2->assertStatus(422);

        // Stock remains exactly 80.0 kg (50 kg advance + 30 kg received bill B)
        $stockFinal = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->sum('current_stock');
        $this->assertEquals(80.0, $stockFinal);
    }

    public function test_scenario_05_multiple_advance_matches_allocate_across_multiple_grns(): void
    {
        $date = now()->toDateString();

        // Advance 1: 40 kg
        $advGrn1 = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN5-ADV1',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn1->id,
            'product_id' => $this->product->id,
            'received_qty' => 40.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $advGrn1->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 40.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN5-ADV1',
            'cost_per_kg' => 20.0,
        ]);

        // Advance 2: 60 kg
        $advGrn2 = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN5-ADV2',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn2->id,
            'product_id' => $this->product->id,
            'received_qty' => 60.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $advGrn2->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 60.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN5-ADV2',
            'cost_per_kg' => 20.0,
        ]);

        // Bill: 100 kg
        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN5-BILL',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 100.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN5-BILL',
            'cost_per_kg' => 20.0,
        ]);
        $inv = PurchaseInvoice::create([
            'goods_received_id' => $billGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN5-100',
            'amount' => 2000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Match bill
        $res = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-advance', $inv),
            ['warehouse_id' => $this->warehouse->id]
        );
        $res->assertOk();

        // 2 match records created across both advances
        $matches = AdvanceReceiveMatch::where('bill_goods_received_id', $billGrn->id)->get();
        $this->assertCount(2, $matches);
        $this->assertEquals(100.0, (float) $matches->sum('matched_qty'));

        $this->assertDatabaseHas('advance_receive_matches', [
            'advance_goods_received_id' => $advGrn1->id,
            'matched_qty' => 40.0,
        ]);
        $this->assertDatabaseHas('advance_receive_matches', [
            'advance_goods_received_id' => $advGrn2->id,
            'matched_qty' => 60.0,
        ]);
    }

    public function test_scenario_06_bulk_match_preview_identifies_exact_advance_ids_and_quantities(): void
    {
        $date = now()->toDateString();

        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN6-ADV',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 50.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN6-ADV',
            'cost_per_kg' => 20.0,
        ]);

        // Bill 1: 30 KG
        $bill1 = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN6-B1',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $bill1->id,
            'product_id' => $this->product->id,
            'received_qty' => 30.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $bill1->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 30.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN6-B1',
            'cost_per_kg' => 20.0,
        ]);
        $inv1 = PurchaseInvoice::create([
            'goods_received_id' => $bill1->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN6-1',
            'amount' => 600.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Bill 2: 20 KG
        $bill2 = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN6-B2',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $bill2->id,
            'product_id' => $this->product->id,
            'received_qty' => 20.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $bill2->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 20.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN6-B2',
            'cost_per_kg' => 20.0,
        ]);
        $inv2 = PurchaseInvoice::create([
            'goods_received_id' => $bill2->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN6-2',
            'amount' => 400.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        $res = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.bulk-match-preview'),
            ['invoice_ids' => [$inv1->id, $inv2->id]]
        );

        $res->assertOk();
        $res->assertJson([
            'success' => true,
            'data' => [
                'matchable_count' => 2,
                'skipped_count' => 0,
                'total_matchable_qty' => 50.0,
            ],
        ]);

        $matchable = $res->json('data.matchable_bills');
        $this->assertCount(2, $matchable);
        $this->assertEquals(30.0, (float) $matchable[0]['matched_qty']);
        $this->assertEquals(20.0, (float) $matchable[1]['matched_qty']);
        $this->assertEquals(50.0, (float) $res->json('data.total_matchable_qty'));

        // Details breakdown confirms candidate advance receipt ID and quantity
        $detailsRes = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports-v1.pending-bills.details', $inv1)
        );
        $detailsRes->assertOk();
        $detailsData = $detailsRes->json('data');
        $suggested = $detailsData['suggestions']['items'][0]['suggested_matches'][0];
        $this->assertEquals($advGrn->id, $suggested['advance_goods_received_id']);
        $this->assertEquals(30.0, (float) $suggested['base_qty']);
    }

    public function test_scenario_07_bulk_confirmation_cannot_match_same_advance_twice_across_bills(): void
    {
        $date = now()->toDateString();

        // Advance has only 50 KG total
        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN7-ADV',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 50.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN7-ADV',
            'cost_per_kg' => 20.0,
        ]);

        // Bill A: 40 KG
        $billA = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN7-A',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billA->id,
            'product_id' => $this->product->id,
            'received_qty' => 40.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $billA->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 40.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN7-A',
            'cost_per_kg' => 20.0,
        ]);
        $invA = PurchaseInvoice::create([
            'goods_received_id' => $billA->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN7-A',
            'amount' => 800.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Bill B: 40 KG
        $billB = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN7-B',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billB->id,
            'product_id' => $this->product->id,
            'received_qty' => 40.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $billB->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 40.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN7-B',
            'cost_per_kg' => 20.0,
        ]);
        $invB = PurchaseInvoice::create([
            'goods_received_id' => $billB->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN7-B',
            'amount' => 800.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Preview: Bill A matches 40 kg, Bill B matches 10 kg, total = 50 kg
        $prev = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.bulk-match-preview'),
            ['invoice_ids' => [$invA->id, $invB->id]]
        );
        $prev->assertOk();
        $this->assertEquals(50.0, (float) $prev->json('data.total_matchable_qty'));

        // Confirm bulk match
        $exec = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.bulk-match-confirm'),
            ['invoice_ids' => [$invA->id, $invB->id]]
        );
        $exec->assertOk();

        // Total matched quantity across the two bills cannot exceed 50 KG!
        $totalMatched = (float) AdvanceReceiveMatch::where('advance_goods_received_id', $advGrn->id)->sum('matched_qty');
        $this->assertEquals(50.0, $totalMatched);

        // Advance is fully consumed (0 kg available)
        $this->assertDatabaseHas('advance_receive_matches', [
            'advance_goods_received_id' => $advGrn->id,
            'bill_goods_received_id' => $billA->id,
            'matched_qty' => 40.0,
        ]);
        $this->assertDatabaseHas('advance_receive_matches', [
            'advance_goods_received_id' => $advGrn->id,
            'bill_goods_received_id' => $billB->id,
            'matched_qty' => 10.0,
        ]);
    }

    public function test_scenario_08_failed_or_ambiguous_records_remain_pending_with_clear_error(): void
    {
        $date = now()->toDateString();

        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN8-FAIL',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 70.0,
            'variance' => 0.0,
        ]);
        $batch = StockBatch::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 70.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN8-FAIL',
            'cost_per_kg' => 20.0,
        ]);
        $inv = PurchaseInvoice::create([
            'goods_received_id' => $billGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN8-FAIL',
            'amount' => 1400.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Attempt to match advance when NO advance exists
        $res = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-advance', $inv),
            ['warehouse_id' => $this->warehouse->id]
        );

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['advance_matches']);

        // State remains strictly pending
        $batch->refresh();
        $this->assertTrue($batch->warehouse_receive_pending);
        $this->assertEquals(70.0, (float) $batch->total_kg);
        $statusValue = $inv->fresh()->status instanceof \BackedEnum ? $inv->fresh()->status->value : (string) $inv->fresh()->status;
        $this->assertEquals('pending', $statusValue);
        $this->assertEquals(0, AdvanceReceiveMatch::count());
    }

    public function test_scenario_09_fully_cleared_bills_disappear_from_pending_and_appear_in_cleared(): void
    {
        $date = now()->toDateString();

        // 1. Pending Bill
        $grnPending = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN9-P',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grnPending->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grnPending->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 50.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN9-P',
            'cost_per_kg' => 20.0,
        ]);
        PurchaseInvoice::create([
            'goods_received_id' => $grnPending->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN9-PENDING',
            'amount' => 1000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // 2. Cleared Bill
        $grnCleared = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN9-C',
            'receipt_type' => 'purchaser',
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grnCleared->id,
            'product_id' => $this->product->id,
            'received_qty' => 60.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grnCleared->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 60.0,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN9-C',
            'cost_per_kg' => 20.0,
        ]);
        PurchaseInvoice::create([
            'goods_received_id' => $grnCleared->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN9-CLEARED',
            'amount' => 1200.0,
            'status' => 'approved',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Default / pending tab: shows Pending, does NOT show Cleared
        $resPending = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.pending-bills', ['status' => 'pending']));
        $resPending->assertOk();
        $resPending->assertSee('BILL-SCEN9-PENDING');
        $resPending->assertDontSee('BILL-SCEN9-CLEARED');

        // Cleared history tab: shows Cleared, does NOT show Pending
        $resCleared = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.pending-bills', ['status' => 'cleared']));
        $resCleared->assertOk();
        $resCleared->assertSee('BILL-SCEN9-CLEARED');
        $resCleared->assertDontSee('BILL-SCEN9-PENDING');
    }

    public function test_scenario_10_direct_shop_vendor_credit_and_cash_purchases_strictly_excluded(): void
    {
        $shop = Shop::factory()->create(['name' => 'Shop City Center']);
        $date = now()->toDateString();

        // 1. Legitimate Warehouse Purchaser Bill
        $grnWh = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN10-WH',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => $date,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grnWh->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grnWh->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => $date,
            'total_kg' => 50.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-SCEN10-WH',
            'cost_per_kg' => 20.0,
        ]);
        PurchaseInvoice::create([
            'goods_received_id' => $grnWh->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN10-WH',
            'amount' => 1000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // 2. Direct Shop Vendor Credit Purchase
        $shopCartCredit = PurchaserCart::create([
            'cart_number' => 'CART-SCEN10-CREDIT',
            'user_id' => $this->purchaserUser->id,
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $shop->id,
            'business_date' => $date,
            'status' => 'submitted',
            'purchase_source' => 'shop',
            'payment_method' => 'Credit',
            'payment_status' => 'pending',
        ]);
        $shopGrnCredit = GoodsReceived::create([
            'purchaser_cart_id' => $shopCartCredit->id,
            'grn_number' => 'SGRN-SCEN10-CREDIT',
            'status' => 'approved',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->purchaserUser->id,
            'received_at' => $date,
        ]);
        PurchaseInvoice::create([
            'goods_received_id' => $shopGrnCredit->id,
            'supplier_id' => $this->supplier->id,
            'shop_id' => $shop->id,
            'purchaser_cart_id' => $shopCartCredit->id,
            'purchase_source' => 'shop',
            'invoice_number' => 'BILL-SCEN10-SHOP-CREDIT',
            'amount' => 500.0,
            'status' => 'pending',
            'payment_method' => 'Credit',
            'payment_paid_by' => 'vendor_credit',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // 3. Direct Shop Cash Purchase
        $shopCartCash = PurchaserCart::create([
            'cart_number' => 'CART-SCEN10-CASH',
            'user_id' => $this->purchaserUser->id,
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $shop->id,
            'business_date' => $date,
            'status' => 'submitted',
            'purchase_source' => 'shop',
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
        ]);
        $shopGrnCash = GoodsReceived::create([
            'purchaser_cart_id' => $shopCartCash->id,
            'grn_number' => 'SGRN-SCEN10-CASH',
            'status' => 'approved',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->purchaserUser->id,
            'received_at' => $date,
        ]);
        PurchaseInvoice::create([
            'goods_received_id' => $shopGrnCash->id,
            'supplier_id' => $this->supplier->id,
            'shop_id' => $shop->id,
            'purchaser_cart_id' => $shopCartCash->id,
            'purchase_source' => 'shop',
            'invoice_number' => 'BILL-SCEN10-SHOP-CASH',
            'amount' => 300.0,
            'status' => 'pending',
            'payment_method' => 'Cash',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // Check metrics: only the 1 warehouse bill is counted!
        $metrics = app(PendingWarehouseBillsManagementService::class)->getSummaryMetrics();
        $this->assertEquals(1, $metrics['total_pending_bills']);
        $this->assertEquals(50.0, $metrics['total_outstanding_qty']);

        // Check page response: Shop bills are completely absent
        $res = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.pending-bills'));
        $res->assertOk();
        $res->assertSee('BILL-SCEN10-WH');
        $res->assertDontSee('BILL-SCEN10-SHOP-CREDIT');
        $res->assertDontSee('BILL-SCEN10-SHOP-CASH');
    }

    public function test_scenario_11_read_only_view_user_cannot_execute_match_or_receive_actions(): void
    {
        $viewer = User::factory()->create(['name' => 'Auditor Viewer', 'email' => 'viewer@greenleaf.test']);
        $viewerRole = Role::firstOrCreate(['name' => 'auditor']);
        Permission::firstOrCreate(['name' => 'cashbook.access']);
        Permission::firstOrCreate(['name' => 'cashbook.reconciliation.view']);
        $viewerRole->syncPermissions(['cashbook.access', 'cashbook.reconciliation.view']);
        $viewer->assignRole($viewerRole);

        // Read-only user can view the page
        $viewRes = $this->actingAs($viewer)->get(route('admin.cashbook.reports-v1.pending-bills'));
        $viewRes->assertOk();

        // Target pending bill
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-SCEN11',
            'receipt_type' => 'purchaser',
            'status' => 'pending_approval',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
        ]);
        $inv = PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-SCEN11',
            'amount' => 500.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaserUser->id,
        ]);

        // All mutating actions are strictly 403 Forbidden for read-only user
        $this->actingAs($viewer)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-advance', $inv),
            ['warehouse_id' => $this->warehouse->id]
        )->assertForbidden();

        $this->actingAs($viewer)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.receive-goods', $inv),
            ['warehouse_id' => $this->warehouse->id]
        )->assertForbidden();

        $this->actingAs($viewer)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.match-receive-balance', $inv),
            ['warehouse_id' => $this->warehouse->id]
        )->assertForbidden();

        $this->actingAs($viewer)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.bulk-match-confirm'),
            ['invoice_ids' => [$inv->id]]
        )->assertForbidden();

        $this->actingAs($viewer)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.bulk-receive-confirm'),
            ['invoice_ids' => [$inv->id], 'warehouse_id' => $this->warehouse->id]
        )->assertForbidden();
    }
}
