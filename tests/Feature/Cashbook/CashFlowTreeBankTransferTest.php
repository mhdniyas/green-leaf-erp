<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Services\Cashbook\CashFlow\CashFlowTreeService;
use App\Services\Cashbook\CashFlow\Sources\BankCashFlowSource;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashFlowTreeBankTransferTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CompanyAccount $bankHdfc;

    private CompanyAccount $bankKotak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->bankHdfc = CompanyAccount::create([
            'name' => 'HDFC BANK',
            'bank_name' => 'HDFC BANK',
            'account_type' => 'bank',
            'opening_balance' => 500000.00,
            'current_balance' => 500000.00,
            'enabled' => true,
        ]);

        $this->bankKotak = CompanyAccount::create([
            'name' => 'KOTAK BANK',
            'bank_name' => 'KOTAK BANK',
            'account_type' => 'bank',
            'opening_balance' => 200000.00,
            'current_balance' => 200000.00,
            'enabled' => true,
        ]);
    }

    public function test_internal_bank_transfer_does_not_change_total_company_money(): void
    {
        // 1. Internal transfer: HDFC -> Kotak: 100,000
        // HDFC outgoing statement entry
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankHdfc->id,
            'transaction_date' => '2026-09-08',
            'direction' => 'out',
            'amount' => 100000.00,
            'narration' => 'Funds Transfer to KOTAK BANK',
            'counterpart_type' => CompanyAccount::class,
            'counterpart_id' => $this->bankKotak->id,
            'source' => 'manual',
        ]);

        // Kotak incoming statement entry
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankKotak->id,
            'transaction_date' => '2026-09-08',
            'direction' => 'in',
            'amount' => 100000.00,
            'narration' => 'Funds Transfer from HDFC BANK',
            'counterpart_type' => CompanyAccount::class,
            'counterpart_id' => $this->bankHdfc->id,
            'source' => 'manual',
        ]);

        $source = app(BankCashFlowSource::class);
        $movements = $source->forMonth('2026-09');

        $transferMovement = $movements->firstWhere('movementType', 'internal_bank_transfer');
        $this->assertNotNull($transferMovement);
        $this->assertTrue($transferMovement->isInternalTransfer());
        $this->assertSame($this->bankHdfc->id, $transferMovement->fromEntityId);
        $this->assertSame($this->bankKotak->id, $transferMovement->toEntityId);
        $this->assertSame(100000.0, $transferMovement->amount);

        $service = app(CashFlowTreeService::class);
        $result = $service->build('2026-09');

        $banksBranch = collect($result['tree']->children)->firstWhere('id', 'branch_banks');
        $this->assertNotNull($banksBranch);

        $hdfcNode = collect($banksBranch->children)->firstWhere('id', "bank_{$this->bankHdfc->id}");
        $kotakNode = collect($banksBranch->children)->firstWhere('id', "bank_{$this->bankKotak->id}");

        $this->assertNotNull($hdfcNode);
        $this->assertNotNull($kotakNode);

        // HDFC: Opening 500k - Out 100k = Closing 400k
        $this->assertEquals(500000.0, $hdfcNode->openingBalance);
        $this->assertEquals(100000.0, $hdfcNode->totalOut);
        $this->assertEquals(400000.0, $hdfcNode->closingBalance);

        // Kotak: Opening 200k + In 100k = Closing 300k
        $this->assertEquals(200000.0, $kotakNode->openingBalance);
        $this->assertEquals(100000.0, $kotakNode->totalIn);
        $this->assertEquals(300000.0, $kotakNode->closingBalance);

        // Invariant: Total Bank Money across company remains exactly 700,000 (Opening 700k = Closing 700k)
        $this->assertEquals(700000.0, $banksBranch->openingBalance);
        $this->assertEquals(700000.0, $banksBranch->closingBalance);

        // Reconciliation summary shows 0 external money in and 0 external money out from this internal movement
        $this->assertEquals(700000.0, $result['summary']->openingCompanyMoney);
        $this->assertEquals(0.0, $result['summary']->externalMoneyIn);
        $this->assertEquals(0.0, $result['summary']->externalMoneyOut);
        $this->assertEquals(700000.0, $result['summary']->expectedClosing);
        $this->assertEquals(700000.0, $result['summary']->locatedMoney);
        $this->assertEquals(0.0, $result['summary']->unexplainedDifference);
        $this->assertTrue($result['summary']->toArray()['is_balanced']);
    }
}
