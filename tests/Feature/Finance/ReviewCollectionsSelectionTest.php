<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCollectionsSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $bankAccount;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $cpType;

    private LedgerEntryType $salaryType;

    private DailyLedgerService $dailyLedgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['name' => 'Non Admin User', 'email' => 'user@greenleaf.test']);

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->shop = Shop::factory()->create(['name' => 'Casio', 'code' => 'AV_CASIO', 'status' => 'active']);
        $this->profile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'av-casio-casio',
            'code' => $this->shop->code,
            'name' => $this->shop->name,
            'enabled' => true,
        ]);

        $this->bankAccount = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'bank_name' => 'Kotak Mahindra Bank',
            'account_number' => '9876543210',
            'account_type' => 'bank',
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'paytm'],
            ['name' => 'Paytm', 'category' => 'income', 'is_active' => true]
        );
        $this->cardType = LedgerEntryType::firstOrCreate(
            ['code' => 'card'],
            ['name' => 'Card', 'category' => 'income', 'is_active' => true]
        );
        $this->cashSalesType = LedgerEntryType::firstOrCreate(
            ['code' => 'cash_sales'],
            ['name' => 'Cash Sales', 'category' => 'income', 'is_active' => true]
        );
        $this->cpType = LedgerEntryType::firstOrCreate(
            ['code' => 'income_cp'],
            ['name' => 'CP', 'category' => 'income', 'is_active' => true]
        );
        $this->salaryType = LedgerEntryType::firstOrCreate(
            ['code' => 'salary_2'],
            ['name' => 'Salary', 'category' => 'income', 'is_active' => true]
        );

        $this->dailyLedgerService = app(DailyLedgerService::class);
    }

    public function test_review_collections_section_renders_all_entries_with_canonical_paise_data(): void
    {
        $businessDate = '2026-08-11';

        // Mapped: Paytm -> Kotak Bank, Card -> Kotak Bank
        // Unmapped: Cash Sales, CP, Salary -> "Destination account not configured"
        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->paytmType->id],
            ['company_account_id' => $this->bankAccount->id, 'is_active' => true, 'effective_from' => '2026-01-01']
        );
        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->bankAccount->id, 'is_active' => true, 'effective_from' => '2026-01-01']
        );

        // Exact Casio amounts on 2026-08-11
        $txPaytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 26296.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $txCard = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 7636.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $txCash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 4300.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $txCp = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cpType->code,
            'amount' => 600.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $txSalary = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->salaryType->code,
            'amount' => 350.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'date' => $businessDate,
        ]));

        $response->assertOk()
            ->assertSee('Review Collections (5)')
            ->assertSee('₹26,296.00')
            ->assertSee('₹7,636.00')
            ->assertSee('₹4,300.00')
            ->assertSee('₹600.00')
            ->assertSee('₹350.00')
            ->assertSee('masterAcceptanceCheckbox')
            ->assertSee(':value="'.$txPaytm['transaction']->id.'"', false)
            ->assertSee(':value="'.$txCard['transaction']->id.'"', false)
            ->assertSee(':value="'.$txCash['transaction']->id.'"', false)
            ->assertSee(':value="'.$txCp['transaction']->id.'"', false)
            ->assertSee(':value="'.$txSalary['transaction']->id.'"', false)
            ->assertSee('x-model.number="selectedIds"', false)
            ->assertSee('Destination account not configured');

        // Check canonical paise data injected into Alpine x-data
        $content = $response->getContent();
        $this->assertStringContainsString('2629600', $content);
        $this->assertStringContainsString('763600', $content);
        $this->assertStringContainsString('430000', $content);
        $this->assertStringContainsString('60000', $content);
        $this->assertStringContainsString('35000', $content);
    }

    public function test_batch_acceptance_submits_exact_selected_ids_and_updates_status(): void
    {
        $businessDate = '2026-08-11';

        $txPaytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 26296.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $txCard = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 7636.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $txCash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 4300.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Accept only Paytm & Card (2 entries: ₹33,932.00)
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.day.accept-selected', $this->profile->slug),
            [
                'business_date' => $businessDate,
                'transaction_ids' => [$txPaytm['transaction']->id, $txCard['transaction']->id],
            ]
        );

        $response->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'date' => $businessDate,
        ]));
        $response->assertSessionHas('success');

        // Status updated to approved for selected entries
        $this->assertSame('approved', $txPaytm['transaction']->fresh()->status);
        $this->assertSame('approved', $txCard['transaction']->fresh()->status);

        // Unselected cash sales remains posted
        $this->assertSame('posted', $txCash['transaction']->fresh()->status);
    }

    public function test_batch_acceptance_handles_unmapped_destination_accounts_gracefully(): void
    {
        $businessDate = '2026-08-11';

        // Cash, CP, Salary without account mapping
        $txCash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 4300.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $txCp = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cpType->code,
            'amount' => 600.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.day.accept-selected', $this->profile->slug),
            [
                'business_date' => $businessDate,
                'transaction_ids' => [$txCash['transaction']->id, $txCp['transaction']->id],
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame('approved', $txCash['transaction']->fresh()->status);
        $this->assertSame('approved', $txCp['transaction']->fresh()->status);
    }

    public function test_batch_acceptance_rejects_empty_selection(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.day.accept-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-11',
                'transaction_ids' => [],
            ]
        );

        $response->assertSessionHasErrors('transaction_ids');
    }

    public function test_batch_acceptance_rejects_transactions_from_another_shop(): void
    {
        $otherShop = Shop::factory()->create(['name' => 'Other Shop', 'code' => 'OTHER', 'status' => 'active']);

        $txOther = $this->dailyLedgerService->recordEntry([
            'shop_id' => $otherShop->id,
            'business_date' => '2026-08-11',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.day.accept-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-11',
                'transaction_ids' => [$txOther['transaction']->id],
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('posted', $txOther['transaction']->fresh()->status);
    }

    public function test_unauthorized_user_cannot_accept_collections(): void
    {
        $response = $this->actingAs($this->cashier)->post(
            route('admin.cashbook.shop.day.accept-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-11',
                'transaction_ids' => [1],
            ]
        );

        $response->assertForbidden();
    }
}
