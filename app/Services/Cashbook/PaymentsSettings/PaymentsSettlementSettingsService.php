<?php

declare(strict_types=1);

namespace App\Services\Cashbook\PaymentsSettings;

use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Services\Cashbook\RelationSettlementCalculator;
use App\Services\Cashbook\ShopSettlementService;
use Illuminate\Support\Facades\DB;

class PaymentsSettlementSettingsService
{
    public function __construct(
        private readonly ShopSettlementService $settlementService,
        private readonly RelationSettlementCalculator $calculator,
    ) {}

    /**
     * Get view model for Settlement tab.
     *
     * @return array<string, mixed>
     */
    public function getViewModel(Shop $shop, string $startDate, string $endDate): array
    {
        $shopId = (int) $shop->id;
        $profile = ShopLedgerProfile::query()->where('shop_id', $shopId)->first();
        if ($profile) {
            $this->settlementService->ensureDefaults($profile);
        }

        $relations = ShopCashbookRelation::query()
            ->with(['items.setting.entryType', 'items.headerGroup.entrySettings', 'items.sourceSettlement'])
            ->where('shop_id', $shopId)
            ->orderBy('display_order')
            ->get();

        $headers = ShopLedgerHeaderGroup::query()
            ->with('entrySettings')
            ->where('shop_id', $shopId)
            ->get();

        $settings = ShopLedgerEntrySetting::query()
            ->with(['entryType', 'headerGroup'])
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->get();

        // Calculate current financial summary for all relations
        $settlementSummary = $this->settlementService->summary($shopId, $startDate, $endDate);

        // Find default payable relation
        $payableRelation = $this->settlementService->getDefaultPaymentPayable($shopId);
        $payableCalc = $payableRelation ? ($settlementSummary['relations'][$payableRelation->id] ?? null) : null;
        $netPayableAmount = (float) ($payableCalc['netSettlement'] ?? 0.0);

        $owesText = $netPayableAmount >= 0
            ? 'Shop owes Company ₹'.number_format($netPayableAmount, 2)
            : 'Company owes Shop ₹'.number_format(abs($netPayableAmount), 2);

        // Detect duplicate category-in-header inclusions per relation
        $duplicateWarnings = [];
        foreach ($relations as $relation) {
            $headerItemIds = $relation->items->whereNotNull('header_group_id')->pluck('header_group_id')->all();
            $categoryItemIds = $relation->items->whereNotNull('shop_ledger_entry_setting_id')->pluck('shop_ledger_entry_setting_id')->all();

            if (! empty($headerItemIds) && ! empty($categoryItemIds)) {
                $relationHeaders = $headers->whereIn('id', $headerItemIds);
                foreach ($relationHeaders as $header) {
                    foreach ($header->entrySettings as $setting) {
                        if (in_array($setting->id, $categoryItemIds, true)) {
                            $duplicateWarnings[$relation->id][] = "Warning: Category '{$setting->displayName()}' is included directly, but is already part of Header '{$header->name}' in relation '{$relation->name}'.";
                        }
                    }
                }
            }
        }

        return [
            'relations' => $relations,
            'headers' => $headers,
            'settings' => $settings,
            'settlement_summary' => $settlementSummary,
            'payable_relation' => $payableRelation,
            'net_payable_amount' => $netPayableAmount,
            'owes_text' => $owesText,
            'duplicate_warnings' => $duplicateWarnings,
        ];
    }

    /**
     * Save items for a settlement relation (strict shop isolation).
     *
     * @param  array<int, array{type: string, id: int, role: string}>  $items
     */
    public function saveRelationItems(Shop $shop, int $relationId, array $items, int $userId): void
    {
        $shopId = (int) $shop->id;

        DB::transaction(function () use ($shopId, $relationId, $items): void {
            $relation = ShopCashbookRelation::query()
                ->where('shop_id', $shopId)
                ->where('id', $relationId)
                ->lockForUpdate()
                ->firstOrFail();

            // Clear old items
            $relation->items()->delete();

            $newItems = [];
            foreach ($items as $idx => $item) {
                $type = $item['type'] ?? 'category';
                $role = in_array(strtolower((string) ($item['role'] ?? 'add')), ['add', 'subtract'], true)
                    ? strtolower((string) $item['role'])
                    : 'add';

                $record = [
                    'relation_id' => $relation->id,
                    'role' => $role,
                    'display_order' => $idx,
                    'shop_ledger_entry_setting_id' => null,
                    'header_group_id' => null,
                    'source_settlement_id' => null,
                ];

                if ($type === 'header') {
                    $record['header_group_id'] = (int) $item['id'];
                } elseif ($type === 'settlement') {
                    $record['source_settlement_id'] = (int) $item['id'];
                } else {
                    $record['shop_ledger_entry_setting_id'] = (int) $item['id'];
                }

                $newItems[] = $record;
            }

            if (! empty($newItems)) {
                $relation->items()->createMany($newItems);
            }
        });
    }
}
