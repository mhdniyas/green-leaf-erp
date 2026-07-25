<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\User;
use App\Services\Finance\AdminFinancePillarService;
use App\Services\Purchasing\PurchaseInvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorJournalPostingRemovedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_vendor_invoice_payment_updates_purchaser_ledger_without_purchase_invoice_journal(): void
    {
        $purchaser = User::factory()->create();
        $invoice = PurchaseInvoice::factory()->create([
            'amount' => 25000,
            'status' => InvoiceStatus::Pending->value,
            'payment_status' => 'unpaid',
            'paid_amount' => 0,
            'purchaser_submitted_by' => $purchaser->id,
        ]);

        app(PurchaseInvoiceService::class)->updatePayment($invoice, [
            'payment_method' => 'Cash',
            'discount_amount' => 0,
            'paid_amount' => 25000,
            'payment_note' => 'Paid by purchaser from advance.',
            'payment_details' => null,
        ]);

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('paid', $invoice->payment_status);

        $this->assertDatabaseHas('purchaser_credits', [
            'purchaser_id' => $purchaser->id,
            'purchase_invoice_id' => $invoice->id,
            'type' => 'out',
            'amount' => '25000.00',
        ]);

        $this->assertSame(0, JournalEntry::query()
            ->where('source_type', PurchaseInvoice::class)
            ->where('source_id', $invoice->id)
            ->count());

        $this->assertSame(1, PurchaserCredit::query()->where('type', 'out')->count());
    }

    public function test_company_paid_vendor_credit_posts_cash_flow_out_without_purchaser_spend(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $purchaser = User::factory()->create();
        $invoice = PurchaseInvoice::factory()->create([
            'amount' => 25000,
            'status' => InvoiceStatus::Pending->value,
            'payment_method' => 'Credit',
            'payment_paid_by' => 'vendor_credit',
            'payment_status' => 'credit_pending_approval',
            'paid_amount' => 0,
            'purchaser_submitted_by' => $purchaser->id,
            'created_at' => Carbon::parse('2026-07-14 10:00:00'),
        ]);

        app(PurchaseInvoiceService::class)->updatePayment($invoice, [
            'payment_method' => 'Credit',
            'payment_paid_by' => 'company',
            'discount_amount' => 0,
            'paid_amount' => 25000,
            'payment_note' => 'Company paid vendor credit.',
            'payment_details' => null,
        ]);

        $invoice->refresh();
        $journalEntry = JournalEntry::query()
            ->where('source_type', PurchaseInvoice::class)
            ->where('source_id', $invoice->id)
            ->where('source_event', 'company_vendor_credit_payment:paid-2500000')
            ->firstOrFail();

        $cashAccount = Account::query()->where('code', '1010')->firstOrFail();

        $this->assertSame('company', $invoice->payment_paid_by);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame(0, PurchaserCredit::query()->where('purchase_invoice_id', $invoice->id)->count());
        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cashAccount->id,
            'type' => 'credit',
            'amount' => '25000.00',
        ]);

        $report = app(AdminFinancePillarService::class)->cashFlowReport(Carbon::parse('2026-07-14'));
        $companyVendorRows = $report['journal_rows']->where('source', 'vendor_credit_company_payment')->values();

        $this->assertSame(25000.00, $report['summary']['total_out']);
        $this->assertCount(1, $companyVendorRows);
        $this->assertSame('OUT', $companyVendorRows->first()['direction']);
        $this->assertSame('Company Vendor Credit Payment', $companyVendorRows->first()['category']);
    }

    public function test_cash_flow_excludes_legacy_purchase_invoice_cash_journal_rows(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $user = User::factory()->create();
        $invoice = PurchaseInvoice::factory()->create();
        $cashAccount = Account::query()->where('code', '1010')->firstOrFail();
        $payableAccount = Account::query()->where('code', '2100')->firstOrFail();

        $journalEntry = JournalEntry::query()->create([
            'entry_date' => '2026-07-14',
            'reference' => 'LEGACY-VENDOR-PAYMENT',
            'description' => 'Legacy vendor payment journal that should not drive cash flow.',
            'source_type' => PurchaseInvoice::class,
            'source_id' => $invoice->id,
            'source_event' => 'payment',
            'created_by' => $user->id,
        ]);

        $journalEntry->transactions()->createMany([
            ['account_id' => $payableAccount->id, 'type' => 'debit', 'amount' => 1000],
            ['account_id' => $cashAccount->id, 'type' => 'credit', 'amount' => 1000],
        ]);

        $report = app(AdminFinancePillarService::class)->cashFlowReport(Carbon::parse('2026-07-14'));

        $this->assertSame(0.00, $report['summary']['total_out']);
        $this->assertSame(0, $report['journal_rows']->count());
    }

    public function test_supplier_bills_page_separates_credit_and_other_purchase_tabs(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $creditInvoice = PurchaseInvoice::factory()->create([
            'invoice_number' => 'CREDIT-BILL-001',
            'payment_method' => 'Credit',
            'payment_paid_by' => 'vendor_credit',
            'payment_status' => 'credit_pending_approval',
            'created_at' => Carbon::parse('2026-07-14 10:00:00'),
        ]);
        $cashInvoice = PurchaseInvoice::factory()->create([
            'invoice_number' => 'CASH-BILL-001',
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'payment_status' => 'paid',
            'paid_amount' => 100,
            'created_at' => Carbon::parse('2026-07-14 11:00:00'),
        ]);

        $this
            ->actingAs($admin)
            ->get(route('purchasing.invoices.index', ['date' => '2026-07-14', 'tab' => 'credit']))
            ->assertOk()
            ->assertSee($creditInvoice->invoice_number)
            ->assertDontSee($cashInvoice->invoice_number);

        $this
            ->actingAs($admin)
            ->get(route('purchasing.invoices.index', ['date' => '2026-07-14', 'tab' => 'other']))
            ->assertOk()
            ->assertSee($cashInvoice->invoice_number)
            ->assertDontSee($creditInvoice->invoice_number);
    }
}
