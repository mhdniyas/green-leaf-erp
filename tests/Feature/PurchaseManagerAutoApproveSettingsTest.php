<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseManagerAutoApproveSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_purchase_manager_can_enable_auto_approval_and_shop_order_is_approved_immediately(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $purchaseManager = User::query()->create([
            'name' => 'Purchase Manager',
            'email' => 'purchase.manager@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $purchaseManager->assignRole(Role::findByName('purchase'));

        $shop = Shop::factory()->create(['name' => 'Auto Shop']);
        $shopUser = User::query()->create([
            'name' => 'Auto Shop Owner',
            'email' => 'auto.shop@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'shop_id' => $shop->id,
        ]);
        $shopUser->assignRole(Role::findByName('shop'));

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Auto Tomato',
            'sku' => 'AUTO-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this
            ->actingAs($purchaseManager)
            ->post(route('business-day-settings.auto-approve.update'), [
                'auto_approve_shop_orders' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('1', BusinessSetting::query()
            ->where('key', 'auto_approve_shop_orders')
            ->value('value'));

        $this
            ->actingAs($shopUser)
            ->post(route('requisitions.store'), [
                'items' => [
                    $product->sku => 7.5,
                ],
            ])
            ->assertRedirect();

        $order = ShopOrder::query()
            ->with(['items', 'shop'])
            ->whereBelongsTo($shop)
            ->firstOrFail();

        $this->assertSame('approved', $order->state);
        $this->assertSame(PurchaserBusinessDayService::AUTO_APPROVE_MANAGER_NOTE, $order->manager_note);
        $this->assertSame(7.5, (float) $order->items->first()->requested_qty);
        $this->assertSame(7.5, (float) $order->items->first()->approved_qty);

        $this
            ->actingAs($purchaseManager)
            ->get(route('requisitions.approved_board', ['date' => $order->business_date->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('Auto Shop')
            ->assertSee('Automatic Approval')
            ->assertSee('1 shop order');
    }
}
