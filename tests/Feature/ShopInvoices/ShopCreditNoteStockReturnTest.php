<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\Inventory\StockLedgerService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopCreditNoteStockReturnTest extends TestCase
{
    use RefreshDatabase;

    private ShopCreditNoteService $creditNoteService;

    private StockLedgerService $stockLedgerService;

    private InvoiceCashbookProjectionService $projectionService;

    private User $admin;

    private Shop $shop;

    private Warehouse $warehouse;

    private Product $tomato;

    private StockBatch $batch1;

    private StockBatch $batch2;

    private ShopOrder $order;

    private ShopOrderItem $orderItem;

    private ShopInvoice $invoice;

    private ShopInvoiceItem $invoiceItem;

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

        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->stockLedgerService = app(StockLedgerService::class);
        $this->projectionService = app(InvoiceCashbookProjectionService::class);

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Central Hub Warehouse',
            'code' => 'WH_HUB',
            'is_active' => true,
        ]);

        $this->shop = Shop::query()->create([
            'name' => 'Metro Fresh Store',
            'code' => 'MFS_01',
            'warehouse_tag' => 'MFS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Local',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        // Create sorted batches with positive stock
        $this->batch1 = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'reference' => 'BAT-TOM-001',
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'warehouse_receive_pending' => false,
            'total_kg' => 100.0,
            'cost_per_kg' => 40.0,
        ]);

        StockMovement::create([
            'batch_id' => $this->batch1->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 100.0,
            'cost_per_unit' => 40.0,
            'created_by' => $this->admin->id,
            'notes' => 'Initial batch 1 stock',
        ]);

        $this->batch2 = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'reference' => 'BAT-TOM-002',
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'warehouse_receive_pending' => false,
            'total_kg' => 50.0,
            'cost_per_kg' => 42.0,
        ]);

        StockMovement::create([
            'batch_id' => $this->batch2->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 50.0,
            'cost_per_unit' => 42.0,
            'created_by' => $this->admin->id,
            'notes' => 'Initial batch 2 stock',
        ]);

        // Order setup
        $this->order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20261105-001',
            'business_date' => '2026-11-05',
        ]);

        $this->orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $this->order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 60,
            'approved_qty' => 60,
            'loaded_qty' => 60,
            'delivered_qty' => 60,
            'unit' => 'kg',
            'unit_price' => 60.0,
            'line_total' => 3600.0,
        ]);

        // Loadout stock deduction: 40 kg from batch1, 20 kg from batch2 (Total 60 kg)
        StockMovement::create([
            'batch_id' => $this->batch1->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $this->orderItem->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::Out,
            'quantity' => 40.0,
            'cost_per_unit' => 40.0,
            'created_by' => $this->admin->id,
            'notes' => "Warehouse loadout completion — Order: {$this->order->order_number}; Warehouse: {$this->warehouse->code}",
        ]);

        StockMovement::create([
            'batch_id' => $this->batch2->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $this->orderItem->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::Out,
            'quantity' => 20.0,
            'cost_per_unit' => 42.0,
            'created_by' => $this->admin->id,
            'notes' => "Warehouse loadout completion — Order: {$this->order->order_number}; Warehouse: {$this->warehouse->code}",
        ]);

        // Finalized invoice
        $this->invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $this->order->id,
            'invoice_number' => 'SINV-20261105-MFS01',
            'business_date' => '2026-11-05',
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => 3600.0,
            'discount_total' => 0.0,
            'final_total' => 3600.0,
            'paid_amount' => 0.0,
            'balance_amount' => 3600.0,
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        $this->invoiceItem = ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $this->invoice->id,
            'shop_order_item_id' => $this->orderItem->id,
            'product_id' => $this->tomato->id,
            'product_name' => 'Tomato Local',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 60,
            'approved_qty' => 60,
            'unit_price' => 60.0,
            'line_subtotal' => 3600.0,
            'final_line_total' => 3600.0,
        ]);

        $this->projectionService->syncInvoice($this->invoice, $this->admin->id);
    }

    public function test_1_approved_physical_return_restores_stock_to_warehouse(): void
    {
        // Initial available stock before return:
        // Batch 1: 100 in - 40 out = 60
        // Batch 2: 50 in - 20 out = 30
        // Total available = 90
        $initialStock = $this->stockLedgerService->availableSortedStockForProduct($this->tomato->id, $this->warehouse->id, ProductGrade::GradeA);
        $this->assertEquals(90.0, $initialStock);

        // Shop returns 15 kg of tomato physically to warehouse
        $draft = $this->creditNoteService->createDraft($this->invoice, [
            'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->invoiceItem->id,
                    'adjusted_quantity' => 15.0,
                    'adjusted_unit_price' => 60.0,
                    'line_credit_amount' => 900.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                    'direction' => ShopCreditNoteDirection::Credit,
                ],
            ],
        ], $this->admin->id);

        $approved = $this->creditNoteService->approve($draft, $this->admin, 'Physical goods returned to bay 2');

        $this->assertTrue($approved->isApproved());

        // Stock movements generated
        $movements = $approved->stockMovements()->get();
        $this->assertNotEmpty($movements);
        $this->assertEquals(15.0, (float) $movements->sum('quantity'));

        // All movements must be SaleReversal
        foreach ($movements as $m) {
            $this->assertEquals(StockMovementType::SaleReversal, $m->type);
            $this->assertEquals($this->warehouse->id, $m->warehouse_id);
            $this->assertEquals($this->tomato->id, $m->product_id);
            $this->assertEquals(ProductGrade::GradeA, $m->grade);
            $this->assertEquals($this->admin->id, $m->created_by);
            $this->assertStringContainsString("Credit Note Return — SCN: {$approved->credit_note_number}", (string) $m->notes);
        }

        // Available warehouse stock increased from 90 to 105
        $newStock = $this->stockLedgerService->availableSortedStockForProduct($this->tomato->id, $this->warehouse->id, ProductGrade::GradeA);
        $this->assertEquals(105.0, $newStock);

        // Net deducted quantity for this order item reduced from 60 to 45
        $netDeducted = $this->stockLedgerService->netDeductedQuantityForOrderItem($this->orderItem->id);
        $this->assertEquals(45.0, $netDeducted);

        // Financial posting: net payable is 3600 - 900 = 2700
        $this->assertEquals(2700.0, $this->invoice->fresh()->effectiveFinalTotal());
    }

    public function test_2_physical_return_restores_to_correct_warehouse_batches_and_grades_in_lifo_order(): void
    {
        // 20 kg was taken from batch2 (latest), 40 kg from batch1
        // If 25 kg is returned:
        // LIFO should restore 20 kg to batch2, and 5 kg to batch1
        $draft = $this->creditNoteService->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->invoiceItem->id,
                    'adjusted_quantity' => 25.0,
                    'line_credit_amount' => 1500.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin->id);

        $approved = $this->creditNoteService->approve($draft, $this->admin);

        $movements = $approved->stockMovements()->get();
        $this->assertCount(2, $movements);

        $b2Movement = $movements->firstWhere('batch_id', $this->batch2->id);
        $b1Movement = $movements->firstWhere('batch_id', $this->batch1->id);

        $this->assertNotNull($b2Movement);
        $this->assertNotNull($b1Movement);

        $this->assertEquals(20.0, (float) $b2Movement->quantity);
        $this->assertEquals(42.0, (float) $b2Movement->cost_per_unit);
        $this->assertEquals(ProductGrade::GradeA, $b2Movement->grade);

        $this->assertEquals(5.0, (float) $b1Movement->quantity);
        $this->assertEquals(40.0, (float) $b1Movement->cost_per_unit);
        $this->assertEquals(ProductGrade::GradeA, $b1Movement->grade);
    }

    public function test_3_repeated_approval_does_not_duplicate_stock_movements(): void
    {
        $draft = $this->creditNoteService->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->invoiceItem->id,
                    'adjusted_quantity' => 10.0,
                    'line_credit_amount' => 600.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin->id);

        $approved = $this->creditNoteService->approve($draft, $this->admin);
        $this->assertEquals(10.0, $approved->totalRestoredStockQuantity());

        // Calling restoreStockForCreditNoteItem again directly must be idempotent
        $creditItem = $approved->items->first();
        $this->stockLedgerService->restoreStockForCreditNoteItem($creditItem, $approved, $this->admin->id);

        // Exactly 10.0 kg restored, not 20.0 kg
        $this->assertEquals(10.0, $approved->totalRestoredStockQuantity());
    }

    public function test_4_previously_processed_delivery_shortage_cannot_restore_stock_twice(): void
    {
        // 60 kg was loaded. Suppose 10 kg was recorded as a delivery shortage by delivery review.
        StockMovement::create([
            'batch_id' => $this->batch2->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $this->orderItem->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::SaleReversal,
            'quantity' => 10.0,
            'cost_per_unit' => 42.0,
            'created_by' => $this->admin->id,
            'notes' => "Delivery shortage added back to inventory - Order: {$this->order->order_number}; Item: {$this->orderItem->id}",
        ]);

        // Net delivered physically retained by shop is 60 - 10 = 50 kg
        // Attempting to return 55 kg must fail validation
        $draft = $this->creditNoteService->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->invoiceItem->id,
                    'adjusted_quantity' => 55.0,
                    'line_credit_amount' => 3300.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin->id);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds remaining physically delivered quantity (50 kg)');

        $this->creditNoteService->approve($draft, $this->admin);
    }

    public function test_5_financial_only_credit_note_does_not_affect_inventory(): void
    {
        $initialStock = $this->stockLedgerService->availableSortedStockForProduct($this->tomato->id, $this->warehouse->id, ProductGrade::GradeA);

        $draft = $this->creditNoteService->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->invoiceItem->id,
                    'adjusted_quantity' => 10.0,
                    'line_credit_amount' => 600.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                ],
            ],
        ], $this->admin->id);

        $approved = $this->creditNoteService->approve($draft, $this->admin);

        // Zero stock movements generated
        $this->assertSame(0, $approved->stockMovements()->count());
        $this->assertSame(0.0, $approved->totalRestoredStockQuantity());

        // Warehouse stock unchanged
        $afterStock = $this->stockLedgerService->availableSortedStockForProduct($this->tomato->id, $this->warehouse->id, ProductGrade::GradeA);
        $this->assertEquals($initialStock, $afterStock);
    }

    public function test_6_failed_stock_restoration_rolls_back_approval_and_financial_posting(): void
    {
        // Delete original loadout deduction movements so physical receipt cannot be verified
        StockMovement::query()->where('shop_order_item_id', $this->orderItem->id)->delete();

        $draft = $this->creditNoteService->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->invoiceItem->id,
                    'adjusted_quantity' => 10.0,
                    'line_credit_amount' => 600.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin->id);

        try {
            $this->creditNoteService->approve($draft, $this->admin);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('No loadout stock deduction was recorded', $e->getMessage());
        }

        // Verify full atomicity: draft is still draft, no ledger tx, no stock movements
        $freshDraft = $draft->fresh();
        $this->assertTrue($freshDraft->isDraft());
        $this->assertNull($freshDraft->approved_at);

        $ledgerCount = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $draft->id)
            ->count();
        $this->assertSame(0, $ledgerCount);

        $this->assertSame(0, $freshDraft->stockMovements()->count());
    }

    public function test_7_original_invoice_and_original_loadout_movements_remain_unmodified(): void
    {
        $originalOutMovements = StockMovement::query()
            ->where('shop_order_item_id', $this->orderItem->id)
            ->where('type', StockMovementType::Out->value)
            ->get();

        $draft = $this->creditNoteService->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->invoiceItem->id,
                    'adjusted_quantity' => 10.0,
                    'line_credit_amount' => 600.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin->id);

        $this->creditNoteService->approve($draft, $this->admin);

        // Original finalized invoice fields
        $freshInvoice = $this->invoice->fresh();
        $this->assertEquals(3600.0, (float) $freshInvoice->final_total);
        $this->assertEquals(3600.0, (float) $freshInvoice->subtotal);

        // Original invoice item
        $freshItem = $this->invoiceItem->fresh();
        $this->assertEquals(60.0, (float) $freshItem->delivered_qty);
        $this->assertEquals(3600.0, (float) $freshItem->final_line_total);

        // Original Out movements count and quantities identical
        $freshOutMovements = StockMovement::query()
            ->where('shop_order_item_id', $this->orderItem->id)
            ->where('type', StockMovementType::Out->value)
            ->get();

        $this->assertCount($originalOutMovements->count(), $freshOutMovements);
        $this->assertEquals($originalOutMovements->sum('quantity'), $freshOutMovements->sum('quantity'));
    }

    public function test_8_multiple_credit_returns_accumulate_up_to_loadout_ceiling(): void
    {
        // 60 kg delivered
        // Return 1: 25 kg
        $d1 = $this->creditNoteService->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'items' => [['shop_invoice_item_id' => $this->invoiceItem->id, 'adjusted_quantity' => 25.0, 'line_credit_amount' => 1500.0, 'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock]],
        ], $this->admin->id);
        $this->creditNoteService->approve($d1, $this->admin);

        // Return 2: 20 kg
        $d2 = $this->creditNoteService->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'items' => [['shop_invoice_item_id' => $this->invoiceItem->id, 'adjusted_quantity' => 20.0, 'line_credit_amount' => 1200.0, 'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock]],
        ], $this->admin->id);
        $this->creditNoteService->approve($d2, $this->admin);

        // Total returned so far via credit notes: 45 kg out of 60 kg.
        // Net deducted quantity remaining on order item is 15 kg.
        $this->assertEquals(15.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($this->orderItem->id));

        // Now suppose 10 kg was separately reversed as a delivery shortage on the order item
        StockMovement::create([
            'batch_id' => $this->batch2->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $this->orderItem->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::SaleReversal,
            'quantity' => 10.0,
            'cost_per_unit' => 42.0,
            'created_by' => $this->admin->id,
            'notes' => "Delivery shortage added back to inventory - Order: {$this->order->order_number}; Item: {$this->orderItem->id}",
        ]);

        // Now only 5 kg was physically retained by the shop (60 total - 45 CN - 10 shortage = 5).
        // Credit note draft requests 10 kg (Invoice item has 60 - 45 = 15 creditable, so draft creation succeeds):
        $d3 = $this->creditNoteService->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'items' => [['shop_invoice_item_id' => $this->invoiceItem->id, 'adjusted_quantity' => 10.0, 'line_credit_amount' => 600.0, 'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock]],
        ], $this->admin->id);

        // But approval must reject because physical remaining loadout is only 5 kg!
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds remaining physically delivered quantity (5 kg)');

        $this->creditNoteService->approve($d3, $this->admin);
    }
}
