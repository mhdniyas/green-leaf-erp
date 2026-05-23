<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountsTest extends TestCase
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
        $this->authorizedUser->givePermissionTo('accounting.ledger.view');

        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_default_chart_of_accounts_seeds_correctly(): void
    {
        $this->assertDatabaseHas('accounts', ['code' => '1010', 'name' => 'Cash on Hand', 'type' => 'asset']);
        $this->assertDatabaseHas('accounts', ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability']);
        $this->assertDatabaseHas('accounts', ['code' => '3100', 'name' => 'Owner\'s Equity', 'type' => 'equity']);
        $this->assertDatabaseHas('accounts', ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue']);
        $this->assertDatabaseHas('accounts', ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'expense']);
    }

    public function test_authorized_user_can_view_chart_of_accounts(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('finance.accounts.index'));

        $response->assertOk();
        $response->assertSee('Chart of Accounts');
        $response->assertSee('Cash on Hand');
        $response->assertSee('Sales Revenue');
    }

    public function test_unauthorized_user_cannot_view_chart_of_accounts(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('finance.accounts.index'));

        $response->assertForbidden();
    }
}
