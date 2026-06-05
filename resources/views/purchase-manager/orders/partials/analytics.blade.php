<section data-order-panel="analytics" class="hidden">
    <div class="purchase-manager-panel p-5">
        <h2 class="text-lg font-black text-slate-950">Monthly Purchase Trend</h2>
        <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($monthlyTrend as $month => $value)
                <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">{{ $month }}</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">₹{{ number_format($value, 2) }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
