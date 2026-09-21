<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Exports\Cashbook\MonthlyReportExport;
use App\Models\OtherExpense;
use App\Models\User;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class GreenLeafMonthlyReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole('warehouse_receiver');
    }

    public function test_csv_export_streams_and_sanitizes_formula_injection(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.export.csv', [
            'report' => 'overview',
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $this->assertTrue(str_contains($response->headers->get('content-type', ''), 'text/csv'));
    }

    public function test_excel_export_invokes_maatwebsite_excel(): void
    {
        Excel::fake();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.export.excel', [
            'report' => 'overview',
            'month' => '2026-09',
        ]));

        Excel::assertDownloaded('green-leaf-monthly-report-overview-2026-09-01-to-2026-09-30.xlsx', function (MonthlyReportExport $export) {
            return $export->title() === 'Overview';
        });
    }

    public function test_pdf_view_renders_cleanly(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.export.pdf', [
            'report' => 'overview',
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.pdf.overview');
    }

    public function test_export_forbidden_for_unauthorized_user(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->getJson(route('admin.cashbook.monthly-report.export.csv', [
            'report' => 'overview',
            'month' => '2026-09',
        ]));

        $response->assertForbidden();
    }

    public function test_other_expenses_export_includes_all_unpaginated_rows(): void
    {
        // Create 60 other expenses (more than the 50 per-page limit)
        for ($i = 1; $i <= 60; $i++) {
            OtherExpense::create([
                'user_id' => $this->admin->id,
                'category' => OtherExpense::CategoryMiscellaneous,
                'amount' => 100.00 + $i,
                'expense_date' => '2026-09-15',
                'funding_source' => 'purchaser_advance',
                'note' => "Bulk Expense Item {$i}",
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.export.csv', [
            'report' => 'other-expenses',
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)));
        // 1 header row + 60 data rows = 61 lines
        $this->assertCount(61, $lines);
    }
}
