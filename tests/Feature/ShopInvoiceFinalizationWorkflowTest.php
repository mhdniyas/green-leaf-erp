<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopInvoiceFinalizationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashbook_uses_only_finalized_invoice_amount_and_retries_are_idempotent(): void
    {
        $admin = User::factory()->create();
        $invoice = $this->invoiceWithSingleItem(finalized: false);
        $service = app(ShopInvoiceService::class);

        $service->recalculate($invoice->fresh('items'));

        $this->assertSame(0, ShopLedgerTransaction::query()->count());

        $finalized = $service->markFinalized(
            $invoice->fresh('items'),
            (int) $admin->id,
            'test_finalized',
            'Test finalization.'
        );

        $this->assertNotNull($finalized->finalized_at);
        $this->assertSame(1, ShopLedgerTransaction::query()->count());
        $this->assertSame(250.0, (float) ShopLedgerTransaction::query()->firstOrFail()->amount);

        $service->markFinalized($finalized->fresh('items'), (int) $admin->id, 'test_retry', 'Retry.');

        $this->assertSame(1, ShopLedgerTransaction::query()->count());
        $this->assertSame(250.0, (float) ShopLedgerTransaction::query()->firstOrFail()->amount);
    }

    public function test_finalized_invoice_rejects_normal_mutation_paths(): void
    {
        $admin = User::factory()->create();
        $invoice = $this->invoiceWithSingleItem(finalized: true, finalizedBy: $admin);
        $service = app(ShopInvoiceService::class);

        try {
            $service->applyAdminDiscount($invoice, [
                'discount_total' => 10,
                'discount_note' => 'Late discount.',
            ], (int) $admin->id);
            $this->fail('Finalized discount mutation was allowed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('discount_total', $exception->errors());
        }

        try {
            $service->repriceInvoice($invoice->fresh('items'), (int) $admin->id, 'Reprice after final.');
            $this->fail('Finalized reprice mutation was allowed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice', $exception->errors());
        }

        $this->assertSame(250.0, (float) $invoice->fresh()->final_total);
    }

    private function invoiceWithSingleItem(bool $finalized, ?User $finalizedBy = null): ShopInvoice
    {
        LedgerEntryType::query()->firstOrCreate(
            ['code' => 'purchase_bill'],
            ['name' => 'Purchase Bill', 'category' => 'expense', 'active' => true]
        );

        $shop = Shop::factory()->create();
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
        ]);
        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-TEST-'.$order->id,
            'business_date' => today()->toDateString(),
            'status' => $finalized ? 'payment_pending' : 'generated',
            'delivery_status' => $finalized ? 'received_full' : 'pending',
            'subtotal' => 250,
            'final_total' => 250,
            'balance_amount' => 250,
            'finalized_by' => $finalizedBy?->id,
            'finalized_at' => $finalized ? now() : null,
        ]);
        $product = Product::factory()->create(['unit' => 'kg']);
        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => 'Tomato',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 10,
            'price_quantity' => 10,
            'delivered_qty' => 10,
            'delivered_price_quantity' => 10,
            'unit_price' => 25,
            'line_subtotal' => 250,
            'final_line_total' => 250,
        ]);

        return $invoice;
    }
}
