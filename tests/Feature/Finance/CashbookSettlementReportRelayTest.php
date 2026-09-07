<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\ShopSettlementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashbookSettlementReportRelayTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Permission::findOrCreate('sales.order.create');

        $this->shop = Shop::factory()->create([
            'name' => 'Relay Test Shop',
            'code' => 'RELAY-SO-01',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->owner = User::factory()->create([
            'email' => 'owner@relay.com',
            'shop_id' => $this->shop->id,
        ]);
        $this->owner->assignRole('shop');
        $this->owner->givePermissionTo('sales.order.create');

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_show_in_summary_column_and_fillable_setting(): void
    {
        $salesType = LedgerEntryType::firstOrCreate(['code' => 'test_summary_type'], ['name' => 'Test Summary Entry', 'category' => 'income', 'active' => true]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'show_in_summary' => false,
        ]);

        $this->assertDatabaseHas('shop_ledger_entry_settings', [
            'id' => $setting->id,
            'enabled' => true,
            'show_in_summary' => false,
        ]);

        $setting->update(['show_in_summary' => true]);
        $this->assertTrue($setting->fresh()->show_in_summary);
    }

    public function test_shop_owner_cashbook_ensures_default_settlements_and_passes_relation_json(): void
    {
        app(ShopSettlementService::class)->ensureDefaults($this->shop);

        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->get(route('shop-owner.cashbook.show', ['date' => '2026-09-07']));

        $response->assertOk();
        $response->assertSee('CashbookSettlementSummary', false);
        $response->assertSee('SETTLEMENTS');

        $relations = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('enabled', true)->get();
        $this->assertNotEmpty($relations);
    }
}
