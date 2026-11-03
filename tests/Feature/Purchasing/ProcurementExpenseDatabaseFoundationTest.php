<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\ProcurementExpense;
use App\Models\ProcurementExpenseCategory;
use App\Models\ProcurementExpenseSubCategory;
use App\Models\User;
use Database\Seeders\ProcurementExpenseCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementExpenseDatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');
    }

    public function test_default_existing_categories_are_seeded_correctly(): void
    {
        $this->seed(ProcurementExpenseCategorySeeder::class);

        $expectedKeys = ['vehicle', 'labour', 'food', 'fuel', 'toll_parking', 'other'];

        foreach ($expectedKeys as $key) {
            $category = ProcurementExpenseCategory::where('key', $key)->first();
            $this->assertNotNull($category, "Expected category {$key} to be seeded.");
            $this->assertTrue($category->is_active);
            $this->assertSame(ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE, $category->sub_category_mode);
            $this->assertGreaterThan(0, $category->sort_order);
        }

        $this->assertSame(6, ProcurementExpenseCategory::count());
    }

    public function test_seeding_repeatedly_creates_no_duplicates(): void
    {
        $this->seed(ProcurementExpenseCategorySeeder::class);
        $countFirst = ProcurementExpenseCategory::count();

        // Run second and third time
        $this->seed(ProcurementExpenseCategorySeeder::class);
        $this->seed(ProcurementExpenseCategorySeeder::class);

        $countSubsequent = ProcurementExpenseCategory::count();
        $this->assertSame($countFirst, $countSubsequent);
        $this->assertSame(6, $countSubsequent);
    }

    public function test_category_keys_are_unique(): void
    {
        ProcurementExpenseCategory::create([
            'key' => 'vehicle',
            'name' => 'Vehicle',
            'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->expectException(QueryException::class);

        ProcurementExpenseCategory::create([
            'key' => 'vehicle',
            'name' => 'Duplicate Vehicle',
            'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    public function test_category_and_location_relationships_work(): void
    {
        $category = ProcurementExpenseCategory::create([
            'key' => 'vehicle_rent',
            'name' => 'Vehicle Rent',
            'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $location1 = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Koyambedu -> Main Warehouse',
            'code' => 'LOC-001',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $location2 = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Market -> Main Warehouse',
            'code' => 'LOC-002',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $category->subCategories);
        $this->assertCount(1, $category->activeSubCategories);
        $this->assertSame($location1->id, $category->activeSubCategories->first()->id);

        $this->assertSame($category->id, $location1->category->id);
        $this->assertSame('Vehicle Rent', $location1->category->name);
        $this->assertTrue($category->requiresSubCategory());
        $this->assertTrue($category->allowsSubCategory());
    }

    public function test_existing_expenses_work_without_locations(): void
    {
        $expense = ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'fuel',
            'amount' => 45.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
            'note' => 'Diesel without sub category',
            'procurement_expense_sub_category_id' => null,
            'sub_category' => null,
        ]);

        $fresh = $expense->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->procurement_expense_sub_category_id);
        $this->assertNull($fresh->sub_category);
        $this->assertNull($fresh->subCategory);
        $this->assertSame('fuel', $fresh->category);
        $this->assertSame(45.00, (float) $fresh->amount);
    }

    public function test_expenses_can_store_sub_category_and_snapshot(): void
    {
        $category = ProcurementExpenseCategory::create([
            'key' => 'vehicle',
            'name' => 'Vehicle',
            'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_OPTIONAL,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subCategory = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Place A -> Place B',
            'code' => 'LOC-AB',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $expense = ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'vehicle',
            'amount' => 120.00,
            'funding_source' => ProcurementExpense::FUNDING_COMPANY_CASH,
            'procurement_expense_sub_category_id' => $subCategory->id,
            'sub_category' => 'Place A -> Place B',
            'note' => 'Trip fare',
        ]);

        $fresh = $expense->fresh(['subCategory', 'categoryDefinition']);
        $this->assertSame($subCategory->id, $fresh->procurement_expense_sub_category_id);
        $this->assertSame('Place A -> Place B', $fresh->sub_category);
        $this->assertSame('Place A -> Place B', $fresh->subCategory?->name);
        $this->assertSame($category->id, $fresh->categoryDefinition?->id);
    }

    public function test_referenced_category_cannot_be_deleted_when_referenced_by_sub_category(): void
    {
        $category = ProcurementExpenseCategory::create([
            'key' => 'vehicle',
            'name' => 'Vehicle',
            'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_OPTIONAL,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Route 1',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->expectException(QueryException::class);
        $category->delete();
    }

    public function test_referenced_sub_category_cannot_be_deleted_when_referenced_by_expense(): void
    {
        $category = ProcurementExpenseCategory::create([
            'key' => 'vehicle',
            'name' => 'Vehicle',
            'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_OPTIONAL,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subCategory = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Route 1',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'vehicle',
            'amount' => 100.00,
            'funding_source' => ProcurementExpense::FUNDING_COMPANY_CASH,
            'procurement_expense_sub_category_id' => $subCategory->id,
            'sub_category' => 'Route 1',
        ]);

        $this->expectException(QueryException::class);
        $subCategory->delete();
    }

    public function test_inactive_categories_and_locations_remain_queryable_for_historical_use(): void
    {
        $category = ProcurementExpenseCategory::create([
            'key' => 'old_equipment',
            'name' => 'Old Equipment',
            'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_OPTIONAL,
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $subCategory = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Old Depot',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $expense = ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->subMonths(3)->toDateString(),
            'category' => 'old_equipment',
            'amount' => 300.00,
            'funding_source' => ProcurementExpense::FUNDING_COMPANY_CASH,
            'procurement_expense_sub_category_id' => $subCategory->id,
            'sub_category' => 'Old Depot',
        ]);

        $activeCategories = ProcurementExpenseCategory::active()->get();
        $this->assertFalse($activeCategories->contains('id', $category->id));

        $activeSubCategories = ProcurementExpenseSubCategory::active()->get();
        $this->assertFalse($activeSubCategories->contains('id', $subCategory->id));

        // Historical record still resolves relations without failure
        $fresh = $expense->fresh(['subCategory', 'categoryDefinition']);
        $this->assertSame('old_equipment', $fresh->category);
        $this->assertSame('Old Equipment', $fresh->categoryDefinition?->name);
        $this->assertSame('Old Depot', $fresh->subCategory?->name);
    }

    public function test_seeder_preserves_unlisted_historical_categories_from_expense_records(): void
    {
        // Simulate a legacy expense using a category key not in default list
        ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'custom_repairs',
            'amount' => 150.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
        ]);

        $this->seed(ProcurementExpenseCategorySeeder::class);

        $customCategory = ProcurementExpenseCategory::where('key', 'custom_repairs')->first();
        $this->assertNotNull($customCategory, 'Historical unlisted category should be discovered and preserved.');
        $this->assertSame('Custom Repairs', $customCategory->name);
        $this->assertSame(ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE, $customCategory->sub_category_mode);
        $this->assertTrue($customCategory->is_active);
    }
}
