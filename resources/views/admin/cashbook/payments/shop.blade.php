@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Payment Reconciliation')

@section('header_title')
    <i data-lucide="handshake" class="h-5 w-5 text-emerald-600"></i> Shop Payment Reconciliation
@endsection

@section('header_subtitle')
    Reconcile a real shop receipt with the shop ledger relations it settles.
@endsection

@section('content')
    @php
        $selectedReconciliation = $selectedPayment?->reconciliations->firstWhere('is_finalized', true);
        $transactionPayload = $openTransactions->map(fn ($transaction) => [
            'ref' => $transaction->secureRouteKey(),
            'date' => $transaction->business_date?->format('d M Y'),
            'type' => $transaction->entryType?->name ?? 'Shop transaction',
            'description' => $transaction->notes ?: ($transaction->entryType?->code ?? '-'),
            'side' => $transaction->settlement_side,
            'open' => (float) $transaction->open_amount,
            'reconciled' => (float) $transaction->reconciled_amount,
        ])->values();
    @endphp

    <div class="mx-auto max-w-7xl space-y-5" x-data="shopPaymentWorkspace(@js($transactionPayload), {{ $selectedPayment ? (float) $selectedPayment->reconciled_amount : 0 }})">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-gradient-to-r from-emerald-50 via-white to-sky-50 px-5 py-5 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-emerald-800">Shop settlement</span>
                            <span class="text-xs font-bold text-slate-500">{{ $month }}</span>
                        </div>
                        <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $currentShop->name }}</h1>
                        <p class="mt-1 text-sm font-medium text-slate-600">Select the open ledger relations covered by one finalized company receipt.</p>
                    </div>
                    <a href="{{ route('admin.cashbook.shop.show', $currentShop->uuid) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 shadow-sm hover:bg-slate-50">
                        <i data-lucide="book-open" class="h-4 w-4"></i> View Shop Ledger
                    </a>
                </div>
            </div>
            <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 sm:grid-cols-4 sm:divide-y-0">
                <div class="p-4 sm:px-5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Shop Position</p>
                    <p class="mt-1 font-mono text-xl font-black {{ (float) $shopPosition->closing_shop_position >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format((float) $shopPosition->closing_shop_position, 2) }}</p>
                    <p class="mt-1 text-[11px] font-semibold text-slate-500">Current remittance position</p>
                </div>
                <div class="p-4 sm:px-5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Petty Position</p>
                    <p class="mt-1 font-mono text-xl font-black text-sky-700">₹{{ number_format((float) $shopPosition->closing_petty, 2) }}</p>
                    <p class="mt-1 text-[11px] font-semibold text-slate-500">Existing petty ledger balance</p>
                </div>
                <div class="p-4 sm:px-5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Company Pending</p>
                    <p class="mt-1 font-mono text-xl font-black text-amber-700">₹{{ number_format((float) $shopPosition->closing_company_pending, 2) }}</p>
                    <p class="mt-1 text-[11px] font-semibold text-slate-500">Company-side pending relations</p>
                </div>
                <div class="p-4 sm:px-5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Open Relations</p>
                    <p class="mt-1 font-mono text-xl font-black text-slate-900">{{ $openTransactions->count() }}</p>
                    <p class="mt-1 text-[11px] font-semibold text-slate-500">Rows with settlement effect</p>
                </div>
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Record Payment</p>
            <h2 class="mt-1 text-lg font-black text-slate-950">Receive payment from {{ $currentShop->name }}</h2>
            <p class="mt-1 text-sm font-medium text-slate-600">This records a payment submission only. It remains pending until matched to an actual company Cash/Bank statement. No shop ledger position changes until finalization.</p>
            <form method="POST" action="{{ route('admin.cashbook.shop.accept-payment.store', $currentShop->uuid) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <input type="hidden" name="request_uuid" value="{{ (string) str()->uuid() }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <label class="text-xs font-black text-slate-700">Amount Received
                    <input required name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 font-mono text-sm font-black text-slate-900">
                </label>
                <label class="text-xs font-black text-slate-700">Payment Method
                    <select required name="payment_method" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-900">
                        <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                        <option value="online_upi" @selected(old('payment_method', 'online_upi') === 'online_upi')>Bank</option>
                        <option value="cheque" @selected(old('payment_method') === 'cheque')>Cheque</option>
                    </select>
                </label>
                <label class="text-xs font-black text-slate-700">Payment Date
                    <input required name="payment_date" type="date" value="{{ old('payment_date', today()->toDateString()) }}" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-900">
                </label>
                <label class="text-xs font-black text-slate-700 md:col-span-2">Reference / Cheque No.
                    <input name="payment_reference" value="{{ old('payment_reference') }}" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-900">
                </label>
                <label class="text-xs font-black text-slate-700">Cheque Bank
                    <input name="cheque_bank_name" value="{{ old('cheque_bank_name') }}" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-900">
                </label>
                <label class="text-xs font-black text-slate-700">Cheque Date
                    <input name="cheque_date" type="date" value="{{ old('cheque_date') }}" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-900">
                </label>
                <label class="text-xs font-black text-slate-700 md:col-span-2">Notes
                    <input name="notes" value="{{ old('notes') }}" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-900">
                </label>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-5 text-sm font-black text-white hover:bg-emerald-800 xl:self-end"><i data-lucide="wallet" class="h-4 w-4"></i> Record Payment</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Pending Payments</p><h2 class="mt-1 text-lg font-black text-slate-950">Awaiting actual company Cash/Bank statement</h2></div>
            <div class="divide-y divide-slate-100">
                @forelse ($pendingPayments as $paymentRequest)
                    @php($statement = $paymentRequest->reconciliations->first()?->statementEntry)
                    @php($paymentStatus = $paymentRequest->reconciliation_status === 'partially_reconciled' ? 'Partially Reconciled' : 'Awaiting Reconciliation')
                    <div class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_auto] sm:items-center sm:px-6">
                        <div class="grid gap-2 text-sm sm:grid-cols-4"><span class="font-mono font-black text-slate-900">₹{{ number_format((float) $paymentRequest->requested_amount, 2) }}</span><span class="font-bold text-slate-700">{{ $paymentRequest->paymentMethodLabel() }} · {{ $paymentRequest->payment_date?->format('d M Y') }}</span><span class="text-xs font-semibold text-slate-500">{{ $statement?->companyAccount?->name ?: 'Awaiting statement' }} · {{ $paymentRequest->payment_reference ?: 'No reference' }}</span><span class="text-xs font-black text-amber-700">{{ $paymentStatus }}</span></div>
                        @if ($statement)
                            <a href="{{ route('admin.cashbook.finance.reconciliation', ['classify_statement' => $statement->public_uuid, 'company_account_uuid' => $statement->companyAccount?->public_uuid, 'month' => $statement->transaction_date?->format('Y-m')]) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-amber-100 px-4 text-xs font-black text-amber-900 hover:bg-amber-200">Reconcile</a>
                        @else
                            <span class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-100 px-4 text-xs font-black text-slate-500">Awaiting Statement</span>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm font-semibold text-slate-500 sm:px-6">No pending payments for this shop.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Reconcile Shop Ledger</p><h2 class="mt-1 text-lg font-black text-slate-950">Recent finalized payments</h2></div><form method="GET" action="{{ route('admin.cashbook.shop.accept-payment', $currentShop->uuid) }}"><input type="hidden" name="month" value="{{ $month }}"><select name="payment_ref" onchange="this.form.submit()" class="min-h-11 max-w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-800"><option value="">Select finalized payment</option>@foreach ($finalizedPayments as $paymentRequest)<option value="{{ $paymentRequest->secureRouteKey() }}" @selected($selectedPayment?->is($paymentRequest))>₹{{ number_format((float) $paymentRequest->reconciled_amount, 2) }} · {{ $paymentRequest->payment_date?->format('d M Y') }}</option>@endforeach</select></form></div>
            <div class="mt-4 divide-y divide-slate-100">@forelse ($recentFinalizedPayments as $paymentRequest)<div class="grid gap-3 py-3 sm:grid-cols-[1fr_auto] sm:items-center"><div class="text-sm font-bold text-slate-700"><span class="font-mono text-slate-950">₹{{ number_format((float) $paymentRequest->reconciled_amount, 2) }}</span> · {{ $paymentRequest->paymentMethodLabel() }} · {{ $paymentRequest->payment_reference ?: 'No reference' }} <span class="ml-1 text-xs font-black {{ $paymentRequest->ledger_allocations_exists ? 'text-slate-600' : 'text-emerald-700' }}">{{ $paymentRequest->ledger_allocations_exists ? 'Applied to Shop Ledger' : 'Finalized' }}</span></div><a href="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $currentShop->uuid, 'month' => $month, 'payment_ref' => $paymentRequest->secureRouteKey()]) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl px-4 text-xs font-black {{ $paymentRequest->ledger_allocations_exists ? 'bg-slate-100 text-slate-700' : 'bg-emerald-100 text-emerald-900' }}">{{ $paymentRequest->ledger_allocations_exists ? 'View Settlement' : 'Reconcile Shop Transactions' }}</a></div>@empty<p class="py-4 text-sm font-semibold text-slate-500">No finalized payments yet.</p>@endforelse</div>
        </section>

        @if ($selectedPayment)
            <form method="POST" action="{{ route('admin.cashbook.shop.accept-payment.reconcile', $currentShop->uuid) }}" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                @csrf
                <input type="hidden" name="payment_ref" value="{{ $selectedPayment->secureRouteKey() }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Step 2</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Select Shop Transactions Covered By This Payment</h2>
                        <p class="mt-1 text-sm font-medium text-slate-600">Credits increase what the shop must remit. Debits reduce it. Fully settled rows are hidden.</p>
                    </div>
                    <div class="border-b border-slate-100 px-5 py-3 sm:px-6">
                        <div class="flex gap-2 overflow-x-auto pb-1">
                            <button type="button" @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'" class="shrink-0 rounded-xl px-3 py-2 text-xs font-black">All</button>
                            <button type="button" @click="activeTab = 'credit'" :class="activeTab === 'credit' ? 'bg-emerald-700 text-white' : 'bg-emerald-50 text-emerald-700'" class="shrink-0 rounded-xl px-3 py-2 text-xs font-black">Income / Credits</button>
                            <button type="button" @click="activeTab = 'debit'" :class="activeTab === 'debit' ? 'bg-rose-700 text-white' : 'bg-rose-50 text-rose-700'" class="shrink-0 rounded-xl px-3 py-2 text-xs font-black">Expenses / Debits</button>
                        </div>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <template x-for="row in filteredRows" :key="row.ref">
                            <div class="p-4 sm:px-6" :class="selected[row.ref] ? 'bg-slate-50' : ''">
                                <div class="flex gap-3">
                                    <input type="checkbox" class="mt-1 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" :checked="selected[row.ref] !== undefined" @change="toggle(row)">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-sm font-black text-slate-950" x-text="row.type"></span>
                                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="row.side === 'credit' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'" x-text="row.side === 'credit' ? 'Credit' : 'Debit'"></span>
                                                </div>
                                                <p class="mt-1 text-xs font-semibold text-slate-500"><span x-text="row.date"></span> · <span x-text="row.description"></span></p>
                                            </div>
                                            <div class="text-left sm:text-right">
                                                <p class="font-mono text-base font-black" :class="row.side === 'credit' ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(row.open)"></p>
                                                <p class="text-[10px] font-bold text-slate-400">Open amount</p>
                                            </div>
                                        </div>
                                        <div class="mt-3 grid gap-2 sm:grid-cols-2" x-show="selected[row.ref] !== undefined" x-cloak>
                                            <label class="text-xs font-bold text-slate-600">Allocate
                                                <input type="number" min="0.01" step="0.01" :max="row.open" x-model.number="selected[row.ref]" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 font-mono font-black text-slate-900">
                                            </label>
                                            <p class="self-end pb-2 text-xs font-semibold text-slate-500">Previously reconciled: <span class="font-mono font-black" x-text="currency(row.reconciled)"></span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div x-show="filteredRows.length === 0" class="p-10 text-center text-sm font-bold text-slate-500">No open shop ledger relations in this view.</div>
                    </div>
                </section>

                <aside class="lg:sticky lg:top-5 lg:self-start">
                    <div class="rounded-3xl border border-slate-200 bg-slate-950 p-5 text-white shadow-xl sm:p-6">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Settlement Summary</p>
                        <div class="mt-5 space-y-3 text-sm font-semibold">
                            <div class="flex justify-between"><span class="text-slate-300">Payment Received</span><strong class="font-mono text-white" x-text="currency(paymentAmount)"></strong></div>
                            <div class="flex justify-between"><span class="text-emerald-300">Selected Credits</span><strong class="font-mono text-emerald-200" x-text="currency(selectedCredits)"></strong></div>
                            <div class="flex justify-between"><span class="text-rose-300">Selected Debits</span><strong class="font-mono text-rose-200" x-text="currency(selectedDebits)"></strong></div>
                            <div class="border-t border-slate-700 pt-3"><div class="flex justify-between"><span class="font-black text-white">Net Shop Amount</span><strong class="font-mono text-base text-white" x-text="currency(netAmount)"></strong></div></div>
                            <div class="flex justify-between rounded-xl px-3 py-2" :class="Math.abs(difference) < 0.005 ? 'bg-emerald-500/20 text-emerald-100' : 'bg-amber-500/20 text-amber-100'"><span class="font-black">Difference</span><strong class="font-mono" x-text="currency(difference)"></strong></div>
                        </div>
                        <p class="mt-5 text-xs font-semibold leading-5 text-slate-300" x-show="Math.abs(difference) >= 0.005">Select credits and debits whose net equals the finalized receipt before continuing.</p>
                        <p class="mt-5 text-xs font-semibold leading-5 text-emerald-200" x-show="Math.abs(difference) < 0.005 && Object.keys(selected).length">The company receipt stays one Cash/Bank movement and one JournalEntry. This only records shop settlement detail.</p>
                        <template x-for="(amount, ref) in selected" :key="ref">
                            <div>
                                <input type="hidden" :name="`allocations[${ref}][ledger_ref]`" :value="ref">
                                <input type="hidden" :name="`allocations[${ref}][amount]`" :value="amount">
                            </div>
                        </template>
                        <button type="submit" :disabled="Math.abs(difference) >= 0.005 || !Object.keys(selected).length" class="mt-6 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 text-sm font-black text-white transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400">
                            <i data-lucide="check-circle-2" class="h-4 w-4"></i> Finalize Shop Reconciliation
                        </button>
                    </div>
                </aside>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
<script>
function shopPaymentWorkspace(rows, paymentAmount) {
    return {
        rows,
        paymentAmount: Number(paymentAmount || 0),
        selected: {},
        activeTab: 'all',
        get filteredRows() { return this.activeTab === 'all' ? this.rows : this.rows.filter((row) => row.side === this.activeTab); },
        get selectedCredits() { return this.rows.filter((row) => row.side === 'credit').reduce((total, row) => total + Number(this.selected[row.ref] || 0), 0); },
        get selectedDebits() { return this.rows.filter((row) => row.side === 'debit').reduce((total, row) => total + Number(this.selected[row.ref] || 0), 0); },
        get netAmount() { return this.selectedCredits - this.selectedDebits; },
        get difference() { return this.paymentAmount - this.netAmount; },
        toggle(row) { if (this.selected[row.ref] === undefined) { this.selected[row.ref] = row.open; return; } delete this.selected[row.ref]; },
        currency(amount) { return '₹' + Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endpush
