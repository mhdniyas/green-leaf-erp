<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentAllocation;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Finance\CompanySummaryReportService;
use App\Services\Purchasing\PurchaseInvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompanySummaryReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_open_company_summary_dashboard(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.company-summary', ['date' => '2026-08-05']))
            ->assertOk()
            ->assertSeeText('Daily and monthly income, expense, and carry-over')
            ->assertSeeText('Carry Over')
            ->assertSeeText('Supplier Bills Pending')
            ->assertSeeText('Invoice Bills Pending');
    }

    public function test_supplier_credit_payment_and_discount_are_reported_in_payment_month_and_clear_old_due(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'amount' => 1000,
            'discount_amount' => 100,
            'paid_amount' => 900,
            'payment_method' => 'Credit',
            'payment_status' => 'paid',
            'created_at' => Carbon::parse('2026-07-10 10:00:00'),
        ]);

        PurchaseInvoicePayment::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'supplier_id' => $invoice->supplier_id,
            'payment_date' => '2026-08-05',
            'amount' => 900,
            'discount_amount' => 100,
            'payment_method' => 'Cash',
            'payment_paid_by' => 'company',
        ]);

        $report = app(CompanySummaryReportService::class)->report(Carbon::parse('2026-08-05'));
        $july = $report['carry_rows']->firstWhere('month', '2026-07');
        $august = $report['carry_rows']->firstWhere('month', '2026-08');

        $this->assertSame(1000.00, $july['expense_bills']);
        $this->assertSame(1000.00, $july['supplier_closing_pending']);
        $this->assertSame(1000.00, $august['supplier_opening_pending']);
        $this->assertSame(900.00, $august['expense_paid']);
        $this->assertSame(100.00, $august['expense_discount']);
        $this->assertSame(0.00, $august['supplier_closing_pending']);
        $this->assertSame(900.00, $report['daily']['expense_paid']);
        $this->assertSame(100.00, $report['daily']['expense_discount']);
    }

    public function test_supplier_payment_update_records_payment_month_movement(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 10:00:00'));

        $invoice = PurchaseInvoice::factory()->create([
            'amount' => 1000,
            'discount_amount' => 0,
            'paid_amount' => 0,
            'payment_status' => 'credit_pending_approval',
            'created_at' => Carbon::parse('2026-07-10 10:00:00'),
        ]);

        app(PurchaseInvoiceService::class)->updatePayment($invoice, [
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'discount_amount' => 100,
            'paid_amount' => 900,
            'payment_note' => 'Late settlement with discount',
            'payment_details' => null,
        ]);

        $this->assertDatabaseHas('purchase_invoice_payments', [
            'purchase_invoice_id' => $invoice->id,
            'payment_date' => '2026-08-05 00:00:00',
            'amount' => '900.00',
            'discount_amount' => '100.00',
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
        ]);

        Carbon::setTestNow();
    }

    public function test_shop_invoice_collection_and_discount_are_reported_in_payment_month_and_clear_old_due(): void
    {
        $user = User::factory()->create();
        $invoice = ShopInvoice::factory()->create([
            'business_date' => '2026-07-10',
            'subtotal' => 1000,
            'shortage_total' => 0,
            'excess_total' => 0,
            'discount_total' => 100,
            'final_total' => 900,
            'paid_amount' => 900,
            'balance_amount' => 0,
            'payment_status' => 'paid',
            'discount_approved_at' => Carbon::parse('2026-08-05 09:00:00'),
            'discount_approved_by' => $user->id,
        ]);
        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $invoice->shop_id,
            'requested_by' => $user->id,
            'request_type' => 'admin_manual',
            'requested_amount' => 900,
            'approved_amount' => 900,
            'applied_amount' => 900,
            'credit_amount' => 0,
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => Carbon::parse('2026-08-05 09:00:00'),
        ]);
        $allocation = ShopInvoicePaymentAllocation::query()->create([
            'payment_request_id' => $paymentRequest->id,
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $invoice->shop_id,
            'amount' => 900,
            'created_by' => $user->id,
        ]);
        $allocation->forceFill([
            'created_at' => Carbon::parse('2026-08-05 09:00:00'),
            'updated_at' => Carbon::parse('2026-08-05 09:00:00'),
        ])->save();

        $report = app(CompanySummaryReportService::class)->report(Carbon::parse('2026-08-05'));
        $july = $report['carry_rows']->firstWhere('month', '2026-07');
        $august = $report['carry_rows']->firstWhere('month', '2026-08');

        $this->assertSame(1000.00, $july['income_bills']);
        $this->assertSame(1000.00, $july['shop_closing_pending']);
        $this->assertSame(1000.00, $august['shop_opening_pending']);
        $this->assertSame(900.00, $august['income_collected']);
        $this->assertSame(100.00, $august['income_discount']);
        $this->assertSame(0.00, $august['shop_closing_pending']);
        $this->assertSame(900.00, $report['daily']['income_collected']);
        $this->assertSame(100.00, $report['daily']['income_discount']);
    }
}
