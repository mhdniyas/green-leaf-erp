<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaseBusinessDayCarryForward;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaserBusinessDayPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Supplier $supplier;

    /** @var Product[] */
    private array $products = [];

    private PurchaseBusinessDay $currentDay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Performance Test Warehouse',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Performance Supplier']);

        $category = Category::factory()->create();

        // Create 10 products
        for ($i = 1; $i <= 10; $i++) {
            $this->products[] = Product::factory()->create([
                'category_id' => $category->id,
                'name' => "Perf Vegetable {$i}",
                'sku' => "PERF-PROD-{$i}",
                'unit' => 'kg',
                'default_warehouse_id' => $this->warehouse->id,
                'is_active' => true,
            ]);
        }

        // Past Day 1 (3 days ago)
        $pastDay1 = PurchaseBusinessDay::create([
            'warehouse_id' => $this->warehouse->id,
            'business_date' => Carbon::now()->subDays(3)->toDateString(),
            'status' => PurchaseBusinessDay::STATUS_CLOSED,
            'opened_by' => $this->user->id,
            'closed_by' => $this->user->id,
            'closed_at' => Carbon::now()->subDays(3)->setTime(18, 0),
        ]);

        // Past Day 2 (2 days ago)
        $pastDay2 = PurchaseBusinessDay::create([
            'warehouse_id' => $this->warehouse->id,
            'business_date' => Carbon::now()->subDays(2)->toDateString(),
            'status' => PurchaseBusinessDay::STATUS_CLOSED,
            'opened_by' => $this->user->id,
            'closed_by' => $this->user->id,
            'closed_at' => Carbon::now()->subDays(2)->setTime(18, 0),
        ]);

        // Current Day (Today)
        $this->currentDay = PurchaseBusinessDay::create([
            'warehouse_id' => $this->warehouse->id,
            'business_date' => Carbon::now()->toDateString(),
            'status' => PurchaseBusinessDay::STATUS_OPEN,
            'opened_by' => $this->user->id,
            'opened_at' => Carbon::now()->setTime(8, 0),
        ]);

        // Seed 15 open carry forwards from past day 1 and past day 2
        foreach ($this->products as $idx => $prod) {
            $originDay = ($idx % 2 === 0) ? $pastDay1 : $pastDay2;
            PurchaseBusinessDayCarryForward::create([
                'warehouse_id' => $this->warehouse->id,
                'origin_business_day_id' => $originDay->id,
                'product_id' => $prod->id,
                'pending_qty_at_close' => 50.0 + $idx,
                'unit' => 'kg',
                'status' => PurchaseBusinessDayCarryForward::STATUS_OPEN,
            ]);
        }

        // Create carry forwards originating from current day as well
        foreach (array_slice($this->products, 0, 5) as $prod) {
            PurchaseBusinessDayCarryForward::create([
                'warehouse_id' => $this->warehouse->id,
                'origin_business_day_id' => $this->currentDay->id,
                'product_id' => $prod->id,
                'pending_qty_at_close' => 20.0,
                'unit' => 'kg',
                'status' => PurchaseBusinessDayCarryForward::STATUS_OPEN,
            ]);
        }

        // Seed Advance Receives for current day
        foreach (array_slice($this->products, 0, 5) as $prod) {
            $dto = new GoodsReceivedData(
                purchaseOrderId: null,
                receivedAt: Carbon::now()->setTime(9, 0)->toDateTimeString(),
                transportCost: 0.0,
                labourCost: 0.0,
                notes: 'Advance receipt',
                items: [
                    [
                        'product_id' => $prod->id,
                        'received_qty' => 100.0,
                        'received_unit' => 'kg',
                    ],
                ],
                billStatus: 'pending_bill',
                warehouseId: $this->warehouse->id,
                receiptType: 'warehouse_advance',
                businessDayId: $this->currentDay->id,
            );
            app(RecordGoodsReceiptAction::class)->execute($dto, $this->user->id);
        }

        // Seed Purchase Bills for current day
        foreach (array_slice($this->products, 0, 5) as $prod) {
            $dto = new GoodsReceivedData(
                purchaseOrderId: null,
                receivedAt: Carbon::now()->setTime(10, 0)->toDateTimeString(),
                transportCost: 0.0,
                labourCost: 0.0,
                notes: 'Purchase bill',
                items: [
                    [
                        'product_id' => $prod->id,
                        'received_qty' => 60.0,
                        'received_unit' => 'kg',
                    ],
                ],
                billStatus: 'billed',
                warehouseId: $this->warehouse->id,
                receiptType: 'purchase_bill',
                businessDayId: $this->currentDay->id,
            );
            app(RecordGoodsReceiptAction::class)->execute($dto, $this->user->id);
        }
    }

    public function test_business_day_detail_page_loads_with_bounded_queries_and_no_mutations(): void
    {
        $this->actingAs($this->user);

        DB::enableQueryLog();

        $startTime = microtime(true);
        $response = $this->get(route('purchasing.business-days.show', $this->currentDay->uuid));
        $executionTimeMs = (microtime(true) - $startTime) * 1000;

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertStatus(200);
        $response->assertSee('Performance Test Warehouse');
        $response->assertSee('Perf Vegetable 1');

        // Verify zero operational mutations (no INSERT, UPDATE, DELETE during GET detail page except user activity timestamp)
        foreach ($queries as $q) {
            $sql = strtoupper(trim($q['query']));
            if (str_contains($sql, 'LAST_SEEN_AT')) {
                continue;
            }
            $this->assertFalse(
                str_starts_with($sql, 'INSERT') || str_starts_with($sql, 'UPDATE') || str_starts_with($sql, 'DELETE'),
                "GET request should have zero operational database mutations. Found: {$sql}"
            );
        }

        fwrite(STDERR, "\n[PERFORMANCE METRICS] Total SQL Query Count: {$queryCount}, Time: ".round($executionTimeMs, 2)."ms\n");

        $this->assertLessThan(80, $queryCount, "Business Day detail page executed {$queryCount} queries (expected < 80, baseline was 249+)");
    }
}
