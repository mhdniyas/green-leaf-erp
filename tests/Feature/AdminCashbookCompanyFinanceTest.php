<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCashbookCompanyFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('admin.user_access.main_admin_email', 'admin@greenleaf.com');
        Role::findOrCreate('admin', 'web');
        $this->seed(LedgerEntryTypeSeeder::class);
    }

    public function test_admin_can_partially_reconcile_shop_payment_and_keep_balance_floating(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        [$paymentRequest, $account, $statementEntry] = $this->paymentSetup(10000, 6000);

        app(CompanyPaymentReconciliationService::class)->reconcilePayment($paymentRequest, [
            'company_account_id' => $account->id,
            'statement_entry_id' => $statementEntry->id,
            'statement_amount' => 6000,
            'cleared_amount' => 6000,
            'difference_action' => 'keep_floating',
        ], $admin->id);

        $paymentRequest->refresh();
        $statementEntry->refresh();
        $account->refresh();

        $this->assertSame('partially_reconciled', $paymentRequest->status);
        $this->assertSame('partially_reconciled', $paymentRequest->reconciliation_status);
        $this->assertSame(6000.00, (float) $paymentRequest->reconciled_amount);
        $this->assertSame(4000.00, (float) $paymentRequest->floating_amount);
        $this->assertSame(0.00, (float) $paymentRequest->shop_advance_amount);
        $this->assertSame('reconciled', $statementEntry->status);
        $this->assertSame(6000.00, (float) $account->current_balance);
    }

    public function test_admin_reconciliation_tracks_overpayment_as_shop_advance(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        [$paymentRequest, $account, $statementEntry] = $this->paymentSetup(10000, 12000);

        app(CompanyPaymentReconciliationService::class)->reconcilePayment($paymentRequest, [
            'company_account_id' => $account->id,
            'statement_entry_id' => $statementEntry->id,
            'statement_amount' => 12000,
            'cleared_amount' => 12000,
            'difference_action' => 'none',
        ], $admin->id);

        $paymentRequest->refresh();
        $account->refresh();

        $this->assertSame('approved', $paymentRequest->status);
        $this->assertSame('reconciled', $paymentRequest->reconciliation_status);
        $this->assertSame(12000.00, (float) $paymentRequest->reconciled_amount);
        $this->assertSame(0.00, (float) $paymentRequest->floating_amount);
        $this->assertSame(2000.00, (float) $paymentRequest->shop_advance_amount);
        $this->assertSame(2000.00, (float) $paymentRequest->credit_amount);
        $this->assertSame(12000.00, (float) $account->current_balance);
    }

    public function test_admin_reconciliation_auto_creates_statement_entry_when_none_selected(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        [$paymentRequest, $account] = $this->paymentSetup(3500, 3500);

        CompanyAccountStatementEntry::query()->delete();
        $account->update(['current_balance' => 0]);

        $reconciliation = app(CompanyPaymentReconciliationService::class)->reconcilePayment($paymentRequest, [
            'company_account_id' => $account->id,
            'statement_amount' => 3500,
            'cleared_amount' => 3500,
            'difference_action' => 'none',
            'admin_note' => 'Bank transfer received.',
        ], $admin->id);

        $paymentRequest->refresh();
        $account->refresh();
        $statementEntry = CompanyAccountStatementEntry::query()->first();

        $this->assertNotNull($statementEntry);
        $this->assertSame($statementEntry->id, $reconciliation->statement_entry_id);
        $this->assertSame($account->id, $statementEntry->company_account_id);
        $this->assertSame('reconciliation', $statementEntry->source);
        $this->assertSame('reconciled', $statementEntry->status);
        $this->assertSame(3500.00, (float) $statementEntry->amount);
        $this->assertSame(3500.00, (float) $statementEntry->matched_amount);
        $this->assertSame(3500.00, (float) $account->current_balance);
        $this->assertSame('reconciled', $paymentRequest->reconciliation_status);
    }

    public function test_cash_in_hand_reconciliation_creates_cash_statement_and_balance(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        [$paymentRequest, $account] = $this->paymentSetup(900, 900);
        $account->update([
            'name' => 'Cash In Hand',
            'account_type' => 'cash',
            'current_balance' => 0,
        ]);
        CompanyAccountStatementEntry::query()->delete();

        app(CompanyPaymentReconciliationService::class)->reconcilePayment($paymentRequest, [
            'company_account_id' => $account->id,
            'cleared_amount' => 900,
            'difference_action' => 'none',
            'admin_note' => 'Cash received by office.',
        ], $admin->id);

        $account->refresh();
        $statementEntry = CompanyAccountStatementEntry::query()->first();

        $this->assertSame('cash', $account->account_type);
        $this->assertSame($account->id, $statementEntry->company_account_id);
        $this->assertSame('reconciliation', $statementEntry->source);
        $this->assertSame('reconciled', $statementEntry->status);
        $this->assertSame(900.00, (float) $statementEntry->amount);
        $this->assertSame(900.00, (float) $account->current_balance);
    }

    public function test_manual_cash_in_hand_statement_updates_account_balance(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $account = CompanyAccount::query()->create([
            'name' => 'Cash In Hand',
            'account_type' => 'cash',
            'opening_balance' => 100,
            'current_balance' => 100,
            'enabled' => true,
        ]);

        app(CompanyPaymentReconciliationService::class)->createStatementEntry([
            'company_account_id' => $account->id,
            'transaction_date' => '2026-08-18',
            'direction' => 'in',
            'amount' => 250,
            'reference' => 'CASH-001',
            'narration' => 'Cash counted in office.',
        ], $admin->id);

        $account->refresh();

        $this->assertSame(350.00, (float) $account->current_balance);
    }

    public function test_main_admin_can_open_company_finance_page(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $this->paymentSetup(5000, 5000);

        $this->actingAs($admin)
            ->get(route('admin.cashbook.finance'))
            ->assertOk()
            ->assertSee('Company Finance')
            ->assertSee('Pending Shop Payments')
            ->assertSee('Add Statement Entry');
    }

    public function test_shop_payment_request_enters_company_finance_as_floating(): void
    {
        $user = User::factory()->create();
        [$paymentRequest] = $this->paymentSetup(5000, 5000);
        $invoice = $paymentRequest->invoice;

        $paymentRequest->delete();

        $created = app(ShopInvoiceService::class)->requestPayment($invoice, [
            'amount_mode' => 'custom',
            'amount' => 5000,
            'payment_method' => 'online_upi',
            'payment_reference' => 'UTR-FLOATING-001',
        ], $user->id);

        $this->assertSame('pending', $created->status);
        $this->assertSame('floating', $created->reconciliation_status);
        $this->assertSame(0.00, (float) $created->reconciled_amount);
        $this->assertSame(5000.00, (float) $created->floating_amount);
        $this->assertSame(0.00, (float) $created->shop_advance_amount);
    }

    private function paymentSetup(float $requestedAmount, float $statementAmount): array
    {
        $shop = Shop::factory()->create([
            'name' => 'Finance Test Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'enabled' => true,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-18',
            'final_total' => 10000,
            'paid_amount' => 0,
            'balance_amount' => 10000,
        ]);

        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $shop->id,
            'requested_by' => User::factory()->create()->id,
            'request_type' => 'custom',
            'payment_method' => 'online_upi',
            'payment_reference' => 'UTR-TEST-001',
            'payment_date' => '2026-08-18',
            'requested_amount' => $requestedAmount,
            'status' => 'pending',
            'reconciliation_status' => 'floating',
            'floating_amount' => $requestedAmount,
        ]);

        $account = CompanyAccount::query()->create([
            'name' => 'Main Bank',
            'account_type' => 'bank',
            'opening_balance' => 0,
            'current_balance' => $statementAmount,
            'enabled' => true,
        ]);

        $statementEntry = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $account->id,
            'transaction_date' => '2026-08-18',
            'direction' => 'in',
            'amount' => $statementAmount,
            'reference' => 'BANK-TEST-001',
            'status' => 'unmatched',
            'matched_amount' => 0,
        ]);

        return [$paymentRequest, $account, $statementEntry];
    }
}
