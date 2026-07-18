<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SecurityRouteProtectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.submit'), [
                'email' => 'admin@greenleaf.com',
                'password' => 'wrong-password',
            ]);
        }

        $this
            ->post(route('login.submit'), [
                'email' => 'admin@greenleaf.com',
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests();
    }

    public function test_route_permissions_block_cross_role_access(): void
    {
        $this->seed(DatabaseSeeder::class);

        $shopUser = User::query()->where('email', 'shop-ashirwad@greenleaf.com')->firstOrFail();
        $purchaseUser = User::query()->where('email', 'purchase@greenleaf.com')->firstOrFail();
        $hrUser = User::query()->where('email', 'hr@greenleaf.com')->firstOrFail();

        $this
            ->actingAs($shopUser)
            ->get(route('sales.customers.index'))
            ->assertForbidden();

        $this
            ->actingAs($purchaseUser)
            ->get(route('admin.staff.assignments.index'))
            ->assertForbidden();

        $this
            ->actingAs($hrUser)
            ->get(route('admin.staff.assignments.index'))
            ->assertOk();
    }

    public function test_guest_users_are_redirected_from_protected_routes(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this
            ->get(route('sort-sheet.index'))
            ->assertRedirect(route('login'));
    }
}
