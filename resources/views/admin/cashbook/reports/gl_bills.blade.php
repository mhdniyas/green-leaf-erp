@extends('admin.cashbook.layouts.app')

@section('title', 'GL Bills & Daily Deliveries — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="receipt" class="w-5 h-5 text-emerald-600"></i> GL Bills &amp; Deliveries
@endsection

@section('header_subtitle')
    Daily shop invoice deliveries &amp; GL procurement bills.
@endsection

@section('content')
    <div x-data="{ activeInvoiceId: null, loading: false }" class="mx-auto max-w-4xl space-y-4">
        <!-- Top Fintech Header -->
        <div class="flex items-center justify-between pt-1">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">GL Bills</h1>
                <p class="text-xs font-bold text-slate-500 mt-0.5">Daily Shop Invoices <span class="text-slate-400 font-medium">&amp; Delivery Records</span></p>
            </div>

            <!-- Custom Tailwind Shop Selector Dropdown -->
            <div x-data="{ open: false }" class="relative inline-block text-left">
                <button @click="open = !open" type="button" class="inline-flex items-center justify-between gap-2 h-10 px-3.5 rounded-2xl bg-white border border-slate-200/90 text-xs font-black text-slate-800 shadow-xs hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all cursor-pointer">
                    <div class="flex items-center gap-2">
                        <i data-lucide="store" class="w-4 h-4 text-emerald-600"></i>
                        <span>{{ $selectedShop ? ($selectedShop->name ?: 'Shop #'.$selectedShop->shop_id) : 'Select Outlet...' }}</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                </button>

                <div x-show="open"
                     @click.away="open = false"
                     x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 mt-2 w-64 origin-top-right rounded-2xl bg-white p-1.5 shadow-xl ring-1 ring-black/5 z-50 divide-y divide-slate-100 max-h-72 overflow-y-auto custom-scrollbar">

                    <!-- Default option: Clear selection -->
                    <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => $timeframe, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                       @click="loading = true"
                       class="group flex items-center justify-between rounded-xl px-3 py-2 text-xs font-bold transition-all {{ !$selectedShopId ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                        <span>Select Outlet</span>
                        <span class="text-[9px] opacity-70">Clear</span>
                    </a>

                    <!-- Shop Options -->
                    <div class="py-1 space-y-0.5">
                        @foreach ($shops as $s)
                            <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => $timeframe, 'shop_id' => $s->shop_id, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                               @click="loading = true"
                               class="group flex items-center justify-between rounded-xl px-3 py-2 text-xs font-bold transition-all {{ $selectedShopId === $s->shop_id ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                                <span class="truncate">{{ $s->name ?: ('Shop #' . $s->shop_id) }}</span>
                                <span class="ml-2 rounded-md px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wide {{ $selectedShopId === $s->shop_id ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $s->code }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Segmented iOS Timeframe Bar (Today, Week, Month + Calendar Jump) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div class="inline-flex items-center max-w-full overflow-x-auto rounded-2xl bg-slate-200/70 p-1 shrink-0 gap-0.5">
                <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => 'today', 'shop_id' => $selectedShopId]) }}" @click="loading = true" class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all {{ $timeframe === 'today' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Today</a>
                <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => 'weekly', 'shop_id' => $selectedShopId]) }}" @click="loading = true" class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all {{ $timeframe === 'weekly' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Week</a>
                <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => 'monthly', 'shop_id' => $selectedShopId]) }}" @click="loading = true" class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all {{ $timeframe === 'monthly' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Month</a>
                <!-- Jump to Date Calendar Picker -->
                <label class="relative flex items-center justify-center cursor-pointer rounded-xl px-2 py-1 text-slate-600 hover:text-slate-900 hover:bg-white/60 transition-all" title="Jump to Specific Date">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    <input
                        type="date"
                        class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                        onchange="window.location.href='{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => 'custom', 'shop_id' => $selectedShopId]) }}&start_date=' + this.value + '&end_date=' + this.value"
                    >
                </label>
            </div>

            <!-- Custom Filter Form -->
            <form method="GET" action="{{ route('admin.cashbook.reports.gl-bills') }}" @submit="loading = true" class="flex flex-wrap sm:flex-nowrap items-center gap-1.5">
                <input type="hidden" name="timeframe" value="custom">
                <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                <input type="date" name="start_date" value="{{ $startDate }}" class="h-8 flex-1 min-w-[115px] rounded-xl border border-slate-200 bg-white px-2 text-xs font-bold text-slate-900 shadow-xs">
                <span class="text-xs font-bold text-slate-400">to</span>
                <input type="date" name="end_date" value="{{ $endDate }}" class="h-8 flex-1 min-w-[115px] rounded-xl border border-slate-200 bg-white px-2 text-xs font-bold text-slate-900 shadow-xs">
                <button type="submit" class="h-8 shrink-0 rounded-xl bg-slate-900 px-3.5 text-xs font-bold text-white hover:bg-slate-800">Apply</button>
            </form>
        </div>

        <!-- 3 Summary Metric Cards (1 Row on All Devices) -->
        <div class="grid grid-cols-3 gap-1.5 sm:gap-3">
            <div class="rounded-2xl border border-slate-100 bg-white p-2.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400 truncate">Total Billed</span>
                    <div class="flex h-5 w-5 sm:h-7 sm:w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600 shrink-0">
                        <i data-lucide="receipt" class="w-3 h-3 sm:w-4 sm:h-4"></i>
                    </div>
                </div>
                <p class="mt-1 sm:mt-2 text-xs sm:text-xl font-black text-slate-900 truncate">₹{{ number_format($totals['total_billed'], 2) }}</p>
                <span class="mt-0.5 block text-[8px] sm:text-[10px] font-bold text-slate-400 truncate">{{ $totals['count'] }} invoices</span>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white p-2.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400 truncate">Paid Amount</span>
                    <div class="flex h-5 w-5 sm:h-7 sm:w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 shrink-0">
                        <i data-lucide="check-circle-2" class="w-3 h-3 sm:w-4 sm:h-4"></i>
                    </div>
                </div>
                <p class="mt-1 sm:mt-2 text-xs sm:text-xl font-black text-emerald-700 truncate">₹{{ number_format($totals['total_paid'], 2) }}</p>
                <span class="mt-0.5 block text-[8px] sm:text-[10px] font-bold text-emerald-600 truncate">Settled</span>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white p-2.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400 truncate">Balance Due</span>
                    <div class="flex h-5 w-5 sm:h-7 sm:w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600 shrink-0">
                        <i data-lucide="alert-circle" class="w-3 h-3 sm:w-4 sm:h-4"></i>
                    </div>
                </div>
                <p class="mt-1 sm:mt-2 text-xs sm:text-xl font-black text-rose-700 truncate">₹{{ number_format($totals['total_balance'], 2) }}</p>
                <span class="mt-0.5 block text-[8px] sm:text-[10px] font-bold text-rose-600 truncate">Payable</span>
            </div>
        </div>

        <!-- Bouncing 3-Dot Loader (From Uiverse.io by mahendrameghwal) -->
        <div x-show="loading" x-cloak class="py-12 flex flex-col items-center justify-center space-y-4 rounded-[28px] border border-slate-100 bg-white shadow-xs">
            <div class="w-full gap-x-2 flex justify-center items-center">
                <div class="w-5 h-5 bg-[#d991c2] animate-pulse rounded-full animate-bounce"></div>
                <div class="w-5 h-5 bg-[#9869b8] animate-pulse rounded-full animate-bounce [animation-delay:0.2s]"></div>
                <div class="w-5 h-5 bg-[#6756cc] animate-pulse rounded-full animate-bounce [animation-delay:0.4s]"></div>
            </div>
            <p class="text-xs font-black tracking-wide text-slate-500">Loading GL Bills &amp; Deliveries...</p>
        </div>

        <!-- GL Bills Data Table Card (Clean 3-Column List: Date & Ref, Shop Outlet, Bill Total) -->
        <div x-show="!loading" class="rounded-[28px] border border-slate-100 bg-white p-4 sm:p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)] space-y-4">
            <div class="flex items-center justify-between px-1">
                <div>
                    <h3 class="text-sm font-black text-slate-900">Daily Invoices &amp; Bills Table</h3>
                    <p class="text-[10px] font-semibold text-slate-400">
                        {{ $selectedShop ? 'Viewing bills for '.$selectedShop->name : 'Select an outlet above to load bills' }}
                    </p>
                </div>
                @if ($selectedShopId)
                    <span class="text-[10px] font-bold text-slate-400">Page {{ $invoices->currentPage() }} of {{ $invoices->lastPage() }}</span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-[9px] font-black uppercase tracking-wider text-slate-400">
                            <th class="py-2.5 px-3">Date &amp; Ref</th>
                            <th class="py-2.5 px-3">Shop Outlet</th>
                            <th class="py-2.5 px-3 text-right">Bill Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                        @if (!$selectedShopId)
                            <tr>
                                <td colspan="3" class="py-12 text-center space-y-2">
                                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <i data-lucide="store" class="w-6 h-6"></i>
                                    </div>
                                    <p class="text-xs font-black text-slate-800">Select an Outlet to View Bills</p>
                                    <p class="text-[11px] font-semibold text-slate-400 max-w-xs mx-auto">Please choose a shop from the dropdown menu above to load its GL invoice delivery bills.</p>
                                </td>
                            </tr>
                        @else
                            @forelse ($invoices as $inv)
                                <tr @click="activeInvoiceId = {{ $inv->id }}" class="hover:bg-slate-50/80 transition cursor-pointer group">
                                    <td class="py-3 px-3">
                                        <span class="font-black text-slate-900 block group-hover:text-indigo-600 transition">{{ optional($inv->business_date)->format('d M Y') }}</span>
                                        <span class="text-[10px] font-bold text-slate-400 block truncate max-w-[140px] sm:max-w-[200px]">{{ $inv->invoice_number }}</span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="font-black text-slate-900 block">{{ $inv->shop?->name ?: ('Shop #' . $inv->shop_id) }}</span>
                                        <span class="text-[10px] font-bold text-slate-400 block uppercase">{{ $inv->shop?->code }}</span>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <span class="font-black text-slate-900 block text-xs sm:text-sm">₹{{ number_format((float) $inv->final_total, 2) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-8 text-center text-xs font-bold text-slate-400">
                                        No GL invoices or delivery bills found for {{ $selectedShop?->name }} in the selected period.
                                    </td>
                                </tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            @if ($selectedShopId)
                <div class="pt-2">
                    {{ $invoices->links() }}
                </div>
            @endif
        </div>

        <!-- Shop Owner Style Invoice Receipt Modals -->
        @if ($selectedShopId)
            @foreach ($invoices as $inv)
                <div
                    x-show="activeInvoiceId === {{ $inv->id }}"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-900/60 backdrop-blur-sm overflow-y-auto"
                    @click.self="activeInvoiceId = null"
                >
                    <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl p-4 sm:p-6 max-h-[90vh] overflow-y-auto custom-scrollbar">
                        <button @click="activeInvoiceId = null" class="absolute top-3 right-3 h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold text-sm z-20">✕</button>
                        @include('shop-owner.finance.partials.invoice-card', ['invoice' => $inv])
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection
