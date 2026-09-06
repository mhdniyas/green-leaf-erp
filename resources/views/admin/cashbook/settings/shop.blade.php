@extends('admin.cashbook.layouts.app')

@section('title', $currentShop->name.' Settings - Cashbook')

@section('content')
@php
    $fundingSourceBusinessLabels = [
        'sales' => 'Shop Cash / Deduct From Company Payable',
        'petty' => 'Petty Cash',
        'company' => 'Paid Directly by Company',
        'company_later' => 'Company Reimbursement Pending',
        'bank' => 'Company Bank Account',
        'none' => 'No Funding Movement',
    ];

    $fundingSources = [
        'sales' => 'Shop Cash / Deduct From Company Payable',
        'petty' => 'Petty Cash',
        'company' => 'Paid Directly by Company',
        'company_later' => 'Company Reimbursement Pending',
        'bank' => 'Company Bank Account',
        'none' => 'No Funding Movement',
    ];

    $effects = [
        'none' => 'No change',
        'increase' => 'Increase',
        'decrease' => 'Decrease',
    ];

    $allShopRows = $settingsByCategory
        ->flatten(1)
        ->sortBy(fn ($setting) => $setting->header_display_order ?: $setting->entryType?->name)
        ->values();

    $configuredTypeIds = $allShopRows->pluck('entry_type_id')->all();

    $collectionIncomeIds = isset($collectionGroup) && $collectionGroup
        ? $collectionGroup->entryTypes->where('role', 'income')->pluck('entry_type_id')->map(fn ($id) => (int) $id)->all()
        : [];
    $collectionExpenseIds = isset($collectionGroup) && $collectionGroup
        ? $collectionGroup->entryTypes->where('role', 'expense')->pluck('entry_type_id')->map(fn ($id) => (int) $id)->all()
        : [];

    $incomeRows = $allShopRows->filter(function ($setting) {
        $cat = strtolower((string) ($setting->entryType?->category ?? ''));
        $isSalesDeduction = $setting->include_in_sales && ($setting->payable_direction === 'minus' || $cat === 'transfer');
        return ($cat === 'income' || $setting->include_in_sales || $setting->include_in_income) && ! $isSalesDeduction;
    });
    $activeIncomeCount = $incomeRows->where('enabled', true)->count();
    $disabledIncomeCount = $incomeRows->where('enabled', false)->count();

    $expenseRows = $allShopRows->filter(function ($setting) {
        $cat = strtolower((string) ($setting->entryType?->category ?? ''));
        $isSalesDeduction = $setting->include_in_sales && ($setting->payable_direction === 'minus' || $cat === 'transfer');
        return ($cat === 'expense' || $setting->include_in_expense) && ! $isSalesDeduction;
    });
    $activeExpenseCount = $expenseRows->where('enabled', true)->count();
    $disabledExpenseCount = $expenseRows->where('enabled', false)->count();

    $transferRows = $allShopRows->filter(function ($setting) {
        $cat = strtolower((string) ($setting->entryType?->category ?? ''));
        $isSalesDeduction = $setting->include_in_sales && ($setting->payable_direction === 'minus' || $cat === 'transfer');
        return $cat === 'transfer' || $cat === 'settlement' || $isSalesDeduction || (! $setting->include_in_sales && ! $setting->include_in_income && ! $setting->include_in_expense);
    });
    $activeTransferCount = $transferRows->where('enabled', true)->count();
    $disabledTransferCount = $transferRows->where('enabled', false)->count();

    $incomeHeaders = $headerGroups->where('type', 'income')->sortBy('display_order')->values();
    $expenseHeaders = $headerGroups->where('type', 'expense')->sortBy('display_order')->values();

    $allEntryTypesList = isset($allEntryTypes) ? $allEntryTypes : collect();

    $unconfiguredIncomeTypes = $allEntryTypesList->filter(function ($type) use ($configuredTypeIds) {
        return strtolower((string) $type->category) === 'income' && ! in_array((int) $type->id, $configuredTypeIds, true);
    });

    $unconfiguredExpenseTypes = $allEntryTypesList->filter(function ($type) use ($configuredTypeIds) {
        return strtolower((string) $type->category) === 'expense' && ! in_array((int) $type->id, $configuredTypeIds, true);
    });

    $unconfiguredTransferTypes = $allEntryTypesList->filter(function ($type) use ($configuredTypeIds) {
        $cat = strtolower((string) $type->category);
        return ($cat === 'transfer' || $cat === 'settlement') && ! in_array((int) $type->id, $configuredTypeIds, true);
    });

    $digitalEntryCodes = ['paytm', 'card', 'upi', 'gpay', 'bank_transfer', 'online'];
@endphp

<div class="space-y-8">
    <!-- Header & Shop Selection -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <a href="{{ route('admin.cashbook.settings') }}" class="mb-2 inline-flex items-center gap-1 text-xs font-black text-slate-400 hover:text-slate-700">
                    <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                    All Shops
                </a>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">{{ $currentShop->name }}</h1>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">Active Shop</span>
                </div>
                <p class="mt-1 font-mono text-xs font-bold text-slate-400">{{ $currentShop->code ?: 'SHOP-'.$currentShop->shop_id }}</p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <!-- Compact Show Disabled Toggle Switch -->
                <div class="flex items-center gap-2.5 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700">
                    <label for="toggle-show-disabled" class="cursor-pointer select-none">Show Disabled Entries</label>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" id="toggle-show-disabled" onchange="toggleShowDisabled(this.checked)" class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-slate-900"></div>
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="#income-sales" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700 hover:bg-emerald-100">Income &amp; Sales</a>
                    <a href="#expenses" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-black text-rose-700 hover:bg-rose-100">Expenses</a>
                    <a href="#transfers-settlements" class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-black text-indigo-700 hover:bg-indigo-100">Transfers &amp; Settlements</a>
                    <a href="#collection" class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-black text-sky-700 hover:bg-sky-100">Collection Form</a>
                    <a href="#historical-fetch" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100">Historical Fetch</a>
                    <a href="#instructions-guide" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-black text-amber-800 hover:bg-amber-100 inline-flex items-center gap-1">
                        <i data-lucide="help-circle" class="h-3.5 w-3.5"></i>
                        Setup Guide
                    </a>
                    <a href="{{ route('admin.cashbook.settings.shop.demo', ['shop' => $currentShop->shop_id]) }}" class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-black text-indigo-700 hover:bg-indigo-100 inline-flex items-center gap-1">
                        <i data-lucide="play-circle" class="h-3.5 w-3.5"></i>
                        Demo Cashbook
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 1 — Income & Sales Section -->
    <section id="income-sales" class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                    <i data-lucide="trending-up" class="h-5 w-5"></i>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-extrabold text-slate-950">Income &amp; Sales</h2>
                        <span id="income-count-badge" class="rounded-lg bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200"
                              data-active="{{ $activeIncomeCount }}" data-disabled="{{ $disabledIncomeCount }}">
                            {{ $activeIncomeCount }} Active
                        </span>
                    </div>
                    <p class="text-xs font-semibold text-slate-500">Organize income sources into custom headers. Drag cards between headers or reorder headers.</p>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="openCreateHeaderModal('income')" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-xs font-black text-emerald-800 hover:bg-emerald-100 transition shadow-xs">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Create Income Header
                </button>
                <button type="button" onclick="openSearchModal('income')" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-extrabold text-slate-800 hover:bg-slate-50 transition shadow-xs">
                    <i data-lucide="search" class="h-4 w-4"></i>
                    Search &amp; Add Income
                </button>
                <button type="button" onclick="openCreateModal('income')" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-600 px-3.5 py-2 text-xs font-black text-white hover:bg-emerald-700 transition shadow-xs">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Create New Income
                </button>
            </div>
        </div>

        <!-- INCOME HEADERS CONTAINER (Draggable Headers) -->
        <div id="income-headers-container" class="space-y-6">
            @foreach($incomeHeaders as $header)
                @php
                    $headerSettings = $incomeRows->where('header_group_id', $header->id)->sortBy('header_display_order')->values();
                @endphp
                <div class="header-group-box rounded-3xl border border-slate-200 bg-slate-50/50 p-5 shadow-xs transition"
                     data-header-id="{{ $header->id }}"
                     data-header-type="income"
                     draggable="true"
                     ondragstart="handleHeaderDragStart(event)"
                     ondragover="handleHeaderDragOver(event)"
                     ondrop="handleHeaderDrop(event)"
                     ondragend="handleHeaderDragEnd(event)">
                    <div class="flex items-center justify-between border-b border-slate-200/80 pb-3 mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="header-drag-handle cursor-grab active:cursor-grabbing text-slate-400 hover:text-slate-700" title="Drag to reorder header">
                                <i data-lucide="grip-vertical" class="h-4 w-4"></i>
                            </span>
                            <div class="flex flex-col gap-1">
                                <h3 class="text-sm font-black text-slate-950 tracking-tight flex items-center gap-2 flex-wrap">
                                    <span id="header-name-{{ $header->id }}">{{ $header->name }}</span>
                                    <span class="rounded-full bg-slate-200/70 px-2 py-0.5 text-[10px] font-extrabold text-slate-600">
                                        {{ $headerSettings->where('enabled', true)->count() }} entries
                                    </span>
                                    @php
                                        $resolver = app(\App\Services\Cashbook\CashFlowResolutionService::class);
                                        $headerSummary = $resolver->resolveHeaderSummaryLabel($header);
                                        $childDests = $header->cash_flow_mode === 'entry_decides' ? $resolver->resolveHeaderChildDestinations($header) : [];
                                    @endphp
                                    <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-extrabold text-emerald-800">
                                        {{ $headerSummary }}
                                    </span>
                                    @if($header->product_tagging_enabled)
                                        <span class="rounded-full bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[10px] font-black text-indigo-700 inline-flex items-center gap-1">
                                            <i data-lucide="tag" class="h-3 w-3"></i> Tagging ON
                                        </span>
                                    @endif
                                    @if($header->show_both_sides)
                                        <span class="rounded-full bg-purple-50 border border-purple-200 px-2 py-0.5 text-[10px] font-black text-purple-700 inline-flex items-center gap-1">
                                            <i data-lucide="arrow-right-left" class="h-3 w-3"></i> Both Sides ON
                                        </span>
                                    @endif
                                </h3>
                                @if(!empty($childDests))
                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                        @foreach($childDests as $dest)
                                            <span class="rounded-md bg-white border border-slate-200 px-2 py-0.5 text-[10px] font-extrabold text-slate-700 shadow-2xs">
                                                {{ $dest }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" onclick="openSearchModal('income', {{ $header->id }})" class="inline-flex items-center gap-1 text-[11px] font-black text-emerald-700 hover:text-emerald-900 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                                <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add Income Source
                            </button>
                            <button type="button" onclick="openEditHeaderModal({{ json_encode([
                                'id' => $header->id,
                                'name' => $header->name,
                                'type' => $header->type,
                                'cash_flow_mode' => $header->cash_flow_mode,
                                'company_account_id' => $header->company_account_id,
                                'from_balance' => $header->from_balance,
                                'to_balance' => $header->to_balance,
                                'enabled' => $header->enabled ? 1 : 0,
                                'note_enabled' => $header->note_enabled ? 1 : 0,
                                'product_tagging_enabled' => $header->product_tagging_enabled ? 1 : 0,
                                'show_both_sides' => $header->show_both_sides ? 1 : 0,
                            ]) }})" class="p-1 text-slate-400 hover:text-slate-700" title="Configure Header Settings">

                                <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                            </button>
                            <button type="button" onclick="deleteHeaderGroup({{ $header->id }}, '{{ addslashes($header->name) }}')" class="p-1 text-slate-400 hover:text-rose-600" title="Delete Header">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Header Cards Dropzone Grid (4 columns) -->
                    <div class="cards-dropzone grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 min-h-[85px] p-2 rounded-2xl border border-dashed border-slate-200/80 bg-white/60"
                         data-header-id="{{ $header->id }}"
                         data-header-type="income"
                         ondragover="handleCardDragOver(event)"
                         ondrop="handleCardDrop(event)">
                        @forelse($headerSettings as $setting)
                            @include('admin.cashbook.settings.partials.card', ['setting' => $setting, 'digitalEntryCodes' => $digitalEntryCodes, 'fundingSourceBusinessLabels' => $fundingSourceBusinessLabels])
                        @empty
                            <div class="no-cards-placeholder col-span-full py-6 text-center text-xs font-bold text-slate-400">
                                No entries assigned yet. Drag cards here or click "+ Add Income Source".
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach

            <!-- UNASSIGNED INCOME HEADER BOX -->
            @php
                $unassignedIncomeRows = $incomeRows->whereNull('header_group_id')->sortBy('header_display_order')->values();
            @endphp
            <div class="header-group-box rounded-3xl border border-slate-200 bg-slate-50/50 p-5 shadow-xs"
                 data-header-id="unassigned"
                 data-header-type="income">
                <div class="flex items-center justify-between border-b border-slate-200/80 pb-3 mb-4">
                    <h3 class="text-sm font-black text-slate-600 tracking-tight flex items-center gap-2">
                        <span>Unassigned Income</span>
                        <span class="rounded-full bg-slate-200/70 px-2 py-0.5 text-[10px] font-extrabold text-slate-600">
                            {{ $unassignedIncomeRows->where('enabled', true)->count() }} entries
                        </span>
                    </h3>
                </div>

                <div class="cards-dropzone grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 min-h-[85px] p-2 rounded-2xl border border-dashed border-slate-200/80 bg-white/60"
                     data-header-id="unassigned"
                     data-header-type="income"
                     ondragover="handleCardDragOver(event)"
                     ondrop="handleCardDrop(event)">
                    @forelse($unassignedIncomeRows as $setting)
                        @include('admin.cashbook.settings.partials.card', ['setting' => $setting, 'digitalEntryCodes' => $digitalEntryCodes, 'fundingSourceBusinessLabels' => $fundingSourceBusinessLabels])
                    @empty
                        <div class="no-cards-placeholder col-span-full py-6 text-center text-xs font-bold text-slate-400">
                            All income entries are assigned to headers.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 2 — Expenses Section -->
    <section id="expenses" class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-700">
                    <i data-lucide="trending-down" class="h-5 w-5"></i>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-extrabold text-slate-950">Expenses</h2>
                        <span id="expense-count-badge" class="rounded-lg bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-700 border border-rose-200"
                              data-active="{{ $activeExpenseCount }}" data-disabled="{{ $disabledExpenseCount }}">
                            {{ $activeExpenseCount }} Active
                        </span>
                    </div>
                    <p class="text-xs font-semibold text-slate-500">Organize expense items into custom headers. Drag cards between headers or reorder headers.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="openCreateHeaderModal('expense')" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-black text-rose-800 hover:bg-rose-100 transition shadow-xs">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Create Expense Header
                </button>
                <button type="button" onclick="openSearchModal('expense')" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-extrabold text-slate-800 hover:bg-slate-50 transition shadow-xs">
                    <i data-lucide="search" class="h-4 w-4"></i>
                    Search &amp; Add Expense
                </button>
                <button type="button" onclick="openCreateModal('expense')" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-600 px-3.5 py-2 text-xs font-black text-white hover:bg-rose-700 transition shadow-xs">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Create New Expense
                </button>
            </div>
        </div>

        <!-- EXPENSE HEADERS CONTAINER (Draggable Headers) -->
        <div id="expense-headers-container" class="space-y-6">
            @foreach($expenseHeaders as $header)
                @php
                    $headerSettings = $expenseRows->where('header_group_id', $header->id)->sortBy('header_display_order')->values();
                @endphp
                <div class="header-group-box rounded-3xl border border-slate-200 bg-slate-50/50 p-5 shadow-xs transition"
                     data-header-id="{{ $header->id }}"
                     data-header-type="expense"
                     draggable="true"
                     ondragstart="handleHeaderDragStart(event)"
                     ondragover="handleHeaderDragOver(event)"
                     ondrop="handleHeaderDrop(event)"
                     ondragend="handleHeaderDragEnd(event)">
                    <div class="flex items-center justify-between border-b border-slate-200/80 pb-3 mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="header-drag-handle cursor-grab active:cursor-grabbing text-slate-400 hover:text-slate-700" title="Drag to reorder header">
                                <i data-lucide="grip-vertical" class="h-4 w-4"></i>
                            </span>
                            <div class="flex flex-col gap-1">
                                <h3 class="text-sm font-black text-slate-950 tracking-tight flex items-center gap-2 flex-wrap">
                                    <span id="header-name-{{ $header->id }}">{{ $header->name }}</span>
                                    <span class="rounded-full bg-slate-200/70 px-2 py-0.5 text-[10px] font-extrabold text-slate-600">
                                        {{ $headerSettings->where('enabled', true)->count() }} entries
                                    </span>
                                    @php
                                        $resolver = app(\App\Services\Cashbook\CashFlowResolutionService::class);
                                        $headerSummary = $resolver->resolveHeaderSummaryLabel($header);
                                        $childSources = $header->cash_flow_mode === 'entry_decides' ? $resolver->resolveHeaderChildDestinations($header) : [];
                                    @endphp
                                    <span class="rounded-full bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-extrabold text-rose-800">
                                        {{ $headerSummary }}
                                    </span>
                                    @if($header->product_tagging_enabled)
                                        <span class="rounded-full bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[10px] font-black text-indigo-700 inline-flex items-center gap-1">
                                            <i data-lucide="tag" class="h-3 w-3"></i> Tagging ON
                                        </span>
                                    @endif
                                    @if($header->show_both_sides)
                                        <span class="rounded-full bg-purple-50 border border-purple-200 px-2 py-0.5 text-[10px] font-black text-purple-700 inline-flex items-center gap-1">
                                            <i data-lucide="arrow-right-left" class="h-3 w-3"></i> Both Sides ON
                                        </span>
                                    @endif
                                </h3>
                                @if(!empty($childSources))
                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                        @foreach($childSources as $src)
                                            <span class="rounded-md bg-white border border-slate-200 px-2 py-0.5 text-[10px] font-extrabold text-slate-700 shadow-2xs">
                                                {{ $src }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" onclick="openSearchModal('expense', {{ $header->id }})" class="inline-flex items-center gap-1 text-[11px] font-black text-rose-700 hover:text-rose-900 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200">
                                <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add Expense Source
                            </button>
                            <button type="button" onclick="openEditHeaderModal({{ json_encode([
                                'id' => $header->id,
                                'name' => $header->name,
                                'type' => $header->type,
                                'cash_flow_mode' => $header->cash_flow_mode,
                                'company_account_id' => $header->company_account_id,
                                'from_balance' => $header->from_balance,
                                'to_balance' => $header->to_balance,
                                'enabled' => $header->enabled ? 1 : 0,
                                'note_enabled' => $header->note_enabled ? 1 : 0,
                                'product_tagging_enabled' => $header->product_tagging_enabled ? 1 : 0,
                                'show_both_sides' => $header->show_both_sides ? 1 : 0,
                            ]) }})" class="p-1 text-slate-400 hover:text-slate-700" title="Configure Header Settings">

                                <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                            </button>
                            <button type="button" onclick="deleteHeaderGroup({{ $header->id }}, '{{ addslashes($header->name) }}')" class="p-1 text-slate-400 hover:text-rose-600" title="Delete Header">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Header Cards Dropzone Grid (4 columns) -->
                    <div class="cards-dropzone grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 min-h-[85px] p-2 rounded-2xl border border-dashed border-slate-200/80 bg-white/60"
                         data-header-id="{{ $header->id }}"
                         data-header-type="expense"
                         ondragover="handleCardDragOver(event)"
                         ondrop="handleCardDrop(event)">
                        @forelse($headerSettings as $setting)
                            @include('admin.cashbook.settings.partials.card', ['setting' => $setting, 'digitalEntryCodes' => $digitalEntryCodes, 'fundingSourceBusinessLabels' => $fundingSourceBusinessLabels])
                        @empty
                            <div class="no-cards-placeholder col-span-full py-6 text-center text-xs font-bold text-slate-400">
                                No entries assigned yet. Drag cards here or click "+ Add Expense Source".
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach

            <!-- UNASSIGNED EXPENSES HEADER BOX -->
            @php
                $unassignedExpenseRows = $expenseRows->whereNull('header_group_id')->sortBy('header_display_order')->values();
            @endphp
            <div class="header-group-box rounded-3xl border border-slate-200 bg-slate-50/50 p-5 shadow-xs"
                 data-header-id="unassigned"
                 data-header-type="expense">
                <div class="flex items-center justify-between border-b border-slate-200/80 pb-3 mb-4">
                    <h3 class="text-sm font-black text-slate-600 tracking-tight flex items-center gap-2">
                        <span>Unassigned Expenses</span>
                        <span class="rounded-full bg-slate-200/70 px-2 py-0.5 text-[10px] font-extrabold text-slate-600">
                            {{ $unassignedExpenseRows->where('enabled', true)->count() }} entries
                        </span>
                    </h3>
                </div>

                <div class="cards-dropzone grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 min-h-[85px] p-2 rounded-2xl border border-dashed border-slate-200/80 bg-white/60"
                     data-header-id="unassigned"
                     data-header-type="expense"
                     ondragover="handleCardDragOver(event)"
                     ondrop="handleCardDrop(event)">
                    @forelse($unassignedExpenseRows as $setting)
                        @include('admin.cashbook.settings.partials.card', ['setting' => $setting, 'digitalEntryCodes' => $digitalEntryCodes, 'fundingSourceBusinessLabels' => $fundingSourceBusinessLabels])
                    @empty
                        <div class="no-cards-placeholder col-span-full py-6 text-center text-xs font-bold text-slate-400">
                            All expense entries are assigned to headers.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 3 — Transfers & Settlements Section -->
    <section id="transfers-settlements" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700">
                    <i data-lucide="arrow-right-left" class="h-5 w-5"></i>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-extrabold text-slate-950">Transfers &amp; Settlements</h2>
                        <span id="transfer-count-badge" class="rounded-lg bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700 border border-indigo-200"
                              data-active="{{ $activeTransferCount }}" data-disabled="{{ $disabledTransferCount }}">
                            {{ $activeTransferCount }} Active
                        </span>
                    </div>
                    <p class="text-xs font-semibold text-slate-500">Configure transfer entries, source balance movements, and supermarket settlement rules for {{ $currentShop->name }}.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="openSearchModal('transfer')" class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-xs font-black text-indigo-800 hover:bg-indigo-100 transition shadow-xs">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Search &amp; Add Transfer
                </button>
                <a href="{{ route('admin.cashbook.settings.shop.settlements.create', $currentShop->slug ?: $currentShop->shop_id) }}" class="inline-flex items-center rounded-xl bg-indigo-700 px-4 py-3 text-xs font-black text-white hover:bg-indigo-800">Create Settlement</a>
            </div>
        </div>

        <!-- Part A: Single Entry Transfers & Settlements -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Transfer &amp; Settlement Entries</h3>
            </div>

            @if($transferRows->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-center text-xs font-bold text-slate-400">
                    No transfer or settlement entries configured for this shop.
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($transferRows as $setting)
                        @include('admin.cashbook.settings.partials.card', [
                            'setting' => $setting,
                            'fundingSourceBusinessLabels' => $fundingSourceBusinessLabels,
                            'digitalEntryCodes' => $digitalEntryCodes
                        ])
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 space-y-3">
            <h3 class="text-base font-black text-slate-950">Settlement calculations</h3>
            <p class="text-sm text-slate-600">Configure Income, Expense, and custom settlements by adding or subtracting categories. Only enabled settlement results appear in the summary.</p>
            <a href="{{ route('admin.cashbook.settings.shop.settlements.index', $currentShop->slug ?: $currentShop->shop_id) }}" class="inline-flex rounded-xl bg-indigo-700 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-800">Manage Settlements</a>
        </div>
    </section>

    <!-- Collection Form Configuration -->
    <section id="collection" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
        <form onsubmit="saveCollectionSettings(event)">
            <input type="hidden" name="shop_id" value="{{ $currentShop->shop_id }}">
            <input type="hidden" name="enabled" value="0">

            <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                        <i data-lucide="list-checks" class="h-5 w-5"></i>
                    </span>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-950">Collection Form Grouping</h2>
                        <p class="text-xs font-semibold text-slate-500">Enable this to show one combined collection form for this shop.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black text-slate-700">
                        <input type="checkbox" name="enabled" value="1" @checked($collectionGroup?->enabled) class="rounded border-slate-300 text-emerald-600">
                        Enable Collection
                    </label>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-xs font-black text-white hover:bg-slate-800">Save Collection</button>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach(['income' => 'Income Rows', 'expense' => 'Expense Rows'] as $category => $title)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">{{ $title }}</h3>
                        <div class="flex gap-2">
                            <input type="text"
                                   data-collection-custom-row="{{ $category }}"
                                   placeholder="New {{ strtolower($category) }} row"
                                   class="h-9 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none sm:w-44">
                            <button type="button"
                                    onclick="createCollectionCustomRow(event, '{{ $category }}')"
                                    class="h-9 rounded-lg bg-slate-950 px-3 text-[10px] font-black text-white hover:bg-slate-800">
                                Add
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-2">
                        @foreach($settingsByCategory->get($category, collect())->where('enabled', true) as $setting)
                            <label class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-xs">
                                <span>{{ $setting->displayName() }}</span>
                                <input type="checkbox"
                                       name="{{ $category === 'income' ? 'income_entry_type_ids[]' : 'expense_entry_type_ids[]' }}"
                                       value="{{ $setting->entry_type_id }}"
                                       @checked(in_array((int) $setting->entry_type_id, $category === 'income' ? $collectionIncomeIds : $collectionExpenseIds, true))
                                       class="rounded border-slate-300 text-emerald-600">
                            </label>
                        @endforeach
                        @if($settingsByCategory->get($category, collect())->where('enabled', true)->isEmpty())
                            <div class="rounded-lg border border-dashed border-slate-300 px-3 py-4 text-center text-xs font-bold text-slate-400">
                                Enable {{ strtolower($title) }} above first.
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
            </div>
        </form>
    </section>

    <!-- Historical Bank Collection Fetch -->
    <section id="historical-fetch" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
        <div class="border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                    <i data-lucide="history" class="h-5 w-5"></i>
                </span>
                <div>
                    <h2 class="text-lg font-extrabold text-slate-950">Historical Bank Collection Fetch</h2>
                    <p class="text-xs font-semibold text-slate-500">Fetch past shop online/digital collections (e.g. Paytm, Card) into a linked bank account for reconciliation.</p>
                </div>
            </div>
        </div>

        <form id="historical-fetch-form" onsubmit="previewHistoricalFetch(event)" class="mt-5 space-y-4">
            <input type="hidden" name="shop_id" value="{{ $currentShop->shop_id }}">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Category / Row</label>
                    <select id="hist-entry-type-id" name="entry_type_id" onchange="onHistoricalCategoryChange()" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                        @foreach($settingsByCategory->get('income', collect())->where('enabled', true) as $setting)
                            <option value="{{ $setting->entry_type_id }}" data-bank-id="{{ $setting->company_account_id ?: '' }}">
                                {{ $setting->displayName() }} ({{ $setting->entryType->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Destination Bank</label>
                    <select id="hist-company-account-id" name="company_account_id" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                        @foreach($companyAccounts as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->name }} ({{ $account->bank_name ?: $account->account_type }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Period Preset</label>
                    <select id="hist-preset" onchange="applyPeriodPreset(this.value)" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="custom">Custom Period</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">From Date</label>
                        <input type="date" id="hist-from-date" name="from_date" value="{{ now()->startOfMonth()->toDateString() }}" required class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500">To Date</label>
                        <input type="date" id="hist-to-date" name="to_date" value="{{ now()->toDateString() }}" required class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[11px] font-semibold text-slate-500">Preview calculates eligibility using shop sales date without changing live data.</p>
                <button type="submit" id="btn-preview-hist" class="rounded-xl bg-slate-950 px-5 py-2.5 text-xs font-black text-white hover:bg-slate-800">
                    Preview Historical Entries
                </button>
            </div>
        </form>

        <div id="hist-preview-container" class="mt-6 hidden border-t border-slate-100 pt-5"></div>
    </section>

    <!-- INSTRUCTIONS / HOW-TO GUIDE: CONFIGURING CATEGORIES & SALES DEDUCTIONS -->
    <section id="instructions-guide" class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-7 shadow-xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 shrink-0">
                    <i data-lucide="help-circle" class="h-5 w-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-black tracking-tight text-slate-950">How to Configure Categories &amp; Sales Deductions</h3>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Instructions to set up deductions (like Casio Delivery, Shop to Supermarket) or custom categories for any shop.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 w-fit">
                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                Standardized Rule Engine
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Step 1 -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">1</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">Locate Category</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Navigate to <strong class="text-slate-900">Transfers &amp; Movements</strong> or <strong class="text-slate-900">Sales</strong> tab. Find the category row (e.g. <em>Casio Delivery</em> or <em>Shop to Supermarket</em>) or search to add it.
                </p>
            </div>

            <!-- Step 2 -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">2</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">Assign to Sales</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Toggle <strong class="text-emerald-700">Enabled: ON</strong> and select <strong class="text-slate-900">Header Group: SALES</strong> so it displays directly on the shop's sales entry card.
                </p>
            </div>

            <!-- Step 3 -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">3</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">Set Sales Deduction</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Set <strong class="text-slate-900">Include in Sales: ON</strong> and <strong class="text-rose-700">Payable Direction: Minus (−)</strong>. Keep <strong class="text-slate-900">Include in Expense: OFF</strong> so it does not count as expense.
                </p>
            </div>

            <!-- Step 4 -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-black text-xs flex items-center justify-center shrink-0">4</span>
                    <h4 class="text-xs font-extrabold uppercase text-slate-900">Funding &amp; Save</h4>
                </div>
                <p class="text-xs font-medium text-slate-600 leading-relaxed">
                    Set <strong class="text-slate-900">Default Funding: Sales Cash</strong> and <strong class="text-slate-900">Settlement: Decrease</strong>, then click <strong class="text-slate-900">Save Changes</strong>.
                </p>
            </div>
        </div>

        <!-- Formula & Visual Preview Banner -->
        <div class="rounded-2xl border border-slate-200 bg-slate-900 text-white p-4 sm:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-rose-500/20 text-rose-300 font-mono font-black text-xs">−</span>
                    <span class="text-xs font-black uppercase tracking-wider text-slate-300">Sales Formula Calculation</span>
                </div>
                <div class="font-mono text-xs sm:text-sm font-bold text-slate-100">
                    Total Sales = Cash + Card + Paytm + Delivery &minus; <span class="text-rose-300 font-black">(Casio Delivery + Deductions)</span>
                </div>
            </div>
            <div class="text-xs text-slate-400 max-w-md font-medium">
                Deductions display with an explicit <span class="text-rose-400 font-bold">− (rose)</span> badge in the owner cashbook and subtract cleanly without inflating total expenses.
            </div>
        </div>
    </section>

    <!-- PER-SETTING CONFIGURATION MODALS -->
    @foreach($allShopRows as $setting)
        @php
            $settingCategory = strtolower((string) ($setting->entryType?->category ?? ''));
            $isIncomeCategory = $settingCategory === 'income' || $setting->include_in_sales || $setting->include_in_income;
            $compatibleHeaderGroups = $isIncomeCategory ? $incomeHeaders : $expenseHeaders;
        @endphp
        <div id="config-modal-{{ $setting->id }}" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
            <div class="relative w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-extrabold text-slate-950">{{ $setting->displayName() }}</h3>
                            @if($setting->display_name && $setting->display_name !== $setting->entryType?->name)
                                <span class="rounded-md bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">Custom Name</span>
                            @endif
                            <span class="font-mono text-xs font-bold text-slate-400">({{ $setting->entryType->code }})</span>
                        </div>
                        <p class="text-xs font-semibold text-slate-500">Configure category name, funding, account routing, header group, and accounting flags.</p>
                    </div>
                    <button type="button" onclick="closeConfigModal({{ $setting->id }})" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <form id="setting-{{ $setting->id }}" onsubmit="saveShopSetting(event, {{ $setting->id }})" class="mt-5 space-y-5">
                    <!-- Category Display Name (Shop-specific override) -->
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <label for="setting-display-name-{{ $setting->id }}" class="block text-xs font-black uppercase tracking-wider text-slate-700">Category Name</label>
                            @if($setting->display_name)
                                <button type="button"
                                        onclick="resetCategoryDisplayName({{ $setting->id }})"
                                        class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 hover:underline inline-flex items-center gap-1 cursor-pointer">
                                    <i data-lucide="rotate-ccw" class="h-3 w-3"></i>
                                    Reset to Default
                                </button>
                            @endif
                        </div>
                        <input type="text"
                               id="setting-display-name-{{ $setting->id }}"
                               name="display_name"
                               value="{{ $setting->display_name ?? '' }}"
                               placeholder="{{ $setting->entryType->name }}"
                               maxlength="255"
                               class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none shadow-2xs">
                        <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500">
                            <span>Default: <strong class="text-slate-700">{{ $setting->entryType->name }}</strong></span>
                            <span class="font-mono text-[10px] text-slate-400">Code: {{ $setting->entryType->code }}</span>
                        </div>
                    </div>

                    <!-- Header Group Assignment Dropdown -->
                    <div>
                        <label class="block text-xs font-extrabold text-slate-900 mb-1.5">Header Group</label>
                        <select name="header_group_id" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                            <option value="">Unassigned</option>
                            @foreach($compatibleHeaderGroups as $hdr)
                                <option value="{{ $hdr->id }}" @selected((int) $setting->header_group_id === (int) $hdr->id)>{{ $hdr->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status Toggle -->
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div>
                            <div class="text-xs font-extrabold text-slate-900">Enable Entry for Shop</div>
                            <div class="text-[11px] font-semibold text-slate-500">Active entries appear in daily cashbook forms.</div>
                        </div>
                        <input type="hidden" name="enabled" value="0">
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" name="enabled" value="1" @checked($setting->enabled) class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>

                    <!-- Note Field Toggle -->
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div>
                            <div class="text-xs font-extrabold text-slate-900">Note Field</div>
                            <div class="text-[11px] font-semibold text-slate-500">Show note input field in Cashbook for this category.</div>
                        </div>
                        <input type="hidden" name="note_enabled" value="0">
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" name="note_enabled" value="1" @checked($setting->note_enabled) class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>

                    <!-- Funding Source / Paid From -->
                    <div>
                        <label class="block text-xs font-extrabold text-slate-900 mb-1.5">
                            {{ $setting->entryType->category === 'expense' ? 'Paid From (Funding Source)' : 'Money Destination / Funding Source' }}
                        </label>
                        <select name="default_funding_source" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                            @foreach($fundingSources as $value => $label)
                                <option value="{{ $value }}" @selected($setting->default_funding_source === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Company Account Routing -->
                    <div>
                        <label class="block text-xs font-extrabold text-slate-900 mb-1.5">Destination Company Bank Account</label>
                        <select name="company_account_id" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                            <option value="">None (Shop Cash / Unmapped)</option>
                            @foreach($companyAccounts as $account)
                                <option value="{{ $account->id }}" @selected((int) $setting->company_account_id === (int) $account->id)>
                                    {{ $account->name }} ({{ $account->bank_name ?: $account->account_type }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Bank Adjustment Rules Button -->
                    @php
                        $rulesForThisSetting = isset($bankAdjustmentRules) ? ($bankAdjustmentRules->get($setting->entry_type_id) ?? collect()) : collect();
                    @endphp
                    <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                        <span class="text-xs font-bold text-slate-600">Bank Settlement Adjustment Rules:</span>
                        <button type="button"
                                id="btn-adj-rules-{{ $setting->entry_type_id }}"
                                onclick="openBankAdjModal({{ $currentShop->shop_id }}, {{ $setting->entry_type_id }}, '{{ addslashes($setting->entryType?->name) }}')"
                                class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border transition {{ $rulesForThisSetting->isNotEmpty() ? 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200' }}">
                            <i data-lucide="sliders-horizontal" class="h-3.5 w-3.5"></i>
                            <span>Adjustments ({{ $rulesForThisSetting->count() }})</span>
                        </button>
                    </div>

                    <!-- COLLAPSIBLE ADVANCED ACCOUNTING -->
                    <details class="group border-t border-slate-200 pt-4">
                        <summary class="flex cursor-pointer items-center justify-between text-xs font-black text-amber-900 select-none">
                            <span class="inline-flex items-center gap-1.5"><i data-lucide="settings-2" class="h-3.5 w-3.5"></i> Advanced Accounting Engine Overrides</span>
                            <i data-lucide="chevron-down" class="h-4 w-4 transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="mt-3 space-y-4 rounded-2xl border border-amber-200 bg-amber-50/60 p-4 text-xs">
                            <p class="text-[11px] font-medium text-amber-900">Advanced flags directly control posting deltas, P&amp;L effects, and secondary entries.</p>

                            <div class="grid grid-cols-2 gap-3">
                                @foreach(['include_in_sales' => 'Include in Sales', 'include_in_income' => 'Include in Income', 'include_in_expense' => 'Include in Expense', 'include_in_pl' => 'Include in P&L'] as $field => $label)
                                    <label class="flex items-center gap-2 font-bold text-slate-800">
                                        <input type="hidden" name="{{ $field }}" value="0">
                                        <input type="checkbox" name="{{ $field }}" value="1" {{ $setting->{$field} ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-slate-900">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="border-t border-amber-200/60 pt-3">
                                <label class="block text-[11px] font-extrabold text-slate-900 mb-1">Payable Balance Direction</label>
                                @php
                                    $currentDirection = $setting->payable_direction ?: (($setting->entryType && in_array($setting->entryType->code, ['company_to_petty', 'company_paid_shop', 'company_paid_vendor'])) ? 'minus' : 'add');
                                @endphp
                                <input type="hidden" id="inc-pay-{{ $setting->id }}" name="include_in_payable" value="{{ $setting->include_in_payable ? 1 : 0 }}">
                                <input type="hidden" id="pay-dir-{{ $setting->id }}" name="payable_direction" value="{{ $currentDirection }}">
                                <div class="flex items-center gap-4 text-xs">
                                    <label class="inline-flex items-center gap-1.5 font-bold text-emerald-700 cursor-pointer">
                                        <input type="radio" name="pay_choice_{{ $setting->id }}" value="add"
                                               {{ ($setting->include_in_payable && $currentDirection === 'add') ? 'checked' : '' }}
                                               onchange="setPayableChoice({{ $setting->id }}, 'add')"
                                               class="h-4 w-4 text-emerald-600">
                                        Add to Payable
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 font-bold text-rose-700 cursor-pointer">
                                        <input type="radio" name="pay_choice_{{ $setting->id }}" value="minus"
                                               {{ ($setting->include_in_payable && $currentDirection === 'minus') ? 'checked' : '' }}
                                               onchange="setPayableChoice({{ $setting->id }}, 'minus')"
                                               class="h-4 w-4 text-rose-600">
                                        Minus from Payable
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 font-bold text-slate-500 cursor-pointer">
                                        <input type="radio" name="pay_choice_{{ $setting->id }}" value="none"
                                               {{ !$setting->include_in_payable ? 'checked' : '' }}
                                               onchange="setPayableChoice({{ $setting->id }}, 'none')"
                                               class="h-4 w-4 text-slate-400">
                                        Off
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2 border-t border-amber-200/60 pt-3">
                                @foreach(['settlement_behavior' => 'Settlement', 'petty_behavior' => 'Petty Cash', 'company_pending_behavior' => 'Company Pending'] as $field => $label)
                                    <div>
                                        <label class="block text-[10px] font-extrabold uppercase text-slate-600 mb-1">{{ $label }}</label>
                                        <select name="{{ $field }}" class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-700">
                                            @foreach($effects as $value => $effectLabel)
                                                <option value="{{ $value }}" {{ (($setting->{$field} ?: 'none') === $value) ? 'selected' : '' }}>{{ $effectLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>

                            <div class="border-t border-amber-200/60 pt-3 space-y-2">
                                <label class="flex items-center gap-2 font-bold text-slate-800">
                                    <input type="hidden" name="generates_secondary_entry" value="0">
                                    <input type="checkbox" name="generates_secondary_entry" value="1" @checked($setting->generates_secondary_entry) class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                                    <span>Auto-generate Secondary Child Entry</span>
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-extrabold uppercase text-slate-600">Child Entry Type</label>
                                        <select name="secondary_entry_type_id" class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-700">
                                            <option value="">None</option>
                                            @foreach($allShopRows->where('enabled', true) as $childSetting)
                                                @if($childSetting->entry_type_id !== $setting->entry_type_id)
                                                    <option value="{{ $childSetting->entry_type_id }}" @selected((int) $setting->secondary_entry_type_id === (int) $childSetting->entry_type_id)>
                                                        {{ $childSetting->displayName() }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-extrabold uppercase text-slate-600">Child Amount</label>
                                        <div class="flex gap-1">
                                            <select name="secondary_amount_mode" class="h-8 rounded-lg border border-slate-200 bg-white px-1 text-[11px] font-bold text-slate-700">
                                                <option value="same_amount" @selected($setting->secondary_amount_mode === 'same_amount')>Same</option>
                                                <option value="percentage" @selected($setting->secondary_amount_mode === 'percentage')>Percent</option>
                                            </select>
                                            <input type="number" min="0" step="0.01" name="secondary_amount_value" value="{{ $setting->secondary_amount_value }}"
                                                   class="h-8 w-16 rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-700" placeholder="%">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </details>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" onclick="closeConfigModal({{ $setting->id }})" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-xl bg-slate-950 px-5 py-2 text-xs font-black text-white hover:bg-slate-800">Save Configuration</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <!-- CREATE HEADER MODAL -->
    <div id="create-header-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
        <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-950 inline-flex items-center gap-1.5" id="create-header-modal-title">
                        <i data-lucide="plus-circle" class="h-4 w-4 text-emerald-600"></i> Create Header
                    </h3>
                    <p class="text-xs font-semibold text-slate-500">Create a shop business header for grouping categories.</p>
                </div>
                <button type="button" onclick="closeCreateHeaderModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form onsubmit="submitCreateHeaderGroup(event)" class="mt-5 space-y-4">
                <input type="hidden" id="create-header-type" name="type" value="income">

                <div>
                    <label class="block text-xs font-extrabold text-slate-900 mb-1">Header Name</label>
                    <input type="text" id="create-header-name" name="name" required placeholder="e.g. Sales, Purchase, Shop Operating Expenses"
                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
                </div>

                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <span class="text-xs font-bold text-slate-700">Header Type</span>
                    <span id="create-header-type-badge" class="rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-900 uppercase">Income</span>
                </div>

                <div>
                    <label class="block text-xs font-extrabold text-slate-900 mb-1">Product Tagging</label>
                    <select id="create-header-product-tagging" name="product_tagging_enabled" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
                        <option value="0" selected>OFF</option>
                        <option value="1">ON</option>
                    </select>
                    <p class="mt-1 text-[11px] font-semibold text-slate-500">When enabled, daily Cashbook entries can be tagged with Products from the existing Product catalog.</p>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="closeCreateHeaderModal()" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-slate-950 px-5 py-2 text-xs font-black text-white hover:bg-slate-800 inline-flex items-center gap-1">
                        <i data-lucide="plus" class="h-4 w-4"></i> Create Header
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT / CONFIGURE HEADER MODAL -->
    <div id="edit-header-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
        <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-950 inline-flex items-center gap-1.5" id="edit-header-modal-title">
                        <i data-lucide="sliders-horizontal" class="h-4 w-4 text-indigo-600"></i> Configure Header
                    </h3>
                    <p class="text-xs font-semibold text-slate-500">Define default cash-flow behavior for all entries in this header.</p>
                </div>
                <button type="button" onclick="closeEditHeaderModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form onsubmit="submitEditHeaderGroup(event)" class="mt-5 space-y-4">
                <input type="hidden" id="edit-header-id" name="id">
                <input type="hidden" id="edit-header-type" name="type" value="income">

                <div>
                    <label class="block text-xs font-extrabold text-slate-900 mb-1">Header Name</label>
                    <input type="text" id="edit-header-name" name="name" required placeholder="e.g. Sales, Cash Purchase, Shop Operating Expenses"
                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
                </div>

                <!-- INCOME CASH FLOW QUESTION -->
                <div id="header-income-cash-flow-box" class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 space-y-3">
                    <label class="block text-xs font-extrabold text-emerald-950 flex items-center gap-1.5">
                        <i data-lucide="arrow-down-left" class="h-4 w-4 text-emerald-600"></i>
                        <span>MONEY DESTINATION</span>
                    </label>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Header Cash Flow Mode</label>
                        <select id="edit-header-income-rule-mode" onchange="onIncomeRuleModeChange(this.value)" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                            <option value="same_for_all">Same destination for all entries</option>
                            <option value="entry_decides">Each entry chooses destination</option>
                        </select>
                    </div>

                    <div id="income-same-destination-container" class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-slate-700">Destination</label>
                        <select id="edit-header-income-single-dest" onchange="onIncomeSingleDestChange(this.value)" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                            <option value="shop_cash">Shop Cash</option>
                            <option value="company_account">Company Account</option>
                            <option value="none">No cash movement</option>
                        </select>
                    </div>
                </div>

                <!-- EXPENSE CASH FLOW QUESTION -->
                <div id="header-expense-cash-flow-box" class="rounded-2xl border border-rose-200 bg-rose-50/40 p-4 space-y-3">
                    <label class="block text-xs font-extrabold text-rose-950 flex items-center gap-1.5">
                        <i data-lucide="arrow-up-right" class="h-4 w-4 text-rose-600"></i>
                        <span>PAID FROM</span>
                    </label>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Header Cash Flow Mode</label>
                        <select id="edit-header-expense-rule-mode" onchange="onExpenseRuleModeChange(this.value)" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-rose-500 focus:outline-none">
                            <option value="same_for_all">Same source for all entries</option>
                            <option value="entry_decides">Each entry chooses source</option>
                        </select>
                    </div>

                    <div id="expense-same-source-container" class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-slate-700">Source</label>
                        <select id="edit-header-expense-single-source" onchange="onExpenseSingleSourceChange(this.value)" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-rose-500 focus:outline-none">
                            <option value="shop_cash">Shop Cash</option>
                            <option value="petty">Petty</option>
                            <option value="company">Company</option>
                            <option value="company_account">Company Account</option>
                            <option value="none">No cash movement</option>
                        </select>
                    </div>
                </div>


                <!-- COMPANY ACCOUNT SELECTOR -->
                <div id="header-company-account-box" class="hidden rounded-2xl border border-indigo-200 bg-indigo-50/40 p-4 space-y-2">
                    <label class="block text-xs font-extrabold text-indigo-950">Company Bank Account</label>
                    <select id="edit-header-company-account-id" name="company_account_id"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:outline-none">
                        <option value="">Select Company Account...</option>
                        @foreach($companyAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->bank_name ?: $acc->account_type }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- OTHERS / TRANSFERS MOVEMENT -->
                <div id="header-others-cash-flow-box" class="hidden rounded-2xl border border-indigo-200 bg-indigo-50/40 p-4 space-y-3">
                    <label class="block text-xs font-extrabold text-indigo-950 flex items-center gap-1.5">
                        <i data-lucide="arrow-right-left" class="h-4 w-4 text-indigo-600"></i>
                        <span>Money Movement</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">From</label>
                            <select id="edit-header-from-balance" name="from_balance"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:outline-none">
                                <option value="shop_cash">Shop Cash</option>
                                <option value="petty">Petty</option>
                                <option value="company">Company</option>
                                <option value="company_account">Company Account</option>
                                <option value="vendor">Vendor</option>
                                <option value="none">No Balance</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">To</label>
                            <select id="edit-header-to-balance" name="to_balance"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:outline-none">
                                <option value="shop_cash">Shop Cash</option>
                                <option value="petty">Petty</option>
                                <option value="company">Company</option>
                                <option value="company_account">Company Account</option>
                                <option value="vendor">Vendor</option>
                                <option value="none">No Balance</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-extrabold text-slate-900 mb-1">Show Both Sides</label>
                        <select id="edit-header-show-both-sides" name="show_both_sides" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:outline-none">
                            <option value="0">OFF</option>
                            <option value="1">ON</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-extrabold text-slate-900 mb-1">Note Field</label>
                        <select id="edit-header-note-enabled" name="note_enabled" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:outline-none">
                            <option value="0">OFF</option>
                            <option value="1">ON</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-extrabold text-slate-900 mb-1">Product Tagging</label>
                        <select id="edit-header-product-tagging" name="product_tagging_enabled" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:outline-none">
                            <option value="0">OFF</option>
                            <option value="1">ON</option>
                        </select>
                    </div>
                </div>


                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="closeEditHeaderModal()" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-slate-950 px-5 py-2 text-xs font-black text-white hover:bg-slate-800 inline-flex items-center gap-1">
                        <i data-lucide="check" class="h-4 w-4"></i> Save Header
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SEARCH & ADD MODALS -->
    <!-- Income Search Modal -->
    <div id="search-income-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
        <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-950 inline-flex items-center gap-1.5">
                        <i data-lucide="search" class="h-4 w-4 text-emerald-700"></i> Search &amp; Add Income Source
                    </h3>
                    <p class="text-xs font-semibold text-slate-500">Activate previously configured disabled settings or add new income entry types for {{ $currentShop->name }}.</p>
                </div>
                <button type="button" onclick="closeSearchModal('income')" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="mt-4">
                <input type="text" id="search-income-input" onkeyup="filterSearchList('income')" placeholder="Search income sources..."
                       class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
            </div>

            <div id="search-income-list" class="mt-4 space-y-2 max-h-72 overflow-y-auto">
                <!-- Disabled Income Settings (Re-enable) -->
                @foreach($incomeRows->where('enabled', false) as $disabledSetting)
                    <div class="search-item flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50/50 p-3 hover:bg-amber-100/60 transition"
                         data-name="{{ strtolower($disabledSetting->displayName().' '.$disabledSetting->entryType->name.' '.$disabledSetting->entryType->code) }}">
                        <div>
                            <div class="font-extrabold text-xs text-slate-950">{{ $disabledSetting->displayName() }}</div>
                            <div class="text-[10px] font-bold text-amber-800">Previously configured • Disabled</div>
                        </div>
                        <button type="button" onclick="reenableSetting({{ $disabledSetting->id }}, '{{ addslashes($disabledSetting->displayName()) }}')"
                                class="rounded-xl bg-emerald-700 px-3.5 py-1.5 text-xs font-black text-white hover:bg-emerald-800 shadow-xs inline-flex items-center gap-1">
                            <i data-lucide="circle-check" class="h-3.5 w-3.5"></i> Re-enable
                        </button>
                    </div>
                @endforeach

                <!-- Unconfigured Income Types (+ Add to Shop) -->
                @foreach($unconfiguredIncomeTypes as $type)
                    <div class="search-item flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 p-3 hover:bg-slate-100 transition"
                         data-name="{{ strtolower($type->name.' '.$type->code) }}">
                        <div>
                            <div class="font-extrabold text-xs text-slate-950">{{ $type->name }}</div>
                            <div class="text-[10px] font-bold text-slate-400">Not configured</div>
                        </div>
                        <button type="button" onclick="addEntryTypeToShop({{ $type->id }}, '{{ addslashes($type->name) }}', 'income')"
                                class="rounded-xl bg-slate-950 px-3.5 py-1.5 text-xs font-black text-white hover:bg-slate-800 inline-flex items-center gap-1">
                            <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add to Shop
                        </button>
                    </div>
                @endforeach

                <div id="no-search-income-results" class="hidden rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs font-bold text-slate-500">
                    <p>No matching income category found.</p>
                    <button type="button" onclick="openCreateModalFromSearch('income')" class="mt-3 inline-flex items-center gap-1 rounded-xl bg-slate-950 px-3.5 py-2 text-xs font-black text-white hover:bg-slate-800">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        <span id="create-income-search-btn-label">Create New Income Category</span>
                    </button>
                </div>
            </div>

            <div class="mt-4 border-t border-slate-100 pt-3 flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500">Cannot find what you need?</span>
                <button type="button" onclick="openCreateModalFromSearch('income')" class="inline-flex items-center gap-1 text-xs font-black text-emerald-700 hover:text-emerald-900">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Create New Income
                </button>
            </div>
        </div>
    </div>

    <!-- Expense Search Modal -->
    <div id="search-expense-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
        <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-950 inline-flex items-center gap-1.5">
                        <i data-lucide="search" class="h-4 w-4 text-rose-700"></i> Search &amp; Add Expense Item
                    </h3>
                    <p class="text-xs font-semibold text-slate-500">Activate previously configured disabled settings or add new expense items for {{ $currentShop->name }}.</p>
                </div>
                <button type="button" onclick="closeSearchModal('expense')" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="mt-4">
                <input type="text" id="search-expense-input" onkeyup="filterSearchList('expense')" placeholder="Search expense items..."
                       class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
            </div>

            <div id="search-expense-list" class="mt-4 space-y-2 max-h-72 overflow-y-auto">
                <!-- Disabled Expense Settings (Re-enable) -->
                @foreach($expenseRows->where('enabled', false) as $disabledSetting)
                    <div class="search-item flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50/50 p-3 hover:bg-amber-100/60 transition"
                         data-name="{{ strtolower($disabledSetting->displayName().' '.$disabledSetting->entryType->name.' '.$disabledSetting->entryType->code) }}">
                        <div>
                            <div class="font-extrabold text-xs text-slate-950">{{ $disabledSetting->displayName() }}</div>
                            <div class="text-[10px] font-bold text-amber-800">Previously configured • Disabled</div>
                        </div>
                        <button type="button" onclick="reenableSetting({{ $disabledSetting->id }}, '{{ addslashes($disabledSetting->displayName()) }}')"
                                class="rounded-xl bg-emerald-700 px-3.5 py-1.5 text-xs font-black text-white hover:bg-emerald-800 shadow-xs inline-flex items-center gap-1">
                            <i data-lucide="circle-check" class="h-3.5 w-3.5"></i> Re-enable
                        </button>
                    </div>
                @endforeach

                <!-- Unconfigured Expense Types (+ Add to Shop) -->
                @foreach($unconfiguredExpenseTypes as $type)
                    <div class="search-item flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 p-3 hover:bg-slate-100 transition"
                         data-name="{{ strtolower($type->name.' '.$type->code) }}">
                        <div>
                            <div class="font-extrabold text-xs text-slate-950">{{ $type->name }}</div>
                            <div class="text-[10px] font-bold text-slate-400">Not configured</div>
                        </div>
                        <button type="button" onclick="addEntryTypeToShop({{ $type->id }}, '{{ addslashes($type->name) }}', 'expense')"
                                class="rounded-xl bg-slate-950 px-3.5 py-1.5 text-xs font-black text-white hover:bg-slate-800 inline-flex items-center gap-1">
                            <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add to Shop
                        </button>
                    </div>
                @endforeach

                <div id="no-search-expense-results" class="hidden rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs font-bold text-slate-500">
                    <p>No matching expense category found.</p>
                    <button type="button" onclick="openCreateModalFromSearch('expense')" class="mt-3 inline-flex items-center gap-1 rounded-xl bg-slate-950 px-3.5 py-2 text-xs font-black text-white hover:bg-slate-800">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        <span id="create-expense-search-btn-label">Create New Expense Category</span>
                    </button>
                </div>
            </div>

            <div class="mt-4 border-t border-slate-100 pt-3 flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500">Cannot find what you need?</span>
                <button type="button" onclick="openCreateModalFromSearch('expense')" class="inline-flex items-center gap-1 text-xs font-black text-rose-700 hover:text-rose-900">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Create New Expense
                </button>
            </div>
        </div>
    </div>

    <!-- Transfer Search Modal -->
    <div id="search-transfer-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
        <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-950 inline-flex items-center gap-1.5">
                        <i data-lucide="search" class="h-4 w-4 text-indigo-700"></i> Search &amp; Add Transfer / Settlement
                    </h3>
                    <p class="text-xs font-semibold text-slate-500">Activate previously configured disabled settings or add transfer/settlement items for {{ $currentShop->name }}.</p>
                </div>
                <button type="button" onclick="closeSearchModal('transfer')" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="mt-4">
                <input type="text" id="search-transfer-input" onkeyup="filterSearchList('transfer')" placeholder="Search transfer/settlement items..."
                       class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
            </div>

            <div id="search-transfer-list" class="mt-4 space-y-2 max-h-72 overflow-y-auto">
                <!-- Disabled Transfer Settings (Re-enable) -->
                @foreach($transferRows->where('enabled', false) as $disabledSetting)
                    <div class="search-item flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50/50 p-3 hover:bg-amber-100/60 transition"
                         data-name="{{ strtolower($disabledSetting->displayName().' '.$disabledSetting->entryType->name.' '.$disabledSetting->entryType->code) }}">
                        <div>
                            <div class="font-extrabold text-xs text-slate-950">{{ $disabledSetting->displayName() }}</div>
                            <div class="text-[10px] font-bold text-amber-800">Previously configured • Disabled</div>
                        </div>
                        <button type="button" onclick="reenableSetting({{ $disabledSetting->id }}, '{{ addslashes($disabledSetting->displayName()) }}')"
                                class="rounded-xl bg-emerald-700 px-3.5 py-1.5 text-xs font-black text-white hover:bg-emerald-800 shadow-xs inline-flex items-center gap-1">
                            <i data-lucide="circle-check" class="h-3.5 w-3.5"></i> Re-enable
                        </button>
                    </div>
                @endforeach

                <!-- Unconfigured Transfer Types (+ Add to Shop) -->
                @foreach($unconfiguredTransferTypes as $type)
                    <div class="search-item flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 p-3 hover:bg-slate-100 transition"
                         data-name="{{ strtolower($type->name.' '.$type->code) }}">
                        <div>
                            <div class="font-extrabold text-xs text-slate-950">{{ $type->name }}</div>
                            <div class="text-[10px] font-bold text-slate-400">Not configured</div>
                        </div>
                        <button type="button" onclick="addEntryTypeToShop({{ $type->id }}, '{{ addslashes($type->name) }}', 'transfer')"
                                class="rounded-xl bg-slate-950 px-3.5 py-1.5 text-xs font-black text-white hover:bg-slate-800 inline-flex items-center gap-1">
                            <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add to Shop
                        </button>
                    </div>
                @endforeach

                <div id="no-search-transfer-results" class="hidden rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs font-bold text-slate-500">
                    <p>No matching transfer or settlement entry found.</p>
                </div>
            </div>
        </div>
    </div>


    <!-- CREATE NEW INCOME MODAL -->
    <div id="create-income-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
        <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-950 inline-flex items-center gap-1.5">
                        <i data-lucide="plus-circle" class="h-4 w-4 text-emerald-600"></i> Create New Income Category
                    </h3>
                    <p class="text-xs font-semibold text-slate-500">Add a brand-new income category to {{ $currentShop->name }}.</p>
                </div>
                <button type="button" onclick="closeCreateModal('income')" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form onsubmit="submitCreateNewCategory(event, 'income')" class="mt-5 space-y-4">
                <div>
                    <label class="block text-xs font-extrabold text-slate-900 mb-1">Category Name</label>
                    <input type="text" id="create-income-name" name="name" required placeholder="e.g. Other Delivery Income"
                           oninput="autoSlugCode('income')"
                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-black uppercase text-slate-500 mb-1">Category Code (Auto-generated)</label>
                    <input type="text" id="create-income-code" name="code" placeholder="other_delivery_income"
                           class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 font-mono text-xs font-bold text-slate-700 focus:border-slate-400 focus:outline-none">
                </div>

                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <span class="text-xs font-bold text-slate-700">Category Classification</span>
                    <span class="rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-900 uppercase">Income</span>
                </div>

                <div>
                    <label class="block text-xs font-extrabold text-slate-900 mb-1">Money Destination / Funding Source</label>
                    <select name="default_funding_source" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                        <option value="sales">Shop Cash / Deduct From Company Payable</option>
                        <option value="bank">Company Bank Account</option>
                        <option value="none">No Funding Movement</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-extrabold text-slate-900 mb-1">Destination Company Account (Optional)</label>
                    <select name="company_account_id" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                        <option value="">None (Shop Cash / Unmapped)</option>
                        @foreach($companyAccounts as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->name }} ({{ $account->bank_name ?: $account->account_type }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="closeCreateModal('income')" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-slate-950 px-5 py-2 text-xs font-black text-white hover:bg-slate-800 inline-flex items-center gap-1">
                        <i data-lucide="plus" class="h-4 w-4"></i> Create Income
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- CREATE NEW EXPENSE MODAL -->
    <div id="create-expense-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
        <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-950 inline-flex items-center gap-1.5">
                        <i data-lucide="plus-circle" class="h-4 w-4 text-rose-600"></i> Create New Expense Category
                    </h3>
                    <p class="text-xs font-semibold text-slate-500">Add a brand-new expense item to {{ $currentShop->name }}.</p>
                </div>
                <button type="button" onclick="closeCreateModal('expense')" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form onsubmit="submitCreateNewCategory(event, 'expense')" class="mt-5 space-y-4">
                <div>
                    <label class="block text-xs font-extrabold text-slate-900 mb-1">Category Name</label>
                    <input type="text" id="create-expense-name" name="name" required placeholder="e.g. Daily Mess"
                           oninput="autoSlugCode('expense')"
                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-black uppercase text-slate-500 mb-1">Category Code (Auto-generated)</label>
                    <input type="text" id="create-expense-code" name="code" placeholder="daily_mess"
                           class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 font-mono text-xs font-bold text-slate-700 focus:border-slate-400 focus:outline-none">
                </div>

                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <span class="text-xs font-bold text-slate-700">Category Classification</span>
                    <span class="rounded-lg bg-rose-100 px-2.5 py-1 text-xs font-black text-rose-900 uppercase">Expense</span>
                </div>

                <div>
                    <label class="block text-xs font-extrabold text-slate-900 mb-1">Paid From (Funding Source)</label>
                    <select name="default_funding_source" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-slate-400 focus:outline-none">
                        <option value="sales">Shop Cash / Deduct From Company Payable</option>
                        <option value="petty">Petty Cash</option>
                        <option value="company">Paid Directly by Company</option>
                        <option value="company_later">Company Reimbursement Pending</option>
                        <option value="bank">Company Bank Account</option>
                        <option value="none">No Funding Movement</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="closeCreateModal('expense')" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-slate-950 px-5 py-2 text-xs font-black text-white hover:bg-slate-800 inline-flex items-center gap-1">
                        <i data-lucide="plus" class="h-4 w-4"></i> Create Expense
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bank Settlement Adjustment Rules Modal -->
    <div id="bank-adj-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-xs flex items-center justify-center">
        <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-950 flex items-center gap-2">
                        <i data-lucide="sliders-horizontal" class="h-4 w-4 text-indigo-600"></i>
                        <span>Bank Settlement Adjustments</span>
                    </h3>
                    <p class="text-xs font-semibold text-slate-500" id="bank-adj-modal-subtitle">Configure optional rules for expected bank calculations.</p>
                </div>
                <button type="button" onclick="closeBankAdjModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="mt-4">
                <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Configured Rules</h4>
                <div id="bank-adj-rules-list" class="space-y-2 max-h-60 overflow-y-auto"></div>
            </div>

            <div class="mt-5 border-t border-slate-100 pt-4">
                <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Add New Rule</h4>
                <form id="add-bank-adj-rule-form" onsubmit="submitAddBankAdjRule(event)" class="space-y-3">
                    <input type="hidden" id="bank-adj-shop-id" name="shop_id">
                    <input type="hidden" id="bank-adj-entry-type-id" name="entry_type_id">

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-black uppercase text-slate-500">Label (e.g. Rent, Other Addition)</label>
                            <input type="text" id="bank-adj-label" name="label" required placeholder="e.g. Rent"
                                   class="mt-1 h-9 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-500">Direction</label>
                            <select id="bank-adj-direction" name="direction" required
                                    class="mt-1 h-9 w-full rounded-xl border border-slate-200 px-2 text-xs font-bold text-slate-900 focus:border-slate-400 focus:outline-none">
                                <option value="minus">MINUS (-)</option>
                                <option value="plus">PLUS (+)</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" id="btn-save-adj-rule"
                                class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800 disabled:bg-slate-300 inline-flex items-center gap-1">
                            <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add Rule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
let currentTargetHeaderId = null;
let draggedCardElement = null;
let draggedHeaderElement = null;

function toggleShowDisabled(showDisabled) {
    const cards = document.querySelectorAll('.setting-card');
    cards.forEach((card) => {
        const enabled = card.getAttribute('data-enabled') === '1';
        if (enabled) {
            card.classList.remove('hidden');
        } else {
            if (showDisabled) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        }
    });

    const incomeBadge = document.getElementById('income-count-badge');
    if (incomeBadge) {
        const active = incomeBadge.getAttribute('data-active');
        const disabled = incomeBadge.getAttribute('data-disabled');
        incomeBadge.textContent = showDisabled ? `${active} Active · ${disabled} Disabled` : `${active} Active`;
    }

    const expenseBadge = document.getElementById('expense-count-badge');
    if (expenseBadge) {
        const active = expenseBadge.getAttribute('data-active');
        const disabled = expenseBadge.getAttribute('data-disabled');
        expenseBadge.textContent = showDisabled ? `${active} Active · ${disabled} Disabled` : `${active} Active`;
    }
}

function openConfigModal(settingId) {
    const modal = document.getElementById('config-modal-' + settingId);
    if (modal) {
        modal.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }
}

function closeConfigModal(settingId) {
    const modal = document.getElementById('config-modal-' + settingId);
    if (modal) {
        modal.classList.add('hidden');
    }
}

function resetCategoryDisplayName(settingId) {
    const input = document.getElementById('setting-display-name-' + settingId);
    if (input) {
        input.value = '';
        input.focus();
        if (window.showToast) {
            showToast('Category name reset to default. Click Save Configuration to apply.', 'info');
        }
    }
}

function openSearchModal(category, headerId = null) {
    currentTargetHeaderId = headerId;
    const modal = document.getElementById('search-' + category + '-modal');
    if (modal) {
        modal.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }
}

function closeSearchModal(category) {
    const modal = document.getElementById('search-' + category + '-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function openCreateModal(category, headerId = null) {
    if (headerId) currentTargetHeaderId = headerId;
    closeSearchModal(category);
    const modal = document.getElementById('create-' + category + '-modal');
    if (modal) {
        modal.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }
}

function closeCreateModal(category) {
    const modal = document.getElementById('create-' + category + '-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function openCreateHeaderModal(type) {
    document.getElementById('create-header-type').value = type;
    document.getElementById('create-header-name').value = '';
    document.getElementById('create-header-modal-title').innerHTML = `
        <i data-lucide="plus-circle" class="h-4 w-4 ${type === 'income' ? 'text-emerald-600' : 'text-rose-600'}"></i>
        <span>Create ${type === 'income' ? 'Income' : 'Expense'} Header</span>
    `;
    const badge = document.getElementById('create-header-type-badge');
    if (badge) {
        badge.textContent = type.toUpperCase();
        badge.className = type === 'income' ? 'rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-900 uppercase' : 'rounded-lg bg-rose-100 px-2.5 py-1 text-xs font-black text-rose-900 uppercase';
    }
    document.getElementById('create-header-modal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}

function closeCreateHeaderModal() {
    document.getElementById('create-header-modal').classList.add('hidden');
}

async function submitCreateHeaderGroup(event) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');

    const name = form.name.value.trim();
    const type = form.type.value;
    const productTaggingEnabled = form.product_tagging_enabled ? (form.product_tagging_enabled.value === '1') : false;

    if (!name) return;

    button.disabled = true;
    button.textContent = 'Creating...';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.headers.create') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                shop_id: '{{ $currentShop->shop_id }}',
                name,
                type,
                product_tagging_enabled: productTaggingEnabled,
            }),
        });

        const data = await response.json();
        if (data.success) {
            if (window.showToast) showToast(data.message || `Created header ${name}`, 'success');
            window.location.reload();
            return;
        }
        if (window.showToast) showToast(data.message || 'Creation failed', 'error');
    } catch (err) {
        if (window.showToast) showToast('Creation failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Create Header';
    }
}

function onIncomeRuleModeChange(mode) {
    const destContainer = document.getElementById('income-same-destination-container');
    const accBox = document.getElementById('header-company-account-box');
    if (destContainer) destContainer.classList.toggle('hidden', mode === 'entry_decides');

    if (mode === 'entry_decides') {
        if (accBox) accBox.classList.add('hidden');
    } else {
        const destVal = document.getElementById('edit-header-income-single-dest')?.value || 'shop_cash';
        if (accBox) accBox.classList.toggle('hidden', destVal !== 'company_account');
    }
}

function onIncomeSingleDestChange(dest) {
    const accBox = document.getElementById('header-company-account-box');
    if (accBox) accBox.classList.toggle('hidden', dest !== 'company_account');
}

function onExpenseRuleModeChange(mode) {
    const srcContainer = document.getElementById('expense-same-source-container');
    const accBox = document.getElementById('header-company-account-box');
    if (srcContainer) srcContainer.classList.toggle('hidden', mode === 'entry_decides');

    if (mode === 'entry_decides') {
        if (accBox) accBox.classList.add('hidden');
    } else {
        const srcVal = document.getElementById('edit-header-expense-single-source')?.value || 'shop_cash';
        if (accBox) accBox.classList.toggle('hidden', srcVal !== 'company_account');
    }
}

function onExpenseSingleSourceChange(src) {
    const accBox = document.getElementById('header-company-account-box');
    if (accBox) accBox.classList.toggle('hidden', src !== 'company_account');
}

function onHeaderTypeOrModeChange(type) {
    const incBox = document.getElementById('header-income-cash-flow-box');
    const expBox = document.getElementById('header-expense-cash-flow-box');
    const othBox = document.getElementById('header-others-cash-flow-box');

    if (incBox) incBox.classList.toggle('hidden', type !== 'income');
    if (expBox) expBox.classList.toggle('hidden', type !== 'expense');
    if (othBox) othBox.classList.toggle('hidden', type !== 'other');

    if (type === 'income') {
        const ruleMode = document.getElementById('edit-header-income-rule-mode')?.value || 'same_for_all';
        onIncomeRuleModeChange(ruleMode);
    } else if (type === 'expense') {
        const ruleMode = document.getElementById('edit-header-expense-rule-mode')?.value || 'same_for_all';
        onExpenseRuleModeChange(ruleMode);
    } else {
        const accBox = document.getElementById('header-company-account-box');
        if (accBox) accBox.classList.add('hidden');
    }
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

function openEditHeaderModal(headerData, currentName, taggingEnabled) {

    let data = {};
    if (typeof headerData === 'object' && headerData !== null) {
        data = headerData;
    } else {
        data = {
            id: headerData,
            name: currentName || '',
            product_tagging_enabled: taggingEnabled ? 1 : 0
        };
    }

    document.getElementById('edit-header-id').value = data.id || '';
    document.getElementById('edit-header-name').value = data.name || '';

    const type = data.type || 'expense';
    document.getElementById('edit-header-type').value = type;
    document.getElementById('edit-header-product-tagging').value = data.product_tagging_enabled ? '1' : '0';

    const showBothSidesEl = document.getElementById('edit-header-show-both-sides');
    if (showBothSidesEl) showBothSidesEl.value = data.show_both_sides ? '1' : '0';

    const noteEl = document.getElementById('edit-header-note-enabled');
    if (noteEl) noteEl.value = data.note_enabled ? '1' : '0';


    const mode = data.cash_flow_mode || 'shop_cash';
    if (type === 'income') {
        const incRuleMode = document.getElementById('edit-header-income-rule-mode');
        const incSingleDest = document.getElementById('edit-header-income-single-dest');
        if (mode === 'entry_decides') {
            if (incRuleMode) incRuleMode.value = 'entry_decides';
        } else {
            if (incRuleMode) incRuleMode.value = 'same_for_all';
            if (incSingleDest) incSingleDest.value = mode;
        }
    } else if (type === 'expense') {
        const expRuleMode = document.getElementById('edit-header-expense-rule-mode');
        const expSingleSource = document.getElementById('edit-header-expense-single-source');
        if (mode === 'entry_decides') {
            if (expRuleMode) expRuleMode.value = 'entry_decides';
        } else {
            if (expRuleMode) expRuleMode.value = 'same_for_all';
            if (expSingleSource) expSingleSource.value = mode;
        }
    }

    const accId = document.getElementById('edit-header-company-account-id');
    if (accId) accId.value = data.company_account_id || '';

    const fromBal = document.getElementById('edit-header-from-balance');
    if (fromBal) fromBal.value = data.from_balance || 'shop_cash';

    const toBal = document.getElementById('edit-header-to-balance');
    if (toBal) toBal.value = data.to_balance || 'petty';

    onHeaderTypeOrModeChange(type);

    document.getElementById('edit-header-modal-title').innerHTML = `
        <i data-lucide="sliders-horizontal" class="h-4 w-4 text-indigo-600"></i>
        <span>Configure Header — ${escapeHtml(data.name || '')}</span>
    `;
    document.getElementById('edit-header-modal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}

function closeEditHeaderModal() {
    document.getElementById('edit-header-modal').classList.add('hidden');
}

async function submitEditHeaderGroup(event) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');

    const id = parseInt(form.id.value, 10);
    const name = form.name.value.trim();
    const type = form.type.value;

    let cashFlowMode = 'shop_cash';
    let companyAccountId = null;

    if (type === 'income') {
        const ruleMode = form.querySelector('#edit-header-income-rule-mode')?.value || 'same_for_all';
        if (ruleMode === 'entry_decides') {
            cashFlowMode = 'entry_decides';
        } else {
            cashFlowMode = form.querySelector('#edit-header-income-single-dest')?.value || 'shop_cash';
            if (cashFlowMode === 'company_account') {
                companyAccountId = form.company_account_id?.value ? parseInt(form.company_account_id.value, 10) : null;
            }
        }
    } else if (type === 'expense') {
        const ruleMode = form.querySelector('#edit-header-expense-rule-mode')?.value || 'same_for_all';
        if (ruleMode === 'entry_decides') {
            cashFlowMode = 'entry_decides';
        } else {
            cashFlowMode = form.querySelector('#edit-header-expense-single-source')?.value || 'shop_cash';
            if (cashFlowMode === 'company_account') {
                companyAccountId = form.company_account_id?.value ? parseInt(form.company_account_id.value, 10) : null;
            }
        }
    }

    const fromBalance = form.from_balance?.value || null;
    const toBalance = form.to_balance?.value || null;
    const noteEnabled = form.note_enabled?.value === '1';
    const productTaggingEnabled = form.product_tagging_enabled?.value === '1';
    const showBothSides = form.show_both_sides?.value === '1';

    if (!name) return;

    button.disabled = true;
    button.textContent = 'Saving...';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.headers.update') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                id: id,
                name: name,
                cash_flow_mode: cashFlowMode,
                company_account_id: companyAccountId,
                from_balance: fromBalance,
                to_balance: toBalance,
                note_enabled: noteEnabled,
                product_tagging_enabled: productTaggingEnabled,
                show_both_sides: showBothSides,
            }),
        });

        const data = await response.json();
        if (data.success) {
            if (window.showToast) showToast(data.message || 'Header updated', 'success');
            window.location.reload();
        } else {
            if (window.showToast) showToast(data.message || 'Update failed', 'error');
        }

    } catch (err) {
        if (window.showToast) showToast('Update failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Save Header';
    }
}

async function renameHeaderGroup(headerId, currentName) {
    openEditHeaderModal(headerId, currentName, 0);
}

async function deleteHeaderGroup(headerId, headerName) {
    if (!confirm(`Are you sure you want to remove the header "${headerName}"? Any assigned categories will safely return to Unassigned.`)) return;

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.headers.delete') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ id: headerId }),
        });
        const data = await response.json();
        if (data.success) {
            if (window.showToast) showToast(data.message, 'success');
            window.location.reload();
        } else {
            if (window.showToast) showToast(data.message || 'Delete failed', 'error');
        }
    } catch (err) {
        if (window.showToast) showToast('Delete failed', 'error');
    }
}

// ─── DRAG & DROP IMPLEMENTATION ─────────────────────────────────────────────

function handleHeaderDragStart(e) {
    if (!e.target.classList.contains('header-group-box')) return;
    draggedHeaderElement = e.target;
    e.target.classList.add('opacity-50', 'scale-[0.99]');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', 'header:' + e.target.getAttribute('data-header-id'));
}

function handleHeaderDragOver(e) {
    if (!draggedHeaderElement) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function handleHeaderDrop(e) {
    if (!draggedHeaderElement) return;
    e.preventDefault();
    const targetHeader = e.currentTarget;
    if (targetHeader && targetHeader !== draggedHeaderElement && targetHeader.getAttribute('data-header-type') === draggedHeaderElement.getAttribute('data-header-type')) {
        const container = targetHeader.parentNode;
        const children = Array.from(container.children);
        const draggedIndex = children.indexOf(draggedHeaderElement);
        const targetIndex = children.indexOf(targetHeader);

        if (draggedIndex < targetIndex) {
            container.insertBefore(draggedHeaderElement, targetHeader.nextSibling);
        } else {
            container.insertBefore(draggedHeaderElement, targetHeader);
        }

        saveHeaderOrders(targetHeader.getAttribute('data-header-type'));
    }
}

function handleHeaderDragEnd(e) {
    if (draggedHeaderElement) {
        draggedHeaderElement.classList.remove('opacity-50', 'scale-[0.99]');
        draggedHeaderElement = null;
    }
}

async function saveHeaderOrders(type) {
    const container = document.getElementById(type + '-headers-container');
    if (!container) return;
    const headerBoxes = container.querySelectorAll('.header-group-box[data-header-id]:not([data-header-id="unassigned"])');
    const headerIds = Array.from(headerBoxes).map(box => parseInt(box.getAttribute('data-header-id'), 10));

    if (headerIds.length === 0) return;

    try {
        await fetch('{{ route('admin.cashbook.api.shop-settings.headers.reorder') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                shop_id: '{{ $currentShop->shop_id }}',
                header_ids: headerIds,
            }),
        });
    } catch (err) {
        console.error('Failed to save header order', err);
    }
}

function handleCardDragStart(e) {
    e.stopPropagation();
    const card = e.target.closest('.setting-card');
    if (!card) return;
    draggedCardElement = card;
    card.classList.add('opacity-40');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', 'card:' + card.getAttribute('data-setting-id'));
}

function handleCardDragOver(e) {
    if (!draggedCardElement) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

async function handleCardDrop(e) {
    if (!draggedCardElement) return;
    e.preventDefault();
    e.stopPropagation();

    const dropzone = e.currentTarget.closest('.cards-dropzone');
    if (!dropzone) return;

    const targetHeaderIdStr = dropzone.getAttribute('data-header-id');
    const targetHeaderId = targetHeaderIdStr === 'unassigned' ? null : parseInt(targetHeaderIdStr, 10);
    const settingId = parseInt(draggedCardElement.getAttribute('data-setting-id'), 10);

    // Remove any placeholder in target dropzone
    const placeholder = dropzone.querySelector('.no-cards-placeholder');
    if (placeholder) placeholder.remove();

    dropzone.appendChild(draggedCardElement);
    draggedCardElement.classList.remove('opacity-40');

    // Update card dataset
    draggedCardElement.setAttribute('data-header-id', targetHeaderIdStr);

    const settingIds = Array.from(dropzone.querySelectorAll('.setting-card')).map(c => parseInt(c.getAttribute('data-setting-id'), 10));

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.cards.reorder') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                shop_id: '{{ $currentShop->shop_id }}',
                header_group_id: targetHeaderId,
                setting_ids: settingIds,
            }),
        });
        const data = await response.json();
        if (data.success && window.showToast) {
            showToast('Card moved & order saved', 'success');
        }
    } catch (err) {
        console.error('Failed to save card move', err);
    } finally {
        draggedCardElement = null;
    }
}

function openCreateModalFromSearch(category) {
    const input = document.getElementById('search-' + category + '-input');
    const query = input ? input.value.trim() : '';
    openCreateModal(category, currentTargetHeaderId);
    if (query) {
        const nameInput = document.getElementById('create-' + category + '-name');
        if (nameInput) {
            nameInput.value = query;
            autoSlugCode(category);
        }
    }
}

function autoSlugCode(category) {
    const nameInput = document.getElementById('create-' + category + '-name');
    const codeInput = document.getElementById('create-' + category + '-code');
    if (nameInput && codeInput) {
        const slug = nameInput.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        codeInput.value = slug;
    }
}

function filterSearchList(category) {
    const input = document.getElementById('search-' + category + '-input');
    const query = input ? input.value.toLowerCase().trim() : '';
    const items = document.querySelectorAll('#search-' + category + '-list .search-item');
    let visibleCount = 0;

    items.forEach((item) => {
        const name = item.getAttribute('data-name') || '';
        if (name.includes(query)) {
            item.classList.remove('hidden');
            visibleCount++;
        } else {
            item.classList.add('hidden');
        }
    });

    const noResults = document.getElementById('no-search-' + category + '-results');
    const labelSpan = document.getElementById('create-' + category + '-search-btn-label');

    if (visibleCount === 0 && query !== '') {
        if (noResults) noResults.classList.remove('hidden');
        if (labelSpan) labelSpan.textContent = `Create "${query}" Category`;
    } else {
        if (noResults) noResults.classList.add('hidden');
    }
}

async function submitCreateNewCategory(event, category) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');

    const name = form.name.value.trim();
    const code = form.code.value.trim();
    const defaultFundingSource = form.default_funding_source?.value;
    const companyAccountId = form.company_account_id?.value;

    if (!name) {
        if (window.showToast) showToast('Category name is required', 'error');
        return;
    }

    button.disabled = true;
    button.textContent = 'Creating...';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.custom-row') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                shop_id: '{{ $currentShop->shop_id }}',
                name,
                code,
                category,
                default_funding_source: defaultFundingSource,
                company_account_id: companyAccountId ? parseInt(companyAccountId, 10) : null,
                header_group_id: currentTargetHeaderId,
            }),
        });

        const data = await response.json();
        if (data.success) {
            if (window.showToast) showToast(data.message || `Created ${name}`, 'success');
            window.location.reload();
            return;
        }
        if (window.showToast) showToast(data.message || 'Creation failed', 'error');
    } catch (err) {
        if (window.showToast) showToast('Creation failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = `Create ${category === 'income' ? 'Income' : 'Expense'}`;
    }
}

async function addEntryTypeToShop(entryTypeId, entryTypeName, category) {
    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.custom-row') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                shop_id: '{{ $currentShop->shop_id }}',
                name: entryTypeName,
                category: category,
                header_group_id: currentTargetHeaderId,
            }),
        });

        const data = await response.json();
        if (data.success) {
            if (window.showToast) showToast(data.message || 'Row added', 'success');
            window.location.reload();
            return;
        }
        if (window.showToast) showToast(data.message || 'Failed to add entry', 'error');
    } catch (err) {
        if (window.showToast) showToast('Failed to add entry', 'error');
    }
}

async function reenableSetting(settingId, name) {
    const form = document.getElementById('setting-' + settingId);
    if (!form) return;
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.setting_id = settingId;
    payload.enabled = '1';
    if (currentTargetHeaderId) {
        payload.header_group_id = currentTargetHeaderId;
    }

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.update') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (data.success) {
            if (window.showToast) showToast(data.message || `Re-enabled ${name}`, 'success');
            window.location.reload();
            return;
        }
        if (window.showToast) showToast(data.message || 'Re-enable failed', 'error');
    } catch (err) {
        if (window.showToast) showToast('Re-enable failed', 'error');
    }
}

function setPayableChoice(settingId, choice) {
    const incField = document.getElementById('inc-pay-' + settingId);
    const dirField = document.getElementById('pay-dir-' + settingId);
    if (!incField || !dirField) return;
    if (choice === 'none') {
        incField.value = '0';
    } else {
        incField.value = '1';
        dirField.value = choice;
    }
}

async function saveShopSetting(event, settingId) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.setting_id = settingId;

    if (button) {
        button.disabled = true;
        button.textContent = 'Saving...';
    }

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.update') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (window.showToast) {
            showToast(data.message || (data.success ? 'Saved' : 'Save failed'), data.success ? 'success' : 'error');
        }
        if (data.success) {
            closeConfigModal(settingId);
            window.location.reload();
        }
    } catch (error) {
        if (window.showToast) {
            showToast('Save failed', 'error');
        }
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = 'Save Configuration';
        }
    }
}

async function createCollectionCustomRow(event, category) {
    const button = event.target;
    const input = document.querySelector(`input[data-collection-custom-row="${category}"]`);
    const name = input.value.trim();

    if (!name) {
        if (window.showToast) showToast('Enter a row name', 'error');
        return;
    }

    button.disabled = true;
    button.textContent = 'Adding...';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.custom-row') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                shop_id: '{{ $currentShop->shop_id }}',
                name,
                category,
            }),
        });
        const data = await response.json();
        if (data.success) {
            if (window.showToast) showToast(data.message || 'Row added', 'success');
            window.location.reload();
            return;
        }
        if (window.showToast) showToast(data.message || 'Add row failed', 'error');
    } catch (error) {
        if (window.showToast) showToast('Add row failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Add';
    }
}

async function saveCollectionSettings(event) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    const payload = {
        shop_id: form.shop_id.value,
        enabled: form.querySelector('input[name="enabled"][value="1"]').checked ? 1 : 0,
        income_entry_type_ids: Array.from(form.querySelectorAll('input[name="income_entry_type_ids[]"]:checked')).map((input) => input.value),
        expense_entry_type_ids: Array.from(form.querySelectorAll('input[name="expense_entry_type_ids[]"]:checked')).map((input) => input.value),
    };

    button.disabled = true;
    button.textContent = 'Saving...';

    try {
        const response = await fetch('/admin/cashbook/api/shop-settings/collection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (window.showToast) {
            showToast(data.message || (data.success ? 'Saved' : 'Save failed'), data.success ? 'success' : 'error');
        }
    } catch (error) {
        if (window.showToast) showToast('Save failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Save Collection';
    }
}

// ─── Historical Bank Collection Fetch JS ─────────────────────────────────────

let currentHistoricalPreview = null;

function applyPeriodPreset(preset) {
    const today = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    const fromInput = document.getElementById('hist-from-date');
    const toInput = document.getElementById('hist-to-date');

    if (!fromInput || !toInput) return;

    if (preset === 'today') {
        fromInput.value = formatDate(today);
        toInput.value = formatDate(today);
    } else if (preset === 'yesterday') {
        const y = new Date();
        y.setDate(y.getDate() - 1);
        fromInput.value = formatDate(y);
        toInput.value = formatDate(y);
    } else if (preset === 'this_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        fromInput.value = formatDate(firstDay);
        toInput.value = formatDate(today);
    } else if (preset === 'last_month') {
        const firstDayLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const lastDayLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
        fromInput.value = formatDate(firstDayLastMonth);
        toInput.value = formatDate(lastDayLastMonth);
    }
}

function onHistoricalCategoryChange() {
    const select = document.getElementById('hist-entry-type-id');
    if (!select) return;
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption) return;
    const bankId = selectedOption.getAttribute('data-bank-id');
    if (bankId) {
        const bankSelect = document.getElementById('hist-company-account-id');
        if (bankSelect) bankSelect.value = bankId;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    applyPeriodPreset('this_month');
    onHistoricalCategoryChange();
});

async function previewHistoricalFetch(event) {
    event.preventDefault();
    const form = event.target;
    const button = document.getElementById('btn-preview-hist');

    const shopId = form.shop_id?.value;
    const entryTypeId = form.entry_type_id?.value;
    const companyAccountId = form.company_account_id?.value;
    const fromDate = form.from_date?.value;
    const toDate = form.to_date?.value;

    if (!entryTypeId) {
        if (window.showToast) showToast('Please select a Category / Row first.', 'error');
        return;
    }
    if (!companyAccountId) {
        if (window.showToast) showToast('Please select a Destination Bank.', 'error');
        return;
    }
    if (!fromDate || !toDate) {
        if (window.showToast) showToast('Please select both From and To dates.', 'error');
        return;
    }

    const payload = {
        shop_id: parseInt(shopId, 10),
        entry_type_id: parseInt(entryTypeId, 10),
        company_account_id: parseInt(companyAccountId, 10),
        from_date: fromDate,
        to_date: toDate,
    };

    button.disabled = true;
    button.textContent = 'Calculating Preview...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.historical-bank-collections.preview') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            data = { message: 'Server returned an invalid response.' };
        }

        if (!response.ok || !data.success) {
            const errorMsg = data.message || 'Preview calculation failed';
            if (window.showToast) showToast(errorMsg, 'error');
            return;
        }

        currentHistoricalPreview = data.preview;
        renderHistoricalPreview(data.preview);
    } catch (error) {
        if (window.showToast) showToast(error.message || 'Preview request failed', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Preview Historical Entries';
    }
}

function renderHistoricalPreview(p) {
    const container = document.getElementById('hist-preview-container');
    if (!container) return;
    container.classList.remove('hidden');

    const formatCurrency = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const shopName = p.shop?.name || 'Selected Shop';
    const entryTypeName = p.entry_type?.name || 'Selected Category';
    const bankName = p.company_account?.name || 'Selected Bank';
    const fromDate = p.from_date || '';
    const toDate = p.to_date || '';

    const sourceCount = Number(p.source_count || 0);
    const sourceAmount = Number(p.source_amount || 0);
    const eligibleCount = Number(p.eligible_count || 0);
    const eligibleAmount = Number(p.eligible_amount || 0);
    const alreadyLinkedCount = Number(p.already_linked_count || 0);
    const alreadyLinkedAmount = Number(p.already_linked_amount || 0);
    const differentBankCount = Number(p.different_bank_count || 0);
    const differentBankAmount = Number(p.different_bank_amount || 0);
    const reconciledCount = Number(p.reconciled_count || 0);
    const reconciledAmount = Number(p.reconciled_amount || 0);
    const voidCount = Number(p.void_count || 0);
    const voidAmount = Number(p.void_amount || 0);

    container.innerHTML = `
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Historical Fetch Preview</h3>
                    <p class="text-xs font-semibold text-slate-500">
                        ${shopName} • ${entryTypeName} &rrarr; <span class="font-bold text-slate-900">${bankName}</span>
                        (${fromDate} &rrarr; ${toDate})
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-xs font-bold text-slate-500">Total Found:</span>
                    <span class="text-sm font-black text-slate-950">${sourceCount} txs (${formatCurrency(sourceAmount)})</span>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Eligible to Fetch</p>
                    <p class="mt-1 text-lg font-black text-emerald-950">${eligibleCount} <span class="text-xs font-bold text-emerald-800">txs</span></p>
                    <p class="text-xs font-bold text-emerald-700">${formatCurrency(eligibleAmount)}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Already Linked</p>
                    <p class="mt-1 text-lg font-black text-slate-900">${alreadyLinkedCount} <span class="text-xs font-bold text-slate-400">txs</span></p>
                    <p class="text-xs font-bold text-slate-500">${formatCurrency(alreadyLinkedAmount)}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-800">Different Bank</p>
                    <p class="mt-1 text-lg font-black text-amber-950">${differentBankCount} <span class="text-xs font-bold text-amber-800">txs</span></p>
                    <p class="text-xs font-bold text-amber-700">${formatCurrency(differentBankAmount)}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Reconciled (Locked)</p>
                    <p class="mt-1 text-lg font-black text-slate-900">${reconciledCount} <span class="text-xs font-bold text-slate-400">txs</span></p>
                    <p class="text-xs font-bold text-slate-500">${formatCurrency(reconciledAmount)}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Void / Excluded</p>
                    <p class="mt-1 text-lg font-black text-slate-900">${voidCount} <span class="text-xs font-bold text-slate-400">txs</span></p>
                    <p class="text-xs font-bold text-slate-500">${formatCurrency(voidAmount)}</p>
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-slate-200 pt-4">
                <p class="text-xs font-bold text-slate-600">
                    ${eligibleCount > 0 ? `Ready to assign ${eligibleCount} entries to ${bankName}.` : `No unassigned eligible transactions found.`}
                </p>
                <button type="button"
                        id="btn-execute-hist"
                        onclick="executeHistoricalFetch()"
                        ${eligibleCount === 0 ? 'disabled' : ''}
                        class="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                    Fetch ${eligibleCount} Eligible Entries
                </button>
            </div>
        </div>
    `;

    if (window.lucide) { lucide.createIcons(); }
}

async function executeHistoricalFetch() {
    if (!currentHistoricalPreview || currentHistoricalPreview.eligible_count <= 0) return;

    const button = document.getElementById('btn-execute-hist');
    button.disabled = true;
    button.textContent = 'Fetching Entries...';

    const payload = {
        shop_id: currentHistoricalPreview.shop.id,
        entry_type_id: currentHistoricalPreview.entry_type.id,
        company_account_id: currentHistoricalPreview.company_account.id,
        from_date: currentHistoricalPreview.from_date,
        to_date: currentHistoricalPreview.to_date,
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.historical-bank-collections.fetch') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            if (window.showToast) showToast(data.message || 'Fetch failed', 'error');
            button.disabled = false;
            button.textContent = `Fetch ${currentHistoricalPreview.eligible_count} Eligible Entries`;
            return;
        }

        if (window.showToast) showToast(data.message, 'success');
        window.location.reload();
    } catch (error) {
        if (window.showToast) showToast(error.message || 'Fetch failed', 'error');
        button.disabled = false;
        button.textContent = `Fetch ${currentHistoricalPreview.eligible_count} Eligible Entries`;
    }
}

// ─── Bank Settlement Adjustment Rules JS ───────────────────────────────────

const allBankAdjustmentRules = @json($bankAdjustmentRules ?? []);
let activeAdjShopId = null;
let activeAdjEntryTypeId = null;

function openBankAdjModal(shopId, entryTypeId, entryTypeName) {
    activeAdjShopId = shopId;
    activeAdjEntryTypeId = entryTypeId;

    document.getElementById('bank-adj-shop-id').value = shopId;
    document.getElementById('bank-adj-entry-type-id').value = entryTypeId;
    document.getElementById('bank-adj-modal-subtitle').textContent = `Shop: {{ $currentShop->name }} • Category: ${entryTypeName}`;
    document.getElementById('bank-adj-label').value = '';

    renderBankAdjRulesList();
    document.getElementById('bank-adj-modal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}

function closeBankAdjModal() {
    document.getElementById('bank-adj-modal').classList.add('hidden');
}

function renderBankAdjRulesList() {
    const listContainer = document.getElementById('bank-adj-rules-list');
    const rules = (allBankAdjustmentRules[activeAdjEntryTypeId] || []);

    if (rules.length === 0) {
        listContainer.innerHTML = `<div class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs font-semibold text-slate-400">No adjustment rules configured yet. Default is direct collection amount.</div>`;
        return;
    }

    listContainer.innerHTML = rules.map(r => `
        <div class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-900">${r.label}</span>
                <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase ${r.direction === 'plus' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">${r.direction}</span>
                ${!r.enabled ? '<span class="rounded bg-slate-200 px-1.5 py-0.5 text-[9px] font-bold text-slate-600">Disabled</span>' : ''}
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleBankAdjRule(${r.id}, ${r.enabled ? 0 : 1})" class="text-[11px] font-bold ${r.enabled ? 'text-amber-700 hover:text-amber-900' : 'text-emerald-700 hover:text-emerald-900'}">
                    ${r.enabled ? 'Disable' : 'Enable'}
                </button>
                <button type="button" onclick="deleteBankAdjRule(${r.id})" class="text-[11px] font-bold text-rose-600 hover:text-rose-800">
                    Delete
                </button>
            </div>
        </div>
    `).join('');
    if (window.lucide) lucide.createIcons();
}

async function submitAddBankAdjRule(event) {
    event.preventDefault();
    const btn = document.getElementById('btn-save-adj-rule');
    const label = document.getElementById('bank-adj-label').value.trim();
    const direction = document.getElementById('bank-adj-direction').value;

    if (!label) return;

    btn.disabled = true;
    btn.textContent = 'Saving...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.bank-adjustment-rules.save') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                shop_id: activeAdjShopId,
                entry_type_id: activeAdjEntryTypeId,
                label,
                direction,
                enabled: 1,
            }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            if (window.showToast) showToast(data.message || 'Failed to save rule', 'error');
            return;
        }

        allBankAdjustmentRules[activeAdjEntryTypeId] = data.rules;
        renderBankAdjRulesList();
        document.getElementById('bank-adj-label').value = '';
        if (window.showToast) showToast('Rule saved successfully.', 'success');
        updateAdjRuleButtonCount(activeAdjEntryTypeId, data.rules.length);
    } catch (err) {
        if (window.showToast) showToast(err.message || 'Failed to save rule', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '+ Add Rule';
    }
}

async function toggleBankAdjRule(ruleId, newEnabledState) {
    const rules = allBankAdjustmentRules[activeAdjEntryTypeId] || [];
    const rule = rules.find(r => r.id === ruleId);
    if (!rule) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    try {
        const response = await fetch('{{ route('admin.cashbook.api.shop-settings.bank-adjustment-rules.save') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                id: rule.id,
                shop_id: activeAdjShopId,
                entry_type_id: activeAdjEntryTypeId,
                label: rule.label,
                direction: rule.direction,
                enabled: newEnabledState,
            }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            if (window.showToast) showToast(data.message || 'Failed to update rule', 'error');
            return;
        }

        allBankAdjustmentRules[activeAdjEntryTypeId] = data.rules;
        renderBankAdjRulesList();
        if (window.showToast) showToast('Rule updated.', 'success');
    } catch (err) {
        if (window.showToast) showToast(err.message || 'Failed to update rule', 'error');
    }
}

async function deleteBankAdjRule(ruleId) {
    if (!confirm('Are you sure you want to delete this adjustment rule?')) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    try {
        const response = await fetch(`/admin/cashbook/api/shop-settings/bank-adjustment-rules/${ruleId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            if (window.showToast) showToast(data.message || 'Failed to delete rule', 'error');
            return;
        }

        allBankAdjustmentRules[activeAdjEntryTypeId] = data.rules;
        renderBankAdjRulesList();
        if (window.showToast) showToast('Rule deleted.', 'success');
        updateAdjRuleButtonCount(activeAdjEntryTypeId, data.rules.length);
    } catch (err) {
        if (window.showToast) showToast(err.message || 'Failed to delete rule', 'error');
    }
}

function updateAdjRuleButtonCount(entryTypeId, count) {
    const btn = document.getElementById(`btn-adj-rules-${entryTypeId}`);
    if (btn) {
        btn.innerHTML = `<i data-lucide="sliders-horizontal" class="h-3.5 w-3.5"></i><span>Adjustments (${count})</span>`;
        if (count > 0) {
            btn.className = 'inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border transition bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100';
        } else {
            btn.className = 'inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border transition bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200';
        }
        if (window.lucide) lucide.createIcons();
    }
}

</script>
@endpush
