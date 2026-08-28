<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\POStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Permission::firstOrCreate(['name' => 'purchasing.order.view']);

        $this->receiver = User::factory()->create(['name' => 'Receiver Tester']);
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->givePermissionTo('warehouse.loadout.all');
        $this->receiver->givePermissionTo('purchasing.order.view');

        $this->supplier = Supplier::factory()->create(['name' => 'Vendor Test']);
    }

    public function test_status_pending_filter_returns_only_actionable_purchase_orders(): void
    {
        Sanctum::actingAs($this->receiver);

        // Actionable / Pending POs
        $poApproved = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Approved,
        ]);
        $poSent = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::SentToSupplier,
        ]);
        $poPartial = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::PartiallyReceived,
        ]);

        // Non-actionable POs
        $poReceived = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Received,
        ]);
        $poClosed = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Closed,
        ]);
        $poDraft = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Draft,
        ]);

        $response = $this->getJson('/api/v1/purchasing/orders?status=pending');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($poApproved->id, $returnedIds);
        $this->assertContains($poSent->id, $returnedIds);
        $this->assertContains($poPartial->id, $returnedIds);
        $this->assertNotContains($poReceived->id, $returnedIds);
        $this->assertNotContains($poClosed->id, $returnedIds);
        $this->assertNotContains($poDraft->id, $returnedIds);
    }

    public function test_status_received_filter_returns_only_received_and_closed_orders(): void
    {
        Sanctum::actingAs($this->receiver);

        $poApproved = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Approved,
        ]);
        $poReceived = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Received,
        ]);
        $poClosed = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Closed,
        ]);

        $response = $this->getJson('/api/v1/purchasing/orders?status=received');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($poReceived->id, $returnedIds);
        $this->assertContains($poClosed->id, $returnedIds);
        $this->assertNotContains($poApproved->id, $returnedIds);
    }

    public function test_pagination_limits_response_count_and_returns_meta(): void
    {
        Sanctum::actingAs($this->receiver);

        PurchaseOrder::factory()->count(5)->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Received,
        ]);

        $response = $this->getJson('/api/v1/purchasing/orders?status=received&per_page=2&page=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3);

        $this->assertCount(2, $response->json('data'));
    }
}
