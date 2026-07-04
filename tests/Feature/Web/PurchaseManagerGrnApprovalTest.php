<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Purchasing\POStatus;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaseManagerGrnApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $receiver;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('purchase');
    }

    public function test_purchase_manager_no_longer_has_daily_grn_approval_route_in_navigation(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertRedirect(route('purchasing.dashboard'));

        $landingResponse = $this->actingAs($manager)
            ->followingRedirects()
            ->get(route('purchasing.dashboard'));

        $landingResponse->assertOk();
        $landingResponse->assertDontSee('Daily GRN Approval');
    }

    public function test_admin_can_send_an_approved_grn_for_recheck_and_receiver_can_resubmit(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => POStatus::SentToSupplier,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 30.00,
        ]);

        $createResponse = $this->actingAs($this->receiver)
            ->post(route('purchasing.grns.store'), [
                'purchase_order_id' => $po->id,
                'received_at' => today()->toDateString(),
                'transport_cost' => 0,
                'labour_cost' => 0,
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $product->id,
                        'received_qty' => 95,
                    ],
                ],
            ]);

        $grn = GoodsReceived::latest('id')->first();

        $createResponse->assertRedirect(route('purchasing.grns.show', $grn));
        $this->assertEquals('approved', $grn->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('purchasing.grns.recheck', $grn), [
                'remarks' => 'Please recheck shortage before release.',
            ])
            ->assertRedirect(route('purchasing.grns.show', $grn));

        $this->assertEquals('recheck_required', $grn->fresh()->status);

        $this->actingAs($this->receiver)
            ->put(route('purchasing.grns.update', $grn), [
                'purchase_order_id' => $po->id,
                'received_at' => today()->toDateString(),
                'transport_cost' => 0,
                'labour_cost' => 0,
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $product->id,
                        'received_qty' => 100,
                    ],
                ],
            ])
            ->assertRedirect(route('purchasing.grns.show', $grn));

        $grn->refresh();

        $this->assertEquals('approved', $grn->status);
        $this->assertEquals($this->receiver->id, $grn->approved_by);
        $this->assertEquals($this->receiver->id, $grn->updated_by);
    }

    public function test_purchase_manager_can_approve_submitted_purchases_and_update_price_proposals(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'Green Valley Farm',
        ]);
        $product = Product::factory()->create([
            'name' => 'Tomato H',
            'sku' => 'TOM-H-001',
            'unit' => 'kg',
        ]);
        $purchaser = User::factory()->create([
            'name' => 'Purchaser One',
        ]);
        $purchaser->assignRole('purchaser');

        $purchaseDate = Carbon::today()->toDateString();
        $pricingDate = Carbon::tomorrow()->toDateString();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => POStatus::Received,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_price' => 1111.00,
            'price_basis' => 'per_kg',
        ]);

        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-DRAFT-APPROVE-01',
            'status' => 'pending_approval',
            'received_by' => $purchaser->id,
            'received_at' => $purchaseDate,
            'transport_cost' => 0,
            'labour_cost' => 0,
            'is_extra' => false,
        ]);

        $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => 20,
            'variance' => 0,
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('purchasing.grns.index', ['date' => $purchaseDate]));

        $response->assertOk();
        $response->assertSee('Approve Submitted Purchases');
        $response->assertSee('Tomato H');
        $response->assertSee('Purchaser One');
        $response->assertSee('GRN-DRAFT-APPROVE-01');
        $response->assertSee('min-w-[780px]', false);
        $response->assertSee('[-webkit-overflow-scrolling:touch]', false);

        $this->actingAs($this->manager)
            ->post(route('purchasing.grns.approve-submitted'), [
                'date' => $purchaseDate,
            ])
            ->assertRedirect(route('purchasing.grns.index', ['date' => $purchaseDate]));

        $grn->refresh();
        $this->assertSame('approved', $grn->status);
        $this->assertSame($this->manager->id, $grn->approved_by);

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', $pricingDate)
            ->first();

        $this->assertNotNull($approval);

        $this->actingAs($this->manager)
            ->patch(route('purchasing.grns.proposed-prices.update'), [
                'date' => $purchaseDate,
                'prices' => [
                    $approval->id => [
                        'price_a' => 1222.00,
                        'price_b' => 1333.00,
                        'price_c' => 1444.00,
                    ],
                ],
            ])
            ->assertRedirect(route('purchasing.grns.index', ['date' => $purchaseDate]));

        $approval->refresh();
        $this->assertSame(1222.00, (float) $approval->price_a);
        $this->assertSame(1333.00, (float) $approval->price_b);
        $this->assertSame(1444.00, (float) $approval->price_c);
        $this->assertSame('pending', $approval->status);
    }

    public function test_price_proposal_section_includes_products_from_submitted_receipts(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Corriander',
            'sku' => 'COR-101',
            'unit' => 'kg',
        ]);
        $purchaser = User::factory()->create([
            'name' => 'Purchaser Two',
        ]);
        $purchaser->assignRole('purchaser');

        $purchaseDate = Carbon::today()->toDateString();
        $pricingDate = Carbon::tomorrow()->toDateString();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => POStatus::Received,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'unit_price' => 220.00,
            'price_basis' => 'per_kg',
        ]);

        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-DRAFT-SUBMITTED-02',
            'status' => 'pending_approval',
            'received_by' => $purchaser->id,
            'received_at' => $purchaseDate,
            'transport_cost' => 0,
            'labour_cost' => 0,
            'is_extra' => false,
        ]);

        $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => 15,
            'variance' => 0,
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('purchasing.grns.index', ['date' => $purchaseDate]));

        $response->assertOk();
        $response->assertSee('Update Proposed Shop Category Prices');
        $response->assertSee('Corriander');
        $response->assertSee('242.00');
        $response->assertSee('min-w-[760px]', false);
    }

    public function test_missing_pending_price_approval_is_created_from_submitted_purchase(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Baby Corn',
            'sku' => 'BABY-CORN-001',
            'unit' => 'kg',
        ]);
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        $purchaseDate = Carbon::today()->toDateString();
        $pricingDate = Carbon::tomorrow()->toDateString();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => POStatus::Received,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 222.00,
            'price_basis' => 'per_kg',
        ]);

        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-DRAFT-SUBMITTED-03',
            'status' => 'pending_approval',
            'received_by' => $purchaser->id,
            'received_at' => $purchaseDate,
            'transport_cost' => 0,
            'labour_cost' => 0,
            'is_extra' => false,
        ]);

        $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => 10,
            'variance' => 0,
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('purchasing.grns.index', ['date' => $purchaseDate]));

        $response->assertOk();
        $response->assertSee('Baby Corn');

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', $pricingDate)
            ->first();

        $this->assertNotNull($approval);
        $this->assertSame('pending', $approval->status);
        $this->assertSame(222.0, (float) $approval->purchase_price);
    }
}
