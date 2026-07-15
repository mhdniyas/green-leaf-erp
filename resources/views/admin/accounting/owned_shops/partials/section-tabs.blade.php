<section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $shop, 'tab' => 'bills', 'date' => $selectedDate->format('Y-m-d'), 'start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="{{ $tab === 'bills' ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800' }} inline-flex h-11 items-center justify-center rounded-2xl px-5 text-sm font-black transition">
                Bills
            </a>
            <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $shop, 'tab' => 'cashbook', 'date' => $selectedDate->format('Y-m-d'), 'start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="{{ $tab === 'cashbook' ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800' }} inline-flex h-11 items-center justify-center rounded-2xl px-5 text-sm font-black transition">
                Ledger
            </a>
        </div>

        <form method="GET" action="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
            <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="h-12 min-w-0 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
            <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="h-12 min-w-0 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
            <button type="submit" class="inline-flex h-12 items-center justify-center rounded-2xl bg-cyan-600 px-4 text-sm font-black text-white transition hover:bg-cyan-500">
                Update Analytics
            </button>
        </form>
    </div>
</section>
