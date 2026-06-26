<x-layouts.accounting title="Accounting Dashboard">
    @php
        $prevDate = $date->copy()->subDay()->format('Y-m-d');
        $nextDate = $date->copy()->addDay()->format('Y-m-d');
        $todayDate = today()->toDateString();
        $ownedCards = [
            ['label' => 'Eligible Shops', 'value' => number_format($ownedMetrics['eligible_shop_count']), 'hint' => $ownedMetrics['owned_shop_count'].' owned • '.$ownedMetrics['partnership_shop_count'].' partnership'],
            ['label' => 'Today Entries', 'value' => number_format($ownedMetrics['entries_today_count']), 'hint' => $ownedMetrics['draft_entries_count'].' drafts still open'],
            ['label' => 'Pending Review', 'value' => number_format($ownedMetrics['pending_review_count']), 'hint' => 'waiting for admin approval'],
            ['label' => 'Rechecks', 'value' => number_format($ownedMetrics['recheck_count']), 'hint' => 'sent back to shop owners'],
            ['label' => 'Approved', 'value' => number_format($ownedMetrics['approved_entries_count']), 'hint' => 'ready for settlement period'],
            ['label' => 'Net Position', 'value' => 'Rs. '.number_format($ownedMetrics['net_amount'], 2), 'hint' => 'selected day owned-shop result'],
        ];
        $workflowActions = [
            [
                'label' => 'Daily Sales Report',
                'href' => route('finance.sales-daily', ['date' => $date->format('Y-m-d')]),
                'eyebrow' => 'Sales',
                'description' => 'Collections, balances, and invoice recovery for the selected day.',
            ],
            [
                'label' => 'Daily Vendor Report',
                'href' => route('finance.vendor-daily', ['date' => $date->format('Y-m-d')]),
                'eyebrow' => 'Vendors',
                'description' => 'Supplier credits, debits, and outstanding balances in one daily ledger.',
            ],
            [
                'label' => 'Shop Invoice Review',
                'href' => route('purchasing.shop-invoices.index'),
                'eyebrow' => 'Invoices',
                'description' => 'Operational invoice review for delivery discrepancies and follow-up.',
            ],
        ];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section id="overview" class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[linear-gradient(135deg,_#062b1f,_#0f172a_58%,_#14532d)] text-white shadow-[0_30px_100px_rgba(15,23,42,0.20)]">
            <div class="flex flex-col gap-6 px-5 py-6 lg:flex-row lg:items-end lg:justify-between lg:px-7">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-200">Accounting Dashboard</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Separate admin accounting workspace for daily finance and owned shops.</h2>
                    <p class="mt-3 max-w-2xl text-sm font-semibold leading-6 text-slate-200">This page runs as its own accounting shell. Daily workflow finance stays connected to the existing shop invoice flow, and owned or partnership shops stay isolated in their own settlement ledger.</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.index') }}" class="flex flex-wrap items-center gap-2 rounded-[1.5rem] border border-white/15 bg-white/10 p-2 backdrop-blur">
                    <a href="{{ route('admin.accounting.index', ['date' => $prevDate]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-white transition hover:bg-white/20" title="Previous day">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </a>
                    <label class="min-w-[12rem] rounded-2xl border border-white/15 bg-white px-4 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black text-slate-900 focus:outline-none focus:ring-0">
                    </label>
                    @if($date->format('Y-m-d') !== $todayDate)
                        <a href="{{ route('admin.accounting.index', ['date' => $todayDate]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-white px-4 text-xs font-black uppercase tracking-[0.18em] text-slate-950 transition hover:bg-slate-100">
                            Today
                        </a>
                    @endif
                    <a href="{{ route('admin.accounting.index', ['date' => $nextDate]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-white transition hover:bg-white/20" title="Next day">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </form>
            </div>

            <div class="grid gap-4 border-t border-white/10 px-5 py-5 md:grid-cols-2 2xl:grid-cols-3 lg:px-7">
                @foreach($ownedCards as $card)
                    <article class="rounded-[1.5rem] border border-white/10 bg-white/8 p-5 backdrop-blur">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-100/80">{{ $card['label'] }}</p>
                        <p class="mt-3 text-3xl font-black tracking-tight text-white">{{ $card['value'] }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-200">{{ $card['hint'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="daily-workflow" class="grid gap-5 2xl:grid-cols-[1.15fr_0.85fr]">
            <article class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Daily Workflow Finance</p>
                        <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Operational finance remains the source of truth.</h3>
                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-600">This section goes straight into the current invoice and collection flow instead of inventing another daily finance model.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.accounting.daily-workflow.invoices') }}" class="rounded-[1.3rem] border border-slate-200 bg-slate-50 p-3">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Generate</p>
                        <button type="submit" class="mt-2 inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">
                            Daily Shop Invoices
                        </button>
                    </form>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    @foreach($workflowActions as $action)
                        <a href="{{ $action['href'] }}" class="rounded-[1.4rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $action['eyebrow'] }}</p>
                            <p class="mt-2 text-sm font-black text-slate-950">{{ $action['label'] }}</p>
                            <p class="mt-2 text-xs font-semibold leading-5 text-slate-500">{{ $action['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </article>

            <article id="owned-accounting" class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Owned Shop Accounting</p>
                        <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Review internal settlement activity fast.</h3>
                    </div>
                    <a href="{{ route('admin.accounting.owned-shops.index') }}" class="inline-flex h-10 items-center rounded-2xl border border-slate-200 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-50">
                        Open Shops
                    </a>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($eligibleShops as $shop)
                        <a href="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="flex items-start justify-between gap-3 rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                            <div>
                                <p class="text-sm font-black text-slate-950">{{ $shop->name }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $shop->code }} • {{ $shop->accounting_mode }}</p>
                                <p class="mt-2 text-xs font-semibold text-slate-500">{{ $shop->ownerships->count() }} ownership row(s) configured</p>
                            </div>
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">
                                Enabled
                            </span>
                        </a>
                    @empty
                        <div class="rounded-[1.35rem] border border-dashed border-slate-300 px-4 py-10 text-center text-sm font-bold text-slate-500">
                            No owned or partnership shops are enabled for accounting yet.
                        </div>
                    @endforelse
                </div>
            </article>
        </section>

        <section id="reports" class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Reports</p>
                    <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Vendor and sales reporting block</h3>
                    <p class="mt-2 text-sm font-semibold text-slate-600">The existing finance report surfaces stay here for fast review inside the accounting workspace.</p>
                </div>
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-slate-500">
                    Selected day: {{ $date->format('d M Y') }}
                </div>
            </div>

            @include('finance.partials.admin-pillars', [
                'finance' => $finance,
                'startDate' => $date,
                'endDate' => $date,
                'showHeader' => false,
            ])
        </section>
    </div>
</x-layouts.accounting>
