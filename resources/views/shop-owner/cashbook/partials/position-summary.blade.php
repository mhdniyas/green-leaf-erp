{{-- TOP SUMMARY CARD (CASHBOOK POSITION) --}}
<div class="rounded-xl sm:rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 shadow-xs space-y-3">
    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
        <span class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-500">
            CASHBOOK POSITION
        </span>
        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Live
        </span>
    </div>

    {{-- 2 Primary Metrics Side-by-Side --}}
    <div class="grid grid-cols-2 gap-3 py-1">
        <div>
            <div class="text-[10px] sm:text-[11px] font-bold text-slate-400">Shop Balance</div>
            <div class="font-mono text-lg sm:text-2xl font-black text-slate-950 mt-0.5" id="kpi-shop-balance">
                ₹0.00
            </div>
        </div>
        <div>
            <div class="text-[10px] sm:text-[11px] font-bold text-slate-400">Today's Net Activity</div>
            <div class="font-mono text-lg sm:text-2xl font-black text-emerald-700 mt-0.5" id="kpi-today-net-activity">
                ₹0.00
            </div>
        </div>
    </div>

    {{-- Smaller Breakdown --}}
    <div class="pt-2.5 border-t border-slate-100 space-y-1.5 text-xs font-semibold">
        <div class="flex items-center justify-between text-slate-600">
            <span class="text-slate-500">Cash on Hand</span>
            <span class="font-mono font-bold text-slate-900" id="kpi-cash-held">₹0.00</span>
        </div>
        <div class="flex items-center justify-between text-slate-600">
            <span class="text-slate-500">Direct to Company</span>
            <span class="font-mono font-bold text-slate-900" id="kpi-reached-company">₹0.00</span>
        </div>
        <div class="flex items-center justify-between text-slate-600">
            <span class="text-slate-500">Petty Balance</span>
            <span class="font-mono font-bold text-slate-900" id="kpi-petty-closing">₹0.00</span>
        </div>
    </div>

    {{-- VIEW CASHBOOK REPORT BUTTON --}}
    <div class="pt-2 border-t border-slate-100">
        <button type="button" onclick="showReportView()"
                class="w-full flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer">
            <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-emerald-600"></i>
            <span>View Cashbook Report</span>
            <i data-lucide="arrow-right" class="h-3 w-3 text-slate-400 ml-auto"></i>
        </button>
    </div>
</div>
