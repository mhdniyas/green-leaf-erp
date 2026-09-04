<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;

final class CashFlowResolutionService
{
    /**
     * Resolve effective funding source for an entry setting.
     */
    public function resolveFundingSource(ShopLedgerEntrySetting $setting): string
    {
        $header = $setting->headerGroup;

        if ($header && ! empty($header->cash_flow_mode) && $header->cash_flow_mode !== 'entry_decides') {
            return match ($header->cash_flow_mode) {
                'shop_cash' => 'sales',
                'petty' => 'petty',
                'company' => 'company',
                'company_account' => 'company',
                'none' => 'none',
                default => $setting->default_funding_source ?: 'sales',
            };
        }

        return $setting->default_funding_source ?: 'sales';
    }

    /**
     * Resolve effective company account ID for an entry setting.
     */
    public function resolveCompanyAccountId(ShopLedgerEntrySetting $setting): ?int
    {
        $header = $setting->headerGroup;

        if ($header && $header->cash_flow_mode === 'company_account' && $header->company_account_id) {
            return (int) $header->company_account_id;
        }

        return $setting->company_account_id ? (int) $setting->company_account_id : null;
    }

    /**
     * Resolve whether Note field is enabled for an entry setting.
     */
    public function resolveNoteEnabled(ShopLedgerEntrySetting $setting): bool
    {
        if ($setting->requiresNote()) {
            return true;
        }

        if ($setting->note_enabled) {
            return true;
        }

        return (bool) ($setting->headerGroup?->note_enabled ?? false);
    }

    /**
     * Format a human-readable summary label for a Header Group based on section type and cash flow settings.
     */
    public function resolveHeaderSummaryLabel(ShopLedgerHeaderGroup $header): string
    {
        $type = strtolower((string) $header->type);
        $mode = $header->cash_flow_mode;
        $accName = $header->companyAccount?->name ?? $header->companyAccount?->bank_name;

        if ($type === 'income') {
            return match ($mode) {
                'shop_cash' => 'Money goes to: Shop Cash',
                'company_account' => 'Money goes to: '.($accName ?: 'Company Account'),
                'entry_decides' => 'Cash Flow: Mixed destinations',
                'none' => 'No Cash Movement',
                default => 'Money goes to: Shop Cash',
            };
        }

        if ($type === 'expense') {
            return match ($mode) {
                'shop_cash' => 'Paid from: Shop Cash',
                'petty' => 'Paid from: Petty',
                'company' => 'Paid by: Company',
                'company_account' => 'Paid from: '.($accName ?: 'Company Account'),
                'entry_decides' => 'Cash Flow: Mixed sources',
                'none' => 'No Cash Movement',
                default => 'Paid from: Shop Cash',
            };
        }

        // Others / Transfers
        $fromLabel = $this->formatBalanceLabel($header->from_balance ?: 'shop_cash');
        $toLabel = $this->formatBalanceLabel($header->to_balance ?: 'petty');

        return "Movement: {$fromLabel} → {$toLabel}";
    }

    /**
     * Resolve effective destination or source label for an individual entry setting.
     */
    public function resolveDestinationLabel(ShopLedgerEntrySetting $setting): string
    {
        $funding = $this->resolveFundingSource($setting);
        $accountId = $this->resolveCompanyAccountId($setting);

        if ($accountId) {
            $acc = $setting->companyAccount ?? $setting->headerGroup?->companyAccount;
            if (! $acc && $setting->company_account_id) {
                $acc = CompanyAccount::find($setting->company_account_id);
            }
            if (! $acc && $setting->headerGroup?->company_account_id) {
                $acc = CompanyAccount::find($setting->headerGroup->company_account_id);
            }
            if ($acc) {
                return $acc->name ?: $acc->bank_name;
            }

            return 'Company Account';
        }

        return match ($funding) {
            'sales', 'shop_balance' => 'Shop Cash',
            'petty' => 'Petty',
            'company' => 'Company',
            'none' => 'No Cash Movement',
            default => 'Shop Cash',
        };
    }

    /**
     * Get an array of child entry destination mappings for headers with entry_decides.
     * Returns array of strings like ["Paytm → Shaanu Account", "Card → IDFC Bank", "Cash → Shop Cash"].
     *
     * @return array<int, string>
     */
    public function resolveHeaderChildDestinations(ShopLedgerHeaderGroup $header): array
    {
        $result = [];
        $settings = $header->relationLoaded('entrySettings')
            ? $header->entrySettings
            : $header->entrySettings()->with(['entryType', 'companyAccount'])->get();

        foreach ($settings->where('enabled', true) as $setting) {
            $entryName = $setting->entryType?->name ?? 'Entry';
            $destLabel = $this->resolveDestinationLabel($setting);
            $result[] = "{$entryName} → {$destLabel}";
        }

        return $result;
    }

    private function formatBalanceLabel(string $balanceKey): string
    {
        return match ($balanceKey) {
            'shop_cash' => 'Shop Cash',
            'petty' => 'Petty',
            'company' => 'Company',
            'company_account' => 'Company Account',
            'vendor' => 'Vendor',
            'none' => 'No Balance',
            default => ucfirst(str_replace('_', ' ', $balanceKey)),
        };
    }
}
