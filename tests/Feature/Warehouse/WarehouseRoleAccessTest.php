<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $warehouseReceiver;

    private User $unauthorizedUser;

    private Warehouse $assignedWarehouse;

    private Warehouse $otherWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->assignedWarehouse = Warehouse::factory()->create(['name' => 'Main Warehouse', 'is_active' => true]);
        $this->otherWarehouse = Warehouse::factory()->create(['name' => 'Other Warehouse', 'is_active' => true]);

        $this->warehouseReceiver = User::factory()->create(['name' => 'Warehouse Receiver Tester']);
        $this->warehouseReceiver->assignRole('warehouse_receiver');
        $this->warehouseReceiver->warehouses()->attach($this->assignedWarehouse->id, ['is_default' => true]);

        $this->unauthorizedUser = User::factory()->create(['name' => 'Random Staff']);
    }

    public function test_warehouse_receiver_can_view_sales_summary_api(): void
    {
        Sanctum::actingAs($this->warehouseReceiver);

        $response = $this->getJson('/api/v1/purchaser/reports/sales-summary?range=today');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_warehouse_receiver_can_view_sales_summary_web(): void
    {
        $this->actingAs($this->warehouseReceiver);

        $response = $this->get('/purchaser/reports/sales-summary');

        $response->assertOk();
    }

    public function test_warehouse_receiver_cannot_perform_finance_or_price_actions(): void
    {
        Sanctum::actingAs($this->warehouseReceiver);

        // Price update should 403
        $priceResponse = $this->postJson('/api/v1/purchaser/bill-prices/update', [
            'vendor_id' => 1,
            'items' => [],
        ]);
        $priceResponse->assertForbidden();

        // Approve special price should 403
        $specialPriceResponse = $this->postJson('/api/v1/purchaser/special-price/approve', [
            'price_id' => 1,
        ]);
        $specialPriceResponse->assertForbidden();
    }

    public function test_unauthorized_user_cannot_access_sales_summary(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);

        $response = $this->getJson('/api/v1/purchaser/reports/sales-summary');
        $response->assertForbidden();
    }

    public function test_warehouse_receiver_can_view_inventory_stock_for_assigned_warehouse(): void
    {
        Sanctum::actingAs($this->warehouseReceiver);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomatoes',
            'sku' => 'TOM-01',
        ]);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->assignedWarehouse->id,
            'type' => 'in',
            'quantity' => 100,
        ]);

        $response = $this->getJson('/api/v1/inventory/stock?warehouse_id='.$this->assignedWarehouse->id);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_warehouse_receiver_cannot_view_unassigned_warehouse_stock(): void
    {
        Sanctum::actingAs($this->warehouseReceiver);

        $response = $this->getJson('/api/v1/inventory/stock?warehouse_id='.$this->otherWarehouse->id);

        $response->assertForbidden();
    }
}
