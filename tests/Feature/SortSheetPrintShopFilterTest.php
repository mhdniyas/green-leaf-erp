<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class SortSheetPrintShopFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $viewUser;

    private User $exportUser;

    private Shop $shopA;

    private Shop $shopB;

    private Shop $shopC;

    private Product $product1;

    private Product $product2;

    private string $testDate = '2026-08-30';

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewUser = User::factory()->create();
        $this->viewUser->givePermissionTo(Permission::findOrCreate('sort.sheet.view'));

        $this->exportUser = User::factory()->create();
        $this->exportUser->givePermissionTo(
            Permission::findOrCreate('sort.sheet.view'),
            Permission::findOrCreate('sort.sheet.export'),
        );

        $category = Category::factory()->create(['name' => 'Vegetables']);

        $this->shopA = Shop::factory()->create(['name' => 'Shop A', 'warehouse_tag' => 'TAG-A', 'status' => 'active']);
        $this->shopB = Shop::factory()->create(['name' => 'Shop B', 'warehouse_tag' => 'TAG-B', 'status' => 'active']);
        $this->shopC = Shop::factory()->create(['name' => 'Shop C', 'warehouse_tag' => 'TAG-C', 'status' => 'active']);

        $this->product1 = Product::factory()->create(['name' => 'Carrot', 'sku' => 'V-001', 'category_id' => $category->id]);
        $this->product2 = Product::factory()->create(['name' => 'Potato', 'sku' => 'V-002', 'category_id' => $category->id]);

        // Orders for testDate
        $orderA = ShopOrder::factory()->approved()->for($this->shopA)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $orderA->id,
            'product_id' => $this->product1->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'KG',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $orderA->id,
            'product_id' => $this->product2->id,
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'KG',
        ]);

        $orderB = ShopOrder::factory()->approved()->for($this->shopB)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $orderB->id,
            'product_id' => $this->product1->id,
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'KG',
        ]);

        $orderC = ShopOrder::factory()->approved()->for($this->shopC)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $orderC->id,
            'product_id' => $this->product1->id,
            'requested_qty' => 30,
            'approved_qty' => 30,
            'unit' => 'KG',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $orderC->id,
            'product_id' => $this->product2->id,
            'requested_qty' => 15,
            'approved_qty' => 15,
            'unit' => 'KG',
        ]);
    }

    public function test_all_shops_selected_by_default_in_print_and_generate(): void
    {
        $response = $this->actingAs($this->viewUser)
            ->get(route('sort-sheet.print', ['date' => $this->testDate]));

        $response->assertOk()
            ->assertViewIs('sort-sheet.print')
            ->assertViewHas('filteredShops', function ($shops): bool {
                return $shops->count() === 3
                    && $shops->contains('id', $this->shopA->id)
                    && $shops->contains('id', $this->shopB->id)
                    && $shops->contains('id', $this->shopC->id);
            })
            ->assertViewHas('matrix', function (array $matrix): bool {
                // Product 1 total across all 3 shops = 10 + 20 + 30 = 60
                $p1Qtys = $matrix[$this->product1->id] ?? [];

                return ($p1Qtys[$this->shopA->id] ?? 0) === 10.0
                    && ($p1Qtys[$this->shopB->id] ?? 0) === 20.0
                    && ($p1Qtys[$this->shopC->id] ?? 0) === 30.0
                    && array_sum($p1Qtys) === 60.0;
            });
    }

    public function test_excluding_single_shop_excludes_it_and_recalculates_matrix(): void
    {
        // Only select Shop A and Shop B (Shop C unticked)
        $response = $this->actingAs($this->viewUser)
            ->get(route('sort-sheet.print', [
                'date' => $this->testDate,
                'print_shop_ids' => [$this->shopA->id, $this->shopB->id],
            ]));

        $response->assertOk()
            ->assertViewHas('filteredShops', function ($shops): bool {
                return $shops->count() === 2
                    && $shops->contains('id', $this->shopA->id)
                    && $shops->contains('id', $this->shopB->id)
                    && ! $shops->contains('id', $this->shopC->id);
            })
            ->assertViewHas('matrix', function (array $matrix): bool {
                $p1Qtys = $matrix[$this->product1->id] ?? [];

                // Product 1 total = 10 + 20 = 30 (Shop C 30 is excluded)
                return ($p1Qtys[$this->shopA->id] ?? 0) === 10.0
                    && ($p1Qtys[$this->shopB->id] ?? 0) === 20.0
                    && ! isset($p1Qtys[$this->shopC->id])
                    && array_sum($p1Qtys) === 30.0;
            });
    }

    public function test_excluding_several_shops_leaves_only_selected_shop_and_data(): void
    {
        // Only select Shop A
        $response = $this->actingAs($this->viewUser)
            ->get(route('sort-sheet.print', [
                'date' => $this->testDate,
                'print_shop_ids' => [$this->shopA->id],
            ]));

        $response->assertOk()
            ->assertViewHas('filteredShops', function ($shops): bool {
                return $shops->count() === 1
                    && $shops->contains('id', $this->shopA->id);
            })
            ->assertViewHas('matrix', function (array $matrix): bool {
                $p1Qtys = $matrix[$this->product1->id] ?? [];
                $p2Qtys = $matrix[$this->product2->id] ?? [];

                return ($p1Qtys[$this->shopA->id] ?? 0) === 10.0
                    && count($p1Qtys) === 1
                    && ($p2Qtys[$this->shopA->id] ?? 0) === 5.0
                    && count($p2Qtys) === 1;
            });
    }

    public function test_empty_shop_selection_returns_empty_results_safely(): void
    {
        $response = $this->actingAs($this->viewUser)
            ->get(route('sort-sheet.print', [
                'date' => $this->testDate,
                'print_shop_ids' => [''],
            ]));

        $response->assertOk()
            ->assertViewHas('filteredShops', fn ($shops): bool => $shops->isEmpty())
            ->assertViewHas('matrix', fn (array $matrix): bool => empty($matrix));
    }

    public function test_shop_with_no_items_does_not_cause_an_error(): void
    {
        $emptyShop = Shop::factory()->create(['name' => 'Empty Shop', 'status' => 'active']);

        $response = $this->actingAs($this->viewUser)
            ->get(route('sort-sheet.print', [
                'date' => $this->testDate,
                'print_shop_ids' => [$this->shopA->id, $emptyShop->id],
            ]));

        $response->assertOk()
            ->assertViewHas('filteredShops', fn ($shops): bool => $shops->contains('id', $emptyShop->id))
            ->assertViewHas('matrix', function (array $matrix) use ($emptyShop): bool {
                $p1Qtys = $matrix[$this->product1->id] ?? [];

                return ($p1Qtys[$this->shopA->id] ?? 0) === 10.0
                    && ! isset($p1Qtys[$emptyShop->id]);
            });
    }

    public function test_export_pdf_and_excel_respect_print_shop_ids(): void
    {
        // PDF Export
        $pdfResponse = $this->actingAs($this->exportUser)
            ->get(route('sort-sheet.export.pdf', [
                'date' => $this->testDate,
                'print_shop_ids' => [$this->shopB->id],
            ]));

        $pdfResponse->assertOk()
            ->assertViewIs('sort-sheet.print')
            ->assertViewHas('filteredShops', fn ($shops): bool => $shops->count() === 1 && $shops->first()->id === $this->shopB->id);

        // Excel Export
        $excelResponse = $this->actingAs($this->exportUser)
            ->get(route('sort-sheet.export.excel', [
                'date' => $this->testDate,
                'print_shop_ids' => [$this->shopB->id],
            ]));

        $excelResponse->assertOk();
    }

    public function test_authorization_and_validation_enforced(): void
    {
        $unauthorizedUser = User::factory()->create();

        // 403 redirected to dashboard without permission
        $this->actingAs($unauthorizedUser)
            ->get(route('sort-sheet.print', ['date' => $this->testDate]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');

        // JSON requests return 403 Forbidden
        $this->actingAs($unauthorizedUser)
            ->getJson(route('sort-sheet.print', ['date' => $this->testDate]))
            ->assertForbidden();

        // Export permission required for export routes
        $this->actingAs($this->viewUser)
            ->get(route('sort-sheet.export.pdf', ['date' => $this->testDate]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');

        // Non-integer and inactive shop IDs are safely sanitized
        $inactiveShop = Shop::factory()->create(['name' => 'Inactive Shop', 'status' => 'inactive']);

        $response = $this->actingAs($this->viewUser)
            ->get(route('sort-sheet.print', [
                'date' => $this->testDate,
                'print_shop_ids' => [$this->shopA->id, $inactiveShop->id, 'invalid_id', -1],
            ]));

        $response->assertOk()
            ->assertViewHas('filteredShops', function ($shops) use ($inactiveShop): bool {
                return $shops->count() === 1
                    && $shops->contains('id', $this->shopA->id)
                    && ! $shops->contains('id', $inactiveShop->id);
            });
    }
}
