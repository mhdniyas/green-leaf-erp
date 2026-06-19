<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
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

    public function test_demo_login_page_requires_page_password_before_showing_accounts(): void
    {
        $response = $this->get(route('login.demo'));

        $response->assertOk();
        $response->assertSee('Protected testing logins');
        $response->assertDontSee('admin@greenleaf.com');
        $response->assertDontSee('Purchase12');
    }

    public function test_demo_login_page_unlocks_staff_accounts_without_showing_passwords(): void
    {
        $unlockResponse = $this->post(route('login.demo.unlock'), [
            'page_password' => '2525',
        ]);

        $unlockResponse->assertRedirect(route('login.demo'));

        $response = $this->withSession(['demo_access_granted' => true])->get(route('login.demo'));

        $response->assertOk();
        $response->assertSee('admin@greenleaf.com');
        $response->assertSee('purchase@greenleaf.com');
        $response->assertSee('Login as Purchase Manager');
        $response->assertDontSee('Purchase12');
        $response->assertDontSee('Admin11');
    }

    public function test_database_seeder_creates_shops_without_seeded_shop_owner_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(14, Shop::query()->count());
        $this->assertSame(0, User::query()->whereNotNull('shop_id')->count());
        $this->assertDatabaseMissing('users', ['email' => 'shop@greenleaf.com']);
        $this->assertDatabaseMissing('users', ['email' => 'shop-easyday@greenleaf.com']);
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

    public function test_guest_can_view_shop_owner_registration_page(): void
    {
        $response = $this->get(route('shop-owner.register'));

        $response->assertOk();
        $response->assertSee('Shop-owner registration');
    }

    public function test_shop_owner_registration_creates_pending_shop_and_user(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $response = $this->post(route('shop-owner.register.store'), [
            'shop_name' => 'Fresh Fields',
            'owner_name' => 'Niyas Shop',
            'email' => 'owner@freshfields.test',
            'phone' => '9876543210',
            'address' => 'Main street',
            'password' => 'OwnerPass88',
            'password_confirmation' => 'OwnerPass88',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $user = User::query()->where('email', 'owner@freshfields.test')->firstOrFail();
        $this->assertSame('pending', $user->registration_status);
        $this->assertTrue($user->hasRole('shop'));
        $this->assertNotNull($user->shop);
        $this->assertSame('pending_approval', $user->shop->status);
        $this->assertSame('+919876543210', $user->shop->contact_phone);
    }

    public function test_shop_owner_registration_accepts_india_code_and_spaces_in_phone_number(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $response = $this->post(route('shop-owner.register.store'), [
            'shop_name' => 'City Greens',
            'owner_name' => 'Owner Two',
            'email' => 'owner2@freshfields.test',
            'phone' => '+91 98765 43210',
            'address' => 'Second street',
            'password' => 'OwnerPass88',
            'password_confirmation' => 'OwnerPass88',
        ]);

        $response->assertRedirect(route('login'));

        $user = User::query()->where('email', 'owner2@freshfields.test')->firstOrFail();
        $this->assertSame('+919876543210', $user->shop?->contact_phone);
    }

    public function test_shop_owner_registration_rejects_non_ten_digit_phone_numbers(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $response = $this->from(route('shop-owner.register'))->post(route('shop-owner.register.store'), [
            'shop_name' => 'Bad Phone Shop',
            'owner_name' => 'Owner Three',
            'email' => 'owner3@freshfields.test',
            'phone' => '1234 5678 9012',
            'address' => 'Third street',
            'password' => 'OwnerPass88',
            'password_confirmation' => 'OwnerPass88',
        ]);

        $response->assertRedirect(route('shop-owner.register'));
        $response->assertSessionHasErrors(['phone']);
    }

    public function test_pending_shop_owner_cannot_sign_in_until_approved(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::create([
            'code' => 'REG-SHOP-999',
            'name' => 'Pending Shop',
            'status' => 'pending_approval',
        ]);

        $user = User::factory()->create([
            'email' => 'pending@shop.test',
            'password' => 'PendingPass88',
            'shop_id' => $shop->id,
            'registration_status' => 'pending',
        ]);
        $user->syncRoles(['shop']);

        $response = $this->post(route('login.submit'), [
            'email' => 'pending@shop.test',
            'password' => 'PendingPass88',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }
}
