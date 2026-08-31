<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ShopInvoiceFinalizationReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

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

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchase');

        $this->shopUser = User::factory()->create();
        $this->shopUser->assignRole('shop');
    }

    public function test_admin_can_safely_revert_finalized_invoice_with_reason(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createFinalizedInvoice('SINV-20260831-TEST1', $date, finalTotal: 250.0);

        // Ensure cashbook transaction exists for the finalized invoice
        $cashbookTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $invoice->shop_id,
            'entry_type_id' => $this->purchaseBillType->id,
            'reference_type' => ShopInvoice::class,
            'reference_id' => $invoice->id,
            'business_date' => $date,
            'amount' => 250.0,
            'direction' => 'expense',
            'funding_source' => 'company',
            'status' => TransactionStatus::Posted->value,
        ]);

        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.revert-finalization', $invoice), [
            'reason' => 'Rate error discovered by manager during audit.',
        ]);

        $response->assertRedirect(route('purchasing.shop-invoices.show', $invoice));
        $response->assertSessionHas('success');

        $invoice->refresh();
        $this->assertNull($invoice->finalized_at);
        $this->assertNull($invoice->finalized_by);
        $this->assertSame('delivery_review', $invoice->status);
        $this->assertSame('awaiting_review', $invoice->delivery_status);

        $order = $invoice->order->fresh();
        $this->assertSame('pending_approval', $order->delivery_status);
        $this->assertSame('pending', $order->delivery_review_status);
        $this->assertFalse($order->is_delivered);
        $this->assertNull($order->delivered_at);

        // Cashbook transaction must be voided
        $cashbookTx->refresh();
        $this->assertSame(TransactionStatus::Void->value, $cashbookTx->status);
        $this->assertStringContainsString('Rate error discovered', (string) $cashbookTx->void_reason);

        // Activity log recorded
        $activity = Activity::query()
            ->where('subject_type', ShopInvoice::class)
            ->where('subject_id', $invoice->id)
            ->where('event', 'finalization_reverted')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame((int) $this->admin->id, (int) $activity->causer_id);
        $this->assertSame('Rate error discovered by manager during audit.', data_get($activity->properties, 'reason'));
    }

    public function test_revert_requires_valid_reason(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createFinalizedInvoice('SINV-20260831-TEST2', $date);

        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.revert-finalization', $invoice), [
            'reason' => 'ab', // Too short
        ]);

        $response->assertSessionHasErrors(['reason']);

        $invoice->refresh();
        $this->assertNotNull($invoice->finalized_at);
    }

    public function test_unauthorized_users_cannot_revert_finalization(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createFinalizedInvoice('SINV-20260831-TEST3', $date);

        $this->actingAs($this->purchaser)
            ->post(route('purchasing.shop-invoices.revert-finalization', $invoice), ['reason' => 'Trying to revert as purchaser.'])
            ->assertForbidden();

        $this->actingAs($this->shopUser)
            ->post(route('purchasing.shop-invoices.revert-finalization', $invoice), ['reason' => 'Trying to revert as shop user.'])
            ->assertForbidden();

        $invoice->refresh();
        $this->assertNotNull($invoice->finalized_at);
    }

    public function test_revert_refuses_when_invoice_has_payments_or_approved_requests(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createFinalizedInvoice('SINV-20260831-TEST4', $date, paidAmount: 100.0);

        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.revert-finalization', $invoice), [
            'reason' => 'Attempting to revert invoice with recorded payments.',
        ]);

        $response->assertSessionHasErrors(['invoice']);

        $invoice->refresh();
        $this->assertNotNull($invoice->finalized_at);

        // Also test approved payment requests block reversal
        $invoice2 = $this->createFinalizedInvoice('SINV-20260831-TEST5', $date, paidAmount: 0.0);
        ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $invoice2->shop_id,
            'shop_invoice_id' => $invoice2->id,
            'requested_amount' => 100.0,
            'approved_amount' => 100.0,
            'payment_method' => 'cash',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $response2 = $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.revert-finalization', $invoice2), [
            'reason' => 'Attempting to revert invoice with approved payment request.',
        ]);

        $response2->assertSessionHasErrors(['invoice']);
        $invoice2->refresh();
        $this->assertNotNull($invoice2->finalized_at);
    }

    public function test_revert_is_idempotent(): void
    {
        $date = '2026-08-31';
        $invoice = $this->createFinalizedInvoice('SINV-20260831-TEST6', $date);

        // First revert
        $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.revert-finalization', $invoice), [
            'reason' => 'First revert.',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertNull($invoice->finalized_at);

        // Second revert (idempotent)
        $this->actingAs($this->admin)->post(route('purchasing.shop-invoices.revert-finalization', $invoice), [
            'reason' => 'Second revert.',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertNull($invoice->finalized_at);
        $this->assertSame('delivery_review', $invoice->status);
    }

    private function createFinalizedInvoice(
        string $invoiceNumber,
        string $businessDate,
        float $finalTotal = 200.0,
        float $paidAmount = 0.0
    ): ShopInvoice {
        $shop = Shop::factory()->create([
            'code' => 'SHP-'.$invoiceNumber,
            'name' => 'Shop '.$invoiceNumber,
            'shop_price_group_id' => $this->priceGroup->id,
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
            'delivery_status' => 'delivered',
            'delivery_review_status' => 'approved',
            'shop_checked_at' => now(),
            'shop_checked_by' => $this->purchaser->id,
            'is_delivered' => true,
            'delivered_at' => now(),
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => $paidAmount >= $finalTotal ? 'paid' : 'payment_pending',
            'delivery_status' => 'approved_after_discrepancy',
            'payment_status' => $paidAmount >= $finalTotal ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'unpaid'),
            'subtotal' => $finalTotal,
            'final_total' => $finalTotal,
            'paid_amount' => $paidAmount,
            'balance_amount' => max(0.0, $finalTotal - $paidAmount),
            'finalized_by' => $this->purchaser->id,
            'finalized_at' => now(),
        ]);

        $product = Product::factory()->create([
            'name' => 'Product '.$invoiceNumber,
            'unit' => 'kg',
            'base_price' => 20,
            'is_active' => true,
        ]);

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
