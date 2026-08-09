<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(CategorySeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
    }

    public function test_1_admin_can_see_deleted_products(): void
    {
        $category = Category::first();
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Tomato',
            'sku' => 'TEST-TOMATO-01',
            'unit' => 'kg',
            'base_price' => 10.00,
            'is_active' => true,
        ]);

        $product->delete();

        $response = $this->actingAs($this->admin)->get(route('inventory.products.trash'));

        $response->assertStatus(200);
        $response->assertSee('Test Tomato');
        $response->assertSee('TEST-TOMATO-01');
    }

    public function test_2_non_admin_cannot_access_deleted_products(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('inventory.products.trash'));

        $response->assertStatus(403);
    }

    public function test_3_deleting_a_product_sets_deleted_at(): void
    {
        $category = Category::first();
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Deletable Item',
            'sku' => 'DEL-001',
            'unit' => 'kg',
            'base_price' => 15.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('inventory.products.destroy', $product));

        $response->assertRedirect(route('inventory.products.index'));
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_4_and_5_restoring_clears_deleted_at_and_makes_product_active(): void
    {
        $category = Category::first();
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Restorable Item',
            'sku' => 'RESTORE-001',
            'unit' => 'kg',
            'base_price' => 20.00,
            'is_active' => false,
        ]);

        $product->delete();
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $response = $this->actingAs($this->admin)->patch(route('inventory.products.restore', $product->getRouteKey()));

        $response->assertRedirect(route('inventory.products.trash'));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null,
            'is_active' => true,
        ]);
    }

    public function test_6_and_7_seeder_finds_soft_deleted_product_and_does_not_create_duplicate_sku(): void
    {
        $this->seed(ProductSeeder::class);

        $seededCountBefore = Product::count();
        $tomatoH = Product::where('sku', '1')->firstOrFail();

        // Soft delete Tomato H
        $tomatoH->delete();
        $this->assertSoftDeleted('products', ['id' => $tomatoH->id]);

        // Re-run ProductSeeder
        $this->seed(ProductSeeder::class);

        // Assert it restored the soft-deleted product instead of creating a duplicate SKU
        $this->assertDatabaseHas('products', [
            'id' => $tomatoH->id,
            'sku' => '1',
            'deleted_at' => null,
        ]);
        $this->assertEquals(1, Product::withTrashed()->where('sku', '1')->count());
        $this->assertEquals($seededCountBefore, Product::count());
    }

    public function test_8_seeder_does_not_deactivate_manually_created_products(): void
    {
        $category = Category::first();
        $manualProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Custom Local Vegetable',
            'sku' => 'CUSTOM-VEG-999',
            'unit' => 'kg',
            'base_price' => 50.00,
            'is_active' => true,
        ]);

        $this->seed(ProductSeeder::class);

        $manualProduct->refresh();
        $this->assertTrue((bool) $manualProduct->is_active);
    }

    public function test_9_seeder_does_not_overwrite_edited_base_price(): void
    {
        $this->seed(ProductSeeder::class);

        $product = Product::where('sku', '1')->firstOrFail();
        $product->update(['base_price' => 999.50]);

        // Re-run seeder
        $this->seed(ProductSeeder::class);

        $product->refresh();
        $this->assertEquals(999.50, (float) $product->base_price);
    }

    public function test_10_seeder_does_not_overwrite_admin_configured_units(): void
    {
        $this->seed(ProductSeeder::class);

        $product = Product::where('sku', '1')->firstOrFail();
        
        // Add custom order unit
        $customUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit' => 'crate',
            'label' => 'SPECIAL CRATE',
            'conversion_to_base' => 25.0,
            'is_base' => false,
            'is_orderable' => true,
            'sort_order' => 5,
        ]);

        // Re-run seeder
        $this->seed(ProductSeeder::class);

        $this->assertDatabaseHas('product_units', [
            'id' => $customUnit->id,
            'label' => 'SPECIAL CRATE',
        ]);
    }

    public function test_11_product_with_historical_transactions_cannot_be_force_deleted(): void
    {
        $category = Category::first();
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Historical Product',
            'sku' => 'HIST-001',
            'unit' => 'kg',
            'base_price' => 25.00,
            'is_active' => true,
        ]);

        \App\Models\StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $this->admin->id,
            'reference' => 'BATCH-HIST-001',
            'received_at' => now(),
            'total_kg' => 10.0,
            'cost_per_kg' => 5.0,
            'status' => \App\Enums\Inventory\BatchStatus::Pending,
        ]);

        $product->delete();

        $response = $this->actingAs($this->admin)->delete(route('inventory.products.force-delete', $product->getRouteKey()));

        $response->assertSessionHas('error');
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_12_and_13_product_list_shows_seeded_and_manual_products_and_edit_page_opens(): void
    {
        $this->seed(ProductSeeder::class);

        $category = Category::first();
        $manualProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Manually Created Product',
            'sku' => 'MANUAL-100',
            'unit' => 'kg',
            'base_price' => 30.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('inventory.products.index', ['search' => 'Manually Created Product']));

        $response->assertStatus(200);
        $response->assertSee('Manually Created Product');

        $seededResponse = $this->actingAs($this->admin)->get(route('inventory.products.index', ['search' => 'Tomato H']));
        $seededResponse->assertStatus(200);
        $seededResponse->assertSee('Tomato H');

        $seededProduct = Product::where('sku', '1')->firstOrFail();

        $editResponse = $this->actingAs($this->admin)->get(route('inventory.products.edit', $seededProduct));
        $editResponse->assertStatus(200);
        $editResponse->assertSee($seededProduct->name);
    }

    public function test_14_sku_validation_checks_active_and_soft_deleted_records(): void
    {
        $category = Category::first();
        $activeProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Active Item',
            'sku' => 'EXISTING-01',
            'unit' => 'kg',
            'base_price' => 10.00,
            'is_active' => true,
        ]);

        $trashedProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Trashed Item',
            'sku' => 'TRASHED-01',
            'unit' => 'kg',
            'base_price' => 10.00,
            'is_active' => true,
        ]);
        $trashedProduct->delete();

        // 1. Try creating with active SKU
        $response1 = $this->actingAs($this->admin)->post(route('inventory.products.store'), [
            'category_id' => $category->id,
            'name' => 'Duplicate Active',
            'sku' => 'EXISTING-01',
            'unit' => 'kg',
        ]);
        $response1->assertSessionHasErrors(['sku' => 'The sku has already been taken.']);

        // 2. Try creating with trashed SKU
        $response2 = $this->actingAs($this->admin)->post(route('inventory.products.store'), [
            'category_id' => $category->id,
            'name' => 'Duplicate Trashed',
            'sku' => 'TRASHED-01',
            'unit' => 'kg',
        ]);
        $response2->assertSessionHasErrors(['sku' => 'This SKU belongs to a deleted product. Restore the deleted product instead.']);
    }

    public function test_product_catalog_exports_include_category_wise_codes(): void
    {
        $category = Category::firstOrFail();
        Product::create([
            'category_id' => $category->id,
            'name' => 'Export Tomato',
            'sku' => 'EXP-001',
            'unit' => 'kg',
            'base_price' => 42.50,
            'is_active' => true,
        ]);

        $csvResponse = $this->actingAs($this->admin)->get(route('inventory.products.export.csv', [
            'search' => 'Export Tomato',
        ]));

        $csvResponse->assertOk();
        $csvResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csvContent = $csvResponse->streamedContent();

        $this->assertStringContainsString('EXP-001', $csvContent);
        $this->assertStringContainsString('Export Tomato', $csvContent);
        $this->assertStringContainsString($category->name, $csvContent);

        $pdfResponse = $this->actingAs($this->admin)->get(route('inventory.products.export.pdf', [
            'search' => 'Export Tomato',
        ]));

        $pdfResponse->assertOk();
        $pdfResponse->assertSee('Category-wise inventory list with product codes');
        $pdfResponse->assertSee('EXP-001');

        $whatsAppResponse = $this->actingAs($this->admin)->get(route('inventory.products.export.whatsapp', [
            'search' => 'Export Tomato',
        ]));

        $whatsAppResponse->assertRedirect();
        $this->assertStringContainsString('api.whatsapp.com/send', $whatsAppResponse->headers->get('Location'));
        $this->assertStringContainsString('EXP-001', rawurldecode((string) $whatsAppResponse->headers->get('Location')));
    }
}
