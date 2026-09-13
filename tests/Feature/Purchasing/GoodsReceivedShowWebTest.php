<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoodsReceivedShowWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private User $unauthorizedUser;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchase');

        $this->unauthorizedUser = User::factory()->create();

        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOM-001', 'unit' => 'kg']);
    }

    public function test_advance_grn_with_null_purchase_order_returns_200_and_shows_advance_indication(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => null,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_at' => now(),
            'transport_cost' => 100.00,
            'labour_cost' => 50.00,
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'purchase_order_item_id' => null,
            'received_qty' => 25.00,
        ]);

        $canonicalUrl = route('purchasing.grns.show', $grn);

        $response = $this->actingAs($this->admin)->get($canonicalUrl);

        $response->assertOk();
        $response->assertSee('Warehouse Advance');
        $response->assertDontSee('Missing required parameter');
    }

    public function test_normal_bill_grn_with_purchase_order_returns_200_and_links_to_po(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Fresh Farms']);
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'order_date' => now(),
            'po_number' => 'PO-20260912-0099',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 50.00,
            'unit_price' => 20.00,
        ]);

        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_at' => now(),
            'transport_cost' => 50.00,
            'labour_cost' => 25.00,
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'purchase_order_item_id' => $poItem->id,
            'received_qty' => 50.00,
        ]);

        $canonicalUrl = route('purchasing.grns.show', $grn);

        $response = $this->actingAs($this->admin)->get($canonicalUrl);

        $response->assertOk();
        $response->assertSee('PO-20260912-0099');
        $response->assertSee(route('purchasing.orders.show', $po));
    }

    public function test_canonical_grn_show_url_contains_public_identifier_and_not_numeric_db_id(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
        ]);

        $generatedUrl = route('purchasing.grns.show', $grn);

        $this->assertNotEmpty($grn->public_uuid);
        $this->assertStringContainsString($grn->public_uuid, $generatedUrl);
        $this->assertStringNotContainsString('/purchasing/grns/'.$grn->id, $generatedUrl);
    }

    public function test_old_numeric_url_returns_404_not_found(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->get('/purchasing/grns/'.$grn->id);

        $response->assertNotFound();
    }

    public function test_invalid_public_identifier_returns_404_not_found(): void
    {
        $response = $this->actingAs($this->admin)->get('/purchasing/grns/nonexistent-public-uuid-or-grn');

        $response->assertNotFound();
    }

    public function test_resolving_by_grn_number_works_on_web_route(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->get('/purchasing/grns/'.$grn->grn_number);

        $response->assertOk();
    }

    public function test_unauthorized_user_is_forbidden_or_redirected(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->unauthorizedUser)->get(route('purchasing.grns.show', $grn));

        // Web exception handler redirects 403 to dashboard with error message
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'You do not have access to that page.');
    }

    public function test_api_route_regression_still_resolves_numeric_id(): void
    {
        Sanctum::actingAs($this->admin);

        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
        ]);

        $response = $this->getJson('/api/v1/purchasing/grns/'.$grn->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $grn->id);
    }

    public function test_warehouse_receive_route_regression_still_resolves_numeric_id(): void
    {
        $receiver = User::factory()->create();
        $receiver->assignRole('warehouse_receiver');

        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($receiver)->get('/warehouse-receiver/receive-grn/'.$grn->id);

        $response->assertOk();
    }
}
