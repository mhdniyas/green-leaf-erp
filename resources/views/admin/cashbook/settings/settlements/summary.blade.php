<section class="space-y-3" aria-label="Configured settlement summary">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-extrabold text-slate-900">Settlement Summary</h2>
        <a href="{{ route('admin.cashbook.settings.shop.settlements.index', $currentShop->slug ?: $currentShop->shop_id) }}" class="py-2 text-xs font-bold text-indigo-700 hover:text-indigo-900">Configure Settlements &rarr;</a>
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($configuredSettlements as $settlement)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-700">{{ $settlement['name'] }}</h3>
                <p class="mt-2 font-mono text-2xl font-black {{ $settlement['netSettlement'] < 0 ? 'text-rose-700' : 'text-indigo-800' }}">₹{{ number_format($settlement['netSettlement'], 2) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $isDayDetail ? 'Selected day' : 'Selected month' }} · Category formula result</p>
            </article>
        @empty
            <p class="text-sm text-slate-600">No enabled settlements. Configure a settlement to show its result here.</p>
        @endforelse
    </div>
</section>
