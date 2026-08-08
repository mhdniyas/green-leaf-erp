<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSpecialPriceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_bill_price_approval_page_renders_for_purchaser_users(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['purchase']);

        $group = ShopPriceGroup::create([
            'name' => 'A',
            'default_margin_percent' => 10,
            'is_active' => true,
        ]);

        Shop::factory()->create([
            'shop_price_group_id' => $group->id,
        ]);

        Product::factory()->create([
            'category_id' => Category::create(['name' => 'Vegetables', 'is_active' => true])->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('purchaser.bill-prices.index', ['date' => '2026-08-07']));

        $response->assertStatus(200);
        $response->assertSee('Approval - Bill');
        $response->assertSee('Shop Bills');
    }

    public function test_bill_price_approval_page_shows_selected_shop_bill_with_category_filter(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['purchaser']);

        $vegetables = Category::create(['name' => 'Vegetables', 'is_active' => true]);
        $fruits = Category::create(['name' => 'Fruits', 'is_active' => true]);

        $tomato = Product::factory()->create([
            'name' => 'Tomato Bill Item',
            'category_id' => $vegetables->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $apple = Product::factory()->create([
            'name' => 'Apple Bill Item',
            'category_id' => $fruits->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $shop = Shop::factory()->create(['name' => 'Morning Market Shop']);
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-07',
            'created_by' => $user->id,
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260807-TEST',
            'business_date' => '2026-08-07',
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 130,
            'final_total' => 130,
            'paid_amount' => 0,
            'balance_amount' => 130,
            'generated_by' => $user->id,
        ]);

        ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $tomato->id,
            'product_name' => $tomato->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 2,
            'price_quantity' => 2,
            'delivered_qty' => 2,
            'delivered_price_quantity' => 2,
            'unit_price' => 20,
            'line_subtotal' => 40,
            'final_line_total' => 40,
        ]);

        ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $apple->id,
            'product_name' => $apple->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 3,
            'price_quantity' => 3,
            'delivered_qty' => 3,
            'delivered_price_quantity' => 3,
            'unit_price' => 30,
            'line_subtotal' => 90,
            'final_line_total' => 90,
        ]);

        $indexResponse = $this->actingAs($user)->get(route('purchaser.bill-prices.index', [
            'date' => '2026-08-07',
        ]));

        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('SINV-20260807-TEST');
        $indexResponse->assertSee(route('purchaser.bill-prices.show', $invoice), false);

        $response = $this->actingAs($user)->get(route('purchaser.bill-prices.show', [
            'invoice' => $invoice,
            'category_id' => $vegetables->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Bill Invoice');
        $response->assertSee('SINV-20260807-TEST');
        $response->assertSee('Morning Market Shop');
        $response->assertSee('<p class="truncate text-sm font-black text-slate-950">Tomato Bill Item</p>', false);
        $response->assertDontSee('<p class="truncate text-sm font-black text-slate-950">Apple Bill Item</p>', false);
        $response->assertSee(route('purchaser.bill-prices.invoice-prices.update', $invoice), false);
    }

    public function test_purchaser_can_save_special_prices_from_selected_shop_bill(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['purchaser']);

        $category = Category::create(['name' => 'Vegetables', 'is_active' => true]);
        $product = Product::factory()->create([
            'name' => 'Bill Tomato Special',
            'category_id' => $category->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);
        $shop = Shop::factory()->create();
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-07',
            'created_by' => $user->id,
        ]);
        $invoice = ShopInvoice::query()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260807-SAVE',
            'business_date' => '2026-08-07',
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 40,
            'final_total' => 40,
            'paid_amount' => 0,
            'balance_amount' => 40,
            'generated_by' => $user->id,
        ]);
        $item = ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 2,
            'price_quantity' => 2,
            'delivered_qty' => 2,
            'delivered_price_quantity' => 2,
            'unit_price' => 20,
            'line_subtotal' => 40,
            'final_line_total' => 40,
        ]);

        $response = $this->actingAs($user)->postJson(route('purchaser.bill-prices.invoice-prices.update', $invoice), [
            'category_id' => $category->id,
            'prices' => [
                $item->id => [
                    'product_id' => $product->id,
                    'selling_price' => '24.50',
                    'price_unit' => 'kg',
                    'reason' => 'Bill correction',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('updated_by_name', $user->name);
        $response->assertJsonPath('updated', 1);

        $this->assertDatabaseHas('shop_daily_product_prices', [
            'business_date' => '2026-08-07 00:00:00',
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'selling_price' => 24.50,
            'status' => 'approved',
            'approved_by' => $user->id,
            'reason' => 'Bill correction',
        ]);
    }

    public function test_approved_special_price_overrides_daily_price_for_matching_shop_and_date(): void
    {
        $group = ShopPriceGroup::create([
            'name' => 'A',
            'default_margin_percent' => 10,
            'is_active' => true,
        ]);

        $shop = Shop::factory()->create([
            'shop_price_group_id' => $group->id,
        ]);

        $product = Product::factory()->create([
            'category_id' => Category::create(['name' => 'Vegetables', 'is_active' => true])->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-08-07',
            'purchase_price' => 18.00,
            'price_unit' => 'kg',
            'price_a' => 25.00,
            'price_b' => 22.50,
            'price_c' => 20.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $creator = User::factory()->create();

        ShopDailyProductPrice::create([
            'business_date' => '2026-08-07',
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'selling_price' => 31.25,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $creator->id,
            'approved_by' => $creator->id,
            'approved_at' => now(),
        ]);

        $resolved = app(ApprovedDailyPriceResolver::class)->resolve($product, $shop, '2026-08-07');

        $this->assertSame('special', $resolved['source']);
        $this->assertSame(31.25, $resolved['price']);
        $this->assertNotNull($resolved['special_price']);
    }

    public function test_resolver_falls_back_to_daily_price_when_no_special_price_exists(): void
    {
        $group = ShopPriceGroup::create([
            'name' => 'A',
            'default_margin_percent' => 10,
            'is_active' => true,
        ]);

        $shop = Shop::factory()->create([
            'shop_price_group_id' => $group->id,
        ]);

        $product = Product::factory()->create([
            'category_id' => Category::create(['name' => 'Vegetables', 'is_active' => true])->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-08-07',
            'purchase_price' => 18.00,
            'price_unit' => 'kg',
            'price_a' => 25.00,
            'price_b' => 22.50,
            'price_c' => 20.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $resolved = app(ApprovedDailyPriceResolver::class)->resolve($product, $shop, '2026-08-07');

        $this->assertSame('normal', $resolved['source']);
        $this->assertSame(25.00, $resolved['price']);
        $this->assertNull($resolved['special_price']);
    }

    public function test_special_price_does_not_leak_to_other_shops(): void
    {
        $group = ShopPriceGroup::create([
            'name' => 'A',
            'default_margin_percent' => 10,
            'is_active' => true,
        ]);

        $shopA = Shop::factory()->create([
            'shop_price_group_id' => $group->id,
        ]);
        $shopB = Shop::factory()->create([
            'shop_price_group_id' => $group->id,
        ]);

        $product = Product::factory()->create([
            'category_id' => Category::create(['name' => 'Vegetables', 'is_active' => true])->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-08-07',
            'purchase_price' => 18.00,
            'price_unit' => 'kg',
            'price_a' => 25.00,
            'price_b' => 22.50,
            'price_c' => 20.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $creator = User::factory()->create();

        ShopDailyProductPrice::create([
            'business_date' => '2026-08-07',
            'shop_id' => $shopA->id,
            'product_id' => $product->id,
            'selling_price' => 31.25,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $creator->id,
            'approved_by' => $creator->id,
            'approved_at' => now(),
        ]);

        $resolved = app(ApprovedDailyPriceResolver::class)->resolve($product, $shopB, '2026-08-07');

        $this->assertSame('normal', $resolved['source']);
        $this->assertSame(25.00, $resolved['price']);
    }
}
