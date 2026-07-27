<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductUnitMeasureWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_product_create_stores_multiple_order_units_and_uses_uuid_edit_route(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = Category::factory()->create();

        $this
            ->actingAs($admin)
            ->post(route('inventory.products.store'), [
                'category_id' => $category->id,
                'name' => 'Tomato H',
                'sku' => 'TOMATO-H',
                'unit' => 'kg',
                'buffer_qty' => 0,
                'carryover_enabled' => '0',
                'units' => [
                    ['unit' => 'kg', 'conversion_to_base' => 1, 'is_base' => '1', 'is_orderable' => '1', 'label' => 'KG'],
                    ['unit' => 'box', 'conversion_to_base' => 12, 'is_base' => '0', 'is_orderable' => '1', 'label' => 'BOX'],
                    ['unit' => 'piece', 'conversion_to_base' => 0.25, 'is_base' => '0', 'is_orderable' => '1', 'label' => 'PCS'],
                ],
            ])
            ->assertRedirect(route('inventory.products.index'));

        $product = Product::query()->where('sku', 'TOMATO-H')->with('orderUnits')->sole();

        $this->assertNotNull($product->public_uuid);
        $this->assertSame($product->public_uuid, $product->getRouteKey());
        $this->assertSame(3, $product->orderUnits->count());
        $this->assertSame(12.0, (float) $product->orderUnits->firstWhere('unit', 'box')->conversion_to_base);
        $this->assertSame(0.25, (float) $product->orderUnits->firstWhere('unit', 'piece')->conversion_to_base);

        $this
            ->actingAs($admin)
            ->get(route('inventory.products.edit', $product))
            ->assertOk()
            ->assertSeeText('Units & Measures');

        $this
            ->actingAs($admin)
            ->get('/inventory/products/'.$product->id.'/edit')
            ->assertNotFound();
    }

    public function test_shop_owner_box_order_is_saved_as_base_quantity_for_purchaser_flow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Asia/Kolkata'));

        $shop = Shop::factory()->create();
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');
        $product = Product::factory()->create([
            'name' => 'Box Tomato',
            'sku' => 'BOX-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'BOX',
            'conversion_to_base' => 12,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $this
            ->actingAs($shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [$product->sku => 3],
                'item_units' => [$product->sku => 'box'],
            ])
            ->assertRedirect();

        $item = ShopOrder::query()->with('items.product')->sole()->items->sole();

        $this->assertSame('kg', $item->unit);
        $this->assertSame(36.0, (float) $item->requested_qty);
        $this->assertSame('box', $item->requested_unit);
        $this->assertSame(3.0, (float) $item->requested_unit_quantity);
        $this->assertSame(12.0, (float) $item->requested_unit_conversion_to_base);
    }
}
