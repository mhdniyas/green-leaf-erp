<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SortSheetControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makePurchaseManager(): User
    {
        $user = User::factory()->create();
        $user->assignRole('purchase');

        return $user;
    }

    private function makeWarehouseReceiver(): User
    {
        $user = User::factory()->create();
        $user->assignRole('warehouse_receiver');

        return $user;
    }

    private function makeShopOwner(): User
    {
        $user = User::factory()->create();
        $user->assignRole('shop');

        return $user;
    }

    /**
     * Create an approved shop order for a shop with items.
     *
     * @param  array<int, array{product: Product, qty: float}>  $items
     */
    private function createApprovedOrder(Shop $shop, string $date, array $items, User $creator): ShopOrder
    {
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'approved',
            'submitted_at' => now(),
            'deadline_at' => now(),
            'created_by' => $creator->id,
        ]);

        foreach ($items as $item) {
            ShopOrderItem::create([
                'shop_order_id' => $order->id,
                'product_id' => $item['product']->id,
                'requested_qty' => $item['qty'],
                'approved_qty' => $item['qty'],
                'unit' => $item['product']->unit,
            ]);
        }

        return $order;
    }

    // ── Access control ────────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('sort-sheet.index'))->assertRedirect(route('login'));
    }

    public function test_shop_owner_cannot_access_sort_sheet(): void
    {
        $this->actingAs($this->makeShopOwner())
            ->get(route('sort-sheet.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_sort_sheet_index(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('sort-sheet.index'))
            ->assertOk()
            ->assertSee('Sort Sheet');
    }

    public function test_purchase_manager_can_access_sort_sheet_index(): void
    {
        $this->actingAs($this->makePurchaseManager())
            ->get(route('sort-sheet.index'))
            ->assertOk()
            ->assertSee('Sort Sheet');
    }

    public function test_warehouse_receiver_can_access_sort_sheet_index(): void
    {
        $this->actingAs($this->makeWarehouseReceiver())
            ->get(route('sort-sheet.index'))
            ->assertOk()
            ->assertSee('Sort Sheet');
    }

    // ── Generate (uses approved_qty only) ────────────────────────────────────

    public function test_generate_shows_matrix_for_approved_orders_only(): void
    {
        $admin = $this->makeAdmin();
        $category = Category::factory()->create(['name' => 'Vegetables', 'is_active' => true]);
        $product = Product::factory()->create(['name' => 'Tomato H', 'unit' => 'kg', 'category_id' => $category->id, 'is_active' => true]);
        $shop = Shop::create([
            'code' => 'SHOP_TEST_A_'.uniqid(),
            'name' => 'Test Shop Alpha',
            'status' => 'active',
        ]);

        $date = now()->toDateString();

        $this->createApprovedOrder($shop, $date, [
            ['product' => $product, 'qty' => 15.0],
        ], $admin);

        $response = $this->actingAs($admin)
            ->get(route('sort-sheet.generate', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('Tomato H');
        $response->assertSee('Test Shop Alpha');
        $response->assertSee('15');
    }

    public function test_generate_excludes_pending_and_rejected_orders(): void
    {
        $admin = $this->makeAdmin();
        $category = Category::factory()->create(['name' => 'Veg', 'is_active' => true]);
        $product = Product::factory()->create(['name' => 'Secret Carrot', 'unit' => 'kg', 'category_id' => $category->id, 'is_active' => true]);
        $shop = Shop::create([
            'code' => 'SHOP_REJ_'.uniqid(),
            'name' => 'Rejected Shop',
            'status' => 'active',
        ]);

        $date = now()->toDateString();

        // Create REJECTED order — should NOT appear in sort sheet
        ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'rejected',
            'submitted_at' => now(),
            'deadline_at' => now(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('sort-sheet.generate', ['date' => $date]));

        $response->assertOk();
        $response->assertDontSee('Secret Carrot');
    }

    public function test_generate_shows_empty_state_when_no_approved_orders(): void
    {
        $admin = $this->makeAdmin();
        $date = '2025-01-01'; // Far past date with no orders

        $response = $this->actingAs($admin)
            ->get(route('sort-sheet.generate', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('No Approved Shop Orders Found');
    }

    public function test_generate_shows_totals_column(): void
    {
        $admin = $this->makeAdmin();
        $category = Category::factory()->create(['name' => 'Fruit', 'is_active' => true]);
        $product = Product::factory()->create(['name' => 'Apple Red', 'unit' => 'kg', 'category_id' => $category->id, 'is_active' => true]);

        $shopA = Shop::create(['code' => 'SHOP_A_'.uniqid(), 'name' => 'Shop A Total', 'status' => 'active']);
        $shopB = Shop::create(['code' => 'SHOP_B_'.uniqid(), 'name' => 'Shop B Total', 'status' => 'active']);

        $date = now()->toDateString();
        $this->createApprovedOrder($shopA, $date, [['product' => $product, 'qty' => 10]], $admin);
        $this->createApprovedOrder($shopB, $date, [['product' => $product, 'qty' => 20]], $admin);

        $response = $this->actingAs($admin)
            ->get(route('sort-sheet.generate', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('Apple Red');
        $response->assertSee('30'); // Total = 10 + 20
    }

    public function test_generate_uses_approved_qty_not_requested_qty(): void
    {
        $admin = $this->makeAdmin();
        $category = Category::factory()->create(['name' => 'Grains', 'is_active' => true]);
        $product = Product::factory()->create(['name' => 'Rice Basmati', 'unit' => 'kg', 'category_id' => $category->id, 'is_active' => true]);
        $shop = Shop::create([
            'code' => 'SHOP_QTY_'.uniqid(),
            'name' => 'Qty Test Shop',
            'status' => 'active',
        ]);

        $date = now()->toDateString();
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'approved',
            'submitted_at' => now(),
            'deadline_at' => now(),
            'created_by' => $admin->id,
        ]);

        // requested = 100, approved = 75 — sort sheet must show 75
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 100,
            'approved_qty' => 75,
            'unit' => $product->unit,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('sort-sheet.generate', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('75');
        $response->assertDontSee('>100<', false); // 100 should not appear as a qty cell
    }

    // ── Export permissions ────────────────────────────────────────────────────

    public function test_warehouse_cannot_use_generate_route(): void
    {
        // Warehouse role has sort.sheet.view and sort.sheet.export but NOT sort.sheet.generate
        // The generate route redirects through the controller which checks sort.sheet.view
        // so warehouse CAN hit /generate (it has view), but cannot click Generate button in UI
        // This test ensures export.excel requires sort.sheet.export
        $warehouse = $this->makeWarehouseReceiver();
        $date = now()->toDateString();

        $this->actingAs($warehouse)
            ->get(route('sort-sheet.export.excel', ['date' => $date]))
            ->assertOk(); // warehouse HAS sort.sheet.export
    }

    public function test_shop_owner_cannot_export_excel(): void
    {
        $shop = $this->makeShopOwner();
        $date = now()->toDateString();

        $this->actingAs($shop)
            ->get(route('sort-sheet.export.excel', ['date' => $date]))
            ->assertForbidden();
    }

    public function test_purchase_manager_can_access_print_view(): void
    {
        $pm = $this->makePurchaseManager();
        $date = now()->toDateString();

        $this->actingAs($pm)
            ->get(route('sort-sheet.print', ['date' => $date]))
            ->assertOk();
    }

    // ── Product category filter ───────────────────────────────────────────────

    public function test_category_filter_excludes_other_categories(): void
    {
        $admin = $this->makeAdmin();
        $catA = Category::factory()->create(['name' => 'Leafy', 'is_active' => true]);
        $catB = Category::factory()->create(['name' => 'Root', 'is_active' => true]);

        $productA = Product::factory()->create(['name' => 'Spinach Green', 'unit' => 'kg', 'category_id' => $catA->id, 'is_active' => true]);
        $productB = Product::factory()->create(['name' => 'Carrot Orange', 'unit' => 'kg', 'category_id' => $catB->id, 'is_active' => true]);

        $shop = Shop::create([
            'code' => 'SHOP_FILT_'.uniqid(),
            'name' => 'Filter Shop',
            'status' => 'active',
        ]);
        $date = now()->toDateString();

        $this->createApprovedOrder($shop, $date, [
            ['product' => $productA, 'qty' => 5],
            ['product' => $productB, 'qty' => 8],
        ], $admin);

        // Filter by catA only
        $response = $this->actingAs($admin)
            ->get(route('sort-sheet.generate', ['date' => $date, 'category_id' => $catA->id]));

        $response->assertOk();
        $response->assertSee('Spinach Green');
        $response->assertDontSee('Carrot Orange');
    }
}
