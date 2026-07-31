<x-layouts.accounting title="Clients Payment Report">
    @php
        $startDateValue = $startDate->toDateString();
        $endDateValue = $endDate->toDateString();
        $tabs = [
            'pending-bills' => 'Pending Bills',
            'approvals' => 'Payment Approvals',
            'history' => 'Payment History',
        ];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-5 border-b border-slate-200 bg-slate-950 px-4 py-5 text-white sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-7">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-300">Owned Shop Cash Flow</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-4xl">Clients Payment Report</h1>
                    <p class="mt-3 text-sm font-semibold text-slate-300">{{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.clients.report') }}" class="grid gap-3 rounded-xl border border-white/10 bg-white/10 p-3 sm:grid-cols-[minmax(0,11rem)_minmax(0,11rem)_auto]">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <label>
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-300">From</span>
                        <input type="date" name="start_date" value="{{ $startDateValue }}" class="mt-2 h-11 w-full rounded-lg border border-white/10 bg-white px-3 text-sm font-black text-slate-950">
                    </label>
                    <label>
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-300">To</span>
                        <input type="date" name="end_date" value="{{ $endDateValue }}" class="mt-2 h-11 w-full rounded-lg border border-white/10 bg-white px-3 text-sm font-black text-slate-950">
                    </label>
                    <button type="submit" class="mt-5 inline-flex h-11 items-center justify-center rounded-lg bg-white px-5 text-xs font-black uppercase tracking-[0.16em] text-slate-950 transition hover:bg-emerald-50">
                        Apply
                    </button>
                </form>
            </div>

            <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4 lg:p-6">
                <article class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Pending Extra</p>
                    <p class="mt-3 text-2xl font-black text-rose-900">Rs. {{ number_format($summary['pending_extra'], 2) }}</p>
                    <p class="mt-1 text-xs font-bold text-rose-700">{{ number_format($summary['pending_shop_count']) }} shop(s)</p>
                </article>
                <article class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Payment Approvals</p>
                    <p class="mt-3 text-2xl font-black text-amber-950">{{ number_format($summary['pending_approvals']) }}</p>
                    <p class="mt-1 text-xs font-bold text-amber-700">Waiting for admin/accounts</p>
                </article>
                <article class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">History Rows</p>
                    <p class="mt-3 text-2xl font-black text-emerald-950">{{ number_format($summary['history_count']) }}</p>
                    <p class="mt-1 text-xs font-bold text-emerald-700">Approved and rejected</p>
                </article>
                <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Formula</p>
                    <p class="mt-3 text-sm font-black text-slate-950">Cashbook - GL Bills - Approved Payments</p>
                    <p class="mt-1 text-xs font-bold text-slate-500">Day-wise, summed monthly</p>
                </article>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <div class="grid grid-cols-1 gap-2 sm:inline-grid sm:grid-flow-col">
                @foreach ($tabs as $tabKey => $tabLabel)
                    <a href="{{ route('admin.accounting.clients.report', ['tab' => $tabKey, 'start_date' => $startDateValue, 'end_date' => $endDateValue]) }}" @class([
                        'rounded-lg border px-4 py-3 text-center text-xs font-black uppercase tracking-[0.14em] transition',
                        'border-slate-950 bg-slate-950 text-white' => $activeTab === $tabKey,
                        'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' => $activeTab !== $tabKey,
                    ])>
                        {{ $tabLabel }}
                    </a>
                @endforeach
            </div>
        </section>

        @if ($activeTab === 'approvals')
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6">
                    <h2 class="text-xl font-black text-slate-950">Payment Approvals</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Payment approval, rejection and cheque clearance are handled from Finance V2 Payments.</p>
                </div>

                <div class="space-y-3 p-4 md:hidden">
                    @forelse ($paymentApprovals as $paymentRequest)
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-950">{{ $paymentRequest->shop?->name ?? 'Shop' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $paymentRequest->applicationLabel() }} | {{ $paymentRequest->paymentMethodLabel() }}</p>
                                    @if ($paymentRequest->payment_reference)
                                        <p class="mt-1 text-xs font-bold text-slate-600">Ref: {{ $paymentRequest->payment_reference }}</p>
                                    @endif
                                </div>
                                <p class="text-right text-sm font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</p>
                            </div>
                            <a href="{{ route('admin.finance-v2.payments.show', ['paymentRequest' => $paymentRequest, 'date' => $endDate->toDateString()]) }}" class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg bg-slate-950 px-3 text-xs font-black uppercase tracking-[0.12em] text-white">
                                Open in Finance V2
                            </a>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">No payment approvals pending.</p>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.14em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Shop</th>
                                <th class="px-4 py-3">Payment</th>
                                <th class="px-4 py-3">Mode</th>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($paymentApprovals as $paymentRequest)
                                <tr>
                                    <td class="px-4 py-4 font-black text-slate-950">{{ $paymentRequest->shop?->name ?? 'Shop' }}</td>
                                    <td class="px-4 py-4 font-semibold text-slate-600">{{ $paymentRequest->applicationLabel() }}</td>
                                    <td class="px-4 py-4 font-semibold text-slate-600">{{ $paymentRequest->paymentMethodLabel() }}</td>
                                    <td class="px-4 py-4 font-semibold text-slate-500">{{ $paymentRequest->payment_reference ?: '-' }}</td>
                                    <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex justify-end">
                                            <a href="{{ route('admin.finance-v2.payments.show', ['paymentRequest' => $paymentRequest, 'date' => $endDate->toDateString()]) }}" class="h-9 rounded-lg bg-slate-950 px-3 py-2 text-xs font-black uppercase tracking-[0.12em] text-white">Open</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No payment approvals pending.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($paymentApprovals->hasPages())
                    <div class="border-t border-slate-200 p-4">{{ $paymentApprovals->links() }}</div>
                @endif
            </section>
        @elseif ($activeTab === 'history')
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6">
                    <h2 class="text-xl font-black text-slate-950">Payment History</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Shop owners see approved state only; admin/accounts keeps full review history.</p>
                </div>

                <div class="space-y-3 p-4 md:hidden">
                    @forelse ($paymentHistory as $paymentRequest)
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-950">{{ $paymentRequest->shop?->name ?? 'Shop' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $paymentRequest->applicationLabel() }} | {{ $paymentRequest->paymentMethodLabel() }}</p>
                                    <p class="mt-1 text-xs font-bold text-slate-500">{{ $paymentRequest->reviewed_at?->format('d M Y h:i A') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</p>
                                    <p class="mt-1 text-xs font-black {{ $paymentRequest->status === 'approved' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $paymentRequest->statusLabel() }}</p>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">No reviewed payments yet.</p>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.14em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Shop</th>
                                <th class="px-4 py-3">Payment</th>
                                <th class="px-4 py-3">Mode</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Reviewed</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($paymentHistory as $paymentRequest)
                                <tr>
                                    <td class="px-4 py-4 font-black text-slate-950">{{ $paymentRequest->shop?->name ?? 'Shop' }}</td>
                                    <td class="px-4 py-4 font-semibold text-slate-600">{{ $paymentRequest->applicationLabel() }}</td>
                                    <td class="px-4 py-4 font-semibold text-slate-600">{{ $paymentRequest->paymentMethodLabel() }}</td>
                                    <td class="px-4 py-4 font-black {{ $paymentRequest->status === 'approved' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $paymentRequest->statusLabel() }}</td>
                                    <td class="px-4 py-4 font-semibold text-slate-500">{{ $paymentRequest->reviewed_at?->format('d M Y h:i A') }}</td>
                                    <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No reviewed payments yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($paymentHistory->hasPages())
                    <div class="border-t border-slate-200 p-4">{{ $paymentHistory->links() }}</div>
                @endif
            </section>
        @else
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6">
                    <h2 class="text-xl font-black text-slate-950">Pending Cashbook Bills</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Only owned shops are listed. Pending extra is the positive closing balance after GL invoice bills and approved payments.</p>
                </div>

                <div class="space-y-3 p-4 md:hidden">
                    @forelse ($pendingBills as $row)
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-950">{{ $row['shop']->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['date']->format('d M Y') }}</p>
                                </div>
                                <p class="text-right text-sm font-black text-rose-700">Rs. {{ number_format($row['pending_extra'], 2) }}</p>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2 text-xs font-bold">
                                <p class="rounded-lg bg-white p-3 text-slate-600">Cashbook<br><span class="text-slate-950">Rs. {{ number_format($row['cashbook_total'], 2) }}</span></p>
                                <p class="rounded-lg bg-white p-3 text-slate-600">GL Bills<br><span class="text-slate-950">Rs. {{ number_format($row['invoice_bill_amount'], 2) }}</span></p>
                                <p class="rounded-lg bg-white p-3 text-slate-600">Approved Paid<br><span class="text-emerald-700">Rs. {{ number_format($row['approved_payment'], 2) }}</span></p>
                                <p class="rounded-lg bg-white p-3 text-slate-600">Closing<br><span class="text-slate-950">Rs. {{ number_format($row['closing_balance'], 2) }}</span></p>
                            </div>
                            <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $row['shop'], 'tab' => 'cashbook', 'date' => $row['date']->toDateString()]) }}" class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.12em] text-white">Open Flow</a>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">No pending owned-shop cashbook bills for this period.</p>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.14em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Shop</th>
                                <th class="px-4 py-3 text-right">Cashbook</th>
                                <th class="px-4 py-3 text-right">GL Bills</th>
                                <th class="px-4 py-3 text-right">Approved Paid</th>
                                <th class="px-4 py-3 text-right">Closing</th>
                                <th class="px-4 py-3 text-right">Pending Extra</th>
                                <th class="px-4 py-3 text-right">Open</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($pendingBills as $row)
                                <tr>
                                    <td class="px-4 py-4 font-black text-slate-950">{{ $row['date']->format('d M Y') }}</td>
                                    <td class="px-4 py-4">
                                        <p class="font-black text-slate-950">{{ $row['shop']->name }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['shop']->code }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format($row['cashbook_total'], 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-slate-700">Rs. {{ number_format($row['invoice_bill_amount'], 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-emerald-700">Rs. {{ number_format($row['approved_payment'], 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format($row['closing_balance'], 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-rose-700">Rs. {{ number_format($row['pending_extra'], 2) }}</td>
                                    <td class="px-4 py-4 text-right">
                                        <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $row['shop'], 'tab' => 'cashbook', 'date' => $row['date']->toDateString()]) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-black uppercase tracking-[0.12em] text-slate-700 transition hover:bg-slate-100">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No pending owned-shop cashbook bills for this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($pendingBills->hasPages())
                    <div class="border-t border-slate-200 p-4">{{ $pendingBills->links() }}</div>
                @endif
            </section>
        @endif
    </div>
</x-layouts.accounting>
