<x-layouts.accounting title="Company Payable Review">
    @php
        $dateParam = $date->format('Y-m-d');
    @endphp
    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Company Payable</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $line->entry?->shop?->name ?? 'Shop' }}</h1>
                <p class="mt-2 text-sm font-semibold text-slate-300">{{ $line->category?->name ?? 'Expense' }} · Rs. {{ number_format((float) ($line->company_payable_amount ?? $line->amount), 2) }}</p>
                <p class="mt-2 text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Status: {{ str($line->company_payable_status)->replace('_', ' ')->title() }} · Settlement: {{ str($line->company_settlement_status ?? 'unsettled')->replace('_', ' ')->title() }}</p>
                @if($line->company_rejection_reason)
                    <p class="mt-3 rounded-[1rem] border border-rose-300/40 bg-rose-500/15 px-4 py-3 text-sm font-semibold text-rose-100">Rejection: {{ $line->company_rejection_reason }}</p>
                @endif
            </div>
        </section>

        @if($line->company_payable_status === 'pending')
            <section class="grid gap-4 lg:grid-cols-2">
                <form method="POST" action="{{ route('admin.finance-v2.company-payables.approve', $line) }}" class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                    @csrf
                    @method('PATCH')
                    <h3 class="text-lg font-black text-slate-950">Approve</h3>
                    <label class="mt-4 block">
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Approved amount</span>
                        <input type="number" step="0.01" min="0.01" name="approved_amount" value="{{ old('approved_amount', $line->company_payable_amount ?? $line->amount) }}" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                    </label>
                    <button class="mt-4 inline-flex h-11 items-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white">Approve payable</button>
                </form>
                <form method="POST" action="{{ route('admin.finance-v2.company-payables.reject', $line) }}" class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                    @csrf
                    @method('PATCH')
                    <h3 class="text-lg font-black text-slate-950">Reject</h3>
                    <label class="mt-4 block">
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Rejection reason</span>
                        <textarea name="rejection_reason" required class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900">{{ old('rejection_reason') }}</textarea>
                    </label>
                    <button class="mt-4 inline-flex h-11 items-center rounded-2xl bg-rose-600 px-5 text-sm font-black text-white">Reject payable</button>
                </form>
            </section>
        @endif

        @if($line->company_payable_status === 'approved' && $line->remainingCompanyPayableAmount() > 0)
            <section class="grid gap-4 lg:grid-cols-2">
                <form method="POST" action="{{ route('admin.finance-v2.company-payables.settle-adjust', $line) }}" class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                    @csrf
                    <h3 class="text-lg font-black text-slate-950">Adjust against shop payment</h3>
                    <label class="mt-4 block">
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Amount</span>
                        <input type="number" step="0.01" min="0.01" max="{{ $line->remainingCompanyPayableAmount() }}" name="amount" value="{{ old('amount', $line->remainingCompanyPayableAmount()) }}" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                    </label>
                    <label class="mt-4 block">
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Approved shop payment</span>
                        <select name="shop_invoice_payment_request_id" required class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                            <option value="">Select payment</option>
                            @foreach($shopPayments as $payment)
                                <option value="{{ $payment->id }}">#{{ $payment->id }} · Rs. {{ number_format((float) $payment->approved_amount, 2) }} · {{ $payment->payment_date?->toDateString() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="mt-4 inline-flex h-11 items-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white">Record adjustment</button>
                </form>
                <form method="POST" action="{{ route('admin.finance-v2.company-payables.settle-direct', $line) }}" class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                    @csrf
                    <h3 class="text-lg font-black text-slate-950">Direct company payment</h3>
                    <label class="mt-4 block">
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Amount</span>
                        <input type="number" step="0.01" min="0.01" max="{{ $line->remainingCompanyPayableAmount() }}" name="amount" value="{{ old('amount', $line->remainingCompanyPayableAmount()) }}" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                    </label>
                    <label class="mt-4 block">
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Payment mode</span>
                        <select name="payment_mode" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="upi">UPI</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </label>
                    <label class="mt-4 block">
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Payee</span>
                        <input type="text" name="payee" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                    </label>
                    <label class="mt-4 block">
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Reference</span>
                        <input type="text" name="reference" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                    </label>
                    <button class="mt-4 inline-flex h-11 items-center rounded-2xl bg-orange-500 px-5 text-sm font-black text-white">Pay directly</button>
                </form>
            </section>
        @endif

        <section class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-black text-slate-950">Settlement history</h3>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse($line->settlements as $settlement)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p class="text-sm font-black text-slate-950">{{ str($settlement->settlement_type)->replace('_', ' ')->title() }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $settlement->settlement_date?->toDateString() }} · {{ $settlement->creator?->name }}</p>
                        </div>
                        <p class="text-sm font-black text-slate-950">Rs. {{ number_format((float) $settlement->amount, 2) }}</p>
                    </div>
                @empty
                    <p class="py-6 text-sm font-bold text-slate-500">No settlements yet.</p>
                @endforelse
            </div>
            <a href="{{ route('admin.finance-v2.company-payables.index', ['date' => $dateParam]) }}" class="mt-4 inline-flex text-xs font-black uppercase tracking-[0.14em] text-slate-600 hover:underline">Back to queue</a>
        </section>
    </div>
</x-layouts.accounting>
