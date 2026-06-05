<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Order Reference</p>
            <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $order->order_number }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $order->business_date->format('d F Y') }}</p>
        </div>
        @include('shop-owner.finance.partials.payment-status-badge', ['order' => $order])
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-4">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Paid</p>
            <p class="mt-2 text-2xl font-black text-emerald-700">Rs. {{ number_format((float) $order->cash_collected, 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Balance</p>
            <p class="mt-2 text-2xl font-black text-red-600">Rs. {{ number_format((float) $order->balance_amount, 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shortage</p>
            <p class="mt-2 text-2xl font-black text-amber-600">Rs. {{ number_format((float) $order->total_shortage_value, 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Variance</p>
            <p class="mt-2 text-2xl font-black text-slate-900">Rs. {{ number_format((float) abs((float) $order->cash_discrepancy), 2) }}</p>
        </div>
    </div>
</section>
