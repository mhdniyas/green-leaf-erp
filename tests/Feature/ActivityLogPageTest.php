<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ActivityLogPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_see_activity_request_metadata_and_change_details(): void
    {
        $admin = $this->adminUser();
        $product = Product::factory()->create(['name' => 'Banana']);

        Activity::query()->create([
            'log_name' => 'default',
            'description' => 'Product price updated',
            'subject_type' => $product::class,
            'subject_id' => $product->id,
            'event' => 'updated',
            'causer_type' => $admin::class,
            'causer_id' => $admin->id,
            'properties' => [
                'ip_address' => '203.0.113.15',
                'user_agent' => 'Feature Test Browser',
                'method' => 'PUT',
                'url' => 'http://green-leaf-erp.test/admin/products/'.$product->id,
                'attributes' => ['base_price' => '120.00'],
                'old' => ['base_price' => '100.00'],
            ],
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.activity-logs.index', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'ip_address' => '203.0.113',
            ]))
            ->assertOk()
            ->assertSee('System Activity Logs')
            ->assertSee('203.0.113.15')
            ->assertSee('Feature Test Browser')
            ->assertSee('PUT')
            ->assertSee('Product #'.$product->id)
            ->assertSee('Product price updated')
            ->assertSee('base_price')
            ->assertSee('100.00')
            ->assertSee('120.00');
    }

    public function test_non_admin_without_activity_permission_cannot_open_activity_logs(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('admin.activity-logs.index'))
            ->assertForbidden();
    }

    public function test_web_activity_logs_capture_request_metadata_automatically(): void
    {
        Route::get('/activity-log-metadata-test', function () {
            Activity::query()->create([
                'log_name' => 'default',
                'description' => 'Metadata capture test',
                'event' => 'created',
            ]);

            return response('ok');
        });

        $this
            ->withServerVariables([
                'REMOTE_ADDR' => '198.51.100.24',
                'HTTP_USER_AGENT' => 'Metadata Test Browser',
            ])
            ->get('/activity-log-metadata-test?source=test')
            ->assertOk();

        $activity = Activity::query()->where('description', 'Metadata capture test')->firstOrFail();

        $this->assertSame('198.51.100.24', $activity->properties->get('ip_address'));
        $this->assertSame('Metadata Test Browser', $activity->properties->get('user_agent'));
        $this->assertSame('GET', $activity->properties->get('method'));
        $this->assertStringContainsString('/activity-log-metadata-test?source=test', $activity->properties->get('url'));
    }

    private function adminUser(): User
    {
        Permission::findOrCreate('admin.activity-log.view', 'web');

        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo('admin.activity-log.view');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
