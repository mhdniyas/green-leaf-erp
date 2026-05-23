<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo('accounting.report.view');

        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_authorized_user_can_view_reports(): void
    {
        // P&L
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('finance.reports.pnl'));
        $response->assertOk();
        $response->assertSee('Profit & Loss Statement'); // HTML escaped automatically by assertSee

        // Balance Sheet
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('finance.reports.balance-sheet'));
        $response->assertOk();
        $response->assertSee('Balance Sheet');

        // Cash Flow
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('finance.reports.cash-flow'));
        $response->assertOk();
        $response->assertSee('Cash Flow Statement');
    }

    public function test_unauthorized_user_cannot_view_reports(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('finance.reports.pnl'));
        $response->assertForbidden();

        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('finance.reports.balance-sheet'));
        $response->assertForbidden();

        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('finance.reports.cash-flow'));
        $response->assertForbidden();
    }
}
