<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseReceiverAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $admin;

    private User $purchaser;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
        $this->product = Product::factory()->create([
            'is_active' => true,
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->warehouses()->attach($this->warehouse->id, ['is_default' => true]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');
    }

    public function test_warehouse_home_summary_returns_200_for_receiver(): void
    {
        Sanctum::actingAs($this->receiver);

        $shop = Shop::factory()->create(['status' => 'active']);
        ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'delivery_status' => 'pending_delivery',
        ]);

        $response = $this->getJson('/api/v1/warehouse/home-summary?warehouse_id='.$this->warehouse->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'receive_pending',
                    'bill_pending',
                    'received_today',
                    'loadout_pending',
                    'loadout_partial',
                    'loadout_completed_today',
                    'check_issues_count',
                    'recent_activity',
                    'check_issues',
                ],
            ]);
    }

    public function test_warehouse_receiver_can_list_advances(): void
    {
        Sanctum::actingAs($this->receiver);

        GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/purchasing/grns?receipt_type=warehouse_advance&warehouse_id='.$this->warehouse->id);

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_warehouse_receiver_can_create_advance_receive(): void
    {
        Sanctum::actingAs($this->receiver);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'received_at' => now()->toDateString(),
            'bill_number' => 'ADV-TEST-001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'received_qty' => 25.0,
                    'received_unit' => 'kg',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/purchasing/grns', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('goods_received', [
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'bill_number' => 'ADV-TEST-001',
        ]);
    }

    public function test_warehouse_receiver_can_view_advance_details(): void
    {
        Sanctum::actingAs($this->receiver);

        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/purchasing/grns/'.$grn->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $grn->id);
    }

    public function test_warehouse_receiver_can_view_item_summary(): void
    {
        Sanctum::actingAs($this->receiver);

        $response = $this->getJson('/api/v1/purchaser/reports/item-summary?range=today');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_warehouse_receiver_is_forbidden_from_unrelated_admin_settings(): void
    {
        Sanctum::actingAs($this->receiver);

        $response = $this->postJson('/api/v1/purchaser/special-price/approve', [
            'purchase_order_id' => 1,
            'product_id' => 1,
            'price' => 10,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_and_purchaser_retain_access(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/warehouse/home-summary')->assertStatus(200);
        $this->getJson('/api/v1/purchaser/reports/item-summary')->assertStatus(200);

        Sanctum::actingAs($this->purchaser);
        $this->getJson('/api/v1/purchaser/reports/item-summary')->assertStatus(200);
    }
}
