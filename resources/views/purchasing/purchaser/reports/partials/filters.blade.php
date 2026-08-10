@php
    $ranges = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'week' => 'This Week',
        'month' => 'This Month',
        'custom' => 'Custom',
    ];
@endphp

<section class="border-b border-slate-200 bg-white px-3 py-3 sm:px-5">
    @if ($errors->any())
        <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Report date range">
        @foreach ($ranges as $value => $label)
            <a
                href="{{ route($routeName, array_merge(request()->except(['page', 'date_from', 'date_to']), ['range' => $value])) }}"
                @class([
                    'shrink-0 rounded-lg border px-3 py-2 text-xs font-bold transition',
                    'border-emerald-700 bg-emerald-700 text-white' => $selectedRange === $value,
                    'border-slate-200 bg-white text-slate-600 hover:border-slate-300' => $selectedRange !== $value,
                ])
            >{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route($routeName) }}" class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_1.3fr_auto]">
        <input type="hidden" name="range" value="{{ $selectedRange }}">
        <label class="text-[10px] font-black uppercase text-slate-500">
            From
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900" @disabled($selectedRange !== 'custom')>
        </label>
        <label class="text-[10px] font-black uppercase text-slate-500">
            To
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900" @disabled($selectedRange !== 'custom')>
        </label>
        <label class="text-[10px] font-black uppercase text-slate-500">
            Shop
            <select name="shop_id" class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 text-sm font-semibold text-slate-900">
                <option value="">All shops</option>
                @foreach ($shops as $shop)
                    <option value="{{ $shop->id }}" @selected($filters['shop_id'] === $shop->id)>{{ $shop->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-[10px] font-black uppercase text-slate-500">
            Status
            <select name="status" class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 text-sm font-semibold text-slate-900">
                <option value="all">All billable</option>
                <option value="finalized" @selected($filters['status'] === 'finalized')>Finalized</option>
                <option value="payment_pending" @selected($filters['status'] === 'payment_pending')>Payment pending</option>
                <option value="paid" @selected($filters['status'] === 'paid')>Paid</option>
            </select>
        </label>
        <label class="text-[10px] font-black uppercase text-slate-500">
            Search
            <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Shop, invoice or product" class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400">
        </label>
        <button type="submit" class="mt-auto h-10 rounded-lg bg-slate-950 px-5 text-sm font-black text-white hover:bg-slate-800">Apply</button>
    </form>
</section>
