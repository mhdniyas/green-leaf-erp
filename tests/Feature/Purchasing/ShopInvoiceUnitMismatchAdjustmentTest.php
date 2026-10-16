<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopInvoiceUnitMismatchAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('purchase');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'name' => 'Test Shop',
            'code' => 'TS01',
        ]);
    }

    public function test_updating_item_with_kg_unit_and_piece_price_unit_succeeds_proportionally(): void
    {
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-16',
            'delivery_status' => 'pending_approval',
        ]);

        $product = Product::factory()->create([
            'name' => 'Coconut',
            'unit' => 'kg',
            'base_price' => 50.00,
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 10.00,
            'loaded_qty' => 10.00,
            'actual_weight' => 10.00,
            'requested_unit' => 'kg',
            'requested_unit_quantity' => 10.00,
            'unit' => 'kg',
            'sorting_status' => 'loaded',
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20261016-TEST',
            'business_date' => '2026-10-16',
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 630.00,
            'final_total' => 630.00,
        ]);

        $invoiceItem = ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'Coconut',
            'unit' => 'kg',
            'price_unit' => 'piece',
            'approved_qty' => 10.00,
            'price_quantity' => 10.0000,
            'delivered_qty' => 10.00,
            'delivered_price_quantity' => 10.0000,
            'shortage_qty' => 0.00,
            'shortage_price_quantity' => 0.0000,
            'excess_qty' => 0.00,
            'excess_price_quantity' => 0.0000,
            'unit_price' => 63.00,
            'line_subtotal' => 630.00,
            'final_line_total' => 630.00,
        ]);

        $service = app(ShopInvoiceService::class);

        // Update item to 5 kg (should proportionally evaluate to 5 pieces at 63 = 315)
        $updatedInvoice = $service->updateItemFinalQuantityAndPrice(
            $invoice,
            $invoiceItem,
            5.0,
            63.0,
            $this->admin->id,
            'Reduced coconut qty'
        );

        $freshItem = $invoiceItem->fresh();
        $this->assertEquals(5.0, (float) $freshItem->delivered_qty);
        $this->assertEquals(5.0, (float) $freshItem->delivered_price_quantity);
        $this->assertEquals(315.00, (float) $freshItem->final_line_total);
        $this->assertEquals(315.00, (float) $updatedInvoice->final_total);
    }

    public function test_updating_daily_price_matrix_cell_updates_price_unit(): void
    {
        $product = Product::factory()->create([
            'name' => 'Coconut',
            'unit' => 'kg',
            'base_price' => 50.00,
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson(route('purchasing.prices.matrix.cell.update'), [
            'product_id' => $product->id,
            'date' => '2026-10-16',
            'price_category' => 'a',
            'price' => 63.00,
            'price_unit' => 'kg',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-10-16')
            ->first();

        $this->assertNotNull($approval);
        $this->assertEquals('kg', $approval->price_unit);
        $this->assertEquals(63.00, (float) $approval->price_a);
    }
}
