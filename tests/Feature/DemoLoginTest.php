<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_shows_demo_users_in_non_production(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create([
            'name' => 'Demo Admin',
            'email' => 'demo-admin@greenleaf.test',
        ]);
        $user->assignRole(Role::findByName('admin'));

        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertSeeText('Demo Login')
            ->assertSeeText('Admin')
            ->assertSeeText('Demo Admin')
            ->assertSeeText('demo-admin@greenleaf.test');

        $content = $response->getContent();
        $loginFormStart = strpos($content, 'id="login-form"');
        $loginFormEnd = strpos($content, '</form>', $loginFormStart);
        $demoFormStart = strpos($content, route('login.demo', $user));

        $this->assertIsInt($loginFormStart);
        $this->assertIsInt($loginFormEnd);
        $this->assertIsInt($demoFormStart);
        $this->assertLessThan($loginFormStart, $demoFormStart);
    }

    public function test_demo_login_authenticates_selected_user(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create([
            'email' => 'demo-shop@greenleaf.test',
            'registration_status' => 'approved',
        ]);
        $user->assignRole(Role::findByName('shop'));

        $csrfToken = 'demo-login-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->post(route('login.demo', $user), [
                '_token' => $csrfToken,
            ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_dedicated_demo_login_page_shows_all_demo_users(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Demo Admin',
            'email' => 'demo-admin@greenleaf.test',
        ]);
        $shop = User::factory()->create([
            'name' => 'Demo Shop',
            'email' => 'demo-shop@greenleaf.test',
        ]);
        $admin->assignRole(Role::findByName('admin'));
        $shop->assignRole(Role::findByName('shop'));

        $response = $this->get(route('login.demo.index'));

        $response
            ->assertOk()
            ->assertSeeText('Choose a demo account')
            ->assertSeeText('Admin')
            ->assertSeeText('Shop Owners')
            ->assertSeeText('Demo Admin')
            ->assertSeeText('Demo Shop')
            ->assertSee(route('login.demo', $admin), false)
            ->assertSee(route('login.demo', $shop), false);
    }
}
