<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\PurchaserCredit;
use App\Models\User;
use App\Services\Finance\PurchaserFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DefaultCompanyAccountPreselectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private CompanyAccount $defaultBank;

    private CompanyAccount $secondaryBank;

    private CompanyAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Test']);
        $this->purchaser->assignRole('purchaser');

        Account::query()->updateOrCreate(['code' => '1010'], ['name' => 'Cash', 'type' => 'asset', 'is_active' => true]);
        Account::query()->updateOrCreate(['code' => '1020'], ['name' => 'Bank', 'type' => 'asset', 'is_active' => true]);
        Account::query()->updateOrCreate(['code' => '1300'], ['name' => 'Purchaser Advances', 'type' => 'asset', 'is_active' => true]);

        $this->defaultBank = CompanyAccount::query()->create([
            'name' => 'Primary HDFC Bank',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'account_number' => '1122334455',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'is_default' => true,
            'enabled' => true,
        ]);

        $this->secondaryBank = CompanyAccount::query()->create([
            'name' => 'Secondary SBI Bank',
            'account_type' => 'bank',
            'bank_name' => 'State Bank of India',
            'account_number' => '9988776655',
            'opening_balance' => 50000.00,
            'current_balance' => 50000.00,
            'is_default' => false,
            'enabled' => true,
        ]);

        $this->cashAccount = CompanyAccount::query()->create([
            'name' => 'Main Office Vault Cash',
            'account_type' => 'cash',
            'opening_balance' => 25000.00,
            'current_balance' => 25000.00,
            'is_default' => false,
            'enabled' => true,
        ]);
    }

    public function test_shared_resolver_identifies_default_account_correctly(): void
    {
        $default = CompanyAccount::getDefaultAccount();
        $this->assertNotNull($default);
        $this->assertEquals($this->defaultBank->id, $default->id);

        $defaultBank = CompanyAccount::getDefaultAccount('bank');
        $this->assertNotNull($defaultBank);
        $this->assertEquals($this->defaultBank->id, $defaultBank->id);

        // No default cash account configured
        $defaultCash = CompanyAccount::getDefaultAccount('cash');
        $this->assertNull($defaultCash);
    }

    public function test_shared_resolver_resolves_selected_id_and_uuid(): void
    {
        $accounts = collect([$this->defaultBank, $this->secondaryBank, $this->cashAccount]);

        // 1. Without explicit selection, default is returned
        $this->assertEquals($this->defaultBank->id, CompanyAccount::resolveSelectedId(null, $accounts));
        $this->assertEquals($this->defaultBank->public_uuid, CompanyAccount::resolveSelectedUuid(null, $accounts));

        // 2. Explicit selection overrides default
        $this->assertEquals($this->secondaryBank->id, CompanyAccount::resolveSelectedId($this->secondaryBank->id, $accounts));
        $this->assertEquals($this->cashAccount->public_uuid, CompanyAccount::resolveSelectedUuid($this->cashAccount->public_uuid, $accounts));

        // 3. Form-specific eligibility: cash-only list ignores default bank
        $cashOnlyAccounts = collect([$this->cashAccount]);
        $this->assertNull(CompanyAccount::resolveSelectedId(null, $cashOnlyAccounts));

        // 4. Form-specific eligibility: bank-only list includes default bank
        $bankOnlyAccounts = collect([$this->defaultBank, $this->secondaryBank]);
        $this->assertEquals($this->defaultBank->id, CompanyAccount::resolveSelectedId(null, $bankOnlyAccounts, 'bank'));

        // 5. isSelected helper
        $this->assertTrue(CompanyAccount::isSelected($this->defaultBank, null, $accounts));
        $this->assertFalse(CompanyAccount::isSelected($this->secondaryBank, null, $accounts));
        $this->assertTrue(CompanyAccount::isSelected($this->secondaryBank, $this->secondaryBank->id, $accounts));
        $this->assertFalse(CompanyAccount::isSelected($this->defaultBank, $this->secondaryBank->id, $accounts));
    }

    public function test_purchaser_finance_page_preselects_default_account(): void
    {
        $res = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'tab' => 'finance',
        ]));

        $res->assertOk();
        // Check that option for default bank has selected attribute
        $res->assertSee('value="'.$this->defaultBank->id.'" selected', false);
        $res->assertDontSee('value="'.$this->secondaryBank->id.'" selected', false);
    }

    public function test_changing_default_account_affects_preselection(): void
    {
        // Change default to cash account
        CompanyAccount::query()->where('id', '!=', $this->cashAccount->id)->update(['is_default' => false]);
        $this->cashAccount->update(['is_default' => true]);

        $res = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'tab' => 'finance',
        ]));

        $res->assertOk();
        $res->assertSee('value="'.$this->cashAccount->id.'" selected', false);
        $res->assertDontSee('value="'.$this->defaultBank->id.'" selected', false);
    }

    public function test_funding_created_with_preselected_account_remains_unmatched_and_creates_single_journal(): void
    {
        $res = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', [
            'purchaser' => $this->purchaser->public_uuid,
        ]), [
            'amount' => 15000.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->defaultBank->id,
            'business_date' => '2026-08-27',
            'description' => 'Advance via default bank',
        ]);

        $res->assertRedirect();

        $credit = PurchaserCredit::query()->where('purchaser_id', $this->purchaser->id)->first();
        $this->assertNotNull($credit);
        $this->assertEquals(15000.00, (float) $credit->amount);
        $this->assertEquals($this->defaultBank->id, $credit->company_account_id);

        // Status must be UNMATCHED
        $service = app(PurchaserFinanceService::class);
        $tx = $service->transactionsFor($this->purchaser->id, '2026-08-01', '2026-08-31');
        $this->assertEquals('unmatched', collect($tx->items())->firstWhere('id', $credit->id)->status);

        // Exactly 1 JournalEntry
        $this->assertEquals(1, JournalEntry::query()->where('source_type', PurchaserCredit::class)->where('source_id', $credit->id)->count());

        // Zero synthetic statement entries created
        $this->assertEquals(0, CompanyAccountStatementEntry::query()->where('company_account_id', $this->defaultBank->id)->count());
    }

    public function test_bank_only_forms_do_not_preselect_cash_default(): void
    {
        // Set cash account as default
        CompanyAccount::query()->update(['is_default' => false]);
        $this->cashAccount->update(['is_default' => true]);

        // Direct sales has bank-only accounts
        $bankAccounts = CompanyAccount::query()->where('account_type', 'bank')->get();
        $this->assertNull(CompanyAccount::resolveSelectedUuid(null, $bankAccounts, 'bank'));
        $this->assertFalse(CompanyAccount::isSelected($this->secondaryBank, null, $bankAccounts, 'bank', 'public_uuid'));
    }

    public function test_no_default_configured_leaves_empty_selection(): void
    {
        CompanyAccount::query()->update(['is_default' => false]);
        $accounts = CompanyAccount::query()->get();

        $this->assertNull(CompanyAccount::getDefaultAccount());
        $this->assertNull(CompanyAccount::resolveSelectedId(null, $accounts));
        $this->assertNull(CompanyAccount::resolveSelectedUuid(null, $accounts));

        foreach ($accounts as $acc) {
            $this->assertFalse(CompanyAccount::isSelected($acc, null, $accounts));
        }
    }
}
