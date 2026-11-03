<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\ProcurementExpense;
use App\Models\ProcurementExpenseCategory;
use App\Models\ProcurementExpenseSubCategory;
use App\Models\User;
use App\Services\Cashbook\MonthlyReport\OperatingExpenseAggregationService;
use App\Services\Cashbook\MonthlyReport\ReportHeadingDictionary;
use App\Services\Finance\ProcurementExpensePostingService;
use Database\Seeders\ProcurementExpenseCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserExpenseWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $otherPurchaser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ProcurementExpenseCategorySeeder::class);

        $this->purchaser = User::factory()->create(['name' => 'John Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->otherPurchaser = User::factory()->create(['name' => 'Bob Purchaser']);
        $this->otherPurchaser->assignRole('purchaser');
    }

    public function test_purchaser_sees_active_categories_and_inactive_categories_are_omitted(): void
    {
        // Create an inactive category
        ProcurementExpenseCategory::create([
            'key' => 'hidden_category',
            'name' => 'Hidden Secret Category',
            'sub_category_mode' => 'none',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.procurement-expenses.index'));

        $response->assertOk();
        $response->assertSee('Vehicle');
        $response->assertSee('Labour');
        $response->assertDontSee('Hidden Secret Category');
    }

    public function test_inactive_category_is_rejected_for_new_expenses(): void
    {
        $inactiveCat = ProcurementExpenseCategory::create([
            'key' => 'deactivated_service',
            'name' => 'Deactivated Service',
            'sub_category_mode' => 'none',
            'is_active' => false,
            'sort_order' => 50,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.procurement-expenses.store'), [
                'expense_date' => now()->toDateString(),
                'category' => $inactiveCat->key,
                'amount' => 100.00,
            ]);

        $response->assertSessionHasErrors(['category']);
    }

    public function test_category_with_none_mode_works_without_location(): void
    {
        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.procurement-expenses.store'), [
                'expense_date' => now()->toDateString(),
                'category' => 'food',
                'amount' => 45.00,
                'procurement_expense_sub_category_id' => '',
                'note' => 'Lunch for team',
            ]);

        $response->assertRedirect();
        $expense = ProcurementExpense::latest('id')->firstOrFail();
        $this->assertSame('food', $expense->category);
        $this->assertSame(45.00, (float) $expense->amount);
        $this->assertNull($expense->procurement_expense_sub_category_id);
        $this->assertNull($expense->sub_category);
    }

    public function test_category_with_none_mode_rejects_unrelated_location(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();
        $loc = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Route 1',
            'is_active' => true,
        ]);

        // 'food' has mode 'none'
        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.procurement-expenses.store'), [
                'expense_date' => now()->toDateString(),
                'category' => 'food',
                'amount' => 45.00,
                'procurement_expense_sub_category_id' => $loc->id,
            ]);

        $response->assertSessionHasErrors(['procurement_expense_sub_category_id']);
    }

    public function test_category_with_optional_mode_works_with_or_without_location(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();
        $category->update(['sub_category_mode' => 'optional']);

        $loc = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Koyambedu -> Warehouse',
            'code' => 'KOY-01',
            'is_active' => true,
        ]);

        // 1. Without location -> succeeds
        $res1 = $this->actingAs($this->purchaser)
            ->post(route('purchaser.procurement-expenses.store'), [
                'expense_date' => now()->toDateString(),
                'category' => 'vehicle',
                'amount' => 150.00,
                'procurement_expense_sub_category_id' => '',
            ]);
        $res1->assertSessionDoesntHaveErrors();

        // 2. With location -> succeeds and captures snapshot
        $res2 = $this->actingAs($this->purchaser)
            ->post(route('purchaser.procurement-expenses.store'), [
                'expense_date' => now()->toDateString(),
                'category' => 'vehicle',
                'amount' => 250.00,
                'procurement_expense_sub_category_id' => $loc->id,
            ]);
        $res2->assertSessionDoesntHaveErrors();

        $expense = ProcurementExpense::latest('id')->firstOrFail();
        $this->assertSame($loc->id, $expense->procurement_expense_sub_category_id);
        $this->assertSame('Koyambedu -> Warehouse', $expense->sub_category);
    }

    public function test_category_with_required_mode_rejects_missing_location(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();

        $loc = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Mandatory Route',
            'is_active' => true,
        ]);

        $category->update(['sub_category_mode' => 'required']);

        // Missing location -> rejected
        $resMissing = $this->actingAs($this->purchaser)
            ->post(route('purchaser.procurement-expenses.store'), [
                'expense_date' => now()->toDateString(),
                'category' => 'vehicle',
                'amount' => 200.00,
                'procurement_expense_sub_category_id' => '',
            ]);
        $resMissing->assertSessionHasErrors(['procurement_expense_sub_category_id']);

        // Valid location -> accepted
        $resValid = $this->actingAs($this->purchaser)
            ->post(route('purchaser.procurement-expenses.store'), [
                'expense_date' => now()->toDateString(),
                'category' => 'vehicle',
                'amount' => 200.00,
                'procurement_expense_sub_category_id' => $loc->id,
            ]);
        $resValid->assertSessionDoesntHaveErrors();
    }

    public function test_wrong_category_location_is_rejected(): void
    {
        $catVehicle = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();
        $catLabour = ProcurementExpenseCategory::where('key', 'labour')->firstOrFail();
        $catVehicle->update(['sub_category_mode' => 'optional']);

        $labourLoc = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $catLabour->id,
            'name' => 'Field Crew Alpha',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.procurement-expenses.store'), [
                'expense_date' => now()->toDateString(),
                'category' => 'vehicle',
                'amount' => 120.00,
                'procurement_expense_sub_category_id' => $labourLoc->id,
            ]);

        $response->assertSessionHasErrors(['procurement_expense_sub_category_id']);
    }

    public function test_inactive_location_is_rejected_for_new_expenses(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();
        $category->update(['sub_category_mode' => 'optional']);

        $loc = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Decommissioned Depot',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.procurement-expenses.store'), [
                'expense_date' => now()->toDateString(),
                'category' => 'vehicle',
                'amount' => 120.00,
                'procurement_expense_sub_category_id' => $loc->id,
            ]);

        $response->assertSessionHasErrors(['procurement_expense_sub_category_id']);
    }

    public function test_existing_inactive_historical_location_and_category_remain_readable_and_updatable(): void
    {
        $category = ProcurementExpenseCategory::create([
            'key' => 'historical_cat',
            'name' => 'Historical Cat',
            'sub_category_mode' => 'optional',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $loc = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Old Historic Route',
            'is_active' => false,
        ]);

        $expense = ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'historical_cat',
            'procurement_expense_sub_category_id' => $loc->id,
            'sub_category' => 'Old Historic Route',
            'amount' => 500.00,
            'funding_source' => ProcurementExpense::FUNDING_COMPANY_CASH,
            'note' => 'Original historic expense',
        ]);

        // Edit page loads without error and includes historical category & location
        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.procurement-expenses.index', ['edit' => $expense->id]));
        $response->assertOk();
        $response->assertSee('Historical Cat');
        $response->assertSee('Old Historic Route');

        // Updating note while keeping the unchanged historical category/location succeeds
        $patchResponse = $this->actingAs($this->purchaser)
            ->patch(route('purchaser.procurement-expenses.update', $expense), [
                'expense_date' => now()->toDateString(),
                'category' => 'historical_cat',
                'procurement_expense_sub_category_id' => $loc->id,
                'amount' => 520.00,
                'note' => 'Updated historic expense note',
            ]);

        $patchResponse->assertSessionDoesntHaveErrors();
        $fresh = $expense->fresh();
        $this->assertSame(520.00, (float) $fresh->amount);
        $this->assertSame('Updated historic expense note', $fresh->note);
        $this->assertSame('Old Historic Route', $fresh->sub_category);
    }

    public function test_expense_edit_updates_location_and_snapshot(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();
        $category->update(['sub_category_mode' => 'optional']);

        $loc1 = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Origin Point A',
            'is_active' => true,
        ]);

        $loc2 = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Origin Point B',
            'is_active' => true,
        ]);

        $expense = ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $loc1->id,
            'sub_category' => 'Origin Point A',
            'amount' => 300.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
        ]);

        $this->actingAs($this->purchaser)
            ->patch(route('purchaser.procurement-expenses.update', $expense), [
                'expense_date' => now()->toDateString(),
                'category' => 'vehicle',
                'procurement_expense_sub_category_id' => $loc2->id,
                'amount' => 350.00,
            ])
            ->assertSessionDoesntHaveErrors();

        $fresh = $expense->fresh();
        $this->assertSame($loc2->id, $fresh->procurement_expense_sub_category_id);
        $this->assertSame('Origin Point B', $fresh->sub_category);
        $this->assertSame(350.00, (float) $fresh->amount);
    }

    public function test_location_selection_never_duplicates_or_alters_expense_values(): void
    {
        $today = now()->toDateString();
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();
        $category->update(['sub_category_mode' => 'optional']);

        $loc = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Regular Route',
            'is_active' => true,
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $loc->id,
            'sub_category' => 'Regular Route',
            'amount' => 150.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => $today,
            'category' => 'food',
            'amount' => 50.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
        ]);

        // Daily posting sync must equal exact total 200.00
        $postingService = app(ProcurementExpensePostingService::class);
        $entry = $postingService->syncDate($today, (int) $this->purchaser->id);

        $this->assertNotNull($entry);
        $this->assertSame(200.00, (float) $entry->amount);
    }

    public function test_new_custom_expense_category_is_included_in_financial_aggregation(): void
    {
        $today = now()->toDateString();

        $customCat = ProcurementExpenseCategory::create([
            'key' => 'cold_storage',
            'name' => 'Cold Storage Facility',
            'sub_category_mode' => 'none',
            'is_active' => true,
            'sort_order' => 15,
        ]);

        $expense = ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => $today,
            'category' => 'cold_storage',
            'amount' => 450.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
            'note' => 'Cooling charges',
        ]);

        $service = app(OperatingExpenseAggregationService::class);
        $result = $service->calculate($today, $today, [$today]);

        $this->assertGreaterThanOrEqual(450.00, $result['total_operating_expenses']);

        $matchedRow = $result['detailed_rows']->firstWhere('id', 'procurement_'.$expense->id);
        $this->assertNotNull($matchedRow);
        $this->assertSame(450.00, (float) $matchedRow['amount']);
        $this->assertSame('Cold Storage Facility', $matchedRow['original_category']);
        $this->assertSame(ReportHeadingDictionary::OTHER_EXPENSE, $matchedRow['report_bucket']);
    }

    public function test_purchaser_cannot_tamper_with_other_purchasers_expenses(): void
    {
        $otherExpense = ProcurementExpense::create([
            'user_id' => $this->otherPurchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'food',
            'amount' => 80.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
        ]);

        // Attempt update
        $this->actingAs($this->purchaser)
            ->patch(route('purchaser.procurement-expenses.update', $otherExpense), [
                'expense_date' => now()->toDateString(),
                'category' => 'food',
                'amount' => 200.00,
            ])
            ->assertNotFound();

        // Attempt delete
        $this->actingAs($this->purchaser)
            ->delete(route('purchaser.procurement-expenses.destroy', $otherExpense))
            ->assertNotFound();
    }
}
