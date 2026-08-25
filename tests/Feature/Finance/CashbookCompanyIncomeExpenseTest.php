<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookCompanyIncomeExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CompanyAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        Permission::firstOrCreate(['name' => 'accounting.dashboard.view']);
        Permission::firstOrCreate(['name' => 'accounting.entry.create']);
        $this->admin->givePermissionTo(['accounting.dashboard.view', 'accounting.entry.create']);
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
        $this->bank = CompanyAccount::query()->create(['name' => 'Main Bank', 'account_type' => 'bank', 'enabled' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
    }

    public function test_income_and_expense_create_one_pending_cashbook_movement_then_finalize_into_all_transactions(): void
    {
        $incomeAccount = Account::query()->firstOrCreate(['code' => '4399'], ['name' => 'Test Other Income', 'type' => 'revenue', 'is_active' => true]);
        $expenseAccount = Account::query()->firstOrCreate(['code' => '5899'], ['name' => 'Test Vehicle Expense', 'type' => 'expense', 'is_active' => true]);
        $income = $this->postEntry(CompanyAccountingCategory::query()->create(['type' => 'income', 'name' => 'Test Other Income', 'account_id' => $incomeAccount->id, 'is_active' => true]), 25000.00);
        $expense = $this->postEntry(CompanyAccountingCategory::query()->create(['type' => 'expense', 'name' => 'Test Vehicle', 'account_id' => $expenseAccount->id, 'is_active' => true]), 8000.00);

        $this->assertSame(2, CompanyAccountingEntry::query()->count());
        $this->assertSame(2, JournalEntry::query()->count());
        $this->assertSame(2, CompanyAccountStatementEntry::query()->count());
        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'))->assertOk()->assertDontSee('Test Other Income')->assertDontSee('Test Vehicle');

        $incomeMovement = CompanyAccountStatementEntry::query()->where('source_id', $income->id)->firstOrFail();
        $expenseMovement = CompanyAccountStatementEntry::query()->where('source_id', $expense->id)->firstOrFail();
        $this->assertSame('in', $incomeMovement->direction);
        $this->assertSame('out', $expenseMovement->direction);
        $this->assertSame('4399', $income->journalEntry->transactions()->with('account')->get()->firstWhere('type', 'credit')->account->code);
        $this->assertSame('1020', $income->journalEntry->transactions()->with('account')->get()->firstWhere('type', 'debit')->account->code);
        $this->assertSame('5899', $expense->journalEntry->transactions()->with('account')->get()->firstWhere('type', 'debit')->account->code);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.reconciliation', [
                'workspace' => 'statements',
                'statementRef' => $incomeMovement->secureRouteKey(),
                'company_account_id' => $this->bank->id,
                'month' => '2026-08',
            ]))
            ->assertOk()
            ->assertSee('Other Income')
            ->assertSee('Test Other Income')
            ->assertSee('Reconcile')
            ->assertSee('/match-journal', false)
            ->assertDontSee('payment_request_ref');

        foreach ([$incomeMovement, $expenseMovement] as $movement) {
            $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.match-journal', $movement->secureRouteKey()), ['journal_entry_id' => $movement->journal_entry_id, 'cleared_amount' => $movement->amount])->assertRedirect();
        }

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'))->assertOk()->assertSee('Other Income')->assertSee('Test Vehicle');
        $this->assertSame(1, JournalEntry::query()
            ->where('source_type', CompanyAccountingEntry::class)
            ->where('source_id', $income->id)
            ->whereHas('statementEntries', fn ($query) => $query->where('is_finalized', true))
            ->count());
    }

    public function test_category_without_matching_account_and_forged_account_uuid_are_rejected(): void
    {
        $account = Account::query()->create(['code' => '4301', 'name' => 'Other Income', 'type' => 'revenue', 'is_active' => false]);
        $category = CompanyAccountingCategory::query()->create(['type' => 'income', 'name' => 'Broken', 'account_id' => $account->id, 'is_active' => true]);
        $payload = $this->payload($category, 100.00);
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.income-expense.store'), $payload)->assertSessionHasErrors('company_accounting_category_id');
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.income-expense.store'), array_merge($payload, ['company_account_uuid' => (string) Str::uuid()]))->assertSessionHasErrors('company_account_uuid');
    }

    public function test_income_expense_pages_render_with_cashbook_sidebar_for_both_types(): void
    {
        Shop::factory()->create(['name' => 'Main Shop']);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.income-expense', ['type' => 'income']))
            ->assertOk()
            ->assertSeeText('Other Income & Expense')
            ->assertSee('Cashbook')
            ->assertSee('Company Finance')
            ->assertDontSee('Active Shop Context')
            ->assertSee('1 shops')
            ->assertSee('Income')
            ->assertSee('Expense');

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.income-expense', ['type' => 'expense']))
            ->assertOk()
            ->assertSeeText('Other Income & Expense')
            ->assertSee('Cashbook')
            ->assertSee('Company Finance')
            ->assertDontSee('Active Shop Context')
            ->assertSee('1 shops')
            ->assertSee('Income')
            ->assertSee('Expense');
    }

    public function test_default_categories_are_practical_filtered_and_include_other(): void
    {
        $income = CompanyAccountingCategory::query()->with('account')->where('type', 'income')->pluck('name')->all();
        $expense = CompanyAccountingCategory::query()->with('account')->where('type', 'expense')->pluck('name')->all();

        $this->assertContains('Other', $income);
        $this->assertContains('Other', $expense);
        $this->assertContains('Refund', $income);
        $this->assertContains('Rent Received', $income);
        $this->assertContains('Vehicle', $expense);
        $this->assertContains('Fuel', $expense);
        $this->assertContains('Bank Charges', $expense);

        CompanyAccountingCategory::query()->with('account')->where('type', 'income')->get()
            ->each(fn (CompanyAccountingCategory $category) => $this->assertSame('revenue', $category->account?->type));
        CompanyAccountingCategory::query()->with('account')->where('type', 'expense')->get()
            ->each(fn (CompanyAccountingCategory $category) => $this->assertSame('expense', $category->account?->type));
    }

    public function test_other_categories_require_notes_and_normal_categories_allow_optional_notes(): void
    {
        $otherIncome = CompanyAccountingCategory::query()->where('type', 'income')->where('name', 'Other')->firstOrFail();
        $otherExpense = CompanyAccountingCategory::query()->where('type', 'expense')->where('name', 'Other')->firstOrFail();
        $refund = CompanyAccountingCategory::query()->where('type', 'income')->where('name', 'Refund')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $this->payload($otherIncome, 100.00, ['description' => null]))
            ->assertSessionHasErrors('description');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $this->payload($otherExpense, 150.00, ['description' => null]))
            ->assertSessionHasErrors('description');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $this->payload($refund, 175.00, ['description' => null]))
            ->assertRedirect();

        $this->assertSame(1, CompanyAccountingEntry::query()->count());
        $this->assertNull(CompanyAccountingEntry::query()->firstOrFail()->description);
    }

    public function test_statement_first_uses_same_categories_and_requires_other_notes(): void
    {
        $otherIncome = CompanyAccountingCategory::query()->where('type', 'income')->where('name', 'Other')->firstOrFail();
        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bank->id,
            'transaction_date' => '2026-08-22',
            'direction' => 'in',
            'amount' => 250.00,
            'reference' => 'BANK-OTHER-IN',
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-company-accounting', $statement), [
                'type' => 'income',
                'company_accounting_category_id' => $otherIncome->id,
            ])
            ->assertSessionHasErrors('description');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-company-accounting', $statement), [
                'type' => 'income',
                'company_accounting_category_id' => $otherIncome->id,
                'description' => 'Emergency refund adjustment',
            ])
            ->assertRedirect();

        $this->assertSame(1, CompanyAccountingEntry::query()->where('company_accounting_category_id', $otherIncome->id)->count());
        $this->assertTrue($statement->fresh()->is_finalized);
    }

    public function test_category_management_prevents_duplicates_can_disable_and_has_no_delete_route(): void
    {
        $expenseAccount = Account::query()->where('type', 'expense')->where('is_active', true)->firstOrFail();
        $category = CompanyAccountingCategory::query()->where('type', 'expense')->where('name', 'Vehicle')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.accounting.main-account.categories.store'), [
                'type' => 'expense',
                'name' => 'Vehicle',
                'account_id' => $expenseAccount->id,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('category');

        $this->actingAs($this->admin)
            ->patch(route('admin.accounting.main-account.categories.update', $category), [
                'is_active' => false,
                'date' => '2026-08-22',
            ])
            ->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);

        $this->actingAs($this->admin)
            ->delete('/admin/accounting/main-account/categories/'.$category->id)
            ->assertMethodNotAllowed();
    }

    private function postEntry(CompanyAccountingCategory $category, float $amount): CompanyAccountingEntry
    {
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.income-expense.store'), $this->payload($category, $amount))->assertRedirect();

        return CompanyAccountingEntry::query()->latest('id')->with('journalEntry')->firstOrFail();
    }

    /** @return array<string, mixed> */
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(CompanyAccountingCategory $category, float $amount, array $overrides = []): array
    {
        return array_merge(['type' => $category->type, 'company_accounting_category_id' => $category->id, 'company_account_uuid' => $this->bank->public_uuid, 'business_date' => '2026-08-22', 'amount' => $amount, 'reference' => $category->type === 'income' ? 'OTHER-25000' : 'VEHICLE-8000', 'description' => $category->name, 'request_uuid' => (string) Str::uuid()], $overrides);
    }
}
