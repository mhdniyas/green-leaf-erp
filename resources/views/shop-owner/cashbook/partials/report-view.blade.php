{{-- DETAILED REPORT VIEW (Shown when tab=reports or toggled by user) --}}
<div id="cashbook-report-view" @class(['space-y-3 sm:space-y-4', 'hidden' => !$isReportTab])>
    {{-- Navigation & Export Top Bar --}}
    <div class="flex items-center justify-between gap-2">
        <button type="button" onclick="hideReportView()"
                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer shadow-2xs">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            <span>Back to Cashbook</span>
        </button>

        <div class="flex items-center gap-2">
            <a id="report-pdf-download-btn"
               href="{{ route('shop-owner.accounting.cashbook.pdf', ['date' => $selectedDate->toDateString(), 'timeframe' => $timeframe, 'start_date' => $startDate, 'end_date' => $endDate, 'month' => $selectedMonth ?? $selectedDate->format('Y-m')]) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                <i data-lucide="download" class="h-3.5 w-3.5 text-emerald-600"></i>
                <span>PDF Report</span>
            </a>
        </div>
    </div>

    {{-- Report Filter Controls (Day, Between Dates, Month) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 shadow-xs space-y-3">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
            <div class="flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-slate-900">
                <i data-lucide="calendar" class="h-4 w-4 text-emerald-600"></i>
                <span>REPORT TIMEFRAME</span>
            </div>
            {{-- Mode Switcher Tabs --}}
            <div class="inline-flex p-0.5 rounded-lg bg-slate-100 border border-slate-200/60 text-[11px] font-bold">
                <button type="button" onclick="switchReportTimeframe('daily')" id="timeframe-btn-daily"
                        @class(['px-2.5 py-1 rounded-md transition cursor-pointer', 'bg-white text-slate-950 shadow-2xs' => ($timeframe ?? 'daily') === 'daily', 'text-slate-500 hover:text-slate-900' => ($timeframe ?? 'daily') !== 'daily'])>
                    Day
                </button>
                <button type="button" onclick="switchReportTimeframe('custom')" id="timeframe-btn-custom"
                        @class(['px-2.5 py-1 rounded-md transition cursor-pointer', 'bg-white text-slate-950 shadow-2xs' => ($timeframe ?? 'daily') === 'custom', 'text-slate-500 hover:text-slate-900' => ($timeframe ?? 'daily') !== 'custom'])>
                    Between Dates
                </button>
                <button type="button" onclick="switchReportTimeframe('monthly')" id="timeframe-btn-monthly"
                        @class(['px-2.5 py-1 rounded-md transition cursor-pointer', 'bg-white text-slate-950 shadow-2xs' => ($timeframe ?? 'daily') === 'monthly', 'text-slate-500 hover:text-slate-900' => ($timeframe ?? 'daily') !== 'monthly'])>
                    Month
                </button>
            </div>
        </div>

        {{-- Dynamic Inputs based on mode --}}
        <form id="report-filter-form" method="GET" action="{{ route('shop-owner.cashbook.show') }}">
            <input type="hidden" name="tab" value="reports">
            <input type="hidden" name="timeframe" id="report-hidden-timeframe" value="{{ $timeframe ?? 'daily' }}">

            {{-- 1. Daily Mode --}}
            <div id="filter-input-daily" @class(['flex items-center gap-2', 'hidden' => ($timeframe ?? 'daily') !== 'daily'])>
                <div class="relative flex-1">
                    <input type="date" name="date" id="report-filter-single-date" value="{{ $selectedDate->format('Y-m-d') }}" onchange="this.form.submit()"
                           class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none cursor-pointer">
                </div>
                <button type="submit" class="h-9 px-3 rounded-xl bg-slate-950 text-white text-xs font-bold hover:bg-slate-800 transition">
                    Apply
                </button>
            </div>

            {{-- 2. Between Dates (Custom Range) --}}
            <div id="filter-input-custom" @class(['flex flex-col sm:flex-row items-stretch sm:items-center gap-2', 'hidden' => ($timeframe ?? 'daily') !== 'custom'])>
                <div class="flex items-center gap-1.5 flex-1">
                    <input type="date" name="start_date" id="report-filter-start-date" value="{{ $startDate ?? $selectedDate->format('Y-m-d') }}"
                           class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none cursor-pointer">
                    <span class="text-xs font-bold text-slate-400 shrink-0">to</span>
                    <input type="date" name="end_date" id="report-filter-end-date" value="{{ $endDate ?? $selectedDate->format('Y-m-d') }}"
                           class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none cursor-pointer">
                </div>
                <button type="submit" class="h-9 px-4 rounded-xl bg-slate-950 text-white text-xs font-bold hover:bg-slate-800 transition shrink-0">
                    Apply Range
                </button>
            </div>

            {{-- 3. Monthly Mode --}}
            <div id="filter-input-monthly" @class(['flex items-center gap-2', 'hidden' => ($timeframe ?? 'daily') !== 'monthly'])>
                <div class="relative flex-1">
                    <input type="month" name="month" id="report-filter-month" value="{{ $selectedMonth ?? $selectedDate->format('Y-m') }}" onchange="this.form.submit()"
                           class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none cursor-pointer">
                </div>
                <button type="submit" class="h-9 px-3 rounded-xl bg-slate-950 text-white text-xs font-bold hover:bg-slate-800 transition">
                    Apply Month
                </button>
            </div>
        </form>
    </div>

    {{-- Report Content Card --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <h2 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-900">
                    CASHBOOK REPORT
                </h2>
                <p class="text-[11px] font-bold text-slate-400 mt-0.5" id="report-period-label">
                    @if(($timeframe ?? 'daily') === 'monthly')
                        {{ \Carbon\Carbon::parse(($selectedMonth ?? $selectedDate->format('Y-m')).'-01')->format('F Y') }}
                    @elseif(($timeframe ?? 'daily') === 'custom')
                        {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} – {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                    @else
                        {{ $selectedDate->format('d M Y') }}
                    @endif
                </p>
            </div>
            <div class="text-right">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Net Activity</span>
                <span class="font-mono text-xs sm:text-sm font-black text-slate-900" id="report-net-activity">₹0.00</span>
            </div>
        </div>

        {{-- Bill-Style Headers Breakdown Container --}}
        <div id="report-headers-breakdown" class="space-y-4">
            <!-- Dynamically rendered by renderReportBreakdown() -->
        </div>

        {{-- Balance Movements Section (Only rendered when active) --}}
        <div id="report-relations-container" class="space-y-2 pt-2 border-t border-slate-200 hidden">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                BALANCE MOVEMENTS
            </div>
            <div id="report-relations-breakdown" class="space-y-1.5 text-xs font-semibold text-slate-700">
                <!-- Rendered by JS -->
            </div>
        </div>

        {{-- Money Position --}}
        <div class="space-y-2 pt-2 border-t border-slate-200">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                MONEY POSITION
            </div>
            <div class="space-y-1.5 text-xs font-semibold text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-100">
                <div class="flex justify-between">
                    <span class="text-slate-500">Cash on Hand</span>
                    <span class="font-mono font-bold text-slate-900" id="report-pos-held">₹0.00</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Direct to Company</span>
                    <span class="font-mono font-bold text-slate-900" id="report-pos-company">₹0.00</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Petty Balance</span>
                    <span class="font-mono font-bold text-slate-900" id="report-pos-petty">₹0.00</span>
                </div>
                <div class="flex justify-between pt-1 border-t border-slate-200 font-black text-slate-950">
                    <span>Shop Balance</span>
                    <span class="font-mono" id="report-pos-shop-bal">₹0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>
