<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\DTOs\Inventory\SortingData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\POStatus;
use App\Exceptions\Inventory\SortingQuantityMismatchException;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Purchasing\PurchaserDailyInitialQuery;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuyMixedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $receiver;

    private Supplier $supplier;

    private Warehouse $warehouse;

    private Shop $shop;

    private Category $category;

    private Product $tomatoH;

    private string $businessDate = '2026-10-20';

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
    }

    /**
     * Test the complete Tomato H 200 kg / ₹3,000 Buy Mixed workflow.
     */
    public function test_buy_mixed_complete_workflow(): void
    {
        // 1. Approved shop demands: A: 100 kg, B: 50 kg, C: 30 kg (Total: 180 kg)
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20261020-01',
            'business_date' => $this->businessDate,
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_qty' => 100,
            'approved_qty' => 100,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'B',
            'unit' => 'kg',
            'requested_qty' => 50,
            'approved_qty' => 50,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'C',
            'unit' => 'kg',
            'requested_qty' => 30,
            'approved_qty' => 30,
        ]);

        // 2. Purchaser adds 200 kg MIXED at ₹15/kg (Total: ₹3,000)
        $addResponse = $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $this->businessDate,
            'product_id' => $this->tomatoH->id,
            'purchase_grade' => 'MIXED',
            'quantity' => 200,
            'unit_price' => 15.00,
            'purchase_source' => 'mixed',
            'return_to' => 'bulk-buy',
        ]);

        $addResponse->assertSessionHasNoErrors();

        $cart = PurchaserCart::query()->where('user_id', $this->purchaser->id)->where('purchase_grade', 'MIXED')->firstOrFail();
        $this->assertEquals('mixed', $cart->purchase_source);
        $this->assertCount(1, $cart->items);
        $item = $cart->items->first();
        $this->assertEquals('MIXED', $item->grade);
        $this->assertEquals(200.0, (float) $item->quantity);
        $this->assertEquals(15.0, (float) $item->unit_price);
        $this->assertEquals(3000.0, (float) $item->line_total);

        // 3. Verify Purchased vs. Remaining demand semantics:
        // Buying 200 kg Mixed MUST NOT immediately mark Grade A, B, or C as purchased!
        $dailyQuery = app(PurchaserDailyInitialQuery::class);
        $pendingDemand = $dailyQuery->getPendingDemand(
            date: Carbon::parse($this->businessDate),
            purchaseGrade: 'ALL',
            user: $this->purchaser
        );

        $tomatoSummary = $pendingDemand->firstWhere('product_id', $this->tomatoH->id);
        $this->assertNotNull($tomatoSummary);
        $this->assertTrue($tomatoSummary['has_non_a_grades']);
        $breakdown = collect($tomatoSummary['grade_breakdown'])->keyBy('grade');

        // All grades remain unbought!
        $this->assertEquals(100.0, (float) $breakdown['A']['approved_qty']);
        $this->assertEquals(0.0, (float) $breakdown['A']['bought_qty']);
        $this->assertEquals(100.0, (float) $breakdown['A']['remaining_qty']);

        $this->assertEquals(50.0, (float) $breakdown['B']['approved_qty']);
        $this->assertEquals(0.0, (float) $breakdown['B']['bought_qty']);
        $this->assertEquals(50.0, (float) $breakdown['B']['remaining_qty']);

        $this->assertEquals(30.0, (float) $breakdown['C']['approved_qty']);
        $this->assertEquals(0.0, (float) $breakdown['C']['bought_qty']);
        $this->assertEquals(30.0, (float) $breakdown['C']['remaining_qty']);

        // 4. Purchaser submits cart: exactly ONE PO, ONE GRN, ONE Supplier Bill
        $submitResponse = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'cart_id' => $cart->id,
            'business_date' => $this->businessDate,
            'purchase_grade' => 'MIXED',
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-MIXED-001',
            'payment_method' => 'Credit',
            'items' => [
                $item->id => [
                    'quantity' => 200,
                    'unit_price' => 15.00,
                ],
            ],
        ]);

        $submitResponse->assertSessionHasNoErrors();

        $cart->refresh();
        $this->assertEquals('submitted', $cart->status);
        $this->assertNotNull($cart->purchase_order_id);
        $this->assertNotNull($cart->goods_received_id);
        $this->assertNotNull($cart->purchase_invoice_id);

        // Assert exactly ONE Supplier Bill created for ₹3,000
        $invoices = PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->get();
        $this->assertCount(1, $invoices);
        $invoice = $invoices->first();
        $this->assertEquals(3000.0, (float) $invoice->amount);
        $this->assertEquals('BILL-MIXED-001', $invoice->invoice_number);

        // Assert exactly ONE PO and ONE GRN
        $po = PurchaseOrder::findOrFail($cart->purchase_order_id);
        $this->assertEquals('MIXED', $po->purchase_grade);
        $this->assertEquals(POStatus::Received, $po->status);

        $grn = GoodsReceived::findOrFail($cart->goods_received_id);
        $this->assertEquals('MIXED', $grn->purchase_grade);

        // 5. Warehouse receives GRN and confirms physical weight as 200 kg
        $receiveResponse = $this->actingAs($this->receiver)->post(route('warehouse.receiver.process-receive-grn', $grn), [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $grn->items->first()->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 200,
                    'discrepancy_type' => 'none',
                ],
            ],
        ]);

        $receiveResponse->assertSessionHasNoErrors();

        // 6. Inspect created StockBatch
        $batches = StockBatch::query()->where('goods_received_id', $grn->id)->get();
        $this->assertCount(1, $batches);
        $batch = $batches->first();

        $this->assertEquals('MIXED', $batch->purchase_grade);
        $this->assertEquals('sort_required', $batch->grading_mode);
        $this->assertEquals(BatchStatus::Pending, $batch->status);
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertEquals(200.0, (float) $batch->total_kg);
        $this->assertEquals(15.0, (float) $batch->cost_per_kg);

        // At this stage, NO sellable stock movements exist yet!
        $initialMovements = StockMovement::query()->where('batch_id', $batch->id)->get();
        $this->assertCount(0, $initialMovements);
        $this->assertTrue($batch->canBeSorted());

        // 7. Warehouse staff sorts batch:
        // A: 110 kg, B: 55 kg, C: 35 kg = 200 kg
        $sortingAction = app(ProcessBatchSortingAction::class);
        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 110.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 55.0],
                ['grade' => ProductGrade::GradeC, 'quantity' => 35.0],
                ['grade' => ProductGrade::Damage, 'quantity' => 0.0],
            ],
            notes: null
        );

        $sortedBatch = $sortingAction->execute($batch, $sortingData, $this->receiver->id);

        $this->assertEquals(BatchStatus::Sorted, $sortedBatch->status);
        $this->assertFalse($sortedBatch->canBeSorted());

        // 8. Assert inventory stock movements:
        $movements = StockMovement::query()->where('batch_id', $batch->id)->get();
        $this->assertCount(3, $movements);

        $movA = $movements->firstWhere('grade', ProductGrade::GradeA);
        $this->assertNotNull($movA);
        $this->assertEquals(110.0, (float) $movA->quantity);
        $this->assertEquals(15.0, (float) $movA->cost_per_unit);
        $this->assertEquals(StockMovementType::In, $movA->type);

        $movB = $movements->firstWhere('grade', ProductGrade::GradeB);
        $this->assertNotNull($movB);
        $this->assertEquals(55.0, (float) $movB->quantity);
        $this->assertEquals(15.0, (float) $movB->cost_per_unit);
        $this->assertEquals(StockMovementType::In, $movB->type);

        $movC = $movements->firstWhere('grade', ProductGrade::GradeC);
        $this->assertNotNull($movC);
        $this->assertEquals(35.0, (float) $movC->quantity);
        $this->assertEquals(15.0, (float) $movC->cost_per_unit);
        $this->assertEquals(StockMovementType::In, $movC->type);

        // Cost verification: 110 * 15 + 55 * 15 + 35 * 15 = 1650 + 825 + 525 = ₹3,000
        $totalStockValue = $movements->sum(fn ($m) => (float) $m->quantity * (float) $m->cost_per_unit);
        $this->assertEquals(3000.0, round($totalStockValue, 2));

        // 9. Loadout approved requirements:
        // A: 100 kg, B: 50 kg, C: 30 kg
        // Expected remaining stock: A: 10 kg, B: 5 kg, C: 5 kg
        $stockA = (float) StockMovement::query()->where('product_id', $this->tomatoH->id)->where('grade', ProductGrade::GradeA)->sum('quantity');
        $stockB = (float) StockMovement::query()->where('product_id', $this->tomatoH->id)->where('grade', ProductGrade::GradeB)->sum('quantity');
        $stockC = (float) StockMovement::query()->where('product_id', $this->tomatoH->id)->where('grade', ProductGrade::GradeC)->sum('quantity');

        $this->assertEquals(110.0, $stockA);
        $this->assertEquals(55.0, $stockB);
        $this->assertEquals(35.0, $stockC);

        $remainingSurplusA = $stockA - 100.0;
        $remainingSurplusB = $stockB - 50.0;
        $remainingSurplusC = $stockC - 30.0;

        $this->assertEquals(10.0, $remainingSurplusA);
        $this->assertEquals(5.0, $remainingSurplusB);
        $this->assertEquals(5.0, $remainingSurplusC);
    }

    /**
     * Test sorting quantity mismatch rejection.
     */
    public function test_sorting_quantity_mismatch_throws_exception(): void
    {
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_grade' => 'MIXED',
            'grading_mode' => 'sort_required',
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'total_kg' => 200.0,
            'cost_per_kg' => 15.0,
        ]);

        $this->expectException(SortingQuantityMismatchException::class);

        $sortingAction = app(ProcessBatchSortingAction::class);
        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 100.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 50.0],
                ['grade' => ProductGrade::GradeC, 'quantity' => 30.0],
            ],
            notes: null
        );

        $sortingAction->execute($batch, $sortingData, $this->receiver->id);
    }

    /**
     * Test sorting with damage creates WastageEntry and reconciles.
     */
    public function test_sorting_with_damage_creates_wastage_entry(): void
    {
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_grade' => 'MIXED',
            'grading_mode' => 'sort_required',
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'total_kg' => 200.0,
            'cost_per_kg' => 15.0,
        ]);

        $sortingAction = app(ProcessBatchSortingAction::class);
        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 100.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 50.0],
                ['grade' => ProductGrade::GradeC, 'quantity' => 30.0],
                ['grade' => ProductGrade::Damage, 'quantity' => 20.0], // 100 + 50 + 30 + 20 = 200 kg
            ],
            notes: null
        );

        $sortedBatch = $sortingAction->execute($batch, $sortingData, $this->receiver->id);

        $this->assertEquals(BatchStatus::Sorted, $sortedBatch->status);

        $wastageEntries = WastageEntry::query()->where('batch_id', $batch->id)->get();
        $this->assertCount(1, $wastageEntries);
        $wastage = $wastageEntries->first();
        $this->assertEquals(20.0, (float) $wastage->quantity);
        $this->assertEquals(15.0, (float) $wastage->cost_per_kg);
        $this->assertEquals(ProductGrade::Damage, $wastage->grade);

        $movements = StockMovement::query()->where('batch_id', $batch->id)->get();
        $this->assertCount(3, $movements);
        $this->assertEquals(180.0, (float) $movements->sum('quantity'));
    }

    /**
     * Test Buy Grade A, Grade B, and Grade C direct purchases continue to work unaffected.
     */
    public function test_buy_a_b_c_direct_still_works_unaffected(): void
    {
        foreach (['A', 'B', 'C'] as $grade) {
            $response = $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
                'business_date' => $this->businessDate,
                'product_id' => $this->tomatoH->id,
                'purchase_grade' => $grade,
                'quantity' => 25,
                'unit_price' => 20.00,
                'purchase_source' => $grade === 'B' ? 'green_leaf_direct_purchase' : 'shop_order',
                'return_to' => 'bulk-buy',
            ]);

            $response->assertSessionHasNoErrors();

            $cart = PurchaserCart::query()
                ->where('user_id', $this->purchaser->id)
                ->where('purchase_grade', $grade)
                ->where('status', 'draft')
                ->firstOrFail();

            $this->assertCount(1, $cart->items);
            $this->assertEquals($grade, $cart->items->first()->grade);
            $this->assertEquals(25.0, (float) $cart->items->first()->quantity);
            $this->assertEquals(500.0, (float) $cart->items->first()->line_total);
        }
    }

    /**
     * Test shortage is identifiable when sorted output is less than approved requirements.
     */
    public function test_shortage_identifiable_when_sorted_less_than_demand(): void
    {
        // Approved demand: A: 100 kg, B: 50 kg, C: 30 kg
        // Warehouse sorts: A: 80 kg, B: 70 kg, C: 50 kg (Total = 200 kg)
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_grade' => 'MIXED',
            'grading_mode' => 'sort_required',
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'total_kg' => 200.0,
            'cost_per_kg' => 15.0,
        ]);

        $sortingAction = app(ProcessBatchSortingAction::class);
        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 80.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 70.0],
                ['grade' => ProductGrade::GradeC, 'quantity' => 50.0],
                ['grade' => ProductGrade::Damage, 'quantity' => 0.0],
            ],
            notes: null
        );

        $sortingAction->execute($batch, $sortingData, $this->receiver->id);

        $stockA = (float) StockMovement::query()->where('product_id', $this->tomatoH->id)->where('grade', ProductGrade::GradeA)->sum('quantity');
        $stockB = (float) StockMovement::query()->where('product_id', $this->tomatoH->id)->where('grade', ProductGrade::GradeB)->sum('quantity');
        $stockC = (float) StockMovement::query()->where('product_id', $this->tomatoH->id)->where('grade', ProductGrade::GradeC)->sum('quantity');

        $this->assertEquals(80.0, $stockA);
        $this->assertEquals(70.0, $stockB);
        $this->assertEquals(50.0, $stockC);

        // Grade A is short by 20 kg (approved 100 - available 80)
        $shortageA = max(0.0, 100.0 - $stockA);
        $surplusB = max(0.0, $stockB - 50.0);
        $surplusC = max(0.0, $stockC - 30.0);

        $this->assertEquals(20.0, $shortageA);
        $this->assertEquals(20.0, $surplusB);
        $this->assertEquals(20.0, $surplusC);
    }

    /**
     * Test purchaser adding mixed lot using total lot amount (e.g. 77 kg @ ₹3,000 total).
     */
    public function test_buy_mixed_with_total_lot_amount_input(): void
    {
        $response = $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $this->businessDate,
            'product_id' => $this->tomatoH->id,
            'purchase_grade' => 'MIXED',
            'quantity' => 77.0,
            'total_amount' => 3000.0,
        ]);

        $response->assertSessionHasNoErrors();

        $cart = PurchaserCart::query()
            ->where('user_id', $this->purchaser->id)
            ->where('purchase_grade', 'MIXED')
            ->firstOrFail();

        $item = $cart->items()->firstOrFail();
        $this->assertEquals(77.0, (float) $item->quantity);
        $this->assertEquals(3000.0, (float) $item->line_total);
        $this->assertEquals(round(3000.0 / 77.0, 4), (float) $item->unit_price);

        // Submit the cart
        $submitResponse = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'cart_id' => $cart->id,
            'business_date' => $this->businessDate,
            'purchase_grade' => 'MIXED',
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Credit',
            'bill_number' => 'BILL-MIXED-77',
            'items' => [
                $item->id => [
                    'quantity' => 77.0,
                    'unit_price' => round(3000.0 / 77.0, 4),
                ],
            ],
        ]);

        $submitResponse->assertSessionHasNoErrors();

        $invoice = PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->firstOrFail();
        $this->assertEquals(3000.0, (float) $invoice->amount);
    }
}
