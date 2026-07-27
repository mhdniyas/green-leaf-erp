@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\ShopInvoice> $payableInvoices */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\ShopInvoicePaymentRequest> $invoicePaymentRequests */
    $totalDue = round((float) ($payableInvoiceTotal ?? $payableInvoices->sum(fn (\App\Models\ShopInvoice $invoice): float => (float) $invoice->balance_amount)), 2);
    $availableInvoicePaymentCredit = round((float) ($availableInvoicePaymentCredit ?? 0), 2);
    $netDue = round(max(0, $totalDue - $availableInvoicePaymentCredit), 2);
@endphp

<div class="grid gap-5 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Green Leaf Invoice Payments</p>
        <h2 class="mt-2 text-lg font-black text-slate-950 sm:text-xl">Submit bill payment for approval</h2>
        <p class="mt-2 text-sm font-semibold leading-6 text-slate-600">These payments settle Green Leaf invoices. Accounting approval updates the invoice paid amount and posts the journal entry.</p>

        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Outstanding</p>
                <p class="mt-2 text-lg font-black text-rose-700">Rs. {{ number_format((float) $outstandingBalance, 2) }}</p>
            </div>
            <div class="rounded-[1.25rem] border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Available Credit</p>
                <p class="mt-2 text-lg font-black text-emerald-800">Rs. {{ number_format($availableInvoicePaymentCredit, 2) }}</p>
            </div>
            <div class="rounded-[1.25rem] border border-amber-200 bg-amber-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Pending Approval</p>
                <p class="mt-2 text-lg font-black text-amber-800">Rs. {{ number_format((float) $pendingPaymentAmount, 2) }}</p>
            </div>
            <div class="rounded-[1.25rem] border border-slate-200 bg-white p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Due</p>
                <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format($netDue, 2) }}</p>
            </div>
        </div>

        @if ($payableInvoices->isEmpty())
            <div class="mt-5 rounded-[1.25rem] border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm font-black text-emerald-800">No unpaid bills are available.</p>
            </div>
        @else
            <div class="mt-5 overflow-hidden rounded-[1.25rem] border border-slate-200">
                <div class="bg-slate-50 px-4 py-3">
                    <p class="text-sm font-black text-slate-950">Pending Bills</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Oldest bills are cleared first after accounting approval.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ($payableInvoices as $invoice)
                        <div class="grid grid-cols-[1fr_auto] gap-3 px-4 py-3 text-sm">
                            <div>
                                <p class="font-black text-slate-950">{{ $invoice->invoice_number }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->business_date?->format('d M Y') }}</p>
                            </div>
                            <p class="whitespace-nowrap text-right font-black text-rose-700">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="flex items-center justify-between gap-3 bg-slate-950 px-4 py-3 text-sm font-black text-white">
                    <span>Total Pending</span>
                    <span>Rs. {{ number_format($totalDue, 2) }}</span>
                </div>
            </div>

            @if ($payableInvoices instanceof \Illuminate\Contracts\Pagination\Paginator && $payableInvoices->hasPages())
                <div class="mt-5">{{ $payableInvoices->links() }}</div>
            @endif

            <form method="POST" action="{{ route('shop-owner.accounting.payment-requests.store') }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="amount_mode" value="custom">

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Amount Paid</span>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', number_format($netDue > 0 ? $netDue : $totalDue, 2, '.', '')) }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        @error('amount')
                            <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                        @enderror
                        <span class="mt-1 block text-xs font-semibold text-slate-500">You can pay less or more than the pending total. Extra becomes shop credit after approval.</span>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Note</span>
                        <input type="text" name="shop_note" value="{{ old('shop_note') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Cash paid, bank transfer, reference...">
                        @error('shop_note')
                            <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500 sm:w-auto">
                    Submit Payment Request
                </button>
            </form>
        @endif
    </section>

    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <h2 class="text-lg font-black text-slate-950 sm:text-xl">Payment History</h2>
        <p class="mt-1 text-sm font-semibold text-slate-500">Pending rows do not affect the journal. Approved rows update invoice payment and journal.</p>

        @if ($invoicePaymentRequests->isEmpty())
            <div class="mt-5 rounded-[1.25rem] border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                <p class="text-sm font-black text-slate-700">No payment requests yet.</p>
            </div>
        @else
            <div class="mt-5 space-y-3 md:hidden">
                @foreach ($invoicePaymentRequests as $paymentRequest)
                    <article class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-950">{{ $paymentRequest->invoice?->invoice_number ?? 'Shop payment' }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $paymentRequest->created_at?->format('d M Y h:i A') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="whitespace-nowrap text-sm font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</p>
                                @include('shop-owner.components.status-badge', ['label' => $paymentRequest->statusLabel(), 'tone' => $paymentRequest->statusTone()])
                                @if ($paymentRequest->status === 'approved')
                                    <p class="mt-1 text-xs font-bold text-emerald-700">Applied Rs. {{ number_format((float) $paymentRequest->applied_amount, 2) }}</p>
                                    @if ((float) $paymentRequest->credit_amount > 0)
                                        <p class="text-xs font-bold text-cyan-700">Credit Rs. {{ number_format((float) $paymentRequest->remainingCreditAmount(), 2) }}</p>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-5 hidden overflow-x-auto md:block">
                <table class="min-w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <th class="py-3 pr-4">Payment</th>
                            <th class="py-3 pr-4">Date</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Note</th>
                            <th class="py-3 text-right">Amount</th>
                            <th class="py-3 text-right">Applied</th>
                            <th class="py-3 text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                        @foreach ($invoicePaymentRequests as $paymentRequest)
                            <tr>
                                <td class="py-4 pr-4 font-bold text-slate-900">{{ $paymentRequest->invoice?->invoice_number ?? 'Shop payment' }}</td>
                                <td class="py-4 pr-4 font-semibold text-slate-500">{{ $paymentRequest->created_at?->format('d M Y h:i A') }}</td>
                                <td class="py-4 pr-4">@include('shop-owner.components.status-badge', ['label' => $paymentRequest->statusLabel(), 'tone' => $paymentRequest->statusTone()])</td>
                                <td class="py-4 pr-4 font-semibold text-slate-600">{{ $paymentRequest->shop_note ?: $paymentRequest->admin_note ?: 'No note' }}</td>
                                <td class="py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</td>
                                <td class="py-4 text-right font-black text-emerald-700">Rs. {{ number_format((float) $paymentRequest->applied_amount, 2) }}</td>
                                <td class="py-4 text-right font-black text-cyan-700">Rs. {{ number_format((float) $paymentRequest->remainingCreditAmount(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $invoicePaymentRequests->links() }}
            </div>
        @endif
    </section>
</div>
