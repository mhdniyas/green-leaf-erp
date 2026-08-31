<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\GoodsReceived;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseHomeSummaryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_receiver_can_fetch_home_summary(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/warehouse/home-summary');

        $response->assertOk()
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

    public function test_home_summary_correctly_counts_bill_pending_and_received_today_with_warehouse_scope(): void
    {
        $wh1 = Warehouse::create(['name' => 'WH 1', 'code' => 'WH1', 'is_active' => true]);
        $wh2 = Warehouse::create(['name' => 'WH 2', 'code' => 'WH2', 'is_active' => true]);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        // 1. Bill pending GRN in WH1
        GoodsReceived::factory()->create([
            'warehouse_id' => $wh1->id,
            'grn_number' => 'GRN-WH1-001',
            'bill_status' => 'bill_pending',
            'status' => 'approved',
            'received_at' => today()->toDateString(),
            'received_by' => $user->id,
        ]);

        // 2. Bill available GRN in WH1 (received today)
        GoodsReceived::factory()->create([
            'warehouse_id' => $wh1->id,
            'grn_number' => 'GRN-WH1-002',
            'bill_status' => 'bill_available',
            'status' => 'approved',
            'received_at' => today()->toDateString(),
            'received_by' => $user->id,
        ]);

        // 3. Bill pending GRN in WH2
        GoodsReceived::factory()->create([
            'warehouse_id' => $wh2->id,
            'grn_number' => 'GRN-WH2-001',
            'bill_status' => 'bill_pending',
            'status' => 'approved',
            'received_at' => today()->toDateString(),
            'received_by' => $user->id,
        ]);

        // Query WH1
        $resWh1 = $this->getJson("/api/v1/warehouse/home-summary?warehouse_id={$wh1->id}");
        $resWh1->assertOk();
        $this->assertEquals(1, $resWh1->json('data.bill_pending'));
        $this->assertEquals(2, $resWh1->json('data.received_today'));

        // Query WH2
        $resWh2 = $this->getJson("/api/v1/warehouse/home-summary?warehouse_id={$wh2->id}");
        $resWh2->assertOk();
        $this->assertEquals(1, $resWh2->json('data.bill_pending'));
        $this->assertEquals(1, $resWh2->json('data.received_today'));
    }
}
