<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-300">Pending Approval</p>
        <p class="mt-3 text-3xl font-black">{{ $stats['pending_approval_count'] }}</p>
    </div>
    <div class="rounded-[2rem] border border-slate-200 bg-white px-5 py-5 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Pending Delivery</p>
        <p class="mt-3 text-3xl font-black text-slate-900">{{ $stats['pending_delivery_count'] }}</p>
    </div>
    <div class="rounded-[2rem] border border-slate-200 bg-white px-5 py-5 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Delivered Orders</p>
        <p class="mt-3 text-3xl font-black text-slate-900">{{ $stats['delivered_orders_count'] }}</p>
    </div>
    <div class="rounded-[2rem] border border-slate-200 bg-white px-5 py-5 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Outstanding Balance</p>
        <p class="mt-3 text-3xl font-black text-red-600">Rs. {{ number_format((float) $stats['outstanding_balance'], 2) }}</p>
    </div>
</div>
