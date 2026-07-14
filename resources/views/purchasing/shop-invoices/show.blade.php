@extends('purchase-manager.layouts.app')

@section('title', $invoice->invoice_number)
@section('page_title', 'Shop Daily Invoice')
@section('page_description', 'Review invoice lines, delivery status, delivery discrepancies, and payment actions.')

@section('content')
    @php
        $invoiceDate = $invoice->business_date->toDateString();
        $invoiceCarbonDate = $invoice->business_date->copy();
    @endphp
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Shop Daily Invoice</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $invoice->invoice_number }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $invoice->shop?->name }} · {{ $invoice->business_date->format('d F Y') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('purchasing.shop-invoices.pdf', $invoice) }}" target="_blank" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                        Print / PDF
                    </a>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                        {{ $invoice->delivery_status === 'awaiting_review' ? 'Awaiting Admin Review' : str($invoice->delivery_status)->replace('_', ' ')->title() }}
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                        {{ str($invoice->payment_status)->replace('_', ' ')->title() }}
                    </span>
                </div>
            </div>

            <div class="border-b border-slate-100 px-5 py-4">
                <form method="GET" action="{{ route('purchasing.shop-invoices.index') }}" class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('purchasing.shop-invoices.index', ['date' => $invoiceCarbonDate->copy()->subDay()->toDateString()]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">
                        Prev Date
                    </a>
                    <input type="date" name="date" value="{{ $invoiceDate }}" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-900">
                    <a href="{{ route('purchasing.shop-invoices.index', ['date' => today()->toDateString()]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 hover:bg-emerald-50">
                        Today
                    </a>
                    <a href="{{ route('purchasing.shop-invoices.index', ['date' => $invoiceCarbonDate->copy()->addDay()->toDateString()]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">
                        Next Date
                    </a>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-slate-800">
                        Open Invoices For Date
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Shop</th>
                            <th class="px-5 py-4">Business Date</th>
                            <th class="px-5 py-4 text-right">Subtotal</th>
                            <th class="px-5 py-4 text-right">Shortage</th>
                            <th class="px-5 py-4 text-right">Discount</th>
                            <th class="px-5 py-4 text-right">Paid</th>
                            <th class="px-5 py-4 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-950">{{ $invoice->shop?->name ?? 'N/A' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ $invoice->business_date->format('d M Y') }}</td>
                            <td class="px-5 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->subtotal, 2) }}</td>
                            <td class="px-5 py-4 text-right font-black text-amber-600">Rs. {{ number_format((float) $invoice->shortage_total, 2) }}</td>
                            <td class="px-5 py-4 text-right font-black text-indigo-700">Rs. {{ number_format((float) $invoice->discount_total, 2) }}</td>
                            <td class="px-5 py-4 text-right font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</td>
                            <td class="px-5 py-4 text-right font-black text-rose-600">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        @if (($invoice->delivery_status === 'awaiting_review' || $invoice->order?->delivery_status === 'pending_approval') && (auth()->user()->hasRole('purchase') || auth()->user()->hasRole('admin')))
            <section class="rounded-3xl border border-amber-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-amber-100 bg-amber-50/70 px-5 py-5 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-amber-700">Delivery Review</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">Admin Approval Required</h2>
                        <p class="mt-2 text-sm text-slate-700">Review each line in table form, approve the final received quantity, or send the delivery back for correction.</p>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-amber-700">
                        Finance impact pending
                    </span>
                </div>

                <form id="delivery-discrepancy-approve-form" method="POST" action="{{ route('requisitions.delivery.approve', $invoice->order?->order_number) }}">
                    @csrf
                    <input type="hidden" name="invoice_number" value="{{ $invoice->invoice_number }}">
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-amber-100 bg-amber-50/40 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4">Product</th>
                                <th class="px-4 py-4 text-right">Approved</th>
                                <th class="px-4 py-4 text-right">Reported Received</th>
                                <th class="px-4 py-4 text-right">Reported Short</th>
                                <th class="px-4 py-4 text-right">Invoice Impact</th>
                                <th class="px-4 py-4">Approved Delivered Qty</th>
                                <th class="px-5 py-4">Manager Item Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($invoice->items as $item)
                                @php
                                    $reportedDeliveredQty = (float) ($item->orderItem?->shop_reported_received_qty ?? $item->delivered_qty);
                                    $reportedShortageQty = (float) ($item->orderItem?->shop_reported_missing_qty ?? $item->shortage_qty);
                                    $approvedDeliveredQty = old("approved_delivered_qty.{$item->shop_order_item_id}", number_format($reportedDeliveredQty, 2, '.', ''));
                                    $reportedShortageAmount = round($reportedShortageQty * (float) $item->unit_price, 2);
                                @endphp
                                <tr class="align-top">
                                    <td class="px-5 py-4">
                                        <p class="font-black text-slate-950">{{ $item->product_name }}</p>
                                        <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ strtoupper($item->unit) }}</p>
                                        @if ($item->orderItem?->notes)
                                            <p class="mt-2 text-xs text-slate-500">Existing note: {{ $item->orderItem->notes }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right font-semibold text-slate-900">{{ number_format((float) $item->approved_qty, 2) }}</td>
                                    <td class="px-4 py-4 text-right font-semibold text-slate-900">{{ number_format($reportedDeliveredQty, 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black {{ $reportedShortageQty > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($reportedShortageQty, 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black {{ $reportedShortageAmount > 0 ? 'text-rose-600' : 'text-slate-900' }}">Rs. {{ number_format($reportedShortageAmount, 2) }}</td>
                                    <td class="px-4 py-4">
                                        <input
                                            form="delivery-discrepancy-approve-form"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="{{ number_format((float) $item->approved_qty, 2, '.', '') }}"
                                            name="approved_delivered_qty[{{ $item->shop_order_item_id }}]"
                                            value="{{ $approvedDeliveredQty }}"
                                            class="w-full min-w-[140px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900"
                                        >
                                        @error("approved_delivered_qty.{$item->shop_order_item_id}")
                                            <span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td class="px-5 py-4">
                                        <textarea
                                            form="delivery-discrepancy-approve-form"
                                            name="item_review_notes[{{ $item->shop_order_item_id }}]"
                                            rows="3"
                                            class="w-full min-w-[260px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900"
                                            placeholder="Example: Reported shortage confirmed after recount."
                                        >{{ old("item_review_notes.{$item->shop_order_item_id}") }}</textarea>
                                        @error("item_review_notes.{$item->shop_order_item_id}")
                                            <span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-4 border-t border-slate-100 px-5 py-5">
                    <label class="block">
                        <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Approval Note</span>
                        <textarea form="delivery-discrepancy-approve-form" name="review_note" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Optional note for approval">{{ old('review_note') }}</textarea>
                    </label>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button form="delivery-discrepancy-approve-form" type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white hover:bg-emerald-700">
                            Approve Delivery Review
                        </button>

                        <form method="POST" action="{{ route('requisitions.delivery.reject', $invoice->order?->order_number) }}" class="flex-1 space-y-3">
                            @csrf
                            <input type="hidden" name="invoice_number" value="{{ $invoice->invoice_number }}">
                            <textarea name="review_note" rows="3" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Tell the shop owner what to correct">{{ old('review_note') }}</textarea>
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-rose-600 px-5 text-sm font-black text-white hover:bg-rose-700">
                                Request Delivery Correction
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-black text-slate-950">Invoice Items</h2>
            </div>
            <div class="overflow-x-auto">
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

        @if (auth()->user()->hasRole('purchase') || auth()->user()->hasRole('admin'))
            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Shop Payment Requests</h2>
                    <p class="mt-1 text-sm text-slate-600">Approve or reject requested collection adjustments from the shop.</p>
                </div>

                @if ($invoice->paymentRequests->isEmpty())
                    <div class="px-5 py-10 text-center text-sm font-bold text-slate-500">
                        No shop payment requests for this invoice yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-5 py-4">Type</th>
                                    <th class="px-5 py-4">Status</th>
                                    <th class="px-5 py-4 text-right">Requested Amount</th>
                                    <th class="px-5 py-4">Requested By</th>
                                    <th class="px-5 py-4">Notes</th>
                                    <th class="px-5 py-4">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($invoice->paymentRequests as $paymentRequest)
                                    <tr class="align-top">
                                        <td class="px-5 py-4 font-black text-slate-900">{{ ucfirst(str_replace('_', ' ', $paymentRequest->request_type)) }}</td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-700">
                                                {{ $paymentRequest->statusLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</td>
                                        <td class="px-5 py-4 text-slate-700">
                                            {{ $paymentRequest->requestedBy?->name ?? 'Shop Owner' }}
                                            <div class="mt-1 text-xs text-slate-500">{{ $paymentRequest->created_at?->format('d M Y h:i A') }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-700">
                                            @if ($paymentRequest->shop_note)
                                                <p><span class="font-black text-slate-900">Shop:</span> {{ $paymentRequest->shop_note }}</p>
                                            @endif
                                            @if ($paymentRequest->admin_note)
                                                <p class="mt-2"><span class="font-black text-slate-900">Admin:</span> {{ $paymentRequest->admin_note }}</p>
                                            @endif
                                            @if (! $paymentRequest->shop_note && ! $paymentRequest->admin_note)
                                                <span class="text-slate-400">No notes</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4">
                                            @if ($paymentRequest->status === 'pending')
                                                <form method="POST" action="{{ route('purchasing.shop-invoices.payment-requests.review', $paymentRequest) }}" class="space-y-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <textarea name="admin_note" rows="3" class="w-full min-w-[240px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Admin note"></textarea>
                                                    <div class="flex flex-col gap-3 sm:flex-row">
                                                        <button type="submit" name="decision" value="approve" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white hover:bg-emerald-700">
                                                            Approve Request
                                                        </button>
                                                        <button type="submit" name="decision" value="reject" class="inline-flex h-11 items-center justify-center rounded-2xl bg-rose-600 px-5 text-sm font-black text-white hover:bg-rose-700">
                                                            Reject
                                                        </button>
                                                    </div>
                                                </form>
                                            @else
                                                <span class="text-sm font-semibold text-slate-500">No action pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                @php
                    $pendingBalanceAmount = max(0, round((float) $invoice->final_total - (float) $invoice->paid_amount, 2));
                @endphp
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Payment Approval</h2>
                    <p class="mt-1 text-sm text-slate-600">Update the final discount, paid amount, and payment note in one full-width row.</p>
                </div>
                <form method="POST" action="{{ route('purchasing.shop-invoices.payment-approval', $invoice) }}" class="space-y-4 px-5 py-5">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 hover:bg-emerald-100"
                            onclick="document.getElementById('payment-approval-paid-amount').value='{{ number_format((float) $invoice->final_total, 2, '.', '') }}'"
                        >
                            Paid Full
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-amber-700 hover:bg-amber-100"
                            onclick="document.getElementById('payment-approval-paid-amount').value='{{ number_format((float) $invoice->paid_amount, 2, '.', '') }}'"
                        >
                            Pending Balance
                        </button>
                        <span class="inline-flex h-10 items-center rounded-2xl bg-slate-100 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-600">
                            Pending now: Rs. {{ number_format($pendingBalanceAmount, 2) }}
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-4">Discount</th>
                                    <th class="px-4 py-4">Paid Amount</th>
                                    <th class="px-4 py-4">Payment Note</th>
                                    <th class="px-4 py-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="align-top">
                                    <td class="px-4 py-4">
                                        <input type="number" step="0.01" min="0" name="discount_total" value="{{ old('discount_total', number_format((float) $invoice->discount_total, 2, '.', '')) }}" class="w-full min-w-[160px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900">
                                    </td>
                                    <td class="px-4 py-4">
                                        <input id="payment-approval-paid-amount" type="number" step="0.01" min="0" name="paid_amount" value="{{ old('paid_amount', number_format((float) $invoice->paid_amount, 2, '.', '')) }}" class="w-full min-w-[160px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900">
                                    </td>
                                    <td class="px-4 py-4">
                                        <textarea name="payment_note" rows="4" class="w-full min-w-[320px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900">{{ old('payment_note', $invoice->payment_note) }}</textarea>
                                    </td>
                                    <td class="px-4 py-4">
                                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white hover:bg-slate-800">
                                            Save Payment Approval
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </form>
            </section>
        @endif

        @if (auth()->user()->hasRole('admin'))
            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Admin Price Refresh</h2>
                    <p class="mt-1 text-sm text-slate-600">Push updated approved prices into this invoice using a single full-width row.</p>
                </div>
                <div class="overflow-x-auto border-b border-slate-100">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                            <tr>
                                <th class="px-4 py-4">Product</th>
                                <th class="px-4 py-4 text-right">Approved Qty</th>
                                <th class="px-4 py-4 text-right">Current Unit Price</th>
                                <th class="px-4 py-4 text-right">Current Line Subtotal</th>
                                <th class="px-4 py-4 text-right">Current Shortage Amount</th>
                                <th class="px-4 py-4 text-right">Current Final Line Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td class="px-4 py-4 font-semibold text-slate-900">{{ $item->product_name }}</td>
                                    <td class="px-4 py-4 text-right text-slate-700">{{ number_format((float) $item->approved_qty, 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-slate-900">Rs. {{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-slate-900">Rs. {{ number_format((float) $item->line_subtotal, 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-amber-700">Rs. {{ number_format((float) $item->shortage_amount, 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-slate-900">Rs. {{ number_format((float) $item->final_line_total, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50/70">
                                <td class="px-4 py-4 font-black text-slate-950">Total</td>
                                <td class="px-4 py-4"></td>
                                <td class="px-4 py-4"></td>
                                <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->subtotal, 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-amber-700">Rs. {{ number_format((float) $invoice->shortage_total, 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('purchasing.shop-invoices.reprice', $invoice) }}" class="space-y-4 px-5 py-5">
                    @csrf
                    @method('PATCH')
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-4">Reason</th>
                                    <th class="px-4 py-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="align-top">
                                    <td class="px-4 py-4">
                                        <textarea name="reason" rows="4" class="w-full min-w-[480px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900">{{ old('reason', $invoice->admin_price_note) }}</textarea>
                                    </td>
                                    <td class="px-4 py-4">
                                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-indigo-600 px-5 text-sm font-black text-white hover:bg-indigo-700">
                                            Refresh Invoice Prices
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
