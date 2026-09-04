{{-- TODAY'S ACTIVITY ROW (Compact tappable day summary) --}}
<div onclick="showReportView()" class="group rounded-xl border border-slate-200 bg-white p-3 shadow-xs hover:border-slate-300 hover:bg-slate-50/70 transition cursor-pointer flex items-center justify-between gap-3 select-none">
    <div class="min-w-0">
        <div class="text-xs sm:text-sm font-black text-slate-900 uppercase">
            {{ $selectedDate->format('d M') }}
        </div>
        <div class="text-[10px] sm:text-[11px] font-bold text-slate-400" id="today-entry-count">
            0 Entries
        </div>
    </div>

    <div class="flex items-center gap-4 sm:gap-6 text-right shrink-0">
        <div>
            <div class="text-[9px] sm:text-[10px] font-black uppercase text-rose-500 tracking-wider">OUT</div>
            <div class="font-mono text-xs sm:text-sm font-black text-rose-700" id="today-row-out">₹0.00</div>
        </div>
        <div>
            <div class="text-[9px] sm:text-[10px] font-black uppercase text-emerald-600 tracking-wider">IN</div>
            <div class="font-mono text-xs sm:text-sm font-black text-emerald-700" id="today-row-in">₹0.00</div>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400 group-hover:text-slate-600 transition shrink-0"></i>
    </div>
</div>
