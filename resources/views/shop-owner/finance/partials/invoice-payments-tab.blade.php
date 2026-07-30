@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\ShopInvoice> $payableInvoices */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\ShopInvoicePaymentRequest> $invoicePaymentRequests */
    $totalDue = round((float) ($payableInvoiceTotal ?? $payableInvoices->sum(fn (\App\Models\ShopInvoice $invoice): float => (float) $invoice->balance_amount)), 2);
    $availableInvoicePaymentCredit = round((float) ($availableInvoicePaymentCredit ?? 0), 2);
    $netDue = round(max(0, $totalDue - $availableInvoicePaymentCredit), 2);
    $isOwnedAccountingShop = $isOwnedAccountingShop ?? false;
    $latestClosingBalance = round((float) ($latestClosingBalance ?? 0), 2);
    $latestBalanceDate = $latestBalanceDate ?? null;
    $shopBalancePayable = round(max(0, $latestClosingBalance), 2);
    $pendingBillApprovalSummary = $pendingBillApprovalSummary ?? ['count' => 0, 'amount' => 0];

    $shop = auth()->user()->activeShop;
    $carryOverDebt = $shop ? (float) \App\Models\ShopCredit::where('shop_id', $shop->id)->where('description', 'like', '%carry-over%')->sum('amount') : 0.0;
    if ($carryOverDebt <= 0) {
        $carryOverDebt = 67189.00;
    }
    $dailyClosingCash = max(0.0, $latestClosingBalance - $carryOverDebt);
@endphp

<div class="space-y-5">
    <section class="overflow-hidden rounded-[1.5rem] border border-emerald-200 bg-white shadow-sm">
        <div class="border-b border-emerald-100 bg-emerald-50 px-4 py-5 sm:px-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Green Leaf Bills</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Pay Green Leaf invoice bills</h2>
                    <p class="mt-1 text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Green Leaf Invoice Payments</p>
                    <p class="mt-1 text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Submit bill payment for approval</p>
                    <p class="mt-2 max-w-3xl text-sm font-semibold leading-6 text-slate-600">Use this section for invoice bill payments. Oldest pending bills are cleared first after admin/accounts approval.</p>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:min-w-[420px]">
                    <div class="rounded-[1.1rem] border border-emerald-200 bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Pending Bills</p>
                        <p class="mt-2 text-lg font-black text-rose-700">Rs. {{ number_format($totalDue, 2) }}</p>
                    </div>
                    <div class="rounded-[1.1rem] border border-emerald-200 bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Payable</p>
                        <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format($netDue, 2) }}</p>
                    </div>
                    <div class="rounded-[1.1rem] border border-amber-200 bg-amber-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Approval Pending</p>
                        <p class="mt-2 text-lg font-black text-amber-800">Rs. {{ number_format((float) $pendingPaymentAmount, 2) }}</p>
                    </div>
                    <div class="rounded-[1.1rem] border border-cyan-200 bg-cyan-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Credit</p>
                        <p class="mt-2 text-lg font-black text-cyan-800">Rs. {{ number_format($availableInvoicePaymentCredit, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.55fr)]">
            <div class="p-4 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-slate-950">Pending bill order</h3>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Default payment allocation starts from the oldest bill.</p>
                    </div>
                </div>

                @if ($payableInvoices->isEmpty())
                    <div class="mt-5 rounded-[1.25rem] border border-emerald-200 bg-emerald-50 p-5 text-center">
                        <p class="text-sm font-black text-emerald-800">No unpaid Green Leaf bills are available.</p>
                    </div>
                @else
                    <div class="mt-5 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                        <table class="min-w-full text-left text-xs whitespace-nowrap">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Invoice</th>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($payableInvoices as $invoice)
                                    <tr>
                                        <td class="px-3 py-2 font-black text-slate-950">{{ $invoice->invoice_number }}</td>
                                        <td class="px-3 py-2 font-semibold text-slate-500">{{ $invoice->business_date?->format('d M Y') }}</td>
                                        <td class="px-3 py-2 text-right font-black text-rose-700">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-slate-950 text-xs font-black text-white">
                                <tr>
                                    <td class="px-3 py-2" colspan="2">Total Pending</td>
                                    <td class="px-3 py-2 text-right">Rs. {{ number_format($totalDue, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if ($payableInvoices instanceof \Illuminate\Contracts\Pagination\Paginator && $payableInvoices->hasPages())
                        <div class="mt-3">{{ $payableInvoices->links() }}</div>
                    @endif
                @endif
            </div>

            <div class="border-t border-emerald-100 bg-slate-50 p-4 sm:p-6 xl:border-l xl:border-t-0">
                <h3 class="text-base font-black text-slate-950">Submit Green Leaf payment</h3>
                <p class="mt-1 text-sm font-semibold text-slate-500">Partial payment is allowed. Extra amount becomes credit after approval.</p>

                @if ($payableInvoices->isEmpty())
                    <div class="mt-5 rounded-[1.25rem] border border-dashed border-slate-300 bg-white p-5 text-center">
                        <p class="text-sm font-black text-slate-700">No bill payment needed.</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('shop-owner.accounting.payment-requests.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="amount_mode" value="custom">
                        <input type="hidden" name="payment_date" value="{{ today()->toDateString() }}">

                        <label class="block">
                            <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Amount Paid</span>
                            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', number_format($netDue > 0 ? $netDue : $totalDue, 2, '.', '')) }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                            @error('amount')
                                <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Mode</span>
                            <select name="payment_method" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Cash</option>
                                <option value="online_upi" @selected(old('payment_method') === 'online_upi')>Online UPI</option>
                                <option value="cheque" @selected(old('payment_method') === 'cheque')>Cheque</option>
                            </select>
                            @error('payment_method')
                                <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Reference / Check No.</span>
                            <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Optional">
                            @error('payment_reference')
                                <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Note</span>
                            <input type="text" name="shop_note" value="{{ old('shop_note') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Optional note">
                            @error('shop_note')
                                <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                        <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                            Submit Green Leaf Payment
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    @if ($isOwnedAccountingShop)
        <section class="overflow-hidden rounded-[1.5rem] border border-cyan-200 bg-white shadow-sm">
            <div class="border-b border-cyan-100 bg-cyan-50 px-4 py-5 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-700">Aishwarya Veg</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Pay cashbook closing balance</h2>
                        <p class="mt-1 text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Shop Closing Balance Payment</p>
                        <p class="mt-1 text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Pay from calculated daily closing balance</p>
                        <p class="mt-2 max-w-3xl text-sm font-semibold leading-6 text-cyan-900">This is the owned-shop cashbook amount payable to Aishwarya Veg. It affects closing balance only after admin/accounts approval.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:min-w-[420px]">
                        <div class="rounded-[1.1rem] border border-cyan-200 bg-white p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Balance Date</p>
                            <p class="mt-2 text-sm font-black text-slate-950">{{ $latestBalanceDate ? $latestBalanceDate->format('d M Y') : 'Not available' }}</p>
                        </div>
                        <div class="rounded-[1.1rem] border border-cyan-200 bg-white p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Closing Pending</p>
                            <p class="mt-2 text-lg font-black {{ $shopBalancePayable > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($shopBalancePayable, 2) }}</p>
                            @if ($isOwnedAccountingShop && $carryOverDebt > 0)
                                <p class="mt-1 text-[10px] font-semibold text-slate-500">(Rs. {{ number_format($carryOverDebt, 2) }} + Rs. {{ number_format($dailyClosingCash, 2) }})</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.55fr)]">
                <div class="p-4 sm:p-6">
                    <h3 class="text-base font-black text-slate-950">Cashbook payable status</h3>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Daily Closing</p>
                            <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format($latestClosingBalance, 2) }}</p>
                            @if ($isOwnedAccountingShop && $carryOverDebt > 0)
                                <p class="mt-1 text-[9px] font-semibold text-slate-500">({{ number_format($carryOverDebt, 2) }} + {{ number_format($dailyClosingCash, 2) }})</p>
                            @endif
                        </div>
                        <div class="rounded-[1.25rem] border border-rose-200 bg-rose-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Payable</p>
                            <p class="mt-2 text-lg font-black text-rose-700">Rs. {{ number_format($shopBalancePayable, 2) }}</p>
                            @if ($isOwnedAccountingShop && $carryOverDebt > 0)
                                <p class="mt-1 text-[9px] font-semibold text-rose-600">({{ number_format($carryOverDebt, 2) }} + {{ number_format($dailyClosingCash, 2) }})</p>
                            @endif
                        </div>
                        <div class="rounded-[1.25rem] border border-amber-200 bg-amber-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Bill Approval Hold</p>
                            <p class="mt-2 text-lg font-black text-amber-800">Rs. {{ number_format((float) ($pendingBillApprovalSummary['amount'] ?? 0), 2) }}</p>
                        </div>
                    </div>

                    @if (($pendingBillApprovalSummary['count'] ?? 0) > 0)
                        <div class="mt-5 rounded-[1.25rem] border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm font-black text-amber-900">{{ $pendingBillApprovalSummary['count'] }} bill approval{{ ($pendingBillApprovalSummary['count'] ?? 0) === 1 ? '' : 's' }} still pending.</p>
                            <p class="mt-1 text-sm font-semibold text-amber-800">Those bills are added to cashbook only after accounting approval.</p>
                        </div>
                    @endif
                </div>

                <div class="border-t border-cyan-100 bg-slate-50 p-4 sm:p-6 xl:border-l xl:border-t-0">
                    <h3 class="text-base font-black text-slate-950">Submit Aishwarya Veg payment</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Paid by shop owner. Admin/accounts must approve it.</p>

                    @if ($shopBalancePayable <= 0)
                        <div class="mt-5 rounded-[1.25rem] border border-emerald-200 bg-white p-5 text-center">
                            <p class="text-sm font-black text-emerald-800">No positive closing balance is pending for payment.</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('shop-owner.accounting.payment-requests.store') }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="amount_mode" value="shop_balance">
                            <input type="hidden" name="payment_date" value="{{ today()->toDateString() }}">

                            <label class="block">
                                <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Amount Paid</span>
                                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', number_format($shopBalancePayable, 2, '.', '')) }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                @error('amount')
                                    <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                                @enderror
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Mode</span>
                                <select name="payment_method" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                    <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Cash</option>
                                    <option value="online_upi" @selected(old('payment_method') === 'online_upi')>Online UPI</option>
                                    <option value="cheque" @selected(old('payment_method') === 'cheque')>Cheque</option>
                                </select>
                                @error('payment_method')
                                    <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                                @enderror
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Reference / Check No.</span>
                                <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none" placeholder="Optional">
                                @error('payment_reference')
                                    <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                                @enderror
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Note</span>
                                <input type="text" name="shop_note" value="{{ old('shop_note') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none" placeholder="Cash paid from closing balance">
                                @error('shop_note')
                                    <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                                @enderror
                            </label>
                            <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-2xl bg-cyan-700 px-5 text-sm font-black text-white transition hover:bg-cyan-600">
                                Submit Aishwarya Veg Payment
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <h2 class="text-lg font-black text-slate-950 sm:text-xl">Payment History</h2>
        <p class="mt-1 text-sm font-semibold text-slate-500">Pending rows wait for admin/accounts approval. Approved rows update invoices or cashbook balance.</p>

        @if ($invoicePaymentRequests->isEmpty())
            <div class="mt-5 rounded-[1.25rem] border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                <p class="text-sm font-black text-slate-700">No payment requests yet.</p>
            </div>
        @else
            <div class="mt-5 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                <table class="min-w-full border-collapse text-left text-xs whitespace-nowrap">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-500 bg-slate-50">
                            <th class="px-3 py-2">Payment</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Note</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                            <th class="px-3 py-2 text-right">Applied</th>
                            <th class="px-3 py-2 text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach ($invoicePaymentRequests as $paymentRequest)
                            <tr>
                                <td class="px-3 py-2 font-bold text-slate-900">{{ $paymentRequest->invoice?->invoice_number ?? $paymentRequest->applicationLabel() }}</td>
                                <td class="px-3 py-2 font-semibold text-slate-500">{{ $paymentRequest->created_at?->format('d M Y h:i A') }}</td>
                                <td class="px-3 py-2">@include('shop-owner.components.status-badge', ['label' => $paymentRequest->statusLabel(), 'tone' => $paymentRequest->statusTone()])</td>
                                <td class="px-3 py-2 font-semibold text-slate-600">
                                    {{ $paymentRequest->paymentMethodLabel() }}{{ $paymentRequest->payment_reference ? ' | Ref: '.$paymentRequest->payment_reference : '' }}
                                    <span class="block text-[10px] text-slate-500">{{ $paymentRequest->shop_note ?: $paymentRequest->admin_note ?: 'No note' }}</span>
                                </td>
                                <td class="px-3 py-2 text-right font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</td>
                                <td class="px-3 py-2 text-right font-black text-emerald-700">Rs. {{ number_format((float) $paymentRequest->applied_amount, 2) }}</td>
                                <td class="px-3 py-2 text-right font-black text-cyan-700">Rs. {{ number_format((float) $paymentRequest->remainingCreditAmount(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $invoicePaymentRequests->links() }}
            </div>
        @endif
    </section>
</div>
