{{-- Animated loading skeleton for tab content --}}
@php $lines = $lines ?? 4; @endphp
<div class="space-y-3 animate-pulse">
    @for ($i = 0; $i < $lines; $i++)
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="h-3 w-2/3 rounded-full bg-slate-200 mb-2"></div>
                    <div class="h-2.5 w-1/2 rounded-full bg-slate-100"></div>
                </div>
                <div class="h-5 w-16 rounded-full bg-slate-100 shrink-0"></div>
            </div>
        </div>
    @endfor
</div>
