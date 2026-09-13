<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WarehouseReceivedBillAdvanceMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Warehouse $warehouse;

    private Product $product;

    private Supplier $supplier;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');
        $this->warehouse = Warehouse::factory()->create();
        $this->receiver->warehouses()->attach($this->warehouse);
        $this->receiver->givePermissionTo(['purchasing.grn.create', 'purchasing.grn.approve', 'warehouse.receive.view']);
        Sanctum::actingAs($this->receiver);
        $this->supplier = Supplier::factory()->create();
        $this->shop = Shop::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
        ]);
    }

    #[DataProvider('receivedBillMatchCases')]
    public function test_received_bill_match_removes_only_the_duplicate_bill_stock(float $advanceQty, float $billQty, float $matchQty): void
    {
        $date = '2026-09-13';
        [$advance, $advanceItem] = $this->createAdvance($advanceQty, $date);
        [$bill, $billItem, $billBatch] = $this->createReceivedBill($billQty, $date);
        $this->assertSame($advanceQty + $billQty, (float) StockBatch::query()->where('warehouse_id', $this->warehouse->id)->where('warehouse_receive_pending', false)->sum('total_kg'));

        $submissionId = (string) Str::uuid();
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'client_submission_id' => $submissionId,
            'advance_matches' => [[
                'advance_goods_received_id' => $advance->id,
                'advance_goods_received_item_id' => $advanceItem->id,
                'goods_received_item_id' => $billItem->id,
                'purchase_order_item_id' => $billItem->purchase_order_item_id,
                'product_id' => $this->product->id,
                'matched_qty' => $matchQty,
                'unit' => 'kg',
            ]],
        ];

        $this->postJson("/api/v1/purchasing/grns/{$bill->id}/advance-matches", $payload)->assertOk();
        $this->assertSame($billQty - $matchQty, (float) $billBatch->fresh()->total_kg);
        $this->assertSame($advanceQty, (float) $advance->stockBatches()->firstOrFail()->fresh()->total_kg);
        $this->assertSame($advanceQty + $billQty - $matchQty, (float) StockBatch::query()->where('warehouse_id', $this->warehouse->id)->where('warehouse_receive_pending', false)->sum('total_kg'));
        $this->assertSame($matchQty, (float) AdvanceReceiveMatch::query()->where('bill_goods_received_id', $bill->id)->sum('base_qty'));

        $this->postJson("/api/v1/purchasing/grns/{$bill->id}/advance-matches", $payload)->assertOk();
        $this->assertSame(1, AdvanceReceiveMatch::query()->where('bill_goods_received_id', $bill->id)->count());
        $this->assertSame($billQty - $matchQty, (float) $billBatch->fresh()->total_kg);
    }

    public static function receivedBillMatchCases(): array
    {
        return ['full match' => [10.0, 10.0, 10.0], 'advance smaller than bill' => [4.0, 10.0, 4.0], 'advance larger than bill' => [10.0, 4.0, 4.0]];
    }

    public function test_match_candidates_include_only_received_bills_for_the_selected_date(): void
    {
        $date = '2026-09-13';
        $this->createReceivedBill(10.0, $date);
        $this->createReceivedBill(10.0, '2026-09-12', false);
        $response = $this->getJson("/api/v1/purchasing/grns/received-advance-match-candidates?warehouse_id={$this->warehouse->id}&date={$date}")->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('received', $response->json('data.0.receipt_status'));
    }

    public function test_unreceived_bill_cannot_be_matched(): void
    {
        [$advance, $advanceItem] = $this->createAdvance(10.0, '2026-09-13');
        [$bill, $billItem] = $this->createReceivedBill(10.0, '2026-09-13', false);

        $this->postJson("/api/v1/purchasing/grns/{$bill->id}/advance-matches", $this->matchPayload($advance, $advanceItem, $billItem, 10.0))
            ->assertUnprocessable();
        $this->assertDatabaseCount('advance_receive_matches', 0);
    }

    public function test_cross_business_date_match_is_rejected(): void
    {
        [$advance, $advanceItem] = $this->createAdvance(10.0, '2026-09-12');
        [$bill, $billItem, $billBatch] = $this->createReceivedBill(10.0, '2026-09-13');

        $this->postJson("/api/v1/purchasing/grns/{$bill->id}/advance-matches", $this->matchPayload($advance, $advanceItem, $billItem, 10.0))
            ->assertUnprocessable();
        $this->assertSame(10.0, (float) $billBatch->fresh()->total_kg);
        $this->assertDatabaseCount('advance_receive_matches', 0);
    }

    private function matchPayload(GoodsReceived $advance, GoodsReceivedItem $advanceItem, GoodsReceivedItem $billItem, float $quantity): array
    {
        return [
            'warehouse_id' => $this->warehouse->id,
            'client_submission_id' => (string) Str::uuid(),
            'advance_matches' => [[
                'advance_goods_received_id' => $advance->id,
                'advance_goods_received_item_id' => $advanceItem->id,
                'goods_received_item_id' => $billItem->id,
                'purchase_order_item_id' => $billItem->purchase_order_item_id,
                'product_id' => $this->product->id,
                'matched_qty' => $quantity,
                'unit' => 'kg',
            ]],
        ];
    }

    private function createAdvance(float $quantity, string $date): array
    {
        $grn = GoodsReceived::factory()->create(['warehouse_id' => $this->warehouse->id, 'receipt_type' => 'warehouse_advance', 'received_at' => $date, 'status' => 'approved', 'bill_status' => 'bill_pending', 'received_by' => $this->receiver->id]);
        $item = GoodsReceivedItem::factory()->create(['goods_received_id' => $grn->id, 'product_id' => $this->product->id, 'received_qty' => $quantity, 'received_unit' => 'kg']);
        StockBatch::factory()->create(['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'goods_received_id' => $grn->id, 'goods_received_item_id' => $item->id, 'total_kg' => $quantity, 'received_at' => $date, 'status' => BatchStatus::Pending, 'warehouse_receive_pending' => false]);

        return [$grn, $item];
    }

    private function createReceivedBill(float $quantity, string $date, bool $received = true): array
    {
        $order = PurchaseOrder::factory()->create(['destination_shop_id' => $this->shop->id, 'supplier_id' => $this->supplier->id, 'order_date' => $date, 'status' => POStatus::Received]);
        $orderItem = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->product->id, 'quantity' => $quantity, 'purchase_unit' => 'kg']);
        $grn = GoodsReceived::factory()->create(['purchase_order_id' => $order->id, 'warehouse_id' => $this->warehouse->id, 'receipt_type' => 'normal_purchase', 'received_at' => $date, 'status' => 'approved', 'bill_status' => 'bill_pending', 'received_by' => $this->receiver->id]);
        $item = GoodsReceivedItem::factory()->create(['goods_received_id' => $grn->id, 'purchase_order_item_id' => $orderItem->id, 'product_id' => $this->product->id, 'received_qty' => $quantity, 'received_unit' => 'kg']);
        $batch = StockBatch::factory()->create(['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'goods_received_id' => $grn->id, 'goods_received_item_id' => $item->id, 'total_kg' => $quantity, 'received_at' => $date, 'status' => BatchStatus::Pending, 'warehouse_receive_pending' => ! $received]);

        return [$grn, $item, $batch];
    }
}
