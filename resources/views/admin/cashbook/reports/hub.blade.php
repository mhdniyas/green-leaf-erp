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
        window.adminReportsHub = function adminReportsHub() {
            return {
                timeframe: '{{ $timeframe }}',
                startDate: '{{ $startDate }}',
                endDate: '{{ $endDate }}',
                searchQuery: '',
                typeFilter: 'owned', // 'owned', 'direct', 'all' (default: 'owned')
                loading: false,
                totals: @json($totals),
                shopMetrics: @json($shopMetrics->values()),
                selectedShopForGraph: null,
                activeGraphPoint: null,
                chartType: 'column',
                showPendingDetails: false,

                pendingShopsList() {
                    return this.filteredShops().filter(s => (s.pending_days_count || 0) > 0);
                },

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
                    const gl_bills_count = list.reduce((sum, s) => sum + (parseInt(s.gl_bills_count || 0) || 0), 0);
                    const net = sales - expense;
                    const sales_less_gl = sales - gl_bills;
                    const sales_less_gl_pct = sales > 0 ? (sales_less_gl / sales) * 100 : 0;
                    const gl_bills_pct = sales > 0 ? (gl_bills / sales) * 100 : 0;
                    return {
                        sales: Math.round(sales * 100) / 100,
                        expense: Math.round(expense * 100) / 100,
                        net: Math.round(net * 100) / 100,
                        gl_bills: Math.round(gl_bills * 100) / 100,
                        gl_bills_count: gl_bills_count,
                        sales_less_gl: Math.round(sales_less_gl * 100) / 100,
                        sales_less_gl_pct: (sales_less_gl_pct % 1 === 0 ? sales_less_gl_pct.toFixed(0) : sales_less_gl_pct.toFixed(1)) + '%',
                        gl_bills_pct: (gl_bills_pct % 1 === 0 ? gl_bills_pct.toFixed(0) : gl_bills_pct.toFixed(1)) + '%',
                        count: list.length,
                    };
                },

                pendingShopsCount() {
                    return this.filteredShops().filter(s => (s.pending_days_count || 0) > 0).length;
                },

                totalPendingDays() {
                    return this.filteredShops().reduce((sum, s) => sum + (s.pending_days_count || 0), 0);
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
                    const yesterdayStr = '{{ today()->subDay()->toDateString() }}';

                    if (preset === 'today') {
                        this.startDate = todayStr;
                        this.endDate = todayStr;
                    } else if (preset === 'upto_yesterday') {
                        if (this.startDate > yesterdayStr || this.startDate === todayStr) {
                            this.startDate = '{{ today()->startOfMonth()->toDateString() }}';
                        }
                        this.endDate = yesterdayStr;
                        this.timeframe = 'custom';
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
                        window.activeHubTypeFilter = this.typeFilter;
                        const url = new URL(window.location.href);
                        url.searchParams.set('timeframe', this.timeframe);
                        url.searchParams.set('start_date', this.startDate);
                        url.searchParams.set('end_date', this.endDate);
                        url.searchParams.set('scope', this.typeFilter);
                        window.history.replaceState({}, '', url);

                        document.querySelectorAll('[data-nav-hub], [data-nav-charts], [data-nav-analytics], [data-nav-glbills]').forEach(el => {
                            const navUrl = new URL(el.href);
                            navUrl.searchParams.set('timeframe', this.timeframe);
                            navUrl.searchParams.set('start_date', this.startDate);
                            navUrl.searchParams.set('end_date', this.endDate);
                            navUrl.searchParams.set('scope', this.typeFilter);
                            el.href = navUrl.toString();
                        });
                    } catch (e) { }
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

                getSparklinePoints() {
                    const list = this.filteredShops();
                    if (!list || list.length === 0) return [];

                    const nets = list.map(s => parseFloat(s.net) || 0);
                    const absNets = nets.map(n => Math.abs(n));
                    const maxAbs = Math.max(...absNets, 1);

                    const minNet = Math.min(...nets, 0);
                    const maxNet = Math.max(...nets, 0);
                    const rangeNet = (maxNet - minNet) || 1;

                    return list.map((shop, i) => {
                        const net = parseFloat(shop.net) || 0;
                        const sales = parseFloat(shop.sales) || 0;
                        const expense = parseFloat(shop.expense) || 0;

                        const x = list.length > 1 ? (i / (list.length - 1)) * 360 + 20 : 200;
                        const y = 50 - ((net - minNet) / rangeNet) * 40;

                        const colHeight = Math.max(3, (Math.abs(net) / maxAbs) * 24);
                        const colY = net >= 0 ? 30 - colHeight : 30;
                        const barColor = net >= 0 ? '#10b981' : '#f43f5e';

                        const tooltipY = net >= 0 ? colY - 15 : colY + colHeight + 3;
                        const textY = net >= 0 ? colY - 6 : colY + colHeight + 12;
                        const labelPrefix = net >= 0 ? 'Max ' : 'Min ';

                        return {
                            x: Math.round(x * 10) / 10,
                            y: Math.round(y * 10) / 10,
                            colY: Math.round(colY * 10) / 10,
                            colHeight: Math.round(colHeight * 10) / 10,
                            tooltipY: Math.round(tooltipY * 10) / 10,
                            textY: Math.round(textY * 10) / 10,
                            labelPrefix,
                            barColor,
                            shop_name: shop.shop_name,
                            shop_code: shop.shop_code,
                            net,
                            sales,
                            expense,
                            margin_pct: shop.margin_pct,
                            status: shop.status
                        };
                    });
                },

                getSparklinePath() {
                    const points = this.getSparklinePoints();
                    if (points.length === 0) return '';
                    if (points.length === 1) return 'M 0,30 L 400,30';
                    return 'M ' + points.map(p => `${p.x},${p.y}`).join(' L ');
                },

                getSparklineAreaPath() {
                    const points = this.getSparklinePoints();
                    if (points.length === 0) return '';
                    if (points.length === 1) return 'M 0,30 L 400,30 L 400,60 L 0,60 Z';
                    return `M ${points[0].x},60 L ` + points.map(p => `${p.x},${p.y}`).join(' L ') + ` L ${points[points.length - 1].x},60 Z`;
                },
                getColumnBarsPath(direction) {
                    const points = this.getSparklinePoints();
                    if (!points || points.length === 0) return '';

                    const filtered = points.filter(p => direction === 'positive' ? p.net >= 0 : p.net < 0);
                    if (filtered.length === 0) return '';

                    return filtered.map(p => {
                        return `M ${p.x - 3.5},30 L ${p.x - 3.5},${p.colY} L ${p.x + 3.5},${p.colY} L ${p.x + 3.5},30 Z`;
                    }).join(' ');
                },

                getHighlightStripPath() {
                    const target = this.activeGraphPoint || this.selectedShopForGraph;
                    if (!target) return '';
                    const points = this.getSparklinePoints();
                    const match = points.find(p => p.shop_code === target.shop_code);
                    if (!match) return '';
                    return `M ${match.x - 12},3 L ${match.x + 12},3 L ${match.x + 12},57 L ${match.x - 12},57 Z`;
                },

                currency(value) {
                    const num = parseFloat(value || 0);
                    return '₹' + num.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                },

                formatSaleLessGl(item) {
                    if (!item) return '';
                    const sales = parseFloat(item.sales || 0);
                    const gl = parseFloat(item.gl_bills || 0);
                    const diff = sales - gl;
                    const pct = sales > 0 ? (diff / sales) * 100 : 0;
                    const pctFormatted = (pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(1)) + '%';
                    return this.currency(diff) + ' (' + pctFormatted + ')';
                },

                formatGlPctOfSales(item) {
                    if (!item) return '0%';
                    const sales = parseFloat(item.sales || 0);
                    const gl = parseFloat(item.gl_bills || 0);
                    const pct = sales > 0 ? (gl / sales) * 100 : 0;
                    return (pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(1)) + '%';
                },
            };
        };

        if (window.Alpine) {
            window.Alpine.data('adminReportsHub', window.adminReportsHub);
        } else {
            document.addEventListener('alpine:init', () => {
                window.Alpine.data('adminReportsHub', window.adminReportsHub);
            });
        }
    </script>

    <div class="mx-auto max-w-4xl space-y-4" x-data="adminReportsHub()" x-init="init()">
        <!-- Top Fintech Header: Full Row with Title, Switcher (Own default), & Refresh Button -->
        <div class="flex items-center justify-between gap-2 pt-1 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <h1 class="text-xl sm:text-3xl font-black tracking-tight text-slate-900 shrink-0">Finance</h1>
                
                <!-- 3-Option Shop Type Switcher (Own, Direct, All) -->
                <div class="inline-flex items-center gap-0.5 rounded-2xl bg-slate-200/70 p-0.5 sm:p-1 shrink-0">
                    <button type="button" @click="typeFilter = 'owned'; syncUrl()"
                        class="rounded-xl px-2 sm:px-3.5 py-0.5 sm:py-1 text-[10px] sm:text-xs font-extrabold transition-all"
                        :class="typeFilter === 'owned' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        Own
                    </button>
                    <button type="button" @click="typeFilter = 'direct'; syncUrl()"
                        class="rounded-xl px-2 sm:px-3.5 py-0.5 sm:py-1 text-[10px] sm:text-xs font-extrabold transition-all"
                        :class="typeFilter === 'direct' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        Direct
                    </button>
                    <button type="button" @click="typeFilter = 'all'; syncUrl()"
                        class="rounded-xl px-2 sm:px-3.5 py-0.5 sm:py-1 text-[10px] sm:text-xs font-extrabold transition-all"
                        :class="typeFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        All
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                <!-- Export Actions (Excel, PDF Share, Copy for Google Sheets) -->
                <x-export-toolbar
                    excel-url="{{ route('admin.cashbook.reports.export.excel', ['timeframe' => $timeframe, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                    pdf-url="{{ route('admin.cashbook.reports.export.pdf', ['timeframe' => $timeframe, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                    title="Shops Overview"
                    align="right"
                />

                <!-- Refresh Control -->
                <button type="button" @click="loadData()"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-2xs transition hover:bg-slate-50 shrink-0 cursor-pointer"
                    title="Refresh Live Data">
                    <span :class="loading ? 'animate-spin' : ''" class="inline-flex items-center justify-center">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    </span>
                </button>
            </div>
        </div>

        <!-- Segmented iOS Timeframe Bar (Today, Week, Month, Custom + Calendar Jump) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div
                class="inline-flex items-center max-w-full overflow-x-auto rounded-2xl bg-slate-200/70 p-1 shrink-0 gap-0.5">
                <button type="button" @click="setPreset('today')"
                    class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all"
                    :class="timeframe === 'today' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                    Today
                </button>
                <button type="button" @click="setPreset('upto_yesterday')"
                    class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all"
                    :class="(timeframe === 'upto_yesterday' || (endDate === '{{ today()->subDay()->toDateString() }}' && timeframe !== 'today')) ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    title="Show data up to yesterday">
                    Upto Y'day
                </button>
                <button type="button" @click="setPreset('weekly')"
                    class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all"
                    :class="timeframe === 'weekly' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                    Week
                </button>
                <button type="button" @click="setPreset('monthly')"
                    class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all"
                    :class="timeframe === 'monthly' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                    Month
                </button>
                <button type="button" @click="setPreset('custom')"
                    class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all"
                    :class="timeframe === 'custom' && endDate !== '{{ today()->subDay()->toDateString() }}' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                    Custom
                </button>
                <!-- Jump to Date Calendar Picker Button -->
                <label
                    class="relative flex items-center justify-center cursor-pointer rounded-xl px-2 py-1 text-slate-600 hover:text-slate-900 hover:bg-white/60 transition-all"
                    title="Jump to Specific Date">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    <input type="date" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                        @change="jumpToDate($event.target.value)">
                </label>
            </div>

            <!-- Search Pill -->
            <div class="relative w-full sm:w-48">
                <input type="search" x-model="searchQuery" placeholder="Search shop..."
                    class="h-9 w-full rounded-2xl border border-slate-200 bg-white pl-8 pr-3 text-xs font-semibold text-slate-900 shadow-xs outline-none focus:border-slate-400 focus:bg-white">
                <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400"></i>
            </div>
        </div>

        <!-- Custom Date Range Picker (Accordion if Custom selected) -->
        <div x-show="timeframe === 'custom'"
            class="flex flex-wrap sm:flex-nowrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2.5 shadow-xs"
            x-cloak>
            <input type="date" x-model="startDate"
                class="h-8 flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900">
            <span class="text-xs font-bold text-slate-400">to</span>
            <input type="date" x-model="endDate"
                class="h-8 flex-1 min-w-[120px] rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900">
            <button type="button" @click="loadData()"
                class="h-8 shrink-0 rounded-xl bg-slate-900 px-4 text-xs font-bold text-white hover:bg-slate-800">Apply</button>
        </div>

        <!-- Pending Shop Entry Alert Banner (Clickable to Expand Outlets Breakdown) -->
        <div x-show="totalPendingDays() > 0"
            class="rounded-2xl bg-amber-500/10 border border-amber-500/20 overflow-hidden transition-all shadow-2xs"
            x-cloak>
            <!-- Banner Header (Clickable) -->
            <div @click="showPendingDetails = !showPendingDetails; if (window.lucide) { $nextTick(() => lucide.createIcons()); }"
                class="p-3 flex items-center justify-between gap-2 text-xs font-bold text-amber-900 cursor-pointer select-none hover:bg-amber-500/15 transition"
                title="Click to view pending outlets details">
                <div class="flex items-center gap-2 min-w-0">
                    <span
                        class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-white shrink-0 shadow-xs">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                    </span>
                    <span class="truncate">
                        <span x-text="pendingShopsCount()"></span> <span
                            x-text="pendingShopsCount() === 1 ? 'outlet has' : 'outlets have'"></span> GL bills pending
                        daily sales entry (<span x-text="totalPendingDays()"></span> pending <span
                            x-text="totalPendingDays() === 1 ? 'day' : 'days'"></span>).
                    </span>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    <span
                        class="text-[10px] font-black uppercase text-amber-900 bg-amber-200/80 hover:bg-amber-300 transition px-2.5 py-1 rounded-full inline-flex items-center gap-1">
                        <span>Show Outlets</span>
                        <i data-lucide="chevron-down" class="w-3 h-3 transition-transform duration-200"
                            :class="{ 'rotate-180': showPendingDetails }"></i>
                    </span>
                </div>
            </div>

            <!-- Itemized Pending Outlets List (Shown when clicked) -->
            <div x-show="showPendingDetails" x-transition class="border-t border-amber-500/20 bg-amber-50/60 p-3 space-y-2">
                <div
                    class="flex items-center justify-between text-[10px] font-black uppercase text-amber-900 tracking-wider">
                    <span>Pending Outlets Breakdown</span>
                    <span><span x-text="pendingShopsList().length"></span> outlets • <span
                            x-text="totalPendingDays()"></span> total pending days</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto pr-1">
                    <template x-for="shop in pendingShopsList()" :key="shop.shop_id">
                        <div @click="window.location.href = '{{ url('/admin/cashbook/mobile/ledger') }}/' + shop.shop_slug + '?timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                            class="rounded-xl border border-amber-200/90 bg-white p-2.5 shadow-2xs hover:border-amber-400 hover:shadow-xs transition cursor-pointer flex flex-col justify-between gap-1.5"
                            title="Open shop mobile ledger">
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span
                                        class="rounded bg-amber-100 px-1.5 py-0.2 text-[8px] font-black uppercase text-amber-900 shrink-0"
                                        x-text="shop.shop_code"></span>
                                    <span class="text-xs font-black text-slate-900 truncate" x-text="shop.shop_name"></span>
                                </div>
                                <span
                                    class="rounded-lg bg-amber-500 text-white px-2 py-0.5 text-[9px] font-black shrink-0 shadow-2xs">
                                    <span x-text="shop.pending_days_count"></span> <span
                                        x-text="shop.pending_days_count === 1 ? 'day' : 'days'"></span>
                                </span>
                            </div>

                            <template x-if="shop.pending_dates && shop.pending_dates.length > 0">
                                <div
                                    class="flex items-center justify-between border-t border-slate-100 pt-1 text-[9px] font-bold text-amber-800/90">
                                    <span class="truncate">
                                        Dates: <span class="font-extrabold text-amber-950"
                                            x-text="shop.pending_dates.slice(0, 2).join(', ') + (shop.pending_dates.length > 2 ? ' +' + (shop.pending_dates.length - 2) + ' more' : '')"></span>
                                    </span>
                                    <i data-lucide="arrow-right" class="w-3 h-3 text-amber-700 shrink-0"></i>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- 2 Side-by-Side Inflow/Outflow Cards (Formatted as 1 Single Horizontal Row each) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
            <!-- 1. Total Gross Sales Card -->
            <div class="rounded-2xl border border-slate-100 bg-white p-3 sm:p-3.5 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400 block truncate">Total Gross Sales</span>
                        <p class="text-sm sm:text-base font-black text-slate-900 truncate" x-text="currency(activeTotals().sales)">{{ number_format($totals['sales'], 2) }}</p>
                    </div>
                </div>
                <span class="text-slate-500 bg-slate-50 px-2 py-0.5 rounded-md border border-slate-200/60 shrink-0 text-[9px] font-bold">
                    <span x-text="activeTotals().count"></span> Outlets
                </span>
            </div>

            <!-- 2. Total Expenses Card -->
            <div class="rounded-2xl border border-slate-100 bg-white p-3 sm:p-3.5 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                        <i data-lucide="arrow-down-right" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400 block truncate">Total Expenses</span>
                        <div class="text-sm sm:text-base font-black text-slate-900 truncate" x-text="currency(activeTotals().expense)">{{ number_format($totals['expense'], 2) }}</div>
                    </div>
                </div>
                <a :href="'{{ url('/admin/cashbook/reports/gl-bills') }}?timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                    class="text-amber-800 bg-amber-50 px-2 py-1 rounded-lg border border-amber-200/90 shrink-0 font-extrabold hover:bg-amber-100 transition inline-flex items-center gap-1 text-[9px] sm:text-[10px] whitespace-nowrap"
                    title="View Synced GL Invoices & Bills">
                    <span>GL:</span>
                    <span x-text="currency(activeTotals().gl_bills)">{{ number_format($totals['gl_bills'], 2) }}</span>
                    <span class="font-bold text-amber-700/90">(<span x-text="activeTotals().gl_bills_pct"></span>)</span>
                    <i data-lucide="arrow-right" class="w-3 h-3 ml-0.5 shrink-0"></i>
                </a>
            </div>
        </div>

        <!-- Accounts / Shops as Tiles Section (Showing 4 MUST Metrics: Sales, Expense, GL Bill, Net) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-black text-slate-900">Accounts &amp; Outlets</h3>
                <p class="text-xs font-bold text-slate-500"><span x-text="filteredShops().length"></span> outlets active</p>
            </div>

            <!-- Grid of Shop Performance Cards -->
            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="item in filteredShops()" :key="item.shop_id">
                    <div @click="window.location.href = '{{ url('/admin/cashbook/mobile/ledger') }}/' + item.shop_slug + '?timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                        class="group relative rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs transition hover:border-slate-400 hover:shadow-md cursor-pointer flex flex-col justify-between">
                        <div>
                            <!-- Header Row: Code + Name + Status -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-700 border border-slate-200"
                                            x-text="item.shop_code"></span>
                                        <template x-if="item.is_client_owned">
                                            <span
                                                class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-black uppercase text-amber-700 border border-amber-100">Client</span>
                                        </template>
                                        <template x-if="!item.is_client_owned">
                                            <span
                                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-700 border border-emerald-100">Own</span>
                                        </template>
                                    </div>
                                    <div class="mt-1 flex items-center gap-1.5 flex-wrap min-w-0">
                                        <h4 class="text-xs font-black text-slate-900 group-hover:text-emerald-700 transition truncate"
                                            x-text="item.shop_name"></h4>
                                        <span
                                            class="text-[8px] font-extrabold text-slate-600 bg-slate-100/90 border border-slate-200/80 rounded-md px-1.5 py-0.2 shrink-0 shadow-2xs inline-flex items-center gap-1"
                                            title="Sales minus GL Bill (Percentage of Total Sales)">
                                            <span
                                                class="text-[7.5px] font-bold text-slate-400 uppercase tracking-tighter">Sales-GL:</span>
                                            <span x-text="formatSaleLessGl(item)"></span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Action Quick Icon -->
                                <button type="button"
                                    @click.stop="window.location.href = '{{ url('/admin/cashbook/reports/shop') }}/' + item.shop_slug + '?timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                                    class="h-7 w-7 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-center text-slate-500 hover:bg-slate-900 hover:text-white transition shrink-0"
                                    title="View Full Ledger Detail">
                                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>

                            <!-- 4 Key Metrics in 2 Compact Rows (Sales, Expense, GL Bill, Net P/L) -->
                            <div class="mt-2 pt-2 border-t border-slate-100 grid grid-cols-2 gap-1.5 text-[10px]">
                                <div class="bg-slate-50/80 rounded-lg px-2 py-1 border border-slate-100">
                                    <span class="text-[7px] font-black uppercase text-slate-400 block tracking-tight">Total
                                        Sales</span>
                                    <span class="text-[11px] font-black text-slate-900 truncate block"
                                        x-text="currency(item.sales)"></span>
                                </div>
                                <div class="bg-slate-50/80 rounded-lg px-2 py-1 border border-slate-100">
                                    <span
                                        class="text-[7px] font-black uppercase text-rose-500 block tracking-tight">Expenses</span>
                                    <span class="text-[11px] font-black text-rose-600 truncate block"
                                        x-text="currency(item.expense)"></span>
                                </div>
                                <a :href="'{{ url('/admin/cashbook/reports/gl-bills') }}?shop_id=' + item.shop_id + '&timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                                    @click.stop
                                    class="bg-slate-50/80 hover:bg-amber-50 rounded-lg px-2 py-1 border border-slate-100 block transition cursor-pointer">
                                    <div class="flex items-center justify-between">
                                        <span
                                            class="text-[7px] font-black uppercase text-amber-600 tracking-tight hover:underline">GL
                                            Bill</span>
                                        <span
                                            class="text-[6.5px] font-black uppercase bg-amber-100 text-amber-800 px-1 rounded"
                                            x-text="formatGlPctOfSales(item)"></span>
                                    </div>
                                    <span class="text-[11px] font-black text-amber-700 truncate block"
                                        x-text="currency(item.gl_bills)"></span>
                                    <span class="text-[7.5px] font-bold text-amber-800/80 block"><span
                                            x-text="item.gl_bills_count || 0"></span> <span
                                            x-text="(item.gl_bills_count === 1 ? 'bill' : 'bills')"></span> • <span
                                            x-text="formatGlPctOfSales(item)"></span> sales</span>
                                </a>
                                <div class="rounded-lg px-2 py-1 border"
                                    :class="item.net >= 0 ? 'bg-emerald-50/60 border-emerald-100' : 'bg-rose-50/60 border-rose-100'">
                                    <span class="text-[7px] font-black uppercase block tracking-tight"
                                        :class="item.net >= 0 ? 'text-emerald-600' : 'text-rose-600'">Net P/L</span>
                                    <span class="text-[11px] font-black truncate block"
                                        :class="item.net >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                        x-text="currency(item.net)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="filteredShops().length === 0"
                class="rounded-2xl border border-slate-100 bg-white p-6 text-center text-xs font-semibold text-slate-400 shadow-xs">
                No shops matched your search query.
            </div>
        </div>

        <!-- Hero Financial Spend Card (Dynamic Fintech Dashboard - Moved to Bottom) -->
        <div class="rounded-[28px] border border-slate-100 bg-white p-4 sm:p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400"
                        x-text="selectedShopForGraph ? selectedShopForGraph.shop_name : 'Consolidated Outlets'">Consolidated
                        Outlets</span>
                    <h2 class="mt-0.5 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight"
                        x-text="selectedShopForGraph ? currency(selectedShopForGraph.sales) : currency(activeTotals().sales)">
                        {{ number_format($totals['sales'], 2) }}</h2>
                </div>
                <div class="text-right flex flex-col items-end">
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider"
                        :class="(selectedShopForGraph ? selectedShopForGraph.net : activeTotals().net) >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                        <span class="h-1.5 w-1.5 rounded-full"
                            :class="(selectedShopForGraph ? selectedShopForGraph.net : activeTotals().net) >= 0 ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                        <span
                            x-text="(selectedShopForGraph ? selectedShopForGraph.net : activeTotals().net) >= 0 ? 'Net Profit' : 'Net Loss'"></span>
                    </span>
                    <p class="mt-1 text-xs font-black"
                        :class="(selectedShopForGraph ? selectedShopForGraph.net : activeTotals().net) >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                        x-text="selectedShopForGraph ? currency(selectedShopForGraph.net) : currency(activeTotals().net)">
                        {{ number_format($totals['net'], 2) }}</p>
                    <template x-if="selectedShopForGraph">
                        <button type="button" @click="selectedShopForGraph = null"
                            class="mt-1 text-[9px] font-black text-indigo-600 hover:text-indigo-800 underline">Show
                            Total</button>
                    </template>
                </div>
            </div>

            <!-- Modern Premium Chart Type Switcher -->
            <div class="flex items-center gap-1.5 mt-3 pb-2 border-b border-slate-100">
                <button type="button"
                    @click="chartType = 'line'; if (window.lucide) { $nextTick(() => lucide.createIcons()); }"
                    class="rounded-xl px-3 py-1.5 text-[10px] font-black transition flex items-center gap-1"
                    :class="chartType === 'line' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 border border-slate-200/60 hover:bg-slate-100'">
                    <i data-lucide="line-chart" class="w-3.5 h-3.5"></i> Line
                </button>
                <button type="button"
                    @click="chartType = 'column'; if (window.lucide) { $nextTick(() => lucide.createIcons()); }"
                    class="rounded-xl px-3 py-1.5 text-[10px] font-black transition flex items-center gap-1"
                    :class="chartType === 'column' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 border border-slate-200/60 hover:bg-slate-100'">
                    <i data-lucide="bar-chart-3" class="w-3.5 h-3.5"></i> Column
                </button>
            </div>

            <!-- Stylized Minimal Dynamic Interactive Chart -->
            <div
                class="mt-4 h-32 w-full rounded-2xl bg-slate-50/70 p-3.5 border border-slate-100/80 relative flex items-center justify-center overflow-hidden">
                <!-- SVG Canvas for Crisp Vector Graphics -->
                <svg viewBox="0 0 400 60" class="h-full w-full overflow-visible pointer-events-none">
                    <defs>
                        <linearGradient id="fintechHeroGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#6366f1" stop-opacity="0.2" />
                            <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
                        </linearGradient>
                    </defs>

                    <!-- LINE CHART MODE -->
                    <g x-show="chartType === 'line'">
                        <path :d="getSparklinePath()" fill="none" stroke="#6366f1" stroke-width="2.5"
                            stroke-linecap="round" />
                        <path :d="getSparklineAreaPath()" fill="url(#fintechHeroGrad)" />
                    </g>

                    <!-- COLUMN CHART MODE -->
                    <g x-show="chartType === 'column'">
                        <!-- Center Baseline average line -->
                        <line x1="0" y1="30" x2="400" y2="30" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3,3" />

                        <!-- Soft Highlight Backdrop Strip for Active/Selected Shop -->
                        <path :d="getHighlightStripPath()" fill="#e0f2fe" opacity="0.6" />

                        <!-- Positive Green Columns -->
                        <path :d="getColumnBarsPath('positive')" fill="#10b981" class="transition-all duration-300" />

                        <!-- Negative Red Columns -->
                        <path :d="getColumnBarsPath('negative')" fill="#f43f5e" class="transition-all duration-300" />
                    </g>
                </svg>

                <!-- Transparent HTML Interaction Layer + Floating Tooltip Pills -->
                <div class="absolute inset-0 flex items-stretch justify-between px-3 pointer-events-auto">
                    <template x-for="pt in getSparklinePoints()" :key="pt.shop_code">
                        <div class="flex-1 flex flex-col items-center justify-center cursor-pointer group relative"
                            @click="selectedShopForGraph = pt" @mouseenter="activeGraphPoint = pt"
                            @mouseleave="activeGraphPoint = null">
                            <!-- Hover / Active Guide Line in Line Mode -->
                            <div x-show="chartType === 'line' && ((selectedShopForGraph && selectedShopForGraph.shop_code === pt.shop_code) || (activeGraphPoint && activeGraphPoint.shop_code === pt.shop_code))"
                                class="absolute inset-y-2 w-0.5 bg-indigo-300 border-dashed border-l border-indigo-400 pointer-events-none"
                                x-cloak></div>

                            <!-- Floating Dark Tooltip Pill -->
                            <div x-show="(selectedShopForGraph && selectedShopForGraph.shop_code === pt.shop_code) || (activeGraphPoint && activeGraphPoint.shop_code === pt.shop_code)"
                                class="absolute z-20 rounded-md bg-slate-900 px-2 py-0.5 text-[9px] font-black text-white shadow-lg pointer-events-none whitespace-nowrap transition-all"
                                :class="pt.net >= 0 ? 'top-1' : 'bottom-1'" x-cloak>
                                <span x-text="pt.labelPrefix + currency(pt.net).replace('.00', '')"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Corner Title Tag -->
                <div
                    class="absolute right-3 top-2 rounded-lg bg-indigo-600 px-2 py-0.5 text-[9px] font-black text-white shadow-xs pointer-events-none z-10">
                    <span
                        x-text="activeGraphPoint ? activeGraphPoint.shop_name : (selectedShopForGraph ? selectedShopForGraph.shop_name : 'Consolidated Outlets')"></span>
                </div>
            </div>
        </div>
    </div>
@endsection