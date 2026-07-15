<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\PurchaserCredit;
use App\Models\User;
use App\Services\Finance\AdminFinancePillarService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserCreditJournalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_purchaser_credit_posts_cash_out_journal_entry(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $purchaser = User::factory()->create();
        $purchaser->assignRole(Role::findByName('purchaser'));

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.accounting.purchasers.credits.store', $purchaser->public_uuid), [
                'amount' => 50000,
                'description' => 'Cash advance for daily buying',
                'business_date' => '2026-07-14',
            ]);

        $response->assertRedirect(route('admin.accounting.purchasers.show', $purchaser->public_uuid));

        $credit = PurchaserCredit::query()->firstOrFail();
        $journalEntry = JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $credit->id)
            ->where('source_event', 'cash_advance')
            ->firstOrFail();

        $advanceAccount = Account::query()->where('code', '1300')->firstOrFail();
        $cashAccount = Account::query()->where('code', '1010')->firstOrFail();

        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $advanceAccount->id,
            'type' => 'debit',
            'amount' => '50000.00',
        ]);

        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cashAccount->id,
            'type' => 'credit',
            'amount' => '50000.00',
        ]);

        $report = app(AdminFinancePillarService::class)->cashFlowReport(Carbon::parse('2026-07-14'));
        $purchaserRows = $report['journal_rows']->where('source', 'purchaser')->values();

        $this->assertSame(50000.00, $report['summary']['total_out']);
        $this->assertSame(-50000.00, $report['summary']['closing_balance']);
        $this->assertCount(1, $purchaserRows);
        $this->assertSame('OUT', $purchaserRows->first()['direction']);
        $this->assertSame('Purchaser Cash Advance', $purchaserRows->first()['category']);
    }
}
