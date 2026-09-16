<div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl {{ $day->isOpen() ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/25' : ($day->isReopened() ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/25' : 'bg-slate-700 text-white') }}">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-500">{{ $warehouse->name }}</span>
                    @if ($day->isOpen())
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">OPEN</span>
                    @elseif ($day->isReopened())
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-800">REOPENED</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-700">CLOSED</span>
                    @endif
                </div>
                <h2 class="mt-1 text-2xl font-black text-slate-950">
                    Close Business Day · {{ $day->business_date->format('d M Y') }}
                </h2>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">
                    @if ($day->isOpen())
                        Opened by {{ $day->openedBy?->name ?? 'Purchaser' }} at {{ $day->opened_at?->format('h:i A') }}
                    @elseif ($day->isReopened())
                        Reopened by {{ $day->reopenedBy?->name ?? 'Purchaser' }} at {{ $day->reopened_at?->format('h:i A') }}
                    @else
                        Closed by {{ $day->closedBy?->name ?? 'Purchaser' }} at {{ $day->closed_at?->format('h:i A') }}
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('purchasing.business-days.show', $day->uuid) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-xs font-bold text-slate-700 shadow-xs transition hover:bg-slate-50">
                ← Back to Business Day
            </a>
        </div>
    </div>
</div>
