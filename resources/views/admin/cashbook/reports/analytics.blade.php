@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Profit Intelligence — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="trending-up" class="w-5 h-5 text-emerald-600"></i> Profit Intelligence
@endsection

@section('header_subtitle')
    What you can gain from your shop — plain &amp; simple.
@endsection

@section('content')
    @php
        $intel = $intelligence;
        $shop  = $selectedShop;
        $tone  = $intel['health_tone'];   // emerald | amber | rose | slate

        $marginPct = $intel['period_sales'] > 0
            ? round(($intel['period_net'] / $intel['period_sales']) * 100, 1)
            : 0;

        // Segmented progress bar: 20 segments
        $totalSegments = 20;
        $filledSegments = (int) round(($intel['captured_pct'] / 100) * $totalSegments);

        // Hero tile bg (solid, saturated — no tints)
        $heroBg = match($tone) {
            'emerald' => 'bg-emerald-500',
            'amber'   => 'bg-amber-500',
            'rose'    => 'bg-red-500',
            default   => 'bg-slate-600',
        };
    @endphp

    {{-- Pure white page wrapper --}}
    <div class="mx-auto max-w-lg space-y-5" style="background:#ffffff;">

        {{-- ── HEADER ROW: title + shop picker ── --}}
        <div class="flex items-center justify-between pt-1">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">30-Day Overview</p>
                <h1 class="text-xl font-black tracking-tight text-slate-900 leading-tight">
                    {{ $shop ? ($shop->name ?: 'Shop #'.$shop->shop_id) : 'Select Shop' }}
                </h1>
            </div>

            {{-- Shop Selector Dropdown --}}
            <div x-data="{ open: false }" class="relative inline-block text-left">
                <button @click="open = !open" type="button"
                    class="inline-flex items-center justify-between gap-2 h-9 px-3 rounded-full bg-slate-900 text-[11px] font-black text-white shadow-sm hover:bg-slate-800 focus:outline-none transition-all cursor-pointer">
                    <i data-lucide="store" class="w-3.5 h-3.5 opacity-70"></i>
                    <span class="max-w-[80px] truncate">{{ $shop ? ($shop->code ?: 'Shop') : 'Select' }}</span>
                    <i data-lucide="chevron-down" class="w-3 h-3 opacity-60 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                </button>

                <div x-show="open" @click.away="open = false" x-cloak
                     x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 mt-2 w-60 origin-top-right rounded-2xl bg-white p-1.5 shadow-[0_20px_60px_rgba(0,0,0,0.12)] ring-1 ring-black/5 z-50 max-h-72 overflow-y-auto">
                    @foreach ($shops as $s)
                        <a href="{{ route('admin.cashbook.reports.analytics', ['shop_id' => $s->shop_id]) }}"
                           class="flex items-center justify-between rounded-xl px-3 py-2.5 text-xs font-bold transition-all {{ $shop?->shop_id === $s->shop_id ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                            <span class="truncate">{{ $s->name ?: ('Shop #' . $s->shop_id) }}</span>
                            <span class="ml-2 rounded-md px-1.5 py-0.5 text-[8px] font-black uppercase {{ $shop?->shop_id === $s->shop_id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-400' }}">
                                {{ $s->code }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @if (!$intel['has_data'])
            {{-- ── EMPTY STATE ── --}}
            <div class="rounded-[28px] bg-white p-10 text-center shadow-[0_8px_40px_rgba(0,0,0,0.06)] space-y-3">
                <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mx-auto">
                    <i data-lucide="bar-chart-2" class="w-7 h-7"></i>
                </div>
                <h3 class="text-base font-black text-slate-800">No Confirmed Transactions</h3>
                <p class="text-xs font-medium text-slate-400 max-w-xs mx-auto leading-relaxed">
                    This shop has no posted, approved, or closed ledger entries in the last 30 days. Data will appear once entries are confirmed.
                </p>
            </div>
        @else

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- HERO ROW: solid green tile (profit capture) + blue stat tile --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <div class="grid grid-cols-2 gap-3">

                {{-- LEFT: Solid saturated "Profit Capture" hero tile --}}
                <div class="rounded-[24px] {{ $heroBg }} p-5 flex flex-col justify-between min-h-[148px]">
                    <div>
                        <p class="text-[9px] font-semibold uppercase tracking-widest text-white/70">Captured</p>
                        <p class="mt-1 text-5xl font-black text-white leading-none tracking-tight">
                            {{ $intel['captured_pct'] }}<span class="text-2xl">%</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-white/80 leading-snug">
                            {{ $intel['health_badge'] }}
                        </p>
                        <p class="text-[9px] text-white/60 font-medium">of potential profit</p>
                    </div>
                </div>

                {{-- RIGHT: Blue gradient "Net Profit" tile --}}
                <div class="rounded-[24px] p-5 flex flex-col justify-between min-h-[148px]"
                     style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
                    <div class="flex items-center justify-between">
                        <p class="text-[9px] font-semibold uppercase tracking-widest text-white/70">Net Profit</p>
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
                            <i data-lucide="{{ $intel['period_net'] >= 0 ? 'trending-up' : 'trending-down' }}" class="w-3 h-3 text-white"></i>
                        </div>
                    </div>
                    <div>
                        <p class="text-3xl font-black text-white leading-tight tracking-tight">
                            ₹{{ number_format(abs($intel['period_net']), 0) }}
                        </p>
                        <p class="text-[9px] font-bold text-white/70 mt-0.5">
                            {{ $intel['period_net'] >= 0 ? 'profit' : 'loss' }} · ₹{{ number_format($intel['period_sales'], 0) }} sales
                        </p>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- SEGMENTED PROGRESS BAR + mini KPI row --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <div class="rounded-[24px] bg-white shadow-[0_4px_24px_rgba(0,0,0,0.06)] p-5 space-y-4">
                {{-- Labels --}}
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Profit capture progress</p>
                        <p class="text-sm font-black text-slate-900 mt-0.5">
                            ₹{{ number_format($intel['captured_profit'], 0) }}
                            <span class="text-slate-400 font-medium text-xs">of ₹{{ number_format($intel['potential_profit'], 0) }}</span>
                        </p>
                    </div>
                    @if ($intel['total_leakage'] > 0)
                        <div class="text-right">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Leakage</p>
                            <p class="text-sm font-black text-red-500 mt-0.5">₹{{ number_format($intel['total_leakage'], 0) }}</p>
                        </div>
                    @endif
                </div>

                {{-- Segmented pill bar --}}
                <div class="flex items-center gap-[3px]">
                    @for ($i = 0; $i < $totalSegments; $i++)
                        <div class="h-2.5 flex-1 rounded-full transition-all
                            {{ $i < $filledSegments
                                ? ($tone === 'emerald' ? 'bg-emerald-500' : ($tone === 'amber' ? 'bg-amber-500' : 'bg-red-500'))
                                : 'bg-slate-100' }}">
                        </div>
                    @endfor
                </div>

                {{-- 3 mini KPI chips in a row --}}
                <div class="grid grid-cols-3 gap-2 pt-1">
                    <div class="text-center">
                        <p class="text-base font-black text-slate-900">{{ $marginPct }}%</p>
                        <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400 mt-0.5">Margin</p>
                    </div>
                    <div class="text-center border-x border-slate-100">
                        <p class="text-base font-black text-slate-900">₹{{ number_format($intel['period_expense'], 0) }}</p>
                        <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400 mt-0.5">Expense</p>
                    </div>
                    <div class="text-center">
                        <p class="text-base font-black text-slate-900">₹{{ number_format($intel['period_sales'], 0) }}</p>
                        <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400 mt-0.5">Sales</p>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- SECTION: LEAKAGE ALERTS --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <div>
                {{-- Section label --}}
                <div class="flex items-center justify-between mb-2.5 px-0.5">
                    <p class="text-sm font-black text-slate-900">Leakage Alerts</p>
                    @if (count($intel['leak_warnings']) > 0)
                        <span class="text-[10px] font-semibold text-slate-400">{{ count($intel['leak_warnings']) }} {{ Str::plural('day', count($intel['leak_warnings'])) }} flagged</span>
                    @endif
                </div>

                @if (count($intel['leak_warnings']) > 0)
                    <div class="space-y-2.5">
                        @foreach ($intel['leak_warnings'] as $w)
                            @php $isDanger = $w['severity'] === 'danger'; @endphp
                            <div class="rounded-[20px] bg-white shadow-[0_4px_24px_rgba(0,0,0,0.06)] p-4 space-y-3">
                                {{-- Top: day name + ratio badge --}}
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <span class="text-[9px] font-black uppercase tracking-widest {{ $isDanger ? 'text-red-500' : 'text-amber-500' }}">
                                            {{ $isDanger ? 'High Risk' : 'Watch Out' }}
                                        </span>
                                        <h4 class="text-base font-black text-slate-900 mt-0.5 leading-tight">{{ $w['day'] }}s</h4>
                                        <p class="text-[10px] font-medium text-slate-400">Overpurchasing detected</p>
                                    </div>
                                    <div class="shrink-0 rounded-full {{ $isDanger ? 'bg-red-500' : 'bg-amber-500' }} px-3 py-1.5 text-[10px] font-black text-white">
                                        {{ $w['purchase_ratio'] }}% GL
                                    </div>
                                </div>

                                {{-- Segmented bar showing ratio vs baseline --}}
                                @php
                                    $maxRatio   = max($w['purchase_ratio'], $w['baseline_ratio'], 1);
                                    $baselineW  = round(($w['baseline_ratio'] / $maxRatio) * 100);
                                    $currentW   = min(100, round(($w['purchase_ratio'] / $maxRatio) * 100));
                                @endphp
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 rounded-full bg-slate-100 overflow-hidden" style="width:100%">
                                            <div class="h-full rounded-full {{ $isDanger ? 'bg-red-500' : 'bg-amber-400' }}"
                                                 style="width:{{ $currentW }}%"></div>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-[9px] font-semibold text-slate-400">
                                        <span>Usual: {{ $w['baseline_ratio'] }}%</span>
                                        <span>Now: {{ $w['purchase_ratio'] }}%</span>
                                    </div>
                                </div>

                                {{-- Plain-English action line --}}
                                <div class="rounded-2xl {{ $isDanger ? 'bg-red-50' : 'bg-amber-50' }} px-3.5 py-2.5">
                                    <p class="text-[11px] font-semibold {{ $isDanger ? 'text-red-700' : 'text-amber-700' }} leading-relaxed">
                                        Cut purchasing ~<strong>{{ $w['suggested_cut_pct'] }}%</strong> on {{ $w['day'] }}s to save
                                        <strong>₹{{ number_format($w['monthly_leakage'], 0) }}/month</strong>
                                        (₹{{ number_format($w['daily_leakage'], 0) }}/day × {{ $w['sample_days'] }} days).
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-[20px] bg-white shadow-[0_4px_24px_rgba(0,0,0,0.06)] p-4 flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-500">
                            <i data-lucide="check" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <p class="text-sm font-black text-slate-900">No Leakage Detected</p>
                            <p class="text-[11px] font-medium text-slate-400">Purchasing is balanced across all days.</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- SECTION: WEEKLY ACTION PLAN (3 cards) --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <div>
                <div class="flex items-center justify-between mb-2.5 px-0.5">
                    <p class="text-sm font-black text-slate-900">Weekly Action Plan</p>
                    <span class="text-[10px] font-semibold text-slate-400">30-day avg · min 4 data pts</span>
                </div>

                <div class="grid grid-cols-3 gap-2">

                    {{-- Best Profit Day — solid green --}}
                    <div class="rounded-[18px] bg-emerald-500 p-3 flex flex-col justify-between min-h-[120px]">
                        <div class="flex items-center justify-between gap-1">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/25">
                                <i data-lucide="star" class="w-3 h-3 text-white"></i>
                            </div>
                            <span class="text-[8px] font-black uppercase tracking-wider text-white/70 text-right leading-tight">Best<br>Profit</span>
                        </div>
                        @if ($intel['best_profit_day'])
                            <div class="mt-2">
                                <p class="text-lg font-black text-white leading-none truncate">{{ substr($intel['best_profit_day']['day'], 0, 3) }}</p>
                                <p class="text-[9px] text-white/80 font-semibold mt-1 leading-snug">
                                    ₹{{ number_format($intel['best_profit_day']['avg_net'], 0) }}
                                </p>
                                <p class="text-[8px] text-white/60 font-medium">{{ $intel['best_profit_day']['margin_pct'] }}% margin</p>
                            </div>
                        @else
                            <p class="text-[9px] font-medium text-white/60 mt-2 leading-snug">Need ≥ 4 data pts</p>
                        @endif
                    </div>

                    {{-- Risk Day — solid red --}}
                    <div class="rounded-[18px] bg-red-500 p-3 flex flex-col justify-between min-h-[120px]">
                        <div class="flex items-center justify-between gap-1">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/25">
                                <i data-lucide="alert-triangle" class="w-3 h-3 text-white"></i>
                            </div>
                            <span class="text-[8px] font-black uppercase tracking-wider text-white/70 text-right leading-tight">Risk<br>Day</span>
                        </div>
                        @if ($intel['risk_day'])
                            <div class="mt-2">
                                <p class="text-lg font-black text-white leading-none truncate">{{ substr($intel['risk_day']['day'], 0, 3) }}</p>
                                <p class="text-[9px] text-white/80 font-semibold mt-1 leading-snug">
                                    GL {{ $intel['risk_day']['purchase_ratio'] }}%
                                </p>
                                <p class="text-[8px] text-white/60 font-medium">Cut ~{{ $intel['risk_day']['suggested_cut_pct'] }}%</p>
                            </div>
                        @else
                            <p class="text-[9px] font-medium text-white/60 mt-2 leading-snug">All days balanced</p>
                        @endif
                    </div>

                    {{-- High Sales Day — blue gradient --}}
                    <div class="rounded-[18px] p-3 flex flex-col justify-between min-h-[120px]"
                         style="background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);">
                        <div class="flex items-center justify-between gap-1">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/25">
                                <i data-lucide="zap" class="w-3 h-3 text-white"></i>
                            </div>
                            <span class="text-[8px] font-black uppercase tracking-wider text-white/70 text-right leading-tight">Peak<br>Sales</span>
                        </div>
                        @if ($intel['high_sales_day'])
                            <div class="mt-2">
                                <p class="text-lg font-black text-white leading-none truncate">{{ substr($intel['high_sales_day']['day'], 0, 3) }}</p>
                                <p class="text-[9px] text-white/80 font-semibold mt-1 leading-snug">
                                    ₹{{ number_format($intel['high_sales_day']['avg_sales'], 0) }}
                                </p>
                                <p class="text-[8px] text-white/60 font-medium">stock up this day</p>
                            </div>
                        @else
                            <p class="text-[9px] font-medium text-white/60 mt-2 leading-snug">Need ≥ 4 data pts</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- SECTION: ALL 7 DAYS COMPACT TABLE --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            @if (count($intel['weekday_analysis']) > 0)
                <div>
                    <div class="flex items-center justify-between mb-2.5 px-0.5">
                        <p class="text-sm font-black text-slate-900">All 7 Days</p>
                        <span class="text-[10px] font-semibold text-slate-400">Avg per day · 30-day window</span>
                    </div>

                    <div class="rounded-[24px] bg-white shadow-[0_4px_24px_rgba(0,0,0,0.06)] overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/60">
                                        <th class="py-3 px-4 text-[9px] font-black uppercase tracking-wider text-slate-400">Day</th>
                                        <th class="py-3 px-3 text-right text-[9px] font-black uppercase tracking-wider text-slate-400">Sales</th>
                                        <th class="py-3 px-3 text-right text-[9px] font-black uppercase tracking-wider text-slate-400">GL %</th>
                                        <th class="py-3 px-3 text-right text-[9px] font-black uppercase tracking-wider text-slate-400">Net</th>
                                        <th class="py-3 px-4 text-right text-[9px] font-black uppercase tracking-wider text-slate-400">Margin</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    @foreach ($intel['weekday_analysis'] as $dayName => $row)
                                        @php
                                            $isRisk  = collect($intel['leak_warnings'])->pluck('day')->contains($dayName);
                                            $isBest  = $intel['best_profit_day'] && $intel['best_profit_day']['day'] === $dayName;
                                            $lowData = $row['sample_days'] < 4;
                                        @endphp
                                        <tr class="hover:bg-slate-50/50 transition-colors {{ $isBest ? 'bg-emerald-50/40' : ($isRisk ? 'bg-red-50/30' : '') }}">
                                            <td class="py-3 px-4">
                                                <div class="flex items-center gap-2">
                                                    {{-- Tiny colored dot indicator instead of emoji --}}
                                                    @if ($isBest)
                                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                                    @elseif ($isRisk)
                                                        <span class="h-1.5 w-1.5 rounded-full bg-red-500 shrink-0"></span>
                                                    @else
                                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-200 shrink-0"></span>
                                                    @endif
                                                    <span class="font-black text-slate-900">{{ substr($dayName, 0, 3) }}</span>
                                                    @if ($lowData)
                                                        <span class="text-[8px] font-bold text-slate-300 bg-slate-100 px-1.5 py-0.5 rounded-full">low</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 px-3 text-right font-black text-slate-800 text-[11px]">
                                                ₹{{ number_format($row['avg_sales'], 0) }}
                                            </td>
                                            <td class="py-3 px-3 text-right">
                                                <span class="font-black text-[11px] {{ $isRisk ? 'text-red-600' : 'text-slate-600' }}">
                                                    {{ $row['purchase_ratio'] }}%
                                                </span>
                                            </td>
                                            <td class="py-3 px-3 text-right font-black text-[11px] {{ $row['avg_net'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                                {{ $row['avg_net'] >= 0 ? '' : '-' }}₹{{ number_format(abs($row['avg_net']), 0) }}
                                            </td>
                                            <td class="py-3 px-4 text-right font-black text-[11px] {{ $row['margin_pct'] >= 15 ? 'text-emerald-600' : ($row['margin_pct'] >= 0 ? 'text-slate-500' : 'text-red-600') }}">
                                                {{ $row['margin_pct'] }}%
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Bottom padding so content clears the fixed nav --}}
            <div class="h-4"></div>

        @endif
    </div>
@endsection
