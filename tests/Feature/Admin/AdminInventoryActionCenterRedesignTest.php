<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryActionCenterRedesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Main Vegetable Hub',
            'code' => 'MAIN-HUB',
            'is_active' => true,
        ]);

        $this->productA = Product::factory()->create([
            'name' => 'Tomato Local',
            'sku' => 'TOM-LOC',
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->productB = Product::factory()->create([
            'name' => 'Onion Red',
            'sku' => 'ONI-RED',
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_top_summary_renders_exactly_four_cards(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory');

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary): bool {
            return array_key_exists('advance_pending_count', $summary)
                && array_key_exists('bills_pending_count', $summary)
                && array_key_exists('ready_to_match_count', $summary)
                && array_key_exists('unbilled_loadout_count', $summary);
        });

        // Assert 4 top card titles are rendered
        $response->assertSee('Advance Pending');
        $response->assertSee('Bills Pending');
        $response->assertSee('Ready to Match');
        $response->assertSee('Loadout Without Bill');
    }

    public function test_five_tabs_rendered_in_navigation(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory');

        $response->assertOk();
        $content = $response->getContent();

        // Check tabs appear in navigation bar
        $this->assertStringContainsString('Advance Pending', $content);
        $this->assertStringContainsString('Bills &amp; Match', $content);
        $this->assertStringContainsString('Loadout Without Bill', $content);
        $this->assertStringContainsString('Inventory', $content);
        $this->assertStringContainsString('Physical Check', $content);

        // Ensure old tabs are not in main navigation bar
        $this->assertStringNotContainsString('data-tab="shop_returns"', $content);
        $this->assertStringNotContainsString('data-tab="damage"', $content);
        $this->assertStringNotContainsString('data-tab="unit_differences"', $content);
    }

    public function test_advance_pending_tab_loads_correct_data(): void
    {
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'bill_status' => 'bill_pending',
            'grn_number' => 'GRN-ADV-999',
            'received_at' => now()->toDateString().' 08:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->productA->id,
            'received_qty' => 120.0,
            'received_unit' => 'KG',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=advance_pending');

        $response->assertOk()
            ->assertSee('GRN-ADV-999')
            ->assertSee('Tomato Local')
            ->assertSee('120.00');
    }

    public function test_bills_match_tab_renders_pending_bills_and_ready_to_match(): void
    {
        $po = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'po_number' => 'PO-TEST-101',
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->productB->id,
            'quantity' => 50,
            'unit_price' => 20,
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'standard',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'grn_number' => 'GRN-BILL-101',
            'received_at' => now()->toDateString().' 09:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->productB->id,
            'received_qty' => 50.0,
            'received_unit' => 'KG',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=bills_match');

        $response->assertOk();
        $content = $response->getContent();

        // Verify NO_ADVANCE is not rendered in error badges or skipped items
        $this->assertStringNotContainsString('NO_ADVANCE', $content);
    }

    public function test_loadout_without_bill_shows_dispatched_items_without_bill_and_advance(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Outlet Downtown',
            'code' => 'OUT-DT',
        ]);

        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'order_number' => 'ORD-9001',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $this->productA->id,
            'requested_qty' => 45.0,
            'approved_qty' => 45.0,
            'unit' => 'KG',
            'sorting_status' => 'loaded',
            'loaded_qty' => 45.0,
            'actual_weight' => 45.0,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=loadout_without_bill');

        $response->assertOk()
            ->assertSee('Outlet Downtown')
            ->assertSee('Tomato Local')
            ->assertSee('45.00')
            ->assertSee('No Bill Created');
    }

    public function test_shop_wise_grouping_groups_products_under_shop_cards_and_separates_shops(): void
    {
        $shop1 = Shop::factory()->create(['name' => 'Sana Outlet', 'code' => 'AV_SANA']);
        $shop2 = Shop::factory()->create(['name' => 'GM Midland', 'code' => 'AV_GM_MIDLAND']);

        $order1 = ShopOrder::factory()->create([
            'shop_id' => $shop1->id,
            'business_date' => now()->toDateString(),
            'order_number' => 'ORD-SANA-01',
        ]);

        $order2 = ShopOrder::factory()->create([
            'shop_id' => $shop2->id,
            'business_date' => now()->toDateString(),
            'order_number' => 'ORD-GMM-01',
        ]);

        // Shop 1 has 2 items
        ShopOrderItem::query()->create([
            'shop_order_id' => $order1->id,
            'product_id' => $this->productA->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'sorting_status' => 'loaded',
            'loaded_qty' => 10.0,
            'actual_weight' => 10.0,
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order1->id,
            'product_id' => $this->productB->id,
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'unit' => 'KG',
            'sorting_status' => 'loaded',
            'loaded_qty' => 5.0,
            'actual_weight' => 5.0,
        ]);

        // Shop 2 has 1 item
        ShopOrderItem::query()->create([
            'shop_order_id' => $order2->id,
            'product_id' => $this->productA->id,
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'unit' => 'KG',
            'sorting_status' => 'loaded',
            'loaded_qty' => 20.0,
            'actual_weight' => 20.0,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=loadout_without_bill');

        $response->assertOk();
        $response->assertSee('Sana Outlet');
        $response->assertSee('AV_SANA');
        $response->assertSee('2 Products Without Bill');
        $response->assertSee('GM Midland');
        $response->assertSee('AV_GM_MIDLAND');
        $response->assertSee('1 Product Without Bill');

        $response->assertViewHas('unbilledLoadoutsGrouped', function (array $grouped): bool {
            $today = now()->toDateString();
            $dateGroup = $grouped[$today] ?? null;
            if (! $dateGroup) {
                return false;
            }

            return count($dateGroup['shops']) === 2;
        });
    }

    public function test_same_normalized_unit_stays_one_to_one_for_kg_piece_and_box(): void
    {
        $productPiece = Product::factory()->create([
            'name' => 'Coconut',
            'sku' => 'COC-01',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $productBox = Product::factory()->create([
            'name' => 'Apple Washington',
            'sku' => 'APP-BOX',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $shop = Shop::factory()->create(['name' => 'Highland Store', 'code' => 'HL-01']);
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'order_number' => 'ORD-HL-01',
        ]);

        // kg item
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->productA->id,
            'requested_qty' => 3.0,
            'approved_qty' => 3.0,
            'unit' => 'kg',
            'sorting_status' => 'loaded',
            'loaded_qty' => 3.0,
            'actual_weight' => 3.0,
        ]);

        // piece item (piece -> piece 1:1)
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $productPiece->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'piece',
            'sorting_status' => 'loaded',
            'loaded_qty' => 10.0,
        ]);

        // box item (box -> box 1:1)
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $productBox->id,
            'requested_qty' => 4.0,
            'approved_qty' => 4.0,
            'unit' => 'box',
            'sorting_status' => 'loaded',
            'loaded_qty' => 4.0,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=loadout_without_bill');

        $response->assertOk();
        $response->assertSee('3.00');
        $response->assertSee('10.00');
        $response->assertSee('4.00');
        // Ensure no false warning is rendered for same-unit items
        $response->assertDontSee('Unit conversion not configured');
    }

    public function test_different_unit_with_configured_conversion_converts_correctly(): void
    {
        $product = Product::factory()->create([
            'name' => 'Orange Sweet',
            'sku' => 'ORG-SWT',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        // Configure unit conversion: 1 box = 15.0 kg
        ProductUnit::create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'Box',
            'conversion_to_base' => 15.0,
            'is_base' => false,
            'is_orderable' => true,
            'sort_order' => 1,
        ]);

        $shop = Shop::factory()->create(['name' => 'Fresh Mart', 'code' => 'FM-01']);
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'order_number' => 'ORD-FM-01',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 2.0,
            'approved_qty' => 2.0,
            'unit' => 'box',
            'requested_unit' => 'box',
            'sorting_status' => 'loaded',
            'loaded_qty' => 2.0,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=loadout_without_bill');

        $response->assertOk();
        $response->assertSee('2.00');
        $response->assertSee('box');
        $response->assertSee('≈ 30.00 kg');
        $response->assertDontSee('Unit conversion not configured');
    }

    public function test_different_unit_without_conversion_preserves_original_unit_and_shows_warning(): void
    {
        $product = Product::factory()->create([
            'name' => 'Dragon Fruit',
            'sku' => 'DF-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $shop = Shop::factory()->create(['name' => 'Exotic Mart', 'code' => 'EX-01']);
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'order_number' => 'ORD-EX-01',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'unit' => 'crate',
            'requested_unit' => 'crate',
            'sorting_status' => 'loaded',
            'loaded_qty' => 5.0,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=loadout_without_bill');

        $response->assertOk();
        $response->assertSee('5.00');
        $response->assertSee('crate');
        $response->assertSee('Unit conversion not configured');
    }

    public function test_unlike_units_are_never_summed_together_in_card_totals(): void
    {
        $productPiece = Product::factory()->create([
            'name' => 'Pineapple',
            'sku' => 'PIN-01',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $shop = Shop::factory()->create(['name' => 'Mega Shop', 'code' => 'MS-01']);
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'order_number' => 'ORD-MS-01',
        ]);

        // 10 kg
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->productA->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
            'sorting_status' => 'loaded',
            'loaded_qty' => 10.0,
            'actual_weight' => 10.0,
        ]);

        // 5 piece
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $productPiece->id,
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'unit' => 'piece',
            'sorting_status' => 'loaded',
            'loaded_qty' => 5.0,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=loadout_without_bill');

        $response->assertOk();
        $response->assertViewHas('unbilledLoadoutsGrouped', function (array $grouped): bool {
            $today = now()->toDateString();
            $shop = $grouped[$today]['shops'][array_key_first($grouped[$today]['shops'])];
            $unitTotals = $shop['unit_totals'];

            // kg = 10, piece = 5
            return ($unitTotals['kg'] ?? 0.0) === 10.0
                && ($unitTotals['piece'] ?? 0.0) === 5.0
                && count($unitTotals) === 2;
        });

        // 10 kg + 5 piece should NOT produce a merged 15.00 total
        $response->assertSee('10.00');
        $response->assertSee('5.00');
        $response->assertSee('2 Products Without Bill');
    }

    public function test_inventory_tab_renders_and_preserves_sorting_and_search(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=inventory&search=Tomato');

        $response->assertOk()
            ->assertSee('Current Qty')
            ->assertSee('With Bill')
            ->assertSee('Without Bill')
            ->assertSee('Stock Deficit')
            ->assertSee('Tomato Local');
    }

    public function test_physical_check_tab_renders_adjustment_and_damage_options(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=physical_check');

        $response->assertOk()
            ->assertSee('System Qty')
            ->assertSee('Physical Qty')
            ->assertSee('Difference')
            ->assertSee('Update Inventory');
    }
}
