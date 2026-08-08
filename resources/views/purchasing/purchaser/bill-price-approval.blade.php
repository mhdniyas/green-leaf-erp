<x-layouts.app title="Approval - Bill">
    @php
        $dateQuery = array_filter([
            'search_product' => $searchProduct !== '' ? $searchProduct : null,
            'search_shop' => $searchShop !== '' ? $searchShop : null,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-xl font-black text-slate-950">Bill Price Approval</h1>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Business date: <span class="text-slate-800">{{ $date->toDateString() }}</span></p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('purchaser.bill-prices.index', array_merge($dateQuery, ['date' => $todayShortcutDate])) }}" class="inline-flex h-9 items-center justify-center rounded-xl border px-3 text-xs font-black uppercase tracking-wider transition {{ $date->toDateString() === $todayShortcutDate ? 'border-slate-950 bg-slate-950 text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">Today</a>
                    <a href="{{ route('purchaser.bill-prices.index', array_merge($dateQuery, ['date' => $yesterdayShortcutDate])) }}" class="inline-flex h-9 items-center justify-center rounded-xl border px-3 text-xs font-black uppercase tracking-wider transition {{ $date->toDateString() === $yesterdayShortcutDate ? 'border-slate-950 bg-slate-950 text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">Yesterday</a>

                    <form method="GET" action="{{ route('purchaser.bill-prices.index') }}" class="flex items-center gap-1.5">
                        <input type="hidden" name="search_product" value="{{ $searchProduct }}">
                        <input type="hidden" name="search_shop" value="{{ $searchShop }}">
                        <input type="text" name="date" value="{{ $date->toDateString() }}" inputmode="numeric" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}" class="h-9 w-32 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 outline-none focus:border-lime-400 focus:bg-white">
                        <button class="h-9 rounded-xl bg-slate-950 px-3 text-xs font-bold text-white transition hover:bg-slate-800">Go</button>
                    </form>
                </div>
            </div>
        </section>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900">
                {{ session('success') }}
            </div>
        @endif

        <section class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Bills</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ number_format((int) $summary['bills']) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total</p>
                <p class="mt-1 text-lg font-black text-slate-950">₹{{ number_format((float) $summary['total'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Items</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ number_format((int) $summary['items']) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Special Edits</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ number_format((int) $summary['specials']) }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('purchaser.bill-prices.index') }}" class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_1fr_auto]">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <input type="text" name="search_shop" value="{{ $searchShop }}" placeholder="Search shop..." class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 outline-none focus:border-lime-400 focus:bg-white">
                <input type="text" name="search_product" value="{{ $searchProduct }}" placeholder="Search product..." class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 outline-none focus:border-lime-400 focus:bg-white">
                <div class="flex gap-2">
                    <button class="h-10 flex-1 rounded-xl bg-slate-950 px-4 text-xs font-black text-white transition hover:bg-slate-900 sm:flex-none">Search</button>
                    @if($searchShop !== '' || $searchProduct !== '')
                        <a href="{{ route('purchaser.bill-prices.index', ['date' => $date->toDateString()]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-600 transition hover:bg-slate-100">Clear</a>
                    @endif
                </div>
            </form>
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Shop Bills</h2>
                <span class="rounded-full bg-slate-200 px-2.5 py-1 text-[10px] font-black text-slate-700">{{ number_format($billCards->count()) }}</span>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($billCards as $invoice)
                @php
                    $invoiceTotal = (float) ($invoice->final_total ?: $invoice->subtotal);
                    $categoryGroups = $invoice->items
                        ->groupBy(fn ($item) => $item->product?->category?->name ?: 'No Category')
                        ->map(fn ($items, $name) => ['name' => $name, 'count' => $items->count()])
                        ->values();
                @endphp
                <a href="{{ route('purchaser.bill-prices.show', $invoice) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-teal-300 hover:shadow-md active:scale-[0.99]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-black text-slate-950">{{ $invoice->shop?->name ?? 'Unknown shop' }}</p>
                            <p class="mt-0.5 truncate text-[11px] font-bold text-slate-500">{{ $invoice->invoice_number }}</p>
                        </div>
                        <p class="shrink-0 text-sm font-black text-slate-950">₹{{ number_format($invoiceTotal, 2) }}</p>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-2 text-[11px] font-bold text-slate-600">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Items</p>
                            <p class="mt-0.5 text-slate-950">{{ $invoice->items->count() }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Status</p>
                            <p class="mt-0.5 text-slate-950">{{ ucfirst((string) $invoice->status) }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex gap-1.5 overflow-hidden">
                        @foreach($categoryGroups->take(3) as $group)
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-600">{{ $group['name'] }} {{ $group['count'] }}</span>
                        @endforeach
                        @if($categoryGroups->count() > 3)
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-600">+{{ $categoryGroups->count() - 3 }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-xs font-bold text-slate-400 sm:col-span-2 xl:col-span-3">
                    No bills found for this date.
                </div>
            @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
