<?php

declare(strict_types=1);

namespace App\Services\Integrations\ZohoBooks;

use App\Models\ZohoBooksConnection;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoOrganizationService
{
    public function __construct(
        private readonly ZohoBooksClient $client,
    ) {}

    /**
     * Fetch all Zoho Books organizations accessible by the connected account.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchOrganizations(ZohoBooksConnection $connection): array
    {
        $response = $this->client->get($connection, 'organizations');

        if (! $response->successful()) {
            $json = $response->json();
            $errorMsg = is_array($json) ? ($json['message'] ?? $json['error'] ?? 'Unknown error') : 'HTTP '.$response->status();
            throw new RuntimeException('Failed to fetch Zoho Books organizations: '.$errorMsg);
        }

        $data = $response->json();

        return (array) ($data['organizations'] ?? []);
    }

    /**
     * Auto-select organization if only one exists, or return available organizations.
     *
     * @return array{connection: ZohoBooksConnection, organizations: array<int, array<string, mixed>>, auto_selected: bool}
     */
    public function discoverAndAssignOrganization(ZohoBooksConnection $connection): array
    {
        $organizations = $this->fetchOrganizations($connection);

        if (count($organizations) === 1) {
            $org = $organizations[0];
            $connection->organization_id = (string) ($org['organization_id'] ?? '');
            $connection->organization_name = (string) ($org['name'] ?? '');
            $connection->save();

            Log::info('Zoho organization automatically selected', [
                'organization_id' => $connection->organization_id,
                'organization_name' => $connection->organization_name,
            ]);

            return [
                'connection' => $connection,
                'organizations' => $organizations,
                'auto_selected' => true,
            ];
        }

        return [
            'connection' => $connection,
            'organizations' => $organizations,
            'auto_selected' => false,
        ];
    }

    /**
     * Assign a specific organization to the connection.
     */
    public function selectOrganization(ZohoBooksConnection $connection, string $organizationId): ZohoBooksConnection
    {
        $organizations = $this->fetchOrganizations($connection);

        $selected = collect($organizations)->firstWhere('organization_id', $organizationId);

        if (! $selected) {
            throw new RuntimeException('Selected organization ID not found in authorized Zoho account.');
        }

        $connection->organization_id = (string) $selected['organization_id'];
        $connection->organization_name = (string) ($selected['name'] ?? '');
        $connection->save();

        Log::info('Zoho organization selected manually', [
            'organization_id' => $connection->organization_id,
            'organization_name' => $connection->organization_name,
        ]);

        return $connection;
    }

    /**
     * Perform a test connection check by fetching organization details.
     *
     * @return array{success: bool, message: string, organization_name?: string, organization_id?: string}
     */
    public function testConnection(ZohoBooksConnection $connection): array
    {
        try {
            $organizations = $this->fetchOrganizations($connection);

            if (empty($organizations)) {
                return [
                    'success' => false,
                    'message' => 'Connected to Zoho successfully, but no Zoho Books organizations were found for this account.',
                ];
            }

            $currentOrg = null;
            if ($connection->organization_id) {
                $currentOrg = collect($organizations)->firstWhere('organization_id', $connection->organization_id);
            }

            if (! $currentOrg) {
                $currentOrg = $organizations[0];
            }

            return [
                'success' => true,
                'message' => 'Connection to Zoho Books is healthy.',
                'organization_name' => (string) ($currentOrg['name'] ?? 'Unknown'),
                'organization_id' => (string) ($currentOrg['organization_id'] ?? ''),
            ];
        } catch (Exception $e) {
            Log::error('Zoho test connection failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ];
        }
    }
}
