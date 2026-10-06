{{-- SETTLEMENTS & BALANCE MOVEMENTS CARD (Top of Cashbook) --}}
<div class="rounded-2xl border border-slate-200/90 bg-white p-3.5 sm:p-5 shadow-xs space-y-3 select-none" id="cashbook-top-settlements-card">
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5 gap-2">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 shadow-2xs shrink-0">
                <i data-lucide="scale" class="h-4 w-4"></i>
            </div>
            <div class="min-w-0">
                <div class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-950 truncate leading-tight">
                    SETTLEMENTS &amp; MOVEMENTS
                </div>
                <div class="text-[10px] sm:text-[11px] font-semibold text-slate-400 mt-0.5">
                    {{ $selectedDate->format('d M Y') }} &middot; Daily Balances
                </div>
            </div>
        </div>
        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700 shrink-0">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Live
        </span>
    </div>

    {{-- Dynamic Settlements & Balance Movements Cards --}}
    <div id="dashboard-settlements-breakdown" class="space-y-2">
        <!-- Dynamically rendered by JS: renderSettlementCardsHtml() -->
    </div>

    {{-- View Cashbook Report Button --}}
    <div class="pt-2 border-t border-slate-100">
        <button type="button" onclick="showReportView()"
                class="w-full flex items-center justify-between rounded-xl border border-slate-200/90 bg-slate-50/80 hover:bg-emerald-50/60 hover:border-emerald-300 py-2.5 px-3 text-xs font-bold text-slate-700 hover:text-emerald-800 transition cursor-pointer group shadow-2xs">
            <div class="flex items-center gap-2 min-w-0">
                <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-emerald-600 shrink-0"></i>
                <span class="truncate">View Cashbook Report &amp; History</span>
            </div>
            <i data-lucide="arrow-right" class="h-3.5 w-3.5 text-slate-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-transform shrink-0 ml-2"></i>
        </button>
    </div>
</div>

