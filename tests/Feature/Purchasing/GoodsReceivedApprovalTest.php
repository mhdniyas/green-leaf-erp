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

    private User $admin;

    private User $receiver;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');

        $this->supplier = Supplier::factory()->create();
        $category = Category::factory()->create();
        $this->product = Product::factory()->create(['category_id' => $category->id]);
    }

    public function test_receiver_created_grn_is_approved_immediately_and_inventory_is_updated(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::SentToSupplier,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 50.000,
            'unit_price' => 10.0000,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('purchasing.grns.store'), [
                'purchase_order_id' => $po->id,
                'received_at' => now()->toDateString(),
                'transport_cost' => 50.00,
                'labour_cost' => 20.00,
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $this->product->id,
                        'received_qty' => 50.000,
                    ],
                ],
            ]);

        $grn = GoodsReceived::latest('id')->first();

        $response->assertRedirect(route('purchasing.grns.show', $grn));
        $this->assertEquals('approved', $grn->fresh()->status);
        $this->assertEquals($this->receiver->id, $grn->fresh()->approved_by);
        $this->assertEquals($this->receiver->id, $grn->fresh()->updated_by);

        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $this->product->id,
            'total_kg' => 50.000,
            'cost_per_kg' => 10.0000,
            'transport_cost' => 50.00,
            'labour_cost' => 20.00,
            'status' => BatchStatus::Pending->value,
            'notes' => 'Auto-created from GRN: '.$grn->grn_number,
        ]);

        $po->refresh();
        $this->assertEquals(POStatus::Closed, $po->status);
    }

    public function test_admin_can_send_approved_grn_for_recheck(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Closed,
        ]);

        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'status' => 'approved',
            'received_by' => $this->receiver->id,
            'approved_by' => $this->receiver->id,
            'updated_by' => $this->receiver->id,
            'approved_at' => now(),
            'received_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('purchasing.grns.recheck', $grn), [
                'remarks' => 'Please verify the landed quantity before final stock use.',
            ]);

        $response->assertRedirect(route('purchasing.grns.show', $grn));

        $grn->refresh();
        $po->refresh();

        $this->assertEquals('recheck_required', $grn->status);
        $this->assertEquals($this->admin->id, $grn->updated_by);
        $this->assertNull($grn->approved_by);
        $this->assertNull($grn->approved_at);
        $this->assertEquals(POStatus::Received, $po->status);
    }
}
