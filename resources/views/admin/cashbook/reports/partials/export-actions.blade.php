<section id="report-export" class="white-card rounded-3xl border border-dashed border-slate-300 p-4 shadow-sm sm:p-5 print:hidden">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Exports &amp; Sharing</p>
            <h3 class="text-base font-black text-slate-950">Export &amp; Share current report</h3>
            <p class="mt-1 text-xs font-semibold text-slate-500">Exports apply the active period filter shown on screen.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-export-toolbar
                excel-url="{{ route('admin.cashbook.reports.export.excel', ['date' => $selectedDate, 'timeframe' => $timeframe, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                title="Cashbook Report"
                align="right"
            />
        </div>
    </div>
</section>

