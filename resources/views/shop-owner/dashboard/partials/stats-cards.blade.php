<div class="grid gap-3 grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl bg-slate-950 p-4 text-white shadow-sm md:rounded-[2rem] md:p-5">
        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-emerald-300 sm:text-[11px] sm:tracking-[0.18em]">Pending Delivery</p>
        <p class="mt-1 text-xl font-black sm:text-2xl md:mt-3 md:text-3xl">{{ $stats['pending_delivery_count'] }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:rounded-[2rem] md:p-5">
        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-500 sm:text-[11px] sm:tracking-[0.18em]">Today Bill</p>
        <p class="mt-1 text-base font-black text-slate-900 sm:text-lg md:mt-3 md:text-3xl truncate" title="Rs. {{ number_format((float) $stats['today_bill_total'], 2) }}">Rs. {{ number_format((float) $stats['today_bill_total'], 2) }}</p>
        <p class="mt-1 text-xs font-bold text-slate-500">{{ $stats['today_bill_count'] }} bill{{ $stats['today_bill_count'] === 1 ? '' : 's' }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:rounded-[2rem] md:p-5">
        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-500 sm:text-[11px] sm:tracking-[0.18em]">{{ $isOwnedAccountingShop ? 'Pending Bill Approval' : 'Unpaid Bills' }}</p>
        <p class="mt-1 text-base font-black text-amber-700 sm:text-lg md:mt-3 md:text-3xl truncate" title="Rs. {{ number_format((float) $stats['pending_bill_approval_amount'], 2) }}">Rs. {{ number_format((float) $stats['pending_bill_approval_amount'], 2) }}</p>
        <p class="mt-1 text-xs font-bold text-slate-500">{{ $stats['pending_bill_approval_count'] }} bill{{ $stats['pending_bill_approval_count'] === 1 ? '' : 's' }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:rounded-[2rem] md:p-5">
        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-500 sm:text-[11px] sm:tracking-[0.18em]">{{ $isOwnedAccountingShop ? 'Today Closing' : 'Outstanding Balance' }}</p>
        <p class="mt-1 text-base font-black {{ $isOwnedAccountingShop ? 'text-emerald-700' : 'text-red-600' }} sm:text-lg md:mt-3 md:text-3xl truncate" title="Rs. {{ number_format((float) ($isOwnedAccountingShop ? $stats['today_closing_balance'] : $stats['outstanding_balance']), 2) }}">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $stats['today_closing_balance'] : $stats['outstanding_balance']), 2) }}</p>
    </div>
</div>
