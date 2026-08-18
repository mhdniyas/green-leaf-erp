@extends('admin.cashbook.layouts.app')

@section('title', 'Payment Journal Detail - Cashbook')

@section('header_title')
    <i data-lucide="book-open-check" class="h-5 w-5 text-emerald-600"></i> Journal Detail
@endsection

@section('header_subtitle')
    Full trace for one shop payment and its reconciliation.
@endsection

@section('header_actions')
    <a href="{{ route('admin.cashbook.finance.journal') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>
        <span class="hidden sm:inline">Journal</span>
    </a>
@endsection

@section('content')
    @php
        $floatingAmount = (float) $paymentRequest->floating_amount > 0
            ? (float) $paymentRequest->floating_amount
            : max(0, (float) $paymentRequest->requested_amount - (float) $paymentRequest->reconciled_amount);
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="break-words text-xl font-extrabold text-slate-950">{{ $paymentRequest->shop?->name ?? 'Shop Payment' }}</h2>
                        <span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-black uppercase text-amber-700">{{ $paymentRequest->reconciliationStatusLabel() }}</span>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $paymentRequest->paymentMethodLabel() }}</span>
                    </div>
                    <div class="mt-2 grid gap-2 text-xs font-semibold text-slate-500 sm:grid-cols-2">
                        <div>Reference: <span class="font-mono font-bold text-slate-700">{{ $paymentRequest->payment_reference ?: '-' }}</span></div>
                        <div>Payment date: <span class="font-mono font-bold text-slate-700">{{ $paymentRequest->payment_date?->format('Y-m-d') ?: '-' }}</span></div>
                        <div>Requested by: <span class="font-bold text-slate-700">{{ $paymentRequest->requestedBy?->name ?: '-' }}</span></div>
                        <div>Reviewed by: <span class="font-bold text-slate-700">{{ $paymentRequest->reviewedBy?->name ?: '-' }}</span></div>
                    </div>
                </div>
                <a href="{{ route('admin.cashbook.finance') }}" class="inline-flex min-h-10 w-full items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 sm:w-auto">
                    <i data-lucide="badge-dollar-sign" class="h-4 w-4"></i> Reconciliation
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Requested</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($paymentRequest->requested_amount, 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Reconciled</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-emerald-700">₹{{ number_format($paymentRequest->reconciled_amount, 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Floating</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-amber-700">₹{{ number_format($floatingAmount, 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Advance</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-cyan-700">₹{{ number_format($paymentRequest->shop_advance_amount, 2) }}</div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[0.8fr_1.2fr]">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <h3 class="text-base font-extrabold text-slate-950">Reconcile This Payment</h3>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Match bank transfer, cheque clearance, UPI, or liquid cash here.</p>
                </div>

                @if($paymentRequest->reconciliation_status !== 'reconciled')
                    <form method="POST" action="{{ route('admin.cashbook.finance.payments.reconcile', $paymentRequest) }}" class="space-y-3 text-xs">
                        @csrf
                        <select name="company_account_id" required class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                            <option value="">Account</option>
                            @foreach($companyAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                        <select name="statement_entry_id" class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                            <option value="">Auto add to selected account statement</option>
                            @foreach($openStatementEntries as $entry)
                                <option value="{{ $entry->id }}">
                                    {{ $entry->companyAccount?->name }} / {{ $entry->transaction_date?->format('d M') }} / ₹{{ number_format($entry->amount - $entry->matched_amount, 2) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <input type="number" step="0.01" min="0.01" name="cleared_amount" value="{{ number_format($floatingAmount ?: $paymentRequest->requested_amount, 2, '.', '') }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800" placeholder="Cleared">
                            <input type="number" step="0.01" min="0" name="statement_amount" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800" placeholder="Bank amount">
                        </div>
                        <select name="difference_action" class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                            <option value="none">No difference</option>
                            <option value="keep_floating">Keep floating</option>
                            <option value="shop_expense">Add shop expense</option>
                            <option value="shop_income">Add shop income</option>
                        </select>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <input type="number" step="0.01" min="0" name="difference_amount" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800" placeholder="Difference">
                            <input type="date" name="business_date" value="{{ today()->toDateString() }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                        </div>
                        <textarea name="admin_note" rows="3" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800" placeholder="Admin note"></textarea>
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 font-bold text-white hover:bg-emerald-500">
                            <i data-lucide="check-circle-2" class="h-4 w-4"></i> Approve Reconciliation
                        </button>
                    </form>
                @else
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">
                        This payment is fully reconciled.
                    </div>
                @endif
            </div>

            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <h3 class="text-base font-extrabold text-slate-950">Reconciliation Trace</h3>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Account, statement, amount, difference, and admin record.</p>
                </div>
                <div class="space-y-3">
                    @forelse($paymentRequest->reconciliations as $reconciliation)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-black text-slate-950">{{ $reconciliation->companyAccount?->name ?? 'Company account' }}</div>
                                    <p class="mt-1 break-words text-xs font-semibold text-slate-500">
                                        {{ $reconciliation->statementEntry?->narration ?: $reconciliation->statementEntry?->reference ?: 'Auto statement entry' }}
                                    </p>
                                    <div class="mt-1 text-[11px] font-bold text-slate-400">
                                        {{ $reconciliation->reconciled_at?->format('Y-m-d H:i') }} / {{ $reconciliation->reconciledBy?->name ?? '-' }}
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase text-emerald-700">{{ $reconciliation->status }}</span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                <div class="rounded-xl bg-white p-2">
                                    <span class="block font-bold text-slate-400">Statement</span>
                                    <strong class="font-mono text-slate-800">₹{{ number_format($reconciliation->statement_amount, 2) }}</strong>
                                </div>
                                <div class="rounded-xl bg-white p-2">
                                    <span class="block font-bold text-slate-400">Cleared</span>
                                    <strong class="font-mono text-emerald-700">₹{{ number_format($reconciliation->cleared_amount, 2) }}</strong>
                                </div>
                                <div class="rounded-xl bg-white p-2">
                                    <span class="block font-bold text-slate-400">Difference</span>
                                    <strong class="font-mono text-amber-700">₹{{ number_format($reconciliation->difference_amount, 2) }}</strong>
                                </div>
                                <div class="rounded-xl bg-white p-2">
                                    <span class="block font-bold text-slate-400">Action</span>
                                    <strong class="text-slate-700">{{ str_replace('_', ' ', $reconciliation->difference_action) }}</strong>
                                </div>
                            </div>
                            @if($reconciliation->admin_note)
                                <div class="mt-3 rounded-xl bg-white p-3 text-xs font-semibold text-slate-600">{{ $reconciliation->admin_note }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">No reconciliation recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 border-b border-slate-200 pb-3">
                <h3 class="text-base font-extrabold text-slate-950">Allocation Details</h3>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">Invoices or balance records connected to this payment.</p>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse($paymentRequest->allocations as $allocation)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="text-sm font-black text-slate-950">Invoice #{{ $allocation->invoice?->invoice_number ?: $allocation->shop_invoice_id }}</div>
                        <div class="mt-2 font-mono text-lg font-extrabold text-emerald-700">₹{{ number_format($allocation->amount, 2) }}</div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400 md:col-span-2 xl:col-span-3">
                        No invoice allocation rows. This may be a shop balance payment.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
