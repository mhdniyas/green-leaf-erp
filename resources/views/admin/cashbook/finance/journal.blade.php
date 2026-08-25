@extends('admin.cashbook.layouts.app')

@section('title', 'Approved Transactions - Cashbook')

@section('header_title')
    <i data-lucide="book-open-check" class="h-5 w-5 text-emerald-600"></i> Approved Transactions
@endsection

@section('header_subtitle')
    Reconciled and finalized company cash and bank transactions.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.accounting.main-account.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 shadow-sm hover:bg-slate-50">
            <i data-lucide="scale" class="h-4 w-4 text-emerald-600"></i>
            <span class="hidden sm:inline">Admin Ledger</span>
        </a>
        <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
            <i data-lucide="check-check" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Reconcile Statements</span>
        </a>
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-5">
        <!-- KPI METRICS -->
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Approved Transaction Volume</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($totals['volume'], 2) }}</div>
                <span class="mt-1 block text-xs font-bold text-slate-500">{{ number_format($totals['count']) }} transactions</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Finalized Transactions</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-emerald-700">{{ number_format($totals['finalized_count']) }}</div>
                <span class="mt-1 block text-xs font-bold text-emerald-600">Locked & reconciled</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pending Visible Here</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-amber-700">{{ number_format($totals['unreconciled_count']) }}</div>
                <span class="mt-1 block text-xs font-bold text-amber-600">Must remain zero</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Canonical Invariant</span>
                <div class="mt-2 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-100 px-2 py-1 text-xs font-black text-emerald-800">
                        <i data-lucide="shield-check" class="h-3.5 w-3.5"></i> Single Ledger
                    </span>
                </div>
                <span class="mt-1 block text-xs font-bold text-slate-500">100% Balanced Debits/Credits</span>
            </div>
        </section>

        <!-- FUNCTIONAL TABS -->
        <section class="white-card rounded-2xl border border-slate-200 p-2 shadow-sm">
            <div class="flex flex-wrap items-center gap-1.5">
                @php
                    $tabs = [
                        'all' => ['label' => 'All Transactions', 'icon' => 'layers'],
                        'bank' => ['label' => 'Bank', 'icon' => 'building-2'],
                        'cash' => ['label' => 'Cash', 'icon' => 'wallet'],
                        'income' => ['label' => 'Income', 'icon' => 'arrow-down-left'],
                        'expense' => ['label' => 'Expenses', 'icon' => 'arrow-up-right'],
                        'purchaser_funding' => ['label' => 'Purchaser Funding', 'icon' => 'wallet-cards'],
                        'vendor_payment' => ['label' => 'Vendor Payments', 'icon' => 'truck'],
                        'customer_receipt' => ['label' => 'Customer Receipts', 'icon' => 'store'],
                        'transfer' => ['label' => 'Transfers', 'icon' => 'arrow-left-right'],
                        'adjustment' => ['label' => 'Adjustments', 'icon' => 'sliders-horizontal'],
                    ];
                @endphp

                @foreach($tabs as $tabKey => $tabInfo)
                    <a href="{{ route('admin.cashbook.finance.journal', array_merge(request()->query(), ['tab' => $tabKey, 'page' => 1])) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition {{ $activeTab === $tabKey ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                        <i data-lucide="{{ $tabInfo['icon'] }}" class="h-3.5 w-3.5"></i>
                        {{ $tabInfo['label'] }}
                    </a>
                @endforeach
            </div>
        </section>

        <!-- FILTERS TOOLBAR -->
        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <form method="GET" action="{{ route('admin.cashbook.finance.journal') }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <input type="hidden" name="tab" value="{{ $activeTab }}">

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Reconciliation Status</label>
                    <select name="status" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        @foreach(['all' => 'All Statuses', 'unreconciled' => 'Unreconciled', 'partially_reconciled' => 'Partially Reconciled', 'reconciled' => 'Reconciled', 'finalized' => 'Finalized'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">From Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">To Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Search Ref / Desc</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search JE#, ref, note..." class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white hover:bg-emerald-500">
                        <i data-lucide="filter" class="h-4 w-4"></i> Filter
                    </button>
                    @if($status !== 'all' || $startDate !== '' || $endDate !== '' || $search !== '' || $activeTab !== 'all')
                        <a href="{{ route('admin.cashbook.finance.journal') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50" title="Clear Filters">
                            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                        </a>
                    @endif
                </div>
            </form>
        </section>

        <!-- CANONICAL JOURNAL TRANSACTIONS TABLE -->
        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Approved Transactions</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Reconciled and finalized company cash and bank transactions.</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $journalEntries->total() }} entries</span>
            </div>

            <!-- MOBILE CARD VIEW -->
            <div class="space-y-3 lg:hidden">
                @forelse($journalEntries as $entry)
                    <a href="{{ route('admin.cashbook.finance.journal.entry-show', $entry->id) }}" class="block rounded-2xl border border-slate-200 bg-slate-50 p-3.5 transition hover:bg-slate-100">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-extrabold text-emerald-800">{{ $entry->formatted_reference }}</span>
                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">{{ $entry->source_label }}</span>
                                </div>
                                <div class="mt-1 text-sm font-black text-slate-900">{{ $entry->description ?: ($entry->reference ?: 'Transaction') }}</div>
                                <div class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $entry->entry_date?->format('d M Y') }}</div>
                            </div>

                            <div class="text-right">
                                <div class="font-mono text-base font-black text-slate-950">₹{{ number_format($entry->primary_amount, 2) }}</div>
                                <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[9px] font-black uppercase {{ $entry->is_finalized ? 'bg-emerald-100 text-emerald-800' : ($entry->reconciliation_status === 'reconciled' ? 'bg-emerald-50 text-emerald-700 border border-emerald-300' : 'bg-slate-200 text-slate-700') }}">
                                    {{ $entry->reconciliation_status_label }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-200 pt-2.5 text-[11px]">
                            <div>
                                <span class="block text-[9px] font-black uppercase text-slate-400">Debit Account</span>
                                <span class="font-semibold text-slate-700">{{ $entry->debit_accounts_summary }}</span>
                            </div>
                            <div>
                                <span class="block text-[9px] font-black uppercase text-slate-400">Credit Account</span>
                                <span class="font-semibold text-slate-700">{{ $entry->credit_accounts_summary }}</span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400">No journal transactions match your current filters.</div>
                @endforelse
            </div>

            <!-- DESKTOP TABLE VIEW -->
            <div class="hidden overflow-x-auto custom-scrollbar lg:block">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-3 py-3">Date</th>
                            <th class="px-3 py-3">Journal Ref</th>
                            <th class="px-3 py-3">Source</th>
                            <th class="px-3 py-3">Description / Reference</th>
                            <th class="px-3 py-3">Debit Account</th>
                            <th class="px-3 py-3">Credit Account</th>
                            <th class="px-3 py-3 text-right">Amount</th>
                            <th class="px-3 py-3 text-center">Balance</th>
                            <th class="px-3 py-3 text-center">Reconciliation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($journalEntries as $entry)
                            <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location.href='{{ route('admin.cashbook.finance.journal.entry-show', $entry->id) }}'">
                                <td class="px-3 py-3 font-mono font-bold text-slate-700">{{ $entry->entry_date?->format('Y-m-d') }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center gap-1 font-mono font-extrabold text-emerald-700 hover:underline">
                                        {{ $entry->formatted_reference }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-700">{{ $entry->source_label }}</span>
                                </td>
                                <td class="max-w-xs truncate px-3 py-3 font-semibold text-slate-900" title="{{ $entry->description }}">
                                    {{ $entry->description ?: ($entry->reference ?: '—') }}
                                </td>
                                <td class="max-w-[12rem] truncate px-3 py-3 font-semibold text-slate-700" title="{{ $entry->debit_accounts_summary }}">
                                    {{ $entry->debit_accounts_summary }}
                                </td>
                                <td class="max-w-[12rem] truncate px-3 py-3 font-semibold text-slate-700" title="{{ $entry->credit_accounts_summary }}">
                                    {{ $entry->credit_accounts_summary }}
                                </td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-950">₹{{ number_format($entry->primary_amount, 2) }}</td>
                                <td class="px-3 py-3 text-center">
                                    @if($entry->is_balanced)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-black text-emerald-700">
                                            <i data-lucide="check" class="h-3 w-3"></i> OK
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[9px] font-black text-rose-700">
                                            <i data-lucide="alert-triangle" class="h-3 w-3"></i> Unbalanced
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($entry->is_finalized)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-900">
                                            <i data-lucide="lock" class="h-3 w-3"></i> FINALIZED
                                        </span>
                                    @elseif($entry->reconciliation_status === 'reconciled')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-300 px-2 py-0.5 text-[10px] font-black text-emerald-700">
                                            RECONCILED
                                        </span>
                                    @elseif($entry->reconciliation_status === 'partially_reconciled')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800">
                                            PARTIAL
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-500">
                                            UNRECONCILED
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-sm font-bold text-slate-400">No journal transactions found for the selected criteria.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $journalEntries->links() }}
            </div>
        </section>
    </div>
@endsection
