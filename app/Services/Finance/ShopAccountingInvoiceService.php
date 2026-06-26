<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Shop;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopAccountingInvoiceService
{
    public function __construct(
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
    ) {}

    public function generate(Shop $shop, Carbon $periodStart, Carbon $periodEnd, int $userId, ?string $notes = null): ShopAccountingInvoice
    {
        if (! $this->ownedShopAccountingService->isEligibleShop($shop)) {
            throw new RuntimeException('Accounting invoices are only available for owned or partnership shops.');
        }

        $ownershipTotal = $this->ownedShopAccountingService->ownershipPercentageTotal($shop);
        if (abs($ownershipTotal - 100.00) > 0.01) {
            throw new RuntimeException('Ownership percentages must total 100% before generating a settlement invoice.');
        }

        $existingInvoice = ShopAccountingInvoice::query()
            ->where('shop_id', $shop->id)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->first();

        if ($existingInvoice instanceof ShopAccountingInvoice) {
            throw new RuntimeException('A settlement invoice already exists for the selected shop and period.');
        }

        $entries = ShopAccountingEntry::query()
            ->with('lines')
            ->where('shop_id', $shop->id)
            ->whereIn('status', ['approved', 'finalized'])
            ->whereDate('business_date', '>=', $periodStart)
            ->whereDate('business_date', '<=', $periodEnd)
            ->get();

        if ($entries->isEmpty()) {
            throw new RuntimeException('No approved accounting entries exist for the selected period.');
        }

        $totalIncome = round((float) $entries->sum(
            fn (ShopAccountingEntry $entry): float => (float) $entry->lines->where('type', 'income')->sum('amount')
        ), 2);
        $totalExpense = round((float) $entries->sum(
            fn (ShopAccountingEntry $entry): float => (float) $entry->lines->where('type', 'expense')->sum('amount')
        ), 2);
        $netAmount = round($totalIncome - $totalExpense, 2);
        $ownerships = $shop->ownerships()->orderBy('id')->get();

        return DB::transaction(function () use ($shop, $periodStart, $periodEnd, $userId, $notes, $totalIncome, $totalExpense, $netAmount, $ownerships): ShopAccountingInvoice {
            $invoice = ShopAccountingInvoice::query()->create([
                'shop_id' => $shop->id,
                'invoice_number' => $this->nextInvoiceNumber($shop),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => 'generated',
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net_amount' => $netAmount,
                'generated_by' => $userId,
                'notes' => $notes,
            ]);

            foreach ($ownerships as $ownership) {
                $invoice->splits()->create([
                    'shop_ownership_id' => $ownership->id,
                    'owner_name_snapshot' => $ownership->owner_name,
                    'ownership_percent_snapshot' => $ownership->ownership_percent,
                    'share_amount' => round($netAmount * ((float) $ownership->ownership_percent / 100), 2),
                ]);
            }

            return $invoice->fresh(['shop', 'generatedBy', 'splits.ownership']);
        });
    }

    private function nextInvoiceNumber(Shop $shop): string
    {
        $prefix = 'SACINV-'.strtoupper($shop->code).'-'.now()->format('Ymd');
        $sequence = 1;

        do {
            $invoiceNumber = $prefix.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (ShopAccountingInvoice::query()->where('invoice_number', $invoiceNumber)->exists());

        return $invoiceNumber;
    }
}
