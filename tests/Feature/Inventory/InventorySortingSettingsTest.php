<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\BusinessSetting;
use App\Models\StockBatch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySortingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_enable_sorting_all_as_grade_a_by_default(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('inventory.settings.update'), [
            'sort_all_as_grade_a' => true,
        ]);

        $response->assertRedirect(route('inventory.settings.edit'));
        $this->assertDatabaseHas('business_settings', [
            'key' => 'inventory_sort_all_as_grade_a',
            'value' => '1',
        ]);
    }

    public function test_non_admin_cannot_update_inventory_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('inventory.settings.update'), ['sort_all_as_grade_a' => true])
            ->assertForbidden();
    }

    public function test_enabled_setting_prefills_the_full_batch_as_grade_a(): void
    {
        BusinessSetting::query()->create([
            'key' => 'inventory_sort_all_as_grade_a',
            'value' => '1',
        ]);
        $batch = StockBatch::factory()->create(['total_kg' => 12.5]);

        $response = $this->actingAs($this->admin)->get(route('inventory.batches.sort', $batch));

        $response->assertOk();
        $response->assertSee('name="grades[0][quantity]"', false);
        $response->assertSee('value="12.5"', false);
    }
}
