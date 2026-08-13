<section class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-950 text-white shadow-sm">
    <div class="p-4 sm:p-6">
        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-300">CEO cashbook report</p>
        <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Business position</h1>
                <p class="mt-2 text-sm font-semibold text-slate-300">{{ $reportRangeLabel }}</p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex">
                <a href="#report-filters" class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 px-4 text-xs font-black uppercase tracking-[0.14em] hover:bg-white/15">
                    Filter
                </a>
                <button type="button" onclick="window.print()" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-orange-500 px-4 text-xs font-black uppercase tracking-[0.14em] text-white hover:bg-orange-600">
                    Print
                </button>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 text-[10px] font-black uppercase tracking-[0.14em] text-slate-200">
            <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1">{{ ucfirst($timeframe) }}</span>
            <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1">{{ $shops->count() }} shops</span>
            <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1">{{ $clients->count() }} clients</span>
        </div>
    </div>
</section>
