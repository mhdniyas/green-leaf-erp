<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\OtherExpense;
use App\Models\User;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseReportAndOtherExpensesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_other_expenses_page_loads_cleanly(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.other-expenses', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.other-expenses');
        $response->assertViewHas(['detailed_rows', 'total_other_expenses', 'period']);
    }

    public function test_expense_report_page_loads_with_matrix(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.expense-report', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.expense-report');
        $response->assertViewHas(['daily_matrix', 'totals', 'total_operating_expenses', 'period', 'reconciliation']);
    }

    public function test_funding_source_assignment_on_expenses(): void
    {
        $otherExpCompany = OtherExpense::create([
            'user_id' => $this->admin->id,
            'category' => OtherExpense::CategoryTravel,
            'amount' => 1200.00,
            'expense_date' => '2026-09-10',
            'funding_source' => OtherExpense::FUNDING_COMPANY_CASH,
            'note' => 'Company cash paid for van service',
        ]);

        $otherExpPurchaser = OtherExpense::create([
            'user_id' => $this->admin->id,
            'category' => OtherExpense::CategoryTravel,
            'amount' => 800.00,
            'expense_date' => '2026-09-10',
            'funding_source' => OtherExpense::FUNDING_PURCHASER_ADVANCE,
            'note' => 'Purchaser paid from advance',
        ]);

        $this->assertEquals('company_cash', $otherExpCompany->funding_source);
        $this->assertEquals('purchaser_advance', $otherExpPurchaser->funding_source);
    }
}
