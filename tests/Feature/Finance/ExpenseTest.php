<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $financeUser;

    private User $entryUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->financeUser = User::factory()->create();
        $this->financeUser->givePermissionTo('accounting.ledger.view');

        $this->entryUser = User::factory()->create();
        $this->entryUser->givePermissionTo('accounting.entry.create');
    }

    public function test_legacy_expense_pages_redirect_to_finance_board(): void
    {
        $this->actingAs($this->financeUser)
            ->get(route('finance.expenses.index'))
            ->assertRedirect(route('finance.vendors.index'));

        $this->actingAs($this->financeUser)
            ->get(route('finance.expenses.create'))
            ->assertRedirect(route('finance.vendors.index'));
    }

    public function test_legacy_expense_write_routes_are_removed(): void
    {
        $this->actingAs($this->entryUser)
            ->post('/finance/expenses', [
                'expense_date' => now()->format('Y-m-d'),
                'account_id' => 1,
                'amount' => 4500.50,
                'payment_method' => 'cash',
            ])
            ->assertMethodNotAllowed();

        $this->actingAs($this->entryUser)
            ->put('/finance/expenses/1', [
                'expense_date' => now()->format('Y-m-d'),
                'account_id' => 1,
                'amount' => 4800.00,
                'payment_method' => 'bank',
            ])
            ->assertNotFound();

        $this->actingAs($this->entryUser)
            ->delete('/finance/expenses/1')
            ->assertNotFound();
    }
}
