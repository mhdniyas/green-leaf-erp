<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\ProductService;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Requisition\ShopOrderItemSyncService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductUnitChangeRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Shop $shop;

    private ShopPriceGroup $priceGroup;

    private User $admin;

    private string $businessDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::factory()->create();
        $this->priceGroup = ShopPriceGroup::factory()->create(['name' => 'A']);
        $this->shop = Shop::factory()->create(['shop_price_group_id' => $this->priceGroup->id]);
        $this->admin = User::factory()->create();
        $this->businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();
    }

    /**
     * Test 1: Historical Shop Order created in kg remains valid and invoice succeeds
     * after Product base unit is changed to piece without a kg conversion.
     */
    public function test_shop_order_history_remains_valid_and_invoice_succeeds_when_product_unit_changes(): void
    {
        // 1. Create product in kg
        $product = Product::factory()->create([
            'name' => 'Tomato Test',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        // Daily price approval in kg
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $this->businessDate,
            'purchase_price' => 20,
            'price_unit' => 'kg',
            'price_a' => 30,
            'price_b' => 30,
            'price_c' => 30,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // 2. Create and approve shop order in kg
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);
        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'loaded_qty' => 20.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 20.0,
            'requested_unit_conversion_to_base' => 1.0,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 30.0,
            'locked_price_source' => 'daily_price',
            'line_total' => 600.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        // 3. Admin updates Product base unit to piece (with piece as base unit and kg deactivated)
        $productService = app(ProductService::class);
        $productService->update($product, new ProductData(
            categoryId: (int) $product->category_id,
            defaultWarehouseId: (int) $this->warehouse->id,
            name: $product->name,
            sku: $product->sku,
            unit: 'piece',
            description: $product->description,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
            units: [
                [
                    'unit' => 'piece',
                    'label' => 'PIECE',
                    'conversion_to_base' => 1.0,
                    'is_base' => true,
                    'is_orderable' => true,
                ],
            ],
        ));

        $product->refresh();
        $this->assertSame('piece', $product->unit);

        // 4. Generate/synchronize invoice for the old order
        $invoiceService = app(ShopInvoiceService::class);
        $invoice = $invoiceService->synchronizeOrderInvoice($order->fresh(['items.product', 'shop.priceGroup']), (int) $this->admin->id);

        // 5. Verify invoice succeeded without conversion errors
        $this->assertNotNull($invoice);
        $this->assertCount(1, $invoice->items);

        $invoiceItem = $invoice->items->first();
        $this->assertSame('kg', $invoiceItem->unit);
        $this->assertSame(20.0, (float) $invoiceItem->approved_qty);
        $this->assertSame(20.0, (float) $invoiceItem->delivered_qty);
        $this->assertSame(600.0, (float) $invoiceItem->final_line_total);
    }

    /**
     * Test 2: New Shop Order created after product unit changes to piece uses piece.
     */
    public function test_new_shop_order_uses_new_unit_after_product_unit_change(): void
    {
        // 1. Create product in piece
        $product = Product::factory()->create([
            'name' => 'Cabbage Test',
            'sku' => 'CAB-001',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'piece',
            'label' => 'PIECE',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $this->businessDate,
            'purchase_price' => 10,
            'price_unit' => 'piece',
            'price_a' => 15,
            'price_b' => 15,
            'price_c' => 15,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // 2. Resolve requested product for shop order
        $syncService = app(ShopOrderItemSyncService::class);
        $resolved = $syncService->resolveRequestedProducts(
            rawItems: [$product->sku => 5],
            rawUnits: [$product->sku => 'piece'],
            rawMeasures: []
        );

        $this->assertCount(1, $resolved);
        $this->assertSame('piece', $resolved[0]['unit']);
        $this->assertSame('piece', $resolved[0]['requested_unit']);
        $this->assertSame(5.0, (float) $resolved[0]['quantity']);

        // 3. Sync into a new shop order
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
        ]);

        $syncService->syncShopOrderItems($order, $resolved);

        $this->assertCount(1, $order->items);
        $orderItem = $order->items->first();
        $this->assertSame('piece', $orderItem->unit);
        $this->assertSame('piece', $orderItem->requested_unit);
        $this->assertSame(5.0, (float) $orderItem->requested_qty);
    }

    /**
     * Test 3: Old Advance GRN in kg and Old PO Bill in kg do not produce unit issues
     * even when the current Product base unit is piece.
     */
    public function test_purchasing_historical_same_unit_does_not_flag_unit_issue_when_product_unit_changes(): void
    {
        $supplier = Supplier::factory()->create();

        // 1. Product currently has base unit = piece
        $product = Product::factory()->create([
            'name' => 'Ginger Test',
            'sku' => 'GIN-001',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'piece',
            'label' => 'PIECE',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        // 2. Historical Advance GRN received in kg
        $advGrn = GoodsReceived::query()->create([
            'grn_number' => 'ADV-'.uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $supplier->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => $this->businessDate,
            'received_by' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);
        $advItem = GoodsReceivedItem::query()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $product->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'unit_price' => 50.0,
            'total_price' => 2500.0,
            'variance' => 0.0,
        ]);
        StockBatch::query()->create([
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'reference' => 'BATCH-'.uniqid(),
            'total_kg' => 50.0,
            'cost_per_kg' => 50.0,
            'quantity' => 50.0,
            'current_quantity' => 50.0,
            'received_at' => $this->businessDate,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->admin->id,
        ]);

        // 3. Historical PO Bill in kg
        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-'.uniqid(),
            'supplier_id' => $supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'approved',
            'order_date' => $this->businessDate,
            'created_by' => $this->admin->id,
        ]);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 50.0,
            'purchase_unit' => 'kg',
            'unit_price' => 50.0,
            'grade' => 'A',
        ]);

        $diffs = app(AdvanceReceiveReconciliationService::class)
            ->paginateUnitDifferences(['warehouse_id' => $this->warehouse->id], 100);

        $this->assertCount(0, $diffs->items());
    }

    /**
     * Test 4: Historical Shop Order created in piece remains valid and invoice succeeds
     * after Product base unit is changed to kg without a piece conversion.
     */
    public function test_historical_piece_order_remains_valid_when_product_unit_changes_to_kg(): void
    {
        // 1. Create product in piece
        $product = Product::factory()->create([
            'name' => 'Coconut Test',
            'sku' => 'COC-001',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'piece',
            'label' => 'PIECE',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $this->businessDate,
            'purchase_price' => 15,
            'price_unit' => 'piece',
            'price_a' => 25,
            'price_b' => 25,
            'price_c' => 25,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // 2. Create and approve shop order in piece
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);
        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'piece',
            'requested_unit' => 'piece',
            'requested_unit_label' => 'PIECE',
            'requested_unit_quantity' => 10.0,
            'requested_unit_conversion_to_base' => 1.0,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 25.0,
            'locked_price_source' => 'daily_price',
            'line_total' => 250.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        // 3. Admin updates Product base unit to kg
        $productService = app(ProductService::class);
        $productService->update($product, new ProductData(
            categoryId: (int) $product->category_id,
            defaultWarehouseId: (int) $this->warehouse->id,
            name: $product->name,
            sku: $product->sku,
            unit: 'kg',
            description: $product->description,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
            units: [
                [
                    'unit' => 'kg',
                    'label' => 'KG',
                    'conversion_to_base' => 1.0,
                    'is_base' => true,
                    'is_orderable' => true,
                ],
            ],
        ));

        // 4. Synchronize invoice for the old order
        $invoiceService = app(ShopInvoiceService::class);
        $invoice = $invoiceService->synchronizeOrderInvoice($order->fresh(['items.product', 'shop.priceGroup']), (int) $this->admin->id);

        // 5. Verify invoice succeeds with piece unit
        $this->assertNotNull($invoice);
        $invoiceItem = $invoice->items->first();
        $this->assertSame('piece', $invoiceItem->unit);
        $this->assertSame(10.0, (float) $invoiceItem->approved_qty);
        $this->assertSame(250.0, (float) $invoiceItem->final_line_total);
    }

    /**
     * Test 5: Variable piece loadout priced by actual measured kg.
     */
    public function test_variable_piece_loadout_priced_by_actual_measured_kg(): void
    {
        $product = Product::factory()->create([
            'name' => 'Watermelon Test',
            'sku' => 'WM-001',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'piece',
            'label' => 'PIECE',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        // Price is set in KG (₹40/kg)
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $this->businessDate,
            'purchase_price' => 30,
            'price_unit' => 'kg',
            'price_a' => 40,
            'price_b' => 40,
            'price_c' => 40,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'loaded_qty' => 20.0,
            'actual_weight' => 13.4, // Measured weight at warehouse loadout
            'unit' => 'piece',
            'requested_unit' => 'piece',
            'requested_unit_label' => 'PIECE',
            'requested_unit_quantity' => 20.0,
            'requested_unit_conversion_to_base' => 1.0,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 40.0,
            'locked_price_source' => 'daily_price',
            'line_total' => 536.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        $invoiceService = app(ShopInvoiceService::class);
        $invoice = $invoiceService->synchronizeOrderInvoice($order->fresh(['items.product', 'shop.priceGroup']), (int) $this->admin->id);

        $this->assertNotNull($invoice);
        $invoiceItem = $invoice->items->first();
        // Price quantity should use the actual measured weight (13.4 kg)
        $this->assertSame(13.4, (float) $invoiceItem->price_quantity);
        $this->assertSame(40.0, (float) $invoiceItem->unit_price);
        // 13.4 kg * ₹40 = ₹536.00
        $this->assertSame(536.0, (float) $invoiceItem->final_line_total);
    }

    /**
     * Test 6: Variable piece loadout priced by kg fails cleanly with clear transaction error
     * when actual weight was not measured.
     */
    public function test_variable_piece_loadout_priced_by_kg_fails_cleanly_when_actual_weight_is_missing(): void
    {
        $product = Product::factory()->create([
            'name' => 'Pumpkin Test',
            'sku' => 'PUMP-001',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'piece',
            'label' => 'PIECE',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $this->businessDate,
            'purchase_price' => 30,
            'price_unit' => 'kg',
            'price_a' => 40,
            'price_b' => 40,
            'price_c' => 40,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'loaded_qty' => 20.0,
            'actual_weight' => null, // No measured weight
            'unit' => 'piece',
            'requested_unit' => 'piece',
            'requested_unit_label' => 'PIECE',
            'requested_unit_quantity' => 20.0,
            'requested_unit_conversion_to_base' => 1.0,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 40.0,
            'locked_price_source' => 'daily_price',
            'line_total' => 800.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Actual kg quantity is required to price this piece-based loadout at a per-kg rate.');

        $invoiceService = app(ShopInvoiceService::class);
        $invoiceService->synchronizeOrderInvoice($order->fresh(['items.product', 'shop.priceGroup']), (int) $this->admin->id);
    }

    /**
     * Test 7: Omitted ProductUnit row is deactivated (not deleted) and retained for history.
     */
    public function test_product_unit_deactivated_when_omitted_from_update(): void
    {
        $product = Product::factory()->create([
            'name' => 'Apple Test',
            'sku' => 'APP-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        $kgUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        $productService = app(ProductService::class);
        $productService->update($product, new ProductData(
            categoryId: (int) $product->category_id,
            defaultWarehouseId: (int) $this->warehouse->id,
            name: $product->name,
            sku: $product->sku,
            unit: 'piece',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
            units: [
                [
                    'unit' => 'piece',
                    'label' => 'PIECE',
                    'conversion_to_base' => 1.0,
                    'is_base' => true,
                    'is_orderable' => true,
                ],
            ],
        ));

        // The kg row still exists in DB but is inactive
        $kgUnit->refresh();
        $this->assertFalse((bool) $kgUnit->is_base);
        $this->assertFalse((bool) $kgUnit->is_orderable);
        $this->assertSame(2, ProductUnit::query()->where('product_id', $product->id)->count());
    }

    /**
     * Test 8: Flip-flopping unit (kg -> piece -> kg) reactivates existing row without duplicate.
     */
    public function test_product_unit_reactivated_without_duplicate_on_unit_flip_flop(): void
    {
        $product = Product::factory()->create([
            'name' => 'Banana Test',
            'sku' => 'BAN-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        $kgUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        $productService = app(ProductService::class);

        // Step 1: Change to piece
        $productService->update($product, new ProductData(
            categoryId: (int) $product->category_id,
            defaultWarehouseId: (int) $this->warehouse->id,
            name: $product->name,
            sku: $product->sku,
            unit: 'piece',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
            units: [
                ['unit' => 'piece', 'label' => 'PIECE', 'conversion_to_base' => 1.0, 'is_base' => true, 'is_orderable' => true],
            ],
        ));

        // Step 2: Change back to kg
        $productService->update($product, new ProductData(
            categoryId: (int) $product->category_id,
            defaultWarehouseId: (int) $this->warehouse->id,
            name: $product->name,
            sku: $product->sku,
            unit: 'kg',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
            units: [
                ['unit' => 'kg', 'label' => 'KG', 'conversion_to_base' => 1.0, 'is_base' => true, 'is_orderable' => true],
            ],
        ));

        // Should still only have 2 rows total (kg and piece), no duplicate kg
        $this->assertSame(2, ProductUnit::query()->where('product_id', $product->id)->count());
        $this->assertSame(1, ProductUnit::query()->where('product_id', $product->id)->where('unit', 'kg')->count());

        $kgUnit->refresh();
        $this->assertTrue((bool) $kgUnit->is_base);
        $this->assertTrue((bool) $kgUnit->is_orderable);
    }

    /**
     * Test 9: Fixed packaging conversion (e.g. 1 Box = 10 KG) continues to work.
     */
    public function test_fixed_packaging_box_conversion_continues_to_work(): void
    {
        $product = Product::factory()->create([
            'name' => 'Mango Box Test',
            'sku' => 'MAN-BOX-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'BOX (10 KG)',
            'conversion_to_base' => 10.0, // 1 Box = 10 KG
            'is_base' => false,
            'is_orderable' => true,
        ]);

        // Price in Box: ₹500 per box
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $this->businessDate,
            'purchase_price' => 400,
            'price_unit' => 'box',
            'price_a' => 500,
            'price_b' => 500,
            'price_c' => 500,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Shop ordered 30 kg (= 3 boxes)
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'delivery_status' => 'pending_delivery',
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'loaded_qty' => 30.0,
            'unit' => 'kg',
            'requested_unit' => 'box',
            'requested_unit_label' => 'BOX (10 KG)',
            'requested_unit_quantity' => 3.0,
            'requested_unit_conversion_to_base' => 10.0,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 500.0,
            'locked_price_source' => 'daily_price',
            'line_total' => 1500.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        $invoiceService = app(ShopInvoiceService::class);
        $invoice = $invoiceService->synchronizeOrderInvoice($order->fresh(['items.product', 'shop.priceGroup']), (int) $this->admin->id);

        $this->assertNotNull($invoice);
        $invoiceItem = $invoice->items->first();
        // 30 kg / 10 kg/box = 3 boxes
        $this->assertSame(3.0, (float) $invoiceItem->price_quantity);
        $this->assertSame('box', $invoiceItem->price_unit);
        $this->assertSame(500.0, (float) $invoiceItem->unit_price);
        $this->assertSame(1500.0, (float) $invoiceItem->final_line_total);
    }

    /**
     * Test 10: Existing inactive CRATE row is reused and reactivated without duplicate or SQL exception.
     */
    public function test_existing_inactive_crate_reused_without_duplicate(): void
    {
        $product = Product::factory()->create([
            'name' => 'Cabbage Test',
            'sku' => 'CAB-001',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        $pieceUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'piece',
            'label' => 'PIECE',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);
        $crateUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'crate',
            'label' => 'CRATE',
            'conversion_to_base' => 20.0,
            'is_base' => false,
            'is_orderable' => false, // Inactive
        ]);

        $productService = app(ProductService::class);
        $productService->update($product, new ProductData(
            categoryId: (int) $product->category_id,
            defaultWarehouseId: (int) $this->warehouse->id,
            name: $product->name,
            sku: $product->sku,
            unit: 'piece',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
            units: [
                ['id' => $pieceUnit->id, 'unit' => 'piece', 'label' => 'PIECE', 'conversion_to_base' => 1.0, 'is_base' => true, 'is_orderable' => true],
                ['unit' => 'crate', 'label' => 'CRATE', 'conversion_to_base' => 25.0, 'is_base' => false, 'is_orderable' => true],
            ],
        ));

        // Total rows must still be 2 (piece and crate)
        $this->assertSame(2, ProductUnit::query()->where('product_id', $product->id)->count());
        $crateUnit->refresh();
        $this->assertTrue((bool) $crateUnit->is_orderable);
        $this->assertSame(25.0, (float) $crateUnit->conversion_to_base);
    }

    /**
     * Test 11: Stale submitted ID does not cause renaming of row A to row B's label.
     */
    public function test_stale_submitted_id_reuses_existing_target_unit_row_safely(): void
    {
        $product = Product::factory()->create([
            'name' => 'Carrot Test',
            'sku' => 'CAR-001',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        $unitA = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'piece',
            'label' => 'PIECE',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);
        $unitB = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'crate',
            'label' => 'CRATE',
            'conversion_to_base' => 15.0,
            'is_base' => false,
            'is_orderable' => false,
        ]);

        // Form submits unitA's id, but with unit=crate, label=CRATE
        $productService = app(ProductService::class);
        $productService->update($product, new ProductData(
            categoryId: (int) $product->category_id,
            defaultWarehouseId: (int) $this->warehouse->id,
            name: $product->name,
            sku: $product->sku,
            unit: 'crate',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
            units: [
                ['id' => $unitA->id, 'unit' => 'crate', 'label' => 'CRATE', 'conversion_to_base' => 1.0, 'is_base' => true, 'is_orderable' => true],
            ],
        ));

        $this->assertSame(2, ProductUnit::query()->where('product_id', $product->id)->count());

        // unitB (CRATE) should have been reactivated
        $unitB->refresh();
        $this->assertTrue((bool) $unitB->is_base);
        $this->assertTrue((bool) $unitB->is_orderable);
        $this->assertSame('CRATE', $unitB->label);
        $this->assertSame('crate', $unitB->unit);

        // unitA (PIECE) remains deactivated and was NOT renamed to CRATE
        $unitA->refresh();
        $this->assertFalse((bool) $unitA->is_base);
        $this->assertFalse((bool) $unitA->is_orderable);
        $this->assertSame('PIECE', $unitA->label);
        $this->assertSame('piece', $unitA->unit);
    }

    /**
     * Test 12: Genuine conflicting label throws ValidationException and prevents HTTP 500 error.
     */
    public function test_genuine_conflicting_label_throws_validation_exception(): void
    {
        $product = Product::factory()->create([
            'name' => 'Lemon Test',
            'sku' => 'LEM-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'BOX (10 KG)',
            'conversion_to_base' => 10.0,
            'is_base' => false,
            'is_orderable' => false,
        ]);

        $this->expectException(ValidationException::class);

        // Incoming attempts to assign existing omitted BOX label to a piece unit
        $productService = app(ProductService::class);
        $productService->update($product, new ProductData(
            categoryId: (int) $product->category_id,
            defaultWarehouseId: (int) $this->warehouse->id,
            name: $product->name,
            sku: $product->sku,
            unit: 'kg',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
            units: [
                ['unit' => 'kg', 'label' => 'KG', 'conversion_to_base' => 1.0, 'is_base' => true, 'is_orderable' => true],
                ['unit' => 'piece', 'label' => 'BOX (10 KG)', 'conversion_to_base' => 0.5, 'is_base' => false, 'is_orderable' => true],
            ],
        ));
    }

    /**
     * Test 13: Historical safety - reactivating/deactivating units does not modify transactions.
     */
    public function test_historical_transactions_safety_on_unit_reactivation(): void
    {
        $product = Product::factory()->create([
            'name' => 'Onion Test',
            'sku' => 'ON-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        $kgUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        // Historical Shop Order Item
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
        ]);
        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'line_total' => 300.0,
        ]);

        // Change unit kg -> piece -> kg
        $productService = app(ProductService::class);
        $productService->update($product, new ProductData(
            categoryId: (int) $product->category_id,
            defaultWarehouseId: (int) $this->warehouse->id,
            name: $product->name,
            sku: $product->sku,
            unit: 'piece',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
            units: [
                ['unit' => 'piece', 'label' => 'PIECE', 'conversion_to_base' => 1.0, 'is_base' => true, 'is_orderable' => true],
            ],
        ));

        $productService->update($product, new ProductData(
            categoryId: (int) $product->category_id,
            defaultWarehouseId: (int) $this->warehouse->id,
            name: $product->name,
            sku: $product->sku,
            unit: 'kg',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
            units: [
                ['unit' => 'kg', 'label' => 'KG', 'conversion_to_base' => 1.0, 'is_base' => true, 'is_orderable' => true],
            ],
        ));

        // Verify orderItem remains completely unchanged
        $orderItem->refresh();
        $this->assertSame('kg', $orderItem->unit);
        $this->assertSame('kg', $orderItem->requested_unit);
        $this->assertSame(15.0, (float) $orderItem->approved_qty);
        $this->assertSame(300.0, (float) $orderItem->line_total);
    }
}
