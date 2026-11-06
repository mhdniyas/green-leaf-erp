<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminShopReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Shop $shopB;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create();

        $this->shopA = Shop::factory()->create(['code' => 'SHP-A', 'name' => 'Shop Alpha']);
        $this->shopB = Shop::factory()->create(['code' => 'SHP-B', 'name' => 'Shop Beta']);

        $this->product = Product::factory()->create(['name' => 'Fresh Tomatoes', 'sku' => 'TOM-01']);
    }

    private function createInvoice(Shop $shop, string $invoiceNumber, float $finalTotal, string $businessDate, float $paid = 0.0): ShopInvoice
    {
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'order_number' => 'ORD-'.str_replace('INV-', '', $invoiceNumber),
            'business_date' => $businessDate,
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'delivered_qty' => 100,
            'unit' => 'kg',
            'rate' => 100,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'subtotal' => $finalTotal,
            'final_total' => $finalTotal,
            'paid_amount' => $paid,
            'balance_amount' => max(0.0, $finalTotal - $paid),
            'status' => 'finalized',
            'payment_status' => $paid >= $finalTotal ? 'paid' : ($paid > 0 ? 'partially_paid' : 'pending'),
            'delivery_status' => 'delivered',
            'finalized_at' => Carbon::parse($businessDate)->toDateTimeString(),
            'finalized_by' => $this->admin->id,
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_grade' => 'A',
            'unit_price' => 100,
            'delivered_qty' => 100,
            'final_line_total' => $finalTotal,
        ]);

        return $invoice;
    }

    private function createCreditNote(
        ShopInvoice $invoice,
        string $number,
        float $amount,
        ShopCreditNoteDirection $direction,
        ShopCreditNoteStatus $status,
        string $date
    ): ShopCreditNote {
        $creditNote = ShopCreditNote::factory()->create([
            'credit_note_number' => $number,
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $invoice->shop_id,
            'credit_note_date' => $date,
            'direction' => $direction,
            'status' => $status,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'subtotal' => $amount,
            'total_credit_amount' => $amount,
            'created_by' => $this->admin->id,
            'approved_by' => $status === ShopCreditNoteStatus::Approved ? $this->admin->id : null,
            'approved_at' => $status === ShopCreditNoteStatus::Approved ? Carbon::parse($date) : null,
        ]);

        $invoiceItem = $invoice->items()->first();

        ShopCreditNoteItem::factory()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_item_id' => $invoiceItem?->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_grade' => 'A',
            'unit' => 'kg',
            'adjusted_quantity' => 10,
            'adjusted_unit_price' => $amount / 10,
            'line_credit_amount' => $amount,
            'direction' => $direction,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
        ]);

        return $creditNote;
    }

    /**
     * 1. Both report pages render successfully for authorized users.
     */
    public function test_both_report_pages_render_successfully(): void
    {
        $responseInvoices = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.invoices'));
        $responseInvoices->assertOk();
        $responseInvoices->assertSee('Shop Invoices');
        $responseInvoices->assertSee('Original Total');
        $responseInvoices->assertSee('Net Payable');

        $responseCreditNotes = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes'));
        $responseCreditNotes->assertOk();
        $responseCreditNotes->assertSee('Shop Credit Notes');
        $responseCreditNotes->assertSee('Approved Credits');
        $responseCreditNotes->assertSee('Approved Debits');
    }

    /**
     * 2. Permission enforcement: Unauthorized users are rejected.
     */
    public function test_unauthorized_shop_reports_users_are_forbidden(): void
    {
        $this->actingAs($this->unauthorizedUser)->getJson(route('admin.cashbook.reports.shops.invoices'))->assertForbidden();
        $this->actingAs($this->unauthorizedUser)->getJson(route('admin.cashbook.reports.shops.credit-notes'))->assertForbidden();
        $this->actingAs($this->unauthorizedUser)->get(route('admin.cashbook.reports.shops.invoices'))->assertRedirect();
        $this->actingAs($this->unauthorizedUser)->get(route('admin.cashbook.reports.shops.credit-notes'))->assertRedirect();
    }

    /**
     * 3. Invoice report displays original values, approved credit/debit adjustments, and net payable correctly.
     */
    public function test_invoice_report_displays_original_values_and_adjustments(): void
    {
        $today = today()->toDateString();

        $invoice = $this->createInvoice($this->shopA, 'INV-1001', 10000.0, $today, 2000.0);

        // Approved credit note of 500
        $this->createCreditNote($invoice, 'SCN-101', 500.0, ShopCreditNoteDirection::Credit, ShopCreditNoteStatus::Approved, $today);

        // Approved debit note of 200
        $this->createCreditNote($invoice, 'SCN-102', 200.0, ShopCreditNoteDirection::Debit, ShopCreditNoteStatus::Approved, $today);

        // Draft credit note of 300 (must NOT affect payable)
        $this->createCreditNote($invoice, 'SCN-103', 300.0, ShopCreditNoteDirection::Credit, ShopCreditNoteStatus::Draft, $today);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.invoices', [
            'timeframe' => 'today',
        ]));

        $response->assertOk();

        // Original amount unmodified
        $this->assertSame(10000.0, (float) $invoice->fresh()->final_total);
        $response->assertSee('10,000.00');

        // Approved adjustments
        $response->assertSee('-₹500.00');
        $response->assertSee('+₹200.00');

        // Net payable = 10000 + 200 - 500 = 9700.00
        $this->assertSame(9700.0, $invoice->effectiveFinalTotal());
        $response->assertSee('9,700.00');

        // Summary cards
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['original_total'] === 10000.0
                && $summary['approved_credits'] === 500.0
                && $summary['approved_debits'] === 200.0
                && $summary['net_payable'] === 9700.0
                && $summary['total_paid'] === 2000.0
                && $summary['outstanding_balance'] === 7700.0;
        });
    }

    /**
     * 4. Invoice report filter by shop and search by invoice number works properly.
     */
    public function test_invoice_report_filters_by_shop_and_search(): void
    {
        $today = today()->toDateString();

        $invA = $this->createInvoice($this->shopA, 'INV-AAA-1', 5000.0, $today);
        $invB = $this->createInvoice($this->shopB, 'INV-BBB-2', 8000.0, $today);

        // Filter by Shop A
        $resShopA = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.invoices', [
            'timeframe' => 'today',
            'shop_id' => $this->shopA->id,
        ]));
        $resShopA->assertOk();
        $resShopA->assertSee('INV-AAA-1');
        $resShopA->assertDontSee('INV-BBB-2');

        // Filter by Search
        $resSearch = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.invoices', [
            'timeframe' => 'today',
            'search' => 'BBB-2',
        ]));
        $resSearch->assertOk();
        $resSearch->assertDontSee('INV-AAA-1');
        $resSearch->assertSee('INV-BBB-2');
    }

    /**
     * 5. Credit Notes report displays summaries and correctly distinguishes Credit and Debit.
     */
    public function test_credit_notes_report_displays_summaries_and_distinguishes_types(): void
    {
        $today = today()->toDateString();
        $invoice = $this->createInvoice($this->shopA, 'INV-2001', 12000.0, $today);

        $cnApproved = $this->createCreditNote($invoice, 'SCN-201', 800.0, ShopCreditNoteDirection::Credit, ShopCreditNoteStatus::Approved, $today);
        $dnApproved = $this->createCreditNote($invoice, 'SCN-202', 300.0, ShopCreditNoteDirection::Debit, ShopCreditNoteStatus::Approved, $today);
        $cnDraft = $this->createCreditNote($invoice, 'SCN-203', 150.0, ShopCreditNoteDirection::Credit, ShopCreditNoteStatus::Draft, $today);
        $cnVoided = $this->createCreditNote($invoice, 'SCN-204', 100.0, ShopCreditNoteDirection::Credit, ShopCreditNoteStatus::Voided, $today);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', [
            'timeframe' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('SCN-201');
        $response->assertSee('SCN-202');
        $response->assertSee('SCN-203');
        $response->assertSee('SCN-204');
        $response->assertSee('INV-2001');

        // Distinguish Credit and Debit
        $response->assertSee('Credit');
        $response->assertSee('Debit');

        // Summary calculations:
        // Total notes: 4
        // Approved credits: 800.00
        // Approved debits: 300.00
        // Net adjustment: 300 - 800 = -500.00
        // Draft notes count: 1
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_count'] === 4
                && $summary['approved_credits'] === 800.0
                && $summary['approved_debits'] === 300.0
                && $summary['net_adjustment'] === -500.0
                && $summary['draft_count'] === 1;
        });
    }

    /**
     * 6. Credit Notes report filters by status, direction, and invoice reference.
     */
    public function test_credit_notes_report_filters(): void
    {
        $today = today()->toDateString();
        $invoice1 = $this->createInvoice($this->shopA, 'INV-3001', 10000.0, $today);
        $invoice2 = $this->createInvoice($this->shopB, 'INV-3002', 15000.0, $today);

        $this->createCreditNote($invoice1, 'SCN-301', 500.0, ShopCreditNoteDirection::Credit, ShopCreditNoteStatus::Approved, $today);
        $this->createCreditNote($invoice1, 'SCN-302', 200.0, ShopCreditNoteDirection::Debit, ShopCreditNoteStatus::Draft, $today);
        $this->createCreditNote($invoice2, 'SCN-303', 700.0, ShopCreditNoteDirection::Credit, ShopCreditNoteStatus::Approved, $today);

        // Filter by direction = debit
        $resDebit = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', [
            'timeframe' => 'today',
            'direction' => 'debit',
        ]));
        $resDebit->assertOk();
        $resDebit->assertSee('SCN-302');
        $resDebit->assertDontSee('SCN-301');
        $resDebit->assertDontSee('SCN-303');

        // Filter by status = draft
        $resDraft = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', [
            'timeframe' => 'today',
            'status' => 'draft',
        ]));
        $resDraft->assertOk();
        $resDraft->assertSee('SCN-302');
        $resDraft->assertDontSee('SCN-301');

        // Filter by invoice_search = 3002
        $resInvoice = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', [
            'timeframe' => 'today',
            'invoice_search' => '3002',
        ]));
        $resInvoice->assertOk();
        $resInvoice->assertSee('SCN-303');
        $resInvoice->assertDontSee('SCN-301');
    }

    /**
     * 7. Existing Cashbook reports remain unaffected.
     */
    public function test_existing_cashbook_reports_remain_unaffected(): void
    {
        $responseGlBills = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills'));
        $responseGlBills->assertOk();

        $responseReports = $this->actingAs($this->admin)->get(route('admin.cashbook.reports'));
        $responseReports->assertOk();
    }
}
