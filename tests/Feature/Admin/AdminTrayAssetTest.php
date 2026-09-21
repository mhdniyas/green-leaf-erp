<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Shop;
use App\Models\TrayMovement;
use App\Models\TrayType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTrayAssetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop1;

    private Shop $shop2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->shop1 = Shop::factory()->create(['name' => 'Casio']);
        $this->shop2 = Shop::factory()->create(['name' => 'Grandcity']);
    }

    public function test_can_view_tray_assets_index_and_calculations(): void
    {
        $trayA = TrayType::create(['name' => 'Small Tray', 'total_owned' => 100, 'is_active' => true]);
        $trayB = TrayType::create(['name' => 'Large Tray', 'total_owned' => 50, 'is_active' => true]);

        // Shop 1: Sent 20, Returned 5 (Held 15)
        TrayMovement::create([
            'shop_id' => $this->shop1->id,
            'date' => '2026-09-21',
            'tray_type_id' => $trayA->id,
            'sent_qty' => 20,
            'returned_qty' => 5,
        ]);

        // Shop 2: Sent 15, Returned 8 (Held 7)
        TrayMovement::create([
            'shop_id' => $this->shop2->id,
            'date' => '2026-09-21',
            'tray_type_id' => $trayA->id,
            'sent_qty' => 15,
            'returned_qty' => 8,
        ]);

        // Total With Shops for Tray A = 15 + 7 = 22
        // In Warehouse for Tray A = 100 - 22 = 78

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.assets.trays.index'));
        $response->assertStatus(200)
            ->assertSee('Small Tray')
            ->assertSee('100') // total owned
            ->assertSee('22')  // with shops
            ->assertSee('78'); // in warehouse
    }

    public function test_can_store_and_update_tray_type(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.assets.trays.store'), [
            'name' => 'Medium Blue Tray',
            'total_owned' => 80,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.cashbook.assets.trays.index'));
        $this->assertDatabaseHas('tray_types', [
            'name' => 'Medium Blue Tray',
            'total_owned' => 80,
            'is_active' => true,
        ]);

        $tray = TrayType::where('name', 'Medium Blue Tray')->first();

        // Update
        $updateResponse = $this->actingAs($this->admin)->put(route('admin.cashbook.assets.trays.update', $tray->id), [
            'name' => 'Medium Red Tray',
            'total_owned' => 85,
            'is_active' => 1,
        ]);

        $updateResponse->assertRedirect(route('admin.cashbook.assets.trays.index'));
        $this->assertDatabaseHas('tray_types', [
            'id' => $tray->id,
            'name' => 'Medium Red Tray',
            'total_owned' => 85,
        ]);
    }

    public function test_admin_can_edit_shop_daily_tray_data_and_recalculates_net(): void
    {
        $trayA = TrayType::create(['name' => 'Small', 'total_owned' => 100]);

        // Create initial movement (sent 10, returned 3)
        TrayMovement::create([
            'shop_id' => $this->shop1->id,
            'date' => '2026-09-21',
            'tray_type_id' => $trayA->id,
            'sent_qty' => 10,
            'returned_qty' => 3,
        ]);

        // Edit via Admin Shop Date Edit
        $response = $this->actingAs($this->admin)->put(route('admin.cashbook.assets.trays.shop-date.update', [
            'shop' => $this->shop1->id,
            'date' => '2026-09-21',
        ]), [
            'trays' => [
                [
                    'tray_type_id' => $trayA->id,
                    'sent_qty' => 12,
                    'returned_qty' => 4,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.cashbook.assets.trays.index'));

        $this->assertDatabaseHas('tray_movements', [
            'shop_id' => $this->shop1->id,
            'date' => '2026-09-21',
            'tray_type_id' => $trayA->id,
            'sent_qty' => 12,
            'returned_qty' => 4,
        ]);
    }
}
