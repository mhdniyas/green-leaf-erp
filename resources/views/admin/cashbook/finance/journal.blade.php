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
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-600">From Date</label>
                        <x-cashbook.previous-month-button mode="range" size="xs" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
                    </div>
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
                    <div class="rounded-2xl border {{ $entry->is_reversed ? 'border-rose-200 bg-rose-50/40' : ($entry->is_reversal ? 'border-slate-300 bg-slate-100/50' : ($entry->is_replacement ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50')) }} p-3.5 transition hover:bg-slate-100/80">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <a href="{{ route('admin.cashbook.finance.journal.entry-show', $entry->id) }}" class="font-mono text-xs font-extrabold text-emerald-800 hover:underline">
                                        {{ $entry->formatted_reference }}
                                    </a>
                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">{{ $entry->source_label }}</span>
                                    @if($entry->is_reversed)
                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase text-rose-800">REVERSED</span>
                                    @elseif($entry->is_reversal)
                                        <span class="rounded-full bg-slate-300 px-2 py-0.5 text-[9px] font-black uppercase text-slate-800">REVERSAL</span>
                                    @elseif($entry->is_replacement)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">REPLACEMENT</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-sm font-black text-slate-900">{{ $entry->description ?: ($entry->reference ?: 'Transaction') }}</div>
                                <div class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $entry->entry_date?->format('d M Y') }}</div>
                                @if($entry->is_reversed && $entry->replacement_entry)
                                    <a href="{{ route('admin.cashbook.finance.journal.entry-show', $entry->replacement_entry->id) }}" class="mt-1 inline-block font-mono text-[10px] font-bold text-emerald-700 underline">
                                        ↳ Replaced by {{ $entry->replacement_entry->formatted_reference }}
                                    </a>
                                @elseif($entry->is_replacement && $entry->original_reversed_entry)
                                    <a href="{{ route('admin.cashbook.finance.journal.entry-show', $entry->original_reversed_entry->id) }}" class="mt-1 inline-block font-mono text-[10px] font-bold text-slate-500 underline">
                                        ↳ Replaces {{ $entry->original_reversed_entry->formatted_reference }}
                                    </a>
                                @endif
                            </div>

                            <div class="text-right">
                                <div class="font-mono text-base font-black text-slate-950 {{ $entry->is_reversed ? 'line-through text-slate-400' : '' }}">₹{{ number_format($entry->primary_amount, 2) }}</div>
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

                        <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-2.5">
                            <a href="{{ route('admin.cashbook.finance.journal.entry-show', $entry->id) }}" class="inline-flex items-center gap-1 text-xs font-bold text-slate-600 hover:text-slate-900">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i> Details
                            </a>
                            @if(! $entry->is_reversed && ! $entry->is_reversal)
                                <button type="button"
                                        onclick="openEditJournalModal({{ json_encode([
                                            'id' => $entry->id,
                                            'formatted_reference' => $entry->formatted_reference,
                                            'source_label' => $entry->source_label,
                                            'source_type' => $entry->source_type,
                                            'purchaser_id' => $entry->purchaserCredit?->purchaser_id,
                                            'amount' => $entry->primary_amount,
                                            'entry_date' => $entry->entry_date?->format('Y-m-d'),
                                            'reference' => $entry->reference,
                                            'description' => $entry->description,
                                            'reconciliation_status' => $entry->reconciliation_status_label,
                                            'is_finalized' => $entry->is_finalized,
                                            'company_account_id' => $entry->statementEntries->first()?->company_account_id ?: $entry->purchaserCredit?->company_account_id,
                                        ]) }})"
                                        class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-100">
                                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit Entry
                                </button>
                            @else
                                <span class="text-[11px] font-bold text-slate-400">Immutable</span>
                            @endif
                        </div>
                    </div>
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
                            <th class="px-3 py-3 text-center">Status</th>
                            <th class="px-3 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($journalEntries as $entry)
                            <tr class="hover:bg-slate-50 {{ $entry->is_reversed ? 'bg-rose-50/20 text-slate-400' : ($entry->is_reversal ? 'bg-slate-100/30' : '') }}">
                                <td class="px-3 py-3 font-mono font-bold text-slate-700">{{ $entry->entry_date?->format('Y-m-d') }}</td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('admin.cashbook.finance.journal.entry-show', $entry->id) }}" class="inline-flex items-center gap-1 font-mono font-extrabold text-emerald-700 hover:underline">
                                        {{ $entry->formatted_reference }}
                                    </a>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-col gap-1">
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-700 w-fit">{{ $entry->source_label }}</span>
                                        @if($entry->is_reversed)
                                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase text-rose-800 w-fit">REVERSED</span>
                                        @elseif($entry->is_reversal)
                                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700 w-fit">REVERSAL</span>
                                        @elseif($entry->is_replacement)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800 w-fit">REPLACEMENT</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="max-w-xs px-3 py-3">
                                    <div class="truncate font-semibold text-slate-900" title="{{ $entry->description }}">
                                        {{ $entry->description ?: ($entry->reference ?: '—') }}
                                    </div>
                                    @if($entry->is_reversed && $entry->replacement_entry)
                                        <a href="{{ route('admin.cashbook.finance.journal.entry-show', $entry->replacement_entry->id) }}" class="font-mono text-[10px] font-bold text-emerald-700 hover:underline block mt-0.5">
                                            ↳ Replaced by {{ $entry->replacement_entry->formatted_reference }}
                                        </a>
                                    @elseif($entry->is_replacement && $entry->original_reversed_entry)
                                        <a href="{{ route('admin.cashbook.finance.journal.entry-show', $entry->original_reversed_entry->id) }}" class="font-mono text-[10px] font-bold text-slate-500 hover:underline block mt-0.5">
                                            ↳ Replaces {{ $entry->original_reversed_entry->formatted_reference }}
                                        </a>
                                    @endif
                                </td>
                                <td class="max-w-[12rem] truncate px-3 py-3 font-semibold text-slate-700" title="{{ $entry->debit_accounts_summary }}">
                                    {{ $entry->debit_accounts_summary }}
                                </td>
                                <td class="max-w-[12rem] truncate px-3 py-3 font-semibold text-slate-700" title="{{ $entry->credit_accounts_summary }}">
                                    {{ $entry->credit_accounts_summary }}
                                </td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-950 {{ $entry->is_reversed ? 'line-through text-slate-400' : '' }}">₹{{ number_format($entry->primary_amount, 2) }}</td>
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
                                <td class="px-3 py-3 text-right">
                                    @if(! $entry->is_reversed && ! $entry->is_reversal)
                                        <button type="button"
                                                onclick="openEditJournalModal({{ json_encode([
                                                    'id' => $entry->id,
                                                    'formatted_reference' => $entry->formatted_reference,
                                                    'source_label' => $entry->source_label,
                                                    'source_type' => $entry->source_type,
                                                    'purchaser_id' => $entry->purchaserCredit?->purchaser_id,
                                                    'amount' => $entry->primary_amount,
                                                    'entry_date' => $entry->entry_date?->format('Y-m-d'),
                                                    'reference' => $entry->reference,
                                                    'description' => $entry->description,
                                                    'reconciliation_status' => $entry->reconciliation_status_label,
                                                    'is_finalized' => $entry->is_finalized,
                                                    'company_account_id' => $entry->statementEntries->first()?->company_account_id ?: $entry->purchaserCredit?->company_account_id,
                                                ]) }})"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-emerald-700">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit
                                        </button>
                                    @else
                                        <span class="text-[10px] font-bold text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-8 text-center text-sm font-bold text-slate-400">No journal transactions found for the selected criteria.</td>
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

    <!-- ADMIN EDIT / CANCEL JOURNAL ENTRY MODAL -->
    <div id="editJournalModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm sm:p-6" aria-modal="true" role="dialog">
        <div class="flex min-h-full items-center justify-center">
            <div class="relative w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 id="modalJournalRef" class="font-mono text-lg font-black text-slate-950">Edit Journal Entry</h3>
                            <span id="modalJournalSource" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-slate-700">—</span>
                        </div>
                        <p class="mt-1 text-xs font-semibold text-slate-500">
                            Immutable double-entry correction: reverses original entry and posts replacement record.
                        </p>
                    </div>
                    <button type="button" onclick="closeEditJournalModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <form id="editJournalForm" method="POST" action="" onsubmit="handleJournalEditSubmit(event)" class="mt-5 space-y-4">
                    @csrf

                    <!-- ERROR NOTIFICATION BANNER -->
                    <div id="modalErrorContainer" class="hidden rounded-2xl border border-rose-200 bg-rose-50 p-3.5 text-xs font-bold text-rose-800">
                        <div class="flex items-center gap-1.5 text-rose-900 font-extrabold">
                            <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600"></i>
                            <span>Unable to save correction:</span>
                        </div>
                        <ul id="modalErrorList" class="mt-1.5 list-disc pl-5 font-semibold text-rose-700 space-y-0.5"></ul>
                    </div>

                    <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-3 text-xs text-amber-900">
                        <div class="flex items-center gap-1.5 font-bold">
                            <i data-lucide="info" class="h-4 w-4 text-amber-700"></i>
                            <span>Accounting Rule:</span>
                        </div>
                        <p class="mt-0.5 text-[11px] leading-relaxed text-amber-800">
                            To <strong>cancel/delete</strong> this entry, set Amount to <strong>0</strong>. All associated statement matches, reconciliation allocations, and purchaser advances will be safely reversed.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Amount (₹) <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" min="0" name="amount" id="modalAmount" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 font-mono text-sm font-extrabold text-slate-900 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Entry Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="entry_date" id="modalEntryDate" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div id="modalPurchaserGroup">
                        <label class="mb-1 block text-xs font-bold text-slate-700">Purchaser</label>
                        <select name="purchaser_id" id="modalPurchaserId" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                            <option value="">-- Keep Current Purchaser --</option>
                            @foreach($purchasers as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->phone ?: 'No phone' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Payment Source</label>
                            <select name="payment_source" id="modalPaymentSource" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                                <option value="Bank">Bank Account</option>
                                <option value="Cash">Cash Vault</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Company Account</label>
                            <select name="company_account_id" id="modalCompanyAccountId" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                                <option value="">-- Select Company Account --</option>
                                @foreach($companyAccounts as $ca)
                                    <option value="{{ $ca->id }}">{{ $ca->name }} ({{ strtoupper($ca->account_type) }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Reference / UTR</label>
                            <input type="text" name="reference" id="modalReference" placeholder="e.g. UTR123456" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Description / Narration</label>
                            <input type="text" name="description" id="modalDescription" placeholder="Narration..." class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Correction Reason <span class="text-rose-500">*</span></label>
                        <textarea name="reason" id="modalReason" rows="2" required placeholder="State why this entry is being edited or cancelled (min 3 chars)..." class="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>

                    <!-- PURCHASER SHORTFALL / DEFICIT CONFIRMATION BOX -->
                    <div id="modalShortfallContainer" class="hidden rounded-2xl border border-rose-300 bg-rose-50/80 p-3.5 text-xs text-rose-950">
                        <div class="flex items-center gap-1.5 font-black text-rose-900">
                            <i data-lucide="alert-triangle" class="h-4 w-4 text-rose-600"></i>
                            <span>Purchaser Advance Deficit Notice:</span>
                        </div>
                        <p id="modalShortfallMessage" class="mt-1 text-[11px] font-medium leading-relaxed text-rose-800">
                            Reducing funding below already utilized purchase bills will leave the purchaser with a negative advance balance. Legitimate purchase bills will remain active.
                        </p>
                        <div class="mt-2.5 flex items-start gap-2 pt-2 border-t border-rose-200">
                            <input type="checkbox" name="confirm_shortfall" id="modalConfirmShortfall" value="1" class="mt-0.5 h-4 w-4 rounded border-rose-300 text-rose-600 focus:ring-rose-500">
                            <label for="modalConfirmShortfall" class="font-bold text-rose-950 text-xs cursor-pointer">
                                I confirm and approve the resulting purchaser deficit/shortfall.
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" onclick="closeEditJournalModal()" class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" id="modalSubmitBtn" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 disabled:opacity-50">
                            <i data-lucide="check" class="h-4 w-4"></i>
                            <span id="modalSubmitBtnText">Apply Correction</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEditJournalModal(data) {
            const modal = document.getElementById('editJournalModal');
            const form = document.getElementById('editJournalForm');
            const errorContainer = document.getElementById('modalErrorContainer');
            const shortfallContainer = document.getElementById('modalShortfallContainer');
            const confirmShortfall = document.getElementById('modalConfirmShortfall');
            if (!modal || !form) return;

            if (errorContainer) {
                errorContainer.classList.add('hidden');
                document.getElementById('modalErrorList').innerHTML = '';
            }
            if (shortfallContainer) {
                shortfallContainer.classList.add('hidden');
            }
            if (confirmShortfall) {
                confirmShortfall.checked = false;
            }

            form.action = "{{ url('admin/cashbook/finance/journal-entry') }}/" + data.id;
            document.getElementById('modalJournalRef').innerText = data.formatted_reference || ('JE #' + data.id);
            document.getElementById('modalJournalSource').innerText = data.source_label || 'Journal Entry';
            document.getElementById('modalAmount').value = parseFloat(data.amount || 0).toFixed(2);
            document.getElementById('modalEntryDate').value = data.entry_date || '';
            document.getElementById('modalReference').value = data.reference || '';
            document.getElementById('modalDescription').value = data.description || '';
            document.getElementById('modalReason').value = '';

            if (data.purchaser_id) {
                document.getElementById('modalPurchaserId').value = data.purchaser_id;
            } else {
                document.getElementById('modalPurchaserId').value = '';
            }

            if (data.company_account_id) {
                document.getElementById('modalCompanyAccountId').value = data.company_account_id;
            } else {
                document.getElementById('modalCompanyAccountId').value = '';
            }

            modal.classList.remove('hidden');
            if (window.lucide) { lucide.createIcons(); }
        }

        function closeEditJournalModal() {
            const modal = document.getElementById('editJournalModal');
            if (modal) { modal.classList.add('hidden'); }
        }

        async function handleJournalEditSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const submitBtn = document.getElementById('modalSubmitBtn');
            const submitText = document.getElementById('modalSubmitBtnText');
            const errorContainer = document.getElementById('modalErrorContainer');
            const errorList = document.getElementById('modalErrorList');
            const shortfallContainer = document.getElementById('modalShortfallContainer');
            const shortfallMessage = document.getElementById('modalShortfallMessage');

            errorContainer.classList.add('hidden');
            errorList.innerHTML = '';
            submitBtn.disabled = true;
            submitText.innerText = 'Applying...';

            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    submitBtn.disabled = false;
                    submitText.innerText = 'Apply Correction';
                    errorContainer.classList.remove('hidden');

                    let isShortfallError = false;

                    if (data.errors) {
                        for (const [field, msgs] of Object.entries(data.errors)) {
                            msgs.forEach(msg => {
                                const li = document.createElement('li');
                                li.textContent = msg;
                                errorList.appendChild(li);
                                if (msg.toLowerCase().includes('deficit') || msg.toLowerCase().includes('shortfall')) {
                                    isShortfallError = true;
                                    if (shortfallMessage) { shortfallMessage.textContent = msg; }
                                }
                            });
                        }
                    } else if (data.message) {
                        const li = document.createElement('li');
                        li.textContent = data.message;
                        errorList.appendChild(li);
                        if (data.message.toLowerCase().includes('deficit') || data.message.toLowerCase().includes('shortfall')) {
                            isShortfallError = true;
                            if (shortfallMessage) { shortfallMessage.textContent = data.message; }
                        }
                    } else {
                        const li = document.createElement('li');
                        li.textContent = 'An unexpected error occurred while saving.';
                        errorList.appendChild(li);
                    }

                    if (isShortfallError && shortfallContainer) {
                        shortfallContainer.classList.remove('hidden');
                    }

                    if (window.lucide) { lucide.createIcons(); }
                    return;
                }

                // Success
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    window.location.reload();
                }
            } catch (err) {
                submitBtn.disabled = false;
                submitText.innerText = 'Apply Correction';
                errorContainer.classList.remove('hidden');
                const li = document.createElement('li');
                li.textContent = 'Network or server error. Please try again.';
                errorList.appendChild(li);
                if (window.lucide) { lucide.createIcons(); }
            }
        }
    </script>
@endsection
