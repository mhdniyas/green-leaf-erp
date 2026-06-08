<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_profile_page(): void
    {
        $this->get(route('profile.show'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_profile_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Profile Update')
            ->assertSee($user->email);
    }

    public function test_shop_user_can_view_profile_page(): void
    {
        $shop = Shop::create([
            'code' => 'PROFILE_SHOP',
            'name' => 'Profile Shop',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $user->assignRole('shop');

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Profile Update')
            ->assertSee('Profile Shop');
    }

    public function test_user_can_update_profile_without_changing_password(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'name' => 'Old Name',
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Updated User',
                'email' => 'updated@example.com',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_user_profile_update_requires_password_confirmation_when_password_is_present(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'wrong-password',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors('password');
    }
}
