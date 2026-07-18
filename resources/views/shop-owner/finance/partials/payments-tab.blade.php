@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\ShopCredit> $companyPayments */
@endphp

<div class="grid gap-5 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Payment To Company</p>
        <h2 class="mt-2 text-lg font-black text-slate-950 sm:text-xl">Pay from shop closing balance</h2>
        <p class="mt-2 text-sm font-semibold leading-6 text-slate-600">Approved delivery bills are already included as cash debits in the cashbook. Submit company payments for admin approval before the closing balance changes.</p>

        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance Date</p>
                <p class="mt-2 text-sm font-black text-slate-950">{{ $latestBalanceDate->format('d M Y') }}</p>
            </div>
            <div class="rounded-[1.25rem] border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Payable</p>
                <p class="mt-2 text-lg font-black text-emerald-800">Rs. {{ number_format((float) $payableToCompany, 2) }}</p>
            </div>
            <div class="rounded-[1.25rem] border border-slate-200 bg-white p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Latest Closing</p>
                <p class="mt-2 text-lg font-black {{ (float) $latestClosingBalance >= 0 ? 'text-slate-950' : 'text-rose-700' }}">Rs. {{ number_format((float) $latestClosingBalance, 2) }}</p>
            </div>
            <div class="rounded-[1.25rem] border border-slate-200 bg-white p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Paid To Company</p>
                <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format((float) $companyPaymentTotal, 2) }}</p>
            </div>
        </div>

        @if (($pendingBillApprovalSummary['count'] ?? 0) > 0)
            <div class="mt-4 rounded-[1.25rem] border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-black text-amber-900">{{ $pendingBillApprovalSummary['count'] }} bills pending approval</p>
                <p class="mt-1 text-sm font-semibold text-amber-800">Rs. {{ number_format((float) $pendingBillApprovalSummary['amount'], 2) }} is not included in cash debit until those bills are approved.</p>
            </div>
        @endif

        @if ((float) $payableToCompany <= 0)
            <div class="mt-5 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-black text-slate-800">No positive closing balance to pay now.</p>
            </div>
        @else
            <form method="POST" action="{{ route('shop-owner.finance.payments.store') }}" class="mt-5 space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Amount Paid</span>
                        <input type="number" step="0.01" min="0.01" max="{{ number_format((float) $payableToCompany, 2, '.', '') }}" name="amount" value="{{ old('amount', number_format((float) $payableToCompany, 2, '.', '')) }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        @error('amount')
                            <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Payment Date</span>
                        <input type="date" name="business_date" value="{{ old('business_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        @error('business_date')
                            <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Note</span>
                    <textarea name="description" rows="3" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Cash handed over, bank deposit note, or reference...">{{ old('description') }}</textarea>
                    @error('description')
                        <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500 sm:w-auto">
                    Record Payment To Company
                </button>
            </form>
        @endif
    </section>

    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-950 sm:text-xl">Company Payment History</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Only approved rows reduce the shop closing balance and show as company cash received.</p>
            </div>
        </div>

        @if ($companyPayments->isEmpty())
            <div class="mt-5 rounded-[1.25rem] border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                <p class="text-sm font-black text-slate-700">No company payments recorded yet.</p>
            </div>
        @else
            <div class="mt-5 space-y-3 md:hidden">
                @foreach ($companyPayments as $payment)
                    <article class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-950">{{ $payment->business_date?->format('d M Y') }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $payment->description ?: 'Cash paid to company' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="whitespace-nowrap text-sm font-black text-rose-700">- Rs. {{ number_format((float) $payment->amount, 2) }}</p>
                                @include('shop-owner.components.status-badge', ['label' => $payment->statusLabel(), 'tone' => $payment->statusTone()])
                            </div>
                        </div>
                        @if ($payment->admin_note)
                            <p class="mt-3 text-sm font-semibold text-slate-600">Admin note: {{ $payment->admin_note }}</p>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="mt-5 hidden overflow-x-auto md:block">
                <table class="min-w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <th class="py-3 pr-4">Date</th>
                            <th class="py-3 pr-4">Details</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Recorded By</th>
                            <th class="py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                        @foreach ($companyPayments as $payment)
                            <tr>
                                <td class="py-4 pr-4 font-bold text-slate-900">{{ $payment->business_date?->format('d M Y') }}</td>
                                <td class="py-4 pr-4 font-semibold text-slate-600">{{ $payment->description ?: 'Cash paid to company' }}</td>
                                <td class="py-4 pr-4">@include('shop-owner.components.status-badge', ['label' => $payment->statusLabel(), 'tone' => $payment->statusTone()])</td>
                                <td class="py-4 pr-4 font-semibold text-slate-500">{{ $payment->creator?->name ?? 'Shop owner' }}</td>
                                <td class="py-4 text-right font-black text-rose-700">- Rs. {{ number_format((float) $payment->amount, 2) }}</td>
                            </tr>
                            @if ($payment->admin_note)
                                <tr class="bg-slate-50/70">
                                    <td></td>
                                    <td colspan="4" class="px-4 py-2 text-sm font-semibold text-slate-600">Admin note: {{ $payment->admin_note }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $companyPayments->links() }}
            </div>
        @endif
    </section>
</div>
