@extends('admin.cashbook.layouts.app')

@section('title', 'Target & Profit Intelligence — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="trending-up" class="w-5 h-5 text-emerald-600"></i> Profit &amp; Target Analytics
@endsection

@section('header_subtitle')
    30-day historical weekday profitability and optimization patterns.
@endsection

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <!-- Top Fintech Header -->
        <div class="flex items-center justify-between pt-1">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Target</h1>
                <p class="text-xs font-bold text-slate-500 mt-0.5">Shop Profitability Intelligence <span class="text-slate-400 font-medium">&amp; Optimization Models</span></p>
            </div>

            <!-- Shop Selector (Tailwind Styled) -->
            <form method="GET" action="{{ route('admin.cashbook.reports.analytics') }}" class="flex items-center gap-2">
                <select name="shop_id" onchange="this.form.submit()" class="h-9 rounded-2xl border border-slate-200 bg-slate-50/80 px-3 text-xs font-black text-slate-800 shadow-xs outline-none focus:border-indigo-500 focus:bg-white cursor-pointer transition">
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
                    <p class="text-xs font-bold text-slate-400 mt-0.5">30-Day Algorithmic Day-of-Week Trend</p>
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
                        <h4 class="text-sm font-black text-slate-900 mt-0.5">30 Days</h4>
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

        <!-- Interactive Procurement & Profitability Calendar Grid -->
        <script>
            function targetCalendarAnalytics() {
                return {
                    selectedDay: null,
                    weekdayData: @json($analytics['weekday_analysis']),
                    warnings: @json($analytics['overpurchase_warnings']),

                    init() {
                        const initialDay = '{{ $analytics["slowest_profit_day"]["day"] ?? "Monday" }}';
                        this.selectDay(initialDay);
                    },

                    selectDay(dayName) {
                        this.selectedDay = this.weekdayData[dayName] || null;
                        this.$nextTick(() => {
                            if (window.lucide) window.lucide.createIcons();
                        });
                    },

                    getWarning(dayName) {
                        if (!this.warnings) return null;
                        return this.warnings.find(w => w.day === dayName);
                    }
                };
            }
        </script>

        <div class="space-y-3" x-data="targetCalendarAnalytics()" x-init="init()">
            <div class="flex items-center justify-between px-1">
                <div>
                    <h3 class="text-sm font-black text-slate-900">Procurement &amp; Profitability Calendar</h3>
                    <p class="text-[11px] font-semibold text-slate-400">Click any day to inspect details &amp; optimization warnings</p>
                </div>
                <span class="text-[10px] font-black uppercase text-rose-600 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-200/80 shrink-0">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse mr-1"></span>
                    Red = Overpurchase Risk
                </span>
            </div>

            <!-- 7-Day Interactive Calendar Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2">
                @foreach ($analytics['weekday_analysis'] as $day => $row)
                    @php
                        $isHighRisk = ($row['avg_sales'] > 500 && $row['purchase_ratio'] > 65);
                    @endphp
                    <div
                        @click="selectDay('{{ $day }}')"
                        class="relative flex flex-col justify-between rounded-2xl border p-3 cursor-pointer transition-all duration-150 select-none shadow-xs"
                        :class="{
                            'ring-2 ring-indigo-600 shadow-md': selectedDay && selectedDay.day === '{{ $day }}',
                            'bg-rose-50/90 border-rose-200 hover:border-rose-400': {{ $isHighRisk ? 'true' : 'false' }},
                            'bg-emerald-50/80 border-emerald-200 hover:border-emerald-400': {{ !$isHighRisk && $row['margin_pct'] >= 15 ? 'true' : 'false' }},
                            'bg-white border-slate-200 hover:border-slate-300': {{ !$isHighRisk && $row['margin_pct'] < 15 ? 'true' : 'false' }}
                        }"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-xs font-black" :class="{{ $isHighRisk ? "'text-rose-900'" : "'text-slate-900'" }}">{{ substr($day, 0, 3) }}</span>
                                @if ($isHighRisk)
                                    <span class="h-2 w-2 rounded-full bg-rose-500 animate-pulse" title="High Overpurchase Risk"></span>
                                @elseif ($row['margin_pct'] >= 15)
                                    <span class="h-2 w-2 rounded-full bg-emerald-500" title="Healthy Margin"></span>
                                @endif
                            </div>
                            <p class="text-[9px] font-bold text-slate-400 mt-0.5">{{ $day }}</p>
                        </div>

                        <div class="mt-3 pt-2 border-t border-slate-200/60">
                            @if ($isHighRisk)
                                <span class="block text-[9px] font-black uppercase text-rose-700 bg-rose-100/80 px-1.5 py-0.5 rounded text-center truncate">
                                    GL: {{ $row['purchase_ratio'] }}%
                                </span>
                            @else
                                <span class="block text-[9px] font-black text-slate-700 text-center truncate">
                                    ₹{{ number_format($row['avg_net'], 0) }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Selected Day Detail View Drawer -->
            <template x-if="selectedDay">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-3 transition-all" x-cloak>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div class="flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 font-black text-xs">
                                <i data-lucide="calendar" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-900" x-text="selectedDay.day + ' Analytics &amp; Details'"></h4>
                                <p class="text-[10px] font-bold text-slate-400">30-day historical weekday performance</p>
                            </div>
                        </div>

                        <span
                            class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider"
                            :class="selectedDay.purchase_ratio > 65 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                            x-text="selectedDay.purchase_ratio > 65 ? 'High Risk Day' : 'Healthy Day'"
                        ></span>
                    </div>

                    <!-- 4 Metric Cards for Selected Day -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                        <div class="rounded-xl bg-slate-50 p-2.5 border border-slate-100">
                            <span class="text-[8px] font-black uppercase text-slate-400 block">Avg Sales</span>
                            <span class="text-xs font-black text-slate-900" x-text="'₹' + Number(selectedDay.avg_sales).toLocaleString('en-IN')"></span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2.5 border border-slate-100">
                            <span class="text-[8px] font-black uppercase text-rose-500 block">Avg Expense</span>
                            <span class="text-xs font-black text-rose-600" x-text="'₹' + Number(selectedDay.avg_expense).toLocaleString('en-IN')"></span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2.5 border border-slate-100">
                            <span class="text-[8px] font-black uppercase text-amber-600 block">GL Bill</span>
                            <span class="text-xs font-black text-amber-700" x-text="'₹' + Number(selectedDay.avg_gl_bills).toLocaleString('en-IN') + ' (' + selectedDay.purchase_ratio + '%)'"></span>
                        </div>
                        <div class="rounded-xl p-2.5 border" :class="selectedDay.avg_net >= 0 ? 'bg-emerald-50/70 border-emerald-100 text-emerald-900' : 'bg-rose-50/70 border-rose-100 text-rose-900'">
                            <span class="text-[8px] font-black uppercase block" :class="selectedDay.avg_net >= 0 ? 'text-emerald-600' : 'text-rose-600'">Avg Net</span>
                            <span class="text-xs font-black" x-text="'₹' + Number(selectedDay.avg_net).toLocaleString('en-IN') + ' (' + selectedDay.margin_pct + '%)'"></span>
                        </div>
                    </div>

                    <!-- Actionable Warning Message for Selected Day -->
                    <template x-if="getWarning(selectedDay.day)">
                        <div class="flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50/90 p-3 text-rose-900 text-xs">
                            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-rose-600 mt-0.5"></i>
                            <div>
                                <span class="font-black uppercase text-rose-800 block" x-text="getWarning(selectedDay.day).title"></span>
                                <p class="mt-0.5 text-xs font-medium text-rose-700 leading-relaxed" x-text="getWarning(selectedDay.day).message"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <!-- Actionable Recommendations -->
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
