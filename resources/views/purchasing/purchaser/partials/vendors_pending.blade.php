@forelse ($pendingCarts as $cart)
    @php
        $warehouseConfirmed = (bool) ($relatedBatchState[$cart->id]['warehouse_confirmed'] ?? false);
        $receiptNotes = $relatedReceiptNotes[$cart->id] ?? '';
        $invoice = $cart->purchaseInvoice;
        $payableTotal = $invoice ? max(0, (float) $invoice->amount - (float) $invoice->discount_amount) : (float) $cart->items->sum('line_total');
        $paidAmount = $invoice ? (float) $invoice->paid_amount : (float) $cart->paid_amount;
        $balanceAmount = max(0, $payableTotal - $paidAmount);
    @endphp
    <article id="cart-card-{{ $cart->id }}" class="overflow-hidden rounded-2xl border {{ ($focusCartId ?? null) === $cart->id ? 'border-teal-400 ring-2 ring-teal-200' : 'border-slate-200' }} bg-white shadow-sm transition-all duration-200 hover:shadow-md">
        {{-- Accordion Trigger Header --}}
        <div
            onclick="toggleCartCollapse({{ $cart->id }})"
            class="flex cursor-pointer items-center justify-between gap-3 bg-white p-4 transition hover:bg-slate-50/80 sm:px-5 select-none"
        >
            <div class="flex items-center gap-3 min-w-0">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $warehouseConfirmed ? 'bg-amber-50 text-amber-700' : 'bg-cyan-50 text-cyan-700' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                </span>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="truncate text-sm font-black text-slate-900">{{ $cart->supplier?->name ?: 'Supplier pending' }}</h3>
                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase {{ $warehouseConfirmed ? 'bg-amber-100 text-amber-800' : 'bg-cyan-100 text-cyan-800' }}">
                            {{ $warehouseConfirmed ? 'Payment Pending' : 'Processing' }}
                        </span>
                    </div>
                    <p class="mt-0.5 truncate text-[11px] font-semibold text-slate-500">
                        Bill: {{ $cart->bill_number ?: ($invoice?->invoice_number ?: 'Pending') }} • Cart: {{ $cart->cart_number }} • {{ $cart->items->count() }} items
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <div class="text-right">
                    <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                    <p class="font-mono text-sm font-black text-slate-950 sm:text-base">
                        ₹<span id="header-total-{{ $cart->id }}">{{ number_format((float) $payableTotal, 2) }}</span>
                    </p>
                    @if ($balanceAmount > 0)
                        <p class="text-[10px] font-bold text-amber-600">Due ₹{{ number_format((float) $balanceAmount, 2) }}</p>
                    @endif
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
                        <p class="text-sm font-black text-slate-900">{{ $cart->bill_number ?: ($invoice?->invoice_number ?: 'Pending') }}</p>
                        <div class="mt-1 space-y-0.5 text-[11px] font-semibold text-slate-500">
                            <p>Cart: {{ $cart->cart_number }}</p>
                            <p>Date: {{ $cart->business_date?->format('d M Y') ?: (isset($selectedDate) ? $selectedDate->format('d M Y') : date('d M Y')) }}</p>
                            <p>Source: {{ $cart->purchaseSourceLabel() }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold {{ $warehouseConfirmed ? 'bg-amber-100 text-amber-800' : 'bg-cyan-100 text-cyan-800' }}">
                            {{ $warehouseConfirmed ? '✓ Warehouse Confirmed' : '⏳ Waiting Receipt' }}
                        </span>
                        @if ($receiptNotes !== '')
                            <span class="text-[10px] font-medium text-slate-500 max-w-[160px] truncate text-right" title="{{ $receiptNotes }}">
                                Note: {{ $receiptNotes }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Vendor Info Section --}}
            <div class="border-b border-dashed border-slate-300 bg-slate-50/20 px-4 py-3 sm:px-6">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">VENDOR</p>
                        <p class="mt-0.5 text-sm font-black text-slate-900">{{ $cart->supplier?->name ?: 'Supplier pending' }}</p>
                        <p class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $cart->supplier?->mobile_number ?: 'Mobile pending' }}{{ $cart->supplier?->location ? ' • '.$cart->supplier->location : '' }}</p>
                        @if ($cart->supplier?->payment_terms)
                            <p class="text-[11px] font-semibold text-slate-500">Terms: {{ $cart->supplier->payment_terms }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" onclick="openChangeVendorModal(@js($cart->cart_number), 'pending', {{ $cart->id }})" class="inline-flex h-8 items-center justify-center rounded-lg border border-teal-200 bg-teal-50 px-2.5 text-[11px] font-bold text-teal-700 hover:bg-teal-100 transition">
                            Change Vendor
                        </button>
                        <button type="button" onclick="openCreateVendorModal(@js($cart->cart_number), 'pending', {{ $cart->id }})" class="inline-flex h-8 items-center justify-center rounded-lg bg-teal-600 px-2.5 text-[11px] font-bold text-white hover:bg-teal-500 transition">
                            + New
                        </button>
                    </div>
                </div>
            </div>

            {{-- Form / Table for Cart Items --}}
            @if ($invoice)
                <form action="{{ route('purchaser.carts.items.update-all', $cart) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="overflow-x-auto px-4 py-2 sm:px-6">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-dashed border-slate-300 text-[10px] font-black uppercase text-slate-950">
                                <tr>
                                    <th class="w-6 py-2 pr-1">SN</th>
                                    <th class="py-2 pr-2">ITEM</th>
                                    <th class="w-14 sm:w-16 py-2 pr-1 text-right">QTY</th>
                                    <th class="w-16 sm:w-20 py-2 pr-1 text-right">PRICE</th>
                                    <th class="w-20 py-2 text-right">AMT</th>
                                    <th class="w-6 py-2 text-right"><span class="sr-only">Remove</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-dashed divide-slate-200/80">
                                @foreach ($cart->items as $item)
                                    <tr class="align-top">
                                        <td class="py-2.5 pr-1 text-[11px] font-bold text-slate-400">{{ $loop->iteration }}</td>
                                        <td class="py-2.5 pr-2">
                                            <p class="font-bold text-slate-900 text-xs leading-snug break-words">{{ $item->product->name }}</p>
                                            <p class="mt-0.5 text-[10px] font-semibold text-slate-500">
                                                {{ $item->product->unit }}
                                                @if ((float) $item->unit_price > 0)
                                                    • Prev Rs. {{ number_format((float) $item->unit_price, 2) }}
                                                @endif
                                                @if ($item->is_extra_purchase)
                                                    <span class="ml-1 inline-flex items-center rounded bg-amber-100 px-1 py-0.2 text-[8px] font-black uppercase text-amber-700">EXTRA</span>
                                                @endif
                                            </p>
                                        </td>
                                        <td class="py-2.5 pr-1 text-right">
                                            <input
                                                type="number"
                                                step="any"
                                                min="0.01"
                                                name="items[{{ $item->id }}][quantity]"
                                                id="processed-qty-{{ $item->id }}"
                                                value="{{ number_format((float) $item->quantity, 2, '.', '') }}"
                                                oninput="updateProcessedItemTotal({{ $item->id }}, {{ $cart->id }})"
                                                class="h-7 w-12 sm:w-14 rounded-md border border-slate-200 bg-slate-50 px-1 text-right font-mono text-xs font-bold text-slate-950 focus:bg-white focus:outline-none"
                                            >
                                        </td>
                                        <td class="py-2.5 pr-1 text-right">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                name="items[{{ $item->id }}][unit_price]"
                                                id="processed-price-{{ $item->id }}"
                                                value="{{ number_format((float) $item->unit_price, 2, '.', '') }}"
                                                oninput="updateProcessedItemTotal({{ $item->id }}, {{ $cart->id }})"
                                                class="h-7 w-14 sm:w-16 rounded-md border border-slate-200 bg-slate-50 px-1 text-right font-mono text-xs font-bold text-slate-950 focus:bg-white focus:outline-none"
                                            >
                                        </td>
                                        <td class="py-2.5 text-right font-mono font-black text-slate-950 text-xs whitespace-nowrap">
                                            ₹<span id="processed-total-{{ $item->id }}">{{ number_format((float) $item->line_total, 2) }}</span>
                                        </td>
                                        <td class="py-2.5 pl-1 text-right">
                                            @if ($cart->items->count() > 1 && $cart->goodsReceived?->status !== 'approved')
                                                <button
                                                    type="button"
                                                    onclick="confirmDeleteItem({{ $item->id }}, '{{ route('purchaser.cart-items.destroy', $item) }}', 'pending', {{ $cart->id }})"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded text-base font-bold text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition"
                                                    title="Remove item"
                                                >
                                                    ×
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Summary & Totals --}}
                    <div class="border-t border-dashed border-slate-300 px-4 py-3 sm:px-6 space-y-3">
                        <div class="flex items-center justify-between font-black text-slate-950">
                            <span class="text-xs uppercase tracking-wider text-slate-500">Total Bill</span>
                            <span class="font-mono text-base text-slate-950">
                                ₹<span id="processed-cart-total-{{ $cart->id }}">{{ number_format((float) $payableTotal, 2) }}</span>
                            </span>
                        </div>

                        <div class="rounded-xl bg-slate-950 p-3.5 text-white shadow-xs">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">TOTAL BILL</span>
                                <span class="font-mono text-base font-black text-white">₹{{ number_format((float) $payableTotal, 2) }}</span>
                            </div>
                            @if ($paidAmount > 0)
                                <div class="mt-1 flex items-center justify-between text-xs text-emerald-400">
                                    <span>Paid Amount</span>
                                    <span class="font-mono font-bold">₹{{ number_format($paidAmount, 2) }}</span>
                                </div>
                            @endif
                            <div class="mt-1.5 flex items-center justify-between border-t border-slate-800 pt-1.5 text-xs">
                                <span class="text-slate-400">Remaining Balance</span>
                                <span class="font-mono font-black text-amber-400">₹{{ number_format($balanceAmount, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Bill Actions Footer --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-6">
                        <input type="hidden" name="action" value="processed_update">
                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl bg-teal-600 px-3.5 text-xs font-black text-white shadow-xs transition hover:bg-teal-500 active:scale-95">
                            Update Qty, Price & Total
                        </button>

                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('purchaser.invoices.show', $invoice) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-teal-200 bg-teal-50 px-3 text-xs font-black text-teal-700 transition hover:bg-teal-100">
                                View Full Bill
                            </a>
                            @if ($warehouseConfirmed && $cart->supplier)
                                <a href="{{ route('purchaser.suppliers.show', ['supplier' => $cart->supplier, 'date' => $cart->business_date?->format('Y-m-d') ?: ($date ?? date('Y-m-d'))]) }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-950 px-3.5 text-xs font-black text-white shadow-xs transition hover:bg-slate-800">
                                    Vendor Hub
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
                <div class="border-t border-slate-100 bg-slate-50/40 px-4 py-2 sm:px-6 text-right">
                    <form action="{{ route('purchaser.invoices.destroy', $invoice) }}" method="POST" class="inline" onsubmit="return confirm('Cancel this bill and revert to pending? The bill will be cancelled with a full audit trail.');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="tab" value="pending">
                        <input type="hidden" name="cancellation_note" value="Cancelled by purchaser from vendor bill view.">
                        <button type="submit" class="text-[11px] font-bold text-rose-600 hover:text-rose-800 hover:underline">
                            Cancel Bill
                        </button>
                    </form>
                </div>
            @else
                <div class="overflow-x-auto px-4 py-2 sm:px-6">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-dashed border-slate-300 text-[10px] font-black uppercase text-slate-950">
                            <tr>
                                <th class="w-6 py-2 pr-1">SN</th>
                                <th class="py-2 pr-2">ITEM</th>
                                <th class="w-20 py-2 pr-1 text-right">QTY</th>
                                <th class="w-24 py-2 text-right">AMT</th>
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
                                    <td class="py-2.5 text-right font-mono font-black text-slate-950">
                                        ₹{{ number_format((float) $item->line_total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-dashed border-slate-300 px-4 py-3 sm:px-6 font-black text-slate-950">
                    <span class="text-xs uppercase tracking-wider text-slate-500">Total Bill</span>
                    <span class="font-mono text-base">Total: ₹{{ number_format((float) $cart->items->sum('line_total') - (float) $cart->discount_amount, 2) }}</span>
                </div>
            @endif
        </div>
    </article>
@empty
    <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm font-bold text-slate-500">No pending submitted carts for this business day.</p>
@endforelse
