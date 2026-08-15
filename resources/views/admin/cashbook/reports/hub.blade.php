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
                selectedShopForGraph: null,
                activeGraphPoint: null,
                chartType: 'column',

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

                        document.querySelectorAll('[data-nav-hub], [data-nav-charts], [data-nav-analytics], [data-nav-glbills]').forEach(el => {
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
                    return `M ${points[0].x},60 L ` + points.map(p => `${p.x},${p.y}`).join(' L ') + ` L ${points[points.length-1].x},60 Z`;
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
                    <span :class="loading ? 'animate-spin' : ''" class="inline-flex items-center justify-center">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i>
                    </span>
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

        <!-- Pending Shop Entry Alert Banner -->
        <div x-show="totalPendingDays() > 0" class="rounded-2xl bg-amber-500/10 border border-amber-500/20 p-3 flex items-center justify-between gap-3 text-xs font-bold text-amber-900" x-cloak>
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-white shrink-0">
                    <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                </span>
                <span>
                    <span x-text="pendingShopsCount()"></span> <span x-text="pendingShopsCount() === 1 ? 'outlet has' : 'outlets have'"></span> GL bills pending daily sales entry (<span x-text="totalPendingDays()"></span> pending <span x-text="totalPendingDays() === 1 ? 'day' : 'days'"></span>).
                </span>
            </div>
            <span class="text-[10px] font-black uppercase text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full shrink-0">
                <span x-text="totalPendingDays()"></span> Pending
            </span>
        </div>

        <!-- Hero Financial Spend Card (Dynamic Fintech Dashboard) -->
        <div class="rounded-[28px] border border-slate-100 bg-white p-4 sm:p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400" x-text="selectedShopForGraph ? selectedShopForGraph.shop_name : 'Consolidated Outlets'">Consolidated Outlets</span>
                    <h2 class="mt-0.5 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight" x-text="selectedShopForGraph ? currency(selectedShopForGraph.sales) : currency(activeTotals().sales)">{{ number_format($totals['sales'], 2) }}</h2>
                </div>
                <div class="text-right flex flex-col items-end">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider" :class="(selectedShopForGraph ? selectedShopForGraph.net : activeTotals().net) >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                        <span class="h-1.5 w-1.5 rounded-full" :class="(selectedShopForGraph ? selectedShopForGraph.net : activeTotals().net) >= 0 ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                        <span x-text="(selectedShopForGraph ? selectedShopForGraph.net : activeTotals().net) >= 0 ? 'Net Profit' : 'Net Loss'"></span>
                    </span>
                    <p class="mt-1 text-xs font-black" :class="(selectedShopForGraph ? selectedShopForGraph.net : activeTotals().net) >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="selectedShopForGraph ? currency(selectedShopForGraph.net) : currency(activeTotals().net)">{{ number_format($totals['net'], 2) }}</p>
                    <template x-if="selectedShopForGraph">
                        <button type="button" @click="selectedShopForGraph = null" class="mt-1 text-[9px] font-black text-indigo-600 hover:text-indigo-800 underline">Show Total</button>
                    </template>
                </div>
            </div>

            <!-- Modern Premium Chart Type Switcher -->
            <div class="flex items-center gap-1.5 mt-3 pb-2 border-b border-slate-100">
                <button 
                    type="button" 
                    @click="chartType = 'line'; if (window.lucide) { $nextTick(() => lucide.createIcons()); }" 
                    class="rounded-xl px-3 py-1.5 text-[10px] font-black transition flex items-center gap-1"
                    :class="chartType === 'line' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 border border-slate-200/60 hover:bg-slate-100'"
                >
                    <i data-lucide="line-chart" class="w-3.5 h-3.5"></i> Line
                </button>
                <button 
                    type="button" 
                    @click="chartType = 'column'; if (window.lucide) { $nextTick(() => lucide.createIcons()); }" 
                    class="rounded-xl px-3 py-1.5 text-[10px] font-black transition flex items-center gap-1"
                    :class="chartType === 'column' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 border border-slate-200/60 hover:bg-slate-100'"
                >
                    <i data-lucide="bar-chart-3" class="w-3.5 h-3.5"></i> Column
                </button>
            </div>

            <!-- Stylized Minimal Dynamic Interactive Chart -->
            <div class="mt-4 h-32 w-full rounded-2xl bg-slate-50/70 p-3.5 border border-slate-100/80 relative flex items-center justify-center overflow-hidden">
                <!-- SVG Canvas for Crisp Vector Graphics -->
                <svg viewBox="0 0 400 60" class="h-full w-full overflow-visible pointer-events-none">
                    <defs>
                        <linearGradient id="fintechHeroGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#6366f1" stop-opacity="0.2"/>
                            <stop offset="100%" stop-color="#6366f1" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    
                    <!-- LINE CHART MODE -->
                    <g x-show="chartType === 'line'">
                        <path :d="getSparklinePath()" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round"/>
                        <path :d="getSparklineAreaPath()" fill="url(#fintechHeroGrad)"/>
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
                             @click="selectedShopForGraph = pt"
                             @mouseenter="activeGraphPoint = pt"
                             @mouseleave="activeGraphPoint = null"
                        >
                            <!-- Hover / Active Guide Line in Line Mode -->
                            <div x-show="chartType === 'line' && ((selectedShopForGraph && selectedShopForGraph.shop_code === pt.shop_code) || (activeGraphPoint && activeGraphPoint.shop_code === pt.shop_code))"
                                 class="absolute inset-y-2 w-0.5 bg-indigo-300 border-dashed border-l border-indigo-400 pointer-events-none"
                                 x-cloak
                            ></div>

                            <!-- Floating Dark Tooltip Pill -->
                            <div x-show="(selectedShopForGraph && selectedShopForGraph.shop_code === pt.shop_code) || (activeGraphPoint && activeGraphPoint.shop_code === pt.shop_code)"
                                 class="absolute z-20 rounded-md bg-slate-900 px-2 py-0.5 text-[9px] font-black text-white shadow-lg pointer-events-none whitespace-nowrap transition-all"
                                 :class="pt.net >= 0 ? 'top-1' : 'bottom-1'"
                                 x-cloak
                            >
                                <span x-text="pt.labelPrefix + currency(pt.net).replace('.00', '')"></span>
                            </div>
                        </div>
                    </template>
                </div>
                
                <!-- Corner Title Tag -->
                <div class="absolute right-3 top-2 rounded-lg bg-indigo-600 px-2 py-0.5 text-[9px] font-black text-white shadow-xs pointer-events-none z-10">
                    <span x-text="activeGraphPoint ? activeGraphPoint.shop_name : (selectedShopForGraph ? selectedShopForGraph.shop_name : 'Consolidated Outlets')"></span>
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
                <div class="mt-2 text-sm sm:text-lg font-black text-slate-900 truncate" x-text="currency(activeTotals().expense)">{{ number_format($totals['expense'], 2) }}</div>
                <div class="mt-1 flex items-center justify-between gap-1 text-[8px] sm:text-[9px] font-bold">
                    <span class="text-rose-600 truncate">Total outflow</span>
                    <a :href="'{{ url('/admin/cashbook/reports/gl-bills') }}?timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate" class="text-amber-800 bg-amber-50 px-1.5 py-0.5 rounded-md border border-amber-200/90 shrink-0 font-black hover:bg-amber-100 transition inline-flex items-center gap-0.5" title="View Synced GL Invoices & Bills">
                        GL Bill: <span x-text="currency(activeTotals().gl_bills)">{{ number_format($totals['gl_bills'], 2) }}</span>
                        <i data-lucide="arrow-right" class="w-2.5 h-2.5"></i>
                    </a>
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
                                      :class="item.status === 'pending' ? 'bg-amber-100 text-amber-800' : (item.net >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700')"
                                      x-text="item.status === 'pending' ? 'Pending Entry' : (item.net >= 0 ? 'Profit' : 'Loss')">
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
                                <a :href="'{{ url('/admin/cashbook/reports/gl-bills') }}?shop_id=' + item.shop_id + '&timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate" @click.stop class="bg-slate-50/80 hover:bg-amber-50 rounded-lg px-2 py-1 border border-slate-100 block transition cursor-pointer">
                                    <span class="text-[7px] font-black uppercase text-amber-600 block tracking-tight hover:underline">GL Bill</span>
                                    <span class="text-[11px] font-black text-amber-700 truncate block" x-text="currency(item.gl_bills)"></span>
                                </a>
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
