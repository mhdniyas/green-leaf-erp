<?php

declare(strict_types=1);

namespace App\Services\Cashbook\PaymentsSettings;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;

class PaymentsCompanyToShopService
{
    /**
     * Get view model for Company -> Shop tab.
     * Purely operational: Current Setup + Output + View Transactions (NO artificial save endpoint).
     *
     * @return array<string, mixed>
     */
    public function getViewModel(Shop $shop, string $startDate, string $endDate): array
    {
        $shopId = (int) $shop->id;

        // Supported flows in system
        $flows = [
            [
                'name' => 'Shop Reimbursement / Inflow',
                'code' => 'company_paid_shop',
                'source' => 'Company Bank / Cash Account',
                'destination' => 'Shop Balance (Increases Shop Balance)',
                'category' => 'Settlement / Transfer',
                'shop_balance_effect' => '+ Positive (Reduces Shop Debt to Company)',
                'petty_effect' => 'None',
                'settlement_effect' => 'Reduces Net Settlement Balance',
                'description' => 'Direct transfer from company bank or cash to reimburse shop expenses or add credit.',
            ],
            [
                'name' => 'Company Petty Funding',
                'code' => 'company_to_petty',
                'source' => 'Company Bank / Cash Account',
                'destination' => 'Shop Petty Cash Drawer',
                'category' => 'Transfer',
                'shop_balance_effect' => 'Neutral (Funds Petty Drawer)',
                'petty_effect' => '+ Increases Petty Drawer Balance',
                'settlement_effect' => 'Informational (Tracked via Statement Entry)',
                'description' => 'Direct bank transfer or cash handed over to shop for operational petty cash.',
            ],
            [
                'name' => 'Company-Paid Vendor Bills',
                'code' => 'company_paid_vendor',
                'source' => 'Company Bank / Accounts Payable',
                'destination' => 'Vendor / Supplier',
                'category' => 'Purchase / Vendor Settle',
                'shop_balance_effect' => 'Reduces Shop Payable for Vendor',
                'petty_effect' => 'None',
                'settlement_effect' => 'Exempt from Shop Cash drawer; Settled directly by Head Office',
                'description' => 'Company head office pays vendor invoices directly from company bank accounts.',
            ],
        ];

        // Fetch transactions for company_paid_shop and company_to_petty
        $transactions = ShopLedgerTransaction::query()
            ->with(['entryType', 'companyAccount'])
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereIn('status', ['posted', 'approved'])
            ->whereNull('voided_at')
            ->whereHas('entryType', fn ($sub) => $sub->whereIn('code', ['company_paid_shop', 'company_to_petty', 'company_paid_vendor']))
            ->get();

        $statements = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->whereIn('source_id', $transactions->pluck('id'))
            ->get()
            ->keyBy('source_id');

        $totalCompanyPaidShop = (float) $transactions->filter(fn ($tx) => $tx->entryType?->code === 'company_paid_shop')->sum('amount');
        $totalPettyFunded = (float) $transactions->filter(fn ($tx) => $tx->entryType?->code === 'company_to_petty')->sum('amount');

        $verified = 0.0;
        $reconciled = 0.0;
        $pending = 0.0;

        foreach ($transactions as $tx) {
            $stmt = $statements->get($tx->id);
            if ($stmt && $stmt->is_finalized) {
                $reconciled += (float) $tx->amount;
                $verified += (float) $tx->amount;
            } elseif ($stmt) {
                $verified += (float) $tx->amount;
            } else {
                $pending += (float) $tx->amount;
            }
        }

        return [
            'flows' => $flows,
            'recent_transactions' => $transactions->take(10),
            'output' => [
                'company_paid_shop_month' => round($totalCompanyPaidShop, 2),
                'total_company_paid_shop' => round($totalCompanyPaidShop, 2),
                'petty_funded_month' => round($totalPettyFunded, 2),
                'pending' => round($pending, 2),
                'verified' => round($verified, 2),
                'reconciled' => round($reconciled, 2),
            ],
        ];
    }
}
