<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopInvoiceBulkFinalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $admin;

    private User $shopUser;

    private ShopPriceGroup $priceGroup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        LedgerEntryType::query()->firstOrCreate(
            ['code' => 'purchase_bill'],
            ['name' => 'Purchase Bill', 'category' => 'expense', 'active' => true]
        );

        $this->priceGroup = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 10, 'is_active' => true],
        );

        $purchaseRole = Role::findOrCreate('purchase');
        $purchaseRole->givePermissionTo([
            Permission::findOrCreate('purchasing.order.view'),
            Permission::findOrCreate('purchasing.order.approve'),
        ]);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchase');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shopUser = User::factory()->create();
        $this->shopUser->assignRole('shop');
    }

    public function test_authorized_purchaser_can_finalize_all_eligible_invoices_for_selected_date(): void
    {
        $date = '2026-08-31';
        $invoice1 = $this->createInvoice('SINV-20260831-SHOP1', $date, deliveryStatus: 'pending_approval', reviewStatus: 'pending', shopSubmitted: true);
        $invoice2 = $this->createInvoice('SINV-20260831-SHOP2', $date, deliveryStatus: 'in_transit', reviewStatus: 'not_started', shopSubmitted: false);

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.finalize-all'), [
                'date' => $date,
                'review_note' => 'Day finalized by purchaser.',
            ]);

        $response->assertRedirect(route('purchasing.shop-invoices.index', ['date' => $date]));
        $response->assertSessionHas('bulk_finalize_result');
        $response->assertSessionHas('success');

        $result = session('bulk_finalize_result');
        $this->assertSame(2, $result['finalized']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(0, $result['skipped']);

        $this->assertTrue($invoice1->fresh()->isFinalized());
        $this->assertTrue($invoice2->fresh()->isFinalized());
        $this->assertSame('approved', $invoice1->order->fresh()->delivery_review_status);
        $this->assertSame('approved', $invoice2->order->fresh()->delivery_review_status);
    }

    public function test_invoices_on_other_dates_remain_untouched(): void
    {
        $targetDate = '2026-08-31';
        $otherDate = '2026-09-01';

        $targetInvoice = $this->createInvoice('SINV-20260831-TARGET', $targetDate, deliveryStatus: 'pending_approval', reviewStatus: 'pending', shopSubmitted: true);
        $otherInvoice = $this->createInvoice('SINV-20260901-OTHER', $otherDate, deliveryStatus: 'pending_approval', reviewStatus: 'pending', shopSubmitted: true);

        $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.finalize-all'), ['date' => $targetDate])
            ->assertRedirect();

        $this->assertTrue($targetInvoice->fresh()->isFinalized());
        $this->assertFalse($otherInvoice->fresh()->isFinalized());
        $this->assertNull($otherInvoice->fresh()->finalized_at);
        $this->assertSame('pending', $otherInvoice->order->fresh()->delivery_review_status);
    }

    public function test_already_finalized_invoices_are_skipped_without_duplicate_side_effects(): void
    {
        $date = '2026-08-31';
        $invoice1 = $this->createInvoice('SINV-20260831-FINAL', $date, finalized: true, shopSubmitted: true);
        $invoice2 = $this->createInvoice('SINV-20260831-READY', $date, deliveryStatus: 'pending_approval', reviewStatus: 'pending', shopSubmitted: true);

        $initialLedgerCount = ShopLedgerTransaction::query()->count();

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.finalize-all'), ['date' => $date]);

        $response->assertRedirect();
        $result = session('bulk_finalize_result');

        $this->assertSame(1, $result['finalized']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertContains($invoice1->invoice_number, $result['skipped_invoices']);
        $this->assertContains($invoice2->invoice_number, $result['finalized_invoices']);

        // Idempotency: only 1 new ledger transaction created for invoice2
        $this->assertSame($initialLedgerCount + 1, ShopLedgerTransaction::query()->count());
    }

    public function test_ineligible_invoices_remain_unchanged_and_failure_reason_is_reported(): void
    {
        $date = '2026-08-31';
        // Invoice without approved daily price
        $invoiceWithoutPrice = $this->createInvoice('SINV-20260831-NOPRICE', $date, approveDailyPrice: false);

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.finalize-all'), ['date' => $date]);

        $response->assertRedirect();
        $result = session('bulk_finalize_result');

        $this->assertSame(0, $result['finalized']);
        $this->assertSame(1, $result['failed']);
        $this->assertCount(1, $result['failures']);
        $this->assertSame($invoiceWithoutPrice->invoice_number, $result['failures'][0]['invoice_number']);
        $this->assertFalse($invoiceWithoutPrice->fresh()->isFinalized());
    }

    public function test_mixed_result_correctly_reports_finalized_skipped_and_failed_counts(): void
    {
        $date = '2026-08-31';
        $finalized = $this->createInvoice('SINV-20260831-ALREADY', $date, finalized: true);
        $ready = $this->createInvoice('SINV-20260831-SUCCESS', $date, deliveryStatus: 'pending_approval', reviewStatus: 'pending', shopSubmitted: true);
        $failed = $this->createInvoice('SINV-20260831-INVALID', $date, approveDailyPrice: false);

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.finalize-all'), ['date' => $date]);

        $response->assertRedirect();
        $result = session('bulk_finalize_result');

        $this->assertSame(1, $result['finalized']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(3, $result['total']);

        $this->assertTrue($ready->fresh()->isFinalized());
        $this->assertFalse($failed->fresh()->isFinalized());
    }

    public function test_repeated_submission_is_idempotent(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createInvoice('SINV-20260831-IDEM', $date, deliveryStatus: 'pending_approval', reviewStatus: 'pending', shopSubmitted: true);

        // First run
        $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.finalize-all'), ['date' => $date]);
        $this->assertTrue($invoice->fresh()->isFinalized());
        $ledgerCountAfterFirst = ShopLedgerTransaction::query()->count();

        // Second run (repeated / retry)
        $response = $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.finalize-all'), ['date' => $date]);
        $response->assertRedirect();
        $result = session('bulk_finalize_result');

        $this->assertSame(0, $result['finalized']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame($ledgerCountAfterFirst, ShopLedgerTransaction::query()->count());
    }

    public function test_unauthorized_user_cannot_invoke_bulk_endpoint(): void
    {
        $date = '2026-08-31';
        $this->createInvoice('SINV-20260831-UNAUTH', $date);

        // Shop user without purchasing role
        $this->actingAs($this->shopUser)
            ->post(route('purchasing.shop-invoices.finalize-all'), ['date' => $date])
            ->assertForbidden();

        // Guest user
        auth()->logout();
        $this->post(route('purchasing.shop-invoices.finalize-all'), ['date' => $date])
            ->assertRedirect(); // redirected to login by auth middleware
    }

    public function test_empty_date_produces_clear_no_bills_result(): void
    {
        $emptyDate = '2026-08-01';

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.finalize-all'), ['date' => $emptyDate]);

        $response->assertRedirect(route('purchasing.shop-invoices.index', ['date' => $emptyDate]));
        $result = session('bulk_finalize_result');

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['finalized']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['failed']);
    }

    public function test_top_control_renders_correct_state_in_ui(): void
    {
        $date = '2026-08-31';
        $this->createInvoice('SINV-20260831-UI-1', $date, deliveryStatus: 'pending_approval', reviewStatus: 'pending', shopSubmitted: true);
        $this->createInvoice('SINV-20260831-UI-2', $date, finalized: true);

        // 1 eligible, 1 finalized
        $this->actingAs($this->purchaser)
            ->get(route('purchasing.shop-invoices.index', ['date' => $date]))
            ->assertOk()
            ->assertSee('Finalize All for 31 Aug 2026')
            ->assertSee('1 Ready')
            ->assertSee('Finalize All (1)')
            ->assertSee('data-open-bulk-finalize', false)
            ->assertSee('data-bulk-finalize-modal', false);

        // After finalizing the remaining 1
        $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.finalize-all'), ['date' => $date]);

        $this->actingAs($this->purchaser)
            ->get(route('purchasing.shop-invoices.index', ['date' => $date]))
            ->assertOk()
            ->assertSee('All Bills Finalized')
            ->assertSee('Completed');
    }

    private function createInvoice(
        string $invoiceNumber,
        string $businessDate,
        bool $finalized = false,
        bool $shopSubmitted = false,
        string $deliveryStatus = 'in_transit',
        string $reviewStatus = 'not_started',
        bool $approveDailyPrice = true,
    ): ShopInvoice {
        $shop = Shop::factory()->create([
            'code' => 'SHP-'.$invoiceNumber,
            'name' => 'Test Shop '.$invoiceNumber,
            'shop_price_group_id' => $this->priceGroup->id,
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
            'delivery_status' => $finalized ? 'delivered' : $deliveryStatus,
            'delivery_review_status' => $finalized ? 'approved' : $reviewStatus,
            'shop_checked_at' => $shopSubmitted ? now() : null,
            'shop_checked_by' => $shopSubmitted ? $this->purchaser->id : null,
            'is_delivered' => $finalized,
            'delivered_at' => $finalized ? now() : null,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => $finalized ? 'payment_pending' : 'generated',
            'delivery_status' => $finalized ? 'received_full' : 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 200,
            'final_total' => 200,
            'balance_amount' => 200,
            'finalized_by' => $finalized ? $this->purchaser->id : null,
            'finalized_at' => $finalized ? now() : null,
        ]);

        $product = Product::factory()->create([
            'name' => 'Product '.$invoiceNumber,
            'unit' => 'kg',
            'base_price' => 20,
            'is_active' => true,
        ]);

        if ($approveDailyPrice) {
            DailyPriceApproval::query()->create([
                'product_id' => $product->id,
                'business_date' => $businessDate,
                'purchase_price' => 15,
                'price_unit' => 'kg',
                'price_a' => 20,
                'price_b' => 20,
                'price_c' => 20,
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'loaded_qty' => 10,
            'delivered_qty' => 10,
            'shop_reported_received_qty' => 10,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 10,
            'requested_unit_conversion_to_base' => 1,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 20,
            'locked_price_source' => 'manual',
            'unit_cost' => 15,
            'unit_price' => 20,
            'line_total' => 200,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 10,
            'price_quantity' => 10,
            'delivered_qty' => 10,
            'delivered_price_quantity' => 10,
            'unit_price' => 20,
            'line_subtotal' => 200,
            'final_line_total' => 200,
        ]);

        return $invoice;
    }
}
