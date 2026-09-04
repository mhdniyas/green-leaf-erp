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
        $isIncome = $cat === 'income' || $s->include_in_sales || $s->include_in_income;
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
            'category' => $isIncome ? 'income' : 'expense',
            'is_income' => $isIncome,
            'is_expense' => !$isIncome,
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
        
        {{-- Deliveries-Style Date Navigator --}}
        <div class="flex items-center justify-between gap-1.5 rounded-xl border border-slate-200 bg-white p-1.5 sm:p-2 shadow-xs">
            <a href="{{ route('shop-owner.cashbook.show', ['date' => $selectedDate->copy()->subDay()->toDateString()]) }}"
               class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 transition shrink-0"
               title="Previous Day">
                <i data-lucide="chevron-left" class="h-4 w-4"></i>
            </a>

            <form method="GET" action="{{ route('shop-owner.cashbook.show') }}" class="flex items-center gap-1.5 min-w-0">
                <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" onchange="this.form.submit()"
                       class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-center text-xs sm:text-sm font-black text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none cursor-pointer">
            </form>

            <div class="flex items-center gap-1.5 shrink-0">
                <a href="{{ route('shop-owner.cashbook.show', ['date' => today()->toDateString()]) }}"
                   @class([
                       'inline-flex h-9 items-center justify-center rounded-lg px-3 text-xs font-black transition',
                       'bg-emerald-600 text-white shadow-xs' => $selectedDate->isToday(),
                       'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' => ! $selectedDate->isToday(),
                   ])>
                    Today
                </a>
                <a href="{{ route('shop-owner.cashbook.show', ['date' => $selectedDate->copy()->addDay()->toDateString()]) }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 transition shrink-0"
                   title="Next Day">
                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                </a>
            </div>
        </div>

        {{-- TOP SUMMARY CARD (CASHBOOK POSITION) --}}
        <div class="rounded-xl sm:rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <span class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-500">
                    CASHBOOK POSITION
                </span>
                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Live
                </span>
            </div>

            {{-- 2 Primary Metrics Side-by-Side --}}
            <div class="grid grid-cols-2 gap-3 py-1">
                <div>
                    <div class="text-[10px] sm:text-[11px] font-bold text-slate-400">Shop Balance</div>
                    <div class="font-mono text-lg sm:text-2xl font-black text-slate-950 mt-0.5" id="kpi-shop-balance">
                        ₹0.00
                    </div>
                </div>
                <div>
                    <div class="text-[10px] sm:text-[11px] font-bold text-slate-400">Today's Net Activity</div>
                    <div class="font-mono text-lg sm:text-2xl font-black text-emerald-700 mt-0.5" id="kpi-today-net-activity">
                        ₹0.00
                    </div>
                </div>
            </div>

            {{-- Smaller Breakdown --}}
            <div class="pt-2.5 border-t border-slate-100 space-y-1.5 text-xs font-semibold">
                <div class="flex items-center justify-between text-slate-600">
                    <span class="text-slate-500">Cash on Hand</span>
                    <span class="font-mono font-bold text-slate-900" id="kpi-cash-held">₹0.00</span>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span class="text-slate-500">Direct to Company</span>
                    <span class="font-mono font-bold text-slate-900" id="kpi-reached-company">₹0.00</span>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span class="text-slate-500">Petty Balance</span>
                    <span class="font-mono font-bold text-slate-900" id="kpi-petty-closing">₹0.00</span>
                </div>
            </div>

            {{-- VIEW CASHBOOK REPORT BUTTON --}}
            <div class="pt-2 border-t border-slate-100">
                <button type="button" onclick="showReportView()"
                        class="w-full flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer">
                    <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-emerald-600"></i>
                    <span>View Cashbook Report</span>
                    <i data-lucide="arrow-right" class="h-3 w-3 text-slate-400 ml-auto"></i>
                </button>
            </div>
        </div>

        {{-- TODAY'S ACTIVITY ROW (Compact tappable day summary) --}}
        <div onclick="showReportView()" class="group rounded-xl border border-slate-200 bg-white p-3 shadow-xs hover:border-slate-300 hover:bg-slate-50/70 transition cursor-pointer flex items-center justify-between gap-3 select-none">
            <div class="min-w-0">
                <div class="text-xs sm:text-sm font-black text-slate-900 uppercase">
                    {{ $selectedDate->format('d M') }}
                </div>
                <div class="text-[10px] sm:text-[11px] font-bold text-slate-400" id="today-entry-count">
                    0 Entries
                </div>
            </div>

            <div class="flex items-center gap-4 sm:gap-6 text-right shrink-0">
                <div>
                    <div class="text-[9px] sm:text-[10px] font-black uppercase text-rose-500 tracking-wider">OUT</div>
                    <div class="font-mono text-xs sm:text-sm font-black text-rose-700" id="today-row-out">₹0.00</div>
                </div>
                <div>
                    <div class="text-[9px] sm:text-[10px] font-black uppercase text-emerald-600 tracking-wider">IN</div>
                    <div class="font-mono text-xs sm:text-sm font-black text-emerald-700" id="today-row-in">₹0.00</div>
                </div>
                <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400 group-hover:text-slate-600 transition shrink-0"></i>
            </div>
        </div>

        {{-- BILL-STYLE CASHBOOK HEADERS LIST --}}
        <div class="space-y-2">
            <div class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-400 px-1">
                Daily Entries & Headers
            </div>

            <div id="today-headers-summary-container" class="space-y-3">
                <!-- Dynamically rendered bill sections by JS -->
            </div>

            {{-- Empty State (if no headers configured) --}}
            <div id="today-empty-state" class="rounded-xl sm:rounded-2xl border border-dashed border-slate-200 bg-white p-6 text-center space-y-2 hidden">
                <div class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-400 border border-slate-100">
                    <i data-lucide="receipt" class="h-5 w-5"></i>
                </div>
                <h3 class="text-xs sm:text-sm font-black text-slate-800">No cashbook headers configured</h3>
                <p class="text-[11px] font-medium text-slate-400 max-w-xs mx-auto">
                    Configure cashbook headers in Shop Settings.
                </p>
            </div>
        </div>
    </div>

    {{-- DETAILED REPORT VIEW (Hidden by default or shown when tab=reports) --}}
    <div id="cashbook-report-view" @class(['space-y-3 sm:space-y-4', 'hidden' => !$isReportTab])>
        <div class="flex items-center justify-between">
            <button type="button" onclick="hideReportView()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                <span>Back to Cashbook</span>
            </button>

            <a href="{{ route('shop-owner.accounting.cashbook.pdf', ['date' => $selectedDate->toDateString()]) }}" target="_blank"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                <i data-lucide="download" class="h-3.5 w-3.5 text-emerald-600"></i>
                <span>PDF Report</span>
            </a>
        </div>

        <div class="rounded-xl sm:rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h2 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-900">CASHBOOK REPORT</h2>
                    <p class="text-[11px] font-bold text-slate-400">{{ $selectedDate->format('d M Y') }}</p>
                </div>
                <div class="text-right">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Net Activity</span>
                    <span class="font-mono text-xs sm:text-sm font-black text-slate-900" id="report-net-activity">₹0.00</span>
                </div>
            </div>

            {{-- Bill-Style Headers Breakdown Container --}}
            <div id="report-headers-breakdown" class="space-y-4">
                <!-- Dynamically rendered by renderReportBreakdown() -->
            </div>

            {{-- Balance Movements Section (Only rendered when active) --}}
            <div id="report-relations-container" class="space-y-2 pt-2 border-t border-slate-200 hidden">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                    BALANCE MOVEMENTS
                </div>
                <div id="report-relations-breakdown" class="space-y-1.5 text-xs font-semibold text-slate-700">
                    <!-- Rendered by JS -->
                </div>
            </div>

            {{-- Money Position --}}
            <div class="space-y-2 pt-2 border-t border-slate-200">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                    MONEY POSITION
                </div>
                <div class="space-y-1.5 text-xs font-semibold text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-100">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Cash on Hand</span>
                        <span class="font-mono font-bold text-slate-900" id="report-pos-held">₹0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Direct to Company</span>
                        <span class="font-mono font-bold text-slate-900" id="report-pos-company">₹0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Petty Balance</span>
                        <span class="font-mono font-bold text-slate-900" id="report-pos-petty">₹0.00</span>
                    </div>
                    <div class="flex justify-between pt-1 border-t border-slate-200 font-black text-slate-950">
                        <span>Closing Shop Balance</span>
                        <span class="font-mono" id="report-pos-shop-bal">₹0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



{{-- IN HEADERS BOTTOM SHEET / MODAL --}}
<div id="in-header-modal" onclick="handleModalBackdropClick(event, 'in-header-modal')" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/40 backdrop-blur-xs hidden transition-opacity">
    <div onclick="event.stopPropagation()" class="w-full max-w-lg rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xl space-y-3 max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 gap-3">
            <div class="min-w-0 flex-1">
                <h3 class="text-xs sm:text-sm font-black uppercase text-slate-900 flex items-center gap-1.5 truncate">
                    <i data-lucide="arrow-down-circle" class="h-4 w-4 text-emerald-600 shrink-0"></i>
                    <span>ADD INCOME</span>
                </h3>
                <p class="text-[10px] sm:text-[11px] font-bold text-slate-400">Choose what you want to record</p>
            </div>
            <button type="button" aria-label="Close" onclick="closeInHeaderModal()"
                    class="h-11 w-11 min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 active:scale-95 transition cursor-pointer shrink-0 shadow-2xs">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <div class="space-y-1.5 divide-y divide-slate-100">
            @forelse($incomeHeaders as $hSec)
                <div onclick='selectHeaderForEntry(@json($hSec["id"]))'
                     class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 transition cursor-pointer group">
                    <div>
                        <div class="text-xs sm:text-sm font-black text-slate-900 group-hover:text-emerald-700 transition">
                            {{ $hSec['name'] }}
                        </div>
                        <div class="text-[10px] font-semibold text-slate-400" id="in-modal-sub-{{ $hSec['id'] }}">
                            {{ count($hSec['settings']) }} categories
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs sm:text-sm font-black text-slate-900" id="in-modal-total-{{ $hSec['id'] }}">
                            ₹0.00
                        </span>
                        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400 group-hover:text-slate-600 transition"></i>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-xs font-bold text-slate-400">
                    No income headers configured.
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- OUT HEADERS BOTTOM SHEET / MODAL --}}
<div id="out-header-modal" onclick="handleModalBackdropClick(event, 'out-header-modal')" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/40 backdrop-blur-xs hidden transition-opacity">
    <div onclick="event.stopPropagation()" class="w-full max-w-lg rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xl space-y-3 max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 gap-3">
            <div class="min-w-0 flex-1">
                <h3 class="text-xs sm:text-sm font-black uppercase text-slate-900 flex items-center gap-1.5 truncate">
                    <i data-lucide="arrow-up-circle" class="h-4 w-4 text-rose-600 shrink-0"></i>
                    <span>ADD EXPENSE</span>
                </h3>
                <p class="text-[10px] sm:text-[11px] font-bold text-slate-400">Choose what you want to record</p>
            </div>
            <button type="button" aria-label="Close" onclick="closeOutHeaderModal()"
                    class="h-11 w-11 min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 active:scale-95 transition cursor-pointer shrink-0 shadow-2xs">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <div class="space-y-1.5 divide-y divide-slate-100">
            @forelse($expenseHeaders as $hSec)
                <div onclick='selectHeaderForEntry(@json($hSec["id"]))'
                     class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 transition cursor-pointer group">
                    <div>
                        <div class="text-xs sm:text-sm font-black text-slate-900 group-hover:text-rose-700 transition">
                            {{ $hSec['name'] }}
                        </div>
                        <div class="text-[10px] font-semibold text-slate-400" id="out-modal-sub-{{ $hSec['id'] }}">
                            @if(!empty($hSec['product_tagging_enabled']))
                                Product Tagging Enabled
                            @else
                                {{ count($hSec['settings']) }} categories
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs sm:text-sm font-black text-slate-900" id="out-modal-total-{{ $hSec['id'] }}">
                            ₹0.00
                        </span>
                        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400 group-hover:text-slate-600 transition"></i>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-xs font-bold text-slate-400">
                    No expense headers configured.
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- DEDICATED HEADER ENTRY DRAWER / MODAL --}}
<div id="header-entry-sheet" onclick="handleModalBackdropClick(event, 'header-entry-sheet')" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/40 backdrop-blur-xs hidden transition-opacity">
    <div onclick="event.stopPropagation()" class="w-full max-w-lg rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xl space-y-3 max-h-[90vh] flex flex-col">
        {{-- Header Top Bar --}}
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 gap-2 shrink-0">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <button type="button" aria-label="Back" onclick="closeHeaderEntrySheet()"
                        class="h-11 w-11 min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 active:scale-95 transition cursor-pointer shrink-0 sm:hidden">
                    <i data-lucide="arrow-left" class="h-5 w-5"></i>
                </button>
                <h3 class="text-xs sm:text-sm font-black uppercase text-slate-900 truncate" id="entry-sheet-title">
                    HEADER TITLE
                </h3>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="font-mono text-xs sm:text-sm font-black text-slate-950 px-2.5 py-1.5 bg-slate-50 rounded-xl border border-slate-200/80" id="entry-sheet-subtotal">
                    ₹0.00
                </span>
                <button type="button" aria-label="Close" onclick="closeHeaderEntrySheet()"
                        class="h-11 w-11 min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 active:scale-95 transition cursor-pointer shrink-0 shadow-2xs">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
        </div>

        {{-- Entry Form Scrollable Body --}}
        <div class="space-y-3 overflow-y-auto flex-1 pr-0.5 py-1" id="entry-sheet-body">
            @foreach($ownerHeaderSections as $hSec)
                <div id="header-form-section-{{ $hSec['id'] }}" class="space-y-2 divide-y divide-slate-100 hidden">
                    @foreach($hSec['settings'] as $s)
                        @php
                            $cat = strtolower((string) ($s->entryType?->category ?? ''));
                            $isIncome = $cat === 'income' || $s->include_in_sales || $s->include_in_income;
                            $resolver = app(\App\Services\Cashbook\CashFlowResolutionService::class);
                            $destLabel = $resolver->resolveDestinationLabel($s);
                            $requiresNote = (bool) ($s->requires_note ?? false);
                            $noteEnabled = $resolver->resolveNoteEnabled($s);
                            $showNote = $requiresNote || $noteEnabled;
                            $rawName = $s->displayName();
                            $displayName = $rawName;
                            $displaySub = (strtolower($displayName) === 'cash' || strtolower($displayName) === 'cash sales') ? 'Remaining cash in shop' : $destLabel;
                        @endphp

                        <div class="pt-2 pb-1 space-y-1" data-entry-row="{{ $s->id }}">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <span class="text-xs sm:text-sm font-bold text-slate-900 block truncate leading-tight">{{ $displayName }}</span>
                                    <span class="text-[10px] sm:text-[11px] font-medium text-slate-400 block truncate leading-tight mt-0.5">{{ $displaySub }}</span>
                                </div>

                                {{-- Amount Input (16px font to avoid iOS zoom, min 44px touch height, ₹ prefix) --}}
                                <div class="relative shrink-0 w-32 sm:w-36">
                                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 font-bold text-xs">₹</span>
                                    <input type="number"
                                           inputmode="decimal"
                                           min="0"
                                           step="0.01"
                                           id="input-s-{{ $s->id }}"
                                           data-setting-id="{{ $s->id }}"
                                           oninput="onOwnerInputChange(this)"
                                           onblur="formatInputOnBlur(this)"
                                           placeholder="0.00"
                                           class="h-10 sm:h-11 w-full rounded-lg border border-slate-200 bg-white pl-6 pr-2.5 text-right text-base font-black font-mono text-slate-950 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 focus:outline-none shadow-2xs">
                                </div>
                            </div>

                            {{-- Optional or Required Note --}}
                            @if($showNote)
                                <div class="pt-0.5">
                                    @if($requiresNote)
                                        <div class="mt-1">
                                            <input type="text"
                                                   id="input-note-{{ $s->id }}"
                                                   data-setting-id="{{ $s->id }}"
                                                   oninput="onOwnerNoteInputChange(this, {{ $s->id }})"
                                                   placeholder="Note (Required for this entry)..."
                                                   class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[11px] font-semibold text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-emerald-600 focus:outline-none">
                                            <span id="note-error-{{ $s->id }}" class="text-[10px] font-bold text-rose-600 hidden mt-0.5 block">Note required for this entry</span>
                                        </div>
                                    @else
                                        <div id="note-wrapper-{{ $s->id }}" class="hidden mt-1">
                                            <input type="text"
                                                   id="input-note-{{ $s->id }}"
                                                   data-setting-id="{{ $s->id }}"
                                                   oninput="onOwnerNoteInputChange(this, {{ $s->id }})"
                                                   placeholder="Add optional note..."
                                                   class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[11px] font-semibold text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-emerald-600 focus:outline-none">
                                        </div>
                                        <button type="button"
                                                onclick="toggleNoteInput({{ $s->id }})"
                                                id="note-toggle-btn-{{ $s->id }}"
                                                class="text-[10px] font-bold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-0.5 cursor-pointer">
                                            <i data-lucide="plus" class="h-2.5 w-2.5"></i> Add Note
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- Product Tagged Rows (If enabled for this Header) --}}
                    @if(!empty($hSec['product_tagging_enabled']))
                        <div class="pt-3 border-t border-slate-100 space-y-2">
                            <div class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Product Items</div>
                            <div id="product-rows-container-{{ $hSec['id'] }}" class="space-y-1 divide-y divide-slate-100">
                                <!-- Dynamic product rows rendered by JS -->
                            </div>
                            <button type="button" onclick='openOwnerProductModal(@json($hSec["id"]), @json($hSec["name"]))'
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition cursor-pointer">
                                <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add Product
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Footer Save Button --}}
        <div class="pt-3 border-t border-slate-100 shrink-0">
            <button type="button"
                    id="save-active-header-btn"
                    onclick="saveActiveHeaderEntries()"
                    class="w-full flex h-11 sm:h-12 items-center justify-center gap-2 rounded-xl bg-emerald-600 text-white font-black text-sm sm:text-base hover:bg-emerald-700 active:scale-[0.98] transition shadow-md cursor-pointer">
                <i data-lucide="check-circle" class="h-4 w-4"></i>
                <span id="save-active-header-text">Save Header</span>
            </button>
        </div>
    </div>
</div>

{{-- DELIVERIES-STYLE PRODUCT SELECTION MODAL --}}
<div id="owner-product-modal" onclick="handleModalBackdropClick(event, 'owner-product-modal')" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4 hidden transition-opacity">
    <div onclick="event.stopPropagation()" class="w-full max-w-lg rounded-xl sm:rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xl space-y-3">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 gap-3">
            <h3 class="text-xs sm:text-sm font-black uppercase text-slate-900 flex items-center gap-1.5 min-w-0 flex-1 truncate" id="owner-product-modal-title">
                <i data-lucide="tag" class="h-4 w-4 text-emerald-600 shrink-0"></i>
                <span class="truncate">Select Product</span>
            </h3>
            <button type="button" aria-label="Close" onclick="closeOwnerProductModal()"
                    class="h-11 w-11 min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 active:scale-95 transition cursor-pointer shrink-0 shadow-2xs">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <div class="space-y-2.5">
            <input type="text" id="owner-product-search-input" oninput="onOwnerProductSearchInput()" placeholder="Search products by name or SKU..."
                   class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-emerald-600 focus:outline-none shadow-2xs">

            <div id="owner-product-list" class="max-h-60 overflow-y-auto space-y-1.5 divide-y divide-slate-100">
                <div class="p-6 text-center text-xs font-bold text-slate-400">Search products...</div>
            </div>
        </div>
    </div>
</div>

<script>
    let activeDayData = {};

    const settings = @json($settingsJson);
    const accounts = @json($accountsJson);
    const relations = @json($relationJson);
    const headers = @json($headersJson);
    const initialTxAmounts = @json($initialTxAmounts);
    const initialTxNotes = @json($initialTxNotes);
    const shopName = @json($shop->name);

    let isSubmitting = false;
    let activeHeaderId = null;
    let activeProductHeaderId = null;
    let productQuery = '';
    let productSearchDebounceTimer = null;
    let productRowsState = {};

    document.addEventListener('DOMContentLoaded', function () {
        // Populate initial amounts and notes from existing transactions
        settings.forEach(s => {
            if (initialTxAmounts[s.id] !== undefined) {
                activeDayData[s.id] = initialTxAmounts[s.id];
                const inputEl = document.getElementById('input-s-' + s.id);
                if (inputEl) inputEl.value = initialTxAmounts[s.id] > 0 ? initialTxAmounts[s.id] : '';
            }
            if (initialTxNotes[s.id] !== undefined) {
                activeDayData.notes = activeDayData.notes || {};
                activeDayData.notes[s.id] = initialTxNotes[s.id];
                const noteEl = document.getElementById('input-note-' + s.id);
                if (noteEl) {
                    noteEl.value = initialTxNotes[s.id];
                    const wrapper = document.getElementById('note-wrapper-' + s.id);
                    if (wrapper) wrapper.classList.remove('hidden');
                }
            }
        });

        recalculateOwnerCashbook();
        if (window.lucide) lucide.createIcons();
    });

    // NAVIGATION / VIEW TOGGLES
    function showReportView() {
        document.getElementById('cashbook-dashboard-view').classList.add('hidden');
        document.getElementById('cashbook-report-view').classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function hideReportView() {
        document.getElementById('cashbook-report-view').classList.add('hidden');
        document.getElementById('cashbook-dashboard-view').classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    // SYNC MODAL OPEN STATE FOR FLOATING CONTROLS
    function syncModalOpenState() {
        const inModal = document.getElementById('in-header-modal');
        const outModal = document.getElementById('out-header-modal');
        const entrySheet = document.getElementById('header-entry-sheet');
        const productModal = document.getElementById('owner-product-modal');

        const hasOpenModal = (inModal && !inModal.classList.contains('hidden')) ||
                             (outModal && !outModal.classList.contains('hidden')) ||
                             (entrySheet && !entrySheet.classList.contains('hidden')) ||
                             (productModal && !productModal.classList.contains('hidden'));

        const jumpControls = document.getElementById('page-jump-controls');
        if (hasOpenModal) {
            document.body.classList.add('cashbook-modal-open');
            if (jumpControls) jumpControls.style.setProperty('display', 'none', 'important');
        } else {
            document.body.classList.remove('cashbook-modal-open');
            if (jumpControls) jumpControls.style.removeProperty('display');
        }
    }

    // MODAL BACKDROP & ESCAPE HANDLERS
    function handleModalBackdropClick(event, modalId) {
        if (event.target && event.target.id === modalId) {
            if (modalId === 'in-header-modal') {
                closeInHeaderModal();
            } else if (modalId === 'out-header-modal') {
                closeOutHeaderModal();
            } else if (modalId === 'header-entry-sheet') {
                closeHeaderEntrySheet();
            } else if (modalId === 'owner-product-modal') {
                closeOwnerProductModal();
            }
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const productModal = document.getElementById('owner-product-modal');
            const entrySheet = document.getElementById('header-entry-sheet');
            const inModal = document.getElementById('in-header-modal');
            const outModal = document.getElementById('out-header-modal');

            if (productModal && !productModal.classList.contains('hidden')) {
                closeOwnerProductModal();
            } else if (entrySheet && !entrySheet.classList.contains('hidden')) {
                closeHeaderEntrySheet();
            } else if (inModal && !inModal.classList.contains('hidden')) {
                closeInHeaderModal();
            } else if (outModal && !outModal.classList.contains('hidden')) {
                closeOutHeaderModal();
            }
        }
    });

    // IN / OUT MODALS
    function openInHeaderModal() {
        document.getElementById('in-header-modal').classList.remove('hidden');
        syncModalOpenState();
        if (window.lucide) lucide.createIcons();
    }

    function closeInHeaderModal() {
        document.getElementById('in-header-modal').classList.add('hidden');
        syncModalOpenState();
    }

    function openOutHeaderModal() {
        document.getElementById('out-header-modal').classList.remove('hidden');
        syncModalOpenState();
        if (window.lucide) lucide.createIcons();
    }

    function closeOutHeaderModal() {
        document.getElementById('out-header-modal').classList.add('hidden');
        syncModalOpenState();
    }

    // HEADER ENTRY SHEET
    function selectHeaderForEntry(headerId) {
        closeInHeaderModal();
        closeOutHeaderModal();

        activeHeaderId = String(headerId);
        const header = headers.find(h => String(h.id) === activeHeaderId);
        if (!header) return;

        // Hide all header form sections and show only the selected one
        headers.forEach(h => {
            const sec = document.getElementById('header-form-section-' + h.id);
            if (sec) sec.classList.add('hidden');
        });

        const activeSec = document.getElementById('header-form-section-' + activeHeaderId);
        if (activeSec) activeSec.classList.remove('hidden');

        document.getElementById('entry-sheet-title').textContent = header.name;
        document.getElementById('save-active-header-text').textContent = 'Save ' + header.name;

        updateActiveHeaderSubtotal();
        document.getElementById('header-entry-sheet').classList.remove('hidden');
        syncModalOpenState();
        if (window.lucide) lucide.createIcons();
    }

    function closeHeaderEntrySheet() {
        document.getElementById('header-entry-sheet').classList.add('hidden');
        activeHeaderId = null;
        syncModalOpenState();
    }

    function updateActiveHeaderSubtotal() {
        if (!activeHeaderId) return;
        const header = headers.find(h => String(h.id) === activeHeaderId);
        if (!header) return;

        let total = 0;
        (header.setting_ids || []).forEach(sId => {
            total += parseFloat(activeDayData[sId]) || 0;
        });

        const pRows = productRowsState[activeHeaderId] || [];
        pRows.forEach(pr => {
            total += parseFloat(pr.amount) || 0;
        });

        const subEl = document.getElementById('entry-sheet-subtotal');
        if (subEl) subEl.textContent = formatCurrency(total);
    }

    function toggleNoteInput(settingId) {
        const wrapper = document.getElementById('note-wrapper-' + settingId);
        const btn = document.getElementById('note-toggle-btn-' + settingId);
        if (!wrapper) return;

        if (wrapper.classList.contains('hidden')) {
            wrapper.classList.remove('hidden');
            if (btn) btn.classList.add('hidden');
            const input = document.getElementById('input-note-' + settingId);
            if (input) input.focus();
        }
    }

    function onOwnerInputChange(inputElement) {
        const settingId = parseInt(inputElement.getAttribute('data-setting-id'));
        let val = parseFloat(inputElement.value);
        if (isNaN(val) || val < 0) val = 0;

        activeDayData[settingId] = val;
        updateActiveHeaderSubtotal();
        recalculateOwnerCashbook();
    }

    function onOwnerNoteInputChange(inputElement, settingId) {
        activeDayData.notes = activeDayData.notes || {};
        activeDayData.notes[settingId] = inputElement.value;
        validateOwnerNotes();
    }

    function validateOwnerNotes(targetSettingIds = null) {
        let allValid = true;
        const dayNotes = activeDayData.notes || {};

        settings.forEach(s => {
            if (targetSettingIds && !targetSettingIds.includes(s.id)) return;
            if (!s.requires_note) return;

            const amt = parseFloat(activeDayData[s.id]) || 0;
            const noteVal = (dayNotes[s.id] || '').trim();

            const inputEl = document.getElementById('input-note-' + s.id);
            const errorEl = document.getElementById('note-error-' + s.id);

            if (amt > 0 && noteVal.length === 0) {
                allValid = false;
                if (inputEl) {
                    inputEl.classList.add('border-rose-500', 'bg-rose-50/20');
                    inputEl.classList.remove('border-slate-200');
                }
                if (errorEl) errorEl.classList.remove('hidden');
            } else {
                if (inputEl) {
                    inputEl.classList.remove('border-rose-500', 'bg-rose-50/20');
                    inputEl.classList.add('border-slate-200');
                }
                if (errorEl) errorEl.classList.add('hidden');
            }
        });

        return allValid;
    }

    function formatInputOnBlur(inputElement) {
        let val = parseFloat(inputElement.value);
        if (isNaN(val) || val <= 0) {
            inputElement.value = '';
        } else {
            inputElement.value = val.toString();
        }
    }

    // CORE RECALCULATION ENGINE
    function recalculateOwnerCashbook() {
        let totalIncome = 0;
        let totalExpense = 0;
        let cashCollectedAtShop = 0;
        let expensesPaidFromShopCash = 0;
        let expensesPaidFromPetty = 0;
        let bankInflows = {};
        let directCompanyTotal = 0;
        let activeEntryCount = 0;

        accounts.forEach(acc => bankInflows[acc.id] = 0);

        const headerTotals = {};
        const headerActiveCounts = {};

        headers.forEach(h => {
            let headerTotal = 0;
            let headerCount = 0;

            (h.setting_ids || []).forEach(sId => {
                const amt = parseFloat(activeDayData[sId]) || 0;
                headerTotal += amt;

                const s = settings.find(item => item.id === sId);
                if (s && amt > 0) {
                    headerCount++;
                    activeEntryCount++;
                    if (s.is_income) {
                        totalIncome += amt;
                        if (s.company_account_id) {
                            bankInflows[s.company_account_id] = (bankInflows[s.company_account_id] || 0) + amt;
                            directCompanyTotal += amt;
                        } else {
                            cashCollectedAtShop += amt;
                        }
                    } else {
                        totalExpense += amt;
                        if (s.funding_source === 'sales' || s.funding_source === 'shop_cash') {
                            expensesPaidFromShopCash += amt;
                        } else if (s.funding_source === 'petty') {
                            expensesPaidFromPetty += amt;
                        }
                    }
                }
            });

            // Product tagged rows subtotal
            const pRows = productRowsState[h.id] || [];
            pRows.forEach(pr => {
                const pAmt = parseFloat(pr.amount) || 0;
                if (pAmt > 0) {
                    headerTotal += pAmt;
                    headerCount++;
                    activeEntryCount++;
                    if (h.type === 'expense') {
                        totalExpense += pAmt;
                        expensesPaidFromShopCash += pAmt;
                    }
                }
            });

            headerTotals[h.id] = headerTotal;
            headerActiveCounts[h.id] = headerCount;

            // Update modal badges
            const inTotalEl = document.getElementById('in-modal-total-' + h.id);
            if (inTotalEl) inTotalEl.textContent = formatCurrency(headerTotal);
            const outTotalEl = document.getElementById('out-modal-total-' + h.id);
            if (outTotalEl) outTotalEl.textContent = formatCurrency(headerTotal);
        });

        const todayNetActivity = totalIncome - totalExpense;

        // Relations settlement
        let relationSettled = 0;
        let relationRule = 'previous_day_balance';
        let relHtml = '';

        if (relations.length > 0) {
            const rel = relations[0];
            relationRule = rel.eligibility_rule || 'previous_day_balance';
            let grossAdd = 0;
            let grossSub = 0;

            (rel.items || []).forEach(item => {
                const itemAmt = parseFloat(activeDayData[item.setting_id]) || 0;
                if (item.role === 'subtract') grossSub += itemAmt;
                else grossAdd += itemAmt;
            });

            const netRel = grossAdd - grossSub;
            const openingPayable = {{ (float) ($snapshot->closing_shop_position ?? 15000) }};
            const eligible = relationRule === 'previous_day_balance' ? Math.max(0, openingPayable) : Math.max(0, openingPayable + cashCollectedAtShop - expensesPaidFromShopCash);

            let pendingAmount = 0;
            if (netRel > 0) {
                relationSettled = Math.min(netRel, eligible);
                pendingAmount = netRel - relationSettled;
            } else {
                relationSettled = netRel;
                pendingAmount = 0;
            }

            if (relationSettled > 0 || Math.abs(netRel) > 0) {
                relHtml = `
                    <div class="flex justify-between py-1 text-xs">
                        <div>
                            <span class="block font-bold text-slate-900">${escapeHtml(rel.name || 'Supermarket Settlement')}</span>
                            <span class="text-[10px] text-slate-400 font-medium block">From: Previous Shop Balance</span>
                        </div>
                        <span class="font-mono text-slate-950 font-black">-${formatCurrency(relationSettled)}</span>
                    </div>
                `;
            }
        }

        const repRelContainer = document.getElementById('report-relations-container');
        const repRelEl = document.getElementById('report-relations-breakdown');
        if (repRelEl) repRelEl.innerHTML = relHtml;
        if (repRelContainer) {
            if (relHtml.trim().length > 0) {
                repRelContainer.classList.remove('hidden');
            } else {
                repRelContainer.classList.add('hidden');
            }
        }

        // Top KPIs
        const shopHeldNet = Math.max(0, cashCollectedAtShop - expensesPaidFromShopCash);
        const openShopBal = {{ (float) ($snapshot->closing_shop_position ?? 15000) }};
        const closingShopBal = openShopBal - relationSettled + shopHeldNet;
        const openingPetty = {{ (float) ($snapshot->petty_balance ?? 5440) }};
        const closingPetty = Math.max(0, openingPetty - expensesPaidFromPetty);

        // Update Dashboard Elements
        const kpiShopBal = document.getElementById('kpi-shop-balance');
        if (kpiShopBal) kpiShopBal.textContent = formatCurrency(closingShopBal);

        const kpiNet = document.getElementById('kpi-today-net-activity');
        if (kpiNet) {
            kpiNet.textContent = formatCurrency(todayNetActivity);
            kpiNet.className = 'font-mono text-lg sm:text-2xl font-black mt-0.5 ' + (todayNetActivity >= 0 ? 'text-emerald-700' : 'text-rose-700');
        }

        const kpiCashHeld = document.getElementById('kpi-cash-held');
        if (kpiCashHeld) kpiCashHeld.textContent = formatCurrency(shopHeldNet);

        const kpiCompany = document.getElementById('kpi-reached-company');
        if (kpiCompany) kpiCompany.textContent = formatCurrency(directCompanyTotal);

        const kpiPetty = document.getElementById('kpi-petty-closing');
        if (kpiPetty) kpiPetty.textContent = formatCurrency(closingPetty);

        // Update Today's Row
        const todayCountEl = document.getElementById('today-entry-count');
        if (todayCountEl) todayCountEl.textContent = activeEntryCount + (activeEntryCount === 1 ? ' Entry' : ' Entries');

        const todayRowOut = document.getElementById('today-row-out');
        if (todayRowOut) todayRowOut.textContent = formatCurrency(totalExpense);

        const todayRowIn = document.getElementById('today-row-in');
        if (todayRowIn) todayRowIn.textContent = formatCurrency(totalIncome);

        // Update Main Bill Sections & Report Breakdown Content
        renderMainBillSections();
        renderReportBreakdown();

        // Update Report View Elements
        const repNet = document.getElementById('report-net-activity');
        if (repNet) repNet.textContent = formatCurrency(todayNetActivity);

        const repHeld = document.getElementById('report-pos-held');
        if (repHeld) repHeld.textContent = formatCurrency(shopHeldNet);

        const repComp = document.getElementById('report-pos-company');
        if (repComp) repComp.textContent = formatCurrency(directCompanyTotal);

        const repPetty = document.getElementById('report-pos-petty');
        if (repPetty) repPetty.textContent = formatCurrency(closingPetty);

        const repShop = document.getElementById('report-pos-shop-bal');
        if (repShop) repShop.textContent = formatCurrency(closingShopBal);

        if (window.lucide) lucide.createIcons();
    }

    function renderMainBillSections() {
        const container = document.getElementById('today-headers-summary-container');
        const emptyState = document.getElementById('today-empty-state');
        if (!container) return;

        if (headers.length === 0) {
            if (emptyState) emptyState.classList.remove('hidden');
            container.innerHTML = '';
            return;
        }

        if (emptyState) emptyState.classList.add('hidden');

        // Order: Income headers first, then Expense headers
        const incomeHeaders = headers.filter(h => h.type === 'income');
        const expenseHeaders = headers.filter(h => h.type === 'expense');
        const orderedHeaders = [...incomeHeaders, ...expenseHeaders];

        container.innerHTML = orderedHeaders.map(h => {
            const isIncome = h.type === 'income';
            let hTotal = 0;

            const childLines = (h.setting_ids || []).map(sId => {
                const amt = parseFloat(activeDayData[sId]) || 0;
                hTotal += amt;
                const s = settings.find(item => item.id === sId);
                if (!s) return '';
                const name = s.name || 'Item';
                let sub = '';
                if (name.toLowerCase() === 'cash' || name.toLowerCase() === 'cash sales') {
                    sub = 'Remaining cash in shop';
                } else if (s.company_account_name) {
                    sub = s.company_account_name;
                } else if (s.destination_label) {
                    sub = s.destination_label;
                }
                const note = (activeDayData.notes && activeDayData.notes[sId]) ? activeDayData.notes[sId].trim() : '';

                return `
                    <div class="flex items-start justify-between gap-2 py-1">
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-bold text-slate-800 block leading-tight truncate">${escapeHtml(name)}</span>
                            ${sub ? `<span class="text-[10px] text-slate-400 font-medium block leading-tight mt-0.5 truncate">${escapeHtml(sub)}</span>` : ''}
                            ${note ? `<span class="text-[10px] text-emerald-600 font-medium block leading-tight mt-0.5 truncate">${escapeHtml(note)}</span>` : ''}
                        </div>
                        <span class="font-mono text-xs font-black text-slate-900 shrink-0 ${amt > 0 ? '' : 'text-slate-400'}">${formatCurrency(amt)}</span>
                    </div>
                `;
            }).filter(Boolean).join('');

            const pRows = productRowsState[h.id] || [];
            const productLines = pRows.map(pr => {
                const pAmt = parseFloat(pr.amount) || 0;
                hTotal += pAmt;
                return `
                    <div class="flex items-start justify-between gap-2 py-1">
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-bold text-slate-800 block leading-tight truncate">${escapeHtml(pr.productName)}</span>
                            ${pr.sku ? `<span class="text-[10px] text-slate-400 font-medium block leading-tight mt-0.5 truncate">${escapeHtml(pr.sku)}</span>` : ''}
                        </div>
                        <span class="font-mono text-xs font-black text-slate-900 shrink-0 ${pAmt > 0 ? '' : 'text-slate-400'}">${formatCurrency(pAmt)}</span>
                    </div>
                `;
            }).join('');

            const noProductsPrompt = (h.product_tagging_enabled && pRows.length === 0 && (h.setting_ids || []).length === 0)
                ? `<div class="py-1 text-[11px] text-slate-400 italic">No products recorded yet (tap to add)</div>`
                : '';

            return `
                <div onclick='selectHeaderForEntry(${JSON.stringify(h.id)})'
                     class="rounded-xl sm:rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 shadow-xs hover:border-emerald-300 hover:shadow-sm transition cursor-pointer group space-y-2 select-none">
                    
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="h-2 w-2 rounded-full ${isIncome ? 'bg-emerald-500' : 'bg-rose-500'} shrink-0"></span>
                            <span class="text-xs sm:text-sm font-black uppercase text-slate-900 group-hover:text-emerald-700 transition truncate">${escapeHtml(h.name)}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-mono text-xs sm:text-sm font-black ${isIncome ? 'text-emerald-700' : 'text-slate-900'}">
                                ${formatCurrency(hTotal)}
                            </span>
                            <span class="inline-flex items-center text-[10px] font-bold text-slate-400 group-hover:text-emerald-700 transition">
                                <span>Edit</span>
                                <i data-lucide="chevron-right" class="h-3 w-3 ml-0.5"></i>
                            </span>
                        </div>
                    </div>

                    <div class="space-y-0.5 divide-y divide-slate-50">
                        ${childLines}
                        ${productLines}
                        ${noProductsPrompt}
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-100 pt-1.5 text-[11px] font-bold text-slate-500">
                        <span>Total ${escapeHtml(h.name)}</span>
                        <span class="font-mono font-bold text-slate-900">${formatCurrency(hTotal)}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderReportBreakdown() {
        const container = document.getElementById('report-headers-breakdown');
        if (!container) return;

        const headerSections = headers.map(h => {
            const settingLines = (h.setting_ids || []).map(sId => {
                const amt = parseFloat(activeDayData[sId]) || 0;
                if (amt <= 0) return '';
                const s = settings.find(item => item.id === sId);
                const name = s ? s.name : 'Item';
                return `
                    <div class="flex justify-between py-1 text-slate-700 font-medium">
                        <span>${escapeHtml(name)}</span>
                        <span class="font-mono font-bold text-slate-900">${formatCurrency(amt)}</span>
                    </div>
                `;
            }).filter(Boolean).join('');

            const pRows = productRowsState[h.id] || [];
            const productLines = pRows.map(pr => {
                const pAmt = parseFloat(pr.amount) || 0;
                if (pAmt <= 0) return '';
                return `
                    <div class="flex justify-between py-1 text-slate-700 font-medium">
                        <span>${escapeHtml(pr.productName)}</span>
                        <span class="font-mono font-bold text-slate-900">${formatCurrency(pAmt)}</span>
                    </div>
                `;
            }).filter(Boolean).join('');

            if (!settingLines && !productLines) {
                return '';
            }

            let hTotal = 0;
            (h.setting_ids || []).forEach(sId => hTotal += (parseFloat(activeDayData[sId]) || 0));
            pRows.forEach(pr => hTotal += (parseFloat(pr.amount) || 0));

            return `
                <div class="space-y-2">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-1.5">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-900">${escapeHtml(h.name)}</span>
                        <span class="font-mono text-xs font-black text-slate-900">${formatCurrency(hTotal)}</span>
                    </div>
                    <div class="space-y-0.5 text-xs">
                        ${settingLines}
                        ${productLines}
                    </div>
                    <div class="flex justify-between border-t border-slate-100 pt-1.5 text-[11px] font-bold text-slate-500">
                        <span>Subtotal</span>
                        <span class="font-mono font-bold text-slate-900">${formatCurrency(hTotal)}</span>
                    </div>
                </div>
            `;
        }).filter(Boolean);

        if (headerSections.length > 0) {
            container.innerHTML = headerSections.join('<div class="border-t border-dashed border-slate-200 my-4"></div>');
        } else {
            container.innerHTML = `
                <div class="py-6 text-center text-xs font-medium text-slate-400">
                    No cashbook transactions recorded for this date.
                </div>
            `;
        }
    }

    // SAVE ACTIVE HEADER ENTRIES
    async function saveActiveHeaderEntries() {
        if (isSubmitting || !activeHeaderId) return;

        const header = headers.find(h => String(h.id) === activeHeaderId);
        if (!header) return;

        if (!validateOwnerNotes(header.setting_ids)) {
            alert('Please fill out all required notes for this header.');
            return;
        }

        const entriesPayload = [];
        // Include entries for all configured settings in active state (new/updated and zeroed out)
        settings.forEach(s => {
            const amt = parseFloat(activeDayData[s.id]) || 0;
            const wasRecorded = initialTxAmounts[s.id] !== undefined && initialTxAmounts[s.id] > 0;
            const isInActiveHeader = header.setting_ids && header.setting_ids.includes(s.id);

            if (amt > 0 || wasRecorded || isInActiveHeader) {
                const noteVal = (activeDayData.notes && activeDayData.notes[s.id]) ? activeDayData.notes[s.id].trim() : null;
                entriesPayload.push({
                    entry_type_code: s.code,
                    amount: amt,
                    funding_source: s.funding_source || 'none',
                    notes: noteVal
                });
            }
        });

        const btn = document.getElementById('save-active-header-btn');
        const textEl = document.getElementById('save-active-header-text');
        if (btn) btn.disabled = true;
        if (textEl) textEl.textContent = 'Saving...';
        isSubmitting = true;

        try {
            const response = await fetch('{{ route('shop-owner.cashbook.api.bulk-record-entries') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    business_date: '{{ $selectedDate->toDateString() }}',
                    entries: entriesPayload
                })
            });

            const data = await response.json();
            if (data.success) {
                // Update baseline initial amounts to match active saved data
                settings.forEach(s => {
                    const amt = parseFloat(activeDayData[s.id]) || 0;
                    if (amt > 0) {
                        initialTxAmounts[s.id] = amt;
                    } else {
                        delete initialTxAmounts[s.id];
                    }
                });

                closeHeaderEntrySheet();
                recalculateOwnerCashbook();
            } else {
                alert(data.message || 'Error saving cashbook header.');
            }
        } catch (err) {
            alert('Network error while saving cashbook. Please try again.');
        } finally {
            isSubmitting = false;
            if (btn) btn.disabled = false;
            if (textEl) textEl.textContent = 'Save ' + (header ? header.name : 'Header');
            if (window.lucide) lucide.createIcons();
        }
    }

    // PRODUCT TAGGING FUNCTIONS
    function openOwnerProductModal(headerId, headerName) {
        activeProductHeaderId = headerId;
        const titleEl = document.getElementById('owner-product-modal-title');
        if (titleEl) {
            titleEl.innerHTML = `<i data-lucide="tag" class="h-4 w-4 text-emerald-600"></i> <span>Select Product for ${escapeHtml(headerName)}</span>`;
        }
        document.getElementById('owner-product-modal').classList.remove('hidden');
        syncModalOpenState();
        fetchOwnerProducts('', 1);
        if (window.lucide) lucide.createIcons();
    }

    function closeOwnerProductModal() {
        document.getElementById('owner-product-modal').classList.add('hidden');
        syncModalOpenState();
    }

    function onOwnerProductSearchInput() {
        if (productSearchDebounceTimer) clearTimeout(productSearchDebounceTimer);
        productSearchDebounceTimer = setTimeout(() => {
            const input = document.getElementById('owner-product-search-input');
            productQuery = input ? input.value.trim() : '';
            fetchOwnerProducts(productQuery, 1);
        }, 250);
    }

    async function fetchOwnerProducts(query = '', page = 1) {
        const container = document.getElementById('owner-product-list');
        if (!container) return;
        container.innerHTML = `<div class="p-6 text-center text-xs font-bold text-slate-400">Searching products...</div>`;

        try {
            const url = new URL('{{ route('shop-owner.cashbook.api.products.search') }}', window.location.origin);
            if (query) url.searchParams.set('q', query);
            if (activeProductHeaderId) url.searchParams.set('header_id', activeProductHeaderId);
            url.searchParams.set('page', page);

            const res = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (data.products && data.products.length > 0) {
                const existing = productRowsState[activeProductHeaderId] || [];
                container.innerHTML = data.products.map(p => {
                    const isAdded = existing.some(r => r.productId === p.id);
                    return `
                        <div class="flex items-center justify-between gap-2 py-2">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs font-bold text-slate-900 block truncate">${escapeHtml(p.name)}</span>
                                <span class="text-[10px] font-semibold text-slate-400 block">${escapeHtml(p.sku || 'N/A')}</span>
                            </div>
                            ${isAdded ? '<span class="text-[10px] font-bold text-slate-400">Added</span>' : `
                                <button type="button" onclick="selectOwnerProduct(${p.id}, '${escapeJsString(p.name)}', '${escapeJsString(p.sku || '')}')"
                                        class="inline-flex items-center justify-center rounded-lg bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 hover:bg-emerald-100 transition cursor-pointer">
                                    Select
                                </button>
                            `}
                        </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = `<div class="p-6 text-center text-xs font-bold text-slate-400">No products found.</div>`;
            }
        } catch (e) {
            container.innerHTML = `<div class="p-6 text-center text-xs font-bold text-rose-500">Error loading catalog.</div>`;
        }
    }

    function selectOwnerProduct(productId, productName, sku) {
        const hId = activeProductHeaderId;
        if (!hId) return;

        productRowsState[hId] = productRowsState[hId] || [];
        if (!productRowsState[hId].some(r => r.productId === productId)) {
            productRowsState[hId].push({ productId, productName, sku, amount: 0 });
        }
        closeOwnerProductModal();
        renderOwnerProductRows(hId);
        updateActiveHeaderSubtotal();
        recalculateOwnerCashbook();
    }

    function removeOwnerProductRow(hId, productId) {
        if (!productRowsState[hId]) return;
        productRowsState[hId] = productRowsState[hId].filter(r => r.productId !== productId);
        renderOwnerProductRows(hId);
        updateActiveHeaderSubtotal();
        recalculateOwnerCashbook();
    }

    function renderOwnerProductRows(hId) {
        const container = document.getElementById('product-rows-container-' + hId);
        if (!container) return;

        const rows = productRowsState[hId] || [];
        if (rows.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = rows.map(r => `
            <div class="flex items-center justify-between gap-2 py-1.5">
                <div class="min-w-0 flex-1">
                    <span class="text-xs font-bold text-slate-900 block truncate">${escapeHtml(r.productName)}</span>
                    <span class="text-[10px] text-slate-400 block">${escapeHtml(r.sku || 'Tag')}</span>
                </div>
                <div class="relative shrink-0 w-28 sm:w-32">
                    <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-slate-400 font-bold text-xs pointer-events-none">₹</span>
                    <input type="number" inputmode="decimal" min="0" step="0.01" value="${r.amount || ''}"
                           oninput="onOwnerProductAmountChange('${hId}', ${r.productId}, this)"
                           placeholder="0.00"
                           class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-5 pr-2 text-right text-sm font-bold font-mono text-slate-950 focus:border-emerald-600 focus:outline-none">
                </div>
                <button type="button" onclick="removeOwnerProductRow('${hId}', ${r.productId})" class="p-1 text-slate-400 hover:text-rose-600">
                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                </button>
            </div>
        `).join('');
        if (window.lucide) lucide.createIcons();
    }

    function onOwnerProductAmountChange(hId, productId, inputEl) {
        const val = parseFloat(inputEl.value) || 0;
        const rows = productRowsState[hId] || [];
        const row = rows.find(r => r.productId === productId);
        if (row) row.amount = val;
        updateActiveHeaderSubtotal();
        recalculateOwnerCashbook();
    }

    function formatCurrency(amount) {
        const val = parseFloat(amount) || 0;
        return '₹' + Math.abs(val).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeJsString(str) {
        return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
</script>
@endsection
