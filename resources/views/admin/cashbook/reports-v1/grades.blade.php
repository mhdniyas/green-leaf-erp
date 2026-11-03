@extends('admin.cashbook.layouts.app')

@section('title', 'Daily Grade Summary - Reports V1')

@section('header_title')
    <i data-lucide="layers" class="h-5 w-5 text-emerald-600"></i> Daily Grade Summary
@endsection

@section('header_subtitle')
    Authoritative daily warehouse stock, dispatches, shop billings, damage quarantine, and returns.
@endsection

@php
    $selectedProductIdentifier = $filters['product'] ?? $filters['product_uuid'] ?? $filters['product_id'] ?? null;
    $selectedWarehouseId = $filters['warehouse_id'] ?? null;
    $reportDate = $reportDate ?? ($filters['date'] ?? ($filters['start_date'] ?? now('Asia/Kolkata')->toDateString()));
    $isToday = $isToday ?? ($reportDate === now('Asia/Kolkata')->toDateString());
    $currentGrade = $selectedGrade ?? ($filters['grade'] ?? 'A');
    $selectedPurchaserId = $filters['purchaser_id'] ?? null;
    $search = $search ?? ($filters['search'] ?? ($filters['search_product'] ?? ''));
    $startDate = $filters['start_date'] ?? $reportDate;
    $endDate = $filters['end_date'] ?? $reportDate;
    $formattedRange = $filters['formatted_range'] ?? (\Carbon\Carbon::parse($reportDate)->format('d M Y'));
    $tabMetrics = $tabMetrics ?? [
        'A' => ['available_formatted' => '0 KG', 'dispatched_formatted' => '0 KG'],
        'B' => ['available_formatted' => '0 KG', 'dispatched_formatted' => '0 KG'],
        'C' => ['available_formatted' => '0 KG', 'dispatched_formatted' => '0 KG'],
        'DAMAGE' => ['moved_today' => 0.0, 'quarantine' => 0.0],
    ];
    $gradeSummary = $gradeSummary ?? null;
    $productRows = $productRows ?? [];
    $dailyProducts = $dailyProducts ?? null;
    $damageData = $damageData ?? null;
    $shopReturns = $shopReturns ?? ['total_count' => 0, 'total_qty' => 0, 'unit_summary' => '0 KG', 'items' => collect()];
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

    <!-- SECTION 1: COMPACT HEADER & FILTERS -->
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.cashbook.reports-v1.grades') }}" id="dailyGradesFilterForm" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="grade" value="{{ $currentGrade }}">

            <!-- Date Picker (Today by default) -->
            <div class="flex items-center gap-2 min-w-[210px]">
                <label for="filter_date" class="text-xs font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                    Date:
                </label>
                <input
                    type="date"
                    id="filter_date"
                    name="date"
                    value="{{ $reportDate }}"
                    onchange="this.form.submit()"
                    class="w-full rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-mono font-bold text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm"
                >
            </div>

            <!-- Warehouse Filter (All Warehouses by default) -->
            <div class="flex items-center gap-2 min-w-[220px]">
                <label for="filter_warehouse" class="text-xs font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                    Warehouse:
                </label>
                <select
                    name="warehouse_id"
                    id="filter_warehouse"
                    onchange="this.form.submit()"
                    class="w-full rounded-xl border border-slate-300 py-1.5 pl-3 pr-8 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm"
                >
                    <option value="">All Warehouses</option>
                    @foreach($options['warehouses'] as $wh)
                        <option value="{{ $wh->id }}" @selected((int)$selectedWarehouseId === (int)$wh->id)>
                            {{ $wh->name }} ({{ $wh->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Product Search -->
            <div class="relative flex-1 min-w-[240px]">
                <i data-lucide="search" class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search product name or SKU..."
                    class="w-full rounded-xl border border-slate-300 py-1.5 pl-8 pr-8 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm"
                >
                @if(!empty($search))
                    <a
                        href="{{ route('admin.cashbook.reports-v1.grades', request()->except(['search', 'search_product', 'product', 'product_id', 'product_uuid', 'page'])) }}"
                        class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600"
                        title="Clear search"
                    >
                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    </a>
                @endif
            </div>

            <!-- Purchaser Filter (Optional) -->
            @if(!empty($options['purchasers']) && $options['purchasers']->isNotEmpty())
                <div class="flex items-center gap-2 min-w-[200px]">
                    <label for="filter_purchaser" class="text-xs font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                        Purchaser:
                    </label>
                    <select
                        name="purchaser_id"
                        id="filter_purchaser"
                        onchange="this.form.submit()"
                        class="w-full rounded-xl border border-slate-300 py-1.5 pl-3 pr-8 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-sm"
                    >
                        <option value="">All Purchasers</option>
                        @foreach($options['purchasers'] as $p)
                            <option value="{{ $p->id }}" @selected((int)$selectedPurchaserId === (int)$p->id)>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <!-- Form Actions & Range Badge -->
            <div class="flex items-center gap-2 ml-auto">
                <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-mono font-bold text-emerald-800 border border-emerald-200">
                    {{ $formattedRange }}
                </span>
                <button
                    type="submit"
                    class="rounded-xl bg-slate-900 px-4 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition"
                >
                    Apply
                </button>
                <a
                    href="{{ route('admin.cashbook.reports-v1.grades') }}"
                    class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition"
                >
                    Reset
                </a>
            </div>
        </form>

        <!-- Multiple search matches selection -->
        @if(!empty($searchResults) && $searchResults->isNotEmpty() && ! $product)
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50/50 p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-800">
                        Select Matching Product ({{ $searchResults->count() }} found)
                    </span>
                    <span class="text-[11px] text-amber-600 font-medium">Click one item to inspect single-product details</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                    @foreach($searchResults as $match)
                        <a
                            href="{{ route('admin.cashbook.reports-v1.grades', array_merge(request()->except(['search', 'search_product', 'product_id', 'product_uuid']), ['product' => $match->public_uuid ?: $match->id])) }}"
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

    <!-- PENDING RECONCILIATION NOTICE IF APPLICABLE -->
    @if(!empty($summary->has_pending_cost_reconciliation))
        <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-2.5 shadow-sm flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="rounded-lg bg-amber-500/15 p-1.5 text-amber-700">
                    <i data-lucide="alert-triangle" class="h-4 w-4"></i>
                </span>
                <div>
                    <span class="text-xs font-black text-amber-950 uppercase tracking-wide">Pending Cost Reconciliation</span>
                    <span class="text-xs text-amber-800 ml-2">
                        {{ $summary->pending_reconciliation_count }} loadout movement(s) consumed negative/unreconciled stock.
                    </span>
                </div>
            </div>
            <span class="text-xs font-bold text-amber-900 font-mono bg-white/80 px-2.5 py-1 rounded-md border border-amber-200">
                {{ number_format($summary->pending_reconciliation_qty ?? 0, 1) }} kg pending
            </span>
        </div>
    @endif

    <!-- SECTION 2: COMPACT HORIZONTAL GRADE SELECTOR -->
    @php
        $isActiveA = ($currentGrade === 'A');
        $isActiveB = ($currentGrade === 'B');
        $isActiveC = ($currentGrade === 'C');
        $isActiveDmg = ($currentGrade === 'DAMAGE');
        $tabB = $tabMetrics['B'] ?? [];
    @endphp
    <div class="bg-slate-100/80 p-1.5 rounded-2xl border border-slate-200 flex flex-wrap sm:flex-nowrap items-center gap-1.5 shadow-inner">
        <!-- Grade A -->
        <a
            href="{{ route('admin.cashbook.reports-v1.grades', array_merge(request()->except(['page']), ['grade' => 'A'])) }}"
            class="flex-1 min-w-[130px] flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all {{ $isActiveA ? 'bg-emerald-600 text-white shadow-sm ring-1 ring-emerald-600' : 'bg-white text-slate-700 hover:text-slate-900 hover:bg-slate-50 border border-slate-200/60 shadow-xs' }}"
        >
            <span class="inline-flex h-5 w-5 items-center justify-center rounded text-xs font-black {{ $isActiveA ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">A</span>
            <span>Grade A</span>
        </a>

        <!-- Grade B -->
        <a
            href="{{ route('admin.cashbook.reports-v1.grades', array_merge(request()->except(['page']), ['grade' => 'B'])) }}"
            class="flex-1 min-w-[130px] flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all {{ $isActiveB ? 'bg-blue-600 text-white shadow-sm ring-1 ring-blue-600' : 'bg-white text-slate-700 hover:text-slate-900 hover:bg-slate-50 border border-slate-200/60 shadow-xs' }}"
        >
            <span class="inline-flex h-5 w-5 items-center justify-center rounded text-xs font-black {{ $isActiveB ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">B</span>
            <span>Grade B</span>
            @if(!empty($tabB['from_mixed']) && $tabB['from_mixed'] > 0.001)
                <span class="text-[10px] {{ $isActiveB ? 'text-blue-100' : 'text-blue-600' }} font-medium">({{ number_format($tabB['from_mixed'], 1) }} kg from Mixed)</span>
            @endif
        </a>

        <!-- Grade C -->
        <a
            href="{{ route('admin.cashbook.reports-v1.grades', array_merge(request()->except(['page']), ['grade' => 'C'])) }}"
            class="flex-1 min-w-[130px] flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all {{ $isActiveC ? 'bg-amber-600 text-white shadow-sm ring-1 ring-amber-600' : 'bg-white text-slate-700 hover:text-slate-900 hover:bg-slate-50 border border-slate-200/60 shadow-xs' }}"
        >
            <span class="inline-flex h-5 w-5 items-center justify-center rounded text-xs font-black {{ $isActiveC ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">C</span>
            <span>Grade C</span>
        </a>

        <!-- Damage -->
        <a
            href="{{ route('admin.cashbook.reports-v1.grades', array_merge(request()->except(['page']), ['grade' => 'damage'])) }}"
            class="flex-1 min-w-[130px] flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all {{ $isActiveDmg ? 'bg-rose-600 text-white shadow-sm ring-1 ring-rose-600' : 'bg-white text-slate-700 hover:text-slate-900 hover:bg-slate-50 border border-slate-200/60 shadow-xs' }}"
        >
            <span class="inline-flex h-5 w-5 items-center justify-center rounded text-xs font-black {{ $isActiveDmg ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">
                <i data-lucide="alert-octagon" class="w-3.5 h-3.5"></i>
            </span>
            <span>Damage</span>
        </a>
    </div>

    <!-- SECTION 3: GRADE SUMMARY (For selected Grade A, B, or C) -->
    @if(in_array($currentGrade, ['A', 'B', 'C'], true) && $gradeSummary)
        @php
            $renderUnitTable = function(array $units) {
                $filtered = [];
                foreach ($units as $u => $q) {
                    if ((float) $q > 0.0001) {
                        $filtered[strtoupper((string) $u)] = (float) $q;
                    }
                }
                $primary = array_slice($filtered, 0, 3, true);
                $extra = array_slice($filtered, 3, null, true);
                return [$filtered, $primary, $extra];
            };
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Available Stock -->
            @php
                [$availUnits, $availPrimary, $availExtra] = $renderUnitTable($gradeSummary['available_units'] ?? []);
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col justify-between" x-data="{ expanded: false }">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-3 pb-2 border-b border-slate-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">
                            {{ $isToday ? 'Available Stock' : 'Current Available Stock' }}
                        </span>
                        <span class="rounded-lg bg-emerald-50 p-1.5 text-emerald-600">
                            <i data-lucide="package" class="h-4 w-4"></i>
                        </span>
                    </div>

                    @if(empty($availUnits))
                        <div class="py-3 text-center text-slate-400 font-mono text-sm font-bold">
                            0 KG
                        </div>
                    @else
                        <div class="space-y-1.5">
                            <table class="w-full text-xs">
                                <tbody>
                                    @foreach($availPrimary as $u => $qty)
                                        <tr class="border-b border-slate-50 last:border-none">
                                            <td class="py-1 font-bold text-slate-500 uppercase tracking-wider text-[11px]">{{ $u }}</td>
                                            <td class="py-1 text-right font-mono font-black text-slate-900 text-sm">{{ number_format($qty, 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @if(!empty($availExtra))
                                <div x-show="expanded" x-collapse>
                                    <table class="w-full text-xs border-t border-slate-100 pt-1">
                                        <tbody>
                                            @foreach($availExtra as $u => $qty)
                                                <tr class="border-b border-slate-50 last:border-none">
                                                    <td class="py-1 font-bold text-slate-500 uppercase tracking-wider text-[11px]">{{ $u }}</td>
                                                    <td class="py-1 text-right font-mono font-black text-slate-800 text-sm">{{ number_format($qty, 1) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button
                                    type="button"
                                    @click="expanded = !expanded"
                                    class="text-[11px] font-bold text-emerald-700 hover:text-emerald-800 flex items-center gap-1 pt-1 transition"
                                >
                                    <span x-text="expanded ? 'Show fewer units' : '+{{ count($availExtra) }} more units'">+{{ count($availExtra) }} more units</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" :class="expanded ? 'rotate-180' : ''"></i>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-3 pt-2 border-t border-slate-100 text-[11px] text-slate-400 flex items-center gap-1.5">
                    @if($isToday)
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        <span>Current warehouse stock</span>
                    @else
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        <span>Real-time ledger balance</span>
                    @endif
                </div>
            </div>

            <!-- 2. Dispatched Today -->
            @php
                [$dispUnits, $dispPrimary, $dispExtra] = $renderUnitTable($gradeSummary['dispatched_units'] ?? []);
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col justify-between" x-data="{ expanded: false }">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-3 pb-2 border-b border-slate-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Dispatched Today</span>
                        <span class="rounded-lg bg-blue-50 p-1.5 text-blue-600">
                            <i data-lucide="truck" class="h-4 w-4"></i>
                        </span>
                    </div>

                    @if(empty($dispUnits))
                        <div class="py-3 text-center text-slate-400 font-mono text-sm font-bold">
                            0 KG
                        </div>
                    @else
                        <div class="space-y-1.5">
                            <table class="w-full text-xs">
                                <tbody>
                                    @foreach($dispPrimary as $u => $qty)
                                        <tr class="border-b border-slate-50 last:border-none">
                                            <td class="py-1 font-bold text-slate-500 uppercase tracking-wider text-[11px]">{{ $u }}</td>
                                            <td class="py-1 text-right font-mono font-black text-slate-900 text-sm">{{ number_format($qty, 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @if(!empty($dispExtra))
                                <div x-show="expanded" x-collapse>
                                    <table class="w-full text-xs border-t border-slate-100 pt-1">
                                        <tbody>
                                            @foreach($dispExtra as $u => $qty)
                                                <tr class="border-b border-slate-50 last:border-none">
                                                    <td class="py-1 font-bold text-slate-500 uppercase tracking-wider text-[11px]">{{ $u }}</td>
                                                    <td class="py-1 text-right font-mono font-black text-slate-800 text-sm">{{ number_format($qty, 1) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button
                                    type="button"
                                    @click="expanded = !expanded"
                                    class="text-[11px] font-bold text-blue-700 hover:text-blue-800 flex items-center gap-1 pt-1 transition"
                                >
                                    <span x-text="expanded ? 'Show fewer units' : '+{{ count($dispExtra) }} more units'">+{{ count($dispExtra) }} more units</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" :class="expanded ? 'rotate-180' : ''"></i>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-3 pt-2 border-t border-slate-100 text-[11px] text-slate-400">
                    Physically dispatched on {{ \Carbon\Carbon::parse($reportDate)->format('d M Y') }}
                </div>
            </div>

            <!-- 3. Billed Today -->
            @php
                [$billUnits, $billPrimary, $billExtra] = $renderUnitTable($gradeSummary['billed_units'] ?? []);
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col justify-between" x-data="{ expanded: false }">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-3 pb-2 border-b border-slate-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Billed Today</span>
                        <span class="rounded-lg bg-indigo-50 p-1.5 text-indigo-600">
                            <i data-lucide="receipt" class="h-4 w-4"></i>
                        </span>
                    </div>

                    @if(empty($billUnits))
                        <div class="py-3 text-center text-slate-400 font-mono text-sm font-bold">
                            0 KG
                        </div>
                    @else
                        <div class="space-y-1.5">
                            <table class="w-full text-xs">
                                <tbody>
                                    @foreach($billPrimary as $u => $qty)
                                        <tr class="border-b border-slate-50 last:border-none">
                                            <td class="py-1 font-bold text-slate-500 uppercase tracking-wider text-[11px]">{{ $u }}</td>
                                            <td class="py-1 text-right font-mono font-black text-slate-900 text-sm">{{ number_format($qty, 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @if(!empty($billExtra))
                                <div x-show="expanded" x-collapse>
                                    <table class="w-full text-xs border-t border-slate-100 pt-1">
                                        <tbody>
                                            @foreach($billExtra as $u => $qty)
                                                <tr class="border-b border-slate-50 last:border-none">
                                                    <td class="py-1 font-bold text-slate-500 uppercase tracking-wider text-[11px]">{{ $u }}</td>
                                                    <td class="py-1 text-right font-mono font-black text-slate-800 text-sm">{{ number_format($qty, 1) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button
                                    type="button"
                                    @click="expanded = !expanded"
                                    class="text-[11px] font-bold text-indigo-700 hover:text-indigo-800 flex items-center gap-1 pt-1 transition"
                                >
                                    <span x-text="expanded ? 'Show fewer units' : '+{{ count($billExtra) }} more units'">+{{ count($billExtra) }} more units</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" :class="expanded ? 'rotate-180' : ''"></i>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-3 pt-2 border-t border-slate-100 text-[11px] text-slate-400 flex items-center justify-between">
                    <span>Finalized shop invoices</span>
                    @if(!empty($summary->total_invoiced_sales) && $summary->total_invoiced_sales > 0)
                        <span class="font-bold text-indigo-700 font-mono">₹{{ number_format($summary->total_invoiced_sales, 0) }}</span>
                    @elseif(!empty($gradeSummary['billed_val']) && $gradeSummary['billed_val'] > 0)
                        <span class="font-bold text-indigo-700 font-mono">₹{{ number_format($gradeSummary['billed_val'], 0) }}</span>
                    @endif
                </div>
            </div>

            <!-- 4. Moved to Damage -->
            @php
                [$dmgUnits, $dmgPrimary, $dmgExtra] = $renderUnitTable($gradeSummary['damage_units'] ?? []);
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col justify-between" x-data="{ expanded: false }">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-3 pb-2 border-b border-slate-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Moved to Damage</span>
                        <span class="rounded-lg bg-rose-50 p-1.5 text-rose-600">
                            <i data-lucide="alert-triangle" class="h-4 w-4"></i>
                        </span>
                    </div>

                    @if(empty($dmgUnits))
                        <div class="py-3 text-center text-slate-400 font-mono text-sm font-bold">
                            0 KG
                        </div>
                    @else
                        <div class="space-y-1.5">
                            <table class="w-full text-xs">
                                <tbody>
                                    @foreach($dmgPrimary as $u => $qty)
                                        <tr class="border-b border-slate-50 last:border-none">
                                            <td class="py-1 font-bold text-slate-500 uppercase tracking-wider text-[11px]">{{ $u }}</td>
                                            <td class="py-1 text-right font-mono font-black text-slate-900 text-sm">{{ number_format($qty, 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @if(!empty($dmgExtra))
                                <div x-show="expanded" x-collapse>
                                    <table class="w-full text-xs border-t border-slate-100 pt-1">
                                        <tbody>
                                            @foreach($dmgExtra as $u => $qty)
                                                <tr class="border-b border-slate-50 last:border-none">
                                                    <td class="py-1 font-bold text-slate-500 uppercase tracking-wider text-[11px]">{{ $u }}</td>
                                                    <td class="py-1 text-right font-mono font-black text-slate-800 text-sm">{{ number_format($qty, 1) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button
                                    type="button"
                                    @click="expanded = !expanded"
                                    class="text-[11px] font-bold text-rose-700 hover:text-rose-800 flex items-center gap-1 pt-1 transition"
                                >
                                    <span x-text="expanded ? 'Show fewer units' : '+{{ count($dmgExtra) }} more units'">+{{ count($dmgExtra) }} more units</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" :class="expanded ? 'rotate-180' : ''"></i>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-3 pt-2 border-t border-slate-100 text-[11px] text-slate-400 flex items-center justify-between">
                    <span>Damaged on {{ \Carbon\Carbon::parse($reportDate)->format('d M Y') }}</span>
                    @if(!empty($summary->total_damaged_cost) && $summary->total_damaged_cost > 0)
                        <span class="font-bold text-rose-600 font-mono">₹{{ number_format($summary->total_damaged_cost, 0) }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- SECTION 4: COMPACT PRODUCT TABLE -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <h3 class="text-sm font-black text-slate-900 tracking-tight">Grade {{ $currentGrade }} Products</h3>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600 font-mono">
                        {{ $dailyProducts ? $dailyProducts->total() : count($productRows) }} Products
                    </span>
                </div>
                <div class="text-xs text-slate-400">
                    Showing 5 products per page
                </div>
            </div>

            @if(!empty($productRows))
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Product</th>
                                <th class="py-3 px-4">Unit</th>
                                <th class="py-3 px-4 text-right">Available</th>
                                <th class="py-3 px-4 text-right">Dispatched</th>
                                <th class="py-3 px-4 text-right">Billed</th>
                                <th class="py-3 px-4 text-right">Damage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @foreach($productRows as $row)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900">{{ $row['name'] }}</div>
                                        @if($row['sku'])
                                            <div class="text-[11px] text-slate-400 font-mono">{{ $row['sku'] }}</div>
                                        @endif
                                        @if(!empty($productSummaries[$row['id']]))
                                            @php $pSum = $productSummaries[$row['id']]; @endphp
                                            <div class="mt-1 flex items-center gap-2 text-[10px] text-slate-400 font-mono">
                                                @if($pSum['weighted_purchase_cost'] > 0)
                                                    <span>Cost: ₹{{ number_format($pSum['weighted_purchase_cost'], 2) }}/{{ strtolower($row['unit']) }}</span>
                                                @endif
                                                @if(!empty($pSum['weighted_selling_rate']) && $pSum['weighted_selling_rate'] > 0)
                                                    <span>• Rate: ₹{{ number_format($pSum['weighted_selling_rate'], 2) }}/{{ strtolower($row['unit']) }}</span>
                                                @elseif(!empty($row['billed_rate']) && $row['billed_rate'] > 0)
                                                    <span>• Rate: ₹{{ number_format($row['billed_rate'], 2) }}/{{ strtolower($row['unit']) }}</span>
                                                @endif
                                                @if(!empty($pSum['grades']['B']['commercial_loaded_kg']) && !empty($pSum['grades']['B']['physical_loaded_kg']) && $pSum['grades']['B']['commercial_loaded_kg'] > $pSum['grades']['B']['physical_loaded_kg'])
                                                    <span class="text-blue-600 font-medium">(including from Mixed)</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 font-mono font-bold text-slate-600">
                                        {{ $row['unit'] }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900">
                                        {{ number_format($row['available'], 1) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-blue-700">
                                        {{ number_format($row['dispatched'], 1) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-indigo-700">
                                        {{ number_format($row['billed'], 1) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-rose-700">
                                        {{ number_format($row['damage'], 1) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Server-Side Pagination -->
                @if($dailyProducts && $dailyProducts->hasPages())
                    <div class="border-t border-slate-100 px-5 py-3">
                        {{ $dailyProducts->links() }}
                    </div>
                @endif
            @else
                <!-- Empty State -->
                <div class="p-12 text-center">
                    <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                        <i data-lucide="package-x" class="h-6 w-6"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-800">No Grade {{ $currentGrade }} Products Found</h4>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        No products had available stock or activity for Grade {{ $currentGrade }} on this reporting date.
                    </p>
                </div>
            @endif
        </div>
    @endif

    <!-- SECTION 5: DAMAGE TAB VIEW (When DAMAGE is active) -->
    @if($currentGrade === 'DAMAGE' && $damageData)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-5 shadow-sm">
                <div class="flex items-center justify-between text-rose-800 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Moved to Damage Today</span>
                    <span class="rounded-xl bg-rose-200/60 p-2 text-rose-700">
                        <i data-lucide="alert-octagon" class="h-4 w-4"></i>
                    </span>
                </div>
                <div class="text-2xl font-black tracking-tight text-rose-950 font-mono">
                    {{ $damageData['moved_formatted'] }}
                </div>
                <div class="mt-1.5 text-xs text-rose-700">
                    Total damage write-offs on {{ \Carbon\Carbon::parse($reportDate)->format('d M Y') }}
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between text-slate-500 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Current Damaged Stock Remaining</span>
                    <span class="rounded-xl bg-slate-100 p-2 text-slate-600">
                        <i data-lucide="archive" class="h-4 w-4"></i>
                    </span>
                </div>
                <div class="text-2xl font-black tracking-tight text-slate-900 font-mono">
                    {{ number_format($damageData['quarantine_remaining'], 1) }} KG
                </div>
                <div class="mt-1.5 text-xs text-slate-500">
                    Damaged stock held in quarantine batches
                </div>
            </div>
        </div>

        <!-- Damage Table -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <h3 class="text-sm font-black text-slate-900 tracking-tight">Damage Logs & Quarantine Transfers</h3>
                    <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-800 font-mono">
                        {{ $damageData['items']->total() }} Entries
                    </span>
                </div>
                <div class="text-xs text-slate-400">
                    Showing 5 records per page
                </div>
            </div>

            @if($damageData['items']->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Product</th>
                                <th class="py-3 px-4">Original Grade</th>
                                <th class="py-3 px-4 text-right">Quantity</th>
                                <th class="py-3 px-4">Unit</th>
                                <th class="py-3 px-4">Reason</th>
                                <th class="py-3 px-4">Updated By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @foreach($damageData['items'] as $item)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900">{{ $item->product_name }}</div>
                                        @if($item->product_sku)
                                            <div class="text-[11px] text-slate-400 font-mono">{{ $item->product_sku }}</div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 font-bold text-[11px] text-slate-700">
                                            Grade {{ $item->original_grade }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-rose-700">
                                        {{ number_format($item->quantity, 1) }}
                                    </td>
                                    <td class="py-3.5 px-4 font-mono font-bold text-slate-600">
                                        {{ $item->unit }}
                                    </td>
                                    <td class="py-3.5 px-4 max-w-xs truncate" title="{{ $item->reason }}">
                                        <span class="text-slate-800">{{ $item->reason }}</span>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $item->reference }} • {{ $item->status }}</div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold text-slate-800">{{ $item->updated_by }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $item->time }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($damageData['items']->hasPages())
                    <div class="border-t border-slate-100 px-5 py-3">
                        {{ $damageData['items']->links() }}
                    </div>
                @endif
            @else
                <div class="p-12 text-center">
                    <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-500 mb-3">
                        <i data-lucide="check-circle" class="h-6 w-6"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-800">No Damage Recorded Today</h4>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        No products were moved to damage or written off on {{ \Carbon\Carbon::parse($reportDate)->format('d M Y') }}.
                    </p>
                </div>
            @endif
        </div>
    @endif

    <!-- SECTION 6: SEPARATE SHOP RETURNS (Independent of Active Tab) -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden" x-data="{ expandedId: null }">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <i data-lucide="rotate-ccw" class="h-4 w-4 text-emerald-600"></i>
                    <h3 class="text-sm font-black text-slate-900 tracking-tight">SHOP RETURNS</h3>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600 font-mono">
                    {{ $shopReturns['total_count'] }} Returns Reported
                </span>
                @if($shopReturns['total_count'] > 0)
                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-800 border border-emerald-200">
                        {{ $shopReturns['unit_summary'] }}
                    </span>
                @endif
            </div>
            <div class="text-xs text-slate-400">
                Delivery review returns for {{ \Carbon\Carbon::parse($reportDate)->format('d M Y') }}
            </div>
        </div>

        @if(!empty($shopReturns['items']) && $shopReturns['items']->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4">Shop</th>
                            <th class="py-3 px-4">Product</th>
                            <th class="py-3 px-4">Grade</th>
                            <th class="py-3 px-4 text-right">Qty</th>
                            <th class="py-3 px-4">Unit</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @foreach($shopReturns['items'] as $ret)
                            <tr class="hover:bg-slate-50/80 transition cursor-pointer" @click="expandedId = (expandedId === {{ $ret->id }} ? null : {{ $ret->id }})">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900">{{ $ret->shop_name }}</div>
                                    @if($ret->shop_code)
                                        <div class="text-[11px] text-slate-400 font-mono">{{ $ret->shop_code }}</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-slate-800">{{ $ret->product_name }}</div>
                                    @if($ret->product_sku)
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $ret->product_sku }}</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 font-bold text-[11px] text-slate-700">
                                        Grade {{ $ret->grade }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900">
                                    {{ number_format($ret->quantity, 1) }}
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-600">
                                    {{ $ret->unit }}
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($ret->status === 'Pending')
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-800 border border-amber-200">
                                            Pending
                                        </span>
                                    @elseif($ret->status === 'Restocked')
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-800 border border-emerald-200">
                                            Restocked
                                        </span>
                                    @elseif($ret->status === 'Moved to Damage')
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-bold text-rose-800 border border-rose-200">
                                            Moved to Damage
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-800 border border-blue-200">
                                            Received
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right text-slate-400">
                                    <i data-lucide="chevron-down" class="h-4 w-4 inline transition-transform" :class="{ 'rotate-180': expandedId === {{ $ret->id }} }"></i>
                                </td>
                            </tr>

                            <!-- Expandable Details Row -->
                            <tr x-show="expandedId === {{ $ret->id }}" x-cloak class="bg-slate-50/60">
                                <td colspan="7" class="px-5 py-3.5 text-xs text-slate-600 border-t border-dashed border-slate-200">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                                        <div>
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Return Reason</span>
                                            <span class="font-semibold text-slate-800">{{ $ret->reason }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Returned Date</span>
                                            <span class="font-mono text-slate-800">{{ $ret->returned_date }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Receiving Warehouse</span>
                                            <span class="font-semibold text-slate-800">{{ $ret->warehouse_name }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Responsible User</span>
                                            <span class="font-semibold text-slate-800">{{ $ret->responsible_user }}</span>
                                            @if($ret->order_number)
                                                <span class="text-[10px] font-mono text-slate-400">({{ $ret->order_number }})</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <!-- Empty State -->
            <div class="p-12 text-center">
                <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                    <i data-lucide="rotate-ccw" class="h-6 w-6"></i>
                </div>
                <h4 class="text-sm font-bold text-slate-800">No Shop Returns Recorded</h4>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    No returned products or delivery shortages were recorded on {{ \Carbon\Carbon::parse($reportDate)->format('d M Y') }}.
                </p>
            </div>
        @endif
    </div>

    <!-- BATCH AUDIT MODAL (Maintained for deep lineage traceability) -->
    <div
        x-show="showModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div
            class="relative w-full max-w-3xl rounded-3xl bg-white shadow-2xl border border-slate-200 overflow-hidden"
            @click.away="closeModal()"
        >
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
                <div class="flex items-center gap-2">
                    <i data-lucide="file-text" class="h-5 w-5 text-emerald-600"></i>
                    <h3 class="text-base font-black text-slate-900 tracking-tight">Batch Audit Lineage</h3>
                </div>
                <button @click="closeModal()" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <template x-if="loadingDetail">
                    <div class="p-12 text-center text-slate-400 text-xs">
                        Loading batch details...
                    </div>
                </template>

                <template x-if="errorDetail">
                    <div class="p-4 rounded-xl bg-rose-50 text-rose-800 text-xs font-semibold" x-text="errorDetail"></div>
                </template>

                <template x-if="activeBatch && !loadingDetail">
                    <div class="space-y-4 text-xs">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400">Reference</span>
                                <div class="font-mono font-bold text-slate-900" x-text="activeBatch.batch.reference"></div>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400">Product</span>
                                <div class="font-bold text-slate-900" x-text="activeBatch.batch.product_name"></div>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400">Grade</span>
                                <div class="font-bold text-slate-900" x-text="activeBatch.batch.grade_label"></div>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400">Available</span>
                                <div class="font-mono font-bold text-emerald-700" x-text="activeBatch.batch.available_balance + ' ' + activeBatch.batch.product_unit"></div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <h4 class="font-bold uppercase tracking-wider text-[11px] text-slate-500">Stock Movements</h4>
                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-400">
                                        <tr>
                                            <th class="p-2">Date</th>
                                            <th class="p-2">Type</th>
                                            <th class="p-2 text-right">Quantity</th>
                                            <th class="p-2">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="m in activeBatch.movements" :key="m.id">
                                            <tr>
                                                <td class="p-2 font-mono text-[11px]" x-text="m.created_at"></td>
                                                <td class="p-2 font-semibold" x-text="m.type_label"></td>
                                                <td class="p-2 text-right font-mono font-bold" x-text="m.quantity"></td>
                                                <td class="p-2 text-slate-500 truncate max-w-xs" x-text="m.notes"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection
