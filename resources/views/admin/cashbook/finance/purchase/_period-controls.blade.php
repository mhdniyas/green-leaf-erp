@php
    $periodLinks = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'week' => 'This Week',
        'month' => 'This Month',
    ];
    $isCustomPeriod = in_array($filters['period'], ['custom', 'between', 'range'], true);
@endphp

<input type="hidden" name="period" value="{{ $filters['period'] }}">

<div class="flex max-w-full gap-2 overflow-x-auto pb-1">
    @foreach($periodLinks as $period => $label)
        <a href="{{ route($periodRoute, $periodBaseQuery + ['period' => $period]) }}" class="min-w-max rounded-lg border px-3 py-2 text-xs font-black {{ $filters['period'] === $period ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300' }}">{{ $label }}</a>
    @endforeach
    <a href="{{ route($periodRoute, $periodBaseQuery + ['period' => 'custom', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]) }}" class="min-w-max rounded-lg border px-3 py-2 text-xs font-black {{ $isCustomPeriod ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300' }}">Custom</a>
</div>

@if($isCustomPeriod)
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(9rem,1fr)_minmax(9rem,1fr)_auto]">
        <label class="text-[10px] font-black uppercase text-slate-500">From<input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800"></label>
        <label class="text-[10px] font-black uppercase text-slate-500">To<input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800"></label>
        <button type="submit" class="min-h-10 self-end rounded-lg bg-emerald-700 px-3 text-xs font-black text-white">Go</button>
    </div>
@endif
