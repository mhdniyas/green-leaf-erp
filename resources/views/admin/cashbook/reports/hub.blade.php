@extends('admin.cashbook.layouts.app')

@section('title', 'Finance & Shops Overview — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="layers" class="w-5 h-5 text-emerald-600"></i> Shops Overview
@endsection

@section('header_subtitle')
    Realtime multi-shop financial health and performance tiles.
@endsection

@section('content')
    <script>
        function adminReportsHub() {
            return {
                timeframe: '{{ $timeframe }}',
                startDate: '{{ $startDate }}',
                endDate: '{{ $endDate }}',
                searchQuery: '',
                typeFilter: 'owned', // 'owned', 'direct', 'all' (default: 'owned')
                loading: false,
                totals: @json($totals),
                shopMetrics: @json($shopMetrics->values()),

                init() {
                    this.syncUrl();
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                },

                filteredShops() {
                    let list = this.shopMetrics;

                    if (this.typeFilter === 'owned') {
                        list = list.filter(s => s.is_client_owned);
                    } else if (this.typeFilter === 'direct') {
                        list = list.filter(s => !s.is_client_owned);
                    }

                    if (!this.searchQuery.trim()) {
                        return list;
                    }
                    const q = this.searchQuery.toLowerCase();
                    return list.filter(s =>
                        s.shop_name.toLowerCase().includes(q) ||
                        s.shop_code.toLowerCase().includes(q)
                    );
                },

                activeTotals() {
                    const list = this.filteredShops();
                    const sales = list.reduce((sum, s) => sum + (parseFloat(s.sales) || 0), 0);
                    const expense = list.reduce((sum, s) => sum + (parseFloat(s.expense) || 0), 0);
                    const gl_bills = list.reduce((sum, s) => sum + (parseFloat(s.gl_bills) || 0), 0);
                    const net = sales - expense;
                    return {
                        sales: Math.round(sales * 100) / 100,
                        expense: Math.round(expense * 100) / 100,
                        net: Math.round(net * 100) / 100,
                        gl_bills: Math.round(gl_bills * 100) / 100,
                        count: list.length,
                    };
                },

                timeframeLabel() {
                    if (this.timeframe === 'today') return 'Today';
                    if (this.timeframe === 'weekly') return 'This Week';
                    if (this.timeframe === 'monthly') return 'This Month';
                    return this.startDate + ' to ' + this.endDate;
                },

                setPreset(preset) {
                    this.timeframe = preset;
                    const todayStr = '{{ today()->toDateString() }}';

                    if (preset === 'today') {
                        this.startDate = todayStr;
                        this.endDate = todayStr;
                    } else if (preset === 'weekly') {
                        this.startDate = '{{ today()->startOfWeek()->toDateString() }}';
                        this.endDate = '{{ today()->endOfWeek()->toDateString() }}';
                    } else if (preset === 'monthly') {
                        this.startDate = '{{ today()->startOfMonth()->toDateString() }}';
                        this.endDate = '{{ today()->endOfMonth()->toDateString() }}';
                    } else if (preset === 'custom') {
                        return;
                    }

                    this.loadData();
                },

                jumpToDate(selectedDate) {
                    if (!selectedDate) return;
                    this.timeframe = 'custom';
                    this.startDate = selectedDate;
                    this.endDate = selectedDate;
                    this.loadData();
                },

                syncUrl() {
                    try {
                        const url = new URL(window.location.href);
                        url.searchParams.set('timeframe', this.timeframe);
                        url.searchParams.set('start_date', this.startDate);
                        url.searchParams.set('end_date', this.endDate);
                        window.history.replaceState({}, '', url);

                        document.querySelectorAll('[data-nav-hub], [data-nav-charts], [data-nav-analytics]').forEach(el => {
                            const navUrl = new URL(el.href);
                            navUrl.searchParams.set('timeframe', this.timeframe);
                            navUrl.searchParams.set('start_date', this.startDate);
                            navUrl.searchParams.set('end_date', this.endDate);
                            el.href = navUrl.toString();
                        });
                    } catch (e) {}
                },

                async loadData() {
                    this.loading = true;
                    this.syncUrl();

                    try {
                        const params = new URLSearchParams({
                            timeframe: this.timeframe,
                            start_date: this.startDate,
                            end_date: this.endDate,
                        });

                        const response = await fetch(`{{ route('admin.cashbook.reports.api.hub') }}?${params.toString()}`);
                        const payload = await response.json();

                        if (payload.success) {
                            this.totals = payload.totals;
                            this.shopMetrics = payload.shopMetrics;
                        }
                    } catch (err) {
                        console.error('Failed to load hub data:', err);
                    } finally {
                        this.loading = false;
                        this.$nextTick(() => {
                            if (window.lucide) window.lucide.createIcons();
                        });
                    }
                },

                currency(value) {
                    const num = parseFloat(value || 0);
                    return '₹' + num.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                },
            };
        }
    </script>

    <div class="mx-auto max-w-4xl space-y-4" x-data="adminReportsHub()" x-init="init()">
        <!-- Top Fintech Header: Full Row with Title, Switcher (Own default), & Refresh Button -->
        <div class="flex items-center justify-between gap-2 pt-1 border-b border-slate-100 pb-3">
            <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl shrink-0">Finance</h1>

            <div class="flex items-center gap-2">
                <!-- 3-Option Shop Type Switcher (Own, Direct, All) -->
                <div class="inline-flex items-center gap-0.5 rounded-2xl bg-slate-200/70 p-1 shrink-0">
                    <button
                        type="button"
                        @click="typeFilter = 'owned'"
                        class="rounded-xl px-2.5 sm:px-3.5 py-1 text-[11px] sm:text-xs font-extrabold transition-all"
                        :class="typeFilter === 'owned' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        Own
                    </button>
                    <button
                        type="button"
                        @click="typeFilter = 'direct'"
                        class="rounded-xl px-2.5 sm:px-3.5 py-1 text-[11px] sm:text-xs font-extrabold transition-all"
                        :class="typeFilter === 'direct' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        Direct
                    </button>
                    <button
                        type="button"
                        @click="typeFilter = 'all'"
                        class="rounded-xl px-2.5 sm:px-3.5 py-1 text-[11px] sm:text-xs font-extrabold transition-all"
                        :class="typeFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        All
                    </button>
                </div>

                <!-- Refresh Control -->
                <button type="button" @click="loadData()" class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-xs transition hover:bg-slate-50 shrink-0" title="Refresh Live Data">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5 sm:w-4 sm:h-4" :class="{ 'animate-spin': loading }"></i>
                </button>
            </div>
        </div>

        <!-- Segmented iOS Timeframe Bar (Today, Week, Month, Custom + Calendar Jump) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div class="inline-flex items-center max-w-full overflow-x-auto rounded-2xl bg-slate-200/70 p-1 shrink-0 gap-0.5">
                <button
                    type="button"
                    @click="setPreset('today')"
                    class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all"
                    :class="timeframe === 'today' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                    Today
                </button>
                <button
                    type="button"
                    @click="setPreset('weekly')"
                    class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all"
                    :class="timeframe === 'weekly' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                    Week
                </button>
                <button
                    type="button"
                    @click="setPreset('monthly')"
                    class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all"
                    :class="timeframe === 'monthly' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                    Month
                </button>
                <button
                    type="button"
                    @click="setPreset('custom')"
                    class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all"
                    :class="timeframe === 'custom' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                    Custom
                </button>
                <!-- Jump to Date Calendar Picker Button -->
                <label class="relative flex items-center justify-center cursor-pointer rounded-xl px-2 py-1 text-slate-600 hover:text-slate-900 hover:bg-white/60 transition-all" title="Jump to Specific Date">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    <input
                        type="date"
                        class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                        @change="jumpToDate($event.target.value)"
                    >
                </label>
            </div>

            <!-- Search Pill -->
            <div class="relative w-full sm:w-48">
                <input
                    type="search"
                    x-model="searchQuery"
                    placeholder="Search shop..."
                    class="h-9 w-full rounded-2xl border border-slate-200 bg-white pl-8 pr-3 text-xs font-semibold text-slate-900 shadow-xs outline-none focus:border-slate-400 focus:bg-white"
                >
                <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400"></i>
            </div>
        </div>

        <!-- Custom Date Range Picker (Accordion if Custom selected) -->
        <div x-show="timeframe === 'custom'" class="flex flex-wrap sm:flex-nowrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2.5 shadow-xs" x-cloak>
            <input type="date" x-model="startDate" class="h-8 flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900">
            <span class="text-xs font-bold text-slate-400">to</span>
            <input type="date" x-model="endDate" class="h-8 flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900">
            <button type="button" @click="loadData()" class="h-8 shrink-0 rounded-xl bg-slate-900 px-4 text-xs font-bold text-white hover:bg-slate-800">Apply</button>
        </div>

        <!-- Hero Financial Spend Card (Screenshot Match) -->
        <div class="rounded-[28px] border border-slate-100 bg-white p-4 sm:p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Consolidated Outlets</span>
                    <h2 class="mt-0.5 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight" x-text="currency(activeTotals().sales)">{{ number_format($totals['sales'], 2) }}</h2>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider" :class="activeTotals().net >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                        <span class="h-1.5 w-1.5 rounded-full" :class="activeTotals().net >= 0 ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                        <span x-text="activeTotals().net >= 0 ? 'Net Profit' : 'Net Loss'"></span>
                    </span>
                    <p class="mt-1 text-xs font-black" :class="activeTotals().net >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(activeTotals().net)">{{ number_format($totals['net'], 2) }}</p>
                </div>
            </div>

            <!-- Stylized Minimal Line Sparkline -->
            <div class="mt-4 h-24 w-full rounded-2xl bg-slate-50/70 p-2 border border-slate-100/80 relative flex items-center justify-center overflow-hidden">
                <svg viewBox="0 0 400 60" class="h-full w-full overflow-visible">
                    <defs>
                        <linearGradient id="fintechHeroGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#6366f1" stop-opacity="0.2"/>
                            <stop offset="100%" stop-color="#6366f1" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <path d="M 0,45 Q 60,15 120,35 T 240,20 T 360,30 L 400,25" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M 0,45 Q 60,15 120,35 T 240,20 T 360,30 L 400,25 L 400,60 L 0,60 Z" fill="url(#fintechHeroGrad)"/>
                    <!-- Interactive Dot Marker -->
                    <circle cx="240" cy="20" r="4.5" fill="#4f46e5" stroke="#ffffff" stroke-width="2"/>
                    <line x1="240" y1="20" x2="240" y2="60" stroke="#c7d2fe" stroke-width="1.5" stroke-dasharray="2,2"/>
                </svg>
                <div class="absolute right-3 top-2 rounded-lg bg-indigo-600 px-2 py-0.5 text-[9px] font-black text-white shadow-xs">
                    Live Velocity
                </div>
            </div>
        </div>

        <!-- 2 Side-by-Side Inflow/Outflow Cards -->
        <div class="grid grid-cols-2 gap-2.5 sm:gap-3">
            <div class="rounded-2xl border border-slate-100 bg-white p-3.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                <div class="flex items-center justify-between">
                    <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400">Total Gross Sales</span>
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                    </div>
                </div>
                <p class="mt-2 text-sm sm:text-lg font-black text-slate-900 truncate" x-text="currency(activeTotals().sales)">{{ number_format($totals['sales'], 2) }}</p>
                <div class="mt-1 flex items-center justify-between gap-1 text-[8px] sm:text-[9px] font-bold">
                    <span class="text-emerald-600 truncate">Gross inflow</span>
                    <span class="text-slate-500 bg-slate-50 px-1.5 py-0.5 rounded-md border border-slate-200/60 shrink-0">
                        <span x-text="activeTotals().count"></span> Outlets
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white p-3.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                <div class="flex items-center justify-between">
                    <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400">Total Expenses</span>
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                        <i data-lucide="arrow-down-right" class="w-3.5 h-3.5"></i>
                    </div>
                </div>
                <p class="mt-2 text-sm sm:text-lg font-black text-slate-900 truncate" x-text="currency(activeTotals().expense)">{{ number_format($totals['expense'], 2) }}</p>
                <div class="mt-1 flex items-center justify-between gap-1 text-[8px] sm:text-[9px] font-bold">
                    <span class="text-rose-600 truncate">Total outflow</span>
                    <span class="text-amber-800 bg-amber-50 px-1.5 py-0.5 rounded-md border border-amber-200/90 shrink-0 font-black" title="GL Bill portion of total expenses">
                        GL Bill: <span x-text="currency(activeTotals().gl_bills)">{{ number_format($totals['gl_bills'], 2) }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Accounts / Shops as Tiles Section (Showing 4 MUST Metrics: Sales, Expense, GL Bill, Net) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-black text-slate-900">Accounts &amp; Outlets</h3>
                <span class="text-[10px] font-bold text-slate-400"><span x-text="filteredShops().length"></span> synced shops</span>
            </div>

            <!-- Modern Ultra-Compact 3 Column Tile Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                <template x-for="(item, idx) in filteredShops()" :key="item.shop_id">
                    <div
                        @click="window.location.href = '{{ url('/admin/cashbook/mobile/ledger') }}/' + item.shop_slug + '?timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                        class="group relative flex flex-col justify-between rounded-xl border border-slate-200/90 bg-white p-3 shadow-xs transition-all hover:border-indigo-400 hover:shadow-md cursor-pointer"
                    >
                        <div>
                            <!-- Header: Icon + Name/Code + Badge (Single Row) -->
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-lg font-black text-white shadow-xs shrink-0"
                                         :class="idx % 3 === 0 ? 'bg-gradient-to-tr from-indigo-600 to-indigo-400' : (idx % 3 === 1 ? 'bg-gradient-to-tr from-blue-600 to-sky-400' : 'bg-gradient-to-tr from-teal-600 to-emerald-400')">
                                        <i data-lucide="store" class="w-3.5 h-3.5"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <!-- Clickable Shop Name -> Details Page -->
                                        <span
                                            @click.stop="window.location.href = '{{ url('/admin/cashbook/reports/shop') }}/' + item.shop_slug + '?timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                                            class="text-xs font-black text-slate-900 truncate leading-tight hover:text-indigo-600 hover:underline transition block cursor-pointer"
                                            x-text="item.shop_name"
                                            title="View Shop Details"
                                        ></span>
                                        <p class="text-[8px] font-bold uppercase text-slate-400 truncate" x-text="item.shop_code"></p>
                                    </div>
                                </div>
                                <span class="rounded-full px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wider shrink-0"
                                      :class="item.net >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                      x-text="item.net >= 0 ? 'Profit' : 'Loss'">
                                </span>
                            </div>

                            <!-- 4 Key Metrics in 2 Compact Rows (Sales, Expense, GL Bill, Net P/L) -->
                            <div class="mt-2 pt-2 border-t border-slate-100 grid grid-cols-2 gap-1.5 text-[10px]">
                                <div class="bg-slate-50/80 rounded-lg px-2 py-1 border border-slate-100">
                                    <span class="text-[7px] font-black uppercase text-slate-400 block tracking-tight">Total Sales</span>
                                    <span class="text-[11px] font-black text-slate-900 truncate block" x-text="currency(item.sales)"></span>
                                </div>
                                <div class="bg-slate-50/80 rounded-lg px-2 py-1 border border-slate-100">
                                    <span class="text-[7px] font-black uppercase text-rose-500 block tracking-tight">Expenses</span>
                                    <span class="text-[11px] font-black text-rose-600 truncate block" x-text="currency(item.expense)"></span>
                                </div>
                                <div class="bg-slate-50/80 rounded-lg px-2 py-1 border border-slate-100">
                                    <span class="text-[7px] font-black uppercase text-amber-600 block tracking-tight">GL Bill</span>
                                    <span class="text-[11px] font-black text-amber-700 truncate block" x-text="currency(item.gl_bills)"></span>
                                </div>
                                <div class="rounded-lg px-2 py-1 border" :class="item.net >= 0 ? 'bg-emerald-50/60 border-emerald-100' : 'bg-rose-50/60 border-rose-100'">
                                    <span class="text-[7px] font-black uppercase block tracking-tight" :class="item.net >= 0 ? 'text-emerald-600' : 'text-rose-600'">Net P/L</span>
                                    <span class="text-[11px] font-black truncate block" :class="item.net >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(item.net)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="filteredShops().length === 0" class="rounded-2xl border border-slate-100 bg-white p-6 text-center text-xs font-semibold text-slate-400 shadow-xs">
                No shops matched your search query.
            </div>
        </div>
    </div>
@endsection
