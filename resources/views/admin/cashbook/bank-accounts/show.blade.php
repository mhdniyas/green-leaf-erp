@extends('admin.cashbook.layouts.app')

@section('title', $account->name . ' - Account Details')

@section('header_title')
    <i data-lucide="landmark" class="h-5 w-5 text-emerald-600"></i> Account Details
@endsection

@section('header_subtitle')
    Account balance, statement activity, and reconciliation trace.
@endsection

@section('header_actions')
    <a href="{{ route('admin.cashbook.bank-accounts.statement', $account) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
        <i data-lucide="list-checks" class="h-4 w-4"></i>
        <span class="hidden sm:inline">Statement</span>
    </a>
@endsection

@section('content')
    @php
        $moneyIn = (float) ($statementSummary?->money_in ?? 0);
        $moneyOut = (float) ($statementSummary?->money_out ?? 0);
        $matchedTotal = (float) ($statementSummary?->matched_total ?? 0);
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="break-words text-xl font-extrabold text-slate-950">{{ $account->name }}</h2>
                        @if($account->is_default)
                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase text-emerald-700">Default</span>
                        @endif
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $account->account_type }}</span>
                    </div>
                    <div class="mt-2 grid gap-2 text-xs font-semibold text-slate-500 sm:grid-cols-2">
                        <div>{{ $account->bank_name ?: 'No bank/provider set' }}</div>
                        <div class="font-mono">{{ $account->account_number ?: 'No account number set' }}</div>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <a href="{{ route('admin.cashbook.bank-accounts.create') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                        <i data-lucide="settings-2" class="h-4 w-4"></i> Manage Accounts
                    </a>
                    <a href="{{ route('admin.cashbook.finance') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                        <i data-lucide="badge-dollar-sign" class="h-4 w-4"></i> Finance
                    </a>
                </div>
            </div>
        </div>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Current Balance</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-emerald-700">₹{{ number_format($account->current_balance, 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Opening Balance</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($account->opening_balance, 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Money In / Out</span>
                <div class="mt-2 font-mono text-sm font-extrabold text-slate-950">₹{{ number_format($moneyIn, 2) }}</div>
                <div class="mt-1 font-mono text-sm font-extrabold text-rose-600">₹{{ number_format($moneyOut, 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Matched / Open</span>
                <div class="mt-2 font-mono text-sm font-extrabold text-cyan-700">₹{{ number_format($matchedTotal, 2) }}</div>
                <div class="mt-1 text-xs font-bold text-amber-700">{{ $account->unmatched_statement_count }} statement rows open</div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-950">Recent Statement</h3>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">Latest entries from this account.</p>
                    </div>
                    <a href="{{ route('admin.cashbook.bank-accounts.statement', $account) }}" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200">View All</a>
                </div>
                <div class="space-y-2">
                    @forelse($recentStatementEntries as $entry)
                        <div class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-slate-700">{{ $entry->transaction_date?->format('Y-m-d') }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase {{ $entry->direction === 'in' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $entry->direction === 'in' ? 'In' : 'Out' }}</span>
                                    <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black uppercase text-slate-500">{{ str_replace('_', ' ', $entry->status) }}</span>
                                </div>
                                <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $entry->narration ?: $entry->reference ?: 'Statement entry' }}</p>
                            </div>
                            <div class="font-mono text-sm font-extrabold {{ $entry->direction === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format($entry->amount, 2) }}</div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-200 p-5 text-center text-xs font-bold text-slate-400">No statement entries yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <h3 class="text-base font-extrabold text-slate-950">Recent Reconciliations</h3>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Where this account money was matched.</p>
                </div>
                <div class="space-y-2">
                    @forelse($recentReconciliations as $reconciliation)
                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="text-xs font-black text-slate-950">{{ $reconciliation->paymentRequest?->shop?->name ?? 'Shop Payment' }}</div>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $reconciliation->admin_note ?: 'Approved reconciliation' }}</p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <div class="font-mono text-sm font-extrabold text-emerald-700">₹{{ number_format($reconciliation->cleared_amount, 2) }}</div>
                                    <div class="mt-1 font-mono text-[11px] font-bold text-slate-400">{{ $reconciliation->reconciled_at?->format('Y-m-d') }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-200 p-5 text-center text-xs font-bold text-slate-400">No reconciliations for this account yet.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
