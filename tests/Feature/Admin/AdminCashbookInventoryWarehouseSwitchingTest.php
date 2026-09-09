<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Purchasing\POStatus;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCashbookInventoryWarehouseSwitchingTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Warehouse $vegetableWarehouse;

    private Warehouse $fruitWarehouse;

    private Category $category;

    private Product $tomato;

    private Product $apple;

    private Supplier $supplier;

    private Shop $shop;

    private Shop $shopB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->vegetableWarehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'WH-VEG',
            'is_active' => true,
        ]);

        $this->fruitWarehouse = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'WH-FRUIT',
            'is_active' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Main Retail Shop',
            'code' => 'SH-MAIN',
        ]);

        $this->shopB = Shop::factory()->create([
            'name' => 'Secondary Retail Shop',
            'code' => 'SH-SEC',
        ]);

        $this->category = Category::factory()->create();

        $this->tomato = Product::factory()->create([
            'name' => 'Roma Tomato',
            'sku' => 'VEG-TOM-01',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->vegetableWarehouse->id,
            'base_price' => 20.0,
            'is_active' => true,
        ]);

        $this->apple = Product::factory()->create([
            'name' => 'Fuji Apple',
            'sku' => 'FRT-APP-01',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->fruitWarehouse->id,
            'base_price' => 50.0,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Farms Agro']);
    }

    public function test_warehouse_switcher_renders_available_warehouses_and_selected_state(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'warehouse_id' => $this->vegetableWarehouse->id,
            'date' => '2026-09-08',
            'tab' => 'daily_inventory',
        ]));

        $response->assertOk();
        $response->assertSee('Warehouse:');
        $response->assertSee('Vegetable Warehouse');
        $response->assertSee('Fruit Warehouse');
        $response->assertSee('value="'.$this->vegetableWarehouse->id.'" selected', false);

        // Switch to Fruit Warehouse
        $responseFruit = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'warehouse_id' => $this->fruitWarehouse->id,
            'date' => '2026-09-08',
            'tab' => 'daily_inventory',
        ]));

        $responseFruit->assertOk();
        $responseFruit->assertSee('value="'.$this->fruitWarehouse->id.'" selected', false);
    }

    public function test_warehouse_switch_changes_advance_bills_data(): void
    {
        // Advance in Vegetable Warehouse
        $vegAdv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'grn_number' => 'GRN-ADV-VEG-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->vegetableWarehouse->id,
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-09-08',
        ]);
        $vegAdv->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        // Advance in Fruit Warehouse
        $fruitAdv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'grn_number' => 'GRN-ADV-FRT-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->fruitWarehouse->id,
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-09-08',
        ]);
        $fruitAdv->items()->create([
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        // 1. View Advance Bills for Vegetable Warehouse
        $resVeg = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'advance_bills',
            'warehouse_id' => $this->vegetableWarehouse->id,
            'date' => '2026-09-08',
        ]));
        $resVeg->assertOk();
        $resVeg->assertSee('GRN-ADV-VEG-001');
        $resVeg->assertDontSee('GRN-ADV-FRT-001');

        // 2. View Advance Bills for Fruit Warehouse
        $resFruit = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'advance_bills',
            'warehouse_id' => $this->fruitWarehouse->id,
            'date' => '2026-09-08',
        ]));
        $resFruit->assertOk();
        $resFruit->assertSee('GRN-ADV-FRT-001');
        $resFruit->assertDontSee('GRN-ADV-VEG-001');
    }

    public function test_warehouse_switch_changes_pending_bills_data(): void
    {
        // PO for Vegetable Warehouse
        $vegPo = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->vegetableWarehouse->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-VEG-PENDING-01',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-08',
            'created_by' => $this->adminUser->id,
        ]);
        $vegPo->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit' => 'kg',
            'purchase_unit' => 'kg',
            'unit_price' => 20.0,
            'total_price' => 2000.0,
        ]);

        // PO for Fruit Warehouse
        $fruitPo = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->fruitWarehouse->id,
            'destination_shop_id' => $this->shopB->id,
            'po_number' => 'PO-FRUIT-PENDING-01',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-08',
            'created_by' => $this->adminUser->id,
        ]);
        $fruitPo->items()->create([
            'product_id' => $this->apple->id,
            'quantity' => 60.0,
            'unit' => 'kg',
            'purchase_unit' => 'kg',
            'unit_price' => 50.0,
            'total_price' => 3000.0,
        ]);

        // View Pending Bills for Vegetable Warehouse
        $resVeg = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'pending_bills',
            'warehouse_id' => $this->vegetableWarehouse->id,
            'date' => '2026-09-08',
        ]));
        $resVeg->assertOk();
        $resVeg->assertSee('PO-VEG-PENDING-01');
        $resVeg->assertDontSee('PO-FRUIT-PENDING-01');

        // View Pending Bills for Fruit Warehouse
        $resFruit = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'pending_bills',
            'warehouse_id' => $this->fruitWarehouse->id,
            'date' => '2026-09-08',
        ]));
        $resFruit->assertOk();
        $resFruit->assertSee('PO-FRUIT-PENDING-01');
        $resFruit->assertDontSee('PO-VEG-PENDING-01');
    }

    public function test_warehouse_preserves_across_tab_and_date_links(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'daily_inventory',
            'warehouse_id' => $this->vegetableWarehouse->id,
            'date' => '2026-09-08',
        ]));

        $response->assertOk();

        // Check that tab links contain warehouse_id
        $response->assertSee('warehouse_id='.$this->vegetableWarehouse->id);
        $response->assertSee('tab=current_inventory');
        $response->assertSee('tab=receive_bills');
        $response->assertSee('tab=stock_without_bill');
        $response->assertSee('tab=shop_returns');
        $response->assertSee('tab=damage');
        $response->assertSee('tab=physical_check');

        // Check that date navigation links contain warehouse_id
        $response->assertSee('date=2026-09-07');
        $response->assertSee('date=2026-09-09');
    }

    private function createPhysicalBatch(Product $product, Warehouse $warehouse, float $qtyKg, string $date, ?GoodsReceived $grn = null): StockBatch
    {
        return StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'goods_received_id' => $grn?->id,
            'reference' => 'BATCH-TEST-'.Str::upper(Str::random(6)),
            'total_kg' => $qtyKg,
            'available_kg' => $qtyKg,
            'cost_per_kg' => 20.0,
            'received_at' => $date,
            'created_at' => Carbon::parse($date)->setTime(10, 0, 0),
            'created_by' => $this->adminUser->id,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => Carbon::parse($date)->setTime(10, 0, 0),
            'warehouse_confirmed_by' => $this->adminUser->id,
        ]);
    }

    private function createAdvanceGrn(Warehouse $warehouse, Product $product, float $qty, string $date, string $billStatus = 'bill_pending'): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-'.Str::upper(Str::random(6)),
            'status' => 'approved',
            'bill_status' => $billStatus,
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $warehouse->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
            'created_at' => Carbon::parse($date)->setTime(10, 0, 0),
        ]);

        $grn->items()->create([
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'variance' => 0,
        ]);

        $this->createPhysicalBatch($product, $warehouse, $qty, $date, $grn);

        return $grn;
    }

    public function test_auto_match_preview_and_execute_isolate_to_selected_warehouse(): void
    {
        // Vegetable Advance + PO
        $vegAdv = $this->createAdvanceGrn($this->vegetableWarehouse, $this->tomato, 100.0, '2026-09-08');

        $vegPo = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->vegetableWarehouse->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-VEG-AUTO-01',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-08',
            'created_by' => $this->adminUser->id,
        ]);
        $vegPo->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit' => 'kg',
            'purchase_unit' => 'kg',
            'unit_price' => 20.0,
            'total_price' => 2000.0,
        ]);

        $vegGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $vegPo->id,
            'grn_number' => 'GRN-BILL-VEG-01',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'warehouse_id' => $this->vegetableWarehouse->id,
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-09-08',
            'created_at' => Carbon::parse('2026-09-08')->setTime(10, 0, 0),
        ]);
        $vegGrn->items()->create([
            'purchase_order_item_id' => $vegPo->items->first()->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        // Fruit Advance + PO
        $fruitAdv = $this->createAdvanceGrn($this->fruitWarehouse, $this->apple, 50.0, '2026-09-08');

        $fruitPo = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->fruitWarehouse->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-FRUIT-AUTO-01',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-08',
            'created_by' => $this->adminUser->id,
        ]);
        $fruitPo->items()->create([
            'product_id' => $this->apple->id,
            'quantity' => 50.0,
            'unit' => 'kg',
            'purchase_unit' => 'kg',
            'unit_price' => 50.0,
            'total_price' => 2500.0,
        ]);
        $fruitGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $fruitPo->id,
            'grn_number' => 'GRN-BILL-FRUIT-01',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'warehouse_id' => $this->fruitWarehouse->id,
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-09-08',
            'created_at' => Carbon::parse('2026-09-08')->setTime(10, 0, 0),
        ]);
        $fruitGrn->items()->create([
            'purchase_order_item_id' => $fruitPo->items->first()->id,
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        // Auto Match Preview for Vegetable Warehouse only
        $vegPlanRes = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.inventory.auto-clear-plan', [
            'warehouse_id' => $this->vegetableWarehouse->id,
        ]));
        $vegPlanRes->assertOk();
        $vegPlanRes->assertJsonPath('data.summary.full_bills', 1);
        $vegPlanRes->assertJsonPath('data.ready_bills.0.reference', 'GRN-BILL-VEG-01');

        $vegPlanHash = $vegPlanRes->json('data.plan_hash');

        // Execute Auto Match for Vegetable Warehouse
        $execVegRes = $this->actingAs($this->adminUser)->postJson(route('admin.cashbook.inventory.auto-clear-execute'), [
            'warehouse_id' => $this->vegetableWarehouse->id,
            'plan_hash' => $vegPlanHash,
            'client_submission_id' => (string) Str::uuid(),
        ]);
        $execVegRes->assertOk();

        // Vegetable Advance should now be cleared
        $this->assertDatabaseHas('goods_received', [
            'id' => $vegAdv->id,
            'bill_status' => 'bill_available',
        ]);

        // Fruit Advance should still be pending
        $this->assertDatabaseHas('goods_received', [
            'id' => $fruitAdv->id,
            'bill_status' => 'bill_pending',
        ]);
    }
}
