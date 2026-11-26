<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Models\Product;
use App\Models\PurchaseCreditNote;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalPurchaseCreditNoteNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplierA;

    private Supplier $supplierB;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->supplierA = Supplier::factory()->create(['name' => 'Alpha Farms']);
        $this->supplierB = Supplier::factory()->create(['name' => 'Beta Orchards']);

        $this->product = Product::factory()->create(['name' => 'Fresh Mango', 'unit' => 'kg']);
        $this->supplierA->products()->attach($this->product->id);
        $this->supplierB->products()->attach($this->product->id);
    }

    public function test_unified_sidebar_permissions_and_global_purchase_credit_notes_index(): void
    {
        $service = app(PurchaseCreditNoteService::class);

        // Active Vendor Credit for Supplier A: ₹300
        $pcnA = $service->create([
            'supplier_id' => $this->supplierA->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 10, 'unit_price' => 30]],
        ], $this->admin);

        // Active & then Voided Vendor Credit for Supplier B: ₹500 (must be excluded from active summary)
        $pcnB = $service->create([
            'supplier_id' => $this->supplierB->id,
            'credit_date' => '2026-11-25',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 10, 'unit_price' => 50]],
        ], $this->admin);
        $service->void($pcnB, 'Duplicate entry', $this->admin);

        // Unauthorized role is denied
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $status = $this->actingAs($purchaser)
            ->get(route('admin.cashbook.purchase-credit-notes.index'))
            ->getStatusCode();
        $this->assertTrue(in_array($status, [302, 403], true));

        // Admin sees unified sidebar & accurate summary (only Active pcnA = ₹300, count = 1)
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.purchase-credit-notes.index', ['date_preset' => 'all_time']));

        $response->assertOk()
            ->assertSee('Shop Credit Notes')
            ->assertSee('Purchase Credit Notes')
            ->assertSee('Warehouse Credit Notes')
            ->assertSee($pcnA->credit_note_number)
            ->assertSee($pcnB->credit_note_number)
            ->assertViewHas('summary', fn (array $s): bool => $s['vendor_credit_total'] === 300.0
                && $s['money_in_total'] === 0.0
                && $s['money_out_total'] === 0.0
                && $s['total_count'] === 1);

        // Filter by supplier_id
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.purchase-credit-notes.index', ['supplier_id' => $this->supplierA->id, 'date_preset' => 'all_time']))
            ->assertOk()
            ->assertSee($pcnA->credit_note_number)
            ->assertDontSee($pcnB->credit_note_number);
    }

    public function test_global_vendor_picker_and_creation_and_existing_vendor_flow_and_void(): void
    {
        // 1. Vendor picker & debounced vendor search
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.purchase-credit-notes.create'))
            ->assertOk()
            ->assertSee('Select Vendor');

        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-vendors', ['q' => 'Alpha']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.public_uuid', $this->supplierA->public_uuid);

        // 2. Selecting vendor renders canonical Purchase Credit Note form
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.purchase-credit-notes.create', ['supplier' => $this->supplierA->public_uuid]))
            ->assertOk()
            ->assertSee('Purchase Credit Note')
            ->assertSee($this->supplierA->name);

        // 3. Existing vendor-specific creation route works identically
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.vendors.credit-notes.create', ['supplier' => $this->supplierA->public_uuid]))
            ->assertOk()
            ->assertSee('Purchase Credit Note');

        // 4. Create via canonical store route, then view & void
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', ['supplier' => $this->supplierA->public_uuid]), [
                'credit_date' => '2026-11-26',
                'reason' => PurchaseCreditReason::PriceAdjustment->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::None->value,
                'items' => [
                    ['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 5, 'unit_price' => 40],
                ],
            ])
            ->assertRedirect();

        $pcn = PurchaseCreditNote::latest('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.vendors.credit-notes.show', [
                'supplier' => $this->supplierA->public_uuid,
                'creditNote' => $pcn->public_uuid,
            ]))
            ->assertOk()
            ->assertSee($pcn->credit_note_number);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.void', [
                'supplier' => $this->supplierA->public_uuid,
                'creditNote' => $pcn->public_uuid,
            ]), ['reason' => 'Test void from global flow'])
            ->assertRedirect();

        $this->assertTrue($pcn->fresh()->isVoided());
    }
}
