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

    // Priority Sort: Income headers first, then Expense headers
    $incomeHeaders = $ownerHeaderSections->filter(fn($h) => $h['type'] === 'income')->sortBy('display_order')->values();
    $expenseHeaders = $ownerHeaderSections->filter(fn($h) => $h['type'] === 'expense')->sortBy('display_order')->values();

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

    $relationJson = $relationList->map(function ($r) {
        return [
            'id' => (int) $r->id,
            'name' => $r->name,
            'settlement_source' => $r->settlement_source ?? 'shop_balance',
            'eligibility_rule' => $r->eligibility_rule ?? 'previous_day_balance',
            'enabled' => (bool) $r->enabled,
            'items' => $r->items->map(function ($i) {
                return [
                    'setting_id' => (int) $i->shop_ledger_entry_setting_id,
                    'role' => strtolower((string) ($i->role ?? 'add')),
                    'name' => $i->setting?->displayName() ?? 'Unknown',
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
                    $initialTxAmounts[$setting->id] = (float) $tx->amount;
                    if ($tx->notes) {
                        $initialTxNotes[$setting->id] = $tx->notes;
                    }
                }
            }
        }
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
