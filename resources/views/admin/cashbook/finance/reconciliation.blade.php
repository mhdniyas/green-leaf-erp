@extends('admin.cashbook.layouts.app')

@section('title', 'Statement Reconciliation - Cashbook')

@section('header_title')
    <i data-lucide="git-compare-arrows" class="h-5 w-5 text-emerald-600"></i> Statement Reconciliation
@endsection

@section('header_subtitle')
    Select one bank or cash statement row, then approve matching shop payments without clutter.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.finance.journal') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
            <i data-lucide="book-open-check" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Journal</span>
        </a>
        <a href="{{ route('admin.cashbook.finance') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
            <i data-lucide="badge-dollar-sign" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Finance</span>
        </a>
    </div>
@endsection

@section('content')
    @php
        $remainingStatement = $selectedStatement
            ? max(0, (float) $selectedStatement->amount - (float) $selectedStatement->matched_amount)
            : 0;
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

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.cashbook.finance.reconciliation') }}" class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto_auto_1fr_auto]">
                <select name="company_account_id" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    @foreach($companyAccounts as $account)
                        <option value="{{ $account->id }}" @selected((int) $selectedAccountId === (int) $account->id)>
                            {{ $account->name }} / {{ strtoupper($account->account_type) }}
                        </option>
                    @endforeach
                </select>
                <input type="month" name="month" value="{{ $month }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                <input type="number" min="0" max="60" name="grace_days" value="{{ $graceDays }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" placeholder="Grace days">
                <input type="search" name="search" value="{{ $search }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" placeholder="Search reference, narration, shop">
                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-4 text-xs font-bold text-white hover:bg-slate-800">
                    <i data-lucide="filter" class="h-4 w-4"></i> Filter
                </button>
            </form>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Statement Queue</h2>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ \Carbon\Carbon::parse($monthStart)->format('F Y') }} unmatched incoming rows.</p>
                    </div>
                    <span class="font-mono text-xs font-bold text-slate-400">{{ $statementEntries->count() }} rows</span>
                </div>

                <div class="space-y-2">
                    @forelse($statementEntries as $entry)
                        @php($remaining = max(0, (float) $entry->amount - (float) $entry->matched_amount))
                        <a href="{{ route('admin.cashbook.finance.reconciliation', ['statementRef' => $entry->secureRouteKey(), 'company_account_id' => $entry->company_account_id, 'month' => $month, 'grace_days' => $graceDays, 'search' => $search]) }}"
                           class="block rounded-2xl border p-3 transition {{ $selectedStatement && $selectedStatement->id === $entry->id ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50 hover:bg-white' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-mono text-sm font-black text-slate-950">₹{{ number_format($entry->amount, 2) }}</div>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-600">{{ $entry->narration ?: $entry->reference ?: 'Statement credit' }}</p>
                                    <p class="mt-1 text-[11px] font-bold text-slate-400">{{ $entry->transaction_date?->format('Y-m-d') }} / {{ $entry->companyAccount?->name }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $entry->status }}</span>
                                    <div class="mt-2 font-mono text-xs font-bold text-amber-700">Open ₹{{ number_format($remaining, 2) }}</div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">
                            No unmatched statement credits for this filter.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                @if($selectedStatement)
                    <div class="mb-4 border-b border-slate-200 pb-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="text-base font-extrabold text-slate-950">Match This Statement Row</h2>
                                <p class="mt-1 break-words text-xs font-semibold text-slate-500">{{ $selectedStatement->narration ?: $selectedStatement->reference ?: 'No narration' }}</p>
                            </div>
                            <div class="shrink-0 rounded-xl bg-slate-50 px-3 py-2 text-right">
                                <span class="block text-[10px] font-black uppercase text-slate-400">Remaining</span>
                                <strong class="font-mono text-lg font-extrabold text-emerald-700">₹{{ number_format($remainingStatement, 2) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @forelse($possiblePayments as $candidate)
                            @php($payment = $candidate['payment'])
                            <form method="POST" action="{{ route('admin.cashbook.finance.reconciliation.match', ['statementRef' => $selectedStatement->secureRouteKey(), 'grace_days' => $graceDays]) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                @csrf
                                <input type="hidden" name="payment_request_ref" value="{{ $payment->secureRouteKey() }}">
                                <input type="hidden" name="business_date" value="{{ $selectedStatement->transaction_date?->toDateString() }}">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a href="{{ route('admin.cashbook.finance.journal.secure-show', $payment->secureRouteKey()) }}" class="text-sm font-black text-slate-950 hover:text-emerald-700">{{ $payment->shop?->name ?? 'Shop' }}</a>
                                            <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $payment->paymentMethodLabel() }}</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase text-emerald-700">Score {{ $candidate['score'] }}</span>
                                        </div>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">
                                            {{ $payment->payment_date?->format('Y-m-d') ?: $payment->created_at?->format('Y-m-d') }}
                                            @if($payment->payment_reference)
                                                / {{ $payment->payment_reference }}
                                            @endif
                                        </p>
                                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                            <div class="rounded-xl bg-white p-2">
                                                <span class="block font-bold text-slate-400">Submitted</span>
                                                <strong class="font-mono text-slate-900">₹{{ number_format($payment->requested_amount, 2) }}</strong>
                                            </div>
                                            <div class="rounded-xl bg-white p-2">
                                                <span class="block font-bold text-slate-400">Approved</span>
                                                <strong class="font-mono text-emerald-700">₹{{ number_format($payment->reconciled_amount, 2) }}</strong>
                                            </div>
                                            <div class="rounded-xl bg-white p-2">
                                                <span class="block font-bold text-slate-400">Floating</span>
                                                <strong class="font-mono text-amber-700">₹{{ number_format($candidate['floating_amount'], 2) }}</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid w-full grid-cols-1 gap-2 text-xs sm:grid-cols-2 lg:max-w-lg">
                                        <input type="number" step="0.01" min="0.01" name="cleared_amount" value="{{ number_format(min($candidate['floating_amount'], $remainingStatement), 2, '.', '') }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-mono font-bold text-slate-800" placeholder="Cleared">
                                        <input type="number" step="0.01" min="0.01" name="statement_amount" value="{{ number_format(min($candidate['floating_amount'], $remainingStatement), 2, '.', '') }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-mono font-bold text-slate-800" placeholder="Statement">
                                        <select name="difference_action" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-bold text-slate-800">
                                            <option value="none">No difference</option>
                                            <option value="keep_floating">Keep floating</option>
                                            <option value="shop_expense">Add shop expense</option>
                                            <option value="shop_income">Add shop income</option>
                                        </select>
                                        <input type="number" step="0.01" min="0" name="difference_amount" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-mono font-bold text-slate-800" placeholder="Difference">
                                        <select name="difference_entry_type_id" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-bold text-slate-800">
                                            <option value="">Default category</option>
                                            @foreach($reconciliationEntryTypes as $entryType)
                                                <option value="{{ $entryType->id }}">{{ $entryType->name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="admin_note" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 font-semibold text-slate-800" placeholder="Admin note">
                                        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 font-bold text-white hover:bg-emerald-500 sm:col-span-2">
                                            <i data-lucide="check-circle-2" class="h-4 w-4"></i> Approve Match
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">
                                No payment suggestions inside the {{ $graceDays }} day window. Try increasing grace days or search by reference.
                            </div>
                        @endforelse
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400">
                        Select an unmatched statement row to start reconciliation.
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
