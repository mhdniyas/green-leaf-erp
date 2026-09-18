<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\SubmitPurchaserBusinessDayAction;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductPurchaserAllotment;
use App\Models\PurchaserCart;
use App\Models\Purchasing\PurchaserBusinessDaySubmission;
use App\Models\Purchasing\PurchaserBusinessDaySubmissionItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserBusinessDayCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchaser1;

    protected User $purchaser2;

    protected Warehouse $warehouse;

    protected Product $potato;

    protected Product $tomato;

    protected Product $banana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->purchaser1 = User::factory()->create(['name' => 'Faisal Purchaser']);
        $this->purchaser1->assignRole('purchaser');

        $this->purchaser2 = User::factory()->create(['name' => 'Rasheed Purchaser']);
        $this->purchaser2->assignRole('purchaser');

        $category = Category::create([
            'name' => 'Vegetables',
            'code' => 'VEG',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->potato = Product::create([
            'category_id' => $category->id,
            'name' => 'Potato',
            'sku' => 'POT-001',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->tomato = Product::create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->banana = Product::create([
            'category_id' => $category->id,
            'name' => 'Banana',
            'sku' => 'BAN-001',
            'unit' => 'piece',
            'is_active' => true,
        ]);

        // Set allotment active for September 2026
        ProductPurchaserAllotment::create([
            'product_id' => $this->potato->id,
            'purchaser_user_id' => $this->purchaser1->id,
            'effective_from' => '2026-09-01',
            'effective_to' => null,
            'created_by' => $this->purchaser1->id,
        ]);

        ProductPurchaserAllotment::create([
            'product_id' => $this->tomato->id,
            'purchaser_user_id' => $this->purchaser1->id,
            'effective_from' => '2026-09-01',
            'effective_to' => null,
            'created_by' => $this->purchaser1->id,
        ]);
    }

    /** 1. Purchaser calendar opens */
    public function test_purchaser_calendar_opens(): void
    {
        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show'));

        $response->assertOk();
        $response->assertViewIs('purchasing.purchaser.business_day_verification');
        $response->assertViewHas('calendar');
        $response->assertViewHas('reconciliation');
    }

    /** 2. Only authenticated purchaser data visible */
    public function test_only_authenticated_purchaser_data_visible(): void
    {
        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['purchaser_id' => $this->purchaser2->id]));

        $response->assertOk();
        // Controller should override unauthorized purchaser_id with authenticated user's ID
        $this->assertEquals($this->purchaser1->id, $response->viewData('purchaserId'));
    }

    /** 3. Current month default */
    public function test_current_month_default(): void
    {
        /** @var PurchaserBusinessDayService $service */
        $service = app(PurchaserBusinessDayService::class);
        $expectedMonth = $service->operationalDate()->format('Y-m');

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show'));

        $response->assertOk();
        $this->assertEquals($expectedMonth, $response->viewData('month'));
    }

    /** 4. Previous / Next month navigation */
    public function test_previous_and_next_month_navigation(): void
    {
        $prevResponse = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['month' => '2026-08']));

        $prevResponse->assertOk();
        $this->assertEquals('2026-08', $prevResponse->viewData('month'));

        $nextResponse = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['month' => '2026-10']));

        $nextResponse->assertOk();
        $this->assertEquals('2026-10', $nextResponse->viewData('month'));
    }

    /** 5. Not-submitted day */
    public function test_not_submitted_day(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-001',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $this->assertFalse($response->viewData('isSubmitted'));
        $response->assertSee('Not Submitted');
    }

    /** 6. Submitted 100% day */
    public function test_submitted_100_percent_day(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-100',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'direct_bill',
            'status' => 'completed',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        /** @var SubmitPurchaserBusinessDayAction $action */
        $action = app(SubmitPurchaserBusinessDayAction::class);
        $action->execute($this->purchaser1->id, '2026-09-17');

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $this->assertTrue($response->viewData('isSubmitted'));
        $response->assertSee('Submitted ✓');
        $response->assertSee('100.0%');
    }

    /** 7. Submitted pending day */
    public function test_submitted_pending_day(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-PEND',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        /** @var SubmitPurchaserBusinessDayAction $action */
        $action = app(SubmitPurchaserBusinessDayAction::class);
        $action->execute($this->purchaser1->id, '2026-09-17', null, 'Pending vendor bill');

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $this->assertTrue($response->viewData('isSubmitted'));
        $response->assertSee('0.0%');
    }

    /** 8. 62.5% submission → current 100% displays both */
    public function test_62_5_percent_submission_to_current_100_percent_displays_both(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-625',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 80,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        // Create a bill GRN for matching (received on 19 Sep)
        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-BILL',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-19',
            'received_at' => '2026-09-19 11:00:00',
            'receipt_type' => 'direct_bill',
            'status' => 'completed',
        ]);

        $billItem = GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 80,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        // Partial match of 50 kg -> 62.5%
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $item->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->potato->id,
            'matched_qty' => 50,
            'base_qty' => 50,
            'confirmed_at' => now(),
            'matched_by' => $this->purchaser1->id,
            'matched_at' => '2026-09-17 15:00:00',
        ]);

        /** @var SubmitPurchaserBusinessDayAction $action */
        $action = app(SubmitPurchaserBusinessDayAction::class);
        $action->execute($this->purchaser1->id, '2026-09-17');

        // Later match remaining 30 kg -> 100% live
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $item->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->potato->id,
            'matched_qty' => 30,
            'base_qty' => 30,
            'confirmed_at' => now(),
            'matched_by' => $this->purchaser1->id,
            'matched_at' => '2026-09-19 10:00:00',
        ]);

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('62.5%');
        $response->assertSee('100.0%');
    }

    /** 9. Original pending quantities remain visible */
    public function test_original_pending_quantities_remain_visible(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-SNAP',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 80,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-SNAPBILL',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-19',
            'received_at' => '2026-09-19 11:00:00',
            'receipt_type' => 'direct_bill',
            'status' => 'completed',
        ]);

        $billItem = GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 80,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $item->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->potato->id,
            'matched_qty' => 50,
            'base_qty' => 50,
            'confirmed_at' => now(),
            'matched_by' => $this->purchaser1->id,
            'matched_at' => '2026-09-17 15:00:00',
        ]);

        /** @var SubmitPurchaserBusinessDayAction $action */
        $action = app(SubmitPurchaserBusinessDayAction::class);
        $submission = $action->execute($this->purchaser1->id, '2026-09-17');

        // Late bill added
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $item->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->potato->id,
            'matched_qty' => 30,
            'base_qty' => 30,
            'confirmed_at' => now(),
            'matched_by' => $this->purchaser1->id,
            'matched_at' => '2026-09-19 10:00:00',
        ]);

        $this->assertEquals(30.0, (float) $submission->fresh()->items->first()->pending_qty);

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('30.00'); // Submitted pending snapshot
    }

    /** 10. Live pending becomes zero after late bill */
    public function test_live_pending_becomes_zero_after_late_bill(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-ZERO',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-ZEROBILL',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 11:00:00',
            'receipt_type' => 'direct_bill',
            'status' => 'completed',
        ]);

        $billItem = GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        /** @var SubmitPurchaserBusinessDayAction $action */
        $action = app(SubmitPurchaserBusinessDayAction::class);
        $action->execute($this->purchaser1->id, '2026-09-17');

        // Match later
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $item->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->potato->id,
            'matched_qty' => 50,
            'base_qty' => 50,
            'confirmed_at' => now(),
            'matched_by' => $this->purchaser1->id,
            'matched_at' => '2026-09-19 10:00:00',
        ]);

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $rec = $response->viewData('reconciliation');
        $this->assertEquals(0.0, $rec['total_pending_qty']);
    }

    /** 11. Empty day handled correctly */
    public function test_empty_day_handled_correctly(): void
    {
        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-02', 'month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('No physical warehouse receipts found for this business day.');
    }

    /** 12. Unit mismatch day displayed correctly */
    public function test_unit_mismatch_day_displayed_correctly(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-MISMATCH',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 100,
            'received_unit' => 'box', // Mismatch with base unit 'kg'
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $rec = $response->viewData('reconciliation');
        $this->assertTrue($rec['products'][0]['unit_mismatch']);
    }

    /** 13. Mixed-unit day does not show invalid aggregate */
    public function test_mixed_unit_day_does_not_show_invalid_aggregate(): void
    {
        // Allot Banana (piece) to purchaser1 as well
        ProductPurchaserAllotment::create([
            'product_id' => $this->banana->id,
            'purchaser_user_id' => $this->purchaser1->id,
            'effective_from' => '2026-09-01',
            'effective_to' => null,
            'created_by' => $this->purchaser1->id,
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-MIXED',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        // 80 kg Potato
        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 80,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        // 20 piece Banana
        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->banana->id,
            'received_qty' => 20,
            'received_unit' => 'piece',
            'variance' => 0.0,
            'unit_price' => 5,
        ]);

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $rec = $response->viewData('reconciliation');
        $this->assertTrue($rec['has_mixed_units']);
        $response->assertSee('Products Covered');
    }

    /** 14. Submit button only before submission */
    public function test_submit_button_only_before_submission(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-SUBBTN',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        PurchaserBusinessDaySubmission::query()->delete();

        $responseBefore = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $responseBefore->assertOk();
        $this->assertFalse($responseBefore->viewData('isSubmitted'));
        $responseBefore->assertSee('Verify & Submit Business Day', false);

        /** @var SubmitPurchaserBusinessDayAction $action */
        $action = app(SubmitPurchaserBusinessDayAction::class);
        $action->execute($this->purchaser1->id, '2026-09-17');

        $responseAfter = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $this->assertTrue($responseAfter->viewData('isSubmitted'));
        $responseAfter->assertDontSee('Verify & Submit Business Day', false);
    }

    /** 15. Submitted day cannot be resubmitted from UI */
    public function test_submitted_day_cannot_be_resubmitted_from_ui(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-RESUB',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        /** @var SubmitPurchaserBusinessDayAction $action */
        $action = app(SubmitPurchaserBusinessDayAction::class);
        $action->execute($this->purchaser1->id, '2026-09-17');

        // Post request to submit again should throw validation exception
        $response = $this->actingAs($this->purchaser1)
            ->post(route('purchaser.business-day.submit'), [
                'business_date' => '2026-09-17',
                'purchaser_id' => $this->purchaser1->id,
            ]);

        $response->assertSessionHasErrors(['business_date']);
    }

    /** 16. Note displayed after submission */
    public function test_note_displayed_after_submission(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-NOTE',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        /** @var SubmitPurchaserBusinessDayAction $action */
        $action = app(SubmitPurchaserBusinessDayAction::class);
        $action->execute($this->purchaser1->id, '2026-09-17', null, 'Vendor invoice delayed by 2 days.');

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('Vendor invoice delayed by 2 days.');
    }

    /** 17. Product rename uses snapshot name for historical submitted details */
    public function test_product_rename_uses_snapshot_name_for_historical_submitted_details(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-RENAME',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        /** @var SubmitPurchaserBusinessDayAction $action */
        $action = app(SubmitPurchaserBusinessDayAction::class);
        $action->execute($this->purchaser1->id, '2026-09-17');

        // Rename product in master database
        $this->potato->update(['name' => 'Super Premium Potato']);

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $submission = $response->viewData('submission');
        $this->assertEquals('Potato', $submission->items->first()->product_name);
    }

    /** 18. Historical unit snapshot displayed correctly */
    public function test_historical_unit_snapshot_displayed_correctly(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-20260917-UNIT',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => '2026-09-17 10:00:00',
            'receipt_type' => 'warehouse_advance',
            'status' => 'completed',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 10,
        ]);

        /** @var SubmitPurchaserBusinessDayAction $action */
        $action = app(SubmitPurchaserBusinessDayAction::class);
        $action->execute($this->purchaser1->id, '2026-09-17');

        // Master unit changes
        $this->potato->update(['unit' => 'bag']);

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();
        $submission = $response->viewData('submission');
        $this->assertEquals('kg', $submission->items->first()->unit);
    }

    /** 19. Another purchaser cannot access date detail */
    public function test_another_purchaser_cannot_access_date_detail(): void
    {
        $response = $this->actingAs($this->purchaser2)
            ->get(route('purchaser.business-day.show', ['purchaser_id' => $this->purchaser1->id]));

        $response->assertOk();
        // Purchaser2 cannot inspect Purchaser1's date detail; view resolves purchaserId = purchaser2
        $this->assertEquals($this->purchaser2->id, $response->viewData('purchaserId'));
    }

    /** 20. GET calendar/detail causes zero operational mutations */
    public function test_get_calendar_detail_causes_zero_operational_mutations(): void
    {
        $subCountBefore = PurchaserBusinessDaySubmission::count();
        $subItemCountBefore = PurchaserBusinessDaySubmissionItem::count();
        $grnItemCountBefore = GoodsReceivedItem::count();
        $cartCountBefore = PurchaserCart::count();

        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17', 'month' => '2026-09']));

        $response->assertOk();

        $this->assertEquals($subCountBefore, PurchaserBusinessDaySubmission::count());
        $this->assertEquals($subItemCountBefore, PurchaserBusinessDaySubmissionItem::count());
        $this->assertEquals($grnItemCountBefore, GoodsReceivedItem::count());
        $this->assertEquals($cartCountBefore, PurchaserCart::count());
    }
}
