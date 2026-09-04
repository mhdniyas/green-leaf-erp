@extends('admin.cashbook.layouts.app')

@section('title', $currentShop->name.' - 3-Day Cashbook Demo')

@section('content')
@php
    $relationList = $relations ?? collect();
    $accountsList = $companyAccounts ?? collect();
    $headerGroupList = $headerGroups ?? collect();

    // Sort settings by header_display_order or display_order
    $sortedSettings = $settings->sortBy(fn ($s) => (int) ($s->header_display_order ?? $s->entryType?->display_order ?? $s->display_order))->values();

    // Group settings by header_group_id
    $settingsByHeader = $sortedSettings->groupBy(fn ($s) => (int) ($s->header_group_id ?? 0));

    $demoHeaderSections = collect();

    // 1. Process explicit saved headers in display_order
    foreach ($headerGroupList->sortBy('display_order') as $hg) {
        $hgId = (int) $hg->id;
        $headerSettings = $settingsByHeader->get($hgId, collect())->values();

        if ($headerSettings->isNotEmpty() || $hg->product_tagging_enabled) {
            $demoHeaderSections->push([
                'id' => $hgId,
                'name' => $hg->name,
                'type' => strtolower((string) ($hg->type ?? 'income')),
                'display_order' => (int) ($hg->display_order ?? 0),
                'product_tagging_enabled' => (bool) ($hg->product_tagging_enabled ?? false),
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
            $demoHeaderSections->push([
                'id' => 'unassigned_income',
                'name' => 'UNASSIGNED INCOME',
                'type' => 'income',
                'display_order' => 9998,
                'settings' => $unassignedIncome,
            ]);
        }

        if ($unassignedExpense->isNotEmpty()) {
            $demoHeaderSections->push([
                'id' => 'unassigned_expense',
                'name' => 'UNASSIGNED EXPENSES',
                'type' => 'expense',
                'display_order' => 9999,
                'settings' => $unassignedExpense,
            ]);
        }
    }

    // Fallback: If no headers produced, wrap all settings in default headers
    if ($demoHeaderSections->isEmpty() && $sortedSettings->isNotEmpty()) {
        $incomeSet = $sortedSettings->filter(function ($s) {
            $cat = strtolower((string) ($s->entryType?->category ?? ''));
            return $cat === 'income' || $s->include_in_sales || $s->include_in_income;
        })->values();
        $expenseSet = $sortedSettings->reject(fn($s) => $incomeSet->contains('id', $s->id))->values();

        if ($incomeSet->isNotEmpty()) {
            $demoHeaderSections->push([
                'id' => 'default_sales',
                'name' => 'SALES',
                'type' => 'income',
                'display_order' => 1,
                'settings' => $incomeSet,
            ]);
        }
        if ($expenseSet->isNotEmpty()) {
            $demoHeaderSections->push([
                'id' => 'default_expense',
                'name' => 'EXPENSES',
                'type' => 'expense',
                'display_order' => 2,
                'settings' => $expenseSet,
            ]);
        }
    }

    $incomeSections = $demoHeaderSections->filter(fn($s) => $s['type'] === 'income')->values();
    $expenseSections = $demoHeaderSections->filter(fn($s) => $s['type'] === 'expense')->values();

    // Collect all setting IDs rendered inside header sections so Relation section doesn't duplicate inputs
    $allRenderedSettingIds = $demoHeaderSections->pluck('settings')->flatten()->pluck('id')->map(fn($id) => (int)$id)->all();

    $relationUniqueItems = collect();
    $relationLinkedItems = collect();

    if ($relationList->isNotEmpty() && $relationList->first()->items->isNotEmpty()) {
        foreach ($relationList->first()->items as $relItem) {
            $settingId = (int) $relItem->shop_ledger_entry_setting_id;
            if (in_array($settingId, $allRenderedSettingIds, true)) {
                $relationLinkedItems->push($relItem);
            } else {
                $relationUniqueItems->push($relItem);
            }
        }
    }

    // Serialize metadata for JS calculation engine
    $settingsJson = $settings->map(function ($s) {
        $cat = strtolower((string) ($s->entryType?->category ?? ''));
        $isIncome = $cat === 'income' || $s->include_in_sales || $s->include_in_income;
        $code = strtolower((string) ($s->entryType?->code ?? ''));
        $name = strtolower((string) ($s->entryType?->name ?? ''));
        $isCashPurchase = str_contains($code, 'cash_purchase') || str_contains($name, 'cash purchase');

        $fundingSource = $s->default_funding_source ?: 'sales';
        $requiresNote = $s->requiresNote() || in_array($code, ['other_income', 'other_expense'], true);
        $noteEnabled = (bool) $s->note_enabled;
        $showNoteField = $noteEnabled || $requiresNote;

        return [
            'id' => (int) $s->id,
            'name' => $s->entryType?->name ?? 'Entry #'.$s->id,
            'category' => $isIncome ? 'income' : 'expense',
            'is_income' => $isIncome,
            'is_expense' => !$isIncome,
            'is_cash_purchase' => $isCashPurchase,
            'requires_note' => $requiresNote,
            'note_enabled' => $noteEnabled,
            'show_note_field' => $showNoteField,
            'company_account_id' => $s->company_account_id ? (int) $s->company_account_id : null,
            'company_account_name' => $s->companyAccount?->name ?? $s->companyAccount?->bank_name,
            'funding_source' => $fundingSource,
            'settlement_behavior' => $s->settlement_behavior ?? 'none',
            'petty_behavior' => $s->petty_behavior ?? 'none',
            'company_pending_behavior' => $s->company_pending_behavior ?? 'none',
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
                    'name' => $i->setting?->entryType?->name ?? 'Unknown',
                ];
            })->values()->all(),
        ];
    })->values()->all();

    $headersJson = $demoHeaderSections->map(function ($hs) {
        return [
            'id' => (string) $hs['id'],
            'name' => $hs['name'],
            'type' => $hs['type'],
            'product_tagging_enabled' => (bool) ($hs['product_tagging_enabled'] ?? false),
            'setting_ids' => $hs['settings']->pluck('id')->map(fn($id) => (int)$id)->all(),
        ];
    })->values()->all();
@endphp

<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header & Top Navigation Bar (Compact Mobile-First) -->
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.cashbook.settings.shop', ['shop' => $currentShop->shop_id]) }}"
                   class="mb-1.5 inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-slate-700">
                    <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                    Back to {{ $currentShop->name }} Settings
                </a>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-950">{{ $currentShop->name }}</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Daily Cashbook Demo
                    </span>
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Simulation Only · No financial data will be saved
                </p>
            </div>

            <!-- Top Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="autoFill3Days()"
                        class="inline-flex items-center gap-1.5 rounded-2xl bg-indigo-600 px-3.5 py-2 text-xs font-extrabold text-white hover:bg-indigo-700 transition shadow-xs cursor-pointer">
                    <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                    <span>Auto Fill 3 Days</span>
                </button>

                <button type="button" onclick="clearAllDemo()"
                        class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                    <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
                    <span>Clear</span>
                </button>

                <button type="button" onclick="downloadDayTxt()"
                        class="inline-flex items-center gap-1.5 rounded-2xl border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-xs font-extrabold text-emerald-900 hover:bg-emerald-100 transition shadow-2xs cursor-pointer"
                        title="Download current day report as plain text TXT">
                    <i data-lucide="download" class="h-3.5 w-3.5 text-emerald-700"></i>
                    <span>Download Day TXT</span>
                </button>

                <button type="button" onclick="download3DaysTxt()"
                        class="inline-flex items-center gap-1.5 rounded-2xl border border-purple-300 bg-purple-50 px-3.5 py-2 text-xs font-extrabold text-purple-900 hover:bg-purple-100 transition shadow-2xs cursor-pointer"
                        title="Download full 3-day report as plain text TXT">
                    <i data-lucide="file-text" class="h-3.5 w-3.5 text-purple-700"></i>
                    <span>Download 3 Days TXT</span>
                </button>

                <button type="button" id="toggle-petty-btn" onclick="togglePettySectionVisibility()"
                        class="inline-flex items-center gap-1.5 rounded-2xl border border-amber-300 bg-amber-50 px-3.5 py-2 text-xs font-extrabold text-amber-900 hover:bg-amber-100 transition shadow-2xs cursor-pointer"
                        title="Toggle Petty section visibility in Demo">
                    <i data-lucide="wallet" class="h-3.5 w-3.5 text-amber-600"></i>
                    <span>Show Petty: <strong id="toggle-petty-status">ON</strong></span>
                </button>

                <a href="{{ route('admin.cashbook.settings.shop', ['shop' => $currentShop->shop_id]) }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-slate-100 px-3.5 py-2 text-xs font-extrabold text-slate-800 hover:bg-slate-200 transition">
                    <i data-lucide="settings-2" class="h-3.5 w-3.5"></i>
                    <span>Open Settings</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Sticky Day Switcher Bar -->
    <div class="sticky top-2 z-30 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white/95 backdrop-blur-md p-2 shadow-xs">
        <div class="flex items-center gap-1.5">
            <button type="button" id="tab-day-1" onclick="switchDay(1)"
                    class="tab-btn inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-black transition border bg-indigo-600 text-white border-indigo-600 shadow-2xs cursor-pointer">
                <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                <span>Day 1</span>
            </button>
            <button type="button" id="tab-day-2" onclick="switchDay(2)"
                    class="tab-btn inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold transition border bg-white text-slate-600 border-slate-200 hover:bg-slate-50 cursor-pointer">
                <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                <span>Day 2</span>
            </button>
            <button type="button" id="tab-day-3" onclick="switchDay(3)"
                    class="tab-btn inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold transition border bg-white text-slate-600 border-slate-200 hover:bg-slate-50 cursor-pointer">
                <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                <span>Day 3</span>
            </button>
        </div>
        <div class="text-xs font-extrabold text-slate-500 pr-2" id="current-day-label">
            Active View: <span class="text-slate-950 font-black">Day 1</span>
        </div>
    </div>

    <!-- MAIN TWO-SIDE LAYOUT (Desktop: 2 columns, Mobile: stacked) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        
        <!-- LEFT SIDE: REUSABLE MOBILE-FIRST BILL INPUT FORM -->
        <div class="space-y-5">
            <div class="flex items-center justify-between px-1">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <i data-lucide="receipt" class="h-4 w-4 text-indigo-600"></i>
                    Shop Activity (Inputs)
                </h2>
                <span class="text-[11px] font-bold text-slate-400">Header-Driven Form</span>
            </div>

            <!-- Simulation Opening Balances Accordion -->
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-xs space-y-3">
                <button type="button" onclick="toggleOpeningBalances()"
                        class="w-full flex items-center justify-between text-left font-extrabold text-xs text-slate-800 hover:text-slate-950 transition cursor-pointer">
                    <span class="flex items-center gap-2">
                        <i data-lucide="wallet" class="h-4 w-4 text-slate-500"></i>
                        Simulation Opening Balances
                    </span>
                    <i data-lucide="chevron-down" id="opening-balances-icon" class="h-4 w-4 text-slate-400 transition-transform"></i>
                </button>

                <div id="opening-balances-content" class="hidden space-y-3 pt-2 border-t border-slate-100">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-[11px] font-bold text-slate-600">Opening Shop Balance</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-slate-400">₹</span>
                                <input type="number" id="input-open-shop-balance" value="15000" min="0" step="0.01" inputmode="decimal"
                                       oninput="onOpeningBalanceChange()"
                                       class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-7 pr-3 text-right text-sm font-extrabold text-slate-950 focus:border-indigo-600 focus:bg-white focus:outline-none">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[11px] font-bold text-slate-600">Opening Petty</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-slate-400">₹</span>
                                <input type="number" id="input-open-petty" value="5000" min="0" step="0.01" inputmode="decimal"
                                       oninput="onOpeningBalanceChange()"
                                       class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-7 pr-3 text-right text-sm font-extrabold text-slate-950 focus:border-indigo-600 focus:bg-white focus:outline-none">
                            </div>
                        </div>
                    </div>

                    @if($accountsList->isNotEmpty())
                        <div class="pt-2 border-t border-slate-100 space-y-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Company Accounts Initial Demo Positions</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($accountsList as $acc)
                                    <div class="space-y-1">
                                        <label class="text-[11px] font-semibold text-slate-700 truncate">{{ $acc->name }} ({{ $acc->bank_name }})</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-slate-400">₹</span>
                                            <input type="number"
                                                   id="input-open-account-{{ $acc->id }}"
                                                   data-account-id="{{ $acc->id }}"
                                                   value="{{ $loop->first ? '50000' : '25000' }}"
                                                   min="0"
                                                   step="0.01"
                                                   inputmode="decimal"
                                                   oninput="onOpeningBalanceChange()"
                                                   class="demo-account-opening h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-7 pr-3 text-right text-sm font-extrabold text-slate-950 focus:border-indigo-600 focus:bg-white focus:outline-none">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 1. MAJOR SECTION: INCOME -->
            <div class="rounded-3xl border border-emerald-200/80 bg-white p-5 shadow-xs space-y-4">
                <div onclick="toggleDemoSection('income')" class="flex items-center justify-between cursor-pointer select-none py-1 min-h-[44px]">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <i data-lucide="trending-up" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-black uppercase tracking-wider text-slate-950">Income</h2>
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-extrabold text-emerald-700 border border-emerald-200">Section</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span id="demo-section-total-income" class="font-mono text-sm font-black text-emerald-700 bg-emerald-50/80 px-2.5 py-1 rounded-xl border border-emerald-200/60">₹0.00</span>
                        <span class="text-slate-400 hover:text-slate-700 transition">
                            <i id="demo-section-icon-income" data-lucide="chevron-down" class="h-5 w-5"></i>
                        </span>
                    </div>
                </div>

                <div id="demo-section-body-income" class="space-y-4 pt-2 border-t border-emerald-100">
                    @foreach($incomeSections as $section)
                        @php
                            $sectionSettings = $section['settings'];
                            $headerId = $section['id'];
                            $headerName = $section['name'];
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/40 p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-200/80 pb-2">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">{{ $headerName }}</h3>
                                    @if(!empty($section['product_tagging_enabled']))
                                        <span class="rounded-full bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[10px] font-black text-indigo-700 inline-flex items-center gap-1">
                                            <i data-lucide="tag" class="h-3 w-3"></i> Product Tagging
                                        </span>
                                    @endif
                                </div>
                                @if(!empty($section['product_tagging_enabled']))
                                    <button type="button" onclick="openDemoProductModal('{{ $headerId }}', '{{ addslashes($headerName) }}')"
                                            class="inline-flex items-center gap-1 rounded-xl bg-indigo-50 border border-indigo-200 px-2.5 py-1 text-xs font-black text-indigo-700 hover:bg-indigo-100 transition shadow-2xs cursor-pointer">
                                        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                                        <span>+ Add Product</span>
                                    </button>
                                @endif
                            </div>

                            <div class="divide-y divide-slate-100 bg-white rounded-xl p-2 border border-slate-200/60">
                                @foreach($sectionSettings as $s)
                                    <div class="py-2 flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1 space-y-0.5">
                                            <label for="input-s-{{ $s->id }}" class="text-xs font-bold text-slate-900 block truncate cursor-pointer">
                                                {{ $s->entryType?->name }}
                                            </label>
                                            <div class="text-[11px] font-medium text-slate-500 truncate flex items-center gap-1">
                                                @if($s->companyAccount)
                                                    <i data-lucide="landmark" class="h-3 w-3 text-indigo-600 inline"></i>
                                                    <span>{{ $s->companyAccount->name }} · Direct Company</span>
                                                @else
                                                    <i data-lucide="banknote" class="h-3 w-3 text-emerald-600 inline"></i>
                                                    <span>Held at Shop</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="relative w-36 sm:w-44 flex-shrink-0">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-semibold text-slate-400">₹</span>
                                            <input type="number"
                                                   id="input-s-{{ $s->id }}"
                                                   data-setting-id="{{ $s->id }}"
                                                   data-category="income"
                                                   min="0"
                                                   step="0.01"
                                                   inputmode="decimal"
                                                   placeholder="0"
                                                   oninput="onInputChange(this)"
                                                   onblur="formatInputOnBlur(this)"
                                                   class="demo-money-input h-11 w-full rounded-xl border border-slate-200 bg-white pl-7 pr-3 text-right text-base font-extrabold text-slate-950 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/10 focus:outline-none shadow-2xs">
                                        </div>
                                    </div>

                                    @php
                                        $sCode = strtolower((string) ($s->entryType?->code ?? ''));
                                        $sRequiresNote = $s->requiresNote() || in_array($sCode, ['other_income', 'other_expense'], true);
                                        $sShowNote = $s->note_enabled || $sRequiresNote;
                                    @endphp
                                    @if($sShowNote)
                                        <div class="mt-2 rounded-2xl border border-amber-200/80 bg-amber-50/40 p-3 space-y-1.5" id="note-block-{{ $s->id }}">
                                            <div class="flex items-center justify-between">
                                                <label for="input-note-{{ $s->id }}" class="text-[11px] font-extrabold text-slate-800 flex items-center gap-1">
                                                    <i data-lucide="file-text" class="h-3.5 w-3.5 text-amber-600"></i>
                                                    <span>Note</span>
                                                    <span class="text-slate-400 font-semibold text-xs" id="note-star-{{ $s->id }}">{{ $sRequiresNote ? '*' : '(Optional)' }}</span>
                                                </label>
                                                <span class="text-[10px] font-bold text-slate-400" id="note-hint-{{ $s->id }}">{{ $sRequiresNote ? 'Required when amount > ₹0' : 'Optional' }}</span>
                                            </div>
                                            <input type="text"
                                                   id="input-note-{{ $s->id }}"
                                                   data-setting-id="{{ $s->id }}"
                                                   placeholder="Describe this income..."
                                                   oninput="onNoteInputChange(this, {{ $s->id }})"
                                                   class="demo-note-input h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/10 focus:outline-none shadow-2xs">
                                            <div id="note-error-{{ $s->id }}" class="text-[11px] font-black text-rose-600 flex items-center gap-1 hidden">
                                                <i data-lucide="alert-circle" class="h-3.5 w-3.5"></i>
                                                <span>Please add a note for {{ $s->entryType?->name ?? 'Income' }}.</span>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            <div id="product-rows-container-{{ $headerId }}" class="space-y-1"></div>

                            <div class="pt-2 flex items-center justify-between text-xs font-black uppercase text-slate-950">
                                <span>Total {{ $headerName }}</span>
                                <span class="font-mono text-emerald-700 text-xs font-black" id="header-total-{{ $headerId }}">₹0.00</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 2. MAJOR SECTION: EXPENSE -->
            <div class="rounded-3xl border border-rose-200/80 bg-white p-5 shadow-xs space-y-4">
                <div onclick="toggleDemoSection('expense')" class="flex items-center justify-between cursor-pointer select-none py-1 min-h-[44px]">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-700 border border-rose-200">
                            <i data-lucide="trending-down" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-black uppercase tracking-wider text-slate-950">Expense</h2>
                                <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-extrabold text-rose-700 border border-rose-200">Section</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span id="demo-section-total-expense" class="font-mono text-sm font-black text-rose-700 bg-rose-50/80 px-2.5 py-1 rounded-xl border border-rose-200/60">-₹0.00</span>
                        <span class="text-slate-400 hover:text-slate-700 transition">
                            <i id="demo-section-icon-expense" data-lucide="chevron-down" class="h-5 w-5"></i>
                        </span>
                    </div>
                </div>

                <div id="demo-section-body-expense" class="space-y-4 pt-2 border-t border-rose-100">
                    @foreach($expenseSections as $section)
                        @php
                            $sectionSettings = $section['settings'];
                            $headerId = $section['id'];
                            $headerName = $section['name'];
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/40 p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-200/80 pb-2">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">{{ $headerName }}</h3>
                                    @if(!empty($section['product_tagging_enabled']))
                                        <span class="rounded-full bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[10px] font-black text-indigo-700 inline-flex items-center gap-1">
                                            <i data-lucide="tag" class="h-3 w-3"></i> Product Tagging
                                        </span>
                                    @endif
                                </div>
                                @if(!empty($section['product_tagging_enabled']))
                                    <button type="button" onclick="openDemoProductModal('{{ $headerId }}', '{{ addslashes($headerName) }}')"
                                            class="inline-flex items-center gap-1 rounded-xl bg-indigo-50 border border-indigo-200 px-2.5 py-1 text-xs font-black text-indigo-700 hover:bg-indigo-100 transition shadow-2xs cursor-pointer">
                                        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                                        <span>+ Add Product</span>
                                    </button>
                                @endif
                            </div>

                            <div class="divide-y divide-slate-100 bg-white rounded-xl p-2 border border-slate-200/60">
                                @foreach($sectionSettings as $s)
                                    @php
                                        $src = $s->default_funding_source ?: 'sales';
                                    @endphp
                                    <div class="py-2 flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1 space-y-0.5">
                                            <label for="input-s-{{ $s->id }}" class="text-xs font-bold text-slate-900 block truncate cursor-pointer">
                                                {{ $s->entryType?->name }}
                                            </label>
                                            <div class="text-[11px] font-medium text-slate-500 truncate flex items-center gap-1">
                                                @if($src === 'petty')
                                                    <i data-lucide="wallet" class="h-3 w-3 text-amber-600 inline"></i>
                                                    <span>Paid from Petty</span>
                                                @elseif($src === 'company' || $src === 'bank' || $src === 'external')
                                                    <i data-lucide="building-2" class="h-3 w-3 text-indigo-600 inline"></i>
                                                    <span>Paid by Company</span>
                                                @else
                                                    <i data-lucide="banknote" class="h-3 w-3 text-slate-600 inline"></i>
                                                    <span>Paid from Shop Balance</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="relative w-36 sm:w-44 flex-shrink-0">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-semibold text-slate-400">₹</span>
                                            <input type="number"
                                                   id="input-s-{{ $s->id }}"
                                                   data-setting-id="{{ $s->id }}"
                                                   data-category="{{ $s->is_cash_purchase ? 'cash_purchase' : 'expense' }}"
                                                   min="0"
                                                   step="0.01"
                                                   inputmode="decimal"
                                                   placeholder="0"
                                                   oninput="onInputChange(this)"
                                                   onblur="formatInputOnBlur(this)"
                                                   class="demo-money-input h-11 w-full rounded-xl border border-slate-200 bg-white pl-7 pr-3 text-right text-base font-extrabold text-slate-950 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/10 focus:outline-none shadow-2xs">
                                        </div>
                                    </div>

                                    @php
                                        $sCode = strtolower((string) ($s->entryType?->code ?? ''));
                                        $sRequiresNote = $s->requiresNote() || in_array($sCode, ['other_income', 'other_expense'], true);
                                        $sShowNote = $s->note_enabled || $sRequiresNote;
                                    @endphp
                                    @if($sShowNote)
                                        <div class="mt-2 rounded-2xl border border-amber-200/80 bg-amber-50/40 p-3 space-y-1.5" id="note-block-{{ $s->id }}">
                                            <div class="flex items-center justify-between">
                                                <label for="input-note-{{ $s->id }}" class="text-[11px] font-extrabold text-slate-800 flex items-center gap-1">
                                                    <i data-lucide="file-text" class="h-3.5 w-3.5 text-amber-600"></i>
                                                    <span>Note</span>
                                                    <span class="text-slate-400 font-semibold text-xs" id="note-star-{{ $s->id }}">{{ $sRequiresNote ? '*' : '(Optional)' }}</span>
                                                </label>
                                                <span class="text-[10px] font-bold text-slate-400" id="note-hint-{{ $s->id }}">{{ $sRequiresNote ? 'Required when amount > ₹0' : 'Optional' }}</span>
                                            </div>
                                            <input type="text"
                                                   id="input-note-{{ $s->id }}"
                                                   data-setting-id="{{ $s->id }}"
                                                   placeholder="Describe this expense..."
                                                   oninput="onNoteInputChange(this, {{ $s->id }})"
                                                   class="demo-note-input h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/10 focus:outline-none shadow-2xs">
                                            <div id="note-error-{{ $s->id }}" class="text-[11px] font-black text-rose-600 flex items-center gap-1 hidden">
                                                <i data-lucide="alert-circle" class="h-3.5 w-3.5"></i>
                                                <span>Please add a note for {{ $s->entryType?->name ?? 'Other' }}.</span>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            <div id="product-rows-container-{{ $headerId }}" class="space-y-1"></div>

                            <div class="pt-2 flex items-center justify-between text-xs font-black uppercase text-slate-950">
                                <span>Total {{ $headerName }}</span>
                                <span class="font-mono text-rose-700 text-xs font-black" id="header-total-{{ $headerId }}">₹0.00</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 3. MAJOR SECTION: OTHERS -->
            <div class="rounded-3xl border border-indigo-200/80 bg-white p-5 shadow-xs space-y-4">
                <div onclick="toggleDemoSection('others')" class="flex items-center justify-between cursor-pointer select-none py-1 min-h-[44px]">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200">
                            <i data-lucide="arrow-right-left" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-black uppercase tracking-wider text-slate-950">Others</h2>
                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-extrabold text-indigo-700 border border-indigo-200">Section</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span id="demo-section-total-others" class="font-mono text-sm font-black text-indigo-700 bg-indigo-50/80 px-2.5 py-1 rounded-xl border border-indigo-200/60">₹0.00</span>
                        <span class="text-slate-400 hover:text-slate-700 transition">
                            <i id="demo-section-icon-others" data-lucide="chevron-down" class="h-5 w-5"></i>
                        </span>
                    </div>
                </div>

                <div id="demo-section-body-others" class="space-y-4 pt-2 border-t border-indigo-100">
                    @php
                        $transferList = $transferSettings ?? collect();
                    @endphp
                    @if($transferList->isNotEmpty())
                        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/30 p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-indigo-100 pb-2">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Transfers &amp; Settlements</h3>
                                </div>
                                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-indigo-100 text-indigo-800 border border-indigo-200">Movement</span>
                            </div>

                            <div class="divide-y divide-indigo-100/60 bg-white rounded-xl p-2 border border-indigo-200/60">
                                @foreach($transferList as $tSetting)
                                    @php
                                        $src = $tSetting->default_funding_source ?? 'none';
                                        $settle = $tSetting->settlement_behavior ?? 'none';
                                        $petty = $tSetting->petty_behavior ?? 'none';
                                        $pending = $tSetting->company_pending_behavior ?? 'none';

                                        if ($src === 'sales' && $petty === 'increase') {
                                            $moveLabel = 'Shop Balance → Petty';
                                        } elseif ($src === 'company' && $petty === 'increase') {
                                            $moveLabel = 'Company → Petty';
                                        } elseif ($src === 'sales' && $settle === 'decrease') {
                                            $moveLabel = 'Shop Balance → Company';
                                        } elseif ($src === 'company' && $pending === 'decrease') {
                                            $moveLabel = 'Company → Shop';
                                        } elseif ($src === 'company' && $pending === 'none' && $petty === 'none' && $settle === 'none') {
                                            $moveLabel = 'Company → Vendor';
                                        } elseif ($src === 'bank' && $petty === 'increase') {
                                            $moveLabel = 'Bank → Petty';
                                        } elseif ($src === 'petty' && $petty === 'decrease') {
                                            $moveLabel = 'Petty → Company';
                                        } elseif ($src === 'sales') {
                                            $moveLabel = 'Shop Balance → Settlement';
                                        } elseif ($src === 'company') {
                                            $moveLabel = 'Company → Settlement';
                                        } else {
                                            $moveLabel = 'Transfer / Settlement';
                                        }
                                    @endphp
                                    <div class="py-2 flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1 space-y-0.5">
                                            <label for="input-s-{{ $tSetting->id }}" class="text-xs font-bold text-slate-900 block truncate cursor-pointer">
                                                {{ $tSetting->entryType?->name }}
                                            </label>
                                            <div class="text-[11px] font-semibold text-indigo-700 truncate flex items-center gap-1">
                                                <i data-lucide="arrow-right-left" class="h-3 w-3 inline text-indigo-600"></i>
                                                <span>{{ $moveLabel }}</span>
                                            </div>
                                        </div>

                                        <div class="relative w-36 sm:w-44 flex-shrink-0">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-semibold text-slate-400">₹</span>
                                            <input type="number"
                                                   id="input-s-{{ $tSetting->id }}"
                                                   data-setting-id="{{ $tSetting->id }}"
                                                   data-category="transfer"
                                                   min="0"
                                                   step="0.01"
                                                   inputmode="decimal"
                                                   placeholder="0"
                                                   oninput="onInputChange(this)"
                                                   onblur="formatInputOnBlur(this)"
                                                   class="demo-money-input h-11 w-full rounded-xl border border-slate-200 bg-white pl-7 pr-3 text-right text-base font-extrabold text-slate-950 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/10 focus:outline-none shadow-2xs">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($relationList->isNotEmpty())
                        <div class="rounded-2xl border border-purple-200 bg-purple-50/20 p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-purple-100 pb-2">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Supermarket Settlement</h3>
                                </div>
                                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-purple-100 text-purple-800 border border-purple-200">Relation</span>
                            </div>

                            @if($relationUniqueItems->isNotEmpty())
                                <div class="divide-y divide-purple-100/60 bg-white rounded-xl p-2 border border-purple-200/60">
                                    @foreach($relationUniqueItems as $relItem)
                                        @php
                                            $setting = $relItem->setting;
                                            $isSub = strtolower((string) ($relItem->role ?? 'add')) === 'subtract';
                                        @endphp
                                        @if($setting)
                                            <div class="py-2 flex items-center justify-between gap-3">
                                                <div class="min-w-0 flex-1 space-y-0.5">
                                                    <label for="input-s-{{ $setting->id }}" class="text-xs font-bold text-slate-900 block truncate cursor-pointer">
                                                        {{ $setting->entryType?->name }}
                                                    </label>
                                                    <div class="text-[11px] font-semibold {{ $isSub ? 'text-rose-600' : 'text-purple-700' }} truncate">
                                                        {{ $isSub ? 'Subtract from Settlement' : 'Add to Settlement' }}
                                                    </div>
                                                </div>

                                                <div class="relative w-36 sm:w-44 flex-shrink-0">
                                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-semibold text-slate-400">₹</span>
                                                    <input type="number"
                                                           id="input-s-{{ $setting->id }}"
                                                           data-setting-id="{{ $setting->id }}"
                                                           data-category="relation"
                                                           min="0"
                                                           step="0.01"
                                                           inputmode="decimal"
                                                           placeholder="0"
                                                           oninput="onInputChange(this)"
                                                           onblur="formatInputOnBlur(this)"
                                                           class="demo-money-input h-11 w-full rounded-xl border border-slate-200 bg-white pl-7 pr-3 text-right text-base font-extrabold text-slate-950 focus:border-purple-600 focus:ring-2 focus:ring-purple-600/10 focus:outline-none shadow-2xs">
                                                </div>
                                            </div>

                                            @php
                                                $rCode = strtolower((string) ($setting->entryType?->code ?? ''));
                                                $rRequiresNote = $setting->requiresNote() || in_array($rCode, ['other_income', 'other_expense'], true);
                                                $rShowNote = $setting->note_enabled || $rRequiresNote;
                                            @endphp
                                            @if($rShowNote)
                                                <div class="mt-2 rounded-2xl border border-amber-200/80 bg-amber-50/40 p-3 space-y-1.5" id="note-block-{{ $setting->id }}">
                                                    <div class="flex items-center justify-between">
                                                        <label for="input-note-{{ $setting->id }}" class="text-[11px] font-extrabold text-slate-800 flex items-center gap-1">
                                                            <i data-lucide="file-text" class="h-3.5 w-3.5 text-amber-600"></i>
                                                            <span>Note</span>
                                                            <span class="text-slate-400 font-semibold text-xs" id="note-star-{{ $setting->id }}">{{ $rRequiresNote ? '*' : '(Optional)' }}</span>
                                                        </label>
                                                        <span class="text-[10px] font-bold text-slate-400" id="note-hint-{{ $setting->id }}">{{ $rRequiresNote ? 'Required when amount > ₹0' : 'Optional' }}</span>
                                                    </div>
                                                    <input type="text"
                                                           id="input-note-{{ $setting->id }}"
                                                           data-setting-id="{{ $setting->id }}"
                                                           placeholder="Describe this item..."
                                                           oninput="onNoteInputChange(this, {{ $setting->id }})"
                                                           class="demo-note-input h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/10 focus:outline-none shadow-2xs">
                                                    <div id="note-error-{{ $setting->id }}" class="text-[11px] font-black text-rose-600 flex items-center gap-1 hidden">
                                                        <i data-lucide="alert-circle" class="h-3.5 w-3.5"></i>
                                                        <span>Please add a note for {{ $setting->entryType?->name ?? 'this item' }}.</span>
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <div class="pt-2 space-y-1.5 border-t border-purple-100/80">
                                <span class="text-[11px] font-extrabold text-purple-900 uppercase tracking-wider">Related from Header Sections</span>
                                <div id="relation-linked-items-list" class="space-y-1 text-xs"></div>
                            </div>

                            <div class="pt-2 border-t-2 border-purple-200 space-y-1 text-xs font-bold text-slate-800">
                                <div class="flex justify-between">
                                    <span class="text-slate-600">Gross Additions</span>
                                    <span class="font-mono text-purple-900" id="rel-gross-add">₹0.00</span>
                                </div>
                                <div class="flex justify-between text-rose-700">
                                    <span>Less: Deductions</span>
                                    <span class="font-mono" id="rel-gross-sub">-₹0.00</span>
                                </div>
                                <div class="pt-2 border-t border-purple-200 flex items-center justify-between text-xs font-black uppercase text-slate-950">
                                    <span>Net Settlement</span>
                                    <span class="font-mono text-purple-900 text-sm font-black" id="day-relation-net">₹0.00</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 4. MAJOR SECTION: PETTY -->
            <div id="demo-petty-section" class="rounded-3xl border border-amber-200/80 bg-white p-5 shadow-xs space-y-4">
                <div onclick="toggleDemoSection('petty')" class="flex items-center justify-between cursor-pointer select-none py-1 min-h-[44px]">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-800 border border-amber-200">
                            <i data-lucide="wallet" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-black uppercase tracking-wider text-slate-950">Petty</h2>
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-extrabold text-amber-800 border border-amber-200">Section</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span id="demo-section-total-petty" class="font-mono text-sm font-black text-amber-900 bg-amber-50/80 px-2.5 py-1 rounded-xl border border-amber-200/60">₹5,000.00</span>
                        <span class="text-slate-400 hover:text-slate-700 transition">
                            <i id="demo-section-icon-petty" data-lucide="chevron-right" class="h-5 w-5"></i>
                        </span>
                    </div>
                </div>

                <div id="demo-section-body-petty" class="space-y-3 pt-2 border-t border-amber-100 hidden">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-700 bg-amber-50/40 p-3 rounded-2xl border border-amber-100">
                        <span>Opening Petty Balance</span>
                        <span class="font-mono text-slate-900 font-black text-sm" id="demo-petty-opening">₹5,000.00</span>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-[11px] font-extrabold uppercase tracking-wider text-amber-900">
                            <span>Used Today</span>
                            <span id="demo-petty-used-count" class="text-[10px] text-amber-700/80 font-bold">0 entries</span>
                        </div>
                        <div id="demo-petty-used-list" class="space-y-1 text-xs">
                            <div class="text-slate-400 font-bold text-[11px] p-2 text-center">No petty-funded expenses today.</div>
                        </div>
                    </div>

                    <div class="pt-3 border-t-2 border-amber-200 space-y-1.5 text-xs font-bold text-slate-800">
                        <div class="flex justify-between text-rose-700">
                            <span>Total Petty Used</span>
                            <span class="font-mono font-black" id="demo-petty-used-total">-₹0.00</span>
                        </div>
                        <div class="pt-2 border-t border-amber-200 flex items-center justify-between text-xs font-black uppercase text-slate-950">
                            <span>Closing Petty Balance</span>
                            <span class="font-mono text-amber-900 text-base font-black" id="demo-petty-closing">₹5,000.00</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT SIDE: CASH MOVEMENT & SUMMARY (Calculated Real-Time Results) -->
        <div class="space-y-5">
            <!-- 5. MAJOR SECTION: SUMMARY -->
            <div class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-xs space-y-4">
                <div onclick="toggleDemoSection('summary')" class="flex items-center justify-between cursor-pointer select-none py-1 min-h-[44px]">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200">
                            <i data-lucide="calculator" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-black uppercase tracking-wider text-slate-950">Summary</h2>
                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-extrabold text-indigo-700 border border-indigo-200">Daily Overview</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span id="demo-section-total-summary" class="font-mono text-sm font-black text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-xl border border-emerald-200/80">₹0.00</span>
                        <span class="text-slate-400 hover:text-slate-700 transition">
                            <i id="demo-section-icon-summary" data-lucide="chevron-down" class="h-5 w-5"></i>
                        </span>
                    </div>
                </div>

                <div id="demo-section-body-summary" class="space-y-4 pt-2 border-t border-slate-100">
                    <!-- DAILY NET POSITION -->
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200/80 pb-2">
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                                <i data-lucide="calculator" class="h-4 w-4 text-indigo-600"></i>
                                Daily Net Position
                            </h3>
                            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Calculated
                            </span>
                        </div>

                        <div class="space-y-1.5 text-xs font-semibold text-slate-700">
                            <div class="flex justify-between">
                                <span>Total Sales / Income</span>
                                <span class="font-mono text-emerald-700 font-bold" id="bill-sales">₹0.00</span>
                            </div>
                            <div class="flex justify-between text-rose-700">
                                <span>Total Expenses</span>
                                <span class="font-mono font-bold" id="bill-expenses">-₹0.00</span>
                            </div>
                            <div class="flex justify-between text-amber-700">
                                <span>Cash Purchase</span>
                                <span class="font-mono font-bold" id="bill-cash-purchase">-₹0.00</span>
                            </div>
                            <div class="flex justify-between text-purple-700">
                                <span>Settlement Paid</span>
                                <span class="font-mono font-bold" id="bill-settlement">-₹0.00</span>
                            </div>
                        </div>

                        <div class="pt-2.5 border-t border-slate-200/80 flex items-center justify-between">
                            <span class="text-xs font-black uppercase tracking-wider text-slate-900">Net Activity</span>
                            <span class="text-base font-black font-mono text-emerald-700" id="bill-net-activity">₹0.00</span>
                        </div>
                    </div>

                    <!-- SHOP BALANCE FOOTER -->
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/30 p-4 space-y-3">
                        <div class="flex items-center justify-between border-b border-emerald-200/80 pb-2">
                            <h3 class="text-xs font-black uppercase tracking-wider text-emerald-950 flex items-center gap-2">
                                <i data-lucide="store" class="h-4 w-4 text-emerald-700"></i>
                                Shop Balance
                            </h3>
                            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-900 border border-emerald-300">
                                Payable Result
                            </span>
                        </div>

                        <div class="space-y-1.5 text-xs font-semibold text-slate-700">
                            <div class="flex justify-between">
                                <span>Opening Shop Balance</span>
                                <span class="font-mono text-slate-900 font-bold" id="sb-opening">₹0.00</span>
                            </div>
                            <div class="flex justify-between text-rose-700">
                                <span>Settlement Paid</span>
                                <span class="font-mono font-bold" id="sb-settlement">-₹0.00</span>
                            </div>
                            <div class="flex justify-between text-emerald-700">
                                <span>Today's Shop-Held Money</span>
                                <span class="font-mono font-bold" id="sb-shop-held">+₹0.00</span>
                            </div>
                        </div>

                        <div class="pt-2.5 border-t border-emerald-200/80 flex items-center justify-between text-xs font-black text-emerald-950">
                            <span class="uppercase tracking-wider">Closing Shop Balance</span>
                            <span class="text-lg font-black font-mono text-emerald-900" id="sb-closing">₹0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between px-1">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <i data-lucide="arrow-right-left" class="h-4 w-4 text-emerald-600"></i>
                    Cash Movement &amp; Company Position
                </h2>
                <span class="text-[11px] font-bold text-slate-400">Read-only calculation</span>
            </div>

            <!-- 1. COMPANY PAYABLE -->
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <i data-lucide="banknote" class="h-4 w-4"></i>
                        </span>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Payable to Company</h3>
                    </div>
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                        Shop Cash
                    </span>
                </div>

                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between font-semibold text-slate-600">
                        <span>Opening Payable</span>
                        <span class="font-mono font-bold text-slate-900" id="move-open-payable">₹0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold text-emerald-700">
                        <span>+ Shop-held Collections Today</span>
                        <span class="font-mono font-bold" id="move-shop-collections">+₹0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold text-rose-700">
                        <span>- Eligible Settlement</span>
                        <span class="font-mono font-bold" id="move-eligible-settlement">-₹0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold text-slate-500">
                        <span>- Other Configured Payable Reductions</span>
                        <span class="font-mono font-bold text-slate-500">₹0.00</span>
                    </div>
                </div>

                <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-900">Closing Payable</span>
                    <span class="text-lg font-black font-mono text-emerald-700" id="move-closing-payable">₹0.00</span>
                </div>
            </div>

            <!-- 2. TODAY'S SHOP-HELD MONEY -->
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <i data-lucide="store" class="h-4 w-4 text-emerald-600"></i>
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Today's Shop-Held Money</h4>
                    </div>
                    <span class="text-[11px] font-bold text-slate-400" id="move-shop-held-count">0 entries</span>
                </div>

                <div id="move-shop-held-entries" class="space-y-1 text-xs">
                    <!-- Dynamic entries list -->
                </div>

                <div class="pt-2 border-t border-slate-100 flex justify-between items-center text-xs font-extrabold text-slate-900">
                    <span>Total Shop-Held Today</span>
                    <span class="font-mono text-sm font-black text-emerald-700" id="move-shop-held-total">₹0.00</span>
                </div>
            </div>

            <!-- 3. DIRECT TO COMPANY BANK ACCOUNTS -->
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <i data-lucide="landmark" class="h-4 w-4 text-indigo-600"></i>
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Direct to Company Bank Accounts</h4>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                        Bypasses Shop
                    </span>
                </div>

                <div id="move-company-accounts-list" class="space-y-3">
                    <!-- Dynamic grouped accounts list -->
                </div>
            </div>

            <!-- 4. COMPANY POSITION SUMMARY -->
            <div class="rounded-3xl border border-indigo-200 bg-indigo-50/40 p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-indigo-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <i data-lucide="shield-check" class="h-4 w-4 text-indigo-700"></i>
                        <h4 class="text-xs font-extrabold text-indigo-950 uppercase tracking-wider">Company Position (Active Day)</h4>
                    </div>
                </div>

                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between font-semibold text-slate-700">
                        <span>Direct to Company Accounts Today</span>
                        <span class="font-mono font-bold text-indigo-700" id="pos-direct-accounts">₹0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold text-slate-700">
                        <span>Still Held by Shop (Payable)</span>
                        <span class="font-mono font-bold text-emerald-700" id="pos-held-by-shop">₹0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold text-slate-700">
                        <span>Petty Balance</span>
                        <span class="font-mono font-bold text-amber-700" id="pos-petty-balance">₹0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold text-slate-700">
                        <span>Pending Settlement</span>
                        <span class="font-mono font-bold text-purple-700" id="pos-pending-settlement">₹0.00</span>
                    </div>
                </div>

                <div class="pt-2.5 border-t border-indigo-200 flex items-center justify-between text-xs font-black text-indigo-950">
                    <span>Total Company-Controlled / Receivable</span>
                    <span class="font-mono text-base text-indigo-900" id="pos-total-controlled">₹0.00</span>
                </div>
            </div>

            <!-- 5. PETTY MOVEMENT -->
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <i data-lucide="wallet" class="h-4 w-4 text-amber-600"></i>
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Petty Movement</h4>
                    </div>
                </div>

                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between font-semibold text-slate-600">
                        <span>Opening Petty</span>
                        <span class="font-mono font-bold text-slate-900" id="petty-open">₹0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold text-rose-600">
                        <span>- Expenses Paid from Petty</span>
                        <span class="font-mono font-bold" id="petty-expenses">-₹0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold text-amber-700">
                        <span>- Cash Purchase Paid from Petty</span>
                        <span class="font-mono font-bold" id="petty-cash-purchase">-₹0.00</span>
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-100 flex justify-between items-center text-xs font-extrabold text-slate-900">
                    <span>Closing Petty Balance</span>
                    <span class="font-mono text-sm font-black text-amber-700" id="petty-closing">₹0.00</span>
                </div>
            </div>

            <!-- 6. COMPANY-PAID EXPENSES -->
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <i data-lucide="building-2" class="h-4 w-4 text-indigo-600"></i>
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Paid Directly by Company</h4>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400">Does not touch Shop Cash</span>
                </div>

                <div id="move-company-paid-list" class="space-y-1 text-xs">
                    <!-- List of company paid expenses -->
                </div>
            </div>

            <!-- 7. SETTLEMENT MOVEMENT -->
            @if($relationList->isNotEmpty())
                <div class="rounded-3xl border border-purple-200 bg-white p-5 shadow-xs space-y-3">
                    <div class="flex items-center justify-between border-b border-purple-100 pb-2.5">
                        <div class="flex items-center gap-2">
                            <i data-lucide="scale" class="h-4 w-4 text-purple-700"></i>
                            <h4 class="text-xs font-extrabold text-purple-950 uppercase tracking-wider">Settlement Movement</h4>
                        </div>
                    </div>

                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between font-semibold text-slate-600">
                            <span>Calculated Settlement</span>
                            <span class="font-mono font-bold text-purple-900" id="settle-calculated">₹0.00</span>
                        </div>
                        <div class="flex justify-between font-semibold text-slate-600">
                            <span>Eligible Today (Prev-Day Balance)</span>
                            <span class="font-mono font-bold text-slate-800" id="settle-eligible">₹0.00</span>
                        </div>
                        <div class="flex justify-between font-semibold text-emerald-700">
                            <span>Settled Today</span>
                            <span class="font-mono font-bold" id="settle-settled">₹0.00</span>
                        </div>
                        <div class="flex justify-between font-semibold text-amber-700">
                            <span>Remaining Pending Settlement</span>
                            <span class="font-mono font-bold" id="settle-remaining">₹0.00</span>
                        </div>
                    </div>
                </div>
            @endif

        </div>

    </div>

    <!-- BOTTOM AREA: 3-DAY SUMMARY & FINAL POSITIONS (INVOICE STYLE) -->
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs space-y-5 border-t-4 border-t-indigo-600">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-base font-black tracking-tight text-slate-950 flex items-center gap-2">
                    <i data-lucide="bar-chart-3" class="h-4.5 w-4.5 text-indigo-600"></i>
                    3-Day Activity & Cash Movement Overview
                </h2>
                <p class="text-xs font-semibold text-slate-500">Cumulative totals across Day 1, Day 2, and Day 3.</p>
            </div>
            <span class="inline-flex items-center gap-1.5 text-xs font-extrabold text-indigo-700 bg-indigo-50 px-3 py-1 rounded-xl border border-indigo-200">
                <i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i>
                Multi-Day Dynamic Carryforward
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- 3-DAY ACTIVITY (INVOICE STYLE LEFT) -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5">3-Day Activity Totals</h3>
                <div class="space-y-1.5 text-xs font-bold text-slate-700">
                    <div class="flex justify-between">
                        <span>3-Day Total Sales</span>
                        <span class="font-mono text-emerald-700 font-black text-sm" id="sum3-sales">₹0.00</span>
                    </div>
                    <div class="flex justify-between text-rose-700">
                        <span>3-Day Total Expenses</span>
                        <span class="font-mono font-black" id="sum3-expenses">-₹0.00</span>
                    </div>
                    <div class="flex justify-between text-amber-700">
                        <span>3-Day Total Cash Purchase</span>
                        <span class="font-mono font-black" id="sum3-cash-purchase">-₹0.00</span>
                    </div>
                    <div class="flex justify-between text-purple-900">
                        <span>3-Day Settlement Calculated</span>
                        <span class="font-mono font-black" id="sum3-settlement-calc">₹0.00</span>
                    </div>
                </div>
            </div>

            <!-- 3-DAY CASH MOVEMENT (INVOICE STYLE RIGHT) -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 border-b border-slate-200 pb-1.5">3-Day Cash Movement</h3>
                <div class="space-y-1.5 text-xs font-bold text-slate-700">
                    <div class="flex justify-between text-indigo-700">
                        <span>Direct to Company Accounts</span>
                        <span class="font-mono font-black" id="sum3-direct-company">₹0.00</span>
                    </div>
                    <div class="flex justify-between text-emerald-700">
                        <span>Shop-Held Collections</span>
                        <span class="font-mono font-black" id="sum3-shop-collections">₹0.00</span>
                    </div>
                    <div class="flex justify-between text-purple-700">
                        <span>Settlement Paid</span>
                        <span class="font-mono font-black" id="sum3-settlement-paid">₹0.00</span>
                    </div>
                    <div class="flex justify-between text-amber-700">
                        <span>Petty Spent</span>
                        <span class="font-mono font-black" id="sum3-petty-spent">₹0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-800">
                        <span>Company-Paid Expenses</span>
                        <span class="font-mono font-black" id="sum3-company-paid">₹0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FINAL POSITIONS (DAY 3 CLOSING INVOICE CARD) -->
        <div class="rounded-3xl border border-indigo-200 bg-indigo-50/30 p-5 text-slate-950 space-y-4 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-indigo-100/80 pb-2.5">
                <h3 class="text-xs font-black uppercase tracking-wider text-indigo-950 flex items-center gap-2">
                    <i data-lucide="shield-check" class="h-4 w-4 text-indigo-700"></i>
                    Final Simulation Positions (End of Day 3)
                </h3>
                <span class="text-[10px] font-bold text-indigo-700/80">
                    Day 3 Closing Balances — NOT summed across days
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="rounded-2xl bg-white border border-indigo-100/80 p-3.5 space-y-0.5 shadow-2xs">
                    <span class="text-[10px] font-extrabold uppercase text-slate-500">Final Shop Payable</span>
                    <div class="text-lg font-black font-mono text-emerald-700" id="final-shop-payable">₹0.00</div>
                    <span class="text-[10px] text-slate-400 font-semibold block">Money still held by shop</span>
                </div>

                <div class="rounded-2xl bg-white border border-indigo-100/80 p-3.5 space-y-0.5 shadow-2xs">
                    <span class="text-[10px] font-extrabold uppercase text-slate-500">Final Petty Balance</span>
                    <div class="text-lg font-black font-mono text-amber-700" id="final-petty-balance">₹0.00</div>
                    <span class="text-[10px] text-slate-400 font-semibold block">Remaining petty cash</span>
                </div>

                <div class="rounded-2xl bg-white border border-indigo-100/80 p-3.5 space-y-0.5 shadow-2xs">
                    <span class="text-[10px] font-extrabold uppercase text-slate-500">Total Company Position</span>
                    <div class="text-lg font-black font-mono text-indigo-700" id="final-total-position">₹0.00</div>
                    <span class="text-[10px] text-slate-400 font-semibold block">Accounts + Shop Payable + Petty</span>
                </div>
            </div>
        </div>

        <!-- COMPANY ACCOUNT 3-DAY MOVEMENT -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                <i data-lucide="landmark" class="h-4 w-4 text-indigo-600"></i>
                Company Account 3-Day Breakdown
            </h3>

            <div id="company-accounts-3day-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Dynamic cards per account -->
            </div>
        </div>

    </div>

    <!-- DEMO PRODUCT SEARCH MODAL -->
    <div id="demo-product-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
        <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-950 inline-flex items-center gap-1.5" id="demo-product-modal-title">
                        <i data-lucide="tag" class="h-4 w-4 text-indigo-600"></i>
                        <span>Search Products</span>
                    </h3>
                    <p class="text-xs font-semibold text-slate-500" id="demo-product-modal-subtitle">Search Green Leaf Product catalog to tag detail rows.</p>
                </div>
                <button type="button" onclick="closeDemoProductModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="mt-4">
                <input type="text" id="demo-product-search-input" oninput="onDemoProductSearchInput()" placeholder="Search by product name or SKU..."
                       class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/10 focus:outline-none shadow-2xs">
            </div>

            <div id="demo-product-list" class="mt-4 space-y-2 max-h-72 overflow-y-auto">
                <div class="p-6 text-center text-xs font-bold text-slate-400">Search products...</div>
            </div>
        </div>
    </div>
</div>

<script>
    let activeDay = 1;

    let demoState = {
        1: {},
        2: {},
        3: {}
    };

    let openingBalances = {
        shopBalance: 15000,
        petty: 5000,
        accounts: {}
    };

    let dayResults = {};

    const settings = @json($settingsJson);
    const accounts = @json($accountsJson);
    const relations = @json($relationJson);
    const headers = @json($headersJson);

    document.addEventListener('DOMContentLoaded', function () {
        accounts.forEach((acc, idx) => {
            const input = document.getElementById('input-open-account-' + acc.id);
            if (input) {
                openingBalances.accounts[acc.id] = parseFloat(input.value) || (idx === 0 ? 50000 : 25000);
            } else {
                openingBalances.accounts[acc.id] = idx === 0 ? 50000 : 25000;
            }
        });

        applySectionCollapseState();
        recalculateDemo();
    });

    function toggleOpeningBalances() {
        const content = document.getElementById('opening-balances-content');
        const icon = document.getElementById('opening-balances-icon');
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.add('rotate-180');
        } else {
            content.classList.add('hidden');
            icon.classList.remove('rotate-180');
        }
    }

    function onOpeningBalanceChange() {
        const sbInput = document.getElementById('input-open-shop-balance');
        const pettyInput = document.getElementById('input-open-petty');

        openingBalances.shopBalance = parseFloat(sbInput.value) || 0;
        openingBalances.petty = parseFloat(pettyInput.value) || 0;

        accounts.forEach(acc => {
            const input = document.getElementById('input-open-account-' + acc.id);
            if (input) {
                openingBalances.accounts[acc.id] = parseFloat(input.value) || 0;
            }
        });

        recalculateDemo();
    }

    function switchDay(day) {
        // Preserve activeDay notes before switching
        document.querySelectorAll('.demo-note-input').forEach(input => {
            const sId = parseInt(input.getAttribute('data-setting-id'));
            if (sId) {
                demoState[activeDay] = demoState[activeDay] || {};
                demoState[activeDay].notes = demoState[activeDay].notes || {};
                demoState[activeDay].notes[sId] = input.value;
            }
        });

        activeDay = day;

        for (let d = 1; d <= 3; d++) {
            const btn = document.getElementById('tab-day-' + d);
            if (btn) {
                if (d === day) {
                    btn.className = 'tab-btn inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-black transition border bg-indigo-600 text-white border-indigo-600 shadow-2xs cursor-pointer';
                } else {
                    btn.className = 'tab-btn inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold transition border bg-white text-slate-600 border-slate-200 hover:bg-slate-50 cursor-pointer';
                }
            }
        }

        const label = document.getElementById('current-day-label');
        if (label) {
            label.innerHTML = 'Active View: <span class="text-slate-950 font-black">Day ' + day + '</span>';
        }

        // Sync input values for activeDay
        settings.forEach(s => {
            const input = document.getElementById('input-s-' + s.id);
            if (input) {
                const val = demoState[day][s.id];
                input.value = (val !== undefined && val !== null && val > 0) ? val : '';
            }
            const noteInput = document.getElementById('input-note-' + s.id);
            if (noteInput) {
                const noteVal = (demoState[day].notes && demoState[day].notes[s.id]) || '';
                noteInput.value = noteVal;
            }
        });

        renderAllProductRowsForActiveDay();
        recalculateDemo();
    }

    function onInputChange(inputElement) {
        const settingId = parseInt(inputElement.getAttribute('data-setting-id'));
        let val = parseFloat(inputElement.value);
        if (isNaN(val) || val < 0) {
            val = 0;
        }

        demoState[activeDay][settingId] = val;
        recalculateDemo();
    }

    function onNoteInputChange(inputElement, settingId) {
        demoState[activeDay] = demoState[activeDay] || {};
        demoState[activeDay].notes = demoState[activeDay].notes || {};
        demoState[activeDay].notes[settingId] = inputElement.value;
        validateDemoNotes();
    }

    function validateDemoNotes() {
        let allValid = true;
        const dayAmounts = demoState[activeDay] || {};
        const dayNotes = dayAmounts.notes || {};

        settings.forEach(s => {
            if (!s.requires_note) return;

            const amt = parseFloat(dayAmounts[s.id]) || 0;
            const noteVal = (dayNotes[s.id] || '').trim();

            const inputEl = document.getElementById('input-note-' + s.id);
            const errorEl = document.getElementById('note-error-' + s.id);
            const starEl = document.getElementById('note-star-' + s.id);

            if (amt > 0) {
                if (starEl) starEl.className = 'text-rose-600 font-black text-xs';
                if (noteVal.length === 0) {
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
            } else {
                if (starEl) starEl.className = 'text-slate-400 font-semibold text-xs';
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

    function autoFill3Days() {
        document.getElementById('input-open-shop-balance').value = '15000';
        document.getElementById('input-open-petty').value = '5000';
        openingBalances.shopBalance = 15000;
        openingBalances.petty = 5000;

        accounts.forEach((acc, idx) => {
            const val = idx === 0 ? 50000 : 25000;
            const input = document.getElementById('input-open-account-' + acc.id);
            if (input) input.value = val.toString();
            openingBalances.accounts[acc.id] = val;
        });

        const samples = {
            1: [12000, 5000, 8000, 2000, 4000, 560, 700, 3500, 1500, 1000],
            2: [13500, 6200, 9500, 2500, 4500, 600, 850, 4000, 1200, 1100],
            3: [11200, 4800, 7200, 1800, 3800, 500, 650, 2800, 1600, 900]
        };

        for (let d = 1; d <= 3; d++) {
            demoState[d] = demoState[d] || {};
            demoState[d].notes = demoState[d].notes || {};

            settings.forEach((s, idx) => {
                const sampleArray = samples[d] || [];
                const val = sampleArray[idx % sampleArray.length] || ((idx + 1) * 1000);
                demoState[d][s.id] = val;

                if (s.requires_note) {
                    if (val > 0) {
                        demoState[d].notes[s.id] = s.is_income
                            ? `Supplier refund / misc income (Day ${d})`
                            : `Emergency maintenance / misc expense (Day ${d})`;
                    } else {
                        demoState[d].notes[s.id] = '';
                    }
                }
            });
        }

        settings.forEach(s => {
            const input = document.getElementById('input-s-' + s.id);
            if (input) {
                const val = demoState[activeDay][s.id];
                input.value = val || '';
            }
            const noteInput = document.getElementById('input-note-' + s.id);
            if (noteInput) {
                noteInput.value = (demoState[activeDay].notes && demoState[activeDay].notes[s.id]) || '';
            }
        });

        renderAllProductRowsForActiveDay();
        recalculateDemo();
    }

    function clearAllDemo() {
        document.getElementById('input-open-shop-balance').value = '0';
        document.getElementById('input-open-petty').value = '0';
        openingBalances.shopBalance = 0;
        openingBalances.petty = 0;

        accounts.forEach(acc => {
            const input = document.getElementById('input-open-account-' + acc.id);
            if (input) input.value = '0';
            openingBalances.accounts[acc.id] = 0;
        });

        demoState = { 1: { notes: {} }, 2: { notes: {} }, 3: { notes: {} } };

        settings.forEach(s => {
            const input = document.getElementById('input-s-' + s.id);
            if (input) input.value = '';
            const noteInput = document.getElementById('input-note-' + s.id);
            if (noteInput) noteInput.value = '';
        });

        renderAllProductRowsForActiveDay();
        recalculateDemo();
    }

    function recalculateDemo() {
        let currentShopPayable = openingBalances.shopBalance;
        let currentPetty = openingBalances.petty;
        let currentAccountPositions = {};

        accounts.forEach(acc => {
            currentAccountPositions[acc.id] = openingBalances.accounts[acc.id] || 0;
        });

        dayResults = {};

        for (let d = 1; d <= 3; d++) {
            const dayAmounts = demoState[d] || {};
            const dayOpenPayable = currentShopPayable;
            const dayOpenPetty = currentPetty;
            const dayOpenAccountPositions = { ...currentAccountPositions };

            let daySales = 0;
            let dayExpenses = 0;
            let dayCashPurchase = 0;

            let cashCollectedAtShop = 0;
            let shopHeldSalesEntries = [];

            let directCompanyBankTotal = 0;
            let bankInflows = {};
            let directBankEntriesMap = {};

            let expensesPaidFromShopCash = 0;
            let expensesPaidFromPetty = 0;
            let expensesPaidDirectlyByCompany = 0;
            let companyPaidEntries = [];
            let pettyUsedEntries = [];

            settings.forEach(s => {
                const amt = parseFloat(dayAmounts[s.id]) || 0;
                if (amt <= 0) return;

                if (s.is_income) {
                    daySales += amt;
                    if (s.company_account_id) {
                        directCompanyBankTotal += amt;
                        bankInflows[s.company_account_id] = (bankInflows[s.company_account_id] || 0) + amt;
                        if (!directBankEntriesMap[s.company_account_id]) {
                            directBankEntriesMap[s.company_account_id] = [];
                        }
                        directBankEntriesMap[s.company_account_id].push({ name: s.name, amount: amt });
                    } else {
                        cashCollectedAtShop += amt;
                        shopHeldSalesEntries.push({ name: s.name, amount: amt });
                    }
                } else {
                    if (s.is_cash_purchase) {
                        dayCashPurchase += amt;
                    } else {
                        dayExpenses += amt;
                    }

                    const src = s.funding_source;
                    if (src === 'sales' || src === 'shop_balance') {
                        expensesPaidFromShopCash += amt;
                    } else if (src === 'petty') {
                        expensesPaidFromPetty += amt;
                        pettyUsedEntries.push({
                            name: s.name,
                            amount: amt
                        });
                    } else if (src === 'company' || src === 'bank' || src === 'external') {
                        expensesPaidDirectlyByCompany += amt;
                        companyPaidEntries.push({
                            name: s.name,
                            amount: amt,
                            sourceName: s.company_account_name || 'Company Account'
                        });
                    } else {
                        expensesPaidFromShopCash += amt;
                    }
                }
            });

            // Product Detail Rows Rollup
            headers.forEach(h => {
                const pRows = (dayAmounts.productRows && dayAmounts.productRows[h.id]) || [];
                pRows.forEach(pr => {
                    const pAmt = parseFloat(pr.amount) || 0;
                    if (pAmt <= 0) return;

                    if (h.type === 'income') {
                        daySales += pAmt;
                        cashCollectedAtShop += pAmt;
                        shopHeldSalesEntries.push({ name: `${h.name} (${pr.productName})`, amount: pAmt });
                    } else {
                        const hName = (h.name || '').toLowerCase();
                        if (hName.includes('cash purchase') || hName.includes('purchase')) {
                            dayCashPurchase += pAmt;
                        } else {
                            dayExpenses += pAmt;
                        }
                        expensesPaidFromShopCash += pAmt;
                    }
                });
            });

            // Relation Settlement Calculation
            let relationGrossAdd = 0;
            let relationGrossSub = 0;
            let relationNet = 0;
            let relationEligible = 0;
            let relationSettled = 0;
            let relationRemaining = 0;
            let relationItemsDetail = [];
            let relationLinkedItemsDetail = [];

            if (relations.length > 0) {
                const rel = relations[0];

                (rel.items || []).forEach(item => {
                    const itemAmt = parseFloat(dayAmounts[item.setting_id]) || 0;
                    const isSub = item.role === 'subtract';
                    if (isSub) {
                        relationGrossSub += itemAmt;
                    } else {
                        relationGrossAdd += itemAmt;
                    }

                    // Classify whether item was entered in header sections vs unique to relation
                    const isRenderedInHeader = settings.some(st => st.id === item.setting_id);

                    if (isRenderedInHeader) {
                        relationLinkedItemsDetail.push({
                            name: item.name,
                            role: item.role,
                            amount: itemAmt
                        });
                    } else {
                        relationItemsDetail.push({
                            name: item.name,
                            role: item.role,
                            amount: itemAmt
                        });
                    }
                });

                relationNet = relationGrossAdd - relationGrossSub;

                const netShopHeldCollections = Math.max(0, cashCollectedAtShop - expensesPaidFromShopCash);
                const rule = rel.eligibility_rule || 'previous_day_balance';

                if (rule === 'previous_day_balance') {
                    relationEligible = Math.max(0, dayOpenPayable);
                } else {
                    relationEligible = Math.max(0, dayOpenPayable + netShopHeldCollections);
                }

                if (relationNet > 0) {
                    relationSettled = Math.min(relationNet, relationEligible);
                    relationRemaining = relationNet - relationSettled;
                } else {
                    relationSettled = relationNet;
                    relationRemaining = 0;
                }
            }

            const netShopCollectionsToday = cashCollectedAtShop - expensesPaidFromShopCash;
            const dayClosingPayable = dayOpenPayable + netShopCollectionsToday - relationSettled;
            const dayClosingPetty = dayOpenPetty - expensesPaidFromPetty;

            const dayClosingAccountPositions = {};
            accounts.forEach(acc => {
                const inflow = bankInflows[acc.id] || 0;
                const openPos = dayOpenAccountPositions[acc.id] || 0;
                dayClosingAccountPositions[acc.id] = openPos + inflow;
            });

            dayResults[d] = {
                openingPayable: dayOpenPayable,
                openingPetty: dayOpenPetty,
                openingAccountPositions: dayOpenAccountPositions,
                totalSales: daySales,
                totalExpenses: dayExpenses,
                totalCashPurchase: dayCashPurchase,
                cashCollectedAtShop: cashCollectedAtShop,
                shopHeldSalesEntries: shopHeldSalesEntries,
                directCompanyBankTotal: directCompanyBankTotal,
                bankInflows: bankInflows,
                directBankEntriesMap: directBankEntriesMap,
                expensesPaidFromShopCash: expensesPaidFromShopCash,
                expensesPaidFromPetty: expensesPaidFromPetty,
                expensesPaidDirectlyByCompany: expensesPaidDirectlyByCompany,
                companyPaidEntries: companyPaidEntries,
                pettyUsedEntries: pettyUsedEntries,
                relationGrossAdd: relationGrossAdd,
                relationGrossSub: relationGrossSub,
                relationNet: relationNet,
                relationEligible: relationEligible,
                relationSettled: relationSettled,
                relationRemaining: relationRemaining,
                relationItemsDetail: relationItemsDetail,
                relationLinkedItemsDetail: relationLinkedItemsDetail,
                closingPayable: dayClosingPayable,
                closingPetty: dayClosingPetty,
                closingAccountPositions: dayClosingAccountPositions
            };

            currentShopPayable = dayClosingPayable;
            currentPetty = dayClosingPetty;
            currentAccountPositions = { ...dayClosingAccountPositions };
        }

        renderActiveDayUI(dayResults[activeDay]);
        render3DayOverviewUI(dayResults);
    }

    // DEMO PRODUCT TAGGING JS ENGINE
    let activeDemoProductHeaderId = null;
    let demoProductQuery = '';
    let demoProductPage = 1;
    let demoProductHasMore = false;
    let demoProductSearchDebounceTimer = null;
    let demoProductCurrentItems = [];

    async function openDemoProductModal(headerId, headerName) {
        activeDemoProductHeaderId = headerId;
        const titleEl = document.getElementById('demo-product-modal-title');
        if (titleEl) {
            titleEl.innerHTML = `
                <i data-lucide="tag" class="h-4 w-4 text-indigo-600"></i>
                <span>Select Product for ${escapeHtml(headerName)}</span>
            `;
        }
        const searchInput = document.getElementById('demo-product-search-input');
        if (searchInput) searchInput.value = '';

        const subtitleEl = document.getElementById('demo-product-modal-subtitle');
        if (subtitleEl) subtitleEl.textContent = 'Search Green Leaf Product catalog to tag detail rows.';

        demoProductQuery = '';
        demoProductPage = 1;
        demoProductCurrentItems = [];

        document.getElementById('demo-product-modal').classList.remove('hidden');
        if (window.lucide) lucide.createIcons();

        await fetchAndRenderDemoProducts('', 1, false);
    }

    function closeDemoProductModal() {
        document.getElementById('demo-product-modal').classList.add('hidden');
    }

    function onDemoProductSearchInput() {
        if (demoProductSearchDebounceTimer) {
            clearTimeout(demoProductSearchDebounceTimer);
        }
        demoProductSearchDebounceTimer = setTimeout(() => {
            const input = document.getElementById('demo-product-search-input');
            demoProductQuery = input ? input.value.trim() : '';
            demoProductPage = 1;
            demoProductCurrentItems = [];
            fetchAndRenderDemoProducts(demoProductQuery, 1, false);
        }, 250);
    }

    async function fetchAndRenderDemoProducts(query = '', page = 1, append = false) {
        const listContainer = document.getElementById('demo-product-list');
        if (!listContainer) return;

        if (!append) {
            listContainer.innerHTML = `<div class="p-6 text-center text-xs font-bold text-slate-400">Searching Green Leaf catalog...</div>`;
        } else {
            const loadBtn = document.getElementById('demo-product-load-more-btn');
            if (loadBtn) {
                loadBtn.disabled = true;
                loadBtn.textContent = 'Loading more products...';
            }
        }

        try {
            const url = new URL('{{ route('admin.cashbook.api.products.search') }}', window.location.origin);
            if (query) url.searchParams.set('q', query);
            if (activeDemoProductHeaderId) url.searchParams.set('header_id', activeDemoProductHeaderId);
            url.searchParams.set('page', page);
            url.searchParams.set('per_page', 50);

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();

            if (!data.products) {
                if (!append) listContainer.innerHTML = `<div class="p-6 text-center text-xs font-bold text-rose-500">Error loading catalog products.</div>`;
                return;
            }

            demoProductHasMore = !!data.has_more;
            demoProductPage = data.current_page || page;

            if (append) {
                demoProductCurrentItems = [...demoProductCurrentItems, ...data.products];
            } else {
                demoProductCurrentItems = data.products;
            }

            const subtitleEl = document.getElementById('demo-product-modal-subtitle');
            if (subtitleEl) {
                if (data.total !== undefined && data.total > 0) {
                    subtitleEl.textContent = `Showing ${demoProductCurrentItems.length} of ${data.total} products from Green Leaf Catalog.`;
                } else {
                    subtitleEl.textContent = 'Search Green Leaf Product catalog to tag detail rows.';
                }
            }

            renderDemoProductList(demoProductCurrentItems, demoProductHasMore);
        } catch (err) {
            if (!append) listContainer.innerHTML = `<div class="p-6 text-center text-xs font-bold text-rose-500">Error loading catalog products.</div>`;
        }
    }

    async function loadMoreDemoProducts() {
        if (!demoProductHasMore) return;
        await fetchAndRenderDemoProducts(demoProductQuery, demoProductPage + 1, true);
    }

    function renderDemoProductList(products, hasMore = false) {
        const listContainer = document.getElementById('demo-product-list');
        if (!listContainer) return;

        if (products.length === 0) {
            listContainer.innerHTML = `<div class="p-6 text-center text-xs font-bold text-slate-400">No matching products found in Green Leaf Catalog.</div>`;
            return;
        }

        const existingRows = (demoState[activeDay]?.productRows?.[activeDemoProductHeaderId]) || [];

        let itemsHtml = products.map(p => {
            const isAlreadyAdded = existingRows.some(r => r.productId === p.id);

            let actionButton = '';
            if (isAlreadyAdded) {
                actionButton = `
                    <span class="rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-500 inline-flex items-center gap-1">
                        <i data-lucide="check" class="h-3.5 w-3.5 text-emerald-600"></i> Already Added
                    </span>
                `;
            } else {
                actionButton = `
                    <button type="button"
                            onclick="addDemoProductToHeader(${p.id}, '${escapeJsString(p.name)}', '${escapeJsString(p.sku || '')}')"
                            class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-black text-white hover:bg-indigo-700 shadow-2xs inline-flex items-center gap-1">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i> Select
                    </button>
                `;
            }

            return `
                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 p-3 hover:bg-slate-100 transition">
                    <div>
                        <div class="font-extrabold text-xs text-slate-950 flex items-center gap-1.5">
                            <i data-lucide="package" class="h-3.5 w-3.5 text-indigo-600"></i>
                            <span>${escapeHtml(p.name)}</span>
                        </div>
                        <div class="text-[10px] font-bold text-slate-500">${p.sku ? 'SKU: ' + escapeHtml(p.sku) : 'Catalog Product'} ${p.unit ? '• ' + escapeHtml(p.unit.toUpperCase()) : ''}</div>
                    </div>
                    <div>${actionButton}</div>
                </div>
            `;
        }).join('');

        if (hasMore) {
            itemsHtml += `
                <div class="pt-2 text-center">
                    <button type="button" id="demo-product-load-more-btn" onclick="loadMoreDemoProducts()"
                            class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 text-xs font-extrabold text-indigo-600 hover:bg-indigo-50 transition shadow-2xs">
                        Load More Products...
                    </button>
                </div>
            `;
        }

        listContainer.innerHTML = itemsHtml;
        if (window.lucide) lucide.createIcons();
    }

    function escapeJsString(str) {
        return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function addDemoProductToHeader(productId, productName, sku) {
        const headerId = activeDemoProductHeaderId;
        if (!headerId) return;

        demoState[activeDay].productRows = demoState[activeDay].productRows || {};
        demoState[activeDay].productRows[headerId] = demoState[activeDay].productRows[headerId] || [];

        const existing = demoState[activeDay].productRows[headerId];
        if (existing.some(r => r.productId === productId)) {
            if (window.showToast) showToast(`${productName} already added to this section.`, 'warning');
            closeDemoProductModal();
            return;
        }

        existing.push({
            productId: productId,
            productName: productName,
            sku: sku,
            amount: 0
        });

        closeDemoProductModal();
        renderProductRowsForHeader(headerId);
        recalculateDemo();
    }

    function removeDemoProductRow(headerId, productId) {
        if (!demoState[activeDay]?.productRows?.[headerId]) return;

        demoState[activeDay].productRows[headerId] = demoState[activeDay].productRows[headerId].filter(r => r.productId !== productId);
        renderProductRowsForHeader(headerId);
        recalculateDemo();
    }

    function onProductRowAmountChange(headerId, productId, inputElement) {
        let val = parseFloat(inputElement.value);
        if (isNaN(val) || val < 0) val = 0;

        const rows = demoState[activeDay]?.productRows?.[headerId] || [];
        const row = rows.find(r => r.productId === productId);
        if (row) {
            row.amount = val;
        }
        recalculateDemo();
    }

    function renderProductRowsForHeader(headerId) {
        const container = document.getElementById('product-rows-container-' + headerId);
        if (!container) return;

        const rows = (demoState[activeDay]?.productRows?.[headerId]) || [];

        if (rows.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = rows.map(r => `
            <div class="py-2 flex items-center justify-between gap-3 border-t border-slate-100/80">
                <div class="min-w-0 flex-1 space-y-0.5">
                    <div class="text-xs font-black text-slate-900 truncate flex items-center gap-1.5">
                        <i data-lucide="tag" class="h-3.5 w-3.5 text-indigo-600"></i>
                        <span>${escapeHtml(r.productName)}</span>
                    </div>
                    <div class="text-[10px] font-semibold text-slate-400 truncate">
                        Product Tag ${r.sku ? '· ' + escapeHtml(r.sku) : ''}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative w-36 sm:w-44 flex-shrink-0">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-semibold text-slate-400">₹</span>
                        <input type="number"
                               value="${r.amount > 0 ? r.amount : ''}"
                               min="0"
                               step="0.01"
                               inputmode="decimal"
                               placeholder="0"
                               oninput="onProductRowAmountChange('${headerId}', ${r.productId}, this)"
                               onblur="formatInputOnBlur(this)"
                               class="demo-product-input demo-money-input h-11 w-full rounded-xl border border-slate-200 bg-white pl-7 pr-3 text-right text-base font-extrabold text-slate-950 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/10 focus:outline-none shadow-2xs">
                    </div>
                    <button type="button" onclick="removeDemoProductRow('${headerId}', ${r.productId})"
                            class="p-2 text-slate-400 hover:text-rose-600 rounded-xl hover:bg-rose-50 transition" title="Remove Product">
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>
        `).join('');

        if (window.lucide) lucide.createIcons();
    }

    function renderAllProductRowsForActiveDay() {
        headers.forEach(h => {
            renderProductRowsForHeader(h.id);
        });
    }

    function renderActiveDayUI(res) {
        if (!res) return;

        const dayAmounts = demoState[activeDay] || {};

        // Update Section Subtotals per Header
        headers.forEach(h => {
            let headerTotal = 0;
            (h.setting_ids || []).forEach(sId => {
                const amt = parseFloat(dayAmounts[sId]) || 0;
                if (amt > 0) headerTotal += amt;
            });

            const pRows = (dayAmounts.productRows && dayAmounts.productRows[h.id]) || [];
            pRows.forEach(pr => {
                const pAmt = parseFloat(pr.amount) || 0;
                if (pAmt > 0) headerTotal += pAmt;
            });

            const el = document.getElementById('header-total-' + h.id);
            if (el) el.textContent = formatCurrency(headerTotal);
        });

        // Update Major Section Header Totals
        const secInc = document.getElementById('demo-section-total-income');
        if (secInc) secInc.textContent = formatCurrency(res.totalSales);

        const secExp = document.getElementById('demo-section-total-expense');
        if (secExp) secExp.textContent = '-' + formatCurrency(res.totalExpenses + res.totalCashPurchase);

        const secOth = document.getElementById('demo-section-total-others');
        if (secOth) secOth.textContent = formatCurrency(res.relationNet);

        const secPet = document.getElementById('demo-section-total-petty');
        if (secPet) secPet.textContent = formatCurrency(res.closingPetty);

        const secSum = document.getElementById('demo-section-total-summary');
        if (secSum) {
            const netAct = res.totalSales - (res.totalExpenses + res.totalCashPurchase + res.relationSettled);
            secSum.textContent = formatCurrency(netAct);
        }

        renderAllProductRowsForActiveDay();

        // Relation Settlement Footers
        const relNet = document.getElementById('day-relation-net');
        if (relNet) relNet.textContent = formatCurrency(res.relationNet);

        const relGrossAdd = document.getElementById('rel-gross-add');
        if (relGrossAdd) relGrossAdd.textContent = formatCurrency(res.relationGrossAdd);
        const relGrossSub = document.getElementById('rel-gross-sub');
        if (relGrossSub) relGrossSub.textContent = '-' + formatCurrency(res.relationGrossSub);

        // Relation Linked Items List (No duplicate inputs!)
        const relLinkedList = document.getElementById('relation-linked-items-list');
        if (relLinkedList) {
            const allLinked = res.relationLinkedItemsDetail || [];
            if (allLinked.length === 0) {
                relLinkedList.innerHTML = '<div class="text-[11px] font-semibold text-purple-700/60">No linked entries from Header Sections.</div>';
            } else {
                relLinkedList.innerHTML = allLinked.map(i => {
                    const isSub = i.role === 'subtract';
                    const sign = isSub ? '-' : '+';
                    const color = isSub ? 'text-rose-700' : 'text-emerald-700';
                    return '<div class="flex justify-between items-center bg-white/80 rounded-lg p-2 border border-purple-100">' +
                           '<span class="font-bold text-slate-900 truncate">' + escapeHtml(i.name) + '</span>' +
                           '<span class="font-mono font-black ' + color + '">' + sign + formatCurrency(i.amount) + '</span>' +
                           '</div>';
                }).join('');
            }
        }

        // Render Demo-Only Petty Section
        const pettyOpeningEl = document.getElementById('demo-petty-opening');
        if (pettyOpeningEl) pettyOpeningEl.textContent = formatCurrency(res.openingPetty);

        const pettyUsedTotalEl = document.getElementById('demo-petty-used-total');
        if (pettyUsedTotalEl) pettyUsedTotalEl.textContent = '-' + formatCurrency(res.expensesPaidFromPetty);

        const pettyClosingEl = document.getElementById('demo-petty-closing');
        if (pettyClosingEl) pettyClosingEl.textContent = formatCurrency(res.closingPetty);

        const pettyUsedCountEl = document.getElementById('demo-petty-used-count');
        const pettyUsedListEl = document.getElementById('demo-petty-used-list');

        if (pettyUsedListEl) {
            const pettyItems = res.pettyUsedEntries || [];
            if (pettyUsedCountEl) pettyUsedCountEl.textContent = pettyItems.length + (pettyItems.length === 1 ? ' entry' : ' entries');

            if (pettyItems.length === 0) {
                pettyUsedListEl.innerHTML = '<div class="text-slate-400 font-bold text-[11px] p-2 text-center">No petty-funded expenses today.</div>';
            } else {
                pettyUsedListEl.innerHTML = pettyItems.map(item => `
                    <div class="flex justify-between items-center text-slate-800 font-bold bg-white/80 p-2.5 rounded-xl border border-amber-100/80">
                        <div class="min-w-0 flex-1 truncate pr-2">
                            <span class="truncate block text-xs font-extrabold text-slate-900">${escapeHtml(item.name)}</span>
                            <span class="text-[10px] text-slate-400 font-semibold block">Funded from Petty</span>
                        </div>
                        <span class="font-mono text-rose-700 font-black text-xs">-${formatCurrency(item.amount)}</span>
                    </div>
                `).join('');
            }
        }

        // Daily Net Position Box (Invoice Footer)
        document.getElementById('bill-sales').textContent = formatCurrency(res.totalSales);
        document.getElementById('bill-expenses').textContent = '-' + formatCurrency(res.totalExpenses);
        document.getElementById('bill-cash-purchase').textContent = '-' + formatCurrency(res.totalCashPurchase);
        document.getElementById('bill-settlement').textContent = '-' + formatCurrency(res.relationSettled);
        const netAct = res.totalSales - (res.totalExpenses + res.totalCashPurchase + res.relationSettled);
        document.getElementById('bill-net-activity').textContent = formatCurrency(netAct);

        // Shop Balance Footer
        document.getElementById('sb-opening').textContent = formatCurrency(res.openingPayable);
        document.getElementById('sb-settlement').textContent = '-' + formatCurrency(res.relationSettled);
        document.getElementById('sb-shop-held').textContent = '+' + formatCurrency(res.cashCollectedAtShop - res.expensesPaidFromShopCash);
        document.getElementById('sb-closing').textContent = formatCurrency(res.closingPayable);

        // 1. Payable to Company (Right Side Card)
        document.getElementById('move-open-payable').textContent = formatCurrency(res.openingPayable);
        document.getElementById('move-shop-collections').textContent = '+' + formatCurrency(res.cashCollectedAtShop - res.expensesPaidFromShopCash);
        document.getElementById('move-eligible-settlement').textContent = '-' + formatCurrency(res.relationSettled);
        document.getElementById('move-closing-payable').textContent = formatCurrency(res.closingPayable);

        // 2. Today's Shop-Held Money
        const shopHeldCount = document.getElementById('move-shop-held-count');
        if (shopHeldCount) shopHeldCount.textContent = res.shopHeldSalesEntries.length + ' entries';
        const shopHeldEntries = document.getElementById('move-shop-held-entries');
        if (shopHeldEntries) {
            if (res.shopHeldSalesEntries.length === 0) {
                shopHeldEntries.innerHTML = '<div class="text-slate-400 font-bold text-[11px]">No shop-held entries today.</div>';
            } else {
                shopHeldEntries.innerHTML = res.shopHeldSalesEntries.map(e =>
                    '<div class="flex justify-between items-center text-slate-700 font-bold">' +
                    '<span>' + escapeHtml(e.name) + '</span>' +
                    '<span class="font-mono text-emerald-700 font-black">' + formatCurrency(e.amount) + '</span>' +
                    '</div>'
                ).join('');
            }
        }
        document.getElementById('move-shop-held-total').textContent = formatCurrency(res.cashCollectedAtShop);

        // 3. Direct to Company Bank Accounts
        const accList = document.getElementById('move-company-accounts-list');
        if (accList) {
            if (accounts.length === 0) {
                accList.innerHTML = '<div class="text-xs font-bold text-slate-400">No company accounts configured.</div>';
            } else {
                accList.innerHTML = accounts.map(acc => {
                    const entries = res.directBankEntriesMap[acc.id] || [];
                    const todayMove = res.bankInflows[acc.id] || 0;
                    const openPos = res.openingAccountPositions[acc.id] || 0;
                    const closingPos = res.closingAccountPositions[acc.id] || 0;

                    let entriesHtml = '';
                    if (entries.length === 0) {
                        entriesHtml = '<div class="text-[11px] font-semibold text-slate-400">No direct collections today.</div>';
                    } else {
                        entriesHtml = entries.map(e =>
                            '<div class="flex justify-between text-xs font-bold text-slate-700">' +
                            '<span>' + escapeHtml(e.name) + '</span>' +
                            '<span class="font-mono text-indigo-700">' + formatCurrency(e.amount) + '</span>' +
                            '</div>'
                        ).join('');
                    }

                    return '<div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3.5 space-y-2.5">' +
                           '<div class="flex justify-between items-center border-b border-slate-200 pb-1.5">' +
                           '<span class="text-xs font-extrabold text-slate-950 uppercase tracking-wider">' + escapeHtml(acc.name) + '</span>' +
                           '<span class="text-[10px] font-bold text-slate-500 font-mono">' + escapeHtml(acc.bank_name || '') + '</span>' +
                           '</div>' +
                           '<div class="space-y-1">' + entriesHtml + '</div>' +
                           '<div class="pt-2 border-t border-slate-200 space-y-1 text-xs font-bold text-slate-800">' +
                           '<div class="flex justify-between"><span>Today\'s Movement</span><span class="font-mono text-indigo-700 font-black">+' + formatCurrency(todayMove) + '</span></div>' +
                           '<div class="flex justify-between text-slate-500"><span>Opening Demo Balance</span><span class="font-mono">' + formatCurrency(openPos) + '</span></div>' +
                           '<div class="flex justify-between text-slate-900 font-black"><span>Demo Company Account Position</span><span class="font-mono text-indigo-900">' + formatCurrency(closingPos) + '</span></div>' +
                           '</div>' +
                           '</div>';
                }).join('');
            }
        }

        // 4. Company Position (Active Day)
        document.getElementById('pos-direct-accounts').textContent = formatCurrency(res.directCompanyBankTotal);
        document.getElementById('pos-held-by-shop').textContent = formatCurrency(res.closingPayable);
        document.getElementById('pos-petty-balance').textContent = formatCurrency(res.closingPetty);
        document.getElementById('pos-pending-settlement').textContent = formatCurrency(res.relationRemaining);

        let totalAccountsClosing = 0;
        accounts.forEach(acc => {
            totalAccountsClosing += (res.closingAccountPositions[acc.id] || 0);
        });
        const totalControlled = totalAccountsClosing + res.closingPayable + res.closingPetty + res.relationRemaining;
        document.getElementById('pos-total-controlled').textContent = formatCurrency(totalControlled);

        // 5. Petty Movement
        document.getElementById('petty-open').textContent = formatCurrency(res.openingPetty);
        document.getElementById('petty-expenses').textContent = '-' + formatCurrency(res.expensesPaidFromPetty);
        document.getElementById('petty-cash-purchase').textContent = '-' + formatCurrency(res.totalCashPurchase);
        document.getElementById('petty-closing').textContent = formatCurrency(res.closingPetty);

        // 6. Paid Directly by Company
        const coPaidList = document.getElementById('move-company-paid-list');
        if (coPaidList) {
            if (res.companyPaidEntries.length === 0) {
                coPaidList.innerHTML = '<div class="text-xs font-bold text-slate-400">No company-paid expenses today.</div>';
            } else {
                coPaidList.innerHTML = res.companyPaidEntries.map(e =>
                    '<div class="flex justify-between items-center text-xs font-bold text-slate-700 bg-slate-50 p-2 rounded-xl border border-slate-100">' +
                    '<div><span>' + escapeHtml(e.name) + '</span><span class="text-[10px] text-slate-400 font-semibold block">Source: ' + escapeHtml(e.sourceName) + '</span></div>' +
                    '<span class="font-mono text-indigo-700 font-black">' + formatCurrency(e.amount) + '</span>' +
                    '</div>'
                ).join('');
            }
        }

        // 7. Settlement Movement
        const setCalc = document.getElementById('settle-calculated');
        if (setCalc) setCalc.textContent = formatCurrency(res.relationNet);
        const setElig = document.getElementById('settle-eligible');
        if (setElig) setElig.textContent = formatCurrency(res.relationEligible);
        const setSettled = document.getElementById('settle-settled');
        if (setSettled) setSettled.textContent = formatCurrency(res.relationSettled);
        const setRem = document.getElementById('settle-remaining');
        if (setRem) setRem.textContent = formatCurrency(res.relationRemaining);
    }

    function render3DayOverviewUI(dayResults) {
        let sumSales = 0;
        let sumExpenses = 0;
        let sumCashPurchase = 0;
        let sumSettlementCalc = 0;

        let sumDirectCompany = 0;
        let sumShopCollections = 0;
        let sumSettlementPaid = 0;
        let sumPettySpent = 0;
        let sumCompanyPaid = 0;

        for (let d = 1; d <= 3; d++) {
            const r = dayResults[d] || {};
            sumSales += (r.totalSales || 0);
            sumExpenses += (r.totalExpenses || 0);
            sumCashPurchase += (r.totalCashPurchase || 0);
            sumSettlementCalc += (r.relationNet || 0);

            sumDirectCompany += (r.directCompanyBankTotal || 0);
            sumShopCollections += (r.cashCollectedAtShop || 0);
            sumSettlementPaid += (r.relationSettled || 0);
            sumPettySpent += (r.expensesPaidFromPetty || 0) + (r.totalCashPurchase || 0);
            sumCompanyPaid += (r.expensesPaidDirectlyByCompany || 0);
        }

        document.getElementById('sum3-sales').textContent = formatCurrency(sumSales);
        document.getElementById('sum3-expenses').textContent = '-' + formatCurrency(sumExpenses);
        document.getElementById('sum3-cash-purchase').textContent = '-' + formatCurrency(sumCashPurchase);
        document.getElementById('sum3-settlement-calc').textContent = formatCurrency(sumSettlementCalc);

        document.getElementById('sum3-direct-company').textContent = formatCurrency(sumDirectCompany);
        document.getElementById('sum3-shop-collections').textContent = formatCurrency(sumShopCollections);
        document.getElementById('sum3-settlement-paid').textContent = formatCurrency(sumSettlementPaid);
        document.getElementById('sum3-petty-spent').textContent = formatCurrency(sumPettySpent);
        document.getElementById('sum3-company-paid').textContent = formatCurrency(sumCompanyPaid);

        // Final positions at Day 3 Closing
        const day3 = dayResults[3] || {};
        document.getElementById('final-shop-payable').textContent = formatCurrency(day3.closingPayable || 0);
        document.getElementById('final-petty-balance').textContent = formatCurrency(day3.closingPetty || 0);

        let totalAccPositionsDay3 = 0;
        accounts.forEach(acc => {
            totalAccPositionsDay3 += (day3.closingAccountPositions[acc.id] || 0);
        });
        const finalTotalPos = totalAccPositionsDay3 + (day3.closingPayable || 0) + (day3.closingPetty || 0) + (day3.relationRemaining || 0);
        document.getElementById('final-total-position').textContent = formatCurrency(finalTotalPos);

        // Company Account 3-Day Breakdown Cards
        const acc3DayList = document.getElementById('company-accounts-3day-list');
        if (acc3DayList) {
            if (accounts.length === 0) {
                acc3DayList.innerHTML = '<div class="text-xs font-bold text-slate-400">No company accounts configured.</div>';
            } else {
                acc3DayList.innerHTML = accounts.map(acc => {
                    const d1Inflow = (dayResults[1]?.bankInflows[acc.id]) || 0;
                    const d2Inflow = (dayResults[2]?.bankInflows[acc.id]) || 0;
                    const d3Inflow = (dayResults[3]?.bankInflows[acc.id]) || 0;
                    const totalMovement = d1Inflow + d2Inflow + d3Inflow;
                    const finalPos = (dayResults[3]?.closingAccountPositions[acc.id]) || 0;

                    return '<div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3.5 space-y-1.5 text-xs font-bold text-slate-700">' +
                           '<div class="flex justify-between items-center border-b border-slate-200 pb-1.5">' +
                           '<span class="font-extrabold text-slate-950 uppercase tracking-wider">' + escapeHtml(acc.name) + '</span>' +
                           '<span class="text-[10px] text-slate-400 font-mono">' + escapeHtml(acc.bank_name || '') + '</span>' +
                           '</div>' +
                           '<div class="flex justify-between text-slate-600"><span>Day 1 Inflow</span><span class="font-mono text-indigo-700">' + formatCurrency(d1Inflow) + '</span></div>' +
                           '<div class="flex justify-between text-slate-600"><span>Day 2 Inflow</span><span class="font-mono text-indigo-700">' + formatCurrency(d2Inflow) + '</span></div>' +
                           '<div class="flex justify-between text-slate-600"><span>Day 3 Inflow</span><span class="font-mono text-indigo-700">' + formatCurrency(d3Inflow) + '</span></div>' +
                           '<div class="pt-1.5 border-t border-slate-200 flex justify-between font-black text-slate-900">' +
                           '<span>3-Day Movement Total</span><span class="font-mono text-indigo-800">+' + formatCurrency(totalMovement) + '</span>' +
                           '</div>' +
                           '<div class="flex justify-between font-black text-slate-900">' +
                           '<span>Final Demo Position (Day 3)</span><span class="font-mono text-indigo-950 text-sm">' + formatCurrency(finalPos) + '</span>' +
                           '</div>' +
                           '</div>';
                }).join('');
            }
        }
    }

    function formatCurrency(amount) {
        if (isNaN(amount) || amount === null || amount === undefined) {
            amount = 0;
        }
        return '₹' + Math.abs(amount).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    let showPettySection = true;

    function togglePettySectionVisibility() {
        showPettySection = !showPettySection;
        const pettySectionEl = document.getElementById('demo-petty-section');
        const statusEl = document.getElementById('toggle-petty-status');
        const btnEl = document.getElementById('toggle-petty-btn');

        if (showPettySection) {
            if (pettySectionEl) pettySectionEl.classList.remove('hidden');
            if (statusEl) statusEl.textContent = 'ON';
            if (btnEl) {
                btnEl.className = 'inline-flex items-center gap-1.5 rounded-2xl border border-amber-300 bg-amber-50 px-3.5 py-2 text-xs font-extrabold text-amber-900 hover:bg-amber-100 transition shadow-2xs cursor-pointer';
            }
        } else {
            if (pettySectionEl) pettySectionEl.classList.add('hidden');
            if (statusEl) statusEl.textContent = 'OFF';
            if (btnEl) {
                btnEl.className = 'inline-flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-slate-100 px-3.5 py-2 text-xs font-extrabold text-slate-500 hover:bg-slate-200 transition shadow-2xs cursor-pointer';
            }
        }
    }

    const sectionCollapseState = {
        income: false,
        expense: false,
        others: false,
        petty: true,
        summary: false
    };

    function toggleDemoSection(key) {
        if (sectionCollapseState[key] === undefined) return;
        sectionCollapseState[key] = !sectionCollapseState[key];
        applySectionCollapseState(key);
    }

    function applySectionCollapseState(key = null) {
        const keys = key ? [key] : Object.keys(sectionCollapseState);
        keys.forEach(k => {
            const isCollapsed = !!sectionCollapseState[k];
            const bodyEl = document.getElementById('demo-section-body-' + k);
            const iconEl = document.getElementById('demo-section-icon-' + k);

            if (bodyEl) {
                if (isCollapsed) {
                    bodyEl.classList.add('hidden');
                } else {
                    bodyEl.classList.remove('hidden');
                }
            }

            if (iconEl) {
                iconEl.setAttribute('data-lucide', isCollapsed ? 'chevron-right' : 'chevron-down');
            }
        });

        if (window.lucide) lucide.createIcons();
    }

    // DEMO TXT EXPORT ENGINE
    const shopName = @json($currentShop->name);
    const shopSlug = @json(\Illuminate\Support\Str::slug($currentShop->name));

    function formatTxtMoney(amount) {
        const val = parseFloat(amount) || 0;
        return '₹' + Math.abs(val).toLocaleString('en-IN', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }

    function generateDayReportText(day) {
        recalculateDemo();

        const res = dayResults[day] || {};
        const dayAmounts = demoState[day] || {};
        const notesMap = (demoState[day] && demoState[day].notes) || {};
        const pRowsMap = (demoState[day] && demoState[day].productRows) || {};

        let lines = [];

        lines.push(`${shopName.toUpperCase()} — CASHBOOK DEMO — DAY ${day}`);
        lines.push('================================');
        lines.push('');

        // 1. INCOME
        lines.push('INCOME');
        lines.push('');

        const incomeHeadersList = headers.filter(h => h.type === 'income');
        incomeHeadersList.forEach(h => {
            lines.push(h.name);
            lines.push('--------------------------------');

            let headerTotal = 0;
            const sIds = h.setting_ids || [];
            sIds.forEach(sId => {
                const s = settings.find(item => item.id === sId);
                if (!s) return;

                const amt = parseFloat(dayAmounts[sId]) || 0;
                headerTotal += amt;
                const note = notesMap[sId] ? String(notesMap[sId]).trim() : '';
                const dest = s.company_account_name ? `${s.company_account_name} · Direct Company` : 'Held at Shop';

                lines.push(`${s.name}: ${formatTxtMoney(amt)}`);
                lines.push(`Destination: ${dest}`);
                if (note) {
                    lines.push(`Note: ${note}`);
                } else if (s.requires_note && amt > 0) {
                    lines.push('Note: [MISSING REQUIRED NOTE]');
                }
                lines.push('');
            });

            const pRows = pRowsMap[h.id] || [];
            pRows.forEach(pr => {
                const pAmt = parseFloat(pr.amount) || 0;
                headerTotal += pAmt;
                lines.push(`${pr.productName}: ${formatTxtMoney(pAmt)}`);
                lines.push(`Product Tag${pr.sku ? ' · ' + pr.sku : ''}`);
                lines.push('');
            });

            lines.push(`Total ${h.name}: ${formatTxtMoney(headerTotal)}`);
            lines.push('');
            lines.push('');
        });

        // 2. EXPENSE
        lines.push('EXPENSE');
        lines.push('');

        const expenseHeadersList = headers.filter(h => h.type === 'expense');
        expenseHeadersList.forEach(h => {
            lines.push(h.name);
            lines.push('--------------------------------');

            let headerTotal = 0;
            const sIds = h.setting_ids || [];
            sIds.forEach(sId => {
                const s = settings.find(item => item.id === sId);
                if (!s) return;

                const amt = parseFloat(dayAmounts[sId]) || 0;
                headerTotal += amt;
                const note = notesMap[sId] ? String(notesMap[sId]).trim() : '';

                let paidFrom = 'Shop Balance';
                if (s.funding_source === 'petty') {
                    paidFrom = 'Petty';
                } else if (s.funding_source === 'company' || s.funding_source === 'bank') {
                    paidFrom = s.company_account_name || 'Company';
                }

                lines.push(`${s.name}: ${formatTxtMoney(amt)}`);
                lines.push(`Paid From: ${paidFrom}`);
                if (note) {
                    lines.push(`Note: ${note}`);
                } else if (s.requires_note && amt > 0) {
                    lines.push('Note: [MISSING REQUIRED NOTE]');
                }
                lines.push('');
            });

            const pRows = pRowsMap[h.id] || [];
            pRows.forEach(pr => {
                const pAmt = parseFloat(pr.amount) || 0;
                headerTotal += pAmt;
                lines.push(`${pr.productName}: ${formatTxtMoney(pAmt)}`);
                lines.push(`Product Tag${pr.sku ? ' · ' + pr.sku : ''}`);
                lines.push('');
            });

            lines.push(`Total ${h.name}: ${formatTxtMoney(headerTotal)}`);
            lines.push('');
            lines.push('');
        });

        // Cash Purchase
        const cashPurchaseSettings = settings.filter(s => s.is_cash_purchase);
        if (cashPurchaseSettings.length > 0 || (res.totalCashPurchase || 0) > 0) {
            lines.push('Cash Purchase');
            lines.push('--------------------------------');
            cashPurchaseSettings.forEach(s => {
                const amt = parseFloat(dayAmounts[s.id]) || 0;
                lines.push(`${s.name}: ${formatTxtMoney(amt)}`);
            });

            headers.forEach(h => {
                const hName = (h.name || '').toLowerCase();
                if (hName.includes('cash purchase') || hName.includes('purchase')) {
                    const pRows = pRowsMap[h.id] || [];
                    pRows.forEach(pr => {
                        lines.push(`${pr.productName}: ${formatTxtMoney(pr.amount)}`);
                    });
                }
            });

            lines.push('');
            lines.push(`Total Cash Purchase: ${formatTxtMoney(res.totalCashPurchase || 0)}`);
            lines.push('Paid From: Petty');
            lines.push('');
            lines.push('');
        }

        // 3. OTHERS
        lines.push('OTHERS');
        lines.push('');

        if (relations.length > 0) {
            const rel = relations[0];
            lines.push(rel.name || 'Transfers & Settlements');
            lines.push('--------------------------------');
            lines.push(`Settlement Source: ${rel.settlement_source === 'sales' ? 'Shop Balance' : 'Company'}`);
            lines.push(`Eligibility: ${rel.eligibility_rule === 'previous_day_balance' ? 'Previous-Day Balance Only' : 'Previous-Day + Today Collections'}`);
            lines.push('');

            const itemsDetail = res.relationLinkedItemsDetail || [];
            const uniqueDetail = res.relationItemsDetail || [];
            const allRelItems = [...itemsDetail, ...uniqueDetail];

            allRelItems.forEach(i => {
                const sign = i.role === 'subtract' ? '-' : '+';
                lines.push(`${i.name}: ${sign}${formatTxtMoney(i.amount)}`);
            });

            lines.push('');
            lines.push(`Net Settlement: ${formatTxtMoney(res.relationNet || 0)}`);
            lines.push('');
            lines.push('');
        }

        // 4. PETTY
        lines.push('PETTY');
        lines.push('--------------------------------');
        lines.push(`Opening Petty: ${formatTxtMoney(res.openingPetty || 0)}`);

        const pettyEntries = res.pettyUsedEntries || [];
        pettyEntries.forEach(p => {
            lines.push(`${p.name}: -${formatTxtMoney(p.amount)}`);
        });
        if ((res.totalCashPurchase || 0) > 0) {
            lines.push(`Cash Purchase: -${formatTxtMoney(res.totalCashPurchase)}`);
        }

        const totalPettyUsed = (res.expensesPaidFromPetty || 0) + (res.totalCashPurchase || 0);
        lines.push('');
        lines.push(`Total Petty Used: ${formatTxtMoney(totalPettyUsed)}`);
        lines.push(`Closing Petty: ${formatTxtMoney(res.closingPetty || 0)}`);
        lines.push('');
        lines.push('');

        // 5. SUMMARY
        lines.push('SUMMARY');
        lines.push('================================');
        lines.push('');
        lines.push(`Total Income: ${formatTxtMoney(res.totalSales || 0)}`);
        lines.push(`Total Expense: ${formatTxtMoney((res.totalExpenses || 0) + (res.totalCashPurchase || 0))}`);
        lines.push('');

        lines.push('DIRECT TO COMPANY');
        lines.push('');
        accounts.forEach(acc => {
            const accEntries = (res.directBankEntriesMap && res.directBankEntriesMap[acc.id]) || [];
            if (accEntries.length > 0) {
                lines.push(acc.name);
                accEntries.forEach(e => {
                    lines.push(`${e.name}: ${formatTxtMoney(e.amount)}`);
                });
                lines.push('');
            }
        });
        lines.push(`Already Reached Company: ${formatTxtMoney(res.directCompanyBankTotal || 0)}`);
        lines.push('');

        lines.push('SHOP HELD');
        lines.push('');
        const shopHeldEntries = res.shopHeldSalesEntries || [];
        shopHeldEntries.forEach(e => {
            lines.push(`${e.name}: ${formatTxtMoney(e.amount)}`);
        });
        const todayShopHeldNet = (res.cashCollectedAtShop || 0) - (res.expensesPaidFromShopCash || 0);
        lines.push('');
        lines.push(`Today's Shop-Held Money: ${formatTxtMoney(todayShopHeldNet)}`);
        lines.push('');

        lines.push('SHOP BALANCE');
        lines.push('');
        lines.push(`Opening Shop Balance: ${formatTxtMoney(res.openingPayable || 0)}`);
        lines.push(`Settlement: -${formatTxtMoney(res.relationSettled || 0)}`);
        lines.push(`Today's Shop-Held Money: +${formatTxtMoney(todayShopHeldNet)}`);
        lines.push('');
        lines.push(`Closing Shop Balance: ${formatTxtMoney(res.closingPayable || 0)}`);
        lines.push('');
        lines.push(`Closing Petty: ${formatTxtMoney(res.closingPetty || 0)}`);
        lines.push('');
        lines.push(`TOTAL CASH AT SHOP: ${formatTxtMoney((res.closingPayable || 0) + (res.closingPetty || 0))}`);

        return lines.join('\n');
    }

    function generate3DaysReportText() {
        recalculateDemo();

        let lines = [];
        lines.push(`${shopName.toUpperCase()} — 3 DAY CASHBOOK DEMO`);
        lines.push('================================');
        lines.push('');
        lines.push('');

        for (let d = 1; d <= 3; d++) {
            lines.push(`DAY ${d}`);
            lines.push('================================');
            lines.push(generateDayReportText(d));
            lines.push('');
            lines.push('');
        }

        let sumSales = 0;
        let sumExpenses = 0;
        let sumCashPurchase = 0;
        let sumSettlement = 0;
        let sumDirectCompany = 0;
        let sumShopCollections = 0;
        let sumPettySpent = 0;

        for (let d = 1; d <= 3; d++) {
            const r = dayResults[d] || {};
            sumSales += (r.totalSales || 0);
            sumExpenses += (r.totalExpenses || 0);
            sumCashPurchase += (r.totalCashPurchase || 0);
            sumSettlement += (r.relationSettled || 0);
            sumDirectCompany += (r.directCompanyBankTotal || 0);
            sumShopCollections += (r.cashCollectedAtShop || 0);
            sumPettySpent += (r.expensesPaidFromPetty || 0) + (r.totalCashPurchase || 0);
        }

        const day3 = dayResults[3] || {};

        lines.push('3-DAY SUMMARY');
        lines.push('================================');
        lines.push('');
        lines.push(`Total Income: ${formatTxtMoney(sumSales)}`);
        lines.push(`Total Expense: ${formatTxtMoney(sumExpenses)}`);
        lines.push(`Total Cash Purchase: ${formatTxtMoney(sumCashPurchase)}`);
        lines.push(`Total Settlement: ${formatTxtMoney(sumSettlement)}`);
        lines.push('');
        lines.push(`Direct to Company: ${formatTxtMoney(sumDirectCompany)}`);
        lines.push(`Shop-Held Collections: ${formatTxtMoney(sumShopCollections)}`);
        lines.push(`Petty Used: ${formatTxtMoney(sumPettySpent)}`);
        lines.push('');
        lines.push('FINAL POSITION');
        lines.push('');
        lines.push(`Closing Shop Balance: ${formatTxtMoney(day3.closingPayable || 0)}`);
        lines.push(`Closing Petty: ${formatTxtMoney(day3.closingPetty || 0)}`);
        lines.push(`Total Cash at Shop: ${formatTxtMoney((day3.closingPayable || 0) + (day3.closingPetty || 0))}`);
        lines.push('');
        lines.push('Company Accounts');
        lines.push('');

        accounts.forEach(acc => {
            const finalPos = (day3.closingAccountPositions && day3.closingAccountPositions[acc.id]) || 0;
            lines.push(`${acc.name}: ${formatTxtMoney(finalPos)}`);
        });

        return lines.join('\n');
    }

    function downloadTxtFile(filename, content) {
        const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(url), 100);
    }

    function downloadDayTxt() {
        try {
            const text = generateDayReportText(activeDay);
            const filename = `${shopSlug || 'cashbook'}-demo-day-${activeDay}.txt`;
            downloadTxtFile(filename, text);
            if (window.showToast) showToast(`Downloaded Day ${activeDay} TXT report`, 'success');
        } catch (err) {
            console.error('Error exporting Day TXT:', err);
            if (window.showToast) showToast('Error generating TXT report', 'error');
        }
    }

    function download3DaysTxt() {
        try {
            const text = generate3DaysReportText();
            const filename = `${shopSlug || 'cashbook'}-demo-3-days.txt`;
            downloadTxtFile(filename, text);
            if (window.showToast) showToast('Downloaded 3-Day TXT report', 'success');
        } catch (err) {
            console.error('Error exporting 3-Day TXT:', err);
            if (window.showToast) showToast('Error generating 3-Day TXT report', 'error');
        }
    }
</script>
@endsection
