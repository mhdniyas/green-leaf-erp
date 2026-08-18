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
            'current_balance' => 0,
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
