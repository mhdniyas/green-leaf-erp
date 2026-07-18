<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminPurchasingAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        view()->share('errors', new ViewErrorBag);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_purchase_dashboard_without_direct_purchase_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this
            ->actingAs($admin)
            ->get(route('purchasing.dashboard'))
            ->assertRedirect(route('purchasing.orders.index'));

        $this
            ->actingAs($admin)
            ->get(route('purchasing.orders.index'))
            ->assertOk()
            ->assertSee('Purchase Orders');
    }

    public function test_admin_layout_shows_purchasing_dashboard_link(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this
            ->actingAsRequestUser($admin)
            ->blade('<x-layouts.admin title="Admin Layout Check"><div>Admin body</div></x-layouts.admin>')
            ->assertSee('Purchasing Dashboard')
            ->assertSee(route('purchasing.dashboard'), false);
    }

    private function actingAsRequestUser(User $user): self
    {
        $this->actingAs($user);
        $this->app['request']->setUserResolver(fn (): User => $user);

        return $this;
    }
}
