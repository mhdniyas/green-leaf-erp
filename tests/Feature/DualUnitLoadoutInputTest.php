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

    public function test_piece_billing_prefers_loadout_unit_quantity_when_present(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->syncRoles(['admin']);

        $category = Category::create(['name' => 'Vegetables', 'is_active' => true]);
        $priceGroup = \App\Models\ShopPriceGroup::create(['name' => 'A', 'code' => 'A', 'is_active' => true]);
        $shop = Shop::create([
            'name' => 'Piece Billing Shop',
            'code' => 'PCS-01',
            'shop_price_group_id' => $priceGroup->id,
            'is_active' => true,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'is_active' => true,
        ]);
        $order = ShopOrder::create([
            'order_number' => 'ORD-PIECE-001',
            'shop_id' => $shop->id,
            'order_date' => now(),
            'business_date' => now()->toDateString(),
            'delivery_date' => now(),
            'state' => 'approved',
            'status' => 'approved',
            'delivery_status' => 'pending_delivery',
            'order_source' => 'shop_owner',
            'created_by' => $user->id,
        ]);

        $emptyWeightProduct = Product::create([
            'name' => 'Coconut No Weight',
            'sku' => 'COC-NW',
            'category_id' => $category->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);
        $actualWeightProduct = Product::create([
            'name' => 'Coconut Actual Weight',
            'sku' => 'COC-AW',
            'category_id' => $category->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);

        foreach ([$emptyWeightProduct, $actualWeightProduct] as $product) {
            ProductUnit::create([
                'product_id' => $product->id,
                'unit' => 'piece',
                'label' => 'PIECE',
                'conversion_to_base' => 1.0000,
                'is_base' => false,
                'is_orderable' => true,
            ]);

            $batch = StockBatch::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'created_by' => $user->id,
                'reference' => 'BATCH-'.$product->sku,
                'received_at' => now(),
                'total_kg' => 20.0,
                'cost_per_kg' => 10.0,
                'status' => BatchStatus::Sorted,
            ]);

            StockMovement::create([
                'batch_id' => $batch->id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'created_by' => $user->id,
                'grade' => 'A',
                'type' => StockMovementType::In,
                'quantity' => 20.0,
                'cost_per_unit' => 10.0,
                'notes' => 'Initial Stock',
            ]);
        }

        \App\Models\DailyPriceApproval::create([
            'product_id' => $emptyWeightProduct->id,
            'price_group_id' => $priceGroup->id,
            'business_date' => now()->toDateString(),
            'price_date' => now()->toDateString(),
            'purchase_price' => 10.0,
            'price_unit' => 'piece',
            'price_a' => 7.0,
            'price_b' => 8.0,
            'price_c' => 9.0,
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        \App\Models\DailyPriceApproval::create([
            'product_id' => $actualWeightProduct->id,
            'price_group_id' => $priceGroup->id,
            'business_date' => now()->toDateString(),
            'price_date' => now()->toDateString(),
            'purchase_price' => 10.0,
            'price_unit' => 'kg',
            'price_a' => 20.0,
            'price_b' => 22.0,
            'price_c' => 24.0,
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        foreach ([$emptyWeightProduct, $actualWeightProduct] as $product) {
            ShopOrderItem::create([
                'shop_order_id' => $order->id,
                'product_id' => $product->id,
                'product_grade' => 'A',
                'requested_qty' => 5.0,
                'approved_qty' => 5.0,
                'requested_unit' => 'piece',
                'requested_unit_label' => 'PIECE',
                'requested_unit_quantity' => 5.0,
                'requested_unit_conversion_to_base' => 1.0,
                'unit' => 'KG',
                'locked_selling_price' => 7.00,
                'line_total' => 35.00,
                'fulfillment_type' => 'warehouse',
                'sorting_status' => 'allocated',
            ]);
        }

        $this->actingAs($user)->post(route('warehouse.loadout.save', $order), [
            'items' => [
                $actualWeightProduct->id => '5.00',
            ],
            'item_unit_qtys' => [
                $emptyWeightProduct->id => '5.00',
                $actualWeightProduct->id => '5.00',
            ],
        ])->assertRedirect(route('warehouse.loadout.show', $order));

        $emptyWeightItem = ShopOrderItem::where('shop_order_id', $order->id)
            ->where('product_id', $emptyWeightProduct->id)
            ->where('sorting_status', 'loaded')
            ->firstOrFail();
        $actualWeightItem = ShopOrderItem::where('shop_order_id', $order->id)
            ->where('product_id', $actualWeightProduct->id)
            ->where('sorting_status', 'loaded')
            ->firstOrFail();

        $this->assertEquals(5.0, (float) $emptyWeightItem->loaded_order_unit_qty);
        $this->assertNull($emptyWeightItem->actual_weight);
        $this->assertEquals(5.0, (float) $emptyWeightItem->loaded_qty);
        $this->assertEquals(5.0, (float) $actualWeightItem->loaded_order_unit_qty);
        $this->assertEquals(5.0, (float) $actualWeightItem->actual_weight);
        $this->assertEquals(5.0, (float) $actualWeightItem->loaded_qty);

        $this->actingAs($user)->post(route('warehouse.loadout.move-to-delivery', $order))->assertRedirect();

        $invoice = app(ShopInvoiceService::class)->synchronizeOrderInvoice($order->fresh(), $user->id);
        $invoice->load('items');
        $emptyWeightInvoiceItem = $invoice->items->where('product_id', $emptyWeightProduct->id)->first();
        $actualWeightInvoiceItem = $invoice->items->where('product_id', $actualWeightProduct->id)->first();

        $this->assertEquals('piece', $emptyWeightInvoiceItem->price_unit);
        $this->assertEquals(5.0, (float) $emptyWeightInvoiceItem->price_quantity);
        $this->assertEquals(7.0, (float) $emptyWeightInvoiceItem->unit_price);
        $this->assertEquals(35.0, (float) $emptyWeightInvoiceItem->final_line_total);
        $this->assertEquals('kg', $actualWeightInvoiceItem->price_unit);
        $this->assertEquals(5.0, (float) $actualWeightInvoiceItem->price_quantity);
        $this->assertEquals(20.0, (float) $actualWeightInvoiceItem->unit_price);
        $this->assertEquals(100.0, (float) $actualWeightInvoiceItem->final_line_total);
    }

    public function test_full_loadout_after_partial_uses_original_shop_order_quantity(): void
    {
        [$user, $order, $product] = $this->createBasicLoadoutOrder(10.0);

        $this->actingAs($user)->post(route('warehouse.loadout.save', $order), [
            'items' => [
                $product->id => '4.00',
            ],
        ])->assertRedirect(route('warehouse.loadout.show', $order));

        $this->assertEquals(2, ShopOrderItem::where('shop_order_id', $order->id)->where('product_id', $product->id)->count());

        $this->actingAs($user)->post(route('warehouse.loadout.save', $order), [
            'items' => [
                $product->id => '10.00',
            ],
        ])->assertRedirect(route('warehouse.loadout.show', $order));

        $items = ShopOrderItem::where('shop_order_id', $order->id)->where('product_id', $product->id)->get();

        $this->assertCount(1, $items);
        $this->assertEquals('loaded', $items->first()->sorting_status);
        $this->assertEquals(10.0, (float) $items->first()->approved_qty);
        $this->assertEquals(10.0, (float) $items->first()->loaded_qty);
        $this->assertEquals(10.0, (float) StockMovement::where('product_id', $product->id)->where('type', StockMovementType::Out)->sum('quantity'));
    }

    public function test_full_loadout_recovers_from_corrupted_split_remainder_quantity(): void
    {
        [$user, $order, $product] = $this->createBasicLoadoutOrder(5.0);

        $item = ShopOrderItem::where('shop_order_id', $order->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $item->update([
            'requested_qty' => 8388611.0,
            'approved_qty' => 3.0,
            'loaded_qty' => 3.0,
            'actual_weight' => 3.0,
            'requested_unit' => 'kg',
            'requested_unit_quantity' => 5.0,
            'requested_unit_conversion_to_base' => 1.0,
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 8388608.0,
            'approved_qty' => 8388608.0,
            'unit' => 'KG',
            'requested_unit' => 'kg',
            'requested_unit_quantity' => 5.0,
            'requested_unit_conversion_to_base' => 1.0,
            'locked_selling_price' => 20.00,
            'line_total' => 100.00,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);

        $this->actingAs($user)->post(route('warehouse.loadout.save', $order), [
            'items' => [
                $product->id => '5.00',
            ],
        ])->assertRedirect(route('warehouse.loadout.show', $order));

        $items = ShopOrderItem::where('shop_order_id', $order->id)
            ->where('product_id', $product->id)
            ->get();

        $this->assertCount(1, $items);
        $this->assertEquals('loaded', $items->first()->sorting_status);
        $this->assertEquals(5.0, (float) $items->first()->approved_qty);
        $this->assertEquals(5.0, (float) $items->first()->loaded_qty);
    }

    public function test_clear_all_loadout_restores_pending_quantity(): void
    {
        [$user, $order, $product] = $this->createBasicLoadoutOrder(10.0);

        $this->actingAs($user)->post(route('warehouse.loadout.save', $order), [
            'items' => [
                $product->id => '10.00',
            ],
        ])->assertRedirect(route('warehouse.loadout.show', $order));

        $this->actingAs($user)->post(route('warehouse.loadout.save', $order), [
            'items' => [
                $product->id => '0.00',
            ],
        ])->assertRedirect(route('warehouse.loadout.show', $order));

        $item = ShopOrderItem::where('shop_order_id', $order->id)->where('product_id', $product->id)->firstOrFail();

        $this->assertEquals('allocated', $item->sorting_status);
        $this->assertEquals(10.0, (float) $item->approved_qty);
        $this->assertNull($item->loaded_qty);
        $this->assertEquals('pending_delivery', $order->fresh()->delivery_status);
        $this->assertEquals(10.0, (float) StockMovement::where('product_id', $product->id)->where('type', StockMovementType::SaleReversal)->sum('quantity'));
    }

    public function test_loadout_addon_adds_product_to_shop_order_for_normal_loadout(): void
    {
        [$user, $order] = $this->createBasicLoadoutOrder(10.0);

        $category = Category::firstOrFail();
        $addonProduct = Product::create([
            'name' => 'Addon Cucumber',
            'sku' => 'CUC-ADD',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => 12.00,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('warehouse.loadout.show', $order))
            ->assertOk()
            ->assertSee('Addon Cucumber');

        $this->actingAs($user)
            ->post(route('warehouse.loadout.addon.store', $order), [
                'product_id' => $addonProduct->id,
                'quantity' => '3.50',
            ])
            ->assertRedirect(route('warehouse.loadout.show', $order));

        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $addonProduct->id,
            'requested_qty' => '3.50',
            'approved_qty' => '3.50',
            'sorting_status' => 'allocated',
            'fulfillment_type' => 'warehouse',
        ]);
    }

    public function test_delivery_verification_only_shows_fulfilled_loadout_items(): void
    {
        [$admin, $order, $loadedProduct] = $this->createBasicLoadoutOrder(10.0);

        $shopUser = User::factory()->create(['shop_id' => $order->shop_id]);
        $shopUser->syncRoles(['shop']);

        $loadedItem = ShopOrderItem::where('shop_order_id', $order->id)
            ->where('product_id', $loadedProduct->id)
            ->firstOrFail();
        $loadedItem->update([
            'loaded_qty' => 10.0,
            'sorting_status' => 'loaded',
            'is_sorted' => true,
            'sorted_at' => now(),
            'sorted_by' => $admin->id,
        ]);

        $pendingProduct = Product::create([
            'name' => 'Pending Addon Product',
            'sku' => 'PEND-ADD',
            'category_id' => $loadedProduct->category_id,
            'unit' => 'KG',
            'is_active' => true,
        ]);
        $pendingItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $pendingProduct->id,
            'product_grade' => 'A',
            'requested_qty' => 3.0,
            'approved_qty' => 3.0,
            'unit' => 'KG',
            'locked_selling_price' => 20.00,
            'line_total' => 60.00,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);

        $order->update([
            'delivery_status' => 'in_transit',
            'delivery_review_status' => 'not_started',
            'is_allocation_completed' => true,
        ]);

        $invoice = ShopInvoice::create([
            'shop_id' => $order->shop_id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-DELIVERY-ONLY',
            'business_date' => $order->business_date->toDateString(),
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 200.00,
            'final_total' => 200.00,
            'generated_by' => $admin->id,
        ]);
        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $loadedItem->id,
            'product_id' => $loadedProduct->id,
            'product_name' => $loadedProduct->name,
            'unit' => 'KG',
            'price_unit' => 'kg',
            'approved_qty' => 10.0,
            'price_quantity' => 10.0,
            'delivered_qty' => 10.0,
            'delivered_price_quantity' => 10.0,
            'unit_price' => 20.00,
            'line_subtotal' => 200.00,
            'final_line_total' => 200.00,
        ]);

        $this->actingAs($shopUser)
            ->get(route('shop-owner.deliveries.show', $order->order_number))
            ->assertOk()
            ->assertSee($loadedProduct->name)
            ->assertDontSee($pendingProduct->name);

        $this->actingAs($shopUser)
            ->postJson(route('shop-owner.deliveries.items.verify', [$order->order_number, $pendingItem]), [
                'received_qty' => 3.0,
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'This product was not fulfilled in loadout.']);
    }

    public function test_invoice_sync_ignores_unfulfilled_allocated_remainder_rows(): void
    {
        [$user, $order, $product] = $this->createBasicLoadoutOrder(5.0);

        $item = ShopOrderItem::where('shop_order_id', $order->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
        $item->update([
            'requested_qty' => 8388611.0,
            'approved_qty' => 3.0,
            'loaded_qty' => 3.0,
            'actual_weight' => 3.0,
            'requested_unit' => 'kg',
            'requested_unit_quantity' => 5.0,
            'requested_unit_conversion_to_base' => 1.0,
            'locked_selling_price' => 20.00,
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 8388608.0,
            'approved_qty' => 8388608.0,
            'unit' => 'KG',
            'requested_unit' => 'kg',
            'requested_unit_quantity' => 5.0,
            'requested_unit_conversion_to_base' => 1.0,
            'locked_selling_price' => 20.00,
            'line_total' => 100.00,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);

        $invoice = app(ShopInvoiceService::class)->synchronizeOrderInvoice($order->fresh(['items.product', 'shop']), $user->id);
        $invoiceItem = $invoice->items()->where('product_id', $product->id)->firstOrFail();

        $this->assertEquals(3.0, (float) $invoiceItem->approved_qty);
        $this->assertEquals(3.0, (float) $invoiceItem->delivered_qty);
        $this->assertEquals(60.0, (float) $invoice->final_total);
    }

    private function createBasicLoadoutOrder(float $approvedQty): array
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->syncRoles(['admin']);

        $category = Category::create(['name' => 'Vegetables', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Tomato',
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
            'reference' => 'BATCH-TOM-001',
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
            'price_unit' => 'kg',
            'price_a' => 20.0,
            'price_b' => 22.0,
            'price_c' => 24.0,
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
            'order_number' => 'ORD-LOADOUT-ALG',
            'shop_id' => $shop->id,
            'order_date' => now(),
            'business_date' => now()->toDateString(),
            'delivery_date' => now(),
            'state' => 'approved',
            'status' => 'approved',
            'delivery_status' => 'pending_delivery',
            'created_by' => $user->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => $approvedQty,
            'approved_qty' => $approvedQty,
            'unit' => 'KG',
            'locked_selling_price' => 20.00,
            'line_total' => $approvedQty * 20.00,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);

        return [$user, $order, $product];
    }
}
