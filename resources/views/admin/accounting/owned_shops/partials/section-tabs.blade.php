<section class="sticky top-0 z-30 rounded-[1.6rem] border border-slate-200 bg-white/95 p-3 shadow-sm backdrop-blur sm:p-4">
    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <nav id="owned-shop-section-nav" class="inline-flex w-fit flex-wrap gap-1 rounded-2xl bg-slate-100 p-1">
            @foreach ([
                'approve' => 'Approve',
                'cashbook' => 'Cashbook',
                'bills' => 'Bills',
            ] as $sectionKey => $sectionLabel)
                <a
                    href="#{{ $sectionKey }}"
                    data-section-nav="{{ $sectionKey }}"
                    class="{{ ($activeSection ?? $defaultSection ?? 'cashbook') === $sectionKey ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900' }} inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold transition"
                >
                    {{ $sectionLabel }}
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
            <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
            <input type="hidden" name="section" value="{{ $defaultSection ?? 'cashbook' }}">
            <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="h-11 min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none">
            <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="h-11 min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none">
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">
                Update Period
            </button>
        </form>
    </div>
</section>
