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

    $payableTotal = (float) ($payableTotal ?? 0);
    $payableReceivedTotal = (float) ($payableReceivedTotal ?? 0);
    $payableBalance = (float) ($payableBalance ?? $shopBalancePayable);
    $payableCategories = $payableCategories ?? collect();

    $shop = auth()->user()->activeShop;
    $carryOverDebt = $shop ? (float) \App\Models\ShopCredit::where('shop_id', $shop->id)->where('description', 'like', '%carry-over%')->sum('amount') : 0.0;
    if ($carryOverDebt <= 0) {
        $carryOverDebt = 67189.00;
    }
    $dailyClosingCash = max(0.0, $latestClosingBalance - $carryOverDebt);
@endphp

<div class="space-y-4 sm:space-y-5">
    {{-- Bills Section --}}
    <section class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-xs">
        <div class="border-b border-emerald-100 bg-emerald-50/70 p-3 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <span class="inline-block rounded-full bg-emerald-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-800">Green Leaf</span>
                    <h2 class="mt-1 text-base font-black text-slate-950 sm:text-lg">Delivery Bills (Invoices)</h2>
                    <p class="mt-0.5 text-xs font-semibold text-emerald-900">Track and pay your Green Leaf stock invoices.</p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:min-w-[420px]">
                    <div class="rounded-xl border border-emerald-200/80 bg-white p-2.5">
                        <p class="text-[9px] font-black uppercase tracking-wider text-emerald-700">Total Billed</p>
                        <p class="mt-0.5 text-xs font-black text-slate-950">Rs. {{ number_format($totalDue, 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-emerald-200/80 bg-white p-2.5">
                        <p class="text-[9px] font-black uppercase tracking-wider text-emerald-700">Net Payable</p>
                        <p class="mt-0.5 text-sm font-black {{ $netDue > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($netDue, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_minmax(340px,0.5fr)]">
            <div class="p-3 sm:p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-950">Pending Bills</h3>
                        <p class="text-xs text-slate-500">Invoices awaiting settlement.</p>
                    </div>
                    @if ($availableInvoicePaymentCredit > 0)
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-black text-emerald-700">Credit: Rs. {{ number_format($availableInvoicePaymentCredit, 2) }}</span>
                    @endif
                </div>

                @if ($payableInvoices->isEmpty())
                    <div class="mt-3 rounded-xl border border-emerald-200 bg-white p-4 text-center">
                        <p class="text-xs font-black text-emerald-800">All delivery bills are cleared.</p>
                    </div>
                @else
                    <div class="mt-3 hidden sm:block">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="py-2 pr-3">Date</th>
                                    <th class="py-2 pr-3">Invoice</th>
                                    <th class="py-2 pr-3 text-right">Billed</th>
                                    <th class="py-2 pr-3 text-right">Balance</th>
                                    <th class="py-2 pr-3">Status</th>
                                    <th class="py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($payableInvoices as $invoice)
                                    @php
                                        $invBalance = (float) $invoice->balance_amount;
                                        $invPaid = (float) $invoice->paid_amount;
                                        $isFullPaid = $invBalance <= 0;
                                        $isPartPaid = ! $isFullPaid && $invPaid > 0;
                                    @endphp
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="py-2.5 pr-3 font-semibold text-slate-700">{{ $invoice->business_date?->format('d M Y') }}</td>
                                        <td class="py-2.5 pr-3 font-mono font-bold text-slate-900">{{ $invoice->invoice_number }}</td>
                                        <td class="py-2.5 pr-3 text-right font-semibold text-slate-700">Rs. {{ number_format((float) $invoice->final_total, 2) }}</td>
                                        <td class="py-2.5 pr-3 text-right font-black text-rose-700">Rs. {{ number_format($invBalance, 2) }}</td>
                                        <td class="py-2.5 pr-3">
                                            @if ($isFullPaid)
                                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">Paid</span>
                                            @elseif ($isPartPaid)
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">Partial</span>
                                            @else
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">Unpaid</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 text-right">
                                            <a href="{{ route('shop-owner.finance.show', $invoice) }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($payableInvoices instanceof \Illuminate\Contracts\Pagination\Paginator && $payableInvoices->hasPages())
                        <div class="mt-2">{{ $payableInvoices->links() }}</div>
                    @endif
                @endif
            </div>

            <div class="border-t border-emerald-100 bg-slate-50/50 p-3 sm:p-4 xl:border-l xl:border-t-0">
                <h3 class="text-sm font-black text-slate-950">Record Bill Payment</h3>
                <p class="mt-0.5 text-xs text-slate-500">Submit a payment request for admin approval.</p>

                @if ($payableInvoices->isEmpty())
                    <div class="mt-3 rounded-xl border border-emerald-200 bg-white p-4 text-center">
                        <p class="text-xs font-black text-emerald-800">No payable bills pending.</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('shop-owner.accounting.payment-requests.store') }}" class="mt-3 space-y-2.5">
                        @csrf
                        <input type="hidden" name="payment_date" value="{{ today()->toDateString() }}">

                        <div>
                            <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Invoice</span>
                            <select name="invoice_id" class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                @foreach ($payableInvoices as $invoice)
                                    <option value="{{ $invoice->id }}">
                                        {{ $invoice->invoice_number }} (Due: Rs. {{ number_format((float) $invoice->balance_amount, 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Amount</span>
                            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Enter amount">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Method</span>
                                <select name="payment_method" class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank</option>
                                    <option value="online_upi">UPI</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>
                            <div>
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Ref / Txn ID</span>
                                <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Optional">
                            </div>
                        </div>
                        <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-emerald-700 px-4 text-xs font-black text-white transition hover:bg-emerald-600">
                            Submit Bill Payment
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    @if ($isOwnedAccountingShop)
        <section class="overflow-hidden rounded-2xl border border-cyan-200 bg-white shadow-xs">
            <div class="border-b border-cyan-100 bg-cyan-50/70 p-3 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <span class="inline-block rounded-full bg-cyan-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-cyan-800">Aishwarya Veg</span>
                        <h2 class="mt-1 text-base font-black text-slate-950 sm:text-lg">Shop Collections & Payable Balance</h2>
                        <p class="mt-0.5 text-xs font-semibold text-cyan-900">Configured income collections and cashbook payment received status.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:min-w-[420px]">
                        <div class="rounded-xl border border-cyan-200/80 bg-white p-2.5">
                            <p class="text-[9px] font-black uppercase tracking-wider text-cyan-700">Balance Date</p>
                            <p class="mt-0.5 text-xs font-black text-slate-950">{{ $latestBalanceDate ? $latestBalanceDate->format('d M Y') : 'N/A' }}</p>
                        </div>
                        <div class="rounded-xl border border-cyan-200/80 bg-white p-2.5">
                            <div class="flex items-center justify-between">
                                <p class="text-[9px] font-black uppercase tracking-wider text-cyan-700">Outstanding Balance</p>
                                <span class="px-2 py-0.5 text-[8px] font-black uppercase tracking-wider rounded-md {{ $payableBalance <= 0 ? 'bg-emerald-100 text-emerald-800' : ($payableReceivedTotal > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $payableBalance <= 0 ? 'Fully Settled' : ($payableReceivedTotal > 0 ? 'Partially Paid' : 'Pending') }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-sm font-black {{ $payableBalance > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($payableBalance > 0 ? $payableBalance : $shopBalancePayable, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_minmax(340px,0.5fr)]">
                <div class="p-3 sm:p-4 space-y-4">
                    <div>
                        <h3 class="text-sm font-black text-slate-950">Cashbook Collections & Payment Received Summary</h3>
                        <div class="mt-2.5 grid gap-2 sm:grid-cols-3">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                                <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Total Collected</p>
                                <p class="mt-0.5 text-sm font-black text-slate-950">Rs. {{ number_format($payableTotal > 0 ? $payableTotal : $latestClosingBalance, 2) }}</p>
                            </div>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-2.5">
                                <p class="text-[9px] font-black uppercase tracking-wider text-emerald-700">Received by Company</p>
                                <p class="mt-0.5 text-sm font-black text-emerald-700">Rs. {{ number_format($payableReceivedTotal, 2) }}</p>
                            </div>
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-2.5">
                                <p class="text-[9px] font-black uppercase tracking-wider text-rose-700">Remaining Balance</p>
                                <p class="mt-0.5 text-sm font-black text-rose-700">Rs. {{ number_format($payableBalance > 0 ? $payableBalance : $shopBalancePayable, 2) }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($payableCategories->isNotEmpty())
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-700 mb-2">Configured Categories Status Matrix</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                @foreach ($payableCategories as $cat)
                                    <div class="p-3 rounded-xl border {{ $cat['status'] === 'received' ? 'border-emerald-200 bg-emerald-50/40' : ($cat['status'] === 'partial' ? 'border-amber-200 bg-amber-50/40' : 'border-slate-200 bg-slate-50') }}">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-black text-slate-900">{{ $cat['name'] }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider {{ $cat['status'] === 'received' ? 'bg-emerald-100 text-emerald-800' : ($cat['status'] === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700') }}">
                                                {{ $cat['status'] }}
                                            </span>
                                        </div>
                                        <div class="mt-2 grid grid-cols-3 gap-1 text-center font-mono text-[10px] pt-1.5 border-t border-slate-200/60">
                                            <div class="bg-white/80 p-1 rounded-lg">
                                                <span class="text-[8px] font-sans font-bold uppercase text-slate-400 block">Total</span>
                                                <span class="font-bold text-slate-800">Rs. {{ number_format((float) $cat['recorded_amount'], 2) }}</span>
                                            </div>
                                            <div class="bg-white/80 p-1 rounded-lg">
                                                <span class="text-[8px] font-sans font-bold uppercase text-emerald-600 block">Recv</span>
                                                <span class="font-bold text-emerald-700">Rs. {{ number_format((float) $cat['received_amount'], 2) }}</span>
                                            </div>
                                            <div class="bg-white/80 p-1 rounded-lg">
                                                <span class="text-[8px] font-sans font-bold uppercase text-amber-600 block">Bal</span>
                                                <span class="font-bold {{ $cat['balance'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">Rs. {{ number_format((float) $cat['balance'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="border-t border-cyan-100 bg-slate-50/50 p-3 sm:p-4 xl:border-l xl:border-t-0">
                    <h3 class="text-sm font-black text-slate-950">Submit Payment to Cashbook</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Submit payment towards closing balance or specific category.</p>

                    @if (($payableBalance <= 0 && $shopBalancePayable <= 0))
                        <div class="mt-3 rounded-xl border border-emerald-200 bg-white p-4 text-center">
                            <p class="text-xs font-black text-emerald-800">All configured category collections are fully settled.</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('shop-owner.accounting.payment-requests.store') }}" class="mt-3 space-y-2.5">
                            @csrf
                            <input type="hidden" name="amount_mode" value="shop_balance">
                            <input type="hidden" name="payment_date" value="{{ today()->toDateString() }}">

                            <div>
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Amount Paid</span>
                                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', number_format($payableBalance > 0 ? $payableBalance : $shopBalancePayable, 2, '.', '')) }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-900 focus:border-cyan-500 focus:outline-none">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Mode</span>
                                    <select name="payment_method" class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs font-bold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                        <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Cash</option>
                                        <option value="online_upi" @selected(old('payment_method') === 'online_upi')>Online UPI</option>
                                        <option value="cheque" @selected(old('payment_method') === 'cheque')>Cheque</option>
                                    </select>
                                </div>
                                <div>
                                    <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Ref / Check No.</span>
                                    <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none" placeholder="Optional">
                                </div>
                            </div>
                            <div>
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Note / Category Reference</span>
                                <input type="text" name="shop_note" value="{{ old('shop_note') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none" placeholder="e.g. Paid Cash Sales closing balance">
                            </div>
                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-cyan-700 px-4 text-xs font-black text-white transition hover:bg-cyan-600">
                                Submit Payment to Cashbook
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- Payment Requests History Section --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs sm:p-4">
        <h2 class="text-base font-black text-slate-950 sm:text-lg">Payment History</h2>
        <p class="mt-0.5 text-xs font-semibold text-slate-500">Pending rows wait for admin/accounts approval.</p>

        @if ($invoicePaymentRequests->isEmpty())
            <div class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-center">
                <p class="text-xs font-black text-slate-700">No payment requests yet.</p>
            </div>
        @else
            {{-- Mobile View: Compact Single-Row Style Cards --}}
            <div class="mt-3 space-y-2 md:hidden">
                @foreach ($invoicePaymentRequests as $paymentRequest)
                    <article class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate text-xs font-black text-slate-900">
                                        @if($paymentRequest->invoice)
                                            <a href="{{ route('shop-owner.finance.show', $paymentRequest->invoice) }}" class="text-emerald-700 hover:underline">
                                                {{ $paymentRequest->invoice->invoice_number }}
                                            </a>
                                        @else
                                            {{ $paymentRequest->applicationLabel() }}
                                        @endif
                                    </span>
                                </div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-[10px] text-slate-500">
                                    <span>{{ $paymentRequest->created_at?->format('d M Y') }}</span>
                                    <span>·</span>
                                    <span>{{ $paymentRequest->paymentMethodLabel() }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-right">
                                <div>
                                    <span class="text-xs font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</span>
                                </div>
                                @include('shop-owner.components.status-badge', ['label' => $paymentRequest->statusLabel(), 'tone' => $paymentRequest->statusTone()])
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Desktop Table View --}}
            <div class="hidden overflow-x-auto rounded-xl border border-slate-200 mt-3 md:block">
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
                                <td class="px-3 py-2 font-bold text-slate-900">
                                    @if($paymentRequest->invoice)
                                        <a href="{{ route('shop-owner.finance.show', $paymentRequest->invoice) }}" class="text-emerald-700 hover:underline">
                                            {{ $paymentRequest->invoice->invoice_number }}
                                        </a>
                                    @else
                                        {{ $paymentRequest->applicationLabel() }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-semibold text-slate-500">{{ $paymentRequest->created_at?->format('d M Y h:i A') }}</td>
                                <td class="px-3 py-2">@include('shop-owner.components.status-badge', ['label' => $paymentRequest->statusLabel(), 'tone' => $paymentRequest->statusTone()])</td>
                                <td class="px-3 py-2 font-semibold text-slate-600">
                                    {{ $paymentRequest->paymentMethodLabel() }}{{ $paymentRequest->payment_reference ? ' | Ref: '.$paymentRequest->payment_reference : '' }}
                                </td>
                                <td class="px-3 py-2 text-right font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</td>
                                <td class="px-3 py-2 text-right font-black text-emerald-700">Rs. {{ number_format((float) $paymentRequest->applied_amount, 2) }}</td>
                                <td class="px-3 py-2 text-right font-black text-cyan-700">Rs. {{ number_format((float) $paymentRequest->remainingCreditAmount(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-2 border-t border-slate-100 pt-2">
                {{ $invoicePaymentRequests->links() }}
            </div>
        @endif
    </section>
</div>
