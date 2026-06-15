<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_lists_multiple_shop_demo_accounts(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('shop@greenleaf.com');
        $response->assertSee('shop-budegere@greenleaf.com');
        $response->assertSee('shop-grancity@greenleaf.com');
        $response->assertSee('shop-ashirwad@greenleaf.com');
    }

    public function test_seeded_demo_admin_can_sign_in_with_shared_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post(route('login.submit'), [
            'email' => 'admin@greenleaf.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }
}
