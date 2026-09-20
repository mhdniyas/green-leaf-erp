<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Integrations;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\User;
use App\Models\ZohoBooksAccountMapping;
use App\Models\ZohoBooksConnection;
use App\Services\Integrations\ZohoBooks\ZohoChartOfAccountsService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ZohoBooksAccountMappingTest extends TestCase
{
    private User $admin;

    private User $regularUser;

    private ZohoBooksConnection $connection;

    private LedgerEntryType $incomeCategory;

    private LedgerEntryType $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            $this->artisan('migrate', ['--force' => true]);
        }

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create(['name' => 'Regular User']);
        $this->regularUser->assignRole('purchaser');

        Config::set('services.zoho.client_id', 'mock-client-id');
        Config::set('services.zoho.client_secret', 'mock-client-secret');

        $this->connection = ZohoBooksConnection::query()->create([
            'organization_id' => '123456789',
            'organization_name' => 'Green Leaf Fresh Ltd',
            'accounts_domain' => 'https://accounts.zoho.com',
            'api_domain' => 'https://www.zohoapis.com',
            'data_center' => 'US',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'valid-refresh-token',
            'access_token_expires_at' => Carbon::now()->addHour(),
            'scopes' => ['ZohoBooks.fullaccess.READ', 'ZohoBooks.accountants.READ'],
            'status' => 'connected',
        ]);

        $this->incomeCategory = LedgerEntryType::query()->firstOrCreate([
            'code' => 'cash_sales',
        ], [
            'name' => 'Cash Sales',
            'category' => 'income',
            'active' => true,
            'display_order' => 1,
        ]);

        $this->expenseCategory = LedgerEntryType::query()->firstOrCreate([
            'code' => 'rent',
        ], [
            'name' => 'Office Rent',
            'category' => 'expense',
            'active' => true,
            'display_order' => 2,
        ]);
    }

    public function test_non_admin_cannot_access_mapping_actions(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');

        $this->actingAs($this->regularUser)
            ->post(route('admin.integrations.zoho-books.refresh-accounts'))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->post(route('admin.integrations.zoho-books.mappings.save'))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->delete(route('admin.integrations.zoho-books.mappings.remove', $this->incomeCategory->id))
            ->assertForbidden();
    }

    public function test_connected_admin_can_read_chart_of_accounts_and_uses_stored_api_domain_and_org_id(): void
    {
        Http::fake([
            'https://www.zohoapis.com/books/v3/chartofaccounts*' => Http::response([
                'code' => 0,
                'message' => 'success',
                'chartofaccounts' => [
                    [
                        'account_id' => 'acc-101',
                        'account_name' => 'Sales Revenue',
                        'account_code' => '4000',
                        'account_type' => 'income',
                        'is_active' => true,
                    ],
                ],
                'page_context' => [
                    'page' => 1,
                    'has_more_page' => false,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']));

        $response->assertOk()
            ->assertSee('Cash Sales')
            ->assertSee('Sales Revenue')
            ->assertSee('4000');

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://www.zohoapis.com/books/v3/chartofaccounts')
                && $request->method() === 'GET'
                && $request->hasHeader('Authorization', 'Zoho-oauthtoken valid-access-token')
                && str_contains($request->url(), 'organization_id=123456789');
        });
    }

    public function test_pagination_retrieves_all_pages_from_chart_of_accounts(): void
    {
        Http::fake([
            'https://www.zohoapis.com/books/v3/chartofaccounts*page=1*' => Http::response([
                'code' => 0,
                'chartofaccounts' => [
                    ['account_id' => 'acc-1', 'account_name' => 'Sales Page 1', 'account_type' => 'income'],
                ],
                'page_context' => ['page' => 1, 'has_more_page' => true],
            ], 200),
            'https://www.zohoapis.com/books/v3/chartofaccounts*page=2*' => Http::response([
                'code' => 0,
                'chartofaccounts' => [
                    ['account_id' => 'acc-2', 'account_name' => 'Rent Page 2', 'account_type' => 'expense'],
                ],
                'page_context' => ['page' => 2, 'has_more_page' => false],
            ], 200),
        ]);

        $service = app(ZohoChartOfAccountsService::class);
        $accounts = $service->getAccounts($this->connection, forceRefresh: true);

        $this->assertCount(2, $accounts);
        $this->assertEquals('acc-1', $accounts[0]['account_id']);
        $this->assertEquals('acc-2', $accounts[1]['account_id']);
    }

    public function test_token_refresh_triggers_automatically_when_expired_during_chart_of_accounts_fetch(): void
    {
        $this->connection->update([
            'access_token' => 'expired-token',
            'access_token_expires_at' => Carbon::now()->subMinute(),
        ]);

        Http::fake([
            'https://accounts.zoho.com/oauth/v2/token' => Http::response([
                'access_token' => 'freshly-refreshed-token',
                'expires_in' => 3600,
            ], 200),
            'https://www.zohoapis.com/books/v3/chartofaccounts*' => Http::response([
                'code' => 0,
                'chartofaccounts' => [
                    ['account_id' => 'acc-999', 'account_name' => 'Bank Account', 'account_type' => 'bank'],
                ],
            ], 200),
        ]);

        $service = app(ZohoChartOfAccountsService::class);
        $accounts = $service->getAccounts($this->connection, forceRefresh: true);

        $this->assertCount(1, $accounts);
        $this->connection->refresh();
        $this->assertEquals('freshly-refreshed-token', $this->connection->access_token);
    }

    public function test_mapping_can_be_created_and_updated_and_removed(): void
    {
        Http::fake([
            'https://www.zohoapis.com/books/v3/chartofaccounts*' => Http::response([
                'code' => 0,
                'chartofaccounts' => [
                    ['account_id' => 'acc-sales', 'account_name' => 'Sales Account', 'account_code' => '4001', 'account_type' => 'income'],
                    ['account_id' => 'acc-rent', 'account_name' => 'Office Rent Account', 'account_code' => '5001', 'account_type' => 'expense'],
                ],
            ], 200),
        ]);

        // 1. Create mapping
        $response = $this->actingAs($this->admin)
            ->post(route('admin.integrations.zoho-books.mappings.save'), [
                'ledger_entry_type_id' => $this->incomeCategory->id,
                'zoho_account_id' => 'acc-sales',
            ]);

        $response->assertRedirect(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']))
            ->assertSessionHas('success');

        $mapping = ZohoBooksAccountMapping::query()
            ->where('zoho_books_connection_id', $this->connection->id)
            ->where('ledger_entry_type_id', $this->incomeCategory->id)
            ->first();

        $this->assertNotNull($mapping);
        $this->assertEquals('acc-sales', $mapping->zoho_account_id);
        $this->assertEquals('Sales Account', $mapping->zoho_account_name);
        $this->assertEquals('4001', $mapping->zoho_account_code);
        $this->assertEquals('income', $mapping->zoho_account_type);

        // 2. Update mapping to another account
        $this->actingAs($this->admin)
            ->post(route('admin.integrations.zoho-books.mappings.save'), [
                'ledger_entry_type_id' => $this->incomeCategory->id,
                'zoho_account_id' => 'acc-rent',
            ])
            ->assertRedirect(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']))
            ->assertSessionHas('success');

        $mapping->refresh();
        $this->assertEquals('acc-rent', $mapping->zoho_account_id);
        $this->assertEquals('Office Rent Account', $mapping->zoho_account_name);

        // 3. Remove mapping
        $this->actingAs($this->admin)
            ->delete(route('admin.integrations.zoho-books.mappings.remove', $this->incomeCategory->id))
            ->assertRedirect(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']))
            ->assertSessionHas('success');

        $this->assertNull(ZohoBooksAccountMapping::find($mapping->id));
    }

    public function test_duplicate_category_mapping_is_prevented_by_unique_constraint(): void
    {
        ZohoBooksAccountMapping::query()->create([
            'zoho_books_connection_id' => $this->connection->id,
            'ledger_entry_type_id' => $this->incomeCategory->id,
            'zoho_account_id' => 'acc-1',
            'zoho_account_name' => 'Account 1',
            'zoho_account_type' => 'income',
        ]);

        $this->expectException(QueryException::class);

        ZohoBooksAccountMapping::query()->create([
            'zoho_books_connection_id' => $this->connection->id,
            'ledger_entry_type_id' => $this->incomeCategory->id,
            'zoho_account_id' => 'acc-2',
            'zoho_account_name' => 'Account 2',
            'zoho_account_type' => 'income',
        ]);
    }

    public function test_inactive_or_unavailable_mapped_account_is_flagged(): void
    {
        ZohoBooksAccountMapping::query()->create([
            'zoho_books_connection_id' => $this->connection->id,
            'ledger_entry_type_id' => $this->incomeCategory->id,
            'zoho_account_id' => 'deleted-or-inactive-account-999',
            'zoho_account_name' => 'Old Sales Account',
            'zoho_account_type' => 'income',
        ]);

        Http::fake([
            'https://www.zohoapis.com/books/v3/chartofaccounts*' => Http::response([
                'code' => 0,
                'chartofaccounts' => [
                    ['account_id' => 'other-active-account', 'account_name' => 'New Sales Account', 'account_type' => 'income'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']));

        $response->assertOk()
            ->assertSee('Account Inactive/Unavailable');
    }

    public function test_no_mutation_or_financial_posting_api_requests_are_made_during_mapping(): void
    {
        Http::fake([
            'https://www.zohoapis.com/books/v3/chartofaccounts*' => Http::response([
                'code' => 0,
                'chartofaccounts' => [
                    ['account_id' => 'acc-1', 'account_name' => 'Sales', 'account_type' => 'income'],
                ],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.integrations.zoho-books.mappings.save'), [
                'ledger_entry_type_id' => $this->incomeCategory->id,
                'zoho_account_id' => 'acc-1',
            ]);

        // Assert NO POST/PUT/DELETE requests were sent to Zoho Books endpoints
        Http::assertNotSent(function ($request) {
            return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        });
    }

    public function test_missing_accountants_scope_shows_reauthorize_warning(): void
    {
        $this->connection->update([
            'scopes' => ['ZohoBooks.fullaccess.READ'], // missing accountants.READ scope explicitly
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']));

        // Note: ZohoBooks.fullaccess.READ actually satisfies accountants.READ in hasAccountantsReadScope
        $this->connection->update([
            'scopes' => ['ZohoBooks.contacts.READ'], // Scope without accountants or fullaccess
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']));

        $response->assertOk()
            ->assertSee('Additional Zoho permission required')
            ->assertSee('Reconnect Zoho Books');
    }
}
