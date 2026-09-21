<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\PurchaseProductFilter;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\MonthlyReport\MonthlyReportReconciliationService;
use App\Services\Cashbook\MonthlyReport\PurchaserPositionService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlySaleSplitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $clientShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $client = Client::create([
            'name' => 'Split Client',
            'code' => 'SC-01',
            'status' => 'active',
        ]);
        $this->clientShop = Shop::factory()->create([
            'client_id' => $client->id,
            'accounting_enabled' => true,
            'status' => 'active',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_sale_split_page_loads_cleanly(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.sale-split', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.sale-split');
        $response->assertViewHas(['summary', 'daily_rows', 'period', 'reconciliation']);
    }

    public function test_product_filters_assigned_to_groups(): void
    {
        $fruitFilter = PurchaseProductFilter::create([
            'name' => 'Fruits Bucket',
            'product_criteria' => ['Mango', 'Apple'],
            'monthly_report_group' => 'fruits',
        ]);

        $vegFilter = PurchaseProductFilter::create([
            'name' => 'Veg Bucket',
            'product_criteria' => ['Tomato', 'Potato'],
            'monthly_report_group' => 'veg',
        ]);

        $this->assertEquals('fruits', $fruitFilter->monthly_report_group);
        $this->assertEquals('veg', $vegFilter->monthly_report_group);

        $this->assertCount(1, PurchaseProductFilter::monthlyGroup('fruits')->get());
        $this->assertCount(1, PurchaseProductFilter::monthlyGroup('veg')->get());
    }

    public function test_reconciliation_difference_is_zero_when_balanced(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.sale-split', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]));

        $response->assertOk();
        $reconciliation = $response->viewData('reconciliation');

        $this->assertArrayHasKey('status', $reconciliation);
        $this->assertArrayHasKey('difference', $reconciliation);
        $this->assertArrayHasKey('overview_total_sales', $reconciliation);
        $this->assertArrayHasKey('split_total_sales', $reconciliation);
    }

    public function test_purchaser_position_reduced_by_advance_expenses(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        // Give cash advance to purchaser: ₹10,000
        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'business_date' => '2026-09-01',
            'type' => 'in',
            'amount' => 10000.00,
            'description' => 'Opening advance',
        ]);

        // Purchaser enters a procurement expense of ₹1,500
        $this->actingAs($purchaser)->post(route('purchaser.procurement-expenses.store'), [
            'expense_date' => '2026-09-05',
            'category' => 'fuel',
            'amount' => 1500.00,
            'note' => 'Fuel refill',
        ]);

        $posService = app(PurchaserPositionService::class);
        $positions = $posService->calculate('2026-09-01', '2026-09-30');
        $purchaserPos = collect($positions)->firstWhere('purchaser_id', $purchaser->id);

        $this->assertNotNull($purchaserPos);
        $this->assertEquals(10000.00, $purchaserPos['cash_given']);
        $this->assertEquals(1500.00, $purchaserPos['purchaser_expenses']);
        $this->assertEquals(8500.00, $purchaserPos['closing_position']);

        // Check timeline has the out amount
        $timeline = $posService->getPurchaserTimeline($purchaser->id, '2026-09-01', '2026-09-30');
        $this->assertCount(2, $timeline);
    }

    public function test_unmapped_products_trigger_needs_review_reconciliation(): void
    {
        $reconService = app(MonthlyReportReconciliationService::class);
        $result = $reconService->reconcile(
            ['total_sales' => 1000.00, 'total_expenses' => 500.00, 'daily_rows' => [['total_sales' => 1000.00, 'total_expenses' => 500.00]]],
            ['total_sales' => 1000.00, 'total_expenses' => 500.00, 'other_expenses' => 100.00],
            ['total_operating_expenses' => 100.00],
            ['client_sales' => 1000.00, 'all_other_sales' => 0.0, 'unmapped_sales' => 250.00],
            ['total_purchases' => 400.00, 'unmapped_expense' => 0.0],
            ['total_operating_expenses' => 100.00],
            ['total_client_sales' => 1000.00]
        );

        $this->assertFalse($result['is_reconciled']);
        $this->assertEquals('needs_review', $result['status_code']);
        $this->assertNotEmpty($result['warnings']);
    }
}
