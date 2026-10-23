@extends('admin.cashbook.layouts.app')

@section('title', 'Damage & Loss Report - Reports V1')

@section('header_title')
    <i data-lucide="trending-down" class="h-5 w-5 text-rose-600"></i> Damage & Loss Report
@endsection

@section('header_subtitle')
    Two-pillar financial loss audit: Realized selling losses below batch cost & unsellable physical warehouse damage.
@endsection

@php
    $selectedProductIdentifier = $filters['product'] ?? $filters['product_uuid'] ?? $filters['product_id'] ?? null;
    $selectedWarehouseId = $filters['warehouse_id'] ?? null;
    $selectedGrade = $filters['grade'] ?? 'all';
    $selectedShopId = $filters['shop_id'] ?? null;
    $selectedClientId = $filters['client_id'] ?? null;
    $currentPeriod = $filters['period'] ?? 'this_month';
    $startDate = $filters['start_date'] ?? '';
    $endDate = $filters['end_date'] ?? '';
    $formattedRange = $filters['formatted_range'] ?? '';
    $activeTab = $filters['tab'] ?? 'overview';
@endphp

@section('content')
<div
    class="mx-auto max-w-[96rem] space-y-6"
    x-data="{
        currentTab: '{{ $activeTab }}',
        setTab(tab) {
            this.currentTab = tab;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        }
    }"
>

    <!-- TOP FILTER TOOLBAR -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <form method="GET" action="{{ route('admin.cashbook.reports-v1.damage-loss') }}" id="damageLossFilterForm" class="space-y-4">
            <input type="hidden" name="tab" :value="currentTab">

            <!-- PERIOD PRESETS -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 mr-1">Period:</span>
                    @php
                        $periods = [
                            'today' => 'Today',
                            'yesterday' => 'Yesterday',
                            'this_week' => 'This Week',
                            'this_month' => 'This Month',
                            'custom' => 'Custom Range',
                        ];
                    @endphp
                    @foreach($periods as $key => $label)
                        <button
                            type="submit"
                            name="period"
                            value="{{ $key }}"
                            class="rounded-xl px-3.5 py-1.5 text-xs font-bold transition {{ $currentPeriod === $key ? 'bg-slate-950 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-500">Date Range:</span>
                    <span class="rounded-lg bg-rose-50 px-3 py-1 text-xs font-black text-rose-800 border border-rose-200">
                        {{ $formattedRange }}
                    </span>
                </div>
            </div>

            <!-- SEARCH & MULTI-FILTER ROW -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <!-- Product Search -->
                <div class="lg:col-span-2">
                    <label for="search_product" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Product Search
                    </label>
                    <div class="relative flex items-center">
                        <i data-lucide="search" class="absolute left-3 h-3.5 w-3.5 text-slate-400"></i>
                        <input
                            type="text"
                            id="search_product"
                            name="search_product"
                            value="{{ request('search_product') ?? ($product?->name ?? '') }}"
                            placeholder="All products or search name/SKU..."
                            class="w-full rounded-lg border border-slate-300 py-1.5 pl-8 pr-7 text-xs font-medium text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                        >
                        @if($product || request('search_product'))
                            <a
                                href="{{ route('admin.cashbook.reports-v1.damage-loss', request()->except(['product', 'product_uuid', 'product_id', 'search_product'])) }}"
                                class="absolute right-2 text-slate-400 hover:text-slate-600 text-xs"
                                title="Clear product filter"
                            >
                                <i data-lucide="x" class="h-3.5 w-3.5"></i>
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Custom Dates -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Date From / To
                    </label>
                    <div class="grid grid-cols-2 gap-1">
                        <input
                            type="date"
                            name="start_date"
                            value="{{ $startDate }}"
                            class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-mono text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                        >
                        <input
                            type="date"
                            name="end_date"
                            value="{{ $endDate }}"
                            class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-mono text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                        >
                    </div>
                </div>

                <!-- Warehouse Filter -->
                <div>
                    <label for="warehouse_id" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Warehouse
                    </label>
                    <select
                        name="warehouse_id"
                        id="warehouse_id"
                        onchange="document.getElementById('damageLossFilterForm').submit()"
                        class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                    >
                        <option value="">All Warehouses</option>
                        @foreach($options['warehouses'] as $wh)
                            <option value="{{ $wh->id }}" @selected((int)$selectedWarehouseId === (int)$wh->id)>
                                {{ $wh->name }} ({{ $wh->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Grade Filter -->
                <div>
                    <label for="grade" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Grade Filter
                    </label>
                    <select
                        name="grade"
                        id="grade"
                        onchange="document.getElementById('damageLossFilterForm').submit()"
                        class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                    >
                        @foreach($options['grades'] as $gVal => $gLbl)
                            <option value="{{ $gVal }}" @selected(strtoupper($selectedGrade) === strtoupper($gVal))>
                                {{ $gLbl }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Shop Filter -->
                <div>
                    <label for="shop_id" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Client / Shop
                    </label>
                    <select
                        name="shop_id"
                        id="shop_id"
                        onchange="document.getElementById('damageLossFilterForm').submit()"
                        class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                    >
                        <option value="">All Shops</option>
                        @foreach($options['shops'] as $s)
                            <option value="{{ $s->id }}" @selected((int)$selectedShopId === (int)$s->id)>
                                {{ $s->name }} ({{ $s->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <div class="text-[11px] text-slate-500">
                    Two-pillar loss accounting: Selling price below batch landed purchase cost, plus physical warehouse damage write-offs.
                </div>
                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('admin.cashbook.reports-v1.damage-loss') }}"
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition"
                    >
                        Reset Filters
                    </a>
                    <button
                        type="submit"
                        class="rounded-lg bg-rose-600 px-4 py-1.5 text-xs font-bold text-white shadow hover:bg-rose-700 transition"
                    >
                        Apply Filters
                    </button>
                </div>
            </div>
        </form>

        <!-- MULTIPLE SEARCH MATCHES -->
        @if($searchResults->isNotEmpty() && ! $product)
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50/50 p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-800">
                        Select Matching Product ({{ $searchResults->count() }} found)
                    </span>
                    <span class="text-[11px] text-amber-600 font-medium">Click one item to filter losses for that specific product</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                    @foreach($searchResults as $match)
                        <a
                            href="{{ route('admin.cashbook.reports-v1.damage-loss', array_merge(request()->except(['search_product', 'product_id', 'product_uuid']), ['product' => $match->public_uuid ?: $match->id])) }}"
                            class="flex items-center justify-between rounded-lg border border-amber-200 bg-white p-2 text-xs font-semibold text-slate-800 hover:border-rose-500 hover:bg-rose-50 transition group"
                        >
                            <span class="truncate group-hover:text-rose-700">{{ $match->name }}</span>
                            <span class="rounded bg-slate-100 px-1 py-0.5 text-[10px] uppercase text-slate-500 font-mono">
                                {{ $match->unit ?: 'kg' }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- MAIN KPI CARDS (SECTION 1) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Selling Below Cost -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Selling Below Cost</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                    <i data-lucide="tag" class="h-4 w-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-amber-700 tracking-tight">
                ₹{{ number_format($summary->total_selling_loss, 2) }}
            </div>
            <div class="mt-2 flex items-center justify-between text-xs text-slate-500 border-t border-slate-100 pt-2">
                <span>Sold Quantity:</span>
                <span class="font-bold text-slate-800 font-mono">{{ number_format($summary->total_sold_below_cost_kg, 1) }} kg</span>
            </div>
            <div class="mt-1 flex items-center justify-between text-xs text-slate-500">
                <span>Transactions:</span>
                <span class="font-semibold text-slate-700 font-mono">{{ $summary->total_sold_below_cost_lines }} lines</span>
            </div>
        </div>

        <!-- 2. Unsellable Damage -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Unsellable Damage</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-rose-600 tracking-tight">
                ₹{{ number_format($summary->total_damaged_cost, 2) }}
            </div>
            <div class="mt-2 flex items-center justify-between text-xs text-slate-500 border-t border-slate-100 pt-2">
                <span>Damaged Qty:</span>
                <span class="font-bold text-rose-700 font-mono">{{ number_format($summary->total_damaged_kg, 1) }} kg</span>
            </div>
            <div class="mt-1 flex items-center justify-between text-xs text-slate-500">
                <span>Wastage Entries:</span>
                <span class="font-semibold text-slate-700 font-mono">{{ $summary->total_damaged_entries }} entries</span>
            </div>
        </div>

        <!-- 3. Total Combined Loss -->
        <div class="rounded-2xl border border-slate-900 bg-slate-950 p-5 text-white shadow-md">
            <div class="flex items-center justify-between text-slate-400 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Total Financial Loss</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-rose-400">
                    <i data-lucide="alert-octagon" class="h-4 w-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-white tracking-tight">
                ₹{{ number_format($summary->total_loss, 2) }}
            </div>
            <div class="mt-2 text-[11px] text-slate-400 border-t border-slate-800 pt-2 flex items-center justify-between">
                <span>Selling Loss:</span>
                <span class="font-mono text-amber-400 font-bold">₹{{ number_format($summary->total_selling_loss, 0) }}</span>
            </div>
            <div class="mt-0.5 text-[11px] text-slate-400 flex items-center justify-between">
                <span>Physical Damage:</span>
                <span class="font-mono text-rose-400 font-bold">₹{{ number_format($summary->total_damaged_cost, 0) }}</span>
            </div>
        </div>

        <!-- 4. Unallocated Damage & Pending Reconciliations -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Warehouse Unallocated</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                    <i data-lucide="warehouse" class="h-4 w-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-slate-900 tracking-tight">
                ₹{{ number_format($summary->unallocated_warehouse_damage_cost, 2) }}
            </div>
            <div class="mt-2 flex items-center justify-between text-xs text-slate-500 border-t border-slate-100 pt-2">
                <span>Unallocated Qty:</span>
                <span class="font-bold text-slate-800 font-mono">{{ number_format($summary->unallocated_warehouse_damage_kg, 1) }} kg</span>
            </div>
            <div class="mt-1 flex items-center justify-between text-xs">
                @if($summary->has_pending_cost_reconciliation)
                    <span class="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800" title="{{ $summary->pending_cost_count }} uncosted dispatches excluded from definitive loss">
                        <i data-lucide="clock" class="h-3 w-3"></i> {{ $summary->pending_cost_count }} Pending Cost
                    </span>
                    <span class="font-mono text-slate-500 text-[11px]">{{ number_format($summary->pending_cost_qty, 1) }} kg</span>
                @else
                    <span class="text-slate-400">Cost Lineage:</span>
                    <span class="font-bold text-emerald-700 text-[11px]">100% Reconciled</span>
                @endif
            </div>
        </div>
    </div>

    <!-- NAVIGATION TABS -->
    <div class="border-b border-slate-200">
        <nav class="flex space-x-2" aria-label="Tabs">
            <button
                type="button"
                @click="setTab('overview')"
                :class="currentTab === 'overview' ? 'border-rose-600 text-rose-600 bg-rose-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                class="flex items-center gap-2 border-b-2 py-3 px-4 text-xs font-bold transition rounded-t-xl"
            >
                <i data-lucide="layout-grid" class="h-4 w-4"></i>
                Overview & Product Matrix
            </button>
            <button
                type="button"
                @click="setTab('selling_loss')"
                :class="currentTab === 'selling_loss' ? 'border-rose-600 text-rose-600 bg-rose-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                class="flex items-center gap-2 border-b-2 py-3 px-4 text-xs font-bold transition rounded-t-xl"
            >
                <i data-lucide="tag" class="h-4 w-4"></i>
                Selling Below Cost ({{ $sellingLossLines->count() }})
            </button>
            <button
                type="button"
                @click="setTab('damage')"
                :class="currentTab === 'damage' ? 'border-rose-600 text-rose-600 bg-rose-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                class="flex items-center gap-2 border-b-2 py-3 px-4 text-xs font-bold transition rounded-t-xl"
            >
                <i data-lucide="trash-2" class="h-4 w-4"></i>
                Unsellable Damage ({{ $damageEntries->count() }})
            </button>
            <button
                type="button"
                @click="setTab('daily_monthly')"
                :class="currentTab === 'daily_monthly' ? 'border-rose-600 text-rose-600 bg-rose-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                class="flex items-center gap-2 border-b-2 py-3 px-4 text-xs font-bold transition rounded-t-xl"
            >
                <i data-lucide="calendar" class="h-4 w-4"></i>
                Daily & Monthly Reconciliation
            </button>
            <button
                type="button"
                @click="setTab('client_shop')"
                :class="currentTab === 'client_shop' ? 'border-rose-600 text-rose-600 bg-rose-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                class="flex items-center gap-2 border-b-2 py-3 px-4 text-xs font-bold transition rounded-t-xl"
            >
                <i data-lucide="store" class="h-4 w-4"></i>
                Client & Shop Attribution
            </button>
        </nav>
    </div>

    <!-- TAB 1: OVERVIEW & PRODUCT MATRIX -->
    <div x-show="currentTab === 'overview'" class="space-y-6">
        <div
            x-data="{
                searchQuery: '',
                page: 1,
                perPage: 5,
                rawRows: {{ Js::from($productMatrix) }},
                get filteredRows() {
                    if (!this.searchQuery.trim()) return this.rawRows;
                    const q = this.searchQuery.toLowerCase().trim();
                    return this.rawRows.filter(r =>
                        (r.product_name && r.product_name.toLowerCase().includes(q)) ||
                        (r.grade_label && r.grade_label.toLowerCase().includes(q))
                    );
                },
                get totalPages() {
                    return Math.max(1, Math.ceil(this.filteredRows.length / this.perPage));
                },
                get paginatedRows() {
                    const start = (this.page - 1) * this.perPage;
                    return this.filteredRows.slice(start, start + this.perPage);
                },
                setSearch(val) {
                    this.searchQuery = val;
                    this.page = 1;
                },
                nextPage() {
                    if (this.page < this.totalPages) this.page++;
                },
                prevPage() {
                    if (this.page > 1) this.page--;
                }
            }"
            class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
        >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 bg-slate-50/75 px-5 py-3.5">
                <div class="flex items-center gap-2">
                    <i data-lucide="table" class="h-4 w-4 text-slate-500"></i>
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Product & Grade Loss Breakdown</h3>
                    <span class="rounded-full bg-slate-200/80 px-2 py-0.5 text-[11px] font-semibold text-slate-700" x-text="`${filteredRows.length} combinations`"></span>
                    <span class="text-[11px] text-slate-400 font-medium hidden sm:inline">(5 per page)</span>
                </div>

                <!-- Product Matrix Search -->
                <div class="relative w-full sm:w-64">
                    <i data-lucide="search" class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400"></i>
                    <input
                        type="text"
                        :value="searchQuery"
                        @input="setSearch($event.target.value)"
                        placeholder="Search product or grade..."
                        class="w-full rounded-lg border border-slate-300 py-1.5 pl-8 pr-7 text-xs font-medium text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 shadow-sm"
                    >
                    <template x-if="searchQuery.length > 0">
                        <button
                            type="button"
                            @click="setSearch('')"
                            class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 text-xs"
                            title="Clear search"
                        >
                            <i data-lucide="x" class="h-3.5 w-3.5"></i>
                        </button>
                    </template>
                </div>
            </div>

            <template x-if="filteredRows.length === 0">
                <div class="p-12 text-center text-slate-400">
                    <i data-lucide="shield-check" class="h-8 w-8 mx-auto text-emerald-500 mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-700">No Losses Matching Criteria</h4>
                    <p class="text-xs text-slate-500 mt-1">
                        No product loss records match your search or filter criteria in the selected period.
                    </p>
                    <template x-if="searchQuery.length > 0">
                        <button
                            type="button"
                            @click="setSearch('')"
                            class="inline-block mt-3 rounded-lg bg-rose-50 border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition"
                        >
                            Clear Search
                        </button>
                    </template>
                </div>
            </template>

            <template x-if="filteredRows.length > 0">
                <div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="py-3 px-4">Product</th>
                                    <th class="py-3 px-4">Grade</th>
                                    <th class="py-3 px-4 text-right">Sold Below Cost (kg)</th>
                                    <th class="py-3 px-4 text-right">Selling Loss (₹)</th>
                                    <th class="py-3 px-4 text-right">Damaged Qty (kg)</th>
                                    <th class="py-3 px-4 text-right">Damage Loss (₹)</th>
                                    <th class="py-3 px-4 text-right font-black">Total Loss (₹)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                <template x-for="row in paginatedRows" :key="`${row.product_id}-${row.grade}`">
                                    <tr class="hover:bg-slate-50/75 transition">
                                        <td class="py-2.5 px-4 font-bold text-slate-900">
                                            <span x-text="row.product_name"></span>
                                            <span class="text-[10px] text-slate-400 font-mono" x-text="`(${row.product_unit.toUpperCase()})`"></span>
                                        </td>
                                        <td class="py-2.5 px-4 whitespace-nowrap">
                                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold" x-text="row.grade_label"></span>
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-mono" x-text="row.sold_below_cost_qty > 0 ? Number(row.sold_below_cost_qty).toFixed(1) : '—'"></td>
                                        <td
                                            class="py-2.5 px-4 text-right font-mono font-bold"
                                            :class="row.selling_loss > 0 ? 'text-amber-700' : 'text-slate-400'"
                                            x-text="row.selling_loss > 0 ? '₹' + Number(row.selling_loss).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '—'"
                                        ></td>
                                        <td class="py-2.5 px-4 text-right font-mono" x-text="row.damaged_qty > 0 ? Number(row.damaged_qty).toFixed(1) : '—'"></td>
                                        <td
                                            class="py-2.5 px-4 text-right font-mono font-bold"
                                            :class="row.damaged_cost > 0 ? 'text-rose-600' : 'text-slate-400'"
                                            x-text="row.damaged_cost > 0 ? '₹' + Number(row.damaged_cost).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '—'"
                                        ></td>
                                        <td class="py-2.5 px-4 text-right font-mono font-black text-rose-700" x-text="'₹' + Number(row.total_loss).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION CONTROLS (5 PER PAGE) -->
                    <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-5 py-3">
                        <div class="text-xs text-slate-500 font-medium">
                            Showing <span class="font-bold text-slate-800" x-text="Math.min((page - 1) * perPage + 1, filteredRows.length)"></span>
                            to <span class="font-bold text-slate-800" x-text="Math.min(page * perPage, filteredRows.length)"></span>
                            of <span class="font-bold text-slate-800" x-text="filteredRows.length"></span> products
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="prevPage()"
                                :disabled="page <= 1"
                                :class="page <= 1 ? 'opacity-50 cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-white text-slate-700 hover:bg-slate-100'"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold shadow-sm transition"
                            >
                                Previous
                            </button>
                            <span class="text-xs font-bold text-slate-700 px-2" x-text="`Page ${page} of ${totalPages}`"></span>
                            <button
                                type="button"
                                @click="nextPage()"
                                :disabled="page >= totalPages"
                                :class="page >= totalPages ? 'opacity-50 cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-white text-slate-700 hover:bg-slate-100'"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold shadow-sm transition"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- TAB 2: SELLING BELOW COST (SECTION 2) -->
    <div x-show="currentTab === 'selling_loss'" class="space-y-4" style="display: none;">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/75 px-5 py-3.5">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Eligible Below-Cost Transactions</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Formula: MAX(Attributable Purchase Cost − Net Selling Value, 0)
                    </p>
                </div>
                <span class="rounded-lg bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">
                    Total: ₹{{ number_format($summary->total_selling_loss, 2) }}
                </span>
            </div>

            @if($sellingLossLines->isEmpty())
                <div class="p-12 text-center text-slate-400">
                    <i data-lucide="check-circle" class="h-8 w-8 mx-auto text-emerald-500 mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-700">Zero Selling Losses</h4>
                    <p class="text-xs text-slate-500 mt-1">All billed invoice transactions were executed at or above batch purchase cost.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Invoice</th>
                                <th class="py-2.5 px-3">Client / Shop</th>
                                <th class="py-2.5 px-3">Product</th>
                                <th class="py-2.5 px-3">Grade</th>
                                <th class="py-2.5 px-3 text-right">Sold Qty</th>
                                <th class="py-2.5 px-3">Batch Ref</th>
                                <th class="py-2.5 px-3 text-right">Cost/kg</th>
                                <th class="py-2.5 px-3 text-right">Net Sell/kg</th>
                                <th class="py-2.5 px-3 text-right">Loss/kg</th>
                                <th class="py-2.5 px-3 text-right font-black">Total Loss (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach($sellingLossLines as $line)
                                <tr class="hover:bg-slate-50/75 transition">
                                    <td class="py-2 px-3 whitespace-nowrap text-slate-500 font-mono">{{ $line->date }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap font-mono font-bold text-slate-900">{{ $line->invoice_number }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        <div class="font-bold text-slate-800">{{ $line->shop_name }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $line->client_name }}</div>
                                    </td>
                                    <td class="py-2 px-3 whitespace-nowrap font-bold text-slate-900">{{ $line->product_name }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold font-mono">
                                            Grade {{ $line->commercial_grade }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono font-bold">{{ number_format($line->sold_qty, 1) }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap font-mono text-[10px] text-slate-500">{{ $line->batch_reference }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-slate-800">₹{{ number_format($line->purchase_cost, 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-slate-800">₹{{ number_format($line->net_selling_rate, 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-rose-600 font-bold">₹{{ number_format($line->loss_per_kg, 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-black text-rose-700">₹{{ number_format($line->total_loss, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- TAB 3: UNSELLABLE DAMAGE (SECTION 3) -->
    <div x-show="currentTab === 'damage'" class="space-y-4" style="display: none;">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/75 px-5 py-3.5">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Unsellable Physical Damage Log</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Authoritative WastageEntry records with attributable batch landed cost.
                    </p>
                </div>
                <span class="rounded-lg bg-rose-100 px-3 py-1 text-xs font-bold text-rose-800">
                    Total: ₹{{ number_format($summary->total_damaged_cost, 2) }}
                </span>
            </div>

            @if($damageEntries->isEmpty())
                <div class="p-12 text-center text-slate-400">
                    <i data-lucide="check-circle" class="h-8 w-8 mx-auto text-emerald-500 mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-700">No Physical Damage Logged</h4>
                    <p class="text-xs text-slate-500 mt-1">No unsellable wastage entries were logged in the selected period.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Product</th>
                                <th class="py-2.5 px-3">Source Grade</th>
                                <th class="py-2.5 px-3 text-right">Quantity (kg)</th>
                                <th class="py-2.5 px-3 text-right">Cost/kg (₹)</th>
                                <th class="py-2.5 px-3 text-right font-black">Loss Amount (₹)</th>
                                <th class="py-2.5 px-3">Warehouse</th>
                                <th class="py-2.5 px-3">Reason</th>
                                <th class="py-2.5 px-3">Batch Ref</th>
                                <th class="py-2.5 px-3">Attribution</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach($damageEntries as $we)
                                <tr class="hover:bg-slate-50/75 transition">
                                    <td class="py-2 px-3 whitespace-nowrap text-slate-500 font-mono">{{ $we->date }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap font-bold text-slate-900">{{ $we->product_name }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold font-mono">
                                            {{ $we->source_grade_label }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono font-bold">{{ number_format($we->quantity, 1) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-slate-800">₹{{ number_format($we->cost_per_kg, 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-black text-rose-700">₹{{ number_format($we->total_cost, 2) }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap text-slate-600">{{ $we->warehouse_name }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        <span class="rounded bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 border border-rose-200">
                                            {{ $we->reason }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 whitespace-nowrap font-mono text-[10px] text-slate-500">{{ $we->batch_reference }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        @if($we->is_client_attributable)
                                            <span class="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-200" title="Delivery review damage: {{ $we->order_number }}">
                                                {{ $we->shop_name }}
                                            </span>
                                        @else
                                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">
                                                Warehouse Unallocated
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- TAB 4: DAILY & MONTHLY RECONCILIATION (SECTION 4) -->
    <div x-show="currentTab === 'daily_monthly'" class="space-y-6" style="display: none;">
        <!-- Daily Report -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/75 px-5 py-3.5">
                <div class="flex items-center gap-2">
                    <i data-lucide="calendar" class="h-4 w-4 text-slate-500"></i>
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Daily Loss Ledger</h3>
                </div>
                <span class="text-xs text-slate-500 font-medium">
                    Strict reconciliation: Daily sums match monthly aggregates
                </span>
            </div>

            @if($dailySummary->isEmpty())
                <div class="p-8 text-center text-slate-400 text-xs">No daily activity recorded for this period.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-4">Business Date</th>
                                <th class="py-2.5 px-4 text-right">Sold Below Cost (kg)</th>
                                <th class="py-2.5 px-4 text-right">Selling Loss (₹)</th>
                                <th class="py-2.5 px-4 text-right">Damaged Qty (kg)</th>
                                <th class="py-2.5 px-4 text-right">Damage Cost (₹)</th>
                                <th class="py-2.5 px-4 text-right font-black">Total Daily Loss (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach($dailySummary as $day)
                                <tr class="hover:bg-slate-50/75 transition">
                                    <td class="py-2.5 px-4 font-mono font-bold text-slate-900">{{ $day->formatted_date }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono">{{ $day->sold_below_cost_qty > 0 ? number_format($day->sold_below_cost_qty, 1) : '—' }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold {{ $day->selling_loss > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                                        {{ $day->selling_loss > 0 ? '₹'.number_format($day->selling_loss, 2) : '—' }}
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-mono">{{ $day->damaged_qty > 0 ? number_format($day->damaged_qty, 1) : '—' }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold {{ $day->damage_cost > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                        {{ $day->damage_cost > 0 ? '₹'.number_format($day->damage_cost, 2) : '—' }}
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-mono font-black text-rose-700">
                                        ₹{{ number_format($day->total_loss, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Monthly Report -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/75 px-5 py-3.5">
                <div class="flex items-center gap-2">
                    <i data-lucide="calendar-range" class="h-4 w-4 text-slate-500"></i>
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Monthly Summary Ledger</h3>
                </div>
                <span class="text-xs text-slate-500 font-medium">Reconciled Monthly Totals</span>
            </div>

            @if($monthlySummary->isEmpty())
                <div class="p-8 text-center text-slate-400 text-xs">No monthly summary data recorded.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-4">Month</th>
                                <th class="py-2.5 px-4 text-right">Sold Below Cost (kg)</th>
                                <th class="py-2.5 px-4 text-right">Selling Loss (₹)</th>
                                <th class="py-2.5 px-4 text-right">Damaged Qty (kg)</th>
                                <th class="py-2.5 px-4 text-right">Damage Cost (₹)</th>
                                <th class="py-2.5 px-4 text-right font-black">Total Monthly Loss (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach($monthlySummary as $month)
                                <tr class="hover:bg-slate-50/75 transition">
                                    <td class="py-2.5 px-4 font-bold text-slate-900">{{ $month->month_label }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono">{{ number_format($month->sold_below_cost_qty, 1) }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold text-amber-700">₹{{ number_format($month->selling_loss, 2) }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono">{{ number_format($month->damaged_qty, 1) }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold text-rose-600">₹{{ number_format($month->damage_cost, 2) }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono font-black text-rose-700">₹{{ number_format($month->total_loss, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- TAB 5: CLIENT & SHOP ATTRIBUTION (SECTION 5) -->
    <div x-show="currentTab === 'client_shop'" class="space-y-6" style="display: none;">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/75 px-5 py-3.5">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Client & Shop Loss Attribution</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Selling losses directly linked to shop orders and invoices. Warehouse inventory damage is strictly unallocated.
                    </p>
                </div>
            </div>

            @if($clientShopSummary->isEmpty())
                <div class="p-8 text-center text-slate-400 text-xs">No client/shop losses recorded in this period.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-4">Shop</th>
                                <th class="py-2.5 px-4">Client</th>
                                <th class="py-2.5 px-4 text-right">Sold Below Cost (kg)</th>
                                <th class="py-2.5 px-4 text-right">Selling Loss (₹)</th>
                                <th class="py-2.5 px-4 text-right">Delivery Damage (kg)</th>
                                <th class="py-2.5 px-4 text-right">Delivery Damage (₹)</th>
                                <th class="py-2.5 px-4 text-right font-black">Total Client Loss (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach($clientShopSummary as $cs)
                                <tr class="hover:bg-slate-50/75 transition">
                                    <td class="py-2.5 px-4 font-bold text-slate-900">{{ $cs->shop_name }}</td>
                                    <td class="py-2.5 px-4 text-slate-500">{{ $cs->client_name }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono">{{ number_format($cs->sold_below_cost_qty, 1) }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold text-amber-700">₹{{ number_format($cs->selling_loss, 2) }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono">{{ number_format($cs->attributable_damage_qty, 1) }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold text-rose-600">₹{{ number_format($cs->attributable_damage_cost, 2) }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono font-black text-rose-700">₹{{ number_format($cs->total_client_loss, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Unallocated Warehouse Damage Notice -->
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <i data-lucide="shield-alert" class="h-5 w-5 text-slate-600"></i>
                    <h4 class="text-sm font-bold text-slate-800">Unallocated Warehouse Damage</h4>
                </div>
                <span class="rounded-lg bg-slate-200 px-3 py-1 text-xs font-bold font-mono text-slate-800">
                    ₹{{ number_format($unallocatedWarehouseDamage->cost, 2) }} ({{ number_format($unallocatedWarehouseDamage->quantity, 1) }} kg)
                </span>
            </div>
            <p class="text-xs text-slate-600">
                In strict compliance with financial segregation rules, internal warehouse sorting and storage damage is not assigned to clients. Only damage verified and documented during shop delivery review is attributed to specific clients or shops.
            </p>
        </div>
    </div>
</div>
@endsection
