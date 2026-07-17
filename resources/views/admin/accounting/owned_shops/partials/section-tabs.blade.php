<section class="rounded-3xl border border-slate-200 bg-white p-3 shadow-[0_12px_34px_rgba(15,23,42,0.04)] sm:p-4">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="inline-flex w-fit rounded-2xl bg-slate-100 p-1">
            <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $shop, 'tab' => 'bills', 'date' => $selectedDate->format('Y-m-d'), 'start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="{{ $tab === 'bills' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900' }} inline-flex h-10 items-center justify-center rounded-xl px-5 text-sm font-semibold transition">
                Bills
            </a>
            <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $shop, 'tab' => 'cashbook', 'date' => $selectedDate->format('Y-m-d'), 'start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="{{ $tab === 'cashbook' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900' }} inline-flex h-10 items-center justify-center rounded-xl px-5 text-sm font-semibold transition">
                Ledger
            </a>
        </div>

        <form method="GET" action="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
            <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="h-11 min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none">
            <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="h-11 min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none">
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">
                Update Analytics
            </button>
        </form>
    </div>
</section>
