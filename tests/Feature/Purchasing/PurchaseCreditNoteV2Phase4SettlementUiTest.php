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
use App\Models\VendorAdvance;
use App\Models\VendorSettlement;
use App\Services\Purchasing\PurchaseCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseCreditNoteV2Phase4SettlementUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Product $product;

    private CompanyAccount $bankAccount;

    private PurchaseInvoice $inv1;

    private PurchaseInvoice $inv2;

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

        $this->bankAccount = CompanyAccount::create([
            'name' => 'HDFC Bank',
            'account_type' => 'bank',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);

        $grn = GoodsReceived::factory()->create();
        $this->inv1 = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'goods_received_id' => $grn->id,
            'invoice_number' => 'GLPB26-1126-INV1',
            'amount' => 10000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'payment_method' => 'Credit',
            'payment_status' => 'unpaid',
            'payment_paid_by' => 'vendor_credit',
        ]);
        $this->inv2 = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'goods_received_id' => $grn->id,
            'invoice_number' => 'GLPB26-1126-INV2',
            'amount' => 5000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'payment_method' => 'Credit',
            'payment_status' => 'unpaid',
            'payment_paid_by' => 'vendor_credit',
        ]);
    }

    public function test_vendor_settlement_ui_displays_separate_credit_notes_and_cash_advances_and_supports_credit_only_settlement(): void
    {
        config(['greenleaf.purchase_credit_notes_v2_enabled' => true]);

        $pcn = app(PurchaseCreditNoteService::class)->create([
            'supplier_id' => $this->supplier->id,
            'purchase_invoice_id' => $this->inv1->id,
            'vendor_credit_note_ref' => 'SUP-401',
            'credit_date' => now()->toDateString(),
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 100, 'unit_price' => 30]],
        ], $this->admin);

        VendorAdvance::query()->create([
            'supplier_id' => $this->supplier->id,
            'amount_original' => 500.00,
            'amount_remaining' => 500.00,
            'business_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        // 1. UI shows separate Purchase Credit and Cash Advance panels
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier))
            ->assertOk()
            ->assertSee('Available Purchase Credit: ₹3,000.00')
            ->assertSee('Available Cash Advance: ₹500.00')
            ->assertSee('Available Purchase Credit Notes')
            ->assertSee($pcn->credit_note_number);

        // 2. Credit-only partial settlement via HTTP POST (₹2,000 against INV1)
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
                'submission_token' => 'phase4-http-settle-001',
                'actual_payment_amount' => 0,
                'payment_date' => now()->toDateString(),
                'invoice_ids' => [$this->inv1->id],
                'credit_notes' => [
                    ['purchase_credit_note_id' => $pcn->id, 'amount' => 2000],
                ],
                'difference_treatment' => 'outstanding',
            ])
            ->assertRedirect();

        $pcn->refresh();
        $this->inv1->refresh();
        $this->assertEquals(2000.0, (float) $pcn->utilized_amount);
        $this->assertEquals(1000.0, $pcn->available_credit);
        $this->assertEquals(8000.0, $this->inv1->remainingBalance());

        // 3. Settlement Details page displays Purchase Credit Note Sources
        $settlement = VendorSettlement::latest('id')->firstOrFail();
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.settlements.show', $settlement))
            ->assertOk()
            ->assertSee('Purchase Credit Note Sources')
            ->assertSee($pcn->credit_note_number)
            ->assertSee('GLPB26-1126-INV1');

        // 4. Reverse settlement via HTTP POST and verify restoration
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.vendor-credit.settlements.delete', $settlement), [
                'reason' => 'Wrong Amount',
            ])
            ->assertRedirect();

        $this->assertEquals(0.0, (float) $pcn->fresh()->utilized_amount);
        $this->assertEquals(3000.0, $pcn->fresh()->available_credit);
        $this->assertEquals(10000.0, $this->inv1->fresh()->remainingBalance());
    }

    public function test_v1_settlement_ui_remains_unchanged_when_v2_flag_is_disabled(): void
    {
        config(['greenleaf.purchase_credit_notes_v2_enabled' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier))
            ->assertOk()
            ->assertSee('Available Advance:')
            ->assertDontSee('Available Purchase Credit Notes');
    }
}
