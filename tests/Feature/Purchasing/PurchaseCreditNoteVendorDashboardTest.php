<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Services\Finance\VendorSettlementService;
use App\Services\Purchasing\PurchaseCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseCreditNoteVendorDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Supplier $vendorAlpha;

    private Supplier $vendorBeta;

    private Product $product;

    private PurchaseCreditNoteService $pcnService;

    private VendorSettlementService $settlementService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1200'], ['name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1400'], ['name' => 'Vendor Advances', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4200'], ['name' => 'Vendor Settlement Discounts', 'type' => 'revenue', 'is_active' => true]);

        $this->pcnService = app(PurchaseCreditNoteService::class);
        $this->settlementService = app(VendorSettlementService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create();

        $this->vendorAlpha = Supplier::factory()->create([
            'name' => 'Alpha Tea Estates',
            'contact' => 'Ramesh',
            'mobile_number' => '9876543210',
            'credit_approved' => true,
        ]);

        $this->vendorBeta = Supplier::factory()->create([
            'name' => 'Beta Green Farms',
            'contact' => 'Suresh',
            'mobile_number' => '9123456789',
            'credit_approved' => true,
        ]);

        $this->product = Product::factory()->create(['unit' => 'kg']);
        $this->vendorAlpha->products()->attach($this->product->id);
        $this->vendorBeta->products()->attach($this->product->id);

        CompanyAccount::create([
            'name' => 'Main Cash Till',
            'account_type' => 'cash',
            'opening_balance' => 50000.00,
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);
    }

    public function test_vendor_search_supports_name_contact_mobile_and_caps_at_20_results(): void
    {
        Supplier::factory()->count(25)->create(['name' => 'Bulk Supplier']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-vendors', ['q' => 'Bulk']))
            ->assertOk()
            ->assertJsonCount(20, 'items');

        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-vendors', ['q' => '9876543210']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.public_uuid', $this->vendorAlpha->public_uuid);

        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-vendors', ['q' => 'Ramesh']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $this->vendorAlpha->id);
    }

    public function test_index_does_not_preload_all_suppliers_and_preserves_vendor_selection_across_pagination(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            PurchaseCreditNote::create([
                'credit_note_number' => sprintf('PCN-20261126-A%04d', $i),
                'supplier_id' => $this->vendorAlpha->id,
                'credit_date' => '2026-11-26',
                'reason' => PurchaseCreditReason::PriceAdjustment->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::None->value,
                'total_amount' => 100.00,
                'applied_to_invoice_amount' => 0.00,
                'created_by' => $this->admin->id,
            ]);
        }

        PurchaseCreditNote::create([
            'credit_note_number' => 'PCN-20261126-BETA1',
            'supplier_id' => $this->vendorBeta->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'total_amount' => 250.00,
            'applied_to_invoice_amount' => 0.00,
            'created_by' => $this->admin->id,
        ]);

        // Initial page without vendor selected should NOT preload all suppliers and hides Vendor Financial Position
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.purchase-credit-notes.index'))
            ->assertOk()
            ->assertViewHas('selectedSupplier', null);

        // Filtered by Alpha preserves selection and filters listing + pagination links
        $filtered = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.purchase-credit-notes.index', [
                'supplier_id' => $this->vendorAlpha->id,
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertViewHas('selectedSupplier', fn (?array $s): bool => ($s['id'] ?? null) === $this->vendorAlpha->id)
            ->assertDontSee('PCN-20261126-BETA1')
            ->assertSee('supplier_id='.$this->vendorAlpha->id);

        $filtered->assertSee('page=2');
    }

    public function test_vendor_financial_summary_accuracy_legacy_cash_applied_cn_partial_advance_and_void_exclusion(): void
    {
        $grn1 = GoodsReceived::factory()->create();
        $grn2 = GoodsReceived::factory()->create();
        $grn3 = GoodsReceived::factory()->create();

        // Invoice 1: 1000.00, legacy cash paid_amount = 400.00 (no allocation) -> remaining 600.00
        PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendorAlpha->id,
            'goods_received_id' => $grn1->id,
            'amount' => 1000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 400.00,
            'status' => 'approved',
        ]);

        // Invoice 2: 500.00, legacy paid 300.00 -> remaining 200.00 -> will receive 200.00 applied from PCN #1 -> remaining 0.00
        $invoice2 = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendorAlpha->id,
            'goods_received_id' => $grn2->id,
            'amount' => 500.00,
            'discount_amount' => 0.00,
            'paid_amount' => 300.00,
            'status' => 'approved',
        ]);

        // Invoice 3: 300.00, unpaid -> will be partially settled using 150.00 from PCN advance
        $invoice3 = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendorAlpha->id,
            'goods_received_id' => $grn3->id,
            'amount' => 300.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
        ]);

        // Cancelled invoice should be excluded from all totals
        PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendorAlpha->id,
            'goods_received_id' => GoodsReceived::factory()->create()->id,
            'amount' => 9999.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'status' => 'cancelled',
        ]);

        // PCN #1: 500.00 VendorCredit linked to Invoice 2 (200.00 applied to Invoice 2, 300.00 VendorAdvance)
        $this->pcnService->create([
            'supplier_id' => $this->vendorAlpha->id,
            'purchase_invoice_id' => $invoice2->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 5, 'unit_price' => 100]],
        ], $this->admin);

        // PCN #2: 250.00 VendorCredit, then VOIDED (must be excluded from active counts, amounts, and advances)
        $voidedPcn = $this->pcnService->create([
            'supplier_id' => $this->vendorAlpha->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 5, 'unit_price' => 50]],
        ], $this->admin);
        $this->pcnService->void($voidedPcn, 'Voided in test', $this->admin);

        // Non-PCN standalone VendorAdvance of 120.00 (open advances = 300 + 120 = 420 before settlement)
        VendorAdvance::create([
            'supplier_id' => $this->vendorAlpha->id,
            'amount_original' => 120.00,
            'amount_remaining' => 120.00,
            'business_date' => '2026-11-26',
            'payment_method' => 'cash',
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        // Partially utilize 150.00 of the 300.00 PCN advance to settle part of Invoice 3
        $this->settlementService->create($this->vendorAlpha, [
            'payment_date' => '2026-11-26',
            'payment_method' => 'cash',
            'actual_payment_amount' => 0.00,
            'vendor_advance_used_amount' => 150.00,
            'settlement_discount_amount' => 0.00,
            'allocations' => [
                [
                    'purchase_invoice_id' => $invoice3->id,
                    'cash_allocated' => 0.00,
                    'advance_allocated' => 150.00,
                    'discount_allocated' => 0.00,
                ],
            ],
        ], $this->admin->id);

        // Expected totals:
        // Total Purchases = 1000 + 500 + 300 = 1800.00
        // Total Settled = 400 (inv1 legacy) + 300 (inv2 legacy) + 150 (inv3 settlement) = 850.00
        // Outstanding Balance = (1000-400) + (500-300-200) + (300-150) = 600 + 0 + 150 = 750.00
        // Pending Bills Count = 2
        // Total Open Vendor Advances = (300 - 150) + 120 = 270.00
        // Available PCN Balance (subset) = 150.00
        // Active PCN Count = 1, Active PCN Amount = 500.00

        DB::enableQueryLog();
        DB::flushQueryLog();

        $res = $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.vendor-summary', [
                'supplier_id' => $this->vendorAlpha->id,
            ]))
            ->assertOk()
            ->assertJsonPath('supplier.public_uuid', $this->vendorAlpha->public_uuid)
            ->assertJsonPath('more_details_url', route('admin.cashbook.finance.purchase.vendors.show', [
                'supplier' => $this->vendorAlpha->public_uuid,
            ]));

        $summary = $res->json('summary');
        $this->assertEquals(1800.0, (float) $summary['total_purchases']);
        $this->assertEquals(850.0, (float) $summary['total_settled']);
        $this->assertEquals(750.0, (float) $summary['outstanding_balance']);
        $this->assertEquals(150.0, (float) $summary['available_credit_note_balance']);
        $this->assertEquals(270.0, (float) $summary['total_open_vendor_advances']);
        $this->assertSame(2, (int) $summary['pending_bills_count']);
        $this->assertSame(1, (int) $summary['total_active_credit_notes']);
        $this->assertEquals(500.0, (float) $summary['total_active_credit_note_amount']);

        // Bounded queries: supplier lookup + 3 summary queries (+ auth/permission checks) <= 10 queries
        $this->assertLessThanOrEqual(10, count(DB::getQueryLog()));
    }

    public function test_unauthorized_user_cannot_access_vendor_search_or_summary_endpoints(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-vendors', ['q' => 'Alpha']))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->getJson(route('admin.cashbook.purchase-credit-notes.vendor-summary', ['supplier_id' => $this->vendorAlpha->id]))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.vendor-summary', ['supplier_id' => 999999]))
            ->assertUnprocessable();
    }
}
