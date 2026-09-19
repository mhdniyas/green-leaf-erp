<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseInvoiceRemainingBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'purchaser']);
        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->supplier = Supplier::factory()->create([
            'credit_approved' => true,
        ]);
    }

    public function test_invoice_with_cash_and_settlement_discount_has_zero_remaining_balance(): void
    {
        // Invoice 1000, cash paid 800, settlement discount 200 => remaining 0
        $cart = PurchaserCart::query()->create([
            'cart_number' => 'VC-TEST-001',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => now()->format('Y-m-d'),
            'status' => 'submitted',
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
        ]);

        $invoice = PurchaseInvoice::factory()
            ->for($this->supplier)
            ->create([
                'purchaser_cart_id' => $cart->id,
                'invoice_number' => 'INV-TEST-1000',
                'amount' => 1000.00,
                'discount_amount' => 0.00,
                'paid_amount' => 800.00,
                'payment_status' => 'paid',
                'payment_method' => 'Bank',
            ]);

        $cart->update(['purchase_invoice_id' => $invoice->id]);

        $settlement = VendorSettlement::query()->create([
            'supplier_id' => $this->supplier->id,
            'actual_payment_amount' => 800.00,
            'settlement_discount_amount' => 200.00,
            'vendor_advance_used_amount' => 0.00,
            'new_vendor_advance_amount' => 0.00,
            'payment_method' => 'Bank',
            'payment_date' => now()->format('Y-m-d'),
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $invoice->id,
            'cash_allocated' => 800.00,
            'advance_allocated' => 0.00,
            'discount_allocated' => 200.00,
            'total_settled' => 1000.00,
        ]);

        $this->assertEquals(0.00, $invoice->remainingBalance());
        $this->assertEquals(200.00, $invoice->settlementDiscountTotal());
    }

    public function test_invoice_cleared_fully_by_settlement_discount_has_zero_remaining_balance(): void
    {
        // Invoice 90, cash 0, settlement discount 90 => remaining 0
        $cart = PurchaserCart::query()->create([
            'cart_number' => 'VC-TEST-002',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => now()->format('Y-m-d'),
            'status' => 'submitted',
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
        ]);

        $invoice = PurchaseInvoice::factory()
            ->for($this->supplier)
            ->create([
                'purchaser_cart_id' => $cart->id,
                'invoice_number' => 'INV-TEST-090',
                'amount' => 90.00,
                'discount_amount' => 0.00,
                'paid_amount' => 0.00,
                'payment_status' => 'paid',
                'payment_method' => 'Bank',
            ]);

        $cart->update(['purchase_invoice_id' => $invoice->id]);

        $settlement = VendorSettlement::query()->create([
            'supplier_id' => $this->supplier->id,
            'actual_payment_amount' => 0.00,
            'settlement_discount_amount' => 90.00,
            'vendor_advance_used_amount' => 0.00,
            'new_vendor_advance_amount' => 0.00,
            'payment_method' => 'Bank',
            'payment_date' => now()->format('Y-m-d'),
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $invoice->id,
            'cash_allocated' => 0.00,
            'advance_allocated' => 0.00,
            'discount_allocated' => 90.00,
            'total_settled' => 90.00,
        ]);

        $this->assertEquals(0.00, $invoice->remainingBalance());
        $this->assertEquals(90.00, $invoice->settlementDiscountTotal());
    }

    public function test_purchaser_supplier_show_page_displays_completed_for_settled_invoices(): void
    {
        $date = now()->format('Y-m-d');

        $cart = PurchaserCart::query()->create([
            'cart_number' => 'VC-TEST-003',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'status' => 'submitted',
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
        ]);

        $invoice = PurchaseInvoice::factory()
            ->for($this->supplier)
            ->create([
                'purchaser_cart_id' => $cart->id,
                'invoice_number' => 'PENDING-BILL-VC-TEST-003',
                'amount' => 12558.00,
                'discount_amount' => 0.00,
                'paid_amount' => 10174.12,
                'payment_status' => 'paid',
                'payment_method' => 'Bank',
            ]);

        $cart->update(['purchase_invoice_id' => $invoice->id]);

        $settlement = VendorSettlement::query()->create([
            'supplier_id' => $this->supplier->id,
            'actual_payment_amount' => 10174.12,
            'settlement_discount_amount' => 2383.88,
            'vendor_advance_used_amount' => 0.00,
            'new_vendor_advance_amount' => 0.00,
            'payment_method' => 'Bank',
            'payment_date' => $date,
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $invoice->id,
            'cash_allocated' => 10174.12,
            'advance_allocated' => 0.00,
            'discount_allocated' => 2383.88,
            'total_settled' => 12558.00,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.suppliers.show', [
                'supplier' => $this->supplier,
                'date' => $date,
            ]));

        $response->assertOk();
        $response->assertViewHas('historyTotals', function (array $totals): bool {
            return $totals['pending_amount'] === 0.0
                && $totals['paid_amount'] === 10174.12
                && $totals['total_amount'] === 12558.0
                && $totals['discount_amount'] === 2383.88;
        });
    }
}
