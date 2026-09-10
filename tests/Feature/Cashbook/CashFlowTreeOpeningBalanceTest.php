<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\PurchaserCredit;
use App\Models\User;
use App\Services\Cashbook\CashFlow\Sources\BankCashFlowSource;
use App\Services\Cashbook\CashFlow\Sources\PurchaserCashFlowSource;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashFlowTreeOpeningBalanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_opening_balance_is_calculated_strictly_from_prior_transactions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        // 1. Bank Account setup
        $bank = CompanyAccount::create([
            'name' => 'South Indian Bank',
            'bank_name' => 'SOUTH INDIAN BANK',
            'account_type' => 'bank',
            'opening_balance' => 10000.00,
            'current_balance' => 10000.00,
            'enabled' => true,
        ]);

        // Prior movement in August (August 15): In 50,000, Out 20,000
        // Expected opening as of Sep 1 = 10,000 + 50,000 - 20,000 = 40,000
        CompanyAccountStatementEntry::create([
            'company_account_id' => $bank->id,
            'transaction_date' => '2026-08-15',
            'direction' => 'in',
            'amount' => 50000.00,
            'narration' => 'August receipt',
            'source' => 'manual',
        ]);
        CompanyAccountStatementEntry::create([
            'company_account_id' => $bank->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'out',
            'amount' => 20000.00,
            'narration' => 'August payment',
            'source' => 'manual',
        ]);

        // Current month movement in September: In 999,999 (should not affect September opening!)
        CompanyAccountStatementEntry::create([
            'company_account_id' => $bank->id,
            'transaction_date' => '2026-09-02',
            'direction' => 'in',
            'amount' => 999999.00,
            'narration' => 'September massive receipt',
            'source' => 'manual',
        ]);

        $bankSource = app(BankCashFlowSource::class);
        $bankOpenings = $bankSource->openingBalances('2026-09-01');

        $this->assertArrayHasKey($bank->id, $bankOpenings);
        $this->assertEquals(40000.0, $bankOpenings[$bank->id]['opening_balance']);

        // 2. Purchaser setup
        $purchaser = User::factory()->create(['name' => 'Purchaser Salim']);
        $purchaser->assignRole('purchaser');

        // Prior in August: Funded 25,000, spent 15,000 -> remaining 10,000
        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'business_date' => '2026-08-10',
            'type' => 'in',
            'amount' => 25000.00,
            'description' => 'August advance',
        ]);
        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'business_date' => '2026-08-28',
            'type' => 'out',
            'amount' => 15000.00,
            'description' => 'August expense',
        ]);

        // September funding: 500,000 (should not affect September 1 opening!)
        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'business_date' => '2026-09-03',
            'type' => 'in',
            'amount' => 500000.00,
            'description' => 'September advance',
        ]);

        $purchaserSource = app(PurchaserCashFlowSource::class);
        $purchaserOpenings = $purchaserSource->openingBalances('2026-09-01', ['purchaser_id' => $purchaser->id]);

        $this->assertArrayHasKey($purchaser->id, $purchaserOpenings);
        $this->assertEquals(10000.0, $purchaserOpenings[$purchaser->id]['opening_advance']);
    }
}
