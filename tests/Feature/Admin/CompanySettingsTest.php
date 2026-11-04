<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            $this->artisan('migrate', ['--force' => true]);
        }

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->admin = User::factory()->create(['name' => 'Settings Admin']);
        $this->admin->assignRole('admin');

        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        BusinessSetting::query()->create([
            'key' => 'default_purchaser_user_id',
            'value' => (string) $purchaser->id,
        ]);
    }

    public function test_company_settings_shows_separate_tabs_and_trigger_history(): void
    {
        activity('auto_load_all')
            ->causedBy($this->admin)
            ->withProperties([
                'trigger_mode' => 'automatic',
                'status' => 'completed',
                'business_date' => '2026-08-22',
                'loaded_orders' => 2,
                'skipped_orders' => 0,
                'failed_orders' => 0,
            ])
            ->log('Auto Load All run recorded');

        $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit'))
            ->assertOk()
            ->assertSee('Operations')
            ->assertSee('Auto Load All')
            ->assertSee('Trigger History')
            ->assertSee('Automatic trigger')
            ->assertSee('2 loaded');
    }

    public function test_manual_run_summary_is_recorded_in_trigger_history(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.auto-load-all.api.runs.store'), [
                'trigger_mode' => 'manual',
                'status' => 'completed',
                'business_date' => '2026-08-22',
                'delay_seconds' => 3,
                'selected_shops' => 2,
                'processed_orders' => 3,
                'loaded_orders' => 2,
                'skipped_orders' => 1,
                'failed_orders' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $activity = Activity::query()->where('log_name', 'auto_load_all')->firstOrFail();

        $this->assertSame($this->admin->id, $activity->causer_id);
        $this->assertSame('manual', $activity->properties->get('trigger_mode'));
        $this->assertSame(2, $activity->properties->get('loaded_orders'));
    }

    public function test_automatic_command_records_an_empty_run(): void
    {
        $this->artisan('greenleaf:auto-load-all', ['--force' => true])
            ->assertExitCode(0);

        $activity = Activity::query()->where('log_name', 'auto_load_all')->firstOrFail();

        $this->assertSame('automatic', $activity->properties->get('trigger_mode'));
        $this->assertSame('completed', $activity->properties->get('status'));
        $this->assertSame('No eligible orders found.', $activity->properties->get('notes'));
    }

    public function test_company_settings_does_not_render_old_purchaser_business_day_close_reopen_controls(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit'));

        $response->assertOk()
            ->assertSee('Purchaser Business Day Verification')
            ->assertDontSee('Purchasers can Close Day')
            ->assertDontSee('Purchasers can Reopen Day')
            ->assertDontSee('Reopen requires Reason')
            ->assertDontSee('Allow Close With Pending')
            ->assertDontSee('Require Digital Verification')
            ->assertDontSee('Admin can Override / Reopen');
    }

    public function test_company_settings_can_update_purchaser_business_day_verification_settings(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Main Hub', 'is_active' => true]);

        $purchaserUser = User::factory()->create();
        $purchaserUser->assignRole('purchaser');

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.update'), [
                'company_name' => 'Green Leaf Fresh',
                'default_purchaser_user_id' => $purchaserUser->id,
                'business_day_warehouse_settings' => [
                    $warehouse->id => [
                        'enabled' => '1',
                        'require_daily_submission' => '1',
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.company-settings.edit'))
            ->assertSessionHas('success');

        $service = app(PurchaserBusinessDayService::class);
        $settings = $service->getWarehouseSettings($warehouse->id);

        $this->assertTrue($settings['enabled']);
        $this->assertTrue($settings['require_daily_submission']);
    }

    public function test_company_settings_can_update_global_purchaser_business_day_settings(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Global Hub', 'is_active' => true]);

        $purchaserUser = User::factory()->create();
        $purchaserUser->assignRole('purchaser');

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.update'), [
                'company_name' => 'Green Leaf Global',
                'default_purchaser_user_id' => $purchaserUser->id,
                'purchaser_business_day_verification_enabled' => '1',
                'purchaser_business_day_require_daily_submission' => '1',
            ]);

        $response->assertRedirect(route('admin.company-settings.edit'))
            ->assertSessionHas('success');

        $this->assertEquals('1', BusinessSetting::where('key', 'purchaser_business_day_verification_enabled')->value('value'));
        $this->assertEquals('1', BusinessSetting::where('key', 'purchaser_business_day_require_daily_submission')->value('value'));

        $service = app(PurchaserBusinessDayService::class);
        $this->assertTrue($service->isWarehouseEnabled($warehouse->id));
    }

    public function test_company_settings_can_view_and_update_grade_default_percentages(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit', ['tab' => 'operations']))
            ->assertOk()
            ->assertSee('Grade Selling Price Default Percentages')
            ->assertSee('grade_b_default_percentage')
            ->assertSee('grade_c_default_percentage');

        $purchaserUser = User::factory()->create();
        $purchaserUser->assignRole('purchaser');

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.update'), [
                'company_name' => 'Green Leaf Fresh',
                'default_purchaser_user_id' => $purchaserUser->id,
                'grade_b_default_percentage' => '88.5',
                'grade_c_default_percentage' => '75',
            ]);

        $response->assertRedirect(route('admin.company-settings.edit'))
            ->assertSessionHas('success');

        $this->assertEquals('88.5', BusinessSetting::where('key', 'grade_b_default_percentage')->value('value'));
        $this->assertEquals('75', BusinessSetting::where('key', 'grade_c_default_percentage')->value('value'));
    }

    public function test_company_settings_renders_purchaser_section_and_subtabs(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit', ['tab' => 'purchaser', 'subtab' => 'daily-prices']));

        $response->assertOk()
            ->assertSee('Purchaser')
            ->assertSee('Daily Price Settings')
            ->assertSee('Purchaser Operations')
            ->assertSee('Operating Expense Settings')
            ->assertSee('Daily Price Update Cutoff')
            ->assertSee('Purchasers can update daily prices before this time. After the cutoff, only administrators can update prices.')
            ->assertSee('09:00');
    }

    public function test_daily_price_cutoff_service_defaults_and_helpers(): void
    {
        $service = app(PurchaserBusinessDayService::class);

        $this->assertEquals('09:00:00', $service->dailyPriceCutoffTime());
        $this->assertEquals('09:00', $service->dailyPriceCutoffInputValue());
        $this->assertEquals('09:00 AM', $service->dailyPriceCutoffLabel());
    }

    public function test_admin_can_update_daily_price_cutoff_time_and_redirects_to_subtab(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.update'), [
                'active_tab' => 'purchaser',
                'active_subtab' => 'daily-prices',
                'daily_price_cutoff_time' => '08:30',
            ]);

        $response->assertRedirect(route('admin.company-settings.edit', [
            'tab' => 'purchaser',
            'subtab' => 'daily-prices',
        ]))->assertSessionHas('success');

        $this->assertEquals('08:30:00', BusinessSetting::where('key', 'daily_price_cutoff_time')->value('value'));

        $service = app(PurchaserBusinessDayService::class);
        $this->assertEquals('08:30:00', $service->dailyPriceCutoffTime());
        $this->assertEquals('08:30', $service->dailyPriceCutoffInputValue());
        $this->assertEquals('08:30 AM', $service->dailyPriceCutoffLabel());
    }

    public function test_daily_price_cutoff_time_validation_rejects_invalid_time(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.company-settings.edit', ['tab' => 'purchaser', 'subtab' => 'daily-prices']))
            ->patch(route('admin.company-settings.update'), [
                'active_tab' => 'purchaser',
                'active_subtab' => 'daily-prices',
                'daily_price_cutoff_time' => '25:00',
            ]);

        $response->assertRedirect(route('admin.company-settings.edit', [
            'tab' => 'purchaser',
            'subtab' => 'daily-prices',
        ]))->assertSessionHasErrors(['daily_price_cutoff_time']);

        $this->assertNull(BusinessSetting::where('key', 'daily_price_cutoff_time')->first());
    }

    public function test_saving_one_subsection_does_not_reset_unrelated_settings(): void
    {
        $purchaserUser = User::factory()->create();
        $purchaserUser->assignRole('purchaser');

        BusinessSetting::updateOrCreate(['key' => 'company_name'], ['value' => 'Original Leaf Enterprise']);
        BusinessSetting::updateOrCreate(['key' => 'default_purchaser_user_id'], ['value' => (string) $purchaserUser->id]);
        BusinessSetting::updateOrCreate(['key' => 'grade_b_default_percentage'], ['value' => '90.00']);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.update'), [
                'active_tab' => 'purchaser',
                'active_subtab' => 'daily-prices',
                'daily_price_cutoff_time' => '10:15',
            ]);

        $response->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertEquals('Original Leaf Enterprise', BusinessSetting::where('key', 'company_name')->value('value'));
        $this->assertEquals((string) $purchaserUser->id, BusinessSetting::where('key', 'default_purchaser_user_id')->value('value'));
        $this->assertEquals('90.00', BusinessSetting::where('key', 'grade_b_default_percentage')->value('value'));
        $this->assertEquals('10:15:00', BusinessSetting::where('key', 'daily_price_cutoff_time')->value('value'));
    }

    public function test_legacy_tabs_are_aliased_to_purchaser_subtabs(): void
    {
        $response1 = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit', ['tab' => 'operating-expenses']));

        $response1->assertOk()
            ->assertViewHas('activeTab', 'purchaser')
            ->assertViewHas('activeSubtab', 'operating-expenses');

        $response2 = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit', ['tab' => 'purchaser-business-day']));

        $response2->assertOk()
            ->assertViewHas('activeTab', 'purchaser')
            ->assertViewHas('activeSubtab', 'operations');
    }
}
