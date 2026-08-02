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
                    <p class="mt-2 text-xl font-black text-slate-950">Rs. {{ number_format((float) $invoice->subtotal, 2) }}</p>
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
                    <p class="mt-2 text-xl font-black text-emerald-700">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
                </div>
            </div>

            @php
                $billedItems = $invoice->items->filter(fn ($item) => (float) $item->delivered_qty > 0 || (float) $item->final_line_total > 0);
                $notAvailableItems = $invoice->items->filter(fn ($item) => (float) $item->delivered_qty <= 0 && (float) $item->final_line_total <= 0);
            @endphp

            <div class="mt-6 overflow-hidden rounded-[1.5rem] border border-slate-200">
                <table class="min-w-full border-collapse text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Approved</th>
                            <th class="px-4 py-3 text-right">Delivered</th>
                            <th class="px-4 py-3 text-right">Unit Price</th>
                            <th class="px-4 py-3 text-right">Shortage</th>
                            <th class="px-4 py-3 text-right">Excess</th>
                            <th class="px-4 py-3 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($billedItems as $item)
                            <tr>
                                <td class="px-4 py-3 font-bold text-slate-950">{{ $item->product_name }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format((float) $item->approved_qty, 2) }} {{ $item->unit }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format((float) $item->delivered_qty, 2) }} {{ $item->unit }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">Rs. {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-amber-600">Rs. {{ number_format((float) $item->shortage_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-cyan-700">Rs. {{ number_format((float) $item->excess_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $item->final_line_total, 2) }}</td>
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
                <div class="mt-6 overflow-hidden rounded-[1.5rem] border border-rose-200 bg-rose-50/40 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-[0.16em] text-rose-800 flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 4.94 2.94 17.76A1.5 1.5 0 0 0 4.24 20h15.52a1.5 1.5 0 0 0 1.3-2.24L13.66 4.94a1.5 1.5 0 0 0-2.6 0Z" />
                            </svg>
                            Not Available / Out of Stock Items (Info Only — Rs. 0.00 Billed)
                        </h3>
                        <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-rose-700">
                            {{ $notAvailableItems->count() }} Item(s)
                        </span>
                    </div>
                    <table class="min-w-full border-collapse text-left text-xs">
                        <thead class="bg-rose-100/60 text-[10px] font-black uppercase tracking-[0.16em] text-rose-800">
                            <tr>
                                <th class="px-3 py-2">Product</th>
                                <th class="px-3 py-2 text-right">Ordered Qty</th>
                                <th class="px-3 py-2 text-right">Delivered Qty</th>
                                <th class="px-3 py-2 text-right">Status</th>
                                <th class="px-3 py-2 text-right">Billed Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rose-100/80 bg-white">
                            @foreach ($notAvailableItems as $item)
                                <tr>
                                    <td class="px-3 py-2 font-bold text-slate-900">{{ $item->product_name }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-slate-600">{{ number_format((float) $item->approved_qty, 2) }} {{ $item->unit }}</td>
                                    <td class="px-3 py-2 text-right font-bold text-rose-600">0.00 {{ $item->unit }}</td>
                                    <td class="px-3 py-2 text-right font-black uppercase tracking-wider text-rose-700">Out of Stock</td>
                                    <td class="px-3 py-2 text-right font-black text-slate-900">Rs. 0.00</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
