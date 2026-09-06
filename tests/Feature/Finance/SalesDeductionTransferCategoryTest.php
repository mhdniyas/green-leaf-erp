<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\TransactionGenerator;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDeductionTransferCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private TransactionGenerator $generator;

    private BalanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.com',
        ]);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'name' => 'Casio',
            'code' => 'AV_CASIO',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $this->shop->id,
            'is_active' => true,
            'auto_approve_cashbook' => true,
            'require_verification' => false,
            'opening_mode' => 'manual',
            'closing_mode' => 'manual',
        ]);

        $this->generator = app(TransactionGenerator::class);
        $this->calculator = app(BalanceCalculator::class);
    }

    public function test_transfer_category_shop_to_supermarket_exists(): void
    {
        $type = LedgerEntryType::where('code', 'shop_to_supermarket')->first();

        $this->assertNotNull($type);
        $this->assertEquals('Shop to Supermarket', $type->name);
        $this->assertEquals('transfer', $type->category);
    }

    public function test_sales_deduction_formula_subtracts_transfer_without_increasing_expense(): void
    {
        $cashType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();
        $cardType = LedgerEntryType::where('code', 'card')->firstOrFail();
        $paytmType = LedgerEntryType::where('code', 'paytm')->firstOrFail();
        $smDeliveryType = LedgerEntryType::where('code', 'income_s_m_delivery')->firstOrFail();
        $transferType = LedgerEntryType::where('code', 'shop_to_supermarket')->firstOrFail();

        // 1. Configure Settings
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cashType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cardType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $paytmType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $smDeliveryType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        // Transfer category with include_in_sales = true and payable_direction = minus
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $transferType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
            'include_in_income' => false,
            'include_in_expense' => false,
            'include_in_pl' => false,
            'include_in_payable' => true,
            'payable_direction' => 'minus',
            'settlement_behavior' => 'decrease',
        ]);

        // 2. Record Entries:
        // Cash = 10,000
        // Card = 5,000
        // Paytm = 3,000
        // S/M Delivery = 2,000
        // Transfer (Shop to Supermarket / Casio Delivery) = 4,000
        $date = '2026-09-05';
        $this->generator->record(['shop_id' => $this->shop->id, 'business_date' => $date, 'entry_type_code' => 'cash_sales', 'amount' => 10000]);
        $this->generator->record(['shop_id' => $this->shop->id, 'business_date' => $date, 'entry_type_code' => 'card', 'amount' => 5000]);
        $this->generator->record(['shop_id' => $this->shop->id, 'business_date' => $date, 'entry_type_code' => 'paytm', 'amount' => 3000]);
        $this->generator->record(['shop_id' => $this->shop->id, 'business_date' => $date, 'entry_type_code' => 'income_s_m_delivery', 'amount' => 2000]);
        $this->generator->record(['shop_id' => $this->shop->id, 'business_date' => $date, 'entry_type_code' => 'shop_to_supermarket', 'amount' => 4000]);

        // 3. Compute Snapshot / Balances
        $snapshot = $this->calculator->recalculate((int) $this->shop->id, $date);

        // Expected Sales = 10000 + 5000 + 3000 + 2000 - 4000 = 16,000
        $this->assertEquals(16000.0, (float) $snapshot->total_sales);

        // Expected Expense = 0 (Transfer is NOT an expense)
        $this->assertEquals(0.0, (float) $snapshot->total_expense);
    }
}
