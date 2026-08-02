<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPdfExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_categories_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));

        $response->assertStatus(200);
        $response->assertViewIs('inventory.categories.index');
    }

    public function test_purchaser_can_view_categories_page(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        $response = $this->actingAs($purchaser)->get(route('inventory.categories.index'));

        $response->assertStatus(200);
        $response->assertViewIs('inventory.categories.index');
    }

    public function test_guest_cannot_access_categories(): void
    {
        $response = $this->get(route('inventory.categories.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_export_pdf_for_all_categories(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cat1 = Category::factory()->create(['name' => 'Leafy Greens']);
        $cat2 = Category::factory()->create(['name' => 'Root Veggies']);

        Product::factory()->create(['category_id' => $cat1->id, 'name' => 'Spinach']);
        Product::factory()->create(['category_id' => $cat2->id, 'name' => 'Carrot']);

        $response = $this->actingAs($admin)->get(route('inventory.categories.export-pdf'));

        $response->assertStatus(200);
        $response->assertViewIs('inventory.categories.pdf');
        $response->assertSee('Leafy Greens');
        $response->assertSee('Root Veggies');
        $response->assertSee('Spinach');
        $response->assertSee('Carrot');
    }

    public function test_purchaser_can_export_pdf_for_multiple_selected_categories(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        $cat1 = Category::factory()->create(['name' => 'Category A']);
        $cat2 = Category::factory()->create(['name' => 'Category B']);
        $cat3 = Category::factory()->create(['name' => 'Category C']);

        Product::factory()->create(['category_id' => $cat1->id, 'name' => 'Item A1']);
        Product::factory()->create(['category_id' => $cat2->id, 'name' => 'Item B1']);
        Product::factory()->create(['category_id' => $cat3->id, 'name' => 'Item C1']);

        $response = $this->actingAs($purchaser)->get(route('inventory.categories.export-pdf', [
            'category_ids' => [$cat1->id, $cat3->id],
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('inventory.categories.pdf');
        $response->assertSee('Category A');
        $response->assertSee('Category C');
        $response->assertDontSee('Category B');
        $response->assertSee('Item A1');
        $response->assertSee('Item C1');
        $response->assertDontSee('Item B1');
    }
}
