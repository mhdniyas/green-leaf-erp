<x-layouts.app :title="$invoice->invoice_number">
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Shop Daily Invoice</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $invoice->invoice_number }}</h1>
                    <p class="mt-2 text-sm text-slate-600">{{ $invoice->shop?->name }} · {{ $invoice->business_date->format('d F Y') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('purchasing.shop-invoices.pdf', $invoice) }}" target="_blank" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                        Print / PDF
                    </a>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-slate-700">{{ str($invoice->delivery_status)->replace('_', ' ')->title() }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-slate-700">{{ str($invoice->payment_status)->replace('_', ' ')->title() }}</span>
                </div>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Subtotal</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) $invoice->subtotal, 2) }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shortage</p>
                    <p class="mt-2 text-2xl font-black text-amber-600">Rs. {{ number_format((float) $invoice->shortage_total, 2) }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Discount</p>
                    <p class="mt-2 text-2xl font-black text-indigo-700">Rs. {{ number_format((float) $invoice->discount_total, 2) }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Paid</p>
                    <p class="mt-2 text-2xl font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Balance</p>
                    <p class="mt-2 text-2xl font-black text-red-600">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                </div>
            </div>
        </section>

        @if (($invoice->delivery_status === 'received_with_discrepancy' || $invoice->order?->delivery_status === 'pending_approval') && (auth()->user()->hasRole('purchase') || auth()->user()->hasRole('admin')))
            <section class="rounded-3xl border border-amber-200 bg-amber-50/80 p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-amber-700">Delivery Review</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">Discrepancy Approval Required</h2>
                        <p class="mt-2 text-sm text-slate-700">Review delivered quantities product by product. You can adjust the final approved delivered qty, add notes for each item, then finalize the shortage into the invoice. Rejection sends the delivery check-in back to the shop owner for resubmission.</p>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-amber-700">
                        Finance impact pending
                    </span>
                </div>

                <form id="delivery-discrepancy-approve-form" method="POST" action="{{ route('requisitions.delivery.approve', $invoice->order?->order_number) }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="invoice_number" value="{{ $invoice->invoice_number }}">
                </form>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    @foreach ($invoice->items as $item)
                        @php
                            $hasShortage = (float) $item->shortage_qty > 0;
                            $reportedDeliveredQty = (float) $item->delivered_qty;
                            $reportedShortageQty = (float) $item->shortage_qty;
                            $approvedDeliveredQty = old("approved_delivered_qty.{$item->shop_order_item_id}", number_format($reportedDeliveredQty, 2, '.', ''));
                        @endphp
                        <article class="rounded-3xl border {{ $hasShortage ? 'border-amber-200 bg-white' : 'border-emerald-200 bg-white' }} p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-black text-slate-950">{{ $item->product_name }}</h3>
                                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ strtoupper($item->unit) }}</p>
                                </div>
                                <span class="{{ $hasShortage ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700' }} rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em]">
                                    {{ $hasShortage ? 'Shortage' : 'Matched' }}
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <span class="block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Approved</span>
                                    <span class="mt-1 block font-black text-slate-950">{{ number_format((float) $item->approved_qty, 2) }} {{ $item->unit }}</span>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <span class="block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Delivered</span>
                                    <span class="mt-1 block font-black text-slate-950">{{ number_format($reportedDeliveredQty, 2) }} {{ $item->unit }}</span>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <span class="block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Reported Short Qty</span>
                                    <span class="mt-1 block font-black {{ $hasShortage ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($reportedShortageQty, 2) }} {{ $item->unit }}</span>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <span class="block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Invoice Impact</span>
                                    <span class="mt-1 block font-black {{ $hasShortage ? 'text-red-600' : 'text-slate-950' }}">Rs. {{ number_format((float) $item->shortage_amount, 2) }}</span>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3">
                                <label class="block">
                                    <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Approved Delivered Qty</span>
                                    <input
                                        form="delivery-discrepancy-approve-form"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="{{ number_format((float) $item->approved_qty, 2, '.', '') }}"
                                        name="approved_delivered_qty[{{ $item->shop_order_item_id }}]"
                                        value="{{ $approvedDeliveredQty }}"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900"
                                    >
                                    @error("approved_delivered_qty.{$item->shop_order_item_id}")
                                        <span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="block">
                                    <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Manager Item Note</span>
                                    <textarea
                                        form="delivery-discrepancy-approve-form"
                                        name="item_review_notes[{{ $item->shop_order_item_id }}]"
                                        rows="3"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900"
                                        placeholder="Example: Reported 5 kg shortage, approved only 3 kg after recount."
                                    >{{ old("item_review_notes.{$item->shop_order_item_id}") }}</textarea>
                                    @if ($item->orderItem?->notes)
                                        <span class="mt-1 block text-xs text-slate-500">Existing item note: {{ $item->orderItem->notes }}</span>
                                    @endif
                                    @error("item_review_notes.{$item->shop_order_item_id}")
                                        <span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>
                                    @enderror
                                </label>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-2">
                    <div class="rounded-3xl border border-emerald-200 bg-white p-4">
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Approval Note</span>
                            <textarea form="delivery-discrepancy-approve-form" name="review_note" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Optional note for approval">{{ old('review_note') }}</textarea>
                        </label>
                        <button form="delivery-discrepancy-approve-form" type="submit" class="mt-4 inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white hover:bg-emerald-700">
                            Approve Discrepancy And Finalize Finance
                        </button>
                    </div>

                    <form method="POST" action="{{ route('requisitions.delivery.reject', $invoice->order?->order_number) }}" class="rounded-3xl border border-rose-200 bg-white p-4">
                        @csrf
                        <input type="hidden" name="invoice_number" value="{{ $invoice->invoice_number }}">
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Rejection Note</span>
                            <textarea name="review_note" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Tell the shop owner what to correct">{{ old('review_note') }}</textarea>
                        </label>
                        <button type="submit" class="mt-4 inline-flex w-full items-center justify-center rounded-2xl bg-rose-600 px-5 py-3 text-sm font-black text-white hover:bg-rose-700">
                            Reject And Reopen Delivery Check-In
                        </button>
                    </form>
                </div>
            </section>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-black text-slate-950">Invoice Items</h2>
            </div>
            <div class="space-y-3 p-4 md:hidden">
                @foreach ($invoice->items as $item)
                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="font-black text-slate-950">{{ $item->product_name }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ strtoupper($item->unit) }}</p>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div><span class="block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Approved</span><span class="mt-1 block font-semibold text-slate-900">{{ number_format((float) $item->approved_qty, 2) }}</span></div>
                            <div><span class="block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Delivered</span><span class="mt-1 block font-semibold text-slate-900">{{ number_format((float) $item->delivered_qty, 2) }}</span></div>
                            <div><span class="block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Unit Price</span><span class="mt-1 block font-semibold text-slate-900">Rs. {{ number_format((float) $item->unit_price, 2) }}</span></div>
                            <div><span class="block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Final</span><span class="mt-1 block font-black text-slate-950">Rs. {{ number_format((float) $item->final_line_total, 2) }}</span></div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Product</th>
                            <th class="px-5 py-4 text-right">Approved</th>
                            <th class="px-5 py-4 text-right">Delivered</th>
                            <th class="px-5 py-4 text-right">Unit Price</th>
                            <th class="px-5 py-4 text-right">Shortage</th>
                            <th class="px-5 py-4 text-right">Final</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td class="px-5 py-4 font-semibold text-slate-950">{{ $item->product_name }}</td>
                                <td class="px-5 py-4 text-right text-slate-700">{{ number_format((float) $item->approved_qty, 2) }}</td>
                                <td class="px-5 py-4 text-right text-slate-700">{{ number_format((float) $item->delivered_qty, 2) }}</td>
                                <td class="px-5 py-4 text-right text-slate-900">Rs. {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="px-5 py-4 text-right text-amber-600">Rs. {{ number_format((float) $item->shortage_amount, 2) }}</td>
                                <td class="px-5 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $item->final_line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            @if (auth()->user()->hasRole('purchase') || auth()->user()->hasRole('admin'))
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-950">Payment Approval</h2>
                    <p class="mt-1 text-sm text-slate-600">Purchase Manager updates discount and paid amount after settlement review.</p>
                    <form method="POST" action="{{ route('purchasing.shop-invoices.payment-approval', $invoice) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PATCH')
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Discount</span>
                                <input type="number" step="0.01" min="0" name="discount_total" value="{{ old('discount_total', number_format((float) $invoice->discount_total, 2, '.', '')) }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900">
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Paid Amount</span>
                                <input type="number" step="0.01" min="0" name="paid_amount" value="{{ old('paid_amount', number_format((float) $invoice->paid_amount, 2, '.', '')) }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900">
                            </label>
                        </div>
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Payment Note</span>
                            <textarea name="payment_note" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900">{{ old('payment_note', $invoice->payment_note) }}</textarea>
                        </label>
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white hover:bg-slate-800">Save Payment Approval</button>
                    </form>
                </section>
            @endif

            @if (auth()->user()->hasRole('admin'))
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-950">Admin Price Refresh</h2>
                    <p class="mt-1 text-sm text-slate-600">Only Admin can push updated approved prices into this invoice.</p>
                    <form method="POST" action="{{ route('purchasing.shop-invoices.reprice', $invoice) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PATCH')
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Reason</span>
                            <textarea name="reason" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900">{{ old('reason', $invoice->admin_price_note) }}</textarea>
                        </label>
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white hover:bg-indigo-700">Refresh Invoice Prices</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
