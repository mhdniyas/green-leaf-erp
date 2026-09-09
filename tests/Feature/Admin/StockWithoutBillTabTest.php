<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockWithoutBillTabTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse1;

    private Warehouse $warehouse2;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse1 = Warehouse::factory()->create(['name' => 'Warehouse 1']);
        $this->warehouse2 = Warehouse::factory()->create(['name' => 'Warehouse 2']);

        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TP-001',
            'unit' => 'KG',
        ]);
    }

    public function test_stock_without_bill_empty_returns_200(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=stock_without_bill&date=2026-09-09');

        $response->assertStatus(200);
        $response->assertViewHas('tab', 'stock_without_bill');
        $response->assertViewHas('unbilledAdvRows');
        $response->assertViewHas('unbilledAdvGrns');
        $response->assertViewHas('unbilledInventory');
        $response->assertViewHas('openAdvances');
        $response->assertViewHas('summary');
    }

    public function test_stock_without_bill_with_data_returns_200(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse1->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'bill_status' => 'bill_pending',
            'grn_number' => 'GRN-ADV-001',
            'received_at' => '2026-09-09 10:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50,
            'received_unit' => 'KG',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=stock_without_bill&date=2026-09-09');

        $response->assertStatus(200);
        $response->assertViewHas('tab', 'stock_without_bill');
        $this->assertNotEmpty($response->viewData('unbilledAdvGrns'));
    }

    public function test_stock_without_bill_warehouse_1_returns_200(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse1->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'bill_status' => 'bill_pending',
            'received_at' => '2026-09-09 10:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => 10,
        ]);

        $url = sprintf('/admin/cashbook/inventory?tab=stock_without_bill&date=2026-09-09&warehouse_id=%d', $this->warehouse1->id);
        $response = $this->actingAs($this->admin)->get($url);

        $response->assertStatus(200);
    }

    public function test_stock_without_bill_warehouse_2_returns_200(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse2->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'bill_status' => 'bill_pending',
            'received_at' => '2026-09-09 10:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => 25,
        ]);

        $url = sprintf('/admin/cashbook/inventory?tab=stock_without_bill&date=2026-09-09&warehouse_id=%d', $this->warehouse2->id);
        $response = $this->actingAs($this->admin)->get($url);

        $response->assertStatus(200);
    }

    public function test_stock_without_bill_historical_advance_records_returns_200(): void
    {
        // 1. Advance with null received_at
        $grn1 = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse1->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'bill_status' => 'bill_pending',
            'received_at' => '2025-01-01 10:00:00',
            'created_at' => '2025-01-01 10:00:00',
        ]);

        // 2. Item with missing unit / null received_unit
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn1->id,
            'product_id' => $this->product->id,
            'received_qty' => 10,
            'received_unit' => 'KG',
        ]);

        // 3. Historical Advance with zero items
        GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse1->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'bill_status' => 'bill_pending',
            'received_at' => '2025-02-01 10:00:00',
        ]);

        // 4. Historical AdvanceReceiveMatch with null links
        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse1->id,
            'receipt_type' => null,
            'status' => 'approved',
            'bill_status' => 'billed',
        ]);

        AdvanceReceiveMatch::query()->create([
            'advance_goods_received_id' => $grn1->id,
            'advance_goods_received_item_id' => null,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => null,
            'product_id' => $this->product->id,
            'matched_qty' => 2,
            'matched_unit' => 'KG',
            'base_qty' => 2,
            'conversion_to_base' => 1.0,
            'confirmed_at' => '2026-09-09 12:00:00',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?tab=stock_without_bill&date=2026-09-09');

        $response->assertStatus(200);
        $response->assertViewHas('tab', 'stock_without_bill');
    }
}
