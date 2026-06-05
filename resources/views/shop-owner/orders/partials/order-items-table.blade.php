<div class="space-y-3 md:hidden">
    @foreach ($order->items as $item)
        <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-bold text-slate-900">{{ $item->product->name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $item->product->sku }}</p>
                </div>
                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-slate-600">{{ $item->unit }}</span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Requested</p>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ number_format((float) $item->requested_qty, 2) }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Approved</p>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ number_format((float) ($item->approved_qty ?? 0), 2) }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Delivered</p>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ number_format((float) ($item->delivered_qty ?? 0), 2) }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Fulfillment</p>
                    <p class="mt-1 text-sm font-semibold capitalize text-slate-700">{{ $item->fulfillment_type ?? 'Pending' }}</p>
                </div>
            </div>
        </article>
    @endforeach
</div>

<div class="hidden overflow-x-auto md:block">
    <table class="min-w-full border-collapse text-left">
        <thead>
            <tr class="border-b border-slate-100 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                <th class="py-3 pr-4">Product</th>
                <th class="py-3 pr-4 text-right">Requested</th>
                <th class="py-3 pr-4 text-right">Approved</th>
                <th class="py-3 pr-4 text-right">Delivered</th>
                <th class="py-3 pr-4">Fulfillment</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
            @foreach ($order->items as $item)
                <tr>
                    <td class="py-4 pr-4">
                        <p class="font-bold text-slate-900">{{ $item->product->name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $item->product->sku }}</p>
                    </td>
                    <td class="py-4 pr-4 text-right font-bold">{{ number_format((float) $item->requested_qty, 2) }} {{ $item->unit }}</td>
                    <td class="py-4 pr-4 text-right font-bold">{{ number_format((float) ($item->approved_qty ?? 0), 2) }} {{ $item->unit }}</td>
                    <td class="py-4 pr-4 text-right font-bold">{{ number_format((float) ($item->delivered_qty ?? 0), 2) }} {{ $item->unit }}</td>
                    <td class="py-4 pr-4 capitalize">{{ $item->fulfillment_type ?? 'Pending' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
