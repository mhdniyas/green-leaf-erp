<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCashbookAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('admin.user_access.main_admin_email', 'admin@greenleaf.com');
        Role::findOrCreate('admin', 'web');
    }

    public function test_only_the_configured_main_admin_can_open_the_new_cashbook(): void
    {
        $mainAdmin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $mainAdmin->assignRole('admin');

        $otherAdmin = User::factory()->create(['email' => 'other-admin@greenleaf.com']);
        $otherAdmin->assignRole('admin');

        $this->actingAs($mainAdmin)
            ->get(route('admin.cashbook.index'))
            ->assertOk()
            ->assertSee('Cashbook');

        $this->actingAs($otherAdmin)
            ->getJson(route('admin.cashbook.index'))
            ->assertForbidden();
    }

    public function test_cashbook_dynamically_lists_only_owned_accounting_shops(): void
    {
        $mainAdmin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $mainAdmin->assignRole('admin');

        $ownedShop = Shop::factory()->create([
            'name' => 'Green Leaf Owned Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $clientShop = Shop::factory()->create([
            'name' => 'Connected Client Shop',
            'client_id' => Client::query()->create([
                'code' => 'CLIENT-001',
                'name' => 'Connected Client',
                'status' => 'active',
            ])->id,
            'accounting_enabled' => true,
            'accounting_mode' => 'standard',
        ]);
        $nonOwnedShop = Shop::factory()->create([
            'name' => 'Third Party Accounting Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'standard',
        ]);
        $disabledOwnedShop = Shop::factory()->create([
            'name' => 'Disabled Owned Shop',
            'accounting_enabled' => false,
            'accounting_mode' => 'owned',
        ]);

        $this->actingAs($mainAdmin)
            ->get(route('admin.cashbook.index'))
            ->assertOk()
            ->assertSee($ownedShop->name)
            ->assertSee($clientShop->name)
            ->assertDontSee($nonOwnedShop->name)
            ->assertDontSee($disabledOwnedShop->name);
    }
}
