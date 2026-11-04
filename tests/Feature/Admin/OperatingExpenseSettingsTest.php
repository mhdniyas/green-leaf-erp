<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ProcurementExpense;
use App\Models\ProcurementExpenseCategory;
use App\Models\ProcurementExpenseSubCategory;
use App\Models\User;
use App\Services\Finance\ProcurementExpensePostingService;
use Database\Seeders\ProcurementExpenseCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingExpenseSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ProcurementExpenseCategorySeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');
    }

    public function test_admin_can_access_operating_expense_settings_tab(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit', ['tab' => 'operating-expenses']));

        $response->assertOk();
        $response->assertSee('Operating Expense Settings');
        $response->assertSee('Expense Categories');
        $response->assertSee('Vehicle');
        $response->assertSee('Locations / Routes');
    }

    public function test_purchaser_cannot_access_configuration_actions(): void
    {
        // GET settings page redirects unauthorized web requests to dashboard with error
        $this->actingAs($this->purchaser)
            ->get(route('admin.company-settings.edit'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');

        // Direct JSON/API GET request receives 403
        $this->actingAs($this->purchaser)
            ->getJson(route('admin.company-settings.edit'))
            ->assertForbidden();

        // POST category receives 403
        $this->actingAs($this->purchaser)
            ->post(route('admin.company-settings.operating-expense-categories.store'), [
                'name' => 'Hacker Category',
                'sub_category_mode' => 'none',
            ])
            ->assertForbidden();

        $category = ProcurementExpenseCategory::first();

        // PATCH category
        $this->actingAs($this->purchaser)
            ->patch(route('admin.company-settings.operating-expense-categories.update', $category), [
                'name' => 'Renamed by Purchaser',
                'sub_category_mode' => 'none',
            ])
            ->assertForbidden();

        // DELETE category
        $this->actingAs($this->purchaser)
            ->delete(route('admin.company-settings.operating-expense-categories.destroy', $category))
            ->assertForbidden();

        // POST sub-category
        $this->actingAs($this->purchaser)
            ->post(route('admin.company-settings.operating-expense-sub-categories.store', $category), [
                'name' => 'Hacker Location',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_a_category(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.company-settings.operating-expense-categories.store'), [
                'name' => 'Equipment Rental',
                'key' => 'equipment_rental',
                'sub_category_mode' => 'none',
                'sort_order' => 10,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.company-settings.edit', [
            'tab' => 'purchaser',
            'subtab' => 'operating-expenses',
            'category_id' => ProcurementExpenseCategory::where('key', 'equipment_rental')->value('id'),
        ]));

        $this->assertDatabaseHas('procurement_expense_categories', [
            'key' => 'equipment_rental',
            'name' => 'Equipment Rental',
            'sub_category_mode' => 'none',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }

    public function test_duplicate_category_keys_are_rejected(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.company-settings.operating-expense-categories.store'), [
                'name' => 'Another Vehicle',
                'key' => 'vehicle', // already exists
                'sub_category_mode' => 'none',
            ]);

        $response->assertSessionHasErrors(['key']);
    }

    public function test_category_key_cannot_be_changed_after_creation(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.operating-expense-categories.update', $category), [
                'name' => 'Fleet Vehicle',
                'key' => 'vehicle_new_key', // attempted mutation
                'sub_category_mode' => 'none',
            ]);

        $response->assertSessionHasErrors(['key']);
        $this->assertSame('vehicle', $category->fresh()->key);
    }

    public function test_admin_can_edit_category_display_name_and_sort_order(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.operating-expense-categories.update', $category), [
                'name' => 'Transport Fleet',
                'sub_category_mode' => 'optional',
                'sort_order' => 99,
                'is_active' => '1',
            ]);

        $response->assertRedirect();
        $fresh = $category->fresh();
        $this->assertSame('Transport Fleet', $fresh->name);
        $this->assertSame('optional', $fresh->sub_category_mode);
        $this->assertSame(99, $fresh->sort_order);
    }

    public function test_admin_can_activate_and_deactivate_a_category(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'labour')->firstOrFail();
        $this->assertTrue($category->is_active);

        // Toggle to inactive
        $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.operating-expense-categories.toggle-status', $category))
            ->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);

        // Toggle back to active
        $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.operating-expense-categories.toggle-status', $category))
            ->assertRedirect();

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_admin_can_create_and_edit_locations(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();

        // Create location
        $response = $this->actingAs($this->admin)
            ->post(route('admin.company-settings.operating-expense-sub-categories.store', $category), [
                'name' => 'Koyambedu -> Main Warehouse',
                'code' => 'KOY-MAIN',
                'sort_order' => 1,
                'is_active' => '1',
            ]);

        $response->assertRedirect();
        $location = ProcurementExpenseSubCategory::where('name', 'Koyambedu -> Main Warehouse')->firstOrFail();
        $this->assertSame($category->id, $location->procurement_expense_category_id);
        $this->assertSame('KOY-MAIN', $location->code);
        $this->assertTrue($location->is_active);

        // Edit location
        $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.operating-expense-sub-categories.update', $location), [
                'name' => 'Koyambedu -> Central Warehouse',
                'code' => 'KOY-CEN',
                'sort_order' => 5,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $fresh = $location->fresh();
        $this->assertSame('Koyambedu -> Central Warehouse', $fresh->name);
        $this->assertSame('KOY-CEN', $fresh->code);
        $this->assertSame(5, $fresh->sort_order);
    }

    public function test_duplicate_locations_within_same_category_are_rejected(): void
    {
        $catA = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();
        $catB = ProcurementExpenseCategory::where('key', 'labour')->firstOrFail();

        ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $catA->id,
            'name' => 'North Depot',
            'is_active' => true,
        ]);

        // Duplicate in same category is rejected
        $response = $this->actingAs($this->admin)
            ->post(route('admin.company-settings.operating-expense-sub-categories.store', $catA), [
                'name' => 'North Depot',
            ]);
        $response->assertSessionHasErrors(['name']);

        // Same name in different category succeeds
        $response2 = $this->actingAs($this->admin)
            ->post(route('admin.company-settings.operating-expense-sub-categories.store', $catB), [
                'name' => 'North Depot',
            ]);
        $response2->assertSessionDoesntHaveErrors();
    }

    public function test_locations_are_scoped_to_their_category(): void
    {
        $catA = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();
        $catB = ProcurementExpenseCategory::where('key', 'labour')->firstOrFail();

        $locA = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $catA->id,
            'name' => 'Route A',
            'is_active' => true,
        ]);

        $this->assertTrue($catA->subCategories->contains('id', $locA->id));
        $this->assertFalse($catB->subCategories->contains('id', $locA->id));
    }

    public function test_required_mode_needs_an_active_location(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'toll_parking')->firstOrFail();
        $this->assertSame(0, $category->activeSubCategories()->count());

        // Attempt to update category to Required mode with 0 locations
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.operating-expense-categories.update', $category), [
                'name' => $category->name,
                'sub_category_mode' => 'required',
            ]);

        $response->assertSessionHasErrors(['sub_category_mode']);
        $this->assertNotSame('required', $category->fresh()->sub_category_mode);

        // Attempt to create new category with Required mode
        $responseCreate = $this->actingAs($this->admin)
            ->post(route('admin.company-settings.operating-expense-categories.store'), [
                'name' => 'Strict Freight',
                'sub_category_mode' => 'required',
            ]);

        $responseCreate->assertSessionHasErrors(['sub_category_mode']);
    }

    public function test_final_active_location_cannot_be_disabled_while_category_mode_is_required(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();

        // Create 1 active location
        $loc = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Sole Route',
            'is_active' => true,
        ]);

        // Enable Required mode
        $category->update(['sub_category_mode' => 'required']);

        // Attempt to toggle location status off
        $responseToggle = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.operating-expense-sub-categories.toggle-status', $loc));

        $responseToggle->assertSessionHasErrors(['status']);
        $this->assertTrue($loc->fresh()->is_active);

        // Attempt to update location with is_active = false
        $responseUpdate = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.operating-expense-sub-categories.update', $loc), [
                'name' => $loc->name,
                'is_active' => '0',
            ]);

        $responseUpdate->assertSessionHasErrors(['is_active']);
        $this->assertTrue($loc->fresh()->is_active);
    }

    public function test_referenced_categories_cannot_be_deleted(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'food')->firstOrFail();

        // Create expense referencing 'food' key
        ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'food',
            'amount' => 50.00,
            'funding_source' => ProcurementExpense::FUNDING_COMPANY_CASH,
        ]);

        // Attempt to delete category
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.company-settings.operating-expense-categories.destroy', $category));

        $response->assertSessionHasErrors(['category']);
        $this->assertDatabaseHas('procurement_expense_categories', ['id' => $category->id]);
    }

    public function test_referenced_locations_cannot_be_deleted(): void
    {
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();

        $loc = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'Protected Route',
            'is_active' => true,
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'vehicle',
            'amount' => 120.00,
            'funding_source' => ProcurementExpense::FUNDING_COMPANY_CASH,
            'procurement_expense_sub_category_id' => $loc->id,
            'sub_category' => 'Protected Route',
        ]);

        // Attempt to delete location
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.company-settings.operating-expense-sub-categories.destroy', $loc));

        $response->assertSessionHasErrors(['location']);
        $this->assertDatabaseHas('procurement_expense_sub_categories', ['id' => $loc->id]);
    }

    public function test_historical_expense_records_remain_unchanged(): void
    {
        $expense = ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => '2026-05-15',
            'category' => 'vehicle',
            'amount' => 250.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
            'note' => 'Original May trip note',
            'procurement_expense_sub_category_id' => null,
            'sub_category' => null,
        ]);

        // Admin adds new categories and locations
        $category = ProcurementExpenseCategory::where('key', 'vehicle')->firstOrFail();
        ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $category->id,
            'name' => 'New June Route',
            'is_active' => true,
        ]);

        $fresh = $expense->fresh();
        $this->assertSame('2026-05-15', $fresh->expense_date->toDateString());
        $this->assertSame('vehicle', $fresh->category);
        $this->assertSame(250.00, (float) $fresh->amount);
        $this->assertNull($fresh->procurement_expense_sub_category_id);
        $this->assertNull($fresh->sub_category);
        $this->assertSame('Original May trip note', $fresh->note);
    }

    public function test_custom_category_configuration_does_not_alter_finance_totals(): void
    {
        $today = now()->toDateString();

        ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => $today,
            'category' => 'fuel',
            'amount' => 100.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
        ]);

        // Add a custom category
        ProcurementExpenseCategory::create([
            'key' => 'machinery_service',
            'name' => 'Machinery Service',
            'sub_category_mode' => 'none',
            'is_active' => false,
            'sort_order' => 10,
        ]);

        // Test posting service
        $postingService = app(ProcurementExpensePostingService::class);
        $entry = $postingService->syncDate($today, (int) $this->purchaser->id);

        $this->assertNotNull($entry);
        $this->assertSame(100.00, (float) $entry->amount);
    }
}
