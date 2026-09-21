<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Shop;
use App\Models\TrayType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrayTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->shop = Shop::factory()->create(['name' => 'Casio']);
    }

    public function test_can_manage_tray_types(): void
    {
        // Add tray type with total_owned
        $response = $this->actingAs($this->user)->postJson('/api/v1/tray-types', [
            'name' => 'Tray A',
            'total_owned' => 100,
        ]);
        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Tray A')
            ->assertJsonPath('data.total_owned', 100)
            ->assertJsonPath('data.is_active', true);

        $trayTypeId = $response->json('data.id');

        // Fetch tray types
        $listResponse = $this->actingAs($this->user)->getJson('/api/v1/tray-types');
        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Tray A')
            ->assertJsonPath('data.0.total_owned', 100);

        // Rename tray type and change total_owned
        $updateResponse = $this->actingAs($this->user)->putJson("/api/v1/tray-types/{$trayTypeId}", [
            'name' => 'Large Tray A',
            'total_owned' => 120,
        ]);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Large Tray A')
            ->assertJsonPath('data.total_owned', 120);

        // Disable tray type
        $this->actingAs($this->user)->putJson("/api/v1/tray-types/{$trayTypeId}", [
            'is_active' => false,
        ]);

        $activeList = $this->actingAs($this->user)->getJson('/api/v1/tray-types');
        $activeList->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_can_save_loadout_and_daily_table_shows_net(): void
    {
        $trayA = TrayType::create(['name' => 'Tray A', 'total_owned' => 50]);
        $trayB = TrayType::create(['name' => 'Tray B', 'total_owned' => 30]);

        $payload = [
            'shop_id' => $this->shop->id,
            'date' => '2026-09-21',
            'trays' => [
                ['tray_type_id' => $trayA->id, 'quantity' => 10],
                ['tray_type_id' => $trayB->id, 'quantity' => 4],
            ],
        ];

        // Initial save
        $response = $this->actingAs($this->user)->postJson('/api/v1/trays/loadout', $payload);
        $response->assertStatus(200)->assertJsonPath('success', true);

        // Now record a return of 3 for Tray A and 1 for Tray B on the same date
        $returnRes = $this->actingAs($this->user)->postJson('/api/v1/trays/return', [
            'shop_id' => $this->shop->id,
            'date' => '2026-09-21',
            'trays' => [
                ['tray_type_id' => $trayA->id, 'quantity' => 3],
                ['tray_type_id' => $trayB->id, 'quantity' => 1],
            ],
        ]);
        $returnRes->assertStatus(200);

        // Daily table must show NET = SENT - RETURNED (Tray A: 10 - 3 = 7, Tray B: 4 - 1 = 3, Total: 10)
        $dailyResponse = $this->actingAs($this->user)->getJson('/api/v1/trays?date=2026-09-21');
        $dailyResponse->assertStatus(200)
            ->assertJsonPath('date', '2026-09-21')
            ->assertJsonPath('shops.0.shop_name', 'Casio')
            ->assertJsonPath('shops.0.trays.0.sent', 10)
            ->assertJsonPath('shops.0.trays.0.returned', 3)
            ->assertJsonPath('shops.0.trays.0.net', 7)
            ->assertJsonPath('shops.0.trays.1.sent', 4)
            ->assertJsonPath('shops.0.trays.1.returned', 1)
            ->assertJsonPath('shops.0.trays.1.net', 3)
            ->assertJsonPath('shops.0.total', 10);
    }

    public function test_tray_returns_and_balance_validation(): void
    {
        $trayA = TrayType::create(['name' => 'Tray A', 'total_owned' => 50]);

        // Send 10 trays to Casio
        $this->actingAs($this->user)->postJson('/api/v1/trays/loadout', [
            'shop_id' => $this->shop->id,
            'date' => '2026-09-21',
            'trays' => [
                ['tray_type_id' => $trayA->id, 'quantity' => 10],
            ],
        ]);

        // Check balance (Sent 10, Returned 0, Balance/Held 10)
        $balRes = $this->actingAs($this->user)->getJson("/api/v1/trays/balance?shop_id={$this->shop->id}");
        $balRes->assertStatus(200)
            ->assertJsonPath('trays.0.sent', 10)
            ->assertJsonPath('trays.0.returned', 0)
            ->assertJsonPath('trays.0.held', 10)
            ->assertJsonPath('total_held', 10);

        // Returning 12 should be rejected (greater than balance 10)
        $excessReturnRes = $this->actingAs($this->user)->postJson('/api/v1/trays/return', [
            'shop_id' => $this->shop->id,
            'date' => '2026-09-21',
            'trays' => [
                ['tray_type_id' => $trayA->id, 'quantity' => 12],
            ],
        ]);
        $excessReturnRes->assertStatus(422);

        // Returning 4 should succeed
        $validReturnRes = $this->actingAs($this->user)->postJson('/api/v1/trays/return', [
            'shop_id' => $this->shop->id,
            'date' => '2026-09-21',
            'trays' => [
                ['tray_type_id' => $trayA->id, 'quantity' => 4],
            ],
        ]);
        $validReturnRes->assertStatus(200);

        // Check updated balance (Sent 10, Returned 4, Balance/Held 6)
        $updatedBalRes = $this->actingAs($this->user)->getJson("/api/v1/trays/balance?shop_id={$this->shop->id}");
        $updatedBalRes->assertStatus(200)
            ->assertJsonPath('trays.0.sent', 10)
            ->assertJsonPath('trays.0.returned', 4)
            ->assertJsonPath('trays.0.held', 6)
            ->assertJsonPath('total_held', 6);
    }
}
