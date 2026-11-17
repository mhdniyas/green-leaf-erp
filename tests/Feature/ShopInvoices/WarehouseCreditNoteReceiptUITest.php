<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteReceiptStatus;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteReceipt;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\Inventory\StockLedgerService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopCreditNoteWarehouseReceiptService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseCreditNoteReceiptUITest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouseStaff;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Shop $shopB;

    private Warehouse $warehouseCentral;

    private Warehouse $warehouseHub;

    private Product $productOnion;

    private Product $productTomato;

    private ShopInvoice $invoiceA1;

    private ShopInvoiceItem $invoiceItemA1;

    private StockBatch $initialDispatchBatch;

    private ShopCreditNoteService $creditNoteService;

    private ShopCreditNoteWarehouseReceiptService $receiptService;

    private StockLedgerService $stockLedgerService;

    private InvoiceCashbookProjectionService $projectionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        config(['admin.user_access.main_admin_email' => 'admin@example.com']);

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->admin->assignRole('admin');

        $this->warehouseStaff = User::factory()->create([
            'name' => 'Kochi Warehouse Operator',
        ]);
        $this->warehouseStaff->assignRole('warehouse_receiver');

        $this->unauthorizedUser = User::factory()->create([
            'name' => 'Purchaser Staff',
        ]);
        $this->unauthorizedUser->assignRole('purchaser');

        $this->warehouseCentral = Warehouse::factory()->create([
            'code' => 'WH-CENTRAL',
            'name' => 'Central Warehouse',
            'is_active' => true,
        ]);

        $this->warehouseHub = Warehouse::factory()->create([
            'code' => 'WH-HUB',
            'name' => 'Suburban Hub Warehouse',
            'is_active' => true,
        ]);

        $this->shopA = Shop::factory()->create([
            'code' => 'SHP-A',
            'name' => 'Kochi Central Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shopB = Shop::factory()->create([
            'code' => 'SHP-B',
            'name' => 'Calicut Retail Hub',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->projectionService = app(InvoiceCashbookProjectionService::class);
        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->receiptService = app(ShopCreditNoteWarehouseReceiptService::class);
        $this->stockLedgerService = app(StockLedgerService::class);

        $this->productOnion = Product::factory()->create([
            'name' => 'Big Onion',
            'default_warehouse_id' => $this->warehouseCentral->id,
        ]);

        $this->productTomato = Product::factory()->create([
            'name' => 'Tomato Local',
            'default_warehouse_id' => $this->warehouseCentral->id,
        ]);

        // Stock in: 200 kg Grade A @ Rs 40/kg
        $this->initialDispatchBatch = StockBatch::factory()->create([
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'cost_per_kg' => 40.00,
            'total_kg' => 200.0,
        ]);

        StockMovement::create([
            'batch_id' => $this->initialDispatchBatch->id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 200.0,
            'cost_per_unit' => 40.00,
            'created_by' => $this->admin->id,
            'notes' => 'Initial purchase stock in',
        ]);

        // Invoice for 100 kg Grade A @ Rs 55/kg
        $this->invoiceA1 = $this->createFinalizedInvoice(
            $this->shopA,
            'SINV-WH-101',
            today()->subDays(3)->toDateString(),
            5500.00,
            0.00
        );

        $this->invoiceItemA1 = $this->createInvoiceItem(
            $this->invoiceA1,
            $this->productOnion,
            'A',
            100.0,
            55.00
        );

        // Loadout deduction: 100 kg Grade A
        StockMovement::create([
            'batch_id' => $this->initialDispatchBatch->id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 100.0,
            'cost_per_unit' => 40.00,
            'created_by' => $this->admin->id,
            'shop_order_item_id' => $this->invoiceItemA1->shop_order_item_id,
            'notes' => "Warehouse loadout — Invoice: {$this->invoiceA1->invoice_number}",
        ]);
    }

    private function createFinalizedInvoice(
        Shop $shop,
        string $invoiceNumber,
        ?string $date = null,
        float $total = 5500.00,
        float $paid = 0.00
    ): ShopInvoice {
        static $orderCounter = 1;
        $orderNum = $orderCounter++;
        $businessDate = $date ?? today()->toDateString();
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
            'shop_daily_order_key' => "shop:{$shop->id}:{$businessDate}:{$orderNum}",
        ]);

        $balance = max(0.00, $total - $paid);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => $balance <= 0.0001 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
            'final_total' => $total,
            'paid_amount' => $paid,
            'balance_amount' => $balance,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);

        $this->projectionService->syncInvoice($invoice);

        return $invoice;
    }

    private function createInvoiceItem(ShopInvoice $invoice, Product $product, string $grade, float $qty, float $price): ShopInvoiceItem
    {
        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $invoice->shop_order_id,
            'product_id' => $product->id,
            'product_grade' => $grade,
            'requested_qty' => $qty,
            'approved_qty' => $qty,
            'loaded_qty' => $qty,
            'delivered_qty' => $qty,
            'unit' => 'kg',
            'unit_cost' => 40.00,
        ]);

        return ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_grade' => $grade,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => $qty,
            'delivered_qty' => $qty,
            'unit_price' => $price,
            'line_subtotal' => round($qty * $price, 2),
            'final_line_total' => round($qty * $price, 2),
        ]);
    }

    private function createFinalizedReturnCreditNote(
        ShopInvoiceItem $sourceItem,
        float $returnQty = 40.0,
        float $creditRate = 35.00
    ): ShopCreditNote {
        $draft = $this->creditNoteService->createShopLevelDraft($sourceItem->invoice->shop, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Customer return claim',
            'items' => [
                [
                    'source_shop_invoice_id' => $sourceItem->shop_invoice_id,
                    'shop_invoice_item_id' => $sourceItem->id,
                    'product_id' => $sourceItem->product_id,
                    'product_grade' => $sourceItem->product_grade,
                    'unit' => 'kg',
                    'credit_quantity' => $returnQty,
                    'credit_rate' => $creditRate,
                    'line_credit_amount' => round($returnQty * $creditRate, 2),
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                ],
            ],
        ], $this->admin);

        return $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
    }

    /**
     * Test 01: Authorized warehouse user opens pending returns queue (200 OK, shows expected item).
     */
    public function test_01_authorized_warehouse_user_opens_pending_returns_queue(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);

        $response = $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.receiver.credit-notes.index', ['status' => 'pending']));

        $response->assertOk();
        $response->assertSee($creditNote->credit_note_number);
        $response->assertSee('Big Onion');
        $response->assertSee('40.00');
        $response->assertSee('Kochi Central Shop');
        $response->assertSee('cdn.jsdelivr.net/npm/alpinejs');
        $response->assertSee('unpkg.com/lucide');
        $response->assertSee('window.warehouseCreditNoteManager = warehouseCreditNoteManager;', false);
    }

    /**
     * Test 02: Unauthorized user is rejected with 403.
     */
    public function test_02_unauthorized_user_rejected_with_403(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // GET index -> 403 (JSON request) or redirect to dashboard (web request)
        $this->actingAs($this->unauthorizedUser)
            ->getJson(route('warehouse.receiver.credit-notes.index'))
            ->assertForbidden();

        // POST receive -> 403
        $this->actingAs($this->unauthorizedUser)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 40.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
            ])
            ->assertForbidden();
    }

    /**
     * Test 03: Shop and product filters work properly.
     */
    public function test_03_shop_and_product_filters_work_properly(): void
    {
        // Credit note for Shop A (Onion)
        $cnA = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 30.0, 35.00);

        // Invoice and credit note for Shop B (Tomato)
        $invB = $this->createFinalizedInvoice($this->shopB, 'SINV-WH-B01', today()->toDateString(), 2000.0, 0.0);
        $itemB = $this->createInvoiceItem($invB, $this->productTomato, 'A', 50.0, 40.0);
        $cnB = $this->createFinalizedReturnCreditNote($itemB, 20.0, 30.0);

        // Filter by Shop A
        $respShopA = $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.receiver.credit-notes.index', [
                'status' => 'pending',
                'shop_id' => $this->shopA->id,
            ]));
        $respShopA->assertOk();
        $respShopA->assertSee($cnA->credit_note_number);
        $respShopA->assertDontSee($cnB->credit_note_number);

        // Filter by Product Tomato
        $respTomato = $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.receiver.credit-notes.index', [
                'status' => 'pending',
                'product_id' => $this->productTomato->id,
            ]));
        $respTomato->assertOk();
        $respTomato->assertSee($cnB->credit_note_number);
        $respTomato->assertDontSee($cnA->credit_note_number);
    }

    /**
     * Test 04: Grade A supplied -> Grade B physically received increases Grade B stock and leaves Grade A unchanged.
     */
    public function test_04_grade_a_supplied_grade_b_received_increases_grade_b_and_leaves_grade_a_unchanged(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Grade A stock before = 100 kg (200 in - 100 out)
        $stockABefore = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeA
        );
        $this->assertEquals(100.0, $stockABefore);

        // Grade B stock before = 0 kg
        $stockBBefore = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(0.0, $stockBBefore);

        // Post receive via UI endpoint
        $response = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 40.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
                'remarks' => 'Downgraded to Grade B upon warehouse inspection',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'received_quantity' => 40.0,
            'item_status' => ShopCreditNoteReceiptStatus::ReceivedAndVerified->value,
        ]);

        // Grade B stock increased by 40 kg
        $stockBAfter = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(40.0, $stockBAfter);

        // Grade A stock remained unchanged at 100 kg
        $stockAAfter = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeA
        );
        $this->assertEquals(100.0, $stockAAfter);
    }

    /**
     * Test 05: Grade A supplied -> Grade C physically received increases Grade C stock.
     */
    public function test_05_grade_a_supplied_grade_c_received_increases_grade_c_stock(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 25.0, 35.00);
        $item = $creditNote->items->first();

        $response = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 25.0,
                'received_grade' => 'C',
                'warehouse_id' => $this->warehouseCentral->id,
                'remarks' => 'Heavily downgraded to Grade C',
            ]);

        $response->assertOk();

        $stockCAfter = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeC
        );
        $this->assertEquals(25.0, $stockCAfter);
    }

    /**
     * Test 06: Partial receipt updates item status to partially_received.
     */
    public function test_06_partial_receipt_updates_item_status_to_partially_received(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $response = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 15.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'received_quantity' => 15.0,
            'item_status' => ShopCreditNoteReceiptStatus::PartiallyReceived->value,
        ]);

        $item->refresh();
        $this->assertEquals(ShopCreditNoteReceiptStatus::PartiallyReceived, $item->receipt_status);
        $this->assertEquals(15.0, (float) $item->received_quantity);
    }

    /**
     * Test 07: Mixed B/C receipts tracked as separate receipts.
     */
    public function test_07_mixed_bc_receipts_tracked_as_separate_receipts(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // 1st partial: 15 kg Grade B
        $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 15.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
            ])
            ->assertOk();

        // 2nd partial: 25 kg Grade C
        $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 25.0,
                'received_grade' => 'C',
                'warehouse_id' => $this->warehouseCentral->id,
            ])
            ->assertOk();

        $item->refresh();
        $this->assertEquals(ShopCreditNoteReceiptStatus::ReceivedAndVerified, $item->receipt_status);
        $this->assertEquals(40.0, (float) $item->received_quantity);
        $this->assertCount(2, $item->receipts);

        $stockB = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(15.0, $stockB);

        $stockC = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeC
        );
        $this->assertEquals(25.0, $stockC);
    }

    /**
     * Test 08: Receipt beyond expected remaining quantity is rejected with 422.
     */
    public function test_08_receipt_beyond_expected_remaining_quantity_is_rejected_422(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Attempt to receive 45 kg on 40 kg expected
        $response = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 45.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test 09: Damaged receipt creates WastageEntry and no saleable stock increase.
     */
    public function test_09_damaged_receipt_creates_wastage_entry_and_no_saleable_stock_increase(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 30.0, 35.00);
        $item = $creditNote->items->first();

        $response = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.damage', $item), [
                'quantity' => 30.0,
                'damage_reason' => 'transit_damage',
                'warehouse_id' => $this->warehouseCentral->id,
                'remarks' => 'Completely crushed in transit',
            ]);

        $response->assertOk();

        // Verify WastageEntry was created
        $wastage = WastageEntry::query()
            ->where('product_id', $this->productOnion->id)
            ->first();

        $this->assertNotNull($wastage);
        $this->assertEquals(30.0, (float) $wastage->quantity);
        $this->assertEquals(WastageReason::TransitDamage, $wastage->reason);

        // Verify no saleable stock increase in Grade A, B or C
        $this->assertEquals(0.0, $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        ));
        $this->assertEquals(0.0, $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeC
        ));
    }

    /**
     * Test 10: Dispute creates no stock movements and sets status to disputed.
     */
    public function test_10_dispute_creates_no_stock_movements_and_sets_status_disputed(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $stockBefore = StockMovement::count();

        $response = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.dispute', $item), [
                'reason' => 'Driver arrived without return crates; shop refuses to hand over goods.',
            ]);

        $response->assertOk();

        $item->refresh();
        $this->assertEquals(ShopCreditNoteReceiptStatus::Disputed, $item->receipt_status);
        $this->assertEquals($stockBefore, StockMovement::count());
    }

    /**
     * Test 11: Receipt history endpoint displays audit trail.
     */
    public function test_11_receipt_history_endpoint_displays_audit_trail(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Create receipt via service
        $receipt = $this->receiptService->receivePhysicalReturn(
            $item,
            20.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff,
            remarks: 'Partial batch 1'
        );

        $response = $this->actingAs($this->warehouseStaff)
            ->getJson(route('warehouse.receiver.credit-notes.items.history', $item));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'item_id' => $item->id,
            'expected_quantity' => 40.0,
            'received_quantity' => 20.0,
        ]);

        $data = $response->json();
        $this->assertCount(1, $data['receipts']);
        $this->assertEquals('B', $data['receipts'][0]['received_grade']);
        $this->assertEquals(20.0, $data['receipts'][0]['received_quantity']);
        $this->assertEquals('Kochi Warehouse Operator', $data['receipts'][0]['receiver_name']);
        $this->assertEquals('Central Warehouse', $data['receipts'][0]['warehouse_name']);
    }

    /**
     * Test 12: Original dispatched grade and credit rate remain read-only.
     */
    public function test_12_original_dispatched_grade_and_credit_rate_remain_read_only(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Warehouse receives with extra malicious payload trying to alter financial credit rate or dispatched grade
        $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 40.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
                'credit_rate' => 99.00,
                'product_grade' => 'C',
                'line_credit_amount' => 9999.00,
            ])
            ->assertOk();

        $item->refresh();
        $this->assertEquals(35.00, (float) $item->credit_rate);
        $this->assertSame('A', is_object($item->product_grade) ? $item->product_grade->value : $item->product_grade);
        $this->assertEquals(1400.00, (float) $item->line_credit_amount);
    }

    /**
     * Test 13: Receipt reversal decreases stock correctly.
     */
    public function test_13_receipt_reversal_decreases_stock_correctly(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        $this->assertEquals(40.0, $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        ));

        // Reversal via UI endpoint
        $response = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.receipts.reverse', $receipt), [
                'reason' => 'Scanned wrong crates; goods belong to different shipment',
            ]);

        $response->assertOk();

        $receipt->refresh();
        $this->assertTrue($receipt->isReversed());

        // Grade B stock decreased back to 0
        $this->assertEquals(0.0, $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        ));

        // Item status reset to PendingReceipt
        $item->refresh();
        $this->assertEquals(ShopCreditNoteReceiptStatus::PendingReceipt, $item->receipt_status);
        $this->assertEquals(0.0, (float) $item->received_quantity);
    }

    /**
     * Test 14: Insufficient stock blocks receipt reversal with 422.
     */
    public function test_14_insufficient_stock_blocks_receipt_reversal_with_422(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        // Deduct 35 kg of Grade B stock out of warehouse (e.g. sale loadout), leaving only 5 kg
        StockMovement::create([
            'batch_id' => $receipt->stockMovement->batch_id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'grade' => 'B',
            'type' => StockMovementType::Out->value,
            'quantity' => 35.0,
            'cost_per_unit' => 35.00,
            'created_by' => $this->admin->id,
            'notes' => 'Subsequent dispatch of returned goods',
        ]);

        // Attempting to reverse 40 kg when only 5 kg remains -> 422 error
        $response = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.receipts.reverse', $receipt), [
                'reason' => 'Should fail due to insufficient stock',
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test 15: Double reversal of same receipt is rejected with 422.
     */
    public function test_15_double_reversal_of_same_receipt_is_rejected_422(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        // 1st reversal succeeds
        $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.receipts.reverse', $receipt), [
                'reason' => 'First reversal',
            ])
            ->assertOk();

        // 2nd reversal fails with 422
        $response = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.receipts.reverse', $receipt), [
                'reason' => 'Second reversal attempt',
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test 16: Retry with same idempotency key does not create duplicate movements.
     */
    public function test_16_retry_with_same_idempotency_key_does_not_create_duplicate_movements(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $key = 'IDEMP-TEST-KEY-001';

        // 1st call
        $r1 = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 20.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
                'idempotency_key' => $key,
            ]);
        $r1->assertOk();

        // 2nd call with same key
        $r2 = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 20.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
                'idempotency_key' => $key,
            ]);
        $r2->assertOk();

        // Only 1 receipt and exactly 20 kg stock increase
        $this->assertCount(1, ShopCreditNoteReceipt::where('idempotency_key', $key)->get());
        $this->assertEquals(20.0, $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        ));
    }

    /**
     * Test 17: Distinct identical receipts with different keys are accepted.
     */
    public function test_17_distinct_identical_receipts_with_different_keys_are_accepted(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // 1st receipt: 10 kg
        $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 10.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
                'idempotency_key' => 'KEY-A',
            ])
            ->assertOk();

        // 2nd receipt: 10 kg with different key
        $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 10.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
                'idempotency_key' => 'KEY-B',
            ])
            ->assertOk();

        $this->assertEquals(20.0, $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        ));
    }

    /**
     * Test 18: Cancelled/voided Credit Note cannot receive goods (422).
     */
    public function test_18_cancelled_voided_credit_note_cannot_receive_goods(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Cancel credit note (no physical receipts existed)
        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Client cancelled claim');

        $this->assertEquals(ShopCreditNoteStatus::Voided, $creditNote->fresh()->status);

        // Attempting to receive goods now should fail with 422
        $response = $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 40.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test 19: Office cancellation allowed after all physical receipts reversed.
     */
    public function test_19_office_cancellation_allowed_after_all_physical_receipts_reversed(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Warehouse receives 40 kg Grade B
        $receipt = $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        // Office cancellation is BLOCKED while active receipt exists
        $blockResponse = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.reports.shops.credit-notes.cancel', $creditNote), [
                'reason' => 'Customer dispute resolved without return',
            ]);
        $blockResponse->assertStatus(422);
        $this->assertEquals(ShopCreditNoteStatus::Approved, $creditNote->fresh()->status);

        // Warehouse reverses the receipt
        $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.receipts.reverse', $receipt), [
                'reason' => 'Correcting error before cancellation',
            ])
            ->assertOk();

        // Office cancellation SUCCEEDS now
        $successResponse = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.reports.shops.credit-notes.cancel', $creditNote), [
                'reason' => 'All physical receipts reversed, voiding credit note',
            ]);
        $successResponse->assertRedirect();

        $this->assertEquals(ShopCreditNoteStatus::Voided, $creditNote->fresh()->status);
    }

    /**
     * Test 20: Existing legacy warehouse receiving remains functional.
     */
    public function test_20_existing_legacy_warehouse_receiving_remains_functional(): void
    {
        $response = $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.receiver.checklist'));

        $response->assertOk();
    }

    /**
     * Test 21: Cashbook and financial credit totals remain unchanged by receiving.
     */
    public function test_21_cashbook_and_financial_credit_totals_remain_unchanged_by_receiving(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Capture financial tx count and shop credit note amounts before receipt
        $txCountBefore = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->count();
        $totalCreditBefore = (float) $creditNote->total_credit_amount;

        $this->assertEquals(1, $txCountBefore);
        $this->assertEquals(1400.00, $totalCreditBefore);

        // Warehouse receives goods
        $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 40.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
            ])
            ->assertOk();

        $creditNote->refresh();
        $txCountAfter = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->count();

        // Financial records completely untouched
        $this->assertEquals(1, $txCountAfter);
        $this->assertEquals($totalCreditBefore, (float) $creditNote->total_credit_amount);
    }

    /**
     * Test 22: Warehouse permissions enforced on every endpoint.
     */
    public function test_22_warehouse_permissions_enforced_on_every_endpoint(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            $item,
            10.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        // Index
        $this->actingAs($this->unauthorizedUser)
            ->getJson(route('warehouse.receiver.credit-notes.index'))
            ->assertForbidden();

        // Receive
        $this->actingAs($this->unauthorizedUser)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $item), [
                'quantity' => 10.0,
                'received_grade' => 'B',
                'warehouse_id' => $this->warehouseCentral->id,
            ])
            ->assertForbidden();

        // Damage
        $this->actingAs($this->unauthorizedUser)
            ->postJson(route('warehouse.receiver.credit-notes.items.damage', $item), [
                'quantity' => 10.0,
                'damage_reason' => 'transit_damage',
                'warehouse_id' => $this->warehouseCentral->id,
            ])
            ->assertForbidden();

        // Dispute
        $this->actingAs($this->unauthorizedUser)
            ->postJson(route('warehouse.receiver.credit-notes.items.dispute', $item), [
                'reason' => 'Unauthorized dispute',
            ])
            ->assertForbidden();

        // Reverse
        $this->actingAs($this->unauthorizedUser)
            ->postJson(route('warehouse.receiver.credit-notes.receipts.reverse', $receipt), [
                'reason' => 'Unauthorized reversal',
            ])
            ->assertForbidden();

        // History
        $this->actingAs($this->unauthorizedUser)
            ->getJson(route('warehouse.receiver.credit-notes.items.history', $item))
            ->assertForbidden();
    }

    /**
     * Test: Stat counter cards at the top are clickable links that filter the return items queue.
     */
    public function test_stat_counter_cards_are_clickable_and_filter_return_items(): void
    {
        // Item 1: Pending receipt
        $cnPending = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 20.0, 35.00);

        // Item 2: Verified & Stocked
        $invB = $this->createFinalizedInvoice($this->shopB, 'SINV-WH-VERIF-01', today()->toDateString(), 1500.0, 0.0);
        $itemB = $this->createInvoiceItem($invB, $this->productTomato, 'A', 10.0, 40.0);
        $cnVerified = $this->createFinalizedReturnCreditNote($itemB, 10.0, 40.00);
        $verifiedItem = $cnVerified->items->first();

        // Perform receipt to make it verified
        $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $verifiedItem), [
                'quantity' => 10.0,
                'received_grade' => 'A',
                'warehouse_id' => $this->warehouseCentral->id,
            ])
            ->assertOk();

        // 1. Visit with status=pending (default)
        $respPending = $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.receiver.credit-notes.index', ['status' => 'pending']));

        $respPending->assertOk();
        // Assert stat cards are links with href
        $respPending->assertSee('href="'.route('warehouse.receiver.credit-notes.index', ['status' => 'pending']).'"', false);
        $respPending->assertSee('href="'.route('warehouse.receiver.credit-notes.index', ['status' => 'verified']).'"', false);
        $respPending->assertSee('href="'.route('warehouse.receiver.credit-notes.index', ['status' => 'partially_received']).'"', false);
        $respPending->assertSee('href="'.route('warehouse.receiver.credit-notes.index', ['status' => 'disputed']).'"', false);

        // Pending item is visible, verified item is NOT shown in table
        $respPending->assertSee($cnPending->credit_note_number);
        $respPending->assertDontSee($cnVerified->credit_note_number);

        // 2. Click / visit the Verified stat card link (status=verified)
        $respVerified = $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.receiver.credit-notes.index', ['status' => 'verified']));

        $respVerified->assertOk();
        // Verified item is now visible below, pending item is filtered out
        $respVerified->assertSee($cnVerified->credit_note_number);
        $respVerified->assertDontSee($cnPending->credit_note_number);
    }

    /**
     * Test: Stat counter cards at the top are clickable links that filter the return items queue.
     */
    public function test_stat_counter_cards_are_clickable_and_filter_return_items(): void
    {
        // Item 1: Pending receipt
        $cnPending = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 20.0, 35.00);

        // Item 2: Verified & Stocked
        $invB = $this->createFinalizedInvoice($this->shopB, 'SINV-WH-VERIF-01', today()->toDateString(), 1500.0, 0.0);
        $itemB = $this->createInvoiceItem($invB, $this->productTomato, 'A', 10.0, 40.0);
        $cnVerified = $this->createFinalizedReturnCreditNote($itemB, 10.0, 40.00);
        $verifiedItem = $cnVerified->items->first();

        // Perform receipt to make it verified
        $this->actingAs($this->warehouseStaff)
            ->postJson(route('warehouse.receiver.credit-notes.items.receive', $verifiedItem), [
                'quantity' => 10.0,
                'received_grade' => 'A',
                'warehouse_id' => $this->warehouseCentral->id,
            ])
            ->assertOk();

        // 1. Visit with status=pending (default)
        $respPending = $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.receiver.credit-notes.index', ['status' => 'pending']));

        $respPending->assertOk();
        // Assert stat cards are links with href
        $respPending->assertSee('href="'.route('warehouse.receiver.credit-notes.index', ['status' => 'pending']).'"', false);
        $respPending->assertSee('href="'.route('warehouse.receiver.credit-notes.index', ['status' => 'verified']).'"', false);
        $respPending->assertSee('href="'.route('warehouse.receiver.credit-notes.index', ['status' => 'partially_received']).'"', false);
        $respPending->assertSee('href="'.route('warehouse.receiver.credit-notes.index', ['status' => 'disputed']).'"', false);

        // Pending item is visible, verified item is NOT shown in table
        $respPending->assertSee($cnPending->credit_note_number);
        $respPending->assertDontSee($cnVerified->credit_note_number);

        // 2. Click / visit the Verified stat card link (status=verified)
        $respVerified = $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.receiver.credit-notes.index', ['status' => 'verified']));

        $respVerified->assertOk();
        // Verified item is now visible below, pending item is filtered out
        $respVerified->assertSee($cnVerified->credit_note_number);
        $respVerified->assertDontSee($cnPending->credit_note_number);
    }
}
