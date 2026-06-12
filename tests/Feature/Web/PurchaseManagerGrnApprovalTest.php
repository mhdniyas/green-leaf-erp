<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseManagerGrnApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $receiver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');
    }

    public function test_purchase_manager_no_longer_has_daily_grn_approval_route_in_navigation(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Daily GRN Approval');
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
}
