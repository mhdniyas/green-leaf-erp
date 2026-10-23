@extends('admin.cashbook.layouts.app')

@section('title', 'Item Purchase Report - Reports V1')

@section('header_title')
    <i data-lucide="package-search" class="h-5 w-5 text-emerald-600"></i> Item Purchase Report
@endsection

@section('header_subtitle')
    Detailed purchasing history, quantities, grades, cash/credit classification, rates, and vendor credit status.
@endsection

@php
    $selectedProductIdentifier = $filters['product'] ?? $filters['product_uuid'] ?? $filters['product_id'] ?? null;
    $selectedPayment = $filters['payment'] ?? 'all';
    $selectedGrade = $filters['grade'] ?? 'all';
    $selectedPurchaserId = $filters['purchaser_id'] ?? null;
    $selectedVendorId = $filters['vendor_id'] ?? null;
    $currentPeriod = $filters['period'] ?? 'this_month';
    $startDate = $filters['start_date'] ?? '';
    $endDate = $filters['end_date'] ?? '';
    $formattedRange = $filters['formatted_range'] ?? '';
    $selectedVendor = $selectedVendorId ? collect($options['vendors'] ?? [])->firstWhere('id', (int)$selectedVendorId) : null;
    $selectedPurchaser = $selectedPurchaserId ? collect($options['purchasers'] ?? [])->firstWhere('id', (int)$selectedPurchaserId) : null;
@endphp

@section('content')
<div
    class="mx-auto max-w-[96rem] space-y-6"
    x-data="{
        showModal: false,
        activeRow: null,
        openDrilldown(row) {
            this.activeRow = row;
            this.showModal = true;
        },
        closeDrilldown() {
            this.showModal = false;
            this.activeRow = null;
        }
    }"
    @keydown.escape.window="closeDrilldown()"
>

    <!-- TOP BAR: ITEM SEARCH & SELECTOR -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('admin.cashbook.reports-v1.item-purchase') }}" class="space-y-4">
            <input type="hidden" name="period" value="{{ $currentPeriod }}">
            @if($currentPeriod === 'custom')
                <input type="hidden" name="start_date" value="{{ $startDate }}">
                <input type="hidden" name="end_date" value="{{ $endDate }}">
            @endif
            @if($selectedGrade !== 'all')
                <input type="hidden" name="grade" value="{{ $selectedGrade }}">
            @endif
            @if($selectedPayment !== 'all')
                <input type="hidden" name="payment" value="{{ $selectedPayment }}">
            @endif
            @if($selectedPurchaserId)
                <input type="hidden" name="purchaser_id" value="{{ $selectedPurchaserId }}">
            @endif
            @if($selectedVendorId)
                <input type="hidden" name="vendor_id" value="{{ $selectedVendorId }}">
            @endif

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex-1">
                    <label for="search_product" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                        Search & Select Product
                    </label>
                    <div class="relative flex items-center">
                        <i data-lucide="search" class="absolute left-3.5 h-4 w-4 text-slate-400"></i>
                        <input
                            type="text"
                            id="search_product"
                            name="search_product"
                            value="{{ request('search_product') }}"
                            placeholder="Type product name or SKU (e.g. Tomato, Onion, Potato)..."
                            class="w-full rounded-xl border border-slate-300 py-2.5 pl-10 pr-28 text-sm font-medium text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            autocomplete="off"
                        >
                        <button
                            type="submit"
                            class="absolute right-1.5 rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-bold text-white shadow hover:bg-emerald-700 transition"
                        >
                            Search
                        </button>
                    </div>
                </div>

                @if($product)
                    <div class="flex items-center gap-2 self-start lg:self-end pt-1">
                        <a
                            href="{{ route('admin.cashbook.reports-v1.item-purchase', request()->except(['product', 'product_uuid', 'product_id', 'search_product'])) }}"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition"
                        >
                            <i data-lucide="x" class="h-3.5 w-3.5 text-slate-400"></i> Clear Selection
                        </a>
                    </div>
                @endif
            </div>
        </form>

        <!-- MULTIPLE SEARCH MATCHES WARNING/SELECTION -->
        @if($searchResults->isNotEmpty() && ! $product)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-800">
                        Select Matching Product ({{ $searchResults->count() }} found)
                    </span>
                    <span class="text-[11px] text-amber-600 font-medium">Please choose one item to prevent combining history</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                    @foreach($searchResults as $match)
                        <a
                            href="{{ route('admin.cashbook.reports-v1.item-purchase', array_merge(request()->except(['search_product', 'product_id', 'product_uuid']), ['product' => $match->public_uuid ?: $match->id])) }}"
                            class="flex items-center justify-between rounded-lg border border-amber-200 bg-white p-2.5 text-left text-xs font-semibold text-slate-800 hover:border-emerald-500 hover:bg-emerald-50/50 transition group"
                        >
                            <span class="truncate group-hover:text-emerald-700">{{ $match->name }}</span>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase text-slate-500 font-mono">
                                {{ $match->unit ?: 'unit' }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if($product)
        <!-- ACTIVE PRODUCT BANNER & FILTER TOOLBAR -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-visible">
            <!-- Header bar with product info -->
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/75 p-5 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow">
                        <i data-lucide="package" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-lg font-black text-slate-900 tracking-tight">{{ $product->name }}</h1>
                            @if($product->category)
                                <span class="rounded-full bg-slate-200/80 px-2.5 py-0.5 text-[11px] font-semibold text-slate-700">
                                    {{ $product->category->name }}
                                </span>
                            @endif
                            <span class="rounded-md border border-slate-300 bg-white px-2 py-0.5 text-[10px] font-bold uppercase text-slate-600 font-mono">
                                Base Unit: {{ strtoupper($product->unit ?: 'unit') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Purchasing history, cash vs credit classification, and vendor pending amounts.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-500">Date Range:</span>
                    <span class="rounded-lg bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-800 border border-emerald-200">
                        {{ $formattedRange }}
                    </span>
                </div>
            </div>

            <!-- FILTERS TOOLBAR -->
            <form method="GET" action="{{ route('admin.cashbook.reports-v1.item-purchase') }}" class="p-5 space-y-4">
                <input type="hidden" name="product" value="{{ $product->public_uuid ?: $product->id }}">

                <!-- PERIOD PRESETS -->
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

                <!-- FILTER ROW: CUSTOM DATES, GRADE, PURCHASE TYPE, PURCHASER, VENDOR -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-2 border-t border-slate-100">
                    <!-- Custom Date Range -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Date From / To
                        </label>
                        <div class="grid grid-cols-2 gap-1.5">
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

                    <!-- Grade Filter (Custom Tailwind Dropdown) -->
                    <div class="relative" x-data="{
                        open: false,
                        selectedGrade: '{{ $selectedGrade }}',
                        selectedLabel: '{{ $selectedGrade === 'all' ? 'All Grades' : (strtoupper($selectedGrade) === 'UNGRADED' ? 'Ungraded / Unspecified' : 'Grade '.$selectedGrade) }}',
                        select(grade, label) {
                            this.selectedGrade = grade;
                            this.selectedLabel = label;
                            this.open = false;
                            this.$nextTick(() => { this.$el.closest('form').submit(); });
                        }
                    }" @click.outside="open = false">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Grade
                        </label>
                        <input type="hidden" name="grade" :value="selectedGrade">

                        <button
                            type="button"
                            @click="open = !open"
                            class="w-full flex items-center justify-between rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-xs hover:border-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition text-left cursor-pointer"
                        >
                            <span class="truncate" x-text="selectedLabel"></span>
                            <svg class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200 shrink-0 ml-1.5" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute left-0 mt-1 w-full min-w-[13rem] rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl ring-1 ring-black/5 z-50 space-y-0.5"
                        >
                            <button
                                type="button"
                                @click="select('all', 'All Grades')"
                                class="w-full flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-bold text-left transition cursor-pointer"
                                :class="selectedGrade === 'all' ? 'bg-emerald-50 text-emerald-900 font-black' : 'text-slate-700 hover:bg-slate-50'"
                            >
                                <span>All Grades ({{ count($options['grades']) }})</span>
                                <svg x-show="selectedGrade === 'all'" class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>

                            @foreach($options['grades'] as $gradeOption)
                                @php
                                    $gKey = is_object($gradeOption) ? $gradeOption->key : $gradeOption;
                                    $gLabel = is_object($gradeOption) ? $gradeOption->label : (strtoupper($gradeOption) === 'UNGRADED' ? 'Ungraded / Unspecified' : 'Grade '.$gradeOption);
                                    $hasCount = is_object($gradeOption) && isset($gradeOption->count);
                                    $countVal = $hasCount ? (int) $gradeOption->count : null;
                                @endphp
                                <button
                                    type="button"
                                    @click="select('{{ $gKey }}', '{{ $gLabel }}{{ $countVal > 0 ? " ({$countVal})" : '' }}')"
                                    class="w-full flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-semibold text-left transition cursor-pointer"
                                    :class="selectedGrade.toLowerCase() === '{{ strtolower((string)$gKey) }}' ? 'bg-emerald-50 text-emerald-900 font-black' : 'text-slate-700 hover:bg-slate-50'"
                                >
                                    <span>{{ $gLabel }}</span>
                                    <div class="flex items-center gap-1.5 shrink-0 ml-2">
                                        @if($countVal !== null && $countVal > 0)
                                            <span class="text-[10px] text-slate-400 font-mono">({{ $countVal }})</span>
                                        @elseif($countVal === 0)
                                            <span class="text-[10px] text-amber-600 font-mono bg-amber-50 px-1 py-0.5 rounded border border-amber-200">0 in period</span>
                                        @endif
                                        <svg x-show="selectedGrade.toLowerCase() === '{{ strtolower((string)$gKey) }}'" class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Purchase Type Filter (Custom Tailwind Dropdown) -->
                    <div class="relative" x-data="{
                        open: false,
                        selectedPayment: '{{ $selectedPayment }}',
                        selectedLabel: '{{ $options['payment_types'][$selectedPayment] ?? 'All Purchases' }}',
                        select(payment, label) {
                            this.selectedPayment = payment;
                            this.selectedLabel = label;
                            this.open = false;
                            this.$nextTick(() => { this.$el.closest('form').submit(); });
                        }
                    }" @click.outside="open = false">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Purchase Type
                        </label>
                        <input type="hidden" name="payment" :value="selectedPayment">

                        <button
                            type="button"
                            @click="open = !open"
                            class="w-full flex items-center justify-between rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-xs hover:border-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition text-left cursor-pointer"
                        >
                            <span class="truncate" x-text="selectedLabel"></span>
                            <svg class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200 shrink-0 ml-1.5" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute left-0 mt-1 w-full min-w-[12rem] rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl ring-1 ring-black/5 z-50 space-y-0.5"
                        >
                            @foreach($options['payment_types'] as $pKey => $pLabel)
                                <button
                                    type="button"
                                    @click="select('{{ $pKey }}', '{{ $pLabel }}')"
                                    class="w-full flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-semibold text-left transition cursor-pointer"
                                    :class="selectedPayment === '{{ $pKey }}' ? 'bg-emerald-50 text-emerald-900 font-black' : 'text-slate-700 hover:bg-slate-50'"
                                >
                                    <span>{{ $pLabel }}</span>
                                    <svg x-show="selectedPayment === '{{ $pKey }}'" class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Purchaser Filter (Custom Tailwind Dropdown) -->
                    <div class="relative" x-data="{
                        open: false,
                        selectedId: '{{ $selectedPurchaserId }}',
                        selectedName: '{{ $selectedPurchaser ? $selectedPurchaser->name : 'All Purchasers' }}',
                        search: '',
                        select(id, name) {
                            this.selectedId = id;
                            this.selectedName = name;
                            this.open = false;
                            this.$nextTick(() => { this.$el.closest('form').submit(); });
                        }
                    }" @click.outside="open = false">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Purchaser
                        </label>
                        <input type="hidden" name="purchaser_id" :value="selectedId">

                        <button
                            type="button"
                            @click="open = !open"
                            class="w-full flex items-center justify-between rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-xs hover:border-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition text-left cursor-pointer"
                        >
                            <span class="truncate" x-text="selectedName"></span>
                            <svg class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200 shrink-0 ml-1.5" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute left-0 mt-1 w-full min-w-[14rem] rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl ring-1 ring-black/5 z-50 space-y-1"
                        >
                            @if(count($options['purchasers']) > 5)
                                <div class="px-1 pb-1 border-b border-slate-100">
                                    <input
                                        type="text"
                                        x-model="search"
                                        placeholder="Search purchaser..."
                                        class="w-full rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                        @click.stop
                                    >
                                </div>
                            @endif

                            <div class="max-h-56 overflow-y-auto space-y-0.5">
                                <button
                                    type="button"
                                    @click="select('', 'All Purchasers')"
                                    class="w-full flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-bold text-left transition cursor-pointer"
                                    :class="selectedId === '' ? 'bg-emerald-50 text-emerald-900 font-black' : 'text-slate-700 hover:bg-slate-50'"
                                >
                                    <span>All Purchasers ({{ count($options['purchasers']) }})</span>
                                    <svg x-show="selectedId === ''" class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>

                                @foreach($options['purchasers'] as $p)
                                    <button
                                        type="button"
                                        x-show="!search || '{{ strtolower($p->name) }}'.includes(search.toLowerCase())"
                                        @click="select('{{ $p->id }}', '{{ addslashes($p->name) }}')"
                                        class="w-full flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-semibold text-left transition cursor-pointer"
                                        :class="selectedId === '{{ $p->id }}' ? 'bg-emerald-50 text-emerald-900 font-black' : 'text-slate-700 hover:bg-slate-50'"
                                    >
                                        <span class="truncate">{{ $p->name }}</span>
                                        <div class="flex items-center gap-1.5 shrink-0 ml-2">
                                            @if(isset($p->purchase_count) && $p->purchase_count > 0)
                                                <span class="text-[10px] text-slate-400 font-mono">({{ $p->purchase_count }})</span>
                                            @elseif(isset($p->purchase_count) && $p->purchase_count === 0)
                                                <span class="text-[10px] text-amber-600 font-mono bg-amber-50 px-1 py-0.5 rounded border border-amber-200">0 in period</span>
                                            @endif
                                            <svg x-show="selectedId === '{{ $p->id }}'" class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Vendor Filter (Custom Tailwind Dropdown) -->
                    <div class="flex items-end gap-2">
                        <div class="flex-1 relative" x-data="{
                            open: false,
                            selectedId: '{{ $selectedVendorId }}',
                            selectedName: '{{ $selectedVendor ? $selectedVendor->name : 'All Vendors' }}',
                            search: '',
                            select(id, name) {
                                this.selectedId = id;
                                this.selectedName = name;
                                this.open = false;
                                this.$nextTick(() => { this.$el.closest('form').submit(); });
                            }
                        }" @click.outside="open = false">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                                Vendor
                            </label>
                            <input type="hidden" name="vendor_id" :value="selectedId">

                            <button
                                type="button"
                                @click="open = !open"
                                class="w-full flex items-center justify-between rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-xs hover:border-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition text-left cursor-pointer"
                            >
                                <span class="truncate" x-text="selectedName"></span>
                                <svg class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200 shrink-0 ml-1.5" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute right-0 mt-1 w-full min-w-[15rem] rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl ring-1 ring-black/5 z-50 space-y-1"
                            >
                                @if(count($options['vendors']) > 5)
                                    <div class="px-1 pb-1 border-b border-slate-100">
                                        <input
                                            type="text"
                                            x-model="search"
                                            placeholder="Search vendor..."
                                            class="w-full rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                            @click.stop
                                        >
                                    </div>
                                @endif

                                <div class="max-h-56 overflow-y-auto space-y-0.5">
                                    <button
                                        type="button"
                                        @click="select('', 'All Vendors')"
                                        class="w-full flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-bold text-left transition cursor-pointer"
                                        :class="selectedId === '' ? 'bg-emerald-50 text-emerald-900 font-black' : 'text-slate-700 hover:bg-slate-50'"
                                    >
                                        <span>All Vendors ({{ count($options['vendors']) }})</span>
                                        <svg x-show="selectedId === ''" class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>

                                    @foreach($options['vendors'] as $v)
                                        <button
                                            type="button"
                                            x-show="!search || '{{ strtolower($v->name) }}'.includes(search.toLowerCase())"
                                            @click="select('{{ $v->id }}', '{{ addslashes($v->name) }}')"
                                            class="w-full flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-semibold text-left transition cursor-pointer"
                                            :class="selectedId === '{{ $v->id }}' ? 'bg-emerald-50 text-emerald-900 font-black' : 'text-slate-700 hover:bg-slate-50'"
                                        >
                                            <span class="truncate">{{ $v->name }}</span>
                                            <div class="flex items-center gap-1.5 shrink-0 ml-2">
                                                @if(isset($v->purchase_count) && $v->purchase_count > 0)
                                                    <span class="text-[10px] text-slate-400 font-mono">({{ $v->purchase_count }})</span>
                                                @elseif(isset($v->purchase_count) && $v->purchase_count === 0)
                                                    <span class="text-[10px] text-amber-600 font-mono bg-amber-50 px-1 py-0.5 rounded border border-amber-200">0 in period</span>
                                                @endif
                                                <svg x-show="selectedId === '{{ $v->id }}'" class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800 transition shadow shrink-0 cursor-pointer"
                        >
                            Apply
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- SUMMARY KPI CARDS (FILTERED & RECONCILED) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Total Purchased Quantity -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Purchased Qty</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <i data-lucide="scale" class="h-4 w-4"></i>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-black font-mono text-slate-900">
                        {{ number_format((float)$summary->total_quantity, 3) }}
                        <span class="text-sm font-bold text-slate-500 uppercase">{{ $summary->unit }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">
                        Across {{ $summary->invoice_count }} bill{{ $summary->invoice_count === 1 ? '' : 's' }}
                    </p>
                </div>
            </div>

            <!-- Total Purchase Amount (Cash + Credit Breakdown) -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Purchase Amount</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <i data-lucide="indian-rupee" class="h-4 w-4"></i>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-black font-mono text-emerald-700">
                        ₹{{ number_format((float)$summary->total_amount, 2) }}
                    </div>
                    <div class="mt-1.5 flex items-center gap-2 text-[11px] font-bold">
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700 font-mono">
                            Cash: ₹{{ number_format((float)$summary->cash_purchase_amount, 2) }}
                        </span>
                        <span class="rounded bg-blue-50 px-1.5 py-0.5 text-blue-800 font-mono border border-blue-200">
                            Credit: ₹{{ number_format((float)$summary->credit_purchase_amount, 2) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Weighted Average Purchase Rate -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Weighted Avg Rate</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <i data-lucide="trending-up" class="h-4 w-4"></i>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-black font-mono text-slate-900">
                        ₹{{ number_format((float)$summary->weighted_avg_rate, 2) }}
                        <span class="text-xs font-bold text-slate-500">/ {{ $summary->unit }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">
                        Total Amount ÷ Total Qty
                    </p>
                </div>
            </div>

            <!-- Associated Credit Bill Pending -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Credit Bill Pending</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <i data-lucide="alert-circle" class="h-4 w-4"></i>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-black font-mono text-rose-700">
                        ₹{{ number_format((float)$summary->associated_credit_bill_pending, 2) }}
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">
                        @if($summary->exact_attributable_pending > 0 && $summary->has_multi_product_credit_bills)
                            <span class="text-slate-600 font-semibold">
                                Attributable: ₹{{ number_format((float)$summary->exact_attributable_pending, 2) }}
                            </span>
                            <span class="text-amber-700 block text-[10px]">
                                *Includes shared multi-item bills
                            </span>
                        @elseif($summary->has_multi_product_credit_bills)
                            <span class="text-amber-700 text-[10px]">
                                *Across {{ $summary->credit_invoice_count }} distinct bills (shared with other products)
                            </span>
                        @else
                            <span class="text-slate-500 text-[10px]">
                                Across {{ $summary->credit_invoice_count }} distinct credit bills
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- GRADE-WISE PURCHASE SUMMARY -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50/50 p-4 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Grade-Wise Purchase Breakdown</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Summary of purchased volume and rates by grade for {{ $product->name }}.</p>
                </div>
                <span class="text-xs font-bold text-slate-500 font-mono">
                    {{ $gradeBreakdown->count() }} Grade{{ $gradeBreakdown->count() === 1 ? '' : 's' }} Present
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="p-3.5">Grade</th>
                            <th class="p-3.5 text-right">Purchased Qty ({{ $summary->unit }})</th>
                            <th class="p-3.5 text-right">Purchase Amount (₹)</th>
                            <th class="p-3.5 text-right">Avg Rate (₹ / {{ $summary->unit }})</th>
                            <th class="p-3.5 text-right">% of Qty</th>
                            <th class="p-3.5 text-right">% of Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($gradeBreakdown as $gradeRow)
                            @php
                                $qtyPercent = $summary->total_quantity > 0 ? ($gradeRow->quantity / $summary->total_quantity) * 100 : 0;
                                $amountPercent = $summary->total_amount > 0 ? ($gradeRow->amount / $summary->total_amount) * 100 : 0;
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="p-3.5 font-bold">
                                    <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-extrabold {{ in_array($gradeRow->grade, ['A', 'Grade A']) ? 'bg-emerald-100 text-emerald-800' : (in_array($gradeRow->grade, ['B', 'Grade B']) ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $gradeRow->grade }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right font-mono font-bold text-slate-900">
                                    {{ number_format((float)$gradeRow->quantity, 3) }}
                                </td>
                                <td class="p-3.5 text-right font-mono font-bold text-slate-900">
                                    ₹{{ number_format((float)$gradeRow->amount, 2) }}
                                </td>
                                <td class="p-3.5 text-right font-mono font-bold text-emerald-700">
                                    ₹{{ number_format((float)$gradeRow->avg_rate, 2) }}
                                </td>
                                <td class="p-3.5 text-right font-mono text-slate-500">
                                    {{ number_format($qtyPercent, 1) }}%
                                </td>
                                <td class="p-3.5 text-right font-mono text-slate-500">
                                    {{ number_format($amountPercent, 1) }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-6 text-center text-slate-400">
                                    No purchase records found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($gradeBreakdown->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50/90 font-black text-slate-950">
                            <tr>
                                <td class="p-3.5 uppercase tracking-wider text-[11px]">Total / Reconciliation</td>
                                <td class="p-3.5 text-right font-mono">{{ number_format((float)$summary->total_quantity, 3) }}</td>
                                <td class="p-3.5 text-right font-mono">₹{{ number_format((float)$summary->total_amount, 2) }}</td>
                                <td class="p-3.5 text-right font-mono text-emerald-800">₹{{ number_format((float)$summary->weighted_avg_rate, 2) }}</td>
                                <td class="p-3.5 text-right font-mono">100.0%</td>
                                <td class="p-3.5 text-right font-mono">100.0%</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        <!-- DETAILED ITEM PURCHASE TABLE WITH CASH/CREDIT & VENDOR DRILL-DOWN -->
        <section
            class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
            x-data="{
                searchLedger: '',
                sortCol: 'date',
                sortAsc: false,
                sortTable(colKey, type = 'text') {
                    const table = document.getElementById('itemPurchasesTable');
                    if (!table) return;
                    const tbody = table.querySelector('tbody');
                    if (!tbody) return;
                    const rows = Array.from(tbody.querySelectorAll('tr[data-sort-row]'));
                    if (rows.length <= 1) return;

                    if (this.sortCol === colKey) {
                        this.sortAsc = !this.sortAsc;
                    } else {
                        this.sortCol = colKey;
                        this.sortAsc = (type === 'date' || type === 'num' ? false : true);
                    }

                    rows.sort((a, b) => {
                        let valA = a.getAttribute('data-' + colKey) || '';
                        let valB = b.getAttribute('data-' + colKey) || '';

                        if (type === 'num') {
                            const numA = parseFloat(valA) || 0;
                            const numB = parseFloat(valB) || 0;
                            return this.sortAsc ? numA - numB : numB - numA;
                        }

                        if (type === 'date') {
                            return this.sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                        }

                        return this.sortAsc
                            ? valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' })
                            : valB.localeCompare(valA, undefined, { numeric: true, sensitivity: 'base' });
                    });

                    rows.forEach(row => tbody.appendChild(row));
                },
                sortBy(col, type = 'text') {
                    this.sortTable(col, type);
                }
            }"
        >
            <div class="border-b border-slate-200 bg-slate-50/50 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Detailed Purchase History</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Line-level item purchases with cash/credit status, vendor contacts, and bill balances.</p>
                </div>

                <div class="flex items-center gap-2">
                    <div class="relative">
                        <i data-lucide="filter" class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400"></i>
                        <input
                            type="text"
                            x-model="searchLedger"
                            placeholder="Filter on page..."
                            class="rounded-lg border border-slate-300 py-1.5 pl-8 pr-3 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 w-44"
                        >
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left" id="itemPurchasesTable">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 select-none">
                        <tr>
                            <th class="p-3.5 cursor-pointer hover:bg-slate-100 transition select-none" @click="sortTable('date', 'date')" title="Click to sort by Date">
                                <div class="flex items-center gap-1.5">
                                    <span>Date</span>
                                    <span class="text-[9px]" :class="sortCol === 'date' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'date' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                </div>
                            </th>
                            <th class="p-3.5 cursor-pointer hover:bg-slate-100 transition select-none" @click="sortTable('bill', 'text')" title="Click to sort by Bill Ref">
                                <div class="flex items-center gap-1.5">
                                    <span>Bill Ref</span>
                                    <span class="text-[9px]" :class="sortCol === 'bill' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'bill' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                </div>
                            </th>
                            <th class="p-3.5 text-center cursor-pointer hover:bg-slate-100 transition select-none" @click="sortTable('grade', 'text')" title="Click to sort by Grade">
                                <div class="flex items-center justify-center gap-1.5">
                                    <span>Grade</span>
                                    <span class="text-[9px]" :class="sortCol === 'grade' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'grade' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                </div>
                            </th>
                            <th class="p-3.5 text-right cursor-pointer hover:bg-slate-100 transition select-none" @click="sortTable('qty', 'num')" title="Click to sort by Quantity">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Qty</span>
                                    <span class="text-[9px]" :class="sortCol === 'qty' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'qty' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                </div>
                            </th>
                            <th class="p-3.5 text-right cursor-pointer hover:bg-slate-100 transition select-none" @click="sortTable('rate', 'num')" title="Click to sort by Rate">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Rate</span>
                                    <span class="text-[9px]" :class="sortCol === 'rate' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'rate' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                </div>
                            </th>
                            <th class="p-3.5 text-right cursor-pointer hover:bg-slate-100 transition select-none" @click="sortTable('total', 'num')" title="Click to sort by Line Total">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Line Total</span>
                                    <span class="text-[9px]" :class="sortCol === 'total' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'total' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                </div>
                            </th>
                            <th class="p-3.5 text-center cursor-pointer hover:bg-slate-100 transition select-none" @click="sortTable('type', 'text')" title="Click to sort by Payment Type">
                                <div class="flex items-center justify-center gap-1.5">
                                    <span>Type &amp; Status</span>
                                    <span class="text-[9px]" :class="sortCol === 'type' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'type' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                </div>
                            </th>
                            <th class="p-3.5 cursor-pointer hover:bg-slate-100 transition select-none" @click="sortTable('purchaser', 'text')" title="Click to sort by Purchaser">
                                <div class="flex items-center gap-1.5">
                                    <span>Purchaser</span>
                                    <span class="text-[9px]" :class="sortCol === 'purchaser' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'purchaser' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                </div>
                            </th>
                            <th class="p-3.5 cursor-pointer hover:bg-slate-100 transition select-none" @click="sortTable('vendor', 'text')" title="Click to sort by Vendor">
                                <div class="flex items-center gap-1.5">
                                    <span>Vendor Details</span>
                                    <span class="text-[9px]" :class="sortCol === 'vendor' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'vendor' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                </div>
                            </th>
                            <th class="p-3.5 text-right cursor-pointer hover:bg-slate-100 transition select-none" @click="sortTable('pending', 'num')" title="Click to sort by Pending Amount">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span>Pending Amount</span>
                                    <span class="text-[9px]" :class="sortCol === 'pending' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'pending' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                </div>
                            </th>
                            <th class="p-3.5 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($items as $row)
                            <tr
                                data-sort-row
                                data-date="{{ \Illuminate\Support\Carbon::parse($row->business_date)->format('Y-m-d') }}"
                                data-bill="{{ $row->invoice_number }}"
                                data-grade="{{ $row->grade ?: 'Ungraded' }}"
                                data-qty="{{ (float)$row->quantity }}"
                                data-rate="{{ (float)$row->unit_price }}"
                                data-total="{{ (float)$row->item_net }}"
                                data-type="{{ $row->payment_class }} {{ $row->payment_status_badge }}"
                                data-purchaser="{{ $row->purchaser_name ?: '' }}"
                                data-vendor="{{ $row->supplier_name ?: '' }}"
                                data-pending="{{ (float)$row->invoice_outstanding }}"
                                class="hover:bg-slate-50/75 transition cursor-pointer"
                                x-show="!searchLedger || '{{ strtolower($row->invoice_number.' '.$row->purchaser_name.' '.$row->supplier_name.' '.$row->grade.' '.$row->payment_class.' '.$row->business_date) }}'.includes(searchLedger.toLowerCase())"
                                @click="openDrilldown({{ json_encode($row) }})"
                            >
                                <td class="p-3.5 font-mono text-slate-700 whitespace-nowrap">
                                    {{ \Illuminate\Support\Carbon::parse($row->business_date)->format('d M Y') }}
                                </td>
                                <td class="p-3.5 font-bold whitespace-nowrap">
                                    <a
                                        href="{{ route('purchasing.invoices.show', $row->invoice_public_uuid) }}"
                                        class="font-black text-emerald-700 hover:text-emerald-800 hover:underline flex items-center gap-1"
                                        @click.stop
                                    >
                                        {{ $row->invoice_number }}
                                        <i data-lucide="external-link" class="h-3 w-3 text-emerald-600"></i>
                                    </a>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-extrabold uppercase {{ in_array($row->grade, ['A', 'Grade A']) ? 'bg-emerald-100 text-emerald-800' : (in_array($row->grade, ['B', 'Grade B']) ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600') }}">
                                        {{ $row->grade ?: 'Ungraded' }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                    {{ number_format((float)$row->quantity, 3) }}
                                    <span class="text-[10px] text-slate-500 font-normal uppercase">{{ $row->product_unit }}</span>
                                </td>
                                <td class="p-3.5 text-right font-mono font-bold text-slate-800 whitespace-nowrap">
                                    ₹{{ number_format((float)$row->unit_price, 2) }}
                                </td>
                                <td class="p-3.5 text-right font-mono font-black text-emerald-700 whitespace-nowrap">
                                    ₹{{ number_format((float)$row->item_net, 2) }}
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-black uppercase {{ $row->payment_badge_color }}">
                                        {{ $row->payment_status_badge }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-slate-800 whitespace-nowrap">
                                    {{ $row->purchaser_name ?: '—' }}
                                </td>
                                <td class="p-3.5 text-slate-800">
                                    @if($row->supplier_public_uuid)
                                        <a
                                            href="{{ route('admin.cashbook.finance.purchase.vendors.show', $row->supplier_public_uuid) }}"
                                            class="font-bold text-slate-900 hover:text-emerald-700 hover:underline block truncate max-w-[12rem]"
                                            @click.stop
                                        >
                                            {{ $row->supplier_name ?: '—' }}
                                        </a>
                                    @else
                                        <span class="font-bold text-slate-900 block truncate max-w-[12rem]">
                                            {{ $row->supplier_name ?: '—' }}
                                        </span>
                                    @endif
                                    @if($row->supplier_mobile || $row->supplier_contact)
                                        <span class="text-[10px] text-slate-400 block font-mono">
                                            {{ $row->supplier_mobile ?: $row->supplier_contact }}
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3.5 text-right whitespace-nowrap">
                                    @if($row->payment_class === 'cash')
                                        <span class="text-slate-400 font-mono text-[11px]">—</span>
                                    @else
                                        @if($row->is_single_product_bill)
                                            <div class="font-mono font-bold {{ $row->invoice_outstanding > 0.01 ? 'text-rose-700' : 'text-emerald-700' }}">
                                                ₹{{ number_format((float)$row->invoice_outstanding, 2) }}
                                            </div>
                                            <span class="rounded bg-rose-50 px-1 py-0.2 text-[9px] font-bold uppercase text-rose-700 border border-rose-200">
                                                {{ $row->invoice_outstanding > 0.01 ? 'Item Pending' : 'Settled' }}
                                            </span>
                                        @else
                                            <div class="font-mono font-bold {{ $row->invoice_outstanding > 0.01 ? 'text-amber-800' : 'text-emerald-700' }}">
                                                ₹{{ number_format((float)$row->invoice_outstanding, 2) }}
                                            </div>
                                            <span class="rounded bg-amber-50 px-1 py-0.2 text-[9px] font-bold uppercase text-amber-800 border border-amber-200" title="Covers {{ $row->product_count }} products on this bill">
                                                Bill Pending*
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap" @click.stop>
                                    <button
                                        type="button"
                                        @click="openDrilldown({{ json_encode($row) }})"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50 shadow-sm"
                                    >
                                        <i data-lucide="info" class="h-3 w-3 text-slate-400"></i> Details
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="p-8 text-center bg-slate-50/40">
                                    <div class="mx-auto max-w-lg space-y-3">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 border border-amber-200">
                                            <i data-lucide="filter-x" class="h-6 w-6"></i>
                                        </div>

                                        <div>
                                            <h4 class="text-sm font-bold text-slate-800">
                                                No purchases found matching active filters
                                            </h4>
                                            <p class="text-xs text-slate-500 mt-0.5">
                                                No records for <strong class="text-slate-900">{{ $product->name }}</strong> match the selected combination in this date range ({{ $filters['formatted_range'] }}).
                                            </p>
                                        </div>

                                        @if(!empty($filterAssistance))
                                            <div class="rounded-xl border border-slate-200 bg-white p-3.5 text-left text-xs space-y-2.5 shadow-xs">
                                                @if(!empty($filterAssistance['vendor_history']))
                                                    <div class="flex items-start gap-2">
                                                        <i data-lucide="calendar" class="h-4 w-4 text-emerald-600 shrink-0 mt-0.5"></i>
                                                        <div>
                                                            <p class="text-slate-700">
                                                                Vendor <strong class="font-bold text-slate-900">{{ $filterAssistance['vendor_history']->vendor_name }}</strong> has no purchases in {{ $filters['formatted_range'] }}, but supplied this item on <strong class="font-bold text-slate-900">{{ \Carbon\Carbon::parse($filterAssistance['vendor_history']->min_date)->format('d M Y') }}</strong> ({{ $filterAssistance['vendor_history']->total_count }} purchase, Grade {{ $filterAssistance['vendor_history']->grades }}).
                                                            </p>
                                                            <a
                                                                href="{{ route('admin.cashbook.reports-v1.item-purchase', ['product' => $product->public_uuid ?: $product->id, 'vendor_id' => $filters['vendor_id'], 'start_date' => $filterAssistance['vendor_history']->min_date, 'end_date' => $filterAssistance['vendor_history']->max_date, 'period' => 'custom', 'grade' => 'all']) }}"
                                                                class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:text-emerald-800 hover:underline mt-1"
                                                            >
                                                                View purchase from {{ \Carbon\Carbon::parse($filterAssistance['vendor_history']->min_date)->format('d M Y') }} &rarr;
                                                            </a>
                                                        </div>
                                                    </div>
                                                @endif

                                                @if(!empty($filterAssistance['grade_history']))
                                                    <div class="flex items-start gap-2 pt-2 border-t border-slate-100">
                                                        <i data-lucide="tag" class="h-4 w-4 text-amber-600 shrink-0 mt-0.5"></i>
                                                        <div>
                                                            <p class="text-slate-700">
                                                                <strong class="font-bold text-slate-900">Grade {{ $filters['grade'] }}</strong> has no records in {{ $filters['formatted_range'] }}, but was supplied on <strong class="font-bold text-slate-900">{{ \Carbon\Carbon::parse($filterAssistance['grade_history']->min_date)->format('d M Y') }}</strong> by <strong class="font-bold text-slate-900">{{ $filterAssistance['grade_history']->vendor_names }}</strong>.
                                                            </p>
                                                            <a
                                                                href="{{ route('admin.cashbook.reports-v1.item-purchase', ['product' => $product->public_uuid ?: $product->id, 'grade' => $filters['grade'], 'start_date' => $filterAssistance['grade_history']->min_date, 'end_date' => $filterAssistance['grade_history']->max_date, 'period' => 'custom']) }}"
                                                                class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:text-emerald-800 hover:underline mt-1"
                                                            >
                                                                View Grade {{ $filters['grade'] }} purchase on {{ \Carbon\Carbon::parse($filterAssistance['grade_history']->min_date)->format('d M Y') }} &rarr;
                                                            </a>
                                                        </div>
                                                    </div>
                                                @endif

                                                @if($filterAssistance['period_total_count'] > 0)
                                                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[11px]">
                                                        <span class="text-slate-500">
                                                            Total purchases for {{ $product->name }} in this period: <strong class="text-slate-900">{{ $filterAssistance['period_total_count'] }}</strong>
                                                        </span>
                                                        <a
                                                            href="{{ route('admin.cashbook.reports-v1.item-purchase', ['product' => $product->public_uuid ?: $product->id, 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'period' => $filters['period'], 'grade' => 'all', 'payment' => 'all']) }}"
                                                            class="font-bold text-blue-600 hover:text-blue-800 hover:underline"
                                                        >
                                                            View all in this period
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="pt-2 flex items-center justify-center gap-2">
                                            <a
                                                href="{{ route('admin.cashbook.reports-v1.item-purchase', ['product' => $product->public_uuid ?: $product->id, 'period' => 'this_month']) }}"
                                                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs"
                                            >
                                                Reset Filters
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($items instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $items->hasPages())
                <div class="border-t border-slate-200 bg-white p-4">
                    {{ $items->links() }}
                </div>
            @endif
        </section>

    @else
        <!-- NO PRODUCT SELECTED: WELCOME & RECENT ITEMS -->
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 mb-4">
                <i data-lucide="package-search" class="h-7 w-7"></i>
            </div>
            <h2 class="text-base font-black text-slate-900">Select a Product to View Item Purchase Report</h2>
            <p class="mx-auto max-w-md text-xs text-slate-500 mt-1">
                Type the product name in the search bar above or choose one of the recently purchased products below to inspect quantities, rates, grades, and supplier bills.
            </p>

            @if($recentProducts->isNotEmpty())
                <div class="mt-8 text-left max-w-4xl mx-auto">
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 text-center sm:text-left">
                        Recently Purchased Products
                    </span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        @foreach($recentProducts as $recent)
                            <a
                                href="{{ route('admin.cashbook.reports-v1.item-purchase', array_merge(request()->query(), ['product' => $recent->public_uuid ?: $recent->id])) }}"
                                class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/50 p-3.5 hover:border-emerald-500 hover:bg-emerald-50/40 transition group shadow-sm"
                            >
                                <div class="truncate mr-2">
                                    <span class="block font-bold text-xs text-slate-900 group-hover:text-emerald-700 truncate">
                                        {{ $recent->name }}
                                    </span>
                                    <span class="text-[10px] text-slate-400">
                                        Last: {{ $recent->last_purchased_date }}
                                    </span>
                                </div>
                                <span class="rounded bg-white px-2 py-0.5 text-[10px] font-mono font-bold uppercase text-slate-500 border border-slate-200 shrink-0">
                                    {{ $recent->unit ?: 'unit' }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- CREDIT PURCHASE & VENDOR DETAILS MODAL (REQUIREMENT C & D) -->
    <div
        x-show="showModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <!-- Backdrop -->
        <div
            x-show="showModal"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-950/50 backdrop-blur-xs transition-opacity"
            @click="closeDrilldown()"
        ></div>

        <!-- Panel -->
        <div class="flex min-h-screen items-center justify-center p-4">
            <div
                x-show="showModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden text-left"
                @click.stop
            >
                <template x-if="activeRow">
                    <div>
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-6 py-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-black text-slate-950">Purchase & Credit Details</h3>
                                    <span
                                        class="rounded-md border px-2 py-0.5 text-[10px] font-black uppercase"
                                        :class="activeRow.payment_badge_color"
                                        x-text="activeRow.payment_status_badge"
                                    ></span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Invoice <strong class="font-mono text-slate-900" x-text="activeRow.invoice_number"></strong> • <span x-text="activeRow.business_date"></span>
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="closeDrilldown()"
                                class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
                            >
                                <i data-lucide="x" class="h-5 w-5"></i>
                            </button>
                        </div>

                        <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
                            <!-- SECTION 1: PRODUCT PURCHASE INFORMATION -->
                            <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-1.5">
                                    <i data-lucide="package" class="h-3.5 w-3.5 text-emerald-600"></i> Purchase Information
                                </h4>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Product</span>
                                        <strong class="font-bold text-slate-900" x-text="activeRow.product_name"></strong>
                                    </div>
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Grade</span>
                                        <strong class="font-bold text-slate-900" x-text="activeRow.grade || 'Ungraded'"></strong>
                                    </div>
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Quantity</span>
                                        <strong class="font-mono font-bold text-slate-900" x-text="Number(activeRow.quantity).toFixed(3) + ' ' + activeRow.product_unit"></strong>
                                    </div>
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Unit Price</span>
                                        <strong class="font-mono font-bold text-slate-900" x-text="'₹' + Number(activeRow.unit_price).toFixed(2)"></strong>
                                    </div>
                                    <div class="sm:col-span-2 pt-2 border-t border-slate-200/60">
                                        <span class="block text-[11px] text-slate-500">Purchased By</span>
                                        <strong class="font-bold text-slate-900" x-text="activeRow.purchaser_name || '—'"></strong>
                                    </div>
                                    <div class="sm:col-span-2 pt-2 border-t border-slate-200/60">
                                        <span class="block text-[11px] text-slate-500">Effective Item Line Amount</span>
                                        <strong class="font-mono text-sm font-black text-emerald-700" x-text="'₹' + Number(activeRow.item_net).toFixed(2)"></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 2: VENDOR INFORMATION -->
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-1.5">
                                    <i data-lucide="store" class="h-3.5 w-3.5 text-blue-600"></i> Vendor Information
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Vendor Name</span>
                                        <strong class="font-bold text-slate-900 block truncate" x-text="activeRow.supplier_name || '—'"></strong>
                                    </div>
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Phone / Contact</span>
                                        <span class="font-mono text-slate-700" x-text="activeRow.supplier_mobile || activeRow.supplier_contact || '—'"></span>
                                    </div>
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Location</span>
                                        <span class="text-slate-700 truncate block" x-text="activeRow.supplier_location || '—'"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 3: CREDIT & SETTLEMENT INFORMATION -->
                            <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-1.5">
                                    <i data-lucide="credit-card" class="h-3.5 w-3.5 text-rose-600"></i> Credit & Invoice Settlement
                                </h4>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Bill Gross Total</span>
                                        <strong class="font-mono font-bold text-slate-800" x-text="'₹' + Number(activeRow.invoice_amount || 0).toFixed(2)"></strong>
                                    </div>
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Bill Discount</span>
                                        <strong class="font-mono font-bold text-slate-800" x-text="'₹' + Number(activeRow.invoice_discount_amount || 0).toFixed(2)"></strong>
                                    </div>
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Net Bill Total</span>
                                        <strong class="font-mono font-bold text-slate-900" x-text="'₹' + Number(activeRow.invoice_net || 0).toFixed(2)"></strong>
                                    </div>
                                    <div>
                                        <span class="block text-[11px] text-slate-500">Amount Paid / Settled</span>
                                        <strong class="font-mono font-bold text-emerald-700" x-text="'₹' + Number(activeRow.invoice_paid || 0).toFixed(2)"></strong>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div>
                                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Bill Pending Balance:</span>
                                        <span class="font-mono text-base font-black ml-2" :class="Number(activeRow.invoice_outstanding) > 0.01 ? 'text-rose-700' : 'text-emerald-700'" x-text="'₹' + Number(activeRow.invoice_outstanding || 0).toFixed(2)"></span>
                                    </div>
                                    <div>
                                        <template x-if="activeRow.is_single_product_bill">
                                            <span class="rounded bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-800 border border-emerald-200">
                                                Single-product bill • 100% attributable
                                            </span>
                                        </template>
                                        <template x-if="!activeRow.is_single_product_bill">
                                            <span class="rounded bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-800 border border-amber-200">
                                                Shared bill • Covers <span x-text="activeRow.product_count"></span> products
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Payment Allocations History -->
                                <template x-if="activeRow.settlements && activeRow.settlements.length > 0">
                                    <div class="mt-4 pt-3 border-t border-slate-200">
                                        <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">
                                            Payment & Settlement History
                                        </span>
                                        <div class="space-y-1.5">
                                            <template x-for="st in activeRow.settlements" :key="st.id || Math.random()">
                                                <div class="flex items-center justify-between text-xs bg-white p-2 rounded border border-slate-200">
                                                    <span class="text-slate-600 font-mono" x-text="st.payment_date"></span>
                                                    <div class="space-x-2 font-mono">
                                                        <template x-if="Number(st.cash_allocated) > 0">
                                                            <span class="text-slate-700">Cash: ₹<span x-text="Number(st.cash_allocated).toFixed(2)"></span></span>
                                                        </template>
                                                        <template x-if="Number(st.advance_allocated) > 0">
                                                            <span class="text-blue-700">Advance: ₹<span x-text="Number(st.advance_allocated).toFixed(2)"></span></span>
                                                        </template>
                                                        <template x-if="Number(st.discount_allocated) > 0">
                                                            <span class="text-amber-700">Disc: ₹<span x-text="Number(st.discount_allocated).toFixed(2)"></span></span>
                                                        </template>
                                                        <strong class="text-emerald-700 font-bold ml-1">Total: ₹<span x-text="Number(st.total_settled).toFixed(2)"></span></strong>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-3.5">
                            <div class="flex items-center gap-2">
                                <template x-if="activeRow.supplier_public_uuid">
                                    <a
                                        :href="'/admin/cashbook/finance/purchase/vendors/' + activeRow.supplier_public_uuid"
                                        class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-2xs"
                                    >
                                        <i data-lucide="store" class="h-3.5 w-3.5 text-blue-600"></i> Full Vendor Profile
                                    </a>
                                </template>
                                <a
                                    :href="'/purchasing/invoices/' + activeRow.invoice_public_uuid"
                                    class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-emerald-800 hover:bg-emerald-50 transition shadow-2xs"
                                >
                                    <i data-lucide="file-text" class="h-3.5 w-3.5 text-emerald-600"></i> Original Bill
                                </a>
                            </div>

                            <button
                                type="button"
                                @click="closeDrilldown()"
                                class="rounded-xl bg-slate-900 px-4 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition shadow"
                            >
                                Done
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection
