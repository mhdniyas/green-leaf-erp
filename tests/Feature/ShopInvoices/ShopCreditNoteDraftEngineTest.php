<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteLineType;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopCreditNoteDraftEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shopA;

    private Shop $shopB;

    private Product $productOnion;

    private Product $productTomato;

    private ShopInvoice $invoiceA1;

    private ShopInvoice $invoiceA2;

    private ShopInvoice $invoiceA3;

    private ShopInvoice $invoiceB1;

    private ShopInvoiceItem $itemOnionA1;

    private ShopInvoiceItem $itemTomatoA2;

    private ShopInvoiceItem $itemOnionA3;

    private ShopInvoiceItem $itemOnionB1;

    private ShopCreditNoteService $creditNoteService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shopA = Shop::factory()->create(['code' => 'SHP-A', 'name' => 'Kochi Central']);
        $this->shopB = Shop::factory()->create(['code' => 'SHP-B', 'name' => 'Calicut Mart']);

        $this->productOnion = Product::factory()->create(['name' => 'Onion Grade A', 'unit' => 'kg']);
        $this->productTomato = Product::factory()->create(['name' => 'Tomato Grade A', 'unit' => 'kg']);

        // Invoice A1 (Shop A) - 100 kg Onion @ 50 = 5000 (2 days ago)
        $this->invoiceA1 = $this->createFinalizedInvoice($this->shopA, 'SINV-A1', today()->subDays(2)->toDateString());
        $this->itemOnionA1 = $this->createInvoiceItem($this->invoiceA1, $this->productOnion, 'A', 100.0, 50.0);

        // Invoice A2 (Shop A) - 80 kg Tomato @ 40 = 3200 (1 day ago)
        $this->invoiceA2 = $this->createFinalizedInvoice($this->shopA, 'SINV-A2', today()->subDays(1)->toDateString());
        $this->itemTomatoA2 = $this->createInvoiceItem($this->invoiceA2, $this->productTomato, 'A', 80.0, 40.0);

        // Invoice A3 (Shop A) - 50 kg Onion @ 60 = 3000 (3 days ago)
        $this->invoiceA3 = $this->createFinalizedInvoice($this->shopA, 'SINV-A3', today()->subDays(3)->toDateString());
        $this->itemOnionA3 = $this->createInvoiceItem($this->invoiceA3, $this->productOnion, 'A', 50.0, 60.0);

        // Invoice B1 (Shop B) - 50 kg Onion @ 55 = 2750 (1 day ago)
        $this->invoiceB1 = $this->createFinalizedInvoice($this->shopB, 'SINV-B1', today()->subDays(1)->toDateString());
        $this->itemOnionB1 = $this->createInvoiceItem($this->invoiceB1, $this->productOnion, 'A', 50.0, 55.0);

        $this->creditNoteService = app(ShopCreditNoteService::class);
    }

    private function createFinalizedInvoice(Shop $shop, string $invoiceNumber, ?string $date = null): ShopInvoice
    {
        $businessDate = $date ?? today()->toDateString();
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
        ]);

        return ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'final_total' => 5000.00,
            'balance_amount' => 5000.00,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);
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
            'unit_cost' => $price * 0.8,
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

    /**
     * Test 1: Creating a draft with one source invoice.
     */
    public function test_01_create_draft_with_one_source_invoice(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'notes' => 'Test single invoice draft',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 25.0,
                    'credit_rate' => 50.0,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin);

        $this->assertInstanceOf(ShopCreditNote::class, $draft);
        $this->assertEquals(ShopCreditNoteAccountingMode::ShopLevel, $draft->accounting_mode);
        $this->assertNull($draft->shop_invoice_id);
        $this->assertEquals(ShopCreditNoteStatus::Draft, $draft->status);
        $this->assertStringStartsWith('DRAFT-SCN-', $draft->credit_note_number);
        $this->assertEquals(1250.00, (float) $draft->total_credit_amount);
        $this->assertEquals(1, $draft->lock_version);
        $this->assertEquals($this->admin->id, $draft->created_by);

        $this->assertCount(1, $draft->items);
        $item = $draft->items->first();
        $this->assertEquals(ShopCreditNoteLineType::ProductAdjustment, $item->line_type);
        $this->assertEquals($this->itemOnionA1->id, $item->shop_invoice_item_id);
        $this->assertEquals($this->invoiceA1->id, $item->source_shop_invoice_id);
        $this->assertEquals(25.0, (float) $item->adjusted_quantity);
        $this->assertEquals(50.0, (float) $item->credit_rate);
        $this->assertEquals(1250.00, (float) $item->line_credit_amount);
    }

    /**
     * Test 2: Creating a draft with multiple source invoices belonging to the same shop.
     */
    public function test_02_create_draft_with_multiple_source_invoices_same_shop(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 30.0,
                    'credit_rate' => 50.0,
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA2->id,
                    'shop_invoice_item_id' => $this->itemTomatoA2->id,
                    'credit_quantity' => 20.0,
                    'credit_rate' => 40.0,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(2300.00, (float) $draft->total_credit_amount);
        $this->assertCount(2, $draft->items);

        $item1 = $draft->items->where('shop_invoice_item_id', $this->itemOnionA1->id)->first();
        $item2 = $draft->items->where('shop_invoice_item_id', $this->itemTomatoA2->id)->first();

        $this->assertEquals($this->invoiceA1->id, $item1->source_shop_invoice_id);
        $this->assertEquals(1500.00, (float) $item1->line_credit_amount);

        $this->assertEquals($this->invoiceA2->id, $item2->source_shop_invoice_id);
        $this->assertEquals(800.00, (float) $item2->line_credit_amount);
    }

    /**
     * Test 3: Rejecting cross-shop invoice lines.
     */
    public function test_03_reject_cross_shop_invoice_lines(): void
    {
        $this->expectException(ValidationException::class);

        $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceB1->id, // Belongs to Shop B!
                    'shop_invoice_item_id' => $this->itemOnionB1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 55.0,
                ],
            ],
        ], $this->admin);
    }

    /**
     * Test 4: Editable credit rate: 40 kg × ₹35 = ₹1,400 with received grade B.
     */
    public function test_04_editable_credit_rate_and_received_grade(): void
    {
        // Onion originally sold as Grade A at ₹50/kg.
        // Office enters: Returned 40 kg, expected returned grade B @ ₹35/kg.
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 40.0,
                    'credit_rate' => 35.0,
                    'received_grade' => 'B',
                    'reason_type' => ShopCreditNoteReason::GradeDowngrade,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(1400.00, (float) $draft->total_credit_amount);

        $item = $draft->items->first();
        $this->assertEquals(40.0, (float) $item->adjusted_quantity);
        $this->assertEquals(35.00, (float) $item->credit_rate);
        $this->assertEquals(1400.00, (float) $item->line_credit_amount);
        $this->assertEquals('A', $item->product_grade);
        $this->assertEquals('B', $item->received_grade);
        $this->assertEquals(ShopCreditNoteReason::GradeDowngrade, $item->reason_type);
    }

    /**
     * Test 5: Different original rates across multiple invoices.
     */
    public function test_05_different_original_rates_across_multiple_invoices(): void
    {
        // Invoice A1: Onion @ 50 (credit 10 kg @ revised rate 45 = 450)
        // Invoice A3: Onion @ 60 (credit 15 kg @ revised rate 52 = 780)
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 45.0,
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA3->id,
                    'shop_invoice_item_id' => $this->itemOnionA3->id,
                    'credit_quantity' => 15.0,
                    'credit_rate' => 52.0,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(1230.00, (float) $draft->total_credit_amount);

        $itemA1 = $draft->items->where('shop_invoice_item_id', $this->itemOnionA1->id)->first();
        $itemA3 = $draft->items->where('shop_invoice_item_id', $this->itemOnionA3->id)->first();

        $this->assertEquals(50.00, (float) $itemA1->original_unit_price);
        $this->assertEquals(45.00, (float) $itemA1->credit_rate);
        $this->assertEquals(450.00, (float) $itemA1->line_credit_amount);

        $this->assertEquals(60.00, (float) $itemA3->original_unit_price);
        $this->assertEquals(52.00, (float) $itemA3->credit_rate);
        $this->assertEquals(780.00, (float) $itemA3->line_credit_amount);
    }

    /**
     * Test 6: Creating a standalone discount-only draft.
     */
    public function test_06_create_standalone_discount_only_draft(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'notes' => 'Commercial Goodwill Discount',
            'discounts' => [
                [
                    'amount' => 300.00,
                    'notes' => 'Prompt settlement discount',
                    'reason_type' => ShopCreditNoteReason::Goodwill,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(300.00, (float) $draft->total_credit_amount);
        $this->assertEquals(300.00, (float) $draft->discount_amount);
        $this->assertEquals('Prompt settlement discount', $draft->discount_reason);
        $this->assertCount(1, $draft->items);

        $item = $draft->items->first();
        $this->assertEquals(ShopCreditNoteLineType::CommercialDiscount, $item->line_type);
        $this->assertNull($item->product_id);
        $this->assertNull($item->shop_invoice_item_id);
        $this->assertEquals(0.0000, (float) $item->adjusted_quantity);
        $this->assertEquals(300.00, (float) $item->line_credit_amount);
        $this->assertEquals(ShopCreditNoteInventoryAction::None, $item->inventory_action);
    }

    /**
     * Test 7: Combining product return and discount.
     */
    public function test_07_combine_product_return_and_commercial_discount(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 40.0,
                    'credit_rate' => 35.0,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                ],
                [
                    'line_type' => 'commercial_discount',
                    'discount_amount' => 300.00,
                    'notes' => 'Commercial discount bonus',
                ],
            ],
        ], $this->admin);

        // 1400 (product) + 300 (discount) = 1700
        $this->assertEquals(1700.00, (float) $draft->total_credit_amount);
        $this->assertEquals(300.00, (float) $draft->discount_amount);
        $this->assertCount(2, $draft->items);

        $productItem = $draft->items->where('line_type', ShopCreditNoteLineType::ProductAdjustment)->first();
        $discountItem = $draft->items->where('line_type', ShopCreditNoteLineType::CommercialDiscount)->first();

        $this->assertNotNull($productItem);
        $this->assertNotNull($discountItem);
        $this->assertEquals(1400.00, (float) $productItem->line_credit_amount);
        $this->assertEquals(300.00, (float) $discountItem->line_credit_amount);
    }

    /**
     * Test 8: Editing a draft without double-counting its own quantities.
     */
    public function test_08_edit_draft_without_double_counting_own_quantities(): void
    {
        // 1. Create draft for 50 kg Onion (50 kg remaining of 100 kg)
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 50.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(1, $draft->lock_version);
        $this->assertEquals(2500.00, (float) $draft->total_credit_amount);

        // 2. Edit draft: Increase quantity to 80 kg
        // If it double-counted its own 50 kg, it would think 50 + 80 = 130 > 100 and fail.
        $updatedDraft = $this->creditNoteService->updateShopLevelDraft($draft, [
            'expected_lock_version' => 1,
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 80.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(2, $updatedDraft->lock_version);
        $this->assertEquals(4000.00, (float) $updatedDraft->total_credit_amount);
        $this->assertCount(1, $updatedDraft->items);
        $this->assertEquals(80.0, (float) $updatedDraft->items->first()->adjusted_quantity);
    }

    /**
     * Test 9: Abandoned drafts not indefinitely blocking eligible quantities.
     */
    public function test_09_abandoned_drafts_not_indefinitely_blocking_eligible_quantities(): void
    {
        // User 1 creates Draft 1 for 70 kg of 100 kg Onion and leaves it abandoned
        $draft1 = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'notes' => 'Draft 1 - Abandoned',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 70.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(ShopCreditNoteStatus::Draft, $draft1->status);

        // User 2 creates Draft 2 for 60 kg of the same invoice item.
        // If Draft 1 blocked quantities, remaining would only be 30 kg, and 60 kg would fail.
        // Because shop-level drafts are provisional, full 100 kg is still available!
        $draft2 = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'notes' => 'Draft 2 - Concurrent/Alternative',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 60.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $this->assertInstanceOf(ShopCreditNote::class, $draft2);
        $this->assertEquals(3000.00, (float) $draft2->total_credit_amount);
    }

    /**
     * Test 10: Concurrent draft edits cannot silently overwrite each other.
     */
    public function test_10_concurrent_draft_edits_prevent_lost_updates(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 20.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(1, $draft->lock_version);

        // User A successfully updates draft from lock_version 1 -> 2
        $this->creditNoteService->updateShopLevelDraft($draft, [
            'expected_lock_version' => 1,
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 25.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        // User B attempts to save changes using old expected_lock_version 1
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This draft has been modified by another user');

        $this->creditNoteService->updateShopLevelDraft($draft, [
            'expected_lock_version' => 1, // Stale version!
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 30.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);
    }

    /**
     * Test 11: Invalid quantities, rates and source lines rejected.
     */
    public function test_11_invalid_quantities_rates_and_lines_rejected(): void
    {
        // 1. Negative quantity
        try {
            $this->creditNoteService->createShopLevelDraft($this->shopA, [
                'items' => [
                    [
                        'source_shop_invoice_id' => $this->invoiceA1->id,
                        'shop_invoice_item_id' => $this->itemOnionA1->id,
                        'credit_quantity' => -5.0,
                        'credit_rate' => 50.0,
                    ],
                ],
            ], $this->admin);
            $this->fail('Expected ValidationException for negative quantity');
        } catch (ValidationException $e) {
            $this->assertTrue(isset($e->errors()['items.0.credit_quantity']));
        }

        // 2. Negative rate
        try {
            $this->creditNoteService->createShopLevelDraft($this->shopA, [
                'items' => [
                    [
                        'source_shop_invoice_id' => $this->invoiceA1->id,
                        'shop_invoice_item_id' => $this->itemOnionA1->id,
                        'credit_quantity' => 10.0,
                        'credit_rate' => -50.0,
                    ],
                ],
            ], $this->admin);
            $this->fail('Expected ValidationException for negative rate');
        } catch (ValidationException $e) {
            $this->assertTrue(isset($e->errors()['items.0.credit_rate']));
        }

        // 3. Exceeds total billed quantity
        try {
            $this->creditNoteService->createShopLevelDraft($this->shopA, [
                'items' => [
                    [
                        'source_shop_invoice_id' => $this->invoiceA1->id,
                        'shop_invoice_item_id' => $this->itemOnionA1->id,
                        'credit_quantity' => 120.0, // Only 100 kg delivered
                        'credit_rate' => 50.0,
                    ],
                ],
            ], $this->admin);
            $this->fail('Expected ValidationException for over-quantity');
        } catch (ValidationException $e) {
            $this->assertTrue(isset($e->errors()['lines.0.credit_quantity']));
        }
    }

    /**
     * Test 12: Draft creation causes zero inventory movements.
     */
    public function test_12_draft_creation_causes_zero_inventory_movements(): void
    {
        $movementsBefore = StockMovement::count();

        $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 30.0,
                    'credit_rate' => 50.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin);

        $movementsAfter = StockMovement::count();
        $this->assertEquals($movementsBefore, $movementsAfter, 'Draft creation must never create inventory movements.');
    }

    /**
     * Test 13: Draft creation causes zero Cashbook changes.
     */
    public function test_13_draft_creation_causes_zero_cashbook_changes(): void
    {
        $transactionsBefore = ShopLedgerTransaction::count();

        $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 30.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $transactionsAfter = ShopLedgerTransaction::count();
        $this->assertEquals($transactionsBefore, $transactionsAfter, 'Draft creation must never create cashbook transactions.');
    }

    /**
     * Test 14: Draft creation causes zero credit applications.
     */
    public function test_14_draft_creation_causes_zero_credit_applications(): void
    {
        $applicationsBefore = ShopCreditApplication::count();

        $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 30.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $applicationsAfter = ShopCreditApplication::count();
        $this->assertEquals($applicationsBefore, $applicationsAfter, 'Draft creation must never create credit applications.');
    }

    /**
     * Test 15: Existing legacy Credit Notes retain current behavior.
     */
    public function test_15_existing_legacy_credit_notes_retain_current_behavior(): void
    {
        $legacyDraft = $this->creditNoteService->createDraft($this->invoiceA1, [
            'items' => [
                [
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'adjusted_quantity' => 10.0,
                    'adjusted_unit_price' => 50.0,
                ],
            ],
        ], $this->admin->id);

        $this->assertEquals(ShopCreditNoteAccountingMode::LegacyInvoiceLinked, $legacyDraft->accounting_mode);
        $this->assertEquals($this->invoiceA1->id, $legacyDraft->shop_invoice_id);
        $this->assertEquals(ShopCreditNoteStatus::Draft, $legacyDraft->status);
        $this->assertEquals(500.00, (float) $legacyDraft->total_credit_amount);

        // Legacy approve works for legacy credit note
        $approved = $this->creditNoteService->approve($legacyDraft, $this->admin);
        $this->assertEquals(ShopCreditNoteStatus::Approved, $approved->status);
    }

    /**
     * Test 16: Premature shop-level finalization cannot occur.
     */
    public function test_16_premature_shop_level_finalization_is_blocked(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 20.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Shop-level credit notes cannot be approved via legacy single-invoice approval');

        // Calling legacy approve on shop-level note must be blocked!
        $this->creditNoteService->approve($draft, $this->admin);
    }
}
