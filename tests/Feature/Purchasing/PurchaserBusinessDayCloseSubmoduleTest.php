<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserBusinessDayCloseSubmoduleTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaserUser;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Product $tomato;

    private Product $onion;

    private PurchaserBusinessDayService $businessDayService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->purchaserUser = User::factory()->create();
        $this->purchaserUser->assignRole('purchaser');
        $this->purchaserUser->assignRole('purchase');

        $this->warehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'WH-VEG',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Agri Supplier Ltd',
            'type' => 'farmer',
            'status' => 'active',
        ]);

        $cat = Category::create(['name' => 'Vegetables', 'is_active' => true]);

        $this->tomato = Product::create([
            'category_id' => $cat->id,
            'name' => 'Tomato Local',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $this->onion = Product::create([
            'category_id' => $cat->id,
            'name' => 'Onion Red',
            'sku' => 'ONI-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $this->businessDayService = app(PurchaserBusinessDayService::class);
    }

    public function test_close_day_main_page_renders_checklist_and_form(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        $response = $this->actingAs($this->purchaserUser)
            ->get(route('purchaser.business-days.close.index', $day->uuid));

        $response->assertOk()
            ->assertViewIs('purchaser.business-days.close.index')
            ->assertSee('Close Business Day')
            ->assertSee($this->warehouse->name)
            ->assertSee('Purchase Bills')
            ->assertSee('Advance Receives')
            ->assertSee('Pending Products')
            ->assertSee('Unit Issues')
            ->assertSee('Inventory Status')
            ->assertSee('Cancelled Purchases')
            ->assertSee('Everything Complete ✓')
            ->assertSee('Close Business Day');
    }

    public function test_close_bills_page_shows_only_current_day_bills(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        $grn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $day->id,
            'received_by' => $this->purchaserUser->id,
            'grn_number' => 'GRN-BILL-01',
            'bill_number' => 'BILL-999',
            'status' => 'approved',
            'received_at' => now(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-BILL-999',
            'amount' => 1000.0,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->purchaserUser)
            ->get(route('purchaser.business-days.close.bills', $day->uuid));

        $response->assertOk()
            ->assertViewIs('purchaser.business-days.close.bills')
            ->assertSee('BILL-999')
            ->assertSee('Tomato Local')
            ->assertSee('50 kg')
            ->assertSee('₹1,000.00')
            ->assertSee('Back to Close Day');
    }

    public function test_close_advances_page_shows_only_current_day_advances(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        $advGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $day->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaserUser->id,
            'grn_number' => 'GRN-ADV-01',
            'status' => 'pending',
            'received_at' => now(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->onion->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
            'unit_price' => 0.0,
            'total_amount' => 0.0,
            'billed_qty' => 0.0,
            'variance' => 0.0,
        ]);

        $response = $this->actingAs($this->purchaserUser)
            ->get(route('purchaser.business-days.close.advances', $day->uuid));

        $response->assertOk()
            ->assertViewIs('purchaser.business-days.close.advances')
            ->assertSee('GRN-ADV-01')
            ->assertSee('Onion Red')
            ->assertSee('100')
            ->assertSee('Back to Close Day');
    }

    public function test_close_pending_page_displays_pending_products(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        $advGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $day->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaserUser->id,
            'grn_number' => 'GRN-ADV-PENDING',
            'status' => 'pending',
            'received_at' => now(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 20,
            'received_unit' => 'kg',
            'unit_price' => 0.0,
            'total_amount' => 0.0,
            'billed_qty' => 0.0,
            'variance' => 0.0,
        ]);

        $response = $this->actingAs($this->purchaserUser)
            ->get(route('purchaser.business-days.close.pending', $day->uuid));

        $response->assertOk()
            ->assertViewIs('purchaser.business-days.close.pending')
            ->assertSee('Tomato Local')
            ->assertSee('20 kg')
            ->assertSee('Back to Close Day');
    }

    public function test_close_issues_page_shows_unit_issues(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        $response = $this->actingAs($this->purchaserUser)
            ->get(route('purchaser.business-days.close.issues', $day->uuid));

        $response->assertOk()
            ->assertViewIs('purchaser.business-days.close.issues')
            ->assertSee('No Unit Mismatches Found')
            ->assertSee('Back to Close Day');
    }

    public function test_close_inventory_page_shows_comparison_data(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        $response = $this->actingAs($this->purchaserUser)
            ->get(route('purchaser.business-days.close.inventory', $day->uuid));

        $response->assertOk()
            ->assertViewIs('purchaser.business-days.close.inventory')
            ->assertSee('Inventory Reconciliation')
            ->assertSee('Back to Close Day');
    }

    public function test_close_cancelled_page_shows_empty_state_and_cancelled_records(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        $response = $this->actingAs($this->purchaserUser)
            ->get(route('purchaser.business-days.close.cancelled', $day->uuid));

        $response->assertOk()
            ->assertViewIs('purchaser.business-days.close.cancelled')
            ->assertSee('No Cancelled Purchases')
            ->assertSee('Back to Close Day');

        // Create cancelled record
        $cancelledGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $day->id,
            'received_by' => $this->purchaserUser->id,
            'grn_number' => 'GRN-CANCELLED-01',
            'status' => 'cancelled',
            'rejection_remarks' => 'Damaged Goods on Delivery',
            'notes' => 'Damaged Goods on Delivery',
            'received_at' => now(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $cancelledGrn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 10,
            'received_unit' => 'kg',
            'unit_price' => 20.0,
            'total_amount' => 200.0,
            'billed_qty' => 0.0,
            'variance' => 0.0,
        ]);

        $responseWithCancelled = $this->actingAs($this->purchaserUser)
            ->get(route('purchaser.business-days.close.cancelled', $day->uuid));

        $responseWithCancelled->assertOk()
            ->assertSee('GRN-CANCELLED-01')
            ->assertSee('Damaged Goods on Delivery')
            ->assertSee('Tomato Local');
    }

    public function test_close_day_action_successfully_closes_day(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        $response = $this->actingAs($this->purchaserUser)
            ->post(route('purchaser.business-days.close.store', $day->uuid), [
                'close_note' => 'All operations complete for today.',
            ]);

        $response->assertRedirect(route('purchasing.business-days.show', $day->uuid))
            ->assertSessionHas('success');

        $day->refresh();
        $this->assertTrue($day->isClosed());
        $this->assertEquals($this->purchaserUser->id, $day->closed_by);
        $this->assertEquals('All operations complete for today.', $day->close_note);
    }

    public function test_close_day_with_pending_requires_reason_note(): void
    {
        $day = $this->businessDayService->open($this->warehouse->id, now()->toDateString(), (int) $this->purchaserUser->id);

        // Advance receive without matching bill
        $advGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $day->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaserUser->id,
            'grn_number' => 'GRN-ADV-02',
            'status' => 'pending',
            'received_at' => now(),
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->onion->id,
            'received_qty' => 30,
            'received_unit' => 'kg',
            'unit_price' => 0.0,
            'total_amount' => 0.0,
            'billed_qty' => 0.0,
            'variance' => 0.0,
        ]);

        // Attempt closing without note should fail validation
        $response = $this->actingAs($this->purchaserUser)
            ->post(route('purchaser.business-days.close.store', $day->uuid), [
                'close_note' => '',
            ]);

        $response->assertSessionHasErrors('close_note');
        $day->refresh();
        $this->assertFalse($day->isClosed());

        // Providing note succeeds
        $successResponse = $this->actingAs($this->purchaserUser)
            ->post(route('purchaser.business-days.close.store', $day->uuid), [
                'close_note' => 'Supplier invoice will be handed over tomorrow morning.',
            ]);

        $successResponse->assertRedirect(route('purchasing.business-days.show', $day->uuid))
            ->assertSessionHas('success');

        $day->refresh();
        $this->assertTrue($day->isClosed());
    }
}
