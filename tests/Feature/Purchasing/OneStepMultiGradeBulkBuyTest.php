<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OneStepMultiGradeBulkBuyTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $receiver;

    private Supplier $supplier;

    private Warehouse $warehouse;

    private Shop $shop;

    private Category $category;

    private Product $tomatoH;

    private Product $tomatoN;

    private string $businessDate = '2026-10-31';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow($this->businessDate.' 10:00:00');

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'purchaser']);
        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Role::firstOrCreate(['name' => 'admin']);

        Permission::firstOrCreate(['name' => 'purchasing.order.view']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.view']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.create']);

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Ali']);
        $this->purchaser->assignRole('purchaser');

        $this->receiver = User::factory()->create(['name' => 'Warehouse Receiver John']);
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->givePermissionTo(['purchasing.order.view', 'warehouse.receive.view', 'purchasing.grn.view', 'purchasing.grn.create']);

        $this->supplier = Supplier::factory()->create(['name' => 'Venkatesh Farms', 'credit_approved' => true]);
        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Central Warehouse', 'code' => 'MCW', 'is_active' => true]);
        $this->shop = Shop::factory()->create(['name' => 'Koramangala Green Leaf']);

        $this->category = Category::factory()->create(['name' => 'Vegetables', 'is_active' => true]);

        $this->tomatoH = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Tomato H',
            'sku' => 'VEG-TOM-H',
            'unit' => 'kg',
            'is_active' => true,
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->tomatoN = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Tomato N',
            'sku' => 'VEG-TOM-N',
            'unit' => 'kg',
            'is_active' => true,
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_all_grades_bulk_buy_screen_renders_inline_inputs_and_bulk_store_form(): void
    {
        // 1. Create shop orders with Grade A and Grade B demand for Tomato H
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20261031-01',
            'business_date' => $this->businessDate,
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 10,
            'approved_qty' => 10,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'B',
            'unit' => 'kg',
            'requested_qty' => 20,
            'approved_qty' => 20,
        ]);

        // Single grade demand for Tomato N
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoN->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 15,
            'approved_qty' => 15,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.bulk-buy', [
                'date' => $this->businessDate,
                'purchase_grade' => 'ALL',
            ]));

        $response->assertOk();
        $response->assertSee(route('purchaser.carts.bulk-store'));
        $response->assertSee('name="items['.$this->tomatoH->id.'][A][quantity]"', false);
        $response->assertSee('name="items['.$this->tomatoH->id.'][A][unit_price]"', false);
        $response->assertSee('name="items['.$this->tomatoH->id.'][B][quantity]"', false);
        $response->assertSee('name="items['.$this->tomatoH->id.'][B][unit_price]"', false);
        $response->assertSee('name="items['.$this->tomatoH->id.'][MIXED][quantity]"', false);
        $response->assertSee('name="items['.$this->tomatoN->id.'][A][quantity]"', false);
        $response->assertSee('Add Selected to Cart');
    }

    public function test_multi_grade_lines_can_be_added_to_single_cart_atomically(): void
    {
        $payload = [
            'business_date' => $this->businessDate,
            'purchase_grade' => 'ALL',
            'submission_key' => 'sub-key-123',
            'product_ids' => [$this->tomatoH->id, $this->tomatoN->id],
            'items' => [
                $this->tomatoH->id => [
                    'A' => ['quantity' => 10, 'unit_price' => 35],
                    'B' => ['quantity' => 20, 'unit_price' => 30],
                ],
                $this->tomatoN->id => [
                    'A' => ['quantity' => 5, 'unit_price' => 25],
                    'B' => ['quantity' => 15, 'unit_price' => 20],
                ],
            ],
        ];

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.carts.bulk-store'), $payload);

        $this->assertEquals(1, PurchaserCart::count());
        $cart = PurchaserCart::firstOrFail();

        $response->assertRedirect(route('purchaser.bill', [
            'cart' => $cart->cart_number,
            'date' => $this->businessDate,
        ]));

        $this->assertEquals('ALL', $cart->purchase_grade);
        $this->assertEquals('draft', $cart->status);

        // Verify all 4 lines are created in this single cart
        $this->assertEquals(4, $cart->items()->count());

        $itemHA = $cart->items()->where('product_id', $this->tomatoH->id)->where('grade', 'A')->firstOrFail();
        $this->assertEquals(10.0, (float) $itemHA->quantity);
        $this->assertEquals(35.0, (float) $itemHA->unit_price);
        $this->assertEquals(350.0, (float) $itemHA->line_total);

        $itemHB = $cart->items()->where('product_id', $this->tomatoH->id)->where('grade', 'B')->firstOrFail();
        $this->assertEquals(20.0, (float) $itemHB->quantity);
        $this->assertEquals(30.0, (float) $itemHB->unit_price);
        $this->assertEquals(600.0, (float) $itemHB->line_total);

        $itemNA = $cart->items()->where('product_id', $this->tomatoN->id)->where('grade', 'A')->firstOrFail();
        $this->assertEquals(5.0, (float) $itemNA->quantity);
        $this->assertEquals(25.0, (float) $itemNA->unit_price);
        $this->assertEquals(125.0, (float) $itemNA->line_total);

        $itemNB = $cart->items()->where('product_id', $this->tomatoN->id)->where('grade', 'B')->firstOrFail();
        $this->assertEquals(15.0, (float) $itemNB->quantity);
        $this->assertEquals(20.0, (float) $itemNB->unit_price);
        $this->assertEquals(300.0, (float) $itemNB->line_total);
    }

    public function test_submitting_multi_grade_cart_creates_po_and_grn_lines_with_individual_line_grades(): void
    {
        // 1. Create shop order demands for Tomato H: Grade A (10 kg), Grade B (20 kg)
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20261031-02',
            'business_date' => $this->businessDate,
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 10,
            'approved_qty' => 10,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'B',
            'unit' => 'kg',
            'requested_qty' => 20,
            'approved_qty' => 20,
        ]);

        // 2. Create multi-grade draft cart
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->businessDate,
            'cart_number' => PurchaserCart::generateCartNumber(Carbon::parse($this->businessDate)),
            'status' => 'draft',
            'purchase_grade' => 'ALL',
        ]);

        $itemA = PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->tomatoH->id,
            'grade' => 'A',
            'quantity' => 10,
            'unit_price' => 35,
            'line_total' => 350,
            'unit' => 'kg',
            'is_extra_purchase' => false,
        ]);

        $itemB = PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->tomatoH->id,
            'grade' => 'B',
            'quantity' => 20,
            'unit_price' => 30,
            'line_total' => 600,
            'unit' => 'kg',
            'is_extra_purchase' => false,
        ]);

        // 3. Submit cart with vendor bill details
        $submitPayload = [
            'business_date' => $this->businessDate,
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-VENK-9901',
            'payment_method' => 'Cash',
            'purchase_grade' => 'ALL',
            'items' => [
                $itemA->id => ['quantity' => 10, 'unit_price' => 35],
                $itemB->id => ['quantity' => 20, 'unit_price' => 30],
            ],
        ];

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.carts.submit'), $submitPayload);

        $response->assertRedirect();
        $this->assertEquals('submitted', $cart->fresh()->status);

        // Verify PurchaseOrder lines retain individual grades
        $this->assertEquals(1, PurchaseOrder::count());
        $po = PurchaseOrder::firstOrFail();
        $poItems = PurchaseOrderItem::where('purchase_order_id', $po->id)->get();
        $this->assertCount(2, $poItems);

        $poItemA = $poItems->firstWhere('grade', 'A');
        $this->assertNotNull($poItemA, 'PO item for Grade A must exist');
        $this->assertEquals(10.0, (float) $poItemA->quantity);
        $this->assertEquals(35.0, (float) $poItemA->unit_price);

        $poItemB = $poItems->firstWhere('grade', 'B');
        $this->assertNotNull($poItemB, 'PO item for Grade B must exist and retain Grade B (not overwritten to A)');
        $this->assertEquals(20.0, (float) $poItemB->quantity);
        $this->assertEquals(30.0, (float) $poItemB->unit_price);

        // Verify GoodsReceived lines retain individual grades
        $this->assertEquals(1, GoodsReceived::count());
        $grn = GoodsReceived::firstOrFail();
        $grnItems = GoodsReceivedItem::where('goods_received_id', $grn->id)->get();
        $this->assertCount(2, $grnItems);

        $grnItemA = $grnItems->firstWhere('grade', 'A');
        $this->assertNotNull($grnItemA, 'GRN item for Grade A must exist');
        $this->assertEquals(10.0, (float) $grnItemA->received_qty);

        $grnItemB = $grnItems->firstWhere('grade', 'B');
        $this->assertNotNull($grnItemB, 'GRN item for Grade B must retain Grade B');
        $this->assertEquals(20.0, (float) $grnItemB->received_qty);

        // Verify PurchaseInvoice
        $this->assertEquals(1, PurchaseInvoice::count());
        $invoice = PurchaseInvoice::firstOrFail();
        $this->assertEquals('BILL-VENK-9901', $invoice->invoice_number);
        $this->assertEquals(950.0, (float) $invoice->amount);

        // 4. Test warehouse receiving approval generates distinct Grade A and Grade B batches
        $action = app(ApproveGoodsReceiptAction::class);
        $action->execute($grn, $this->receiver->id);

        $batches = StockBatch::where('product_id', $this->tomatoH->id)->get();
        $this->assertCount(2, $batches);

        $batchA = $batches->firstWhere('purchase_grade', 'A');
        $this->assertNotNull($batchA, 'Stock batch for Grade A must be created');
        $this->assertEquals(10.0, (float) $batchA->total_kg);

        $batchB = $batches->firstWhere('purchase_grade', 'B');
        $this->assertNotNull($batchB, 'Stock batch for Grade B must be created');
        $this->assertEquals(20.0, (float) $batchB->total_kg);
    }

    public function test_vendor_benchmark_prices_only_sync_for_grade_a(): void
    {
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->businessDate,
            'cart_number' => PurchaserCart::generateCartNumber(Carbon::parse($this->businessDate)),
            'status' => 'draft',
            'purchase_grade' => 'ALL',
        ]);

        $itemA = PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->tomatoH->id,
            'grade' => 'A',
            'quantity' => 10,
            'unit_price' => 45,
            'line_total' => 450,
            'unit' => 'kg',
            'is_extra_purchase' => false,
        ]);

        $itemB = PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->tomatoH->id,
            'grade' => 'B',
            'quantity' => 20,
            'unit_price' => 30,
            'line_total' => 600,
            'unit' => 'kg',
            'is_extra_purchase' => false,
        ]);

        $this->actingAs($this->purchaser)
            ->post(route('purchaser.carts.submit'), [
                'business_date' => $this->businessDate,
                'cart_id' => $cart->id,
                'supplier_id' => $this->supplier->id,
                'bill_number' => 'BILL-PRICE-TEST-01',
                'payment_method' => 'Cash',
                'purchase_grade' => 'ALL',
                'items' => [
                    $itemA->id => ['quantity' => 10, 'unit_price' => 45],
                    $itemB->id => ['quantity' => 20, 'unit_price' => 30],
                ],
            ]);

        // product_supplier last_price must record Grade A rate (45), NOT Grade B rate (30)
        $lastPrice = DB::table('product_supplier')
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $this->tomatoH->id)
            ->value('last_price');

        $this->assertEquals(45.0, (float) $lastPrice, 'Vendor price benchmark must reflect Grade A rate only');
    }

    public function test_duplicate_vendor_bill_validation_keeps_cart_in_draft(): void
    {
        // 1. Create an existing invoice with BILL-DUP-01
        $grn = GoodsReceived::factory()->create();

        PurchaseInvoice::create([
            'invoice_number' => 'BILL-DUP-01',
            'supplier_id' => $this->supplier->id,
            'goods_received_id' => $grn->id,
            'invoice_date' => $this->businessDate,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'amount' => 100,
            'paid_amount' => 0,
        ]);

        // 2. Draft cart attempting to use the same bill number
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->businessDate,
            'cart_number' => PurchaserCart::generateCartNumber(Carbon::parse($this->businessDate)),
            'status' => 'draft',
            'purchase_grade' => 'ALL',
        ]);

        $itemA = PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->tomatoH->id,
            'grade' => 'A',
            'quantity' => 10,
            'unit_price' => 35,
            'line_total' => 350,
            'unit' => 'kg',
            'is_extra_purchase' => false,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.carts.submit'), [
                'business_date' => $this->businessDate,
                'cart_id' => $cart->id,
                'supplier_id' => $this->supplier->id,
                'bill_number' => 'BILL-DUP-01',
                'payment_method' => 'Cash',
                'purchase_grade' => 'ALL',
                'items' => [
                    $itemA->id => ['quantity' => 10, 'unit_price' => 35],
                ],
            ]);

        $response->assertSessionHasErrors(['bill_number']);
        $this->assertEquals('draft', $cart->fresh()->status, 'Cart must remain in draft and not drop items');
    }

    public function test_adding_to_existing_cart_accumulates_quantity_and_updates_price_for_same_grade(): void
    {
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->businessDate,
            'cart_number' => PurchaserCart::generateCartNumber(Carbon::parse($this->businessDate)),
            'status' => 'draft',
            'purchase_grade' => 'ALL',
        ]);

        // Existing Grade A: 10 kg @ ₹30
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->tomatoH->id,
            'grade' => 'A',
            'quantity' => 10,
            'unit_price' => 30,
            'line_total' => 300,
            'unit' => 'kg',
        ]);

        // Add 10 kg @ ₹40 to the same cart
        $payload = [
            'business_date' => $this->businessDate,
            'cart_id' => $cart->id,
            'purchase_grade' => 'ALL',
            'items' => [
                $this->tomatoH->id => [
                    'A' => ['quantity' => 10, 'unit_price' => 40],
                ],
            ],
        ];

        $this->actingAs($this->purchaser)
            ->post(route('purchaser.carts.bulk-store'), $payload);

        $cartItems = $cart->items()->where('product_id', $this->tomatoH->id)->where('grade', 'A')->get();
        $this->assertCount(1, $cartItems, 'Should update existing item, not duplicate composite key');

        $item = $cartItems->first();
        $this->assertEquals(20.0, (float) $item->quantity);
        $this->assertEquals(40.0, (float) $item->unit_price);
        $this->assertEquals(800.0, (float) $item->line_total);
    }
}
