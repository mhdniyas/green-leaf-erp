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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
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
}
