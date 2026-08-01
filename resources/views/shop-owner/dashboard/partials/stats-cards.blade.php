<div class="grid grid-cols-3 gap-2">
    <div class="rounded-xl bg-slate-950 p-2.5 text-white shadow-sm sm:rounded-2xl sm:p-3">
        <p class="truncate text-[8px] font-black uppercase tracking-[0.1em] text-emerald-300 sm:text-[10px] sm:tracking-[0.14em]">Delivery</p>
        <p class="mt-1 truncate text-lg font-black sm:text-2xl">{{ $stats['pending_delivery_count'] }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm sm:rounded-2xl sm:p-3">
        <p class="truncate text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px] sm:tracking-[0.14em]">Today Bill</p>
        <p class="mt-1 truncate text-sm font-black text-slate-900 sm:text-xl" title="Rs. {{ number_format((float) $stats['today_bill_total'], 2) }}">Rs. {{ number_format((float) $stats['today_bill_total'], 2) }}</p>
        <p class="mt-0.5 truncate text-[9px] font-bold text-slate-500 sm:text-xs">{{ $stats['today_bill_count'] }} bill{{ $stats['today_bill_count'] === 1 ? '' : 's' }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm sm:rounded-2xl sm:p-3">
        <p class="truncate text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px] sm:tracking-[0.14em]">{{ $isOwnedAccountingShop ? 'Pending' : 'Unpaid' }}</p>
        <p class="mt-1 truncate text-sm font-black text-amber-700 sm:text-xl" title="Rs. {{ number_format((float) $stats['pending_bill_approval_amount'], 2) }}">Rs. {{ number_format((float) $stats['pending_bill_approval_amount'], 2) }}</p>
        <p class="mt-0.5 truncate text-[9px] font-bold text-slate-500 sm:text-xs">{{ $stats['pending_bill_approval_count'] }} bill{{ $stats['pending_bill_approval_count'] === 1 ? '' : 's' }}</p>
    </div>
    <div class="col-span-3 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm sm:rounded-2xl sm:p-3 lg:col-span-1">
        <p class="truncate text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px] sm:tracking-[0.14em]">{{ $isOwnedAccountingShop ? 'Closing' : 'Outstanding' }}</p>
        <p class="mt-1 truncate text-sm font-black {{ $isOwnedAccountingShop ? 'text-emerald-700' : 'text-red-600' }} sm:text-xl" title="Rs. {{ number_format((float) ($isOwnedAccountingShop ? $stats['today_closing_balance'] : $stats['outstanding_balance']), 2) }}">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $stats['today_closing_balance'] : $stats['outstanding_balance']), 2) }}</p>
    </div>
</div>
