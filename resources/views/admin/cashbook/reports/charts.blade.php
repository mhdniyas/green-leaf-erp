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

            <!-- Shop Dropdown Selector (Tailwind Styled) -->
            <form method="GET" action="{{ route('admin.cashbook.reports.charts') }}" class="flex items-center gap-2">
                <input type="hidden" name="timeframe" value="{{ $timeframe }}">
                <input type="hidden" name="start_date" value="{{ $startDate }}">
                <input type="hidden" name="end_date" value="{{ $endDate }}">

                <select name="shop_id" onchange="this.form.submit()" class="h-9 rounded-2xl border border-slate-200 bg-slate-50/80 px-3 text-xs font-black text-slate-800 shadow-xs outline-none focus:border-indigo-500 focus:bg-white cursor-pointer transition">
                    <option value="">All Owned Outlets</option>
                    @foreach ($shops as $s)
                        <option value="{{ $s->shop_id }}" @selected($selectedShopId === $s->shop_id)>
                            {{ $s->name ?: ('Shop #' . $s->shop_id) }}
                        </option>
                    @endforeach
                </select>
            </form>
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

        <!-- Category Breakdown Grid (Matching "Your Stats" 2x2 Screen) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-black text-slate-900">Your Stats &amp; Category Outflow</h3>
                <span class="text-[10px] font-bold text-slate-400">Where outflow money is spent</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @php($totalExp = max(1, $chartData['total_expense']))
                @forelse ($chartData['expense_categories']['labels'] as $index => $label)
                    @php($amount = $chartData['expense_categories']['data'][$index] ?? 0)
                    @php($pct = round(($amount / $totalExp) * 100, 1))
                    <div class="rounded-2xl border border-slate-150 bg-white p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex flex-col justify-between">
                        <div class="flex items-start justify-between">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-indigo-600">
                                <i data-lucide="tag" class="w-4 h-4"></i>
                            </div>
                            <span class="text-[10px] font-black text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded-md">{{ $pct }}%</span>
                        </div>
                        <div class="mt-3">
                            <h4 class="text-xs font-black text-slate-900 truncate">{{ $label }}</h4>
                            <p class="text-sm font-black text-slate-800 mt-0.5">₹{{ number_format($amount, 0) }}</p>
                        </div>
                    </div>
                @empty
                    <p class="col-span-4 rounded-2xl border border-slate-100 bg-white p-6 text-center text-xs font-semibold text-slate-400">
                        No category records in this timeframe.
                    </p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
