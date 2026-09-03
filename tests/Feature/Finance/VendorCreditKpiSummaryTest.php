<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementAllocation;
use App\Services\Finance\VendorSettlementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorCreditKpiSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(['admin', 'purchaser']);

        $this->supplier = Supplier::query()->create([
            'type' => 'supplier',
            'name' => 'Kovai Fresh Vegetables',
            'mobile_number' => '9988776655',
            'contact' => 'Kovai Manager',
            'credit_approved' => true,
        ]);
    }

    public function test_multiple_valid_payments_accumulate_into_total_settled(): void
    {
        $inv1 = PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'BILL-KVI-001',
            'amount' => 50000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 20000.00,
            'payment_method' => 'Credit',
            'payment_status' => 'partial',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
            'created_at' => now()->subDays(10),
        ]);

        $inv2 = PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'BILL-KVI-002',
            'amount' => 30000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 15000.00,
            'payment_method' => 'Credit',
            'payment_status' => 'partial',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
            'created_at' => now()->subDays(5),
        ]);

        $settlement1 = VendorSettlement::query()->create([
            'supplier_id' => $this->supplier->id,
            'actual_payment_amount' => 20000.00,
            'payment_date' => now()->subDays(8)->toDateString(),
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement1->id,
            'purchase_invoice_id' => $inv1->id,
            'cash_allocated' => 20000.00,
            'total_settled' => 20000.00,
        ]);

        $settlement2 = VendorSettlement::query()->create([
            'supplier_id' => $this->supplier->id,
            'actual_payment_amount' => 15000.00,
            'payment_date' => now()->subDays(3)->toDateString(),
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement2->id,
            'purchase_invoice_id' => $inv2->id,
            'cash_allocated' => 15000.00,
            'total_settled' => 15000.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));

        $response->assertOk();
        $kpi = $response->viewData('kpi');
        $this->assertEquals(80000.0, $kpi['total_invoiced']);
        $this->assertEquals(35000.0, $kpi['total_paid']);
        $this->assertEquals(45000.0, $kpi['total_outstanding']);
        $this->assertEquals(2, $kpi['invoice_count']);
    }

    public function test_table_status_unpaid_filter_does_not_change_kpi_totals(): void
    {
        $invPaid = PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'BILL-PAID-001',
            'amount' => 40000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 40000.00,
            'payment_method' => 'Credit',
            'payment_status' => 'paid',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
        ]);

        $invUnpaid = PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'BILL-UNPAID-002',
            'amount' => 25000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
        ]);

        $settlement = VendorSettlement::query()->create([
            'supplier_id' => $this->supplier->id,
            'actual_payment_amount' => 40000.00,
            'payment_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $invPaid->id,
            'cash_allocated' => 40000.00,
            'total_settled' => 40000.00,
        ]);

        // Unfiltered page
        $responseUnfiltered = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $responseUnfiltered->assertOk();
        $kpiUnfiltered = $responseUnfiltered->viewData('kpi');
        $this->assertEquals(65000.0, $kpiUnfiltered['total_invoiced']);
        $this->assertEquals(40000.0, $kpiUnfiltered['total_paid']);
        $this->assertEquals(25000.0, $kpiUnfiltered['total_outstanding']);

        // Filtered by status=unpaid (Table shows 1 unpaid bill, KPI summary card keeps supplier overall 65000/40000/25000)
        $responseFiltered = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', [$this->supplier, 'status' => 'unpaid']));
        $responseFiltered->assertOk();
        $kpiFiltered = $responseFiltered->viewData('kpi');
        $this->assertEquals(65000.0, $kpiFiltered['total_invoiced']);
        $this->assertEquals(40000.0, $kpiFiltered['total_paid']);
        $this->assertEquals(25000.0, $kpiFiltered['total_outstanding']);
    }

    public function test_table_search_filter_does_not_change_kpi_totals(): void
    {
        PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'ALPHA-111',
            'amount' => 10000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 5000.00,
            'payment_method' => 'Credit',
            'payment_status' => 'partial',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
        ]);

        PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'BETA-222',
            'amount' => 20000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
        ]);

        $responseSearched = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', [$this->supplier, 'search' => 'BETA-222']));

        $responseSearched->assertOk();
        $kpiSearched = $responseSearched->viewData('kpi');
        $this->assertEquals(30000.0, $kpiSearched['total_invoiced']);
        $this->assertEquals(5000.0, $kpiSearched['total_paid']);
        $this->assertEquals(25000.0, $kpiSearched['total_outstanding']);
    }

    public function test_cancelled_invoices_are_excluded_from_kpi_totals(): void
    {
        PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'ACTIVE-001',
            'amount' => 15000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
        ]);

        PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'CANCELLED-002',
            'amount' => 50000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));

        $response->assertOk();
        $kpi = $response->viewData('kpi');
        $this->assertEquals(15000.0, $kpi['total_invoiced']);
        $this->assertEquals(15000.0, $kpi['total_outstanding']);
        $this->assertEquals(1, $kpi['invoice_count']);
    }

    public function test_mixed_payment_with_previous_direct_payment_and_later_settlement_allocations(): void
    {
        // 1. Initial invoice with direct cash payment of ₹5,000
        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'BILL-MIX-001',
            'amount' => 20000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 5000.00,
            'payment_method' => 'Credit',
            'payment_status' => 'partial',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
        ]);

        // 2. Open vendor advance available for supplier of ₹2,000
        VendorAdvance::query()->create([
            'supplier_id' => $this->supplier->id,
            'business_date' => now()->toDateString(),
            'amount_original' => 2000.00,
            'amount_remaining' => 2000.00,
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        $companyAccount = CompanyAccount::query()->create([
            'name' => 'State Bank of India',
            'account_type' => 'bank',
            'bank_name' => 'State Bank of India',
            'enabled' => true,
        ]);

        // 3. Execute VendorSettlement with ₹10,000 cash, ₹2,000 advance, and ₹500 discount allocated
        $service = app(VendorSettlementService::class);
        $service->create($this->supplier, [
            'payment_date' => now()->toDateString(),
            'company_account_id' => $companyAccount->id,
            'actual_payment_amount' => 10000.00,
            'settlement_discount_amount' => 500.00,
            'vendor_advance_used_amount' => 2000.00,
            'allocations' => [
                [
                    'purchase_invoice_id' => $invoice->id,
                    'cash_allocated' => 10000.00,
                    'advance_allocated' => 2000.00,
                    'discount_allocated' => 500.00,
                ],
            ],
        ], $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));

        $response->assertOk();
        $kpi = $response->viewData('kpi');

        // Total Invoiced: 20,000.00
        // Total Settled (5000 direct + 10000 settlement cash + 2000 advance + 500 discount): 17,500.00
        // Current Outstanding: 2,500.00
        $this->assertEquals(20000.0, $kpi['total_invoiced']);
        $this->assertEquals(17500.0, $kpi['total_paid']);
        $this->assertEquals(2500.0, $kpi['total_outstanding']);
    }
}
