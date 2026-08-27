<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\PurchaserCredit;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementAllocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseInvoiceCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->product = Product::factory()->create();
    }

    public function test_authorized_admin_can_cancel_eligible_unpaid_invoice_without_touching_stock(): void
    {
        [$invoice, $goodsReceived, $batch] = $this->invoiceWithReceivedStock();

        PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 250,
            'description' => 'Debit for invoice: '.$invoice->invoice_number,
            'purchase_invoice_id' => $invoice->id,
            'created_by' => $this->admin->id,
            'business_date' => today(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('purchasing.invoices.cancel', $invoice), [
            'cancellation_reason' => 'Test invoice',
        ]);

        $response->assertRedirect(route('purchasing.invoices.show', $invoice->fresh()));
        $response->assertSessionHas('success', 'Purchase bill cancelled successfully.');

        $invoice->refresh();
        $goodsReceived->refresh();
        $batch->refresh();

        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertSame('cancelled', $invoice->payment_status);
        $this->assertSame('Test invoice', $invoice->cancellation_reason);
        $this->assertSame($this->admin->id, $invoice->cancelled_by);
        $this->assertNotNull($invoice->cancelled_at);
        $this->assertDatabaseHas('purchase_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('goods_received_items', ['goods_received_id' => $goodsReceived->id]);
        $this->assertSame('bill_pending', $goodsReceived->bill_status);
        $this->assertNull($goodsReceived->matched_by);
        $this->assertNull($goodsReceived->matched_at);
        $this->assertSame('50.000', (string) $batch->total_kg);
        $this->assertDatabaseMissing('purchaser_credits', ['purchase_invoice_id' => $invoice->id]);
    }

    public function test_unauthorized_user_receives_403(): void
    {
        [$invoice] = $this->invoiceWithReceivedStock();

        $this->actingAs($this->purchaser)
            ->post(route('purchasing.invoices.cancel', $invoice), ['cancellation_reason' => 'Test invoice'])
            ->assertForbidden();
    }

    public function test_cancellation_reason_and_other_note_are_required(): void
    {
        [$invoice] = $this->invoiceWithReceivedStock();

        $this->actingAs($this->admin)
            ->post(route('purchasing.invoices.cancel', $invoice), [])
            ->assertSessionHasErrors('cancellation_reason');

        $this->actingAs($this->admin)
            ->post(route('purchasing.invoices.cancel', $invoice), ['cancellation_reason' => 'Other'])
            ->assertSessionHasErrors('cancellation_note');
    }

    public function test_paid_payment_journal_and_settled_invoices_are_blocked(): void
    {
        [$paidInvoice] = $this->invoiceWithReceivedStock(['paid_amount' => 10]);
        $this->actingAs($this->admin)
            ->post(route('purchasing.invoices.cancel', $paidInvoice), ['cancellation_reason' => 'Test invoice'])
            ->assertSessionHasErrors('cancel');

        [$paymentInvoice] = $this->invoiceWithReceivedStock();
        PurchaseInvoicePayment::query()->create([
            'purchase_invoice_id' => $paymentInvoice->id,
            'supplier_id' => $paymentInvoice->supplier_id,
            'payment_date' => today(),
            'amount' => 1,
            'discount_amount' => 0,
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->admin)
            ->post(route('purchasing.invoices.cancel', $paymentInvoice), ['cancellation_reason' => 'Test invoice'])
            ->assertSessionHasErrors('cancel');

        [$journalInvoice] = $this->invoiceWithReceivedStock();
        JournalEntry::query()->create([
            'entry_date' => today(),
            'reference' => 'TEST-'.$journalInvoice->id,
            'description' => 'Existing payment journal',
            'source_type' => PurchaseInvoice::class,
            'source_id' => $journalInvoice->id,
            'source_event' => 'payment',
            'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->admin)
            ->post(route('purchasing.invoices.cancel', $journalInvoice), ['cancellation_reason' => 'Test invoice'])
            ->assertSessionHasErrors('cancel');

        [$settledInvoice] = $this->invoiceWithReceivedStock();
        $settlement = VendorSettlement::factory()->create(['supplier_id' => $settledInvoice->supplier_id]);
        VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $settledInvoice->id,
            'cash_allocated' => 1,
            'advance_allocated' => 0,
            'discount_allocated' => 0,
            'total_settled' => 1,
        ]);
        $this->actingAs($this->admin)
            ->post(route('purchasing.invoices.cancel', $settledInvoice), ['cancellation_reason' => 'Test invoice'])
            ->assertSessionHasErrors('cancel');
    }

    public function test_cancelled_invoice_is_excluded_from_active_invoice_list_but_detail_remains_available(): void
    {
        [$cancelled] = $this->invoiceWithReceivedStock([
            'invoice_number' => 'PINV-CANCELLED',
            'status' => InvoiceStatus::Cancelled,
            'payment_status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $this->admin->id,
            'cancellation_reason' => 'Test invoice',
        ]);
        $this->invoiceWithReceivedStock(['invoice_number' => 'PINV-ACTIVE']);

        $this->actingAs($this->admin)
            ->get(route('purchasing.invoices.index'))
            ->assertOk()
            ->assertSee('PINV-ACTIVE')
            ->assertDontSee('PINV-CANCELLED');

        $this->actingAs($this->admin)
            ->get(route('purchasing.invoices.show', $cancelled))
            ->assertOk()
            ->assertSee('CANCELLED')
            ->assertSee('Test invoice')
            ->assertDontSee('Record Payment');
    }

    /**
     * @param  array<string, mixed>  $invoiceOverrides
     * @return array{PurchaseInvoice, GoodsReceived, StockBatch}
     */
    private function invoiceWithReceivedStock(array $invoiceOverrides = []): array
    {
        $supplier = Supplier::factory()->create();
        $goodsReceived = GoodsReceived::factory()->create([
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'matched_by' => $this->admin->id,
            'matched_at' => now(),
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $goodsReceived->id,
            'purchase_order_item_id' => null,
            'product_id' => $this->product->id,
            'received_qty' => 50,
        ]);
        $batch = StockBatch::factory()->create([
            'goods_received_id' => $goodsReceived->id,
            'product_id' => $this->product->id,
            'total_kg' => 50,
        ]);
        $invoice = PurchaseInvoice::factory()->create(array_merge([
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PINV-'.Str::upper(Str::random(10)),
            'amount' => 250,
            'discount_amount' => 0,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'status' => InvoiceStatus::Pending,
        ], $invoiceOverrides));

        return [$invoice, $goodsReceived, $batch];
    }
}
