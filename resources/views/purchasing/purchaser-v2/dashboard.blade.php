<x-layouts.purchaser-v2 title="Dashboard" :date="$date" :grade="$grade">
    <div class="mx-auto max-w-5xl space-y-4 sm:space-y-6">
        <!-- Top Status Banner Card -->
        <div class="relative overflow-hidden rounded-2xl sm:rounded-3xl border border-emerald-100 bg-gradient-to-br from-emerald-50/90 via-white to-teal-50/50 p-4 sm:p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                <div>
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100/80 px-2.5 py-0.5 text-[11px] font-extrabold text-emerald-800">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                        Active Business Day
                    </div>
                    <h2 class="mt-1.5 text-lg sm:text-2xl font-black tracking-tight text-slate-900">Procurement Overview</h2>
                    <p class="text-xs text-slate-500">Live aggregate counts for <span class="font-bold text-slate-700">{{ $date }}</span> &bull; Grade {{ $grade }}</p>
                </div>
                <div>
                    <a href="{{ route('purchaser-v2.daily', ['date' => $date, 'grade' => $grade]) }}"
                       class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white shadow-sm shadow-emerald-600/25 transition active:scale-95 hover:bg-emerald-700">
                        <span>Open Daily Demand</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- 5 Core KPI Grid (PWA Mobile Touch Optimized) -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <!-- 1. Intended Products -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] sm:text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Intended</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </span>
                </div>
                <div class="mt-2.5">
                    <div class="text-2xl sm:text-3xl font-black text-slate-900">{{ number_format($summary['intended_count']) }}</div>
                    <p class="mt-0.5 text-[10px] sm:text-[11px] font-medium text-slate-400">Products required</p>
                </div>
            </div>

            <!-- 2. Fulfilled -->
            <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/40 p-4 sm:p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] sm:text-[11px] font-extrabold uppercase tracking-wider text-emerald-800">Fulfilled</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </span>
                </div>
                <div class="mt-2.5">
                    <div class="text-2xl sm:text-3xl font-black text-emerald-700">{{ number_format($summary['fulfilled_count']) }}</div>
                    <p class="mt-0.5 text-[10px] sm:text-[11px] font-medium text-emerald-600/80">Completed items</p>
                </div>
            </div>

            <!-- 3. Pending -->
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50/40 p-4 sm:p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] sm:text-[11px] font-extrabold uppercase tracking-wider text-amber-800">Pending</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>
                <div class="mt-2.5">
                    <div class="text-2xl sm:text-3xl font-black text-amber-600">{{ number_format($summary['pending_count']) }}</div>
                    <p class="mt-0.5 text-[10px] sm:text-[11px] font-medium text-amber-700/80">Needing purchase</p>
                </div>
            </div>

            <!-- 4. Draft Carts -->
            <div class="rounded-2xl border border-cyan-200/80 bg-cyan-50/40 p-4 sm:p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] sm:text-[11px] font-extrabold uppercase tracking-wider text-cyan-800">Draft Carts</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-cyan-100 text-cyan-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </span>
                </div>
                <div class="mt-2.5">
                    <div class="text-2xl sm:text-3xl font-black text-cyan-700">{{ number_format($summary['draft_carts_count']) }}</div>
                    <p class="mt-0.5 text-[10px] sm:text-[11px] font-medium text-cyan-700/80">In-progress carts</p>
                </div>
            </div>

            <!-- 5. Today Spend -->
            <div class="col-span-2 sm:col-span-1 rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] sm:text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Spend Today</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>
                <div class="mt-2.5">
                    <div class="text-xl sm:text-2xl font-black text-slate-900">₹{{ number_format($summary['today_purchased_amount'], 2) }}</div>
                    <p class="mt-0.5 text-[10px] sm:text-[11px] font-medium text-slate-400">Submitted carts</p>
                </div>
            </div>
        </div>

        <!-- Quantity Progress Card -->
        <div class="rounded-2xl sm:rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xs sm:text-sm font-extrabold uppercase tracking-wider text-slate-500">Total Quantity Progress</h3>
                    <p class="text-xs text-slate-400">Approved demands vs purchases recorded today</p>
                </div>
                @php
                    $percentage = $summary['total_approved_qty'] > 0
                        ? min(100, round(($summary['total_purchased_qty'] / $summary['total_approved_qty']) * 100))
                        : 0;
                @endphp
                <span class="rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-black text-emerald-700">
                    {{ $percentage }}% Complete
                </span>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400">Total Approved</p>
                    <p class="text-xl sm:text-2xl font-black text-slate-900">{{ number_format($summary['total_approved_qty'], 1) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-[11px] font-bold text-slate-400">Total Bought</p>
                    <p class="text-xl sm:text-2xl font-black text-emerald-600">{{ number_format($summary['total_purchased_qty'], 1) }}</p>
                </div>
            </div>

            <div class="mt-3">
                <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
                </div>
            </div>
        </div>

        <!-- Direct Navigation Banner -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 font-black">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div>
                    <h4 class="text-xs sm:text-sm font-black text-slate-900">Ready to buy products?</h4>
                    <p class="text-[11px] text-slate-500">Go to Daily Demand to review and purchase items.</p>
                </div>
            </div>
            <a href="{{ route('purchaser-v2.daily', ['date' => $date, 'grade' => $grade]) }}"
               class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-800 active:scale-95">
                View Demand &rarr;
            </a>
        </div>
    </div>
</x-layouts.purchaser-v2>
