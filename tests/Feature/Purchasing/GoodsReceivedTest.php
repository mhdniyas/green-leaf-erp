<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsReceivedTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    private Supplier $supplier;

    private Product $product1;

    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // User with GRN permissions
        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo([
            'purchasing.grn.view',
            'purchasing.grn.create',
            'purchasing.order.view',
        ]);

        $this->unauthorizedUser = User::factory()->create();

        $this->supplier = Supplier::factory()->create();
        $category = Category::factory()->create();
        $this->product1 = Product::factory()->create(['category_id' => $category->id, 'name' => 'Grade A Leaf']);
        $this->product2 = Product::factory()->create(['category_id' => $category->id, 'name' => 'Grade B Leaf']);
    }

    public function test_authorized_user_can_view_goods_received_log(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Approved,
            'created_by' => $this->authorizedUser->id,
        ]);

        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'received_by' => $this->authorizedUser->id,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.grns.index'));

        $response->assertOk();
        $response->assertSee($grn->grn_number);
    }

    public function test_unauthorized_user_cannot_view_goods_received_log(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('purchasing.grns.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_see_create_grn_page_for_approved_po(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Approved,
            'created_by' => $this->authorizedUser->id,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.grns.create', ['purchase_order_id' => $po->id]));

        $response->assertOk();
        $response->assertSee($po->po_number);
    }

    public function test_authorized_user_cannot_see_create_grn_page_for_draft_po(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Draft,
            'created_by' => $this->authorizedUser->id,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.grns.create', ['purchase_order_id' => $po->id]));

        $response->assertNotFound();
    }

    public function test_authorized_user_can_store_grn_and_allocates_landed_costs(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Approved,
            'created_by' => $this->authorizedUser->id,
        ]);

        $poItem1 = $po->items()->create([
            'product_id' => $this->product1->id,
            'quantity' => 10.000,
            'unit_price' => 5.0000,
        ]);

        $poItem2 = $po->items()->create([
            'product_id' => $this->product2->id,
            'quantity' => 20.000,
            'unit_price' => 3.0000,
        ]);

        // Post GRN with 10kg received for item 1 and 20kg received for item 2
        // Total received = 30kg.
        // Transport = 30.00, Labour = 60.00.
        // Proportional cost split:
        // Item 1 (10kg): transport = (10/30)*30 = 10.00, labour = (10/30)*60 = 20.00.
        // Item 2 (20kg): transport = (20/30)*30 = 20.00, labour = (20/30)*60 = 40.00.
        $grnData = [
            'purchase_order_id' => $po->id,
            'received_at' => now()->toDateString(),
            'transport_cost' => 30.00,
            'labour_cost' => 60.00,
            'notes' => 'Test landed cost allocation notes',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem1->id,
                    'product_id' => $this->product1->id,
                    'received_qty' => 10.000,
                ],
                [
                    'purchase_order_item_id' => $poItem2->id,
                    'product_id' => $this->product2->id,
                    'received_qty' => 20.000,
                ],
            ],
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('purchasing.grns.store'), $grnData);

        $grn = GoodsReceived::latest('id')->first();
        $response->assertRedirect(route('purchasing.grns.show', $grn));

        // Check GRN database entry
        $this->assertDatabaseHas('goods_received', [
            'id' => $grn->id,
            'purchase_order_id' => $po->id,
            'transport_cost' => 30.00,
            'labour_cost' => 60.00,
        ]);

        // Check GRN Items variance
        $this->assertDatabaseHas('goods_received_items', [
            'goods_received_id' => $grn->id,
            'product_id' => $this->product1->id,
            'received_qty' => 10.000,
            'variance' => 0.000,
        ]);

        $this->assertDatabaseHas('goods_received_items', [
            'goods_received_id' => $grn->id,
            'product_id' => $this->product2->id,
            'received_qty' => 20.000,
            'variance' => 0.000,
        ]);

        // Check Stock Batch creation with correct allocated costs
        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $this->product1->id,
            'total_kg' => 10.000,
            'cost_per_kg' => 5.0000,
            'transport_cost' => 10.00,
            'labour_cost' => 20.00,
            'status' => BatchStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $this->product2->id,
            'total_kg' => 20.000,
            'cost_per_kg' => 3.0000,
            'transport_cost' => 20.00,
            'labour_cost' => 40.00,
            'status' => BatchStatus::Pending->value,
        ]);

        // Check PO status has transitioned to received
        $po->refresh();
        $this->assertEquals(POStatus::Received, $po->status);
    }
}
