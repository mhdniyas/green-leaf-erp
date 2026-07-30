<a href="{{ $href ?? '#' }}" class="block rounded-[1.15rem] border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $label }}</p>
            <p class="mt-2 text-2xl font-black tracking-tight text-slate-950">Rs. {{ number_format((float) $value, 2) }}</p>
            @if(! empty($hint))
                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $hint }}</p>
            @endif
        </div>
        <span class="mt-1 inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </span>
    </div>
</a>
