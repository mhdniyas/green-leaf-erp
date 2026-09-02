<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Models\BillReconciliation;
use App\Models\BillReconciliationLine;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\AdvanceInventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvanceInventoryDailyReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Warehouse $warehouseA;
    private Warehouse $warehouseB;
    private Category $category;
    private Product $tomato;
    private Product $onion;
    private Product $potato;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->warehouseA = Warehouse::create([
            'name' => 'Main Warehouse Alpha',
            'code' => 'WH-A',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Secondary Warehouse Beta',
            'code' => 'WH-B',
            'is_active' => true,
        ]);

        $this->category = Category::factory()->create();

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Hybrid',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'base_price' => 20.0,
            'is_active' => true,
        ]);

        $this->onion = Product::factory()->create([
            'name' => 'Red Onion',
            'sku' => 'ONI-001',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'base_price' => 30.0,
            'is_active' => true,
        ]);

        $this->potato = Product::factory()->create([
            'name' => 'Agra Potato',
            'sku' => 'POT-001',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'base_price' => 25.0,
            'is_active' => true,
        ]);

        // Multi-unit for Tomato: 1 crate = 25 kg
        ProductUnit::create([
            'product_id' => $this->tomato->id,
            'unit' => 'crate',
            'label' => 'Crate (25kg)',
            'conversion_to_base' => 25.0,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Agri Supplier']);

        Sanctum::actingAs($this->adminUser);
    }

    private function createAdvanceReceipt(Product $product, float $qty, string $unit, string $date, ?Warehouse $warehouse = null): array
    {
        $wh = $warehouse ?? $this->warehouseA;
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-' . Str::upper(Str::random(8)),
            'warehouse_id' => $wh->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => $date,
            'received_by' => $this->adminUser->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $unit,
            'variance' => 0.0,
        ]);

        $conv = (float) ($product->conversionToBaseForUnit($unit) ?? 1.0);
        $baseQty = round($qty * $conv, 3);

        $batch = StockBatch::create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'total_kg' => $baseQty,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
            'created_by' => $this->adminUser->id,
            'reference' => 'BAT-' . Str::upper(Str::random(6)),
        ]);

        return [$grn, $item, $batch];
    }

    private function createConfirmedBillReconciliation(
        Product $product,
        float $billBaseQty,
        float $advanceMatchedBaseQty,
        float $newReceiveBaseQty,
        string $confirmedDate,
        ?Warehouse $warehouse = null
    ): BillReconciliation {
        $wh = $warehouse ?? $this->warehouseA;
        $recon = BillReconciliation::create([
            'warehouse_id' => $wh->id,
            'source_type' => $advanceMatchedBaseQty > 0 ? ($newReceiveBaseQty > 0 ? 'mixed' : 'advance') : 'normal',
            'status' => 'confirmed',
            'total_bill_base_qty' => $billBaseQty,
            'total_matched_base_qty' => $advanceMatchedBaseQty,
            'total_new_receive_base_qty' => $newReceiveBaseQty,
            'confirmed_by' => $this->adminUser->id,
            'confirmed_at' => Carbon::parse($confirmedDate)->setTime(12, 0, 0),
            'created_at' => Carbon::parse($confirmedDate)->setTime(12, 0, 0),
        ]);

        BillReconciliationLine::create([
            'bill_reconciliation_id' => $recon->id,
            'product_id' => $product->id,
            'bill_qty' => $billBaseQty,
            'bill_unit' => $product->unit,
            'bill_base_qty' => $billBaseQty,
            'advance_matched_qty' => $advanceMatchedBaseQty,
            'advance_matched_unit' => $product->unit,
            'advance_matched_base_qty' => $advanceMatchedBaseQty,
            'new_receive_qty' => $newReceiveBaseQty,
            'new_receive_unit' => $product->unit,
            'new_receive_base_qty' => $newReceiveBaseQty,
            'reconciled_qty' => $billBaseQty,
            'reconciled_base_qty' => $billBaseQty,
            'difference_status' => $advanceMatchedBaseQty >= $billBaseQty ? 'matched' : 'partial',
            'created_at' => Carbon::parse($confirmedDate)->setTime(12, 0, 0),
        ]);

        // If new physical quantity arrived with the bill, create stock batch for remainder only
        if ($newReceiveBaseQty > 0) {
            StockBatch::create([
                'product_id' => $product->id,
                'warehouse_id' => $wh->id,
                'total_kg' => $newReceiveBaseQty,
                'cost_per_kg' => 20.0,
                'status' => BatchStatus::Pending,
                'warehouse_receive_pending' => false,
                'received_at' => $confirmedDate,
                'created_by' => $this->adminUser->id,
                'reference' => 'BAT-' . Str::upper(Str::random(6)),
            ]);
        }

        return $recon;
    }

    public function test_1_current_day_product_aggregation(): void
    {
        $this->createAdvanceReceipt($this->tomato, 100.0, 'kg', '2026-09-03');
        $this->createAdvanceReceipt($this->onion, 50.0, 'kg', '2026-09-03');

        $response = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&date=2026-09-03');

        $response->assertOk();
        $data = collect($response->json('data'));

        $tomRow = $data->firstWhere('product_id', $this->tomato->id);
        $this->assertNotNull($tomRow);
        $this->assertEquals(100.0, (float) $tomRow['physical_base_qty']);
        $this->assertEquals(0.0, (float) $tomRow['billed_base_qty']);
        $this->assertEquals(100.0, (float) $tomRow['difference_base_qty']);
        $this->assertEquals('excess', $tomRow['difference_type']);
    }

    public function test_2_previous_day_unresolved_excess_carries_forward(): void
    {
        // 02 Sep: 40 KG Tomato advance received
        $this->createAdvanceReceipt($this->tomato, 40.0, 'kg', '2026-09-02');

        // 03 Sep: 100 KG Tomato advance received, 75 KG bill confirmed
        $this->createAdvanceReceipt($this->tomato, 100.0, 'kg', '2026-09-03');
        $this->createConfirmedBillReconciliation($this->tomato, 75.0, 75.0, 0.0, '2026-09-03');

        $response = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&date=2026-09-03');

        $response->assertOk();
        $tomRow = collect($response->json('data'))->firstWhere('product_id', $this->tomato->id);

        $this->assertNotNull($tomRow);
        // Total physical as of 03 Sep = 40 + 100 = 140 KG
        $this->assertEquals(140.0, (float) $tomRow['physical_base_qty']);
        // Total billed as of 03 Sep = 75 KG
        $this->assertEquals(75.0, (float) $tomRow['billed_base_qty']);
        // Closing diff = 140 - 75 = +65 KG excess
        $this->assertEquals(65.0, (float) $tomRow['difference_base_qty']);
        $this->assertEquals('excess', $tomRow['difference_type']);
    }

    public function test_3_previous_day_shortage_carries_forward(): void
    {
        // 02 Sep: Advance receipt 355 KG
        $this->createAdvanceReceipt($this->onion, 355.0, 'kg', '2026-09-02');
        // 02 Sep: Vendor bill confirmed for 400 KG (short delivery: no new stock arrived)
        $this->createConfirmedBillReconciliation($this->onion, 400.0, 355.0, 0.0, '2026-09-02');

        // Check 03 Sep
        $response = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&date=2026-09-03');

        $response->assertOk();
        $oniRow = collect($response->json('data'))->firstWhere('product_id', $this->onion->id);

        $this->assertNotNull($oniRow);
        $this->assertEquals(355.0, (float) $oniRow['physical_base_qty']);
        $this->assertEquals(400.0, (float) $oniRow['billed_base_qty']);
        $this->assertEquals(-45.0, (float) $oniRow['difference_base_qty']);
        $this->assertEquals('short', $oniRow['difference_type']);
    }

    public function test_4_partial_matching_updates_difference(): void
    {
        $this->createAdvanceReceipt($this->tomato, 100.0, 'kg', '2026-09-03');
        $this->createConfirmedBillReconciliation($this->tomato, 40.0, 40.0, 0.0, '2026-09-03');

        $response = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&date=2026-09-03');

        $response->assertOk();
        $tomRow = collect($response->json('data'))->firstWhere('product_id', $this->tomato->id);

        $this->assertEquals(100.0, (float) $tomRow['physical_base_qty']);
        $this->assertEquals(40.0, (float) $tomRow['billed_base_qty']);
        $this->assertEquals(60.0, (float) $tomRow['difference_base_qty']);
        $this->assertEquals('excess', $tomRow['difference_type']);
    }

    public function test_5_full_matching_reaches_zero_balanced(): void
    {
        $this->createAdvanceReceipt($this->potato, 220.0, 'kg', '2026-09-03');
        $this->createConfirmedBillReconciliation($this->potato, 220.0, 220.0, 0.0, '2026-09-03');

        $response = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&date=2026-09-03');

        $response->assertOk();
        $potRow = collect($response->json('data'))->firstWhere('product_id', $this->potato->id);

        $this->assertEquals(220.0, (float) $potRow['physical_base_qty']);
        $this->assertEquals(220.0, (float) $potRow['billed_base_qty']);
        $this->assertEquals(0.0, (float) $potRow['difference_base_qty']);
        $this->assertEquals('balanced', $potRow['difference_type']);
    }

    public function test_6_multi_unit_conversion_uses_base_units_correctly(): void
    {
        // 4 crates Tomato (1 crate = 25 kg) = 100 KG
        $this->createAdvanceReceipt($this->tomato, 4.0, 'crate', '2026-09-03');
        // Bill for 50 KG confirmed
        $this->createConfirmedBillReconciliation($this->tomato, 50.0, 50.0, 0.0, '2026-09-03');

        $response = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&date=2026-09-03');

        $response->assertOk();
        $tomRow = collect($response->json('data'))->firstWhere('product_id', $this->tomato->id);

        $this->assertEquals('KG', $tomRow['base_unit']);
        $this->assertEquals(100.0, (float) $tomRow['physical_base_qty']);
        $this->assertEquals(50.0, (float) $tomRow['billed_base_qty']);
        $this->assertEquals(50.0, (float) $tomRow['difference_base_qty']);
    }

    public function test_7_warehouse_isolation(): void
    {
        $this->createAdvanceReceipt($this->tomato, 100.0, 'kg', '2026-09-03', $this->warehouseA);
        $this->createAdvanceReceipt($this->tomato, 200.0, 'kg', '2026-09-03', $this->warehouseB);

        // Warehouse A query
        $resA = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&date=2026-09-03');
        $resA->assertOk();
        $tomA = collect($resA->json('data'))->firstWhere('product_id', $this->tomato->id);
        $this->assertEquals(100.0, (float) $tomA['physical_base_qty']);

        // Warehouse B query
        $resB = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseB->id . '&date=2026-09-03');
        $resB->assertOk();
        $tomB = collect($resB->json('data'))->firstWhere('product_id', $this->tomato->id);
        $this->assertEquals(200.0, (float) $tomB['physical_base_qty']);
    }

    public function test_8_historical_as_of_date_and_specific_finalization_sequence(): void
    {
        // 1. Advance physically received Sep 1 (500 KG)
        $this->createAdvanceReceipt($this->tomato, 500.0, 'kg', '2026-09-01');

        // 2. Bill remains pending through Sep 2 (no confirmed bill reconciliation)

        // Query Sep 2 => Billed must remain 0, Advance must be +500
        $resSep2 = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&date=2026-09-02');
        $resSep2->assertOk();
        $tomSep2 = collect($resSep2->json('data'))->firstWhere('product_id', $this->tomato->id);
        $this->assertEquals(0.0, (float) $tomSep2['billed_base_qty']);
        $this->assertEquals(500.0, (float) $tomSep2['physical_base_qty']);
        $this->assertEquals(500.0, (float) $tomSep2['difference_base_qty']);

        // 3. Bill reconciliation confirmed Sep 3 (400 KG)
        $this->createConfirmedBillReconciliation($this->tomato, 400.0, 400.0, 0.0, '2026-09-03');

        // Query Sep 3 => Billed 400, Physical 500, Advance +100
        $resSep3 = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&date=2026-09-03');
        $resSep3->assertOk();
        $tomSep3 = collect($resSep3->json('data'))->firstWhere('product_id', $this->tomato->id);
        $this->assertEquals(400.0, (float) $tomSep3['billed_base_qty']);
        $this->assertEquals(500.0, (float) $tomSep3['physical_base_qty']);
        $this->assertEquals(100.0, (float) $tomSep3['difference_base_qty']);

        // 4. Query Sep 2 AGAIN => Billed MUST STILL BE 0 (no retroactive alteration)
        $resSep2Again = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&date=2026-09-02');
        $resSep2Again->assertOk();
        $tomSep2Again = collect($resSep2Again->json('data'))->firstWhere('product_id', $this->tomato->id);
        $this->assertEquals(0.0, (float) $tomSep2Again['billed_base_qty']);
        $this->assertEquals(500.0, (float) $tomSep2Again['physical_base_qty']);
        $this->assertEquals(500.0, (float) $tomSep2Again['difference_base_qty']);
    }

    public function test_9_product_search(): void
    {
        $this->createAdvanceReceipt($this->tomato, 50.0, 'kg', '2026-09-03');
        $this->createAdvanceReceipt($this->onion, 50.0, 'kg', '2026-09-03');

        $res = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&search=Tomato&date=2026-09-03');
        $res->assertOk();
        $data = collect($res->json('data'));

        $this->assertTrue($data->contains('product_id', $this->tomato->id));
        $this->assertFalse($data->contains('product_id', $this->onion->id));
    }

    public function test_10_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $p = Product::factory()->create([
                'name' => "Paginated Product {$i}",
                'sku' => "PAG-00{$i}",
                'unit' => 'kg',
                'category_id' => $this->category->id,
                'default_warehouse_id' => $this->warehouseA->id,
                'base_price' => 10.0,
                'is_active' => true,
            ]);
            $this->createAdvanceReceipt($p, 10.0 * $i, 'kg', '2026-09-03');
        }

        $res = $this->getJson('/api/v1/purchasing/advance-inventory?warehouse_id=' . $this->warehouseA->id . '&per_page=2&page=1&date=2026-09-03');
        $res->assertOk();

        $this->assertEquals(2, count($res->json('data')));
        $this->assertArrayHasKey('meta', $res->json());
        $this->assertGreaterThanOrEqual(5, $res->json('meta.total'));
    }

    public function test_11_old_cleared_grn_older_than_3_days_hidden_operationally(): void
    {
        // Cleared GRN from 10 days ago (all items matched)
        [$grn, $item] = $this->createAdvanceReceipt($this->tomato, 50.0, 'kg', '2026-08-20');
        $grn->update(['bill_status' => 'bill_available']);

        // Operational list query (date = today 2026-09-03)
        $res = $this->getJson('/api/v1/purchasing/grns?receipt_type=warehouse_advance&warehouse_id=' . $this->warehouseA->id . '&date=2026-09-03');
        $res->assertOk();

        $data = collect($res->json('data'));
        $this->assertFalse($data->contains('id', $grn->id));
    }

    public function test_12_old_pending_or_partial_grn_older_than_3_days_not_hidden(): void
    {
        // Pending GRN from 10 days ago (unbilled balance remains)
        [$grn, $item, $batch] = $this->createAdvanceReceipt($this->tomato, 50.0, 'kg', '2026-08-20');

        $res = $this->getJson('/api/v1/purchasing/grns?receipt_type=warehouse_advance&warehouse_id=' . $this->warehouseA->id . '&date=2026-09-03');
        $res->assertOk();

        $data = collect($res->json('data'));
        $this->assertTrue($data->contains('id', $grn->id));
    }

    public function test_13_query_count_and_n_plus_one_regression(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $p = Product::factory()->create([
                'name' => "Batch Product {$i}",
                'sku' => "BAT-P-{$i}",
                'unit' => 'kg',
                'category_id' => $this->category->id,
                'default_warehouse_id' => $this->warehouseA->id,
                'base_price' => 15.0,
                'is_active' => true,
            ]);
            $this->createAdvanceReceipt($p, 100.0, 'kg', '2026-09-03');
            $this->createConfirmedBillReconciliation($p, 50.0, 50.0, 0.0, '2026-09-03');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $service = app(AdvanceInventoryService::class);
        $res = $service->paginateDailyInventory([
            'warehouse_id' => $this->warehouseA->id,
            'date' => '2026-09-03',
        ], 10);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals(10, $res->count());
        $this->assertLessThanOrEqual(5, $queryCount);
    }
}
