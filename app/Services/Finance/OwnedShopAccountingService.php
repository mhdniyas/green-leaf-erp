<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnedShopAccountingService
{
    /**
     * @return Collection<int, Shop>
     */
    public function eligibleShops(): Collection
    {
        return Shop::query()
            ->where('accounting_enabled', true)
            ->whereIn('accounting_mode', ['owned', 'partnership'])
            ->with(['ownerships.user'])
            ->orderBy('name')
            ->get();
    }

    public function isEligibleShop(Shop $shop): bool
    {
        return $shop->isOwnedAccountingEnabled();
    }

    public function ownershipPercentageTotal(Shop $shop): float
    {
        return round((float) $shop->ownerships()->sum('ownership_percent'), 2);
    }

    /**
     * @param  array<int, array{user_id?: int|null, owner_name: string, ownership_percent: float|int|string, role_label?: string|null}>  $ownerships
     */
    public function replaceOwnerships(Shop $shop, array $ownerships): void
    {
        $shop->ownerships()->delete();

        foreach ($ownerships as $ownership) {
            $shop->ownerships()->create([
                'user_id' => $ownership['user_id'] ?? null,
                'owner_name' => trim((string) $ownership['owner_name']),
                'ownership_percent' => round((float) $ownership['ownership_percent'], 2),
                'role_label' => filled($ownership['role_label'] ?? null) ? trim((string) $ownership['role_label']) : null,
            ]);
        }
    }

    /**
     * @return Collection<int, ShopAccountingCategory>
     */
    public function availableCategoriesForShop(Shop $shop): Collection
    {
        return ShopAccountingCategory::query()
            ->where('is_active', true)
            ->where(function ($query) use ($shop): void {
                $query->whereNull('shop_id')
                    ->orWhere('shop_id', $shop->id);
            })
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{
     *     business_date:string,
     *     status:string,
     *     opening_cash?: float|int|string|null,
     *     closing_cash?: float|int|string|null,
     *     notes?: string|null,
     *     lines: array<int, array{
     *         shop_accounting_category_id:int,
     *         amount: float|int|string,
     *         description?: string|null
     *     }>
     * }  $payload
     */
    public function saveEntry(Shop $shop, array $payload, int $userId, ?ShopAccountingEntry $entry = null): ShopAccountingEntry
    {
        return DB::transaction(function () use ($shop, $payload, $userId, $entry): ShopAccountingEntry {
            $entry ??= new ShopAccountingEntry;

            $entry->fill([
                'shop_id' => $shop->id,
                'business_date' => Carbon::parse($payload['business_date'])->toDateString(),
                'status' => $payload['status'],
                'opening_cash' => $payload['opening_cash'] ?? null,
                'closing_cash' => $payload['closing_cash'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $entry->exists ? $entry->created_by : $userId,
                'updated_by' => $entry->exists ? $userId : null,
            ]);
            $entry->save();

            $categoryIds = collect($payload['lines'])
                ->pluck('shop_accounting_category_id')
                ->map(fn ($categoryId): int => (int) $categoryId)
                ->all();

            $categories = ShopAccountingCategory::query()
                ->whereIn('id', $categoryIds)
                ->where(function ($query) use ($shop): void {
                    $query->whereNull('shop_id')
                        ->orWhere('shop_id', $shop->id);
                })
                ->get()
                ->keyBy('id');

            $entry->lines()->delete();

            foreach ($payload['lines'] as $line) {
                /** @var ShopAccountingCategory|null $category */
                $category = $categories->get((int) $line['shop_accounting_category_id']);

                if (! $category instanceof ShopAccountingCategory) {
                    continue;
                }

                $entry->lines()->create([
                    'shop_accounting_category_id' => $category->id,
                    'type' => $category->type,
                    'amount' => round((float) $line['amount'], 2),
                    'description' => filled($line['description'] ?? null) ? trim((string) $line['description']) : null,
                ]);
            }

            return $entry->fresh(['lines.category', 'shop', 'createdBy', 'updatedBy']);
        });
    }

    /**
     * @param  array{
     *     business_date:string,
     *     submission_action:string,
     *     opening_cash?: float|int|string|null,
     *     closing_cash?: float|int|string|null,
     *     notes?: string|null,
     *     shop_reply_note?: string|null,
     *     lines: array<int, array{
     *         shop_accounting_category_id:int,
     *         amount: float|int|string,
     *         description?: string|null
     *     }>
     * }  $payload
     */
    public function saveShopOwnerEntry(Shop $shop, array $payload, int $userId, ?ShopAccountingEntry $entry = null): ShopAccountingEntry
    {
        if ($entry instanceof ShopAccountingEntry && ! $entry->canBeEditedByShopOwner()) {
            throw ValidationException::withMessages([
                'business_date' => 'This accounting day is locked until admin sends it back for recheck.',
            ]);
        }

        $status = $payload['submission_action'] === 'submit' ? 'submitted' : 'draft';
        $entry = $this->saveEntry($shop, [
            'business_date' => $payload['business_date'],
            'status' => $status,
            'opening_cash' => $payload['opening_cash'] ?? null,
            'closing_cash' => $payload['closing_cash'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'lines' => $payload['lines'],
        ], $userId, $entry);

        $entry->forceFill([
            'submitted_by' => $status === 'submitted' ? $userId : $entry->submitted_by,
            'submitted_at' => $status === 'submitted' ? now() : $entry->submitted_at,
            'reviewed_by' => $status === 'submitted' ? null : $entry->reviewed_by,
            'reviewed_at' => $status === 'submitted' ? null : $entry->reviewed_at,
            'shop_reply_note' => filled($payload['shop_reply_note'] ?? null) ? trim((string) $payload['shop_reply_note']) : null,
        ])->save();

        return $entry->fresh(['lines.category', 'shop', 'createdBy', 'updatedBy', 'submittedBy', 'reviewedBy']);
    }

    public function reviewEntry(ShopAccountingEntry $entry, string $decision, int $userId, ?string $adminNote = null): ShopAccountingEntry
    {
        $entry->forceFill([
            'status' => $decision === 'approve' ? 'approved' : 'recheck_required',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'admin_note' => filled($adminNote) ? trim((string) $adminNote) : null,
        ])->save();

        return $entry->fresh(['lines.category', 'shop', 'createdBy', 'updatedBy', 'submittedBy', 'reviewedBy']);
    }

    public function entryForDate(Shop $shop, Carbon $date): ?ShopAccountingEntry
    {
        return ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $date)
            ->with(['lines.category', 'createdBy', 'updatedBy', 'submittedBy', 'reviewedBy'])
            ->first();
    }

    /**
     * @return array{
     *     eligible_shop_count:int,
     *     owned_shop_count:int,
     *     partnership_shop_count:int,
     *     entries_today_count:int,
     *     draft_entries_count:int,
     *     pending_review_count:int,
     *     recheck_count:int,
     *     approved_entries_count:int,
     *     total_income:float,
     *     total_expense:float,
     *     net_amount:float,
     *     invoice_count:int
     * }
     */
    public function dashboardMetrics(Carbon $date): array
    {
        $eligibleShops = $this->eligibleShops();
        $entries = ShopAccountingEntry::query()
            ->with('lines')
            ->whereIn('shop_id', $eligibleShops->pluck('id'))
            ->whereDate('business_date', $date)
            ->get();

        $totalIncome = round((float) $entries->sum(
            fn (ShopAccountingEntry $entry): float => (float) $entry->lines->where('type', 'income')->sum('amount')
        ), 2);
        $totalExpense = round((float) $entries->sum(
            fn (ShopAccountingEntry $entry): float => (float) $entry->lines->where('type', 'expense')->sum('amount')
        ), 2);

        return [
            'eligible_shop_count' => $eligibleShops->count(),
            'owned_shop_count' => $eligibleShops->where('accounting_mode', 'owned')->count(),
            'partnership_shop_count' => $eligibleShops->where('accounting_mode', 'partnership')->count(),
            'entries_today_count' => $entries->count(),
            'draft_entries_count' => $entries->where('status', 'draft')->count(),
            'pending_review_count' => $entries->where('status', 'submitted')->count(),
            'recheck_count' => $entries->where('status', 'recheck_required')->count(),
            'approved_entries_count' => $entries->where('status', 'approved')->count(),
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_amount' => round($totalIncome - $totalExpense, 2),
            'invoice_count' => ShopAccountingInvoice::query()
                ->whereIn('shop_id', $eligibleShops->pluck('id'))
                ->whereDate('created_at', $date)
                ->count(),
        ];
    }
}
