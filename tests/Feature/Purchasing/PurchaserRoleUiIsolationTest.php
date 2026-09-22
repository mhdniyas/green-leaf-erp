<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserRoleUiIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaserUser;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->purchaserUser = User::factory()->create();
        $this->purchaserUser->assignRole('purchaser');
        $this->purchaserUser->assignRole('purchase');

        $this->warehouse = Warehouse::create([
            'name' => 'Main Vegetable Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Farm Direct Supplier',
            'type' => 'farmer',
            'status' => 'active',
        ]);

        $cat = Category::create(['name' => 'Vegetables', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Carrot Fresh',
            'sku' => 'CAR-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);
    }

    public function test_purchaser_dashboard_route_redirects_to_original_daily_flow(): void
    {
        $response = $this->actingAs($this->purchaserUser)->get(route('purchaser.dashboard'));

        $response->assertRedirect(route('purchaser.daily'));
    }

    public function test_original_purchaser_sidebar_contains_business_day_section(): void
    {
        $response = $this->actingAs($this->purchaserUser)->get(route('purchaser.daily'));

        $response->assertOk()
            ->assertSee('Purchaser')
            ->assertSee('Business Day')
            ->assertSee('Today')
            ->assertSee('Business Days')
            ->assertSee('Daily')
            ->assertSee('B Grade Purchase')
            ->assertSee('Daily Prices')
            ->assertSee('Products')
            ->assertSee('Shop Orders')
            ->assertSee('Daily Carts')
            ->assertSee('Vendor Hub')
            ->assertSee('Finance')
            ->assertSee('Cash');
    }

    public function test_business_days_index_renders_purchaser_blade_and_layout(): void
    {
        $response = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.index', ['view' => 'history']));

        $response->assertOk()
            ->assertViewIs('purchaser.business-days.index')
            ->assertSee('Monthly Control Board')
            ->assertSee('Business Day');
    }

    public function test_business_days_show_renders_purchaser_blade_and_layout(): void
    {
        /** @var PurchaserBusinessDayService $service */
        $service = app(PurchaserBusinessDayService::class);
        $day = $service->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        $response = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.show', $day->uuid));

        $response->assertOk()
            ->assertViewIs('purchaser.business-days.show')
            ->assertSee('Summary')
            ->assertSee('Pending Work');
    }

    public function test_business_days_bills_create_and_edit_render_purchaser_blades(): void
    {
        /** @var PurchaserBusinessDayService $service */
        $service = app(PurchaserBusinessDayService::class);
        $day = $service->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        $createResponse = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.bills.create', $day->uuid));
        $createResponse->assertOk()
            ->assertViewIs('purchaser.business-days.bills.create')
            ->assertSee('Record Purchase Bill');

        // Create a bill
        $grn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $day->id,
            'received_by' => $this->purchaserUser->id,
            'grn_number' => 'GRN-TEST-001',
            'bill_number' => 'BILL-1001',
            'status' => 'approved',
            'received_at' => now(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => 10,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $editResponse = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.bills.edit', [$day->uuid, $grn->id]));
        $editResponse->assertOk()
            ->assertViewIs('purchaser.business-days.bills.edit')
            ->assertSee('Edit Purchase Bill');
    }

    public function test_purchaser_bottom_nav_renders_four_target_items(): void
    {
        $response = $this->actingAs($this->purchaserUser)->get(route('purchaser.daily'));

        $response->assertOk()
            ->assertSee('id="layout-mobile-nav"', false)
            ->assertSee('title="Demand"', false)
            ->assertSee('title="Cart"', false)
            ->assertSee('title="Buy"', false)
            ->assertSee('title="Report"', false)
            ->assertDontSee('title="Bills"', false)
            ->assertSee(route('purchaser.daily'), false)
            ->assertSee(route('purchaser.cart'), false)
            ->assertSee(route('purchaser.bulk-buy'), false)
            ->assertSee(route('purchaser.history'), false);
    }

    public function test_purchaser_bottom_nav_shows_cart_as_active_on_vendors_page(): void
    {
        $response = $this->actingAs($this->purchaserUser)->get(route('purchaser.vendors'));

        $response->assertOk();
        $content = $response->getContent();

        // Cart link must have bg-cyan-500 active state
        $this->assertMatchesRegularExpression('/href="[^"]*purchaser\/cart"[^>]*class="[^"]*bg-cyan-500[^"]*"[^>]*title="Cart"/', $content);
        // Buy link must NOT have bg-cyan-500 active state
        $this->assertMatchesRegularExpression('/href="[^"]*purchaser\/bulk-buy"[^>]*class="[^"]*bg-transparent[^"]*"[^>]*title="Buy"/', $content);
    }

    public function test_purchaser_bottom_nav_shows_buy_as_active_on_bulk_buy_page(): void
    {
        $response = $this->actingAs($this->purchaserUser)->get(route('purchaser.bulk-buy'));

        $response->assertOk();
        $content = $response->getContent();

        // Buy link must have bg-cyan-500 active state
        $this->assertMatchesRegularExpression('/href="[^"]*purchaser\/bulk-buy"[^>]*class="[^"]*bg-cyan-500[^"]*"[^>]*title="Buy"/', $content);
        // Cart link must NOT have bg-cyan-500 active state
        $this->assertMatchesRegularExpression('/href="[^"]*purchaser\/cart"[^>]*class="[^"]*bg-transparent[^"]*"[^>]*title="Cart"/', $content);
    }
}
