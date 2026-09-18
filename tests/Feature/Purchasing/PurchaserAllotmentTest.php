<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPurchaserAllotment;
use App\Models\User;
use App\Services\Purchasing\PurchaserAllotmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaserAllotmentTest extends TestCase
{
    use RefreshDatabase;

    private PurchaserAllotmentService $allotmentService;

    private User $adminUser;

    private User $purchaser1;

    private User $purchaser2;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->allotmentService = app(PurchaserAllotmentService::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->purchaser1 = User::factory()->create(['name' => 'Faisal']);
        $this->purchaser1->assignRole('purchaser');

        $this->purchaser2 = User::factory()->create(['name' => 'Rasheed']);
        $this->purchaser2->assignRole('purchaser');

        $category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Tomato',
            'sku' => 'VEG-TOM',
            'unit' => 'kg',
            'category_id' => $category->id,
            'is_active' => true,
        ]);
    }

    public function test_first_product_allotment_creates_open_ended_record(): void
    {
        $allotment = $this->allotmentService->assignPurchaser(
            $this->product->id,
            $this->purchaser1->id,
            '2026-09-01',
            $this->adminUser->id
        );

        $this->assertInstanceOf(ProductPurchaserAllotment::class, $allotment);
        $this->assertEquals($this->product->id, $allotment->product_id);
        $this->assertEquals($this->purchaser1->id, $allotment->purchaser_user_id);
        $this->assertEquals('2026-09-01', $allotment->effective_from->toDateString());
        $this->assertNull($allotment->effective_to);
    }

    public function test_current_purchaser_lookup_returns_active_purchaser(): void
    {
        $this->allotmentService->assignPurchaser(
            $this->product->id,
            $this->purchaser1->id,
            now()->subDays(5)->toDateString(),
            $this->adminUser->id
        );

        $currentPurchaser = $this->allotmentService->getCurrentPurchaserForProduct($this->product->id);

        $this->assertNotNull($currentPurchaser);
        $this->assertEquals($this->purchaser1->id, $currentPurchaser->id);
    }

    public function test_historical_purchaser_lookup_returns_purchaser_active_on_target_date(): void
    {
        // Faisal active from 01 Sep to 17 Sep
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser1->id, '2026-09-01', $this->adminUser->id);
        // Rasheed active from 18 Sep
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, '2026-09-18', $this->adminUser->id);

        $purchaserOnSep10 = $this->allotmentService->getPurchaserForProductOnDate($this->product->id, '2026-09-10');
        $purchaserOnSep18 = $this->allotmentService->getPurchaserForProductOnDate($this->product->id, '2026-09-18');
        $purchaserOnSep25 = $this->allotmentService->getPurchaserForProductOnDate($this->product->id, '2026-09-25');

        $this->assertNotNull($purchaserOnSep10);
        $this->assertEquals($this->purchaser1->id, $purchaserOnSep10->id);

        $this->assertNotNull($purchaserOnSep18);
        $this->assertEquals($this->purchaser2->id, $purchaserOnSep18->id);

        $this->assertNotNull($purchaserOnSep25);
        $this->assertEquals($this->purchaser2->id, $purchaserOnSep25->id);
    }

    public function test_purchaser_change_closes_previous_period_the_day_before_new_effective_date(): void
    {
        $firstAllotment = $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser1->id, '2026-09-01', $this->adminUser->id);
        $secondAllotment = $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, '2026-09-18', $this->adminUser->id);

        $firstAllotment->refresh();

        $this->assertEquals('2026-09-17', $firstAllotment->effective_to?->toDateString());
        $this->assertEquals('2026-09-18', $secondAllotment->effective_from->toDateString());
        $this->assertNull($secondAllotment->effective_to);
    }

    public function test_new_period_starts_correctly_on_effective_date(): void
    {
        $allotment = $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, '2026-09-20', $this->adminUser->id);

        $this->assertEquals('2026-09-20', $allotment->effective_from->toDateString());
        $this->assertNull($allotment->effective_to);
    }

    public function test_future_dated_assignment_leaves_current_assignment_active_until_effective_date(): void
    {
        $todayStr = now()->toDateString();
        $futureDateStr = now()->addDays(5)->toDateString();
        $dayBeforeFutureStr = now()->addDays(4)->toDateString();

        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser1->id, '2026-09-01', $this->adminUser->id);
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, $futureDateStr, $this->adminUser->id);

        $purchaserToday = $this->allotmentService->getPurchaserForProductOnDate($this->product->id, $todayStr);
        $purchaserDayBeforeFuture = $this->allotmentService->getPurchaserForProductOnDate($this->product->id, $dayBeforeFutureStr);
        $purchaserFuture = $this->allotmentService->getPurchaserForProductOnDate($this->product->id, $futureDateStr);

        $this->assertEquals($this->purchaser1->id, $purchaserToday?->id);
        $this->assertEquals($this->purchaser1->id, $purchaserDayBeforeFuture?->id);
        $this->assertEquals($this->purchaser2->id, $purchaserFuture?->id);
    }

    public function test_overlapping_or_invalid_effective_dates_are_rejected(): void
    {
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser1->id, '2026-09-20', $this->adminUser->id);

        $this->expectException(ValidationException::class);

        // Attempting to assign an earlier effective date when a later one exists should fail
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, '2026-09-10', $this->adminUser->id);
    }

    public function test_historical_record_remains_unchanged_after_new_reassignment(): void
    {
        $firstAllotment = $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser1->id, '2026-09-01', $this->adminUser->id);
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, '2026-09-18', $this->adminUser->id);

        $firstAllotment->refresh();

        $this->assertEquals($this->purchaser1->id, $firstAllotment->purchaser_user_id);
        $this->assertEquals('2026-09-01', $firstAllotment->effective_from->toDateString());
        $this->assertEquals('2026-09-17', $firstAllotment->effective_to->toDateString());
    }

    public function test_product_without_allotment_returns_null(): void
    {
        $purchaser = $this->allotmentService->getPurchaserForProductOnDate($this->product->id, '2026-09-15');

        $this->assertNull($purchaser);
    }

    public function test_current_assigned_category_ids_does_not_override_historical_allotment_lookup(): void
    {
        // Give purchaser1 category preference in user settings
        $this->purchaser1->update([
            'assigned_category_ids' => [$this->product->category_id],
        ]);

        // Historical lookup for a date with no allotment should return null, NOT purchaser1
        $purchaser = $this->allotmentService->getPurchaserForProductOnDate($this->product->id, '2026-08-01');

        $this->assertNull($purchaser);
    }

    public function test_non_admin_users_cannot_assign_allotments(): void
    {
        $response = $this->actingAs($this->purchaser1)->post(route('admin.cashbook.finance.purchase.product-allotments.store'), [
            'product_id' => $this->product->id,
            'purchaser_user_id' => $this->purchaser2->id,
            'effective_from' => '2026-09-18',
        ]);

        $response->assertStatus(403);
    }

    public function test_history_endpoint_returns_complete_allotment_timeline(): void
    {
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser1->id, '2026-09-01', $this->adminUser->id);
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, '2026-09-18', $this->adminUser->id);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.finance.purchase.product-allotments.history', $this->product));

        $response->assertStatus(200);
        $response->assertViewHas('history', function ($history) {
            return $history->count() === 2;
        });
    }

    public function test_assign_one_product(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.purchaser-business-days.allotments.assign'), [
            'product_ids' => [$this->product->id],
            'purchaser_user_id' => $this->purchaser1->id,
            'effective_from' => '2026-09-17',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('product_purchaser_allotments', [
            'product_id' => $this->product->id,
            'purchaser_user_id' => $this->purchaser1->id,
            'effective_from' => '2026-09-17',
            'effective_to' => null,
        ]);
    }

    public function test_assign_entire_category(): void
    {
        $category = Category::create(['name' => 'Fruits', 'is_active' => true]);
        $prod1 = Product::create(['name' => 'Apple', 'sku' => 'FRU-APP', 'unit' => 'kg', 'category_id' => $category->id, 'is_active' => true]);
        $prod2 = Product::create(['name' => 'Orange', 'sku' => 'FRU-ORA', 'unit' => 'kg', 'category_id' => $category->id, 'is_active' => true]);

        $categoryProductIds = Product::where('category_id', $category->id)->pluck('id')->all();

        $result = $this->allotmentService->bulkAssignPurchaser($categoryProductIds, $this->purchaser1->id, '2026-09-17', $this->adminUser->id);

        $this->assertEquals(2, $result['assigned_count']);
        $this->assertDatabaseHas('product_purchaser_allotments', ['product_id' => $prod1->id, 'purchaser_user_id' => $this->purchaser1->id]);
        $this->assertDatabaseHas('product_purchaser_allotments', ['product_id' => $prod2->id, 'purchaser_user_id' => $this->purchaser1->id]);
    }

    public function test_assign_multiple_categories(): void
    {
        $cat1 = Category::create(['name' => 'Leafy', 'is_active' => true]);
        $cat2 = Category::create(['name' => 'Tubers', 'is_active' => true]);
        $prod1 = Product::create(['name' => 'Spinach', 'sku' => 'LEA-SPI', 'unit' => 'kg', 'category_id' => $cat1->id, 'is_active' => true]);
        $prod2 = Product::create(['name' => 'Potato', 'sku' => 'TUB-POT', 'unit' => 'kg', 'category_id' => $cat2->id, 'is_active' => true]);

        $productIds = [$prod1->id, $prod2->id];
        $result = $this->allotmentService->bulkAssignPurchaser($productIds, $this->purchaser2->id, '2026-09-17', $this->adminUser->id);

        $this->assertEquals(2, $result['assigned_count']);
        $this->assertDatabaseHas('product_purchaser_allotments', ['product_id' => $prod1->id, 'purchaser_user_id' => $this->purchaser2->id]);
        $this->assertDatabaseHas('product_purchaser_allotments', ['product_id' => $prod2->id, 'purchaser_user_id' => $this->purchaser2->id]);
    }

    public function test_category_selects_all_products(): void
    {
        $category = Category::create(['name' => 'Melons', 'is_active' => true]);
        $p1 = Product::create(['name' => 'Watermelon', 'sku' => 'MEL-WAT', 'unit' => 'kg', 'category_id' => $category->id, 'is_active' => true]);
        $p2 = Product::create(['name' => 'Muskmelon', 'sku' => 'MEL-MUS', 'unit' => 'kg', 'category_id' => $category->id, 'is_active' => true]);

        $catProducts = Product::where('category_id', $category->id)->pluck('id')->all();

        $this->assertCount(2, $catProducts);
        $this->assertContains($p1->id, $catProducts);
        $this->assertContains($p2->id, $catProducts);
    }

    public function test_individual_product_can_be_unchecked(): void
    {
        $category = Category::create(['name' => 'Berries', 'is_active' => true]);
        $p1 = Product::create(['name' => 'Strawberry', 'sku' => 'BER-STR', 'unit' => 'kg', 'category_id' => $category->id, 'is_active' => true]);
        $p2 = Product::create(['name' => 'Blueberry', 'sku' => 'BER-BLU', 'unit' => 'kg', 'category_id' => $category->id, 'is_active' => true]);

        // Simulating admin selecting category but unchecking p2 -> assigning only p1
        $selectedIds = [$p1->id];
        $result = $this->allotmentService->bulkAssignPurchaser($selectedIds, $this->purchaser1->id, '2026-09-17', $this->adminUser->id);

        $this->assertEquals(1, $result['assigned_count']);
        $this->assertDatabaseHas('product_purchaser_allotments', ['product_id' => $p1->id, 'purchaser_user_id' => $this->purchaser1->id]);
        $this->assertDatabaseMissing('product_purchaser_allotments', ['product_id' => $p2->id]);
    }

    public function test_bulk_products_assigned_to_one_purchaser(): void
    {
        $cat = Category::create(['name' => 'Exotic', 'is_active' => true]);
        $productIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $p = Product::create(['name' => "Exotic Fruit {$i}", 'sku' => "EXO-{$i}", 'unit' => 'kg', 'category_id' => $cat->id, 'is_active' => true]);
            $productIds[] = $p->id;
        }

        $result = $this->allotmentService->bulkAssignPurchaser($productIds, $this->purchaser1->id, '2026-09-17', $this->adminUser->id);

        $this->assertEquals(10, $result['assigned_count']);
        $this->assertEquals(0, $result['skipped_count']);
    }

    public function test_existing_same_purchaser_assignment_skipped_safely(): void
    {
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser1->id, '2026-09-10', $this->adminUser->id);

        // Bulk assign product to SAME purchaser1 effective 2026-09-17
        $result = $this->allotmentService->bulkAssignPurchaser([$this->product->id], $this->purchaser1->id, '2026-09-17', $this->adminUser->id);

        $this->assertEquals(0, $result['assigned_count']);
        $this->assertEquals(1, $result['skipped_count']);

        // Only 1 allotment record exists
        $this->assertDatabaseCount('product_purchaser_allotments', 1);
    }

    public function test_reassignment_closes_previous_effective_period(): void
    {
        $first = $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser1->id, '2026-09-01', $this->adminUser->id);
        $second = $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, '2026-09-17', $this->adminUser->id);

        $first->refresh();
        $this->assertEquals('2026-09-16', $first->effective_to?->toDateString());
        $this->assertEquals('2026-09-17', $second->effective_from->toDateString());
        $this->assertNull($second->effective_to);
    }

    public function test_historical_assignment_preserved(): void
    {
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser1->id, '2026-09-01', $this->adminUser->id);
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, '2026-09-17', $this->adminUser->id);

        $historicalPurchaser = $this->allotmentService->getPurchaserForProductOnDate($this->product->id, '2026-09-10');
        $this->assertNotNull($historicalPurchaser);
        $this->assertEquals($this->purchaser1->id, $historicalPurchaser->id);
    }

    public function test_overlapping_assignment_rejected(): void
    {
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser1->id, '2026-09-20', $this->adminUser->id);

        $this->expectException(ValidationException::class);
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, '2026-09-10', $this->adminUser->id);
    }

    public function test_non_admin_blocked(): void
    {
        $response = $this->actingAs($this->purchaser1)->post(route('admin.cashbook.purchaser-business-days.allotments.assign'), [
            'product_ids' => [$this->product->id],
            'purchaser_user_id' => $this->purchaser2->id,
            'effective_from' => '2026-09-17',
        ]);

        $response->assertStatus(403);
    }

    public function test_assigned_category_ids_remains_unchanged(): void
    {
        $initialCategories = [1, 2, 3];
        $this->purchaser1->update(['assigned_category_ids' => $initialCategories]);

        $this->allotmentService->bulkAssignPurchaser([$this->product->id], $this->purchaser1->id, '2026-09-17', $this->adminUser->id);

        $this->purchaser1->refresh();
        $this->assertEquals($initialCategories, $this->purchaser1->assigned_category_ids);
    }

    public function test_normal_purchaser_product_visibility_remains_unchanged(): void
    {
        $initialVisibility = $this->purchaser1->assigned_category_ids;

        $this->allotmentService->bulkAssignPurchaser([$this->product->id], $this->purchaser1->id, '2026-09-17', $this->adminUser->id);

        $this->purchaser1->refresh();
        $this->assertEquals($initialVisibility, $this->purchaser1->assigned_category_ids);
    }

    public function test_business_day_reconciliation_recognizes_new_allotment(): void
    {
        $this->allotmentService->assignPurchaser($this->product->id, $this->purchaser2->id, '2026-09-17', $this->adminUser->id);

        $activePurchaser = $this->allotmentService->getPurchaserForProductOnDate($this->product->id, '2026-09-17');
        $this->assertNotNull($activePurchaser);
        $this->assertEquals($this->purchaser2->id, $activePurchaser->id);
    }
}
