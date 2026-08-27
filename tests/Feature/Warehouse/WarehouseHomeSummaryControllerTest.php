<?php

namespace Tests\Feature\Warehouse;

use App\Models\User;
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
}
