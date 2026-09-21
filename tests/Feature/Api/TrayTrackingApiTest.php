<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Shop;
use App\Models\TrayMovement;
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
        // Add tray type
        $response = $this->actingAs($this->user)->postJson('/api/v1/tray-types', [
            'name' => 'Tray A',
        ]);
        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Tray A')
            ->assertJsonPath('data.is_active', true);

        $trayTypeId = $response->json('data.id');

        // Fetch tray types
        $listResponse = $this->actingAs($this->user)->getJson('/api/v1/tray-types');
        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Tray A');

        // Rename tray type
        $updateResponse = $this->actingAs($this->user)->putJson("/api/v1/tray-types/{$trayTypeId}", [
            'name' => 'Large Tray A',
        ]);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Large Tray A');

        // Disable tray type
        $this->actingAs($this->user)->putJson("/api/v1/tray-types/{$trayTypeId}", [
            'is_active' => false,
        ]);

        $activeList = $this->actingAs($this->user)->getJson('/api/v1/tray-types');
        $activeList->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_can_save_loadout_and_update_without_duplication(): void
    {
        $trayA = TrayType::create(['name' => 'Tray A']);
        $trayB = TrayType::create(['name' => 'Tray B']);

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

        $this->assertDatabaseHas('tray_movements', [
            'shop_id' => $this->shop->id,
            'date' => '2026-09-21',
            'tray_type_id' => $trayA->id,
            'sent_qty' => 10,
        ]);

        // Edit save (Tray A 10 -> 12)
        $payload['trays'][0]['quantity'] = 12;
        $this->actingAs($this->user)->postJson('/api/v1/trays/loadout', $payload);

        // Ensure record is updated and not duplicated
        $this->assertDatabaseCount('tray_movements', 2);
        $this->assertDatabaseHas('tray_movements', [
            'shop_id' => $this->shop->id,
            'date' => '2026-09-21',
            'tray_type_id' => $trayA->id,
            'sent_qty' => 12,
        ]);

        // Daily tray table API
        $dailyResponse = $this->actingAs($this->user)->getJson('/api/v1/trays?date=2026-09-21');
        $dailyResponse->assertStatus(200)
            ->assertJsonPath('date', '2026-09-21')
            ->assertJsonPath('shops.0.shop_name', 'Casio')
            ->assertJsonPath('shops.0.total', 16);
    }

    public function test_tray_returns_and_balance_validation(): void
    {
        $trayA = TrayType::create(['name' => 'Tray A']);

        // Send 10 trays to Casio
        $this->actingAs($this->user)->postJson('/api/v1/trays/loadout', [
            'shop_id' => $this->shop->id,
            'date' => '2026-09-21',
            'trays' => [
                ['tray_type_id' => $trayA->id, 'quantity' => 10],
            ],
        ]);

        // Check balance (Sent 10, Returned 0, Balance 10)
        $balRes = $this->actingAs($this->user)->getJson("/api/v1/trays/balance?shop_id={$this->shop->id}");
        $balRes->assertStatus(200)
            ->assertJsonPath('trays.0.sent', 10)
            ->assertJsonPath('trays.0.returned', 0)
            ->assertJsonPath('trays.0.balance', 10);

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

        // Check updated balance (Sent 10, Returned 4, Balance 6)
        $updatedBalRes = $this->actingAs($this->user)->getJson("/api/v1/trays/balance?shop_id={$this->shop->id}");
        $updatedBalRes->assertStatus(200)
            ->assertJsonPath('trays.0.sent', 10)
            ->assertJsonPath('trays.0.returned', 4)
            ->assertJsonPath('trays.0.balance', 6);
    }
}
