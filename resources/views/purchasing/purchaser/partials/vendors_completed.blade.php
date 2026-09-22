@forelse ($completedCarts as $cart)
    @php
        $receiptNotes = $relatedReceiptNotes[$cart->id] ?? '';
        $receiptDiscrepancy = $relatedReceiptDiscrepancies[$cart->id] ?? null;
        $invoice = $cart->purchaseInvoice;
        $payableTotal = $invoice ? max(0, (float) $invoice->amount - (float) $invoice->discount_amount) : (float) $cart->items->sum('line_total');
        $paidAmount = $invoice ? (float) $invoice->paid_amount : $payableTotal;
        $balanceAmount = max(0, $payableTotal - $paidAmount);
    @endphp
    <article id="cart-card-{{ $cart->id }}" class="overflow-hidden rounded-2xl border {{ ($focusCartId ?? null) === $cart->id ? 'border-emerald-400 ring-2 ring-emerald-200' : 'border-slate-200' }} bg-white shadow-sm transition-all duration-200 hover:shadow-md">
        {{-- Accordion Trigger Header --}}
        <div
            onclick="toggleCartCollapse({{ $cart->id }})"
            class="flex cursor-pointer items-center justify-between gap-3 bg-white p-4 transition hover:bg-slate-50/80 sm:px-5 select-none"
        >
            <div class="flex items-center gap-3 min-w-0">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="truncate text-sm font-black text-slate-900">{{ $cart->supplier?->name ?: 'Supplier pending' }}</h3>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">
                            Completed
                        </span>
                    </div>
                    <p class="mt-0.5 truncate text-[11px] font-semibold text-slate-500">
                        Bill: {{ $cart->bill_number ?: ($invoice?->invoice_number ?: 'Settled') }} • Cart: {{ $cart->cart_number }} • {{ $cart->items->count() }} items
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <div class="text-right">
                    <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                    <p class="font-mono text-sm font-black text-emerald-700 sm:text-base">
                        ₹<span id="header-total-{{ $cart->id }}">{{ number_format((float) $payableTotal, 2) }}</span>
                    </p>
                    <p class="text-[10px] font-bold text-emerald-600">✓ Settled</p>
                </div>
                <div id="chevron-{{ $cart->id }}" class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-transform duration-200 {{ ($focusCartId ?? null) === $cart->id || $loop->first ? 'rotate-180' : '' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                </div>
            </div>
        </div>

        {{-- Collapsible Content Body --}}
        <div id="cart-body-{{ $cart->id }}" class="{{ ($focusCartId ?? null) === $cart->id || $loop->first ? '' : 'hidden' }} border-t border-dashed border-slate-200">
            {{-- Bill Header Info --}}
            <div class="border-b border-dashed border-slate-300 bg-slate-50/40 px-4 pt-4 pb-3 sm:px-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">BILL NO</p>
                        <p class="text-sm font-black text-slate-900">{{ $cart->bill_number ?: ($invoice?->invoice_number ?: 'Settled') }}</p>
                        <div class="mt-1 space-y-0.5 text-[11px] font-semibold text-slate-500">
                            <p>Cart: {{ $cart->cart_number }}</p>
                            <p>Date: {{ $cart->business_date?->format('d M Y') ?: (isset($selectedDate) ? $selectedDate->format('d M Y') : date('d M Y')) }}</p>
                            <p>Source: {{ $cart->purchaseSourceLabel() }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-black text-emerald-800">
                        ✓ Fully Settled
                    </span>
                </div>
            </div>

            {{-- Vendor Info Section --}}
            <div class="border-b border-dashed border-slate-300 bg-slate-50/20 px-4 py-3 sm:px-6">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">VENDOR</p>
                        <p class="mt-0.5 text-sm font-black text-slate-900">{{ $cart->supplier?->name ?: 'Supplier pending' }}</p>
                        <p class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $cart->supplier?->mobile_number ?: '' }}{{ $cart->supplier?->location ? ' • '.$cart->supplier->location : '' }}</p>
                    </div>
                </div>

                @if ($receiptNotes !== '' || filled($receiptDiscrepancy))
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[10px] font-bold">
                        @if ($receiptNotes !== '')
                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-slate-700">
                                Receipt Note: {{ $receiptNotes }}
                            </span>
                        @endif
                        @if (filled($receiptDiscrepancy))
                            <span class="inline-flex items-center rounded-md bg-blue-100 px-2 py-0.5 text-blue-800">
                                Discrepancy: {{ $receiptDiscrepancy }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Receipt Items Table --}}
            <div class="overflow-x-auto px-4 py-2 sm:px-6">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-dashed border-slate-300 text-[10px] font-black uppercase text-slate-950">
                        <tr>
                            <th class="w-6 py-2 pr-1">SN</th>
                            <th class="py-2 pr-2">ITEM</th>
                            <th class="w-16 sm:w-20 py-2 pr-1 text-right">QTY</th>
                            <th class="w-16 sm:w-20 py-2 pr-1 text-right">PRICE</th>
                            <th class="w-20 py-2 text-right">AMT</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dashed divide-slate-200/80">
                        @foreach ($cart->items as $item)
                            <tr class="align-top">
                                <td class="py-2.5 pr-1 text-[11px] font-bold text-slate-400">{{ $loop->iteration }}</td>
                                <td class="py-2.5 pr-2">
                                    <p class="font-bold text-slate-900 text-xs leading-snug break-words">{{ $item->product->name }}</p>
                                    <p class="mt-0.5 text-[10px] font-semibold text-slate-500">Unit: {{ $item->product->unit }}</p>
                                </td>
                                <td class="py-2.5 pr-1 text-right font-mono font-bold text-slate-800">
                                    {{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }}
                                </td>
                                <td class="py-2.5 pr-1 text-right font-mono font-bold text-slate-800">
                                    ₹{{ number_format((float) $item->unit_price, 2) }}
                                </td>
                                <td class="py-2.5 text-right font-mono font-black text-slate-950">
                                    ₹{{ number_format((float) $item->line_total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Totals Summary --}}
            <div class="border-t border-dashed border-slate-300 px-4 py-3 sm:px-6 space-y-1 text-xs">
                <div class="flex items-center justify-between font-black text-slate-950">
                    <span class="text-slate-500 uppercase tracking-wider text-[10px]">Total Bill</span>
                    <span class="font-mono text-sm">Total: ₹{{ number_format((float) $payableTotal, 2) }}</span>
                </div>
                <div class="flex items-center justify-between font-bold text-emerald-700">
                    <span>Paid Amount</span>
                    <span class="font-mono font-black">₹{{ number_format((float) $paidAmount, 2) }}</span>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-6">
                <span class="text-[11px] font-bold text-slate-500">{{ $cart->items->count() }} item{{ $cart->items->count() !== 1 ? 's' : '' }}</span>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($invoice)
                        <a href="{{ route('purchaser.invoices.show', $invoice) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-teal-200 bg-teal-50 px-3.5 text-xs font-black text-teal-700 transition hover:bg-teal-100">
                            View Full Bill
                        </a>
                        <form action="{{ route('purchaser.invoices.destroy', $invoice) }}" method="POST" onsubmit="return confirm('Revert this cart to Pending? The bill will be cancelled (with full audit trail) so you can re-process it.');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="cancellation_note" value="Reverted to pending by purchaser from completed view.">
                            <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 text-xs font-black text-amber-700 hover:bg-amber-100 transition">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                                Revert to Pending
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </article>
@empty
    <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm font-bold text-slate-500">No completed carts for this business day.</p>
@endforelse
