<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Services\Finance\JournalService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class JournalServiceDefaultAccountsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_restores_a_missing_default_chart_account_on_demand(): void
    {
        Account::query()->where('code', '1200')->delete();

        $journalService = app(JournalService::class);
        $method = new ReflectionMethod($journalService, 'getAccountIdByCode');
        $method->setAccessible(true);

        $accountId = $method->invoke($journalService, '1200');

        $this->assertIsInt($accountId);
        $this->assertDatabaseHas('accounts', [
            'code' => '1200',
            'name' => 'Graded Inventory',
            'type' => 'asset',
            'is_active' => 1,
        ]);
    }

    public function test_it_still_throws_for_unknown_account_codes(): void
    {
        $journalService = app(JournalService::class);
        $method = new ReflectionMethod($journalService, 'getAccountIdByCode');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Chart of Accounts is missing account code: 9999. Please seed the Chart of Accounts.');

        $method->invoke($journalService, '9999');
    }
}
