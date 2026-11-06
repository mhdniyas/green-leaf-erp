<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
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
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopFinalBillService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinalInvoiceProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Shop $shop;

    private Warehouse $warehouse;

    private Product $tomato;

    private ShopPriceGroup $priceGroup;

    private StockBatch $batch;

    private ShopInvoiceService $invoiceService;

    private ShopCreditNoteService $creditNoteService;

    private ShopFinalBillService $finalBillService;

    private InvoiceCashbookProjectionService $cashbookService;

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

        $this->purchaser = User::factory()->create([
            'email' => 'purchaser@example.com',
        ]);
        $this->purchaser->assignRole('purchase');

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Main Sorting Warehouse',
            'code' => 'WH_MAIN',
            'is_active' => true,
        ]);

        $this->priceGroup = ShopPriceGroup::query()->create([
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
            'shop_price_group_id' => $this->priceGroup->id,
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Premium',
            'sku' => 'TOM-PREM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
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

        $this->batch = StockBatch::query()->create([
            'reference' => 'BATCH-TOM-01',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'total_kg' => 500.0,
            'sorted_kg' => 500.0,
            'initial_quantity' => 500.0,
            'cost_per_kg' => 30.0,
            'cost_per_unit' => 30.0,
        ]);

        $this->invoiceService = app(ShopInvoiceService::class);
        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->finalBillService = app(ShopFinalBillService::class);
        $this->cashbookService = app(InvoiceCashbookProjectionService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generated_invoice_is_protected_from_reprice_invoice_and_skipped_by_reprice_all(): void
    {
        [$order, $orderItem, $invoice, $invoiceItem] = $this->createDeliveredOrderWithInvoice();

        $invoice->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        // Update daily price to ₹60/kg (was ₹40/kg)
        DailyPriceApproval::query()
            ->where('product_id', $this->tomato->id)
            ->where('business_date', '2026-08-25')
            ->update([
                'price_a' => 60.0,
            ]);

        // 1. Direct reprice must be rejected with ValidationException
        try {
            $this->invoiceService->repriceInvoice($invoice->fresh(['items', 'order']), $this->admin->id, 'Reprice attempt');
            $this->fail('Repricing a generated/dispatched invoice should have thrown ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('invoice', $e->errors());
        }

        // 2. Batch repricing must skip this invoice
        $summary = $this->invoiceService->repriceAllForBusinessDate('2026-08-25', $this->admin->id);
        $this->assertSame(0, $summary['repriced']);

        // Verify original invoice values remain intact
        $freshInvoice = $invoice->fresh('items');
        $this->assertEquals(2000.0, (float) $freshInvoice->final_total);
        $this->assertEquals(40.0, (float) $freshInvoice->items->first()->unit_price);
        $this->assertEquals(2000.0, (float) $freshInvoice->items->first()->final_line_total);
    }

    public function test_generated_invoice_is_protected_from_synchronize_order_invoice(): void
    {
        [$order, $orderItem, $invoice, $invoiceItem] = $this->createDeliveredOrderWithInvoice();

        $invoice->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        // Alter order item quantities on the order
        $orderItem->update([
            'requested_qty' => 100.0,
            'approved_qty' => 100.0,
            'loaded_qty' => 100.0,
        ]);

        $syncedInvoice = $this->invoiceService->synchronizeOrderInvoice($order->fresh('items'), $this->admin->id);

        // Synced invoice items and totals must remain identical to original snapshot
        $this->assertEquals(2000.0, (float) $syncedInvoice->final_total);
        $this->assertEquals(50.0, (float) $syncedInvoice->items->first()->approved_qty);
        $this->assertEquals(50.0, (float) $syncedInvoice->items->first()->delivered_qty);
        $this->assertEquals(2000.0, (float) $syncedInvoice->items->first()->final_line_total);
    }

    public function test_reopen_finalized_invoice_is_rejected(): void
    {
        [$order, $orderItem, $invoice, $invoiceItem] = $this->createDeliveredOrderWithInvoice();

        $invoice->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        $invoice->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        // Direct service call must throw ValidationException
        try {
            $this->invoiceService->reopenFinalizedInvoice($invoice, $this->admin->id);
            $this->fail('Reopening a finalized invoice should have thrown ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('invoice', $e->errors());
            $this->assertStringContainsString('Reopening finalized invoices is disabled', $e->errors()['invoice'][0]);
        }

        // Web route call must redirect with error and keep invoice finalized
        $response = $this->actingAs($this->admin)->post(
            route('purchasing.shop-invoices.reopen-for-edit', $invoice)
        );

        $response->assertRedirect(route('purchasing.shop-invoices.show', $invoice));
        $response->assertSessionHasErrors(['invoice']);
        $this->assertTrue($invoice->fresh()->isFinalized());
    }

    public function test_direct_admin_discount_on_protected_invoice_is_rejected(): void
    {
        [$order, $orderItem, $invoice, $invoiceItem] = $this->createDeliveredOrderWithInvoice();

        $invoice->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        try {
            $this->invoiceService->applyAdminDiscount($invoice, [
                'discount_total' => 150.0,
                'discount_note' => 'Unauthorized late discount',
            ], $this->admin->id);
            $this->fail('Admin discount on a protected invoice should have thrown ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('discount_total', $e->errors());
        }

        $this->assertEquals(0.0, (float) $invoice->fresh()->discount_total);
        $this->assertEquals(2000.0, (float) $invoice->fresh()->final_total);
    }

    public function test_delivery_review_generates_credit_notes_without_changing_original_invoice(): void
    {
        [$order, $orderItem, $invoice, $invoiceItem] = $this->createDeliveredOrderWithInvoice();

        $invoice->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        $order->update([
            'delivery_review_status' => 'pending',
        ]);

        $resolveAction = app(ResolveDeliveryReviewAction::class);
        $resolveAction->approve(
            $order,
            [$orderItem->id => 45.0],
            [],
            [$orderItem->id => 'return_to_warehouse'],
            [$orderItem->id => 'loadout_mistake'],
            [],
            $this->admin->id,
            'Approved review with shortage'
        );

        // Original invoice item must remain immutable
        $freshInvoice = $invoice->fresh(['items', 'creditNotes']);
        $this->assertEquals(2000.0, (float) $freshInvoice->final_total);
        $freshItem = $freshInvoice->items->first();
        $this->assertEquals(50.0, (float) $freshItem->delivered_qty);
        $this->assertEquals(50.0, (float) $freshItem->approved_qty);
        $this->assertEquals(0.0, (float) $freshItem->shortage_qty);
        $this->assertEquals(2000.0, (float) $freshItem->final_line_total);

        // A separate credit note must exist for 5 KG * ₹40 = ₹200
        $creditNotes = ShopCreditNote::query()->where('shop_invoice_id', $invoice->id)->get();
        $this->assertCount(1, $creditNotes);
        $creditNote = $creditNotes->first();
        $this->assertSame(ShopCreditNoteStatus::Approved, $creditNote->status);
        $this->assertEquals(200.0, (float) $creditNote->total_credit_amount);

        // Canonical Final Bill must show original ₹2000 - credit ₹200 = ₹1800
        $finalBill = $this->finalBillService->calculate($freshInvoice);
        $this->assertEquals(2000.0, $finalBill->originalInvoiceTotal);
        $this->assertEquals(200.0, $finalBill->finalizedCreditTotal);
        $this->assertEquals(1800.0, $finalBill->remainingBalance);
    }

    public function test_purchaser_cannot_arbitrarily_create_or_approve_credit_note_directly(): void
    {
        [$order, $orderItem, $invoice, $invoiceItem] = $this->createDeliveredOrderWithInvoice();

        $invoice->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        // 1. Purchaser cannot create a draft credit note directly outside delivery review
        try {
            $this->creditNoteService->createDraft($invoice, [
                'direction' => ShopCreditNoteDirection::Credit,
                'reason_type' => ShopCreditNoteReason::PriceCorrection,
                'inventory_action' => ShopCreditNoteInventoryAction::None,
                'items' => [
                    [
                        'shop_invoice_item_id' => $invoiceItem->id,
                        'product_id' => $this->tomato->id,
                        'unit_price' => 40.0,
                        'adjusted_quantity' => 5.0,
                        'line_credit_amount' => 200.0,
                    ],
                ],
            ], $this->purchaser->id);
            $this->fail('Purchaser should not be allowed to create credit notes directly.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('creator', $e->errors());
        }

        // 2. Draft created by admin cannot be approved directly by purchaser
        $adminDraft = $this->creditNoteService->createDraft($invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'items' => [
                [
                    'shop_invoice_item_id' => $invoiceItem->id,
                    'product_id' => $this->tomato->id,
                    'unit_price' => 40.0,
                    'adjusted_quantity' => 5.0,
                    'line_credit_amount' => 200.0,
                ],
            ],
        ], $this->admin->id);

        try {
            $this->creditNoteService->approve($adminDraft, $this->purchaser);
            $this->fail('Purchaser should not be allowed to approve credit notes directly.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('approved_by', $e->errors());
        }

        $this->assertTrue($adminDraft->fresh()->isDraft());
    }

    public function test_admin_can_create_and_manually_approve_credit_note(): void
    {
        [$order, $orderItem, $invoice, $invoiceItem] = $this->createDeliveredOrderWithInvoice();

        $invoice->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        $draft = $this->creditNoteService->createDraft($invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'items' => [
                [
                    'shop_invoice_item_id' => $invoiceItem->id,
                    'product_id' => $this->tomato->id,
                    'unit_price' => 40.0,
                    'adjusted_quantity' => 5.0,
                    'line_credit_amount' => 200.0,
                ],
            ],
        ], $this->admin->id);

        $approved = $this->creditNoteService->approve($draft, $this->admin, 'Approved manual correction');

        $this->assertSame(ShopCreditNoteStatus::Approved, $approved->status);
        $this->assertEquals(200.0, (float) $approved->total_credit_amount);

        // Final Bill reflects adjustment
        $finalBill = $this->finalBillService->calculate($invoice->fresh());
        $this->assertEquals(1800.0, $finalBill->remainingBalance);
    }

    public function test_payment_and_cashbook_balances_respect_canonical_final_bill(): void
    {
        [$order, $orderItem, $invoice, $invoiceItem] = $this->createDeliveredOrderWithInvoice();

        $invoice->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        // Create and approve ₹200 credit note
        $draft = $this->creditNoteService->createDraft($invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'items' => [
                [
                    'shop_invoice_item_id' => $invoiceItem->id,
                    'product_id' => $this->tomato->id,
                    'unit_price' => 40.0,
                    'adjusted_quantity' => 5.0,
                    'line_credit_amount' => 200.0,
                ],
            ],
        ], $this->admin->id);
        $this->creditNoteService->approve($draft, $this->admin);

        // Record payment of ₹1800 (the full final bill)
        $this->invoiceService->recordAdminPaymentReceived($invoice->fresh(), [
            'paid_amount' => 1800.0,
            'payment_method' => 'cash',
            'collected_at' => '2026-08-25 15:00:00',
            'payment_note' => 'Settled in full based on credit note adjustment',
        ], $this->admin->id);

        $freshInvoice = $invoice->fresh(['items', 'creditNotes']);
        $this->assertEquals(1800.0, (float) $freshInvoice->paid_amount);
        $this->assertEquals(0.0, (float) $freshInvoice->balance_amount);
        $this->assertSame('paid', $freshInvoice->payment_status);

        // Final Bill verifies full settlement
        $finalBill = $this->finalBillService->calculate($freshInvoice);
        $this->assertEquals(1800.0, $finalBill->finalNetPayable);
        $this->assertEquals(0.0, $finalBill->remainingBalance);
        $this->assertSame('paid', $finalBill->effectivePaymentStatus);
    }

    public function test_pre_generation_warehouse_loadout_save_remains_functional(): void
    {
        // Order in active warehouse loading state
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-LOADOUT-01',
            'business_date' => '2026-08-25',
            'delivery_status' => 'loading',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 50.0,
            'approved_qty' => 50.0,
            'loaded_qty' => 20.0,
            'actual_weight' => 20.0,
            'product_grade' => 'A',
            'sorting_status' => 'loaded',
            'unit_price' => 40.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_conversion_to_base' => 1.0,
        ]);

        // First loadout save
        $invoice = $this->invoiceService->synchronizeOrderInvoice($order->fresh('items'), $this->admin->id);
        $this->assertEquals(800.0, (float) $invoice->final_total); // 20 * 40

        // Worker loads additional 15 KG (total 35 KG) and saves again
        $orderItem->update([
            'loaded_qty' => 35.0,
            'actual_weight' => 35.0,
        ]);

        $reSyncedInvoice = $this->invoiceService->synchronizeOrderInvoice($order->fresh('items'), $this->admin->id);
        $this->assertEquals(1400.0, (float) $reSyncedInvoice->final_total); // 35 * 40

        $repricedInvoice = $this->invoiceService->repriceInvoice($reSyncedInvoice, $this->admin->id, 'Warehouse loadout update');
        $this->assertEquals(1400.0, (float) $repricedInvoice->final_total);
    }

    /**
     * @return array{0: ShopOrder, 1: ShopOrderItem, 2: ShopInvoice, 3: ShopInvoiceItem}
     */
    private function createDeliveredOrderWithInvoice(): array
    {
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20260825-01',
            'business_date' => '2026-08-25',
            'delivery_status' => 'delivered',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 50.0,
            'approved_qty' => 50.0,
            'loaded_qty' => 50.0,
            'actual_weight' => 50.0,
            'requested_unit_quantity' => 50.0,
            'product_grade' => 'A',
            'sorting_status' => 'loaded',
            'unit_price' => 40.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_conversion_to_base' => 1.0,
        ]);

        StockMovement::create([
            'batch_id' => $this->batch->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::Out,
            'quantity' => 50.0,
            'balance_after' => 450.0,
            'unit_price' => 40.0,
            'cost_per_unit' => 30.0,
            'created_by' => $this->admin->id,
            'notes' => "Order: {$order->order_number}; Item: {$orderItem->id}",
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260825-001',
            'business_date' => '2026-08-25',
            'status' => 'generated',
            'delivery_status' => 'delivered',
            'payment_status' => 'unpaid',
            'subtotal' => 2000.0,
            'discount_total' => 0.0,
            'shortage_total' => 0.0,
            'excess_total' => 0.0,
            'final_total' => 2000.0,
            'paid_amount' => 0.0,
            'balance_amount' => 2000.0,
            'generated_by' => $this->admin->id,
        ]);

        $invoiceItem = ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $this->tomato->id,
            'product_name' => $this->tomato->name,
            'product_grade' => 'A',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'price_quantity' => 50.0,
            'approved_qty' => 50.0,
            'delivered_qty' => 50.0,
            'shortage_qty' => 0.0,
            'excess_qty' => 0.0,
            'unit_price' => 40.0,
            'line_subtotal' => 2000.0,
            'shortage_amount' => 0.0,
            'excess_amount' => 0.0,
            'final_line_total' => 2000.0,
        ]);

        return [$order, $orderItem, $invoice, $invoiceItem];
    }
}
