<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopInvoiceFinalizedPriceEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private ShopPriceGroup $priceGroup;

    private LedgerEntryType $purchaseBillType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->purchaseBillType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'purchase_bill'],
            ['name' => 'Purchase Bill', 'category' => 'expense', 'active' => true]
        );

        $this->priceGroup = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 10, 'is_active' => true],
        );

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');
    }

    public function test_finalized_price_edit_via_bill_prices_page_updates_item_totals_and_invoice_total(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createFinalizedInvoice('SINV-20260831-PRC1', $date, initialPrice: 20.0, qty: 10.0);
        $item = $invoice->items->first();

        // 1. Post new special price to bill-prices endpoint
        $response = $this->actingAs($this->admin)->post(route('purchaser.bill-prices.invoice-prices.update', $invoice), [
            'prices' => [
                $item->id => [
                    'product_id' => $item->product_id,
                    'price_unit' => 'kg',
                    'selling_price' => 35.0,
                    'reason' => 'Admin updated price after market review.',
                ],
            ],
        ]);

        $response->assertRedirect();

        // 2. Verify ShopDailyProductPrice was created and approved
        $specialPrice = ShopDailyProductPrice::query()
            ->where('shop_id', $invoice->shop_id)
            ->where('product_id', $item->product_id)
            ->whereDate('business_date', $date)
            ->first();

        $this->assertNotNull($specialPrice);
        $this->assertSame(35.0, (float) $specialPrice->selling_price);

        // 3. Verify Invoice item and Invoice totals were updated
        $item->refresh();
        $this->assertSame(35.0, (float) $item->unit_price);
        $this->assertSame(350.0, (float) $item->line_subtotal);
        $this->assertSame(350.0, (float) $item->final_line_total);

        $invoice->refresh();
        $this->assertSame(350.0, (float) $invoice->subtotal);
        $this->assertSame(350.0, (float) $invoice->final_total);
        $this->assertSame(350.0, (float) $invoice->balance_amount);

        // 4. Verify visiting both invoice show URL and bill-prices show URL displays the new total
        $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.show', $invoice))
            ->assertOk()
            ->assertSee('350.00');

        $this->actingAs($this->admin)
            ->get(route('purchaser.bill-prices.show', $invoice))
            ->assertOk()
            ->assertSee('350.00');
    }

    public function test_finalized_price_edit_via_api_updates_invoice(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createFinalizedInvoice('SINV-20260831-PRC2', $date, initialPrice: 20.0, qty: 10.0);
        $item = $invoice->items->first();

        $this->withoutExceptionHandling();
        Sanctum::actingAs($this->purchaser);
        $response = $this->postJson('/api/v1/purchaser/bill-prices/update', [
            'order_number' => $invoice->invoice_number,
            'item_id' => $item->id,
            'product_id' => $item->product_id,
            'shop_id' => $invoice->shop_id,
            'special_price' => 50.0,
            'notes' => 'Special price from purchaser app.',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'invoice_final_total' => 500.0,
                'invoice_repriced' => true,
            ],
        ]);

        $invoice->refresh();
        $this->assertSame(500.0, (float) $invoice->final_total);
        $this->assertSame(500.0, (float) $invoice->balance_amount);
    }

    public function test_finalized_price_edit_blocked_when_payments_exist(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createFinalizedInvoice('SINV-20260831-PRC3', $date, initialPrice: 20.0, qty: 10.0, paidAmount: 200.0);
        $item = $invoice->items->first();

        Sanctum::actingAs($this->purchaser);
        $response = $this->postJson('/api/v1/purchaser/bill-prices/update', [
            'order_number' => $invoice->invoice_number,
            'item_id' => $item->id,
            'product_id' => $item->product_id,
            'shop_id' => $invoice->shop_id,
            'special_price' => 50.0,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);

        // Invoice final total must remain unchanged
        $invoice->refresh();
        $this->assertSame(200.0, (float) $invoice->final_total);
    }

    public function test_unrelated_invoices_remain_unchanged(): void
    {
        $date = '2026-08-31';
        $invoiceA = $this->createFinalizedInvoice('SINV-20260831-SHPA', $date, initialPrice: 20.0, qty: 10.0);
        $invoiceB = $this->createFinalizedInvoice('SINV-20260831-SHPB', $date, initialPrice: 20.0, qty: 10.0);

        $itemA = $invoiceA->items->first();

        $this->actingAs($this->admin)->post(route('purchaser.bill-prices.invoice-prices.update', $invoiceA), [
            'prices' => [
                $itemA->id => [
                    'product_id' => $itemA->product_id,
                    'price_unit' => 'kg',
                    'selling_price' => 30.0,
                ],
            ],
        ]);

        $invoiceA->refresh();
        $invoiceB->refresh();

        $this->assertSame(300.0, (float) $invoiceA->final_total);
        $this->assertSame(200.0, (float) $invoiceB->final_total);
    }

    private function createFinalizedInvoice(
        string $invoiceNumber,
        string $businessDate,
        float $initialPrice = 20.0,
        float $qty = 10.0,
        float $paidAmount = 0.0
    ): ShopInvoice {
        $finalTotal = round($initialPrice * $qty, 2);

        $shop = Shop::factory()->create([
            'code' => 'SHP-'.$invoiceNumber,
            'name' => 'Shop '.$invoiceNumber,
            'shop_price_group_id' => $this->priceGroup->id,
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
            'delivery_status' => 'delivered',
            'delivery_review_status' => 'approved',
            'shop_checked_at' => now(),
            'shop_checked_by' => $this->admin->id,
            'is_delivered' => true,
            'delivered_at' => now(),
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => $paidAmount >= $finalTotal ? 'paid' : 'payment_pending',
            'delivery_status' => 'approved_after_discrepancy',
            'payment_status' => $paidAmount >= $finalTotal ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'unpaid'),
            'subtotal' => $finalTotal,
            'final_total' => $finalTotal,
            'paid_amount' => $paidAmount,
            'balance_amount' => max(0.0, $finalTotal - $paidAmount),
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);

        $product = Product::factory()->create([
            'name' => 'Product '.$invoiceNumber,
            'unit' => 'kg',
            'base_price' => $initialPrice,
            'is_active' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $businessDate,
            'purchase_price' => $initialPrice - 5,
            'price_unit' => 'kg',
            'price_a' => $initialPrice,
            'price_b' => $initialPrice,
            'price_c' => $initialPrice,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => $qty,
            'approved_qty' => $qty,
            'loaded_qty' => $qty,
            'delivered_qty' => $qty,
            'shop_reported_received_qty' => $qty,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => $qty,
            'requested_unit_conversion_to_base' => 1,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => $initialPrice,
            'locked_price_source' => 'manual',
            'unit_cost' => $initialPrice - 5,
            'unit_price' => $initialPrice,
            'line_total' => $finalTotal,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => $qty,
            'price_quantity' => $qty,
            'delivered_qty' => $qty,
            'delivered_price_quantity' => $qty,
            'unit_price' => $initialPrice,
            'line_subtotal' => $finalTotal,
            'final_line_total' => $finalTotal,
        ]);

        return $invoice;
    }
}
