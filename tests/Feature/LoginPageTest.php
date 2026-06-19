<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_hides_demo_credentials_and_keeps_sign_in_form_available(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Sign in to continue');
        $response->assertDontSee('Green Leaf Traders Demo Access');
        $response->assertDontSee('Testing Environment');
        $response->assertDontSee(route('login.demo'));
        $response->assertDontSee('shop@greenleaf.com');
        $response->assertDontSee('shop-budegere@greenleaf.com');
        $response->assertDontSee('shop-grancity@greenleaf.com');
        $response->assertDontSee('shop-ashirwad@greenleaf.com');
    }

    public function test_hidden_demo_login_page_lists_staff_and_shop_accounts(): void
    {
        $response = $this->get(route('login.demo'));

        $response->assertOk();
        $response->assertSee('Demo Login');
        $response->assertSee('admin@greenleaf.com');
        $response->assertSee('purchase@greenleaf.com');
        $response->assertSee('shop@greenleaf.com');
        $response->assertSee('shop-easyday@greenleaf.com');
        $response->assertSee('Casio17');
        $response->assertSee('Easyday30');
        $response->assertSee('Login as Purchase Manager');
        $response->assertSee('Login as Easyday Shop');
    }

    public function test_database_seeder_creates_fourteen_shop_owner_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(14, Shop::query()->count());
        $this->assertSame(14, User::query()->whereNotNull('shop_id')->count());
        $this->assertDatabaseHas('users', ['email' => 'shop@greenleaf.com']);
        $this->assertDatabaseHas('users', ['email' => 'shop-easyday@greenleaf.com']);
    }

    public function test_seeded_demo_admin_can_sign_in_with_shared_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post(route('login.submit'), [
            'email' => 'admin@greenleaf.com',
            'password' => 'Admin11',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_seeded_purchase_manager_can_sign_in_with_demo_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post(route('login.submit'), [
            'email' => 'purchase@greenleaf.com',
            'password' => 'Purchase12',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }
}
