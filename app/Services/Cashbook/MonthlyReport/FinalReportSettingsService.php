<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

use App\Models\Cashbook\CashbookMonthlyReportExpenseMapping;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\CompanyAccountingCategory;
use App\Models\PurchaseProductFilter;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FinalReportSettingsService
{
    /**
     * Evaluate comprehensive readiness status for final report settings.
     *
     * @return array{
     *     status: string,
     *     status_code: string,
     *     status_class: string,
     *     blocked_reasons: array<int, string>,
     *     warning_reasons: array<int, string>,
     *     is_ready: bool,
     *     is_blocked: bool,
     *     product_group_status: array<string, mixed>,
     *     shop_heading_status: array<string, mixed>,
     *     expense_mapping_status: array<string, mixed>
     * }
     */
    public function getReadinessSummary(): array
    {
        $blockedReasons = [];
        $warningReasons = [];

        // 1. Check Product Groups (Fruits, Vegetables, Stationery)
        $activeFilters = PurchaseProductFilter::query()
            ->active()
            ->with(['filterItems.product'])
            ->get();

        $fruitsFilter = $activeFilters->firstWhere('monthly_report_group', 'fruits');
        $vegFilter = $activeFilters->firstWhere('monthly_report_group', 'vegetables');
        $stationeryFilter = $activeFilters->firstWhere('monthly_report_group', 'stationery');

        if (! $fruitsFilter) {
            $blockedReasons[] = 'Missing active product filter assigned to Fruits.';
        }
        if (! $vegFilter) {
            $blockedReasons[] = 'Missing active product filter assigned to Vegetables.';
        }
        if (! $stationeryFilter) {
            $blockedReasons[] = 'Missing active product filter assigned to Stationery.';
        }

        // Check product overlaps between assigned active filters
        $productAssignments = [];
        $overlapProducts = [];

        foreach ($activeFilters->whereNotNull('monthly_report_group') as $filter) {
            $group = $filter->monthly_report_group;
            foreach ($filter->filterItems as $item) {
                $pid = (int) $item->product_id;
                $pName = $item->product?->name ?? "Product #{$pid}";
                if (isset($productAssignments[$pid]) && $productAssignments[$pid]['group'] !== $group) {
                    $overlapProducts[] = "{$pName} belongs to both {$productAssignments[$pid]['group']} ({$productAssignments[$pid]['filter_name']}) and {$group} ({$filter->name})";
                } else {
                    $productAssignments[$pid] = [
                        'group' => $group,
                        'filter_name' => $filter->name,
                        'product_name' => $pName,
                    ];
                }
            }
        }

        if (! empty($overlapProducts)) {
            $blockedReasons[] = 'Overlapping product filter memberships detected: '.implode('; ', array_slice($overlapProducts, 0, 3)).(count($overlapProducts) > 3 ? ' (and more)' : '');
        }

        // 2. Check Client/Owned Shops Headings
        $clientShops = Shop::query()
            ->with(['client', 'ledgerEntrySettings.entryType'])
            ->whereNotNull('client_id')
            ->where('status', 'active')
            ->get();

        $unmappedShopCategories = [];
        $unmappedShops = [];

        foreach ($clientShops as $shop) {
            $settings = $shop->ledgerEntrySettings->where('enabled', true);
            $hasSales = false;
            $shopUnmappedCount = 0;

            foreach ($settings as $st) {
                $bucket = $st->resolveMonthlyReportBucket();
                if (! $bucket || ! ReportHeadingDictionary::isValid($bucket)) {
                    $shopUnmappedCount++;
                    $unmappedShopCategories[] = "Shop '{$shop->name}' category '{$st->displayName()}' is unmapped";
                }
                if ($bucket && ReportHeadingDictionary::isSale($bucket)) {
                    $hasSales = true;
                }
            }

            if (! $hasSales) {
                $warningReasons[] = "Shop '{$shop->name}' has no active category mapped to a Sales heading.";
            }

            if ($shopUnmappedCount > 0) {
                $unmappedShops[$shop->id] = $shop->name;
            }
        }

        if (! empty($unmappedShopCategories)) {
            $warningReasons[] = count($unmappedShopCategories).' active client shop category/categories have no explicit monthly report heading.';
        }

        // 3. Check Expense Mappings
        $companyCategories = CompanyAccountingCategory::all();
        $customMappings = CashbookMonthlyReportExpenseMapping::where('source_type', 'company_accounting_category')->get()->keyBy('source_key');

        $unmappedCompanyCategories = [];
        foreach ($companyCategories as $cc) {
            if (! $customMappings->has((string) $cc->id) && ! $customMappings->has($cc->name)) {
                $unmappedCompanyCategories[] = $cc->name;
            }
        }

        if (! empty($unmappedCompanyCategories)) {
            $warningReasons[] = count($unmappedCompanyCategories).' company expense categories are using default heuristic mappings.';
        }

        $isBlocked = count($blockedReasons) > 0;
        $isReady = (! $isBlocked && count($warningReasons) === 0);

        $statusCode = $isBlocked ? 'blocked' : (count($warningReasons) > 0 ? 'warning' : 'ready');
        $statusLabel = $isBlocked ? 'Blocked' : (count($warningReasons) > 0 ? 'Warning' : 'Ready');
        $statusClass = match ($statusCode) {
            'ready' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
            'blocked' => 'bg-rose-50 text-rose-700 border-rose-200',
        };

        $unmappedProductFilters = $activeFilters->whereNull('monthly_report_group')->pluck('name')->values()->all();
        $configuredShops = $clientShops->reject(fn ($s) => isset($unmappedShops[$s->id]))->pluck('name')->values()->all();

        return [
            'status' => $statusLabel,
            'status_code' => $statusCode,
            'status_class' => $statusClass,
            'blocked_reasons' => $blockedReasons,
            'warning_reasons' => $warningReasons,
            'is_ready' => $isReady,
            'is_blocked' => $isBlocked,
            'unmapped_product_filters' => $unmappedProductFilters,
            'configured_shops' => $configuredShops,
            'unconfigured_shops' => array_values($unmappedShops),
            'unmapped_expense_categories' => $unmappedCompanyCategories,
            'product_group_status' => [
                'fruits_filter' => $fruitsFilter,
                'vegetables_filter' => $vegFilter,
                'stationery_filter' => $stationeryFilter,
                'overlap_count' => count($overlapProducts),
            ],
            'shop_heading_status' => [
                'total_client_shops' => $clientShops->count(),
                'unmapped_shop_count' => count($unmappedShops),
            ],
            'expense_mapping_status' => [
                'unmapped_company_categories_count' => count($unmappedCompanyCategories),
            ],
        ];
    }

    /**
     * Update product filter groups with overlap validation.
     *
     * @param  array<int, array{filter_id: int, monthly_report_group: ?string}>  $assignments
     */
    public function updateProductGroups(array $assignments, User $actor): void
    {
        DB::transaction(function () use ($assignments, $actor): void {
            // First check for duplicate groups in the assignment payload
            $assignedGroups = [];
            foreach ($assignments as $as) {
                $group = $as['monthly_report_group'] ?? null;
                if ($group && $group !== 'none') {
                    if (isset($assignedGroups[$group])) {
                        throw new InvalidArgumentException("Duplicate product group assignment: multiple filters assigned to {$group}.");
                    }
                    $assignedGroups[$group] = $as['filter_id'];
                }
            }

            foreach ($assignments as $as) {
                $filter = PurchaseProductFilter::findOrFail($as['filter_id']);
                $oldGroup = $filter->monthly_report_group;
                $newGroup = ($as['monthly_report_group'] === 'none' || empty($as['monthly_report_group'])) ? null : $as['monthly_report_group'];

                if ($oldGroup !== $newGroup) {
                    $filter->update(['monthly_report_group' => $newGroup]);
                    activity('final_report_settings')
                        ->performedOn($filter)
                        ->causedBy($actor)
                        ->withProperties([
                            'action' => 'update_product_group',
                            'filter_name' => $filter->name,
                            'old_group' => $oldGroup,
                            'new_group' => $newGroup,
                        ])
                        ->log("Updated product group for filter {$filter->name} from '{$oldGroup}' to '{$newGroup}'");
                }
            }
        });
    }

    /**
     * Update shop heading mappings for a shop's entry settings.
     *
     * @param  array<int, array{setting_id: int, monthly_report_bucket: ?string}>  $mappings
     */
    public function updateShopHeadings(int $shopId, array $mappings, User $actor): void
    {
        DB::transaction(function () use ($shopId, $mappings, $actor): void {
            $shop = Shop::findOrFail($shopId);

            foreach ($mappings as $m) {
                $setting = ShopLedgerEntrySetting::where('shop_id', $shopId)->findOrFail($m['setting_id']);
                $oldBucket = $setting->monthly_report_bucket;
                $newBucket = empty($m['monthly_report_bucket']) ? null : $m['monthly_report_bucket'];

                if ($oldBucket !== $newBucket) {
                    $setting->update(['monthly_report_bucket' => $newBucket]);
                    activity('final_report_settings')
                        ->performedOn($setting)
                        ->causedBy($actor)
                        ->withProperties([
                            'action' => 'update_shop_heading',
                            'shop_id' => $shopId,
                            'shop_name' => $shop->name,
                            'setting_name' => $setting->displayName(),
                            'old_bucket' => $oldBucket,
                            'new_bucket' => $newBucket,
                        ])
                        ->log("Updated final report heading for shop {$shop->name} - {$setting->displayName()} to '{$newBucket}'");
                }
            }
        });
    }

    /**
     * Bulk copy mappings from source shop to target shop.
     */
    public function copyShopHeadings(int $sourceShopId, int $targetShopId, User $actor): void
    {
        DB::transaction(function () use ($sourceShopId, $targetShopId, $actor): void {
            $sourceSettings = ShopLedgerEntrySetting::where('shop_id', $sourceShopId)->get()->keyBy('entry_type_id');
            $targetSettings = ShopLedgerEntrySetting::where('shop_id', $targetShopId)->get();

            $targetShop = Shop::findOrFail($targetShopId);
            $sourceShop = Shop::findOrFail($sourceShopId);

            foreach ($targetSettings as $tSetting) {
                $src = $sourceSettings->get($tSetting->entry_type_id);
                if ($src && $src->monthly_report_bucket !== null) {
                    $tSetting->update(['monthly_report_bucket' => $src->monthly_report_bucket]);
                }
            }

            activity('final_report_settings')
                ->causedBy($actor)
                ->withProperties([
                    'action' => 'copy_shop_headings',
                    'source_shop_id' => $sourceShopId,
                    'target_shop_id' => $targetShopId,
                ])
                ->log("Copied report headings from {$sourceShop->name} to {$targetShop->name}");
        });
    }

    /**
     * Update category expense mappings.
     *
     * @param  array<int, array{source_type: string, source_key: string, report_bucket: string}>  $mappings
     */
    public function updateExpenseMappings(array $mappings, User $actor): void
    {
        DB::transaction(function () use ($mappings, $actor): void {
            foreach ($mappings as $m) {
                $sourceType = $m['source_type'];
                $sourceKey = (string) $m['source_key'];
                $bucket = $m['report_bucket'] ?? null;

                if (empty($bucket)) {
                    CashbookMonthlyReportExpenseMapping::where('source_type', $sourceType)
                        ->where('source_key', $sourceKey)
                        ->delete();

                    continue;
                }

                CashbookMonthlyReportExpenseMapping::updateOrCreate(
                    ['source_type' => $sourceType, 'source_key' => $sourceKey],
                    [
                        'report_bucket' => $bucket,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]
                );

                activity('final_report_settings')
                    ->causedBy($actor)
                    ->withProperties([
                        'action' => 'update_expense_mapping',
                        'source_type' => $sourceType,
                        'source_key' => $sourceKey,
                        'report_bucket' => $bucket,
                    ])
                    ->log("Updated expense mapping for {$sourceType}:{$sourceKey} to '{$bucket}'");
            }
        });
    }
}
