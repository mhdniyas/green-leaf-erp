<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOwnerRegistrationLocalGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_owner_registration_is_hidden_on_local_environment(): void
    {
        config()->set('app.env', 'local');

        $userCountBefore = User::query()->count();
        $shopCountBefore = Shop::query()->count();

        $this->get(route('shop-owner.register'))->assertNotFound();

        $response = $this->post(route('shop-owner.register.store'), [
            'shop_name' => 'Local Test Shop',
            'owner_name' => 'Local Owner',
            'email' => 'local-owner@example.test',
            'phone' => '9876543210',
            'address' => 'Test Address',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertNotFound();

        $this->assertSame($userCountBefore, User::query()->count());
        $this->assertSame($shopCountBefore, Shop::query()->count());
        $this->assertDatabaseMissing('users', [
            'email' => 'local-owner@example.test',
        ]);
    }
}
