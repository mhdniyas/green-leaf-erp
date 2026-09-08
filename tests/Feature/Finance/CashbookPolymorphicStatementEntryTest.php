<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookPolymorphicStatementEntryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected CompanyAccount $bankAccount;

    protected Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.test',
        ]);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create(['name' => 'Kalyan Test Shop']);

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'Main Test Bank',
            'account_number' => 'ACC-98765',
            'bank_name' => 'HDFC Bank',
            'account_type' => 'bank',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);
    }

    public function test_statement_views_render_safely_when_entries_link_to_shop_invoice_payment_requests_and_other_morphs(): void
    {
        Carbon::setTestNow('2026-09-08');

        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'request_type' => 'shop_balance',
            'payment_method' => 'bank_transfer',
            'requested_amount' => 15000.00,
            'approved_amount' => 15000.00,
            'reconciled_amount' => 15000.00,
            'floating_amount' => 0.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'requested_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $statementEntryPayment = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => now()->toDateString(),
            'amount' => 15000.00,
            'direction' => 'in',
            'reference' => 'PAY-REQ-TEST-001',
            'status' => 'reconciled',
            'is_finalized' => true,
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $paymentRequest->id,
        ]);

        $purchaserCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->admin->id,
            'company_account_id' => $this->bankAccount->id,
            'type' => 'credit',
            'amount' => 5000.00,
            'created_by' => $this->admin->id,
            'business_date' => now()->toDateString(),
            'created_at' => now(),
        ]);

        $statementEntryCredit = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => now()->toDateString(),
            'amount' => 5000.00,
            'direction' => 'out',
            'reference' => 'PURCHASER-CREDIT-001',
            'status' => 'unmatched',
            'is_finalized' => false,
            'source_type' => PurchaserCredit::class,
            'source_id' => $purchaserCredit->id,
        ]);

        // 1. Bank Account Statement page
        $responseStatement = $this->actingAs($this->admin)->get(route('admin.cashbook.bank-accounts.statement', [
            'account' => $this->bankAccount->id,
            'month' => '2026-09',
        ]));

        $responseStatement->assertOk();
        $responseStatement->assertSee('PAY-REQ-TEST-001');
        $responseStatement->assertSee('Kalyan Test Shop');

        // 2. Bank Account Show page
        $responseShow = $this->actingAs($this->admin)->get(route('admin.cashbook.bank-accounts.show', [
            'account' => $this->bankAccount->id,
        ]));

        $responseShow->assertOk();
        $responseShow->assertSee('PAY-REQ-TEST-001');

        // 3. Statement / Finance page
        $responseReview = $this->actingAs($this->admin)->get(route('admin.cashbook.finance'));

        $responseReview->assertOk();
        $responseReview->assertViewHas('statementEntries');

        Carbon::setTestNow();
    }
}
