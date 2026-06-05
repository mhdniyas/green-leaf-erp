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
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsReceivedApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $operator;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        // Purchase Manager
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo([
            'purchasing.grn.view',
            'purchasing.grn.approve',
        ]);

        // Warehouse Operator
        $this->operator = User::factory()->create();
        $this->operator->givePermissionTo([
            'purchasing.grn.view',
            'purchasing.grn.create',
        ]);

        $this->supplier = Supplier::factory()->create();
        $category = Category::factory()->create();
        $this->product = Product::factory()->create(['category_id' => $category->id]);
    }

    public function test_purchase_manager_can_approve_grn_and_inventory_is_updated(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Received,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 50.000,
            'unit_price' => 10.0000,
        ]);

        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-TEST-123',
            'status' => 'pending_approval',
            'received_by' => $this->operator->id,
            'received_at' => now()->toDateString(),
            'transport_cost' => 50.00,
            'labour_cost' => 20.00,
        ]);

        $grnItem = $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.000,
            'variance' => 0.000,
        ]);

        // Prior to approval, assert StockBatch does not exist
        $this->assertDatabaseMissing('stock_batches', [
            'product_id' => $this->product->id,
        ]);

        // Call the approve endpoint
        $response = $this->actingAs($this->manager)
            ->post(route('purchasing.grns.approve', $grn));

        $response->assertRedirect(route('purchasing.grns.show', $grn));
        $this->assertEquals('approved', $grn->fresh()->status);

        // Check Stock Batch creation with correct allocated costs
        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $this->product->id,
            'total_kg' => 50.000,
            'cost_per_kg' => 10.0000,
            'transport_cost' => 50.00,
            'labour_cost' => 20.00,
            'status' => BatchStatus::Pending->value,
            'notes' => 'Auto-created from GRN: GRN-TEST-123',
        ]);

        // PO status should now be Closed
        $po->refresh();
        $this->assertEquals(POStatus::Closed, $po->status);
    }

    public function test_unauthorized_user_cannot_approve_grn(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Received,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 50.000,
            'unit_price' => 10.0000,
        ]);

        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-TEST-123',
            'status' => 'pending_approval',
            'received_by' => $this->operator->id,
            'received_at' => now()->toDateString(),
        ]);

        // Warehouse operator tries to approve
        $response = $this->actingAs($this->operator)
            ->post(route('purchasing.grns.approve', $grn));

        $response->assertForbidden();
        $this->assertEquals('pending_approval', $grn->fresh()->status);
    }
}
