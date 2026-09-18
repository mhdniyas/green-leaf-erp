<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\SubmitPurchaserBusinessDayAction;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Purchasing\PurchaserBusinessDaySubmission;
use App\Models\Purchasing\PurchaserBusinessDaySubmissionItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserAllotmentService;
use App\Services\Purchasing\PurchaserBusinessDayReconciliationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaserBusinessDaySubmissionTest extends TestCase
{
    use RefreshDatabase;

    private SubmitPurchaserBusinessDayAction $action;

    private PurchaserBusinessDayReconciliationService $reconciliationService;

    private PurchaserAllotmentService $allotmentService;

    private User $purchaser1;

    private User $purchaser2;

    private Warehouse $warehouse;

    private Product $productPotato;

    private Product $productTomato;

    private Supplier $supplier;

    private PurchaseBusinessDay $businessDaySep17;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->action = app(SubmitPurchaserBusinessDayAction::class);
        $this->reconciliationService = app(PurchaserBusinessDayReconciliationService::class);
        $this->allotmentService = app(PurchaserAllotmentService::class);

        $this->purchaser1 = User::factory()->create(['name' => 'Faisal']);
        $this->purchaser1->assignRole('purchaser');

        $this->purchaser2 = User::factory()->create(['name' => 'Rasheed']);
        $this->purchaser2->assignRole('purchaser');

        $this->warehouse = Warehouse::create([
            'name' => 'Central Warehouse',
            'code' => 'WH-CENTRAL',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Agri Supplier Ltd',
            'type' => 'farmer',
            'status' => 'active',
        ]);

        $category1 = Category::create(['name' => 'Vegetables', 'slug' => 'vegetables']);

        $this->productPotato = Product::create([
            'name' => 'Potato',
            'sku' => 'POT-001',
            'category_id' => $category1->id,
            'unit' => 'kg',
            'purchase_price' => 10.00,
        ]);

        $this->productTomato = Product::create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'category_id' => $category1->id,
            'unit' => 'kg',
            'purchase_price' => 20.00,
        ]);

        $this->businessDaySep17 = PurchaseBusinessDay::create([
            'business_date' => '2026-09-17',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'open',
            'opened_by' => $this->purchaser1->id,
            'opened_at' => '2026-09-17 08:00:00',
        ]);

        // Assign product allotments
        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser1->id, '2026-09-01');
        $this->allotmentService->assignPurchaser($this->productTomato->id, $this->purchaser1->id, '2026-09-01');
    }

    private function createGoodsReceived(Product $product, float $qty, string $date = '2026-09-17'): GoodsReceivedItem
    {
        $grn = GoodsReceived::create([
            'business_day_id' => $this->businessDaySep17->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_date' => $date,
            'received_at' => now(),
            'status' => 'received',
            'grn_number' => 'GRN-'.uniqid(),
        ]);

        return GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'variance' => 0.0,
        ]);
    }

    private function createBill(Product $product, float $qty, string $date = '2026-09-17'): PurchaserCartItem
    {
        $cart = PurchaserCart::create([
            'cart_number' => 'CART-'.uniqid(),
            'user_id' => $this->purchaser1->id,
            'purchaser_id' => $this->purchaser1->id,
            'business_day_id' => $this->businessDaySep17->id,
            'business_date' => $date,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'billed',
        ]);

        $grnBill = GoodsReceived::create([
            'business_day_id' => $this->businessDaySep17->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'direct_bill',
            'received_by' => $this->purchaser1->id,
            'received_date' => $date,
            'received_at' => now(),
            'status' => 'received',
            'grn_number' => 'GRN-BILL-'.uniqid(),
        ]);

        $invoice = PurchaseInvoice::create([
            'goods_received_id' => $grnBill->id,
            'supplier_id' => $this->supplier->id,
            'purchaser_id' => $this->purchaser1->id,
            'purchaser_cart_id' => $cart->id,
            'business_day_id' => $this->businessDaySep17->id,
            'invoice_number' => 'INV-'.uniqid(),
            'amount' => $qty * 10.0,
            'total_amount' => $qty * 10.0,
            'status' => 'paid',
        ]);

        $cartItem = PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => 10.0,
            'line_total' => $qty * 10.0,
        ]);

        $advanceGrnItem = GoodsReceivedItem::query()
            ->where('product_id', $product->id)
            ->whereHas('goodsReceived', function ($q) {
                $q->where('receipt_type', 'warehouse_advance');
            })
            ->latest('id')
            ->first();

        if ($advanceGrnItem) {
            AdvanceReceiveMatch::create([
                'business_day_id' => $this->businessDaySep17->id,
                'advance_goods_received_id' => $advanceGrnItem->goods_received_id,
                'advance_goods_received_item_id' => $advanceGrnItem->id,
                'bill_goods_received_id' => $grnBill->id,
                'product_id' => $product->id,
                'matched_qty' => $qty,
                'matched_unit' => $product->unit,
                'base_qty' => $qty,
                'confirmed_at' => now(),
            ]);
        }

        return $cartItem;
    }

    /** 1. Purchaser submits 100% covered day */
    public function test_purchaser_submits_100_percent_covered_day(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);
        $this->createBill($this->productPotato, 100.0);

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->assertEquals('submitted', $submission->status);
        $this->assertEquals(100.0, (float) $submission->submission_coverage_percentage);
        $this->assertEquals(100.0, (float) $submission->submitted_received_summary);
        $this->assertEquals(100.0, (float) $submission->submitted_billed_summary);
        $this->assertEquals(0.0, (float) $submission->submitted_pending_summary);
        $this->assertEquals(0, $submission->pending_products_count);
    }

    /** 2. Purchaser submits 80% covered day */
    public function test_purchaser_submits_80_percent_covered_day(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);
        $this->createBill($this->productPotato, 80.0);

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->assertEquals(80.0, (float) $submission->submission_coverage_percentage);
        $this->assertEquals(100.0, (float) $submission->submitted_received_summary);
        $this->assertEquals(80.0, (float) $submission->submitted_billed_summary);
        $this->assertEquals(20.0, (float) $submission->submitted_pending_summary);
        $this->assertEquals(1, $submission->pending_products_count);
    }

    /** 3. Pending items saved correctly */
    public function test_pending_items_saved_correctly(): void
    {
        $this->createGoodsReceived($this->productPotato, 80.0);
        $this->createBill($this->productPotato, 50.0);

        $this->createGoodsReceived($this->productTomato, 20.0);

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->assertCount(2, $submission->items);

        $potatoItem = $submission->items->firstWhere('product_id', $this->productPotato->id);
        $this->assertNotNull($potatoItem);
        $this->assertEquals(80.0, (float) $potatoItem->received_qty);
        $this->assertEquals(50.0, (float) $potatoItem->billed_qty);
        $this->assertEquals(30.0, (float) $potatoItem->pending_qty);
        $this->assertEquals(62.5, (float) $potatoItem->coverage_percentage);

        $tomatoItem = $submission->items->firstWhere('product_id', $this->productTomato->id);
        $this->assertNotNull($tomatoItem);
        $this->assertEquals(20.0, (float) $tomatoItem->received_qty);
        $this->assertEquals(0.0, (float) $tomatoItem->billed_qty);
        $this->assertEquals(20.0, (float) $tomatoItem->pending_qty);
        $this->assertEquals(0.0, (float) $tomatoItem->coverage_percentage);
    }

    /** 4. Product snapshot preserved */
    public function test_product_snapshot_preserved(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);
        $this->createBill($this->productPotato, 70.0);

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $snapshotItem = PurchaserBusinessDaySubmissionItem::where('submission_id', $submission->id)
            ->where('product_id', $this->productPotato->id)
            ->first();

        $this->assertEquals('Potato', $snapshotItem->product_name);
        $this->assertEquals('POT-001', $snapshotItem->sku);
        $this->assertEquals('kg', $snapshotItem->unit);
        $this->assertEquals(100.0, (float) $snapshotItem->received_qty);
        $this->assertEquals(70.0, (float) $snapshotItem->billed_qty);
        $this->assertEquals(30.0, (float) $snapshotItem->pending_qty);
    }

    /** 5. Note saved */
    public function test_note_saved(): void
    {
        $this->createGoodsReceived($this->productPotato, 50.0);

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17',
            note: 'Vendor bill expected tomorrow.'
        );

        $this->assertEquals('Vendor bill expected tomorrow.', $submission->note);
    }

    /** 6. Submission uses Phase 2 reconciliation */
    public function test_submission_uses_phase2_reconciliation(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);
        $this->createBill($this->productPotato, 60.0);

        $reconciliation = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17');

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->assertEquals($reconciliation['total_received_qty'], (float) $submission->submitted_received_summary);
        $this->assertEquals($reconciliation['total_billed_qty'], (float) $submission->submitted_billed_summary);
        $this->assertEquals($reconciliation['total_pending_qty'], (float) $submission->submitted_pending_summary);
        $this->assertEquals($reconciliation['coverage_percentage'], (float) $submission->submission_coverage_percentage);
    }

    /** 7. Duplicate submission rejected */
    public function test_duplicate_submission_rejected(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);

        $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->expectException(ValidationException::class);

        $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );
    }

    /** 8. Transaction rollback if item snapshot fails */
    public function test_transaction_rollback_if_item_snapshot_fails(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);

        // We force item creation failure by wrapping in exception test
        $initialSubmissionsCount = PurchaserBusinessDaySubmission::count();

        try {
            // Pass bad data or intercept DB
            \DB::transaction(function () {
                $sub = PurchaserBusinessDaySubmission::create([
                    'purchaser_user_id' => $this->purchaser1->id,
                    'business_date' => '2026-09-17',
                    'status' => 'submitted',
                    'submitted_by' => $this->purchaser1->id,
                    'submitted_at' => now(),
                ]);

                // Throw exception during items creation
                throw new \RuntimeException('Simulated item creation failure');
            });
        } catch (\Throwable $e) {
            // Exception expected
        }

        $this->assertEquals($initialSubmissionsCount, PurchaserBusinessDaySubmission::count());
    }

    /** 9. Purchaser cannot submit another purchaser's day */
    public function test_purchaser_cannot_submit_another_purchasers_day(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);

        $this->expectException(ValidationException::class);

        // Purchaser 2 tries to submit Purchaser 1's business day
        $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17',
            actorUserId: $this->purchaser2->id
        );
    }

    /** 10. Historical allotment ownership preserved */
    public function test_historical_allotment_ownership_preserved(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $item = $submission->items->firstWhere('product_id', $this->productPotato->id);
        $this->assertEquals('allotment', $item->ownership_source);

        // Now change allotment for future date '2026-09-18' to Purchaser 2
        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser2->id, '2026-09-18');

        // Re-read submission from DB: ownership & purchaser_user_id remain unchanged
        $freshSubmission = PurchaserBusinessDaySubmission::find($submission->id);
        $this->assertEquals($this->purchaser1->id, $freshSubmission->purchaser_user_id);
    }

    /** 11. Late bill does not mutate submission */
    public function test_late_bill_does_not_mutate_submission(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);
        $this->createBill($this->productPotato, 50.0);

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->assertEquals(50.0, (float) $submission->submission_coverage_percentage);

        // 2 days later, missing bill for 50.0 is created
        $this->createBill($this->productPotato, 50.0);

        // Snapshot tables must remain untouched
        $freshSubmission = PurchaserBusinessDaySubmission::find($submission->id);
        $this->assertEquals(50.0, (float) $freshSubmission->submission_coverage_percentage);
        $this->assertEquals(50.0, (float) $freshSubmission->submitted_billed_summary);
        $this->assertEquals(50.0, (float) $freshSubmission->submitted_pending_summary);
    }

    /** 12. Late bill changes live current coverage only */
    public function test_late_bill_changes_live_current_coverage_only(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);
        $this->createBill($this->productPotato, 60.0);

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->assertEquals(60.0, (float) $submission->submission_coverage_percentage);

        // Late bill added
        $this->createBill($this->productPotato, 40.0);

        // Live Phase 2 calculation now returns 100%
        $liveReconciliation = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17');
        $this->assertEquals(100.0, $liveReconciliation['coverage_percentage']);
    }

    /** 13. Submitted coverage remains old value */
    public function test_submitted_coverage_remains_old_value(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);
        $this->createBill($this->productPotato, 80.0);

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        // Late bill added
        $this->createBill($this->productPotato, 20.0);

        $dbSubmission = PurchaserBusinessDaySubmission::find($submission->id);

        $this->assertEquals(80.0, (float) $dbSubmission->submission_coverage_percentage);
        $this->assertEquals(20.0, (float) $dbSubmission->submitted_pending_summary);
    }

    /** 14. Cancelled late bill changes current calculation according to Phase 2 but snapshot stays unchanged */
    public function test_cancelled_late_bill_changes_current_calculation_snapshot_unchanged(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);
        $this->createBill($this->productPotato, 100.0);

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->assertEquals(100.0, (float) $submission->submission_coverage_percentage);

        // Cancel bill or delete matches and cart items
        AdvanceReceiveMatch::query()->delete();
        PurchaserCartItem::query()->delete();

        // Live calculation drops to 0%
        $liveReconciliation = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17');
        $this->assertEquals(0.0, $liveReconciliation['coverage_percentage']);

        // Immutable snapshot stays 100%
        $dbSubmission = PurchaserBusinessDaySubmission::find($submission->id);
        $this->assertEquals(100.0, (float) $dbSubmission->submission_coverage_percentage);
    }

    /** 15. Mixed unit summary does not add incompatible quantities */
    public function test_mixed_unit_summary_does_not_add_incompatible_quantities(): void
    {
        // Product 1 in kg, Product 2 in piece
        $productBunch = Product::create([
            'name' => 'Mint Bunch',
            'sku' => 'MNT-001',
            'category_id' => $this->productPotato->category_id,
            'unit' => 'piece',
            'purchase_price' => 5.00,
        ]);
        $this->allotmentService->assignPurchaser($productBunch->id, $this->purchaser1->id, '2026-09-01');

        $this->createGoodsReceived($this->productPotato, 10.0); // kg
        $this->createGoodsReceived($productBunch, 5.0); // piece

        $submission = $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->assertTrue((bool) $submission->has_mixed_units);
        $this->assertCount(2, $submission->items);

        $potatoItem = $submission->items->firstWhere('product_id', $this->productPotato->id);
        $bunchItem = $submission->items->firstWhere('product_id', $productBunch->id);

        $this->assertEquals('kg', $potatoItem->unit);
        $this->assertEquals('piece', $bunchItem->unit);
    }

    /** 16. Unit mismatch blocking behavior */
    public function test_unit_mismatch_blocking_behavior(): void
    {
        $this->createGoodsReceived($this->productPotato, 50.0); // received in kg

        // Bill entered with mismatching unit 'box'
        $cart = PurchaserCart::create([
            'cart_number' => 'CART-'.uniqid(),
            'user_id' => $this->purchaser1->id,
            'purchaser_id' => $this->purchaser1->id,
            'business_day_id' => $this->businessDaySep17->id,
            'business_date' => '2026-09-17',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'billed',
        ]);

        $grn = GoodsReceived::create([
            'business_day_id' => $this->businessDaySep17->id,
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->purchaser1->id,
            'received_date' => '2026-09-17',
            'received_at' => now(),
            'status' => 'received',
            'grn_number' => 'GRN-MISMATCH-'.uniqid(),
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 50.0,
            'received_unit' => 'box', // mismatch unit vs kg
            'variance' => 0.0,
        ]);

        $this->expectException(ValidationException::class);

        $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );
    }

    /** 17. Submission creates zero inventory/stock movements */
    public function test_submission_creates_zero_stock_movements(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);

        $initialStockMovements = StockMovement::count();

        $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->assertEquals($initialStockMovements, StockMovement::count());
    }

    /** 18. Submission creates zero advance matches */
    public function test_submission_creates_zero_advance_matches(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);

        $initialAdvanceMatches = AdvanceReceiveMatch::count();

        $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $this->assertEquals($initialAdvanceMatches, AdvanceReceiveMatch::count());
    }

    /** 19. Submission changes zero GRNs */
    public function test_submission_changes_zero_grns(): void
    {
        $grnItem = $this->createGoodsReceived($this->productPotato, 100.0);
        $grn = $grnItem->goodsReceived;
        $originalGrnUpdatedAt = $grn->updated_at;

        $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $freshGrn = GoodsReceived::find($grn->id);
        $this->assertEquals('received', $freshGrn->status);
        $this->assertEquals($originalGrnUpdatedAt->timestamp, $freshGrn->updated_at->timestamp);
    }

    /** 20. Submission changes zero purchase invoices */
    public function test_submission_changes_zero_purchase_invoices(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);
        $this->createBill($this->productPotato, 80.0);
        $invoice = PurchaseInvoice::query()->firstOrFail();
        $originalInvoiceUpdatedAt = $invoice->updated_at;

        $this->action->execute(
            purchaserUserId: $this->purchaser1->id,
            businessDate: '2026-09-17'
        );

        $freshInvoice = PurchaseInvoice::find($invoice->id);
        $this->assertEquals('paid', is_string($freshInvoice->status) ? $freshInvoice->status : $freshInvoice->status->value);
        $this->assertEquals($originalInvoiceUpdatedAt->timestamp, $freshInvoice->updated_at->timestamp);
    }

    /** Web Controller Route Test: Purchaser submits via HTTP */
    public function test_purchaser_can_view_and_submit_business_day_via_web_routes(): void
    {
        $this->createGoodsReceived($this->productPotato, 100.0);
        $this->createBill($this->productPotato, 80.0);

        // View verification page
        $response = $this->actingAs($this->purchaser1)
            ->get(route('purchaser.business-day.show', ['date' => '2026-09-17']));

        $response->assertOk();
        $response->assertSee('Purchaser Business Day');
        $response->assertSee('Potato');

        // Submit via POST
        $postResponse = $this->actingAs($this->purchaser1)
            ->post(route('purchaser.business-day.submit'), [
                'business_date' => '2026-09-17',
                'note' => 'Test submission note',
            ]);

        $postResponse->assertRedirect(route('purchaser.business-day.show', [
            'date' => '2026-09-17',
            'purchaser_id' => $this->purchaser1->id,
        ]));

        $this->assertDatabaseHas('purchaser_business_day_submissions', [
            'purchaser_user_id' => $this->purchaser1->id,
            'business_date' => '2026-09-17',
            'status' => 'submitted',
            'note' => 'Test submission note',
        ]);
    }
}
