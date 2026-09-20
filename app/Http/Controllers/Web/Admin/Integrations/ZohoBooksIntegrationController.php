<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\Integrations;

use App\Http\Controllers\Controller;
use App\Models\ZohoBooksConnection;
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
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

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

        return view('admin.integrations.zoho-books.index', [
            'connection' => $connection,
            'isConfigured' => $isConfigured,
            'availableOrganizations' => $availableOrganizations,
            'clientId' => config('services.zoho.client_id'),
            'redirectUri' => config('services.zoho.redirect_uri'),
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
                    ->route('admin.integrations.zoho-books.index')
                    ->with('success', "Zoho Books connected successfully to organization: {$connection->organization_name}.");
            }

            return redirect()
                ->route('admin.integrations.zoho-books.index')
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
                ->route('admin.integrations.zoho-books.index')
                ->with('success', "Linked Zoho Books organization: {$connection->organization_name}.");
        } catch (Exception $e) {
            return redirect()
                ->route('admin.integrations.zoho-books.index')
                ->with('error', 'Failed to select organization: '.$e->getMessage());
        }
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
