<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\Cashbook\CompanyAccount;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Services\Cashbook\CashFlow\CashFlowTreeService;
use App\Services\Cashbook\CashFlow\Sources\VendorCreditCashFlowSource;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashFlowTreeVendorCreditTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Supplier $vendor;

    private CompanyAccount $bank;

    private User $purchaser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->vendor = Supplier::factory()->create(['name' => 'Kisan Agro Traders']);

        $this->bank = CompanyAccount::create([
            'name' => 'Company HDFC',
            'bank_name' => 'HDFC BANK',
            'account_type' => 'bank',
            'opening_balance' => 1000000.00,
            'current_balance' => 1000000.00,
            'enabled' => true,
        ]);

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Ali']);
        $this->purchaser->assignRole('purchaser');
    }

    public function test_vendor_credit_branch_tracks_opening_bills_settlement_and_discount(): void
    {
        // 1. Credit invoice before September (August 20): 100,000
        $invAugust = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'payment_method' => 'Credit',
            'amount' => 100000.00,
            'discount_amount' => 0.00,
            'status' => InvoiceStatus::Approved,
        ]);
        DB::table('purchase_invoices')->where('id', $invAugust->id)->update(['created_at' => '2026-08-20 10:00:00']);

        // 2. New credit bill in September (September 05): 50,000
        $invSept = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'payment_method' => 'Credit',
            'amount' => 50000.00,
            'discount_amount' => 0.00,
            'status' => InvoiceStatus::Approved,
        ]);
        DB::table('purchase_invoices')->where('id', $invSept->id)->update(['created_at' => '2026-09-05 11:00:00']);

        // 3. Paid by Company in September (September 12): 80,000 cash + 5,000 discount
        VendorSettlement::create([
            'supplier_id' => $this->vendor->id,
            'actual_payment_amount' => 80000.00,
            'settlement_discount_amount' => 5000.00,
            'vendor_advance_used_amount' => 0.00,
            'new_vendor_advance_amount' => 0.00,
            'company_account_id' => $this->bank->id,
            'payment_date' => '2026-09-12',
            'reference' => 'SETTLE-001',
            'status' => 'completed',
            'created_by' => $this->purchaser->id,
        ]);

        $source = app(VendorCreditCashFlowSource::class);
        $openings = $source->openingBalances('2026-09-01', ['vendor_id' => $this->vendor->id]);

        $this->assertArrayHasKey($this->vendor->id, $openings);
        $this->assertEquals(100000.0, $openings[$this->vendor->id]['opening_payable']);

        $service = app(CashFlowTreeService::class);
        $result = $service->build('2026-09', ['vendor_id' => $this->vendor->id]);

        $vendorsBranch = collect($result['tree']->children)->firstWhere('id', 'branch_vendors');
        $this->assertNotNull($vendorsBranch);

        $vendorNode = collect($vendorsBranch->children)->firstWhere('id', "vendor_{$this->vendor->id}");
        $this->assertNotNull($vendorNode);

        // Invariant: Opening (100k) + New Bills (50k) - Paid (80k) - Discount (5k) = Closing (65,000)
        $this->assertEquals(100000.0, $vendorNode->openingBalance);
        $this->assertEquals(50000.0, $vendorNode->totalIn);
        $this->assertEquals(85000.0, $vendorNode->totalOut); // 80k paid + 5k discount
        $this->assertEquals(65000.0, $vendorNode->closingBalance);
    }

    public function test_purchaser_cash_purchases_and_vendor_credit_settlement_do_not_duplicate(): void
    {
        // 1. Purchaser cash purchase: 20,000 to Kisan Agro Traders
        $cashInv = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'amount' => 20000.00,
            'discount_amount' => 0.00,
            'status' => InvoiceStatus::Approved,
        ]);
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'business_date' => '2026-09-03',
            'type' => 'out',
            'amount' => 20000.00,
            'purchase_invoice_id' => $cashInv->id,
            'description' => 'Cash purchase at mandi',
        ]);

        // 2. Separate Credit invoice: 30,000
        $creditInv = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'payment_method' => 'Credit',
            'amount' => 30000.00,
            'discount_amount' => 0.00,
            'status' => InvoiceStatus::Approved,
        ]);
        DB::table('purchase_invoices')->where('id', $creditInv->id)->update(['created_at' => '2026-09-04 10:00:00']);

        // 3. Company settlement for credit: 30,000
        VendorSettlement::create([
            'supplier_id' => $this->vendor->id,
            'actual_payment_amount' => 30000.00,
            'settlement_discount_amount' => 0.00,
            'vendor_advance_used_amount' => 0.00,
            'new_vendor_advance_amount' => 0.00,
            'company_account_id' => $this->bank->id,
            'payment_date' => '2026-09-10',
            'reference' => 'NEFT-881',
            'status' => 'completed',
            'created_by' => $this->purchaser->id,
        ]);

        $service = app(CashFlowTreeService::class);
        $result = $service->build('2026-09');

        $purchasersBranch = collect($result['tree']->children)->firstWhere('id', 'branch_purchasers');
        $vendorsBranch = collect($result['tree']->children)->firstWhere('id', 'branch_vendors');

        // Cash purchase appears under Purchaser
        $this->assertEquals(20000.0, $purchasersBranch->totalOut);

        // Credit settlement appears under Vendors
        $vendorNode = collect($vendorsBranch->children)->firstWhere('id', "vendor_{$this->vendor->id}");
        $this->assertNotNull($vendorNode);
        $this->assertEquals(30000.0, $vendorNode->totalIn);
        $this->assertEquals(30000.0, $vendorNode->totalOut);
        $this->assertEquals(0.0, $vendorNode->closingBalance);
    }
}
