@extends('admin.cashbook.layouts.app')

@section('title', 'Target & Profit Intelligence — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="trending-up" class="w-5 h-5 text-emerald-600"></i> Profit &amp; Target Analytics
@endsection

@section('header_subtitle')
    60-day historical weekday profitability and optimization patterns.
@endsection

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <!-- Top Fintech Header -->
        <div class="flex items-center justify-between pt-1">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Target</h1>
                <p class="text-xs font-bold text-slate-500 mt-0.5">Shop Profitability Intelligence <span class="text-slate-400 font-medium">&amp; Optimization Models</span></p>
            </div>

            <!-- Shop Selector -->
            <form method="GET" action="{{ route('admin.cashbook.reports.analytics') }}" class="flex items-center gap-2">
                <select name="shop_id" onchange="this.form.submit()" class="h-9 rounded-2xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-xs outline-none focus:border-slate-400">
                    @foreach ($shops as $s)
                        <option value="{{ $s->shop_id }}" @selected($selectedShop?->shop_id === $s->shop_id)>
                            {{ $s->name ?: ('Shop #' . $s->shop_id) }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <!-- Target Progress Card (Matching Screenshot Right Screen "Target") -->
        <div class="rounded-[28px] border border-slate-100 bg-white p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Profit Target &amp; Velocity</span>
                    <h2 class="mt-0.5 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                        {{ $selectedShop?->name ?: 'Consolidated Outlets' }}
                    </h2>
                    <p class="text-xs font-bold text-slate-400 mt-0.5">60-Day Algorithmic Day-of-Week Trend</p>
                </div>
                <div class="text-right">
                    @if ($analytics['best_profit_day'])
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Peak: {{ $analytics['best_profit_day']['day'] }}
                        </span>
                    @endif
                </div>
            </div>

            <!-- Status Indicator Bar -->
            <div class="mt-4 space-y-2">
                <div class="flex justify-between text-[10px] font-black uppercase tracking-wider text-slate-400">
                    <span>Performance Consistency</span>
                    <span>7-Day Profit Distribution</span>
                </div>
                <div class="h-3 w-full rounded-full bg-slate-100 p-0.5 relative overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r from-teal-500 via-indigo-600 to-blue-600 transition-all" style="width: 82%"></div>
                </div>
            </div>

            <div class="mt-4 rounded-xl bg-indigo-600 px-3.5 py-2 text-white flex items-center justify-between text-xs font-black shadow-xs">
                <span>Peak Average Daily Net</span>
                <span class="text-[11px] font-extrabold text-indigo-200">
                    @if ($analytics['best_profit_day'])
                        ₹{{ number_format($analytics['best_profit_day']['avg_net'], 0) }} / day
                    @else
                        —
                    @endif
                </span>
            </div>
        </div>

        <!-- "Your Stats" 2x2 Screen (Screenshot Match) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-black text-slate-900">Your Stats</h3>
                <span class="text-[10px] font-bold text-slate-400">Intelligence metrics</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <!-- Tile 1: Best Day -->
                <div class="rounded-2xl border border-slate-150 bg-white p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex flex-col justify-between">
                    <div class="flex items-start justify-between">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <i data-lucide="award" class="w-4 h-4"></i>
                        </div>
                        <span class="text-[9px] font-black text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded-md">Top Day</span>
                    </div>
                    <div class="mt-3">
                        <span class="text-[10px] font-black uppercase text-slate-400 block">Highest Net</span>
                        <h4 class="text-sm font-black text-slate-900 mt-0.5">{{ $analytics['best_profit_day']['day'] ?? '—' }}</h4>
                        <p class="text-[10px] font-bold text-emerald-700 mt-0.5">{{ $analytics['best_profit_day']['margin_pct'] ?? 0 }}% margin</p>
                    </div>
                </div>

                <!-- Tile 2: Slow Day -->
                <div class="rounded-2xl border border-slate-150 bg-white p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex flex-col justify-between">
                    <div class="flex items-start justify-between">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                        </div>
                        <span class="text-[9px] font-black text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded-md">Slow Day</span>
                    </div>
                    <div class="mt-3">
                        <span class="text-[10px] font-black uppercase text-slate-400 block">Lowest Margin</span>
                        <h4 class="text-sm font-black text-slate-900 mt-0.5">{{ $analytics['slowest_profit_day']['day'] ?? '—' }}</h4>
                        <p class="text-[10px] font-bold text-amber-700 mt-0.5">{{ $analytics['slowest_profit_day']['margin_pct'] ?? 0 }}% margin</p>
                    </div>
                </div>

                <!-- Tile 3: Streaks / Profit Score -->
                <div class="rounded-2xl border border-slate-150 bg-white p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex flex-col justify-between">
                    <div class="flex items-start justify-between">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <i data-lucide="zap" class="w-4 h-4"></i>
                        </div>
                        <span class="text-[9px] font-black text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded-md">Pattern</span>
                    </div>
                    <div class="mt-3">
                        <span class="text-[10px] font-black uppercase text-slate-400 block">Consistency</span>
                        <h4 class="text-sm font-black text-slate-900 mt-0.5">60 Days</h4>
                        <p class="text-[10px] font-bold text-blue-600 mt-0.5">Reliable dataset</p>
                    </div>
                </div>

                <!-- Tile 4: Procurements -->
                <div class="rounded-2xl border border-slate-150 bg-white p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex flex-col justify-between">
                    <div class="flex items-start justify-between">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                            <i data-lucide="bar-chart-2" class="w-4 h-4"></i>
                        </div>
                        <span class="text-[9px] font-black text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded-md">Bills</span>
                    </div>
                    <div class="mt-3">
                        <span class="text-[10px] font-black uppercase text-slate-400 block">GL Ratio</span>
                        <h4 class="text-sm font-black text-slate-900 mt-0.5">Procurement</h4>
                        <p class="text-[10px] font-bold text-purple-600 mt-0.5">Tracked daily</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overpurchase Warnings -->
        @if (!empty($analytics['overpurchase_warnings']))
            <div class="space-y-2">
                @foreach ($analytics['overpurchase_warnings'] as $warning)
                    <div class="flex items-start gap-3 rounded-2xl border border-rose-100 bg-rose-50/70 p-4 text-rose-900 shadow-xs">
                        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 mt-0.5"></i>
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-rose-800">{{ $warning['title'] }}</h4>
                            <p class="mt-1 text-xs font-medium text-rose-700 leading-relaxed">{{ $warning['message'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Weekday Pattern Tiles (Matching Mobile Cards) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-black text-slate-900">Day-of-Week Profitability Matrix</h3>
                <span class="text-[10px] font-bold text-slate-400">7-Day Aggregation</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($analytics['weekday_analysis'] as $day => $row)
                    <div class="rounded-2xl border border-slate-150 bg-white p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)] transition hover:border-slate-300">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span class="text-xs font-black text-slate-900">{{ $day }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-wider {{ $row['margin_pct'] >= 15 ? 'bg-emerald-50 text-emerald-700' : ($row['margin_pct'] >= 0 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">
                                {{ $row['margin_pct'] }}% Margin
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-[11px] font-semibold">
                            <div class="rounded-xl bg-slate-50 p-2">
                                <span class="text-[8px] uppercase text-slate-400 block font-bold">Avg Sales</span>
                                <span class="font-black text-slate-900">₹{{ number_format($row['avg_sales'], 0) }}</span>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-2">
                                <span class="text-[8px] uppercase text-slate-400 block font-bold">Avg Expense</span>
                                <span class="font-black text-rose-600">₹{{ number_format($row['avg_expense'], 0) }}</span>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-2">
                                <span class="text-[8px] uppercase text-slate-400 block font-bold">GL Bill</span>
                                <span class="font-black text-amber-700">₹{{ number_format($row['avg_gl_bills'], 0) }}</span>
                            </div>
                            <div class="rounded-xl p-2 {{ $row['avg_net'] >= 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }}">
                                <span class="text-[8px] uppercase block font-bold {{ $row['avg_net'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">Avg Net</span>
                                <span class="font-black">
                                    {{ $row['avg_net'] >= 0 ? '+' : '' }}₹{{ number_format($row['avg_net'], 0) }}
                                </span>
                            </div>
                        </div>

                        <!-- Mini score bar -->
                        <div class="mt-3 flex items-center justify-between text-[9px] font-black text-slate-400 uppercase pt-2 border-t border-slate-100">
                            <span>Score</span>
                            <div class="h-1.5 w-20 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full {{ $row['margin_pct'] >= 15 ? 'bg-emerald-500' : ($row['margin_pct'] >= 0 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ max(5, min(100, $row['profit_score'])) }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Actionable Recommendations (Matching Screenshot Cards) -->
        <div class="space-y-2.5">
            <h3 class="text-sm font-black text-slate-900 px-1">Optimization Recommendations</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @forelse ($analytics['recommendations'] as $rec)
                    <div class="flex flex-col justify-between rounded-2xl border border-slate-150 bg-white p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                        <div>
                            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ $rec['category'] }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700">
                                    {{ $rec['badge'] }}
                                </span>
                            </div>
                            <h4 class="mt-2.5 text-xs font-black text-slate-900">{{ $rec['title'] }}</h4>
                            <p class="mt-1 text-xs font-medium text-slate-500 leading-relaxed">{{ $rec['description'] }}</p>
                        </div>
                    </div>
                @empty
                    <p class="col-span-3 rounded-2xl border border-slate-100 bg-white p-6 text-center text-xs font-semibold text-slate-400">
                        Insufficient transaction history to generate recommendations yet.
                    </p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
