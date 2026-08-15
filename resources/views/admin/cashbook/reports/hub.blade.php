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
                    if (!this.searchQuery.trim()) {
                        return this.shopMetrics;
                    }
                    const q = this.searchQuery.toLowerCase();
                    return this.shopMetrics.filter(s =>
                        s.shop_name.toLowerCase().includes(q) ||
                        s.shop_code.toLowerCase().includes(q)
                    );
                },

                timeframeLabel() {
                    if (this.timeframe === 'today') return 'Today';
                    if (this.timeframe === 'yesterday') return 'Yesterday';
                    if (this.timeframe === 'weekly') return 'This Week';
                    if (this.timeframe === 'monthly') return 'This Month';
                    return this.startDate + ' to ' + this.endDate;
                },

                setPreset(preset) {
                    this.timeframe = preset;
                    const todayStr = '{{ today()->toDateString() }}';
                    const yesterdayStr = '{{ today()->subDay()->toDateString() }}';

                    if (preset === 'today') {
                        this.startDate = todayStr;
                        this.endDate = todayStr;
                    } else if (preset === 'yesterday') {
                        this.startDate = yesterdayStr;
                        this.endDate = yesterdayStr;
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

                syncUrl() {
                    try {
                        const url = new URL(window.location.href);
                        url.searchParams.set('timeframe', this.timeframe);
                        url.searchParams.set('start_date', this.startDate);
                        url.searchParams.set('end_date', this.endDate);
                        window.history.replaceState({}, '', url);
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
        <!-- Top Fintech Header -->
        <div class="flex items-center justify-between pt-1">
            <div class="min-w-0 pr-2">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Finance</h1>
                <p class="text-xs font-bold text-slate-500 mt-0.5 truncate">Owned Shops Financial Cards <span class="text-slate-400 font-medium">— Live Outlets</span></p>
            </div>

            <!-- Top Right Action Controls -->
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('admin.cashbook.post-entry') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 text-white shadow-sm transition hover:bg-slate-800" title="Add New Entry">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                </a>
                <button type="button" @click="loadData()" class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-xs transition hover:bg-slate-50" title="Refresh Live Data">
                    <i data-lucide="refresh-cw" class="w-4 h-4" :class="{ 'animate-spin': loading }"></i>
                </button>
            </div>
        </div>

        <!-- Segmented iOS Timeframe Bar + Search (Responsive 2-column or stacked) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div class="inline-flex max-w-full overflow-x-auto rounded-2xl bg-slate-200/70 p-1 shrink-0">
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
                    @click="setPreset('yesterday')"
                    class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all"
                    :class="timeframe === 'yesterday' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                    Yesterday
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
        <div x-show="timeframe === 'custom'" class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2.5 shadow-xs" x-cloak>
            <input type="date" x-model="startDate" class="h-8 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900">
            <span class="text-xs font-bold text-slate-400">to</span>
            <input type="date" x-model="endDate" class="h-8 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900">
            <button type="button" @click="loadData()" class="h-8 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white hover:bg-slate-800">Apply</button>
        </div>

        <!-- Hero Financial Spend Card (Screenshot Match) -->
        <div class="rounded-[28px] border border-slate-100 bg-white p-4 sm:p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Consolidated Outlets</span>
                    <h2 class="mt-0.5 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight" x-text="currency(totals.sales)">{{ number_format($totals['sales'], 2) }}</h2>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider" :class="totals.net >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                        <span class="h-1.5 w-1.5 rounded-full" :class="totals.net >= 0 ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                        <span x-text="totals.net >= 0 ? 'Net Profit' : 'Net Loss'"></span>
                    </span>
                    <p class="mt-1 text-xs font-black" :class="totals.net >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(totals.net)">{{ number_format($totals['net'], 2) }}</p>
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

        <!-- 2 Side-by-Side Inflow/Outflow Cards (Screenshot Match) -->
        <div class="grid grid-cols-2 gap-2.5 sm:gap-3">
            <div class="rounded-2xl border border-slate-100 bg-white p-3.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                <div class="flex items-center justify-between">
                    <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400">Total Gross Sales</span>
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                    </div>
                </div>
                <p class="mt-2 text-sm sm:text-lg font-black text-slate-900 truncate" x-text="currency(totals.sales)">{{ number_format($totals['sales'], 2) }}</p>
                <span class="text-[8px] sm:text-[9px] font-bold text-emerald-600 block">Gross inflow</span>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white p-3.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                <div class="flex items-center justify-between">
                    <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400">Total Expenses</span>
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                        <i data-lucide="arrow-down-right" class="w-3.5 h-3.5"></i>
                    </div>
                </div>
                <p class="mt-2 text-sm sm:text-lg font-black text-slate-900 truncate" x-text="currency(totals.expense)">{{ number_format($totals['expense'], 2) }}</p>
                <span class="text-[8px] sm:text-[9px] font-bold text-rose-600 block">Total outflow</span>
            </div>
        </div>

        <!-- Accounts / Shops as Tiles Section (Screenshot Match) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-black text-slate-900">Accounts &amp; Outlets</h3>
                <span class="text-[10px] font-bold text-slate-400">{{ $shops->count() }} synced shops</span>
            </div>

            <!-- Modern 2 or 3 Column Tile Grid (Matching Screenshot) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 sm:gap-3">
                <template x-for="(item, idx) in filteredShops()" :key="item.shop_id">
                    <div class="group relative flex flex-col justify-between rounded-2xl border border-slate-150 bg-white p-3.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)] transition-all hover:border-indigo-300 hover:shadow-md">
                        <div>
                            <!-- Tile Header: Logo / Avatar Box + Code -->
                            <div class="flex items-start justify-between">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl font-black text-white shadow-xs shrink-0"
                                     :class="idx % 3 === 0 ? 'bg-gradient-to-tr from-indigo-600 to-indigo-400' : (idx % 3 === 1 ? 'bg-gradient-to-tr from-blue-600 to-sky-400' : 'bg-gradient-to-tr from-teal-600 to-emerald-400')">
                                    <i data-lucide="store" class="w-4 h-4"></i>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-wider shrink-0"
                                      :class="item.net >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                      x-text="item.net >= 0 ? 'Profitable' : 'Loss'">
                                </span>
                            </div>

                            <!-- Shop Info -->
                            <div class="mt-2.5">
                                <h4 class="text-xs font-black text-slate-900 truncate group-hover:text-indigo-600 transition" x-text="item.shop_name"></h4>
                                <p class="text-[9px] font-bold uppercase text-slate-400 mt-0.5 truncate" x-text="item.shop_code"></p>
                            </div>

                            <!-- Balance / Sales -->
                            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between">
                                <div>
                                    <span class="text-[8px] font-black uppercase text-slate-400 block">Sales</span>
                                    <span class="text-xs font-black text-slate-900" x-text="currency(item.sales)"></span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[8px] font-black uppercase block" :class="item.net >= 0 ? 'text-emerald-600' : 'text-rose-600'">Net P/L</span>
                                    <span class="text-xs font-black" :class="item.net >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(item.net)"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Direct Tile Actions -->
                        <div class="mt-3 flex items-center gap-1.5 pt-2 border-t border-slate-100">
                            <a
                                :href="'{{ url('/admin/cashbook/reports/shop') }}/' + item.shop_slug + '?timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                                class="flex-1 min-w-0 rounded-xl bg-slate-900 py-1.5 text-center text-[10px] font-extrabold text-white transition hover:bg-slate-800"
                            >
                                Details
                            </a>
                            <a
                                :href="'{{ url('/admin/cashbook/mobile/ledger') }}/' + item.shop_slug + '?timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                                class="flex-1 min-w-0 rounded-xl border border-slate-200 bg-slate-50 py-1.5 text-center text-[10px] font-extrabold text-slate-700 transition hover:bg-slate-100"
                            >
                                Ledger
                            </a>
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
