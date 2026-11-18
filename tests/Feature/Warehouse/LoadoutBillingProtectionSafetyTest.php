<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ShopInvoices\ShopInvoiceService;
use App\Services\Warehouse\WarehouseDispatchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoadoutBillingProtectionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Shop $shop;

    private ShopPriceGroup $priceGroup;

    private Product $tomato;

    private ShopInvoiceService $invoiceService;

    private WarehouseDispatchService $dispatchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00'));

        $this->user = User::factory()->create([
            'email' => 'warehouse_safety@example.com',
        ]);
        $this->user->assignRole('admin');
        $this->user->givePermissionTo([
            'warehouse.loadout.all',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Vegetable Warehouse',
            'code' => 'WH1',
            'is_active' => true,
        ]);
        $this->user->warehouses()->attach($this->warehouse->id);

        $this->priceGroup = ShopPriceGroup::factory()->create([
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Safety Mart',
            'shop_price_group_id' => $this->priceGroup->id,
            'status' => 'active',
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Local',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'sku' => 'VEG-TOM-01',
            'is_active' => true,
        ]);

        $this->invoiceService = app(ShopInvoiceService::class);
        $this->dispatchService = app(WarehouseDispatchService::class);

        $this->actingAs($this->user);
        $this->withHeader('X-Client-Supports-Multi-Grade', 'true');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Requirement 1: Editable invoice can still be synchronized and repriced.
     */
    public function test_editable_invoice_can_still_be_synchronized_and_repriced(): void
    {
        $businessDate = '2026-11-18';
        $this->approvePrice($this->tomato, $businessDate, 'A', 40.0);
        $this->createStock($this->tomato, 100.0, 'A', $businessDate);

        $order = $this->createOrder($businessDate, 'pending_delivery');
        $item = $this->createOrderItem($order, $this->tomato, 'A', 10.0, 10.0, 10.0, 'loaded');

        // Initial invoice creation & repricing
        $invoice = $this->invoiceService->synchronizeOrderInvoice($order, (int) $this->user->id);
        $this->assertFalse($invoice->isFinalLocked());
        $this->assertFalse($invoice->isBillingProtected());

        $this->invoiceService->repriceInvoice($invoice, (int) $this->user->id, 'Initial pricing');
        $invoice = $invoice->fresh('items');
        $this->assertNotNull($invoice->items->first());
        $this->assertEquals(40.0, (float) $invoice->items->first()->unit_price);
        $this->assertEquals(400.0, (float) $invoice->final_total);

        // Price changes to ₹50/kg
        $this->approvePrice($this->tomato, $businessDate, 'A', 50.0);

        // Reprice succeeds because invoice is editable
        $this->invoiceService->repriceInvoice($invoice, (int) $this->user->id, 'Reprice after price update');
        $freshInvoice = $invoice->fresh('items');
        $this->assertEquals(50.0, (float) $freshInvoice->items->first()->unit_price);
        $this->assertEquals(500.0, (float) $freshInvoice->final_total);
    }

    /**
     * Requirement 2 & 3: Billing-protected invoice does not get repriced,
     * and original protected invoice lines and totals remain unchanged.
     */
    public function test_billing_protected_invoice_is_rejected_by_reprice_and_synchronize_leaves_data_unchanged(): void
    {
        $businessDate = '2026-11-18';
        $this->approvePrice($this->tomato, $businessDate, 'A', 40.0);
        $this->createStock($this->tomato, 100.0, 'A', $businessDate);

        $order = $this->createOrder($businessDate, 'pending_delivery');
        $item = $this->createOrderItem($order, $this->tomato, 'A', 10.0, 10.0, 10.0, 'loaded');

        $invoice = $this->invoiceService->synchronizeOrderInvoice($order, (int) $this->user->id);
        $invoice->update([
            'status' => 'delivery_review',
        ]);

        $this->assertTrue($invoice->isBillingProtected());

        // Price increases to ₹60/kg
        $this->approvePrice($this->tomato, $businessDate, 'A', 60.0);

        // 1. Direct reprice must be rejected with ValidationException
        $this->expectException(ValidationException::class);
        $this->invoiceService->repriceInvoice($invoice, (int) $this->user->id, 'Attempt reprice on protected invoice');
    }

    /**
     * Requirement 3 continued: synchronizeOrderInvoice preserves original lines and totals.
     */
    public function test_synchronize_order_invoice_preserves_protected_invoice_snapshot(): void
    {
        $businessDate = '2026-11-18';
        $this->approvePrice($this->tomato, $businessDate, 'A', 40.0);
        $this->createStock($this->tomato, 100.0, 'A', $businessDate);

        $order = $this->createOrder($businessDate, 'pending_delivery');
        $item = $this->createOrderItem($order, $this->tomato, 'A', 10.0, 10.0, 10.0, 'loaded');

        $invoice = $this->invoiceService->synchronizeOrderInvoice($order, (int) $this->user->id);
        $this->invoiceService->repriceInvoice($invoice, (int) $this->user->id);

        // Move invoice to protected
        $invoice->update([
            'delivery_status' => 'received_full',
        ]);

        $this->assertTrue($invoice->isBillingProtected());

        // Alter order item quantities
        $item->update([
            'loaded_qty' => 50.0,
            'approved_qty' => 50.0,
        ]);

        // synchronizeOrderInvoice returns existing invoice without changing items
        $syncedInvoice = $this->invoiceService->synchronizeOrderInvoice($order->fresh(['items', 'invoice']), (int) $this->user->id);

        $this->assertEquals(400.0, (float) $syncedInvoice->final_total);
        $this->assertEquals(10.0, (float) $syncedInvoice->items->first()->approved_qty);
        $this->assertEquals(40.0, (float) $syncedInvoice->items->first()->unit_price);
    }

    /**
     * Requirement 4: Loadout save does not fail because of the repricing guard.
     */
    public function test_loadout_save_api_succeeds_without_repricing_error_when_invoice_is_protected(): void
    {
        $businessDate = '2026-11-18';
        $this->approvePrice($this->tomato, $businessDate, 'A', 40.0);
        $this->createStock($this->tomato, 100.0, 'A', $businessDate);

        $order = $this->createOrder($businessDate, 'ready_for_dispatch');
        $item = $this->createOrderItem($order, $this->tomato, 'A', 10.0, 10.0, 10.0, 'loaded');

        $invoice = $this->invoiceService->synchronizeOrderInvoice($order, (int) $this->user->id);
        $this->invoiceService->repriceInvoice($invoice, (int) $this->user->id);

        // Mark invoice billing protected (e.g. delivery review)
        $invoice->update([
            'delivery_status' => 'awaiting_review',
            'status' => 'delivery_review',
        ]);
        $this->assertTrue($invoice->isBillingProtected());

        // Post loadout save
        $response = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => [
                "{$this->tomato->id}|A" => 12.0,
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'loadout_saved' => true,
            'invoice_synced' => true,
            'invoice_error' => null,
            'message' => 'Loadout saved successfully.',
        ]);

        // Physical quantity was updated
        $this->assertEquals(12.0, (float) $item->fresh()->loaded_qty);

        // Invoice was NOT repriced or corrupted
        $this->assertEquals(400.0, (float) $invoice->fresh()->final_total);
    }

    /**
     * Requirement 5: Dispatch does not fail because of the repricing guard.
     */
    public function test_dispatch_does_not_fail_when_invoice_is_billing_protected(): void
    {
        $businessDate = '2026-11-18';
        $this->approvePrice($this->tomato, $businessDate, 'A', 40.0);
        $this->createStock($this->tomato, 100.0, 'A', $businessDate);

        $order = $this->createOrder($businessDate, 'ready_for_dispatch');
        $item = $this->createOrderItem($order, $this->tomato, 'A', 10.0, 10.0, 10.0, 'loaded');

        $invoice = $this->invoiceService->synchronizeOrderInvoice($order, (int) $this->user->id);
        $this->invoiceService->repriceInvoice($invoice, (int) $this->user->id);

        // Make invoice billing protected
        $invoice->update([
            'status' => 'payment_pending',
        ]);
        $this->assertTrue($invoice->isBillingProtected());

        // Dispatch via WarehouseDispatchService
        $this->dispatchService->dispatch($order, $this->user, false);

        $this->assertSame('in_transit', $order->fresh()->delivery_status);
        $this->assertEquals(400.0, (float) $invoice->fresh()->final_total);
    }

    /**
     * Requirement 6: Zero-total invoice without actual payment is not incorrectly protected solely by paid status.
     */
    public function test_zero_total_invoice_without_payment_is_not_protected_solely_by_paid_status(): void
    {
        $businessDate = '2026-11-18';
        $this->approvePrice($this->tomato, $businessDate, 'A', 40.0);
        $this->createStock($this->tomato, 100.0, 'A', $businessDate);

        $order = $this->createOrder($businessDate, 'pending_delivery');
        // Item requested/approved, but loaded_qty = 0 and allocated
        $item = $this->createOrderItem($order, $this->tomato, 'A', 10.0, 10.0, 0.0, 'allocated');

        // Create initial invoice with 0 line items (e.g. not yet loaded)
        $invoice = ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-TEST-ZERO-1',
            'business_date' => $businessDate,
            'subtotal' => 0.00,
            'final_total' => 0.00,
            'balance_amount' => 0.00,
            'paid_amount' => 0.00,
            'status' => 'paid', // Assigned by ShopFinalBillService because finalNetPayable <= 0
            'payment_status' => 'paid',
            'delivery_status' => 'pending',
            'payment_approved_at' => null,
            'finalized_at' => null,
            'generated_by' => $this->user->id,
        ]);

        // It must NOT be billing-protected or final-locked
        $this->assertFalse($invoice->isBillingProtected());
        $this->assertFalse($invoice->isFinalLocked());

        // Now save loadout with 10 kg loaded
        $response = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => [
                "{$this->tomato->id}|A" => 10.0,
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'loadout_saved' => true,
            'invoice_synced' => true,
            'invoice_error' => null,
        ]);

        // Invoice is now priced and updated properly
        $freshInvoice = $invoice->fresh('items');
        $this->assertEquals(400.0, (float) $freshInvoice->final_total);
    }

    /**
     * Requirement 7: Genuinely paid invoices remain protected.
     */
    public function test_genuinely_paid_invoices_remain_protected(): void
    {
        $businessDate = '2026-11-18';
        $shopA = Shop::factory()->create(['shop_price_group_id' => $this->priceGroup->id]);
        $shopB = Shop::factory()->create(['shop_price_group_id' => $this->priceGroup->id]);
        $shopC = Shop::factory()->create(['shop_price_group_id' => $this->priceGroup->id]);

        $orderA = ShopOrder::factory()->create(['shop_id' => $shopA->id, 'business_date' => $businessDate, 'delivery_status' => 'pending_delivery']);
        $orderB = ShopOrder::factory()->create(['shop_id' => $shopB->id, 'business_date' => $businessDate, 'delivery_status' => 'pending_delivery']);
        $orderC = ShopOrder::factory()->create(['shop_id' => $shopC->id, 'business_date' => $businessDate, 'delivery_status' => 'pending_delivery']);

        // Case A: final_total > 0 and status = paid
        $invoiceA = ShopInvoice::create([
            'shop_id' => $shopA->id,
            'shop_order_id' => $orderA->id,
            'invoice_number' => 'INV-TEST-PAID-A',
            'business_date' => $businessDate,
            'subtotal' => 500.00,
            'final_total' => 500.00,
            'balance_amount' => 0.00,
            'paid_amount' => 500.00,
            'status' => 'paid',
            'payment_status' => 'paid',
            'delivery_status' => 'pending',
            'generated_by' => $this->user->id,
        ]);
        $this->assertTrue($invoiceA->isBillingProtected());
        $this->assertTrue($invoiceA->isFinalLocked());

        // Case B: paid_amount > 0 even if final_total is 0
        $invoiceB = ShopInvoice::create([
            'shop_id' => $shopB->id,
            'shop_order_id' => $orderB->id,
            'invoice_number' => 'INV-TEST-PAID-B',
            'business_date' => $businessDate,
            'subtotal' => 0.00,
            'final_total' => 0.00,
            'balance_amount' => 0.00,
            'paid_amount' => 50.00,
            'status' => 'paid',
            'payment_status' => 'paid',
            'delivery_status' => 'pending',
            'generated_by' => $this->user->id,
        ]);
        $this->assertTrue($invoiceB->isBillingProtected());

        // Case C: payment_approved_at is set
        $invoiceC = ShopInvoice::create([
            'shop_id' => $shopC->id,
            'shop_order_id' => $orderC->id,
            'invoice_number' => 'INV-TEST-PAID-C',
            'business_date' => $businessDate,
            'subtotal' => 0.00,
            'final_total' => 0.00,
            'balance_amount' => 0.00,
            'paid_amount' => 0.00,
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_approved_at' => now(),
            'delivery_status' => 'pending',
            'generated_by' => $this->user->id,
        ]);
        $this->assertTrue($invoiceC->isBillingProtected());
    }

    /**
     * Requirement 8: Finalized invoices remain protected.
     */
    public function test_finalized_invoices_remain_protected(): void
    {
        $businessDate = '2026-11-18';
        $order = $this->createOrder($businessDate, 'pending_delivery');

        $invoice = ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-TEST-FINALIZED',
            'business_date' => $businessDate,
            'subtotal' => 300.00,
            'final_total' => 300.00,
            'balance_amount' => 300.00,
            'paid_amount' => 0.00,
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $this->user->id,
            'delivery_status' => 'pending',
            'generated_by' => $this->user->id,
        ]);

        $this->assertTrue($invoice->isBillingProtected());
        $this->assertTrue($invoice->isFinalLocked());

        // Direct reprice must be rejected
        $this->expectException(ValidationException::class);
        $this->invoiceService->repriceInvoice($invoice, (int) $this->user->id);
    }

    /**
     * Requirement 9: Web loadout save also skips repricing cleanly on billing-protected invoices.
     */
    public function test_web_loadout_save_succeeds_without_error_when_invoice_is_protected(): void
    {
        $businessDate = '2026-11-18';
        $this->approvePrice($this->tomato, $businessDate, 'A', 40.0);
        $this->createStock($this->tomato, 100.0, 'A', $businessDate);

        $order = $this->createOrder($businessDate, 'ready_for_dispatch');
        $item = $this->createOrderItem($order, $this->tomato, 'A', 10.0, 10.0, 10.0, 'loaded');

        $invoice = $this->invoiceService->synchronizeOrderInvoice($order, (int) $this->user->id);
        $this->invoiceService->repriceInvoice($invoice, (int) $this->user->id);

        // Mark invoice protected
        $invoice->update([
            'delivery_status' => 'awaiting_review',
            'status' => 'delivery_review',
        ]);

        $response = $this->post("/warehouse/loadout/{$order->id}/save", [
            'items' => [
                "{$this->tomato->id}|A" => 15.0,
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'loadout_saved' => true,
            'invoice_synced' => true,
            'invoice_error' => null,
            'message' => 'Loadout saved successfully.',
        ]);

        $this->assertEquals(15.0, (float) $item->fresh()->loaded_qty);
        $this->assertEquals(400.0, (float) $invoice->fresh()->final_total);
    }

    private function approvePrice(Product $product, string $date, string $grade, float $price): DailyPriceApproval
    {
        $existing = DailyPriceApproval::where('product_id', $product->id)
            ->whereDate('business_date', $date)
            ->where('grade', $grade)
            ->first();

        if ($existing) {
            $existing->update([
                'purchase_price' => $price * 0.8,
                'price_unit' => 'kg',
                'price_a' => $price,
                'price_b' => $price,
                'price_c' => $price,
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $this->user->id,
            ]);

            return $existing;
        }

        return DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => $date,
            'grade' => $grade,
            'purchase_price' => $price * 0.8,
            'price_unit' => 'kg',
            'price_a' => $price,
            'price_b' => $price,
            'price_c' => $price,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);
    }

    private function createStock(Product $product, float $quantity, string $grade, string $date): StockBatch
    {
        $batch = StockBatch::factory()->create([
            'reference' => 'BATCH-TEST-'.uniqid(),
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => $quantity,
            'received_at' => $date,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'purchase_grade' => $grade,
            'grading_mode' => 'fixed_purchase_grade',
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'grade' => $grade,
            'type' => StockMovementType::In,
            'quantity' => $quantity,
            'cost_per_unit' => 20.0,
            'movement_date' => $date,
        ]);

        return $batch;
    }

    private function createOrder(string $date, string $deliveryStatus): ShopOrder
    {
        return ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'delivery_status' => $deliveryStatus,
        ]);
    }

    private function createOrderItem(
        ShopOrder $order,
        Product $product,
        string $grade,
        float $requestedQty,
        float $approvedQty,
        ?float $loadedQty,
        string $sortingStatus = 'loaded'
    ): ShopOrderItem {
        return ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => $grade,
            'requested_qty' => $requestedQty,
            'approved_qty' => $approvedQty,
            'loaded_qty' => $loadedQty,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'sorting_status' => $sortingStatus,
        ]);
    }
}
