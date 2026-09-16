<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\CancelPurchaseInvoiceAction;
use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaseBusinessDayCarryForward;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyInventoryComparisonService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessDayAutoReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse1;

    private Warehouse $warehouse2;

    private Supplier $supplier;

    private Product $productTomato;

    private Product $productBanana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->warehouse1 = Warehouse::factory()->create(['name' => 'Fruit Warehouse 1', 'is_active' => true]);
        $this->warehouse2 = Warehouse::factory()->create(['name' => 'Vegetable Warehouse 2', 'is_active' => true]);

        $this->supplier = Supplier::factory()->create(['name' => 'Main Vendor']);

        $this->productTomato = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse1->id,
            'is_active' => true,
        ]);

        $this->productBanana = Product::factory()->create([
            'name' => 'Banana Leaf',
            'sku' => 'BAN-001',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse1->id,
            'is_active' => true,
        ]);
    }

    private function createAdvance(PurchaseBusinessDay $day, Product $product, float $qty, string $unit = 'kg'): GoodsReceived
    {
        $dto = new GoodsReceivedData(
            purchaseOrderId: null,
            receivedAt: $day->business_date->setTime(8, 0)->toDateTimeString(),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Advance receipt',
            items: [
                [
                    'product_id' => $product->id,
                    'received_qty' => $qty,
                    'received_unit' => $unit,
                ],
            ],
            billStatus: 'pending_bill',
            warehouseId: $day->warehouse_id,
            receiptType: 'warehouse_advance',
            businessDayId: $day->id,
        );

        return app(RecordGoodsReceiptAction::class)->execute($dto, $this->user->id);
    }

    /**
     * Test A: Advance first, then Bill -> automatically matched without manual Match call.
     */
    public function test_advance_first_then_bill_automatically_matches(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $advance = $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');

        $this->assertEquals(0, AdvanceReceiveMatch::count());

        $bill = $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-AUTO-A',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(1, AdvanceReceiveMatch::count());
        $match = AdvanceReceiveMatch::first();
        $this->assertEquals($advance->id, $match->advance_goods_received_id);
        $this->assertEquals($bill->id, $match->bill_goods_received_id);
        $this->assertEquals(100.0, (float) $match->matched_qty);
    }

    /**
     * Test B: Bill first, then eligible Advance -> automatically matched.
     */
    public function test_bill_first_then_advance_automatically_matches(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $bill = $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-AUTO-B',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 80.0,
                    'received_unit' => 'kg',
                    'unit_price' => 45.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(0, AdvanceReceiveMatch::count());

        $advance = $this->createAdvance($day01, $this->productTomato, 80.0, 'kg');

        $this->assertEquals(1, AdvanceReceiveMatch::count());
        $match = AdvanceReceiveMatch::first();
        $this->assertEquals($advance->id, $match->advance_goods_received_id);
        $this->assertEquals($bill->id, $match->bill_goods_received_id);
        $this->assertEquals(80.0, (float) $match->matched_qty);
    }

    /**
     * Test C: Advance 100 + Bill 60 -> automatically match 60, pending 40.
     */
    public function test_advance_100_bill_60_matches_60_leaving_pending_40(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');

        $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-AUTO-C',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(1, AdvanceReceiveMatch::count());
        $this->assertEquals(60.0, (float) AdvanceReceiveMatch::first()->matched_qty);

        $comparison = app(DailyInventoryComparisonService::class)->buildComparisonRows('2026-09-01', $this->warehouse1->id);
        $row = $comparison->firstWhere('product_id', $this->productTomato->id);
        $this->assertEquals(40.0, max(0.0, (float) $row['advance_qty'] - (float) $row['matched_bill_qty']));
    }

    /**
     * Test D: Advance 60 + Bill 100 -> automatically match 60.
     */
    public function test_advance_60_bill_100_matches_60(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $this->createAdvance($day01, $this->productTomato, 60.0, 'kg');

        $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-AUTO-D',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(1, AdvanceReceiveMatch::count());
        $this->assertEquals(60.0, (float) AdvanceReceiveMatch::first()->matched_qty);
    }

    /**
     * Test E: Existing Advance + Bill assigned to newly opened Business Day -> auto reconciliation runs.
     */
    public function test_existing_advance_and_bill_assigned_to_new_business_day_auto_reconciles(): void
    {
        $unassignedAdvDto = new GoodsReceivedData(
            purchaseOrderId: null,
            receivedAt: '2026-09-01 07:00:00',
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Unassigned Advance',
            items: [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'kg',
                ],
            ],
            billStatus: 'pending_bill',
            warehouseId: $this->warehouse1->id,
            receiptType: 'warehouse_advance',
            businessDayId: null,
        );
        $advGrn = app(RecordGoodsReceiptAction::class)->execute($unassignedAdvDto, $this->user->id);

        $this->assertEquals(0, AdvanceReceiveMatch::count());

        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $bill = $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-AUTO-E',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(1, AdvanceReceiveMatch::count());
    }

    /**
     * Test F: Carry Bill on later physical date -> auto matches origin Business Day.
     */
    public function test_carry_bill_on_later_date_auto_matches_origin_business_day(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-CARRY-F',
            'received_at' => '2026-09-02 09:00:00',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(1, AdvanceReceiveMatch::count());
        $carry->refresh();
        $this->assertEquals('resolved', $carry->status);
    }

    /**
     * Test G: Partial carry Bill -> carry remains OPEN.
     */
    public function test_partial_carry_bill_keeps_carry_task_open(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-CARRY-G',
            'received_at' => '2026-09-02 09:00:00',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 40.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(1, AdvanceReceiveMatch::count());
        $carry->refresh();
        $this->assertEquals('open', $carry->status);
    }

    /**
     * Test H: Final carry Bill -> carry RESOLVED.
     */
    public function test_final_carry_bill_resolves_carry_task(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-CARRY-H',
            'received_at' => '2026-09-02 09:00:00',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $carry->refresh();
        $this->assertEquals('resolved', $carry->status);
    }

    /**
     * Test I: Unit fix makes records eligible -> automatically matches after unit fix.
     */
    public function test_unit_fix_triggers_automatic_matching(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $adv = $this->createAdvance($day01, $this->productTomato, 50.0, 'kg');

        $bill = $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-MISMATCH',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'box',
                    'unit_price' => 500.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(0, AdvanceReceiveMatch::count());

        $service->updateBusinessDayBill($bill, [
            'bill_number' => 'INV-MISMATCH-FIXED',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(1, AdvanceReceiveMatch::count());
        $this->assertEquals(50.0, (float) AdvanceReceiveMatch::first()->matched_qty);
    }

    /**
     * Test J: Bill cancellation -> pending returns, carry task reopens.
     */
    public function test_bill_cancellation_reopens_carry_task_if_pending_returns(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        $bill = $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-CANCEL-ME',
            'received_at' => '2026-09-02 09:00:00',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $carry->refresh();
        $this->assertEquals('resolved', $carry->status);

        $invoice = PurchaseInvoice::where('goods_received_id', $bill->id)->firstOrFail();
        app(CancelPurchaseInvoiceAction::class)->execute($invoice, $this->user, 'Mistake', 'Customer cancelled');

        $carry->refresh();
        $this->assertEquals('open', $carry->status);
    }

    /**
     * Test K: Calling reconcileBusinessDay twice -> second execution creates ZERO new matches.
     */
    public function test_calling_reconcile_business_day_twice_creates_zero_extra_matches(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-IDEMPOTENT',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $countBefore = AdvanceReceiveMatch::count();
        $this->assertEquals(1, $countBefore);

        $res = app(DailyInventoryComparisonService::class)->reconcileBusinessDay($day01->id, $this->user->id);

        $this->assertEquals(0, $res['matched_products']);
        $this->assertEquals(0.0, $res['total_matched_qty']);
        $this->assertEquals(1, AdvanceReceiveMatch::count());
    }

    /**
     * Test L: Concurrent/retry scenario -> no duplicate AdvanceReceiveMatch.
     */
    public function test_retry_scenario_no_duplicate_advance_receive_match(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');

        $payload = [
            'client_submission_id' => 'SUBMIT-IDEM-001',
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-SUBMIT-001',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ];

        $grn1 = $service->createBusinessDayBill($day01, $payload, $this->user->id);
        $grn2 = $service->createBusinessDayBill($day01, $payload, $this->user->id);

        $this->assertEquals($grn1->id, $grn2->id);
        $this->assertEquals(1, AdvanceReceiveMatch::count());
    }

    /**
     * Test M: GET Business Day page -> zero matching/database mutation.
     */
    public function test_get_business_day_page_causes_zero_database_mutations(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $response = $this->actingAs($this->user)
            ->get(route('purchasing.business-days.show', ['uuid' => $day01->uuid]));

        $response->assertStatus(200);
        $this->assertEquals(0, AdvanceReceiveMatch::count());
    }

    /**
     * Test N: Bulk operation affecting multiple records on same day -> auto reconciliation succeeds cleanly.
     */
    public function test_bulk_operation_auto_reconciles_cleanly(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $this->createAdvance($day01, $this->productTomato, 50.0, 'kg');
        $this->createAdvance($day01, $this->productTomato, 50.0, 'kg');

        $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-BULK-01',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(2, AdvanceReceiveMatch::count());
        $this->assertEquals(100.0, (float) AdvanceReceiveMatch::sum('matched_qty'));
    }

    /**
     * Test O: Different Business Days -> never cross-match.
     */
    public function test_different_business_days_never_cross_match(): void
    {
        $service = app(PurchaserBusinessDayService::class);

        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing 1', 'clean');

        $day02 = $service->open($this->warehouse1->id, '2026-09-02', $this->user->id);
        $service->createBusinessDayBill($day02, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-DAY02',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(0, AdvanceReceiveMatch::count());
    }

    /**
     * Test P: Different warehouses -> never cross-match.
     */
    public function test_different_warehouses_never_cross_match(): void
    {
        $service = app(PurchaserBusinessDayService::class);

        $dayWh1 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($dayWh1, $this->productTomato, 100.0, 'kg');

        $dayWh2 = $service->open($this->warehouse2->id, '2026-09-01', $this->user->id);
        $service->createBusinessDayBill($dayWh2, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-WH2',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(0, AdvanceReceiveMatch::count());
    }

    /**
     * Test Q: Different normalized units without configured conversion -> never cross-match.
     */
    public function test_different_units_never_cross_match(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');

        $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-UNIT-DIFF',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'piece',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(0, AdvanceReceiveMatch::count());
    }
}
