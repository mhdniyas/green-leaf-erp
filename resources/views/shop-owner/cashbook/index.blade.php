@extends('shop-owner.layouts.app')

@section('title', 'Daily Cashbook — '.$shop->name)
@section('page_title', 'Daily Cashbook')
@section('page_description', 'Record daily collections, store expenses, settlements, and closing cash balance.')

@section('content')
@php
    $breadcrumbs = [['label' => 'Cashbook']];
    $relationList = $relations ?? collect();
    $accountsList = $companyAccounts ?? collect();
    $headerGroupList = $headerGroups ?? collect();

    // Sort settings by header_display_order or display_order
    $sortedSettings = $settings->sortBy(fn ($s) => (int) ($s->header_display_order ?? $s->entryType?->display_order ?? $s->display_order))->values();

    // Group settings by header_group_id
    $settingsByHeader = $sortedSettings->groupBy(fn ($s) => (int) ($s->header_group_id ?? 0));

    $ownerHeaderSections = collect();

    // 1. Process explicit saved headers in display_order
    foreach ($headerGroupList->sortBy('display_order') as $hg) {
        $hgId = (int) $hg->id;
        $headerSettings = $settingsByHeader->get($hgId, collect())->values();

        if ($headerSettings->isNotEmpty() || $hg->product_tagging_enabled) {
            $ownerHeaderSections->push([
                'id' => (string) $hgId,
                'name' => $hg->name,
                'type' => strtolower((string) ($hg->type ?? 'income')),
                'display_order' => (int) ($hg->display_order ?? 0),
                'product_tagging_enabled' => (bool) ($hg->product_tagging_enabled ?? false),
                'show_both_sides' => (bool) ($hg->show_both_sides ?? false),
                'settings' => $headerSettings,
            ]);
        }
    }

    // 2. Process unassigned settings (header_group_id == 0 or not matching any saved header)
    $assignedHeaderIds = $headerGroupList->pluck('id')->map(fn($id) => (int) $id)->all();
    $unassignedSettings = $sortedSettings->reject(function ($s) use ($assignedHeaderIds) {
        return $s->header_group_id && in_array((int) $s->header_group_id, $assignedHeaderIds, true);
    })->values();

    if ($unassignedSettings->isNotEmpty()) {
        $unassignedTransfers = $unassignedSettings->filter(function ($s) {
            $cat = strtolower((string) ($s->entryType?->category ?? ''));
            return $cat === 'transfer' || $cat === 'settlement' || (! $s->include_in_sales && ! $s->include_in_income && ! $s->include_in_expense);
        })->values();

        $unassignedIncome = $unassignedSettings->filter(function ($s) use ($unassignedTransfers) {
            if ($unassignedTransfers->contains('id', $s->id)) return false;
            $cat = strtolower((string) ($s->entryType?->category ?? ''));
            return $cat === 'income' || $s->include_in_sales || $s->include_in_income;
        })->values();

        $unassignedExpense = $unassignedSettings->reject(function ($s) use ($unassignedIncome, $unassignedTransfers) {
            return $unassignedIncome->contains('id', $s->id) || $unassignedTransfers->contains('id', $s->id);
        })->values();

        if ($unassignedIncome->isNotEmpty()) {
            $ownerHeaderSections->push([
                'id' => 'unassigned_income',
                'name' => 'OTHER INCOME',
                'type' => 'income',
                'display_order' => 9998,
                'product_tagging_enabled' => false,
                'show_both_sides' => false,
                'settings' => $unassignedIncome,
            ]);
        }

        if ($unassignedExpense->isNotEmpty()) {
            $ownerHeaderSections->push([
                'id' => 'unassigned_expense',
                'name' => 'OTHER EXPENSES',
                'type' => 'expense',
                'display_order' => 9999,
                'product_tagging_enabled' => false,
                'show_both_sides' => false,
                'settings' => $unassignedExpense,
            ]);
        }
    }

    // Fallback: If no headers produced, wrap all settings in default headers
    if ($ownerHeaderSections->isEmpty() && $sortedSettings->isNotEmpty()) {
        $incomeSet = $sortedSettings->filter(function ($s) {
            $cat = strtolower((string) ($s->entryType?->category ?? ''));
            return $cat === 'income' || $s->include_in_sales || $s->include_in_income;
        })->values();
        $expenseSet = $sortedSettings->reject(fn($s) => $incomeSet->contains('id', $s->id))->values();

        if ($incomeSet->isNotEmpty()) {
            $ownerHeaderSections->push([
                'id' => 'default_sales',
                'name' => 'SALES',
                'type' => 'income',
                'display_order' => 1,
                'product_tagging_enabled' => false,
                'show_both_sides' => false,
                'settings' => $incomeSet,
            ]);
        }
        if ($expenseSet->isNotEmpty()) {
            $ownerHeaderSections->push([
                'id' => 'default_expense',
                'name' => 'SHOP EXPENSES',
                'type' => 'expense',
                'display_order' => 2,
                'product_tagging_enabled' => false,
                'show_both_sides' => false,
                'settings' => $expenseSet,
            ]);
        }
    }

    // Priority Sort: Income headers first, then Expense headers (include show_both_sides in both)
    $incomeHeaders = $ownerHeaderSections->filter(fn($h) => $h['type'] === 'income' || ! empty($h['show_both_sides']))->sortBy('display_order')->values();
    $expenseHeaders = $ownerHeaderSections->filter(fn($h) => $h['type'] === 'expense' || ! empty($h['show_both_sides']))->sortBy('display_order')->values();

    // Serialize metadata for JS calculation engine
    $settingsJson = $settings->map(function ($s) {
        $cat = strtolower((string) ($s->entryType?->category ?? ''));
        $isSalesDeduction = $s->include_in_sales && ($s->payable_direction === 'minus' || $cat === 'transfer');
        $isIncome = ($cat === 'income' || $s->include_in_sales || $s->include_in_income) && ! $isSalesDeduction;
        $isExpense = ($cat === 'expense' || $s->include_in_expense) && ! $isSalesDeduction;
        $code = strtolower((string) ($s->entryType?->code ?? ''));
        $name = strtolower((string) ($s->entryType?->name ?? ''));
        $isCashPurchase = str_contains($code, 'cash_purchase') || str_contains($name, 'cash purchase');

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
            'requires_note' => $requiresNote,
            'note_enabled' => $noteEnabled,
            'show_note_field' => $showNoteField,
            'show_in_summary' => (bool) ($s->show_in_summary ?? true),
            'company_account_id' => $companyAccountId,
            'company_account_name' => $compAccName,
            'funding_source' => $fundingSource,
            'destination_label' => $resolver->resolveDestinationLabel($s),
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
        return [
            'id' => (string) $hs['id'],
            'name' => $hs['name'],
            'type' => $hs['type'],
            'product_tagging_enabled' => (bool) ($hs['product_tagging_enabled'] ?? false),
            'show_both_sides' => (bool) ($hs['show_both_sides'] ?? false),
            'setting_ids' => $hs['settings']->pluck('id')->map(fn($id) => (int)$id)->all(),
        ];
    })->values()->all();

    // Map today's existing transactions to initial JS state
    $initialTxAmounts = [];
    $initialTxNotes = [];
    if (isset($todayTransactions) && $todayTransactions->isNotEmpty()) {
        foreach ($todayTransactions as $tx) {
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
        @include('shop-owner.cashbook.partials.position-summary')
        @include('shop-owner.cashbook.partials.header-bill-list')
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
