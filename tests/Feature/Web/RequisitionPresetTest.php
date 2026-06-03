<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPreset;
use App\Models\ShopPresetItem;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionPresetTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $shopOwner;

    private Product $product1;

    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);

        $this->shop = Shop::create([
            'code' => 'SHOP_TEST',
            'name' => 'Test Hypermarket',
        ]);

        $this->shopOwner = User::factory()->create([
            'shop_id' => $this->shop->id,
        ]);
        $this->shopOwner->assignRole('shop');

        $this->product1 = Product::first();
        $this->product2 = Product::skip(1)->first();
    }

    public function test_shop_owner_can_view_presets_index(): void
    {
        $preset = ShopPreset::create([
            'shop_id' => $this->shop->id,
            'name' => 'Weekly Staples',
            'created_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.presets.index'));

        $response->assertOk();
        $response->assertSee('Weekly Staples');
    }

    public function test_shop_owner_can_view_create_page(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.presets.create'));

        $response->assertOk();
    }

    public function test_shop_owner_can_store_preset(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.presets.store'), [
                'name' => 'Monday Morning Load',
                'items' => [
                    [
                        'product_id' => $this->product1->id,
                        'quantity' => 12.50,
                    ],
                    [
                        'product_id' => $this->product2->id,
                        'quantity' => 20.00,
                    ],
                ],
            ]);

        $response->assertRedirect(route('requisitions.presets.index'));
        $response->assertSessionHas('success', 'Preset created successfully.');

        $this->assertDatabaseHas('shop_presets', [
            'shop_id' => $this->shop->id,
            'name' => 'Monday Morning Load',
            'created_by' => $this->shopOwner->id,
        ]);

        $preset = ShopPreset::where('name', 'Monday Morning Load')->first();

        $this->assertDatabaseHas('shop_preset_items', [
            'shop_preset_id' => $preset->id,
            'product_id' => $this->product1->id,
            'quantity' => 12.50,
        ]);

        $this->assertDatabaseHas('shop_preset_items', [
            'shop_preset_id' => $preset->id,
            'product_id' => $this->product2->id,
            'quantity' => 20.00,
        ]);
    }

    public function test_shop_owner_can_edit_preset_page(): void
    {
        $preset = ShopPreset::create([
            'shop_id' => $this->shop->id,
            'name' => 'Weekend Special',
            'created_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.presets.edit', $preset->id));

        $response->assertOk();
        $response->assertSee('Weekend Special');
    }

    public function test_shop_owner_can_update_preset(): void
    {
        $preset = ShopPreset::create([
            'shop_id' => $this->shop->id,
            'name' => 'Old Name',
            'created_by' => $this->shopOwner->id,
        ]);

        ShopPresetItem::create([
            'shop_preset_id' => $preset->id,
            'product_id' => $this->product1->id,
            'quantity' => 10.00,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->put(route('requisitions.presets.update', $preset->id), [
                'name' => 'Updated Name',
                'items' => [
                    [
                        'product_id' => $this->product2->id,
                        'quantity' => 45.00,
                    ],
                ],
            ]);

        $response->assertRedirect(route('requisitions.presets.index'));
        $response->assertSessionHas('success', 'Preset updated successfully.');

        $this->assertDatabaseHas('shop_presets', [
            'id' => $preset->id,
            'name' => 'Updated Name',
        ]);

        // Old item should be deleted
        $this->assertDatabaseMissing('shop_preset_items', [
            'shop_preset_id' => $preset->id,
            'product_id' => $this->product1->id,
        ]);

        // New item should be added
        $this->assertDatabaseHas('shop_preset_items', [
            'shop_preset_id' => $preset->id,
            'product_id' => $this->product2->id,
            'quantity' => 45.00,
        ]);
    }

    public function test_shop_owner_can_delete_preset(): void
    {
        $preset = ShopPreset::create([
            'shop_id' => $this->shop->id,
            'name' => 'To Be Deleted',
            'created_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->delete(route('requisitions.presets.destroy', $preset->id));

        $response->assertRedirect(route('requisitions.presets.index'));
        $response->assertSessionHas('success', 'Preset deleted successfully.');

        $this->assertDatabaseMissing('shop_presets', [
            'id' => $preset->id,
        ]);
    }

    public function test_shop_owner_cannot_access_other_shops_preset(): void
    {
        $otherShop = Shop::create([
            'code' => 'OTHER',
            'name' => 'Other Shop',
        ]);
        $otherUser = User::factory()->create([
            'shop_id' => $otherShop->id,
        ]);

        $otherPreset = ShopPreset::create([
            'shop_id' => $otherShop->id,
            'name' => 'Other Shop Preset',
            'created_by' => $otherUser->id,
        ]);

        // Try to edit
        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.presets.edit', $otherPreset->id));
        $response->assertStatus(403);

        // Try to update
        $response = $this->actingAs($this->shopOwner)
            ->put(route('requisitions.presets.update', $otherPreset->id), [
                'name' => 'Hacked',
                'items' => [
                    [
                        'product_id' => $this->product1->id,
                        'quantity' => 1.00,
                    ],
                ],
            ]);
        $response->assertStatus(403);

        // Try to delete
        $response = $this->actingAs($this->shopOwner)
            ->delete(route('requisitions.presets.destroy', $otherPreset->id));
        $response->assertStatus(403);
    }

    public function test_shop_owner_can_store_preset_via_ajax(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->postJson(route('requisitions.presets.store'), [
                'name' => 'AJAX Preset',
                'items' => [
                    [
                        'product_id' => $this->product1->id,
                        'quantity' => 15.00,
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Preset created successfully.',
        ]);

        $this->assertDatabaseHas('shop_presets', [
            'shop_id' => $this->shop->id,
            'name' => 'AJAX Preset',
        ]);
    }

    public function test_shop_owner_can_update_preset_via_ajax(): void
    {
        $preset = ShopPreset::create([
            'shop_id' => $this->shop->id,
            'name' => 'AJAX Preset to Update',
            'created_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->putJson(route('requisitions.presets.update', $preset->id), [
                'name' => 'AJAX Preset Updated',
                'items' => [
                    [
                        'product_id' => $this->product2->id,
                        'quantity' => 22.00,
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Preset updated successfully.',
        ]);

        $this->assertDatabaseHas('shop_presets', [
            'id' => $preset->id,
            'name' => 'AJAX Preset Updated',
        ]);
    }
}
