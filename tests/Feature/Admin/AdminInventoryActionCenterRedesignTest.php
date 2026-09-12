<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
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
