<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopOperationPageCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        Role::firstOrCreate(['name' => 'shop']);
        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole('shop');

        $this->shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'code' => 'CS01',
            'name' => 'Casio Boutique',
        ]);

        $this->profile = ShopLedgerProfile::query()->firstOrCreate(
            ['shop_id' => $this->shop->id],
            [
                'profile_template' => 'owned_standard',
                'name' => 'Casio Boutique',
                'code' => 'CS01',
                'slug' => 'av-casio-boutique',
                'status' => 'active',
                'payment_configuration' => [
                    'petty_cash' => [
                        'funding_source' => 'company_bank',
                        'default_bank_account_id' => 1,
                    ],
                ],
            ]
        );

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'Main Operating Bank',
            'account_type' => 'bank',
            'enabled' => true,
            'current_balance' => 100000.00,
        ]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1500'], ['name' => 'Shop Petty Advances', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '5000'], ['name' => 'General Expenses', 'type' => 'expense', 'is_active' => true]);

        LedgerEntryType::query()->firstOrCreate(['code' => 'company_to_petty'], [
            'name' => 'Company to Petty',
            'category' => 'transfer',
            'active' => true,
        ]);

        LedgerEntryType::query()->firstOrCreate(['code' => 'shop_cash_settlement'], [
            'name' => 'Cash Settlement',
            'category' => 'settlement',
            'active' => true,
        ]);

        LedgerEntryType::query()->firstOrCreate(['code' => 'shop_cheque_settlement'], [
            'name' => 'Cheque Settlement',
            'category' => 'settlement',
            'active' => true,
        ]);
    }

    public function test_shop_operation_page_loads_successfully_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', ['shop' => $this->profile->slug])
        );

        $response->assertOk();
        $response->assertSee('Casio Boutique');
        $response->assertSee('CS01');
    }

    public function test_settings_button_appears_for_authorized_user_with_correct_route(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', ['shop' => $this->profile->slug])
        );

        $response->assertOk();
        $expectedSettingsUrl = route('admin.cashbook.settings.shop', $this->profile->slug);
        $response->assertSee($expectedSettingsUrl, false);
        $response->assertSee('Settings');
    }

    public function test_unauthorized_user_cannot_access_settings_or_history_routes(): void
    {
        $historyRoutes = [
            route('admin.cashbook.shop.history.payments', $this->profile->slug),
            route('admin.cashbook.shop.history.allocations', $this->profile->slug),
            route('admin.cashbook.shop.history.cheques', $this->profile->slug),
            route('admin.cashbook.shop.history.petty', $this->profile->slug),
            route('admin.cashbook.shop.history.adjustments', $this->profile->slug),
            route('admin.cashbook.shop.history.banking', $this->profile->slug),
            route('admin.cashbook.settings.shop', $this->profile->slug),
        ];

        foreach ($historyRoutes as $url) {
            $response = $this->actingAs($this->unauthorizedUser)->get($url);
            $this->assertTrue(
                in_array($response->status(), [401, 403, 302], true),
                "Expected unauthorized status for {$url}, got {$response->status()}"
            );
        }
    }

    public function test_main_page_shows_latest_5_petty_records_only(): void
    {
        $pettyType = LedgerEntryType::query()->where('code', 'company_to_petty')->firstOrFail();

        // Create 8 petty transactions
        for ($i = 1; $i <= 8; $i++) {
            ShopLedgerTransaction::query()->create([
                'shop_id' => $this->shop->id,
                'business_date' => now()->subDays(8 - $i)->toDateString(),
                'entry_type_id' => $pettyType->id,
                'entry_type_code' => 'company_to_petty',
                'direction' => 'in',
                'funding_source' => 'company_bank',
                'amount' => 1000.00 + $i,
                'status' => 'approved',
                'notes' => "Petty advance test #{$i}",
            ]);
        }

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', ['shop' => $this->profile->slug])
        );

        $response->assertOk();
        $pettyHistory = $response->viewData('pettyHistory');
        $this->assertCount(5, $pettyHistory);

        // Verify latest first (record #8 should be first)
        $this->assertEquals(1008.00, (float) $pettyHistory->first()->amount);

        // View more link exists
        $response->assertSee(route('admin.cashbook.shop.history.petty', $this->profile->slug), false);
    }

    public function test_full_history_petty_page_loads_and_paginates(): void
    {
        $pettyType = LedgerEntryType::query()->where('code', 'company_to_petty')->firstOrFail();

        for ($i = 1; $i <= 25; $i++) {
            ShopLedgerTransaction::query()->create([
                'shop_id' => $this->shop->id,
                'business_date' => now()->subDays(25 - $i)->toDateString(),
                'entry_type_id' => $pettyType->id,
                'entry_type_code' => 'company_to_petty',
                'direction' => 'in',
                'funding_source' => 'company_bank',
                'amount' => 500.00 + $i,
                'status' => 'approved',
                'notes' => "Petty test #{$i}",
            ]);
        }

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.history.petty', $this->profile->slug)
        );

        $response->assertOk();
        $response->assertSee('Petty Cash Transactions');
        $response->assertSee('Back to Shop Operation Center');
        $this->assertNotNull($response->viewData('pettyTransactions'));
    }

    public function test_full_history_payments_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.history.payments', $this->profile->slug)
        );

        $response->assertOk();
        $response->assertSee('Payment History');
    }

    public function test_full_history_allocations_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.history.allocations', $this->profile->slug)
        );

        $response->assertOk();
        $response->assertSee('Expense Settlement Allocations');
    }

    public function test_full_history_cheques_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.history.cheques', $this->profile->slug)
        );

        $response->assertOk();
        $response->assertSee('Shop Cheque Receipts');
        $response->assertSee('Back to Shop Operation Center');
    }

    public function test_full_history_adjustments_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.history.adjustments', $this->profile->slug)
        );

        $response->assertOk();
        $response->assertSee('Settlement Adjustments');
        $response->assertSee('Back to Shop Operation Center');
    }

    public function test_full_history_banking_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.history.banking', $this->profile->slug)
        );

        $response->assertOk();
        $response->assertSee('Banking Collections');
        $response->assertSee('Back to Shop Operation Center');
    }

    public function test_real_payment_allocation_loads_and_renders_on_main_and_history_pages(): void
    {
        $settlementType = LedgerEntryType::query()->where('code', 'shop_cash_settlement')->firstOrFail();

        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'payment_method' => 'bank',
            'payment_reference' => 'ALLOC-REF-9999',
            'payment_date' => now()->toDateString(),
            'requested_amount' => 5000.00,
            'approved_amount' => 5000.00,
            'status' => 'approved',
        ]);

        $transaction = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => now()->toDateString(),
            'entry_type_id' => $settlementType->id,
            'entry_type_code' => 'shop_cash_settlement',
            'direction' => 'in',
            'funding_source' => 'company_bank',
            'amount' => 5000.00,
            'status' => 'approved',
        ]);

        $allocation = ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $transaction->id,
            'amount' => 5000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        // 1. Confirm main shop operation page loads with 200 without undefined relation error
        $showResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', $this->profile->slug)
        );
        $showResponse->assertOk();
        $recentAllocations = $showResponse->viewData('recentAllocations');
        $this->assertNotNull($recentAllocations);
        $this->assertTrue($recentAllocations->contains('id', $allocation->id));

        // 2. Confirm dedicated allocations history page loads with 200 and renders allocation
        $historyResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.history.allocations', $this->profile->slug)
        );
        $historyResponse->assertOk();
        $historyResponse->assertSee('ALLOC-REF-9999');
        $historyResponse->assertSee('₹5,000.00');
        $historyResponse->assertSee($this->admin->name);
    }

    public function test_real_adjustment_loads_and_renders_on_main_and_history_pages(): void
    {
        $otherIncomeType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'other_income'],
            ['name' => 'Other Income', 'category' => 'income', 'active' => true]
        );

        $adjustment = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => now()->toDateString(),
            'entry_type_id' => $otherIncomeType->id,
            'direction' => 'in',
            'funding_source' => 'petty',
            'amount' => 750.00,
            'settlement_delta' => 750.00,
            'status' => 'approved',
            'notes' => 'Audited scrap sale adjustment',
            'entered_by' => $this->admin->id,
        ]);

        // 1. Confirm main shop operation page loads with 200 without unknown column error
        $showResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', $this->profile->slug)
        );
        // 2. Confirm dedicated adjustments history page loads with 200 and renders adjustment
        $historyResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.history.adjustments', $this->profile->slug)
        );
        $historyResponse->assertOk();
        $historyResponse->assertSee('Audited scrap sale adjustment');
        $historyResponse->assertSee('₹750.00');
        $historyResponse->assertSee($this->admin->name);
    }

    public function test_section_collapse_states_default_on_initial_page_load(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', $this->profile->slug)
        );
        $response->assertOk();

        // 1. Verified Payments Received must be open by default
        $response->assertSee('Verified Payments Received', false);
        $response->assertSee('x-data="{ expanded: true }"', false);

        // 2. Collapsible sections must have collapsed default
        $response->assertSee('Petty Transactions', false);
        $response->assertSee('Payments &amp; Allocation', false);
        $response->assertSee('Cheques', false);
        $response->assertSee('Banking Verification', false);
        $response->assertSee('Adjustments', false);
        $response->assertSee('x-data="{ expanded: false }"', false);
    }
}
