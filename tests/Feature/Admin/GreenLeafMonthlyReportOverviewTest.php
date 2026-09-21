<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cashbook\ShopCashbookMonthConfigSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Client;
use App\Models\DirectCompanySale;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GreenLeafMonthlyReportOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $clientShop;

    private ShopLedgerProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $client = Client::create([
            'name' => 'Overview Client',
            'code' => 'OC-01',
            'status' => 'active',
        ]);
        $this->clientShop = Shop::factory()->create([
            'client_id' => $client->id,
            'accounting_enabled' => true,
            'status' => 'active',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $this->profile = ShopLedgerProfile::where('shop_id', $this->clientShop->id)->firstOrFail();
    }

    public function test_overview_page_loads_with_correct_structure(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.overview', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.overview');
        $response->assertViewHas(['summary', 'daily_rows', 'period', 'reconciliation']);
    }

    public function test_overview_calculates_client_and_other_sales_correctly(): void
    {
        // 1. Client shop sale ledger transaction (Credit/income type with positive amount)
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->clientShop->id)->firstOrFail();
        $setting->update(['monthly_report_bucket' => 'fruits_sale', 'enabled' => true]);
        $salesType = $setting->entryType;

        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-15',
            'amount' => 5000.00,
            'direction' => 'credit',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'approved',
        ]);

        // 2. Direct company sale (All other sales)
        DirectCompanySale::create([
            'business_date' => '2026-09-15',
            'amount' => 2500.00,
            'sale_status' => 'confirmed',
            'payment_status' => 'paid',
            'notes' => 'Direct B2B Sale',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.overview', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');

        $this->assertEquals(5000.00, $summary['client_sales']);
        $this->assertEquals(2500.00, $summary['all_other_sales']);
        $this->assertEquals(7500.00, $summary['total_sales']);
    }

    public function test_drilldown_modal_json_endpoint(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.drilldown', [
            'metric' => 'total_sales',
            'month' => '2026-09',
            'date' => '2026-09-15',
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'period',
            'metric',
            'metric_label',
            'total',
            'rows',
            'row_count',
        ]);
    }

    public function test_historical_snapshots_isolate_past_month_reports(): void
    {
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->clientShop->id)->firstOrFail();
        $setting->update(['monthly_report_bucket' => 'fruits_sale', 'enabled' => true]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $setting->entry_type_id,
            'business_date' => '2026-08-10',
            'amount' => 12000.00,
            'direction' => 'credit',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'approved',
        ]);

        // Create a historical snapshot for 2026-08 capturing fruits_sale
        ShopCashbookMonthConfigSnapshot::create([
            'shop_id' => $this->clientShop->id,
            'month' => '2026-08',
            'status' => 'live_frozen',
            'source' => 'monthly_close',
            'config_data' => [
                'settings' => [
                    [
                        'id' => $setting->id,
                        'entry_type_id' => $setting->entry_type_id,
                        'monthly_report_bucket' => 'fruits_sale',
                        'header_group_id' => $setting->header_group_id,
                    ],
                ],
            ],
            'captured_by' => $this->admin->id,
        ]);

        // Now modify live setting today to ignore
        $setting->update(['monthly_report_bucket' => 'ignore']);

        // Historical report for 2026-08 must still calculate the 12000.00 fruits_sale from the snapshot!
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.overview', [
            'month' => '2026-08',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(12000.00, $summary['client_sales']);
        $this->assertEquals(12000.00, $summary['total_sales']);
    }
}
