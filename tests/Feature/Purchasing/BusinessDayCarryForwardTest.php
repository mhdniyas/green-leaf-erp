<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaseBusinessDayCarryForward;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessDayCarryForwardTest extends TestCase
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

    /**
     * Helper to create an Advance Receive for a Business Day.
     */
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
     * Test A: Closed day with carry exists, normal Add Bill without carry_forward_uuid -> BLOCK.
     */
    public function test_closed_day_without_carry_forward_uuid_blocks_normal_add_bill(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $this->expectException(ValidationException::class);

        $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-FAIL-01',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);
    }

    /**
     * Test B: Closed day + valid OPEN carry_forward_uuid -> CREATE new carry Bill allowed.
     */
    public function test_closed_day_with_valid_open_carry_forward_uuid_allows_carry_bill(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        $grnBill = $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-01-001',
            'received_at' => '2026-09-02 08:30:00',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertNotNull($grnBill);
        $carry->refresh();
        $this->assertEquals('resolved', $carry->status);
    }

    /**
     * Test C: Resolved carry task -> BLOCK.
     */
    public function test_resolved_carry_task_blocks_bill_creation(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        // Complete carry task
        $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-01-FULL',
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

        // Attempt second bill on resolved carry task -> should block
        $this->expectException(ValidationException::class);

        $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-01-EXTRA',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 10.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);
    }

    /**
     * Test D: Carry Product A, attempt Product B -> BLOCK.
     */
    public function test_carry_product_a_attempt_product_b_blocks(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        $this->expectException(ValidationException::class);

        $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-WRONG-PROD',
            'items' => [
                [
                    'product_id' => $this->productBanana->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'piece',
                    'unit_price' => 10.0,
                ],
            ],
        ], $this->user->id);
    }

    /**
     * Test E: Wrong warehouse -> BLOCK.
     */
    public function test_wrong_warehouse_carry_forward_blocks(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $dayWh1 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($dayWh1, $this->productTomato, 100.0, 'kg');
        $service->close($dayWh1, $this->user->id, 'Closing Wh1', 'carry_forward');

        $dayWh2 = $service->open($this->warehouse2->id, '2026-09-01', $this->user->id);
        $service->close($dayWh2, $this->user->id, 'Closing Wh2', 'carry_forward');

        $carryWh1 = PurchaseBusinessDayCarryForward::where('warehouse_id', $this->warehouse1->id)->firstOrFail();

        $this->expectException(ValidationException::class);

        $service->createBusinessDayBill($dayWh2, [
            'carry_forward_uuid' => $carryWh1->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-WRONG-WH',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);
    }

    /**
     * Test F: Carry from Day A used on Day B -> BLOCK.
     */
    public function test_carry_from_day_a_used_on_day_b_blocks(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $day02 = $service->open($this->warehouse1->id, '2026-09-02', $this->user->id);
        $this->createAdvance($day02, $this->productTomato, 50.0, 'kg');
        $service->close($day02, $this->user->id, 'Closing day 2', 'carry_forward');

        $carryDay01 = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        $this->expectException(ValidationException::class);

        // Attempting to submit carry for Day 01 against Day 02 target
        $service->createBusinessDayBill($day02, [
            'carry_forward_uuid' => $carryDay01->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-CROSS-DAY',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);
    }

    /**
     * Test G: Partial Bill -> carry stays OPEN.
     */
    public function test_partial_bill_keeps_carry_forward_open(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-PARTIAL',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 40.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $carry->refresh();
        $this->assertEquals('open', $carry->status);
    }

    /**
     * Test H: Final Bill completes pending -> carry becomes RESOLVED.
     */
    public function test_final_bill_completes_pending_and_resolves_carry_forward(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        // 1. First partial bill (40kg)
        $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-P1',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 40.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $carry->refresh();
        $this->assertEquals('open', $carry->status);

        // 2. Final bill completing remaining 60kg
        $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-P2',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $carry->refresh();
        $this->assertEquals('resolved', $carry->status);
    }

    /**
     * Test I: GET Business Day show -> zero database mutation.
     */
    public function test_get_business_day_show_does_zero_database_mutations(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();
        $updatedAtBefore = $carry->updated_at->toDateTimeString();

        $response = $this->actingAs($this->user)
            ->get(route('purchasing.business-days.show', $day01->uuid));

        $response->assertStatus(200);

        $carry->refresh();
        $this->assertEquals('open', $carry->status);
        $this->assertEquals($updatedAtBefore, $carry->updated_at->toDateTimeString());
    }

    /**
     * Test J: GET Business Day history -> zero database mutation.
     */
    public function test_get_business_day_history_does_zero_database_mutations(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();
        $updatedAtBefore = $carry->updated_at->toDateTimeString();

        $response = $this->actingAs($this->user)
            ->get(route('purchasing.business-days.index', ['history' => 1, 'warehouse_id' => $this->warehouse1->id]));

        $response->assertStatus(200);

        $carry->refresh();
        $this->assertEquals('open', $carry->status);
        $this->assertEquals($updatedAtBefore, $carry->updated_at->toDateTimeString());
    }

    /**
     * Test K: Edit old Bill on closed carry day -> BLOCK.
     */
    public function test_edit_old_bill_on_closed_carry_day_blocks(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);

        $grnBill = $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-EDIT-TEST',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $this->expectException(ValidationException::class);

        $service->updateBusinessDayBill($grnBill, [
            'bill_number' => 'INV-EDIT-MODIFIED',
            'items' => [
                [
                    'id' => $grnBill->items->first()->id,
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);
    }

    /**
     * Test L: Edit Advance on closed carry day -> BLOCK.
     */
    public function test_edit_advance_on_closed_carry_day_blocks(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $this->expectException(ValidationException::class);

        $service->assertBusinessDayNotClosed($day01->id);
    }

    /**
     * Test M: Reopen Day with reason -> normal corrections allowed.
     */
    public function test_reopen_day_with_reason_allows_normal_corrections(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        // Reopen day with valid reason
        $reopenedDay = $service->reopen($day01, $this->user->id, 'Need to correct supplier bill details');

        $this->assertTrue($reopenedDay->isReopened());

        // Now normal add bill allowed without carry_forward_uuid
        $grnBill = $service->createBusinessDayBill($day01, [
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-REOPENED-CORRECTION',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        $this->assertNotNull($grnBill);
    }

    /**
     * Test N: Matching rule remains unchanged.
     */
    public function test_matching_rule_remains_unchanged(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $advance = $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        $billGrn = $service->createBusinessDayBill($day01, [
            'carry_forward_uuid' => $carry->uuid,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-MATCH-RULE',
            'received_at' => '2026-09-02 10:00:00',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ], $this->user->id);

        // Verify AdvanceReceiveMatch record created for day01
        $match = AdvanceReceiveMatch::where('bill_goods_received_id', $billGrn->id)->first();
        $this->assertNotNull($match);
        $this->assertEquals($advance->id, $match->advance_goods_received_id);
        $this->assertEquals(100.0, (float) $match->matched_qty);
    }

    /**
     * Test O: Retry same carry Bill request -> no duplicate stock / match.
     */
    public function test_retry_same_carry_bill_request_causes_no_duplicate_stock_or_match(): void
    {
        $service = app(PurchaserBusinessDayService::class);
        $day01 = $service->open($this->warehouse1->id, '2026-09-01', $this->user->id);
        $this->createAdvance($day01, $this->productTomato, 100.0, 'kg');
        $service->close($day01, $this->user->id, 'Closing day 1', 'carry_forward');

        $carry = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day01->id)->firstOrFail();

        $payload = [
            'carry_forward_uuid' => $carry->uuid,
            'client_submission_id' => 'RETRY-SUBMISSION-UUID-999',
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'INV-RETRY-01',
            'received_at' => '2026-09-02 11:00:00',
            'items' => [
                [
                    'product_id' => $this->productTomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ];

        // First attempt
        $grn1 = $service->createBusinessDayBill($day01, $payload, $this->user->id);
        $batchCount1 = StockBatch::count();
        $matchCount1 = AdvanceReceiveMatch::count();

        // Retry with same payload and submission ID
        $grn2 = $service->createBusinessDayBill($day01, $payload, $this->user->id);
        $batchCount2 = StockBatch::count();
        $matchCount2 = AdvanceReceiveMatch::count();

        $this->assertEquals($grn1->id, $grn2->id);
        $this->assertEquals($batchCount1, $batchCount2);
        $this->assertEquals($matchCount1, $matchCount2);
    }
}
