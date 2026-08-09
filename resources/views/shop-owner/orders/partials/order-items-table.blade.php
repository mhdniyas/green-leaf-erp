@php
    $sortedItems = $order->items->sortBy(
        fn ($item) => \App\Models\Product::sortableSku((string) ($item->product?->sku ?? ''))
    );
@endphp

{{-- Mobile View: Strict Single-Row Cards --}}
<div class="space-y-1.5 md:hidden">
    @foreach ($sortedItems as $item)
        @php
            $reqQty = (float) $item->requested_qty;
            $apprQty = (float) ($item->approved_qty ?? 0);
            $delivQty = (float) ($item->delivered_qty ?? 0);
            $isRejected = $item->approved_qty !== null && $apprQty < $reqQty;
        @endphp
        <article class="flex items-center justify-between gap-1.5 rounded-lg border border-slate-200 bg-white p-2 shadow-xs text-xs transition hover:border-slate-300">
            {{-- Item & SKU --}}
            <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-900 truncate">
                    @if($item->product?->sku)
                        <span class="font-mono text-[9px] font-semibold text-slate-400 mr-1">#{{ $item->product->sku }}</span>
                    @endif
                    {{ $item->product->name }}
                </p>
            </div>

            {{-- Quantities: Requested -> Approved/Delivered --}}
            <div class="shrink-0 text-right flex items-center gap-1.5">
                <span class="font-bold text-slate-700 whitespace-nowrap">{{ number_format($reqQty, 2) }} {{ strtoupper($item->unit) }}</span>
                @if ($delivQty > 0)
                    <span class="text-[9px] font-black text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200 whitespace-nowrap">Del: {{ number_format($delivQty, 2) }}</span>
                @elseif ($item->approved_qty !== null)
                    <span class="text-[9px] font-black {{ $isRejected ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-slate-700 bg-slate-100 border-slate-200' }} border px-1.5 py-0.5 rounded whitespace-nowrap">Appr: {{ number_format($apprQty, 2) }}</span>
                @else
                    <span class="text-[9px] font-semibold text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded whitespace-nowrap">Pending</span>
                @endif
            </div>
        </article>
    @endforeach
</div>

{{-- Desktop Table View --}}
<div class="hidden overflow-x-auto rounded-xl border border-slate-200 md:block">
    <table class="min-w-full border-collapse text-left text-xs whitespace-nowrap">
        <thead>
            <tr class="border-b border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                <th class="px-3 py-2.5">#</th>
                <th class="px-3 py-2.5">Product Item</th>
                <th class="px-3 py-2.5 text-right">Requested</th>
                <th class="px-3 py-2.5 text-right">Approved</th>
                <th class="px-3 py-2.5 text-right">Delivered</th>
                <th class="px-3 py-2.5">Review Note</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
            @foreach ($sortedItems as $item)
                @php
                    $reqQty = (float) $item->requested_qty;
                    $apprQty = (float) ($item->approved_qty ?? 0);
                    $delivQty = (float) ($item->delivered_qty ?? 0);
                @endphp
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-3 py-2 font-bold text-slate-500">{{ $loop->iteration }}</td>
                    <td class="px-3 py-2 font-bold text-slate-950">
                        @if($item->product?->sku)
                            <span class="inline-block rounded bg-slate-100 px-1 py-0.5 text-[9px] font-mono text-slate-600 mr-1">#{{ $item->product->sku }}</span>
                        @endif
                        {{ $item->product->name }}
                    </td>
                    <td class="px-3 py-2 text-right font-bold text-slate-900">{{ number_format($reqQty, 2) }} {{ strtoupper($item->unit) }}</td>
                    <td class="px-3 py-2 text-right font-bold text-slate-800">{{ number_format($apprQty, 2) }} {{ strtoupper($item->unit) }}</td>
                    <td class="px-3 py-2 text-right font-bold text-emerald-700">{{ number_format($delivQty, 2) }} {{ strtoupper($item->unit) }}</td>
                    <td class="px-3 py-2 text-slate-600 text-xs">{{ $item->notes ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
