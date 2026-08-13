<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Client;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
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

    public function test_cashbook_dynamically_lists_client_shops_and_direct_shops(): void
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
        $directRegularShop = Shop::factory()->create([
            'name' => 'Direct Regular Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'standard',
        ]);
        $directDisabledShop = Shop::factory()->create([
            'name' => 'Direct Disabled Shop',
            'accounting_enabled' => false,
            'accounting_mode' => 'owned',
        ]);

        $this->actingAs($mainAdmin)
            ->get(route('admin.cashbook.index'))
            ->assertOk()
            ->assertSee($ownedShop->name)
            ->assertSee($clientShop->name)
            ->assertSee($directRegularShop->name)
            ->assertSee($directDisabledShop->name);

        $this->assertSame(
            'direct_buyer',
            ShopLedgerProfile::query()->where('shop_id', $ownedShop->id)->value('profile_template')
        );
    }

    public function test_shop_sync_reconciles_client_profiles_maps_clients_stably_and_includes_direct_shops(): void
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
        $directShop = Shop::factory()->create([
            'code' => 'DIRECT_SHOP',
            'name' => 'Direct Shop',
            'accounting_enabled' => false,
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
        $directProfile = ShopLedgerProfile::query()->create([
            'shop_id' => $directShop->id,
            'uuid' => fake()->uuid(),
            'slug' => 'direct-shop-stable-url',
            'code' => $directShop->code,
            'name' => $directShop->name,
            'enabled' => true,
        ]);

        $profiles = app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $ledgerClient = LedgerClient::query()->where('erp_client_id', $erpClient->id)->firstOrFail();
        $syncedProfile = ShopLedgerProfile::query()->where('shop_id', $clientShop->id)->firstOrFail();
        $syncedDirectProfile = ShopLedgerProfile::query()->where('shop_id', $directShop->id)->firstOrFail();

        $this->assertTrue($profiles->contains('shop_id', $clientShop->id));
        $this->assertTrue($profiles->contains('shop_id', $directShop->id));
        $this->assertSame($ledgerClient->id, $syncedProfile->client_id);
        $this->assertSame($legacyLedgerClient->id, $ledgerClient->id);
        $this->assertSame('future-shop-stable-url', $syncedProfile->slug);
        $this->assertSame('direct_buyer', $syncedDirectProfile->profile_template);
        $this->assertNull($syncedDirectProfile->client_id);
        $this->assertTrue($directProfile->fresh()->enabled);
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

    public function test_cashbook_defaults_to_today(): void
    {
        $this->travelTo('2026-08-13 10:00:00');

        $mainAdmin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $mainAdmin->assignRole('admin');

        $this->actingAs($mainAdmin)
            ->get(route('admin.cashbook.index'))
            ->assertOk()
            ->assertSee('13 Aug 2026')
            ->assertSee('let currentDate = "2026-08-13";', false);
    }

    public function test_cashbook_monthly_shop_data_clamps_future_month_end_to_today(): void
    {
        $this->travelTo('2026-08-13 10:00:00');

        $mainAdmin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $mainAdmin->assignRole('admin');

        $entryType = LedgerEntryType::query()->create([
            'code' => 'test_sales',
            'name' => 'Test Sales',
            'category' => 'income',
            'system_type' => 'manual',
            'active' => true,
        ]);
        $shop = Shop::factory()->create([
            'client_id' => null,
            'accounting_enabled' => false,
            'accounting_mode' => 'regular',
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-13',
            'entry_type_id' => $entryType->id,
            'amount' => 100,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_income' => true,
            'affects_pl' => true,
            'pl_delta' => 100,
        ]);
        ShopLedgerTransaction::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-31',
            'entry_type_id' => $entryType->id,
            'amount' => 900,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_income' => true,
            'affects_pl' => true,
            'pl_delta' => 900,
        ]);

        $response = $this->actingAs($mainAdmin)
            ->getJson(route('admin.cashbook.api.shop-data', [
                'shop_id' => $shop->id,
                'business_date' => '2026-08-31',
                'timeframe' => 'monthly',
            ]));

        $response->assertOk();
        $this->assertEquals(100.0, $response->json('snapshot.total_sales'));
        $this->assertEquals(100.0, $response->json('snapshot.net_pl'));
    }

    public function test_direct_shops_are_preserved_and_grouped_from_cashbook_profile_state(): void
    {
        $mainAdmin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $mainAdmin->assignRole('admin');

        $shop = Shop::factory()->create([
            'name' => 'Legacy Direct Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'uuid' => fake()->uuid(),
            'slug' => 'legacy-direct-shop',
            'code' => $shop->code,
            'name' => $shop->name,
            'profile_template' => 'direct_buyer',
            'enabled' => true,
        ]);

        $shop->forceFill(['accounting_mode' => 'regular'])->save();

        $response = $this->actingAs($mainAdmin)
            ->getJson(route('admin.cashbook.api.all-shops-overview', ['business_date' => '2026-08-13']));

        $response->assertOk();
        $this->assertSame(
            [$shop->id],
            collect($response->json('direct_owned_shops'))->pluck('shop.shop_id')->all()
        );
        $this->assertTrue(
            (bool) ShopLedgerProfile::query()->where('shop_id', $shop->id)->value('enabled')
        );
    }

    public function test_direct_shop_overview_uses_shop_invoice_totals_for_gl_bills(): void
    {
        $mainAdmin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $mainAdmin->assignRole('admin');

        $shop = Shop::factory()->create([
            'name' => 'Direct Invoice Shop',
            'client_id' => null,
            'accounting_enabled' => false,
            'accounting_mode' => 'regular',
        ]);
        $order = ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => '2026-08-13',
            'created_by' => $mainAdmin->id,
        ]);

        ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260813-DIRECT',
            'business_date' => '2026-08-13',
            'status' => 'generated',
            'final_total' => 6315.50,
            'paid_amount' => 0,
            'balance_amount' => 6315.50,
        ]);

        $response = $this->actingAs($mainAdmin)
            ->getJson(route('admin.cashbook.api.all-shops-overview', ['business_date' => '2026-08-13']));

        $response->assertOk();

        $directRow = collect($response->json('direct_owned_shops'))->firstWhere('shop.shop_id', $shop->id);

        $this->assertNotNull($directRow);
        $this->assertSame(6315.50, $directRow['green_leaf_bill']);
        $this->assertSame(6315.50, $directRow['net_receivable']);
    }

    public function test_invoice_cashbook_projection_updates_stale_purchase_bill_without_duplicate(): void
    {
        $this->seed([LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $shop = Shop::factory()->create([
            'client_id' => Client::query()->create([
                'code' => 'BAZARO',
                'name' => 'Bazaro Client',
                'status' => 'active',
            ])->id,
            'accounting_enabled' => true,
            'accounting_mode' => 'standard',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $order = ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => '2026-08-02',
            'created_by' => User::factory()->create()->id,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260802-AV_BAZARO',
            'business_date' => '2026-08-02',
            'status' => 'generated',
            'final_total' => 50064.80,
            'paid_amount' => 0,
            'balance_amount' => 50064.80,
        ]);

        $purchaseBillType = LedgerEntryType::query()->where('code', 'purchase_bill')->firstOrFail();
        ShopLedgerTransaction::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-02',
            'entry_type_id' => $purchaseBillType->id,
            'amount' => 83584.80,
            'direction' => 'expense',
            'funding_source' => 'company',
            'affects_expense' => true,
            'affects_pl' => true,
            'pl_delta' => -83584.80,
            'reference_type' => ShopInvoice::class,
            'reference_id' => $invoice->id,
            'status' => 'posted',
        ]);

        app(InvoiceCashbookProjectionService::class)->syncInvoice($invoice);

        $transactions = ShopLedgerTransaction::query()
            ->where('reference_type', ShopInvoice::class)
            ->where('reference_id', $invoice->id)
            ->get();

        $this->assertCount(1, $transactions);
        $this->assertSame(50064.80, (float) $transactions->first()->amount);
        $this->assertSame(-50064.80, (float) $transactions->first()->pl_delta);
    }

    public function test_invoice_reconciliation_reports_stale_rows_before_apply(): void
    {
        $this->seed([LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $shop = Shop::factory()->create([
            'client_id' => Client::query()->create([
                'code' => 'CLIENT-RECON',
                'name' => 'Recon Client',
                'status' => 'active',
            ])->id,
            'accounting_enabled' => true,
            'accounting_mode' => 'standard',
        ]);
        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'invoice_number' => 'SINV-RECON',
            'business_date' => '2026-08-02',
            'status' => 'generated',
            'final_total' => 1234.50,
            'paid_amount' => 0,
            'balance_amount' => 1234.50,
        ]);

        $purchaseBillType = LedgerEntryType::query()->where('code', 'purchase_bill')->firstOrFail();
        ShopLedgerTransaction::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-02',
            'entry_type_id' => $purchaseBillType->id,
            'amount' => 999.00,
            'direction' => 'expense',
            'funding_source' => 'company',
            'affects_expense' => true,
            'affects_pl' => true,
            'pl_delta' => -999.00,
            'reference_type' => ShopInvoice::class,
            'reference_id' => $invoice->id,
            'status' => 'posted',
        ]);

        $summary = app(InvoiceCashbookProjectionService::class)->reconcile('2026-08-01', '2026-08-13');

        $this->assertSame(1, $summary['checked']);
        $this->assertCount(1, $summary['mismatches']);
        $this->assertSame(999.00, (float) ShopLedgerTransaction::query()->firstOrFail()->amount);
    }
}
