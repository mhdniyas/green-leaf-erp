<x-layouts.accounting title="Payment Approval">
    @php
        $verifiedDefault = old('admin_verified_amount', $paymentRequest->admin_verified_amount ?? $paymentRequest->requested_amount);
        $excess = max(0, (float) $verifiedDefault - (float) $invoicePreview['applied_amount']);
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Payment Approval</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $paymentRequest->shop?->name ?? 'Shop Payment' }}</h1>
                        <p class="mt-2 text-sm font-semibold text-slate-300">Review pending invoices and related payable items before approval.</p>
                    </div>
                    <a href="{{ route('admin.finance-v2.payments.index') }}" class="inline-flex h-11 items-center rounded-[1rem] border border-white/20 bg-white/10 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-white/15">Back to Payments</a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[0.75fr_1.25fr]">
            <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Payment Details</p>
                <div class="mt-4 grid gap-3">
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Submitted Amount</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Method</p>
                            <p class="mt-2 text-sm font-black text-slate-950">{{ $paymentRequest->paymentMethodLabel() }}</p>
                        </div>
                        <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Status</p>
                            <p class="mt-2 text-sm font-black text-slate-950">{{ $paymentRequest->statusLabel() }}</p>
                        </div>
                    </div>
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Reference</p>
                        <p class="mt-2 text-sm font-black text-slate-950">{{ $paymentRequest->payment_reference ?: '-' }}</p>
                    </div>
                    @if($paymentRequest->payment_method === 'cheque')
                        <div class="rounded-[1rem] border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-amber-600">Cheque Status</p>
                            <p class="mt-2 text-sm font-black text-amber-900">{{ $paymentRequest->chequeStatusLabel() }}</p>
                            <p class="mt-1 text-xs font-semibold text-amber-800">{{ $paymentRequest->cheque_bank_name ?: 'Bank not set' }} | {{ $paymentRequest->cheque_date?->toDateString() ?: 'No cheque date' }}</p>
                        </div>

                        @if($paymentRequest->status === 'pending')
                            <form method="POST" action="{{ route('admin.finance-v2.payments.cheque', $paymentRequest) }}" class="rounded-[1rem] border border-slate-200 bg-white p-4">
                                @csrf
                                @method('PATCH')
                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Update Cheque</span>
                                    <select name="cheque_status" class="mt-2 h-11 w-full rounded-[0.9rem] border border-slate-200 px-3 text-sm font-bold">
                                        @foreach(['pending' => 'Pending', 'deposited' => 'Deposited', 'cleared' => 'Cleared', 'bounced' => 'Bounced'] as $value => $label)
                                            <option value="{{ $value }}" @selected($paymentRequest->cheque_status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <button class="mt-3 inline-flex h-10 items-center rounded-[0.9rem] bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.14em] text-white">Update</button>
                            </form>
                        @endif
                    @endif
                </div>
            </article>

            <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Approval Result</p>
                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Pending Bills</p>
                        <p class="mt-2 text-xl font-black text-slate-950">Rs. {{ number_format((float) $invoicePreview['total_due'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Invoice Applied</p>
                        <p class="mt-2 text-xl font-black text-emerald-700">Rs. {{ number_format((float) $invoicePreview['applied_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Excess Credit</p>
                        <p class="mt-2 text-xl font-black text-cyan-700">Rs. {{ number_format((float) $excess, 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Manual Items</p>
                        <p class="mt-2 text-xl font-black text-amber-700">{{ $manualRows->count() }}</p>
                    </div>
                </div>

                @if($paymentRequest->status === 'pending')
                    <form method="POST" action="{{ route('admin.finance-v2.payments.approve', $paymentRequest) }}" class="mt-5 rounded-[1.2rem] border border-slate-200 bg-slate-50 p-4">
                        @csrf
                        @method('PATCH')
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Admin Verified Amount</span>
                                <input type="number" step="0.01" min="0.01" name="admin_verified_amount" value="{{ $verifiedDefault }}" class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 px-3 text-sm font-bold">
                            </label>
                            <label class="block">
                                <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Admin Note</span>
                                <input type="text" name="admin_note" value="{{ old('admin_note', $paymentRequest->admin_note) }}" class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 px-3 text-sm font-bold">
                            </label>
                        </div>
                        <div class="mt-4 flex flex-wrap justify-end gap-3">
                            <button formaction="{{ route('admin.finance-v2.payments.reject', $paymentRequest) }}" class="inline-flex h-11 items-center rounded-[1rem] border border-red-200 bg-white px-5 text-xs font-black uppercase tracking-[0.16em] text-red-700">Reject</button>
                            <button class="inline-flex h-11 items-center rounded-[1rem] bg-orange-500 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-orange-600">Approve</button>
                        </div>
                    </form>
                @endif
            </article>
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Auto Selected</p>
                <h3 class="mt-1 text-xl font-black text-slate-950">Oldest pending invoices</h3>
            </div>
            <div class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Invoice</th>
                            <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Date</th>
                            <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Remaining</th>
                            <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Allocate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($invoicePreview['invoices'] as $row)
                            <tr>
                                <td class="px-4 py-3 text-sm font-black text-slate-950">{{ $row['invoice']->invoice_number }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-600">{{ $row['invoice']->business_date?->toDateString() }}</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-amber-700">Rs. {{ number_format((float) $row['invoice']->balance_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-emerald-700">Rs. {{ number_format((float) $row['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No invoice pending. Approved amount becomes shop credit.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Manual Check Required</p>
                <h3 class="mt-1 text-xl font-black text-slate-950">Expenses, salary and staff advances</h3>
                <p class="mt-1 text-sm font-semibold text-slate-500">These items are visible for admin review. Invoice allocation is applied first; related payables stay listed for checking.</p>
            </div>
            @include('admin.finance-v2.partials.detail-table', ['rows' => $manualRows])
        </section>
    </div>
</x-layouts.accounting>
