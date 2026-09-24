<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Client;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookSidebarAndScopedMoneyFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $clientAishwarya;

    private Shop $shopCasio;

    private Shop $shopLulu;

    private Shop $shopDirect;

    private ShopLedgerProfile $profileCasio;

    private ShopLedgerProfile $profileLulu;

    private ShopLedgerProfile $profileDirect;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        CompanyAccount::create([
            'name' => 'Kotak Bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => '9988776655',
            'account_type' => 'bank',
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);

        $this->clientAishwarya = Client::create([
            'name' => 'Aishwarya Veg',
            'code' => 'AV',
            'status' => 'active',
        ]);

        $ledgerClient = LedgerClient::create([
            'name' => 'Aishwarya Veg',
            'slug' => 'av',
            'erp_client_id' => $this->clientAishwarya->id,
            'enabled' => true,
        ]);

        $this->shopCasio = Shop::factory()->create([
            'name' => 'Casio',
            'code' => 'CASIO',
            'client_id' => $this->clientAishwarya->id,
            'status' => 'active',
        ]);

        $this->shopLulu = Shop::factory()->create([
            'name' => 'Lulu Budhigere',
            'code' => 'LULU',
            'client_id' => $this->clientAishwarya->id,
            'status' => 'active',
        ]);

        $this->shopDirect = Shop::factory()->create([
            'name' => 'Quick Mart',
            'code' => 'QUICK',
            'client_id' => null,
            'status' => 'active',
        ]);

        $this->profileCasio = ShopLedgerProfile::create([
            'shop_id' => $this->shopCasio->id,
            'code' => $this->shopCasio->code,
            'name' => $this->shopCasio->name,
            'slug' => 'av-casio-casio',
            'client_id' => $ledgerClient->id,
            'enabled' => true,
        ]);

        $this->profileLulu = ShopLedgerProfile::create([
            'shop_id' => $this->shopLulu->id,
            'code' => $this->shopLulu->code,
            'name' => $this->shopLulu->name,
            'slug' => 'av-lulu-budhigere-lulu-budhigere',
            'client_id' => $ledgerClient->id,
            'enabled' => true,
        ]);

        $this->profileDirect = ShopLedgerProfile::create([
            'shop_id' => $this->shopDirect->id,
            'code' => $this->shopDirect->code,
            'name' => $this->shopDirect->name,
            'slug' => 'ds-quick-mart-quick-mart',
            'client_id' => null,
            'enabled' => true,
        ]);
    }

    public function test_all_shops_money_flow_renders_all_shops_and_sidebar_structure(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow'));

        $response->assertOk()
            ->assertViewIs('admin.cashbook.money-flow.index')
            ->assertSee('All Shops')
            ->assertSee('Aishwarya Veg')
            ->assertSee('Direct Shops')
            ->assertSee('Casio')
            ->assertSee('Lulu Budhigere')
            ->assertSee('Quick Mart')
            ->assertSee('Others')
            ->assertSee('All Shops Overview')
            ->assertSee('Single Shop Ledger')
            ->assertSee('Shop Ledger CRUD')
            ->assertSee('Post Entry Simulator');

        $cards = $response->viewData('shopCards');
        $this->assertCount(3, $cards);
    }

    public function test_client_scoped_money_flow_renders_only_client_shops(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow.client', $this->clientAishwarya));

        $response->assertOk()
            ->assertViewIs('admin.cashbook.money-flow.index')
            ->assertSee('Aishwarya Veg')
            ->assertSee('Casio')
            ->assertSee('Lulu Budhigere');

        $cards = $response->viewData('shopCards');
        $this->assertCount(2, $cards);
        $this->assertContains('Casio', array_column($cards, 'shop_name'));
        $this->assertContains('Lulu Budhigere', array_column($cards, 'shop_name'));
        $this->assertNotContains('Quick Mart', array_column($cards, 'shop_name'));
    }

    public function test_direct_shops_money_flow_renders_only_unlinked_shops(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow.direct-shops'));

        $response->assertOk()
            ->assertViewIs('admin.cashbook.money-flow.index')
            ->assertSee('Direct Shops')
            ->assertSee('Quick Mart');

        $cards = $response->viewData('shopCards');
        $this->assertCount(1, $cards);
        $this->assertSame('Quick Mart', $cards[0]['shop_name']);
        $this->assertNotContains('Casio', array_column($cards, 'shop_name'));
    }

    public function test_single_shop_view_highlights_under_client_hierarchy_in_sidebar(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', ['shop' => 'av-casio-casio']));

        $response->assertOk();

        // The single shop should be rendered in sidebar under Aishwarya Veg
        $response->assertSee('Aishwarya Veg');
        $response->assertSee('Casio');
        $response->assertSee(route('admin.cashbook.shop.show', ['shop' => 'av-casio-casio']));
    }

    public function test_inactive_shops_are_excluded_from_sidebar_and_money_flow(): void
    {
        $inactiveShop = Shop::factory()->create([
            'name' => 'Ashirwad Closed',
            'code' => 'ASHIRWAD',
            'client_id' => $this->clientAishwarya->id,
            'status' => 'inactive',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $inactiveShop->id,
            'code' => $inactiveShop->code,
            'name' => $inactiveShop->name,
            'slug' => 'av-ashirwad-ashirwad',
            'client_id' => $this->profileCasio->client_id,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow'));

        $response->assertOk()
            ->assertDontSee('Ashirwad Closed');

        $cards = $response->viewData('shopCards');
        $this->assertNotContains('Ashirwad Closed', array_column($cards, 'shop_name'));
    }
}
