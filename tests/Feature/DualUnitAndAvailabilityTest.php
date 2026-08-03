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

class DualUnitAndAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dual_unit_and_not_available_workflow(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->syncRoles(['admin']);

        // 1. Setup Product with base unit KG and secondary unit Bunch (1 Bunch = 1.8 KG)
        $category = Category::create(['name' => 'Greens', 'is_active' => true]);
        $product1 = Product::create([
            'name' => 'Spinach',
            'sku' => 'SPN-001',
            'category_id' => $category->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);

        $productUnit1 = ProductUnit::create([
            'product_id' => $product1->id,
            'unit' => 'bunch',
            'label' => 'BUNCH',
            'conversion_to_base' => 1.8000,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $product2 = Product::create([
            'name' => 'Coriander',
            'sku' => 'COR-001',
            'category_id' => $category->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'is_active' => true,
        ]);

        // Stock IN for Spinach (15 KG available)
        $batch = StockBatch::create([
            'product_id' => $product1->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'reference' => 'GRN-SPN-001',
            'received_at' => now(),
            'total_kg' => 15.0,
            'cost_per_kg' => 10.0,
            'status' => BatchStatus::Sorted,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product1->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'grade' => 'A',
            'type' => StockMovementType::In,
            'quantity' => 15.0,
            'cost_per_unit' => 10.0,
            'notes' => 'Initial Stock',
        ]);

        // Approved daily price
        \App\Models\DailyPriceApproval::create([
            'product_id' => $product1->id,
            'business_date' => now()->toDateString(),
            'price_date' => now()->toDateString(),
            'purchase_price' => 10.0,
            'price_a' => 20.0,
            'price_b' => 22.0,
            'price_c' => 25.0,
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        \App\Models\DailyPriceApproval::create([
            'product_id' => $product2->id,
            'business_date' => now()->toDateString(),
            'price_date' => now()->toDateString(),
            'purchase_price' => 10.0,
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
            'name' => 'Test Shop B',
            'code' => 'SHOP-B',
            'shop_price_group_id' => $priceGroup->id,
            'is_active' => true,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'order_number' => 'ORD-DUAL-001',
            'business_date' => now()->toDateString(),
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'order_source' => 'shop_owner',
            'created_by' => $user->id,
        ]);

        // Item 1: Shop ordered 10 Bunch (Estimated 18.00 KG)
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product1->id,
            'unit' => 'KG',
            'requested_product_unit_id' => $productUnit1->id,
            'requested_unit' => 'bunch',
            'requested_unit_label' => 'BUNCH',
            'requested_unit_quantity' => 10.0,
            'requested_unit_conversion_to_base' => 1.8000,
            'requested_qty' => 18.0,
            'approved_qty' => 18.0,
            'locked_selling_price' => 20.0,
            'line_total' => 360.0,
            'sorting_status' => 'allocated',
        ]);

        // Item 2: Shop ordered 5 KG Coriander
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product2->id,
            'unit' => 'KG',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'locked_selling_price' => 20.0,
            'line_total' => 100.0,
            'sorting_status' => 'allocated',
        ]);

        // 2. Perform Loadout:
        // Product 1 (Spinach): KG input is ignored because loadout unit quantity (10 bunch) is authoritative
        // Product 2 (Coriander): Marked as "Not Available" (Out of Stock)
        $this->actingAs($user)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => [
                    $product1->id => 21.0,
                    $product2->id => 0.0,
                ],
                'item_status' => [
                    $product1->id => 'loaded',
                    $product2->id => 'not_available',
                ],
                'item_notes' => [
                    $product2->id => 'Crop fresh harvest out of stock today',
                ],
            ])
            ->assertRedirect();

        // 3. Move to Delivery
        $this->actingAs($user)
            ->post(route('warehouse.loadout.move-to-delivery', $order))
            ->assertRedirect();

        $order->fresh();
        $order->update(['delivery_status' => 'delivered']);

        // 4. Generate Invoice
        $invoiceService = app(ShopInvoiceService::class);
        $invoice = $invoiceService->synchronizeOrderInvoice($order, $user->id);

        // 5. Assertions
        $item1 = ShopOrderItem::where('shop_order_id', $order->id)->where('product_id', $product1->id)->first();
        $item2 = ShopOrderItem::where('shop_order_id', $order->id)->where('product_id', $product2->id)->first();

        // Verify Item 1 (Dual Unit):
        $this->assertEquals(10.0, (float) $item1->requested_unit_quantity);
        $this->assertEquals('bunch', $item1->requested_unit);
        $this->assertEquals(1.8000, (float) $item1->requested_unit_conversion_to_base);
        $this->assertEquals(18.0, (float) $item1->approved_qty); // Preserved estimated base qty
        $this->assertEquals(18.0, (float) $item1->loaded_qty); // Converted from 10 bunch * 1.8
        $this->assertEquals(0.0, (float) $item1->excess_qty); // Matches approved quantity

        // Verify Item 2 (Not Available):
        $this->assertEquals(0.0, (float) $item2->loaded_qty);
        $this->assertEquals('not_available', $item2->sorting_status);
        $this->assertEquals('not_available', $item2->loadout_discrepancy_type);
        $this->assertEquals('Crop fresh harvest out of stock today', $item2->loadout_discrepancy_note);

        // Verify Invoice:
        $invoiceFresh = ShopInvoice::with('items')->find($invoice->id);
        $invItem1 = $invoiceFresh->items->where('product_id', $product1->id)->first();
        $invItem2 = $invoiceFresh->items->where('product_id', $product2->id)->first();

        // Item 1 billed for 18.00 KG = 18 * 20 = 360.00
        $this->assertEquals(18.0, (float) $invItem1->delivered_qty);
        $this->assertEquals(360.0, (float) $invItem1->final_line_total);

        // Item 2 not available -> delivered_qty = 0, total = 0
        $this->assertEquals(0.0, (float) ($invItem2?->delivered_qty ?? 0.0));
        $this->assertEquals(0.0, (float) ($invItem2?->final_line_total ?? 0.0));
    }
}
