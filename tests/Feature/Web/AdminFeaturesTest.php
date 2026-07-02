<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);
    }

    public function test_guest_user_cannot_access_admin_dashboards(): void
    {
        $response = $this->get(route('admin.overview'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('admin.daily-progress'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('admin.activity-logs.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_roles_cannot_access_admin_dashboards(): void
    {
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');

        $response = $this->actingAs($shopUser)->get(route('admin.overview'));
        $response->assertStatus(403);

        $response = $this->actingAs($shopUser)->get(route('admin.daily-progress'));
        $response->assertStatus(403);

        $response = $this->actingAs($shopUser)->get(route('admin.activity-logs.index'));
        $response->assertStatus(403);
    }

    public function test_legacy_admin_can_access_admin_dashboards(): void
    {
        $legacyAdmin = User::factory()->create();
        $legacyAdmin->assignRole('admin');

        $response = $this->actingAs($legacyAdmin)->get(route('admin.overview'));
        $response->assertOk();
        $response->assertSee('Admin Control Center');
        $response->assertSee('Suspicious Activity');

        $response = $this->actingAs($legacyAdmin)->get(route('admin.daily-progress'));
        $response->assertOk();
        $response->assertSee('Daily Operational Progress');

        $response = $this->actingAs($legacyAdmin)->get(route('admin.activity-logs.index'));
        $response->assertOk();
        $response->assertSee('System Activity Logs');
    }

    public function test_admin_overview_sidebar_shows_full_admin_navigation(): void
    {
        $legacyAdmin = User::factory()->create();
        $legacyAdmin->assignRole('admin');

        $response = $this->actingAs($legacyAdmin)->get(route('admin.overview'));

        $response->assertOk();
        $response->assertSee('Accounting Dashboard');
        $response->assertSee('Staff Dashboard');
        $response->assertSeeText('Users & Roles');
        $response->assertSee('Daily Progress');
        $response->assertSee('Activity Log');
    }

    public function test_activity_log_displays_logs_correctly(): void
    {
        $legacyAdmin = User::factory()->create();
        $legacyAdmin->assignRole('admin');

        // Trigger Spatie activity logging by updating a user
        $targetUser = User::factory()->create(['name' => 'Original Name']);

        $this->actingAs($legacyAdmin);

        // Log custom activity to guarantee a log exists
        activity()
            ->causedBy($legacyAdmin)
            ->performedOn($targetUser)
            ->event('updated')
            ->withProperties(['attributes' => ['name' => 'New Name'], 'old' => ['name' => 'Original Name']])
            ->log('User updated');

        $response = $this->get(route('admin.activity-logs.index'));
        $response->assertOk();
        $response->assertSee('User updated');
        $response->assertSee('New Name');
        $response->assertSee('Original Name');
    }

    public function test_daily_progress_displays_requisitions_correctly(): void
    {
        $legacyAdmin = User::factory()->create();
        $legacyAdmin->assignRole('admin');

        $shop = Shop::create(['code' => 'S10', 'name' => 'Super Shop']);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $legacyAdmin->id,
        ]);

        $response = $this->actingAs($legacyAdmin)->get(route('admin.daily-progress'));
        $response->assertOk();
        $response->assertSee('Super Shop');
        $response->assertSee($order->order_number);
    }

    public function test_admin_overview_displays_online_users_and_permission_overrides(): void
    {
        $legacyAdmin = User::factory()->create([
            'last_seen_at' => now(),
        ]);
        $legacyAdmin->assignRole('admin');

        $shop = Shop::create(['code' => 'S11', 'name' => 'Online Shop']);

        $purchaseUser = User::factory()->create([
            'name' => 'Purchase Lead',
            'shop_id' => $shop->id,
            'last_seen_at' => now(),
        ]);
        $purchaseUser->assignRole('purchase');
        $purchaseUser->givePermissionTo('purchasing.order.approve');

        $response = $this->actingAs($legacyAdmin)->get(route('admin.overview'));

        $response->assertOk();
        $response->assertSee('Who is online right now');
        $response->assertSee('Purchase Lead');
        $response->assertSee('Online Shop');
        $response->assertSee('Users with direct control changes');
    }
}
