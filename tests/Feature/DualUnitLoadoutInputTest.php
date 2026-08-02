<?php

namespace Tests\Feature;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualUnitLoadoutInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_dual_loadout_input_stores_bunch_count_and_actual_kg(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->syncRoles(['admin']);

        $category = Category::create(['name' => 'Fruits', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Banana Yelakki Green',
            'sku' => 'BAN-001',
            'category_id' => $category->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);

        ProductUnit::create([
            'product_id' => $product->id,
            'unit' => 'full_bunch',
            'label' => 'FULL_BUNCH',
            'conversion_to_base' => 12.5000,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'is_active' => true,
        ]);

        // Initial warehouse stock: 50.00 KG
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'reference' => 'BATCH-BAN-001',
            'received_at' => now(),
            'total_kg' => 50.0,
            'cost_per_kg' => 15.0,
            'status' => BatchStatus::Sorted,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'grade' => 'A',
            'type' => StockMovementType::In,
            'quantity' => 50.0,
            'cost_per_unit' => 15.0,
            'notes' => 'Initial Stock',
        ]);

        $priceGroup = \App\Models\ShopPriceGroup::create(['name' => 'A', 'code' => 'A', 'is_active' => true]);

        \App\Models\DailyPriceApproval::create([
            'product_id' => $product->id,
            'price_group_id' => $priceGroup->id,
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

        $shop = Shop::create([
            'name' => 'Green Leaf Fresh Shop',
            'code' => 'SH-01',
            'shop_price_group_id' => $priceGroup->id,
            'is_active' => true,
        ]);

        $order = ShopOrder::create([
            'order_number' => 'ORD-BANANA-001',
            'shop_id' => $shop->id,
            'order_date' => now(),
            'business_date' => now()->toDateString(),
            'delivery_date' => now(),
            'state' => 'approved',
            'status' => 'approved',
            'delivery_status' => 'pending_delivery',
            'created_by' => $user->id,
            'total_amount' => 250.00,
        ]);

        // 1 FULL_BUNCH ordered (estimated base weight = 12.50 KG)
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 12.50,
            'approved_qty' => 12.50,
            'requested_unit' => 'full_bunch',
            'requested_unit_label' => 'FULL_BUNCH',
            'requested_unit_quantity' => 1.0,
            'requested_unit_conversion_to_base' => 12.50,
            'unit' => 'KG',
            'locked_selling_price' => 20.00,
            'line_total' => 250.00,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);

        // Submit warehouse loadout: 1 FULL_BUNCH, actual weight = 12.50 KG
        $response = $this->actingAs($user)->post(route('warehouse.loadout.save', $order), [
            'items' => [
                $product->id => '12.50',
            ],
            'item_unit_qtys' => [
                $product->id => '1.00',
            ],
        ]);

        $response->assertRedirect(route('warehouse.loadout.show', $order));

        $loadedOrderItem = ShopOrderItem::where('shop_order_id', $order->id)
            ->where('product_id', $product->id)
            ->where('sorting_status', 'loaded')
            ->firstOrFail();

        $this->assertEquals(1.00, (float) $loadedOrderItem->requested_unit_quantity);
        $this->assertEquals('full_bunch', $loadedOrderItem->requested_unit);
        $this->assertEquals(1.00, (float) $loadedOrderItem->loaded_order_unit_qty);
        $this->assertEquals(12.50, (float) $loadedOrderItem->loaded_qty);
        $this->assertEquals('KG', $loadedOrderItem->unit);

        // Verify out for delivery moves stock in KG
        $this->actingAs($user)->post(route('warehouse.loadout.move-to-delivery', $order));
        $order->refresh();
        $this->assertEquals('in_transit', $order->delivery_status);

        // Check stock movement (50 KG - 12.50 KG = 37.50 KG remaining stock)
        $outMovement = StockMovement::where('product_id', $product->id)
            ->where('type', StockMovementType::Out)
            ->first();
        $this->assertNotNull($outMovement);
        $this->assertEquals(12.50, (float) $outMovement->quantity);

        // Generate Shop Invoice and verify billing uses actual delivered KG (12.50 KG * 20.00 = Rs. 250.00)
        $invoiceService = app(ShopInvoiceService::class);
        $invoice = $invoiceService->applyDeliveryCheckin($order->fresh(), [$loadedOrderItem->id => 12.50], $user->id);
        $invoiceItem = $invoice->items->first();

        $this->assertEquals(12.50, (float) $invoiceItem->delivered_qty);
        $this->assertEquals(250.00, (float) $invoiceItem->final_line_total);
        $this->assertEquals(250.00, (float) $invoice->final_total);
    }
}
