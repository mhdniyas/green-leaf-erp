<?php

declare(strict_types=1);

namespace App\Services\Cashbook\CashFlow\Sources;

use App\DTOs\Cashbook\MoneyMovement;
use App\Models\PurchaserCredit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PurchaserCashFlowSource implements CashFlowSourceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MoneyMovement>
     */
    public function forMonth(string $month, array $filters = []): Collection
    {
        $start = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        $query = PurchaserCredit::query()
            ->with(['purchaser', 'companyAccount', 'purchaseInvoice.supplier'])
            ->whereBetween('business_date', [$start, $end]);

        if (! empty($filters['purchaser_id'])) {
            $query->where('purchaser_id', (int) $filters['purchaser_id']);
        }

        $records = $query->orderBy('business_date')->orderBy('id')->get();
        $movements = collect();

        foreach ($records as $credit) {
            $date = $credit->business_date ? $credit->business_date->toDateString() : Carbon::parse($credit->created_at)->toDateString();
            $purchaserName = $credit->purchaser?->name ?? 'Purchaser #'.$credit->purchaser_id;
            $purchaserId = (int) $credit->purchaser_id;
            $amount = (float) $credit->amount;
            if ($amount <= 0) {
                continue;
            }

            if ($credit->type === 'in') {
                // Scenario A: Company funds the purchaser
                $companyAccount = $credit->companyAccount;
                $fromEntityName = $companyAccount
                    ? ($companyAccount->name ?: ($companyAccount->bank_name ?: 'Company Account'))
                    : ($credit->payment_source ?: 'Company Cash Vault');
                $fromEntityId = $companyAccount?->id;
                $fromEntityType = $companyAccount ? 'company_bank' : 'company_vault';

                $movements->push(new MoneyMovement(
                    date: $date,
                    sourceType: 'purchaser',
                    sourceId: $credit->id,
                    fromEntityType: $fromEntityType,
                    fromEntityId: $fromEntityId,
                    fromEntityName: $fromEntityName,
                    toEntityType: 'purchaser',
                    toEntityId: $purchaserId,
                    toEntityName: $purchaserName,
                    amount: $amount,
                    movementType: 'purchaser_funding',
                    category: 'funding',
                    referenceType: 'purchaser_credit',
                    referenceId: $credit->id,
                    referenceNumber: $credit->reference,
                    notes: $credit->description,
                    metadata: [
                        'purchaser_id' => $purchaserId,
                        'company_account_id' => $fromEntityId,
                    ]
                ));
            } else {
                // Scenario B: Outgoing money from purchaser
                if ($credit->purchase_invoice_id) {
                    // Cash purchase paid to a vendor/supplier
                    $supplier = $credit->purchaseInvoice?->supplier;
                    $supplierName = $supplier?->name ?? 'Unlinked Vendor';
                    $supplierId = $supplier?->id;

                    $movements->push(new MoneyMovement(
                        date: $date,
                        sourceType: 'purchaser',
                        sourceId: $credit->id,
                        fromEntityType: 'purchaser',
                        fromEntityId: $purchaserId,
                        fromEntityName: $purchaserName,
                        toEntityType: 'vendor',
                        toEntityId: $supplierId,
                        toEntityName: $supplierName,
                        amount: $amount,
                        movementType: 'cash_purchase',
                        category: 'purchase',
                        referenceType: 'purchase_invoice',
                        referenceId: $credit->purchase_invoice_id,
                        referenceNumber: $credit->purchaseInvoice?->invoice_number ?? $credit->reference,
                        notes: $credit->description,
                        metadata: [
                            'purchaser_id' => $purchaserId,
                            'supplier_id' => $supplierId,
                            'purchase_invoice_id' => $credit->purchase_invoice_id,
                        ]
                    ));
                } else {
                    // Check if it's a return to company or an expense
                    $desc = strtolower((string) $credit->description);
                    $isReturn = str_contains($desc, 'return') || str_contains($desc, 'refund') || str_contains($desc, 'deposit back');

                    if ($isReturn) {
                        $companyAccount = $credit->companyAccount;
                        $toEntityName = $companyAccount
                            ? ($companyAccount->name ?: ($companyAccount->bank_name ?: 'Company Account'))
                            : 'Company Cash Vault';
                        $toEntityId = $companyAccount?->id;

                        $movements->push(new MoneyMovement(
                            date: $date,
                            sourceType: 'purchaser',
                            sourceId: $credit->id,
                            fromEntityType: 'purchaser',
                            fromEntityId: $purchaserId,
                            fromEntityName: $purchaserName,
                            toEntityType: $companyAccount ? 'company_bank' : 'company_vault',
                            toEntityId: $toEntityId,
                            toEntityName: $toEntityName,
                            amount: $amount,
                            movementType: 'purchaser_return',
                            category: 'return',
                            referenceType: 'purchaser_credit',
                            referenceId: $credit->id,
                            referenceNumber: $credit->reference,
                            notes: $credit->description,
                            metadata: [
                                'purchaser_id' => $purchaserId,
                                'company_account_id' => $toEntityId,
                            ]
                        ));
                    } else {
                        $movements->push(new MoneyMovement(
                            date: $date,
                            sourceType: 'purchaser',
                            sourceId: $credit->id,
                            fromEntityType: 'purchaser',
                            fromEntityId: $purchaserId,
                            fromEntityName: $purchaserName,
                            toEntityType: 'operating_expense',
                            toEntityId: null,
                            toEntityName: $credit->description ?: 'Purchaser Expense',
                            amount: $amount,
                            movementType: 'purchaser_expense',
                            category: 'expense',
                            referenceType: 'purchaser_credit',
                            referenceId: $credit->id,
                            referenceNumber: $credit->reference,
                            notes: $credit->description,
                            metadata: [
                                'purchaser_id' => $purchaserId,
                            ]
                        ));
                    }
                }
            }
        }

        return $movements;
    }

    /**
     * Opening balance with each purchaser prior to startDate.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function openingBalances(string $startDate, array $filters = []): array
    {
        $query = DB::table('purchaser_credits')
            ->join('users', 'users.id', '=', 'purchaser_credits.purchaser_id')
            ->whereDate('purchaser_credits.business_date', '<', $startDate);

        if (! empty($filters['purchaser_id'])) {
            $query->where('purchaser_credits.purchaser_id', (int) $filters['purchaser_id']);
        }

        $rows = $query->selectRaw("
            purchaser_credits.purchaser_id,
            users.name as purchaser_name,
            SUM(CASE WHEN purchaser_credits.type = 'in' THEN purchaser_credits.amount ELSE 0 END) as total_in,
            SUM(CASE WHEN purchaser_credits.type = 'out' THEN purchaser_credits.amount ELSE 0 END) as total_out,
            SUM(CASE WHEN purchaser_credits.type = 'in' THEN purchaser_credits.amount ELSE -purchaser_credits.amount END) as opening_advance
        ")
            ->groupBy('purchaser_credits.purchaser_id', 'users.name')
            ->get();

        $balances = [];
        foreach ($rows as $row) {
            $balances[$row->purchaser_id] = [
                'purchaser_id' => (int) $row->purchaser_id,
                'purchaser_name' => $row->purchaser_name,
                'opening_advance' => round((float) $row->opening_advance, 2),
                'total_funded_prior' => round((float) $row->total_in, 2),
                'total_used_prior' => round((float) $row->total_out, 2),
            ];
        }

        return $balances;
    }
}
