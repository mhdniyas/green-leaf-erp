<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\PurchaserReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserItemSummaryDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Warehouse $warehouse;

    private Product $product;

    private Shop $shop;

    private Supplier $supplier;

    private PurchaserReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse', 'code' => 'MW1']);
        $this->supplier = Supplier::factory()->create();

        $priceGroup = ShopPriceGroup::factory()->create(['name' => 'Standard', 'is_active' => true]);
        $this->shop = Shop::factory()->create(['shop_price_group_id' => $priceGroup->id]);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'name' => 'Tomato Hybrid',
            'sku' => 'TOM-HYB',
        ]);

        $this->reportService = app(PurchaserReportService::class);
    }

    private function createBilledShopInvoice(float $billedQty, string $date): void
    {
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'approved',
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_order_id' => $order->id,
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'status' => 'approved',
            'final_total' => $billedQty * 10,
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'delivered_qty' => $billedQty,
            'delivered_price_quantity' => $billedQty,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'final_line_total' => $billedQty * 10,
        ]);
    }

    private function createAdvanceGrn(float $qty, string $date): array
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'received_at' => $date,
            'status' => 'approved',
        ]);

        $item = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => $qty,
        ]);

        return [$grn, $item];
    }

    private function createBillGrn(float $qty, string $date): array
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
        ]);

        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'received_at' => $date,
            'status' => 'approved',
        ]);

        $item = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => $qty,
        ]);

        return [$grn, $item];
    }

    private function createMatch(GoodsReceived $advGrn, GoodsReceivedItem $advItem, GoodsReceived $billGrn, GoodsReceivedItem $billItem, float $matchedQty): AdvanceReceiveMatch
    {
        return AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->product->id,
            'matched_qty' => $matchedQty,
            'matched_unit' => 'kg',
            'base_qty' => $matchedQty,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->purchaser->id,
            'confirmed_at' => now(),
        ]);
    }

    public function test_fully_matched_advance_and_bill_counts_incoming_once(): void
    {
        $date = '2026-10-27';
        $this->createBilledShopInvoice(50.0, $date);

        [$advGrn, $advItem] = $this->createAdvanceGrn(100.0, $date);
        [$billGrn, $billItem] = $this->createBillGrn(100.0, $date);
        $this->createMatch($advGrn, $advItem, $billGrn, $billItem, 100.0);

        $report = $this->reportService->itemSummary([
            'date_from' => $date,
            'date_to' => $date,
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
        ]);

        $this->assertCount(1, $report['items']);
        $item = $report['items'][0];

        // Physical delivery was 100 kg. Must not be 200 kg.
        $this->assertEquals('100.0000', $item['incoming_quantity']);
        $this->assertEquals('50.0000', $item['outgoing_quantity']);
        $this->assertEquals('50.0000', $item['balance_quantity']);
    }

    public function test_partially_matched_advance_and_bill_excludes_only_reconciled_quantity(): void
    {
        $date = '2026-10-27';
        $this->createBilledShopInvoice(30.0, $date);

        [$advGrn, $advItem] = $this->createAdvanceGrn(100.0, $date);
        [$billGrn, $billItem] = $this->createBillGrn(40.0, $date);
        $this->createMatch($advGrn, $advItem, $billGrn, $billItem, 40.0);

        $report = $this->reportService->itemSummary([
            'date_from' => $date,
            'date_to' => $date,
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
        ]);

        $item = $report['items'][0];

        // 100 kg advance + 40 kg bill - 40 kg match = 100 kg physical
        $this->assertEquals('100.0000', $item['incoming_quantity']);
        $this->assertEquals('30.0000', $item['outgoing_quantity']);
        $this->assertEquals('70.0000', $item['balance_quantity']);
    }

    public function test_unmatched_advance_receipt_is_counted_normally(): void
    {
        $date = '2026-10-27';
        $this->createBilledShopInvoice(20.0, $date);

        $this->createAdvanceGrn(100.0, $date);

        $report = $this->reportService->itemSummary([
            'date_from' => $date,
            'date_to' => $date,
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
        ]);

        $item = $report['items'][0];
        $this->assertEquals('100.0000', $item['incoming_quantity']);
        $this->assertEquals('20.0000', $item['outgoing_quantity']);
        $this->assertEquals('80.0000', $item['balance_quantity']);
    }

    public function test_direct_purchase_without_advance_is_counted_normally(): void
    {
        $date = '2026-10-27';
        $this->createBilledShopInvoice(25.0, $date);

        $this->createBillGrn(100.0, $date);

        $report = $this->reportService->itemSummary([
            'date_from' => $date,
            'date_to' => $date,
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
        ]);

        $item = $report['items'][0];
        $this->assertEquals('100.0000', $item['incoming_quantity']);
        $this->assertEquals('25.0000', $item['outgoing_quantity']);
        $this->assertEquals('75.0000', $item['balance_quantity']);
    }

    public function test_cross_day_matching_preserves_accurate_date_attribution(): void
    {
        $day1 = '2026-10-27';
        $day2 = '2026-10-28';

        $this->createBilledShopInvoice(10.0, $day1);
        $this->createBilledShopInvoice(15.0, $day2);

        // Advance arrives on Day 1 (100 kg)
        [$advGrn, $advItem] = $this->createAdvanceGrn(100.0, $day1);

        // Bill arrives on Day 2 (100 kg) and is matched to Day 1 Advance
        [$billGrn, $billItem] = $this->createBillGrn(100.0, $day2);
        $this->createMatch($advGrn, $advItem, $billGrn, $billItem, 100.0);

        // Query Day 1: Physical delivery of 100 kg happened on Day 1
        $reportDay1 = $this->reportService->itemSummary([
            'date_from' => $day1,
            'date_to' => $day1,
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
        ]);
        $this->assertEquals('100.0000', $reportDay1['items'][0]['incoming_quantity']);

        // Query Day 2: Bill arrived on Day 2, but matched against Day 1 advance -> Net incoming on Day 2 is 0 kg
        $reportDay2 = $this->reportService->itemSummary([
            'date_from' => $day2,
            'date_to' => $day2,
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
        ]);
        $this->assertEquals('0.0000', $reportDay2['items'][0]['incoming_quantity']);

        // Query Range (Day 1 to Day 2): Total physical incoming across both days is 100 kg
        $reportRange = $this->reportService->itemSummary([
            'date_from' => $day1,
            'date_to' => $day2,
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
        ]);
        $this->assertEquals('100.0000', $reportRange['items'][0]['incoming_quantity']);
    }

    public function test_cancelled_or_deleted_grn_is_excluded(): void
    {
        $date = '2026-10-27';
        $this->createBilledShopInvoice(10.0, $date);

        // Create cancelled advance GRN
        $cancelledGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'received_at' => $date,
            'status' => 'cancelled',
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $cancelledGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
        ]);

        // Create approved advance GRN
        $this->createAdvanceGrn(100.0, $date);

        $report = $this->reportService->itemSummary([
            'date_from' => $date,
            'date_to' => $date,
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
        ]);

        // Only approved 100 kg should be counted
        $this->assertEquals('100.0000', $report['items'][0]['incoming_quantity']);
    }
}
