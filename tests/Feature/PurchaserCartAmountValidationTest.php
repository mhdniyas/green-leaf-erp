<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaserCartItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserCartAmountValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_buy_rejects_zero_unit_price(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $purchaser = $this->purchaserUser();
        $product = $this->activeProduct();

        $this
            ->actingAs($purchaser)
            ->from(route('purchaser.bulk-buy.details', [
                'date' => today()->toDateString(),
                'products' => [$product->id],
            ]))
            ->post(route('purchaser.carts.bulk-store'), [
                'business_date' => today()->toDateString(),
                'product_ids' => [$product->id],
                'items' => [
                    $product->id => [
                        'quantity' => 5,
                        'unit_price' => 0,
                    ],
                ],
            ])
            ->assertSessionHasErrors("items.{$product->id}.unit_price");

        $this->assertSame(0, PurchaserCartItem::query()->count());
    }

    public function test_bulk_buy_adds_only_rows_with_quantity_above_zero(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $purchaser = $this->purchaserUser();
        $selectedProduct = $this->activeProduct();
        $draftOnlyProduct = Product::factory()->create([
            'category_id' => $selectedProduct->category_id,
            'name' => 'Draft Onion',
            'sku' => 'DRAFT-ONION',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this
            ->actingAs($purchaser)
            ->post(route('purchaser.carts.bulk-store'), [
                'business_date' => today()->toDateString(),
                'product_ids' => [$selectedProduct->id, $draftOnlyProduct->id],
                'items' => [
                    $selectedProduct->id => [
                        'quantity' => 5,
                        'unit_price' => 12.5,
                    ],
                    $draftOnlyProduct->id => [
                        'quantity' => 0,
                        'unit_price' => null,
                    ],
                ],
            ])
            ->assertRedirect(route('purchaser.vendors', ['date' => today()->toDateString()]))
            ->assertSessionHas('success', '1 product added to cart.')
            ->assertSessionHas('cart_success_actions', true);

        $this->assertSame(1, PurchaserCartItem::query()->count());
        $this->assertDatabaseHas('purchaser_cart_items', [
            'product_id' => $selectedProduct->id,
            'quantity' => 5,
        ]);
        $this->assertDatabaseMissing('purchaser_cart_items', [
            'product_id' => $draftOnlyProduct->id,
        ]);
    }

    public function test_bulk_buy_rejects_submit_without_selected_quantities(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $purchaser = $this->purchaserUser();
        $product = $this->activeProduct();

        $this
            ->actingAs($purchaser)
            ->from(route('purchaser.bulk-buy.details', [
                'date' => today()->toDateString(),
                'products' => [$product->id],
            ]))
            ->post(route('purchaser.carts.bulk-store'), [
                'business_date' => today()->toDateString(),
                'product_ids' => [$product->id],
                'items' => [
                    $product->id => [
                        'quantity' => 0,
                        'unit_price' => null,
                    ],
                ],
            ])
            ->assertRedirect(route('purchaser.bulk-buy.details', [
                'date' => today()->toDateString(),
                'products' => [$product->id],
            ]))
            ->assertSessionHas('error', 'Enter quantity for at least one product before adding to cart.');

        $this->assertSame(0, PurchaserCartItem::query()->count());
    }

    public function test_add_to_cart_rejects_zero_unit_price(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $purchaser = $this->purchaserUser();
        $product = $this->activeProduct();

        $this
            ->actingAs($purchaser)
            ->from(route('purchaser.daily', ['date' => today()->toDateString()]))
            ->post(route('purchaser.cart-items.store'), [
                'business_date' => today()->toDateString(),
                'product_id' => $product->id,
                'quantity' => 5,
                'unit_price' => 0,
                'purchase_source' => 'shop_order',
            ])
            ->assertSessionHasErrors('unit_price');

        $this->assertSame(0, PurchaserCartItem::query()->count());
    }

    private function purchaserUser(): User
    {
        $user = User::query()->create([
            'name' => 'Purchaser',
            'email' => 'purchaser@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::findByName('purchaser'));

        return $user;
    }

    private function activeProduct(): Product
    {
        $category = Category::factory()->create(['name' => 'Vegetables']);

        return Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Test Tomato',
            'sku' => 'TEST-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);
    }
}
