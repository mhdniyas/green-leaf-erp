<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\Finance\JournalEntryData;
use App\Models\Account;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Finance\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanyAccountingCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyMainAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
            CompanyAccountingCategorySeeder::class,
        ]);
    }

    public function test_accounts_user_can_create_main_account_entry_and_journal_posting(): void
    {
        $user = User::factory()->create();
        $user->assignRole('accounts');
        $category = CompanyAccountingCategory::query()->where('type', 'expense')->where('name', 'Rent Expense')->firstOrFail();

        $this
            ->actingAs($user)
            ->post(route('admin.accounting.main-account.entries.store'), [
                'type' => 'expense',
                'company_accounting_category_id' => $category->id,
                'business_date' => '2026-07-29',
                'payment_mode' => 'upi',
                'payment_reference' => 'UPI-100',
                'amount' => 1500,
                'reference' => 'RENT-JUL',
                'description' => 'July office rent',
            ])
            ->assertRedirect(route('admin.accounting.main-account.index', ['date' => '2026-07-29']))
            ->assertSessionHas('success');

        $entry = CompanyAccountingEntry::query()->with('journalEntry.transactions.account')->firstOrFail();
        $bankAccount = Account::query()->where('code', '1020')->firstOrFail();

        $this->assertSame(CompanyAccountingEntry::StatusFinal, $entry->status);
        $this->assertNotNull($entry->journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->journal_entry_id,
            'source_type' => CompanyAccountingEntry::class,
            'source_id' => $entry->id,
            'source_event' => 'final',
        ]);
        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $entry->journal_entry_id,
            'account_id' => $category->account_id,
            'type' => 'debit',
            'amount' => '1500.00',
        ]);
        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $entry->journal_entry_id,
            'account_id' => $bankAccount->id,
            'type' => 'credit',
            'amount' => '1500.00',
        ]);
    }

    public function test_main_account_report_shows_daily_monthly_and_category_details(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $incomeCategory = CompanyAccountingCategory::query()->where('type', 'income')->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.accounting.main-account.entries.store'), [
                'type' => 'income',
                'company_accounting_category_id' => $incomeCategory->id,
                'business_date' => '2026-07-29',
                'payment_mode' => 'cash',
                'amount' => 2500,
                'description' => 'Counter sale',
            ])
            ->assertRedirect();

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.main-account.index', ['date' => '2026-07-29']))
            ->assertOk()
            ->assertSeeText('Main Account')
            ->assertSeeText('Daily Income')
            ->assertSeeText('Monthly Daily Details')
            ->assertSeeText('Monthly Category Details')
            ->assertSeeText('Monthly Transaction Details')
            ->assertSeeText('Rs. 2,500.00')
            ->assertSeeText($incomeCategory->name);
    }

    public function test_main_account_reflects_journal_cash_and_bank_movements_directly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $bankAccount = Account::query()->where('code', '1020')->firstOrFail();
        $salesAccount = Account::query()->where('code', '4100')->firstOrFail();

        app(JournalService::class)->createEntry(new JournalEntryData(
            entryDate: '2026-07-29',
            reference: 'DIRECT-JOURNAL-001',
            description: 'Direct journal receipt',
            lines: [
                ['account_id' => $bankAccount->id, 'type' => 'debit', 'amount' => 3200],
                ['account_id' => $salesAccount->id, 'type' => 'credit', 'amount' => 3200],
            ],
            sourceType: null,
            sourceId: null,
            sourceEvent: null,
        ), $admin->id);

        $this->assertSame(0, CompanyAccountingEntry::query()->count());

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.main-account.index', ['date' => '2026-07-29']))
            ->assertOk()
            ->assertSeeText('DIRECT-JOURNAL-001')
            ->assertSeeText('Direct journal receipt')
            ->assertSeeText('Sales Revenue')
            ->assertSeeText('Rs. 3,200.00');
    }

    public function test_accounts_user_can_add_dynamic_main_account_category(): void
    {
        $user = User::factory()->create();
        $user->assignRole('accounts');
        $expenseAccount = Account::query()->where('code', '5900')->firstOrFail();

        $this
            ->actingAs($user)
            ->post(route('admin.accounting.main-account.categories.store'), [
                'type' => 'expense',
                'name' => 'Office Expense',
                'account_id' => $expenseAccount->id,
                'is_active' => 1,
                'date' => '2026-07-29',
            ])
            ->assertRedirect(route('admin.accounting.main-account.index', ['date' => '2026-07-29']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('company_accounting_categories', [
            'type' => 'expense',
            'name' => 'Office Expense',
            'account_id' => $expenseAccount->id,
            'is_active' => true,
        ]);
    }

    public function test_main_account_category_dropdown_hides_ledger_codes_from_category_labels(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.main-account.index', ['date' => '2026-07-29']))
            ->assertOk()
            ->assertSee('data-entry-category-option', false)
            ->assertDontSee('Other Income - 4100', false)
            ->assertDontSee('Sales Income - 4100', false);
    }

    public function test_only_admin_can_reverse_main_account_entry(): void
    {
        $accountsUser = User::factory()->create();
        $accountsUser->assignRole('accounts');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = CompanyAccountingCategory::query()->where('type', 'income')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.accounting.main-account.entries.store'), [
            'type' => 'income',
            'company_accounting_category_id' => $category->id,
            'business_date' => '2026-07-29',
            'payment_mode' => 'bank',
            'amount' => 700,
            'description' => 'Other income',
        ]);

        $entry = CompanyAccountingEntry::query()->firstOrFail();

        $this
            ->actingAs($accountsUser)
            ->patch(route('admin.accounting.main-account.entries.reverse', $entry), [
                'reversal_note' => 'Wrong entry',
            ])
            ->assertForbidden();

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.main-account.entries.reverse', $entry), [
                'reversal_note' => 'Wrong entry',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $entry->refresh();

        $this->assertSame(CompanyAccountingEntry::StatusReversed, $entry->status);
        $this->assertNotNull($entry->reversal_journal_entry_id);
        $this->assertSame(2, JournalEntry::query()->where('source_type', CompanyAccountingEntry::class)->count());
    }
}
