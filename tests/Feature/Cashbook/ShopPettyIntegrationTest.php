<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopPettyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

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

        $this->shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'code' => 'SH01',
            'name' => 'Test Shop Alpha',
        ]);

        Role::firstOrCreate(['name' => 'shop']);
        Permission::firstOrCreate(['name' => 'sales.order.create']);
        $this->shopOwner = User::factory()->create([
            'shop_id' => $this->shop->id,
        ]);
        $this->shopOwner->assignRole('shop');
        $this->shopOwner->givePermissionTo('sales.order.create');

        $this->profile = ShopLedgerProfile::query()->firstOrCreate(
            ['shop_id' => $this->shop->id],
            [
                'profile_template' => 'owned_standard',
                'name' => 'Test Shop Alpha',
                'code' => 'SH01',
                'status' => 'active',
                'payment_configuration' => [],
            ]
        );

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'Main Operating Bank',
            'account_type' => 'bank',
            'enabled' => true,
            'current_balance' => 50000.00,
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

        LedgerEntryType::query()->firstOrCreate(['code' => 'tea_snacks'], [
            'name' => 'Tea & Snacks',
            'category' => 'expense',
            'active' => true,
        ]);
    }

    public function test_legacy_shop_without_petty_config_allows_existing_petty_expense_but_blocks_add_petty(): void
    {
        // Add Petty action blocked by default for unconfigured shops
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.petty.fund', $this->profile->slug ?: $this->profile->shop_id),
            [
                'business_date' => '2026-09-17',
                'amount' => 5000.00,
                'company_account_id' => $this->bankAccount->id,
            ]
        )->assertSessionHas('error');

        // Existing petty expense continues working for legacy shop
        $this->actingAs($this->shopOwner)->postJson(
            route('shop-owner.cashbook.api.record-entry'),
            [
                'business_date' => '2026-09-17',
                'entry_type_code' => 'tea_snacks',
                'amount' => 150.00,
                'funding_source' => 'petty',
                'notes' => 'Evening tea',
            ]
        )->assertOk();

        $tx = ShopLedgerTransaction::query()->where('funding_source', 'petty')->firstOrFail();
        $this->assertSame(-150.0, (float) $tx->petty_delta);
    }

    public function test_saving_petty_settings_and_funding_shop_petty_preserves_all_accounting_invariants(): void
    {
        // Admin saves payment settings with petty enabled and allow_company_to_petty = true
        $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.settings.shop.payments-configuration.save', $this->profile->slug ?: $this->profile->shop_id),
            [
                'petty' => [
                    'enabled' => true,
                    'allow_company_to_petty' => true,
                    'shop_owner_view_petty' => true,
                    'allow_expenses_from_petty' => true,
                ],
            ]
        )->assertOk();

        $this->profile->refresh();
        $pettyConfig = $this->profile->getPaymentConfiguration()['petty'];
        $this->assertTrue($pettyConfig['configured']);
        $this->assertTrue($pettyConfig['enabled']);
        $this->assertTrue($pettyConfig['allow_company_to_petty']);

        // Admin funds shop petty with ₹5,000
        $initialBalance = (float) $this->bankAccount->fresh()->current_balance;

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.petty.fund', $this->profile->slug ?: $this->profile->shop_id),
            [
                'business_date' => '2026-09-17',
                'amount' => 5000.00,
                'company_account_id' => $this->bankAccount->id,
                'reference' => 'PETTY-FUND-001',
                'notes' => 'Shop float refill',
            ]
        );

        $response->assertSessionHas('success');

        // Company Account decreased by ₹5,000
        $this->assertSame($initialBalance - 5000.00, (float) $this->bankAccount->fresh()->current_balance);

        // Statement entry created
        $statement = CompanyAccountStatementEntry::query()->firstOrFail();
        $this->assertSame('out', $statement->direction);
        $this->assertSame('shop_petty_funding', $statement->source);
        $this->assertSame(5000.0, (float) $statement->amount);

        // ShopLedgerTransaction created
        $tx = ShopLedgerTransaction::query()->whereHas('entryType', fn ($q) => $q->where('code', 'company_to_petty'))->firstOrFail();
        $this->assertSame(5000.0, (float) $tx->petty_delta);
        $this->assertSame(0.0, (float) $tx->settlement_delta);
        $this->assertFalse((bool) $tx->affects_sales);
        $this->assertFalse((bool) $tx->affects_pl);

        // Daily ledger snapshot closing_petty updated
        $snapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-17')
            ->firstOrFail();
        $this->assertSame(5000.0, (float) $snapshot->closing_petty);

        // GL Journal created: Dr 1500, Cr 1020
        $journal = JournalEntry::query()->with('transactions.account')->firstOrFail();
        $debitLine = $journal->transactions->firstWhere('type', 'debit');
        $creditLine = $journal->transactions->firstWhere('type', 'credit');
        $this->assertSame('1500', $debitLine->account->code);
        $this->assertSame(5000.0, (float) $debitLine->amount);
        $this->assertSame('1020', $creditLine->account->code);
        $this->assertSame(5000.0, (float) $creditLine->amount);

        // Verify operation center view loads cleanly with petty cards & history
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug ?: $this->profile->shop_id, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('Shop Petty Cash &amp; Floating Fund', false)
            ->assertSee('5,000.00');

        // Day detail view
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug ?: $this->profile->shop_id, 'date' => '2026-09-17']))
            ->assertOk()
            ->assertSee('Shop Petty Cash &amp; Floating Fund', false)
            ->assertSee('5,000.00');
    }

    public function test_disabling_petty_expenses_blocks_shop_owner_from_using_petty_funding_source(): void
    {
        // Admin disables petty expenses
        $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.settings.shop.payments-configuration.save', $this->profile->slug ?: $this->profile->shop_id),
            [
                'petty' => [
                    'enabled' => true,
                    'allow_company_to_petty' => true,
                    'shop_owner_view_petty' => false,
                    'allow_expenses_from_petty' => false,
                ],
            ]
        )->assertOk();

        // Attempting to record petty expense fails
        $this->actingAs($this->shopOwner)->postJson(
            route('shop-owner.cashbook.api.record-entry'),
            [
                'business_date' => '2026-09-17',
                'entry_type_code' => 'tea_snacks',
                'amount' => 200.00,
                'funding_source' => 'petty',
            ]
        )->assertStatus(422)
            ->assertJsonValidationErrors('funding_source');

        // Verify cashbookData suppresses petty balance when shop_owner_view_petty is false
        $res = $this->actingAs($this->shopOwner)->getJson(
            route('shop-owner.cashbook.api.shop-data', ['business_date' => '2026-09-17'])
        )->assertOk();

        $this->assertNull($res->json('snapshot.closing_petty'));
        $this->assertNull($res->json('snapshot.opening_petty'));
    }
}
