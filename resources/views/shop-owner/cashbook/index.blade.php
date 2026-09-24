@extends('shop-owner.layouts.app')

@section('title', 'Daily Cashbook — '.$shop->name)
@section('page_title', 'Daily Cashbook')
@section('page_description', 'Record daily collections, store expenses, settlements, and closing cash balance.')
@section('page_actions')
    <a href="{{ route('shop-owner.cashbook.vendor-purchases', ['date' => $selectedDate->format('Y-m-d')]) }}"
       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-800 shadow-xs hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 transition cursor-pointer">
        <i data-lucide="shopping-bag" class="h-3.5 w-3.5 text-emerald-600"></i>
        <span>Vendor Purchases</span>
    </a>
@endsection

@section('content')
@php
    $breadcrumbs = [['label' => 'Cashbook']];
    $relationList = $relations ?? collect();
    $accountsList = $companyAccounts ?? collect();
    $headerGroupList = $headerGroups ?? collect();

    // Group settings by header
    $settingsByHeader = $settings->groupBy(fn ($s) => (int) ($s->header_group_id ?? 0));

    // Use ShopCashbookUiLayoutService to resolve visual UI layout (display names, custom ordering, sub-headers)
    $uiLayoutService = app(\App\Services\Cashbook\ShopCashbookUiLayoutService::class);
    $resolvedLayout = $uiLayoutService->getResolvedLayout((int) $shop->id, $headerGroupList, $settings);
    $ownerHeaderSections = collect($resolvedLayout['headers']);

    // Priority Sort: Income headers first, then Expense headers (include show_both_sides in both)
    $incomeHeaders = $ownerHeaderSections->filter(fn($h) => $h['type'] === 'income' || ! empty($h['show_both_sides']))->values();
    $expenseHeaders = $ownerHeaderSections->filter(fn($h) => $h['type'] === 'expense' || ! empty($h['show_both_sides']))->values();

    // Serialize metadata for JS calculation engine
    $settingsJson = $settings->map(function ($s) use ($vendorPurchaseSummaries) {
        $cat = strtolower((string) ($s->entryType?->category ?? ''));
        $isSalesDeduction = $s->include_in_sales && ($s->payable_direction === 'minus' || $cat === 'transfer');
        $isIncome = ($cat === 'income' || $s->include_in_sales || $s->include_in_income) && ! $isSalesDeduction;
        $isExpense = ($cat === 'expense' || $s->include_in_expense) && ! $isSalesDeduction;
        $code = strtolower((string) ($s->entryType?->code ?? ''));
        $name = (string) ($s->entryType?->name ?? '');
        $isCashPurchase = $code === 'cash_purchase';

        $resolver = app(\App\Services\Cashbook\CashFlowResolutionService::class);
        $fundingSource = $resolver->resolveFundingSource($s);
        $companyAccountId = $resolver->resolveCompanyAccountId($s);
        $noteEnabled = $resolver->resolveNoteEnabled($s);
        $requiresNote = (bool) ($s->requires_note ?? false);
        $showNoteField = $noteEnabled || $requiresNote;

        $compAccName = null;
        if ($companyAccountId) {
            $acc = \App\Models\Cashbook\CompanyAccount::find($companyAccountId);
            $compAccName = $acc?->name;
        }

        return [
            'id' => (int) $s->id,
            'header_id' => (string) ($s->header_group_id ?? 'unassigned_' . ($isIncome ? 'income' : 'expense')),
            'name' => $s->displayName(),
            'code' => $s->entryType?->code ?? '',
            'category' => $isSalesDeduction ? 'transfer' : ($isIncome ? 'income' : 'expense'),
            'is_income' => $isIncome,
            'is_expense' => $isExpense,
            'is_sales_deduction' => $isSalesDeduction,
            'payable_direction' => $s->payable_direction ?? ($isSalesDeduction ? 'minus' : ($isIncome ? 'plus' : 'minus')),
            'is_cash_purchase' => $isCashPurchase,
            'edit_policy' => (string) ($s->edit_policy ?? 'past_days_allowed'),
            'is_today_only' => $s->isTodayOnly(),
            'requires_note' => $requiresNote,
            'note_enabled' => $noteEnabled,
            'show_note_field' => $showNoteField,
            'show_in_summary' => (bool) ($s->show_in_summary ?? true),
            'company_account_id' => $companyAccountId,
            'company_account_name' => $compAccName,
            'funding_source' => $fundingSource,
            'destination_label' => $resolver->resolveDestinationLabel($s),
            'is_readonly' => (bool) ($s->is_readonly || in_array($code, ['salary', 'staff_advance', 'advance'], true)),
            'is_vendor_purchase' => (bool) ($s->is_vendor_purchase ?? false),
            'vendor_purchase_payment_type' => (string) ($s->vendor_purchase_payment_type ?? ''),
            'mirror_to_cashbook' => (bool) ($s->mirror_to_cashbook ?? true),
            'vendor_purchase_summary' => ($vendorPurchaseSummaries ?? [])[$s->id] ?? [
                'setting_id' => (int) $s->id,
                'name' => $s->displayName(),
                'total_amount' => 0.0,
                'cash_amount' => 0.0,
                'credit_amount' => 0.0,
                'count' => 0,
                'payment_type' => (string) ($s->vendor_purchase_payment_type ?? ''),
            ],
            'vendor_access_mode' => (string) ($s->vendor_access_mode ?? 'linked_create'),
            'vendor_settlement_relation_id' => $s->vendor_settlement_relation_id ? (int) $s->vendor_settlement_relation_id : null,
            'vendor_settlement_name' => $s->vendorSettlementRelation?->name,
            'defined_supplier_ids' => $s->definedShopSuppliers->pluck('supplier_id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    })->values()->all();

    $accountsJson = $accountsList->map(function ($a) {
        return [
            'id' => (int) $a->id,
            'name' => $a->name,
            'bank_name' => $a->bank_name,
            'account_number' => $a->account_number,
        ];
    })->values()->all();

    $relationJson = $relationList->map(function ($r) use ($settingsByHeader, $headerGroupList) {
        return [
            'id' => (int) $r->id,
            'public_uuid' => (string) $r->public_uuid,
            'name' => (string) $r->name,
            'kind' => $r->relation_type,
            'enabled' => (bool) $r->enabled,
            'is_company_payable' => (bool) $r->is_company_payable,
            'is_net_balance' => (bool) $r->is_net_balance,
            'display_order' => (int) ($r->display_order ?? 0),
            'settlement_source' => $r->settlement_source ?? 'shop_balance',
            'eligibility_rule' => $r->eligibility_rule ?? 'previous_day_balance',
            'items' => $r->items->map(function ($item) use ($settingsByHeader, $headerGroupList) {
                $settingId = $item->shop_ledger_entry_setting_id;
                $headerGroupId = $item->header_group_id;
                $sourceSettlementId = $item->source_settlement_id;
                $headerSettingIds = [];
                if ($headerGroupId) {
                    $hg = $headerGroupList->firstWhere('id', $headerGroupId);
                    $hSettings = $settingsByHeader->get((int) $headerGroupId, collect());
                    $headerSettingIds = $hSettings->pluck('id')->map(fn ($id) => (int) $id)->all();
                }

                $itemName = 'Item';
                if ($sourceSettlementId) {
                    $targetRel = $item->sourceSettlement;
                    $itemName = 'Settlement: '.($targetRel?->name ?? ('#'.$sourceSettlementId));
                } elseif ($headerGroupId) {
                    $hg = $item->headerGroup ?? $headerGroupList->firstWhere('id', $headerGroupId);
                    $itemName = 'Header: '.($hg?->name ?? ('#'.$headerGroupId));
                } elseif ($settingId) {
                    $itemName = $item->setting?->displayName() ?? ('Category #'.$settingId);
                }

                return [
                    'setting_id' => $settingId ? (int) $settingId : null,
                    'header_group_id' => $headerGroupId ? (int) $headerGroupId : null,
                    'header_setting_ids' => $headerSettingIds,
                    'header_mode' => $item->header_mode ?? 'all_categories',
                    'source_settlement_id' => $sourceSettlementId ? (int) $sourceSettlementId : null,
                    'role' => strtolower((string) ($item->role ?? 'add')),
                    'name' => $itemName,
                ];
            })->values()->all(),
        ];
    })->values()->all();

    $headersJson = $ownerHeaderSections->map(function ($hs) {
        $subHeadersJson = collect($hs['sub_headers'] ?? [])->map(function ($sub) {
            return [
                'id' => (string) $sub['id'],
                'source_id' => $sub['source_id'] ?? null,
                'name' => (string) ($sub['display_name'] ?? $sub['original_name'] ?? ''),
                'display_name' => (string) ($sub['display_name'] ?? $sub['original_name'] ?? ''),
                'original_name' => (string) ($sub['original_name'] ?? ''),
                'type' => $sub['type'] ?? 'income',
                'product_tagging_enabled' => (bool) ($sub['product_tagging_enabled'] ?? false),
                'setting_ids' => collect($sub['settings'])->pluck('id')->map(fn($id) => (int)$id)->all(),
                'products' => $sub['products'] ?? [],
                'product_ids' => collect($sub['products'] ?? [])->pluck('id')->map(fn($id) => (int)$id)->all(),
            ];
        })->values()->all();

        return [
            'id' => (string) $hs['id'],
            'source_id' => $hs['source_id'] ?? null,
            'name' => (string) ($hs['display_name'] ?? $hs['original_name'] ?? ''),
            'display_name' => (string) ($hs['display_name'] ?? $hs['original_name'] ?? ''),
            'original_name' => (string) ($hs['original_name'] ?? ''),
            'type' => $hs['type'],
            'product_tagging_enabled' => (bool) ($hs['product_tagging_enabled'] ?? false),
            'show_both_sides' => (bool) ($hs['show_both_sides'] ?? false),
            'sub_headers' => $subHeadersJson,
            'setting_ids' => collect($hs['settings'])->pluck('id')->map(fn($id) => (int)$id)->all(),
            'products' => $hs['products'] ?? [],
            'product_ids' => collect($hs['products'] ?? [])->pluck('id')->map(fn($id) => (int)$id)->all(),
        ];
    })->values()->all();

    // Map today's existing transactions to initial JS state.
    // Salary-owned transactions (reference_type = ShopStaffPayment) are rendered
    // in the dedicated SALARY section partial and must not be double-counted here.
    $salaryExcludedTxIds = $salarySectionData->excludedTxIds ?? [];
    $initialTxAmounts = [];
    $initialTxNotes = [];
    if (isset($todayTransactions) && $todayTransactions->isNotEmpty()) {
        foreach ($todayTransactions as $tx) {
            if ($tx->status === 'void' || $tx->status === \App\Enums\Cashbook\TransactionStatus::Void->value) {
                continue;
            }
            // Skip salary-owned transactions — they belong to the SALARY section.
            if (in_array($tx->id, $salaryExcludedTxIds, true)) {
                continue;
            }
            if ($tx->entry_type_id) {
                $setting = $settings->firstWhere('entry_type_id', $tx->entry_type_id);
                if ($setting) {
                    $initialTxAmounts[$setting->id] = ($initialTxAmounts[$setting->id] ?? 0.0) + (float) $tx->amount;
                    if ($tx->notes) {
                        $initialTxNotes[$setting->id] = isset($initialTxNotes[$setting->id])
                            ? $initialTxNotes[$setting->id] . '; ' . $tx->notes
                            : $tx->notes;
                    }
                }
            }
        }
    }

    // Map existing product rows to initial JS state
    $initialProductRows = [];
    $productEntriesList = $productEntries ?? collect();
    foreach ($productEntriesList as $pe) {
        $hId = (string) $pe->header_group_id;
        $units = [];
        $baseUnit = strtolower(trim((string) ($pe->unit ?: $pe->product?->unit ?: 'unit')));
        $units[] = [
            'unit' => $baseUnit,
            'label' => strtoupper($baseUnit),
            'conversion_to_base' => 1.0,
            'is_base' => true,
        ];
        if ($pe->product) {
            foreach ($pe->product->orderUnits as $ou) {
                $ouUnit = strtolower(trim((string) $ou->unit));
                if ($ouUnit !== '' && ! collect($units)->contains('unit', $ouUnit)) {
                    $units[] = [
                        'unit' => $ouUnit,
                        'label' => $ou->label ?: strtoupper($ouUnit),
                        'conversion_to_base' => $ou->conversion_to_base !== null ? (float) $ou->conversion_to_base : 1.0,
                        'is_base' => (bool) $ou->is_base,
                    ];
                }
            }
        }
        $qty = (float) $pe->quantity;
        $amt = (float) $pe->amount;
        $avgPrice = ($qty > 0 && $amt > 0) ? round($amt / $qty, 2) : null;
        $initialProductRows[$hId][] = [
            'productId' => (int) $pe->product_id,
            'product_id' => (int) $pe->product_id,
            'productName' => (string) ($pe->product_name ?: ($pe->product?->name ?? '')),
            'product_name' => (string) ($pe->product_name ?: ($pe->product?->name ?? '')),
            'sku' => (string) ($pe->product_sku ?: ($pe->product?->sku ?? '')),
            'product_sku' => (string) ($pe->product_sku ?: ($pe->product?->sku ?? '')),
            'qty' => $qty > 0 ? $qty : '',
            'quantity' => $qty,
            'unit' => $pe->unit ?: $baseUnit,
            'units' => $units,
            'amount' => $amt,
            'avgPrice' => $avgPrice,
        ];
    }

    $isReportTab = ($activeTab ?? 'cashbook') === 'reports';
@endphp

<style>
    /* Hide global mobile bottom nav specifically on Cashbook page */
    #layout-mobile-nav {
        display: none !important;
    }
</style>

<div class="max-w-xl mx-auto pb-10 sm:pb-12 space-y-3 sm:space-y-4">

    {{-- MAIN CASHBOOK DASHBOARD VIEW --}}
    <div id="cashbook-dashboard-view" @class(['space-y-3 sm:space-y-4', 'hidden' => $isReportTab])>
        @include('shop-owner.cashbook.partials.header')
        @include('shop-owner.cashbook.partials.daily-overview')
        @include('shop-owner.cashbook.partials.header-bill-list')
        @include('shop-owner.cashbook.partials.vendor-purchase-section')
        @if($salarySectionData->hasAnyData())
            @include('shop-owner.cashbook.partials.salary-section', ['salarySectionData' => $salarySectionData])
        @endif
        @include('shop-owner.cashbook.partials.position-summary')
    </div>

    {{-- DETAILED REPORT VIEW --}}
    @include('shop-owner.cashbook.partials.report-view')

</div>

{{-- MODALS & DRAWERS --}}
@include('shop-owner.cashbook.partials.modals.in-header')
@include('shop-owner.cashbook.partials.modals.out-header')
@include('shop-owner.cashbook.partials.modals.header-entry')
@include('shop-owner.cashbook.partials.modals.product-search')

@push('scripts')
    @include('shop-owner.cashbook.partials.scripts')
@endpush
@endsection
