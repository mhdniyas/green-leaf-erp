<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopCashbookUiLayout;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProductEntry;
use App\Models\Product;
use Illuminate\Support\Collection;

class ShopCashbookUiLayoutService
{
    /**
     * Get the resolved visual layout tree for a shop.
     * Combines saved UI metadata (display names, nesting, ordering) with current DB sources.
     *
     * @param  Collection<int, ShopLedgerHeaderGroup>  $headerGroups
     * @param  Collection<int, ShopLedgerEntrySetting>  $settings
     * @return array{headers: array<int, array<string, mixed>>, is_custom_layout: bool}
     */
    public function getResolvedLayout(int $shopId, Collection $headerGroups, Collection $settings): array
    {
        $layoutRecord = ShopCashbookUiLayout::query()->where('shop_id', $shopId)->first();
        $savedTree = $layoutRecord?->layout_data;

        $headerGroups->loadMissing('allowedProducts');

        // Fetch any products recorded in ShopLedgerProductEntry for this shop
        $enteredProductEntries = ShopLedgerProductEntry::query()
            ->with('product')
            ->where('shop_id', $shopId)
            ->whereIn('header_group_id', $headerGroups->pluck('id'))
            ->get();

        // Build canonical product map by header_group_id
        $productsByHeader = [];
        foreach ($headerGroups as $hg) {
            $hgId = (int) $hg->id;
            $headerProds = collect();

            if ($hg->product_tagging_enabled) {
                // 1. Configured allowed products (or all active products if unconstrained)
                $availableProducts = $hg->allowedProducts->isNotEmpty()
                    ? $hg->allowedProducts
                    : Product::query()->active()->get();

                foreach ($availableProducts as $prod) {
                    $headerProds->put((int) $prod->id, [
                        'id' => (int) $prod->id,
                        'name' => (string) $prod->name,
                        'sku' => (string) ($prod->sku ?? ''),
                        'unit' => (string) ($prod->unit ?: 'kg'),
                        'is_product' => true,
                    ]);
                }

                // 2. Entered product entries for this header in this shop
                foreach ($enteredProductEntries->where('header_group_id', $hgId) as $pe) {
                    $pId = (int) ($pe->product_id ?? $pe->product?->id ?? 0);
                    if ($pId > 0 && ! $headerProds->has($pId)) {
                        $headerProds->put($pId, [
                            'id' => $pId,
                            'name' => (string) ($pe->product_name ?? $pe->product?->name ?? 'Product #'.$pId),
                            'sku' => (string) ($pe->product_sku ?? $pe->product?->sku ?? ''),
                            'unit' => (string) ($pe->unit ?: $pe->product?->unit ?: 'kg'),
                            'is_product' => true,
                        ]);
                    }
                }
            }

            $productsByHeader[$hgId] = $headerProds->values()->all();
        }

        $sortedSettings = $settings->sortBy(fn (ShopLedgerEntrySetting $s): int => (int) ($s->header_display_order ?? $s->entryType?->display_order ?? $s->display_order))->values();
        $settingsByHeader = $sortedSettings->groupBy(fn (ShopLedgerEntrySetting $s): int => (int) ($s->header_group_id ?? 0));
        $assignedHeaderIds = $headerGroups->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if (is_array($savedTree) && ! empty($savedTree['headers'])) {
            return [
                'headers' => $this->buildFromSavedTree($savedTree['headers'], $headerGroups, $settings, $settingsByHeader, $productsByHeader, $assignedHeaderIds),
                'is_custom_layout' => true,
            ];
        }

        return [
            'headers' => $this->buildDefaultLayout($headerGroups, $sortedSettings, $settingsByHeader, $productsByHeader, $assignedHeaderIds),
            'is_custom_layout' => false,
        ];
    }

    /**
     * Save the UI-only layout structure for a shop.
     *
     * @param  array<string, mixed>  $layoutData
     */
    public function saveLayout(int $shopId, array $layoutData): ShopCashbookUiLayout
    {
        return ShopCashbookUiLayout::updateOrCreate(
            ['shop_id' => $shopId],
            ['layout_data' => $layoutData]
        );
    }

    /**
     * Reset the layout to default (deletes custom UI metadata).
     */
    public function resetLayout(int $shopId): void
    {
        ShopCashbookUiLayout::query()->where('shop_id', $shopId)->delete();
    }

    /**
     * Reconstruct visual tree from saved layout metadata while validating against active DB records.
     *
     * @param  array<int, mixed>  $savedHeaders
     * @param  Collection<int, ShopLedgerHeaderGroup>  $headerGroups
     * @param  Collection<int, ShopLedgerEntrySetting>  $settings
     * @param  Collection<int, Collection<int, ShopLedgerEntrySetting>>  $settingsByHeader
     * @param  array<int, array<int, array<string, mixed>>>  $productsByHeader
     * @param  array<int, int>  $assignedHeaderIds
     * @return array<int, array<string, mixed>>
     */
    private function buildFromSavedTree(
        array $savedHeaders,
        Collection $headerGroups,
        Collection $settings,
        Collection $settingsByHeader,
        array $productsByHeader,
        array $assignedHeaderIds
    ): array {
        $usedHeaderIds = [];
        $usedSettingIds = [];
        $usedProductIdsByHeader = [];
        $resultHeaders = [];

        foreach ($savedHeaders as $hNode) {
            $sourceId = $hNode['source_id'] ?? $hNode['id'] ?? null;
            $hg = is_numeric($sourceId) ? $headerGroups->firstWhere('id', (int) $sourceId) : null;
            $hgId = $hg ? (int) $hg->id : null;

            if ($hg) {
                $usedHeaderIds[] = (int) $hg->id;
                $originalName = (string) $hg->name;
                $type = strtolower((string) ($hg->type ?? 'income'));
                $nodeId = (string) $hg->id;
                $productTagging = (bool) ($hg->product_tagging_enabled ?? false);
                $showBothSides = (bool) ($hg->show_both_sides ?? false);
            } else {
                $nodeId = (string) ($hNode['id'] ?? 'unassigned_'.uniqid());
                $originalName = (string) ($hNode['original_name'] ?? $hNode['display_name'] ?? 'Section');
                $type = strtolower((string) ($hNode['type'] ?? 'income'));
                $productTagging = false;
                $showBothSides = false;
            }

            $customDisplayName = ! empty($hNode['custom_display_name'])
                ? trim((string) $hNode['custom_display_name'])
                : (! empty($hNode['display_name']) && trim((string) $hNode['display_name']) !== $originalName ? trim((string) $hNode['display_name']) : null);

            $displayName = $customDisplayName ?: $originalName;

            // Resolve Sub-headers
            $resolvedSubHeaders = [];
            if (! empty($hNode['sub_headers']) && is_array($hNode['sub_headers'])) {
                foreach ($hNode['sub_headers'] as $subNode) {
                    $subSourceId = $subNode['source_id'] ?? $subNode['id'] ?? null;
                    $subHg = is_numeric($subSourceId) ? $headerGroups->firstWhere('id', (int) $subSourceId) : null;
                    $subHgId = $subHg ? (int) $subHg->id : null;

                    if ($subHg) {
                        $usedHeaderIds[] = (int) $subHg->id;
                        $subOrigName = (string) $subHg->name;
                        $subType = strtolower((string) ($subHg->type ?? 'income'));
                        $subId = (string) $subHg->id;
                        $subProductTagging = (bool) ($subHg->product_tagging_enabled ?? false);
                    } else {
                        $subId = (string) ($subNode['id'] ?? 'sub_'.uniqid());
                        $subOrigName = (string) ($subNode['original_name'] ?? $subNode['display_name'] ?? 'Sub Header');
                        $subType = strtolower((string) ($subNode['type'] ?? 'income'));
                        $subProductTagging = false;
                    }

                    $subCustomName = ! empty($subNode['custom_display_name'])
                        ? trim((string) $subNode['custom_display_name'])
                        : (! empty($subNode['display_name']) && trim((string) $subNode['display_name']) !== $subOrigName ? trim((string) $subNode['display_name']) : null);

                    $subDisplayName = $subCustomName ?: $subOrigName;

                    // Resolve items in sub-header
                    $subItems = collect();
                    if (! empty($subNode['setting_ids']) && is_array($subNode['setting_ids'])) {
                        foreach ($subNode['setting_ids'] as $sId) {
                            $st = $settings->firstWhere('id', (int) $sId);
                            if ($st) {
                                $subItems->push($st);
                                $usedSettingIds[] = (int) $st->id;
                            }
                        }
                    }

                    // Resolve products in sub-header
                    $subCanonicalProducts = $subHgId && isset($productsByHeader[$subHgId]) ? $productsByHeader[$subHgId] : [];
                    $subResolvedProducts = [];
                    $savedSubProductIds = $subNode['product_ids'] ?? [];

                    if (! empty($savedSubProductIds) && is_array($savedSubProductIds)) {
                        foreach ($savedSubProductIds as $pId) {
                            $matchedProd = collect($subCanonicalProducts)->firstWhere('id', (int) $pId);
                            if ($matchedProd) {
                                $subResolvedProducts[] = $matchedProd;
                                $usedProductIdsByHeader[$subHgId][] = (int) $pId;
                            }
                        }
                    }

                    // Append any new canonical products not in saved subNode layout
                    foreach ($subCanonicalProducts as $cProd) {
                        if (! in_array((int) $cProd['id'], $usedProductIdsByHeader[$subHgId] ?? [], true)) {
                            $subResolvedProducts[] = $cProd;
                            $usedProductIdsByHeader[$subHgId][] = (int) $cProd['id'];
                        }
                    }

                    $resolvedSubHeaders[] = [
                        'id' => $subId,
                        'source_id' => $subHg ? (int) $subHg->id : null,
                        'name' => $subDisplayName,
                        'original_name' => $subOrigName,
                        'display_name' => $subDisplayName,
                        'custom_display_name' => $subCustomName,
                        'type' => $subType,
                        'product_tagging_enabled' => $subProductTagging,
                        'settings' => $subItems,
                        'products' => $subResolvedProducts,
                    ];
                }
            }

            // Resolve direct items in root header
            $directItems = collect();
            if (! empty($hNode['setting_ids']) && is_array($hNode['setting_ids'])) {
                foreach ($hNode['setting_ids'] as $sId) {
                    $st = $settings->firstWhere('id', (int) $sId);
                    if ($st && ! in_array((int) $st->id, $usedSettingIds, true)) {
                        $directItems->push($st);
                        $usedSettingIds[] = (int) $st->id;
                    }
                }
            }

            // Resolve direct products in root header
            $rootCanonicalProducts = $hgId && isset($productsByHeader[$hgId]) ? $productsByHeader[$hgId] : [];
            $rootResolvedProducts = [];
            $savedRootProductIds = $hNode['product_ids'] ?? [];

            if (! empty($savedRootProductIds) && is_array($savedRootProductIds)) {
                foreach ($savedRootProductIds as $pId) {
                    $matchedProd = collect($rootCanonicalProducts)->firstWhere('id', (int) $pId);
                    if ($matchedProd) {
                        $rootResolvedProducts[] = $matchedProd;
                        $usedProductIdsByHeader[$hgId][] = (int) $pId;
                    }
                }
            }

            // Append any new canonical products not in saved hNode layout
            foreach ($rootCanonicalProducts as $cProd) {
                if (! in_array((int) $cProd['id'], $usedProductIdsByHeader[$hgId] ?? [], true)) {
                    $rootResolvedProducts[] = $cProd;
                    $usedProductIdsByHeader[$hgId][] = (int) $cProd['id'];
                }
            }

            $resultHeaders[] = [
                'id' => $nodeId,
                'source_id' => $hg ? (int) $hg->id : null,
                'name' => $displayName,
                'original_name' => $originalName,
                'display_name' => $displayName,
                'custom_display_name' => $customDisplayName,
                'type' => $type,
                'product_tagging_enabled' => $productTagging,
                'show_both_sides' => $showBothSides,
                'sub_headers' => $resolvedSubHeaders,
                'settings' => $directItems,
                'products' => $rootResolvedProducts,
            ];
        }

        // Safely append any newly created DB headers not present in the saved layout
        foreach ($headerGroups->sortBy('display_order') as $hg) {
            if (! in_array((int) $hg->id, $usedHeaderIds, true)) {
                $hSettings = $settingsByHeader->get((int) $hg->id, collect())
                    ->reject(fn ($s): bool => in_array((int) $s->id, $usedSettingIds, true))
                    ->values();

                foreach ($hSettings as $st) {
                    $usedSettingIds[] = (int) $st->id;
                }

                $hProds = $productsByHeader[(int) $hg->id] ?? [];

                $resultHeaders[] = [
                    'id' => (string) $hg->id,
                    'source_id' => (int) $hg->id,
                    'name' => (string) $hg->name,
                    'original_name' => (string) $hg->name,
                    'display_name' => (string) $hg->name,
                    'custom_display_name' => null,
                    'type' => strtolower((string) ($hg->type ?? 'income')),
                    'product_tagging_enabled' => (bool) ($hg->product_tagging_enabled ?? false),
                    'show_both_sides' => (bool) ($hg->show_both_sides ?? false),
                    'sub_headers' => [],
                    'settings' => $hSettings,
                    'products' => $hProds,
                ];
            }
        }

        // Safely append any orphan settings not present in the saved layout
        $orphanSettings = $settings->reject(fn ($s): bool => in_array((int) $s->id, $usedSettingIds, true))->values();
        if ($orphanSettings->isNotEmpty()) {
            $orphanIncome = $orphanSettings->filter(function ($s): bool {
                $cat = strtolower((string) ($s->entryType?->category ?? ''));

                return $cat === 'income' || $s->include_in_sales || $s->include_in_income;
            })->values();

            $orphanExpense = $orphanSettings->reject(fn ($s): bool => $orphanIncome->contains('id', $s->id))->values();

            if ($orphanIncome->isNotEmpty()) {
                $resultHeaders[] = [
                    'id' => 'unassigned_income',
                    'source_id' => null,
                    'name' => 'OTHER INCOME',
                    'original_name' => 'OTHER INCOME',
                    'display_name' => 'OTHER INCOME',
                    'custom_display_name' => null,
                    'type' => 'income',
                    'product_tagging_enabled' => false,
                    'show_both_sides' => false,
                    'sub_headers' => [],
                    'settings' => $orphanIncome,
                    'products' => [],
                ];
            }

            if ($orphanExpense->isNotEmpty()) {
                $resultHeaders[] = [
                    'id' => 'unassigned_expense',
                    'source_id' => null,
                    'name' => 'OTHER EXPENSES',
                    'original_name' => 'OTHER EXPENSES',
                    'display_name' => 'OTHER EXPENSES',
                    'custom_display_name' => null,
                    'type' => 'expense',
                    'product_tagging_enabled' => false,
                    'show_both_sides' => false,
                    'sub_headers' => [],
                    'settings' => $orphanExpense,
                    'products' => [],
                ];
            }
        }

        return $resultHeaders;
    }

    /**
     * Construct default un-nested layout from active DB records.
     *
     * @param  Collection<int, ShopLedgerHeaderGroup>  $headerGroups
     * @param  Collection<int, ShopLedgerEntrySetting>  $sortedSettings
     * @param  Collection<int, Collection<int, ShopLedgerEntrySetting>>  $settingsByHeader
     * @param  array<int, int>  $assignedHeaderIds
     * @return array<int, array<string, mixed>>
     */
    private function buildDefaultLayout(
        Collection $headerGroups,
        Collection $sortedSettings,
        Collection $settingsByHeader,
        array $productsByHeader,
        array $assignedHeaderIds
    ): array {
        $sections = [];

        foreach ($headerGroups->sortBy('display_order') as $hg) {
            $hgId = (int) $hg->id;
            $headerSettings = $settingsByHeader->get($hgId, collect())->values();
            $hProds = $productsByHeader[$hgId] ?? [];

            if ($headerSettings->isNotEmpty() || $hg->product_tagging_enabled || ! empty($hProds)) {
                $sections[] = [
                    'id' => (string) $hgId,
                    'source_id' => $hgId,
                    'name' => (string) $hg->name,
                    'original_name' => (string) $hg->name,
                    'display_name' => (string) $hg->name,
                    'custom_display_name' => null,
                    'type' => strtolower((string) ($hg->type ?? 'income')),
                    'product_tagging_enabled' => (bool) ($hg->product_tagging_enabled ?? false),
                    'show_both_sides' => (bool) ($hg->show_both_sides ?? false),
                    'sub_headers' => [],
                    'settings' => $headerSettings,
                    'products' => $hProds,
                ];
            }
        }

        $unassignedSettings = $sortedSettings->reject(function ($s) use ($assignedHeaderIds): bool {
            return $s->header_group_id && in_array((int) $s->header_group_id, $assignedHeaderIds, true);
        })->values();

        if (! empty($unassignedSettings) && $unassignedSettings->isNotEmpty()) {
            $unassignedTransfers = $unassignedSettings->filter(function ($s): bool {
                $cat = strtolower((string) ($s->entryType?->category ?? ''));

                return $cat === 'transfer' || $cat === 'settlement';
            })->values();

            $unassignedIncome = $unassignedSettings->filter(function ($s) use ($unassignedTransfers): bool {
                if ($unassignedTransfers->contains('id', $s->id)) {
                    return false;
                }
                $cat = strtolower((string) ($s->entryType?->category ?? ''));

                return $cat === 'income' || $s->include_in_sales || $s->include_in_income;
            })->values();

            $unassignedExpense = $unassignedSettings->reject(function ($s) use ($unassignedIncome, $unassignedTransfers): bool {
                return $unassignedIncome->contains('id', $s->id) || $unassignedTransfers->contains('id', $s->id);
            })->values();

            if ($unassignedIncome->isNotEmpty()) {
                $sections[] = [
                    'id' => 'unassigned_income',
                    'source_id' => null,
                    'name' => 'OTHER INCOME',
                    'original_name' => 'OTHER INCOME',
                    'display_name' => 'OTHER INCOME',
                    'custom_display_name' => null,
                    'type' => 'income',
                    'product_tagging_enabled' => false,
                    'show_both_sides' => false,
                    'sub_headers' => [],
                    'settings' => $unassignedIncome,
                    'products' => [],
                ];
            }

            if ($unassignedExpense->isNotEmpty()) {
                $sections[] = [
                    'id' => 'unassigned_expense',
                    'source_id' => null,
                    'name' => 'OTHER EXPENSES',
                    'original_name' => 'OTHER EXPENSES',
                    'display_name' => 'OTHER EXPENSES',
                    'custom_display_name' => null,
                    'type' => 'expense',
                    'product_tagging_enabled' => false,
                    'show_both_sides' => false,
                    'sub_headers' => [],
                    'settings' => $unassignedExpense,
                    'products' => [],
                ];
            }

            if ($unassignedTransfers->isNotEmpty()) {
                $sections[] = [
                    'id' => 'unassigned_transfers',
                    'source_id' => null,
                    'name' => 'TRANSFERS & SETTLEMENTS',
                    'original_name' => 'TRANSFERS & SETTLEMENTS',
                    'display_name' => 'TRANSFERS & SETTLEMENTS',
                    'custom_display_name' => null,
                    'type' => 'expense',
                    'product_tagging_enabled' => false,
                    'show_both_sides' => false,
                    'sub_headers' => [],
                    'settings' => $unassignedTransfers,
                    'products' => [],
                ];
            }
        }

        if (empty($sections) && $sortedSettings->isNotEmpty()) {
            $incomeSet = $sortedSettings->filter(function ($s): bool {
                $cat = strtolower((string) ($s->entryType?->category ?? ''));

                return $cat === 'income' || $s->include_in_sales || $s->include_in_income;
            })->values();
            $expenseSet = $sortedSettings->reject(fn ($s): bool => $incomeSet->contains('id', $s->id))->values();

            if ($incomeSet->isNotEmpty()) {
                $sections[] = [
                    'id' => 'default_sales',
                    'source_id' => null,
                    'name' => 'SALES',
                    'original_name' => 'SALES',
                    'display_name' => 'SALES',
                    'custom_display_name' => null,
                    'type' => 'income',
                    'product_tagging_enabled' => false,
                    'show_both_sides' => false,
                    'sub_headers' => [],
                    'settings' => $incomeSet,
                    'products' => [],
                ];
            }
            if ($expenseSet->isNotEmpty()) {
                $sections[] = [
                    'id' => 'default_expense',
                    'source_id' => null,
                    'name' => 'SHOP EXPENSES',
                    'original_name' => 'SHOP EXPENSES',
                    'display_name' => 'SHOP EXPENSES',
                    'custom_display_name' => null,
                    'type' => 'expense',
                    'product_tagging_enabled' => false,
                    'show_both_sides' => false,
                    'sub_headers' => [],
                    'settings' => $expenseSet,
                    'products' => [],
                ];
            }
        }

        return $sections;
    }
}
