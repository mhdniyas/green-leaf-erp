{{-- DETAILED REPORT VIEW (Hidden by default or shown when tab=reports) --}}
<div id="cashbook-report-view" @class(['space-y-3 sm:space-y-4', 'hidden' => !$isReportTab])>
    <div class="flex items-center justify-between">
        <button type="button" onclick="hideReportView()"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            <span>Back to Cashbook</span>
        </button>

        <a href="{{ route('shop-owner.accounting.cashbook.pdf', ['date' => $selectedDate->toDateString()]) }}" target="_blank"
           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
            <i data-lucide="download" class="h-3.5 w-3.5 text-emerald-600"></i>
            <span>PDF Report</span>
        </a>
    </div>

    <div class="rounded-xl sm:rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <h2 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-900">CASHBOOK REPORT</h2>
                <p class="text-[11px] font-bold text-slate-400">{{ $selectedDate->format('d M Y') }}</p>
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
                    <span>Closing Shop Balance</span>
                    <span class="font-mono" id="report-pos-shop-bal">₹0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>
