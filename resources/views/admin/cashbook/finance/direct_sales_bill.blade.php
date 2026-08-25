<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIRECT-SALE-{{ $sale->id }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950">
    <div class="mx-auto max-w-5xl p-4 sm:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Direct Company Sale Bill</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">DIRECT-SALE-{{ $sale->id }}</h1>
            </div>
            <button type="button" onclick="window.print()" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white">Print / Save PDF</button>
        </div>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">{{ $sale->shop?->name ?: 'Direct Company Sale' }}</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">DIRECT-SALE-{{ $sale->id }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $sale->business_date->format('d F Y') }}</p>
                </div>
                <div class="text-right text-sm font-bold text-slate-700">
                    <p>{{ $sale->customer_name ?: 'Walk-in buyer' }}</p>
                    <p class="mt-1">{{ $sale->payment_method ? strtoupper($sale->payment_method) : 'Legacy amount-only' }}</p>
                    @if($sale->reference)<p class="mt-1">Ref: {{ $sale->reference }}</p>@endif
                </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-[1.5rem] border border-slate-200">
                <table class="min-w-full border-collapse text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr><th class="px-4 py-3">Product</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Rate</th><th class="px-4 py-3 text-right">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($sale->items as $item)
                            <tr>
                                <td class="px-4 py-3 font-bold text-slate-950">{{ $item->product?->name }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format((float) $item->quantity, 3) }} {{ strtoupper($item->unit) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">Rs. {{ number_format((float) $item->unit_rate, 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-xs font-bold text-slate-500">Legacy amount-only sale. No item rows.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <div class="w-full max-w-xs rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex justify-between gap-4 text-sm font-bold text-slate-600"><span>Subtotal</span><span>Rs. {{ number_format((float) $sale->amount, 2) }}</span></div>
                    <div class="mt-3 flex justify-between gap-4 border-t border-slate-200 pt-3 text-lg font-black text-slate-950"><span>Grand Total</span><span>Rs. {{ number_format((float) $sale->amount, 2) }}</span></div>
                </div>
            </div>

            @if($sale->note)<div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"><p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Note</p><p class="mt-2">{{ $sale->note }}</p></div>@endif
        </section>
    </div>
</body>
</html>
