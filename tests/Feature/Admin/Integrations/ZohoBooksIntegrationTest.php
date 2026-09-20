<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Integrations;

use App\Models\User;
use App\Models\ZohoBooksConnection;
use App\Services\Integrations\ZohoBooks\ZohoBooksClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ZohoBooksIntegrationTest extends TestCase
{
    private User $admin;

    private User $regularUser;

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

        Config::set('services.zoho.client_id', 'mock-zoho-client-id');
        Config::set('services.zoho.client_secret', 'mock-zoho-client-secret');
        Config::set('services.zoho.redirect_uri', 'http://green-leaf-erp.test/admin/integrations/zoho-books/callback');
    }

    public function test_non_admin_cannot_access_zoho_books_integration_routes(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.integrations.zoho-books.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');

        $this->actingAs($this->regularUser)
            ->get(route('admin.integrations.zoho-books.connect'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');

        $this->actingAs($this->regularUser)
            ->post(route('admin.integrations.zoho-books.disconnect'))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->post(route('admin.integrations.zoho-books.test'))
            ->assertForbidden();
    }

    public function test_admin_can_view_zoho_books_page_when_disconnected(): void
    {
        ZohoBooksConnection::query()->delete();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.index'));

        $response->assertOk()
            ->assertSee('Zoho Books Integration')
            ->assertSee('Status: Not Connected')
            ->assertSee('Connect Zoho Books');
    }

    public function test_connect_generates_state_in_session_and_redirects_to_zoho_oauth(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.connect'));

        $response->assertRedirect();
        $targetUrl = (string) $response->headers->get('Location');

        $parsedUrl = parse_url($targetUrl);
        $this->assertStringContainsString('accounts.zoho.com', $parsedUrl['host'] ?? '');
        $this->assertEquals('/oauth/v2/auth', $parsedUrl['path'] ?? '');

        parse_str($parsedUrl['query'] ?? '', $queryParams);

        $this->assertArrayHasKey('state', $queryParams);
        $this->assertNotEmpty($queryParams['state']);
        $this->assertEquals(session('zoho_oauth_state'), $queryParams['state']);
        $this->assertEquals(config('services.zoho.redirect_uri'), $queryParams['redirect_uri']);
        $this->assertEquals('code', $queryParams['response_type']);
        $this->assertEquals('offline', $queryParams['access_type']);
        $this->assertEquals('consent', $queryParams['prompt']);
        $this->assertEquals('mock-zoho-client-id', $queryParams['client_id']);
        $this->assertEquals('ZohoBooks.fullaccess.READ', $queryParams['scope']);

        // Assert client secret is NEVER in the authorization URL
        $this->assertStringNotContainsString('mock-zoho-client-secret', $targetUrl);
        $this->assertArrayNotHasKey('client_secret', $queryParams);
    }

    public function test_callback_rejects_missing_or_invalid_oauth_state_and_does_not_clear_stored_state_on_mismatch(): void
    {
        session(['zoho_oauth_state' => 'valid-session-state-123']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.callback', [
                'state' => 'forged-state-456',
                'code' => 'mock-code-123',
            ]));

        $response->assertRedirect(route('admin.integrations.zoho-books.index'))
            ->assertSessionHas('error', 'Invalid OAuth state token. Please restart the Zoho Books connection flow.');

        // Verify stored state was NOT removed prematurely on mismatch
        $this->assertEquals('valid-session-state-123', session('zoho_oauth_state'));
    }

    public function test_callback_rejects_when_state_is_missing(): void
    {
        session(['zoho_oauth_state' => 'valid-session-state-123']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.callback', [
                'code' => 'mock-code-123',
            ]));

        $response->assertRedirect(route('admin.integrations.zoho-books.index'))
            ->assertSessionHas('error', 'Invalid OAuth state token. Please restart the Zoho Books connection flow.');
    }

    public function test_callback_handles_zoho_error_response(): void
    {
        session(['zoho_oauth_state' => 'valid-state']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.callback', [
                'error' => 'access_denied',
                'error_description' => 'User denied access to the application',
                'state' => 'valid-state',
            ]));

        $response->assertRedirect(route('admin.integrations.zoho-books.index'))
            ->assertSessionHas('error', 'Zoho authorization failed: User denied access to the application');
    }

    public function test_callback_exchanges_code_for_tokens_stores_encrypted_and_removes_state(): void
    {
        Http::fake([
            'https://accounts.zoho.com/oauth/v2/token' => Http::response([
                'access_token' => '1000.sample-access-token-raw-secret',
                'refresh_token' => '1000.sample-refresh-token-raw-secret',
                'api_domain' => 'https://www.zohoapis.com',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://www.zohoapis.com/books/v3/organizations' => Http::response([
                'code' => 0,
                'message' => 'success',
                'organizations' => [
                    [
                        'organization_id' => '987654321',
                        'name' => 'Green Leaf Fresh Ltd',
                        'is_default_org' => true,
                    ],
                ],
            ], 200),
        ]);

        session(['zoho_oauth_state' => 'valid-state-abc']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.callback', [
                'code' => 'sample-auth-code-xyz',
                'state' => 'valid-state-abc',
                'location' => 'US',
            ]));

        $response->assertRedirect(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']))
            ->assertSessionHas('success');

        // Verify state is cleared after successful authorization
        $this->assertNull(session('zoho_oauth_state'));

        $connection = ZohoBooksConnection::query()->first();
        $this->assertNotNull($connection);
        $this->assertEquals('connected', $connection->status);
        $this->assertEquals('987654321', $connection->organization_id);
        $this->assertEquals('Green Leaf Fresh Ltd', $connection->organization_name);
        $this->assertEquals('https://www.zohoapis.com', $connection->api_domain);

        // Verify model access yields decrypted tokens
        $this->assertEquals('1000.sample-access-token-raw-secret', $connection->access_token);
        $this->assertEquals('1000.sample-refresh-token-raw-secret', $connection->refresh_token);

        // Security check: Verify raw database record stores encrypted values, not plain text
        $rawRecord = DB::table('zoho_books_connections')->where('id', $connection->id)->first();
        $this->assertNotEquals('1000.sample-access-token-raw-secret', $rawRecord->access_token);
        $this->assertNotEquals('1000.sample-refresh-token-raw-secret', $rawRecord->refresh_token);
        $this->assertStringNotContainsString('sample-access-token-raw-secret', $rawRecord->access_token);
        $this->assertStringNotContainsString('sample-refresh-token-raw-secret', $rawRecord->refresh_token);
    }

    public function test_duplicate_replayed_callback_is_rejected(): void
    {
        Http::fake([
            'https://accounts.zoho.com/oauth/v2/token' => Http::response([
                'access_token' => '1000.sample-access-token-raw-secret',
                'refresh_token' => '1000.sample-refresh-token-raw-secret',
                'api_domain' => 'https://www.zohoapis.com',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://www.zohoapis.com/books/v3/organizations' => Http::response([
                'code' => 0,
                'message' => 'success',
                'organizations' => [
                    ['organization_id' => '987654321', 'name' => 'Green Leaf Fresh Ltd'],
                ],
            ], 200),
        ]);

        session(['zoho_oauth_state' => 'single-use-state-999']);

        // First attempt succeeds
        $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.callback', [
                'code' => 'sample-auth-code-1',
                'state' => 'single-use-state-999',
            ]))
            ->assertRedirect(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']))
            ->assertSessionHas('success');

        // Replay attempt fails because state was consumed
        $this->actingAs($this->admin)
            ->get(route('admin.integrations.zoho-books.callback', [
                'code' => 'sample-auth-code-1',
                'state' => 'single-use-state-999',
            ]))
            ->assertRedirect(route('admin.integrations.zoho-books.index'))
            ->assertSessionHas('error', 'Invalid OAuth state token. Please restart the Zoho Books connection flow.');
    }

    public function test_token_refresh_refreshes_expired_access_token(): void
    {
        $connection = ZohoBooksConnection::query()->create([
            'organization_id' => '12345',
            'organization_name' => 'Green Leaf Fresh Ltd',
            'accounts_domain' => 'https://accounts.zoho.com',
            'api_domain' => 'https://www.zohoapis.com',
            'data_center' => 'US',
            'access_token' => 'old-expired-token',
            'refresh_token' => 'valid-refresh-token',
            'access_token_expires_at' => Carbon::now()->subMinutes(10),
            'status' => 'connected',
        ]);

        Http::fake([
            'https://accounts.zoho.com/oauth/v2/token' => Http::response([
                'access_token' => 'new-refreshed-access-token-999',
                'expires_in' => 3600,
                'api_domain' => 'https://www.zohoapis.com',
            ], 200),
            'https://www.zohoapis.com/books/v3/organizations' => Http::response([
                'code' => 0,
                'message' => 'success',
                'organizations' => [
                    [
                        'organization_id' => '12345',
                        'name' => 'Green Leaf Fresh Ltd',
                    ],
                ],
            ], 200),
        ]);

        $client = app(ZohoBooksClient::class);
        $response = $client->get($connection, 'organizations');

        $this->assertTrue($response->successful());

        $connection->refresh();
        $this->assertEquals('new-refreshed-access-token-999', $connection->access_token);
        $this->assertTrue($connection->access_token_expires_at->isFuture());
    }

    public function test_test_connection_verifies_active_zoho_books_link(): void
    {
        $connection = ZohoBooksConnection::query()->create([
            'organization_id' => '12345',
            'organization_name' => 'Green Leaf Fresh Ltd',
            'accounts_domain' => 'https://accounts.zoho.com',
            'api_domain' => 'https://www.zohoapis.com',
            'data_center' => 'US',
            'access_token' => 'valid-token',
            'refresh_token' => 'valid-refresh-token',
            'access_token_expires_at' => Carbon::now()->addHour(),
            'status' => 'connected',
        ]);

        Http::fake([
            'https://www.zohoapis.com/books/v3/organizations' => Http::response([
                'code' => 0,
                'message' => 'success',
                'organizations' => [
                    [
                        'organization_id' => '12345',
                        'name' => 'Green Leaf Fresh Ltd',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.integrations.zoho-books.test'));

        $response->assertRedirect(route('admin.integrations.zoho-books.index'))
            ->assertSessionHas('success');
    }

    public function test_admin_can_select_organization_from_multiple_choices(): void
    {
        $connection = ZohoBooksConnection::query()->create([
            'organization_id' => '111',
            'organization_name' => 'Org One',
            'accounts_domain' => 'https://accounts.zoho.com',
            'api_domain' => 'https://www.zohoapis.com',
            'data_center' => 'US',
            'access_token' => 'valid-token',
            'refresh_token' => 'valid-refresh-token',
            'access_token_expires_at' => Carbon::now()->addHour(),
            'status' => 'connected',
        ]);

        Http::fake([
            'https://www.zohoapis.com/books/v3/organizations' => Http::response([
                'code' => 0,
                'message' => 'success',
                'organizations' => [
                    ['organization_id' => '111', 'name' => 'Org One'],
                    ['organization_id' => '222', 'name' => 'Org Two'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.integrations.zoho-books.select-organization'), [
                'organization_id' => '222',
            ]);

        $response->assertRedirect(route('admin.integrations.zoho-books.index', ['tab' => 'mapping']))
            ->assertSessionHas('success');

        $connection->refresh();
        $this->assertEquals('222', $connection->organization_id);
        $this->assertEquals('Org Two', $connection->organization_name);
    }

    public function test_disconnect_clears_tokens_and_marks_disconnected(): void
    {
        $connection = ZohoBooksConnection::query()->create([
            'organization_id' => '12345',
            'organization_name' => 'Green Leaf Fresh Ltd',
            'accounts_domain' => 'https://accounts.zoho.com',
            'api_domain' => 'https://www.zohoapis.com',
            'data_center' => 'US',
            'access_token' => 'valid-token',
            'refresh_token' => 'valid-refresh-token',
            'access_token_expires_at' => Carbon::now()->addHour(),
            'status' => 'connected',
        ]);

        Http::fake([
            'https://accounts.zoho.com/oauth/v2/token/revoke' => Http::response(['status' => 'success'], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.integrations.zoho-books.disconnect'));

        $response->assertRedirect(route('admin.integrations.zoho-books.index'))
            ->assertSessionHas('success');

        $connection->refresh();
        $this->assertEquals('disconnected', $connection->status);
        $this->assertNull($connection->access_token);
        $this->assertNull($connection->refresh_token);
        $this->assertNull($connection->access_token_expires_at);
    }
}
