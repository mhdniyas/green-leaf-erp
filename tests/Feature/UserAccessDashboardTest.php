<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAccessDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $this->withoutVite();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('shop', 'web');
        Role::findOrCreate('purchase', 'web');
    }

    public function test_main_admin_can_view_user_access_dashboard_and_search_users(): void
    {
        config()->set('admin.user_access.main_admin_email', 'admin@greenleaf.com');

        $mainAdmin = User::factory()->create([
            'email' => 'admin@greenleaf.com',
            'registration_status' => 'approved',
        ]);
        $mainAdmin->assignRole('admin');

        $shop = Shop::factory()->create(['name' => 'Lulu Fresh']);

        $shopUser = User::factory()->create([
            'name' => 'Amina Shop',
            'email' => 'amina@example.com',
            'shop_id' => $shop->id,
            'registration_status' => 'approved',
        ]);
        $shopUser->assignRole('shop');

        $purchaseUser = User::factory()->create([
            'name' => 'Procurement Ravi',
            'email' => 'ravi@example.com',
            'registration_status' => 'approved',
        ]);
        $purchaseUser->assignRole('purchase');

        $response = $this->actingAs($mainAdmin)->get(route('admin.user-access.index', ['search' => 'Lulu']));

        $response->assertOk();
        $response->assertSee('Login as User');
        $response->assertSee('Amina Shop');
        $response->assertDontSee('Procurement Ravi');
    }

    public function test_non_main_admin_cannot_open_user_access_dashboard(): void
    {
        config()->set('admin.user_access.main_admin_email', 'admin@greenleaf.com');

        $admin = User::factory()->create([
            'email' => 'second-admin@greenleaf.com',
            'registration_status' => 'approved',
        ]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.user-access.index'));

        $response->assertForbidden();
    }

    public function test_main_admin_can_view_as_user_and_return_to_admin_with_activity_logged(): void
    {
        config()->set('admin.user_access.main_admin_email', 'admin@greenleaf.com');

        $mainAdmin = User::factory()->create([
            'name' => 'Main Admin',
            'email' => 'admin@greenleaf.com',
            'registration_status' => 'approved',
        ]);
        $mainAdmin->assignRole('admin');

        $shopUser = User::factory()->create([
            'name' => 'Support Target',
            'registration_status' => 'approved',
        ]);
        $shopUser->assignRole('shop');

        $startResponse = $this->from(route('admin.user-access.index'))
            ->actingAs($mainAdmin)
            ->post(route('admin.user-access.store', $shopUser->public_uuid));

        $startResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($shopUser);

        $bannerResponse = $this->get(route('profile.show'));
        $bannerResponse->assertSee('Viewing as: Support Target');

        $stopResponse = $this->post(route('admin.user-access.stop'));

        $stopResponse->assertRedirect(route('admin.user-access.index'));
        $this->assertAuthenticatedAs($mainAdmin);

        $activity = Activity::query()->where('log_name', 'user_access')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('User impersonation session completed', $activity->description);
        $properties = $activity->properties->toArray();

        $this->assertSame('Main Admin', data_get($properties, 'admin_name'));
        $this->assertSame('Support Target', data_get($properties, 'selected_user_name'));
        $this->assertNotNull(data_get($properties, 'login_time'));
        $this->assertNotNull(data_get($properties, 'logout_time'));
    }

    public function test_main_admin_cannot_view_another_admin_account(): void
    {
        config()->set('admin.user_access.main_admin_email', 'admin@greenleaf.com');

        $mainAdmin = User::factory()->create([
            'email' => 'admin@greenleaf.com',
            'registration_status' => 'approved',
        ]);
        $mainAdmin->assignRole('admin');

        $otherAdmin = User::factory()->create([
            'registration_status' => 'approved',
        ]);
        $otherAdmin->assignRole('admin');

        $response = $this->actingAs($mainAdmin)->post(route('admin.user-access.store', $otherAdmin->public_uuid));

        $response->assertForbidden();
        $this->assertAuthenticatedAs($mainAdmin);
    }
}
