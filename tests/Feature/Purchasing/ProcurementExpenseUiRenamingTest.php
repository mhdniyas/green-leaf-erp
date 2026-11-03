<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\ProcurementExpense;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementExpenseUiRenamingTest extends TestCase
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

    public function test_purchaser_expense_page_renders_with_renamed_labels(): void
    {
        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.procurement-expenses.index'));

        $response->assertOk();
        $response->assertSee('Expense');
        $response->assertSee('Add Expense');
        $response->assertSee('Expense History');
        $response->assertDontSee('Procurement Expenses');
    }

    public function test_store_expense_flashes_operating_expense_success_message(): void
    {
        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.procurement-expenses.store'), [
                'expense_date' => now()->toDateString(),
                'category' => 'fuel',
                'amount' => 50.00,
                'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
                'note' => 'Diesel for truck',
            ]);

        $response->assertRedirect(route('purchaser.procurement-expenses.index', ['date' => now()->toDateString()]));
        $response->assertSessionHas('success', 'Operating expense saved and posted to daily company expense.');
    }

    public function test_update_expense_flashes_operating_expense_success_message(): void
    {
        $expense = ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'fuel',
            'amount' => 45.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->patch(route('purchaser.procurement-expenses.update', $expense), [
                'expense_date' => now()->toDateString(),
                'category' => 'fuel',
                'amount' => 60.00,
                'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
                'note' => 'Updated fuel cost',
            ]);

        $response->assertRedirect(route('purchaser.procurement-expenses.index', ['date' => now()->toDateString()]));
        $response->assertSessionHas('success', 'Operating expense updated and daily company expense resynced.');
    }

    public function test_delete_expense_flashes_operating_expense_success_message(): void
    {
        $expense = ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => now()->toDateString(),
            'category' => 'fuel',
            'amount' => 45.00,
            'funding_source' => ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->delete(route('purchaser.procurement-expenses.destroy', $expense));

        $response->assertRedirect(route('purchaser.procurement-expenses.index', ['date' => now()->toDateString()]));
        $response->assertSessionHas('success', 'Operating expense deleted and daily company expense resynced.');
    }
}
