@php
    $hidden = $hidden ?? [];
    $startValue = $startDate instanceof \Illuminate\Support\Carbon
        ? $startDate->toDateString()
        : (string) ($startDate ?? request('start_date', ''));
    $endValue = $endDate instanceof \Illuminate\Support\Carbon
        ? $endDate->toDateString()
        : (string) ($endDate ?? request('end_date', ''));
@endphp

<form method="GET" action="{{ $action }}" class="grid gap-2 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-2 sm:grid-cols-[1fr_1fr_auto_auto]">
    @foreach ($hidden as $name => $value)
        @if (filled($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">From</span>
        <input type="date" name="start_date" value="{{ $startValue }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
    </label>
    <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">To</span>
        <input type="date" name="end_date" value="{{ $endValue }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
    </label>
    <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800">Filter</button>
    <a href="{{ $clearUrl ?? $action }}" class="inline-flex h-14 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-800 transition hover:bg-slate-100">Clear</a>
</form>
