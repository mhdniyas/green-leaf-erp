<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
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

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo('accounting.ledger.view');

        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_legacy_financial_reports_redirect_to_finance_board(): void
    {
        $this->actingAs($this->authorizedUser)
            ->get(route('finance.reports.pnl'))
            ->assertRedirect(route('finance.vendors.index'));

        $this->actingAs($this->authorizedUser)
            ->get(route('finance.reports.balance-sheet'))
            ->assertRedirect(route('finance.vendors.index'));

        $this->actingAs($this->authorizedUser)
            ->get(route('finance.reports.cash-flow'))
            ->assertRedirect(route('finance.vendors.index'));
    }

    public function test_unauthorized_user_cannot_open_legacy_financial_reports(): void
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
