<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\PurchaseInvoice;
use App\Models\ShopInvoice;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_finance_root_redirects_to_vendor_reports_page(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('accounting.ledger.view');

        $this->actingAs($user)
            ->get(route('finance.index'))
            ->assertRedirect(route('finance.vendors.index'));
    }

    public function test_vendor_and_sales_reports_are_separate_pages_with_daily_credit_and_debit_tables(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('accounting.ledger.view');

        $supplier = Supplier::factory()->create([
            'name' => 'Credit Request Vendor',
            'credit_approval_requested_at' => now(),
            'credit_approval_requested_by' => $user->id,
            'credit_approved' => false,
        ]);

        PurchaseInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'amount' => 900.00,
            'paid_amount' => 100.00,
            'created_at' => today()->setTime(9, 30),
        ]);

        ShopInvoice::factory()->create([
            'invoice_number' => 'SINV-FINANCE-PILLAR',
            'business_date' => today()->toDateString(),
            'final_total' => 1200.00,
            'paid_amount' => 900.00,
            'balance_amount' => 300.00,
        ]);

        $this->actingAs($user)
            ->get(route('finance.vendors.index', [
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Vendor Reports')
            ->assertSee('Daily credit and debit')
            ->assertSee('Credit Request Vendor')
            ->assertSee('Approve Credit')
            ->assertDontSee('Chart of Accounts')
            ->assertDontSee('General Ledger')
            ->assertDontSee('Expenses')
            ->assertDontSee('P&L Statement')
            ->assertDontSee('Balance Sheet')
            ->assertDontSee('Cash Flow')
            ->assertDontSee('Payment History')
            ->assertDontSee('Ledger Statement')
            ->assertDontSee('Download Statements');

        $this->actingAs($user)
            ->get(route('finance.sales.index', [
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Sales Reports')
            ->assertSee('Daily credit and debit')
            ->assertDontSee('Vendor credit waiting for admin decision');
    }

    public function test_finance_daily_detail_pages_render_vendor_and_sales_transactions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('accounting.ledger.view');

        $supplier = Supplier::factory()->create([
            'name' => 'Detail Vendor',
        ]);

        PurchaseInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PINV-DAILY-01',
            'amount' => 1500.00,
            'paid_amount' => 500.00,
            'created_at' => today()->setTime(8, 15),
        ]);

        ShopInvoice::factory()->create([
            'invoice_number' => 'SINV-DAILY-01',
            'business_date' => today()->toDateString(),
            'final_total' => 2200.00,
            'paid_amount' => 1200.00,
            'balance_amount' => 1000.00,
        ]);

        $this->actingAs($user)
            ->get(route('finance.vendor-daily', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Vendor report for')
            ->assertSee('PINV-DAILY-01')
            ->assertSee('Detail Vendor');

        $this->actingAs($user)
            ->get(route('finance.sales-daily', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Sales report for')
            ->assertSee('SINV-DAILY-01');
    }

    public function test_vendor_and_sales_exports_are_available(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('accounting.ledger.view');

        $this->actingAs($user)
            ->get(route('finance.vendors.excel', ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString()]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($user)
            ->get(route('finance.sales.excel', ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString()]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($user)
            ->get(route('finance.vendors.pdf', ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Vendor Reports');

        $this->actingAs($user)
            ->get(route('finance.sales.pdf', ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Sales Reports');
    }
}
