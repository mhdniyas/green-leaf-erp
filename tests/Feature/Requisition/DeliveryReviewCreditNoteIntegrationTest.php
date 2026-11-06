<?php

declare(strict_types=1);

namespace Tests\Feature\Requisition;

use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeliveryReviewCreditNoteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private Warehouse $warehouse;

    private Product $tomato;

    private StockBatch $batchA;

    private ResolveDeliveryReviewAction $action;

    private ShopFinalBillService $finalBillService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        Carbon::setTestNow('2026-08-25 12:00:00');

        config(['admin.user_access.main_admin_email' => 'admin@example.com']);

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Main Sorting Warehouse',
            'code' => 'WH_MAIN',
            'is_active' => true,
        ]);

        $priceGroup = ShopPriceGroup::query()->create([
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->shop = Shop::query()->create([
            'name' => 'Downtown Fresh Mart',
            'code' => 'DFM_01',
            'warehouse_tag' => 'DFM',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_price_group_id' => $priceGroup->id,
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Premium',
            'sku' => 'TOM-PREM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $this->tomato->id,
            'business_date' => '2026-08-25',
            'grade' => 'A',
            'purchase_price' => 30.0,
            'price_unit' => 'kg',
            'price_a' => 40.0,
            'price_b' => 40.0,
            'price_c' => 40.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->batchA = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'reference' => 'BAT-TOM-A',
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'warehouse_receive_pending' => false,
            'total_kg' => 500.0,
            'cost_per_kg' => 30.0,
        ]);

        StockMovement::create([
            'batch_id' => $this->batchA->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 500.0,
            'balance_after' => 500.0,
            'unit_price' => 40.0,
            'cost_per_unit' => 30.0,
            'created_by' => $this->admin->id,
        ]);

        $this->action = app(ResolveDeliveryReviewAction::class);
        $this->finalBillService = app(ShopFinalBillService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper to set up an order + invoice in 'awaiting_review' / 'pending_approval' state.
     */
    private function createOrderAndInvoice(array $itemsData): array
    {
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'SO-DR-'.uniqid(),
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'business_date' => '2026-08-25',
            'shop_checked_by' => $this->admin->id,
            'shop_checked_at' => now(),
        ]);

        $totalSubtotal = 0.0;
        $orderItems = [];
        $invoiceItems = [];

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-DR-'.uniqid(),
            'business_date' => '2026-08-25',
            'status' => 'delivery_review',
            'delivery_status' => 'awaiting_review',
            'payment_status' => 'pending',
            'paid_amount' => 0.0,
        ]);

        foreach ($itemsData as $data) {
            $qty = (float) $data['qty'];
            $price = (float) $data['price'];
            $grade = $data['grade'] ?? 'A';
            $unit = $data['unit'] ?? 'kg';
            $product = $data['product'] ?? $this->tomato;
            $lineSubtotal = round($qty * $price, 2);
            $totalSubtotal += $lineSubtotal;

            $orderItem = ShopOrderItem::query()->create([
                'shop_order_id' => $order->id,
                'product_id' => $product->id,
                'requested_qty' => $qty,
                'approved_qty' => $qty,
                'loaded_qty' => $qty,
                'product_grade' => $grade,
                'sorting_status' => 'loaded',
                'unit_price' => $price,
                'unit_cost' => 30.0,
                'unit' => $unit,
                'requested_unit' => $unit,
                'requested_unit_conversion_to_base' => 1.0,
                'shop_reported_received_qty' => $data['received_qty'] ?? $qty,
            ]);

            StockMovement::create([
                'batch_id' => $this->batchA->id,
                'shop_order_item_id' => $orderItem->id,
                'product_id' => $product->id,
                'warehouse_id' => $this->warehouse->id,
                'grade' => ProductGrade::tryFrom($grade) ?? ProductGrade::GradeA,
                'type' => StockMovementType::Out,
                'quantity' => $qty,
                'balance_after' => 500.0 - $qty,
                'unit_price' => $price,
                'cost_per_unit' => 30.0,
                'created_by' => $this->admin->id,
                'notes' => "Order: {$order->order_number}; Item: {$orderItem->id}",
            ]);

            $invoiceItem = ShopInvoiceItem::factory()->create([
                'shop_invoice_id' => $invoice->id,
                'shop_order_item_id' => $orderItem->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_grade' => $grade,
                'unit' => $unit,
                'price_unit' => $unit,
                'unit_price' => $price,
                'approved_qty' => $qty,
                'price_quantity' => $qty,
                'delivered_qty' => $qty,
                'delivered_price_quantity' => $qty,
                'line_subtotal' => $lineSubtotal,
                'final_line_total' => $lineSubtotal,
            ]);

            $orderItems[] = $orderItem;
            $invoiceItems[] = $invoiceItem;
        }

        $invoice->update([
            'subtotal' => $totalSubtotal,
            'final_total' => $totalSubtotal,
            'balance_amount' => $totalSubtotal,
        ]);

        return [$order->fresh(['items']), $invoice->fresh(['items']), $orderItems, $invoiceItems];
    }

    public function test_01_delivery_without_discrepancies_finalizes_without_credit_or_debit_notes(): void
    {
        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            ['qty' => 50.0, 'price' => 40.0, 'received_qty' => 50.0],
        ]);

        $this->action->approve(
            $order,
            [$orderItems[0]->id => 50.0],
            [],
            [],
            [],
            [],
            $this->admin->id,
            'All quantities match loaded amount'
        );

        $freshInvoice = $invoice->fresh(['items', 'creditNotes']);
        $this->assertSame(2000.0, (float) $freshInvoice->subtotal);
        $this->assertSame(2000.0, (float) $freshInvoice->final_total);
        $this->assertSame(50.0, (float) $freshInvoice->items->first()->delivered_qty);
        $this->assertSame(2000.0, (float) $freshInvoice->items->first()->final_line_total);

        // No credit notes created
        $this->assertSame(0, $freshInvoice->creditNotes()->count());

        // Final bill equals original invoice
        $finalBill = $this->finalBillService->calculate($freshInvoice);
        $this->assertSame(2000.0, $finalBill->originalInvoiceTotal);
        $this->assertSame(0.0, $finalBill->finalizedCreditTotal);
        $this->assertSame(0.0, $finalBill->finalizedDebitTotal);
        $this->assertSame(2000.0, $finalBill->finalNetPayable);
        $this->assertSame(2000.0, $finalBill->remainingBalance);
    }

    public function test_02_shortage_creates_and_approves_credit_note_preserving_original_invoice(): void
    {
        // Tomato A: 50 KG loaded at ₹40 = ₹2,000. Shop received 45 KG (shortage 5 KG = ₹200).
        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            ['qty' => 50.0, 'price' => 40.0, 'received_qty' => 45.0],
        ]);

        $this->action->approve(
            $order,
            [$orderItems[0]->id => 45.0],
            [],
            [$orderItems[0]->id => 'return_to_warehouse'],
            [$orderItems[0]->id => 'loadout_mistake'],
            [],
            $this->admin->id,
            'Shortage 5kg returned'
        );

        // 1. Original invoice remains unchanged
        $freshInvoice = $invoice->fresh(['items', 'creditNotes']);
        $this->assertSame(2000.0, (float) $freshInvoice->subtotal);
        $this->assertSame(2000.0, (float) $freshInvoice->final_total);
        $this->assertSame(50.0, (float) $freshInvoice->items->first()->approved_qty);
        $this->assertSame(2000.0, (float) $freshInvoice->items->first()->final_line_total);

        // 2. Separate approved credit note exists
        $creditNotes = $freshInvoice->creditNotes;
        $this->assertCount(1, $creditNotes);

        $cn = $creditNotes->first();
        $this->assertSame(ShopCreditNoteStatus::Approved, $cn->status);
        $this->assertSame(ShopCreditNoteDirection::Credit, $cn->direction);
        $this->assertSame(200.0, (float) $cn->total_credit_amount);
        $this->assertSame(1, $cn->items()->count());

        $cnItem = $cn->items->first();
        $this->assertSame(5.0, (float) $cnItem->adjusted_quantity);
        $this->assertSame(40.0, (float) $cnItem->adjusted_unit_price);
        $this->assertSame(200.0, (float) $cnItem->line_credit_amount);

        // 3. Final Bill shows canonical net payable: ₹2,000 - ₹200 = ₹1,800
        $finalBill = $this->finalBillService->calculate($freshInvoice);
        $this->assertSame(2000.0, $finalBill->originalInvoiceTotal);
        $this->assertSame(200.0, $finalBill->finalizedCreditTotal);
        $this->assertSame(0.0, $finalBill->finalizedDebitTotal);
        $this->assertSame(1800.0, $finalBill->finalNetPayable);
        $this->assertSame(1800.0, $finalBill->remainingBalance);
        $this->assertSame(1800.0, (float) $freshInvoice->balance_amount);
    }

    public function test_03_excess_delivery_creates_and_approves_debit_note_preserving_original_invoice(): void
    {
        // Tomato A: 50 KG loaded at ₹40 = ₹2,000. Shop received 55 KG (excess 5 KG = ₹200).
        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            ['qty' => 50.0, 'price' => 40.0, 'received_qty' => 55.0],
        ]);

        $this->action->approve(
            $order,
            [$orderItems[0]->id => 55.0],
            [],
            [$orderItems[0]->id => 'deduct_extra'],
            [],
            [],
            $this->admin->id,
            'Excess 5kg approved'
        );

        $freshInvoice = $invoice->fresh(['items', 'creditNotes']);
        $this->assertSame(2000.0, (float) $freshInvoice->subtotal);
        $this->assertSame(2000.0, (float) $freshInvoice->final_total);

        // Exactly 1 approved Debit Note
        $debitNotes = $freshInvoice->creditNotes;
        $this->assertCount(1, $debitNotes);

        $dn = $debitNotes->first();
        $this->assertSame(ShopCreditNoteStatus::Approved, $dn->status);
        $this->assertSame(ShopCreditNoteDirection::Debit, $dn->direction);
        $this->assertSame(200.0, (float) $dn->total_credit_amount);

        // Final Bill: ₹2,000 + ₹200 = ₹2,200
        $finalBill = $this->finalBillService->calculate($freshInvoice);
        $this->assertSame(2000.0, $finalBill->originalInvoiceTotal);
        $this->assertSame(0.0, $finalBill->finalizedCreditTotal);
        $this->assertSame(200.0, $finalBill->finalizedDebitTotal);
        $this->assertSame(2200.0, $finalBill->finalNetPayable);
        $this->assertSame(2200.0, $finalBill->remainingBalance);
        $this->assertSame(2200.0, (float) $freshInvoice->balance_amount);
    }

    public function test_04_damaged_quantity_creates_wastage_entry_once_and_categorizes_credit_note(): void
    {
        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            ['qty' => 30.0, 'price' => 40.0, 'received_qty' => 25.0],
        ]);

        $this->action->approve(
            $order,
            [$orderItems[0]->id => 25.0],
            [],
            [$orderItems[0]->id => 'wastage'],
            [$orderItems[0]->id => 'transit_damage'],
            [$orderItems[0]->id => 'Damaged during transit'],
            $this->admin->id,
            'Approved 5kg wastage'
        );

        // Wastage entry created exactly once
        $wastageCount = WastageEntry::query()
            ->where('product_id', $this->tomato->id)
            ->where('quantity', 5.0)
            ->count();
        $this->assertSame(1, $wastageCount);

        // Credit note created with DamageWastage reason
        $freshInvoice = $invoice->fresh(['creditNotes']);
        $cn = $freshInvoice->creditNotes->first();
        $this->assertSame(ShopCreditNoteReason::DamageWastage, $cn->reason_type);
        $this->assertSame(200.0, (float) $cn->total_credit_amount);

        // Original invoice unchanged
        $this->assertSame(1200.0, (float) $freshInvoice->final_total);
    }

    public function test_05_returned_stock_restores_inventory_exactly_once(): void
    {
        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            ['qty' => 30.0, 'price' => 40.0, 'received_qty' => 20.0],
        ]);

        $initialWarehouseStock = StockMovement::query()
            ->where('product_id', $this->tomato->id)
            ->where('type', StockMovementType::SaleReversal->value)
            ->count();
        $this->assertSame(0, $initialWarehouseStock);

        $this->action->approve(
            $order,
            [$orderItems[0]->id => 20.0],
            [],
            [$orderItems[0]->id => 'return_to_warehouse'],
            [$orderItems[0]->id => 'delivery_mistake'],
            [],
            $this->admin->id,
            'Returned 10kg'
        );

        // Exactly one SaleReversal stock movement of 10kg
        $reversals = StockMovement::query()
            ->where('product_id', $this->tomato->id)
            ->where('type', StockMovementType::SaleReversal->value)
            ->get();
        $this->assertCount(1, $reversals);
        $this->assertSame(10.0, (float) $reversals->first()->quantity);

        // Credit note created with 10kg credit
        $freshInvoice = $invoice->fresh(['creditNotes']);
        $cn = $freshInvoice->creditNotes->first();
        $this->assertSame(400.0, (float) $cn->total_credit_amount);
    }

    public function test_06_multiple_products_and_grades_shortage_and_excess_create_separate_notes(): void
    {
        $cucumber = Product::factory()->create([
            'name' => 'Cucumber Fresh',
            'sku' => 'CUC-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $cucumber->id,
            'business_date' => '2026-08-25',
            'grade' => 'A',
            'purchase_price' => 15.0,
            'price_unit' => 'kg',
            'price_a' => 20.0,
            'price_b' => 20.0,
            'price_c' => 20.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        StockMovement::create([
            'batch_id' => $this->batchA->id,
            'product_id' => $cucumber->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 100.0,
            'balance_after' => 100.0,
            'unit_price' => 20.0,
            'cost_per_unit' => 15.0,
            'created_by' => $this->admin->id,
        ]);

        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            ['product' => $this->tomato, 'qty' => 50.0, 'price' => 40.0, 'received_qty' => 45.0], // shortage 5kg at 40 = 200 credit
            ['product' => $cucumber, 'qty' => 20.0, 'price' => 20.0, 'received_qty' => 25.0],     // excess 5kg at 20 = 100 debit
        ]);

        $this->action->approve(
            $order,
            [
                $orderItems[0]->id => 45.0,
                $orderItems[1]->id => 25.0,
            ],
            [],
            [
                $orderItems[0]->id => 'return_to_warehouse',
                $orderItems[1]->id => 'deduct_extra',
            ],
            [],
            [],
            $this->admin->id,
            'Mixed shortage and excess'
        );

        $freshInvoice = $invoice->fresh(['creditNotes', 'items']);
        // Original: (50 * 40) + (20 * 20) = 2000 + 400 = 2400
        $this->assertSame(2400.0, (float) $freshInvoice->final_total);

        // One Credit Note of 200, One Debit Note of 100
        $creditNotes = $freshInvoice->creditNotes;
        $this->assertCount(2, $creditNotes);

        $credit = $creditNotes->firstWhere('direction', ShopCreditNoteDirection::Credit);
        $debit = $creditNotes->firstWhere('direction', ShopCreditNoteDirection::Debit);

        $this->assertNotNull($credit);
        $this->assertNotNull($debit);
        $this->assertSame(200.0, (float) $credit->total_credit_amount);
        $this->assertSame(100.0, (float) $debit->total_credit_amount);

        // Final Bill: 2400 - 200 + 100 = 2300
        $finalBill = $this->finalBillService->calculate($freshInvoice);
        $this->assertSame(2400.0, $finalBill->originalInvoiceTotal);
        $this->assertSame(200.0, $finalBill->finalizedCreditTotal);
        $this->assertSame(100.0, $finalBill->finalizedDebitTotal);
        $this->assertSame(2300.0, $finalBill->finalNetPayable);
        $this->assertSame(2300.0, $finalBill->remainingBalance);
    }

    public function test_07_repeated_approval_is_idempotent_no_duplicate_credit_notes_or_movements(): void
    {
        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            ['qty' => 50.0, 'price' => 40.0, 'received_qty' => 45.0],
        ]);

        // First approval
        $this->action->approve(
            $order,
            [$orderItems[0]->id => 45.0],
            [],
            [$orderItems[0]->id => 'return_to_warehouse'],
            [],
            [],
            $this->admin->id,
            'First approval'
        );

        $creditNoteCountBefore = ShopCreditNote::where('shop_invoice_id', $invoice->id)->count();
        $stockMovementCountBefore = StockMovement::where('type', StockMovementType::SaleReversal->value)->count();

        $this->assertSame(1, $creditNoteCountBefore);
        $this->assertSame(1, $stockMovementCountBefore);

        // Second approval call on already approved order
        $this->action->approve(
            $order->fresh(),
            [$orderItems[0]->id => 45.0],
            [],
            [$orderItems[0]->id => 'return_to_warehouse'],
            [],
            [],
            $this->admin->id,
            'Second approval attempt'
        );

        $creditNoteCountAfter = ShopCreditNote::where('shop_invoice_id', $invoice->id)->count();
        $stockMovementCountAfter = StockMovement::where('type', StockMovementType::SaleReversal->value)->count();

        $this->assertSame(1, $creditNoteCountAfter);
        $this->assertSame(1, $stockMovementCountAfter);
    }

    public function test_08_approval_reversal_is_blocked_when_finalized_credit_notes_exist(): void
    {
        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            ['qty' => 50.0, 'price' => 40.0, 'received_qty' => 45.0],
        ]);

        $this->action->approve(
            $order,
            [$orderItems[0]->id => 45.0],
            [],
            [$orderItems[0]->id => 'return_to_warehouse'],
            [],
            [],
            $this->admin->id,
            'Approved delivery'
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Delivery approval cannot be reverted because credit/debit adjustments have already been finalized.');

        $this->action->revertApprovalForAdminEdit(
            $order->fresh(),
            $this->admin->id,
            'Attempting unsafe approval reversal'
        );
    }

    public function test_09_cashbook_ledger_records_original_invoice_and_credit_note_reconciling_to_final_bill(): void
    {
        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            ['qty' => 50.0, 'price' => 40.0, 'received_qty' => 45.0],
        ]);

        $this->action->approve(
            $order,
            [$orderItems[0]->id => 45.0],
            [],
            [$orderItems[0]->id => 'return_to_warehouse'],
            [],
            [],
            $this->admin->id,
            'Approved delivery'
        );

        // Check cashbook transactions:
        // 1. Purchase bill for original invoice: 2000.00
        $invoiceTx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopInvoice::class)
            ->where('reference_id', $invoice->id)
            ->first();

        $this->assertNotNull($invoiceTx);
        $this->assertSame(2000.0, (float) $invoiceTx->amount);

        // 2. Credit note transaction: 200.00 (income / credit reduction)
        $creditNote = ShopCreditNote::where('shop_invoice_id', $invoice->id)->first();
        $cnTx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->first();

        $this->assertNotNull($cnTx);
        $this->assertSame(200.0, (float) $cnTx->amount);

        // Net liability in cashbook = 2000 - 200 = 1800
        $finalBill = $this->finalBillService->calculate($invoice->fresh());
        $this->assertSame(1800.0, $finalBill->finalNetPayable);
    }

    public function test_10_quantity_unit_conversion_shortage_records_proportional_credit(): void
    {
        $coconut = Product::factory()->create([
            'name' => 'Coconut',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $coconut->id,
            'business_date' => '2026-08-25',
            'grade' => 'A',
            'purchase_price' => 50.0,
            'price_unit' => 'piece',
            'price_a' => 63.0,
            'price_b' => 63.0,
            'price_c' => 63.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        StockMovement::create([
            'batch_id' => $this->batchA->id,
            'product_id' => $coconut->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 100.0,
            'balance_after' => 100.0,
            'unit_price' => 63.0,
            'cost_per_unit' => 50.0,
            'created_by' => $this->admin->id,
        ]);

        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            [
                'product' => $coconut,
                'qty' => 10.0,
                'price' => 63.0,
                'received_qty' => 5.0, // 5 kg shortage
                'unit' => 'kg',
            ],
        ]);

        $orderItems[0]->update([
            'requested_unit_quantity' => 10.0,
            'loaded_qty' => 10.0,
            'actual_weight' => 10.0,
        ]);

        // Set price_unit to piece on invoice item
        $invoiceItems[0]->update([
            'price_unit' => 'piece',
            'price_quantity' => 10.0,
            'line_subtotal' => 630.0,
            'final_line_total' => 630.0,
        ]);
        $invoice->update([
            'subtotal' => 630.0,
            'final_total' => 630.0,
            'balance_amount' => 630.0,
        ]);

        $this->action->approve(
            $order,
            [$orderItems[0]->id => 5.0],
            [],
            [$orderItems[0]->id => 'return_to_warehouse'],
            [],
            [],
            $this->admin->id,
            'Coconut shortage'
        );

        $freshInvoice = $invoice->fresh(['creditNotes']);
        $this->assertSame(630.0, (float) $freshInvoice->final_total);

        $cn = $freshInvoice->creditNotes->first();
        $this->assertNotNull($cn);
        // Shortage of 5 kg proportionally equals 5 pieces at 63 = 315
        $this->assertSame(315.0, (float) $cn->total_credit_amount);

        $finalBill = $this->finalBillService->calculate($freshInvoice);
        $this->assertSame(630.0, $finalBill->originalInvoiceTotal);
        $this->assertSame(315.0, $finalBill->finalizedCreditTotal);
        $this->assertSame(315.0, $finalBill->finalNetPayable);
        $this->assertSame(315.0, $finalBill->remainingBalance);
    }

    public function test_11_transaction_rollback_preserves_clean_state_on_failure(): void
    {
        [$order, $invoice, $orderItems, $invoiceItems] = $this->createOrderAndInvoice([
            ['qty' => 50.0, 'price' => 40.0, 'received_qty' => 45.0],
        ]);

        try {
            // Negative delivered quantity triggers ValidationException
            $this->action->approve(
                $order,
                [$orderItems[0]->id => -5.0],
                [],
                [],
                [],
                [],
                $this->admin->id,
                'Should fail and roll back'
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            // Success
        }

        // Verify zero credit notes and unchanged order status
        $this->assertSame(0, ShopCreditNote::where('shop_invoice_id', $invoice->id)->count());
        $this->assertSame('pending', $order->fresh()->delivery_review_status);
        $this->assertSame(2000.0, (float) $invoice->fresh()->final_total);
    }
}
