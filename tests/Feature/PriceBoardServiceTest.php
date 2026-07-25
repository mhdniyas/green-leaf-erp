<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Purchasing\POStatus;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PriceBoardServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_per_unit_purchase_order_items_use_packet_quantity_for_daily_purchase_price(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Container 500 G',
            'unit' => 'pcs',
            'base_price' => 18,
        ]);

        $purchaseOrder = PurchaseOrder::query()->create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-PER-UNIT-TEST',
            'status' => POStatus::Received,
            'fulfillment_type' => 'warehouse',
            'order_date' => '2026-07-23',
            'created_by' => $user->id,
        ]);

        $purchaseOrderItem = $purchaseOrder->items()->create([
            'product_id' => $product->id,
            'purchase_unit' => 'pcs',
            'packet_qty' => 6,
            'quantity' => 6,
            'unit_price' => 55,
            'price_basis' => 'per_unit',
        ]);

        $grn = GoodsReceived::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'grn_number' => 'GRN-PER-UNIT-TEST',
            'status' => 'approved',
            'received_by' => $user->id,
            'received_at' => '2026-07-23',
        ]);

        $grn->items()->create([
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'received_qty' => 6,
            'variance' => 0,
        ]);

        app(PriceBoardService::class)->ensurePendingApprovalsForPurchaseDate('2026-07-23');

        $approval = DailyPriceApproval::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame('2026-07-23', $approval->business_date->toDateString());
        $this->assertSame('55.0000', $approval->purchase_price);
        $this->assertSame('pending', $approval->status);
    }
}
