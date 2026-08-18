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
    @unless ($hideDeliveryBills ?? false)
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

                        {{-- Mobile Card List for Pending Bills --}}
                        <div class="mt-3 space-y-2.5 block sm:hidden">
                            @foreach ($payableInvoices as $invoice)
                                @php
                                    $invBalance = (float) $invoice->balance_amount;
                                    $invPaid = (float) $invoice->paid_amount;
                                    $isFullPaid = $invBalance <= 0;
                                    $isPartPaid = ! $isFullPaid && $invPaid > 0;
                                @endphp
                                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-2xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-mono text-xs font-black text-slate-900">{{ $invoice->invoice_number }}</span>
                                        @if ($isFullPaid)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">Paid</span>
                                        @elseif ($isPartPaid)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">Partial</span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">Unpaid</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-xs pt-2 border-t border-slate-100">
                                        <div class="text-slate-500 font-semibold">{{ $invoice->business_date?->format('d M Y') }}</div>
                                        <div class="text-right">
                                            <span class="text-[10px] uppercase text-slate-400 font-bold block">Balance Due</span>
                                            <span class="font-black text-rose-700">Rs. {{ number_format($invBalance, 2) }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-2.5 pt-2 border-t border-slate-100 flex justify-end">
                                        <a href="{{ route('shop-owner.finance.show', $invoice) }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-100">
                                            View Invoice Details
                                        </a>
                                    </div>
                                </div>
                            @endforeach
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
    @endunless

    @if ($isOwnedAccountingShop)
        <script id="daily-payables-data" type="application/json">
            {!! json_encode(($dailyPayableBalances instanceof \Illuminate\Contracts\Pagination\Paginator ? $dailyPayableBalances->items() : $dailyPayableBalances) ?? []) !!}
        </script>
        <section class="overflow-hidden rounded-2xl border border-cyan-200 bg-white shadow-xs"
                 x-data="{
                    selectedDates: [],
                    datesData: [],
                    paymentAmount: '{{ number_format((float) ($payableBalance > 0 ? $payableBalance : ($shopBalancePayable ?? 0)), 2, '.', '') }}',
                    paymentMethod: '{{ old('payment_method', 'cash') }}',
                    chequeNumber: '{{ old('cheque_number', '') }}',
                    chequeBank: '{{ old('cheque_bank', '') }}',
                    chequeDate: '{{ old('cheque_date', today()->toDateString()) }}',
                    upiRef: '{{ old('upi_ref', '') }}',
                    init() {
                        try {
                            const raw = document.getElementById('daily-payables-data')?.textContent;
                            this.datesData = raw ? JSON.parse(raw) : [];
                        } catch (e) {
                            this.datesData = [];
                        }
                        this.recalculateTotal();
                    },
                    recalculateTotal() {
                        if (!Array.isArray(this.datesData) || this.datesData.length === 0) {
                            return;
                        }
                        if (this.selectedDates.length === 0) {
                            this.paymentAmount = '{{ number_format((float) ($payableBalance > 0 ? $payableBalance : ($shopBalancePayable ?? 0)), 2, '.', '') }}';
                            return;
                        }
                        const selectedSet = new Set(this.selectedDates.map(String));
                        let sum = 0;
                        for (const item of this.datesData) {
                            if (selectedSet.has(String(item.date))) {
                                const val = parseFloat(item.net_balance);
                                if (!isNaN(val)) {
                                    sum += val;
                                }
                            }
                        }
                        this.paymentAmount = sum.toFixed(2);
                    },
                    toggleAll(checked) {
                        if (checked && Array.isArray(this.datesData)) {
                            this.selectedDates = this.datesData.map(d => d.date);
                        } else {
                            this.selectedDates = [];
                        }
                        this.recalculateTotal();
                    },
                    get selectedTotalFormatted() {
                        const amt = parseFloat(this.paymentAmount || 0);
                        return 'Rs. ' + amt.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    get computedPaymentReference() {
                        if (this.paymentMethod === 'cheque') {
                            let ref = this.chequeNumber ? ('CHQ#' + this.chequeNumber) : '';
                            if (this.chequeBank) ref += (ref ? ' | ' : '') + 'Bank: ' + this.chequeBank;
                            if (this.chequeDate) ref += (ref ? ' | ' : '') + 'Date: ' + this.chequeDate;
                            return ref;
                        } else if (this.paymentMethod === 'online_upi') {
                            return this.upiRef ? ('UPI UTR: ' + this.upiRef) : '';
                        }
                        return '';
                    },
                    get datesNote() {
                        if (this.selectedDates.length === 0) return '';
                        return 'Payment for dates: ' + this.selectedDates.slice().sort().join(', ');
                    }
                 }">
            <!-- Header Bar -->
            <div class="flex items-center justify-between border-b border-cyan-100 bg-cyan-50/70 p-3.5 sm:p-4">
                <div>
                    <span class="inline-block rounded-full bg-cyan-100 px-2 py-0.5 text-[9px] font-black uppercase text-cyan-800">Aishwarya Veg</span>
                    <h2 class="mt-0.5 text-sm sm:text-base font-black text-slate-950">Daily Payable Balances</h2>
                </div>
                <div class="text-right">
                    <span class="text-[9px] font-black uppercase tracking-wider text-cyan-800 block">Total Balance</span>
                    <span class="text-sm sm:text-base font-black {{ $payableBalance > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($payableBalance > 0 ? $payableBalance : $shopBalancePayable, 2) }}</span>
                </div>
            </div>

            <!-- Top Live Selection Total Banner -->
            <div x-show="selectedDates.length > 0" x-cloak class="border-b border-cyan-200 bg-cyan-700 px-3.5 py-2.5 text-white shadow-xs">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-white/20 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-white">
                            <span x-text="selectedDates.length"></span> Date(s) Selected
                        </span>
                        <span class="text-xs font-bold text-cyan-100 hidden sm:inline">Amount input updated automatically</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <span class="text-[9px] uppercase font-bold text-cyan-200 block">Total Selected</span>
                            <span class="text-sm sm:text-base font-black text-white font-mono" x-text="selectedTotalFormatted"></span>
                        </div>
                        <button type="button" @click="selectedDates = []; recalculateTotal();" class="rounded-lg bg-white/10 px-2 py-1 text-[10px] font-bold hover:bg-white/20 text-white transition">
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-3 sm:p-4 space-y-4">
                @if (empty($dailyPayableBalances) || $dailyPayableBalances->isEmpty())
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
                        <p class="text-xs font-bold text-slate-500">No daily payable records found for the selected period.</p>
                    </div>
                @else
                    <!-- Desktop Table View -->
                    <div class="hidden sm:block overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-100 text-left text-xs whitespace-nowrap">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-3 py-2.5 w-10 text-center">
                                        <input type="checkbox" @change="toggleAll($event.target.checked)" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                                    </th>
                                    <th class="px-3.5 py-2.5">Business Date</th>
                                    <th class="px-3.5 py-2.5 text-right">Collected (Out)</th>
                                    <th class="px-3.5 py-2.5 text-right">Received (In)</th>
                                    <th class="px-3.5 py-2.5 text-right">Net Balance</th>
                                    <th class="px-3.5 py-2.5 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700 font-mono">
                                @foreach ($dailyPayableBalances as $day)
                                    <tr class="hover:bg-cyan-50/30 transition-colors" :class="selectedDates.includes('{{ $day['date'] }}') ? 'bg-cyan-50/50' : ''">
                                        <td class="px-3 py-2.5 text-center">
                                            <input type="checkbox" value="{{ $day['date'] }}" x-model="selectedDates" @change="recalculateTotal()" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                                        </td>
                                        <td class="px-3.5 py-2.5 font-sans font-bold text-slate-900">{{ $day['date_label'] }}</td>
                                        <td class="px-3.5 py-2.5 text-right font-semibold text-slate-900">Rs. {{ number_format($day['out_amount'], 2) }}</td>
                                        <td class="px-3.5 py-2.5 text-right font-semibold text-emerald-700">Rs. {{ number_format($day['in_amount'], 2) }}</td>
                                        <td class="px-3.5 py-2.5 text-right font-black {{ $day['net_balance'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($day['net_balance'], 2) }}</td>
                                        <td class="px-3.5 py-2.5 text-center font-sans">
                                            @if ($day['status'] === 'fully_settled')
                                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">Settled</span>
                                            @elseif ($day['status'] === 'partially_settled')
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">Partial</span>
                                            @elseif ($day['status'] === 'pending_approval')
                                                <span class="rounded-full bg-purple-100 px-2 py-0.5 text-[9px] font-black uppercase text-purple-800">Pending</span>
                                            @else
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">Unpaid</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Clean Mobile Table / List View -->
                    <div class="block sm:hidden border border-slate-200 rounded-xl divide-y divide-slate-100 bg-white overflow-hidden shadow-2xs">
                        <div class="flex items-center justify-between p-2.5 bg-slate-50 text-xs font-bold text-slate-700 border-b border-slate-200">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" @change="toggleAll($event.target.checked)" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                                <span>Select All</span>
                            </label>
                            <span class="text-[11px] text-slate-500 font-normal">Tap date to select</span>
                        </div>
                        @foreach ($dailyPayableBalances as $day)
                            <label class="flex items-center justify-between p-3 transition-colors cursor-pointer"
                                   :class="selectedDates.includes('{{ $day['date'] }}') ? 'bg-cyan-50/70' : 'bg-white'">
                                <div class="flex items-center gap-2.5 min-w-0 pr-2">
                                    <input type="checkbox" value="{{ $day['date'] }}" x-model="selectedDates" @change="recalculateTotal()" class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500 shrink-0">
                                    <div class="min-w-0">
                                        <span class="font-sans text-xs font-bold text-slate-900 block truncate">{{ $day['date_label'] }}</span>
                                        <span class="text-[10px] text-slate-500 font-mono block mt-0.5">Coll: {{ number_format($day['out_amount']) }} · Recv: {{ number_format($day['in_amount']) }}</span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0 font-mono">
                                    <span class="text-xs font-black {{ $day['net_balance'] > 0 ? 'text-rose-700' : 'text-emerald-700' }} block">Rs. {{ number_format($day['net_balance'], 2) }}</span>
                                    @if ($day['status'] === 'fully_settled')
                                        <span class="text-[8px] font-black uppercase text-emerald-700">Settled</span>
                                    @elseif ($day['status'] === 'partially_settled')
                                        <span class="text-[8px] font-black uppercase text-amber-700">Partial</span>
                                    @elseif ($day['status'] === 'pending_approval')
                                        <span class="text-[8px] font-black uppercase text-purple-700">Pending</span>
                                    @else
                                        <span class="text-[8px] font-black uppercase text-slate-500">Unpaid</span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>

                    @if ($dailyPayableBalances instanceof \Illuminate\Contracts\Pagination\Paginator && $dailyPayableBalances->hasPages())
                        <div class="mt-2">
                            {{ $dailyPayableBalances->appends(request()->query())->links() }}
                        </div>
                    @endif
                @endif

                <!-- Payment Form below table -->
                @if ($payableBalance > 0 || $shopBalancePayable > 0)
                    <div class="mt-3 rounded-xl border border-cyan-200 bg-slate-50/70 p-3 sm:p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-xs sm:text-sm font-black text-slate-950">Submit Payment to Cashbook</h3>
                            <span x-show="selectedDates.length > 0" class="rounded-full bg-cyan-100 px-2 py-0.5 text-[10px] font-black text-cyan-900">
                                <span x-text="selectedDates.length"></span> date(s): Rs. <strong x-text="paymentAmount"></strong>
                            </span>
                        </div>
                        <form method="POST" action="{{ route('shop-owner.accounting.payment-requests.store') }}" class="space-y-2.5">
                            @csrf
                            <input type="hidden" name="amount_mode" value="shop_balance">
                            <input type="hidden" name="payment_date" value="{{ today()->toDateString() }}">
                            <input type="hidden" name="payment_reference" :value="computedPaymentReference">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-600">Amount Paid (Rs.)</span>
                                    <input type="text" inputmode="decimal" name="amount" x-model="paymentAmount" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-mono font-black text-slate-900 focus:border-cyan-500 focus:outline-none">
                                </div>
                                <div>
                                    <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-600">Payment Mode</span>
                                    <select name="payment_method" x-model="paymentMethod" class="w-full rounded-xl border border-slate-300 bg-white px-2.5 py-2 text-xs font-bold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                        <option value="cash">Cash</option>
                                        <option value="online_upi">Online UPI</option>
                                        <option value="cheque">Cheque</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Dynamic Cheque Details -->
                            <div x-show="paymentMethod === 'cheque'" x-cloak class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 space-y-2 text-xs transition-all">
                                <div class="flex items-center justify-between pb-1 border-b border-amber-200/80">
                                    <span class="font-black text-amber-950 uppercase text-[10px] tracking-wider">Cheque Details</span>
                                    <span class="text-[10px] text-amber-800 font-semibold">Enter cheque information for verification</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-900 uppercase mb-0.5">Cheque Number *</label>
                                        <input type="text" x-model="chequeNumber" placeholder="e.g. 000124" class="w-full rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 text-xs font-mono font-bold text-slate-900 focus:ring-amber-500 focus:border-amber-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-900 uppercase mb-0.5">Bank Name & Branch</label>
                                        <input type="text" x-model="chequeBank" placeholder="e.g. HDFC Bank, Kochi" class="w-full rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-900 focus:ring-amber-500 focus:border-amber-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-900 uppercase mb-0.5">Cheque / Deposit Date</label>
                                        <input type="date" x-model="chequeDate" class="w-full rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-900 focus:ring-amber-500 focus:border-amber-500">
                                    </div>
                                </div>
                            </div>

                            <!-- Dynamic UPI Details -->
                            <div x-show="paymentMethod === 'online_upi'" x-cloak class="rounded-xl border border-blue-200 bg-blue-50/70 p-3 text-xs transition-all">
                                <div class="flex items-center justify-between pb-1 border-b border-blue-200/80 mb-2">
                                    <span class="font-black text-blue-950 uppercase text-[10px] tracking-wider">UPI Transaction Details</span>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-blue-900 uppercase mb-0.5">UTR / Transaction Ref No.</label>
                                    <input type="text" x-model="upiRef" placeholder="e.g. 423491029384" class="w-full rounded-lg border border-blue-300 bg-white px-2.5 py-1.5 text-xs font-mono font-bold text-slate-900 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <div>
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-600">Note / Dates Reference</span>
                                <input type="text" name="shop_note" :value="datesNote" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none" placeholder="e.g. Payment for selected daily balances">
                            </div>
                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-cyan-700 px-4 text-xs font-black text-white transition hover:bg-cyan-600 shadow-xs">
                                Submit Payment to Cashbook
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Dedicated Company Payable Section --}}
    {{-- Status of Last Payments Section with Details Popup --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs sm:p-4" x-data="{ activePaymentModal: null }">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-sm sm:text-base font-black text-slate-950">Status of Last Payments</h2>
                <p class="text-[11px] font-semibold text-slate-500">Tap or click any payment row to view full details.</p>
            </div>
        </div>

        @if (empty($invoicePaymentRequests) || $invoicePaymentRequests->isEmpty())
            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-6 text-center">
                <p class="text-xs font-bold text-slate-500">No recent submitted payment requests found.</p>
            </div>
        @else
            {{-- Mobile List View (Clean 1-line layout, tap to view details popup) --}}
            <div class="mt-3 block md:hidden border border-slate-200 rounded-xl divide-y divide-slate-100 bg-white overflow-hidden shadow-2xs">
                @foreach ($invoicePaymentRequests as $paymentRequest)
                    @php
                        $paymentPayload = [
                            'id' => $paymentRequest->id,
                            'title' => $paymentRequest->invoice ? ('Bill #' . $paymentRequest->invoice->invoice_number) : $paymentRequest->applicationLabel(),
                            'date' => $paymentRequest->created_at?->format('d M Y, h:i A') ?? 'N/A',
                            'amount' => number_format((float) $paymentRequest->requested_amount, 2),
                            'method' => $paymentRequest->paymentMethodLabel(),
                            'reference' => $paymentRequest->payment_reference ?: 'N/A',
                            'note' => $paymentRequest->shop_note ?: 'No note',
                            'status_label' => $paymentRequest->statusLabel(),
                            'status_tone' => $paymentRequest->statusTone(),
                            'admin_note' => $paymentRequest->admin_note ?: ($paymentRequest->rejection_reason ?: null),
                        ];
                    @endphp
                    <div @click="activePaymentModal = @js($paymentPayload)"
                         class="flex items-center justify-between p-3 cursor-pointer hover:bg-slate-50 transition-colors">
                        <div class="min-w-0 pr-2">
                            <span class="font-sans text-xs font-bold text-slate-900 block truncate">{{ $paymentPayload['title'] }}</span>
                            <span class="text-[10px] text-slate-500 font-mono block mt-0.5">{{ $paymentPayload['date'] }} · {{ $paymentPayload['method'] }}</span>
                        </div>
                        <div class="text-right shrink-0 font-mono">
                            <span class="text-xs font-black text-slate-950 block">Rs. {{ $paymentPayload['amount'] }}</span>
                            <span class="mt-0.5 inline-block">
                                @include('shop-owner.components.status-badge', ['label' => $paymentRequest->statusLabel(), 'tone' => $paymentRequest->statusTone()])
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop Table View (Click to open details popup) --}}
            <div class="mt-3 hidden md:block overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs whitespace-nowrap">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-3.5 py-2.5">Date & Time</th>
                            <th class="px-3.5 py-2.5">Payment For</th>
                            <th class="px-3.5 py-2.5">Mode / Ref</th>
                            <th class="px-3.5 py-2.5 text-center">Status</th>
                            <th class="px-3.5 py-2.5 text-right">Amount</th>
                            <th class="px-3.5 py-2.5 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 font-mono">
                        @foreach ($invoicePaymentRequests as $paymentRequest)
                            @php
                                $paymentPayload = [
                                    'id' => $paymentRequest->id,
                                    'title' => $paymentRequest->invoice ? ('Bill #' . $paymentRequest->invoice->invoice_number) : $paymentRequest->applicationLabel(),
                                    'date' => $paymentRequest->created_at?->format('d M Y, h:i A') ?? 'N/A',
                                    'amount' => number_format((float) $paymentRequest->requested_amount, 2),
                                    'method' => $paymentRequest->paymentMethodLabel(),
                                    'reference' => $paymentRequest->payment_reference ?: 'N/A',
                                    'note' => $paymentRequest->shop_note ?: 'No note',
                                    'status_label' => $paymentRequest->statusLabel(),
                                    'status_tone' => $paymentRequest->statusTone(),
                                    'admin_note' => $paymentRequest->admin_note ?: ($paymentRequest->rejection_reason ?: null),
                                ];
                            @endphp
                            <tr @click="activePaymentModal = @js($paymentPayload)" class="hover:bg-slate-50 transition-colors cursor-pointer">
                                <td class="px-3.5 py-2.5 font-sans font-semibold text-slate-500">{{ $paymentPayload['date'] }}</td>
                                <td class="px-3.5 py-2.5 font-sans font-bold text-slate-900">{{ $paymentPayload['title'] }}</td>
                                <td class="px-3.5 py-2.5 font-sans font-medium text-slate-600">
                                    {{ $paymentPayload['method'] }} {{ $paymentRequest->payment_reference ? '('.$paymentRequest->payment_reference.')' : '' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center font-sans">
                                    @include('shop-owner.components.status-badge', ['label' => $paymentRequest->statusLabel(), 'tone' => $paymentRequest->statusTone()])
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-black text-slate-950">Rs. {{ $paymentPayload['amount'] }}</td>
                                <td class="px-3.5 py-2.5 text-center font-sans">
                                    <button type="button" @click.stop="activePaymentModal = @js($paymentPayload)" class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-200">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($invoicePaymentRequests instanceof \Illuminate\Contracts\Pagination\Paginator && $invoicePaymentRequests->hasPages())
                <div class="mt-3">
                    {{ $invoicePaymentRequests->appends(request()->query())->links() }}
                </div>
            @endif
        @endif

        {{-- Details Popup Modal --}}
        <div x-show="activePaymentModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
            <div @click.away="activePaymentModal = null" class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl transition-all space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Payment Details</span>
                        <h3 class="text-base font-black text-slate-900" x-text="activePaymentModal?.title"></h3>
                    </div>
                    <button @click="activePaymentModal = null" class="rounded-full bg-slate-100 p-1.5 text-slate-500 hover:bg-slate-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-2.5 text-xs">
                    <div class="flex justify-between py-1.5 border-b border-slate-100">
                        <span class="text-slate-500 font-semibold">Submitted Date:</span>
                        <span class="font-bold text-slate-900" x-text="activePaymentModal?.date"></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-100">
                        <span class="text-slate-500 font-semibold">Amount Paid:</span>
                        <span class="font-black text-slate-950 font-mono text-sm" x-text="'Rs. ' + activePaymentModal?.amount"></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-100">
                        <span class="text-slate-500 font-semibold">Payment Mode:</span>
                        <span class="font-bold text-slate-800" x-text="activePaymentModal?.method"></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-100">
                        <span class="text-slate-500 font-semibold">Reference / Check No:</span>
                        <span class="font-mono font-bold text-slate-800" x-text="activePaymentModal?.reference"></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100">
                        <span class="text-slate-500 font-semibold block mb-1">Shop Incharge Note:</span>
                        <div class="font-medium text-slate-800 bg-slate-50 p-2.5 rounded-xl border border-slate-200/60" x-text="activePaymentModal?.note"></div>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-100">
                        <span class="text-slate-500 font-semibold">Current Status:</span>
                        <span class="font-black uppercase text-xs" x-text="activePaymentModal?.status_label"></span>
                    </div>
                    <template x-if="activePaymentModal?.admin_note">
                        <div class="py-1.5">
                            <span class="text-slate-500 font-semibold block mb-1">Admin Response / Note:</span>
                            <div class="font-medium text-slate-800 bg-amber-50 p-2.5 rounded-xl border border-amber-200/80" x-text="activePaymentModal?.admin_note"></div>
                        </div>
                    </template>
                </div>

                <div class="pt-2">
                    <button @click="activePaymentModal = null" class="w-full rounded-xl bg-slate-900 py-2.5 text-xs font-black text-white hover:bg-slate-800 transition">
                        Close Popup
                    </button>
                </div>
            </div>
        </div>
    </section>
</div>
