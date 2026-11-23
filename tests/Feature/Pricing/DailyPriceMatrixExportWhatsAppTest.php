<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DailyPriceMatrixExportWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchase', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->category = Category::factory()->create(['name' => 'Vegetables']);
    }

    public function test_export_whatsapp_with_small_catalog_redirects_to_whatsapp(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name' => 'Quick Carrot',
            'sku' => 'SKU-QCK-1',
            'unit' => 'kg',
            'base_price' => 20.00,
            'vendor_price' => 15.00,
            'is_active' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-11-23',
            'grade' => 'A',
            'price_a' => 25.00,
            'price_b' => 24.00,
            'price_c' => 22.00,
            'purchase_price' => 18.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $response = $this->actingAs($this->admin)->get(
            '/purchasing/prices/matrix/export/whatsapp?date=2026-11-23&search=Quick+Carrot&matrix_category=a&quality_grade=A&scope=today'
        );

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('api.whatsapp.com', $location);
        $this->assertStringContainsString('Quick%20Carrot', $location);
        $this->assertLessThanOrEqual(3500, strlen($location));
    }

    public function test_export_whatsapp_with_large_catalog_returns_view_preventing_bad_gateway(): void
    {
        // Create 80 products to exceed the 3,500 character header threshold
        for ($i = 0; $i < 80; $i++) {
            Product::query()->create([
                'category_id' => $this->category->id,
                'name' => "Catalog Big Item {$i}",
                'sku' => "SKU-CAT-{$i}",
                'unit' => 'kg',
                'base_price' => 30.00,
                'vendor_price' => 25.00,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(
            '/purchasing/prices/matrix/export/whatsapp?date=2026-11-23&matrix_category=a&quality_grade=A&scope=today'
        );

        // Must return 200 view instead of a giant Location header that causes Nginx/Cloudflare 502
        $response->assertOk();
        $response->assertViewIs('purchase-manager.prices.matrix-whatsapp');
        $response->assertSee('Selling Price Matrix — WhatsApp');
        $response->assertSee('Catalog Big Item 0');
        $response->assertSee('Catalog Big Item 79');
        $response->assertSee('Copy All Text');
        $response->assertViewHas('totalItemsCount', 80);
    }

    public function test_export_whatsapp_with_preview_param_forces_view_even_for_small_catalog(): void
    {
        Product::query()->create([
            'category_id' => $this->category->id,
            'name' => 'Preview Single Item',
            'sku' => 'SKU-PRE-1',
            'unit' => 'kg',
            'base_price' => 10.00,
            'vendor_price' => 8.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(
            '/purchasing/prices/matrix/export/whatsapp?date=2026-11-23&search=Preview+Single&matrix_category=a&quality_grade=A&scope=today&preview=1'
        );

        $response->assertOk();
        $response->assertViewIs('purchase-manager.prices.matrix-whatsapp');
        $response->assertSee('Preview Single Item');
    }

    public function test_matrix_index_view_dropdown_container_does_not_clip_overflow(): void
    {
        Product::query()->create([
            'category_id' => $this->category->id,
            'name' => 'Matrix Visual Item',
            'sku' => 'SKU-VIS-1',
            'unit' => 'kg',
            'base_price' => 15.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(
            '/purchasing/prices/matrix?date=2026-11-24'
        );

        $response->assertOk();
        $html = $response->getContent();

        // Ensure the section does not have overflow-hidden cutting off dropdown menus
        $this->assertStringNotContainsString('SECTION B+C — Filter control card'."\n".'    ═══════════════════════════════════════════════════════════ --}}'."\n".'    <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">', $html);
        $this->assertStringContainsString('id="dd-export"', $html);
        $this->assertStringContainsString('WhatsApp', $html);
        $this->assertStringContainsString('Excel', $html);
        $this->assertStringContainsString('PDF', $html);
    }
}
