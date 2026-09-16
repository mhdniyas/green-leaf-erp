@if ($pendingList->isNotEmpty())
    <div class="overflow-hidden rounded-[2rem] border border-amber-200 bg-amber-50/40 p-5 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h4 class="text-xs font-black uppercase tracking-wider text-amber-900">Pending Advance Products ({{ $pendingList->count() }})</h4>
                <p class="text-[11px] font-medium text-amber-700">These items were received as warehouse advance but have not been fully matched with purchase bills.</p>
            </div>
            <a href="{{ route('purchaser.business-days.close.pending', $day->uuid) }}" class="text-xs font-bold text-amber-800 hover:underline">
                View All Details &rarr;
            </a>
        </div>

        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($pendingList as $item)
                <div class="flex items-center justify-between rounded-xl border border-amber-200 bg-white p-3 shadow-xs">
                    <div>
                        <p class="text-xs font-black text-slate-900">{{ $item['product_name'] }}</p>
                        @if ($item['unit_mismatch'])
                            <span class="text-[10px] font-bold text-rose-600">Unit mismatch detected</span>
                        @endif
                    </div>
                    <span class="rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-900">
                        {{ $item['formatted_pending'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endif
