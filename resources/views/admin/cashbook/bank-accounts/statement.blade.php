@extends('admin.cashbook.layouts.app')

@section('title', $account->name . ' - Statement')

@section('header_title')
    <i data-lucide="list-checks" class="h-5 w-5 text-emerald-600"></i> Account Statement
@endsection

@section('header_subtitle')
    Statement rows and reconciliation status for one bank, cash in hand, or wallet account.
@endsection

@section('header_actions')
    <a href="{{ route('admin.cashbook.bank-accounts.show', $account) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
        <i data-lucide="landmark" class="h-4 w-4"></i>
        <span class="hidden sm:inline">Details</span>
    </a>
@endsection

@section('content')
    @php
        $moneyIn = (float) ($statementSummary?->money_in ?? 0);
        $moneyOut = (float) ($statementSummary?->money_out ?? 0);
        $matchedTotal = (float) ($statementSummary?->matched_total ?? 0);
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

        <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="break-words text-xl font-extrabold text-slate-950">{{ $account->name }}</h2>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $account->account_type }}</span>
                    </div>
                    <div class="mt-2 grid gap-2 text-xs font-semibold text-slate-500 sm:grid-cols-2">
                        <div>{{ $account->bank_name ?: 'No bank/provider set' }}</div>
                        <div class="font-mono">{{ $account->account_number ?: 'No account number set' }}</div>
                    </div>
                </div>

                <div class="grid w-full grid-cols-2 gap-2 text-xs sm:grid-cols-4 xl:max-w-3xl">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <span class="block font-black uppercase text-slate-400">Balance</span>
                        <strong class="mt-1 block break-words font-mono text-slate-950">₹{{ number_format($account->current_balance, 2) }}</strong>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3">
                        <span class="block font-black uppercase text-emerald-600">Money In</span>
                        <strong class="mt-1 block break-words font-mono text-emerald-700">₹{{ number_format($moneyIn, 2) }}</strong>
                    </div>
                    <div class="rounded-xl bg-rose-50 p-3">
                        <span class="block font-black uppercase text-rose-600">Money Out</span>
                        <strong class="mt-1 block break-words font-mono text-rose-700">₹{{ number_format($moneyOut, 2) }}</strong>
                    </div>
                    <div class="rounded-xl bg-cyan-50 p-3">
                        <span class="block font-black uppercase text-cyan-600">Matched</span>
                        <strong class="mt-1 block break-words font-mono text-cyan-700">₹{{ number_format($matchedTotal, 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[0.72fr_1.28fr]">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <h3 class="text-base font-extrabold text-slate-950">Add Statement Entry</h3>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Manual entry for this account only.</p>
                </div>

                <form method="POST" action="{{ route('admin.cashbook.finance.statement-entries.store') }}" class="space-y-3 text-xs">
                    @csrf
                    <input type="hidden" name="company_account_id" value="{{ $account->id }}">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <input type="date" name="transaction_date" value="{{ today()->toDateString() }}" required class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                        <select name="direction" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                            <option value="in">Money In</option>
                            <option value="out">Money Out</option>
                        </select>
                    </div>
                    <input type="number" step="0.01" min="0.01" name="amount" required class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800" placeholder="Amount">
                    <input type="text" name="reference" class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800" placeholder="Reference number">
                    <textarea name="narration" rows="3" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800" placeholder="Bank narration or cash in hand receipt detail"></textarea>
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 font-bold text-white hover:bg-emerald-500">
                        <i data-lucide="plus-circle" class="h-4 w-4"></i> Add Entry
                    </button>
                </form>
            </div>

            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-950">Statement Rows</h3>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">Open, partial, and matched entries for this account.</p>
                    </div>
                    <span class="font-mono text-xs font-bold text-slate-400">{{ $statementEntries->total() }} rows</span>
                </div>

                <div class="space-y-3 lg:hidden">
                    @forelse($statementEntries as $entry)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-mono text-xs font-bold text-slate-700">{{ $entry->transaction_date?->format('Y-m-d') }}</div>
                                    <p class="mt-1 break-words text-xs font-semibold text-slate-500">{{ $entry->narration ?: $entry->reference ?: 'Statement entry' }}</p>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono text-sm font-extrabold {{ $entry->direction === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format($entry->amount, 2) }}</div>
                                    <div class="mt-1 rounded-full bg-white px-2 py-0.5 text-[10px] font-black uppercase text-slate-500">{{ str_replace('_', ' ', $entry->status) }}</div>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-lg bg-white p-2">
                                    <span class="block font-bold text-slate-400">Matched</span>
                                    <strong class="font-mono text-cyan-700">₹{{ number_format($entry->matched_amount, 2) }}</strong>
                                </div>
                                <div class="rounded-lg bg-white p-2">
                                    <span class="block font-bold text-slate-400">Open</span>
                                    <strong class="font-mono text-amber-700">₹{{ number_format(max(0, (float) $entry->amount - (float) $entry->matched_amount), 2) }}</strong>
                                </div>
                            </div>
                            @if($entry->reconciliations->isNotEmpty())
                                <div class="mt-3 rounded-lg bg-white p-2 text-xs font-semibold text-slate-600">
                                    {{ $entry->reconciliations->pluck('paymentRequest.shop.name')->filter()->join(', ') ?: 'Reconciled payment' }}
                                </div>
                            @endif
                            @if($entry->direction === 'in' && in_array($entry->status, ['unmatched', 'partially_matched'], true))
                                <a href="{{ route('admin.cashbook.finance.reconciliation', ['statementRef' => $entry->secureRouteKey(), 'company_account_id' => $entry->company_account_id, 'month' => $entry->transaction_date?->format('Y-m')]) }}" class="mt-3 inline-flex min-h-9 w-full items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-500">
                                    <i data-lucide="git-compare-arrows" class="h-4 w-4"></i> Reconcile
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">No statement entries yet.</div>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto custom-scrollbar lg:block">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <th class="px-3 py-3">Date</th>
                                <th class="px-3 py-3">Narration</th>
                                <th class="px-3 py-3">Ref</th>
                                <th class="px-3 py-3 text-right">Amount</th>
                                <th class="px-3 py-3 text-right">Matched</th>
                                <th class="px-3 py-3 text-right">Open</th>
                                <th class="px-3 py-3">Reconciled With</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($statementEntries as $entry)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-3 font-mono font-bold text-slate-700">{{ $entry->transaction_date?->format('Y-m-d') }}</td>
                                    <td class="max-w-md px-3 py-3 font-semibold text-slate-700">{{ $entry->narration ?: '-' }}</td>
                                    <td class="px-3 py-3 font-mono font-bold text-slate-600">{{ $entry->reference ?: '-' }}</td>
                                    <td class="px-3 py-3 text-right font-mono font-extrabold {{ $entry->direction === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format($entry->amount, 2) }}</td>
                                    <td class="px-3 py-3 text-right font-mono font-bold text-cyan-700">₹{{ number_format($entry->matched_amount, 2) }}</td>
                                    <td class="px-3 py-3 text-right font-mono font-bold text-amber-700">₹{{ number_format(max(0, (float) $entry->amount - (float) $entry->matched_amount), 2) }}</td>
                                    <td class="px-3 py-3 font-semibold text-slate-600">
                                        {{ $entry->reconciliations->pluck('paymentRequest.shop.name')->filter()->join(', ') ?: '-' }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ str_replace('_', ' ', $entry->status) }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        @if($entry->direction === 'in' && in_array($entry->status, ['unmatched', 'partially_matched'], true))
                                            <a href="{{ route('admin.cashbook.finance.reconciliation', ['statementRef' => $entry->secureRouteKey(), 'company_account_id' => $entry->company_account_id, 'month' => $entry->transaction_date?->format('Y-m')]) }}" class="inline-flex min-h-8 items-center justify-center rounded-lg bg-emerald-50 px-2 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100">
                                                Reconcile
                                            </a>
                                        @else
                                            <span class="text-[11px] font-bold text-slate-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-3 py-6 text-center font-bold text-slate-400">No statement entries yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $statementEntries->links() }}
                </div>
            </div>
        </section>
    </div>
@endsection
