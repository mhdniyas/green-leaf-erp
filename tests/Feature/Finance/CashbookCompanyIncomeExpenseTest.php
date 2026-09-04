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
        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal', ['status' => 'finalized']))->assertOk()->assertDontSee('Test Other Income')->assertDontSee('Test Vehicle');

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
            ->assertSeeText('Company Income & Expense')
            ->assertSee('Cashbook')
            ->assertSee('Finance')
            ->assertDontSee('Active Shop Context')
            ->assertSee('1 shops')
            ->assertSee('Income')
            ->assertSee('Expense');

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.income-expense', ['type' => 'expense']))
            ->assertOk()
            ->assertSeeText('Company Income & Expense')
            ->assertSee('Cashbook')
            ->assertSee('Finance')
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

    public function test_other_categories_allow_optional_notes_and_normal_categories_also_allow_optional_notes(): void
    {
        $otherIncome = CompanyAccountingCategory::query()->where('type', 'income')->where('name', 'Other')->firstOrFail();
        $otherExpense = CompanyAccountingCategory::query()->where('type', 'expense')->where('name', 'Other')->firstOrFail();
        $refund = CompanyAccountingCategory::query()->where('type', 'income')->where('name', 'Refund')->firstOrFail();

        // 'Other' without description is now accepted — validation is UI-only
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $this->payload($otherIncome, 100.00, ['description' => null]))
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $this->payload($otherExpense, 150.00, ['description' => null]))
            ->assertRedirect();

        // Normal categories still work without description
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $this->payload($refund, 175.00, ['description' => null]))
            ->assertRedirect();

        $this->assertSame(3, CompanyAccountingEntry::query()->count());
    }

    public function test_statement_first_uses_same_categories_and_other_notes_are_optional(): void
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

        // 'Other' without description is now accepted — description is UI-only hint
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-company-accounting', $statement), [
                'type' => 'income',
                'company_accounting_category_id' => $otherIncome->id,
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

    public function test_admin_can_open_company_income_and_expense_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.income-expense'))
            ->assertOk()
            ->assertSeeText('Company Income & Expense')
            ->assertSeeText('Total Income')
            ->assertSeeText('Total Expense')
            ->assertSeeText('Net Position');
    }

    public function test_page_is_linked_from_cashbook_finance_sidebar_ui(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance'))
            ->assertOk()
            ->assertSee(route('admin.cashbook.finance.income-expense'))
            ->assertSee('Company Income &amp; Expense', false);
    }

    public function test_add_income_and_add_expense_buttons_are_visible(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.income-expense'))
            ->assertOk()
            ->assertSeeText('+ Add Income')
            ->assertSeeText('+ Add Expense');
    }

    public function test_income_requires_and_selects_company_account(): void
    {
        $category = CompanyAccountingCategory::query()->create([
            'type' => 'income',
            'name' => 'Consulting Income',
            'account_id' => Account::query()->create(['code' => '4388', 'name' => 'Consulting', 'type' => 'revenue', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $payloadWithoutAccount = [
            'type' => 'income',
            'company_accounting_category_id' => $category->id,
            'business_date' => '2026-08-22',
            'amount' => 15000.00,
            'company_account_uuid' => '',
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $payloadWithoutAccount)
            ->assertSessionHasErrors('company_account_uuid');

        $payloadWithAccount = array_merge($payloadWithoutAccount, [
            'company_account_uuid' => $this->bank->public_uuid,
            'reference' => 'CONSULT-01',
            'description' => 'Consulting payment',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $payloadWithAccount)
            ->assertRedirect();

        $entry = CompanyAccountingEntry::query()->where('reference', 'CONSULT-01')->firstOrFail();
        $this->assertSame($this->bank->id, $entry->company_account_id);
        $this->assertSame('income', $entry->type);
    }

    public function test_expense_requires_and_selects_company_account(): void
    {
        $category = CompanyAccountingCategory::query()->create([
            'type' => 'expense',
            'name' => 'Office Internet',
            'account_id' => Account::query()->create(['code' => '5888', 'name' => 'Internet Expense', 'type' => 'expense', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $payloadWithoutAccount = [
            'type' => 'expense',
            'company_accounting_category_id' => $category->id,
            'business_date' => '2026-08-22',
            'amount' => 3500.00,
            'company_account_uuid' => '',
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $payloadWithoutAccount)
            ->assertSessionHasErrors('company_account_uuid');

        $payloadWithAccount = array_merge($payloadWithoutAccount, [
            'company_account_uuid' => $this->bank->public_uuid,
            'reference' => 'NET-SEP-2026',
            'description' => 'Airtel Broadband',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $payloadWithAccount)
            ->assertRedirect();

        $entry = CompanyAccountingEntry::query()->where('reference', 'NET-SEP-2026')->firstOrFail();
        $this->assertSame($this->bank->id, $entry->company_account_id);
        $this->assertSame('expense', $entry->type);
    }

    public function test_income_creates_direction_in_and_increments_balance(): void
    {
        $this->bank->update(['current_balance' => 50000.00]);
        $category = CompanyAccountingCategory::query()->create([
            'type' => 'income',
            'name' => 'Unique Scrap Sale',
            'account_id' => Account::query()->create(['code' => '4389', 'name' => 'Scrap', 'type' => 'revenue', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $entry = $this->postEntry($category, 15000.00);

        $movement = CompanyAccountStatementEntry::query()->where('source_id', $entry->id)->firstOrFail();
        $this->assertSame('in', $movement->direction);
        $this->assertSame(15000.00, (float) $movement->amount);
        $this->assertSame(65000.00, (float) $this->bank->fresh()->current_balance);
    }

    public function test_expense_creates_direction_out_and_decrements_balance(): void
    {
        $this->bank->update(['current_balance' => 50000.00]);
        $category = CompanyAccountingCategory::query()->create([
            'type' => 'expense',
            'name' => 'Office Supply',
            'account_id' => Account::query()->create(['code' => '5890', 'name' => 'Office Supply', 'type' => 'expense', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $entry = $this->postEntry($category, 8000.00);

        $movement = CompanyAccountStatementEntry::query()->where('source_id', $entry->id)->firstOrFail();
        $this->assertSame('out', $movement->direction);
        $this->assertSame(8000.00, (float) $movement->amount);
        $this->assertSame(42000.00, (float) $this->bank->fresh()->current_balance);
    }

    public function test_selected_account_appears_in_table_listing(): void
    {
        $category = CompanyAccountingCategory::query()->create([
            'type' => 'income',
            'name' => 'Dividend',
            'account_id' => Account::query()->create(['code' => '4391', 'name' => 'Dividends', 'type' => 'revenue', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $this->postEntry($category, 12000.00);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.income-expense'))
            ->assertOk()
            ->assertSee($this->bank->name)
            ->assertSee('₹12,000.00');
    }

    public function test_entry_can_be_viewed(): void
    {
        $category = CompanyAccountingCategory::query()->create([
            'type' => 'expense',
            'name' => 'Fuel Expense',
            'account_id' => Account::query()->create(['code' => '5892', 'name' => 'Fuel Acc', 'type' => 'expense', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $entry = $this->postEntry($category, 4500.00);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.income-expense.show', $entry))
            ->assertOk()
            ->assertSeeText('Fuel Expense')
            ->assertSeeText($this->bank->name)
            ->assertSee('₹4,500.00');
    }

    public function test_entry_can_be_safely_edited(): void
    {
        $category = CompanyAccountingCategory::query()->create([
            'type' => 'expense',
            'name' => 'Warehouse Utility',
            'account_id' => Account::query()->create(['code' => '5893', 'name' => 'Utility', 'type' => 'expense', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $entry = $this->postEntry($category, 10000.00);
        $this->assertSame('final', $entry->status);

        $updatePayload = [
            'type' => 'expense',
            'company_accounting_category_id' => $category->id,
            'company_account_uuid' => $this->bank->public_uuid,
            'business_date' => '2026-08-25',
            'amount' => 12000.00,
            'reference' => 'UTIL-CORRECTED',
            'description' => 'Updated utility payment',
            'request_uuid' => (string) Str::uuid(),
        ];

        $this->actingAs($this->admin)
            ->patch(route('admin.cashbook.finance.income-expense.update', $entry), $updatePayload)
            ->assertRedirect();

        $this->assertSame('reversed', $entry->fresh()->status);
        $newEntry = CompanyAccountingEntry::query()->where('reference', 'UTIL-CORRECTED')->firstOrFail();
        $this->assertSame('final', $newEntry->status);
        $this->assertSame(12000.00, (float) $newEntry->amount);
    }

    public function test_changing_bank_account_on_edit_moves_financial_effect_without_double_counting(): void
    {
        $bankA = $this->bank;
        $bankA->update(['current_balance' => 100000.00]);
        $bankB = CompanyAccount::query()->create(['name' => 'Second Bank', 'account_type' => 'bank', 'enabled' => true, 'current_balance' => 50000.00]);

        $category = CompanyAccountingCategory::query()->create([
            'type' => 'expense',
            'name' => 'Office Rent',
            'account_id' => Account::query()->create(['code' => '5894', 'name' => 'Rent Acc', 'type' => 'expense', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        // 1. Post expense of ₹10,000 on Bank A
        $entry = $this->postEntry($category, 10000.00);
        $this->assertSame(90000.00, (float) $bankA->fresh()->current_balance);
        $this->assertSame(50000.00, (float) $bankB->fresh()->current_balance);

        // 2. Edit transaction to ₹8,000 paid from Bank B instead
        $updatePayload = [
            'type' => 'expense',
            'company_accounting_category_id' => $category->id,
            'company_account_uuid' => $bankB->public_uuid,
            'business_date' => '2026-08-25',
            'amount' => 8000.00,
            'reference' => 'RENT-MOVED',
            'description' => 'Corrected bank payment',
            'request_uuid' => (string) Str::uuid(),
        ];

        $this->actingAs($this->admin)
            ->patch(route('admin.cashbook.finance.income-expense.update', $entry), $updatePayload)
            ->assertRedirect();

        // Bank A should be restored (+10,000 => 100,000.00)
        $this->assertSame(100000.00, (float) $bankA->fresh()->current_balance);

        // Bank B should be charged (-8,000 => 42,000.00)
        $this->assertSame(42000.00, (float) $bankB->fresh()->current_balance);

        // Active movements count
        $activeEntries = CompanyAccountingEntry::query()->where('status', 'final')->get();
        $this->assertSame(1, $activeEntries->count());
        $this->assertSame($bankB->id, $activeEntries->first()->company_account_id);
    }

    public function test_changing_amount_on_edit_does_not_double_count(): void
    {
        $this->bank->update(['current_balance' => 50000.00]);
        $category = CompanyAccountingCategory::query()->create([
            'type' => 'income',
            'name' => 'Consulting Fee',
            'account_id' => Account::query()->create(['code' => '4395', 'name' => 'Consulting Acc', 'type' => 'revenue', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $entry = $this->postEntry($category, 5000.00);
        $this->assertSame(55000.00, (float) $this->bank->fresh()->current_balance);

        $updatePayload = [
            'type' => 'income',
            'company_accounting_category_id' => $category->id,
            'company_account_uuid' => $this->bank->public_uuid,
            'business_date' => '2026-08-25',
            'amount' => 7000.00,
            'reference' => 'CONSULT-ADJUSTED',
            'description' => 'Updated fee amount',
            'request_uuid' => (string) Str::uuid(),
        ];

        $this->actingAs($this->admin)
            ->patch(route('admin.cashbook.finance.income-expense.update', $entry), $updatePayload)
            ->assertRedirect();

        // Previous +5000 reversed, +7000 applied => balance should be 57,000.00
        $this->assertSame(57000.00, (float) $this->bank->fresh()->current_balance);
    }

    public function test_delete_reverses_financial_effect_and_marks_status_reversed(): void
    {
        $this->bank->update(['current_balance' => 50000.00]);
        $category = CompanyAccountingCategory::query()->create([
            'type' => 'expense',
            'name' => 'Unique Maintenance',
            'account_id' => Account::query()->create(['code' => '5896', 'name' => 'Maintenance Acc', 'type' => 'expense', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $entry = $this->postEntry($category, 6000.00);
        $this->assertSame(44000.00, (float) $this->bank->fresh()->current_balance);

        $this->actingAs($this->admin)
            ->delete(route('admin.cashbook.finance.income-expense.destroy', $entry), ['reason' => 'Duplicate expense voucher'])
            ->assertRedirect();

        $this->assertSame('reversed', $entry->fresh()->status);
        $this->assertSame(50000.00, (float) $this->bank->fresh()->current_balance);
        $this->assertNotNull($entry->fresh()->reversal_journal_entry_id);
    }

    public function test_invalid_company_account_is_rejected(): void
    {
        $category = CompanyAccountingCategory::query()->create([
            'type' => 'income',
            'name' => 'Legal Recovery',
            'account_id' => Account::query()->create(['code' => '4397', 'name' => 'Legal Recovery', 'type' => 'revenue', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $payload = [
            'type' => 'income',
            'company_accounting_category_id' => $category->id,
            'company_account_uuid' => (string) Str::uuid(),
            'business_date' => '2026-08-22',
            'amount' => 5000.00,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.income-expense.store'), $payload)
            ->assertSessionHasErrors('company_account_uuid');
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
