{{-- SETTLEMENTS & BALANCE MOVEMENTS CARD (Top of Cashbook) --}}
<div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-xs space-y-3.5 select-none" id="cashbook-top-settlements-card">
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
        <div>
            <div class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-950 flex items-center gap-1.5">
                <i data-lucide="scale" class="h-4 w-4 text-emerald-600"></i>
                <span>SETTLEMENTS & BALANCE MOVEMENTS</span>
            </div>
            <div class="text-[11px] font-bold text-slate-400 mt-0.5">
                {{ $selectedDate->format('d M Y') }}
            </div>
        </div>
        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Live
        </span>
    </div>

    {{-- Dynamic Settlements & Balance Movements Cards --}}
    <div id="dashboard-settlements-breakdown" class="space-y-2.5">
        <!-- Dynamically rendered by JS: renderSettlementCardsHtml() -->
    </div>

    {{-- View Cashbook Report Button --}}
    <div class="pt-2 border-t border-slate-100">
        <button type="button" onclick="showReportView()"
                class="w-full flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 py-2 px-3 text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer">
            <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-emerald-600"></i>
            <span>View Cashbook Report &amp; History</span>
            <i data-lucide="arrow-right" class="h-3 w-3 text-slate-400 ml-auto"></i>
        </button>
    </div>
</div>

