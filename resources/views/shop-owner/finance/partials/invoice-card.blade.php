@php
    $billedItems = $invoice->items->filter(fn ($item) => (float) $item->delivered_qty > 0 || (float) $item->final_line_total > 0 || (float) $item->approved_qty > 0);
    if ($billedItems->isEmpty()) {
        $billedItems = $invoice->items;
    }
    $computedSubtotal = (float) $billedItems->sum(function ($item) {
        $qty = (float) ($item->delivered_price_quantity ?? $item->price_quantity ?? $item->delivered_qty ?? 0);
        $rate = (float) ($item->unit_price ?? 0);
        return $qty * $rate;
    });
    $subtotal = (float) $invoice->subtotal > 0 ? (float) $invoice->subtotal : $computedSubtotal;
    $discountTotal = (float) $invoice->discount_total;
    $finalTotal = (float) $invoice->final_total > 0 ? (float) $invoice->final_total : max(0, $subtotal - $discountTotal);
    $paidAmount = (float) $invoice->paid_amount;
    $balanceAmount = (float) $invoice->balance_amount;
@endphp

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm sm:rounded-2xl">
    <div class="relative mx-auto max-w-[38rem] bg-white px-3 py-4 text-slate-950 sm:px-6 sm:py-7">
        {{-- Header Section --}}
        <header class="border-b border-dashed border-slate-400 pb-3 text-center sm:pb-4">
            <div class="flex items-start justify-between gap-2">
                <div class="flex-1">
                    <h3 class="text-base font-black uppercase tracking-wide text-slate-950 sm:text-xl">{{ $invoice->shop?->name ?? 'Green Leaf' }}</h3>
                    <p class="mt-1 text-xs font-black uppercase tracking-wider text-slate-700 sm:text-sm">Daily Invoice Bill</p>
                    <p class="mt-0.5 text-[11px] font-semibold text-slate-600">{{ $invoice->invoice_number }} · {{ $invoice->business_date?->format('d M Y') }}</p>
                </div>
                <a href="{{ route('shop-owner.finance.pdf', $invoice) }}" target="_blank"
                   class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[9px] font-black uppercase tracking-[0.12em] text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700 sm:px-4 sm:py-2 sm:text-xs">
                    Print / PDF
                </a>
            </div>
        </header>

        {{-- Bill Details Summary Row --}}
        <div class="grid grid-cols-2 gap-2 border-b border-dashed border-slate-400 py-2.5 text-[10px] font-bold leading-tight text-slate-800 sm:gap-3 sm:py-3 sm:text-[11px]">
            <div class="min-w-0">
                <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px] sm:tracking-[0.12em]">Invoice Ref</p>
                <p class="mt-0.5 break-all font-black text-slate-950 sm:mt-1">{{ $invoice->invoice_number }}</p>
                <p class="mt-0.5 text-slate-600 sm:mt-1">Items: {{ $billedItems->count() }}</p>
            </div>
            <div class="text-right">
                <div class="flex items-center justify-end gap-1.5 mb-1">
                    @include('shop-owner.finance.partials.payment-status-badge', ['invoice' => $invoice])
                </div>
                <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px]">Net Total</p>
                <p class="mt-0.5 text-xs font-black text-slate-950 sm:mt-1 sm:text-sm">Rs. {{ number_format($finalTotal, 2) }}</p>
            </div>
        </div>

        {{-- Receipt Table --}}
        <div class="overflow-x-auto border-b border-dashed border-slate-400 py-2 sm:py-3">
            <table class="w-full table-fixed text-left text-[9px] sm:text-[11px]">
                <thead class="border-b border-dashed border-slate-400 text-[8px] font-black uppercase text-slate-950 sm:text-[10px]">
                    <tr>
                        <th class="w-5 py-0.5 pr-0.5 sm:w-7 sm:py-1 sm:pr-1">SN</th>
                        <th class="py-0.5 pr-1 sm:py-1 sm:pr-2">Item</th>
                        <th class="w-14 py-0.5 pr-0.5 text-right sm:w-16 sm:py-1 sm:pr-1">Delivered</th>
                        <th class="w-14 py-0.5 pr-0.5 text-right sm:w-16 sm:py-1 sm:pr-1">Rate</th>
                        <th class="w-16 py-0.5 text-right sm:w-20 sm:py-1">Amt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($billedItems as $item)
                        @php
                            $qty = (float) ($item->delivered_price_quantity ?? $item->price_quantity ?? $item->delivered_qty ?? $item->approved_qty ?? 0);
                            $unitLabel = strtoupper($item->price_unit ?: $item->unit ?: $item->product?->unit ?: 'KG');
                            $rate = (float) ($item->unit_price ?? 0);
                            $lineTotal = $item->final_line_total !== null ? (float) $item->final_line_total : ($qty * $rate);
                        @endphp
                        <tr class="align-top">
                            <td class="py-1.5 pr-0.5 font-bold sm:py-2 sm:pr-1">{{ $loop->iteration }}</td>
                            <td class="py-1.5 pr-1 sm:py-2 sm:pr-2">
                                <p class="font-black leading-tight text-slate-950">
                                    @if($item->product?->sku)
                                        <span class="inline-block rounded bg-slate-100 px-1 py-0.5 text-[9px] font-black text-slate-700 mr-1">#{{ $item->product->sku }}</span>
                                    @endif
                                    {{ $item->product_name ?: $item->product?->name }}
                                </p>
                            </td>
                            <td class="py-1.5 pr-0.5 text-right font-bold text-slate-900 sm:py-2 sm:pr-1">
                                {{ number_format($qty, 2) }} {{ $unitLabel }}
                            </td>
                            <td class="py-1.5 pr-0.5 text-right font-bold text-slate-700 sm:py-2 sm:pr-1">
                                Rs. {{ number_format($rate, 2) }}
                            </td>
                            <td class="py-1.5 text-right font-black text-slate-950 sm:py-2">
                                Rs. {{ number_format($lineTotal, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-xs font-bold text-slate-500">No delivered products in this bill.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Receipt Totals Footer --}}
        <div class="ml-auto w-full border-b border-dashed border-slate-400 py-2.5 text-[10px] font-bold text-slate-800 sm:max-w-[20rem] sm:py-3 sm:text-[11px] space-y-1">
            <div class="flex items-center justify-between text-slate-700">
                <span>Subtotal</span>
                <span>Rs. {{ number_format($subtotal, 2) }}</span>
            </div>
            @if ($discountTotal > 0)
                <div class="flex items-center justify-between text-rose-700 font-bold">
                    <span>Discount</span>
                    <span>- Rs. {{ number_format($discountTotal, 2) }}</span>
                </div>
            @endif
            <div class="flex items-center justify-between font-black text-slate-950 text-xs sm:text-sm pt-1 border-t border-slate-200">
                <span>Net Invoice Total</span>
                <span>Rs. {{ number_format($finalTotal, 2) }}</span>
            </div>
            <div class="flex items-center justify-between text-emerald-700 font-bold">
                <span>Paid Amount</span>
                <span>Rs. {{ number_format($paidAmount, 2) }}</span>
            </div>
            <div class="flex items-center justify-between font-black {{ $balanceAmount > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                <span>Balance Due</span>
                <span>Rs. {{ number_format($balanceAmount, 2) }}</span>
            </div>
        </div>
    </div>
</section>
