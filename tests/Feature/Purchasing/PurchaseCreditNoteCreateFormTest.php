<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseCreditNote;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PurchaseCreditNoteCreateFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Product $product;

    private Warehouse $warehouse;

    private CompanyAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1200'], ['name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1400'], ['name' => 'Vendor Advances', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);

        $this->admin = User::factory()->create(['name' => 'Office Staff']);
        $this->admin->assignRole('admin');

        $this->supplier = Supplier::factory()->create([
            'name' => 'Abdulla Estates',
            'contact' => '7903749918',
            'mobile_number' => '7903749918',
            'credit_approved' => true,
        ]);

        $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
        $this->product = Product::factory()->create(['unit' => 'kg']);
        $this->supplier->products()->attach($this->product->id);

        ProductUnit::create([
            'product_id' => $this->product->id,
            'unit' => 'box',
            'label' => 'Box (10kg)',
            'conversion_to_base' => 10.0,
            'is_default' => false,
        ]);

        $this->cashAccount = CompanyAccount::create([
            'name' => 'Main Cash',
            'account_type' => 'cash',
            'opening_balance' => 10000.00,
            'current_balance' => 10000.00,
            'enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_credit_date_uses_asia_kolkata_calendar_date_without_rollover(): void
    {
        // Midnight / early morning IST (02:30 IST = previous day 21:00 UTC)
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 2, 30, 0, 'Asia/Kolkata'));

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.purchase-credit-notes.create', ['supplier' => $this->supplier->public_uuid]))
            ->assertOk()
            ->assertViewHas('defaultCreditDate', '2026-11-26')
            ->assertSee('Internal ERP Credit Note')
            ->assertSee('Auto-generated on posting')
            ->assertSee('7903749918')
            ->assertDontSee('new Date().toISOString().slice(0, 10)');

        // After 9:30 PM IST (22:15 IST) must still default to 2026-11-26 (no purchaser rollover)
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 22, 15, 0, 'Asia/Kolkata'));

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.vendors.credit-notes.create', ['supplier' => $this->supplier->public_uuid]))
            ->assertOk()
            ->assertViewHas('defaultCreditDate', '2026-11-26');
    }

    public function test_validation_failure_preserves_submitted_form_inputs_and_rows(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.cashbook.finance.purchase.vendors.credit-notes.create', ['supplier' => $this->supplier->public_uuid]))
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => '2026-11-20',
                'reason' => PurchaseCreditReason::PriceAdjustment->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::None->value,
                'notes' => 'Preserve this remark',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'B',
                        'unit' => 'box',
                        'quantity' => 0, // invalid (>0 required)
                        'unit_price' => 500,
                    ],
                ],
            ]);

        $response->assertRedirect()
            ->assertSessionHasErrors('items.0.quantity')
            ->assertSessionHasInput('credit_date', '2026-11-20')
            ->assertSessionHasInput('notes', 'Preserve this remark')
            ->assertSessionHasInput('items.0.unit', 'box')
            ->assertSessionHasInput('items.0.grade', 'B');
    }

    public function test_http_store_applies_unit_conversion_and_blocks_duplicate_submissions(): void
    {
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_grade' => ProductGrade::GradeA->value,
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
            'sorted_at' => now(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'received_at' => '2026-11-20',
            'total_kg' => 100.0,
            'cost_per_kg' => 100.0,
            'reference' => 'CREATE-TEST-BATCH',
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA->value,
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 100.0,
            'created_by' => $this->admin->id,
            'notes' => 'Initial stock',
        ]);

        $payload = [
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::Return->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::Out->value,
            'warehouse_id' => $this->warehouse->id,
            'submission_token' => 'unique-submit-token-123',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'unit' => 'box',
                    'quantity' => 2,
                    'unit_price' => 1000,
                ],
            ],
        ];

        // First submission creates 1 credit note with 2 boxes = 20 kg base_qty
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), $payload)
            ->assertRedirect(route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $this->supplier->public_uuid]));

        $this->assertEquals(1, PurchaseCreditNote::count());
        $note = PurchaseCreditNote::firstOrFail();
        $item = $note->items()->firstOrFail();
        $this->assertEquals('box', $item->unit);
        $this->assertEquals(20.0, (float) $item->base_qty);
        $this->assertEquals(2000.0, (float) $note->total_amount);

        // Duplicate submission with same token is rejected idempotently
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), $payload)
            ->assertRedirect(route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $this->supplier->public_uuid]));

        $this->assertEquals(1, PurchaseCreditNote::count());

        // Simulating cache expiry (after 10 minutes) STILL blocks resubmission thanks to durable DB submission_token
        Cache::forget("pcn_submission:{$this->admin->id}:unique-submit-token-123");
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), $payload)
            ->assertRedirect(route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $this->supplier->public_uuid]));

        $this->assertEquals(1, PurchaseCreditNote::count());

        // Missing submission_token still works for API/legacy callers without error
        unset($payload['submission_token']);
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), $payload)
            ->assertRedirect(route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $this->supplier->public_uuid]));

        $this->assertEquals(2, PurchaseCreditNote::count());
    }
}
