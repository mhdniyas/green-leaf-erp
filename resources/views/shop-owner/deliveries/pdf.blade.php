<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $order->order_number }} - Delivery</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950">
    <div class="mx-auto max-w-5xl p-4 sm:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Delivery PDF View</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $order->order_number }}</h1>
            </div>
            <button type="button" onclick="window.print()" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white">
                Print / Save PDF
            </button>
        </div>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Delivery Document</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $order->order_number }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $order->shop?->name }} · {{ $order->business_date->format('d F Y') }}</p>
                </div>
                <div class="grid gap-2 text-right text-sm font-bold text-slate-700">
                    <span>{{ str($order->delivery_status)->replace('_', ' ')->title() }}</span>
                </div>
            </div>

            @php
                $sortedItems = $order->items->sortBy(
                    fn ($item) => \App\Models\Product::sortableSku((string) ($item->product?->sku ?? ''))
                );
                $invoice = $order->invoice;
                $invoiceItemsByProductId = $invoice?->items?->keyBy('product_id') ?? collect();
                
                // Recalculate totals from delivered quantities (DB may have wrong values)
                $recalculatedSubtotal = (float) $invoiceItemsByProductId->sum(function ($invoiceItem) {
                    $qty = (float) ($invoiceItem->delivered_price_quantity ?? $invoiceItem->price_quantity ?? $invoiceItem->delivered_qty ?? 0);
                    $rate = (float) ($invoiceItem->unit_price ?? 0);
                    return $qty * $rate;
                });
                
                $fulfilledItems = $sortedItems
                    ->filter(fn ($item) => $item->sorting_status === 'loaded' && (float) ($item->loaded_qty ?? 0) > 0)
                    ->groupBy('product_id')
                    ->map(function ($group) {
                        $loadedRow = $group->first(fn ($i) => $i->sorting_status === 'loaded' || (float) ($i->loaded_qty ?? 0) > 0);
                        return $loadedRow ?: $group->first();
                    })
                    ->values();
                $notAvailableItems = $sortedItems->filter(fn ($item) => $item->sorting_status === 'not_available');
            @endphp

            <div class="mt-6 overflow-hidden rounded-[1.5rem] border border-slate-200">
                <table class="min-w-full border-collapse text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Delivered Qty</th>
                            <th class="px-4 py-3 text-right">Unit Price</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($fulfilledItems as $item)
                            @php
                                $invoiceItem = $invoiceItemsByProductId->get($item->product_id);
                                
                                // Use invoice's pricing quantity and unit for display
                                if ($invoiceItem) {
                                    $approvedQty = (float) ($invoiceItem->delivered_price_quantity ?? $invoiceItem->price_quantity ?? $invoiceItem->delivered_qty ?? 0);
                                    $displayUnitLabel = strtoupper($invoiceItem->price_unit ?: $item->product->unit);
                                    $unitRate = $invoiceItem->unit_price;
                                    // Calculate line total (DB value may be wrong)
                                    $lineTotal = $approvedQty * $unitRate;
                                } else {
                                    // Fallback if no invoice item
                                    $approvedQty = (float) ($item->loaded_qty ?? $item->approved_qty ?? 0);
                                    $displayUnitLabel = strtoupper($item->product->unit ?? 'KG');
                                    $unitRate = null;
                                    $lineTotal = null;
                                }
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-bold text-slate-950">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 font-bold text-slate-950">
                                    @if($item->product?->sku)
                                        <span class="text-xs font-semibold text-slate-500 mr-1">[{{ $item->product->sku }}]</span>
                                    @endif
                                    {{ $item->product->name }}
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format($approvedQty, 2) }} {{ $displayUnitLabel }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">
                                    @if($unitRate !== null)
                                        Rs. {{ number_format((float) $unitRate, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">
                                    @if($lineTotal !== null)
                                        Rs. {{ number_format((float) $lineTotal, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-xs font-bold text-slate-500">No items loaded for this delivery.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($notAvailableItems->isNotEmpty())
                <div class="mt-4 flex flex-wrap items-center gap-2 rounded-xl border border-rose-200 bg-rose-50/60 px-4 py-3 text-xs text-rose-950">
                    <span class="font-black uppercase tracking-wider text-rose-700 flex items-center gap-1.5 shrink-0">
                        <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 4.94 2.94 17.76A1.5 1.5 0 0 0 4.24 20h15.52a1.5 1.5 0 0 0 1.3-2.24L13.66 4.94a1.5 1.5 0 0 0-2.6 0Z" />
                        </svg>
                        Out of Stock Items:
                    </span>
                    <span class="font-medium text-slate-800">
                        {{ $notAvailableItems->map(fn($i) => $i->product->name . ' (' . number_format((float) $i->approved_qty, 2) . ' ' . strtoupper($i->product->unit) . ')')->join(', ') }}
                    </span>
                </div>
            @endif

            @if ($invoice)
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Subtotal</p>
                        <p class="mt-2 text-xl font-black text-slate-950">Rs. {{ number_format($recalculatedSubtotal, 2) }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Shortage</p>
                        <p class="mt-2 text-xl font-black text-amber-600">Rs. {{ number_format((float) $invoice->shortage_total, 2) }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Excess</p>
                        <p class="mt-2 text-xl font-black text-cyan-700">Rs. {{ number_format((float) $invoice->excess_total, 2) }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Discount</p>
                        <p class="mt-2 text-xl font-black text-indigo-700">Rs. {{ number_format((float) $invoice->discount_total, 2) }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Final Total</p>
                        <p class="mt-2 text-xl font-black text-emerald-700">Rs. {{ number_format($recalculatedSubtotal - (float)$invoice->shortage_total + (float)$invoice->excess_total - (float)$invoice->discount_total, 2) }}</p>
                    </div>
                </div>
            @endif

            @if ($invoice?->delivery_note)
                <div class="mt-6">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Delivery Note</p>
                        <p class="mt-2 text-sm text-slate-700">{{ $invoice->delivery_note }}</p>
                    </div>
                </div>
            @endif
        </section>
    </div>
</body>
</html>
