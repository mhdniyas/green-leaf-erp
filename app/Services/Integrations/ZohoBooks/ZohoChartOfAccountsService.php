<?php

declare(strict_types=1);

namespace App\Services\Integrations\ZohoBooks;

use App\Models\ZohoBooksConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoChartOfAccountsService
{
    public function __construct(
        private readonly ZohoBooksClient $client,
    ) {}

    /**
     * Get all Chart of Accounts entries from Zoho Books (handles pagination).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAccounts(ZohoBooksConnection $connection, bool $forceRefresh = false): array
    {
        if (empty($connection->organization_id)) {
            throw new RuntimeException('Zoho Books organization is not selected.');
        }

        $cacheKey = 'zoho_coa_'.$connection->id.'_'.$connection->organization_id;

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 600, function () use ($connection): array {
            $allAccounts = [];
            $page = 1;
            $hasMorePages = true;

            while ($hasMorePages) {
                $response = $this->client->get($connection, 'chartofaccounts', [
                    'organization_id' => $connection->organization_id,
                    'page' => $page,
                    'per_page' => 200,
                ]);

                if (! $response->successful()) {
                    $json = $response->json();
                    $errorMsg = is_array($json) ? ($json['message'] ?? $json['error'] ?? 'HTTP '.$response->status()) : 'HTTP '.$response->status();
                    throw new RuntimeException('Failed to fetch Zoho Chart of Accounts: '.$errorMsg);
                }

                $data = $response->json();
                $accounts = (array) ($data['chartofaccounts'] ?? []);
                $allAccounts = array_merge($allAccounts, $accounts);

                $pageContext = (array) ($data['page_context'] ?? []);
                $hasMorePages = ! empty($pageContext['has_more_page']);
                $page++;

                // Safety break for unexpected infinite pagination loops
                if ($page > 50) {
                    break;
                }
            }

            Log::info('Fetched Zoho Chart of Accounts', [
                'connection_id' => $connection->id,
                'total_accounts' => count($allAccounts),
            ]);

            return $allAccounts;
        });
    }

    /**
     * Get active Chart of Accounts entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveAccounts(ZohoBooksConnection $connection, bool $forceRefresh = false): array
    {
        $all = $this->getAccounts($connection, $forceRefresh);

        return array_values(array_filter($all, function (array $acc): bool {
            return ! isset($acc['is_active']) || filter_var($acc['is_active'], FILTER_VALIDATE_BOOLEAN);
        }));
    }

    /**
     * Group Chart of Accounts entries by account type.
     *
     * @param  array<int, array<string, mixed>>  $accounts
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function groupAccountsByType(array $accounts): array
    {
        $grouped = [];

        foreach ($accounts as $acc) {
            $type = (string) ($acc['account_type'] ?? 'other');
            $grouped[$type][] = $acc;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Find a specific account by account ID.
     *
     * @param  array<int, array<string, mixed>>  $accounts
     * @return array<string, mixed>|null
     */
    public function findAccount(array $accounts, string $accountId): ?array
    {
        foreach ($accounts as $acc) {
            if (isset($acc['account_id']) && (string) $acc['account_id'] === $accountId) {
                return $acc;
            }
        }

        return null;
    }
}
