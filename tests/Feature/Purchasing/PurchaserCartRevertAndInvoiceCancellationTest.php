<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCredit;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserCartRevertAndInvoiceCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create();
    }

    public function test_revert_to_pending_flow_full_lifecycle(): void
    {
        try {
            $today = today();

            // 1. Completed purchaser cart with payment_status = paid
            $cart = PurchaserCart::query()->create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $this->supplier->id,
                'status' => 'submitted',
                'payment_status' => 'paid',
                'business_date' => $today,
                'cart_number' => 'VC-TEST-'.uniqid(),
                'bill_number' => 'INV-TEST-001',
            ]);

            $cartItem = PurchaserCartItem::query()->create([
                'purchaser_cart_id' => $cart->id,
                'product_id' => $this->product->id,
                'quantity' => 10,
                'unit_price' => 50,
                'line_total' => 500,
            ]);

            // 4. Approved GRN exists
            $goodsReceived = GoodsReceived::factory()->create([
                'purchaser_cart_id' => $cart->id,
                'status' => 'approved',
                'bill_status' => 'matched',
                'bill_number' => 'INV-TEST-001',
            ]);

            GoodsReceivedItem::factory()->create([
                'goods_received_id' => $goodsReceived->id,
                'product_id' => $this->product->id,
                'received_qty' => 10,
            ]);

            StockBatch::factory()->create([
                'goods_received_id' => $goodsReceived->id,
                'product_id' => $this->product->id,
                'warehouse_receive_pending' => false,
            ]);

            // 3. Active completed PurchaseInvoice exists
            $invoice = PurchaseInvoice::factory()->create([
                'purchaser_cart_id' => $cart->id,
                'goods_received_id' => $goodsReceived->id,
                'supplier_id' => $this->supplier->id,
                'invoice_number' => 'INV-TEST-001',
                'status' => 'approved',
                'amount' => 500,
                'paid_amount' => 500,
                'payment_status' => 'paid',
            ]);

            $cart->update(['purchase_invoice_id' => $invoice->id]);

            // 6. Auto-payment journal entry exists
            $journal = JournalEntry::query()->create([
                'source_type' => PurchaseInvoice::class,
                'source_id' => $invoice->id,
                'source_event' => 'purchaser_daily_purchase_payment:paid-50000',
                'description' => 'Auto payment sync for invoice',
                'amount' => 500,
                'entry_date' => $today,
                'created_by' => $this->purchaser->id,
            ]);

            // 7. Purchaser credit exists
            $credit = PurchaserCredit::query()->create([
                'purchaser_id' => $this->purchaser->id,
                'type' => 'out',
                'amount' => 500,
                'description' => 'Credit for invoice: '.$invoice->invoice_number,
                'purchase_invoice_id' => $invoice->id,
                'created_by' => $this->purchaser->id,
                'business_date' => $today,
            ]);

            // Step 1: Initial check
            $initialVendors = $this->actingAs($this->purchaser)
                ->get(route('purchaser.vendors', ['date' => $today->format('Y-m-d'), 'tab' => 'completed']));
            $initialVendors->assertOk();

            // Step 2: Delete / Revert
            $response = $this->actingAs($this->purchaser)
                ->delete(route('purchaser.invoices.destroy', $invoice), [
                    'cancellation_note' => 'Reverting cart for quantity change',
                ]);
            $response->assertRedirect();

            // Step 3: Check database records
            $cart->refresh();
            $goodsReceived->refresh();
            $trashedInvoice = PurchaseInvoice::withTrashed()->find($invoice->id);

            $this->assertDatabaseMissing('journal_entries', ['id' => $journal->id]);
            $this->assertDatabaseMissing('purchaser_credits', ['id' => $credit->id]);
            $this->assertNotNull($trashedInvoice);
            $this->assertSame(InvoiceStatus::Cancelled, $trashedInvoice->status);
            $this->assertNotNull($trashedInvoice->deleted_at);
            $this->assertSame('submitted', $cart->status);
            $this->assertNull($cart->bill_number);
            $this->assertSame('unpaid', $cart->payment_status);
            $this->assertSame('approved', $goodsReceived->status);
            $this->assertSame('bill_pending', $goodsReceived->bill_status);

            // Step 4: Check Pending view
            $vendorsPendingResponse = $this->actingAs($this->purchaser)
                ->get(route('purchaser.vendors', ['date' => $today->format('Y-m-d'), 'tab' => 'pending']));
            $vendorsPendingResponse->assertOk();

            // Step 5: Check Cancelled view
            $vendorsCancelledResponse = $this->actingAs($this->purchaser)
                ->get(route('purchaser.vendors', ['date' => $today->format('Y-m-d'), 'tab' => 'cancelled']));
            $vendorsCancelledResponse->assertOk();

            // Step 6: Check Invoice show
            $invoiceShowResponse = $this->actingAs($this->purchaser)
                ->get(route('purchaser.invoices.show', $invoice));
            $invoiceShowResponse->assertOk();

            // Step 7: Repeated delete
            $repeatedResponse = $this->actingAs($this->purchaser)
                ->delete(route('purchaser.invoices.destroy', $invoice));
            $repeatedResponse->assertNotFound();
        } catch (\Throwable $e) {
            echo "\n\nEXCEPTION: ".$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n".$e->getTraceAsString()."\n\n";
            throw $e;
        }
    }

    public function test_cancelled_tab_deduplication_does_not_double_count_reverted_cart_and_invoice(): void
    {
        $today = today();

        // 1. A reverted cart (submitted, unpaid, with cancelled invoice)
        $cart1 = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'payment_status' => 'unpaid',
            'business_date' => $today,
            'cart_number' => 'VC-REVERTED-1',
        ]);

        $invoice1 = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart1->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-REV-001',
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'deleted_at' => now(),
        ]);

        // 2. A standalone cancelled cart (never had an invoice)
        $cart2 = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'cancelled',
            'business_date' => $today,
            'cart_number' => 'VC-STANDALONE-CANCEL',
        ]);

        $vendorsResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors', ['date' => $today->format('Y-m-d'), 'tab' => 'cancelled']));

        $vendorsResponse->assertOk();
        $vendorsResponse->assertViewHas('totalCancelledCount', 2);
        $vendorsResponse->assertViewHas('cancelledInvoices', fn ($invoices) => $invoices->count() === 1 && $invoices->contains('id', $invoice1->id));
        $vendorsResponse->assertViewHas('cancelledCarts', fn ($carts) => $carts->count() === 1 && $carts->contains('id', $cart2->id));
    }

    public function test_purchaser_cannot_destroy_another_purchasers_invoice(): void
    {
        $otherPurchaser = User::factory()->create();
        $otherPurchaser->assignRole('purchaser');

        $cart = PurchaserCart::query()->create([
            'user_id' => $otherPurchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'business_date' => today(),
            'cart_number' => 'VC-TEST-'.uniqid(),
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-OTHER-001',
        ]);

        $response = $this->actingAs($this->purchaser)
            ->delete(route('purchaser.invoices.destroy', $invoice));

        $response->assertNotFound();
    }
}
