<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\Client;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Purchasing\DamageLossReportService;
use App\Services\Purchasing\PurchaserBillsNotReceivedService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsV1DamageLossReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Category $category;

    private Shop $shop;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow('2026-10-23');

        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@greenleaf.test']);
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'code' => 'VEG',
        ]);

        $this->client = Client::create([
            'name' => 'Apex Retail Group',
            'code' => 'APEX',
            'status' => 'active',
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Apex Downtown',
            'code' => 'APX-01',
            'client_id' => $this->client->id,
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createProduct(string $name = 'Tomato', string $unit = 'kg'): Product
    {
        return Product::create([
            'name' => $name,
            'sku' => strtoupper(substr($name, 0, 3)).'-'.rand(100, 999),
            'unit' => $unit,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);
    }

    private function createBatch(
        Product $product,
        string $grade,
        float $quantity,
        float $costPerKg,
        string $receivedAt = '2026-10-23',
        ?Warehouse $warehouse = null
    ): StockBatch {
        $wh = $warehouse ?? $this->warehouse;

        return StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'created_by' => $this->admin->id,
            'reference' => 'BAT-'.rand(1000, 9999),
            'purchase_grade' => $grade,
            'received_at' => $receivedAt,
            'total_kg' => $quantity,
            'cost_per_kg' => $costPerKg,
            'status' => $grade === 'U' ? BatchStatus::Pending : BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);
    }

    public function test_sidebar_includes_damage_loss_report_under_reports_v1(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));
        $response->assertOk();
        $response->assertSee(route('admin.cashbook.reports-v1.damage-loss'));
        $response->assertSee('Damage &amp; Loss Report', false);
    }

    public function test_damage_loss_report_requires_authorization(): void
    {
        $unauthorized = User::factory()->create();

        $response = $this->actingAs($unauthorized)->get(route('admin.cashbook.reports-v1.damage-loss'));
        $this->assertTrue(in_array($response->status(), [302, 403], true));
    }

    public function test_selling_below_cost_calculation(): void
    {
        // Purchase: ₹40/kg, Actual Selling: ₹28/kg, Qty: 30 kg -> Loss: ₹12/kg = ₹360
        $product = $this->createProduct('Carrot');
        $batch = $this->createBatch($product, 'A', 50.0, 40.00);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-23',
        ]);

        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'loaded_qty' => 30.0,
            'line_total' => 840.00,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $orderItem->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 30.0,
            'cost_per_unit' => 40.00,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-23',
            'status' => 'finalized',
            'subtotal' => 840.00,
            'final_total' => 840.00,
            'discount_total' => 0.00,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'Carrot',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 30.0,
            'unit_price' => 28.00,
            'final_line_total' => 840.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Selling below cost: ₹360.00
        $response->assertSee('360.00');
        $response->assertSee('Carrot');
        $response->assertSee('30.0');
    }

    public function test_profitable_sale_does_not_create_selling_loss(): void
    {
        // Purchase: ₹40/kg, Actual Selling: ₹55/kg -> Profit (Loss = 0)
        $product = $this->createProduct('Capsicum');
        $batch = $this->createBatch($product, 'A', 50.0, 40.00);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-23',
        ]);

        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'loaded_qty' => 20.0,
            'line_total' => 1100.00,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $orderItem->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 20.0,
            'cost_per_unit' => 40.00,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-23',
            'status' => 'finalized',
            'subtotal' => 1100.00,
            'final_total' => 1100.00,
            'discount_total' => 0.00,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'Capsicum',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 20.0,
            'unit_price' => 55.00,
            'final_line_total' => 1100.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Total selling loss must be 0.00
        $response->assertSee('₹0.00');
    }

    public function test_physical_damage_loss_reporting(): void
    {
        $product = $this->createProduct('Onion');
        $batch = $this->createBatch($product, 'A', 100.0, 20.00);

        // Record physical damage of 15 kg at ₹20 = ₹300
        WastageEntry::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'recorded_by' => $this->admin->id,
            'grade' => 'A',
            'quantity' => 15.0,
            'cost_per_kg' => 20.00,
            'reason' => WastageReason::Rotten,
            'wastage_date' => '2026-10-23',
            'notes' => 'Rotten stock discovered during morning inspection',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Unsellable damage = ₹300.00
        $response->assertSee('300.00');
        $response->assertSee('15.0');
        // Total loss must also equal ₹300.00
        $response->assertSee('300.00');
    }

    public function test_total_loss_combines_selling_loss_and_physical_damage(): void
    {
        $product = $this->createProduct('Potato');
        $batch = $this->createBatch($product, 'A', 100.0, 30.00);

        // 1. Selling loss: 20 kg sold @ ₹22 (cost ₹30 -> loss ₹8/kg = ₹160)
        $order = ShopOrder::factory()->create(['shop_id' => $this->shop->id, 'business_date' => '2026-10-23']);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'loaded_qty' => 20.0,
            'line_total' => 440.00,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $orderItem->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 20.0,
            'cost_per_unit' => 30.00,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-23',
            'status' => 'finalized',
            'subtotal' => 440.00,
            'final_total' => 440.00,
            'discount_total' => 0.00,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'Potato',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 20.0,
            'unit_price' => 22.00,
            'final_line_total' => 440.00,
        ]);

        // 2. Physical damage: 10 kg damaged @ ₹30 = ₹300
        WastageEntry::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'recorded_by' => $this->admin->id,
            'grade' => 'A',
            'quantity' => 10.0,
            'cost_per_kg' => 30.00,
            'reason' => WastageReason::TransitDamage,
            'wastage_date' => '2026-10-23',
            'notes' => 'Crushed in transit',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Selling Loss: ₹160.00
        $response->assertSee('160.00');
        // Damage Loss: ₹300.00
        $response->assertSee('300.00');
        // Total Loss: 160 + 300 = ₹460.00
        $response->assertSee('460.00');
    }

    public function test_damage_reversal_decreases_physical_loss(): void
    {
        $product = $this->createProduct('Apple');
        $batch = $this->createBatch($product, 'A', 50.0, 50.00);

        // 10 kg damaged
        $wastage = WastageEntry::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'recorded_by' => $this->admin->id,
            'grade' => 'A',
            'quantity' => 10.0,
            'cost_per_kg' => 50.00,
            'reason' => WastageReason::Rotten,
            'wastage_date' => '2026-10-23',
            'notes' => 'Damaged entry to reverse',
        ]);

        // Soft-delete the wastage entry (representing full reversal)
        $wastage->delete();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Loss should be 0 since the entry was reversed
        $response->assertSee('₹0.00');
    }

    public function test_invoice_discounts_reduce_net_selling_rate(): void
    {
        // Purchase cost: ₹50/kg
        // Billed unit price: ₹50/kg for 10 kg = ₹500 line subtotal
        // Overall invoice discount: ₹100 on ₹500 bill -> Net realized selling rate is ₹40/kg
        // Selling loss: ₹10/kg for 10 kg = ₹100
        $product = $this->createProduct('Garlic');
        $batch = $this->createBatch($product, 'A', 50.0, 50.00);

        $order = ShopOrder::factory()->create(['shop_id' => $this->shop->id, 'business_date' => '2026-10-23']);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'line_total' => 500.00,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $orderItem->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.0,
            'cost_per_unit' => 50.00,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-23',
            'status' => 'finalized',
            'subtotal' => 500.00,
            'discount_total' => 100.00,
            'final_total' => 400.00,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'Garlic',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 10.0,
            'unit_price' => 50.00,
            'final_line_total' => 500.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Loss: ₹100.00
        $response->assertSee('100.00');
    }

    public function test_negative_stock_dispatch_marked_pending_cost_reconciliation(): void
    {
        $product = $this->createProduct('Ginger');

        $negBatch = StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'reference' => 'NEG-20261023-'.$product->id.'-A',
            'purchase_grade' => 'A',
            'received_at' => '2026-10-23',
            'total_kg' => 0,
            'cost_per_kg' => 0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);

        $order = ShopOrder::factory()->create(['shop_id' => $this->shop->id, 'business_date' => '2026-10-23']);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'loaded_qty' => 15.0,
            'line_total' => 300.00,
        ]);

        StockMovement::create([
            'batch_id' => $negBatch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $orderItem->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 15.0,
            'cost_per_unit' => 0.00,
            'notes' => 'Fast dispatch shortfall; negative stock allowed',
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-23',
            'status' => 'finalized',
            'subtotal' => 300.00,
            'final_total' => 300.00,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'Ginger',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 15.0,
            'unit_price' => 20.00,
            'final_line_total' => 300.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertSee('Pending Cost');
    }

    public function test_cancelled_invoice_is_excluded_from_selling_loss(): void
    {
        $product = $this->createProduct('Banana');
        $batch = $this->createBatch($product, 'A', 50.0, 30.00);

        $order = ShopOrder::factory()->create(['shop_id' => $this->shop->id, 'business_date' => '2026-10-23']);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'line_total' => 150.00,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $orderItem->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.0,
            'cost_per_unit' => 30.00,
        ]);

        // Cancelled invoice
        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-23',
            'status' => 'cancelled',
            'subtotal' => 150.00,
            'final_total' => 150.00,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'Banana',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 10.0,
            'unit_price' => 15.00,
            'final_line_total' => 150.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Since invoice is cancelled, loss is 0
        $response->assertSee('₹0.00');
    }

    public function test_client_attribution_and_unallocated_warehouse_damage_separation(): void
    {
        $product = $this->createProduct('Orange');
        $batch = $this->createBatch($product, 'A', 100.0, 40.00);

        // 1. General warehouse sorting damage (Unallocated Warehouse Damage)
        WastageEntry::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'recorded_by' => $this->admin->id,
            'grade' => 'A',
            'quantity' => 8.0,
            'cost_per_kg' => 40.00,
            'reason' => WastageReason::SortingDamage,
            'wastage_date' => '2026-10-23',
            'notes' => 'Sorting damage in warehouse',
        ]);

        // 2. Delivery review wastage for this shop
        $order = ShopOrder::factory()->create(['shop_id' => $this->shop->id, 'order_number' => 'ORD-9876']);
        WastageEntry::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'recorded_by' => $this->admin->id,
            'grade' => 'A',
            'quantity' => 2.0,
            'cost_per_kg' => 40.00,
            'reason' => WastageReason::TransitDamage,
            'wastage_date' => '2026-10-23',
            'notes' => 'Delivery review wastage - Order: ORD-9876; Item: 1',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss', [
            'product' => $product->id,
            'tab' => 'client_shop',
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Unallocated Warehouse Damage: 8 kg * 40 = ₹320.00
        $response->assertSee('320.00');
        // Shop Attributable Delivery Damage: 2 kg * 40 = ₹80.00
        $response->assertSee('80.00');
        $response->assertSee($this->shop->name);
    }

    public function test_commercial_sale_below_cost_calculates_selling_loss_without_physical_damage(): void
    {
        // Purchase cost ₹40/kg (Mixed source batch)
        $product = $this->createProduct('Cabbage');
        $batch = $this->createBatch($product, 'U', 100.0, 40.00);

        // Sold as Grade C @ ₹15/kg for 20 kg
        // Loss = (40 - 15) * 20 = ₹500 selling loss
        // Unsellable damage = ₹0
        $order = ShopOrder::factory()->create(['shop_id' => $this->shop->id, 'business_date' => '2026-10-23']);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'C',
            'unit' => 'kg',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'loaded_qty' => 20.0,
            'line_total' => 300.00,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $orderItem->id,
            'created_by' => $this->admin->id,
            'grade' => 'C',
            'type' => StockMovementType::Out->value,
            'quantity' => 20.0,
            'cost_per_unit' => 40.00,
            'notes' => 'Commercial sale of Grade C produce below cost',
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-23',
            'status' => 'finalized',
            'subtotal' => 300.00,
            'final_total' => 300.00,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'Cabbage',
            'product_grade' => 'C',
            'unit' => 'kg',
            'delivered_qty' => 20.0,
            'unit_price' => 15.00,
            'final_line_total' => 300.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Selling Loss: ₹500.00
        $response->assertSee('500.00');
        // Unsellable damage should be ₹0.00 (not confused with commercial sale)
        $this->assertDatabaseMissing('wastage_entries', [
            'product_id' => $product->id,
        ]);
    }

    public function test_grade_d_is_not_a_valid_commercial_grade_and_rejected(): void
    {
        // ProductGrade does not contain GradeD
        $this->assertNull(ProductGrade::tryFrom('D'));

        // Grade D is not in commercial grades list
        $commercialValues = array_map(fn ($g) => $g->value, ProductGrade::commercialGrades());
        $this->assertSame(['A', 'B', 'C'], $commercialValues);
        $this->assertNotContains('D', $commercialValues);

        // Segregating into Grade D is rejected by validation
        $product = $this->createProduct('Turnip');
        $batch = $this->createBatch($product, 'U', 50.0, 20.00);

        $response = $this->actingAs($this->admin)->post(route('inventory.batches.segregate', $batch), [
            'target_grade' => 'D',
            'quantity' => 10.0,
        ]);

        $response->assertSessionHasErrors('target_grade');
    }

    public function test_daily_reconciles_strictly_with_monthly_totals(): void
    {
        $product = $this->createProduct('Broccoli');
        $batch = $this->createBatch($product, 'A', 100.0, 50.00);

        // Day 1 (2026-10-21): 10 kg sold @ ₹40 -> ₹100 loss
        $order1 = ShopOrder::factory()->create(['shop_id' => $this->shop->id, 'business_date' => '2026-10-21']);
        $item1 = ShopOrderItem::create([
            'shop_order_id' => $order1->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'line_total' => 400.00,
        ]);
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $item1->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.0,
            'cost_per_unit' => 50.00,
            'created_at' => '2026-10-21 10:00:00',
        ]);
        $inv1 = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-21',
            'status' => 'finalized',
            'subtotal' => 400.00,
            'final_total' => 400.00,
        ]);
        ShopInvoiceItem::create([
            'shop_invoice_id' => $inv1->id,
            'shop_order_item_id' => $item1->id,
            'product_id' => $product->id,
            'product_name' => 'Broccoli',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 10.0,
            'unit_price' => 40.00,
            'final_line_total' => 400.00,
        ]);

        // Day 2 (2026-10-22): 5 kg damaged @ ₹50 -> ₹250 damage loss
        WastageEntry::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'recorded_by' => $this->admin->id,
            'grade' => 'A',
            'quantity' => 5.0,
            'cost_per_kg' => 50.00,
            'reason' => WastageReason::Rotten,
            'wastage_date' => '2026-10-22',
            'notes' => 'Rotten Day 2',
        ]);

        $service = app(DamageLossReportService::class);
        $data = $service->getReportData([
            'product' => $product->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $daily = $data['dailySummary'];
        $monthly = $data['monthlySummary'];

        $sumDailySellingLoss = $daily->sum('selling_loss');
        $sumDailyDamageLoss = $daily->sum('damage_cost');
        $sumDailyTotalLoss = $daily->sum('total_loss');

        $sumMonthlySellingLoss = $monthly->sum('selling_loss');
        $sumMonthlyDamageLoss = $monthly->sum('damage_cost');
        $sumMonthlyTotalLoss = $monthly->sum('total_loss');

        $this->assertEquals($sumMonthlySellingLoss, $sumDailySellingLoss);
        $this->assertEquals($sumMonthlyDamageLoss, $sumDailyDamageLoss);
        $this->assertEquals($sumMonthlyTotalLoss, $sumDailyTotalLoss);
        $this->assertEquals(100.00, $sumMonthlySellingLoss);
        $this->assertEquals(250.00, $sumMonthlyDamageLoss);
        $this->assertEquals(350.00, $sumMonthlyTotalLoss);
    }

    public function test_damage_loss_report_renders_purchaser_bills_not_received_card_and_navigates_to_pending_bills(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss'));

        $response->assertOk();
        $response->assertSee('Purchaser Bills Not Received');
        $response->assertSee(route('admin.cashbook.reports-v1.pending-bills'));
        $response->assertSee('Awaiting Receiving:');
        $response->assertSee('Manage pending bills');
    }

    public function test_purchaser_bills_not_received_service_computes_correct_outstanding_and_statuses(): void
    {
        $purchaserUser = User::factory()->create(['name' => 'Test Purchaser']);
        $supplier = Supplier::factory()->create(['name' => 'Green Valley Farms']);
        $product = $this->createProduct('Carrot');

        // 1. Pending Bill: 100 kg billed, 0 received, 0 matched -> 100 kg outstanding, Not Received
        $grn1 = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-001',
            'receipt_type' => 'purchaser',
            'status' => 'pending',
            'received_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn1->id,
            'product_id' => $product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn1->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
            'total_kg' => 100.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-TEST-001',
            'cost_per_kg' => 20.0,
        ]);
        $inv1 = PurchaseInvoice::create([
            'goods_received_id' => $grn1->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'BILL-PENDING-001',
            'amount' => 2000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $purchaserUser->id,
        ]);

        // 2. Partially Received Bill: 100 kg billed, 40 kg confirmed, 60 kg pending -> Partially Received
        $grn2 = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-002',
            'receipt_type' => 'purchaser',
            'status' => 'pending',
            'received_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn2->id,
            'product_id' => $product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn2->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
            'total_kg' => 40.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-TEST-002A',
            'cost_per_kg' => 20.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn2->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
            'total_kg' => 60.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-TEST-002B',
            'cost_per_kg' => 20.0,
        ]);
        $inv2 = PurchaseInvoice::create([
            'goods_received_id' => $grn2->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'BILL-PARTIAL-002',
            'amount' => 2000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $purchaserUser->id,
        ]);

        // 3. Fully Received Bill: 80 kg billed, 80 kg confirmed -> Excluded
        $grn3 = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-003',
            'receipt_type' => 'purchaser',
            'status' => 'completed',
            'received_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn3->id,
            'product_id' => $product->id,
            'received_qty' => 80.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn3->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
            'total_kg' => 80.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'reference' => 'BAT-TEST-003',
            'cost_per_kg' => 20.0,
        ]);
        $inv3 = PurchaseInvoice::create([
            'goods_received_id' => $grn3->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'BILL-COMPLETED-003',
            'amount' => 1600.0,
            'status' => 'approved',
            'purchaser_submitted_by' => $purchaserUser->id,
        ]);

        // 4. Cancelled Bill -> Excluded
        $grn4 = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-004',
            'receipt_type' => 'purchaser',
            'status' => 'cancelled',
            'received_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
        ]);
        $inv4 = PurchaseInvoice::create([
            'goods_received_id' => $grn4->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'BILL-CANCELLED-004',
            'amount' => 1000.0,
            'status' => 'cancelled',
            'purchaser_submitted_by' => $purchaserUser->id,
        ]);

        // 5. Fully Advance-Matched Bill: 100 kg billed, 100 kg advance-matched -> Excluded
        $advanceGrn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-001',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
            'received_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
        ]);

        $grn5 = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-005',
            'receipt_type' => 'purchaser',
            'status' => 'pending',
            'received_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn5->id,
            'product_id' => $product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn5->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
            'total_kg' => 100.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-TEST-005',
            'cost_per_kg' => 20.0,
        ]);
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advanceGrn->id,
            'bill_goods_received_id' => $grn5->id,
            'base_qty' => 100.0,
            'matched_qty' => 100.0,
            'matched_unit' => 'kg',
            'product_id' => $product->id,
            'confirmed_at' => now(),
            'confirmed_by' => $this->admin->id,
        ]);
        $inv5 = PurchaseInvoice::create([
            'goods_received_id' => $grn5->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'BILL-MATCHED-005',
            'amount' => 2000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $purchaserUser->id,
        ]);

        // 6. Partially Advance-Matched Bill: 100 kg billed, 30 kg advance-matched -> 70 kg pending, Partially Received
        $grn6 = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-006',
            'receipt_type' => 'purchaser',
            'status' => 'pending',
            'received_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn6->id,
            'product_id' => $product->id,
            'received_qty' => 100.0,
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'goods_received_id' => $grn6->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
            'total_kg' => 100.0,
            'warehouse_receive_pending' => true,
            'status' => BatchStatus::Pending,
            'purchase_grade' => 'A',
            'reference' => 'BAT-TEST-006',
            'cost_per_kg' => 20.0,
        ]);
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advanceGrn->id,
            'bill_goods_received_id' => $grn6->id,
            'base_qty' => 30.0,
            'matched_qty' => 30.0,
            'matched_unit' => 'kg',
            'product_id' => $product->id,
            'confirmed_at' => now(),
            'confirmed_by' => $this->admin->id,
        ]);
        $inv6 = PurchaseInvoice::create([
            'goods_received_id' => $grn6->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'BILL-PARTIAL-MATCH-006',
            'amount' => 2000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $purchaserUser->id,
        ]);

        // 7. Warehouse Bill Without Stock Batches Yet: 50 kg billed, 0 batches -> 50 kg pending, Included as Not Received
        $grn7 = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-007',
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'received_by' => $this->admin->id,
            'received_at' => now()->format('Y-m-d'),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn7->id,
            'product_id' => $product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
        ]);
        $inv7 = PurchaseInvoice::create([
            'goods_received_id' => $grn7->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'BILL-NO-BATCHES-007',
            'amount' => 1000.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $purchaserUser->id,
        ]);

        // 8. Direct Shop Vendor Credit Purchase (using SAME vendor): -> Excluded
        $shopCartCredit = PurchaserCart::create([
            'cart_number' => 'CART-CREDIT-008',
            'user_id' => $purchaserUser->id,
            'supplier_id' => $supplier->id,
            'destination_shop_id' => $this->shop->id,
            'business_date' => now()->format('Y-m-d'),
            'status' => 'submitted',
            'purchase_source' => 'shop',
            'payment_method' => 'Credit',
            'payment_status' => 'pending',
        ]);
        $shopPoCredit = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'destination_shop_id' => $this->shop->id,
            'purchaser_cart_id' => $shopCartCredit->id,
            'po_number' => 'SPO-CREDIT-008',
            'status' => 'received',
            'fulfillment_type' => 'shop',
            'order_date' => now()->format('Y-m-d'),
            'created_by' => $purchaserUser->id,
        ]);
        $shopGrnCredit = GoodsReceived::create([
            'purchase_order_id' => $shopPoCredit->id,
            'purchaser_cart_id' => $shopCartCredit->id,
            'grn_number' => 'SGRN-CREDIT-008',
            'status' => 'approved',
            'receipt_type' => 'normal_purchase',
            'received_by' => $purchaserUser->id,
            'received_at' => now()->format('Y-m-d'),
            'notes' => 'Shop Goods Received',
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $shopGrnCredit->id,
            'product_id' => $product->id,
            'received_qty' => 35.0,
            'variance' => 0.0,
        ]);
        $invCreditShop = PurchaseInvoice::create([
            'goods_received_id' => $shopGrnCredit->id,
            'supplier_id' => $supplier->id,
            'shop_id' => $this->shop->id,
            'purchaser_cart_id' => $shopCartCredit->id,
            'purchase_source' => 'shop',
            'invoice_number' => 'SPINV-CREDIT-SHOP-008',
            'amount' => 700.0,
            'status' => 'pending',
            'payment_method' => 'Credit',
            'payment_paid_by' => 'vendor_credit',
            'purchaser_submitted_by' => $purchaserUser->id,
        ]);

        // 9. Direct Shop Cash Purchase (using SAME vendor): -> Excluded
        $shopCartCash = PurchaserCart::create([
            'cart_number' => 'CART-CASH-009',
            'user_id' => $purchaserUser->id,
            'supplier_id' => $supplier->id,
            'destination_shop_id' => $this->shop->id,
            'business_date' => now()->format('Y-m-d'),
            'status' => 'submitted',
            'purchase_source' => 'shop',
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
        ]);
        $shopPoCash = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'destination_shop_id' => $this->shop->id,
            'purchaser_cart_id' => $shopCartCash->id,
            'po_number' => 'SPO-CASH-009',
            'status' => 'received',
            'fulfillment_type' => 'shop',
            'order_date' => now()->format('Y-m-d'),
            'created_by' => $purchaserUser->id,
        ]);
        $shopGrnCash = GoodsReceived::create([
            'purchase_order_id' => $shopPoCash->id,
            'purchaser_cart_id' => $shopCartCash->id,
            'grn_number' => 'SGRN-CASH-009',
            'status' => 'approved',
            'receipt_type' => 'normal_purchase',
            'received_by' => $purchaserUser->id,
            'received_at' => now()->format('Y-m-d'),
            'notes' => 'Shop Goods Received',
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $shopGrnCash->id,
            'product_id' => $product->id,
            'received_qty' => 25.0,
            'variance' => 0.0,
        ]);
        $invCashShop = PurchaseInvoice::create([
            'goods_received_id' => $shopGrnCash->id,
            'supplier_id' => $supplier->id,
            'shop_id' => $this->shop->id,
            'purchaser_cart_id' => $shopCartCash->id,
            'purchase_source' => 'shop',
            'invoice_number' => 'SPINV-CASH-SHOP-009',
            'amount' => 500.0,
            'status' => 'paid',
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'purchaser_submitted_by' => $purchaserUser->id,
        ]);

        // Compute pending bills
        $service = app(PurchaserBillsNotReceivedService::class);
        $result = $service->computePendingBillsData();

        // Eligible bills:
        // 1. BILL-PENDING-001 (100 kg)
        // 2. BILL-PARTIAL-002 (60 kg)
        // 3. BILL-PARTIAL-MATCH-006 (70 kg)
        // 4. BILL-NO-BATCHES-007 (50 kg)
        // Total expected count: 4, Total outstanding: 280 kg
        $this->assertEquals(4, $result['count']);
        $this->assertEquals(280.0, $result['total_outstanding_qty']); // 100 + 60 + 70 + 50

        $billNumbers = array_column($result['bills'], 'bill_number');
        $this->assertContains('BILL-PENDING-001', $billNumbers);
        $this->assertContains('BILL-PARTIAL-002', $billNumbers);
        $this->assertContains('BILL-PARTIAL-MATCH-006', $billNumbers);
        $this->assertContains('BILL-NO-BATCHES-007', $billNumbers);

        // Verify exclusions
        $this->assertNotContains('BILL-COMPLETED-003', $billNumbers);
        $this->assertNotContains('BILL-CANCELLED-004', $billNumbers);
        $this->assertNotContains('BILL-MATCHED-005', $billNumbers);
        $this->assertNotContains('SPINV-CREDIT-SHOP-008', $billNumbers);
        $this->assertNotContains('SPINV-CASH-SHOP-009', $billNumbers);

        $pendingBill = collect($result['bills'])->firstWhere('bill_number', 'BILL-PENDING-001');
        $this->assertEquals('Not Received', $pendingBill['status']);
        $this->assertEquals(100.0, $pendingBill['outstanding_qty']);
        $this->assertEquals('Test Purchaser', $pendingBill['purchaser']);

        $partialBill = collect($result['bills'])->firstWhere('bill_number', 'BILL-PARTIAL-002');
        $this->assertEquals('Partially Received', $partialBill['status']);
        $this->assertEquals(60.0, $partialBill['outstanding_qty']);

        $partialMatchBill = collect($result['bills'])->firstWhere('bill_number', 'BILL-PARTIAL-MATCH-006');
        $this->assertEquals('Partially Received', $partialMatchBill['status']);
        $this->assertEquals(70.0, $partialMatchBill['outstanding_qty']);

        $noBatchBill = collect($result['bills'])->firstWhere('bill_number', 'BILL-NO-BATCHES-007');
        $this->assertEquals('Not Received', $noBatchBill['status']);
        $this->assertEquals(50.0, $noBatchBill['outstanding_qty']);

        // Verify Damage & Loss report renders the card with correct count and outstanding kg
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.damage-loss'));
        $response->assertOk();
        $response->assertSee('Purchaser Bills Not Received');
        $response->assertSee('280.0 kg');
        $response->assertSee(route('admin.cashbook.reports-v1.pending-bills'));

        // Verify Pending Warehouse Bills management page renders the bills
        $pendingPage = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.pending-bills'));
        $pendingPage->assertOk();
        $pendingPage->assertSee('BILL-PENDING-001');
        $pendingPage->assertSee('BILL-PARTIAL-002');
        $pendingPage->assertSee('BILL-PARTIAL-MATCH-006');
        $pendingPage->assertSee('BILL-NO-BATCHES-007');
        $pendingPage->assertDontSee('SPINV-CREDIT-SHOP-008');
        $pendingPage->assertDontSee('SPINV-CASH-SHOP-009');
        $pendingPage->assertDontSee('BILL-COMPLETED-003');
        $pendingPage->assertDontSee('BILL-CANCELLED-004');
    }
}
