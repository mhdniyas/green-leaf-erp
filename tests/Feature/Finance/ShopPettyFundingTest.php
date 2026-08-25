<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopPettyFundingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private CompanyAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
        $this->shop = Shop::factory()->create(['accounting_enabled' => true, 'accounting_mode' => 'owned']);
        $this->bankAccount = CompanyAccount::query()->create(['name' => 'Test Bank', 'account_type' => 'bank', 'enabled' => true]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1500'], ['name' => 'Shop Petty Advances', 'type' => 'asset', 'is_active' => true]);
        LedgerEntryType::query()->create(['code' => 'company_to_petty', 'name' => 'Company to Petty', 'category' => 'transfer', 'active' => true]);
    }

    public function test_shop_petty_funding_creates_one_out_movement_counterpart_and_journal_then_finalizes_once(): void
    {
        $payload = $this->payload();
        $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.pay-shop'), $payload)->assertOk();
        $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.pay-shop'), $payload)->assertOk();

        $statement = CompanyAccountStatementEntry::query()->firstOrFail();
        $transaction = ShopLedgerTransaction::query()->firstOrFail();
        $journal = JournalEntry::query()->with('transactions.account')->firstOrFail();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, ShopLedgerTransaction::query()->count());
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame('out', $statement->direction);
        $this->assertFalse($statement->is_finalized);
        $this->assertSame(2000.0, (float) $statement->amount);
        $this->assertSame(2000.0, (float) $transaction->petty_delta);
        $this->assertSame($this->bankAccount->id, $transaction->company_account_id);
        $this->assertSame(ShopLedgerTransaction::class, $statement->source_type);
        $this->assertSame($transaction->id, $statement->source_id);
        $this->assertSame('1500', $journal->transactions->firstWhere('type', 'debit')->account->code);
        $this->assertSame('1020', $journal->transactions->firstWhere('type', 'credit')->account->code);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.match-journal', $statement->secureRouteKey()), ['journal_entry_id' => $journal->id, 'cleared_amount' => 2000.00])->assertRedirect();

        $this->assertTrue($statement->fresh()->is_finalized);
        $this->assertTrue($journal->fresh()->is_finalized);
        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'))->assertOk()->assertSee('Shop Petty Advances');
    }

    public function test_invalid_or_forged_public_uuids_are_rejected(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.pay-shop'), array_merge($this->payload(), ['company_account_uuid' => (string) Str::uuid()]))->assertUnprocessable();
        $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.pay-shop'), array_merge($this->payload(), ['shop_uuid' => (string) Str::uuid()]))->assertUnprocessable();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['shop_uuid' => $this->shop->public_uuid, 'company_account_uuid' => $this->bankAccount->public_uuid, 'request_uuid' => (string) Str::uuid(), 'business_date' => '2026-08-22', 'amount' => 2000.00, 'reference' => 'PETTY-001', 'notes' => 'Petty top-up'];
    }
}
