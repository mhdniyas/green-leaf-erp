<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashFlow\CashFlowTreeService;
use App\Services\Cashbook\CashFlow\Sources\ShopCashFlowSource;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashFlowTreeShopTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Shop $shop;

    private CompanyAccount $bank;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class]);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create(['name' => 'Casio Shop']);

        $this->bank = CompanyAccount::create([
            'name' => 'Company HDFC',
            'bank_name' => 'HDFC BANK',
            'account_type' => 'bank',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);
    }

    public function test_shop_settlement_follows_configured_relation_to_company_bank(): void
    {
        $entryType = LedgerEntryType::firstOrCreate(
            ['code' => 'PAYTM_COLLECTION'],
            ['name' => 'Paytm', 'category' => 'sales', 'default_direction' => 'credit']
        );

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $entryType->id,
            'display_name' => 'Paytm',
            'company_account_id' => $this->bank->id,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-10',
            'entry_type_id' => $entryType->id,
            'amount' => 72000.00,
            'direction' => 'credit',
            'funding_source' => 'sales',
            'company_account_id' => $this->bank->id,
            'status' => 'active',
        ]);

        $source = app(ShopCashFlowSource::class);
        $movements = $source->forMonth('2026-09', ['shop_id' => $this->shop->id]);

        $this->assertNotEmpty($movements);
        $movement = $movements->firstWhere('amount', 72000.00);
        $this->assertNotNull($movement);
        $this->assertSame('shop', $movement->fromEntityType);
        $this->assertSame($this->shop->id, $movement->fromEntityId);
        $this->assertSame('company_bank', $movement->toEntityType);
        $this->assertSame($this->bank->id, $movement->toEntityId);
        $this->assertSame('Company HDFC', $movement->toEntityName);
        $this->assertSame('shop_settlement', $movement->movementType);
    }

    public function test_shop_settlement_delta_company_owes_shop_and_shop_owes_company(): void
    {
        $entryType = LedgerEntryType::first();

        // Shop owes company (settlement_direction = minus)
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-12',
            'entry_type_id' => $entryType->id,
            'amount' => 15000.00,
            'direction' => 'credit',
            'funding_source' => 'sales',
            'settlement_delta' => 15000.00,
            'settlement_direction' => 'minus',
            'status' => 'active',
        ]);

        // Company owes shop (settlement_direction = plus)
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $entryType->id,
            'amount' => 5000.00,
            'direction' => 'debit',
            'funding_source' => 'sales',
            'settlement_delta' => 5000.00,
            'settlement_direction' => 'plus',
            'status' => 'active',
        ]);

        $source = app(ShopCashFlowSource::class);
        $movements = $source->forMonth('2026-09', ['shop_id' => $this->shop->id]);

        $shopOwes = $movements->firstWhere('amount', 15000.00);
        $this->assertNotNull($shopOwes);
        $this->assertSame('shop', $shopOwes->fromEntityType);
        $this->assertSame('company', $shopOwes->toEntityType);
        $this->assertSame('shop_settlement', $shopOwes->movementType);

        $companyOwes = $movements->firstWhere('amount', 5000.00);
        $this->assertNotNull($companyOwes);
        $this->assertSame('company', $companyOwes->fromEntityType);
        $this->assertSame('shop', $companyOwes->toEntityType);
        $this->assertSame('company_settlement', $companyOwes->movementType);
    }

    public function test_shop_cash_flow_tree_renders_correct_closing_balance(): void
    {
        ShopDailyLedgerSnapshot::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-01',
            'opening_shop_position' => 25000.00,
            'closing_shop_position' => 25000.00,
        ]);

        $entryType = LedgerEntryType::first();

        // 50,000 sales
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-05',
            'entry_type_id' => $entryType->id,
            'amount' => 50000.00,
            'direction' => 'credit',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'status' => 'active',
        ]);

        // 10,000 expense
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-08',
            'entry_type_id' => $entryType->id,
            'amount' => 10000.00,
            'direction' => 'debit',
            'funding_source' => 'sales',
            'affects_expense' => true,
            'status' => 'active',
        ]);

        $service = app(CashFlowTreeService::class);
        $result = $service->build('2026-09', ['shop_id' => $this->shop->id]);

        $shopsBranch = collect($result['tree']->children)->firstWhere('id', 'branch_shops');
        $this->assertNotNull($shopsBranch);

        $shopNode = collect($shopsBranch->children)->firstWhere('id', "shop_{$this->shop->id}");
        $this->assertNotNull($shopNode);
        $this->assertSame(25000.0, $shopNode->openingBalance);
        $this->assertSame(50000.0, $shopNode->totalIn);
        $this->assertSame(10000.0, $shopNode->totalOut);
        $this->assertSame(65000.0, $shopNode->closingBalance);
    }
}
