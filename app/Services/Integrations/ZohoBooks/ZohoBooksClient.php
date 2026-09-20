<?php

declare(strict_types=1);

namespace App\Services\Integrations\ZohoBooks;

use App\Models\ZohoBooksConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZohoBooksClient
{
    public function __construct(
        private readonly ZohoOAuthService $oauthService,
    ) {}

    /**
     * Get a valid access token for the connection, refreshing if expired.
     */
    public function getValidAccessToken(ZohoBooksConnection $connection): string
    {
        if ($connection->isAccessTokenExpired()) {
            return $this->oauthService->refreshAccessToken($connection);
        }

        return (string) $connection->access_token;
    }

    /**
     * Perform a GET request to the Zoho Books API.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(ZohoBooksConnection $connection, string $endpoint, array $query = []): Response
    {
        $accessToken = $this->getValidAccessToken($connection);
        $baseUrl = rtrim((string) ($connection->api_domain ?: 'https://www.zohoapis.com'), '/');
        $url = $baseUrl.'/books/v3/'.ltrim($endpoint, '/');

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$accessToken,
        ])->get($url, $query);

        if (! $response->successful()) {
            $this->logError('Zoho Books API GET failed', $endpoint, $response);
        }

        return $response;
    }

    /**
     * Perform a POST request to the Zoho Books API.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $query
     */
    public function post(ZohoBooksConnection $connection, string $endpoint, array $data = [], array $query = []): Response
    {
        $accessToken = $this->getValidAccessToken($connection);
        $baseUrl = rtrim((string) ($connection->api_domain ?: 'https://www.zohoapis.com'), '/');
        $url = $baseUrl.'/books/v3/'.ltrim($endpoint, '/');

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$accessToken,
        ])->post($url.'?'.http_build_query($query), $data);

        if (! $response->successful()) {
            $this->logError('Zoho Books API POST failed', $endpoint, $response);
        }

        return $response;
    }

    private function logError(string $context, string $endpoint, Response $response): void
    {
        $json = $response->json();
        $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? 'HTTP '.$response->status()) : 'HTTP '.$response->status();

        Log::error($context, [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'message' => $message,
        ]);
    }
}
