<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopOwnerPwaLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shop_dashboard_is_available_at_pwa_start_url(): void
    {
        $shopOwner = $this->shopOwner();

        $this
            ->actingAs($shopOwner)
            ->get('/dashboard')
            ->assertRedirect(route('shop.dashboard'));

        $this
            ->actingAs($shopOwner)
            ->get(route('shop.dashboard'))
            ->assertOk()
            ->assertSee('data-pwa-install-button', false);
    }

    public function test_remember_me_login_accepts_shop_owner_and_keeps_their_shop_id(): void
    {
        $shopOwner = $this->shopOwner([
            'email' => 'shop-pwa@greenleaf.test',
            'shop_id' => null,
        ]);
        $assignedShopId = $shopOwner->ownedShopAssignments()->value('shop_id');

        $response = $this->post(route('login.submit'), [
            'email' => 'shop-pwa@greenleaf.test',
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($shopOwner);
        $this->assertSame($assignedShopId, $shopOwner->fresh()->shop_id);
    }

    public function test_inactive_shop_owner_cannot_login(): void
    {
        $this->shopOwner([
            'email' => 'inactive-shop@greenleaf.test',
            'registration_status' => 'disabled',
        ]);

        $this
            ->from(route('login'))
            ->post(route('login.submit'), [
                'email' => 'inactive-shop@greenleaf.test',
                'password' => 'password',
                'remember' => '1',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function shopOwner(array $attributes = []): User
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create();
        $shopOwner = User::factory()->create(array_merge([
            'registration_status' => 'approved',
            'shop_id' => $shop->id,
        ], $attributes));

        $shopOwner->assignRole(Role::findByName('shop'));
        ShopOwnerAssignment::factory()->create([
            'user_id' => $shopOwner->id,
            'shop_id' => $shop->id,
        ]);

        return $shopOwner;
    }
}
