<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Services\Finance\VendorSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorAdvanceApiTest extends TestCase
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
            'name' => 'ABC Traders',
        ]);
    }

    public function test_vendor_advance_can_be_recorded_without_bill(): void
    {
        Sanctum::actingAs($this->purchaser);

        $response = $this->postJson('/api/v1/purchasing/vendor-advances', [
            'supplier_id' => $this->supplier->id,
            'amount' => 20000.00,
            'date' => '2026-08-26',
            'payment_method' => 'upi',
            'reference' => 'UPI-REF-987654',
            'notes' => 'Advance payment for tomorrow morning harvest',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.supplier_id', $this->supplier->id)
            ->assertJsonPath('data.supplier_name', 'ABC Traders')
            ->assertJsonPath('data.payment_method', 'upi')
            ->assertJsonPath('data.reference', 'UPI-REF-987654')
            ->assertJsonPath('data.status', 'bill_pending')
            ->assertJsonPath('data.status_label', 'BILL PENDING')
            ->assertJsonPath('data.is_bill_pending', true);

        $this->assertEquals(20000.0, (float) $response->json('data.amount_original'));
        $this->assertEquals(20000.0, (float) $response->json('data.amount_remaining'));

        $this->assertDatabaseHas('vendor_advances', [
            'supplier_id' => $this->supplier->id,
            'amount_original' => 20000.00,
            'amount_remaining' => 20000.00,
            'payment_method' => 'upi',
            'reference' => 'UPI-REF-987654',
            'status' => 'open',
            'source_settlement_id' => null,
        ]);
    }

    public function test_vendor_advance_recording_does_not_create_purchase_invoice(): void
    {
        Sanctum::actingAs($this->purchaser);

        $this->assertSame(0, PurchaseInvoice::count());

        $this->postJson('/api/v1/purchasing/vendor-advances', [
            'supplier_id' => $this->supplier->id,
            'amount' => 15000.00,
            'date' => '2026-08-26',
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->assertSame(0, PurchaseInvoice::count(), 'No PurchaseInvoice should be created on advance receive.');
    }

    public function test_vendor_advance_index_lists_advances_with_bill_pending_status(): void
    {
        Sanctum::actingAs($this->purchaser);

        VendorAdvance::create([
            'supplier_id' => $this->supplier->id,
            'amount_original' => 20000.00,
            'amount_remaining' => 20000.00,
            'business_date' => '2026-08-26',
            'payment_method' => 'bank_transfer',
            'reference' => 'TXN-101',
            'status' => 'open',
            'created_by' => $this->purchaser->id,
        ]);

        $response = $this->getJson('/api/v1/purchasing/vendor-advances');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.supplier_name', 'ABC Traders')
            ->assertJsonPath('data.0.status_label', 'BILL PENDING')
            ->assertJsonPath('data.0.is_bill_pending', true);

        $this->assertEquals(20000.0, (float) $response->json('data.0.amount'));
    }

    public function test_later_bill_settlement_allocates_and_settles_advance_using_existing_mechanism(): void
    {
        $advance = VendorAdvance::create([
            'supplier_id' => $this->supplier->id,
            'amount_original' => 20000.00,
            'amount_remaining' => 20000.00,
            'business_date' => '2026-08-20',
            'payment_method' => 'cash',
            'status' => 'open',
            'created_by' => $this->purchaser->id,
        ]);

        // Later, vendor sends a bill of ₹20,000
        $invoice = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-ABC-20260826-01',
            'amount' => 20000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
            'status' => 'approved',
        ]);

        // Settle the bill using the advance via existing VendorSettlementService
        /** @var VendorSettlementService $settlementService */
        $settlementService = app(VendorSettlementService::class);
        $settlementService->create($this->supplier, [
            'actual_payment_amount' => 0.00,
            'settlement_discount_amount' => 0.00,
            'vendor_advance_used_amount' => 20000.00,
            'payment_date' => '2026-08-26',
            'payment_method' => 'advance',
            'reference' => 'SETTLE-ADV-1',
            'note' => 'Bill settlement from advance',
            'allocations' => [
                [
                    'purchase_invoice_id' => $invoice->id,
                    'cash_allocated' => 0.00,
                    'advance_allocated' => 20000.00,
                    'discount_allocated' => 0.00,
                ],
            ],
        ], $this->purchaser->id);

        $advance->refresh();
        $this->assertEquals(0.00, (float) $advance->amount_remaining);
        $this->assertEquals('used', $advance->status);
        $this->assertEquals('SETTLED', $advance->status_label);
        $this->assertFalse($advance->isBillPending());

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->payment_status);
    }

    public function test_partial_advance_allocation_updates_remaining_amount(): void
    {
        $advance = VendorAdvance::create([
            'supplier_id' => $this->supplier->id,
            'amount_original' => 20000.00,
            'amount_remaining' => 20000.00,
            'business_date' => '2026-08-20',
            'payment_method' => 'cash',
            'status' => 'open',
            'created_by' => $this->purchaser->id,
        ]);

        // Partial bill of ₹8,000 arrives
        $invoice = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-ABC-PARTIAL',
            'amount' => 8000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
            'status' => 'approved',
        ]);

        /** @var VendorSettlementService $settlementService */
        $settlementService = app(VendorSettlementService::class);
        $settlementService->create($this->supplier, [
            'actual_payment_amount' => 0.00,
            'settlement_discount_amount' => 0.00,
            'vendor_advance_used_amount' => 8000.00,
            'payment_date' => '2026-08-26',
            'payment_method' => 'advance',
            'reference' => 'SETTLE-ADV-PARTIAL',
            'note' => 'Partial settlement',
            'allocations' => [
                [
                    'purchase_invoice_id' => $invoice->id,
                    'cash_allocated' => 0.00,
                    'advance_allocated' => 8000.00,
                    'discount_allocated' => 0.00,
                ],
            ],
        ], $this->purchaser->id);

        $advance->refresh();
        $this->assertEquals(12000.00, (float) $advance->amount_remaining);
        $this->assertEquals('open', $advance->status);
        $this->assertEquals('PARTIALLY SETTLED', $advance->status_label);
        $this->assertFalse($advance->isBillPending());
    }

    public function test_unauthorized_user_cannot_access_vendor_advances(): void
    {
        $guestUser = User::factory()->create();
        Sanctum::actingAs($guestUser);

        $this->getJson('/api/v1/purchasing/vendor-advances')->assertForbidden();
        $this->postJson('/api/v1/purchasing/vendor-advances', [
            'supplier_id' => $this->supplier->id,
            'amount' => 1000.00,
        ])->assertForbidden();
    }
}
