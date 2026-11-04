<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GradeWorkflowVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $receiver;

    private Warehouse $warehouse;

    private ShopPriceGroup $priceGroupA;

    private Shop $shop1;

    private Shop $shop2;

    private Product $tomato;

    private Carbon $businessDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->businessDate = Carbon::parse('2026-11-04');
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00'));

        $this->mock(PurchaserBusinessDayService::class, function ($mock): void {
            $mock->shouldReceive('operationalDate')->andReturn($this->businessDate);
            $mock->shouldReceive('currentCalendarDate')->andReturn($this->businessDate);
        });

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Jane']);
        $this->purchaser->assignRole('purchaser');

        Role::firstOrCreate(['name' => 'warehouse_receiver', 'guard_name' => 'web']);
        $this->receiver = User::factory()->create(['name' => 'Receiver Bob']);
        $this->receiver->assignRole('warehouse_receiver');

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse', 'code' => 'MWH-01']);
        $this->receiver->warehouses()->attach($this->warehouse);

        $this->priceGroupA = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);

        $this->shop1 = Shop::factory()->create([
            'name' => 'Shop 1 Downtown',
            'code' => 'SH-01',
            'shop_price_group_id' => $this->priceGroupA->id,
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $this->shop2 = Shop::factory()->create([
            'name' => 'Shop 2 Uptown',
            'code' => 'SH-02',
            'shop_price_group_id' => $this->priceGroupA->id,
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'VEG-TOM-01',
            'unit' => 'kg',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        // Standard approved daily prices: Grade A = 50, Grade B = 35, Grade C = 25
        DailyPriceApproval::create([
            'product_id' => $this->tomato->id,
            'business_date' => $this->businessDate->toDateString(),
            'grade' => 'A',
            'price_a' => 50.00,
            'price_b' => 50.00,
            'price_c' => 50.00,
            'purchase_price' => 30.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->purchaser->id,
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->tomato->id,
            'business_date' => $this->businessDate->toDateString(),
            'grade' => 'B',
            'price_a' => 35.00,
            'price_b' => 35.00,
            'price_c' => 35.00,
            'purchase_price' => 20.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->purchaser->id,
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->tomato->id,
            'business_date' => $this->businessDate->toDateString(),
            'grade' => 'C',
            'price_a' => 25.00,
            'price_b' => 25.00,
            'price_c' => 25.00,
            'purchase_price' => 15.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->purchaser->id,
        ]);

        // DailyProductPrice for selling price lookups
        foreach ([['A', 50.0], ['B', 35.0], ['C', 25.0]] as [$g, $p]) {
            DailyProductPrice::create([
                'product_id' => $this->tomato->id,
                'shop_price_group_id' => $this->priceGroupA->id,
                'grade' => $g,
                'selling_price' => $p,
                'effective_date' => $this->businessDate,
                'price_source' => 'approved_daily',
                'business_date' => $this->businessDate,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createSortedBatch(string $grade, float $quantity): StockBatch
    {
        static $seq = 100;
        $batch = StockBatch::create([
            'reference' => 'BATCH-VERIFY-'.($seq++),
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => $quantity,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => $grade,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->receiver->id,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => $grade,
            'type' => StockMovementType::In,
            'quantity' => $quantity,
            'cost_per_unit' => 20.0,
            'notes' => "Sorted batch {$grade}",
        ]);

        return $batch;
    }

    /**
     * Complete Workflow Verification:
     * Loadout Add-on (A, B, C) -> Inventory movements -> Shop Invoice generation/sync -> Special Price Isolation.
     */
    public function test_complete_grade_a_b_c_workflow(): void
    {
        // 1. Stock Setup: 50 KG of A, 50 KG of B, 50 KG of C
        $batchA = $this->createSortedBatch('A', 50.0);
        $batchB = $this->createSortedBatch('B', 50.0);
        $batchC = $this->createSortedBatch('C', 50.0);

        // 2. Initial Order: Shop 1 orders Tomato Grade A — 10 KG
        $order = ShopOrder::factory()->approved()->create([
            'order_number' => 'ORD-TEST-001',
            'shop_id' => $this->shop1->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'kg',
            'locked_price_group_id' => $this->priceGroupA->id,
            'locked_selling_price' => 50.0,
            'line_total' => 500.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);

        // Consume stock for initial Grade A line
        StockMovement::create([
            'batch_id' => $batchA->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::Out,
            'quantity' => 10.0,
            'cost_per_unit' => 20.0,
            'notes' => 'Initial loadout',
        ]);

        // 3. Step 2 & 3: Flutter Add-on adds:
        //    - Tomato Grade B — 5 KG
        //    - Tomato Grade C — 3 KG
        $responseB = $this->actingAs($this->receiver)
            ->withHeaders(['X-Client-Supports-Multi-Grade' => 'true'])
            ->postJson("/api/v1/warehouse/loadout/{$order->order_number}/addon", [
                'product_id' => $this->tomato->id,
                'quantity' => 5.0,
                'product_grade' => 'B',
            ]);

        $responseB->assertOk();
        $responseB->assertJsonFragment(['success' => true]);

        $responseC = $this->actingAs($this->receiver)
            ->withHeaders(['X-Client-Supports-Multi-Grade' => 'true'])
            ->postJson("/api/v1/warehouse/loadout/{$order->order_number}/addon", [
                'product_id' => $this->tomato->id,
                'quantity' => 3.0,
                'product_grade' => 'C',
            ]);

        $responseC->assertOk();
        $responseC->assertJsonFragment(['success' => true]);

        // VERIFICATION 1: All three lines coexist independently in ShopOrderItem
        $orderItems = ShopOrderItem::where('shop_order_id', $order->id)
            ->where('product_id', $this->tomato->id)
            ->orderBy('product_grade')
            ->get();

        $this->assertCount(3, $orderItems);
        $this->assertSame('A', $orderItems[0]->product_grade);
        $this->assertSame(10.0, (float) $orderItems[0]->loaded_qty);
        $this->assertSame(50.0, (float) $orderItems[0]->locked_selling_price);

        $this->assertSame('B', $orderItems[1]->product_grade);
        $this->assertSame(5.0, (float) $orderItems[1]->loaded_qty);
        $this->assertSame(35.0, (float) $orderItems[1]->locked_selling_price);

        $this->assertSame('C', $orderItems[2]->product_grade);
        $this->assertSame(3.0, (float) $orderItems[2]->loaded_qty);
        $this->assertSame(25.0, (float) $orderItems[2]->locked_selling_price);

        // VERIFICATION 2: Inventory deductions per grade
        $movementsA = StockMovement::where('batch_id', $batchA->id)->where('type', StockMovementType::Out)->sum('quantity');
        $movementsB = StockMovement::where('batch_id', $batchB->id)->where('type', StockMovementType::Out)->sum('quantity');
        $movementsC = StockMovement::where('batch_id', $batchC->id)->where('type', StockMovementType::Out)->sum('quantity');

        $this->assertSame(10.0, (float) $movementsA);
        $this->assertSame(5.0, (float) $movementsB);
        $this->assertSame(3.0, (float) $movementsC);

        // VERIFICATION 3: Duplicate Add-on for existing Grade B is rejected
        $dupResponse = $this->actingAs($this->receiver)
            ->withHeaders(['X-Client-Supports-Multi-Grade' => 'true'])
            ->postJson("/api/v1/warehouse/loadout/{$order->order_number}/addon", [
                'product_id' => $this->tomato->id,
                'quantity' => 2.0,
                'product_grade' => 'B',
            ]);
        $dupResponse->assertStatus(422);

        // VERIFICATION 4: Failed request (exceeds stock) leaves no partial changes
        $movementsCountBefore = StockMovement::count();
        $failResponse = $this->actingAs($this->receiver)
            ->withHeaders(['X-Client-Supports-Multi-Grade' => 'true'])
            ->postJson("/api/v1/warehouse/loadout/{$order->order_number}/addon", [
                'product_id' => $this->tomato->id,
                'quantity' => 999.0,
                'product_grade' => 'B',
            ]);
        $failResponse->assertStatus(422);
        $this->assertSame($movementsCountBefore, StockMovement::count());

        // 4. Shop Invoice Generation / Sync
        $invoiceService = app(ShopInvoiceService::class);
        $genResult = $invoiceService->generateForBusinessDate($this->businessDate->toDateString(), (int) $this->receiver->id);

        $invoice = ShopInvoice::where('shop_order_id', $order->id)->first();
        $this->assertNotNull($invoice);

        $invoiceItems = ShopInvoiceItem::where('shop_invoice_id', $invoice->id)
            ->where('product_id', $this->tomato->id)
            ->orderBy('product_grade')
            ->get();

        $this->assertCount(3, $invoiceItems);
        // Grade A: 10 KG * 50.00 = 500.00
        $this->assertSame('A', $invoiceItems[0]->product_grade);
        $this->assertSame(10.0, (float) $invoiceItems[0]->delivered_qty);
        $this->assertSame(50.00, (float) $invoiceItems[0]->unit_price);
        $this->assertSame(500.00, (float) $invoiceItems[0]->line_subtotal);

        // Grade B: 5 KG * 35.00 = 175.00
        $this->assertSame('B', $invoiceItems[1]->product_grade);
        $this->assertSame(5.0, (float) $invoiceItems[1]->delivered_qty);
        $this->assertSame(35.00, (float) $invoiceItems[1]->unit_price);
        $this->assertSame(175.00, (float) $invoiceItems[1]->line_subtotal);

        // Grade C: 3 KG * 25.00 = 75.00
        $this->assertSame('C', $invoiceItems[2]->product_grade);
        $this->assertSame(3.0, (float) $invoiceItems[2]->delivered_qty);
        $this->assertSame(25.00, (float) $invoiceItems[2]->unit_price);
        $this->assertSame(75.00, (float) $invoiceItems[2]->line_subtotal);

        // Total = 500 + 175 + 75 = 750.00
        $this->assertSame(750.00, (float) $invoice->fresh()->final_total);

        // 5. Special Price Isolation:
        // Set Special Price for Grade B in Shop 1 = 30.00 via Mobile API
        $invoiceItemB = $invoiceItems[1];
        $specialPriceResponse = $this->actingAs($this->purchaser)
            ->postJson(route('api.v1.purchaser.special-price.approve'), [
                'id' => $invoiceItemB->id,
                'item_id' => $invoiceItemB->id,
                'order_number' => $invoice->invoice_number,
                'shop_id' => $this->shop1->id,
                'product_id' => $this->tomato->id,
                'product_grade' => 'B',
                'special_price' => 30.00,
                'business_date' => $this->businessDate->toDateString(),
            ]);

        $specialPriceResponse->assertOk();
        $specialPriceResponse->assertJsonFragment([
            'special_price' => 30.00,
            'product_grade' => 'B',
            'invoice_repriced' => true,
        ]);

        // VERIFICATION 5: Invoice items after Special Price
        $freshInvoice = $invoice->fresh(['items']);
        $reFreshItems = $freshInvoice->items()->where('product_id', $this->tomato->id)->orderBy('product_grade')->get();

        // Grade A remains unchanged at 50.00
        $this->assertSame(50.00, (float) $reFreshItems[0]->unit_price);
        $this->assertSame(500.00, (float) $reFreshItems[0]->line_subtotal);

        // Grade B receives Special Price 30.00
        $this->assertSame(30.00, (float) $reFreshItems[1]->unit_price);
        $this->assertSame(150.00, (float) $reFreshItems[1]->line_subtotal);

        // Grade C remains unchanged at 25.00
        $this->assertSame(25.00, (float) $reFreshItems[2]->unit_price);
        $this->assertSame(75.00, (float) $reFreshItems[2]->line_subtotal);

        // Invoice total recalculated: 500 + 150 + 75 = 725.00
        $this->assertSame(725.00, (float) $freshInvoice->final_total);

        // VERIFICATION 6: Isolation - Shop 2 resolves normal price for Grade B (35.00)
        $resolver = app(ApprovedDailyPriceResolver::class);
        $resShop2B = $resolver->resolve($this->tomato, $this->shop2, $this->businessDate->toDateString(), ProductGrade::GradeB);
        $this->assertSame(35.00, $resShop2B['price']);
        $this->assertSame('normal', $resShop2B['source']);

        // VERIFICATION 7: Isolation - Another business date resolves normal price
        $tomorrow = '2026-11-05';
        DailyPriceApproval::create([
            'product_id' => $this->tomato->id,
            'business_date' => $tomorrow,
            'grade' => 'B',
            'price_a' => 38.00,
            'price_b' => 38.00,
            'price_c' => 38.00,
            'purchase_price' => 22.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->purchaser->id,
        ]);
        $resTomorrowB = $resolver->resolve($this->tomato, $this->shop1, $tomorrow, ProductGrade::GradeB);
        $this->assertSame(38.00, $resTomorrowB['price']);
        $this->assertSame('normal', $resTomorrowB['source']);

        // VERIFICATION 8: Finalized invoice locking
        $freshInvoice->update(['status' => 'finalized']);
        $lockedResponse = $this->actingAs($this->purchaser)
            ->postJson(route('api.v1.purchaser.special-price.approve'), [
                'id' => $invoiceItemB->id,
                'item_id' => $invoiceItemB->id,
                'order_number' => $freshInvoice->invoice_number,
                'shop_id' => $this->shop1->id,
                'product_id' => $this->tomato->id,
                'product_grade' => 'B',
                'special_price' => 28.00,
                'business_date' => $this->businessDate->toDateString(),
            ]);
        $lockedResponse->assertStatus(422);
        $this->assertStringContainsString('finalized', (string) $lockedResponse->json('message'));
    }
}
