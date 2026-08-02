<?php

namespace Tests\Feature;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExcessLoadoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_excess_loadout_scenario(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->syncRoles(['admin']);

        // 1. Setup Product, Warehouse with 8 kg stock
        $category = Category::create(['name' => 'Vegetables', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Test Tomato',
            'sku' => 'TOM-001',
            'category_id' => $category->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'is_active' => true,
        ]);

        $batch = StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'reference' => 'GRN-001',
            'received_at' => now(),
            'total_kg' => 8.0,
            'cost_per_kg' => 15.0,
            'status' => BatchStatus::Sorted,
        ]);

        // Stock movement IN (8 kg)
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'grade' => 'A',
            'type' => StockMovementType::In,
            'quantity' => 8.0,
            'cost_per_unit' => 15.0,
            'notes' => 'Initial Stock',
        ]);

        // Create approved daily price
        \App\Models\DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => now()->toDateString(),
            'price_date' => now()->toDateString(),
            'purchase_price' => 15.0,
            'price_a' => 20.0,
            'price_b' => 22.0,
            'price_c' => 25.0,
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        $priceGroup = \App\Models\ShopPriceGroup::create([
            'name' => 'A',
            'code' => 'GROUP-A',
            'margin_percentage' => 10.0,
            'margin_a' => 10.0,
            'margin_b' => 12.0,
            'margin_c' => 15.0,
            'is_active' => true,
        ]);

        $shop = Shop::create([
            'name' => 'Test Shop A',
            'code' => 'SHOP-A',
            'shop_price_group_id' => $priceGroup->id,
            'is_active' => true,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'order_number' => 'ORD-TEST-001',
            'business_date' => now()->toDateString(),
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'order_source' => 'shop_owner',
            'created_by' => $user->id,
        ]);

        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'unit' => 'KG',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'locked_selling_price' => 20.0,
            'line_total' => 200.0,
            'sorting_status' => 'allocated',
        ]);

        // 3. Execute Loadout with 12 kg (excess quantity)
        $this->actingAs($user)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => [
                    $product->id => 12.0,
                ],
            ])
            ->assertRedirect();

        // 4. Move to Delivery & Confirm Delivery
        $this->actingAs($user)
            ->post(route('warehouse.loadout.move-to-delivery', $order))
            ->assertRedirect();

        $order->fresh();
        $order->update(['delivery_status' => 'delivered']);

        // 5. Generate Invoice
        $invoiceService = app(ShopInvoiceService::class);
        $invoice = $invoiceService->synchronizeOrderInvoice($order, $user->id);

        // 6. Assertions & Output Printing for User
        $orderItems = ShopOrderItem::where('shop_order_id', $order->id)->get();
        $movements = StockMovement::where('product_id', $product->id)->get();
        $batchFresh = StockBatch::find($batch->id);
        $invoiceFresh = ShopInvoice::with('items')->find($invoice->id);
        $invoiceItem = $invoiceFresh->items->first();

        fwrite(STDERR, "\n=== TEST RESULT OUTPUT ===\n" . json_encode([
            '1_shop_order_items' => $orderItems->toArray(),
            '2_stock_movements' => $movements->toArray(),
            '3_stock_batch_remaining_kg' => $batchFresh->remaining_kg,
            '4_delivery_quantity' => 12.0,
            '5_shop_invoice' => [
                'approved_qty' => $invoiceItem->approved_qty,
                'delivered_qty' => $invoiceItem->delivered_qty,
                'excess_qty' => $invoiceItem->excess_qty,
                'unit_price' => $invoiceItem->unit_price,
                'line_subtotal' => $invoiceItem->line_subtotal,
                'excess_amount' => $invoiceItem->excess_amount,
                'final_line_total' => $invoiceItem->final_line_total,
            ],
            '6_negative_or_duplicate_items_check' => [
                'item_count' => $orderItems->count(),
                'any_negative_remaining' => $orderItems->contains(fn ($i) => (float) $i->approved_qty < 0),
            ],
        ], JSON_PRETTY_PRINT) . "\n=== END TEST RESULT OUTPUT ===\n");

        $this->assertEquals(1, $orderItems->count());
        $this->assertEquals(10.0, (float) $orderItems->first()->approved_qty);
        $this->assertEquals(12.0, (float) $orderItems->first()->loaded_qty);
        $this->assertEquals(2.0, (float) $orderItems->first()->excess_qty);
        $this->assertEquals(10.0, (float) $invoiceItem->approved_qty);
        $this->assertEquals(12.0, (float) $invoiceItem->delivered_qty);
        $this->assertEquals(2.0, (float) $invoiceItem->excess_qty);
        $this->assertEquals(240.0, (float) $invoiceItem->final_line_total);
        $this->assertFalse($orderItems->contains(fn ($i) => (float) $i->approved_qty < 0));
    }
}
