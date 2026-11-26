<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Models\Account;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaseCreditNotePhase4Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaserOne;

    private User $purchaserTwo;

    private Supplier $abdulla;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1400'], ['name' => 'Vendor Advances', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->purchaserOne = User::factory()->create(['name' => 'Kiran Purchaser']);
        $this->purchaserOne->assignRole('purchaser');

        $this->purchaserTwo = User::factory()->create(['name' => 'Manoj Purchaser']);
        $this->purchaserTwo->assignRole('purchaser');

        $this->warehouse = Warehouse::factory()->create();
        Product::factory()->create(['unit' => 'kg']);

        $this->abdulla = Supplier::factory()->create([
            'name' => 'Abdulla',
            'contact' => '7903749918',
            'mobile_number' => '7903749918',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_vendor_search_deduplicates_mobile_numbers_and_normalizes_country_codes(): void
    {
        // 1. Identical contact and mobile_number -> only primary_phone, secondary_phone is null
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-vendors', ['q' => 'Abdulla']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.primary_phone', '7903749918')
            ->assertJsonPath('items.0.secondary_phone', null);

        // 2. Country-code & spacing normalization (+91 98765-43210 == 9876543210)
        Supplier::factory()->create([
            'name' => 'Normalized Phone Vendor',
            'contact' => '+91 98765-43210',
            'mobile_number' => '9876543210',
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-vendors', ['q' => 'Normalized']))
            ->assertOk()
            ->assertJsonPath('items.0.primary_phone', '9876543210')
            ->assertJsonPath('items.0.secondary_phone', null);

        // 3. Empty mobile_number falls back to contact
        Supplier::factory()->create([
            'name' => 'Contact Fallback Vendor',
            'contact' => '9111222333',
            'mobile_number' => null,
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-vendors', ['q' => 'Fallback']))
            ->assertOk()
            ->assertJsonPath('items.0.primary_phone', '9111222333')
            ->assertJsonPath('items.0.secondary_phone', null);

        // 4. Two genuinely different numbers -> primary_phone prefers mobile_number, secondary_phone holds contact
        Supplier::factory()->create([
            'name' => 'Dual Phone Vendor',
            'contact' => '9000011111',
            'mobile_number' => '9999988888',
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-vendors', ['q' => 'Dual']))
            ->assertOk()
            ->assertJsonPath('items.0.primary_phone', '9999988888')
            ->assertJsonPath('items.0.secondary_phone', '9000011111');
    }

    public function test_vendor_search_prioritizes_exact_matches_and_excludes_unrelated_vendors(): void
    {
        Supplier::factory()->create(['name' => 'A 27', 'contact' => '9048075070', 'mobile_number' => '9048075070']);
        Supplier::factory()->create(['name' => 'Achu', 'contact' => '9048075070', 'mobile_number' => '9048075070']);
        Supplier::factory()->create(['name' => 'Abdulla Traders']);
        Supplier::factory()->create(['name' => 'K Abdulla']);

        $res = $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-vendors', ['q' => 'Abdulla']))
            ->assertOk()
            ->assertJsonCount(3, 'items');

        $names = collect($res->json('items'))->pluck('name')->all();
        $this->assertSame(['Abdulla', 'Abdulla Traders', 'K Abdulla'], $names);
        $this->assertNotContains('A 27', $names);
        $this->assertNotContains('Achu', $names);
    }

    public function test_purchaser_attribution_uses_linked_invoice_cart_only_and_supports_unassigned(): void
    {
        $cart1 = PurchaserCart::create([
            'cart_number' => 'CART-TEST-001',
            'user_id' => $this->purchaserOne->id,
            'warehouse_id' => $this->warehouse->id,
            'business_date' => '2026-11-26',
            'status' => 'completed',
        ]);

        $grn1 = GoodsReceived::factory()->create();
        $invoice1 = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->abdulla->id,
            'goods_received_id' => $grn1->id,
            'purchaser_cart_id' => $cart1->id,
            'amount' => 1000.00,
        ]);

        // Linked to Purchaser One (even though created_by is Admin!)
        $pcnAssigned = PurchaseCreditNote::create([
            'credit_note_number' => 'PCN-20261126-P001',
            'supplier_id' => $this->abdulla->id,
            'purchase_invoice_id' => $invoice1->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'total_amount' => 200.00,
            'applied_to_invoice_amount' => 200.00,
            'created_by' => $this->admin->id,
        ]);

        // Standalone note created by purchaserOne must still be Unassigned (never use created_by!)
        $pcnStandalone = PurchaseCreditNote::create([
            'credit_note_number' => 'PCN-20261126-UNAS',
            'supplier_id' => $this->abdulla->id,
            'purchase_invoice_id' => null,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'total_amount' => 150.00,
            'applied_to_invoice_amount' => 0.00,
            'created_by' => $this->purchaserOne->id,
        ]);

        // Search purchasers endpoint
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.search-purchasers', ['q' => 'Kiran']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $this->purchaserOne->id);

        // Filter by Purchaser One -> only pcnAssigned
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.index', [
                'purchaser_id' => $this->purchaserOne->id,
                'date_preset' => 'all_time',
                'async' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('summary.total_count', 1)
            ->assertSee($pcnAssigned->credit_note_number)
            ->assertDontSee($pcnStandalone->credit_note_number);

        // Filter by Unassigned -> only pcnStandalone
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.index', [
                'purchaser_id' => 'unassigned',
                'date_preset' => 'all_time',
                'async' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('summary.total_count', 1)
            ->assertSee($pcnStandalone->credit_note_number)
            ->assertDontSee($pcnAssigned->credit_note_number);
    }

    public function test_date_presets_rollover_and_combined_async_filtering(): void
    {
        // Freeze time at 2026-11-26 22:00 Asia/Kolkata -> after 9:30 PM rollover, operational "today" is 2026-11-27!
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 22, 0, 0, 'Asia/Kolkata'));

        $noteToday = PurchaseCreditNote::create([
            'credit_note_number' => 'PCN-OP-TODAY',
            'supplier_id' => $this->abdulla->id,
            'credit_date' => '2026-11-27',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'total_amount' => 100.00,
            'applied_to_invoice_amount' => 0.00,
            'created_by' => $this->admin->id,
        ]);

        $noteYesterday = PurchaseCreditNote::create([
            'credit_note_number' => 'PCN-OP-YESTERDAY',
            'supplier_id' => $this->abdulla->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::Shortage->value,
            'money_effect' => PurchaseCreditMoneyEffect::MoneyIn->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'total_amount' => 200.00,
            'applied_to_invoice_amount' => 0.00,
            'created_by' => $this->admin->id,
        ]);

        $noteLastMonth = PurchaseCreditNote::create([
            'credit_note_number' => 'PCN-OP-LASTMONTH',
            'supplier_id' => $this->abdulla->id,
            'credit_date' => '2026-10-15',
            'reason' => PurchaseCreditReason::Other->value,
            'money_effect' => PurchaseCreditMoneyEffect::MoneyOut->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'total_amount' => 300.00,
            'applied_to_invoice_amount' => 0.00,
            'created_by' => $this->admin->id,
        ]);

        // Default (today) includes both calendar today (2026-11-26) and operational rollover date (2026-11-27) after 9:30 PM IST
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.index', ['async' => 1]))
            ->assertOk()
            ->assertJsonPath('filters.date_preset', 'today')
            ->assertJsonPath('filters.start_date', '2026-11-26')
            ->assertJsonPath('filters.end_date', '2026-11-27')
            ->assertJsonPath('summary.total_count', 2)
            ->assertSee($noteToday->credit_note_number)
            ->assertSee($noteYesterday->credit_note_number);

        // Yesterday -> 2026-11-26
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.index', ['date_preset' => 'yesterday', 'async' => 1]))
            ->assertOk()
            ->assertJsonPath('filters.start_date', '2026-11-26')
            ->assertJsonPath('summary.total_count', 1)
            ->assertSee($noteYesterday->credit_note_number);

        // Last 7 days -> includes today & yesterday (2 notes)
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.index', ['date_preset' => 'last_7_days', 'async' => 1]))
            ->assertOk()
            ->assertJsonPath('summary.total_count', 2);

        // Last month -> 2026-10-01 to 2026-10-31 -> includes noteLastMonth
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.index', ['date_preset' => 'last_month', 'async' => 1]))
            ->assertOk()
            ->assertJsonPath('summary.total_count', 1)
            ->assertSee($noteLastMonth->credit_note_number);

        // Custom range validation rejects start_date > end_date
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.index', [
                'date_preset' => 'custom',
                'start_date' => '2026-11-27',
                'end_date' => '2026-11-25',
                'async' => 1,
            ]))
            ->assertUnprocessable();

        // Combined filtering: vendor + custom range + reason + money_effect + q
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.index', [
                'supplier_id' => $this->abdulla->id,
                'date_preset' => 'custom',
                'start_date' => '2026-11-26',
                'end_date' => '2026-11-27',
                'reason' => PurchaseCreditReason::Shortage->value,
                'money_effect' => PurchaseCreditMoneyEffect::MoneyIn->value,
                'q' => 'YESTERDAY',
                'async' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('summary.total_count', 1)
            ->assertJsonPath('summary.money_in_total', 200)
            ->assertSee($noteYesterday->credit_note_number);

        // Vendor financial summary remains all-time (all 3 active notes = 600.00) regardless of date preset
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.vendor-summary', ['supplier_id' => $this->abdulla->id]))
            ->assertOk()
            ->assertJsonPath('summary.total_active_credit_notes', 3)
            ->assertJsonPath('summary.total_active_credit_note_amount', 600);
    }
}
