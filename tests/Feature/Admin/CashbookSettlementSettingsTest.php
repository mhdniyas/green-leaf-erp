<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\ShopSettlementService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookSettlementSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->shop = Shop::factory()->create(['accounting_enabled' => true, 'accounting_mode' => 'owned']);
        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $this->profile = ShopLedgerProfile::where('shop_id', $this->shop->id)->firstOrFail();
    }

    public function test_each_shop_gets_defaults_and_customizations_survive_sync(): void
    {
        $balance = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('relation_type', 'default_balance')->firstOrFail();
        $this->assertSame('Balance', $balance->name);
        $this->assertNotEmpty($balance->items);
        $balance->update(['name' => 'Shop Balance', 'enabled' => false]);
        $balance->items()->delete();
        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $balance->refresh();
        $this->assertSame('Shop Balance', $balance->name);
        $this->assertFalse($balance->enabled);
        $this->assertCount(0, $balance->items);
        $this->assertSame(5, ShopCashbookRelation::where('shop_id', $this->shop->id)->count());
        $this->assertNotNull(ShopCashbookRelation::where('shop_id', $this->shop->id)->where('relation_type', 'default_income')->first());
        $this->assertNotNull(ShopCashbookRelation::where('shop_id', $this->shop->id)->where('relation_type', 'default_expense')->first());
        $this->assertNotNull(ShopCashbookRelation::where('shop_id', $this->shop->id)->where('relation_type', 'default_company_payable')->first());

        $balance->delete();
        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $this->assertDatabaseMissing('shop_cashbook_relations', ['id' => $balance->id]);
        $this->assertSame(4, ShopCashbookRelation::where('shop_id', $this->shop->id)->count());
    }

    public function test_settings_link_to_separate_settlement_pages(): void
    {
        $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop', $this->profile->slug))->assertOk()->assertSee('Manage Settlements');
        $this->get($this->url('index'))->assertOk()->assertSee('Create Settlement')->assertSee('Balance')->assertSee('Company Payable');
        $this->get($this->url('create'))->assertOk()->assertSee('Formula preview')->assertSee('Add Formula Row');
    }

    public function test_admin_can_create_edit_and_disable_a_multi_category_formula(): void
    {
        $a = $this->category('a');
        $b = $this->category('b');
        $c = $this->category('c');
        $items = [['setting_id' => $a->id, 'role' => 'add'], ['setting_id' => $b->id, 'role' => 'subtract'], ['setting_id' => $c->id, 'role' => 'add']];
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'Custom Settlement', 'enabled' => 1, 'items' => $items])->assertRedirect($this->url('index'));
        $relation = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('name', 'Custom Settlement')->firstOrFail();
        $this->assertSame('formula', $relation->relation_type);
        $this->assertSame(['add', 'subtract', 'add'], $relation->items->pluck('role')->all());
        $this->get($this->url('edit', $relation))->assertOk()->assertSee('Edit Settlement');
        $this->put($this->url('update', $relation), ['name' => 'Revised Settlement', 'enabled' => 0, 'items' => [$items[1]]])->assertRedirect();
        $relation->refresh();
        $this->assertFalse($relation->enabled);
        $this->assertSame('Revised Settlement', $relation->name);
        $audit = Activity::where('log_name', 'cashbook_settlement')->latest('id')->firstOrFail();
        $this->assertSame($this->admin->id, $audit->causer_id);
        $this->assertSame('Custom Settlement', $audit->properties['before']['name']);
        $this->assertSame('Revised Settlement', $audit->properties['after']['name']);
        $this->assertCount(1, $relation->items);
    }

    public function test_target_dropdown_only_shows_active_items_for_the_current_shop(): void
    {
        $activeCategory = $this->category('active_target');
        $inactiveCategory = $this->category('inactive_target');
        $inactiveCategory->update(['enabled' => false]);
        $activeHeader = ShopLedgerHeaderGroup::create(['shop_id' => $this->shop->id, 'name' => 'Active Target Header', 'type' => 'income', 'enabled' => true]);
        $inactiveHeader = ShopLedgerHeaderGroup::create(['shop_id' => $this->shop->id, 'name' => 'Inactive Target Header', 'type' => 'income', 'enabled' => false]);
        $activeSettlement = ShopCashbookRelation::create(['shop_id' => $this->shop->id, 'name' => 'Active Target Settlement', 'relation_type' => 'formula', 'enabled' => true]);
        $inactiveSettlement = ShopCashbookRelation::create(['shop_id' => $this->shop->id, 'name' => 'Inactive Target Settlement', 'relation_type' => 'formula', 'enabled' => false]);

        $response = $this->actingAs($this->admin)->get($this->url('create'));

        $response->assertOk()
            ->assertSee('value="setting:'.$activeCategory->id.'"', false)
            ->assertDontSee('value="setting:'.$inactiveCategory->id.'"', false)
            ->assertSee('value="header:'.$activeHeader->id.'"', false)
            ->assertDontSee('value="header:'.$inactiveHeader->id.'"', false)
            ->assertSee('value="settlement:'.$activeSettlement->id.'"', false)
            ->assertDontSee('value="settlement:'.$inactiveSettlement->id.'"', false);

        $this->postJson($this->url('store'), [
            'name' => 'Invalid Inactive Target',
            'enabled' => 1,
            'items' => [['setting_id' => $inactiveCategory->id, 'role' => 'add']],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.setting_id');
    }

    public function test_admin_can_delete_configured_and_default_settlements(): void
    {
        $custom = ShopCashbookRelation::create(['shop_id' => $this->shop->id, 'name' => 'Default Petty', 'relation_type' => 'default_petty', 'enabled' => true]);
        $default = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('relation_type', 'default_income')->firstOrFail();

        $this->actingAs($this->admin)->get($this->url('index'))
            ->assertSee(route('admin.cashbook.settings.shop.settlements.destroy', [$this->profile->slug, $custom->public_uuid]), false);
        $this->actingAs($this->admin)->delete($this->url('destroy', $custom))->assertRedirect($this->url('index'));
        $this->assertModelMissing($custom);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'cashbook_settlement', 'description' => 'Settlement deleted']);

        $this->delete($this->url('destroy', $default))->assertRedirect($this->url('index'));
        $this->get($this->url('index'))->assertOk();
        $this->assertModelMissing($default);
    }

    public function test_formula_rejects_foreign_invalid_and_empty_categories_while_allowing_multiple_occurrences(): void
    {
        $a = $this->category('a');
        $foreignShop = Shop::factory()->create();
        $foreign = ShopLedgerEntrySetting::create(['shop_id' => $foreignShop->id, 'entry_type_id' => $a->entry_type_id, 'effective_from' => '2026-01-01', 'enabled' => true]);
        $cases = [
            [[['setting_id' => $foreign->id, 'role' => 'add']], 'items.0.setting_id'],
            [[['setting_id' => $a->id, 'role' => 'multiply']], 'items.0.role'],
            [[], 'items'],
        ];
        $count = ShopCashbookRelation::count();
        foreach ($cases as [$items, $error]) {
            $this->actingAs($this->admin)->postJson($this->url('store'), ['name' => 'Invalid', 'enabled' => 1, 'items' => $items])->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->assertSame($count, ShopCashbookRelation::count());

        // Verify multiple occurrences of the same category are allowed
        $this->actingAs($this->admin)->post($this->url('store'), [
            'name' => 'Duplicate Category Formula',
            'enabled' => 1,
            'items' => [
                ['setting_id' => $a->id, 'role' => 'add'],
                ['setting_id' => $a->id, 'role' => 'subtract'],
            ],
        ])->assertRedirect();
        $relation = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('name', 'Duplicate Category Formula')->firstOrFail();
        $this->assertCount(2, $relation->items);
    }

    public function test_non_admin_and_wrong_shop_cannot_manage_settlements(): void
    {
        $this->getJson($this->url('index'))->assertUnauthorized();
        $user = User::factory()->create();
        Role::findOrCreate('manager', 'web');
        $user->assignRole('manager');
        $this->actingAs($user)->getJson($this->url('create'))->assertForbidden();
        $this->postJson($this->url('store'), [])->assertForbidden();
        $foreignShop = Shop::factory()->create();
        $foreign = ShopCashbookRelation::create(['shop_id' => $foreignShop->id, 'name' => 'Foreign']);
        $this->actingAs($this->admin)->getJson($this->url('edit', $foreign))->assertNotFound();
        $a = $this->category('a');
        $this->putJson($this->url('update', $foreign), ['name' => 'Tampered', 'enabled' => 1, 'items' => [['setting_id' => $a->id, 'role' => 'add']]])->assertNotFound();
        $this->getJson(route('admin.cashbook.settings.shop.settlements.index', 'unknown-shop'))->assertNotFound();
        $this->assertSame('Foreign', $foreign->fresh()->name);
    }

    public function test_summary_calculates_all_enabled_formulas_for_only_selected_shop_and_period(): void
    {
        $a = $this->category('a');
        $b = $this->category('b');
        $c = $this->category('c');
        ShopCashbookRelation::where('shop_id', $this->shop->id)->update(['enabled' => false]);
        $service = app(ShopSettlementService::class);
        $formula = $service->save($this->profile, ['name' => 'Computed Output', 'enabled' => 1, 'items' => [['setting_id' => $a->id, 'role' => 'add'], ['setting_id' => $b->id, 'role' => 'subtract'], ['setting_id' => $c->id, 'role' => 'add']]]);
        $service->save($this->profile, ['name' => 'Negative Output', 'enabled' => 1, 'items' => [['setting_id' => $b->id, 'role' => 'subtract']]]);
        foreach ([[$a, '2026-09-01', 100.10, 'posted'], [$a, '2026-09-02', 40, 'approved'], [$b, '2026-09-01', 25.05, 'posted'], [$c, '2026-09-01', 10, 'posted'], [$a, '2026-08-31', 900, 'posted'], [$a, '2026-09-01', 900, 'voided'], [$a, '2026-09-01', 900, 'draft']] as [$setting, $date, $amount, $status]) {
            $this->transaction($setting, $date, $amount, $status);
        }
        $foreignShop = Shop::factory()->create();
        $foreign = ShopLedgerEntrySetting::create(['shop_id' => $foreignShop->id, 'entry_type_id' => $a->entry_type_id, 'effective_from' => '2026-01-01']);
        $this->transaction($foreign, '2026-09-01', 999, 'posted');
        $count = ShopLedgerTransaction::count();
        $emptyDay = $service->summary($this->shop->id, '2026-10-01', '2026-10-01');
        $this->assertEquals([0, 0], array_column($emptyDay, 'netSettlement'));
        $summary = $service->summary($this->shop->id, '2026-09-01', '2026-09-02');
        $this->assertCount(2, $summary);
        $this->assertEqualsWithDelta(125.05, $summary[0]['netSettlement'], 0.001);
        $this->assertEqualsWithDelta(-25.05, $summary[1]['netSettlement'], 0.001);
        $this->assertSame($count, ShopLedgerTransaction::count());
        $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [$this->profile->slug, 'month' => '2026-09']))->assertOk()->assertViewHas('configuredSettlements', fn ($rows): bool => count($rows) === 2)->assertSee('Computed Output');
        $this->get(route('admin.cashbook.settings.shop.demo', $this->profile->slug))->assertOk()->assertSee('CashbookSettlementSummary.calculate', false)->assertSee('Net Balance')->assertDontSee('Dynamic Header-based summary');
        $formula->update(['enabled' => false]);
        $this->assertCount(1, $service->summary($this->shop->id, '2026-09-01', '2026-09-02'));
        ShopCashbookRelation::where('shop_id', $this->shop->id)->update(['enabled' => false]);
        $this->assertSame([], $service->summary($this->shop->id, '2026-09-01', '2026-09-02'));
    }

    public function test_admin_can_copy_settlement_to_other_shops(): void
    {
        $targetShop = Shop::factory()->create(['accounting_enabled' => true, 'accounting_mode' => 'owned']);
        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $targetProfile = ShopLedgerProfile::where('shop_id', $targetShop->id)->firstOrFail();

        $a = $this->category('copy_a');
        $targetA = ShopLedgerEntrySetting::create(['shop_id' => $targetShop->id, 'entry_type_id' => $a->entry_type_id, 'effective_from' => '2026-01-01', 'enabled' => true]);

        $service = app(ShopSettlementService::class);
        $relation = $service->save($this->profile, [
            'name' => 'Outlet Formula',
            'enabled' => 1,
            'items' => [['setting_id' => $a->id, 'role' => 'add']],
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.shop.settlements.copy', [
            'shop' => $this->profile->slug,
            'settlement' => $relation->public_uuid,
        ]), [
            'target_shop_ids' => [$targetShop->id],
        ]);

        $response->assertRedirect();
        $copied = ShopCashbookRelation::where('shop_id', $targetShop->id)->where('name', 'Outlet Formula')->firstOrFail();
        $this->assertSame('formula', $copied->relation_type);
        $this->assertCount(1, $copied->items);
        $this->assertSame($targetA->id, $copied->items->first()->shop_ledger_entry_setting_id);
    }

    public function test_admin_can_create_settlement_with_header_group_and_tagged_products_mode(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Header Sales',
            'type' => 'income',
            'enabled' => true,
            'product_tagging_enabled' => true,
        ]);

        $items = [
            ['header_group_id' => $header->id, 'header_mode' => 'tagged_products_only', 'role' => 'add'],
        ];

        $this->actingAs($this->admin)
            ->post($this->url('store'), [
                'name' => 'Tagged Products Formula',
                'enabled' => 1,
                'items' => $items,
            ])
            ->assertRedirect($this->url('index'));

        $relation = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('name', 'Tagged Products Formula')->firstOrFail();
        $this->assertCount(1, $relation->items);
        $this->assertSame($header->id, $relation->items->first()->header_group_id);
        $this->assertSame('tagged_products_only', $relation->items->first()->header_mode);
    }

    public function test_admin_can_create_settlement_referencing_another_settlement(): void
    {
        $target1 = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('relation_type', 'default_income')->firstOrFail();
        $target2 = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('relation_type', 'default_expense')->firstOrFail();

        $items = [
            ['source_settlement_id' => $target1->id, 'role' => 'add'],
            ['source_settlement_id' => $target2->id, 'role' => 'subtract'],
        ];

        $this->actingAs($this->admin)
            ->post($this->url('store'), [
                'name' => 'Net Profit Shortcut Formula',
                'enabled' => 1,
                'items' => $items,
            ])
            ->assertRedirect($this->url('index'));

        $relation = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('name', 'Net Profit Shortcut Formula')->firstOrFail();
        $this->assertCount(2, $relation->items);
        $this->assertSame($target1->id, $relation->items->first()->source_settlement_id);
        $this->assertSame($target2->id, $relation->items->last()->source_settlement_id);
        $this->assertSame('subtract', $relation->items->last()->role);
    }

    public function test_admin_can_reorder_settlements(): void
    {
        $relations = ShopCashbookRelation::where('shop_id', $this->shop->id)->get();
        $reversedUuids = $relations->reverse()->pluck('public_uuid')->all();

        $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.settings.shop.settlements.reorder', $this->profile->slug), [
                'order' => $reversedUuids,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $freshRelations = ShopCashbookRelation::where('shop_id', $this->shop->id)->orderBy('display_order')->pluck('public_uuid')->all();
        $this->assertSame($reversedUuids, $freshRelations);
    }

    public function test_admin_can_set_net_balance_settlement(): void
    {
        $income = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('relation_type', 'default_income')->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.settings.shop.settlements.set-net-balance', [$this->profile->slug, $income->public_uuid]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue($income->fresh()->is_net_balance);
        $otherNetBalances = ShopCashbookRelation::where('shop_id', $this->shop->id)->where('id', '!=', $income->id)->where('is_net_balance', true)->count();
        $this->assertSame(0, $otherNetBalances);
    }

    private function category(string $code): ShopLedgerEntrySetting
    {
        $type = LedgerEntryType::create(['code' => 'test_'.$code, 'name' => 'Category '.strtoupper($code), 'category' => 'income', 'active' => true]);

        return ShopLedgerEntrySetting::create(['shop_id' => $this->shop->id, 'entry_type_id' => $type->id, 'effective_from' => '2026-01-01', 'enabled' => true]);
    }

    private function transaction(ShopLedgerEntrySetting $setting, string $date, float $amount, string $status): void
    {
        ShopLedgerTransaction::create(['shop_id' => $setting->shop_id, 'entry_type_id' => $setting->entry_type_id, 'business_date' => $date, 'amount' => $amount, 'direction' => 'income', 'funding_source' => 'sales', 'status' => $status]);
    }

    private function url(string $action, ?ShopCashbookRelation $relation = null): string
    {
        return route('admin.cashbook.settings.shop.settlements.'.$action, array_filter(['shop' => $this->profile->slug, 'settlement' => $relation?->public_uuid]));
    }
}
