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

    public function test_admin_purchaser_credit_stores_ledger_without_premature_cash_out_journal_entry(): void
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
        $this->assertSame('in', $credit->type);
        $this->assertEquals(50000.0, $credit->amount);

        // Verify that no premature cash out journal entry was created on cash advance handover
        $journalEntry = JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $credit->id)
            ->first();

        $this->assertNull($journalEntry);
    }
}
