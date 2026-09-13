<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Purchasing\POStatus;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\GoodsReceivedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NormalPurchaseGrnUnitPreservationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $receiver;

    private Supplier $supplier;

    private Category $category;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'purchaser']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchase']);
        Role::firstOrCreate(['name' => 'warehouse_receiver']);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');

        $this->supplier = Supplier::factory()->create(['credit_approved' => true]);
        $this->category = Category::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
    }

    public function test_purchaser_cart_split_into_main_and_addon_pos_preserves_box_unit_on_both_grns(): void
    {
        $today = now()->format('Y-m-d');

        $productBox = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Anar / Pomegranate',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $shop = Shop::factory()->create();
        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $today,
            'state' => 'approved',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $productBox->id,
            'unit' => 'box',
            'approved_qty' => 4.000,
            'product_grade' => 'A',
            'suggested_qty' => 4.000,
            'requested_qty' => 4.000,
        ]);

        $cart = PurchaserCart::query()->create([
            'cart_number' => 'CART-BOX-SPLIT-01',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'draft',
            'payment_method' => 'Cash',
            'purchase_source' => 'shop_order',
        ]);

        $cartItem = PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $productBox->id,
            'quantity' => 5.000,
            'unit_price' => 1450.00,
            'line_total' => 7250.00,
            'grade' => 'A',
        ]);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'cart_id' => $cart->id,
            'business_date' => $today,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 7250.00,
            'bill_number' => 'BILL-12345',
            'items' => [
                $cartItem->id => [
                    'unit_price' => 1450.00,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $pos = PurchaseOrder::where('purchaser_cart_id', $cart->id)->orderBy('id')->get();
        $this->assertCount(2, $pos);

        $mainPo = $pos[0];
        $addonPo = $pos[1];

        $this->assertEquals(4.000, (float) $mainPo->items()->first()->quantity);
        $this->assertEquals('box', $mainPo->items()->first()->purchase_unit);

        $this->assertEquals(1.000, (float) $addonPo->items()->first()->quantity);
        $this->assertEquals('box', $addonPo->items()->first()->purchase_unit);

        $mainGrn = GoodsReceived::where('purchase_order_id', $mainPo->id)->firstOrFail();
        $addonGrn = GoodsReceived::where('purchase_order_id', $addonPo->id)->firstOrFail();

        $mainGrnItem = $mainGrn->items()->firstOrFail();
        $addonGrnItem = $addonGrn->items()->firstOrFail();

        $this->assertEquals(4.000, (float) $mainGrnItem->received_qty);
        $this->assertEquals('box', $mainGrnItem->received_unit, 'Main GRN unit must be box');

        $this->assertEquals(1.000, (float) $addonGrnItem->received_qty);
        $this->assertEquals('box', $addonGrnItem->received_unit, 'Add-on GRN unit must be box');
    }

    public function test_piece_unit_po_creates_piece_grn_item(): void
    {
        $productPiece = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Banana Leaf',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $po = PurchaseOrder::query()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-PIECE-01',
            'status' => POStatus::SentToSupplier,
            'fulfillment_type' => 'warehouse',
            'order_date' => now(),
            'created_by' => $this->purchaser->id,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $productPiece->id,
            'purchase_unit' => 'piece',
            'quantity' => 85.000,
            'unit_price' => 5.00,
            'price_basis' => 'per_unit',
        ]);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: now()->format('Y-m-d'),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Testing Piece Unit',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $productPiece->id,
                    'received_qty' => 85.000,
                ],
            ]
        );

        $action = app(RecordGoodsReceiptAction::class);
        $grn = $action->execute($dto, (int) $this->receiver->id);

        $grnItem = $grn->items()->firstOrFail();
        $this->assertEquals(85.000, (float) $grnItem->received_qty);
        $this->assertEquals('piece', $grnItem->received_unit, 'GRN received_unit must inherit PO purchase_unit piece');
    }

    public function test_kg_unit_po_creates_kg_grn_item(): void
    {
        $productKg = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Tomato',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $po = PurchaseOrder::query()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-KG-01',
            'status' => POStatus::SentToSupplier,
            'fulfillment_type' => 'warehouse',
            'order_date' => now(),
            'created_by' => $this->purchaser->id,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $productKg->id,
            'purchase_unit' => 'kg',
            'quantity' => 10.000,
            'unit_price' => 20.00,
            'price_basis' => 'per_kg',
        ]);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: now()->format('Y-m-d'),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Testing KG Unit',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $productKg->id,
                    'received_qty' => 10.000,
                ],
            ]
        );

        $action = app(RecordGoodsReceiptAction::class);
        $grn = $action->execute($dto, (int) $this->receiver->id);

        $grnItem = $grn->items()->firstOrFail();
        $this->assertEquals(10.000, (float) $grnItem->received_qty);
        $this->assertEquals('kg', $grnItem->received_unit, 'GRN received_unit must be kg');
    }

    public function test_goods_received_service_preserves_purchase_order_unit(): void
    {
        $productBox = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Apple',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $po = PurchaseOrder::query()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-SRV-01',
            'status' => POStatus::SentToSupplier,
            'fulfillment_type' => 'warehouse',
            'order_date' => now(),
            'created_by' => $this->purchaser->id,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $productBox->id,
            'purchase_unit' => 'box',
            'quantity' => 15.000,
            'unit_price' => 1200.00,
            'price_basis' => 'per_unit',
        ]);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: now()->format('Y-m-d'),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Testing GoodsReceivedService PO unit',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $productBox->id,
                    'received_qty' => 15.000,
                ],
            ]
        );

        $service = app(GoodsReceivedService::class);
        $grn = $service->create($dto, (int) $this->receiver->id);

        $grnItem = $grn->items()->firstOrFail();
        $this->assertEquals(15.000, (float) $grnItem->received_qty);
        $this->assertEquals('box', $grnItem->received_unit, 'GoodsReceivedService must inherit PO purchase_unit box');
    }
}
