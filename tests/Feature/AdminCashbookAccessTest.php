<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Client;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
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

        $this->assertSame(
            'direct_buyer',
            ShopLedgerProfile::query()->where('shop_id', $ownedShop->id)->value('profile_template')
        );
    }

    public function test_shop_sync_uses_erp_eligibility_reconciles_stale_profiles_and_maps_clients_stably(): void
    {
        $legacyLedgerClient = LedgerClient::query()->create([
            'name' => 'Future Client Legacy Record',
            'slug' => 'future-client-legacy-record',
            'enabled' => true,
        ]);

        $erpClient = Client::query()->create([
            'code' => 'FUTURE_CLIENT',
            'name' => 'Future Client',
            'status' => 'active',
        ]);
        $clientShop = Shop::factory()->create([
            'code' => 'FUTURE_SHOP',
            'name' => 'Future Shop',
            'client_id' => $erpClient->id,
            'accounting_enabled' => true,
            'accounting_mode' => 'standard',
        ]);
        $staleShop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'regular',
            'client_id' => null,
        ]);
        ShopLedgerProfile::query()->create([
            'shop_id' => $clientShop->id,
            'uuid' => fake()->uuid(),
            'slug' => 'future-shop-stable-url',
            'code' => $clientShop->code,
            'name' => $clientShop->name,
            'client_id' => $legacyLedgerClient->id,
            'enabled' => true,
        ]);
        $staleProfile = ShopLedgerProfile::query()->create([
            'shop_id' => $staleShop->id,
            'uuid' => fake()->uuid(),
            'slug' => 'stale-profile',
            'code' => $staleShop->code,
            'name' => $staleShop->name,
            'enabled' => true,
        ]);

        $profiles = app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $ledgerClient = LedgerClient::query()->where('erp_client_id', $erpClient->id)->firstOrFail();
        $syncedProfile = ShopLedgerProfile::query()->where('shop_id', $clientShop->id)->firstOrFail();

        $this->assertTrue($profiles->contains('shop_id', $clientShop->id));
        $this->assertFalse($profiles->contains('shop_id', $staleShop->id));
        $this->assertSame($ledgerClient->id, $syncedProfile->client_id);
        $this->assertSame($legacyLedgerClient->id, $ledgerClient->id);
        $this->assertSame('future-shop-stable-url', $syncedProfile->slug);
        $this->assertFalse($staleProfile->fresh()->enabled);
    }

    public function test_cashbook_uses_the_requested_date(): void
    {
        $mainAdmin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $mainAdmin->assignRole('admin');

        $this->actingAs($mainAdmin)
            ->get(route('admin.cashbook.index', ['date' => '2026-08-13']))
            ->assertOk()
            ->assertSee('13 Aug 2026')
            ->assertSee('let currentDate = "2026-08-13";', false);
    }
}
