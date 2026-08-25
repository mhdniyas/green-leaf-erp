<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\CompanyAccountingEntry;
use App\Models\CompanyPayableSettlement;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Models\VendorSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookActionCenterPhaseD2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CompanyAccount $bankCompanyAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->bankCompanyAccount = CompanyAccount::query()->create([
            'name' => 'Action Center Bank',
            'account_type' => 'bank',
            'bank_name' => 'Test Bank',
            'enabled' => true,
        ]);

        $this->seedAccounts();
    }

    public function test_statement_first_company_payable_reuses_statement_and_appears_once(): void
    {
        $payableLine = $this->approvedCompanyPayableLine(1200.00);
        $statement = $this->importedOutStatement(600.00, 'PAYABLE-STMT-1');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-company-payable', $statement), [
                'shop_accounting_entry_line_id' => $payableLine->id,
                'notes' => 'Payable from statement',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, CompanyPayableSettlement::query()->count());
        $this->assertSame(1, JournalEntry::query()->where('source_type', CompanyPayableSettlement::class)->count());
        $this->assertSame(0, CompanyAccountingEntry::query()->count());

        $settlement = CompanyPayableSettlement::query()->firstOrFail();
        $statement = $statement->fresh();
        $this->assertSame($settlement->id, $statement->source_id);
        $this->assertSame(CompanyPayableSettlement::class, $statement->source_type);
        $this->assertSame('company_payable', $statement->source);
        $this->assertTrue((bool) $statement->is_finalized);
        $this->assertSame(600.00, (float) $settlement->amount);
        $this->assertSame(600.00, $payableLine->fresh()->remainingCompanyPayableAmount());

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-company-payable', $statement), [
                'shop_accounting_entry_line_id' => $payableLine->id,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(1, CompanyPayableSettlement::query()->count());

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $settlement->journalEntry->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Company Payable')
            ->assertSee('600.00');
    }

    public function test_statement_first_vendor_payment_handles_multi_bill_discount_and_advance(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'D2 Vendor', 'credit_approved' => true]);
        $invoiceA = $this->vendorInvoice($supplier, 'D2-V-001', 1000.00);
        $invoiceB = $this->vendorInvoice($supplier, 'D2-V-002', 800.00);
        VendorAdvance::query()->create([
            'supplier_id' => $supplier->id,
            'amount_original' => 200.00,
            'amount_remaining' => 200.00,
            'business_date' => '2026-08-10',
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);
        $statement = $this->importedOutStatement(1000.00, 'VENDOR-STMT-1');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-vendor-payment', $statement), [
                'supplier_id' => $supplier->id,
                'invoice_ids' => [$invoiceA->id, $invoiceB->id],
                'use_vendor_advance' => '1',
                'difference_treatment' => 'discount',
                'allocation_order' => 'oldest',
                'note' => 'Vendor statement settlement',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, VendorSettlement::query()->count());
        $this->assertSame(1, JournalEntry::query()->where('source_type', VendorSettlement::class)->count());

        $settlement = VendorSettlement::query()->with('allocations')->firstOrFail();
        $statement = $statement->fresh();
        $this->assertSame($settlement->id, $statement->source_id);
        $this->assertSame(VendorSettlement::class, $statement->source_type);
        $this->assertSame('vendor_settlement', $statement->source);
        $this->assertTrue((bool) $statement->is_finalized);
        $this->assertTrue((bool) $settlement->is_finalized);
        $this->assertSame(1000.00, (float) $settlement->actual_payment_amount);
        $this->assertSame(600.00, (float) $settlement->settlement_discount_amount);
        $this->assertSame(200.00, (float) $settlement->vendor_advance_used_amount);
        $this->assertCount(2, $settlement->allocations);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $settlement->journalEntry->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Vendor Settlement')
            ->assertSee('1,800.00');
    }

    public function test_statement_first_purchaser_funding_reuses_statement_and_finalizes_once(): void
    {
        $purchaser = User::factory()->create(['name' => 'D2 Purchaser']);
        $purchaser->assignRole('purchaser');
        $statement = $this->importedOutStatement(5000.00, 'PURCH-STMT-1');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-purchaser-funding', $statement), [
                'purchaser_uuid' => $purchaser->public_uuid,
                'description' => 'Statement purchaser funding',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, PurchaserCredit::query()->count());
        $this->assertSame(1, JournalEntry::query()->where('source_type', PurchaserCredit::class)->count());

        $credit = PurchaserCredit::query()->firstOrFail();
        $statement = $statement->fresh();
        $this->assertSame($credit->id, $statement->source_id);
        $this->assertSame(PurchaserCredit::class, $statement->source_type);
        $this->assertSame('purchaser_funding', $statement->source);
        $this->assertTrue((bool) $statement->is_finalized);
        $this->assertSame(5000.00, (float) $credit->amount);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-purchaser-funding', $statement), [
                'purchaser_uuid' => $purchaser->public_uuid,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(1, PurchaserCredit::query()->count());

        $journalEntry = JournalEntry::query()->where('source_type', PurchaserCredit::class)->firstOrFail();
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $journalEntry->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Purchaser Funding')
            ->assertSee('5,000.00');
    }

    public function test_statement_first_d2_actions_reject_wrong_direction(): void
    {
        $payableLine = $this->approvedCompanyPayableLine(500.00);
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => '2026-08-22',
            'value_date' => '2026-08-22',
            'direction' => 'in',
            'amount' => 500.00,
            'reference' => 'WRONG-DIR',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-company-payable', $statement), [
                'shop_accounting_entry_line_id' => $payableLine->id,
            ])
            ->assertSessionHasErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-purchaser-funding', $statement), [
                'purchaser_uuid' => $purchaser->public_uuid,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, CompanyPayableSettlement::query()->count());
        $this->assertSame(0, PurchaserCredit::query()->count());
        $this->assertNull($statement->fresh()->journal_entry_id);
    }

    private function importedOutStatement(float $amount, string $reference): CompanyAccountStatementEntry
    {
        return CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => '2026-08-22',
            'value_date' => '2026-08-22',
            'direction' => 'out',
            'amount' => $amount,
            'reference' => $reference,
            'narration' => 'Imported D2 statement row',
            'source' => 'bank_import',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);
    }

    private function approvedCompanyPayableLine(float $amount): ShopAccountingEntryLine
    {
        $shop = Shop::factory()->create(['name' => 'D2 Shop']);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-22',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'expense',
            'name' => 'Repairs',
            'is_active' => true,
        ]);

        return ShopAccountingEntryLine::query()->create([
            'shop_accounting_entry_id' => $entry->id,
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'cash_effect' => true,
            'funding_source' => ShopAccountingEntryLine::FundingCompany,
            'company_payable_status' => ShopAccountingEntryLine::PayableApproved,
            'company_payable_amount' => $amount,
            'company_approved_amount' => $amount,
            'company_settled_amount' => 0,
            'company_settlement_status' => ShopAccountingEntryLine::SettlementUnsettled,
            'amount' => $amount,
            'description' => 'Company-funded shop repair',
        ])->fresh(['entry.shop', 'category', 'settlements']);
    }

    private function vendorInvoice(Supplier $supplier, string $number, float $amount): PurchaseInvoice
    {
        return PurchaseInvoice::factory()->for($supplier)->create([
            'supplier_id' => $supplier->id,
            'invoice_number' => $number,
            'amount' => $amount,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
        ]);
    }

    private function seedAccounts(): void
    {
        foreach ([
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => 'asset'],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Graded Inventory', 'type' => 'asset'],
            ['code' => '1300', 'name' => 'Purchaser Advances', 'type' => 'asset'],
            ['code' => '1400', 'name' => 'Vendor Advances', 'type' => 'asset'],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '2200', 'name' => 'Company Payable to Shops', 'type' => 'liability'],
            ['code' => '4200', 'name' => 'Vendor Settlement Discounts', 'type' => 'revenue'],
            ['code' => '5900', 'name' => 'Shop Expense', 'type' => 'expense'],
        ] as $account) {
            Account::query()->firstOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'is_active' => true,
                    'parent_id' => null,
                ],
            );
        }
    }
}
