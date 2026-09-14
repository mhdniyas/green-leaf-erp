<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\WarehouseSalesAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseSalesAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $salesUser;

    private User $unauthorizedUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private WarehouseSalesAccessService $accessService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->accessService = app(WarehouseSalesAccessService::class);

        $this->warehouseA = Warehouse::factory()->create(['name' => 'Vegetable Warehouse', 'is_active' => true]);
        $this->warehouseB = Warehouse::factory()->create(['name' => 'Fruit Warehouse', 'is_active' => true]);

        $this->adminUser = User::factory()->create(['name' => 'Admin User']);
        $this->adminUser->assignRole('admin');

        $this->salesUser = User::factory()->create(['name' => 'Sales Staff']);
        $this->unauthorizedUser = User::factory()->create(['name' => 'Unauthorized Staff']);

        // Configure settings: enable warehouse sales, allow salesUser for warehouseA only
        $this->accessService->updateSettings([
            'enabled' => true,
            'allowed_user_ids' => [$this->salesUser->id],
            'user_warehouses' => [
                (string) $this->salesUser->id => [$this->warehouseA->id],
            ],
        ]);
    }

    public function test_authorized_user_can_access_sales_index_and_create_pages(): void
    {
        $this->actingAs($this->salesUser);

        $response = $this->get(route('warehouse.sales.index'));
        $response->assertOk();

        $createResponse = $this->get(route('warehouse.sales.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('Vegetable Warehouse');
        $createResponse->assertDontSee('Fruit Warehouse');
    }

    public function test_unauthorized_user_is_forbidden_from_accessing_sales_page(): void
    {
        $this->actingAs($this->unauthorizedUser);

        // GET requests render 403 redirected to dashboard with error per app exception policy
        $response = $this->get(route('warehouse.sales.index'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');

        // JSON requests receive explicit 403 status
        $jsonResponse = $this->getJson(route('warehouse.sales.index'));
        $jsonResponse->assertForbidden();
    }

    public function test_disabled_setting_blocks_operational_access_for_all_users(): void
    {
        $this->accessService->updateSettings([
            'enabled' => false,
            'allowed_user_ids' => [$this->salesUser->id],
            'user_warehouses' => [
                (string) $this->salesUser->id => [$this->warehouseA->id],
            ],
        ]);

        $this->actingAs($this->salesUser);

        $response = $this->get(route('warehouse.sales.index'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');

        $jsonResponse = $this->getJson(route('warehouse.sales.index'));
        $jsonResponse->assertForbidden();
    }

    public function test_user_cannot_sell_from_unauthorized_warehouse(): void
    {
        $this->actingAs($this->salesUser);

        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouseB->id,
            'customer_name' => 'Walk-in Customer',
            'items' => [
                [
                    'product_id' => 1,
                    'entered_qty' => 10,
                    'unit_price' => 50,
                ],
            ],
            'payment_method' => 'cash',
            'money_holder_type' => 'user',
        ]);

        $response->assertForbidden();
    }
}
