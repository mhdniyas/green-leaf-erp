@props(['item', 'isExtra' => false])

@php
    $pid = $item['product_id'];
    $collapseId = "product-row-{$pid}";
@endphp

<div class="border-b border-slate-100 last:border-b-0">
    {{-- Product Header Row --}}
    <button type="button" onclick="toggleProductRow('{{ $collapseId }}')"
        class="flex w-full cursor-pointer items-center justify-between gap-4 px-5 py-4 text-left hover:bg-slate-50 transition border-0 bg-transparent">
        <div class="flex items-center gap-3 min-w-0">
            @if($isExtra)
                <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-700">Extra</span>
            @else
                <span class="shrink-0 rounded-full bg-cyan-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-cyan-700">Daily</span>
            @endif
            <div class="min-w-0">
                <p class="truncate text-sm font-black text-slate-900">{{ $item['product_name'] }}</p>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $item['sku'] }}</p>
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-4">
            <div class="text-right">
                <p class="text-sm font-black text-slate-900">
                    {{ number_format($item['total_qty'], 2) }} {{ $item['unit'] }}
                </p>
                <p class="text-[10px] font-bold text-emerald-700">
                    Avg: INR {{ number_format($item['avg_price'], 2) }}/{{ $item['unit'] }}
                </p>
            </div>
            {{-- Expand chevron --}}
            <svg id="{{ $collapseId }}-chevron" class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </button>

    {{-- Expandable Details --}}
    <div id="{{ $collapseId }}" class="hidden border-t border-slate-100 bg-slate-50/70 px-5 pb-4 pt-3">
        <div class="grid gap-4 sm:grid-cols-2">

            {{-- Shop Order Splits --}}
            <div>
                <p class="mb-2 text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">Shop Order Splits</p>
                @if(count($item['shop_splits']) > 0)
                    <div class="space-y-1.5">
                        @foreach($item['shop_splits'] as $split)
                            <div class="flex items-center justify-between rounded-xl bg-white border border-slate-100 px-3 py-2">
                                <span class="text-xs font-bold text-slate-700">{{ $split['shop_name'] }}</span>
                                <span class="text-xs font-black text-slate-900">{{ number_format($split['quantity'], 2) }} {{ $split['unit'] }}</span>
                            </div>
                        @endforeach
                        @if(!$isExtra)
                            <div class="mt-1.5 flex justify-between border-t border-slate-200 pt-1.5 text-[10px] font-black">
                                <span class="text-slate-500">Daily needed</span>
                                <span class="text-slate-900">{{ number_format($item['daily_needed'], 2) }} {{ $item['unit'] }}</span>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-xs text-slate-400">No shop order splits for this date.</p>
                @endif
            </div>

            {{-- Purchaser Entries --}}
            <div>
                <p class="mb-2 text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">
                    Purchaser Entries ({{ count($item['entries']) }})
                </p>
                <div class="space-y-1.5">
                    @foreach($item['entries'] as $entry)
                        <div class="rounded-xl bg-white border border-slate-100 px-3 py-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-700">{{ $entry['supplier'] }}</span>
                                <span class="text-xs font-black text-slate-900">{{ number_format($entry['qty'], 2) }} {{ $item['unit'] }}</span>
                            </div>
                            <div class="mt-0.5 flex items-center justify-between">
                                <span class="text-[10px] text-slate-400">by {{ $entry['purchaser'] }}</span>
                                <span class="text-[10px] font-bold text-cyan-700">INR {{ number_format($entry['unit_price'], 2) }}/{{ $item['unit'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- Weighted Average Summary --}}
        <div class="mt-3 flex items-center justify-between rounded-xl bg-cyan-50 border border-cyan-100 px-4 py-2.5">
            <span class="text-xs font-black text-cyan-800">Weighted Avg Price (will be applied on approval)</span>
            <span class="text-sm font-black text-cyan-900">INR {{ number_format($item['avg_price'], 2) }}/{{ $item['unit'] }}</span>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function toggleProductRow(id) {
                const el = document.getElementById(id);
                const chevron = document.getElementById(id + '-chevron');
                el.classList.toggle('hidden');
                chevron.style.transform = el.classList.contains('hidden') ? '' : 'rotate(180deg)';
            }
        </script>
    @endpush
@endonce
