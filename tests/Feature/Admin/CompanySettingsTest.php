<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\User;
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
}
