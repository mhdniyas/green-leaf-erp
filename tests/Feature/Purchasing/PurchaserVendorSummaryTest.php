<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Cashbook\CompanyAccount;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementAllocation;
use App\Services\Purchasing\PurchaserVendorSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserVendorSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser1;

    private User $purchaser2;

    private Supplier $vendorA;

    private Supplier $vendorB;

    private PurchaserVendorSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-12 12:00:00');

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'purchaser', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
        config(['greenleaf.main_admin_email' => $this->admin->email]);

        $this->purchaser1 = User::factory()->create(['name' => 'Purchaser One']);
        $this->purchaser1->assignRole('purchaser');

        $this->purchaser2 = User::factory()->create(['name' => 'Purchaser Two']);
        $this->purchaser2->assignRole('purchaser');

        $this->vendorA = Supplier::factory()->create(['name' => 'Vendor Alpha']);
        $this->vendorB = Supplier::factory()->create(['name' => 'Vendor Beta']);

        $this->service = app(PurchaserVendorSummaryService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createPurchase(
        User $purchaser,
        Supplier $vendor,
        float $amount,
        string $paymentMethod = 'Cash',
        string $businessDate = '2026-09-12',
        float $discount = 0.0
    ): PurchaseInvoice {
        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $vendor->id,
            'business_date' => $businessDate,
            'status' => 'submitted',
            'cart_number' => 'CART-'.fake()->unique()->numerify('####'),
            'bill_number' => 'BILL-'.fake()->unique()->numerify('####'),
            'payment_method' => $paymentMethod,
            'discount_amount' => $discount,
            'submitted_at' => $businessDate.' 10:00:00',
        ]);

        $isCredit = strcasecmp($paymentMethod, 'credit') === 0;

        return PurchaseInvoice::factory()->create([
            'supplier_id' => $vendor->id,
            'purchaser_cart_id' => $cart->id,
            'purchaser_submitted_by' => $purchaser->id,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('####'),
            'amount' => $amount,
            'discount_amount' => $discount,
            'paid_amount' => $isCredit ? 0.0 : max(0.0, $amount - $discount),
            'payment_method' => $paymentMethod,
            'payment_paid_by' => $isCredit ? 'vendor_credit' : 'purchaser',
            'payment_status' => $isCredit ? 'credit_pending_approval' : 'paid',
            'status' => 'pending',
            'created_at' => $businessDate.' 10:00:00',
        ]);
    }

    private function createSettlementAllocation(
        PurchaseInvoice $invoice,
        float $allocatedAmount,
        string $paymentDate = '2026-09-12',
        string $status = 'approved'
    ): VendorSettlementAllocation {
        $companyAccount = CompanyAccount::query()->create([
            'name' => 'Main Operating Account',
            'account_number' => '9876543210',
            'bank_name' => 'HDFC',
            'account_type' => 'bank',
            'enabled' => true,
        ]);

        $settlement = VendorSettlement::query()->create([
            'supplier_id' => $invoice->supplier_id,
            'actual_payment_amount' => $allocatedAmount,
            'settlement_discount_amount' => 0,
            'vendor_advance_used_amount' => 0,
            'new_vendor_advance_amount' => 0,
            'company_account_id' => $companyAccount->id,
            'payment_method' => 'Bank',
            'payment_date' => $paymentDate,
            'reference' => 'SETTLE-REF-'.fake()->numerify('####'),
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);

        return VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $invoice->id,
            'cash_allocated' => $allocatedAmount,
            'advance_allocated' => 0,
            'discount_allocated' => 0,
            'total_settled' => $allocatedAmount,
        ]);
    }

    public function test_1_purchaser_isolation(): void
    {
        // Vendor Alpha has purchases under Purchaser 1 (₹20,000) and Purchaser 2 (₹50,000)
        $this->createPurchase($this->purchaser1, $this->vendorA, 20000.0, 'Cash', '2026-09-12');
        $this->createPurchase($this->purchaser2, $this->vendorA, 50000.0, 'Cash', '2026-09-12');

        $filters = ['period' => 'today', 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'];

        $summary = $this->service->getSummary($this->purchaser1, $filters);
        $this->assertEquals(20000.0, $summary['total_purchase']);
        $this->assertEquals(20000.0, $summary['cash_purchase']);
        $this->assertEquals(1, $summary['bills_count']);

        $vendorRows = $this->service->getVendorRows($this->purchaser1, $filters);
        $this->assertCount(1, $vendorRows);
        $this->assertEquals(20000.0, $vendorRows->first()->total_purchase);

        $vendorDetail = $this->service->getVendorDetail($this->purchaser1, $this->vendorA, $filters);
        $this->assertEquals(20000.0, $vendorDetail['total_purchase']);
    }

    public function test_2_cash_purchase(): void
    {
        $this->createPurchase($this->purchaser1, $this->vendorA, 5000.0, 'Cash', '2026-09-12');
        $filters = ['period' => 'today', 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'];

        $summary = $this->service->getSummary($this->purchaser1, $filters);

        $this->assertEquals(5000.0, $summary['cash_purchase']);
        $this->assertEquals(0.0, $summary['credit_purchase']);
        $this->assertEquals(0.0, $summary['other_modes_purchase']);
        $this->assertEquals(0.0, $summary['credit_paid_by_company']);
        $this->assertEquals(0.0, $summary['credit_outstanding']);
        $this->assertEquals(5000.0, $summary['total_purchase']);
    }

    public function test_3_unpaid_credit(): void
    {
        $this->createPurchase($this->purchaser1, $this->vendorA, 10000.0, 'Credit', '2026-09-12');
        $filters = ['period' => 'today', 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'];

        $summary = $this->service->getSummary($this->purchaser1, $filters);

        $this->assertEquals(10000.0, $summary['credit_purchase']);
        $this->assertEquals(0.0, $summary['credit_paid_by_company']);
        $this->assertEquals(10000.0, $summary['credit_outstanding']);
        $this->assertEquals(0.0, $summary['cash_purchase']);
        $this->assertEquals(10000.0, $summary['total_purchase']);
    }

    public function test_4_partially_company_paid_credit(): void
    {
        $invoice = $this->createPurchase($this->purchaser1, $this->vendorA, 10000.0, 'Credit', '2026-09-12');
        $this->createSettlementAllocation($invoice, 6000.0, '2026-09-12');

        $filters = ['period' => 'today', 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'];

        $summary = $this->service->getSummary($this->purchaser1, $filters);

        $this->assertEquals(10000.0, $summary['credit_purchase']);
        $this->assertEquals(6000.0, $summary['credit_paid_by_company']);
        $this->assertEquals(4000.0, $summary['credit_outstanding']);
        $this->assertEquals(10000.0, $summary['total_purchase']);
    }

    public function test_5_fully_paid_credit(): void
    {
        $invoice = $this->createPurchase($this->purchaser1, $this->vendorA, 10000.0, 'Credit', '2026-09-12');
        $this->createSettlementAllocation($invoice, 10000.0, '2026-09-12');

        $filters = ['period' => 'today', 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'];

        $summary = $this->service->getSummary($this->purchaser1, $filters);

        $this->assertEquals(10000.0, $summary['credit_purchase']);
        $this->assertEquals(10000.0, $summary['credit_paid_by_company']);
        $this->assertEquals(0.0, $summary['credit_outstanding']);
        $this->assertEquals(10000.0, $summary['total_purchase']);
    }

    public function test_6_other_mode(): void
    {
        $this->createPurchase($this->purchaser1, $this->vendorA, 3000.0, 'UPI', '2026-09-12');
        $filters = ['period' => 'today', 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'];

        $summary = $this->service->getSummary($this->purchaser1, $filters);

        $this->assertEquals(3000.0, $summary['other_modes_purchase']);
        $this->assertEquals(0.0, $summary['cash_purchase']);
        $this->assertEquals(0.0, $summary['credit_purchase']);
        $this->assertEquals(3000.0, $summary['total_purchase']);
    }

    public function test_7_totals_reconciliation(): void
    {
        // Vendor A: Cash 12,500, Credit 8,000 (Paid 5,000, Out 3,000), Other 2,000 -> Total 22,500 (6 bills)
        $invA1 = $this->createPurchase($this->purchaser1, $this->vendorA, 12500.0, 'Cash', '2026-09-12');
        $invA2 = $this->createPurchase($this->purchaser1, $this->vendorA, 8000.0, 'Credit', '2026-09-12');
        $this->createSettlementAllocation($invA2, 5000.0, '2026-09-12');
        $invA3 = $this->createPurchase($this->purchaser1, $this->vendorA, 2000.0, 'GPay', '2026-09-12');

        // Vendor B: Cash 5,000, Credit 15,500 (Paid 10,000, Out 5,500), Other 0 -> Total 20,500 (4 bills)
        $invB1 = $this->createPurchase($this->purchaser1, $this->vendorB, 5000.0, 'Cash', '2026-09-12');
        $invB2 = $this->createPurchase($this->purchaser1, $this->vendorB, 15500.0, 'Credit', '2026-09-12');
        $this->createSettlementAllocation($invB2, 10000.0, '2026-09-12');

        $filters = ['period' => 'today', 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'];

        $summary = $this->service->getSummary($this->purchaser1, $filters);
        $vendorRows = $this->service->getVendorRows($this->purchaser1, $filters);

        // Purchaser totals must equal sum of vendor rows
        $this->assertEquals($summary['cash_purchase'], (float) $vendorRows->sum('cash_purchase'));
        $this->assertEquals($summary['credit_purchase'], (float) $vendorRows->sum('credit_purchase'));
        $this->assertEquals($summary['credit_paid_by_company'], (float) $vendorRows->sum('paid_by_company'));
        $this->assertEquals($summary['credit_outstanding'], (float) $vendorRows->sum('credit_outstanding'));
        $this->assertEquals($summary['other_modes_purchase'], (float) $vendorRows->sum('other_modes_purchase'));
        $this->assertEquals($summary['total_purchase'], (float) $vendorRows->sum('total_purchase'));
        $this->assertEquals($summary['bills_count'], (int) $vendorRows->sum('bills_count'));

        // Expected specific totals
        $this->assertEquals(17500.0, $summary['cash_purchase']);
        $this->assertEquals(23500.0, $summary['credit_purchase']);
        $this->assertEquals(15000.0, $summary['credit_paid_by_company']);
        $this->assertEquals(8500.0, $summary['credit_outstanding']);
        $this->assertEquals(2000.0, $summary['other_modes_purchase']);
        $this->assertEquals(43000.0, $summary['total_purchase']);

        // Check equation: Credit Purchase = Paid + Outstanding
        $this->assertEquals($summary['credit_purchase'], $summary['credit_paid_by_company'] + $summary['credit_outstanding']);
        // Check equation: Total Purchase = Cash + Credit + Other
        $this->assertEquals($summary['total_purchase'], $summary['cash_purchase'] + $summary['credit_purchase'] + $summary['other_modes_purchase']);

        // Vendor detail totals must equal vendor row
        $detailA = $this->service->getVendorDetail($this->purchaser1, $this->vendorA, $filters);
        $rowA = $vendorRows->where('supplier_id', $this->vendorA->id)->first();
        $this->assertEquals($rowA->total_purchase, $detailA['total_purchase']);
        $this->assertEquals($rowA->cash_purchase, $detailA['cash_purchase']);
        $this->assertEquals($rowA->credit_purchase, $detailA['credit_purchase']);
        $this->assertEquals($rowA->paid_by_company, $detailA['credit_paid_by_company']);
        $this->assertEquals($rowA->credit_outstanding, $detailA['credit_outstanding']);
        $this->assertEquals($rowA->other_modes_purchase, $detailA['other_modes_purchase']);
    }

    public function test_8_reversed_company_allocation(): void
    {
        $invoice = $this->createPurchase($this->purchaser1, $this->vendorA, 10000.0, 'Credit', '2026-09-12');
        $allocation = $this->createSettlementAllocation($invoice, 6000.0, '2026-09-12');

        $filters = ['period' => 'today', 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'];

        // Initially partially paid
        $summary1 = $this->service->getSummary($this->purchaser1, $filters);
        $this->assertEquals(6000.0, $summary1['credit_paid_by_company']);
        $this->assertEquals(4000.0, $summary1['credit_outstanding']);

        // Allocation reversed / deleted
        $allocation->delete();

        $summary2 = $this->service->getSummary($this->purchaser1, $filters);
        $this->assertEquals(0.0, $summary2['credit_paid_by_company']);
        $this->assertEquals(10000.0, $summary2['credit_outstanding']);
        $this->assertEquals(10000.0, $summary2['total_purchase']);
    }

    public function test_9_partial_allocation_across_bills(): void
    {
        $billA = $this->createPurchase($this->purchaser1, $this->vendorA, 10000.0, 'Credit', '2026-09-12');
        $billB = $this->createPurchase($this->purchaser1, $this->vendorA, 8000.0, 'Credit', '2026-09-12');

        $companyAccount = CompanyAccount::query()->create([
            'name' => 'Operating Bank',
            'account_number' => '1122334455',
            'bank_name' => 'SBI',
            'account_type' => 'bank',
            'enabled' => true,
        ]);

        // Single company payment of ₹10,000 allocated ₹6,000 to Bill A and ₹4,000 to Bill B
        $settlement = VendorSettlement::query()->create([
            'supplier_id' => $this->vendorA->id,
            'actual_payment_amount' => 10000.0,
            'company_account_id' => $companyAccount->id,
            'payment_date' => '2026-09-12',
            'created_by' => $this->admin->id,
        ]);

        VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $billA->id,
            'cash_allocated' => 6000.0,
            'total_settled' => 6000.0,
        ]);

        VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $billB->id,
            'cash_allocated' => 4000.0,
            'total_settled' => 4000.0,
        ]);

        $filters = ['period' => 'today', 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'];

        $txns = $this->service->getVendorTransactions($this->purchaser1, $this->vendorA, $filters);
        $txnCollection = $txns->getCollection();
        $this->assertCount(2, $txnCollection);

        $txnA = $txnCollection->firstWhere('id', $billA->id);
        $txnB = $txnCollection->firstWhere('id', $billB->id);

        $this->assertNotNull($txnA);
        $this->assertNotNull($txnB);

        $this->assertEquals(6000.0, $txnA->company_paid);
        $this->assertEquals(4000.0, $txnA->outstanding);

        $this->assertEquals(4000.0, $txnB->company_paid);
        $this->assertEquals(4000.0, $txnB->outstanding);

        $summary = $this->service->getSummary($this->purchaser1, $filters);
        $this->assertEquals(10000.0, $summary['credit_paid_by_company']);
        $this->assertEquals(8000.0, $summary['credit_outstanding']);
        $this->assertEquals(18000.0, $summary['credit_purchase']);
    }

    public function test_10_period_filter(): void
    {
        // Purchase from yesterday: Sep 11
        $this->createPurchase($this->purchaser1, $this->vendorA, 7000.0, 'Cash', '2026-09-11');
        // Purchase from today: Sep 12
        $this->createPurchase($this->purchaser1, $this->vendorA, 3000.0, 'Cash', '2026-09-12');

        $todayFilters = ['period' => 'today', 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'];
        $yesterdayFilters = ['period' => 'yesterday', 'start_date' => '2026-09-11', 'end_date' => '2026-09-11'];

        $todaySummary = $this->service->getSummary($this->purchaser1, $todayFilters);
        $this->assertEquals(3000.0, $todaySummary['total_purchase']);
        $this->assertEquals(1, $todaySummary['bills_count']);

        $yesterdaySummary = $this->service->getSummary($this->purchaser1, $yesterdayFilters);
        $this->assertEquals(7000.0, $yesterdaySummary['total_purchase']);
        $this->assertEquals(1, $yesterdaySummary['bills_count']);
    }

    public function test_11_http_purchaser_page_renders_vendor_summary(): void
    {
        $this->createPurchase($this->purchaser1, $this->vendorA, 12000.0, 'Cash', '2026-09-12');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.purchasers.show', [
                'purchaser' => $this->purchaser1->public_uuid,
                'period' => 'today',
            ]));

        $response->assertOk();
        $response->assertSee('Vendor Summary');
        $response->assertSee('Vendor Alpha');
        $response->assertSee('₹12,000.00');
    }

    public function test_12_http_purchaser_vendor_detail_page_renders_transactions(): void
    {
        $invoice = $this->createPurchase($this->purchaser1, $this->vendorA, 10000.0, 'Credit', '2026-09-12');
        $this->createSettlementAllocation($invoice, 6000.0, '2026-09-12');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.purchasers.vendors.show', [
                'purchaser' => $this->purchaser1->public_uuid,
                'supplier' => $this->vendorA->public_uuid,
                'period' => 'today',
            ]));

        $response->assertOk();
        $response->assertSee($this->purchaser1->name);
        $response->assertSee($this->vendorA->name);
        $response->assertSee('₹10,000.00');
        $response->assertSee('₹6,000.00');
        $response->assertSee('₹4,000.00');
        $response->assertSee($invoice->invoice_number);
    }
}
