<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
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

    public function test_fully_settled_credit_invoice_with_overwritten_payment_fields_remains_in_kpi(): void
    {
        // Create an invoice for ₹8,500 where purchaser cart was GPay
        $purchaserCart = PurchaserCart::query()->create([
            'user_id' => $this->admin->id,
            'shop_id' => 1,
            'business_date' => now()->toDateString(),
            'bill_number' => 'BILL-CART-001',
            'cart_number' => 'CART-001',
            'payment_method' => 'GPay',
            'status' => 'completed',
        ]);

        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'PENDING-BILL-VC-20260812-F2C5',
            'purchaser_cart_id' => $purchaserCart->id,
            'amount' => 8500.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
        ]);

        $companyAccount = CompanyAccount::query()->create([
            'name' => 'Canara Bank',
            'account_type' => 'bank',
            'bank_name' => 'Canara Bank',
            'enabled' => true,
        ]);

        // Settle the invoice via VendorSettlement (overwrites payment_method=Bank, payment_status=paid, payment_paid_by=company, paid_amount=8500)
        $service = app(VendorSettlementService::class);
        $service->create($this->supplier, [
            'payment_date' => now()->toDateString(),
            'company_account_id' => $companyAccount->id,
            'payment_method' => 'Bank',
            'actual_payment_amount' => 8500.00,
            'settlement_discount_amount' => 0.00,
            'vendor_advance_used_amount' => 0.00,
            'allocations' => [
                [
                    'purchase_invoice_id' => $invoice->id,
                    'cash_allocated' => 8500.00,
                    'advance_allocated' => 0.00,
                    'discount_allocated' => 0.00,
                ],
            ],
        ], $this->admin->id);

        $invoice->refresh();
        $this->assertSame('Bank', $invoice->payment_method);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame('company', $invoice->payment_paid_by);
        $this->assertEquals(0.0, (float) $invoice->amount - (float) $invoice->paid_amount);

        // Fetch vendor credit page
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));

        $response->assertOk();
        $kpi = $response->viewData('kpi');

        // Invoice ₹8,500 must remain included in Total Credit Purchases and Total Settled
        $this->assertEquals(8500.0, $kpi['total_invoiced']);
        $this->assertEquals(8500.0, $kpi['total_paid']);
        $this->assertEquals(0.0, $kpi['total_outstanding']);
    }

    public function test_vendor_settlement_delete_route_uses_public_uuid_and_reverses_financial_state(): void
    {
        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'BILL-DEL-UUID-001',
            'amount' => 12000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
        ]);

        $companyAccount = CompanyAccount::query()->create([
            'name' => 'HDFC Bank',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'enabled' => true,
        ]);

        $service = app(VendorSettlementService::class);
        $settlement = $service->create($this->supplier, [
            'payment_date' => now()->toDateString(),
            'company_account_id' => $companyAccount->id,
            'payment_method' => 'Bank',
            'actual_payment_amount' => 12000.00,
            'settlement_discount_amount' => 0.00,
            'vendor_advance_used_amount' => 0.00,
            'allocations' => [
                [
                    'purchase_invoice_id' => $invoice->id,
                    'cash_allocated' => 12000.00,
                    'advance_allocated' => 0.00,
                    'discount_allocated' => 0.00,
                ],
            ],
        ], $this->admin->id);

        // 1. Verify VendorSettlement has numeric DB ID and public_uuid
        $this->assertIsInt($settlement->id);
        $this->assertNotEmpty($settlement->public_uuid);
        $this->assertNotEquals((string) $settlement->id, $settlement->public_uuid);

        // 2. Fetch page and verify generated delete button passes public_uuid and NOT supplier uuid
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));

        $response->assertOk();
        $response->assertSee($settlement->public_uuid);
        $response->assertSee('settlements/${deleteTarget.uuid}/delete', false);

        // 3. Numeric internal ID delete endpoint returns 404
        $this->actingAs($this->admin)
            ->post("/admin/cashbook/finance/vendor-credit/settlements/{$settlement->id}/delete", [
                'reason' => 'Wrong Payment Amount',
            ])
            ->assertNotFound();

        // 4. Invalid UUID returns 404
        $this->actingAs($this->admin)
            ->post('/admin/cashbook/finance/vendor-credit/settlements/00000000-0000-0000-0000-000000000000/delete', [
                'reason' => 'Wrong Payment Amount',
            ])
            ->assertNotFound();

        // 5. Authorized admin executes reversal using public_uuid delete endpoint
        $delResponse = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.vendor-credit.settlements.delete', $settlement->public_uuid), [
                'reason' => 'Duplicate Entry',
                'notes' => 'Testing UUID delete reversal',
            ]);

        $delResponse->assertRedirect();

        // 6. Verify reversal restored correct financial state and deleted settlement
        $invoice->refresh();
        $this->assertEquals(0.0, (float) $invoice->paid_amount);
        $this->assertDatabaseMissing('vendor_settlements', ['id' => $settlement->id]);
    }

    public function test_vendor_credit_settlement_with_discount_reduces_bill_outstanding_and_displays_in_history(): void
    {
        $bill = PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'BILL-DISC-TEST-001',
            'amount' => 45000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.finance.vendor-credit.settle', $this->supplier),
            [
                'invoice_ids' => [$bill->id],
                'actual_payment_amount' => 40000.00,
                'difference_treatment' => 'discount',
                'settlement_discount_amount' => 5000.00,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Bank',
                'company_account_id' => $this->companyAccount->id,
                'allocation_order' => 'oldest',
            ]
        );

        $response->assertRedirect();

        $settlement = VendorSettlement::query()
            ->where('supplier_id', $this->supplier->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals(40000.00, (float) $settlement->actual_payment_amount);
        $this->assertEquals(5000.00, (float) $settlement->settlement_discount_amount);

        $allocation = VendorSettlementAllocation::query()
            ->where('vendor_settlement_id', $settlement->id)
            ->where('purchase_invoice_id', $bill->id)
            ->firstOrFail();

        $this->assertEquals(40000.00, (float) $allocation->cash_allocated);
        $this->assertEquals(5000.00, (float) $allocation->discount_allocated);
        $this->assertEquals(45000.00, (float) $allocation->total_settled);

        $bill->refresh();
        $this->assertEquals(40000.00, (float) $bill->paid_amount);
        $this->assertSame('paid', $bill->payment_status);

        $net = (float) $bill->amount - (float) $bill->discount_amount;
        $allSettled = (float) $bill->vendorSettlementAllocations()->sum('total_settled');
        $this->assertEquals(0.00, round(max(0, $net - $allSettled), 2));

        $showResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.finance.vendor-credit.show', $this->supplier)
        );

        $showResponse->assertOk();
        $showResponse->assertSee('40,000.00');
        $showResponse->assertSee('5,000.00');
        $showResponse->assertSee('Settlement Discount');
        $showResponse->assertSee('Fully Settled');
        $showResponse->assertDontSee('5,000.00 Outstanding');
    }

    public function test_vendor_credit_partial_settlement_with_discount_and_remaining_balance(): void
    {
        $bill = PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'BILL-DISC-PARTIAL-001',
            'amount' => 45000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.finance.vendor-credit.settle', $this->supplier),
            [
                'invoice_ids' => [$bill->id],
                'actual_payment_amount' => 30000.00,
                'difference_treatment' => 'discount',
                'settlement_discount_amount' => 5000.00,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Bank',
                'company_account_id' => $this->companyAccount->id,
                'allocation_order' => 'oldest',
            ]
        );

        $response->assertRedirect();

        $settlement = VendorSettlement::query()
            ->where('supplier_id', $this->supplier->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals(30000.00, (float) $settlement->actual_payment_amount);
        $this->assertEquals(5000.00, (float) $settlement->settlement_discount_amount);

        $allocation = VendorSettlementAllocation::query()
            ->where('vendor_settlement_id', $settlement->id)
            ->where('purchase_invoice_id', $bill->id)
            ->firstOrFail();

        $this->assertEquals(30000.00, (float) $allocation->cash_allocated);
        $this->assertEquals(5000.00, (float) $allocation->discount_allocated);
        $this->assertEquals(35000.00, (float) $allocation->total_settled);

        $bill->refresh();
        $this->assertSame('partial', $bill->payment_status);

        $net = (float) $bill->amount - (float) $bill->discount_amount;
        $allSettled = (float) $bill->vendorSettlementAllocations()->sum('total_settled');
        $this->assertEquals(10000.00, round(max(0, $net - $allSettled), 2));

        $showResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.finance.vendor-credit.show', $this->supplier)
        );

        $showResponse->assertOk();
        $showResponse->assertSee('30,000.00');
        $showResponse->assertSee('5,000.00');
        $showResponse->assertSee('10,000.00 Outstanding');
    }
}
