<div class="grid gap-3 grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl bg-slate-950 p-4 text-white shadow-sm md:rounded-[2rem] md:p-5">
        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-emerald-300 sm:text-[11px] sm:tracking-[0.18em]">Pending Approval</p>
        <p class="mt-1 text-xl font-black sm:text-2xl md:mt-3 md:text-3xl">{{ $stats['pending_approval_count'] }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:rounded-[2rem] md:p-5">
        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-500 sm:text-[11px] sm:tracking-[0.18em]">Pending Delivery</p>
        <p class="mt-1 text-xl font-black text-slate-900 sm:text-2xl md:mt-3 md:text-3xl">{{ $stats['pending_delivery_count'] }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:rounded-[2rem] md:p-5">
        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-500 sm:text-[11px] sm:tracking-[0.18em]">Delivered Orders</p>
        <p class="mt-1 text-xl font-black text-slate-900 sm:text-2xl md:mt-3 md:text-3xl">{{ $stats['delivered_orders_count'] }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:rounded-[2rem] md:p-5">
        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-500 sm:text-[11px] sm:tracking-[0.18em]">Outstanding Balance</p>
        <p class="mt-1 text-base font-black text-red-600 sm:text-lg md:mt-3 md:text-3xl truncate" title="Rs. {{ number_format((float) $stats['outstanding_balance'], 2) }}">Rs. {{ number_format((float) $stats['outstanding_balance'], 2) }}</p>
    </div>
</div>
