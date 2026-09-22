@forelse ($completedCarts as $cart)
    @php
        $receiptNotes = $relatedReceiptNotes[$cart->id] ?? '';
        $receiptDiscrepancy = $relatedReceiptDiscrepancies[$cart->id] ?? null;
    @endphp
    <article id="cart-card-{{ $cart->id }}" class="rounded-2xl border {{ ($focusCartId ?? null) === $cart->id ? 'border-teal-300 ring-2 ring-teal-100' : 'border-slate-200' }} bg-white p-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="min-w-0">
                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $cart->cart_number }}</p>
                <h3 class="mt-1 truncate text-sm font-black text-slate-950">{{ $cart->supplier?->name ?: 'Supplier pending' }}</h3>
                <p class="mt-1 text-xs font-semibold text-slate-600">Bill {{ $cart->bill_number ?: 'Pending' }} • Fully settled</p>
            </div>
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Completed</span>
        </div>

        <details class="mt-3 rounded-2xl border border-slate-100 bg-slate-50 p-2" {{ $cart->purchaseInvoice ? 'open' : '' }}>
            <summary class="cursor-pointer px-2 py-1 text-[10px] font-black text-slate-700">
                {{ $cart->purchaseInvoice ? 'Edit Qty / Price (Processed Bill)' : 'View Cart Items' }}
            </summary>
            <div class="mt-2 border-t border-slate-200/60 pt-2">
                @if ($cart->purchaseInvoice)
                    <form action="{{ route('purchaser.carts.items.update-all', $cart) }}" method="POST" class="space-y-2">
                        @csrf
                        @method('PATCH')
                        @foreach ($cart->items as $item)
                            <div class="rounded-xl border border-slate-200 bg-white p-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-[10px] font-black text-slate-800">{{ $item->product->name }}</span>
                                    <span class="text-[10px] font-black text-slate-900">₹<span id="processed-total-{{ $item->id }}">{{ number_format((float) $item->line_total, 2, '.', '') }}</span></span>
                                </div>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[9px] font-bold text-slate-500">Qty ({{ $item->product->unit }})</label>
                                        <input type="number" step="any" min="0.01" name="items[{{ $item->id }}][quantity]" id="processed-qty-{{ $item->id }}" value="{{ number_format((float) $item->quantity, 2, '.', '') }}" oninput="updateProcessedItemTotal({{ $item->id }})" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-center text-[10px] font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-slate-500">Price</label>
                                        <input type="number" step="0.01" min="0.01" name="items[{{ $item->id }}][unit_price]" id="processed-price-{{ $item->id }}" value="{{ number_format((float) $item->unit_price, 2, '.', '') }}" oninput="updateProcessedItemTotal({{ $item->id }})" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-center text-[10px] font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                                    </div>
                                    @if ($cart->items->count() > 1 && $cart->goodsReceived?->status !== 'approved')
                                        <button type="button" onclick="confirmDeleteItem({{ $item->id }}, '{{ route('purchaser.cart-items.destroy', $item) }}', 'completed', {{ $cart->id }})" class="mt-5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600" title="Remove item" aria-label="Remove {{ $item->product->name }}">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v3M4 7h16" /></svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        <input type="hidden" name="action" value="processed_update">
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-lg bg-teal-600 px-3 text-[10px] font-black text-white hover:bg-teal-500">
                            Update Qty, Price & Total
                        </button>
                    </form>
                    <form action="{{ route('purchaser.invoices.destroy', $cart->purchaseInvoice) }}" method="POST" class="mt-2" onsubmit="return confirm('Cancel this bill and revert to pending? The bill will be cancelled with a full audit trail.');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="tab" value="completed">
                        <input type="hidden" name="cancellation_note" value="Cancelled by purchaser from vendor bill view.">
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-[10px] font-black text-rose-700 hover:bg-rose-100">
                            Cancel Bill
                        </button>
                    </form>
                @else
                    <div class="space-y-1">
                        @foreach ($cart->items as $item)
                            <div class="flex items-center justify-between gap-2 px-2 py-1 text-[10px] font-bold text-slate-600">
                                <span class="truncate">{{ $item->product->name }}</span>
                                <span>{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }} • ₹{{ number_format((float) $item->line_total, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </details>

        @if ($receiptNotes !== '')
            <div class="mt-3 rounded-2xl border border-emerald-100 bg-emerald-50 px-3 py-3 text-xs font-semibold text-emerald-800">
                Receipt note: {{ $receiptNotes }}
            </div>
        @endif

        @if (filled($receiptDiscrepancy))
            <div class="mt-3 rounded-2xl border border-blue-200 bg-blue-50 px-3 py-3 text-xs font-semibold text-blue-800">
                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-blue-700">Delivery Discrepancy</p>
                <p class="mt-1 whitespace-pre-line">{{ $receiptDiscrepancy }}</p>
            </div>
        @endif

        <!-- Embedded Bill Receipt (Collapsed as Default) -->
        @if ($cart->purchaseInvoice)
            <details class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-2 text-slate-900">
                <summary class="cursor-pointer px-2 py-1 text-[10px] font-black text-cyan-700 hover:text-cyan-600 select-none">
                    View Matched Bill Invoice
                </summary>
                <div class="mt-3 border-t border-dashed border-slate-300 pt-3 px-1">
                    @php
                        $invoice = $cart->purchaseInvoice;
                        $payableTotal = max(0, (float) $invoice->amount - (float) $invoice->discount_amount);
                        $paidAmount = (float) $invoice->paid_amount;
                        $balanceAmount = max(0, $payableTotal - $paidAmount);
                        $paymentMethod = $invoice->payment_method ?: 'Credit';
                        $supplier = $invoice->supplier;
                        $businessDate = $cart->business_date;

                        $statusRibbonText = match(true) {
                            $invoice->status->value === 'paid' => 'PAID',
                            $balanceAmount <= 0 => 'PAID',
                            $paidAmount > 0 => 'PARTIAL',
                            default => 'UNPAID',
                        };

                        $statusRibbonColor = match($statusRibbonText) {
                            'PAID' => 'text-emerald-700 bg-emerald-100 border border-emerald-200',
                            'PARTIAL' => 'text-cyan-700 bg-cyan-100 border border-cyan-200',
                            default => 'text-amber-700 bg-amber-100 border border-amber-200',
                        };
                    @endphp
                    
                    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
                        <div class="flex items-center justify-between border-b border-dashed border-slate-300 pb-2">
                            <div>
                                <p class="text-[9px] font-black text-slate-900 uppercase">BILL INVOICE</p>
                                <p class="text-[8px] font-bold text-slate-500">GREEN LEAF</p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-wider {{ $statusRibbonColor }}">
                                {{ $statusRibbonText }}
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 py-2 text-[9px] text-slate-700 border-b border-dashed border-slate-300">
                            <div>
                                <p>Bill: <span class="font-bold text-slate-900">{{ $invoice->invoice_number }}</span></p>
                                <p>Cart: <span class="font-bold text-slate-900">{{ $cart->cart_number }}</span></p>
                            </div>
                            <div class="text-right">
                                <p>Date: <span class="font-bold text-slate-900">{{ $businessDate->format('d M Y') }}</span></p>
                            </div>
                        </div>
                        
                        <div class="py-2 text-[9px] text-slate-700 border-b border-dashed border-slate-300">
                            <p class="font-black text-slate-950">{{ $supplier?->name }}</p>
                            <p class="mt-0.5 text-slate-600">{{ $supplier?->mobile_number }}</p>
                        </div>
                        
                        <table class="w-full text-left text-[9px] border-b border-dashed border-slate-300 py-1.5">
                            <thead>
                                <tr class="border-b border-dashed border-slate-200 font-bold text-slate-800">
                                    <th class="py-1">Item</th>
                                    <th class="py-1 text-right">Qty</th>
                                    <th class="py-1 text-right">Amt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cart->items as $item)
                                    <tr>
                                        <td class="py-1">{{ $item->product->name }}</td>
                                        <td class="py-1 text-right">{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }}</td>
                                        <td class="py-1 text-right">₹{{ number_format((float) $item->line_total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        
                        <div class="pt-2 text-[9px] space-y-1 text-slate-800">
                            <div class="flex justify-between font-bold text-slate-900">
                                <span>Total</span>
                                <span>₹{{ number_format($payableTotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-emerald-700 font-bold">
                                <span>Paid</span>
                                <span>₹{{ number_format($paidAmount, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-amber-700 font-bold">
                                <span>Balance</span>
                                <span>₹{{ number_format($balanceAmount, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
        @endif

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
            <div class="text-[10px] font-bold text-slate-500">
                Total: ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($cart->purchaseInvoice)
                    <a href="{{ route('purchaser.invoices.show', $cart->purchaseInvoice) }}" class="inline-flex h-8 items-center rounded-lg border border-teal-200 bg-teal-50 px-3 text-[10px] font-black text-teal-700 hover:bg-teal-100">
                        View Full Bill
                    </a>
                    <form action="{{ route('purchaser.invoices.destroy', $cart->purchaseInvoice) }}" method="POST" onsubmit="return confirm('Revert this cart to Pending? The bill will be cancelled (with full audit trail) so you can re-process it.');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="cancellation_note" value="Reverted to pending by purchaser from completed view.">
                        <button type="submit" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 text-[10px] font-black text-amber-700 hover:bg-amber-100">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                            Revert to Pending
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </article>
@empty
    <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">No completed carts for this business day.</p>
@endforelse
