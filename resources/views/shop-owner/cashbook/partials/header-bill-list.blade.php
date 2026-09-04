{{-- BILL-STYLE CASHBOOK HEADERS LIST --}}
<div class="space-y-2">
    <div class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-400 px-1">
        Daily Entries & Headers
    </div>

    <div id="today-headers-summary-container" class="space-y-3">
        <!-- Dynamically rendered bill sections by JS -->
    </div>

    {{-- Empty State (if no headers configured) --}}
    <div id="today-empty-state" class="rounded-xl sm:rounded-2xl border border-dashed border-slate-200 bg-white p-6 text-center space-y-2 hidden">
        <div class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-400 border border-slate-100">
            <i data-lucide="receipt" class="h-5 w-5"></i>
        </div>
        <h3 class="text-xs sm:text-sm font-black text-slate-800">No cashbook headers configured</h3>
        <p class="text-[11px] font-medium text-slate-400 max-w-xs mx-auto">
            Configure cashbook headers in Shop Settings.
        </p>
    </div>
</div>
