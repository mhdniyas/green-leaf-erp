<?php

declare(strict_types=1);

namespace App\Services\Integrations\ZohoBooks;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\User;
use App\Models\ZohoBooksAccountMapping;
use App\Models\ZohoBooksConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ZohoAccountMappingService
{
    /**
     * Map an ERP LedgerEntryType to a Zoho Books account.
     *
     * @param  array<string, mixed>  $zohoAccountData
     */
    public function mapAccount(
        ZohoBooksConnection $connection,
        int $ledgerEntryTypeId,
        string $zohoAccountId,
        array $zohoAccountData,
        User $user
    ): ZohoBooksAccountMapping {
        $entryType = LedgerEntryType::query()->findOrFail($ledgerEntryTypeId);

        $mapping = ZohoBooksAccountMapping::query()->firstOrNew([
            'zoho_books_connection_id' => $connection->id,
            'ledger_entry_type_id' => $entryType->id,
        ]);

        $oldAccountId = $mapping->zoho_account_id;
        $oldAccountName = $mapping->zoho_account_name;
        $action = $mapping->exists ? 'updated' : 'mapped';

        $mapping->fill([
            'zoho_account_id' => $zohoAccountId,
            'zoho_account_name' => (string) ($zohoAccountData['account_name'] ?? 'Unknown Account'),
            'zoho_account_code' => isset($zohoAccountData['account_code']) ? (string) $zohoAccountData['account_code'] : null,
            'zoho_account_type' => (string) ($zohoAccountData['account_type'] ?? 'other'),
            'mapped_by' => $user->id,
            'mapped_at' => Carbon::now(),
        ]);

        $mapping->save();

        if (function_exists('activity')) {
            activity('zoho_account_mapping')
                ->causedBy($user)
                ->performedOn($mapping)
                ->withProperties([
                    'ledger_entry_type_id' => $entryType->id,
                    'ledger_entry_type_code' => $entryType->code,
                    'ledger_entry_type_name' => $entryType->name,
                    'old_zoho_account_id' => $oldAccountId,
                    'old_zoho_account_name' => $oldAccountName,
                    'new_zoho_account_id' => $zohoAccountId,
                    'new_zoho_account_name' => $mapping->zoho_account_name,
                    'action' => $action,
                ])
                ->log("Zoho account mapping {$action} for ERP category {$entryType->name}");
        }

        Log::info("Zoho account mapping {$action}", [
            'ledger_entry_type_id' => $entryType->id,
            'zoho_account_id' => $zohoAccountId,
            'user_id' => $user->id,
        ]);

        return $mapping;
    }

    /**
     * Remove a mapping for an ERP category.
     */
    public function removeMapping(
        ZohoBooksConnection $connection,
        int $ledgerEntryTypeId,
        User $user
    ): bool {
        $mapping = ZohoBooksAccountMapping::query()
            ->where('zoho_books_connection_id', $connection->id)
            ->where('ledger_entry_type_id', $ledgerEntryTypeId)
            ->first();

        if (! $mapping) {
            return false;
        }

        $entryType = $mapping->entryType;

        if (function_exists('activity')) {
            activity('zoho_account_mapping')
                ->causedBy($user)
                ->performedOn($mapping)
                ->withProperties([
                    'ledger_entry_type_id' => $mapping->ledger_entry_type_id,
                    'removed_zoho_account_id' => $mapping->zoho_account_id,
                    'removed_zoho_account_name' => $mapping->zoho_account_name,
                    'action' => 'removed',
                ])
                ->log('Zoho account mapping removed for ERP category '.($entryType?->name ?? 'Category #'.$ledgerEntryTypeId));
        }

        Log::info('Zoho account mapping removed', [
            'ledger_entry_type_id' => $ledgerEntryTypeId,
            'user_id' => $user->id,
        ]);

        return (bool) $mapping->delete();
    }

    /**
     * Get all active mappings for a connection keyed by ledger_entry_type_id.
     *
     * @return Collection<int, ZohoBooksAccountMapping>
     */
    public function getMappingsKeyedByEntryType(ZohoBooksConnection $connection): Collection
    {
        return ZohoBooksAccountMapping::query()
            ->where('zoho_books_connection_id', $connection->id)
            ->with(['entryType', 'mappedByUser'])
            ->get()
            ->keyBy('ledger_entry_type_id');
    }
}
