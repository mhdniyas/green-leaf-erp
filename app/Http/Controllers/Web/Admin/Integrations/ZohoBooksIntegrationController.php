<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\ZohoBooksConnection;
use App\Services\Integrations\ZohoBooks\ZohoAccountMappingService;
use App\Services\Integrations\ZohoBooks\ZohoChartOfAccountsService;
use App\Services\Integrations\ZohoBooks\ZohoOAuthService;
use App\Services\Integrations\ZohoBooks\ZohoOrganizationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ZohoBooksIntegrationController extends Controller
{
    public function __construct(
        private readonly ZohoOAuthService $oauthService,
        private readonly ZohoOrganizationService $organizationService,
        private readonly ZohoChartOfAccountsService $chartOfAccountsService,
        private readonly ZohoAccountMappingService $mappingService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $activeTab = $request->query('tab', 'connection');
        $connection = ZohoBooksConnection::query()->with('connectedByUser')->first();
        $availableOrganizations = [];
        $isConfigured = ! empty(config('services.zoho.client_id')) && ! empty(config('services.zoho.client_secret'));

        if ($connection?->isConnected()) {
            try {
                $availableOrganizations = $this->organizationService->fetchOrganizations($connection);
            } catch (Exception) {
                // Keep page viewable even if remote API has a transient error
            }
        }

        // Data for Account Mapping tab
        $entryTypes = collect();
        $zohoAccounts = [];
        $groupedZohoAccounts = [];
        $mappings = collect();
        $missingAccountantsScope = false;
        $coaError = null;

        if ($connection?->isConnected()) {
            $missingAccountantsScope = ! $connection->hasAccountantsReadScope();

            if (! $missingAccountantsScope) {
                $entryTypes = LedgerEntryType::query()
                    ->where('active', true)
                    ->orderBy('display_order')
                    ->orderBy('name')
                    ->get();

                try {
                    $zohoAccounts = $this->chartOfAccountsService->getAccounts($connection);
                    $groupedZohoAccounts = $this->chartOfAccountsService->groupAccountsByType($zohoAccounts);
                    $mappings = $this->mappingService->getMappingsKeyedByEntryType($connection);
                } catch (Exception $e) {
                    $coaError = $e->getMessage();
                }
            }
        }

        $totalCategories = $entryTypes->count();
        $mappedCount = $mappings->count();
        $unmappedCount = max(0, $totalCategories - $mappedCount);

        return view('admin.integrations.zoho-books.index', [
            'activeTab' => $activeTab,
            'connection' => $connection,
            'isConfigured' => $isConfigured,
            'availableOrganizations' => $availableOrganizations,
            'clientId' => config('services.zoho.client_id'),
            'redirectUri' => config('services.zoho.redirect_uri'),
            // Mapping tab props
            'entryTypes' => $entryTypes,
            'zohoAccounts' => $zohoAccounts,
            'groupedZohoAccounts' => $groupedZohoAccounts,
            'mappings' => $mappings,
            'missingAccountantsScope' => $missingAccountantsScope,
            'coaError' => $coaError,
            'totalCategories' => $totalCategories,
            'mappedCount' => $mappedCount,
            'unmappedCount' => $unmappedCount,
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if (empty(config('services.zoho.client_id')) || empty(config('services.zoho.client_secret'))) {
            return redirect()
                ->route('admin.integrations.zoho-books.index')
                ->with('error', 'Zoho API credentials are not configured. Please set ZOHO_CLIENT_ID and ZOHO_CLIENT_SECRET in your environment.');
        }

        $state = Str::random(40);
        $request->session()->put('zoho_oauth_state', $state);
        $request->session()->save();

        $authParams = $this->oauthService->getAuthorizationParams($state);
        $authUrl = $this->oauthService->getAuthorizationUrl($state);

        Log::info('Initiated Zoho OAuth connection flow', [
            'state_parameter_present' => ! empty($authParams['state']),
            'redirect_uri' => $this->oauthService->getRedirectUri(),
            'accounts_domain' => $this->oauthService->getAccountsDomain(),
            'authorization_query_keys' => array_keys($authParams),
            'session_id' => $request->session()->getId(),
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
        ]);

        return redirect()->away($authUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($request->has('error')) {
            $errorMsg = (string) ($request->input('error_description') ?: $request->input('error'));

            return redirect()
                ->route('admin.integrations.zoho-books.index')
                ->with('error', 'Zoho authorization failed: '.$errorMsg);
        }

        $storedState = $request->session()->get('zoho_oauth_state');
        $receivedState = $request->input('state');

        $hasStoredState = is_string($storedState) && ! empty($storedState);
        $hasReceivedState = is_string($receivedState) && ! empty($receivedState);
        $isValidState = $hasStoredState && $hasReceivedState && hash_equals($storedState, $receivedState);

        if (! $isValidState) {
            Log::warning('Zoho OAuth state validation failed', [
                'session_state_present' => $hasStoredState,
                'returned_state_present' => $hasReceivedState,
                'session_id' => $request->session()->getId(),
                'host' => $request->getHost(),
                'scheme' => $request->getScheme(),
            ]);

            return redirect()
                ->route('admin.integrations.zoho-books.index')
                ->with('error', 'Invalid OAuth state token. Please restart the Zoho Books connection flow.');
        }

        // Forget stored state only AFTER successful validation to prevent replay attacks
        $request->session()->forget('zoho_oauth_state');
        $request->session()->save();

        $code = (string) $request->input('code');

        if (empty($code)) {
            return redirect()
                ->route('admin.integrations.zoho-books.index')
                ->with('error', 'Missing authorization code from Zoho response.');
        }

        try {
            $accountsServer = $request->input('accounts-server');
            $location = $request->input('location');

            $connection = $this->oauthService->exchangeAuthorizationCode(
                code: $code,
                accountsServer: $accountsServer ? (string) $accountsServer : null,
                location: $location ? (string) $location : null,
                userId: $request->user()?->id,
            );

            $discovery = $this->organizationService->discoverAndAssignOrganization($connection);

            if ($discovery['auto_selected']) {
                return redirect()
                    ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
                    ->with('success', "Zoho Books connected successfully to organization: {$connection->organization_name}.");
            }

            return redirect()
                ->route('admin.integrations.zoho-books.index', ['tab' => 'connection'])
                ->with('info', 'Zoho Books authorized. Please select which organization to link with Green Leaf ERP.');
        } catch (Exception $e) {
            return redirect()
                ->route('admin.integrations.zoho-books.index')
                ->with('error', 'Connection failed: '.$e->getMessage());
        }
    }

    public function selectOrganization(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'organization_id' => 'required|string',
        ]);

        $connection = ZohoBooksConnection::query()->firstOrFail();

        try {
            $this->organizationService->selectOrganization($connection, (string) $request->input('organization_id'));

            return redirect()
                ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
                ->with('success', "Linked Zoho Books organization: {$connection->organization_name}.");
        } catch (Exception $e) {
            return redirect()
                ->route('admin.integrations.zoho-books.index')
                ->with('error', 'Failed to select organization: '.$e->getMessage());
        }
    }

    public function refreshAccounts(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $connection = ZohoBooksConnection::query()->first();

        if (! $connection || ! $connection->isConnected()) {
            return redirect()
                ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
                ->with('error', 'Zoho Books is not currently connected.');
        }

        try {
            $accounts = $this->chartOfAccountsService->getAccounts($connection, forceRefresh: true);
            $count = count($accounts);

            return redirect()
                ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
                ->with('success', "Chart of Accounts refreshed successfully from Zoho Books ({$count} accounts loaded).");
        } catch (Exception $e) {
            return redirect()
                ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
                ->with('error', 'Failed to refresh Chart of Accounts: '.$e->getMessage());
        }
    }

    public function saveMapping(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'ledger_entry_type_id' => 'required|integer|exists:ledger_entry_types,id',
            'zoho_account_id' => 'required|string',
        ]);

        $connection = ZohoBooksConnection::query()->first();

        if (! $connection || ! $connection->isConnected()) {
            return redirect()
                ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
                ->with('error', 'Zoho Books is not currently connected.');
        }

        try {
            $allAccounts = $this->chartOfAccountsService->getAccounts($connection);
            $zohoAccountId = (string) $request->input('zoho_account_id');
            $accountData = $this->chartOfAccountsService->findAccount($allAccounts, $zohoAccountId);

            if (! $accountData) {
                return redirect()
                    ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
                    ->with('error', 'The selected Zoho account was not found in your Chart of Accounts.');
            }

            $mapping = $this->mappingService->mapAccount(
                connection: $connection,
                ledgerEntryTypeId: (int) $request->input('ledger_entry_type_id'),
                zohoAccountId: $zohoAccountId,
                zohoAccountData: $accountData,
                user: $request->user(),
            );

            return redirect()
                ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
                ->with('success', "Mapped category '{$mapping->entryType?->name}' to Zoho account '{$mapping->zoho_account_name}'.");
        } catch (Exception $e) {
            return redirect()
                ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
                ->with('error', 'Failed to save mapping: '.$e->getMessage());
        }
    }

    public function removeMapping(Request $request, int $ledgerEntryTypeId): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $connection = ZohoBooksConnection::query()->first();

        if (! $connection) {
            return redirect()
                ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
                ->with('error', 'Zoho Books is not currently connected.');
        }

        $this->mappingService->removeMapping($connection, $ledgerEntryTypeId, $request->user());

        return redirect()
            ->route('admin.integrations.zoho-books.index', ['tab' => 'mapping'])
            ->with('success', 'Zoho account mapping removed successfully.');
    }

    public function test(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin($request);

        $connection = ZohoBooksConnection::query()->first();

        if (! $connection || ! $connection->isConnected()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Zoho Books is not currently connected.',
                ], 422);
            }

            return redirect()
                ->route('admin.integrations.zoho-books.index')
                ->with('error', 'Zoho Books is not currently connected.');
        }

        $result = $this->organizationService->testConnection($connection);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return redirect()
                ->route('admin.integrations.zoho-books.index')
                ->with('success', "{$result['message']} (Organization: {$result['organization_name']})");
        }

        return redirect()
            ->route('admin.integrations.zoho-books.index')
            ->with('error', $result['message']);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $connection = ZohoBooksConnection::query()->first();

        if ($connection) {
            $this->oauthService->disconnect($connection);
        }

        return redirect()
            ->route('admin.integrations.zoho-books.index')
            ->with('success', 'Zoho Books integration disconnected successfully.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403, 'Unauthorized access to Zoho Books integration.');
    }
}
