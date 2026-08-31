<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\DailyPriceApproval;
use App\Models\DailyPricePublication;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\ShopOrders\DeliveryVerificationEligibility;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ShopInvoiceMoveBackToTransitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private User $warehouseUser;

    private User $shopUser;

    private ShopPriceGroup $priceGroup;

    private LedgerEntryType $purchaseBillType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->purchaseBillType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'purchase_bill'],
            ['name' => 'Purchase Bill', 'category' => 'expense', 'active' => true]
        );

        $this->priceGroup = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 10, 'is_active' => true],
        );

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create(['name' => 'Purchaser User']);
        $this->purchaser->assignRole('purchaser');

        $this->warehouseUser = User::factory()->create(['name' => 'Warehouse User']);
        $this->warehouseUser->assignRole('warehouse_receiver');

        $this->shopUser = User::factory()->create(['name' => 'Shop User']);
        $this->shopUser->assignRole('shop');
    }

    public function test_admin_can_move_eligible_invoice_back_to_in_transit(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createCheckedInInvoiceWithDiscrepancy('SINV-20260831-TRN1', $date);
        $order = $invoice->order;

        // Verify pre-conditions: order is pending_approval, shop checked in
        $this->assertSame('pending_approval', $order->delivery_status);
        $this->assertSame('pending', $order->delivery_review_status);
        $this->assertNotNull($order->shop_checked_at);
        $this->assertSame('awaiting_review', $invoice->delivery_status);
        $this->assertSame('delivery_review', $invoice->status);

        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.move-back-to-transit', $invoice), [
            'reason' => 'Driver missed one crate and is re-delivering to shop.',
        ]);

        $response->assertRedirect(route('purchasing.shop-invoices.show', $invoice));
        $response->assertSessionHas('success');

        $order->refresh();
        $invoice->refresh();

        // 1. Verify Order state
        $this->assertSame('in_transit', $order->delivery_status);
        $this->assertSame('not_started', $order->delivery_review_status);
        $this->assertNull($order->shop_checked_at);
        $this->assertNull($order->shop_checked_by);
        $this->assertFalse((bool) $order->is_delivered);
        $this->assertNull($order->delivered_at);
        $this->assertSame(0.0, (float) $order->total_shortage_value);
        $this->assertSame(0.0, (float) $order->total_excess_value);
        $this->assertStringContainsString('Moved back to in transit by admin: Driver missed one crate', (string) $order->delivery_notes);

        // 2. Verify Order Items state
        foreach ($order->items as $orderItem) {
            $this->assertNull($orderItem->delivered_qty);
            $this->assertNull($orderItem->shop_reported_received_qty);
            $this->assertNull($orderItem->shop_verified_at);
            $this->assertSame(0.0, (float) $orderItem->shortage_qty);
            $this->assertSame(0.0, (float) $orderItem->excess_qty);
            $this->assertSame('none', (string) $orderItem->delivery_discrepancy_type);
        }

        // 3. Verify Invoice state
        $this->assertSame('pending', $invoice->delivery_status);
        $this->assertSame('generated', $invoice->status);
        $this->assertNull($invoice->finalized_at);
        $this->assertNull($invoice->delivery_confirmed_at);
        $this->assertStringContainsString('Moved back to in transit by admin: Driver missed one crate', (string) $invoice->delivery_note);

        // 4. Verify Invoice Items state
        foreach ($invoice->items as $invoiceItem) {
            $this->assertSame(0.0, (float) $invoiceItem->shortage_qty);
            $this->assertSame(0.0, (float) $invoiceItem->excess_qty);
            $this->assertSame((float) $invoiceItem->line_subtotal, (float) $invoiceItem->final_line_total);
        }

        // 5. Verify Spatie Activity Log preservation
        $orderActivity = Activity::query()
            ->where('log_name', 'shop_order')
            ->where('subject_id', $order->id)
            ->where('event', 'moved_back_to_transit')
            ->first();

        $this->assertNotNull($orderActivity);
        $this->assertSame('Driver missed one crate and is re-delivering to shop.', data_get($orderActivity->properties, 'reason'));
        $this->assertSame('pending_approval', data_get($orderActivity->properties, 'before.order_delivery_status'));
        $this->assertSame('in_transit', data_get($orderActivity->properties, 'after.delivery_status'));

        $invoiceActivity = Activity::query()
            ->where('log_name', 'shop_invoice')
            ->where('subject_id', $invoice->id)
            ->where('event', 'moved_back_to_transit')
            ->first();

        $this->assertNotNull($invoiceActivity);
        $this->assertSame('delivery_review', data_get($invoiceActivity->properties, 'before.status'));
        $this->assertSame('generated', data_get($invoiceActivity->properties, 'after.status'));
    }

    public function test_confirmation_reason_is_mandatory(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createCheckedInInvoiceWithDiscrepancy('SINV-20260831-TRN2', $date);

        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.move-back-to-transit', $invoice), [
            'reason' => '   ',
        ]);

        $response->assertSessionHasErrors(['reason']);

        $invoice->refresh();
        $this->assertSame('delivery_review', $invoice->status);
    }

    public function test_unauthorized_users_are_rejected(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createCheckedInInvoiceWithDiscrepancy('SINV-20260831-TRN3', $date);

        $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.move-back-to-transit', $invoice), ['reason' => 'Purchaser attempting move'])
            ->assertForbidden();

        $this->actingAs($this->warehouseUser)
            ->post(route('purchasing.shop-invoices.move-back-to-transit', $invoice), ['reason' => 'Warehouse user attempting move'])
            ->assertForbidden();

        $this->actingAs($this->shopUser)
            ->post(route('purchasing.shop-invoices.move-back-to-transit', $invoice), ['reason' => 'Shop user attempting move'])
            ->assertForbidden();

        $invoice->refresh();
        $this->assertSame('delivery_review', $invoice->status);
    }

    public function test_finalized_invoices_are_blocked(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createCheckedInInvoiceWithDiscrepancy('SINV-20260831-TRN4', $date);
        $invoice->forceFill([
            'status' => 'payment_pending',
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ])->save();

        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.move-back-to-transit', $invoice), [
            'reason' => 'Trying to move finalized invoice directly to in transit.',
        ]);

        $response->assertSessionHasErrors(['invoice']);

        $invoice->refresh();
        $this->assertNotNull($invoice->finalized_at);
    }

    public function test_move_back_to_transit_is_idempotent(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createCheckedInInvoiceWithDiscrepancy('SINV-20260831-TRN5', $date);

        // First move back to in_transit
        $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.move-back-to-transit', $invoice), [
            'reason' => 'First transition to in transit.',
        ]);

        $invoice->refresh();
        $this->assertSame('pending', $invoice->delivery_status);
        $this->assertSame('in_transit', $invoice->order->delivery_status);

        // Second move back should succeed cleanly without error
        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.move-back-to-transit', $invoice), [
            'reason' => 'Second idempotent transition.',
        ]);

        $response->assertRedirect(route('purchasing.shop-invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame('pending', $invoice->delivery_status);
        $this->assertSame('in_transit', $invoice->order->delivery_status);
    }

    public function test_normal_delivery_and_review_can_be_completed_again_afterward(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createCheckedInInvoiceWithDiscrepancy('SINV-20260831-TRN6', $date);
        $order = $invoice->order;

        // 1. Admin moves back to in_transit
        $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.move-back-to-transit', $invoice), [
            'reason' => 'Reset for fresh delivery check.',
        ]);

        $order->refresh();
        $invoice->refresh();

        // 2. Verify DeliveryVerificationEligibility is allowed
        $eligibility = app(DeliveryVerificationEligibility::class)->forOrder($order);
        $this->assertTrue($eligibility['allowed']);

        // 3. Complete normal delivery review resolution through action
        $item = $order->items->first();
        app(ResolveDeliveryReviewAction::class)->submit(
            $order,
            [(int) $item->id => 10.0],
            (int) $this->shopUser->id,
            'Shop re-received full 10kg.'
        );

        $order->refresh();
        $this->assertSame('pending_approval', $order->delivery_status);
        $this->assertSame('pending', $order->delivery_review_status);
        $this->assertNotNull($order->shop_checked_at);

        // 4. Admin approves and completes delivery
        app(ResolveDeliveryReviewAction::class)->approve(
            $order,
            [(int) $item->id => 10.0],
            [],
            [],
            [],
            [],
            (int) $this->admin->id,
            'Approved fresh delivery.'
        );

        $order->refresh();
        $this->assertSame('delivered', $order->delivery_status);
        $this->assertSame('approved', $order->delivery_review_status);
        $this->assertTrue((bool) $order->is_delivered);
    }

    public function test_existing_warehouse_guards_continue_working_and_prevent_accidental_reopening(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createCheckedInInvoiceWithDiscrepancy('SINV-20260831-TRN7', $date);
        $order = $invoice->order;

        // 1. Warehouse user attempts to move to delivery via loadout controller - blocked by warehouse guard
        $response = $this->actingAs($this->warehouseUser)->post(
            route('warehouse.loadout.move-to-delivery', $order->order_number),
            ['partial_delivery' => 0]
        );
        $response->assertSessionHasErrors();

        // 2. Admin explicitly moves back to in_transit via dedicated admin action
        $this->actingAs($this->admin)->post(
            route('purchasing.shop-invoices.move-back-to-transit', $invoice),
            ['reason' => 'Administrative correction by manager.']
        );

        $order->refresh();
        $this->assertSame('in_transit', $order->delivery_status);
        $this->assertNull($order->shop_checked_at);
    }

    private function createCheckedInInvoiceWithDiscrepancy(
        string $invoiceNumber,
        string $businessDate,
        float $unitPrice = 20.0,
        float $orderedQty = 10.0,
        float $deliveredQty = 8.0
    ): ShopInvoice {
        $subtotal = round($unitPrice * $orderedQty, 2);
        $shortageQty = max(0.0, $orderedQty - $deliveredQty);
        $shortageAmount = round($shortageQty * $unitPrice, 2);
        $finalTotal = round($subtotal - $shortageAmount, 2);

        $shop = Shop::factory()->create([
            'code' => 'SHP-'.$invoiceNumber,
            'name' => 'Shop '.$invoiceNumber,
            'shop_price_group_id' => $this->priceGroup->id,
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'shop_checked_at' => now(),
            'shop_checked_by' => $this->shopUser->id,
            'is_delivered' => false,
            'delivered_at' => null,
            'total_shortage_value' => $shortageAmount,
            'total_excess_value' => 0.0,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => 'delivery_review',
            'delivery_status' => 'awaiting_review',
            'payment_status' => 'unpaid',
            'subtotal' => $subtotal,
            'shortage_total' => $shortageAmount,
            'excess_total' => 0.0,
            'final_total' => $finalTotal,
            'paid_amount' => 0.0,
            'balance_amount' => $finalTotal,
            'finalized_by' => null,
            'finalized_at' => null,
            'delivery_confirmed_by' => $this->shopUser->id,
            'delivery_confirmed_at' => now(),
        ]);

        $product = Product::factory()->create([
            'name' => 'Product '.$invoiceNumber,
            'unit' => 'kg',
            'base_price' => $unitPrice,
            'is_active' => true,
        ]);

        DailyPricePublication::query()->firstOrCreate(
            ['business_date' => $businessDate],
            ['is_published' => true, 'published_at' => now(), 'published_by' => $this->admin->id]
        );

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $businessDate,
            'purchase_price' => $unitPrice - 5,
            'price_unit' => 'kg',
            'price_a' => $unitPrice,
            'price_b' => $unitPrice,
            'price_c' => $unitPrice,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => $orderedQty,
            'approved_qty' => $orderedQty,
            'loaded_qty' => $orderedQty,
            'delivered_qty' => $deliveredQty,
            'shop_reported_received_qty' => $deliveredQty,
            'shortage_qty' => $shortageQty,
            'shortage_value' => $shortageAmount,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => $orderedQty,
            'requested_unit_conversion_to_base' => 1,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => $unitPrice,
            'locked_price_source' => 'manual',
            'unit_cost' => $unitPrice - 5,
            'unit_price' => $unitPrice,
            'line_total' => $finalTotal,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'delivery_discrepancy_type' => 'shortage',
            'delivery_discrepancy_note' => '2kg short received',
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => $orderedQty,
            'price_quantity' => $orderedQty,
            'delivered_qty' => $deliveredQty,
            'delivered_price_quantity' => $deliveredQty,
            'unit_price' => $unitPrice,
            'line_subtotal' => $subtotal,
            'shortage_qty' => $shortageQty,
            'shortage_amount' => $shortageAmount,
            'final_line_total' => $finalTotal,
        ]);

        return $invoice;
    }
}
