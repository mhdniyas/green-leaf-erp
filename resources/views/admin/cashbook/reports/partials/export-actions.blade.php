<section id="report-export" class="white-card rounded-3xl border border-dashed border-slate-300 p-4 shadow-sm sm:p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Exports</p>
            <h3 class="text-base font-black text-slate-950">Export current report</h3>
            <p class="mt-1 text-xs font-semibold text-slate-500">Downloads use the same period filter shown on screen.</p>
        </div>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <a href="{{ route('admin.cashbook.reports.export.csv', ['date' => $selectedDate, 'timeframe' => $timeframe, 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-xs font-black uppercase tracking-[0.14em] text-slate-700 hover:bg-slate-50">
                Export CSV
            </a>
            <a href="{{ route('admin.cashbook.reports.export.excel', ['date' => $selectedDate, 'timeframe' => $timeframe, 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-xs font-black uppercase tracking-[0.14em] text-white hover:bg-emerald-500">
                Export Excel
            </a>
        </div>
    </div>
</section>
