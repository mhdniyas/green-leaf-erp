<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\PurchaserCredit;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookReconciliationMonthResetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private CompanyAccount $bankAccount;

    private CompanyPaymentReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->purchaser = User::factory()->create(['name' => 'Test Purchaser']);
        $this->purchaser->assignRole('purchaser');

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '1300'], ['name' => 'Purchaser Advances', 'type' => 'asset']);

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'Main Operating Bank',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'enabled' => true,
        ]);

        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
    }

    public function test_reset_month_reconciliation_clears_imported_matches_for_selected_month_only(): void
    {
        // 1. Create August statement entry and journal entry
        $augustStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-15',
            'direction' => 'out',
            'amount' => 5000.00,
            'reference' => 'STMT-AUG-001',
            'source' => 'imported',
            'import_file_name' => 'august_statement.csv',
            'status' => 'reconciled',
            'matched_amount' => 5000.00,
            'is_finalized' => true,
            'finalized_at' => now(),
        ]);

        $augustCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 5000.00,
            'business_date' => '2026-08-14',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'created_by' => $this->admin->id,
        ]);

        $augustJournal = JournalEntry::query()->create([
            'entry_date' => '2026-08-14',
            'reference' => 'PURCH-FUND-AUG',
            'description' => 'Purchaser funding',
            'primary_amount' => 5000.00,
            'source_type' => PurchaserCredit::class,
            'source_id' => $augustCredit->id,
            'created_by' => $this->admin->id,
        ]);

        $augustStatement->update([
            'journal_entry_id' => $augustJournal->id,
            'source_type' => PurchaserCredit::class,
            'source_id' => $augustCredit->id,
        ]);

        // 2. Create September statement entry and match
        $septemberCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 3000.00,
            'business_date' => '2026-09-09',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'created_by' => $this->admin->id,
        ]);

        $septemberStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-09-10',
            'direction' => 'out',
            'amount' => 3000.00,
            'reference' => 'STMT-SEP-001',
            'source' => 'imported',
            'import_file_name' => 'september_statement.csv',
            'status' => 'reconciled',
            'matched_amount' => 3000.00,
            'is_finalized' => true,
            'finalized_at' => now(),
        ]);

        $septemberJournal = JournalEntry::query()->create([
            'entry_date' => '2026-09-09',
            'reference' => 'PURCH-FUND-SEP',
            'description' => 'Purchaser funding September',
            'primary_amount' => 3000.00,
            'source_type' => PurchaserCredit::class,
            'source_id' => $septemberCredit->id,
            'created_by' => $this->admin->id,
        ]);

        $septemberStatement->update([
            'journal_entry_id' => $septemberJournal->id,
            'source_type' => PurchaserCredit::class,
            'source_id' => $septemberCredit->id,
        ]);

        // 3. Post Reset Month for August 2026
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.reset-month'), [
            'month' => '2026-08',
            'confirmation' => 'CLEAR AUGUST 2026',
        ]);

        $response->assertRedirect(route('admin.cashbook.finance.reconciliation', [
            'month' => '2026-08',
            'workspace' => 'transactions',
            'status' => 'NEEDS_REVIEW',
        ]));
        $response->assertSessionHas('success', 'August 2026 reset: 1 imported matches cleared, 0 manual counterparts skipped, 0 failures.');

        // 4. Verify August statement is unmatched and preserved (not deleted)
        $augustStatement->refresh();
        $this->assertEquals('unmatched', $augustStatement->status);
        $this->assertEquals(0.0, (float) $augustStatement->matched_amount);
        $this->assertFalse($augustStatement->is_finalized);
        $this->assertNull($augustStatement->journal_entry_id);
        $this->assertNull($augustStatement->source_type);
        $this->assertNull($augustStatement->source_id);

        // 5. Verify September statement is still reconciled and untouched
        $septemberStatement->refresh();
        $this->assertEquals('reconciled', $septemberStatement->status);
        $this->assertTrue($septemberStatement->is_finalized);
        $this->assertEquals($septemberJournal->id, $septemberStatement->journal_entry_id);

        // 6. Verify original models and journal entries were preserved
        $this->assertDatabaseHas('purchaser_credits', ['id' => $augustCredit->id, 'amount' => 5000.00]);
        $this->assertDatabaseHas('journal_entries', ['id' => $augustJournal->id, 'reference' => 'PURCH-FUND-AUG']);
    }

    public function test_reset_month_safely_skips_manual_counterparts(): void
    {
        // 1. Create a manual statement entry
        $manualStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'out',
            'amount' => 1500.00,
            'reference' => 'MANUAL-ENTRY-1',
            'source' => 'manual',
            'status' => 'reconciled',
            'matched_amount' => 1500.00,
            'is_finalized' => true,
        ]);

        // 2. Post Reset Month for August 2026
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.reset-month'), [
            'month' => '2026-08',
            'confirmation' => 'CLEAR AUGUST 2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'August 2026 reset: 0 imported matches cleared, 1 manual counterparts skipped, 0 failures.');
        $response->assertSessionHas('skipped_reconciliations');

        // Verify manual statement is still reconciled and intact
        $manualStatement->refresh();
        $this->assertEquals('reconciled', $manualStatement->status);
        $this->assertTrue($manualStatement->is_finalized);
    }

    public function test_reset_month_requires_exact_confirmation_phrase(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.reset-month'), [
            'month' => '2026-08',
            'confirmation' => 'CLEAR ALL',
        ]);

        $response->assertSessionHasErrors('confirmation');
    }

    public function test_non_admin_cannot_reset_reconciliation(): void
    {
        $nonAdmin = User::factory()->create();

        $response = $this->actingAs($nonAdmin)->post(route('admin.cashbook.finance.reconciliation.reset-month'), [
            'month' => '2026-08',
            'confirmation' => 'CLEAR AUGUST 2026',
        ]);

        $response->assertForbidden();
    }
}
