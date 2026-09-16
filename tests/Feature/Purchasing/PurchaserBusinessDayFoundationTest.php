<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\DTOs\Purchasing\PurchaseInvoiceData;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyInventoryComparisonService;
use App\Services\Purchasing\PurchaseInvoiceService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaserBusinessDayFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Product $product;

    private PurchaserBusinessDayService $businessDayService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();

        $this->warehouse = Warehouse::create([
            'name' => 'Fruit Main Warehouse',
            'code' => 'WH-FRUIT',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Fresh Fruit Supplier',
            'type' => 'farmer',
            'status' => 'active',
        ]);

        $category = Category::create([
            'name' => 'Fruits',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Apple Royal Gala',
            'sku' => 'APL-RG-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $this->businessDayService = app(PurchaserBusinessDayService::class);
    }

    /**
     * Test A: Open Fruit Business Day 15 Sep.
     * Advance received: 15 Sep 11:30 PM.
     * Bill received: 16 Sep 08:00 AM.
     * Both business_day_id = same day.
     * Expected: eligible to match & successfully matches across midnight.
     */
    public function test_case_a_cross_midnight_same_business_day_matching(): void
    {
        $businessDay = $this->businessDayService->open(
            $this->warehouse->id,
            '2026-09-15',
            $this->user->id
        );

        // 1. Advance received at 15 Sep 11:30 PM
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $businessDay->id,
            'grn_number' => 'GRN-ADV-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->user->id,
            'received_at' => '2026-09-15 23:30:00',
        ]);

        $advItem = GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $advBatch = StockBatch::create([
            'reference' => 'BATCH-ADV-001',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'batch_number' => 'BATCH-ADV-001',
            'total_kg' => 50.0,
            'cost_per_kg' => 10.0,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->user->id,
            'received_at' => '2026-09-15',
        ]);

        // 2. Bill received at 16 Sep 08:00 AM under same business day
        $billGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $businessDay->id,
            'grn_number' => 'GRN-BILL-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->user->id,
            'received_at' => '2026-09-16 08:00:00',
        ]);

        $billItem = GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $billBatch = StockBatch::create([
            'reference' => 'BATCH-BILL-001',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'batch_number' => 'BATCH-BILL-001',
            'total_kg' => 50.0,
            'cost_per_kg' => 10.0,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->user->id,
            'received_at' => '2026-09-16',
        ]);

        // Eligibility check
        $this->assertTrue($this->businessDayService->areEligibleForMatch($advGrn, $billGrn));

        // Execute match via canonical DailyInventoryComparisonService
        $comparisonService = app(DailyInventoryComparisonService::class);
        $result = $comparisonService->executeDayInventoryMatch(
            '2026-09-15',
            $this->product->id,
            'kg',
            $this->warehouse->id,
            null,
            $this->user->id
        );

        $this->assertEquals(50.0, $result['matched_qty']);
        $this->assertEquals(1, $result['matches_count']);

        $this->assertDatabaseHas('advance_receive_matches', [
            'business_day_id' => $businessDay->id,
            'advance_goods_received_id' => $advGrn->id,
            'bill_goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'matched_qty' => 50.0,
        ]);
    }

    /**
     * Test B: Different business_day_id -> matching rejected.
     */
    public function test_case_b_different_business_day_matching_rejected(): void
    {
        $day1 = $this->businessDayService->open($this->warehouse->id, '2026-09-15', $this->user->id);
        $this->businessDayService->close($day1, $this->user->id, 'EOD');

        $day2 = $this->businessDayService->open($this->warehouse->id, '2026-09-16', $this->user->id);

        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $day1->id,
            'grn_number' => 'GRN-ADV-DAY1',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->user->id,
            'received_at' => '2026-09-15 20:00:00',
        ]);

        $billGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $day2->id,
            'grn_number' => 'GRN-BILL-DAY2',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->user->id,
            'received_at' => '2026-09-16 08:00:00',
        ]);

        $this->assertFalse($this->businessDayService->areEligibleForMatch($advGrn, $billGrn));
    }

    /**
     * Test C: Legacy records both business_day_id NULL on same received_at date -> current matching still works.
     * If dates differ, rejected.
     */
    public function test_case_c_legacy_records_null_business_day_fallback(): void
    {
        $legacyAdv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => null,
            'grn_number' => 'GRN-LEGACY-ADV',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->user->id,
            'received_at' => '2026-09-15 10:00:00',
        ]);

        $legacyBillSameDay = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => null,
            'grn_number' => 'GRN-LEGACY-BILL-1',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->user->id,
            'received_at' => '2026-09-15 16:00:00',
        ]);

        $legacyBillDiffDay = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => null,
            'grn_number' => 'GRN-LEGACY-BILL-2',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->user->id,
            'received_at' => '2026-09-16 10:00:00',
        ]);

        // Same calendar date legacy -> matches
        $this->assertTrue($this->businessDayService->areEligibleForMatch($legacyAdv, $legacyBillSameDay));

        // Different calendar date legacy -> rejected
        $this->assertFalse($this->businessDayService->areEligibleForMatch($legacyAdv, $legacyBillDiffDay));

        // Legacy vs New Business Day record -> rejected (no accidental cross-matching)
        $day = $this->businessDayService->open($this->warehouse->id, '2026-09-15', $this->user->id);
        $newAdv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $day->id,
            'grn_number' => 'GRN-NEW-ADV',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->user->id,
            'received_at' => '2026-09-15 10:00:00',
        ]);

        $this->assertFalse($this->businessDayService->areEligibleForMatch($newAdv, $legacyBillSameDay));
    }

    /**
     * Test D: Closed Business Day -> new bill/advance blocked.
     */
    public function test_case_d_closed_business_day_blocks_mutation(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, '2026-09-15', $this->user->id);
        $this->businessDayService->close($day, $this->user->id, 'Day closed for the night');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('is closed. Reopen the business day to perform mutations.');

        $action = app(RecordGoodsReceiptAction::class);
        $data = new GoodsReceivedData(
            purchaseOrderId: null,
            receivedAt: '2026-09-15 23:00:00',
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Test advance on closed day',
            items: [
                ['purchase_order_item_id' => null, 'product_id' => $this->product->id, 'received_qty' => 10.0, 'received_unit' => 'kg'],
            ],
            warehouseId: $this->warehouse->id,
            receiptType: 'warehouse_advance',
            businessDayId: $day->id,
        );

        $action->execute($data, $this->user->id);
    }

    /**
     * Test E: Reopen with reason -> bill/advance allowed again.
     */
    public function test_case_e_reopen_with_reason_allows_mutation(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, '2026-09-15', $this->user->id);
        $this->businessDayService->close($day, $this->user->id, 'Closing day');

        $this->assertTrue($day->fresh()->isClosed());

        // Reopen requires reason
        $reopenedDay = $this->businessDayService->reopen($day, $this->user->id, 'Late midnight delivery arrived from farm');

        $this->assertTrue($reopenedDay->isReopened());
        $this->assertTrue($reopenedDay->isActive());
        $this->assertEquals('Late midnight delivery arrived from farm', $reopenedDay->reopen_reason);

        $action = app(RecordGoodsReceiptAction::class);
        $data = new GoodsReceivedData(
            purchaseOrderId: null,
            receivedAt: '2026-09-16 01:30:00',
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Late midnight advance',
            items: [
                ['purchase_order_item_id' => null, 'product_id' => $this->product->id, 'received_qty' => 20.0, 'received_unit' => 'kg'],
            ],
            warehouseId: $this->warehouse->id,
            receiptType: 'warehouse_advance',
            businessDayId: $reopenedDay->id,
        );

        $grn = $action->execute($data, $this->user->id);

        $this->assertNotNull($grn);
        $this->assertEquals($reopenedDay->id, $grn->business_day_id);
    }

    /**
     * Test F: Cart -> PO -> GRN -> Invoice business_day_id propagates correctly.
     */
    public function test_case_f_cart_po_grn_invoice_propagation(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, '2026-09-15', $this->user->id);

        // 1. Create cart under active day
        $cart = PurchaserCart::create([
            'cart_number' => 'CART-TEST-001',
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $day->id,
            'business_date' => '2026-09-15',
            'status' => 'draft',
        ]);

        $cart->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 100.0,
            'unit' => 'kg',
            'unit_price' => 10.0,
            'total_price' => 1000.0,
        ]);

        // 2. PO inherits business_day_id from cart
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $cart->business_day_id,
            'order_date' => '2026-09-15',
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);
        $poItem = $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 100.0,
            'purchase_unit' => 'kg',
            'unit_price' => 10.0,
            'total_price' => 1000.0,
        ]);

        $this->assertEquals($day->id, $po->business_day_id);

        // 3. Receive PO to GRN
        $action = app(RecordGoodsReceiptAction::class);
        $data = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: '2026-09-15 14:00:00',
            transportCost: 50.0,
            labourCost: 20.0,
            notes: 'Full receipt',
            items: [
                ['purchase_order_item_id' => $poItem->id, 'product_id' => $this->product->id, 'received_qty' => 100.0, 'received_unit' => 'kg'],
            ],
            warehouseId: $this->warehouse->id,
        );

        $grn = $action->execute($data, $this->user->id);
        $this->assertEquals($day->id, $grn->business_day_id);

        // 4. Create Purchase Invoice
        $invoiceService = app(PurchaseInvoiceService::class);
        $invoiceData = new PurchaseInvoiceData(
            goodsReceivedId: $grn->id,
            supplierId: $this->supplier->id,
            invoiceNumber: 'INV-TEST-001',
            amount: 1000.0,
            status: 'pending',
            notes: 'Test invoice',
        );
        $invoice = $invoiceService->create($invoiceData);

        $this->assertEquals($day->id, $invoice->business_day_id);
    }

    /**
     * Test G: received_at remains actual timestamp and is never rewritten to business_date.
     */
    public function test_case_g_received_at_remains_actual_timestamp(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, '2026-09-15', $this->user->id);

        $realTimestamp = '2026-09-16 04:45:12';

        $action = app(RecordGoodsReceiptAction::class);
        $data = new GoodsReceivedData(
            purchaseOrderId: null,
            receivedAt: $realTimestamp,
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Physical arrival timestamp',
            items: [
                ['purchase_order_item_id' => null, 'product_id' => $this->product->id, 'received_qty' => 15.0, 'received_unit' => 'kg'],
            ],
            warehouseId: $this->warehouse->id,
            receiptType: 'warehouse_advance',
        );

        $grn = $action->execute($data, $this->user->id);

        $this->assertEquals($day->id, $grn->business_day_id);
        $this->assertEquals($realTimestamp, Carbon::parse($grn->received_at)->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-09-15', $this->businessDayService->effectiveBusinessDate($grn));
    }

    /**
     * Test warehouse cannot have multiple concurrently active open/reopened days.
     */
    public function test_warehouse_cannot_have_multiple_active_business_days(): void
    {
        $this->businessDayService->open($this->warehouse->id, '2026-09-15', $this->user->id);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already open for this warehouse');

        $this->businessDayService->open($this->warehouse->id, '2026-09-16', $this->user->id);
    }
}
