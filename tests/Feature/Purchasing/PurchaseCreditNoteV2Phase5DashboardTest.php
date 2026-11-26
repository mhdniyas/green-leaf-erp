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
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\VendorSettlementService;
use App\Services\Purchasing\PurchaseCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaseCreditNoteV2Phase5DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Product $product;

    private CompanyAccount $cashAccount;

    private PurchaseInvoice $inv1;

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

        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create(['unit' => 'kg']);
        $this->supplier->products()->attach($this->product->id);

        $this->cashAccount = CompanyAccount::create([
            'name' => 'Main Cash',
            'account_type' => 'cash',
            'opening_balance' => 50000.00,
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);

        $grn = GoodsReceived::factory()->create();
        $this->inv1 = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'goods_received_id' => $grn->id,
            'invoice_number' => 'GLPB26-1126-DASH',
            'amount' => 10000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_dashboard_cards_listing_columns_utilization_filters_and_settle_button(): void
    {
        config(['greenleaf.purchase_credit_notes_v2_enabled' => true]);
        $pcnService = app(PurchaseCreditNoteService::class);
        $settlementService = app(VendorSettlementService::class);
        $today = Carbon::now('Asia/Kolkata')->toDateString();
        $yesterday = Carbon::now('Asia/Kolkata')->subDay()->toDateString();

        // 1. Open V2 note from yesterday (₹3,000)
        $openNote = $pcnService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_invoice_id' => $this->inv1->id,
            'credit_date' => $yesterday,
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 100, 'unit_price' => 30]],
        ], $this->admin);

        // 2. Partially utilized V2 note today (₹2,000 total, ₹500 used -> ₹1,500 available)
        $partialNote = $pcnService->create([
            'supplier_id' => $this->supplier->id,
            'credit_date' => $today,
            'reason' => PurchaseCreditReason::Shortage->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 100, 'unit_price' => 20]],
        ], $this->admin);

        $settlementService->createAutomatic($this->supplier, [
            'invoice_ids' => [$this->inv1->id],
            'actual_payment_amount' => 0,
            'use_vendor_advance' => false,
            'credit_notes' => [['purchase_credit_note_id' => $partialNote->id, 'amount' => 500]],
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => $today,
        ], $this->admin->id);

        // 3. Money IN note today (₹400) — must NOT count as available credit
        $moneyInNote = $pcnService->create([
            'supplier_id' => $this->supplier->id,
            'credit_date' => $today,
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::MoneyIn->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'company_account_id' => $this->cashAccount->id,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 20, 'unit_price' => 20]],
        ], $this->admin);

        // Default Today view: filtered totals reflect today (2,000 credit + 400 money_in),
        // while live available_credits = 3,000 + 1,500 = 4,500, utilized = 500, open_count = 2
        $resToday = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.purchase-credit-notes.index'))
            ->assertOk()
            ->assertSee('Total Purchase Credits')
            ->assertSee('Available Credits')
            ->assertSee('Utilized Credits')
            ->assertSee('Open Credit Notes')
            ->assertSee($partialNote->credit_note_number)
            ->assertSee('Partially Utilized')
            ->assertSee('Settle Vendor');

        $summary = $resToday->viewData('summary');
        $this->assertEquals(2000.0, $summary['vendor_credit_total']);
        $this->assertEquals(4500.0, $summary['available_credits']);
        $this->assertEquals(500.0, $summary['utilized_credits']);
        $this->assertSame(2, $summary['open_credit_notes_count']);
        $this->assertEquals(400.0, $summary['money_in_total']);

        // Async utilization_status=open filter across all_time shows openNote (not partialNote or moneyInNote)
        $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.purchase-credit-notes.index', [
                'date_preset' => 'all_time',
                'utilization_status' => 'open',
                'async' => 1,
            ]))
            ->assertOk()
            ->assertSee($openNote->credit_note_number)
            ->assertSee('GLPB26-1126-DASH')
            ->assertDontSee($partialNote->credit_note_number)
            ->assertDontSee($moneyInNote->credit_note_number);
    }
}
