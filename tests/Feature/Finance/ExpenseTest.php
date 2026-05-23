<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Expense;
use App\Models\User;
use App\Services\Finance\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo('accounting.entry.create');

        $this->unauthorizedUser = User::factory()->create();

        // Rent Expense
        $this->expenseAccount = Account::where('code', '5600')->first();
    }

    public function test_authorized_user_can_view_expenses_list(): void
    {
        $expense = Expense::factory()->create([
            'account_id' => $this->expenseAccount->id,
            'amount' => 12000.00,
            'payment_method' => 'bank',
            'recorded_by' => $this->authorizedUser->id,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('finance.expenses.index'));

        $response->assertOk();
        $response->assertSee('Rent Expense');
        $response->assertSee('INR 12,000.00');
    }

    public function test_authorized_user_can_record_expense(): void
    {
        $data = [
            'expense_date' => now()->format('Y-m-d'),
            'account_id' => $this->expenseAccount->id,
            'amount' => 4500.50,
            'payment_method' => 'cash',
            'reference' => 'RENT-MAY',
            'description' => 'Office Rent May 2026',
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('finance.expenses.store'), $data);

        $response->assertRedirect(route('finance.expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'amount' => 4500.50,
            'payment_method' => 'cash',
            'reference' => 'RENT-MAY',
        ]);

        // General ledger entry should be created automatically
        $this->assertDatabaseHas('journal_entries', [
            'reference' => 'RENT-MAY',
            'description' => 'Office Rent May 2026',
        ]);
    }

    public function test_authorized_user_can_update_expense(): void
    {
        $expense = Expense::factory()->create([
            'account_id' => $this->expenseAccount->id,
            'amount' => 4000.00,
            'payment_method' => 'cash',
            'reference' => 'OLD-REF',
            'recorded_by' => $this->authorizedUser->id,
        ]);

        // Post original journal entry first as the controller/service does
        app(JournalService::class)->recordExpense($expense);

        $data = [
            'expense_date' => now()->format('Y-m-d'),
            'account_id' => $this->expenseAccount->id,
            'amount' => 4800.00,
            'payment_method' => 'bank',
            'reference' => 'NEW-REF',
            'description' => 'Updated Office Rent',
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('finance.expenses.update', $expense), $data);

        $response->assertRedirect(route('finance.expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'amount' => 4800.00,
            'payment_method' => 'bank',
            'reference' => 'NEW-REF',
        ]);

        // Old journal entry should be deleted and new one posted
        $this->assertDatabaseMissing('journal_entries', ['reference' => 'OLD-REF']);
        $this->assertDatabaseHas('journal_entries', ['reference' => 'NEW-REF', 'description' => 'Updated Office Rent']);
    }

    public function test_authorized_user_can_delete_expense(): void
    {
        $expense = Expense::factory()->create([
            'account_id' => $this->expenseAccount->id,
            'amount' => 3000.00,
            'payment_method' => 'cash',
            'reference' => 'DEL-REF',
            'recorded_by' => $this->authorizedUser->id,
        ]);

        app(JournalService::class)->recordExpense($expense);

        $response = $this->actingAs($this->authorizedUser)
            ->delete(route('finance.expenses.destroy', $expense));

        $response->assertRedirect(route('finance.expenses.index'));

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        $this->assertDatabaseMissing('journal_entries', ['reference' => 'DEL-REF']);
    }
}
