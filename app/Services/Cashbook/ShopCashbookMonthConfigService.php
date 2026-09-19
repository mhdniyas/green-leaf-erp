<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CategoryVendorMapping;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopBankSettlementAdjustmentRule;
use App\Models\Cashbook\ShopCashbookMonthConfigSnapshot;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerCollectionGroup;
use App\Models\Cashbook\ShopLedgerCollectionGroupEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopSupplier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ShopCashbookMonthConfigService
{
    public function __construct(
        private readonly CashbookShopSyncService $shopSyncService,
    ) {}

    /**
     * Get the full cashbook category and header configuration for a given shop and month.
     *
     * @return array{
     *     month: string,
     *     month_label: string,
     *     is_current: bool,
     *     is_historical: bool,
     *     is_legacy: bool,
     *     is_read_only: bool,
     *     snapshot: ?ShopCashbookMonthConfigSnapshot,
     *     headers: EloquentCollection<int, ShopLedgerHeaderGroup>,
     *     settings: EloquentCollection<int, ShopLedgerEntrySetting>,
     *     settings_by_category: Collection<string, EloquentCollection<int, ShopLedgerEntrySetting>>,
     *     relations: EloquentCollection<int, ShopCashbookRelation>,
     *     collection_group: ?ShopLedgerCollectionGroup,
     *     bank_adjustment_rules: Collection<int, Collection<int, ShopBankSettlementAdjustmentRule>>,
     *     all_entry_types: EloquentCollection<int, LedgerEntryType>,
     *     shop_suppliers: Collection<int, ShopSupplier>,
     * }
     */
    public function getConfigurationForMonth(int $shopId, string $month): array
    {
        $currentMonth = Carbon::now()->format('Y-m');
        $isCurrent = ($month === $currentMonth);
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthLabel = $monthDate->translatedFormat('F Y');

        if ($isCurrent) {
            return $this->getLiveConfiguration($shopId, $month, $monthLabel);
        }

        return $this->getHistoricalConfiguration($shopId, $month, $monthLabel);
    }

    /**
     * Get live configuration directly from database models.
     */
    public function getLiveConfiguration(int $shopId, string $month, string $monthLabel): array
    {
        $shop = Shop::query()->findOrFail($shopId);
        $this->shopSyncService->ensureVendorPurchaseForShop($shopId);

        $headers = ShopLedgerHeaderGroup::query()
            ->where('shop_id', $shopId)
            ->orderBy('display_order')
            ->get();

        $settings = ShopLedgerEntrySetting::query()
            ->with(['entryType', 'headerGroup', 'companyAccount', 'vendorSettlementRelation', 'definedShopSuppliers.supplier'])
            ->where('shop_id', $shopId)
            ->get()
            ->sortBy(fn (ShopLedgerEntrySetting $s): int => (int) ($s->entryType?->display_order ?? $s->display_order))
            ->values();

        $settingsByCategory = $settings->groupBy(fn (ShopLedgerEntrySetting $s): string => (string) ($s->entryType?->category ?? 'other'));

        $collectionGroup = ShopLedgerCollectionGroup::query()
            ->where('shop_id', $shopId)
            ->where('code', 'collection')
            ->with('entryTypes.entryType')
            ->first();

        $bankAdjustmentRules = ShopBankSettlementAdjustmentRule::query()
            ->where('shop_id', $shopId)
            ->get()
            ->groupBy('entry_type_id');

        $relations = ShopCashbookRelation::query()
            ->with(['items.setting.entryType', 'items.setting.companyAccount', 'items.headerGroup', 'items.sourceSettlement'])
            ->where('shop_id', $shopId)
            ->orderBy('display_order')
            ->get();

        $allEntryTypes = LedgerEntryType::where('active', true)->orderBy('display_order')->get();

        $shopSuppliers = ShopSupplier::query()
            ->with('supplier')
            ->where('shop_id', $shopId)
            ->get()
            ->sortBy(fn (ShopSupplier $ss): string => strtolower((string) ($ss->supplier?->name ?? '')))
            ->values();

        return [
            'month' => $month,
            'month_label' => $monthLabel,
            'is_current' => true,
            'is_historical' => false,
            'is_legacy' => false,
            'is_read_only' => false,
            'snapshot' => null,
            'headers' => $headers,
            'settings' => $settings,
            'settings_by_category' => $settingsByCategory,
            'relations' => $relations,
            'collection_group' => $collectionGroup,
            'bank_adjustment_rules' => $bankAdjustmentRules,
            'all_entry_types' => $allEntryTypes,
            'shop_suppliers' => $shopSuppliers,
        ];
    }

    /**
     * Get historical configuration from a snapshot or reconstruct legacy history.
     */
    public function getHistoricalConfiguration(int $shopId, string $month, string $monthLabel): array
    {
        $snapshot = ShopCashbookMonthConfigSnapshot::query()
            ->where('shop_id', $shopId)
            ->where('month', $month)
            ->first();

        if (! $snapshot) {
            $snapshot = $this->reconstructLegacyMonthConfig($shopId, $month);
        }

        return $this->hydrateFromSnapshot($shopId, $snapshot, $monthLabel);
    }

    /**
     * Capture a configuration snapshot for a shop and month.
     * Idempotent unless $force is true.
     */
    public function captureSnapshot(
        int $shopId,
        string $month,
        string $source = 'live_frozen',
        bool $force = false,
        ?int $userId = null,
        ?string $notes = null
    ): ShopCashbookMonthConfigSnapshot {
        $existing = ShopCashbookMonthConfigSnapshot::query()
            ->where('shop_id', $shopId)
            ->where('month', $month)
            ->first();

        if ($existing && ! $force) {
            return $existing;
        }

        $configData = $this->buildConfigDataFromLive($shopId);

        $status = ($source === 'legacy_reconstruction') ? 'legacy_reconstructed' : 'finalized';

        return ShopCashbookMonthConfigSnapshot::query()->updateOrCreate(
            [
                'shop_id' => $shopId,
                'month' => $month,
            ],
            [
                'status' => $status,
                'source' => $source,
                'config_data' => $configData,
                'captured_by' => $userId,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Preserve prior months before any configuration mutation occurs.
     * Ensures that changes today will NEVER rewrite or affect prior months.
     */
    public function preservePriorMonthsBeforeMutation(int $shopId): void
    {
        $currentMonth = Carbon::now()->format('Y-m');

        // Check recent past months with transactions or activity
        $pastTransactionMonths = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->selectRaw('DISTINCT SUBSTRING(business_date, 1, 7) as ym')
            ->whereRaw('SUBSTRING(business_date, 1, 7) < ?', [$currentMonth])
            ->pluck('ym')
            ->all();

        // Also always ensure immediately preceding month
        $previousMonth = Carbon::now()->subMonth()->format('Y-m');
        if (! in_array($previousMonth, $pastTransactionMonths, true)) {
            $pastTransactionMonths[] = $previousMonth;
        }

        foreach ($pastTransactionMonths as $month) {
            if (! is_string($month) || strlen($month) !== 7) {
                continue;
            }

            $hasSnapshot = ShopCashbookMonthConfigSnapshot::query()
                ->where('shop_id', $shopId)
                ->where('month', $month)
                ->exists();

            if (! $hasSnapshot) {
                $this->captureSnapshot($shopId, $month, 'pre_mutation');
            }
        }
    }

    /**
     * Build full config data array from live state for a shop.
     *
     * @return array<string, mixed>
     */
    public function buildConfigDataFromLive(int $shopId): array
    {
        $headers = ShopLedgerHeaderGroup::query()
            ->with('allowedProducts')
            ->where('shop_id', $shopId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (ShopLedgerHeaderGroup $h): array => [
                'id' => $h->id,
                'name' => $h->name,
                'type' => $h->type,
                'cash_flow_mode' => $h->cash_flow_mode,
                'company_account_id' => $h->company_account_id,
                'from_balance' => $h->from_balance,
                'to_balance' => $h->to_balance,
                'display_order' => $h->display_order,
                'enabled' => (bool) $h->enabled,
                'note_enabled' => (bool) $h->note_enabled,
                'product_tagging_enabled' => (bool) $h->product_tagging_enabled,
                'show_both_sides' => (bool) $h->show_both_sides,
                'product_ids' => $h->allowedProducts->pluck('id')->all(),
            ])
            ->all();

        $settings = ShopLedgerEntrySetting::query()
            ->with(['entryType', 'categoryVendorMappings'])
            ->where('shop_id', $shopId)
            ->get()
            ->map(fn (ShopLedgerEntrySetting $s): array => [
                'id' => $s->id,
                'entry_type_id' => $s->entry_type_id,
                'entry_type_code' => $s->entryType?->code,
                'entry_type_name' => $s->entryType?->name,
                'entry_type_category' => $s->entryType?->category,
                'entry_type_display_order' => $s->entryType?->display_order,
                'display_name' => $s->display_name,
                'header_group_id' => $s->header_group_id,
                'header_display_order' => $s->header_display_order,
                'company_account_id' => $s->company_account_id,
                'enabled' => (bool) $s->enabled,
                'show_in_summary' => (bool) $s->show_in_summary,
                'note_enabled' => (bool) $s->note_enabled,
                'is_readonly' => (bool) $s->is_readonly,
                'edit_policy' => $s->edit_policy,
                'default_funding_source' => $s->default_funding_source,
                'allowed_funding_sources' => $s->allowed_funding_sources,
                'include_in_sales' => (bool) $s->include_in_sales,
                'include_in_income' => (bool) $s->include_in_income,
                'include_in_expense' => (bool) $s->include_in_expense,
                'include_in_pl' => (bool) $s->include_in_pl,
                'include_in_payable' => (bool) $s->include_in_payable,
                'payable_direction' => $s->payable_direction,
                'settlement_behavior' => $s->settlement_behavior,
                'petty_behavior' => $s->petty_behavior,
                'company_pending_behavior' => $s->company_pending_behavior,
                'generates_secondary_entry' => (bool) $s->generates_secondary_entry,
                'secondary_entry_type_id' => $s->secondary_entry_type_id,
                'secondary_amount_mode' => $s->secondary_amount_mode,
                'secondary_amount_value' => $s->secondary_amount_value,
                'display_order' => $s->display_order,
                'is_vendor_purchase' => (bool) $s->is_vendor_purchase,
                'vendor_purchase_payment_type' => $s->vendor_purchase_payment_type,
                'mirror_to_cashbook' => (bool) ($s->mirror_to_cashbook ?? true),
                'vendor_access_mode' => $s->vendor_access_mode ?? 'linked_create',
                'vendor_settlement_relation_id' => $s->vendor_settlement_relation_id,
                'defined_shop_supplier_ids' => $s->categoryVendorMappings->pluck('shop_supplier_id')->all(),
            ])
            ->all();

        $relations = ShopCashbookRelation::query()
            ->with('items')
            ->where('shop_id', $shopId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (ShopCashbookRelation $r): array => [
                'id' => $r->id,
                'name' => $r->name,
                'type' => $r->type,
                'source_type' => $r->source_type,
                'target_type' => $r->target_type,
                'display_order' => $r->display_order,
                'enabled' => (bool) $r->enabled,
                'is_active' => (bool) ($r->is_active ?? true),
                'is_company_payable' => (bool) $r->is_company_payable,
                'is_net_balance' => (bool) $r->is_net_balance,
                'is_default_payment_payable' => (bool) $r->is_default_payment_payable,
                'is_default_payment_paid' => (bool) $r->is_default_payment_paid,
                'items' => $r->items->map(fn (ShopCashbookRelationItem $i): array => [
                    'id' => $i->id,
                    'shop_ledger_entry_setting_id' => $i->shop_ledger_entry_setting_id,
                    'header_group_id' => $i->header_group_id,
                    'source_settlement_id' => $i->source_settlement_id,
                    'role' => $i->role,
                    'display_order' => $i->display_order,
                ])->all(),
            ])
            ->all();

        $collectionGroup = ShopLedgerCollectionGroup::query()
            ->where('shop_id', $shopId)
            ->where('code', 'collection')
            ->with('entryTypes')
            ->first();

        $collectionGroupData = $collectionGroup ? [
            'id' => $collectionGroup->id,
            'name' => $collectionGroup->name,
            'code' => $collectionGroup->code,
            'entry_types' => $collectionGroup->entryTypes->map(fn (ShopLedgerCollectionGroupEntryType $et): array => [
                'id' => $et->id,
                'entry_type_id' => $et->entry_type_id,
                'role' => $et->role,
                'display_order' => $et->display_order,
            ])->all(),
        ] : null;

        $bankAdjustmentRules = ShopBankSettlementAdjustmentRule::query()
            ->where('shop_id', $shopId)
            ->get()
            ->map(fn (ShopBankSettlementAdjustmentRule $rule): array => [
                'id' => $rule->id,
                'entry_type_id' => $rule->entry_type_id,
                'rule_type' => $rule->rule_type,
                'action' => $rule->action,
                'amount_mode' => $rule->amount_mode,
                'amount_value' => $rule->amount_value,
            ])
            ->all();

        return [
            'headers' => $headers,
            'settings' => $settings,
            'relations' => $relations,
            'collection_group' => $collectionGroupData,
            'bank_adjustment_rules' => $bankAdjustmentRules,
        ];
    }

    /**
     * Reconstruct configuration for a legacy month before snapshot feature existed.
     */
    public function reconstructLegacyMonthConfig(int $shopId, string $month): ShopCashbookMonthConfigSnapshot
    {
        $configData = $this->buildConfigDataFromLive($shopId);

        $start = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        // Check if there are historical invoices from this month with original_header_group_id
        $historicalHeaderIds = PurchaseInvoice::query()
            ->where('shop_id', $shopId)
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('original_business_date', [$start, $end])
                    ->orWhere(function ($sq) use ($start, $end): void {
                        $sq->whereNull('original_business_date')
                            ->whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59']);
                    });
            })
            ->whereNotNull('original_header_group_id')
            ->pluck('original_header_group_id')
            ->filter()
            ->unique()
            ->all();

        // If historical invoices used a specific original header, preserve it for vendor purchase settings in that month
        if (! empty($historicalHeaderIds)) {
            $lastHistoricalHeaderId = end($historicalHeaderIds);
            foreach ($configData['settings'] as &$setting) {
                if (! empty($setting['is_vendor_purchase'])) {
                    $setting['header_group_id'] = $lastHistoricalHeaderId;
                }
            }
            unset($setting);
        }

        return ShopCashbookMonthConfigSnapshot::query()->create([
            'shop_id' => $shopId,
            'month' => $month,
            'status' => 'legacy_reconstructed',
            'source' => 'legacy_reconstruction',
            'config_data' => $configData,
            'notes' => 'Reconstructed historical month configuration from legacy state and invoices.',
        ]);
    }

    /**
     * Hydrate Eloquent instances and DTO collections from a snapshot.
     */
    private function hydrateFromSnapshot(int $shopId, ShopCashbookMonthConfigSnapshot $snapshot, string $monthLabel): array
    {
        $allEntryTypes = LedgerEntryType::where('active', true)->orderBy('display_order')->get()->keyBy('id');
        $allCompanyAccounts = CompanyAccount::all()->keyBy('id');
        $allSuppliers = ShopSupplier::with('supplier')->where('shop_id', $shopId)->get()->keyBy('id');

        $rawHeaders = $snapshot->getHeaders();
        $rawSettings = $snapshot->getSettings();
        $rawRelations = $snapshot->getRelations();
        $rawCollectionGroup = $snapshot->getCollectionGroup();
        $rawBankAdjustmentRules = $snapshot->getBankAdjustmentRules();

        // Hydrate Headers
        $headersList = [];
        foreach ($rawHeaders as $rh) {
            $h = new ShopLedgerHeaderGroup;
            $h->forceFill([
                'id' => $rh['id'],
                'shop_id' => $shopId,
                'name' => $rh['name'],
                'type' => $rh['type'],
                'cash_flow_mode' => $rh['cash_flow_mode'] ?? 'entry_decides',
                'company_account_id' => $rh['company_account_id'] ?? null,
                'from_balance' => $rh['from_balance'] ?? null,
                'to_balance' => $rh['to_balance'] ?? null,
                'display_order' => $rh['display_order'] ?? 0,
                'enabled' => $rh['enabled'] ?? true,
                'note_enabled' => $rh['note_enabled'] ?? false,
                'product_tagging_enabled' => $rh['product_tagging_enabled'] ?? false,
                'show_both_sides' => $rh['show_both_sides'] ?? false,
            ]);
            $h->exists = true;

            if (! empty($rh['product_ids'])) {
                $products = Product::query()->whereIn('id', $rh['product_ids'])->get();
                $h->setRelation('allowedProducts', $products);
            } else {
                $h->setRelation('allowedProducts', new EloquentCollection);
            }

            if (! empty($rh['company_account_id']) && isset($allCompanyAccounts[$rh['company_account_id']])) {
                $h->setRelation('companyAccount', $allCompanyAccounts[$rh['company_account_id']]);
            }

            $headersList[] = $h;
        }

        $headers = new EloquentCollection($headersList);
        $headersById = $headers->keyBy('id');

        // Hydrate Relations
        $relationsList = [];
        foreach ($rawRelations as $rr) {
            $r = new ShopCashbookRelation;
            $r->forceFill([
                'id' => $rr['id'],
                'shop_id' => $shopId,
                'name' => $rr['name'],
                'type' => $rr['type'] ?? 'payment',
                'source_type' => $rr['source_type'] ?? 'none',
                'target_type' => $rr['target_type'] ?? 'none',
                'display_order' => $rr['display_order'] ?? 0,
                'enabled' => $rr['enabled'] ?? true,
                'is_company_payable' => $rr['is_company_payable'] ?? false,
                'is_net_balance' => $rr['is_net_balance'] ?? false,
                'is_default_payment_payable' => $rr['is_default_payment_payable'] ?? false,
                'is_default_payment_paid' => $rr['is_default_payment_paid'] ?? false,
            ]);
            $r->exists = true;
            $relationsList[] = $r;
        }
        $relations = new EloquentCollection($relationsList);
        $relationsById = $relations->keyBy('id');

        // Hydrate Settings
        $settingsList = [];
        foreach ($rawSettings as $rs) {
            $s = new ShopLedgerEntrySetting;
            $s->forceFill([
                'id' => $rs['id'],
                'shop_id' => $shopId,
                'entry_type_id' => $rs['entry_type_id'],
                'display_name' => $rs['display_name'] ?? null,
                'header_group_id' => $rs['header_group_id'] ?? null,
                'header_display_order' => $rs['header_display_order'] ?? 0,
                'company_account_id' => $rs['company_account_id'] ?? null,
                'enabled' => $rs['enabled'] ?? true,
                'show_in_summary' => $rs['show_in_summary'] ?? true,
                'note_enabled' => $rs['note_enabled'] ?? false,
                'is_readonly' => $rs['is_readonly'] ?? false,
                'edit_policy' => $rs['edit_policy'] ?? 'past_days_allowed',
                'default_funding_source' => $rs['default_funding_source'] ?? 'sales',
                'allowed_funding_sources' => $rs['allowed_funding_sources'] ?? null,
                'include_in_sales' => $rs['include_in_sales'] ?? false,
                'include_in_income' => $rs['include_in_income'] ?? false,
                'include_in_expense' => $rs['include_in_expense'] ?? false,
                'include_in_pl' => $rs['include_in_pl'] ?? false,
                'include_in_payable' => $rs['include_in_payable'] ?? false,
                'payable_direction' => $rs['payable_direction'] ?? null,
                'settlement_behavior' => $rs['settlement_behavior'] ?? 'none',
                'petty_behavior' => $rs['petty_behavior'] ?? 'none',
                'company_pending_behavior' => $rs['company_pending_behavior'] ?? 'none',
                'generates_secondary_entry' => $rs['generates_secondary_entry'] ?? false,
                'secondary_entry_type_id' => $rs['secondary_entry_type_id'] ?? null,
                'secondary_amount_mode' => $rs['secondary_amount_mode'] ?? 'same_amount',
                'secondary_amount_value' => $rs['secondary_amount_value'] ?? null,
                'display_order' => $rs['display_order'] ?? 0,
                'is_vendor_purchase' => $rs['is_vendor_purchase'] ?? false,
                'vendor_purchase_payment_type' => $rs['vendor_purchase_payment_type'] ?? null,
                'mirror_to_cashbook' => $rs['mirror_to_cashbook'] ?? true,
                'vendor_access_mode' => $rs['vendor_access_mode'] ?? 'linked_create',
                'vendor_settlement_relation_id' => $rs['vendor_settlement_relation_id'] ?? null,
            ]);
            $s->exists = true;

            // Set relations on setting
            if (isset($allEntryTypes[$rs['entry_type_id']])) {
                $s->setRelation('entryType', $allEntryTypes[$rs['entry_type_id']]);
            }

            if (! empty($rs['header_group_id']) && isset($headersById[$rs['header_group_id']])) {
                $s->setRelation('headerGroup', $headersById[$rs['header_group_id']]);
            }

            if (! empty($rs['company_account_id']) && isset($allCompanyAccounts[$rs['company_account_id']])) {
                $s->setRelation('companyAccount', $allCompanyAccounts[$rs['company_account_id']]);
            }

            if (! empty($rs['vendor_settlement_relation_id']) && isset($relationsById[$rs['vendor_settlement_relation_id']])) {
                $s->setRelation('vendorSettlementRelation', $relationsById[$rs['vendor_settlement_relation_id']]);
            }

            $definedSuppliers = new EloquentCollection;
            $categoryMappings = new EloquentCollection;
            if (! empty($rs['defined_shop_supplier_ids'])) {
                foreach ($rs['defined_shop_supplier_ids'] as $supId) {
                    if (isset($allSuppliers[$supId])) {
                        $definedSuppliers->push($allSuppliers[$supId]);
                    }
                    $cvm = new CategoryVendorMapping;
                    $cvm->forceFill([
                        'shop_ledger_entry_setting_id' => $s->id,
                        'shop_supplier_id' => $supId,
                    ]);
                    $cvm->exists = true;
                    if (isset($allSuppliers[$supId])) {
                        $cvm->setRelation('shopSupplier', $allSuppliers[$supId]);
                    }
                    $categoryMappings->push($cvm);
                }
            }
            $s->setRelation('definedShopSuppliers', $definedSuppliers);
            $s->setRelation('categoryVendorMappings', $categoryMappings);

            $settingsList[] = $s;
        }

        $settings = (new EloquentCollection($settingsList))
            ->sortBy(fn (ShopLedgerEntrySetting $s): int => (int) ($s->entryType?->display_order ?? $s->display_order))
            ->values();

        $settingsByCategory = $settings->groupBy(fn (ShopLedgerEntrySetting $s): string => (string) ($s->entryType?->category ?? 'other'));

        // Hydrate relation items
        $settingsById = $settings->keyBy('id');
        foreach ($rawRelations as $rr) {
            $relModel = $relationsById[$rr['id']] ?? null;
            if (! $relModel) {
                continue;
            }

            $itemsList = [];
            foreach (($rr['items'] ?? []) as $ri) {
                $item = new ShopCashbookRelationItem;
                $item->forceFill([
                    'id' => $ri['id'],
                    'shop_cashbook_relation_id' => $rr['id'],
                    'shop_ledger_entry_setting_id' => $ri['shop_ledger_entry_setting_id'] ?? null,
                    'header_group_id' => $ri['header_group_id'] ?? null,
                    'source_settlement_id' => $ri['source_settlement_id'] ?? null,
                    'role' => $ri['role'] ?? 'entry',
                    'display_order' => $ri['display_order'] ?? 0,
                ]);
                $item->exists = true;

                if (! empty($ri['shop_ledger_entry_setting_id']) && isset($settingsById[$ri['shop_ledger_entry_setting_id']])) {
                    $item->setRelation('setting', $settingsById[$ri['shop_ledger_entry_setting_id']]);
                }
                if (! empty($ri['header_group_id']) && isset($headersById[$ri['header_group_id']])) {
                    $item->setRelation('headerGroup', $headersById[$ri['header_group_id']]);
                }
                if (! empty($ri['source_settlement_id']) && isset($relationsById[$ri['source_settlement_id']])) {
                    $item->setRelation('sourceSettlement', $relationsById[$ri['source_settlement_id']]);
                }

                $itemsList[] = $item;
            }
            $relModel->setRelation('items', new EloquentCollection($itemsList));
        }

        // Hydrate collection group
        $collectionGroup = null;
        if ($rawCollectionGroup) {
            $cg = new ShopLedgerCollectionGroup;
            $cg->forceFill([
                'id' => $rawCollectionGroup['id'],
                'shop_id' => $shopId,
                'name' => $rawCollectionGroup['name'],
                'code' => $rawCollectionGroup['code'],
            ]);
            $cg->exists = true;

            $entryTypesList = [];
            foreach (($rawCollectionGroup['entry_types'] ?? []) as $cget) {
                $cgEntryType = new ShopLedgerCollectionGroupEntryType;
                $cgEntryType->forceFill([
                    'id' => $cget['id'],
                    'collection_group_id' => $rawCollectionGroup['id'],
                    'entry_type_id' => $cget['entry_type_id'],
                    'role' => $cget['role'],
                    'display_order' => $cget['display_order'] ?? 0,
                ]);
                $cgEntryType->exists = true;
                if (isset($allEntryTypes[$cget['entry_type_id']])) {
                    $cgEntryType->setRelation('entryType', $allEntryTypes[$cget['entry_type_id']]);
                }
                $entryTypesList[] = $cgEntryType;
            }
            $cg->setRelation('entryTypes', new EloquentCollection($entryTypesList));
            $collectionGroup = $cg;
        }

        // Hydrate bank adjustment rules
        $rulesList = [];
        foreach ($rawBankAdjustmentRules as $rbar) {
            $bar = new ShopBankSettlementAdjustmentRule;
            $bar->forceFill([
                'id' => $rbar['id'],
                'shop_id' => $shopId,
                'entry_type_id' => $rbar['entry_type_id'],
                'rule_type' => $rbar['rule_type'],
                'action' => $rbar['action'],
                'amount_mode' => $rbar['amount_mode'],
                'amount_value' => $rbar['amount_value'],
            ]);
            $bar->exists = true;
            $rulesList[] = $bar;
        }
        $bankAdjustmentRules = (new EloquentCollection($rulesList))->groupBy('entry_type_id');

        $shopSuppliers = $allSuppliers->values()->sortBy(fn (ShopSupplier $ss): string => strtolower((string) ($ss->supplier?->name ?? '')))->values();

        return [
            'month' => $snapshot->month,
            'month_label' => $monthLabel,
            'is_current' => false,
            'is_historical' => true,
            'is_legacy' => $snapshot->isLegacy(),
            'is_read_only' => true,
            'snapshot' => $snapshot,
            'headers' => $headers,
            'settings' => $settings,
            'settings_by_category' => $settingsByCategory,
            'relations' => $relations,
            'collection_group' => $collectionGroup,
            'bank_adjustment_rules' => $bankAdjustmentRules,
            'all_entry_types' => $allEntryTypes->values(),
            'shop_suppliers' => $shopSuppliers,
        ];
    }

    /**
     * Get list of available months for selection in Admin Category Settings.
     *
     * @return array<int, array{
     *     value: string,
     *     label: string,
     *     is_current: bool,
     *     is_snapshot: bool,
     *     is_legacy: bool,
     * }>
     */
    public function getAvailableMonthsForShop(int $shopId): array
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $currentYm = $currentMonth->format('Y-m');

        // Find earliest month with data or snapshot
        $earliestTx = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->min('business_date');

        $earliestSnap = ShopCashbookMonthConfigSnapshot::query()
            ->where('shop_id', $shopId)
            ->min('month');

        $earliestDate = $currentMonth->copy()->subMonths(11); // default minimum 12 months range

        if ($earliestTx) {
            $txDate = Carbon::parse($earliestTx)->startOfMonth();
            if ($txDate->lt($earliestDate)) {
                $earliestDate = $txDate;
            }
        }

        if ($earliestSnap) {
            $snapDate = Carbon::createFromFormat('Y-m', $earliestSnap)->startOfMonth();
            if ($snapDate->lt($earliestDate)) {
                $earliestDate = $snapDate;
            }
        }

        $existingSnapshots = ShopCashbookMonthConfigSnapshot::query()
            ->where('shop_id', $shopId)
            ->get()
            ->keyBy('month');

        $months = [];
        $cursor = $currentMonth->copy();

        while ($cursor->gte($earliestDate)) {
            $ym = $cursor->format('Y-m');
            $isCur = ($ym === $currentYm);
            $snap = $existingSnapshots->get($ym);

            $label = $cursor->translatedFormat('F Y');
            if ($isCur) {
                $label .= ' (Current)';
            }

            $months[] = [
                'value' => $ym,
                'label' => $label,
                'is_current' => $isCur,
                'is_snapshot' => ($snap !== null),
                'is_legacy' => ($snap?->isLegacy() ?? false),
            ];

            $cursor->subMonth();
        }

        return $months;
    }
}
