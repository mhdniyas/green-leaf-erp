<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopLedgerDemoRealDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();

        $this->shop = Shop::query()->create([
            'name' => 'Casio Shop',
            'code' => 'CASIO_TEST',
            'warehouse_tag' => 'CASIO',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_or_unauthorized_user_cannot_access_real_data(): void
    {
        $response = $this->getJson(route('admin.cashbook.settings.shop.demo.real-data', [
            'shop' => $this->shop->id,
            'date' => '2026-09-01',
        ]));
        $response->assertUnauthorized();

        $response = $this->actingAs($this->regularUser)->getJson(route('admin.cashbook.settings.shop.demo.real-data', [
            'shop' => $this->shop->id,
            'date' => '2026-09-01',
        ]));
        $response->assertForbidden();
    }

    public function test_admin_can_fetch_real_shop_data_for_single_and_multi_day(): void
    {
        $entryType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)
            ->where('entry_type_id', $entryType->id)
            ->first();

        if (! $setting) {
            $setting = ShopLedgerEntrySetting::query()->create([
                'shop_id' => $this->shop->id,
                'entry_type_id' => $entryType->id,
                'display_name' => $entryType->name,
                'effective_from' => '2026-01-01',
                'enabled' => true,
            ]);
        }

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-01',
            'entry_type_id' => $entryType->id,
            'amount' => 5000.00,
            'direction' => 'credit',
            'funding_source' => 'cash',
            'notes' => 'Evening register batch',
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.cashbook.settings.shop.demo.real-data', [
            'shop' => $this->shop->id,
            'date' => '2026-09-01',
            'days' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('shop_id', $this->shop->id)
            ->assertJsonStructure([
                'success',
                'shop_id',
                'shop_name',
                'single',
                'days' => [
                    '2026-09-01' => [
                        'date',
                        'formatted_date',
                        'amounts',
                        'notes',
                        'productRows',
                        'entry_count',
                        'opening_cash',
                        'closing_cash',
                        'total_income',
                        'total_expense',
                        'net_sales',
                    ],
                ],
            ]);

        $dayData = $response->json('days.2026-09-01');
        $activeSetting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)
            ->where('entry_type_id', $entryType->id)
            ->where('enabled', true)
            ->first();

        $this->assertNotNull($activeSetting);
        $this->assertEquals(5000.0, (float) $dayData['amounts'][$activeSetting->id]);
        $this->assertEquals('Evening register batch', $dayData['notes'][$activeSetting->id]);
    }
}
