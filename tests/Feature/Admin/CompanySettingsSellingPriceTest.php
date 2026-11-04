<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingsSellingPriceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Super Admin']);
        $this->admin->assignRole('admin');
    }

    public function test_legacy_company_settings_tab_redirects_to_selling_price_module(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit', ['tab' => 'selling-price']));

        $response->assertRedirect(route('admin.selling-price.index'));
    }

    public function test_legacy_company_settings_update_with_selling_price_tab_redirects_to_selling_price_module(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.update'), [
                'active_tab' => 'selling-price',
                'selling_price_default_percentage' => '22.5',
            ]);

        $response->assertRedirect(route('admin.selling-price.index'))
            ->assertSessionHas('success');

        $this->assertEquals(
            '22.5',
            BusinessSetting::query()->where('key', 'selling_price_default_percentage')->value('value')
        );
    }

    public function test_company_settings_edit_no_longer_renders_selling_price_tab_or_panel(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit'));

        $response->assertOk();
        $response->assertDontSee('data-settings-tab="selling-price"', false);
        $response->assertDontSee('data-settings-panel="selling-price"', false);
    }
}
