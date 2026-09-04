{{-- Deliveries-Style Date Navigator --}}
<div class="flex items-center justify-between gap-1.5 rounded-xl border border-slate-200 bg-white p-1.5 sm:p-2 shadow-xs">
    <a href="{{ route('shop-owner.cashbook.show', ['date' => $selectedDate->copy()->subDay()->toDateString()]) }}"
       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 transition shrink-0"
       title="Previous Day">
        <i data-lucide="chevron-left" class="h-4 w-4"></i>
    </a>

    <form method="GET" action="{{ route('shop-owner.cashbook.show') }}" class="flex items-center gap-1.5 min-w-0">
        <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" onchange="this.form.submit()"
               class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-center text-xs sm:text-sm font-black text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none cursor-pointer">
    </form>

    <div class="flex items-center gap-1.5 shrink-0">
        <a href="{{ route('shop-owner.cashbook.show', ['date' => today()->toDateString()]) }}"
           @class([
               'inline-flex h-9 items-center justify-center rounded-lg px-3 text-xs font-black transition',
               'bg-emerald-600 text-white shadow-xs' => $selectedDate->isToday(),
               'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' => ! $selectedDate->isToday(),
           ])>
            {{ $selectedDate->format('l') }}
        </a>
        <a href="{{ route('shop-owner.cashbook.show', ['date' => $selectedDate->copy()->addDay()->toDateString()]) }}"
           class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 transition shrink-0"
           title="Next Day">
            <i data-lucide="chevron-right" class="h-4 w-4"></i>
        </a>
    </div>
</div>
