@extends('admin.cashbook.layouts.app')

@section('title', $journalEntry->formatted_reference . ' — Journal Entry Details')

@section('header_title')
    <i data-lucide="file-spreadsheet" class="h-5 w-5 text-emerald-600"></i> {{ $journalEntry->formatted_reference }}
@endsection

@section('header_subtitle')
    Canonical double-entry accounting record & operational reconciliation details.
@endsection

@section('header_actions')
    <a href="{{ route('admin.cashbook.finance.journal') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>
        <span>Back to Journal</span>
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-6">
        <!-- HEADER METADATA CARD -->
        <section class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xl font-extrabold text-slate-950">{{ $journalEntry->formatted_reference }}</span>
                        <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-black uppercase tracking-wider text-white">
                            {{ $journalEntry->source_label }}
                        </span>
                        @if($journalEntry->is_finalized)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-900">
                                <i data-lucide="lock" class="h-3.5 w-3.5"></i> FINALIZED
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">
                                {{ $journalEntry->reconciliation_status_label }}
                            </span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm font-semibold text-slate-600">{{ $journalEntry->description ?: 'No description provided.' }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-xs font-bold">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2">
                        <span class="block text-[10px] font-black uppercase text-slate-400">Entry Date</span>
                        <span class="font-mono text-slate-800">{{ $journalEntry->entry_date?->format('d M Y') }}</span>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2">
                        <span class="block text-[10px] font-black uppercase text-slate-400">Reference</span>
                        <span class="font-mono text-slate-800">{{ $journalEntry->reference ?: '—' }}</span>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2">
                        <span class="block text-[10px] font-black uppercase text-slate-400">Created By</span>
                        <span class="text-slate-800">{{ $journalEntry->createdBy?->name ?? 'System' }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- DOUBLE ENTRY LEDGER LINES -->
        <section class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm sm:p-6">
            <div class="mb-4 flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Double-Entry Ledger Lines</h2>
                    <p class="text-xs font-semibold text-slate-500">Immutable debit and credit transactions posted to chart of accounts.</p>
                </div>
                @if($journalEntry->is_balanced)
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-800 border border-emerald-200">
                        <i data-lucide="check-circle-2" class="h-4 w-4 text-emerald-600"></i> Perfectly Balanced
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-3 py-1 text-xs font-black text-rose-800 border border-rose-200">
                        <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600"></i> Unbalanced
                    </span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Account Code</th>
                            <th class="px-4 py-3">Account Name</th>
                            <th class="px-4 py-3">Account Type</th>
                            <th class="px-4 py-3 text-right">Debit (₹)</th>
                            <th class="px-4 py-3 text-right">Credit (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($journalEntry->transactions as $line)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3.5 font-mono font-extrabold text-slate-900">{{ $line->account?->code ?? '—' }}</td>
                                <td class="px-4 py-3.5 font-bold text-slate-800">{{ $line->account?->name ?? 'Unknown Account' }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-600">
                                        {{ $line->account?->type ?? 'Asset' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold {{ $line->type === 'debit' ? 'text-slate-950' : 'text-slate-300' }}">
                                    {{ $line->type === 'debit' ? '₹'.number_format($line->amount, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold {{ $line->type === 'credit' ? 'text-slate-950' : 'text-slate-300' }}">
                                    {{ $line->type === 'credit' ? '₹'.number_format($line->amount, 2) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-slate-300 bg-slate-50/50 font-bold">
                            <td colspan="3" class="px-4 py-3 text-right text-xs font-black uppercase text-slate-600">Total:</td>
                            <td class="px-4 py-3 text-right font-mono text-sm font-extrabold text-slate-950">₹{{ number_format($journalEntry->total_debit, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm font-extrabold text-slate-950">₹{{ number_format($journalEntry->total_credit, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- OPERATIONAL CASHBOOK RECONCILIATION CARD -->
        <section class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm sm:p-6">
            <div class="mb-4 flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Operational Bank & Statement Reconciliation</h2>
                    <p class="text-xs font-semibold text-slate-500">Bank statement records and cashbook match verification.</p>
                </div>
                @if($journalEntry->is_finalized)
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-900">
                        LOCKED & FINALIZED
                    </span>
                @else
                    <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800">
                        <i data-lucide="check-check" class="h-3.5 w-3.5"></i> Reconcile in Cashbook
                    </a>
                @endif
            </div>

            @if($journalEntry->statementEntries->isNotEmpty())
                <div class="space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Linked Bank Statement Entries</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                                    <th class="px-3 py-2.5">Date</th>
                                    <th class="px-3 py-2.5">Account</th>
                                    <th class="px-3 py-2.5">Direction</th>
                                    <th class="px-3 py-2.5">Reference / Narration</th>
                                    <th class="px-3 py-2.5 text-right">Statement Amount</th>
                                    <th class="px-3 py-2.5">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($journalEntry->statementEntries as $stmt)
                                    <tr>
                                        <td class="px-3 py-2.5 font-mono text-slate-700">{{ $stmt->transaction_date?->format('Y-m-d') }}</td>
                                        <td class="px-3 py-2.5 font-bold text-slate-900">{{ $stmt->companyAccount?->name ?? 'Bank Account' }}</td>
                                        <td class="px-3 py-2.5">
                                            <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase {{ $stmt->direction === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                                {{ strtoupper($stmt->direction) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 font-semibold text-slate-700">{{ $stmt->reference ?: ($stmt->narration ?: '—') }}</td>
                                        <td class="px-3 py-2.5 text-right font-mono font-bold text-slate-950">₹{{ number_format($stmt->amount, 2) }}</td>
                                        <td class="px-3 py-2.5">
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-700">
                                                {{ $stmt->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center text-xs font-bold text-slate-400">
                    No bank statement entries are currently linked to this canonical Journal Entry.
                </div>
            @endif
        </section>
    </div>
@endsection
