<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PurchaserShopOrderSurfaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        view()->share('errors', new ViewErrorBag);
        Carbon::setTestNow(Carbon::parse('2026-07-28 10:00:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_purchaser_can_view_shop_orders_read_only(): void
    {
        $purchaser = $this->purchaser();
        $shop = Shop::factory()->create(['name' => 'Mobile Shop']);
        $product = Product::factory()->create(['name' => 'Apple Premium', 'sku' => 'APPLE-001', 'unit' => 'kg']);
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-28',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 12,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        $this
            ->actingAs($purchaser)
            ->get(route('purchaser.shop-orders.index', ['date' => '2026-07-28', 'search' => 'Apple']))
            ->assertOk()
            ->assertSee('Shop orders')
            ->assertSee('Mobile Shop')
            ->assertSee($order->order_number)
            ->assertSee('Read-only', false)
            ->assertDontSee('Approve</button>', false);

        $this
            ->actingAs($purchaser)
            ->get(route('purchaser.shop-orders.show', $order->order_number))
            ->assertOk()
            ->assertSee('Read Only Shop Order')
            ->assertSee('Apple Premium')
            ->assertSee('name="date"', false)
            ->assertSee('value="2026-07-28"', false)
            ->assertSee('Requested')
            ->assertSee('Approved')
            ->assertSee('Rejected')
            ->assertSee('Delivered')
            ->assertSee('10.00')
            ->assertDontSee('Status')
            ->assertDontSee('Created By')
            ->assertDontSee('rounded-2xl border border-slate-200 bg-slate-50 p-3', false)
            ->assertDontSee('Approve</button>', false);
    }

    public function test_purchaser_can_create_add_on_direct_purchase_demand(): void
    {
        $purchaser = $this->purchaser();
        $product = Product::factory()->create(['name' => 'Office Tape', 'sku' => 'OFFICE-TAPE', 'unit' => 'pcs']);

        $this
            ->actingAs($purchaser)
            ->post(route('purchaser.add-ons.store'), [
                'business_date' => '2026-07-28',
                'items' => [
                    $product->sku => '5',
                ],
            ])
            ->assertRedirect(route('purchaser.daily', ['date' => '2026-07-28']));

        $order = ShopOrder::query()
            ->whereNull('shop_id')
            ->where('order_source', 'admin_direct_purchase')
            ->whereDate('business_date', '2026-07-28')
            ->firstOrFail();

        $this->assertSame('approved', $order->state);
        $this->assertSame('Purchaser Add-on', $order->manager_note);
        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 5,
            'approved_qty' => 5,
            'notes' => 'Purchaser Add-on',
        ]);
    }

    public function test_admin_direct_purchase_hides_accounting_mobile_bottom_nav(): void
    {
        $adminPurchaser = $this->purchaser();
        $permission = Permission::findOrCreate('accounting.dashboard.view', 'web');
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->givePermissionTo($permission);
        $adminPurchaser->assignRole($adminRole);

        $this
            ->actingAs($adminPurchaser)
            ->get(route('admin.accounting.purchasers.direct-purchase.create', ['date' => '2026-07-28']))
            ->assertOk()
            ->assertSee('Direct purchase demand')
            ->assertDontSee('Buy</a>', false);
    }

    private function purchaser(): User
    {
        $role = Role::findOrCreate('purchaser', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
