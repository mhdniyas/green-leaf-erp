<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Models\Account;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaseCreditNoteV2Phase3UiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Product $product;

    private PurchaseInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        foreach ([
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => 'asset'],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Graded Inventory', 'type' => 'asset'],
            ['code' => '1400', 'name' => 'Vendor Advances', 'type' => 'asset'],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '4200', 'name' => 'Vendor Settlement Discounts', 'type' => 'revenue'],
            ['code' => '5200', 'name' => 'Wastage Expense', 'type' => 'expense'],
        ] as $acc) {
            Account::firstOrCreate(['code' => $acc['code']], $acc + ['is_active' => true]);
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->supplier = Supplier::factory()->create(['mobile_number' => '+91 98765 43210']);
        $this->product = Product::factory()->create(['unit' => 'kg']);
        $this->supplier->products()->attach($this->product->id);

        ProductUnit::create([
            'product_id' => $this->product->id,
            'unit' => 'box',
            'label' => 'Box (10 kg)',
            'conversion_to_base' => 10.0,
            'is_default' => false,
        ]);

        $grn = GoodsReceived::factory()->create();
        $this->invoice = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'goods_received_id' => $grn->id,
            'invoice_number' => 'GLPB26-1126-TEST',
            'amount' => 10000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_v2_create_form_renders_reference_only_and_v1_compatibility(): void
    {
        config(['greenleaf.purchase_credit_notes_v2_enabled' => true]);

        $resV2 = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.vendors.credit-notes.create', $this->supplier))
            ->assertOk();

        $resV2->assertSee('New Available Vendor Credit')
            ->assertSee('Reference Only')
            ->assertSee('Supplier Credit Note Ref (Optional)')
            ->assertSee('Add to Vendor Credit Balance')
            ->assertSee('No Stock Movement')
            ->assertSee('Import Invoice Products')
            ->assertSee(Carbon::now('Asia/Kolkata')->toDateString())
            ->assertDontSee('Applied to Selected Bill');

        // Legacy V1 mode renders V1 labels
        config(['greenleaf.purchase_credit_notes_v2_enabled' => false]);

        $resV1 = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.vendors.credit-notes.create', $this->supplier))
            ->assertOk();

        $resV1->assertSee('Invoice Adjustment')
            ->assertSee('Excess Vendor Advance')
            ->assertDontSee('New Available Vendor Credit');
    }

    public function test_http_v2_store_persists_supplier_ref_keeps_invoice_unchanged_and_enforces_server_version(): void
    {
        config(['greenleaf.purchase_credit_notes_v2_enabled' => true]);

        $payload = [
            'credit_date' => Carbon::now('Asia/Kolkata')->toDateString(),
            'vendor_credit_note_ref' => 'SUP-CN-9988',
            'purchase_invoice_id' => $this->invoice->id,
            'accounting_version' => 'v1', // forged input must be ignored by server
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'submission_token' => 'v2-http-token-001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => ProductGrade::GradeA->value,
                    'unit' => 'box',
                    'quantity' => 2,
                    'unit_price' => 1500, // 2 boxes = 20kg, total = 3000
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), $payload)
            ->assertRedirect(route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $this->supplier->public_uuid]));

        $note = PurchaseCreditNote::firstOrFail();
        $this->assertSame('v2', $note->accounting_version);
        $this->assertSame('SUP-CN-9988', $note->vendor_credit_note_ref);
        $this->assertEquals(3000.0, (float) $note->total_amount);
        $this->assertEquals(0.0, (float) $note->applied_to_invoice_amount);
        $this->assertEquals(10000.0, $this->invoice->fresh()->remainingBalance());

        // Conflicting payload with same submission_token is rejected
        $payload['items'][0]['unit_price'] = 2000;
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), $payload)
            ->assertSessionHasErrors('submission_token');

        // Show page renders V2 utilization details & supplier ref
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.vendors.credit-notes.show', [$this->supplier, $note]))
            ->assertOk()
            ->assertSee('SUP-CN-9988')
            ->assertSee('Invoice Ref (Reference Only):')
            ->assertSee('Settlement Allocation History')
            ->assertSee('Original Credit:');
    }

    public function test_validation_error_preserves_vendor_credit_note_ref_and_inputs(): void
    {
        config(['greenleaf.purchase_credit_notes_v2_enabled' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => '2026-11-25',
                'vendor_credit_note_ref' => 'SUP-REF-KEEP',
                'reason' => PurchaseCreditReason::PriceAdjustment->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::None->value,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'unit' => 'box',
                        'quantity' => 0,
                        'unit_price' => 500,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.quantity')
            ->assertSessionHasInput('vendor_credit_note_ref', 'SUP-REF-KEEP')
            ->assertSessionHasInput('credit_date', '2026-11-25');
    }
}
