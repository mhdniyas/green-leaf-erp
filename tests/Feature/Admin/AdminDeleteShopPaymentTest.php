<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\ShopPaymentLedgerReconciliationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDeleteShopPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $companyAccount;

    private ShopPaymentLedgerReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        config(['admin.user_access.main_admin_email' => 'admin-delete-test@example.com']);
        $this->admin = User::factory()->create([
            'email' => 'admin-delete-test@example.com',
        ]);
        $this->admin->assignRole('admin');

        $this->nonAdmin = User::factory()->create();
        $this->nonAdmin->assignRole('shop');

        $this->shop = Shop::factory()->create(['name' => 'Casio Shop', 'code' => 'CASIO']);
        $this->profile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'uuid' => (string) Str::uuid(),
            'slug' => 'casio-shop',
            'code' => $this->shop->code,
            'name' => $this->shop->name,
            'enabled' => true,
        ]);

        $this->companyAccount = CompanyAccount::query()->create([
            'name' => 'Main Company Bank',
            'account_type' => 'bank',
            'current_balance' => 10000.00,
            'enabled' => true,
        ]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);

        foreach ([
            ['code' => 'cash_sales', 'name' => 'Cash Sales', 'category' => 'income'],
            ['code' => 'rent_expense', 'name' => 'Rent', 'category' => 'expense'],
            ['code' => 'shop_paid_company', 'name' => 'Shop Paid Company', 'category' => 'settlement'],
        ] as $entryType) {
            LedgerEntryType::query()->firstOrCreate(['code' => $entryType['code']], $entryType + ['active' => true]);
        }

        $this->reconciliationService = app(ShopPaymentLedgerReconciliationService::class);
    }

    public function test_admin_sees_delete_payment_action_in_modal(): void
    {
        $payment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 1500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank',
            'company_account_id' => $this->companyAccount->id,
            'payment_reference' => 'REF-MODAL-TEST',
            'notes' => 'Test modal view',
        ], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
        ]));

        $response->assertOk();
        $response->assertSee('Delete Payment');
        $response->assertSee(route('admin.cashbook.shop.payments.destroy', $this->profile->slug));
        $response->assertSee('Are you sure you want to delete this payment? Any linked allocation or reconciliation will be reversed where required.');
    }

    public function test_non_admin_does_not_see_delete_payment_action(): void
    {
        $payment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 1500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank',
            'company_account_id' => $this->companyAccount->id,
            'payment_reference' => 'REF-MODAL-TEST',
            'notes' => 'Test modal view',
        ], $this->admin->id);

        $response = $this->actingAs($this->nonAdmin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
        ]));

        if ($response->status() === 200) {
            $response->assertDontSee('Delete Payment');
        } else {
            $this->assertTrue($response->isRedirection() || $response->isForbidden());
        }
    }

    public function test_unauthorized_user_cannot_call_delete_payment_endpoint(): void
    {
        $payment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 2000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank',
            'company_account_id' => $this->companyAccount->id,
        ], $this->admin->id);

        $response = $this->actingAs($this->nonAdmin)->deleteJson(route('admin.cashbook.shop.payments.destroy', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $payment->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('shop_invoice_payment_requests', ['id' => $payment->id]);
    }

    public function test_admin_can_delete_unallocated_received_payment(): void
    {
        $initialBalance = (float) $this->companyAccount->fresh()->current_balance;

        $payment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 3000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank',
            'company_account_id' => $this->companyAccount->id,
            'payment_reference' => 'REF-UNALLOCATED',
            'notes' => 'Unallocated test payment',
        ], $this->admin->id);

        $this->assertEquals($initialBalance + 3000.00, (float) $this->companyAccount->fresh()->current_balance);
        $this->assertDatabaseHas('shop_invoice_payment_requests', ['id' => $payment->id]);
        $this->assertDatabaseHas('cashbook_company_payment_reconciliations', ['payment_request_id' => $payment->id]);
        $this->assertDatabaseHas('cashbook_company_account_statement_entries', [
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $payment->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.cashbook.shop.payments.destroy', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $payment->id,
            'month' => now()->format('Y-m'),
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'month' => now()->format('Y-m'),
        ]));
        $response->assertSessionHas('success');

        // Verify full reversal
        $this->assertDatabaseMissing('shop_invoice_payment_requests', ['id' => $payment->id]);
        $this->assertDatabaseMissing('cashbook_company_payment_reconciliations', ['payment_request_id' => $payment->id]);
        $this->assertDatabaseMissing('cashbook_company_account_statement_entries', [
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $payment->id,
        ]);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $payment->id,
        ]);
        $this->assertEquals($initialBalance, (float) $this->companyAccount->fresh()->current_balance);
    }

    public function test_admin_can_delete_partially_and_fully_allocated_payment(): void
    {
        $ledgerService = app(DailyLedgerService::class);
        $tx = $ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => now()->toDateString(),
            'entry_type_code' => 'cash_sales',
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $payment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 5000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank',
            'company_account_id' => $this->companyAccount->id,
            'payment_reference' => 'REF-ALLOCATED',
        ], $this->admin->id);

        // Allocate 2000 of 5000
        $this->reconciliationService->allocatePayment($payment, [
            ['ledger_transaction_id' => $tx->id, 'amount' => 2000.00],
        ], $this->admin->id);

        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'payment_request_id' => $payment->id,
            'shop_ledger_transaction_id' => $tx->id,
            'amount' => 2000.00,
        ]);

        // Delete payment
        $response = $this->actingAs($this->admin)->delete(route('admin.cashbook.shop.payments.destroy', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $payment->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify allocation rows are removed with no orphan records
        $this->assertDatabaseMissing('shop_payment_ledger_allocations', [
            'payment_request_id' => $payment->id,
        ]);
        $this->assertDatabaseMissing('shop_invoice_payment_requests', [
            'id' => $payment->id,
        ]);
    }

    public function test_admin_can_delete_payment_reconciled_with_imported_statement(): void
    {
        $importedStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'value_date' => now()->toDateString(),
            'entry_type' => 'bank_transfer',
            'direction' => 'in',
            'amount' => 4500.00,
            'reference' => 'IMP-STATEMENT-99',
            'source' => 'imported',
            'import_file_name' => 'bank_statement_sep.csv',
            'status' => 'reconciled',
            'is_finalized' => true,
        ]);

        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank',
            'payment_reference' => 'IMP-STATEMENT-99',
            'payment_date' => now()->toDateString(),
            'requested_amount' => 4500.00,
            'approved_amount' => 4500.00,
            'reconciled_amount' => 4500.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $recon = CompanyPaymentReconciliation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_id' => $this->shop->id,
            'company_account_id' => $this->companyAccount->id,
            'statement_entry_id' => $importedStatement->id,
            'statement_amount' => 4500.00,
            'cleared_amount' => 4500.00,
            'difference_amount' => 0.00,
            'difference_action' => 'none',
            'status' => 'approved',
            'is_finalized' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.cashbook.shop.payments.destroy', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $payment->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Payment is deleted
        $this->assertDatabaseMissing('shop_invoice_payment_requests', ['id' => $payment->id]);
        $this->assertDatabaseMissing('cashbook_company_payment_reconciliations', ['id' => $recon->id]);

        // Imported statement entry is reset to unmatched, NOT deleted
        $this->assertDatabaseHas('cashbook_company_account_statement_entries', [
            'id' => $importedStatement->id,
            'status' => 'unmatched',
            'matched_amount' => 0.00,
            'is_finalized' => false,
        ]);
    }

    public function test_admin_can_delete_pending_cheque_payment(): void
    {
        $chequePayment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 7000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cheque',
            'cheque_bank_name' => 'State Bank of India',
            'cheque_date' => now()->addDays(5)->toDateString(),
            'company_account_id' => $this->companyAccount->id,
            'payment_reference' => 'CHQ-882211',
            'notes' => 'Pending cheque payment',
        ], $this->admin->id);

        $this->assertSame('pending', $chequePayment->cheque_status);
        $this->assertSame('floating', $chequePayment->reconciliation_status);

        $response = $this->actingAs($this->admin)->delete(route('admin.cashbook.shop.payments.destroy', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $chequePayment->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shop_invoice_payment_requests', ['id' => $chequePayment->id]);
    }

    public function test_query_parameters_preserved_after_payment_deletion(): void
    {
        $payment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 1200.00,
            'payment_date' => '2026-09-02',
            'payment_method' => 'cash',
            'company_account_id' => $this->companyAccount->id,
        ], $this->admin->id);

        $response = $this->actingAs($this->admin)->delete(route('admin.cashbook.shop.payments.destroy', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
            'date' => '2026-09-02',
            'payments_page' => '2',
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
            'date' => '2026-09-02',
            'payments_page' => '2',
        ]));
    }
}
