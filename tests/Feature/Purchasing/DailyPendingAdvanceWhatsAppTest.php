<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyPendingAdvanceWhatsAppService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPendingAdvanceWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Product $tomato;

    private Product $cucumber;

    private Product $bananaStem;

    private Supplier $supplier;

    private Shop $shop;

    private DailyPendingAdvanceWhatsAppService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->warehouseA = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'WH-VEG',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'WH-FRUIT',
            'is_active' => true,
        ]);

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
        ]);

        $this->cucumber = Product::factory()->create([
            'name' => 'Cucumber',
            'sku' => 'CUC-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
        ]);

        $this->bananaStem = Product::factory()->create([
            'name' => 'Banana Stem',
            'sku' => 'BAN-STEM-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Green Fields Supplier']);
        $this->shop = Shop::factory()->create(['name' => 'City Center Shop']);

        $this->service = app(DailyPendingAdvanceWhatsAppService::class);
    }

    public function test_same_day_unmatched_advance_appears_in_share_message(): void
    {
        $selectedDate = '2026-09-12';

        $advGrn = $this->createAdvanceGrn($this->warehouseA, $this->tomato, 25.0, $selectedDate, 'GRN-20260912-0012');

        $data = $this->service->getDailyPendingAdvances($selectedDate, $this->warehouseA->id);
        $message = $this->service->buildWhatsAppMessage($data);

        $this->assertSame(1, $data['total_pending_count']);
        $this->assertCount(1, $data['standard_items']);
        $this->assertCount(0, $data['unit_issues']);

        $this->assertStringContainsString('GREEN LEAF', $message);
        $this->assertStringContainsString('Advance Bills Pending', $message);
        $this->assertStringContainsString('12 Sep 2026', $message);
        $this->assertStringContainsString('Vegetable Warehouse', $message);
        $this->assertStringContainsString('1. Tomato — 25 kg', $message);
        $this->assertStringContainsString('GRN: GRN-20260912-0012', $message);
        $this->assertStringContainsString('Total Pending Advance Lines: 1', $message);
        $this->assertStringContainsString('Please create / provide the pending purchaser bills for these Advance Receives.', $message);
    }

    public function test_previous_day_advance_does_not_appear(): void
    {
        $selectedDate = '2026-09-12';
        $previousDate = '2026-09-11';

        // Advance on previous day
        $this->createAdvanceGrn($this->warehouseA, $this->tomato, 25.0, $previousDate, 'GRN-20260911-0001');

        $data = $this->service->getDailyPendingAdvances($selectedDate, $this->warehouseA->id);
        $this->assertSame(0, $data['total_pending_count']);
        $this->assertNull($this->service->generateShareUrl($selectedDate, $this->warehouseA->id));
    }

    public function test_fully_matched_advance_does_not_appear(): void
    {
        $selectedDate = '2026-09-12';

        $advGrn = $this->createAdvanceGrn($this->warehouseA, $this->tomato, 25.0, $selectedDate, 'GRN-20260912-0012');
        $advItem = $advGrn->items->first();

        $billGrn = $this->createBillGrn($this->warehouseA, $this->tomato, 25.0, $selectedDate);
        $billItem = $billGrn->items->first();

        // Create match for full quantity (25 kg)
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->tomato->id,
            'matched_qty' => 25.0,
            'matched_unit' => 'kg',
            'base_qty' => 25.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->adminUser->id,
            'confirmed_at' => now(),
        ]);

        $data = $this->service->getDailyPendingAdvances($selectedDate, $this->warehouseA->id);
        $this->assertSame(0, $data['total_pending_count']);
    }

    public function test_partial_advance_shares_remaining_qty_only(): void
    {
        $selectedDate = '2026-09-12';

        // Advance 10 kg
        $advGrn = $this->createAdvanceGrn($this->warehouseA, $this->tomato, 10.0, $selectedDate, 'GRN-20260912-0050');
        $advItem = $advGrn->items->first();

        $billGrn = $this->createBillGrn($this->warehouseA, $this->tomato, 4.0, $selectedDate);
        $billItem = $billGrn->items->first();

        // Match 4 kg
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->tomato->id,
            'matched_qty' => 4.0,
            'matched_unit' => 'kg',
            'base_qty' => 4.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->adminUser->id,
            'confirmed_at' => now(),
        ]);

        $data = $this->service->getDailyPendingAdvances($selectedDate, $this->warehouseA->id);
        $message = $this->service->buildWhatsAppMessage($data);

        $this->assertSame(1, $data['total_pending_count']);
        $this->assertEquals(6.0, $data['standard_items'][0]['remaining_qty']);

        // Must show remaining qty (6 kg), NOT 10 kg
        $this->assertStringContainsString('1. Tomato — 6 kg', $message);
        $this->assertStringNotContainsString('10 kg', $message);
    }

    public function test_unit_issue_is_classified_correctly(): void
    {
        $selectedDate = '2026-09-12';

        // Advance received in KG (20 kg)
        $advGrn = $this->createAdvanceGrnWithUnit($this->warehouseA, $this->bananaStem, 20.0, 'kg', $selectedDate, 'GRN-20260912-0018');

        // Same-day PO exists for Banana Stem but in 'piece' unit with no conversion defined
        $po = PurchaseOrder::create([
            'po_number' => 'PO-UNIT-001',
            'destination_shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'order_date' => $selectedDate,
            'created_by' => $this->adminUser->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->bananaStem->id,
            'quantity' => 20.0,
            'unit_price' => 25.0,
            'total_price' => 500.0,
            'purchase_unit' => 'piece',
        ]);

        $data = $this->service->getDailyPendingAdvances($selectedDate, $this->warehouseA->id);
        $message = $this->service->buildWhatsAppMessage($data);

        $this->assertSame(1, $data['total_pending_count']);
        $this->assertCount(0, $data['standard_items']);
        $this->assertCount(1, $data['unit_issues']);

        $issue = $data['unit_issues'][0];
        $this->assertSame('Banana Stem', $issue['product_name']);
        $this->assertEquals(20.0, $issue['advance_qty']);
        $this->assertSame('kg', $issue['advance_unit']);
        $this->assertEquals(20.0, $issue['bill_qty']);
        $this->assertSame('piece', $issue['bill_unit']);
        $this->assertSame('Unit conversion missing', $issue['reason']);

        $this->assertStringContainsString('UNIT ISSUE', $message);
        $this->assertStringContainsString('Banana Stem', $message);
        $this->assertStringContainsString('Advance: 20 kg', $message);
        $this->assertStringContainsString('Bill: 20 piece', $message);
        $this->assertStringContainsString('Reason: Unit conversion missing', $message);
        $this->assertStringContainsString('GRN: GRN-20260912-0018', $message);
    }

    public function test_selected_warehouse_filter_is_respected(): void
    {
        $selectedDate = '2026-09-12';

        // Advance in warehouse A
        $this->createAdvanceGrn($this->warehouseA, $this->tomato, 25.0, $selectedDate, 'GRN-WH-A');

        // Advance in warehouse B
        $this->createAdvanceGrn($this->warehouseB, $this->cucumber, 15.0, $selectedDate, 'GRN-WH-B');

        // Filter to warehouse A only
        $dataA = $this->service->getDailyPendingAdvances($selectedDate, $this->warehouseA->id);
        $this->assertSame(1, $dataA['total_pending_count']);
        $this->assertSame('Tomato', $dataA['standard_items'][0]['product_name']);

        // Filter to warehouse B only
        $dataB = $this->service->getDailyPendingAdvances($selectedDate, $this->warehouseB->id);
        $this->assertSame(1, $dataB['total_pending_count']);
        $this->assertSame('Cucumber', $dataB['standard_items'][0]['product_name']);
    }

    public function test_selected_date_is_respected(): void
    {
        $date1 = '2026-09-11';
        $date2 = '2026-09-12';

        $this->createAdvanceGrn($this->warehouseA, $this->tomato, 25.0, $date1, 'GRN-DAY1');
        $this->createAdvanceGrn($this->warehouseA, $this->cucumber, 14.0, $date2, 'GRN-DAY2');

        $data1 = $this->service->getDailyPendingAdvances($date1, $this->warehouseA->id);
        $this->assertSame(1, $data1['total_pending_count']);
        $this->assertSame('Tomato', $data1['standard_items'][0]['product_name']);

        $data2 = $this->service->getDailyPendingAdvances($date2, $this->warehouseA->id);
        $this->assertSame(1, $data2['total_pending_count']);
        $this->assertSame('Cucumber', $data2['standard_items'][0]['product_name']);
    }

    public function test_share_action_does_not_modify_database_or_inventory(): void
    {
        $selectedDate = '2026-09-12';

        $advGrn = $this->createAdvanceGrn($this->warehouseA, $this->tomato, 25.0, $selectedDate, 'GRN-20260912-0012');

        $initialGrnStatus = $advGrn->fresh()->status;
        $initialBillStatus = $advGrn->fresh()->bill_status;
        $initialMatchCount = AdvanceReceiveMatch::count();
        $initialBatchQty = StockBatch::where('goods_received_id', $advGrn->id)->value('current_quantity');

        // Run share service
        $url = $this->service->generateShareUrl($selectedDate, $this->warehouseA->id);

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://api.whatsapp.com/send?text=', $url);

        // Verify zero database modifications
        $this->assertSame($initialGrnStatus, $advGrn->fresh()->status);
        $this->assertSame($initialBillStatus, $advGrn->fresh()->bill_status);
        $this->assertSame($initialMatchCount, AdvanceReceiveMatch::count());
        $this->assertEquals($initialBatchQty, StockBatch::where('goods_received_id', $advGrn->id)->value('current_quantity'));
    }

    public function test_empty_day_does_not_share_and_redirects_with_warning(): void
    {
        $selectedDate = '2026-09-12';

        $url = $this->service->generateShareUrl($selectedDate, $this->warehouseA->id);
        $this->assertNull($url);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory.share.unmatched-advances.whatsapp', [
            'date' => $selectedDate,
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('warning', 'No pending Advance bills for this day.');
    }

    public function test_web_route_redirects_to_whatsapp_when_pending_advances_exist(): void
    {
        $selectedDate = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->tomato, 25.0, $selectedDate, 'GRN-20260912-0012');

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory.share.unmatched-advances.whatsapp', [
            'date' => $selectedDate,
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://api.whatsapp.com/send?text=', $response->getTargetUrl());
    }

    public function test_inventory_bills_match_page_renders_whatsapp_share_button(): void
    {
        $selectedDate = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->tomato, 25.0, $selectedDate, 'GRN-20260912-0012');

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'bills_match',
            'date' => $selectedDate,
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertOk();
        $response->assertSee('WhatsApp — Pending Bills');
        $response->assertSee('https://api.whatsapp.com/send?text=');
    }

    private function createAdvanceGrn(Warehouse $warehouse, Product $product, float $qty, string $date, ?string $grnNumber = null): GoodsReceived
    {
        return $this->createAdvanceGrnWithUnit($warehouse, $product, $qty, $product->unit, $date, $grnNumber);
    }

    private function createAdvanceGrnWithUnit(Warehouse $warehouse, Product $product, float $qty, string $unit, string $date, ?string $grnNumber = null): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'grn_number' => $grnNumber ?? 'ADV-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => $date,
            'received_by' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $unit,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'reference' => 'BATCH-'.uniqid(),
            'total_kg' => $qty,
            'cost_per_kg' => 50.0,
            'quantity' => $qty,
            'current_quantity' => $qty,
            'received_at' => $date,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        return $grn;
    }

    private function createBillGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-'.uniqid(),
            'destination_shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'order_date' => $date,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'unit' => $product->unit,
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-'.uniqid(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $this->supplier->id,
            'receipt_type' => 'normal',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => $date,
            'received_by' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'reference' => 'BATCH-'.uniqid(),
            'total_kg' => $qty,
            'cost_per_kg' => 50.0,
            'quantity' => $qty,
            'current_quantity' => $qty,
            'received_at' => $date,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        return $grn;
    }
}
