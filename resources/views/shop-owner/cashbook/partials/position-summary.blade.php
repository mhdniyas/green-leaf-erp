{{-- TODAY SUMMARY BILL CARD (Receipt-Style Cashbook Summary) --}}
<div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-xs space-y-3.5 select-none" id="today-summary-bill-card">
    {{-- Bill Header --}}
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
        <div>
            <div class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-950 flex items-center gap-1.5">
                <i data-lucide="receipt" class="h-4 w-4 text-emerald-600"></i>
                <span>TODAY SUMMARY</span>
            </div>
            <div class="text-[11px] font-bold text-slate-400 mt-0.5">
                {{ $selectedDate->format('d M Y') }}
            </div>
        </div>
        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Live
        </span>
    </div>

    {{-- Activity Breakdown (Configured Headers: Sales, Cash Purchase, Expenses, etc.) --}}
    <div id="summary-bill-headers-container" class="space-y-1 divide-y divide-slate-100">
        <!-- Dynamically populated by JS: renderSummaryBillHeaders() -->
    </div>

    {{-- TODAY NET ACTIVITY (Highlight Divider & Strongest Number) --}}
    <div class="flex items-center justify-between border-t-2 border-slate-900 pt-2.5">
        <span class="text-xs sm:text-sm font-black uppercase tracking-wide text-slate-950">
            TODAY NET ACTIVITY
        </span>
        <span class="font-mono text-base sm:text-xl font-black text-emerald-700" id="kpi-today-net-activity">
            ₹0.00
        </span>
    </div>

    {{-- MONEY POSITION Section --}}
    <div class="pt-2 space-y-1.5">
        <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">
            MONEY POSITION
        </div>
        <div class="space-y-1.5 text-xs font-semibold bg-slate-50/90 p-3 rounded-xl border border-slate-100 divide-y divide-slate-100/80">
            <div class="flex items-center justify-between text-slate-700">
                <span class="text-slate-500">Cash on Hand</span>
                <span class="font-mono font-bold text-slate-900" id="kpi-cash-held">₹0.00</span>
            </div>
            <div class="flex items-center justify-between text-slate-700 pt-1.5">
                <span class="text-slate-500">Direct to Company</span>
                <span class="font-mono font-bold text-slate-900" id="kpi-reached-company">₹0.00</span>
            </div>
            <div class="flex items-center justify-between text-slate-700 pt-1.5">
                <span class="text-slate-500">Shop Balance</span>
                <span class="font-mono font-black text-slate-950" id="kpi-shop-balance">₹0.00</span>
            </div>
            <div class="flex items-center justify-between text-slate-700 pt-1.5">
                <span class="text-slate-500">Petty Balance</span>
                <span class="font-mono font-bold text-slate-900" id="kpi-petty-closing">₹0.00</span>
            </div>
        </div>
    </div>

    {{-- BALANCE MOVEMENT Section (Only visible when active) --}}
    <div id="summary-bill-relations-container" class="pt-2 space-y-1.5 hidden">
        <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">
            BALANCE MOVEMENT
        </div>
        <div id="summary-bill-relations-content" class="bg-slate-50/90 p-3 rounded-xl border border-slate-100 text-xs font-semibold text-slate-700 space-y-1">
            <!-- Populated by JS -->
        </div>
    </div>

    {{-- Bill Footer --}}
    <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-400">
        <span id="today-entry-count">0 Entries Recorded</span>
        <span id="today-last-updated-time">Last updated {{ now()->format('g:i A') }}</span>
    </div>

    {{-- View Cashbook Report Button --}}
    <div class="pt-1">
        <button type="button" onclick="showReportView()"
                class="w-full flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 py-2 px-3 text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer">
            <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-emerald-600"></i>
            <span>View Cashbook Report &amp; History</span>
            <i data-lucide="arrow-right" class="h-3 w-3 text-slate-400 ml-auto"></i>
        </button>
    </div>
</div>
