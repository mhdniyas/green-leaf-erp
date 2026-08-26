@extends('admin.cashbook.layouts.app')

@section('title', 'Category Charts & Flow Trends — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="pie-chart" class="w-5 h-5 text-emerald-600"></i> Flow &amp; Category Charts
@endsection

@section('header_subtitle')
    Visual spending breakdown and revenue trends.
@endsection

@section('content')
    @php
        $trend = collect($chartData['daily_trend']);
        $count = $trend->count();
        $maxVal = max(100, $trend->max(fn($d) => max($d['sales'], $d['expense'])));
        
        $width = 500;
        $height = 140;
        
        $salesPoints = [];
        $expensePoints = [];
        $circles = [];
        $labels = [];
        
        foreach ($trend as $index => $day) {
            $x = $count > 1 ? ($index / ($count - 1)) * $width : 0;
            $ySales = $height - (($day['sales'] / $maxVal) * ($height - 25)) - 12;
            $yExpense = $height - (($day['expense'] / $maxVal) * ($height - 25)) - 12;
            
            $salesPoints[] = "{$x},{$ySales}";
            $expensePoints[] = "{$x},{$yExpense}";
            
            $circles[] = '<circle cx="' . $x . '" cy="' . $ySales . '" r="4" fill="#ffffff" stroke="#6366f1" stroke-width="2" />';
            $circles[] = '<circle cx="' . $x . '" cy="' . $yExpense . '" r="4" fill="#ffffff" stroke="#f43f5e" stroke-width="2" />';
            
            $labels[] = '<span>' . Carbon\Carbon::parse($day['date'])->format('d M') . '</span>';
        }
        
        $salesPath = $count > 0 ? "M " . implode(" L ", $salesPoints) : "";
        $expensePath = $count > 0 ? "M " . implode(" L ", $expensePoints) : "";
        
        $salesFillPath = $count > 0 ? "{$salesPath} L {$width},{$height} L 0,{$height} Z" : "";
        $expenseFillPath = $count > 0 ? "{$expensePath} L {$width},{$height} L 0,{$height} Z" : "";
        
        $circlesHtml = implode("\n", $circles);
        $labelsHtml = implode("\n", $labels);
        
        $totalInflow = max(1, $chartData['total_sales']);
        $totalOutflow = max(1, $chartData['total_expense']);
        $marginPct = round(($chartData['net_profit'] / $totalInflow) * 100, 1);
    @endphp

    <div class="mx-auto max-w-4xl space-y-4">
        <!-- Top Fintech Header -->
        <div class="flex items-center justify-between pt-1">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Analytics</h1>
                <p class="text-xs font-bold text-slate-500 mt-0.5">Category Distribution <span class="text-slate-400 font-medium">&amp; Cashflow Charts</span></p>
            </div>

            <!-- Custom Tailwind Shop Selector Dropdown -->
            <div x-data="{ open: false }" class="relative inline-block text-left">
                <button @click="open = !open" type="button" class="inline-flex items-center justify-between gap-2 h-10 px-3.5 rounded-2xl bg-white border border-slate-200/90 text-xs font-black text-slate-800 shadow-xs hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all cursor-pointer">
                    <div class="flex items-center gap-2">
                        <i data-lucide="store" class="w-4 h-4 text-emerald-600"></i>
                        <span>{{ $selectedShop ? ($selectedShop->name ?: 'Shop #'.$selectedShop->shop_id) : 'All Owned Outlets' }}</span>
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

                    <!-- Default option: All Owned Outlets -->
                    <a href="{{ route('admin.cashbook.reports.charts', ['timeframe' => $timeframe, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                       class="group flex items-center justify-between rounded-xl px-3 py-2 text-xs font-bold transition-all {{ !$selectedShopId ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                        <span>All Owned Outlets</span>
                        <span class="text-[9px] opacity-70">Clear</span>
                    </a>

                    <!-- Shop Options -->
                    <div class="py-1 space-y-0.5">
                        @foreach ($shops as $s)
                            <a href="{{ route('admin.cashbook.reports.charts', ['timeframe' => $timeframe, 'shop_id' => $s->shop_id, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
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
                <a href="{{ route('admin.cashbook.reports.charts', ['timeframe' => 'today', 'shop_id' => $selectedShopId]) }}" class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all {{ $timeframe === 'today' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Today</a>
                <a href="{{ route('admin.cashbook.reports.charts', ['timeframe' => 'weekly', 'shop_id' => $selectedShopId]) }}" class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all {{ $timeframe === 'weekly' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Week</a>
                <a href="{{ route('admin.cashbook.reports.charts', ['timeframe' => 'monthly', 'shop_id' => $selectedShopId]) }}" class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all {{ $timeframe === 'monthly' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Month</a>
                <!-- Jump to Date Calendar Picker -->
                <label class="relative flex items-center justify-center cursor-pointer rounded-xl px-2 py-1 text-slate-600 hover:text-slate-900 hover:bg-white/60 transition-all" title="Jump to Specific Date">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    <input
                        type="date"
                        class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                        onchange="window.location.href='{{ route('admin.cashbook.reports.charts', ['timeframe' => 'custom', 'shop_id' => $selectedShopId]) }}&start_date=' + this.value + '&end_date=' + this.value"
                    >
                </label>
            </div>

            <!-- Custom Filter Form -->
            <form method="GET" action="{{ route('admin.cashbook.reports.charts') }}" class="flex flex-wrap sm:flex-nowrap items-center gap-1.5">
                <input type="hidden" name="timeframe" value="custom">
                <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                <input type="date" name="start_date" value="{{ $startDate }}" class="h-8 flex-1 min-w-[115px] rounded-xl border border-slate-200 bg-white px-2 text-xs font-bold text-slate-900 shadow-xs">
                <span class="text-xs font-bold text-slate-400">to</span>
                <input type="date" name="end_date" value="{{ $endDate }}" class="h-8 flex-1 min-w-[115px] rounded-xl border border-slate-200 bg-white px-2 text-xs font-bold text-slate-900 shadow-xs">
                <button type="submit" class="h-8 shrink-0 rounded-xl bg-slate-900 px-3.5 text-xs font-bold text-white hover:bg-slate-800">Apply</button>
            </form>
        </div>

        @if (($chartData['pending_days_count'] ?? 0) > 0)
            <div class="rounded-2xl bg-amber-500/10 border border-amber-500/20 p-3 flex items-center justify-between gap-3 text-xs font-bold text-amber-900">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-white shrink-0">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                    </span>
                    <span>
                        {{ $chartData['pending_days_count'] }} {{ Str::plural('day', $chartData['pending_days_count']) }} with GL bills pending shop entries are excluded from charts.
                    </span>
                </div>
                <span class="text-[10px] font-black uppercase text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">
                    {{ $chartData['pending_days_count'] }} Pending
                </span>
            </div>
        @endif

        <!-- Target / Health Status Card (Screenshot Target Match) -->
        <div class="rounded-[28px] border border-slate-100 bg-white p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Target &amp; Net Efficiency</span>
                    <h2 class="mt-0.5 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                        ₹{{ number_format($chartData['net_profit'], 2) }}
                    </h2>
                    <p class="text-xs font-bold text-slate-400 mt-0.5">out of ₹{{ number_format($chartData['total_sales'], 2) }} Gross Inflow</p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $marginPct >= 0 ? 'bg-indigo-50 text-indigo-700' : 'bg-rose-50 text-rose-700' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $marginPct >= 0 ? 'bg-indigo-600' : 'bg-rose-600' }}"></span>
                        {{ $marginPct }}% Margin
                    </span>
                </div>
            </div>

            <!-- Sleek Horizontal Progress Bar with Dots (Matching Screenshot) -->
            <div class="mt-4 space-y-2">
                <div class="flex justify-between text-[10px] font-black uppercase tracking-wider text-slate-400">
                    <span>Performance Progress</span>
                    <span>₹{{ number_format(max(0, $chartData['total_sales'] - $chartData['total_expense']), 0) }} Net Retained</span>
                </div>
                <div class="h-3 w-full rounded-full bg-slate-100 p-0.5 relative overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r from-blue-500 via-indigo-600 to-violet-600 transition-all duration-500" style="width: {{ min(100, max(5, $marginPct)) }}%"></div>
                </div>
            </div>

            <div class="mt-4 rounded-xl bg-blue-600 px-3.5 py-2 text-white flex items-center justify-between text-xs font-black shadow-xs">
                <span>Healthy Cashflow Retained</span>
                <span class="text-[11px] font-extrabold text-blue-200">₹{{ number_format($chartData['net_profit'], 2) }}</span>
            </div>
        </div>

        <!-- Smooth Wave Chart (Matching Screenshot Left Screen) -->
        <div class="rounded-[28px] border border-slate-100 bg-white p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)] space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-black text-slate-900">Spending &amp; Revenue Trends</h3>
                    <p class="text-[10px] font-bold text-slate-400">Daily financial trajectory</p>
                </div>
                <!-- Legend -->
                <div class="flex items-center gap-3 text-[10px] font-black uppercase">
                    <span class="flex items-center gap-1 text-indigo-600">
                        <span class="h-2 w-2 rounded-full bg-indigo-600"></span> Sales
                    </span>
                    <span class="flex items-center gap-1 text-rose-600">
                        <span class="h-2 w-2 rounded-full bg-rose-500"></span> Expenses
                    </span>
                </div>
            </div>

            <!-- SVG Line Graph with Area Fill -->
            <div class="mt-2 rounded-2xl bg-slate-50/70 p-3 border border-slate-100/80">
                @if ($count > 0)
                    <svg viewBox="0 0 500 140" class="w-full h-auto overflow-visible">
                        <defs>
                            <linearGradient id="fintechSalesGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#6366f1" stop-opacity="0.25"/>
                                <stop offset="100%" stop-color="#6366f1" stop-opacity="0"/>
                            </linearGradient>
                            <linearGradient id="fintechExpGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.2"/>
                                <stop offset="100%" stop-color="#f43f5e" stop-opacity="0"/>
                            </linearGradient>
                        </defs>

                        <!-- Grid -->
                        <line x1="0" y1="20" x2="500" y2="20" stroke="#f1f5f9" stroke-width="1.5" />
                        <line x1="0" y1="70" x2="500" y2="70" stroke="#f1f5f9" stroke-width="1.5" />
                        <line x1="0" y1="120" x2="500" y2="120" stroke="#f1f5f9" stroke-width="1.5" />

                        <!-- Fills -->
                        <path d="{{ $salesFillPath }}" fill="url(#fintechSalesGrad)"/>
                        <path d="{{ $expenseFillPath }}" fill="url(#fintechExpGrad)"/>

                        <!-- Lines -->
                        <path d="{{ $salesPath }}" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="{{ $expensePath }}" fill="none" stroke="#f43f5e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>

                        <!-- Dots -->
                        {!! $circlesHtml !!}
                    </svg>

                    <!-- X-Axis Dates -->
                    <div class="mt-3 flex justify-between text-[8px] font-black uppercase tracking-wider text-slate-400 px-1">
                        {!! $labelsHtml !!}
                    </div>
                @else
                    <p class="p-8 text-center text-xs font-semibold text-slate-400">No trend transactions found in this period.</p>
                @endif
            </div>
        </div>

        <!-- Animated Cake Donut Graph & Category Outflow Section -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            function categoryCakeAnalytics() {
                return {
                    selectedCategory: null,
                    showModal: false,
                    detailedCategories: @json($chartData['expense_categories']['detailed'] ?? []),
                    chartLabels: @json($chartData['expense_categories']['labels'] ?? []),
                    chartData: @json($chartData['expense_categories']['data'] ?? []),
                    totalExpense: @json($chartData['total_expense'] ?? 0),
                    totalInflow: @json($chartData['total_sales'] ?? 0),
                    chartInstance: null,

                    initChart() {
                        const ctx = document.getElementById('cakeChartCanvas');
                        if (!ctx || this.chartLabels.length === 0) return;

                        const colors = [
                            '#6366f1', '#f43f5e', '#f59e0b', '#10b981', '#06b6d4',
                            '#8b5cf6', '#ec4899', '#3b82f6', '#14b8a6', '#f97316'
                        ];

                        this.chartInstance = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: this.chartLabels,
                                datasets: [{
                                    data: this.chartData,
                                    backgroundColor: colors.slice(0, this.chartLabels.length),
                                    borderWidth: 3,
                                    borderColor: '#ffffff',
                                    hoverOffset: 10
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '68%',
                                animation: {
                                    animateRotate: true,
                                    animateScale: true,
                                    duration: 1200,
                                    easing: 'easeOutQuart'
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: (context) => {
                                                const label = context.label || '';
                                                const value = context.parsed || 0;
                                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                                return ` ${label}: ₹${value.toLocaleString('en-IN')} (${pct}%)`;
                                            }
                                        }
                                    }
                                },
                                onClick: (evt, elements) => {
                                    if (elements.length > 0) {
                                        const index = elements[0].index;
                                        const label = this.chartLabels[index];
                                        this.openCategoryDetail(label);
                                    }
                                }
                            }
                        });
                    },

                    openCategoryDetail(categoryName) {
                        let item = Array.isArray(this.detailedCategories) 
                            ? this.detailedCategories.find(c => c.name === categoryName) 
                            : null;

                        if (!item) {
                            const idx = this.chartLabels.indexOf(categoryName);
                            const amt = Number(this.chartData[idx] || 0);
                            const totExp = Number(this.totalExpense || 0);
                            const totInf = Number(this.totalInflow || 0);
                            item = {
                                name: categoryName,
                                amount: amt,
                                pct: totExp > 0 ? Number(((amt / totExp) * 100).toFixed(1)) : 0,
                                inflow_pct: totInf > 0 ? Number(((amt / totInf) * 100).toFixed(1)) : null,
                                count: 1,
                                avg: amt
                            };
                        }
                        this.selectedCategory = item;
                        this.showModal = true;
                    }
                };
            }
        </script>

        <div class="space-y-3" x-data="categoryCakeAnalytics()" x-init="initChart()">
            <div class="flex items-center justify-between px-1">
                <div>
                    <h3 class="text-sm font-black text-slate-900">Your Stats &amp; Category Outflow</h3>
                    <p class="text-[11px] font-semibold text-slate-400">Click any cake slice or card to inspect category details</p>
                </div>
                <span class="text-[10px] font-black uppercase text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full border border-indigo-100 shrink-0">
                    Cake Donut Chart
                </span>
            </div>

            <!-- Cake Chart Hero Banner -->
            <div class="rounded-[28px] border border-slate-100 bg-white p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)] grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
                <!-- Donut Cake Canvas -->
                <div class="sm:col-span-5 flex justify-center relative h-52">
                    <canvas id="cakeChartCanvas"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-[9px] font-black uppercase text-slate-400">Total Outflow</span>
                        <span class="text-sm font-black text-slate-900 font-mono">₹{{ number_format($chartData['total_expense'], 0) }}</span>
                    </div>
                </div>

                <!-- Cake Graph Legend & Top Categories -->
                <div class="sm:col-span-7 space-y-2">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-400">Outflow Share Breakdown</h4>
                    <div class="space-y-1.5 max-h-44 overflow-y-auto pr-1">
                        @php
                            $totExp = max(1, $chartData['total_expense']);
                            $totInf = (float) $chartData['total_sales'];
                        @endphp
                        @foreach ($chartData['expense_categories']['labels'] as $idx => $lbl)
                            @if ($idx < 5)
                                @php
                                    $amt = (float) ($chartData['expense_categories']['data'][$idx] ?? 0);
                                    $pct = round(($amt / $totExp) * 100, 1);
                                    $infPct = $totInf > 0 ? round(($amt / $totInf) * 100, 1) : null;
                                @endphp
                                <div
                                    @click="openCategoryDetail('{{ $lbl }}')"
                                    class="flex items-center justify-between p-2 rounded-xl bg-slate-50 hover:bg-slate-100 transition cursor-pointer text-xs"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="h-2.5 w-2.5 rounded-full shrink-0" style="background-color: {{ ['#6366f1', '#f43f5e', '#f59e0b', '#10b981', '#06b6d4', '#8b5cf6'][$idx % 6] }}"></span>
                                        <span class="font-black text-slate-900 truncate">{{ $lbl }}</span>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="font-black text-slate-900 font-mono">₹{{ number_format($amt, 0) }}</span>
                                        <span class="text-[10px] font-bold text-slate-500 ml-1">
                                            ({{ $pct }}% Exp • {{ $infPct !== null ? $infPct.'% Inf' : '—' }})
                                        </span>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Outflow Category Cards Grid (Matching User Screenshot) -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @php
                    $totalExp = (float) $chartData['total_expense'];
                    $totalInf = (float) $chartData['total_sales'];
                @endphp
                @forelse ($chartData['expense_categories']['labels'] as $index => $label)
                    @php
                        $amount = (float) ($chartData['expense_categories']['data'][$index] ?? 0);
                        $pctExp = $totalExp > 0 ? round(($amount / $totalExp) * 100, 1) : 0;
                        $pctInf = $totalInf > 0 ? round(($amount / $totalInf) * 100, 1) : null;
                    @endphp
                    <div
                        @click="openCategoryDetail('{{ $label }}')"
                        class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex flex-col justify-between cursor-pointer transition hover:border-indigo-400 hover:shadow-md"
                    >
                        <div class="flex items-start justify-between gap-1">
                            <div class="flex h-7 w-7 items-center justify-center rounded-xl bg-slate-100 text-indigo-600 shrink-0">
                                <i data-lucide="tag" class="w-3.5 h-3.5"></i>
                            </div>
                            <div class="flex flex-col items-end gap-0.5">
                                <span class="text-[9px] font-black text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded-md leading-tight" title="Outflow share of total expenses">
                                    {{ $pctExp }}% <span class="font-normal text-[8px] text-rose-500">Exp</span>
                                </span>
                                <span class="text-[9px] font-black text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded-md leading-tight" title="Inflow share of total inflow">
                                    {{ $pctInf !== null ? $pctInf.'%' : '—' }} <span class="font-normal text-[8px] text-emerald-600">Inf</span>
                                </span>
                            </div>
                        </div>
                        <div class="mt-2.5">
                            <h4 class="text-xs font-black text-slate-900 truncate" title="{{ $label }}">{{ $label }}</h4>
                            <p class="text-sm font-black text-slate-900 mt-0.5 font-mono">₹{{ number_format($amount, 0) }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[9px] font-bold">
                                <span class="text-rose-600">{{ $pctExp }}% of Expenses</span>
                                <span class="text-slate-300">•</span>
                                <span class="text-emerald-700">{{ $pctInf !== null ? $pctInf.'% of Inflow' : '— Inflow' }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="col-span-2 sm:col-span-4 rounded-2xl border border-slate-100 bg-white p-6 text-center text-xs font-semibold text-slate-400">
                        No category records in this timeframe.
                    </p>
                @endforelse
            </div>

            <!-- Category Detail Modal Overlay -->
            <div
                x-show="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-cloak
            >
                <div
                    @click.away="showModal = false"
                    class="w-full max-w-md rounded-3xl bg-white p-5 shadow-2xl space-y-4 relative border border-slate-100"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 font-black">
                                <i data-lucide="pie-chart" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-black text-slate-900" x-text="selectedCategory?.name"></h3>
                                <p class="text-[10px] font-bold text-slate-400">Category Outflow Inspection</p>
                            </div>
                        </div>

                        <button @click="showModal = false" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 text-sm font-bold cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <template x-if="selectedCategory">
                        <div class="space-y-3.5">
                            <!-- Outflow Share Bar -->
                            <div>
                                <div class="flex justify-between text-xs font-black mb-1">
                                    <span class="text-slate-500 uppercase text-[9px] tracking-wider">Outflow Share</span>
                                    <span class="text-rose-600 font-mono" x-text="selectedCategory.pct + '% of total expenses'"></span>
                                </div>
                                <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-rose-500 transition-all duration-500" :style="'width: ' + Math.min(100, Math.max(5, selectedCategory.pct)) + '%'"></div>
                                </div>
                            </div>

                            <!-- Inflow Share Bar -->
                            <div>
                                <div class="flex justify-between text-xs font-black mb-1">
                                    <span class="text-slate-500 uppercase text-[9px] tracking-wider">Inflow Share</span>
                                    <span class="text-emerald-700 font-mono" x-text="selectedCategory.inflow_pct !== null ? (selectedCategory.inflow_pct + '% of total inflow') : '—'"></span>
                                </div>
                                <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-600 transition-all duration-500" :style="'width: ' + (selectedCategory.inflow_pct !== null ? Math.min(100, Math.max(5, selectedCategory.inflow_pct)) : 0) + '%'"></div>
                                </div>
                            </div>

                            <!-- 3 Stats Grid -->
                            <div class="grid grid-cols-3 gap-2 text-center pt-1">
                                <div class="rounded-2xl bg-slate-50 p-2.5 border border-slate-100">
                                    <span class="text-[8px] font-black uppercase text-slate-400 block">Total Spent</span>
                                    <span class="text-xs font-black text-slate-900 font-mono" x-text="'₹' + Number(selectedCategory.amount).toLocaleString('en-IN')"></span>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-2.5 border border-slate-100">
                                    <span class="text-[8px] font-black uppercase text-slate-400 block">Entries</span>
                                    <span class="text-xs font-black text-indigo-600 font-mono" x-text="selectedCategory.count || 1"></span>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-2.5 border border-slate-100">
                                    <span class="text-[8px] font-black uppercase text-slate-400 block">Avg Size</span>
                                    <span class="text-xs font-black text-slate-900 font-mono" x-text="'₹' + Number(selectedCategory.avg || selectedCategory.amount).toLocaleString('en-IN')"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
@endsection
