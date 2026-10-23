@extends('admin.cashbook.layouts.app')

@section('title', 'Grades Report - Reports V1')

@section('header_title')
    <i data-lucide="layers" class="h-5 w-5 text-emerald-600"></i> Grades Report
@endsection

@section('header_subtitle')
    Authoritative product & grade-wise procurement, segregation, regrading, loadout, damage, and realized sales.
@endsection

@php
    $selectedProductIdentifier = $filters['product'] ?? $filters['product_uuid'] ?? $filters['product_id'] ?? null;
    $selectedWarehouseId = $filters['warehouse_id'] ?? null;
    $selectedGrade = $filters['grade'] ?? 'all';
    $selectedPurchaserId = $filters['purchaser_id'] ?? null;
    $selectedShopId = $filters['shop_id'] ?? null;
    $currentPeriod = $filters['period'] ?? 'this_month';
    $startDate = $filters['start_date'] ?? '';
    $endDate = $filters['end_date'] ?? '';
    $formattedRange = $filters['formatted_range'] ?? '';
@endphp

@section('content')
<div
    class="mx-auto max-w-[96rem] space-y-6"
    x-data="{
        showModal: false,
        loadingDetail: false,
        activeBatch: null,
        errorDetail: null,
        async openBatchModal(batchIdentifier) {
            if (!batchIdentifier) return;
            this.showModal = true;
            this.loadingDetail = true;
            this.errorDetail = null;
            this.activeBatch = null;

            try {
                const url = '{{ route('admin.cashbook.reports-v1.grades.batch-detail', ':batch') }}'.replace(':batch', encodeURIComponent(batchIdentifier));
                const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Failed to load batch audit detail');
                const json = await res.json();
                this.activeBatch = json.data;
            } catch (e) {
                this.errorDetail = e.message || 'Unable to load batch details';
            } finally {
                this.loadingDetail = false;
            }
        },
        closeModal() {
            this.showModal = false;
            this.activeBatch = null;
            this.errorDetail = null;
        }
    }"
    @keydown.escape.window="closeModal()"
>

    <!-- TOP FILTER TOOLBAR -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <form method="GET" action="{{ route('admin.cashbook.reports-v1.grades') }}" id="gradesFilterForm" class="space-y-4">
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
                    <span class="rounded-lg bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-800 border border-emerald-200">
                        {{ $formattedRange }}
                    </span>
                </div>
            </div>

            <!-- SEARCH & MULTI-FILTER ROW -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <!-- Product Search / Input -->
                <div class="lg:col-span-2">
                    <label for="search_product" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Product Search / Filter
                    </label>
                    <div class="relative flex items-center">
                        <i data-lucide="search" class="absolute left-3 h-3.5 w-3.5 text-slate-400"></i>
                        <input
                            type="text"
                            id="search_product"
                            name="search_product"
                            value="{{ request('search_product') ?? ($product?->name ?? '') }}"
                            placeholder="All products or search name/SKU..."
                            class="w-full rounded-lg border border-slate-300 py-1.5 pl-8 pr-7 text-xs font-medium text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        >
                        @if($product || request('search_product'))
                            <a
                                href="{{ route('admin.cashbook.reports-v1.grades', request()->except(['product', 'product_uuid', 'product_id', 'search_product'])) }}"
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
                            class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-mono text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        >
                        <input
                            type="date"
                            name="end_date"
                            value="{{ $endDate }}"
                            class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-mono text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
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
                        onchange="document.getElementById('gradesFilterForm').submit()"
                        class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
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
                        onchange="document.getElementById('gradesFilterForm').submit()"
                        class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    >
                        @foreach($options['grades'] as $gVal => $gLbl)
                            <option value="{{ $gVal }}" @selected(strtoupper($selectedGrade) === strtoupper($gVal))>
                                {{ $gLbl }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Purchaser & Shop Filters -->
                <div>
                    <label for="purchaser_id" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                        Purchaser
                    </label>
                    <select
                        name="purchaser_id"
                        id="purchaser_id"
                        onchange="document.getElementById('gradesFilterForm').submit()"
                        class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs font-medium text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    >
                        <option value="">All Purchasers</option>
                        @foreach($options['purchasers'] as $p)
                            <option value="{{ $p->id }}" @selected((int)$selectedPurchaserId === (int)$p->id)>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <div class="text-[11px] text-slate-500">
                    Showing authoritative lineage: direct-grade & mixed purchases, segregation output, loadout dispatches, wastage write-offs, and invoiced selling prices.
                </div>
                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('admin.cashbook.reports-v1.grades') }}"
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition"
                    >
                        Reset Filters
                    </a>
                    <button
                        type="submit"
                        class="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-bold text-white shadow hover:bg-emerald-700 transition"
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
                    <span class="text-[11px] text-amber-600 font-medium">Click one item to inspect single-product grade lineage</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                    @foreach($searchResults as $match)
                        <a
                            href="{{ route('admin.cashbook.reports-v1.grades', array_merge(request()->except(['search_product', 'product_id', 'product_uuid']), ['product' => $match->public_uuid ?: $match->id])) }}"
                            class="flex items-center justify-between rounded-lg border border-amber-200 bg-white p-2 text-xs font-semibold text-slate-800 hover:border-emerald-500 hover:bg-emerald-50 transition group"
                        >
                            <span class="truncate group-hover:text-emerald-700">{{ $match->name }}</span>
                            <span class="rounded bg-slate-100 px-1 py-0.5 text-[10px] uppercase text-slate-500 font-mono">
                                {{ $match->unit ?: 'kg' }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- SUMMARY KPI METRICS CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- 1. Original Purchased -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-1.5">
                <span class="text-[11px] font-bold uppercase tracking-wider">Orig. Purchased</span>
                <i data-lucide="shopping-cart" class="h-4 w-4 text-emerald-600"></i>
            </div>
            <div class="text-lg font-black text-slate-900 tracking-tight">
                {{ number_format($summary->total_purchased_kg, 1) }} <span class="text-xs font-semibold text-slate-400">kg</span>
            </div>
            <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                <span>Cost: ₹{{ number_format($summary->total_purchased_cost, 0) }}</span>
                <span class="font-semibold text-slate-700 font-mono">₹{{ number_format($summary->weighted_purchase_cost, 1) }}/kg</span>
            </div>
        </div>

        <!-- 2. Segregated & Regraded -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-1.5">
                <span class="text-[11px] font-bold uppercase tracking-wider">Segregated / Regraded</span>
                <i data-lucide="git-branch" class="h-4 w-4 text-indigo-600"></i>
            </div>
            <div class="text-lg font-black text-slate-900 tracking-tight">
                {{ number_format($summary->total_segregated_kg, 1) }} <span class="text-xs font-semibold text-slate-400">kg</span>
            </div>
            <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                <span>Regraded:</span>
                <span class="font-semibold text-indigo-700 font-mono">{{ number_format($summary->total_regraded_kg, 1) }} kg</span>
            </div>
        </div>

        <!-- 3. Loaded Out -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-1.5">
                <span class="text-[11px] font-bold uppercase tracking-wider">Loaded Dispatches</span>
                <i data-lucide="truck" class="h-4 w-4 text-blue-600"></i>
            </div>
            <div class="text-lg font-black text-slate-900 tracking-tight">
                {{ number_format($summary->total_physical_loaded_kg, 1) }} <span class="text-xs font-semibold text-slate-400">kg</span>
            </div>
            <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                <span>Physical Out:</span>
                <span class="font-semibold text-blue-700 font-mono">{{ number_format($summary->total_physical_loaded_kg, 1) }} kg</span>
            </div>
        </div>

        <!-- 4. Damaged Write-off -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-1.5">
                <span class="text-[11px] font-bold uppercase tracking-wider">Damage (Write-off)</span>
                <i data-lucide="trash-2" class="h-4 w-4 text-rose-500"></i>
            </div>
            <div class="text-lg font-black text-rose-600 tracking-tight">
                {{ number_format($summary->total_damaged_kg, 1) }} <span class="text-xs font-semibold text-rose-400">kg</span>
            </div>
            <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                <span>Loss:</span>
                <span class="font-semibold text-rose-700 font-mono">₹{{ number_format($summary->total_damaged_cost, 0) }}</span>
            </div>
        </div>

        <!-- 5. Available Stock -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-1.5">
                <span class="text-[11px] font-bold uppercase tracking-wider">Available Stock</span>
                <i data-lucide="archive" class="h-4 w-4 text-emerald-600"></i>
            </div>
            <div class="text-lg font-black text-slate-900 tracking-tight">
                {{ number_format($summary->total_available_kg, 1) }} <span class="text-xs font-semibold text-slate-400">kg</span>
            </div>
            <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                <span>Valuation:</span>
                <span class="font-semibold text-emerald-700 font-mono">₹{{ number_format($summary->total_available_valuation, 0) }}</span>
            </div>
        </div>

        <!-- 6. Realized Sales & Margin -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-1.5">
                <span class="text-[11px] font-bold uppercase tracking-wider">Invoiced Sales</span>
                <i data-lucide="receipt" class="h-4 w-4 text-slate-700"></i>
            </div>
            <div class="text-lg font-black text-slate-900 tracking-tight">
                ₹{{ number_format($summary->total_invoiced_sales, 0) }}
            </div>
            <div class="mt-1 flex items-center justify-between text-[11px]">
                @if($summary->has_pending_cost_reconciliation)
                    <span class="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800" title="{{ $summary->pending_reconciliation_count }} negative dispatches awaiting purchase settlement">
                        <i data-lucide="alert-triangle" class="h-3 w-3"></i> Pending Cost
                    </span>
                    <span class="font-mono text-slate-400">—</span>
                @else
                    <span class="text-slate-500">Realized Rate:</span>
                    <span class="font-bold text-emerald-700 font-mono">
                        {{ $summary->margin_percent !== null ? $summary->margin_percent.'%' : '₹'.number_format($summary->weighted_selling_rate, 1).'/kg' }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- NOTICE ON COMMERCIAL GRADES & PENDING COST RECONCILIATION -->
    <div class="rounded-xl border border-slate-200 bg-slate-50/75 p-3.5 flex flex-col md:flex-row md:items-center md:justify-between gap-2 text-xs text-slate-600">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
            <span><strong>Commercial Grades A, B, and C</strong> are tracked separately from <strong>Mixed (U)</strong> stock and unallocated <strong>Damage (Wastage)</strong> write-offs.</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
            <span>Where negative dispatches occurred, cost lineage is retained and marked <strong>Pending Cost Reconciliation</strong> until matched with vendor purchases.</span>
        </div>
    </div>

    <!-- PRODUCT & GRADE MATRIX TABLE -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 bg-slate-50/75 px-5 py-3.5">
            <div class="flex items-center gap-2">
                <i data-lucide="table" class="h-4 w-4 text-slate-500"></i>
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Product & Grade Breakdown</h2>
                <span class="rounded-full bg-slate-200/80 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                    {{ $products->total() }} Products
                </span>
                <span class="text-[11px] text-slate-400 font-medium hidden sm:inline">(5 per page)</span>
            </div>

            <!-- PROMINENT PRODUCT SEARCH FIELD (SERVER-SIDE DEBOUNCED 300ms) -->
            <div x-data="{
                searchQuery: '{{ request('search') ?? request('search_product') ?? '' }}',
                timer: null,
                onInput(val) {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => {
                        const url = new URL(window.location.href);
                        if (val.trim()) {
                            url.searchParams.set('search', val.trim());
                        } else {
                            url.searchParams.delete('search');
                            url.searchParams.delete('search_product');
                        }
                        url.searchParams.set('page', '1');
                        window.location.href = url.toString();
                    }, 300);
                },
                clearSearch() {
                    this.searchQuery = '';
                    const url = new URL(window.location.href);
                    url.searchParams.delete('search');
                    url.searchParams.delete('search_product');
                    url.searchParams.set('page', '1');
                    window.location.href = url.toString();
                }
            }" class="relative w-full sm:w-72">
                <div class="relative flex items-center">
                    <i data-lucide="search" class="absolute left-3 h-3.5 w-3.5 text-slate-400"></i>
                    <input
                        type="text"
                        id="grades-product-search"
                        x-model="searchQuery"
                        @input="onInput($event.target.value)"
                        placeholder="Search products by name or SKU..."
                        class="w-full rounded-lg border border-slate-300 py-1.5 pl-8 pr-7 text-xs font-medium text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm"
                    >
                    <template x-if="searchQuery && searchQuery.length > 0">
                        <button
                            type="button"
                            @click="clearSearch()"
                            class="absolute right-2 text-slate-400 hover:text-slate-600 text-xs"
                            title="Clear search"
                        >
                            <i data-lucide="x" class="h-3.5 w-3.5"></i>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        @if(empty($productSummaries) || $products->isEmpty())
            <div class="p-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                    <i data-lucide="inbox" class="h-6 w-6"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800">No Products Matching Criteria</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    @if(request('search') || request('search_product'))
                        No products found matching "{{ request('search') ?? request('search_product') }}". Try adjusting your search query or clear it.
                    @else
                        No batches, movements, or invoice records match the selected date range and filter criteria.
                    @endif
                </p>
                @if(request('search') || request('search_product'))
                    <a
                        href="{{ route('admin.cashbook.reports-v1.grades', request()->except(['search', 'search_product', 'page'])) }}"
                        class="inline-block mt-3 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition"
                    >
                        Clear Search
                    </a>
                @endif
            </div>
        @else
            <div class="divide-y divide-slate-200">
                @foreach($productSummaries as $prodId => $data)
                    @php
                        $prod = $data['product'];
                        $grades = $data['grades'];
                    @endphp

                    <div class="p-4 hover:bg-slate-50/50 transition">
                        <!-- Product Header Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800 font-bold text-xs">
                                    {{ substr($prod->name, 0, 2) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <a
                                            href="{{ route('admin.cashbook.reports-v1.grades', array_merge(request()->query(), ['product' => $prod->public_uuid ?: $prod->id])) }}"
                                            class="text-sm font-bold text-slate-900 hover:text-emerald-700 transition"
                                        >
                                            {{ $prod->name }}
                                        </a>
                                        @if($prod->category)
                                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                                {{ $prod->category->name }}
                                            </span>
                                        @endif
                                        <span class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-mono text-slate-500">
                                            {{ strtoupper($prod->unit ?: 'kg') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 text-xs">
                                <div>
                                    <span class="text-slate-400">Total Purchased:</span>
                                    <span class="font-bold text-slate-800 font-mono">{{ number_format($data['total_purchased_kg'], 1) }} kg</span>
                                </div>
                                <div class="text-slate-300">•</div>
                                <div>
                                    <span class="text-slate-400">Available:</span>
                                    <span class="font-bold text-emerald-700 font-mono">{{ number_format($data['total_available_kg'], 1) }} kg</span>
                                </div>
                                <div class="text-slate-300">•</div>
                                <div>
                                    <span class="text-slate-400">Weighted Cost:</span>
                                    <span class="font-bold text-slate-800 font-mono">₹{{ number_format($data['weighted_purchase_cost'], 1) }}/kg</span>
                                </div>
                                <div class="text-slate-300">•</div>
                                <div>
                                    <span class="text-slate-400">Realized Rate:</span>
                                    <span class="font-bold text-slate-800 font-mono">₹{{ number_format($data['weighted_selling_rate'], 1) }}/kg</span>
                                </div>
                                @if($data['has_pending_cost'])
                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">
                                        Pending Cost Reconciliation ({{ $data['pending_count'] }})
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Grades Breakdown Sub-Table -->
                        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                    <tr>
                                        <th class="py-2.5 px-3">Grade</th>
                                        <th class="py-2.5 px-3 text-right">Orig. Purchased</th>
                                        <th class="py-2.5 px-3 text-right">Segregated In</th>
                                        <th class="py-2.5 px-3 text-right">Regraded</th>
                                        <th class="py-2.5 px-3 text-right">Physical Loaded</th>
                                        <th class="py-2.5 px-3 text-right">Commercial Loaded</th>
                                        <th class="py-2.5 px-3 text-right">Damaged (Loss)</th>
                                        <th class="py-2.5 px-3 text-right">Current Stock</th>
                                        <th class="py-2.5 px-3 text-right">Purchase Cost</th>
                                        <th class="py-2.5 px-3 text-right">Invoiced Rate</th>
                                        <th class="py-2.5 px-3 text-right">Gross Margin</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                    @foreach($grades as $gCode => $gData)
                                        @php
                                            $gradeBadge = match($gCode) {
                                                'A' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                                'B' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                'C' => 'bg-amber-100 text-amber-800 border-amber-200',
                                                default => 'bg-slate-100 text-slate-800 border-slate-200',
                                            };
                                        @endphp
                                        <tr class="hover:bg-slate-50/75 transition">
                                            <td class="py-2 px-3 whitespace-nowrap">
                                                <span class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-bold {{ $gradeBadge }}">
                                                    <span>{{ $gData['grade_label'] }}</span>
                                                </span>
                                            </td>

                                            <!-- Original Purchased (kg) -->
                                            <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                                                @if($gData['purchased_kg'] > 0)
                                                    <span class="font-bold text-slate-900">{{ number_format($gData['purchased_kg'], 1) }}</span>
                                                    <span class="text-[10px] text-slate-400">kg</span>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>

                                            <!-- Segregated In (kg) -->
                                            <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                                                @if($gData['segregated_kg'] > 0)
                                                    <span class="font-semibold text-indigo-700">{{ number_format($gData['segregated_kg'], 1) }}</span>
                                                    <span class="text-[10px] text-slate-400">kg</span>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>

                                            <!-- Regraded (kg) -->
                                            <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                                                @if($gData['regraded_kg'] > 0)
                                                    <span class="font-semibold text-purple-700">{{ number_format($gData['regraded_kg'], 1) }}</span>
                                                    <span class="text-[10px] text-slate-400">kg</span>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>

                                            <!-- Physical Loaded (kg) -->
                                            <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                                                @if($gData['physical_loaded_kg'] > 0)
                                                    <span class="font-semibold text-slate-800">{{ number_format($gData['physical_loaded_kg'], 1) }}</span>
                                                    <span class="text-[10px] text-slate-400">kg</span>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>

                                            <!-- Commercial Loaded (kg) -->
                                            <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                                                @if($gData['commercial_loaded_kg'] > 0)
                                                    <span class="font-bold text-blue-700">{{ number_format($gData['commercial_loaded_kg'], 1) }}</span>
                                                    <span class="text-[10px] text-slate-400">kg</span>
                                                    @if($gCode !== 'U' && $gData['commercial_loaded_kg'] > $gData['physical_loaded_kg'])
                                                        <span class="block text-[10px] text-blue-500 font-sans">
                                                            (incl. {{ number_format($gData['commercial_loaded_kg'] - $gData['physical_loaded_kg'], 1) }}kg from Mixed)
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>

                                            <!-- Damaged Write-off (kg) -->
                                            <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                                                @if($gData['damaged_kg'] > 0)
                                                    <span class="font-bold text-rose-600">{{ number_format($gData['damaged_kg'], 1) }}</span>
                                                    <span class="text-[10px] text-rose-400">kg</span>
                                                    <span class="block text-[10px] text-rose-500 font-sans">₹{{ number_format($gData['damaged_total_cost'], 0) }}</span>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>

                                            <!-- Current Available Stock -->
                                            <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                                                @if($gData['available_kg'] > 0)
                                                    <span class="font-bold text-emerald-700">{{ number_format($gData['available_kg'], 1) }}</span>
                                                    <span class="text-[10px] text-emerald-500">kg</span>
                                                @else
                                                    <span class="text-slate-300">0.0</span>
                                                @endif
                                            </td>

                                            <!-- Purchase Cost per kg -->
                                            <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                                                @if($gData['weighted_purchase_cost'] > 0)
                                                    <span>₹{{ number_format($gData['weighted_purchase_cost'], 2) }}</span>
                                                @else
                                                    <span class="text-slate-400">—</span>
                                                @endif
                                            </td>

                                            <!-- Realized Selling Rate -->
                                            <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                                                @if($gData['weighted_selling_rate'] > 0)
                                                    <span class="font-bold text-slate-900">₹{{ number_format($gData['weighted_selling_rate'], 2) }}</span>
                                                    <span class="block text-[10px] text-slate-400 font-sans">
                                                        ({{ number_format($gData['invoiced_delivered_kg'], 1) }} kg billed)
                                                    </span>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>

                                            <!-- Realized Margin or Pending Cost -->
                                            <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                                                @if($gData['has_pending_cost_reconciliation'])
                                                    <span class="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200" title="Negative dispatches awaiting purchase cost reconciliation">
                                                        <i data-lucide="clock" class="h-3 w-3"></i> Pending Cost
                                                    </span>
                                                @elseif($gData['margin_percent'] !== null)
                                                    <span class="font-bold {{ $gData['margin_percent'] >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                                        {{ $gData['margin_percent'] }}%
                                                    </span>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- PAGINATION -->
            <div class="border-t border-slate-200 bg-slate-50 px-5 py-3">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    <!-- BATCH & MOVEMENT AUDIT DETAIL MODAL -->
    <div
        x-show="showModal"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm p-4 sm:p-6 md:p-10 flex items-center justify-center"
        style="display: none;"
    >
        <div
            @click.outside="closeModal()"
            class="relative w-full max-w-4xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white">
                        <i data-lucide="shield-check" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Batch Audit & Lineage Inspector
                        </h3>
                        <p class="text-xs text-slate-500">
                            Complete traceability across procurement, segregation, loadout, and shop invoices.
                        </p>
                    </div>
                </div>
                <button
                    @click="closeModal()"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-600 transition"
                >
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="max-h-[75vh] overflow-y-auto p-6 space-y-5">
                <!-- Loading State -->
                <div x-show="loadingDetail" class="py-12 text-center text-slate-400">
                    <i data-lucide="loader-2" class="h-8 w-8 animate-spin mx-auto text-emerald-600 mb-2"></i>
                    <p class="text-xs font-semibold">Tracing batch lineage and transactions...</p>
                </div>

                <!-- Error State -->
                <div x-show="errorDetail" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-700">
                    <span x-text="errorDetail"></span>
                </div>

                <!-- Content State -->
                <div x-show="!loadingDetail && activeBatch" class="space-y-6">
                    <!-- Top Batch Meta Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 rounded-xl border border-slate-200 bg-slate-50/75 p-4 text-xs">
                        <div>
                            <span class="text-slate-400 uppercase text-[10px] font-bold">Batch Reference</span>
                            <div class="font-bold text-slate-900 font-mono" x-text="activeBatch?.batch?.reference"></div>
                        </div>
                        <div>
                            <span class="text-slate-400 uppercase text-[10px] font-bold">Grade</span>
                            <div class="font-bold text-slate-900" x-text="activeBatch?.batch?.grade_label"></div>
                        </div>
                        <div>
                            <span class="text-slate-400 uppercase text-[10px] font-bold">Unit Purchase Cost</span>
                            <div class="font-bold text-slate-900 font-mono">
                                ₹<span x-text="activeBatch?.batch?.cost_per_kg"></span>/kg
                            </div>
                        </div>
                        <div>
                            <span class="text-slate-400 uppercase text-[10px] font-bold">Available Balance</span>
                            <div class="font-bold text-emerald-700 font-mono">
                                <span x-text="activeBatch?.batch?.available_balance"></span> kg
                            </div>
                        </div>
                    </div>

                    <!-- Pending Cost Alert -->
                    <template x-if="activeBatch?.batch?.is_pending_cost_reconciliation">
                        <div class="rounded-xl border border-amber-300 bg-amber-50 p-3.5 flex items-center gap-2.5 text-xs text-amber-900">
                            <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600 shrink-0"></i>
                            <div>
                                <strong>Pending Cost Reconciliation:</strong> This batch originated from a negative stock dispatch. Cost per unit will remain pending until matched with a vendor bill.
                            </div>
                        </div>
                    </template>

                    <!-- Source Procurement -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Source Procurement / GRN</h4>
                        <div class="rounded-xl border border-slate-200 p-3.5 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs bg-white">
                            <div>
                                <span class="text-slate-400">GRN Number:</span>
                                <div class="font-semibold text-slate-800 font-mono" x-text="activeBatch?.procurement?.grn_number || 'N/A'"></div>
                            </div>
                            <div>
                                <span class="text-slate-400">Vendor / Supplier:</span>
                                <div class="font-semibold text-slate-800" x-text="activeBatch?.procurement?.supplier_name || 'N/A'"></div>
                            </div>
                            <div>
                                <span class="text-slate-400">Purchaser:</span>
                                <div class="font-semibold text-slate-800" x-text="activeBatch?.procurement?.purchaser_name || 'N/A'"></div>
                            </div>
                            <div>
                                <span class="text-slate-400">Bill Number:</span>
                                <div class="font-semibold text-slate-800 font-mono" x-text="activeBatch?.procurement?.bill_number || 'Pending'"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Movements Table -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Stock Movements on this Batch</h4>
                        <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                    <tr>
                                        <th class="py-2 px-3">Date</th>
                                        <th class="py-2 px-3">Type</th>
                                        <th class="py-2 px-3">Qty</th>
                                        <th class="py-2 px-3">Source Grade</th>
                                        <th class="py-2 px-3">Commercial Loadout</th>
                                        <th class="py-2 px-3">Notes / Allocation</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                    <template x-for="m in activeBatch?.movements" :key="m.id">
                                        <tr class="hover:bg-slate-50/75">
                                            <td class="py-2 px-3 whitespace-nowrap text-slate-500 font-mono" x-text="m.created_at"></td>
                                            <td class="py-2 px-3 whitespace-nowrap">
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase" x-text="m.type_label"></span>
                                            </td>
                                            <td class="py-2 px-3 font-mono font-bold whitespace-nowrap" x-text="m.quantity + ' kg'"></td>
                                            <td class="py-2 px-3 whitespace-nowrap">
                                                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold font-mono" x-text="'Grade ' + m.physical_grade"></span>
                                            </td>
                                            <td class="py-2 px-3 whitespace-nowrap">
                                                <template x-if="m.is_mixed_loaded_as_grade">
                                                    <span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-800 border border-blue-200">
                                                        Loaded as Grade <span x-text="m.commercial_grade"></span>
                                                    </span>
                                                </template>
                                                <template x-if="!m.is_mixed_loaded_as_grade && m.commercial_grade">
                                                    <span class="text-slate-600 font-mono text-[11px]" x-text="'Grade ' + m.commercial_grade"></span>
                                                </template>
                                                <template x-if="!m.commercial_grade">
                                                    <span class="text-slate-300">—</span>
                                                </template>
                                            </td>
                                            <td class="py-2 px-3 text-slate-500 text-[11px]">
                                                <span x-text="m.notes"></span>
                                                <template x-if="m.shop">
                                                    <span class="block text-emerald-700 font-semibold" x-text="'→ ' + m.shop.name"></span>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="!activeBatch?.movements || activeBatch?.movements.length === 0">
                                        <tr>
                                            <td colspan="6" class="p-4 text-center text-slate-400">No stock movements recorded.</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Wastage / Damage Entries -->
                    <template x-if="activeBatch?.wastages && activeBatch?.wastages.length > 0">
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-rose-600">Damage & Wastage Write-offs</h4>
                            <div class="rounded-xl border border-rose-200 overflow-hidden bg-rose-50/20">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-rose-50 text-[10px] font-bold uppercase tracking-wider text-rose-700 border-b border-rose-200">
                                        <tr>
                                            <th class="py-2 px-3">Date</th>
                                            <th class="py-2 px-3">Grade</th>
                                            <th class="py-2 px-3">Quantity</th>
                                            <th class="py-2 px-3">Loss Cost</th>
                                            <th class="py-2 px-3">Reason</th>
                                            <th class="py-2 px-3">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-rose-100 font-medium text-slate-700">
                                        <template x-for="w in activeBatch?.wastages" :key="w.id">
                                            <tr>
                                                <td class="py-2 px-3 font-mono" x-text="w.date"></td>
                                                <td class="py-2 px-3 font-bold" x-text="'Grade ' + w.grade"></td>
                                                <td class="py-2 px-3 font-mono font-bold text-rose-600" x-text="w.quantity + ' kg'"></td>
                                                <td class="py-2 px-3 font-mono" x-text="'₹' + w.total_cost"></td>
                                                <td class="py-2 px-3" x-text="w.reason"></td>
                                                <td class="py-2 px-3 text-slate-500" x-text="w.notes"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="border-t border-slate-200 bg-slate-50 px-6 py-3 flex justify-end">
                <button
                    @click="closeModal()"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow hover:bg-slate-800 transition"
                >
                    Close Inspector
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
