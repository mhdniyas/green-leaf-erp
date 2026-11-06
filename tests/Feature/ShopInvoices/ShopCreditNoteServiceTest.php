<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopCreditNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShopCreditNoteService $service;

    private User $admin;

    private Shop $shop;

    private ShopInvoice $invoice;

    private ShopInvoiceItem $tomatoItem;

    private ShopInvoiceItem $onionItem;

    private Product $tomato;

    private Product $onion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ShopCreditNoteService::class);
        $this->admin = User::factory()->create();
        $this->shop = Shop::factory()->create(['code' => 'CITY01', 'name' => 'City Fresh']);

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Local',
            'sku' => 'TOM-LOC-01',
            'unit' => 'kg',
        ]);

        $this->onion = Product::factory()->create([
            'name' => 'Onion Red',
            'sku' => 'ONI-RED-01',
            'unit' => 'kg',
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20261101-001',
            'business_date' => '2026-11-01',
        ]);

        $orderItem1 = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 50,
            'approved_qty' => 50,
            'loaded_qty' => 50,
            'delivered_qty' => 50,
            'unit' => 'kg',
            'unit_cost' => 30,
        ]);

        $orderItem2 = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->onion->id,
            'product_grade' => 'B',
            'requested_qty' => 30,
            'approved_qty' => 30,
            'loaded_qty' => 30,
            'delivered_qty' => 30,
            'unit' => 'kg',
            'unit_cost' => 20,
        ]);

        $this->invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20261101-CITY01',
            'business_date' => '2026-11-01',
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => 3200.00,
            'shortage_total' => 0.00,
            'discount_total' => 0.00,
            'final_total' => 3200.00,
            'paid_amount' => 0.00,
            'balance_amount' => 3200.00,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now()->subDays(4),
        ]);

        $this->tomatoItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $this->invoice->id,
            'shop_order_item_id' => $orderItem1->id,
            'product_id' => $this->tomato->id,
            'product_name' => 'Tomato Local',
            'product_grade' => 'A',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 50.00,
            'price_quantity' => 50.0000,
            'delivered_qty' => 50.00,
            'delivered_price_quantity' => 50.0000,
            'unit_price' => 40.00,
            'line_subtotal' => 2000.00,
            'final_line_total' => 2000.00,
        ]);

        $this->onionItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $this->invoice->id,
            'shop_order_item_id' => $orderItem2->id,
            'product_id' => $this->onion->id,
            'product_name' => 'Onion Red',
            'product_grade' => 'B',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 30.00,
            'price_quantity' => 30.0000,
            'delivered_qty' => 30.00,
            'delivered_price_quantity' => 30.0000,
            'unit_price' => 40.00,
            'line_subtotal' => 1200.00,
            'final_line_total' => 1200.00,
        ]);
    }

    public function test_creates_single_credit_note_draft_successfully(): void
    {
        $draft = $this->service->createDraft(
            $this->invoice,
            [
                'reason_type' => ShopCreditNoteReason::Shortage,
                'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                'notes' => 'Shortage of 5kg tomato reported by shop manager',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'adjusted_quantity' => 5.0,
                        'adjusted_unit_price' => 40.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $this->assertInstanceOf(ShopCreditNote::class, $draft);
        $this->assertSame(ShopCreditNoteStatus::Draft, $draft->status);
        $this->assertSame('SCN-20261101-CITY01-01', $draft->credit_note_number);
        $this->assertSame($this->shop->id, $draft->shop_id);
        $this->assertSame($this->invoice->id, $draft->shop_invoice_id);
        $this->assertSame(ShopCreditNoteReason::Shortage, $draft->reason_type);
        $this->assertSame(ShopCreditNoteDirection::Credit, $draft->direction);
        $this->assertSame(ShopCreditNoteInventoryAction::ReturnToStock, $draft->inventory_action);
        $this->assertEquals(200.00, (float) $draft->total_credit_amount);
        $this->assertEquals(200.00, (float) $draft->subtotal);

        $this->assertCount(1, $draft->items);
        $item = $draft->items->first();
        $this->assertSame($this->tomatoItem->id, $item->shop_invoice_item_id);
        $this->assertSame($this->tomato->id, $item->product_id);
        $this->assertSame('A', $item->product_grade);
        $this->assertEquals(50.0000, (float) $item->original_quantity);
        $this->assertEquals(40.00, (float) $item->original_unit_price);
        $this->assertEquals(2000.00, (float) $item->original_line_total);
        $this->assertEquals(5.0000, (float) $item->adjusted_quantity);
        $this->assertEquals(40.00, (float) $item->adjusted_unit_price);
        $this->assertEquals(200.00, (float) $item->line_credit_amount);
        $this->assertSame(ShopCreditNoteDirection::Credit, $item->direction);
    }

    public function test_creates_multiple_drafts_with_sequential_numbering_for_same_invoice(): void
    {
        $draft1 = $this->service->createDraft(
            $this->invoice,
            [
                'reason_type' => ShopCreditNoteReason::Shortage,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'adjusted_quantity' => 2.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $draft2 = $this->service->createDraft(
            $this->invoice,
            [
                'reason_type' => ShopCreditNoteReason::PriceCorrection,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->onionItem->id,
                        'adjusted_quantity' => 10.0,
                        'adjusted_unit_price' => 5.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $this->assertSame('SCN-20261101-CITY01-01', $draft1->credit_note_number);
        $this->assertSame('SCN-20261101-CITY01-02', $draft2->credit_note_number);
        $this->assertCount(2, ShopCreditNote::where('shop_invoice_id', $this->invoice->id)->get());
    }

    public function test_can_create_credit_note_draft_against_old_finalized_historical_invoice(): void
    {
        // Invoice date is 4 days in the past and marked finalized
        $this->assertTrue($this->invoice->isFinalized());

        $draft = $this->service->createDraft(
            $this->invoice,
            [
                'reason_type' => ShopCreditNoteReason::DamageWastage,
                'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->onionItem->id,
                        'adjusted_quantity' => 4.0,
                        'adjusted_unit_price' => 40.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $this->assertNotNull($draft->id);
        $this->assertSame(ShopCreditNoteStatus::Draft, $draft->status);
        $this->assertEquals(160.00, (float) $draft->total_credit_amount);
    }

    public function test_calculates_credit_from_revised_quantity_without_full_line_double_adjustment(): void
    {
        // Tomato was 50 kg @ 40 = 2000. Revised to 45 kg.
        // Difference is 5 kg @ 40 = 200 (Credit)
        $draft = $this->service->createDraft(
            $this->invoice,
            [
                'reason_type' => ShopCreditNoteReason::Shortage,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'revised_quantity' => 45.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $item = $draft->items->first();
        $this->assertEquals(5.0000, (float) $item->adjusted_quantity);
        $this->assertEquals(40.00, (float) $item->adjusted_unit_price);
        $this->assertEquals(200.00, (float) $item->line_credit_amount);
        $this->assertSame(ShopCreditNoteDirection::Credit, $item->direction);
        $this->assertEquals(200.00, (float) $draft->total_credit_amount);
    }

    public function test_calculates_credit_from_revised_unit_price(): void
    {
        // Tomato was 50 kg @ 40 = 2000. Revised rate is 35 (reduction of 5/kg).
        // Difference is 50 * 5 = 250 (Credit)
        $draft = $this->service->createDraft(
            $this->invoice,
            [
                'reason_type' => ShopCreditNoteReason::PriceCorrection,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'revised_unit_price' => 35.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $item = $draft->items->first();
        $this->assertEquals(250.00, (float) $item->line_credit_amount);
        $this->assertSame(ShopCreditNoteDirection::Credit, $item->direction);
        $this->assertEquals(250.00, (float) $draft->total_credit_amount);
    }

    public function test_calculates_debit_adjustment_when_revision_increases_payable(): void
    {
        // Tomato was 50 kg @ 40 = 2000. Extra 5 kg delivered (revised to 55 kg @ 40 = 2200).
        // Difference is +200 (Debit - increases shop debt)
        $draft = $this->service->createDraft(
            $this->invoice,
            [
                'reason_type' => ShopCreditNoteReason::ExcessDelivered,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'revised_quantity' => 55.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $this->assertSame(ShopCreditNoteDirection::Debit, $draft->direction);
        $item = $draft->items->first();
        $this->assertSame(ShopCreditNoteDirection::Debit, $item->direction);
        $this->assertEquals(5.0000, (float) $item->adjusted_quantity);
        $this->assertEquals(200.00, (float) $item->line_credit_amount);
        $this->assertEquals(200.00, (float) $draft->total_credit_amount);
    }

    public function test_handles_mixed_credit_and_debit_lines_in_single_draft(): void
    {
        // Tomato: 50 -> 40 (-10 @ 40 = -400 Credit)
        // Onion: 30 -> 35 (+5 @ 40 = +200 Debit)
        // Net: 400 credit - 200 debit = 200 net credit
        $draft = $this->service->createDraft(
            $this->invoice,
            [
                'reason_type' => ShopCreditNoteReason::Other,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'revised_quantity' => 40.0,
                    ],
                    [
                        'shop_invoice_item_id' => $this->onionItem->id,
                        'revised_quantity' => 35.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $this->assertSame(ShopCreditNoteDirection::Credit, $draft->direction);
        $this->assertEquals(600.00, (float) $draft->subtotal); // Gross sum
        $this->assertEquals(200.00, (float) $draft->total_credit_amount); // Net effect

        $items = $draft->items->keyBy('shop_invoice_item_id');
        $this->assertSame(ShopCreditNoteDirection::Credit, $items->get($this->tomatoItem->id)->direction);
        $this->assertEquals(400.00, (float) $items->get($this->tomatoItem->id)->line_credit_amount);

        $this->assertSame(ShopCreditNoteDirection::Debit, $items->get($this->onionItem->id)->direction);
        $this->assertEquals(200.00, (float) $items->get($this->onionItem->id)->line_credit_amount);
    }

    public function test_rejects_item_from_another_invoice(): void
    {
        $otherInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
        ]);
        $otherItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $otherInvoice->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invoice item #{$otherItem->id} does not belong to invoice");

        $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $otherItem->id,
                        'adjusted_quantity' => 2.0,
                    ],
                ],
            ],
            $this->admin->id
        );
    }

    public function test_rejects_mismatched_product_grade(): void
    {
        // Onion item is Grade B
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Product grade 'A' does not match invoice item grade 'B'");

        $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->onionItem->id,
                        'product_grade' => 'A', // Mismatch!
                        'adjusted_quantity' => 2.0,
                    ],
                ],
            ],
            $this->admin->id
        );
    }

    public function test_rejects_incompatible_unit(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Unit 'piece' is incompatible with invoice item unit 'kg'");

        $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'unit' => 'piece', // Incompatible with kg
                        'adjusted_quantity' => 2.0,
                    ],
                ],
            ],
            $this->admin->id
        );
    }

    public function test_rejects_empty_items_or_zero_adjustment(): void
    {
        try {
            $this->service->createDraft($this->invoice, ['items' => []], $this->admin->id);
            $this->fail('Empty items accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        try {
            $this->service->createDraft($this->invoice, [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'revised_quantity' => 50.0, // Same as original
                        'revised_unit_price' => 40.0, // Same as original
                    ],
                ],
            ], $this->admin->id);
            $this->fail('Zero adjustment accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0', $e->errors());
        }
    }

    public function test_original_invoice_inventory_and_cashbook_remain_completely_unchanged(): void
    {
        $originalInvoiceSnapshot = $this->invoice->fresh()->toArray();
        $originalTomatoSnapshot = $this->tomatoItem->fresh()->toArray();
        $originalOnionSnapshot = $this->onionItem->fresh()->toArray();

        $initialStockMovementsCount = StockMovement::query()->count();
        $initialLedgerTransactionsCount = ShopLedgerTransaction::query()->count();

        // Create a draft with multiple lines
        $draft = $this->service->createDraft(
            $this->invoice,
            [
                'reason_type' => ShopCreditNoteReason::Shortage,
                'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'adjusted_quantity' => 10.0,
                        'adjusted_unit_price' => 40.0,
                    ],
                    [
                        'shop_invoice_item_id' => $this->onionItem->id,
                        'adjusted_quantity' => 5.0,
                        'adjusted_unit_price' => 40.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $this->assertNotNull($draft->id);

        // 1. Check Invoice record
        $freshInvoice = $this->invoice->fresh();
        $this->assertSame($originalInvoiceSnapshot['subtotal'], $freshInvoice->subtotal);
        $this->assertSame($originalInvoiceSnapshot['final_total'], $freshInvoice->final_total);
        $this->assertSame($originalInvoiceSnapshot['balance_amount'], $freshInvoice->balance_amount);
        $this->assertSame($originalInvoiceSnapshot['shortage_total'], $freshInvoice->shortage_total);
        $this->assertSame($originalInvoiceSnapshot['status'], $freshInvoice->status);
        $this->assertEquals($this->invoice->finalized_at, $freshInvoice->finalized_at);

        // 2. Check Invoice Items
        $freshTomato = $this->tomatoItem->fresh();
        $freshOnion = $this->onionItem->fresh();
        $this->assertSame($originalTomatoSnapshot['delivered_qty'], $freshTomato->delivered_qty);
        $this->assertSame($originalTomatoSnapshot['final_line_total'], $freshTomato->final_line_total);
        $this->assertSame($originalOnionSnapshot['delivered_qty'], $freshOnion->delivered_qty);
        $this->assertSame($originalOnionSnapshot['final_line_total'], $freshOnion->final_line_total);

        // 3. Check Stock movements & Ledger transactions
        $this->assertSame($initialStockMovementsCount, StockMovement::query()->count());
        $this->assertSame($initialLedgerTransactionsCount, ShopLedgerTransaction::query()->count());
    }

    public function test_prevents_duplicate_identical_revision_on_same_item(): void
    {
        // First draft credits 5kg (50 -> 45)
        $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'revised_quantity' => 45.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        // Attempting to submit revised_quantity = 45 again must be rejected as already revised
        try {
            $this->service->createDraft(
                $this->invoice,
                [
                    'items' => [
                        [
                            'shop_invoice_item_id' => $this->tomatoItem->id,
                            'revised_quantity' => 45.0,
                        ],
                    ],
                ],
                $this->admin->id
            );
            $this->fail('Duplicate revision accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0', $e->errors());
            $this->assertStringContainsString('already revised to 45', $e->errors()['items.0'][0]);
        }
    }

    public function test_allows_legitimate_subsequent_revision_calculating_incremental_delta(): void
    {
        // First draft credits 5kg (50 -> 45 @ 40 = 200 credit)
        $draft1 = $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'revised_quantity' => 45.0,
                    ],
                ],
            ],
            $this->admin->id
        );
        $this->assertEquals(200.00, (float) $draft1->total_credit_amount);

        // Second draft reduces further from 45 to 40 (delta 5kg @ 40 = 200 credit)
        $draft2 = $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'revised_quantity' => 40.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $item2 = $draft2->items->first();
        $this->assertEquals(5.0000, (float) $item2->adjusted_quantity);
        $this->assertEquals(200.00, (float) $item2->line_credit_amount);
        $this->assertEquals(200.00, (float) $draft2->total_credit_amount);
    }

    public function test_rejects_cumulative_credit_quantity_exceeding_original(): void
    {
        // Tomato original is 50kg.
        // First draft credits 35kg.
        $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'adjusted_quantity' => 35.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        // Second draft attempts to credit 20kg (total 55kg > 50kg).
        try {
            $this->service->createDraft(
                $this->invoice,
                [
                    'items' => [
                        [
                            'shop_invoice_item_id' => $this->tomatoItem->id,
                            'adjusted_quantity' => 20.0,
                        ],
                    ],
                ],
                $this->admin->id
            );
            $this->fail('Excessive cumulative credit quantity accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.adjusted_quantity', $e->errors());
            $this->assertStringContainsString('exceeds remaining creditable quantity (15)', $e->errors()['items.0.adjusted_quantity'][0]);
        }
    }

    public function test_rejects_cumulative_credit_amount_exceeding_original_line_total(): void
    {
        // Tomato original total is 2000.00.
        // First draft credits 1500.00.
        $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'line_credit_amount' => 1500.00,
                        'adjusted_quantity' => 20.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        // Second draft attempts to credit 600.00 (total 2100.00 > 2000.00).
        try {
            $this->service->createDraft(
                $this->invoice,
                [
                    'items' => [
                        [
                            'shop_invoice_item_id' => $this->tomatoItem->id,
                            'line_credit_amount' => 600.00,
                            'adjusted_quantity' => 10.0,
                        ],
                    ],
                ],
                $this->admin->id
            );
            $this->fail('Excessive cumulative credit amount accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.line_credit_amount', $e->errors());
            $this->assertStringContainsString('exceeds remaining creditable amount (₹500.00)', $e->errors()['items.0.line_credit_amount'][0]);
        }
    }

    public function test_voided_credit_notes_do_not_count_towards_cumulative_limits(): void
    {
        // First draft credits 40kg of tomato.
        $draft1 = $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'adjusted_quantity' => 40.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        // Void draft 1
        $draft1->update([
            'status' => ShopCreditNoteStatus::Voided,
            'voided_by' => $this->admin->id,
            'voided_at' => now(),
            'void_reason' => 'Customer cancelled claim',
        ]);

        // A new draft can now credit 50kg because the voided draft does not count against limits
        $draft2 = $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'adjusted_quantity' => 50.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $this->assertEquals(50.0000, (float) $draft2->items->first()->adjusted_quantity);
        $this->assertEquals(2000.00, (float) $draft2->total_credit_amount);
    }

    public function test_validates_conflicting_explicit_header_direction(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Document direction "credit" conflicts with debit-only line adjustments.');

        $this->service->createDraft(
            $this->invoice,
            [
                'direction' => ShopCreditNoteDirection::Credit, // Conflict!
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'direction' => ShopCreditNoteDirection::Debit,
                        'adjusted_quantity' => 5.0,
                        'adjusted_unit_price' => 40.0,
                    ],
                ],
            ],
            $this->admin->id
        );
    }

    public function test_mixed_direction_totals_and_signed_net_payable_adjustment(): void
    {
        // Credit: 10kg tomato @ 40 = 400.00 credit (reduces debt)
        // Debit: 5kg onion @ 40 = 200.00 debit (increases debt)
        $draft = $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'direction' => ShopCreditNoteDirection::Credit,
                        'adjusted_quantity' => 10.0,
                        'adjusted_unit_price' => 40.0,
                    ],
                    [
                        'shop_invoice_item_id' => $this->onionItem->id,
                        'direction' => ShopCreditNoteDirection::Debit,
                        'adjusted_quantity' => 5.0,
                        'adjusted_unit_price' => 40.0,
                    ],
                ],
            ],
            $this->admin->id
        );

        $this->assertSame(ShopCreditNoteDirection::Credit, $draft->direction);
        $this->assertEquals(600.00, (float) $draft->subtotal); // Gross
        $this->assertEquals(200.00, (float) $draft->total_credit_amount); // Net
        $this->assertEquals(400.00, $draft->creditTotal());
        $this->assertEquals(200.00, $draft->debitTotal());
        // Signed payable adjustment: Credit reduces debt, so netPayableAdjustment is -200.00
        $this->assertEquals(-200.00, $draft->netPayableAdjustment());
        $this->assertFalse($draft->isNetZero());

        // Test recalculateTotals
        $draft->recalculateTotals();
        $this->assertEquals(200.00, (float) $draft->fresh()->total_credit_amount);
    }

    public function test_deletion_protection_blocks_finalized_invoice_deletion(): void
    {
        $this->assertTrue($this->invoice->isFinalized());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot delete invoice {$this->invoice->invoice_number}: Finalized invoices and invoices with associated credit notes cannot be deleted.");

        $this->invoice->delete();
    }

    public function test_deletion_protection_blocks_invoice_deletion_when_credit_notes_exist(): void
    {
        $unfinalizedInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => 'generated',
            'finalized_at' => null,
            'finalized_by' => null,
        ]);

        ShopCreditNote::factory()->forInvoice($unfinalizedInvoice)->create();

        $this->assertFalse($unfinalizedInvoice->isFinalized());
        $this->assertTrue($unfinalizedInvoice->creditNotes()->exists());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot delete invoice {$unfinalizedInvoice->invoice_number}: Finalized invoices and invoices with associated credit notes cannot be deleted.");

        $unfinalizedInvoice->delete();
    }

    public function test_deletion_protection_blocks_approved_credit_note_deletion(): void
    {
        $approvedNote = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->approved()
            ->create();

        $this->assertTrue($approvedNote->isApproved());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot delete approved credit note {$approvedNote->credit_note_number}. Approved credit notes must be voided, not deleted.");

        $approvedNote->delete();
    }

    public function test_deletion_protection_allows_draft_credit_note_deletion(): void
    {
        $draftNote = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->create();

        $this->assertTrue($draftNote->isDraft());

        $draftNoteId = $draftNote->id;
        $draftNote->delete();

        $this->assertDatabaseMissing('shop_credit_notes', ['id' => $draftNoteId]);
    }

    public function test_intra_request_multiple_items_for_same_invoice_item_enforce_cumulative_limit(): void
    {
        // Tomato original is 50kg.
        // Single draft request with 2 lines for tomato: 30kg + 25kg = 55kg (exceeds 50kg)
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds remaining creditable quantity (20)');

        $this->service->createDraft(
            $this->invoice,
            [
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'adjusted_quantity' => 30.0,
                    ],
                    [
                        'shop_invoice_item_id' => $this->tomatoItem->id,
                        'adjusted_quantity' => 25.0, // Exceeds remaining 20kg!
                    ],
                ],
            ],
            $this->admin->id
        );
    }
}
