<?php

declare(strict_types=1);

namespace App\Services\Integrations\ZohoBooks;

use App\Models\ZohoBooksConnection;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoOAuthService
{
    public const string DEFAULT_ACCOUNTS_DOMAIN = 'https://accounts.zoho.com';

    public const string DEFAULT_SCOPES = 'ZohoBooks.fullaccess.READ';

    public function getClientId(): string
    {
        return (string) config('services.zoho.client_id');
    }

    public function getClientSecret(): string
    {
        return (string) config('services.zoho.client_secret');
    }

    public function getRedirectUri(): string
    {
        $configured = config('services.zoho.redirect_uri');

        if (! empty($configured)) {
            return (string) $configured;
        }

        return route('admin.integrations.zoho-books.callback');
    }

    public function getScopes(): string
    {
        return (string) (config('services.zoho.scopes') ?: self::DEFAULT_SCOPES);
    }

    /**
     * Generate the Zoho OAuth authorization URL.
     */
    public function getAuthorizationUrl(string $state, ?string $accountsDomain = null): string
    {
        $domain = rtrim($accountsDomain ?: self::DEFAULT_ACCOUNTS_DOMAIN, '/');

        $params = [
            'client_id' => $this->getClientId(),
            'response_type' => 'code',
            'redirect_uri' => $this->getRedirectUri(),
            'scope' => $this->getScopes(),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return $domain.'/oauth/v2/auth?'.http_build_query($params);
    }

    /**
     * Exchange the authorization code for access and refresh tokens.
     */
    public function exchangeAuthorizationCode(
        string $code,
        ?string $accountsServer = null,
        ?string $location = null,
        ?int $userId = null
    ): ZohoBooksConnection {
        $accountsDomain = $this->normalizeAccountsDomain($accountsServer);

        $response = Http::asForm()->post($accountsDomain.'/oauth/v2/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'redirect_uri' => $this->getRedirectUri(),
            'code' => $code,
        ]);

        if (! $response->successful()) {
            $this->logError('Zoho token exchange failed', $response);
            throw new RuntimeException('Failed to exchange Zoho authorization code: '.$this->extractErrorMessage($response));
        }

        $data = $response->json();

        if (isset($data['error'])) {
            $this->logError('Zoho token exchange returned error payload', $response);
            throw new RuntimeException('Zoho OAuth Error: '.($data['error_description'] ?? $data['error']));
        }

        $accessToken = $data['access_token'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        $apiDomain = $data['api_domain'] ?? null;

        if (! $accessToken || ! $refreshToken) {
            throw new RuntimeException('Invalid token response from Zoho: missing access or refresh token.');
        }

        $connection = ZohoBooksConnection::query()->first() ?? new ZohoBooksConnection;

        $connection->fill([
            'accounts_domain' => $accountsDomain,
            'api_domain' => $apiDomain,
            'data_center' => $location ?: $this->detectDataCenter($accountsDomain, $apiDomain),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'access_token_expires_at' => Carbon::now()->addSeconds($expiresIn),
            'scopes' => explode(',', $this->getScopes()),
            'status' => 'connected',
            'connected_by' => $userId,
            'connected_at' => Carbon::now(),
        ]);

        $connection->save();

        Log::info('Zoho connection established', [
            'data_center' => $connection->data_center,
            'connected_by' => $userId,
        ]);

        return $connection;
    }

    /**
     * Refresh the access token using the stored refresh token.
     */
    public function refreshAccessToken(ZohoBooksConnection $connection): string
    {
        if (empty($connection->refresh_token)) {
            throw new RuntimeException('No refresh token available on Zoho Books connection.');
        }

        $accountsDomain = $this->normalizeAccountsDomain($connection->accounts_domain);

        $response = Http::asForm()->post($accountsDomain.'/oauth/v2/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'refresh_token' => $connection->refresh_token,
        ]);

        if (! $response->successful()) {
            $this->logError('Zoho token refresh failed', $response);
            throw new RuntimeException('Failed to refresh Zoho access token: '.$this->extractErrorMessage($response));
        }

        $data = $response->json();

        if (isset($data['error'])) {
            $this->logError('Zoho token refresh returned error payload', $response);
            throw new RuntimeException('Zoho Token Refresh Error: '.($data['error_description'] ?? $data['error']));
        }

        $newAccessToken = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        if (! $newAccessToken) {
            throw new RuntimeException('Zoho refresh response did not include a new access token.');
        }

        $connection->access_token = $newAccessToken;
        $connection->access_token_expires_at = Carbon::now()->addSeconds($expiresIn);

        if (! empty($data['api_domain'])) {
            $connection->api_domain = $data['api_domain'];
        }

        if (! empty($data['refresh_token'])) {
            $connection->refresh_token = $data['refresh_token'];
        }

        $connection->save();

        Log::info('Zoho token refreshed', [
            'connection_id' => $connection->id,
        ]);

        return $newAccessToken;
    }

    /**
     * Disconnect the Zoho connection locally and attempt remote revocation.
     */
    public function disconnect(ZohoBooksConnection $connection): void
    {
        $refreshToken = $connection->refresh_token;
        $accountsDomain = $this->normalizeAccountsDomain($connection->accounts_domain);

        if ($refreshToken) {
            try {
                Http::asForm()->post($accountsDomain.'/oauth/v2/token/revoke', [
                    'token' => $refreshToken,
                ]);
            } catch (Exception $e) {
                Log::warning('Zoho token revocation request failed during disconnect', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $connection->fill([
            'access_token' => null,
            'refresh_token' => null,
            'access_token_expires_at' => null,
            'status' => 'disconnected',
        ]);

        $connection->save();

        Log::info('Zoho connection disconnected', [
            'connection_id' => $connection->id,
        ]);
    }

    private function normalizeAccountsDomain(?string $domain): string
    {
        if (! $domain) {
            return self::DEFAULT_ACCOUNTS_DOMAIN;
        }

        $trimmed = rtrim($domain, '/');

        if (! str_starts_with($trimmed, 'http://') && ! str_starts_with($trimmed, 'https://')) {
            $trimmed = 'https://'.$trimmed;
        }

        return $trimmed;
    }

    private function detectDataCenter(?string $accountsDomain, ?string $apiDomain): string
    {
        $domain = $accountsDomain ?: $apiDomain ?: '';

        if (str_contains($domain, '.in')) {
            return 'IN';
        }
        if (str_contains($domain, '.eu')) {
            return 'EU';
        }
        if (str_contains($domain, '.com.au')) {
            return 'AU';
        }
        if (str_contains($domain, '.ca')) {
            return 'CA';
        }
        if (str_contains($domain, '.jp')) {
            return 'JP';
        }
        if (str_contains($domain, '.sa')) {
            return 'SA';
        }

        return 'US';
    }

    private function logError(string $context, Response $response): void
    {
        Log::error($context, [
            'status' => $response->status(),
            'error_summary' => $this->extractErrorMessage($response),
        ]);
    }

    private function extractErrorMessage(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            return (string) ($json['error_description'] ?? $json['message'] ?? $json['error'] ?? 'HTTP '.$response->status());
        }

        return 'HTTP '.$response->status();
    }
}
