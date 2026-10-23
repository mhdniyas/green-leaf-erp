<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class PricingPerformancePhase2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Category $category;

    private ShopPriceGroup $groupA;

    private ShopPriceGroup $groupB;

    private ShopPriceGroup $groupC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchase', 'web');
        Role::findOrCreate('purchaser', 'web');
        Role::findOrCreate('shop', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchase');

        $this->category = Category::factory()->create(['name' => 'Vegetables']);

        $this->groupA = ShopPriceGroup::query()->firstOrCreate(
            ['code' => 'A'],
            ['name' => 'Customer Price Group A', 'description' => 'Grade A default tier', 'is_active' => true]
        );
        $this->groupB = ShopPriceGroup::query()->firstOrCreate(
            ['code' => 'B'],
            ['name' => 'Customer Price Group B', 'description' => 'Grade B default tier', 'is_active' => true]
        );
        $this->groupC = ShopPriceGroup::query()->firstOrCreate(
            ['code' => 'C'],
            ['name' => 'Customer Price Group C', 'description' => 'Grade C default tier', 'is_active' => true]
        );
    }

    public function test_purchaser_daily_prices_bounded_loading_does_not_load_unbounded_history(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Test Carrot',
            'is_active' => true,
        ]);

        // Create 10 historical approvals on past dates
        for ($i = 1; $i <= 10; $i++) {
            DailyPriceApproval::query()->create([
                'product_id' => $product->id,
                'business_date' => sprintf('2026-10-%02d', $i),
                'grade' => 'A',
                'price_a' => 10.0 + $i,
                'price_b' => 8.0 + $i,
                'price_c' => 6.0 + $i,
                'purchase_price' => 5.0 + $i,
                'status' => 'approved',
                'price_unit' => 'kg',
            ]);
        }

        $response = $this->actingAs($this->purchaser)->get('/purchaser/daily-prices?date=2026-10-21');

        $response->assertOk();
        $response->assertSee('Test Carrot');
        // Initial L1 table should have the latest previous price (from 2026-10-10, which is 20)
        $response->assertSee('20');
    }

    public function test_purchaser_daily_prices_l2_history_endpoint_returns_last_three_historical_records(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Historical Apple',
            'is_active' => true,
        ]);

        // Create 5 approvals on dates: 2026-10-01 to 2026-10-05
        for ($i = 1; $i <= 5; $i++) {
            DailyPriceApproval::query()->create([
                'product_id' => $product->id,
                'business_date' => sprintf('2026-10-%02d', $i),
                'grade' => 'A',
                'price_a' => 50.0 + $i,
                'price_b' => 40.0 + $i,
                'price_c' => 30.0 + $i,
                'purchase_price' => 25.0 + $i,
                'status' => 'approved',
                'price_unit' => 'kg',
            ]);
        }

        $response = $this->actingAs($this->purchaser)->getJson(
            route('purchaser.daily-prices.history', ['product' => $product->id, 'date' => '2026-10-21'])
        );

        $response->assertOk();
        $data = $response->json('history');
        $this->assertCount(3, $data);
        // Latest should be 2026-10-05, then 2026-10-04, then 2026-10-03
        $this->assertSame('05 Oct 2026', $data[0]['date']);
        $this->assertSame(55.0, (float) $data[0]['price']);
        $this->assertSame('04 Oct 2026', $data[1]['date']);
        $this->assertSame(54.0, (float) $data[1]['price']);
        $this->assertSame('03 Oct 2026', $data[2]['date']);
        $this->assertSame(53.0, (float) $data[2]['price']);
    }

    public function test_purchaser_daily_prices_previous_price_fallback_older_than_yesterday(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Old Reference Item',
            'is_active' => true,
        ]);

        // Last price was 10 days ago, not yesterday
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-11',
            'grade' => 'A',
            'price_a' => 77.50,
            'price_b' => 60.00,
            'price_c' => 50.00,
            'purchase_price' => 45.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $response = $this->actingAs($this->purchaser)->get('/purchaser/daily-prices?date=2026-10-21');

        $response->assertOk();
        $response->assertSee('77.5');
    }

    public function test_price_matrix_paginates_products_and_preserves_query_string(): void
    {
        for ($i = 0; $i < 45; $i++) {
            Product::query()->create([
                'category_id' => $this->category->id,
                'name' => "Item Alpha {$i}",
                'sku' => "SKU-A-{$i}",
                'unit' => 'kg',
                'base_price' => 20.00,
                'vendor_price' => 15.00,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/purchasing/prices/matrix?date=2026-10-21&matrix_category=a&quality_grade=A');

        $response->assertOk();
        $response->assertViewHas('paginatedProducts');
        $paginated = $response->viewData('paginatedProducts');
        $this->assertNotNull($paginated);
        $this->assertSame(40, $paginated->perPage());
        $this->assertSame(45, $paginated->total());
        $this->assertSame(2, $paginated->lastPage());
    }

    public function test_price_matrix_search_queries_across_full_catalog(): void
    {
        for ($i = 0; $i < 45; $i++) {
            Product::query()->create([
                'category_id' => $this->category->id,
                'name' => "Standard Veggie {$i}",
                'sku' => "SKU-B-{$i}",
                'unit' => 'kg',
                'base_price' => 20.00,
                'vendor_price' => 15.00,
                'is_active' => true,
            ]);
        }

        $dragonfruit = Product::query()->create([
            'category_id' => $this->category->id,
            'name' => 'Exotic Dragonfruit Prime',
            'sku' => 'DRAGON-999',
            'unit' => 'kg',
            'base_price' => 45.00,
            'vendor_price' => 35.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/purchasing/prices/matrix?date=2026-10-21&search=Dragonfruit');

        $response->assertOk();
        $response->assertSee('Exotic Dragonfruit Prime');
        $paginated = $response->viewData('paginatedProducts');
        $this->assertSame(1, $paginated->total());
    }

    public function test_price_matrix_export_includes_full_catalog_without_pagination(): void
    {
        for ($i = 0; $i < 45; $i++) {
            Product::query()->create([
                'category_id' => $this->category->id,
                'name' => "Exportable Item {$i}",
                'sku' => "SKU-EXP-{$i}",
                'unit' => 'kg',
                'base_price' => 20.00,
                'vendor_price' => 15.00,
                'is_active' => true,
            ]);
        }

        // WhatsApp export endpoint redirects to wa.me URL
        $response = $this->actingAs($this->admin)->get('/purchasing/prices/matrix/export/whatsapp?date=2026-10-21&scope=today');

        $response->assertRedirect();
        $this->assertStringContainsString('whatsapp.com', $response->headers->get('Location') ?? '');
        $this->assertStringContainsString('Exportable%20Item%2044', $response->headers->get('Location') ?? '');
    }

    public function test_price_matrix_cell_update_preserves_grade_and_customer_group_isolation(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Matrix Isolated Item',
            'is_active' => true,
        ]);

        // Pre-create Grade B approval
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'B',
            'price_a' => 12.00,
            'price_b' => 10.00,
            'price_c' => 8.00,
            'purchase_price' => 6.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // Save Grade A selling price via matrix cell update
        $response = $this->actingAs($this->admin)->postJson(route('purchasing.prices.matrix.cell.update'), [
            'product_id' => $product->id,
            'date' => '2026-10-21',
            'price_category' => 'a',
            'product_grade' => 'A',
            'price' => '25.50',
            'price_unit' => 'kg',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Verify Grade A approval was created/updated
        $gradeA = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-10-21')
            ->where('grade', 'A')
            ->first();

        $this->assertNotNull($gradeA);
        $this->assertSame(25.5, (float) $gradeA->price_a);

        // Verify Grade B approval remains untouched
        $gradeB = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-10-21')
            ->where('grade', 'B')
            ->first();

        $this->assertNotNull($gradeB);
        $this->assertSame(12.0, (float) $gradeB->price_a);
        $this->assertSame(6.0, (float) $gradeB->purchase_price);
    }

    public function test_purchaser_daily_prices_updates_grade_a_only_and_preserves_purchase_cost(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Purchaser Banana',
            'is_active' => true,
        ]);

        // Existing Grade A with purchase price
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'price_a' => 30.00,
            'price_b' => 25.00,
            'price_c' => 20.00,
            'purchase_price' => 18.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // Update price from purchaser daily prices
        $response = $this->actingAs($this->purchaser)->post('/purchaser/daily-prices', [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => '35.00',
                ],
            ],
        ]);

        $response->assertRedirect();

        $updated = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-10-21')
            ->where('grade', 'A')
            ->first();

        $this->assertNotNull($updated);
        $this->assertSame(35.0, (float) $updated->price_a);
        // Purchase cost must be preserved
        $this->assertSame(18.0, (float) $updated->purchase_price);
    }

    public function test_unauthorized_user_cannot_access_or_edit_pricing_screens(): void
    {
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');

        $this->actingAs($shopUser)
            ->get('/purchaser/daily-prices?date=2026-10-21')
            ->assertRedirect();

        $this->actingAs($shopUser)
            ->get('/purchasing/prices/matrix?date=2026-10-21')
            ->assertRedirect();
    }
}
