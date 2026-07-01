<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950">
    @php
        $lineItems = $invoice->purchaserCart?->items ?? $invoice->goodsReceived?->items ?? collect();
        $businessDate = $invoice->purchaserCart?->business_date ?? $invoice->created_at;
        $balance = max(0, round(((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->paid_amount, 2));
    @endphp

    <div class="mx-auto max-w-5xl p-4 sm:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Purchase Bill PDF View</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $invoice->invoice_number }}</h1>
            </div>
            <button type="button" onclick="window.print()" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white">
                Print / Save PDF
            </button>
        </div>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Supplier Bill</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $invoice->invoice_number }}</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ $invoice->supplier?->name ?: 'Supplier pending' }}
                        @if ($businessDate)
                            · {{ $businessDate->format('d F Y') }}
                        @endif
                    </p>
                </div>
                <div class="grid gap-2 text-right text-sm font-bold text-slate-700">
                    <span>{{ str($invoice->status?->value ?? $invoice->status)->replace('_', ' ')->title() }}</span>
                    <span>{{ str($invoice->payment_status ?: 'unpaid')->replace('_', ' ')->title() }}</span>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Bill Amount</p>
                    <p class="mt-2 text-xl font-black text-slate-950">Rs. {{ number_format((float) $invoice->amount, 2) }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Paid</p>
                    <p class="mt-2 text-xl font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Discount</p>
                    <p class="mt-2 text-xl font-black text-indigo-700">Rs. {{ number_format((float) $invoice->discount_amount, 2) }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                    <p class="mt-2 text-xl font-black text-amber-600">Rs. {{ number_format($balance, 2) }}</p>
                </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-[1.5rem] border border-slate-200">
                <table class="min-w-full border-collapse text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Unit</th>
                            <th class="px-4 py-3 text-right">Unit Price</th>
                            <th class="px-4 py-3 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($lineItems as $item)
                            @php
                                $productName = $item->product?->name ?? $item->product_name ?? 'Item';
                                $quantity = $item->quantity ?? $item->received_qty ?? 0;
                                $unit = $item->product?->unit ?? $item->unit ?? '-';
                                $unitPrice = $item->unit_price ?? 0;
                                $lineTotal = $item->line_total ?? ((float) $quantity * (float) $unitPrice);
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-bold text-slate-950">{{ $productName }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format((float) $quantity, 2) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ $unit }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">Rs. {{ number_format((float) $unitPrice, 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $lineTotal, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm font-bold text-slate-500">No line items available for this bill.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Supplier</p>
                    <p class="mt-2 text-sm font-bold text-slate-900">{{ $invoice->supplier?->name ?: 'Supplier pending' }}</p>
                    <p class="mt-1 text-sm text-slate-700">{{ $invoice->supplier?->mobile_number ?: 'Mobile pending' }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Payment Details</p>
                    <p class="mt-2 text-sm font-bold text-slate-900">{{ $invoice->payment_method ?: 'Pending' }}</p>
                    <p class="mt-1 text-sm text-slate-700">{{ $invoice->payment_details ?: ($invoice->payment_note ?: 'No payment note added') }}</p>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
