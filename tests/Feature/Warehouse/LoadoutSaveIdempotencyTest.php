<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 3B Safety Gate — Loadout Save Idempotency Tests.
 *
 * Proves that POST /warehouse/loadout/{order}/save:
 * 1. Uses ABSOLUTE (SET) semantics — not additive delta to the submitted value.
 * 2. Does not duplicate stock movements on identical retry.
 * 3. Can handle stale vs newer requests without incorrect overwrite when called correctly.
 *
 * Stock accounting:
 * - diff = submittedQty - oldLoadedQty
 * - diff > 0.001 → StockOut (consume from stock)
 * - diff < -0.001 → SaleReversal (return to stock)
 * - diff ≈ 0    → no stock movement
 */
class LoadoutSaveIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->operator = User::factory()->create(['name' => 'Warehouse Operator']);
        $this->operator->assignRole('warehouse_receiver');

        $this->product = Product::factory()->create(['unit' => 'kg']);

        // Seed a sorted stock batch with 200 kg available
        $batch = StockBatch::factory()->sorted()->create([
            'product_id' => $this->product->id,
            'created_by' => $this->operator->id,
            'total_kg' => 200.0,
            'cost_per_kg' => 5.0,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'created_by' => $this->operator->id,
            'grade' => 'A',
            'type' => 'in',
            'quantity' => 200.0,
            'cost_per_unit' => 5.0,
            'warehouse_id' => $batch->warehouse_id,
            'notes' => 'Initial stock for test',
        ]);

        Sanctum::actingAs($this->operator);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array{0: ShopOrder, 1: ShopOrderItem}
     */
    private function orderWithItem(float $approvedQty = 20.0): array
    {
        $priceGroup = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);
        $shop = Shop::factory()->create(['shop_price_group_id' => $priceGroup->id]);

        DailyPriceApproval::query()->create([
            'product_id' => $this->product->id,
            'business_date' => today()->toDateString(),
            'purchase_price' => 5,
            'price_unit' => 'kg',
            'price_a' => 10,
            'price_b' => 10,
            'price_c' => 10,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
            'delivery_status' => 'pending_delivery',
        ]);

        $item = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => $approvedQty,
            'approved_qty' => $approvedQty,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => $approvedQty,
            'requested_unit_conversion_to_base' => 1,
            'locked_price_group_id' => $priceGroup->id,
            'locked_selling_price' => 10,
            'locked_price_source' => 'manual',
            'line_total' => $approvedQty * 10,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);

        return [$order, $item];
    }

    private function saveLoadout(ShopOrder $order, array $items): void
    {
        $this->postJson(
            route('warehouse.loadout.save', $order),
            ['items' => $items],
        )->assertSuccessful();
    }

    private function stockOutCount(ShopOrder $order): int
    {
        return StockMovement::where('product_id', $this->product->id)
            ->where('type', 'out')
            ->where('notes', 'like', "%Order: {$order->order_number}%")
            ->count();
    }

    private function stockReversalCount(ShopOrder $order): int
    {
        return StockMovement::where('product_id', $this->product->id)
            ->where('type', 'sale_reversal')
            ->where('notes', 'like', "%Order: {$order->order_number}%")
            ->count();
    }

    private function totalStockOut(ShopOrder $order): float
    {
        return (float) StockMovement::where('product_id', $this->product->id)
            ->where('type', 'out')
            ->where('notes', 'like', "%Order: {$order->order_number}%")
            ->sum('quantity');
    }

    private function totalStockReversal(ShopOrder $order): float
    {
        return (float) StockMovement::where('product_id', $this->product->id)
            ->where('type', 'sale_reversal')
            ->where('notes', 'like', "%Order: {$order->order_number}%")
            ->sum('quantity');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 1: Absolute semantics — SET not ADD
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST desired = 12 twice.
     * loaded_qty must be 12, NOT 24.
     * Only 1 stock movement: Out +12.
     */
    public function test_identical_payload_repeated_does_not_duplicate_stock_or_quantity(): void
    {
        [$order, $item] = $this->orderWithItem();

        // First save: 0 → 12 (diff = +12)
        $this->saveLoadout($order, [$this->product->id => 12]);

        $item->refresh();
        $this->assertSame(12.0, (float) $item->loaded_qty);
        $this->assertSame(1, $this->stockOutCount($order));
        $this->assertSame(12.0, $this->totalStockOut($order));

        // Second save: 12 → 12 (diff = 0, no stock movement)
        $this->saveLoadout($order->fresh(), [$this->product->id => 12]);

        $item->refresh();
        $this->assertSame(12.0, (float) $item->loaded_qty, 'Repeated identical save must not change loaded_qty');
        $this->assertSame(1, $this->stockOutCount($order), 'No additional stock Out movement on identical retry');
        $this->assertSame(12.0, $this->totalStockOut($order), 'Total Out must not increase');
        $this->assertSame(0, $this->stockReversalCount($order), 'No SaleReversal on identical payload');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 2: 10 → 12 → retry 12
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Save 10, then 12, then retry 12 (simulates a network retry after success was lost).
     * Net stock change from 10→12 = +2. retry 12 → diff 0, no new movement.
     */
    public function test_save_12_after_10_then_retry_12_is_idempotent(): void
    {
        [$order, $item] = $this->orderWithItem();

        // First save 10
        $this->saveLoadout($order, [$this->product->id => 10]);
        $this->assertSame(10.0, (float) $item->fresh()->loaded_qty);
        $this->assertSame(10.0, $this->totalStockOut($order));

        // Increment to 12
        $this->saveLoadout($order->fresh(), [$this->product->id => 12]);
        $this->assertSame(12.0, (float) $item->fresh()->loaded_qty);
        $this->assertSame(12.0, $this->totalStockOut($order), 'Total Out: 10 first, then +2 more = 12 net');
        $this->assertSame(2, $this->stockOutCount($order), 'Two separate Out movements (10 and +2)');

        // Retry 12 — must be zero-op
        $this->saveLoadout($order->fresh(), [$this->product->id => 12]);
        $this->assertSame(12.0, (float) $item->fresh()->loaded_qty);
        $this->assertSame(12.0, $this->totalStockOut($order), 'Retry must not add more stock Out');
        $this->assertSame(2, $this->stockOutCount($order), 'Still exactly 2 Out movements');
        $this->assertSame(0, $this->stockReversalCount($order), 'No SaleReversal created');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 3: 12 → 13 correct incremental delta
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * 0 → 12 → 13: net stock Out = 13, loaded_qty = 13.
     */
    public function test_sequential_increase_accumulates_correctly(): void
    {
        [$order, $item] = $this->orderWithItem();

        $this->saveLoadout($order, [$this->product->id => 12]);
        $this->saveLoadout($order->fresh(), [$this->product->id => 13]);

        $item->refresh();
        $this->assertSame(13.0, (float) $item->loaded_qty);
        $this->assertSame(13.0, $this->totalStockOut($order));
        $this->assertSame(0, $this->stockReversalCount($order));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 4: Stale-write test
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * CRITICAL: POST 13 (newer edit) then stale POST 12.
     *
     * The save endpoint uses $oldLoadedQty from the DB inside a lockForUpdate
     * transaction. When 13 is committed, a subsequent POST of 12 sees:
     *   diff = 12 - 13 = -1 → SaleReversal of 1
     *
     * Final loaded_qty = 12 (NOT 13).
     *
     * This means stale writes CAN SILENTLY OVERWRITE newer values.
     * This is the critical finding for Phase 3B.
     *
     * Documents the ACTUAL behavior so Phase 3B retry logic can be designed
     * to avoid sending stale payloads.
     */
    public function test_stale_write_overwrites_newer_quantity_documen_t_only(): void
    {
        [$order, $item] = $this->orderWithItem();

        // Save 13 (newer value confirmed server-side)
        $this->saveLoadout($order, [$this->product->id => 13]);
        $this->assertSame(13.0, (float) $item->fresh()->loaded_qty);

        // Stale POST with 12 (network retry of an older request)
        $this->saveLoadout($order->fresh(), [$this->product->id => 12]);

        $item->refresh();

        // Document actual behavior: stale POST DOES overwrite — loaded = 12, SaleReversal of 1
        $this->assertSame(12.0, (float) $item->loaded_qty,
            'STALE WRITE CONFIRMED: sending older payload after newer save overwrites loaded_qty');
        $this->assertSame(1, $this->stockReversalCount($order),
            'Stale POST causes a SaleReversal of 1 to reduce 13→12');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 5: N/A retry is safe
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Marking product as not_available twice should not create duplicate
     * movements or corrupt the row.
     */
    public function test_not_available_retry_is_safe(): void
    {
        [$order, $item] = $this->orderWithItem();

        // First N/A
        $this->postJson(
            route('warehouse.loadout.save', $order),
            ['item_status' => [$this->product->id => 'not_available']],
        )->assertSuccessful();

        $item->refresh();
        $this->assertSame('not_available', $item->sorting_status);
        $this->assertSame(0.0, (float) $item->loaded_qty);
        $this->assertSame(0, $this->stockOutCount($order), 'N/A sends 0, no Out needed');

        // Second N/A (retry)
        $this->postJson(
            route('warehouse.loadout.save', $order->fresh()),
            ['item_status' => [$this->product->id => 'not_available']],
        )->assertSuccessful();

        $item->refresh();
        $this->assertSame('not_available', $item->sorting_status);
        $this->assertSame(0, $this->stockOutCount($order), 'Still no Out on N/A retry');
        $this->assertSame(0, $this->stockReversalCount($order), 'No SaleReversal on N/A retry');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 6: Transaction rollback — partial save leaves DB unchanged
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Sending an invalid numeric field causes validation exception.
     * The ShopOrderItem must not be mutated.
     */
    public function test_validation_error_leaves_order_unchanged(): void
    {
        [$order, $item] = $this->orderWithItem();

        // Set a known initial state
        $this->saveLoadout($order, [$this->product->id => 10]);
        $this->assertSame(10.0, (float) $item->fresh()->loaded_qty);

        // Bad payload — negative quantity
        $this->postJson(
            route('warehouse.loadout.save', $order->fresh()),
            ['items' => [$this->product->id => -5]],
        )->assertUnprocessable();

        // DB must be unchanged
        $this->assertSame(10.0, (float) $item->fresh()->loaded_qty, 'Failed validation must not change loaded_qty');
        $this->assertSame(1, $this->stockOutCount($order), 'No additional stock movement on failed request');
    }
}
