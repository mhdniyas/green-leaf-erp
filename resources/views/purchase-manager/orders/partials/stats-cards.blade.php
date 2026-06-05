<div class="mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <article class="purchase-manager-stat-card">
        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Monthly Spend</p>
        <p class="mt-3 text-3xl font-black text-slate-950">₹{{ number_format($thisMonthSpend, 2) }}</p>
    </article>
    <article class="purchase-manager-stat-card">
        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Orders This Month</p>
        <p class="mt-3 text-3xl font-black text-slate-950">{{ $thisMonthOrdersCount }}</p>
    </article>
    <article class="purchase-manager-stat-card">
        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Average Order</p>
        <p class="mt-3 text-3xl font-black text-slate-950">₹{{ number_format($avgOrderValue, 2) }}</p>
    </article>
    <article class="purchase-manager-stat-card">
        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Top Supplier</p>
        <p class="mt-3 text-xl font-black text-slate-950">{{ $topSupplier }}</p>
    </article>
</div>
