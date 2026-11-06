<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCreditNoteFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopInvoice $invoice;

    private ShopInvoiceItem $invoiceItem;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->shop = Shop::factory()->create(['code' => 'SHP01', 'name' => 'City Fresh Shop']);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-TEST-100',
            'business_date' => '2026-11-01',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Premium Tomato',
            'sku' => 'TOM-001',
            'unit' => 'kg',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 50,
            'approved_qty' => 50,
            'loaded_qty' => 50,
            'delivered_qty' => 50,
            'unit' => 'kg',
            'unit_cost' => 30,
        ]);

        $this->invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20261101-SHP01',
            'business_date' => '2026-11-01',
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => 2000.00,
            'shortage_total' => 0.00,
            'discount_total' => 0.00,
            'final_total' => 2000.00,
            'paid_amount' => 0.00,
            'balance_amount' => 2000.00,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);

        $this->invoiceItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $this->invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $this->product->id,
            'product_name' => 'Premium Tomato',
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
    }

    public function test_credit_note_can_be_created_and_linked_to_original_invoice(): void
    {
        $creditNote = ShopCreditNote::query()->create([
            'credit_note_number' => ShopCreditNote::generateCreditNoteNumber($this->invoice),
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => $this->invoice->id,
            'credit_note_date' => '2026-11-05',
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::Shortage,
            'status' => ShopCreditNoteStatus::Draft,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'subtotal' => 200.00,
            'total_credit_amount' => 200.00,
            'notes' => '5 kg returned due to minor shortage upon unpacking.',
            'created_by' => $this->admin->id,
        ]);

        $item = ShopCreditNoteItem::query()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_item_id' => $this->invoiceItem->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'product_name' => $this->product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'original_quantity' => 50.0000,
            'original_unit_price' => 40.00,
            'original_line_total' => 2000.00,
            'adjusted_quantity' => 5.0000,
            'adjusted_unit_price' => 40.00,
            'line_credit_amount' => 200.00,
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            'notes' => '5kg shortage credited',
        ]);

        $this->assertDatabaseHas('shop_credit_notes', [
            'id' => $creditNote->id,
            'credit_note_number' => 'SCN-20261101-SHP01-01',
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => $this->invoice->id,
            'direction' => 'credit',
            'reason_type' => 'shortage',
            'status' => 'draft',
            'total_credit_amount' => 200.00,
        ]);

        $this->assertDatabaseHas('shop_credit_note_items', [
            'id' => $item->id,
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_item_id' => $this->invoiceItem->id,
            'adjusted_quantity' => 5.0000,
            'line_credit_amount' => 200.00,
        ]);

        // Test relationships
        $this->assertTrue($creditNote->invoice->is($this->invoice));
        $this->assertTrue($creditNote->shop->is($this->shop));
        $this->assertTrue($creditNote->creator->is($this->admin));
        $this->assertCount(1, $creditNote->items);
        $this->assertTrue($creditNote->items->first()->is($item));
        $this->assertTrue($item->invoiceItem->is($this->invoiceItem));
        $this->assertTrue($item->product->is($this->product));
        $this->assertTrue($this->invoice->creditNotes->first()->is($creditNote));
        $this->assertTrue($this->invoiceItem->creditNoteItems->first()->is($item));
        $this->assertTrue($this->shop->creditNotes->first()->is($creditNote));
    }

    public function test_multiple_credit_notes_can_reference_the_same_invoice(): void
    {
        $creditNote1 = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->create([
                'reason_type' => ShopCreditNoteReason::Shortage,
                'total_credit_amount' => 120.00,
            ]);

        $creditNote2 = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->create([
                'reason_type' => ShopCreditNoteReason::PriceCorrection,
                'total_credit_amount' => 80.00,
            ]);

        $creditNote3 = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->create([
                'reason_type' => ShopCreditNoteReason::DamageWastage,
                'total_credit_amount' => 50.00,
            ]);

        $this->assertSame('SCN-20261101-SHP01-01', $creditNote1->credit_note_number);
        $this->assertSame('SCN-20261101-SHP01-02', $creditNote2->credit_note_number);
        $this->assertSame('SCN-20261101-SHP01-03', $creditNote3->credit_note_number);

        $freshInvoice = $this->invoice->fresh(['creditNotes']);
        $this->assertCount(3, $freshInvoice->creditNotes);
        $this->assertEquals(250.00, (float) $freshInvoice->creditNotes->sum('total_credit_amount'));
    }

    public function test_decimal_precision_is_preserved_for_quantities_and_amounts(): void
    {
        $creditNote = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->create([
                'subtotal' => 123.45,
                'total_credit_amount' => 123.45,
            ]);

        $item = ShopCreditNoteItem::factory()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_item_id' => $this->invoiceItem->id,
            'product_id' => $this->product->id,
            'original_quantity' => 12.3456,
            'original_unit_price' => 45.67,
            'original_line_total' => 563.82,
            'adjusted_quantity' => 2.7050,
            'adjusted_unit_price' => 45.67,
            'line_credit_amount' => 123.54,
        ]);

        $freshItem = $item->fresh();
        $this->assertSame('12.3456', (string) $freshItem->original_quantity);
        $this->assertSame('2.7050', (string) $freshItem->adjusted_quantity);
        $this->assertSame('45.67', (string) $freshItem->adjusted_unit_price);
        $this->assertSame('123.54', (string) $freshItem->line_credit_amount);

        // Test recalculateTotals
        $creditNote->recalculateTotals();
        $this->assertEquals(123.54, (float) $creditNote->fresh()->total_credit_amount);
    }

    public function test_referential_integrity_cascades_and_null_on_delete(): void
    {
        $creditNote = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->create();

        $creditNoteItem = ShopCreditNoteItem::factory()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_item_id' => $this->invoiceItem->id,
            'product_id' => $this->product->id,
        ]);

        // When invoice item is deleted, credit note item remains with null shop_invoice_item_id
        $this->invoiceItem->delete();
        $this->assertDatabaseHas('shop_credit_note_items', [
            'id' => $creditNoteItem->id,
            'shop_invoice_item_id' => null,
        ]);

        // When credit note is deleted, its items are cascaded
        $creditNote->delete();
        $this->assertDatabaseMissing('shop_credit_notes', ['id' => $creditNote->id]);
        $this->assertDatabaseMissing('shop_credit_note_items', ['id' => $creditNoteItem->id]);
    }

    public function test_creating_credit_notes_does_not_mutate_original_invoice(): void
    {
        $originalSubtotal = (float) $this->invoice->subtotal;
        $originalFinalTotal = (float) $this->invoice->final_total;
        $originalBalance = (float) $this->invoice->balance_amount;
        $originalItemDeliveredQty = (float) $this->invoiceItem->delivered_qty;
        $originalItemFinalTotal = (float) $this->invoiceItem->final_line_total;

        $creditNote = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->approved()
            ->create([
                'total_credit_amount' => 500.00,
            ]);

        ShopCreditNoteItem::factory()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_item_id' => $this->invoiceItem->id,
            'product_id' => $this->product->id,
            'adjusted_quantity' => 12.5,
            'adjusted_unit_price' => 40.00,
            'line_credit_amount' => 500.00,
        ]);

        $freshInvoice = $this->invoice->fresh();
        $freshItem = $this->invoiceItem->fresh();

        $this->assertSame($originalSubtotal, (float) $freshInvoice->subtotal);
        $this->assertSame($originalFinalTotal, (float) $freshInvoice->final_total);
        $this->assertSame($originalBalance, (float) $freshInvoice->balance_amount);
        $this->assertSame($originalItemDeliveredQty, (float) $freshItem->delivered_qty);
        $this->assertSame($originalItemFinalTotal, (float) $freshItem->final_line_total);
        $this->assertSame('finalized', $freshInvoice->status);
    }

    public function test_supports_both_credit_and_debit_adjustment_directions(): void
    {
        $creditNote = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->create([
                'direction' => ShopCreditNoteDirection::Credit,
                'reason_type' => ShopCreditNoteReason::Shortage,
                'total_credit_amount' => 200.00,
            ]);

        $debitNote = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->create([
                'direction' => ShopCreditNoteDirection::Debit,
                'reason_type' => ShopCreditNoteReason::ExcessDelivered,
                'total_credit_amount' => 150.00,
            ]);

        $this->assertTrue($creditNote->isCredit());
        $this->assertFalse($creditNote->isDebit());
        $this->assertTrue($debitNote->isDebit());
        $this->assertFalse($debitNote->isCredit());
        $this->assertSame('Credit (Reduces Shop Payable)', $creditNote->direction->label());
        $this->assertSame('Debit (Increases Shop Payable)', $debitNote->direction->label());
    }

    public function test_credit_note_statuses_and_inventory_actions(): void
    {
        $approver = User::factory()->create();
        $voider = User::factory()->create();

        $creditNote = ShopCreditNote::factory()
            ->forInvoice($this->invoice)
            ->create([
                'status' => ShopCreditNoteStatus::Draft,
                'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
            ]);

        $this->assertTrue($creditNote->isDraft());
        $this->assertSame('Write Off as Wastage', $creditNote->inventory_action->label());

        // Approve
        $creditNote->update([
            'status' => ShopCreditNoteStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->assertTrue($creditNote->fresh()->isApproved());
        $this->assertTrue($creditNote->fresh()->approvedBy->is($approver));

        // Void
        $creditNote->update([
            'status' => ShopCreditNoteStatus::Voided,
            'voided_by' => $voider->id,
            'voided_at' => now(),
            'void_reason' => 'Duplicate entry',
        ]);

        $this->assertTrue($creditNote->fresh()->isVoided());
        $this->assertTrue($creditNote->fresh()->voidedBy->is($voider));
        $this->assertSame('Duplicate entry', $creditNote->fresh()->void_reason);
    }
}
