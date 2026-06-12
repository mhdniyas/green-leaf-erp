{{-- Expandable product detail inside the daily approval collapsible card --}}
{{-- $item: array with keys product_name, total_qty, avg_price, unit, shop_splits, entries, daily_needed --}}

<div class="grid gap-4 sm:grid-cols-2">

    {{-- Shop Order Splits --}}
    <div>
        <p class="mb-2 text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">Shop Order Splits</p>
        @if(count($item['shop_splits']) > 0)
            <div class="space-y-1.5">
                @foreach($item['shop_splits'] as $split)
                    <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-white px-3 py-2">
                        <span class="text-xs font-bold text-slate-700">{{ $split['shop_name'] }}</span>
                        <span class="text-xs font-black text-slate-900">{{ number_format($split['quantity'], 2) }} {{ $split['unit'] }}</span>
                    </div>
                @endforeach
                @if(!$item['is_extra'])
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-[10px] font-black">
                        <span class="text-slate-500">Total needed</span>
                        <span class="text-slate-900">{{ number_format($item['daily_needed'], 2) }} {{ $item['unit'] }}</span>
                    </div>
                @endif
            </div>
        @else
            <p class="text-xs text-slate-400">No shop order splits.</p>
        @endif
    </div>

    {{-- Purchaser Entries --}}
    <div>
        <p class="mb-2 text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">
            Purchaser Entries ({{ count($item['entries']) }})
        </p>
        <div class="space-y-1.5">
            @foreach($item['entries'] as $entry)
                <div class="rounded-xl border border-slate-100 bg-white px-3 py-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700">{{ $entry['supplier'] }}</span>
                        <span class="text-xs font-black text-slate-900">{{ number_format($entry['qty'], 2) }} {{ $item['unit'] }}</span>
                    </div>
                    <div class="mt-0.5 flex items-center justify-between">
                        <span class="text-[10px] text-slate-400">by {{ $entry['purchaser'] }}</span>
                        <span class="text-[10px] font-bold text-cyan-700">INR {{ number_format($entry['unit_price'], 2) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

</div>

{{-- Weighted avg summary --}}
<div class="mt-3 flex items-center justify-between rounded-xl border border-cyan-100 bg-cyan-50 px-4 py-2.5">
    <span class="text-xs font-black text-cyan-800">Weighted Avg (applied on approval)</span>
    <span class="text-sm font-black text-cyan-900">INR {{ number_format($item['avg_price'], 2) }}/{{ $item['unit'] }}</span>
</div>
