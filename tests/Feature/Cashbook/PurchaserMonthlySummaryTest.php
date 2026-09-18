<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\Cashbook\CompanyAccount;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\PurchaserMonthlySummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserMonthlySummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private User $activePurchaser;

    private User $secondPurchaser;

    private User $inactivePurchaser;

    private Supplier $vendor;

    private CompanyAccount $companyAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'staff']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->staff = User::factory()->create(['email' => 'staff@greenleaf.test']);
        $this->staff->assignRole('staff');

        $this->activePurchaser = User::factory()->create([
            'name' => 'John Purchaser',
            'email' => 'john.purchaser@greenleaf.test',
            'registration_status' => 'approved',
        ]);
        $this->activePurchaser->assignRole('purchaser');

        $this->secondPurchaser = User::factory()->create([
            'name' => 'Jane Purchaser',
            'email' => 'jane.purchaser@greenleaf.test',
            'registration_status' => 'approved',
        ]);
        $this->secondPurchaser->assignRole('purchaser');

        $this->inactivePurchaser = User::factory()->create([
            'name' => 'Disabled User',
            'email' => 'disabled@greenleaf.test',
            'registration_status' => 'disabled',
        ]);
        $this->inactivePurchaser->assignRole('purchaser');

        $this->vendor = Supplier::factory()->create([
            'name' => 'Fresh Farms Co.',
        ]);

        $this->companyAccount = CompanyAccount::create([
            'name' => 'Main Cash Account',
            'account_type' => 'cash',
            'account_number' => 'CASH-01',
            'current_balance' => 100000.0,
            'enabled' => true,
        ]);
    }

    public function test_unauthenticated_users_are_redirected_or_rejected(): void
    {
        $response = $this->get(route('admin.cashbook.finance.purchase.monthly-summary.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_users_cannot_access_purchase_monthly_summary(): void
    {
        $response = $this->actingAs($this->staff)->get(route('admin.cashbook.finance.purchase.monthly-summary.index'));
        $this->assertTrue(in_array($response->status(), [302, 401, 403], true));
    }

    public function test_only_active_purchasers_appear_in_summary_matrix(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.monthly-summary.index', ['month' => '2026-08']));
        $response->assertOk();
        $response->assertSee('John Purchaser');
        $response->assertSee('Jane Purchaser');
        $response->assertDontSee('Disabled User');
    }

    public function test_non_purchasers_cannot_access_detail_page(): void
    {
        $nonPurchaser = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'regular@greenleaf.test',
            'registration_status' => 'approved',
        ]);
        $nonPurchaser->assignRole('staff');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.monthly-summary.show', [
            'purchaser' => $nonPurchaser->public_uuid,
            'month' => '2026-08',
        ]));
        $response->assertNotFound();
    }

    public function test_historical_opening_balance_derives_correctly_from_prior_ledger_state(): void
    {
        // In July 2026: Company gave 50,000, purchaser returned 10,000, purchaser spent 15,000 on cash bills
        PurchaserCredit::create([
            'purchaser_id' => $this->activePurchaser->id,
            'company_account_id' => $this->companyAccount->id,
            'business_date' => '2026-07-10',
            'type' => 'in',
            'amount' => 50000.00,
            'description' => 'July Advance',
        ]);

        PurchaserCredit::create([
            'purchaser_id' => $this->activePurchaser->id,
            'business_date' => '2026-07-15',
            'type' => 'out',
            'purchase_invoice_id' => null,
            'amount' => 10000.00,
            'description' => 'July Cash Returned',
        ]);

        $julyInvoice = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'purchaser_submitted_by' => $this->activePurchaser->id,
            'invoice_number' => 'INV-JUL-01',
            'amount' => 15000.00,
            'discount_amount' => 0.00,
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'status' => InvoiceStatus::Approved,
            'original_business_date' => '2026-07-20',
        ]);

        PurchaserCredit::create([
            'purchaser_id' => $this->activePurchaser->id,
            'business_date' => '2026-07-20',
            'type' => 'out',
            'purchase_invoice_id' => $julyInvoice->id,
            'amount' => 15000.00,
            'description' => 'Payment for INV-JUL-01',
        ]);

        // Opening balance for August 2026 should be 50,000 - 10,000 - 15,000 = 25,000
        $service = app(PurchaserMonthlySummaryService::class);
        $detail = $service->getPurchaserMonthlyDetail($this->activePurchaser, '2026-08');

        $this->assertEquals(25000.00, $detail['opening']['cash_balance']);
        $this->assertEquals('purchaser_holds_company_cash', $detail['opening']['direction']);
    }

    public function test_company_funding_and_purchases_are_attributed_to_correct_month(): void
    {
        // July funding
        PurchaserCredit::create([
            'purchaser_id' => $this->activePurchaser->id,
            'company_account_id' => $this->companyAccount->id,
            'business_date' => '2026-07-25',
            'type' => 'in',
            'amount' => 20000.00,
        ]);

        // August funding
        PurchaserCredit::create([
            'purchaser_id' => $this->activePurchaser->id,
            'company_account_id' => $this->companyAccount->id,
            'business_date' => '2026-08-05',
            'type' => 'in',
            'amount' => 30000.00,
            'reference' => 'AUG-FUND-01',
        ]);

        // August cash purchase
        $cart = PurchaserCart::create([
            'user_id' => $this->activePurchaser->id,
            'supplier_id' => $this->vendor->id,
            'business_date' => '2026-08-10',
            'status' => 'received',
            'cart_number' => 'CART-AUG-01',
        ]);

        $augInvoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->vendor->id,
            'purchaser_submitted_by' => $this->activePurchaser->id,
            'invoice_number' => 'INV-AUG-01',
            'amount' => 12000.00,
            'discount_amount' => 0.00,
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'status' => InvoiceStatus::Approved,
            'original_business_date' => '2026-08-10',
        ]);

        PurchaserCredit::create([
            'purchaser_id' => $this->activePurchaser->id,
            'business_date' => '2026-08-10',
            'type' => 'out',
            'purchase_invoice_id' => $augInvoice->id,
            'amount' => 12000.00,
        ]);

        $service = app(PurchaserMonthlySummaryService::class);
        $augDetail = $service->getPurchaserMonthlyDetail($this->activePurchaser, '2026-08');

        // Opening July = 20,000
        $this->assertEquals(20000.00, $augDetail['opening']['cash_balance']);
        // August Funding = 30,000
        $this->assertEquals(30000.00, $augDetail['activity']['company_funded']);
        // August Total Purchases = 12,000
        $this->assertEquals(12000.00, $augDetail['activity']['total_purchases']);
        $this->assertEquals(12000.00, $augDetail['activity']['cash_purchases']);
        // Closing Cash = 20,000 + 30,000 - 12,000 = 38,000
        $this->assertEquals(38000.00, $augDetail['closing']['cash_balance']);
    }

    public function test_cash_and_credit_purchases_remain_separate(): void
    {
        // August Cash Purchase
        PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'purchaser_submitted_by' => $this->activePurchaser->id,
            'invoice_number' => 'INV-CASH-01',
            'amount' => 10000.00,
            'discount_amount' => 500.00, // net = 9500
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'status' => InvoiceStatus::Approved,
            'original_business_date' => '2026-08-12',
        ]);

        // August Credit Purchase
        PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'purchaser_submitted_by' => $this->activePurchaser->id,
            'invoice_number' => 'INV-CREDIT-01',
            'amount' => 20000.00,
            'discount_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_paid_by' => 'vendor_credit',
            'paid_amount' => 5000.00, // outstanding = 15000
            'status' => InvoiceStatus::Approved,
            'original_business_date' => '2026-08-14',
        ]);

        $service = app(PurchaserMonthlySummaryService::class);
        $detail = $service->getPurchaserMonthlyDetail($this->activePurchaser, '2026-08');

        $this->assertEquals(29500.00, $detail['activity']['total_purchases']);
        $this->assertEquals(9500.00, $detail['activity']['cash_purchases']);
        $this->assertEquals(20000.00, $detail['activity']['credit_purchases']);
        $this->assertEquals(15000.00, $detail['bills']['credit_outstanding']);
    }

    public function test_cancelled_bills_are_excluded_from_active_totals(): void
    {
        // Valid Invoice
        PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'purchaser_submitted_by' => $this->activePurchaser->id,
            'invoice_number' => 'INV-VALID-01',
            'amount' => 8000.00,
            'discount_amount' => 0.00,
            'payment_method' => 'Cash',
            'status' => InvoiceStatus::Approved,
            'original_business_date' => '2026-08-15',
        ]);

        // Cancelled Invoice
        PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'purchaser_submitted_by' => $this->activePurchaser->id,
            'invoice_number' => 'INV-CANCELLED-01',
            'amount' => 5000.00,
            'discount_amount' => 0.00,
            'payment_method' => 'Cash',
            'status' => InvoiceStatus::Cancelled,
            'original_business_date' => '2026-08-16',
        ]);

        $service = app(PurchaserMonthlySummaryService::class);
        $detail = $service->getPurchaserMonthlyDetail($this->activePurchaser, '2026-08');

        $this->assertEquals(8000.00, $detail['activity']['total_purchases']);
        $this->assertEquals(1, $detail['bills']['total_count']);
        $this->assertEquals(1, $detail['bills']['cancelled_count']);
        $this->assertEquals(5000.00, $detail['bills']['cancelled_amount']);
    }

    public function test_pending_bills_and_inventory_matching_remain_separately_visible(): void
    {
        // Invoice without GoodsReceived (Pending inventory match)
        $grn = GoodsReceived::factory()->create();
        PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'purchaser_submitted_by' => $this->activePurchaser->id,
            'invoice_number' => 'INV-NO-GRN-01',
            'amount' => 7000.00,
            'discount_amount' => 0.00,
            'goods_received_id' => $grn->id,
            'payment_method' => 'Cash',
            'status' => InvoiceStatus::Approved,
            'original_business_date' => '2026-08-18',
        ]);

        // Unbilled Cart
        PurchaserCart::create([
            'user_id' => $this->activePurchaser->id,
            'supplier_id' => $this->vendor->id,
            'business_date' => '2026-08-20',
            'status' => 'submitted',
            'cart_number' => 'CART-PENDING-01',
            'purchase_invoice_id' => null,
        ]);

        $service = app(PurchaserMonthlySummaryService::class);
        $detail = $service->getPurchaserMonthlyDetail($this->activePurchaser, '2026-08');

        $this->assertEquals(1, $detail['bills']['inventory_matched_count']);
        $this->assertEquals(1, $detail['pending']['pending_bills_count']);
    }

    public function test_september_activity_does_not_alter_august_historical_totals(): void
    {
        // August Funding
        PurchaserCredit::create([
            'purchaser_id' => $this->activePurchaser->id,
            'company_account_id' => $this->companyAccount->id,
            'business_date' => '2026-08-01',
            'type' => 'in',
            'amount' => 40000.00,
        ]);

        $service = app(PurchaserMonthlySummaryService::class);
        $augDetailBefore = $service->getPurchaserMonthlyDetail($this->activePurchaser, '2026-08');

        // September Funding & Purchase added
        PurchaserCredit::create([
            'purchaser_id' => $this->activePurchaser->id,
            'company_account_id' => $this->companyAccount->id,
            'business_date' => '2026-09-05',
            'type' => 'in',
            'amount' => 60000.00,
        ]);

        PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'purchaser_submitted_by' => $this->activePurchaser->id,
            'invoice_number' => 'INV-SEPT-01',
            'amount' => 25000.00,
            'discount_amount' => 0.00,
            'payment_method' => 'Cash',
            'status' => InvoiceStatus::Approved,
            'original_business_date' => '2026-09-08',
        ]);

        $augDetailAfter = $service->getPurchaserMonthlyDetail($this->activePurchaser, '2026-08');

        // August totals must be completely unchanged
        $this->assertEquals($augDetailBefore['activity']['company_funded'], $augDetailAfter['activity']['company_funded']);
        $this->assertEquals($augDetailBefore['closing']['cash_balance'], $augDetailAfter['closing']['cash_balance']);

        // September opening must equal August closing (40,000)
        $septDetail = $service->getPurchaserMonthlyDetail($this->activePurchaser, '2026-09');
        $this->assertEquals(40000.00, $septDetail['opening']['cash_balance']);
        $this->assertEquals(60000.00, $septDetail['activity']['company_funded']);
    }

    public function test_viewing_purchase_month_causes_zero_database_mutations(): void
    {
        $this->actingAs($this->admin);

        $initialCreditsCount = PurchaserCredit::query()->count();
        $initialInvoicesCount = PurchaseInvoice::query()->count();
        $initialUsersCount = User::query()->count();

        $responseIndex = $this->get(route('admin.cashbook.finance.purchase.monthly-summary.index', ['month' => '2026-08']));
        $responseIndex->assertOk();

        $responseShow = $this->get(route('admin.cashbook.finance.purchase.monthly-summary.show', [
            'purchaser' => $this->activePurchaser->public_uuid,
            'month' => '2026-08',
        ]));
        $responseShow->assertOk();

        $this->assertEquals($initialCreditsCount, PurchaserCredit::query()->count());
        $this->assertEquals($initialInvoicesCount, PurchaseInvoice::query()->count());
        $this->assertEquals($initialUsersCount, User::query()->count());
    }

    public function test_sidebar_navigation_renders_monthly_reports_with_correct_sublinks_and_active_state(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.monthly-summary.index'));
        $response->assertOk();

        // Must see Monthly Reports parent
        $response->assertSee('Monthly Reports');
        $response->assertSee('Shop Monthly Closing');
        $response->assertSee('Purchase Month');

        // Verify Shop Monthly Closing page also shows Monthly Reports
        $shopResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-closing-summary.index'));
        $shopResponse->assertOk();
        $shopResponse->assertSee('Monthly Reports');
        $shopResponse->assertSee('Shop Monthly Closing');
        $shopResponse->assertSee('Purchase Month');
    }
}
