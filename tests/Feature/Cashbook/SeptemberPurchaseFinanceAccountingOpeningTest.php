<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\PurchaserMonthlySummaryService;
use App\Services\Finance\PurchaseFinanceAccountingOpeningService;
use App\Services\Finance\PurchaserSettlementService;
use App\Services\Finance\VendorSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeptemberPurchaseFinanceAccountingOpeningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaserFaisal;

    private User $purchaserShadhuli;

    private Supplier $vendorA;

    private Supplier $vendorB;

    private PurchaseFinanceAccountingOpeningService $openingService;

    private PurchaserSettlementService $settlementService;

    private PurchaserMonthlySummaryService $monthlySummaryService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->purchaserFaisal = User::factory()->create(['name' => 'Faisal', 'email' => 'faisal@greenleaf.test']);
        $this->purchaserFaisal->assignRole('purchaser');

        $this->purchaserShadhuli = User::factory()->create(['name' => 'Shadhuli', 'email' => 'shadhuli@greenleaf.test']);
        $this->purchaserShadhuli->assignRole('purchaser');

        $this->vendorA = Supplier::create([
            'name' => 'Vendor Nagesh',
            'type' => 'farmer',
            'is_active' => true,
        ]);

        $this->vendorB = Supplier::create([
            'name' => 'Vendor Aman Onions',
            'type' => 'farmer',
            'is_active' => true,
        ]);

        $this->openingService = app(PurchaseFinanceAccountingOpeningService::class);
        $this->settlementService = app(PurchaserSettlementService::class);
        $this->monthlySummaryService = app(PurchaserMonthlySummaryService::class);
    }

    private function createInvoice(array $attributes = []): PurchaseInvoice
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-'.uniqid(),
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => now(),
        ]);

        return PurchaseInvoice::create(array_merge([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->vendorA->id,
            'purchaser_submitted_by' => $this->purchaserFaisal->id,
            'invoice_number' => 'INV-'.uniqid(),
            'amount' => 10000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'status' => 'approved',
            'original_business_date' => '2026-08-15',
            'created_at' => '2026-08-15 10:00:00',
        ], $attributes));
    }

    public function test_september_opening_cash_is_zero_for_all_purchasers(): void
    {
        // 1. August physical cash advance movements
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaserFaisal->id,
            'type' => 'in',
            'amount' => 50000.00,
            'business_date' => '2026-08-10',
        ]);
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaserFaisal->id,
            'type' => 'out',
            'amount' => 20000.00,
            'business_date' => '2026-08-20',
        ]);

        // Initialize September opening
        $this->openingService->setPurchaserOpening($this->purchaserFaisal, '2026-09-01', [
            'opening_balance' => 0.00,
            'opening_balance_direction' => 'settled',
        ]);

        $summary = $this->monthlySummaryService->getAllPurchasersSummary('2026-09');
        $faisalRow = collect($summary['purchasers'])->firstWhere('purchaser_id', $this->purchaserFaisal->id);

        $this->assertNotNull($faisalRow);
        $this->assertEquals(0.00, $faisalRow['opening_cash']);
        $this->assertEquals('settled', $faisalRow['opening_cash_direction']);
    }

    public function test_august_physical_cash_does_not_carry_forward_into_september_cash_in_hand(): void
    {
        $inv = $this->createInvoice([
            'original_business_date' => '2026-09-10',
            'created_at' => '2026-09-10 10:00:00',
        ]);

        // August cash: +100,000 in, -40,000 out => August ending physical cash is 60,000
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaserFaisal->id,
            'type' => 'in',
            'amount' => 100000.00,
            'business_date' => '2026-08-15',
        ]);
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaserFaisal->id,
            'type' => 'out',
            'amount' => 40000.00,
            'business_date' => '2026-08-25',
        ]);

        // September cash: +30,000 funded, -10,000 cash used
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaserFaisal->id,
            'type' => 'in',
            'amount' => 30000.00,
            'business_date' => '2026-09-05',
        ]);
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaserFaisal->id,
            'type' => 'out',
            'amount' => 10000.00,
            'purchase_invoice_id' => $inv->id,
            'business_date' => '2026-09-10',
        ]);

        $detail = $this->monthlySummaryService->getPurchaserMonthlyDetail($this->purchaserFaisal, '2026-09');

        // Cash in Hand must be: 0 (Sep opening) + 30,000 (funded) - 10,000 (used) = 20,000
        $this->assertEquals(0.00, $detail['opening']['cash_balance']);
        $this->assertEquals(30000.00, $detail['activity']['company_funded']);
        $this->assertEquals(10000.00, $detail['activity']['cash_used']);
        $this->assertEquals(20000.00, $detail['closing']['cash_balance']);
        $this->assertEquals('purchaser_holds_company_cash', $detail['closing']['direction']);
    }

    public function test_august_vendor_credit_outstanding_carries_forward_into_opening_credit_pending(): void
    {
        $this->createInvoice([
            'supplier_id' => $this->vendorA->id,
            'purchaser_submitted_by' => $this->purchaserFaisal->id,
            'invoice_number' => 'INV-AUG-FAISAL-001',
            'amount' => 80000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'original_business_date' => '2026-08-15',
            'created_at' => '2026-08-15 10:00:00',
        ]);

        $detail = $this->monthlySummaryService->getPurchaserMonthlyDetail($this->purchaserFaisal, '2026-09');

        // Opening credit pending as of Sep 1 must be 80,000
        $this->assertEquals(80000.00, $detail['credit']['opening_credit_pending']);
        $this->assertEquals(80000.00, $detail['credit']['credit_pending']);
    }

    public function test_purchaser_credits_is_not_used_for_credit_pending(): void
    {
        // Create 200,000 in purchaser_credits (physical cash ledger)
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaserFaisal->id,
            'type' => 'in',
            'amount' => 200000.00,
            'business_date' => '2026-08-10',
        ]);

        // Faisal has 0 vendor credit invoices
        $detail = $this->monthlySummaryService->getPurchaserMonthlyDetail($this->purchaserFaisal, '2026-09');

        // Credit pending must be 0.00 (NOT 200,000 from purchaser_credits)
        $this->assertEquals(0.00, $detail['credit']['credit_pending']);
        $this->assertEquals(0.00, $detail['credit']['opening_credit_pending']);
    }

    public function test_september_credit_purchases_increase_credit_pending(): void
    {
        // August carried credit: 50,000
        $this->createInvoice([
            'supplier_id' => $this->vendorA->id,
            'purchaser_submitted_by' => $this->purchaserFaisal->id,
            'invoice_number' => 'INV-AUG-001',
            'amount' => 50000.00,
            'payment_method' => 'Credit',
            'original_business_date' => '2026-08-20',
            'created_at' => '2026-08-20 10:00:00',
        ]);

        // September new credit purchase: 35,000
        $this->createInvoice([
            'supplier_id' => $this->vendorA->id,
            'purchaser_submitted_by' => $this->purchaserFaisal->id,
            'invoice_number' => 'INV-SEP-001',
            'amount' => 35000.00,
            'payment_method' => 'Credit',
            'original_business_date' => '2026-09-08',
            'created_at' => '2026-09-08 10:00:00',
        ]);

        $detail = $this->monthlySummaryService->getPurchaserMonthlyDetail($this->purchaserFaisal, '2026-09');

        $this->assertEquals(50000.00, $detail['credit']['opening_credit_pending']);
        $this->assertEquals(35000.00, $detail['credit']['credit_purchases']);
        $this->assertEquals(85000.00, $detail['credit']['credit_pending']);
    }

    public function test_september_vendor_settlements_and_discounts_reduce_credit_pending(): void
    {
        $vendorSettlementService = app(VendorSettlementService::class);

        // August carried invoice: 100,000
        $augInvoice = $this->createInvoice([
            'supplier_id' => $this->vendorA->id,
            'purchaser_submitted_by' => $this->purchaserFaisal->id,
            'invoice_number' => 'INV-AUG-SETTLE-001',
            'amount' => 100000.00,
            'payment_method' => 'Credit',
            'original_business_date' => '2026-08-25',
            'created_at' => '2026-08-25 10:00:00',
        ]);

        // Settle in September: 60,000 cash payment + 5,000 discount = 65,000 settled
        $vendorSettlementService->create($this->vendorA, [
            'actual_payment_amount' => 60000.00,
            'settlement_discount_amount' => 5000.00,
            'vendor_advance_used_amount' => 0.00,
            'payment_date' => '2026-09-05',
            'payment_method' => 'Bank',
            'allocations' => [
                [
                    'purchase_invoice_id' => $augInvoice->id,
                    'cash_allocated' => 60000.00,
                    'advance_allocated' => 0.00,
                    'discount_allocated' => 5000.00,
                ],
            ],
        ], $this->admin->id);

        $detail = $this->monthlySummaryService->getPurchaserMonthlyDetail($this->purchaserFaisal, '2026-09');

        // Opening: 100,000; Settled: 60,000; Discount: 5,000; Pending: 35,000
        $this->assertEquals(100000.00, $detail['credit']['opening_credit_pending']);
        $this->assertEquals(60000.00, $detail['credit']['credit_settled']);
        $this->assertEquals(5000.00, $detail['credit']['credit_discount']);
        $this->assertEquals(35000.00, $detail['credit']['credit_pending']);
    }

    public function test_credit_pending_is_properly_attributed_to_the_correct_purchaser(): void
    {
        // Invoice for Faisal: 50,000
        $this->createInvoice([
            'supplier_id' => $this->vendorA->id,
            'purchaser_submitted_by' => $this->purchaserFaisal->id,
            'invoice_number' => 'INV-FAISAL-001',
            'amount' => 50000.00,
            'payment_method' => 'Credit',
            'original_business_date' => '2026-08-20',
            'created_at' => '2026-08-20 10:00:00',
        ]);

        // Invoice for Shadhuli: 30,000
        $this->createInvoice([
            'supplier_id' => $this->vendorB->id,
            'purchaser_submitted_by' => $this->purchaserShadhuli->id,
            'invoice_number' => 'INV-SHADHULI-001',
            'amount' => 30000.00,
            'payment_method' => 'Credit',
            'original_business_date' => '2026-08-20',
            'created_at' => '2026-08-20 10:00:00',
        ]);

        $faisalDetail = $this->monthlySummaryService->getPurchaserMonthlyDetail($this->purchaserFaisal, '2026-09');
        $shadhuliDetail = $this->monthlySummaryService->getPurchaserMonthlyDetail($this->purchaserShadhuli, '2026-09');

        $this->assertEquals(50000.00, $faisalDetail['credit']['credit_pending']);
        $this->assertEquals(30000.00, $shadhuliDetail['credit']['credit_pending']);
    }

    public function test_vendor_breakdown_totals_equal_purchaser_credit_pending(): void
    {
        // Faisal purchases from Vendor A (40,000) and Vendor B (25,000)
        $this->createInvoice([
            'supplier_id' => $this->vendorA->id,
            'purchaser_submitted_by' => $this->purchaserFaisal->id,
            'invoice_number' => 'INV-FAISAL-VEND-A',
            'amount' => 40000.00,
            'payment_method' => 'Credit',
            'original_business_date' => '2026-08-20',
            'created_at' => '2026-08-20 10:00:00',
        ]);
        $this->createInvoice([
            'supplier_id' => $this->vendorB->id,
            'purchaser_submitted_by' => $this->purchaserFaisal->id,
            'invoice_number' => 'INV-FAISAL-VEND-B',
            'amount' => 25000.00,
            'payment_method' => 'Credit',
            'original_business_date' => '2026-08-20',
            'created_at' => '2026-08-20 10:00:00',
        ]);

        $detail = $this->monthlySummaryService->getPurchaserMonthlyDetail($this->purchaserFaisal, '2026-09');
        $breakdown = $detail['credit']['breakdown_by_vendor'];

        $this->assertCount(2, $breakdown);
        $vendorASum = collect($breakdown)->firstWhere('supplier_id', $this->vendorA->id);
        $vendorBSum = collect($breakdown)->firstWhere('supplier_id', $this->vendorB->id);

        $this->assertEquals(40000.00, $vendorASum['credit_pending']);
        $this->assertEquals(25000.00, $vendorBSum['credit_pending']);
        $this->assertEquals(65000.00, $detail['credit']['credit_pending']);
    }

    public function test_credit_pending_does_not_affect_cash_in_hand(): void
    {
        // 1. Credit invoice of 100,000
        $this->createInvoice([
            'supplier_id' => $this->vendorA->id,
            'purchaser_submitted_by' => $this->purchaserFaisal->id,
            'invoice_number' => 'INV-FAISAL-CREDIT-ONLY',
            'amount' => 100000.00,
            'payment_method' => 'Credit',
            'original_business_date' => '2026-09-05',
            'created_at' => '2026-09-05 10:00:00',
        ]);

        // 2. Physical cash: 10,000 funded, 0 cash used
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaserFaisal->id,
            'type' => 'in',
            'amount' => 10000.00,
            'business_date' => '2026-09-06',
        ]);

        $detail = $this->monthlySummaryService->getPurchaserMonthlyDetail($this->purchaserFaisal, '2026-09');

        // Cash in hand must be 10,000 (NOT mixed with 100,000 credit)
        $this->assertEquals(10000.00, $detail['closing']['cash_balance']);
        $this->assertEquals(100000.00, $detail['credit']['credit_pending']);
    }

    public function test_monthly_summary_matrix_page_renders_cleanly_without_db_mutations(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.monthly-summary.index', ['month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('Purchaser Closing Matrix');
        $response->assertSee('Opening Cash');
        $response->assertSee('Credit Pending');
        $response->assertSee('Cash in Hand');
    }
}
