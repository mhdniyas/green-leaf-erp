@php
    $hidden = $hidden ?? [];
    $startValue = $startDate instanceof \Illuminate\Support\Carbon
        ? $startDate->toDateString()
        : (string) ($startDate ?? request('start_date', ''));
    $endValue = $endDate instanceof \Illuminate\Support\Carbon
        ? $endDate->toDateString()
        : (string) ($endDate ?? request('end_date', ''));
@endphp

<form method="GET" action="{{ $action }}" class="grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2 sm:grid-cols-[1fr_1fr_auto_auto] sm:rounded-[1.25rem]">
    @foreach ($hidden as $name => $value)
        @if (filled($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <label class="rounded-xl bg-white px-3 py-2 text-slate-900 shadow-sm sm:rounded-2xl sm:px-4">
        <span class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-400 sm:text-[10px] sm:tracking-[0.18em]">From</span>
        <input type="date" name="start_date" value="{{ $startValue }}" class="mt-0.5 w-full border-0 bg-transparent p-0 text-xs font-black focus:outline-none focus:ring-0 sm:mt-1 sm:text-sm">
    </label>
    <label class="rounded-xl bg-white px-3 py-2 text-slate-900 shadow-sm sm:rounded-2xl sm:px-4">
        <span class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-400 sm:text-[10px] sm:tracking-[0.18em]">To</span>
        <input type="date" name="end_date" value="{{ $endValue }}" class="mt-0.5 w-full border-0 bg-transparent p-0 text-xs font-black focus:outline-none focus:ring-0 sm:mt-1 sm:text-sm">
    </label>
    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-black text-white transition hover:bg-slate-800 sm:h-14 sm:rounded-2xl sm:px-4 sm:text-sm">Filter</button>
    <a href="{{ $clearUrl ?? $action }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-800 transition hover:bg-slate-100 sm:h-14 sm:rounded-2xl sm:px-4 sm:text-sm">Clear</a>
</form>
