<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-xl font-black text-slate-950">Invoice Breakdown</h2>
    <p class="mt-2 text-sm text-slate-600">This invoice is generated from the approved shop order and updated after the delivery review is approved.</p>

    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3 text-right">Bill Qty</th>
                        <th class="px-4 py-3 text-right">Delivered</th>
                        <th class="px-4 py-3 text-right">Unit Price</th>
                        <th class="px-4 py-3 text-right">Shortage</th>
                        <th class="px-4 py-3 text-right">Excess</th>
                        <th class="px-4 py-3 text-right">Final</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-bold text-slate-900">{{ $item->product_name }}</p>
                                <p class="text-xs text-slate-500">{{ strtoupper($item->unit) }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-700">{{ number_format((float) ($item->price_quantity ?: $item->approved_qty), 4) }} {{ strtoupper($item->price_unit ?: $item->unit) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-700">{{ number_format((float) $item->delivered_qty, 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">Rs. {{ number_format((float) $item->unit_price, 2) }} / {{ strtoupper($item->price_unit ?: $item->unit) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-amber-600">Rs. {{ number_format((float) $item->shortage_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-cyan-700">Rs. {{ number_format((float) $item->excess_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $item->final_line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if (in_array($invoice->delivery_status, ['pending', 'awaiting_review'], true) && $invoice->order?->is_allocation_completed)
        <div class="mt-5">
            @include('shop-owner.components.action-button', ['href' => route('shop-owner.deliveries.show', $invoice->order->order_number), 'label' => 'Confirm Delivery Against Invoice', 'classes' => 'bg-indigo-600 text-white'])
        </div>
    @endif
</section>
