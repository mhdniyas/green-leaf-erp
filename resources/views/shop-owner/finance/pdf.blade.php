<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950">
    <div class="mx-auto max-w-5xl p-4 sm:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Shop Invoice PDF View</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $invoice->invoice_number }}</h1>
            </div>
            <button type="button" onclick="window.print()" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white">
                Print / Save PDF
            </button>
        </div>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Daily Invoice</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $invoice->invoice_number }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $invoice->shop?->name }} · {{ $invoice->business_date->format('d F Y') }}</p>
                </div>
                <div class="grid gap-2 text-right text-sm font-bold text-slate-700">
                    <span>{{ str($invoice->delivery_status)->replace('_', ' ')->title() }}</span>
                    <span>{{ str($invoice->payment_status)->replace('_', ' ')->title() }}</span>
                </div>
            </div>

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

            @php
                $billedItems = $invoice->items->filter(fn ($item) => (float) $item->delivered_qty > 0 || (float) $item->final_line_total > 0);
                $notAvailableItems = $invoice->items->filter(fn ($item) => (float) $item->delivered_qty <= 0 && (float) $item->final_line_total <= 0);
                
                // Recalculate subtotal from delivered quantities (DB may have wrong values)
                $recalculatedSubtotal = (float) $billedItems->sum(function ($item) {
                    $qty = (float) ($item->delivered_price_quantity ?? $item->price_quantity ?? $item->delivered_qty ?? 0);
                    $rate = (float) ($item->unit_price ?? 0);
                    return $qty * $rate;
                });
            @endphp

            <div class="mt-6 overflow-hidden rounded-[1.5rem] border border-slate-200">
                <table class="min-w-full border-collapse text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Bill Qty</th>
                            <th class="px-4 py-3 text-right">Delivered</th>
                            <th class="px-4 py-3 text-right">Unit Price</th>
                            <th class="px-4 py-3 text-right">Shortage</th>
                            <th class="px-4 py-3 text-right">Excess</th>
                            <th class="px-4 py-3 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($billedItems as $item)
                            @php
                                $qty = (float) ($item->delivered_price_quantity ?? $item->price_quantity ?? $item->delivered_qty ?? 0);
                                $rate = (float) ($item->unit_price ?? 0);
                                $calculatedLineTotal = $qty * $rate;
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-bold text-slate-950">
                                    @if($item->product?->sku)
                                        <span class="text-xs font-semibold text-slate-500 mr-1">[{{ $item->product->sku }}]</span>
                                    @endif
                                    {{ $item->product_name }}
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format((float) ($item->price_quantity ?: $item->approved_qty), 4) }} {{ strtoupper($item->price_unit ?: $item->product->unit) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format((float) $item->delivered_qty, 2) }} {{ strtoupper($item->product->unit) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">Rs. {{ number_format((float) $item->unit_price, 2) }} / {{ strtoupper($item->price_unit ?: $item->product->unit) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-amber-600">Rs. {{ number_format((float) $item->shortage_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-cyan-700">Rs. {{ number_format((float) $item->excess_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($calculatedLineTotal, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-xs font-bold text-slate-500">No billed items for this order.</td>
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
                        Out of Stock Items (Rs. 0.00 Billed):
                    </span>
                    <span class="font-medium text-slate-800">
                        {{ $notAvailableItems->map(fn($i) => $i->product_name . ' (' . number_format((float) $i->approved_qty, 2) . ' ' . $i->unit . ')')->join(', ') }}
                    </span>
                </div>
            @endif

            @if ($invoice->delivery_note || $invoice->payment_note)
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    @if ($invoice->delivery_note)
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Delivery Note</p>
                            <p class="mt-2 text-sm text-slate-700">{{ $invoice->delivery_note }}</p>
                        </div>
                    @endif
                    @if ($invoice->payment_note)
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Payment Note</p>
                            <p class="mt-2 text-sm text-slate-700">{{ $invoice->payment_note }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </section>
    </div>
</body>
</html>
