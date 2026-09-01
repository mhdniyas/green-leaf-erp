@extends('admin.cashbook.layouts.app')

@section('title', $journalEntry->formatted_reference . ' — Journal Entry Details')

@section('header_title')
    <i data-lucide="file-spreadsheet" class="h-5 w-5 text-emerald-600"></i> {{ $journalEntry->formatted_reference }}
@endsection

@section('header_subtitle')
    Canonical double-entry accounting record & operational reconciliation details.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.finance.journal') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            <span>Back to Journal</span>
        </a>
        @if(! $journalEntry->is_reversed && ! $journalEntry->is_reversal)
            <button type="button"
                    onclick="openEditJournalModal({{ json_encode([
                        'id' => $journalEntry->id,
                        'formatted_reference' => $journalEntry->formatted_reference,
                        'source_label' => $journalEntry->source_label,
                        'source_type' => $journalEntry->source_type,
                        'purchaser_id' => $journalEntry->purchaserCredit?->purchaser_id,
                        'amount' => $journalEntry->primary_amount,
                        'entry_date' => $journalEntry->entry_date?->format('Y-m-d'),
                        'reference' => $journalEntry->reference,
                        'description' => $journalEntry->description,
                        'reconciliation_status' => $journalEntry->reconciliation_status_label,
                        'is_finalized' => $journalEntry->is_finalized,
                        'company_account_id' => $journalEntry->statementEntries->first()?->company_account_id ?: $journalEntry->purchaserCredit?->company_account_id,
                    ]) }})"
                    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                <i data-lucide="pencil" class="h-4 w-4"></i>
                <span>Edit Entry</span>
            </button>
        @endif
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-6">

        @if($journalEntry->is_reversed)
            <div class="rounded-2xl border border-rose-300 bg-rose-50 p-4 text-xs text-rose-900 shadow-sm">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-triangle" class="h-5 w-5 text-rose-600 shrink-0 mt-0.5"></i>
                    <div class="space-y-1">
                        <div class="font-extrabold text-sm text-rose-950">This Journal Entry has been Reversed / Cancelled</div>
                        <p class="font-medium text-rose-800 leading-relaxed">
                            Canonical double-entry accounting records remain immutable. Its financial effect has been reversed.
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-3 pt-1 text-xs">
                            @if($journalEntry->reversal_entry)
                                <a href="{{ route('admin.cashbook.finance.journal.entry-show', $journalEntry->reversal_entry->id) }}" class="inline-flex items-center gap-1 font-mono font-bold text-rose-700 bg-white border border-rose-200 rounded-lg px-2.5 py-1 hover:bg-rose-100">
                                    <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i> Reversal Record: {{ $journalEntry->reversal_entry->formatted_reference }}
                                </a>
                            @endif
                            @if($journalEntry->replacement_entry)
                                <a href="{{ route('admin.cashbook.finance.journal.entry-show', $journalEntry->replacement_entry->id) }}" class="inline-flex items-center gap-1 font-mono font-bold text-emerald-800 bg-emerald-100/80 border border-emerald-300 rounded-lg px-2.5 py-1 hover:bg-emerald-200">
                                    <i data-lucide="check-circle" class="h-3.5 w-3.5 text-emerald-700"></i> Corrected Replacement: {{ $journalEntry->replacement_entry->formatted_reference }} (₹{{ number_format($journalEntry->replacement_entry->primary_amount, 2) }})
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @elseif($journalEntry->is_reversal)
            <div class="rounded-2xl border border-slate-300 bg-slate-100 p-4 text-xs text-slate-800 shadow-sm">
                <div class="flex items-start gap-3">
                    <i data-lucide="rotate-ccw" class="h-5 w-5 text-slate-600 shrink-0 mt-0.5"></i>
                    <div>
                        <div class="font-extrabold text-sm text-slate-950">Double-Entry Reversal Record</div>
                        <p class="font-medium text-slate-600 mt-0.5">
                            This transaction was generated to cleanly invert debit and credit lines of an earlier entry.
                        </p>
                        @if($journalEntry->original_reversed_entry)
                            <div class="mt-2">
                                <a href="{{ route('admin.cashbook.finance.journal.entry-show', $journalEntry->original_reversed_entry->id) }}" class="inline-flex items-center gap-1 font-mono font-bold text-slate-900 bg-white border border-slate-300 rounded-lg px-2.5 py-1 hover:bg-slate-50">
                                    <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Original Entry: {{ $journalEntry->original_reversed_entry->formatted_reference }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @elseif($journalEntry->is_replacement)
            <div class="rounded-2xl border border-emerald-300 bg-emerald-50/80 p-4 text-xs text-emerald-900 shadow-sm">
                <div class="flex items-start gap-3">
                    <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600 shrink-0 mt-0.5"></i>
                    <div>
                        <div class="font-extrabold text-sm text-emerald-950">Active Replacement Entry</div>
                        <p class="font-medium text-emerald-800 mt-0.5">
                            This entry was posted as the corrected replacement record following an admin modification.
                        </p>
                        @if($journalEntry->original_reversed_entry)
                            <div class="mt-2">
                                <a href="{{ route('admin.cashbook.finance.journal.entry-show', $journalEntry->original_reversed_entry->id) }}" class="inline-flex items-center gap-1 font-mono font-bold text-slate-700 bg-white border border-emerald-200 rounded-lg px-2.5 py-1 hover:bg-emerald-100">
                                    <i data-lucide="history" class="h-3.5 w-3.5 text-slate-500"></i> Original Entry: {{ $journalEntry->original_reversed_entry->formatted_reference }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- HEADER METADATA CARD -->
        <section class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xl font-extrabold text-slate-950">{{ $journalEntry->formatted_reference }}</span>
                        <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-black uppercase tracking-wider text-white">
                            {{ $journalEntry->source_label }}
                        </span>
                        @if($journalEntry->is_reversed)
                            <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-black uppercase text-rose-800">
                                REVERSED
                            </span>
                        @elseif($journalEntry->is_reversal)
                            <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-black uppercase text-slate-800">
                                REVERSAL
                            </span>
                        @elseif($journalEntry->is_replacement)
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black uppercase text-emerald-800">
                                REPLACEMENT
                            </span>
                        @elseif($journalEntry->is_finalized)
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
                        @foreach($journalEntry->transactions as $txn)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono font-bold text-slate-700">{{ $txn->account?->code ?? '—' }}</td>
                                <td class="px-4 py-3 font-bold text-slate-900">{{ $txn->account?->name ?? 'Unknown Account' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">
                                        {{ $txn->account?->type ?? 'Asset' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-950">
                                    {{ $txn->type === 'debit' ? '₹' . number_format($txn->amount, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-950">
                                    {{ $txn->type === 'credit' ? '₹' . number_format($txn->amount, 2) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-slate-300 bg-slate-50 font-mono font-extrabold text-slate-950">
                            <td colspan="3" class="px-4 py-3 uppercase tracking-wider text-[11px]">Total</td>
                            <td class="px-4 py-3 text-right">₹{{ number_format($journalEntry->total_debit, 2) }}</td>
                            <td class="px-4 py-3 text-right">₹{{ number_format($journalEntry->total_credit, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- LINKED BANK / CASH STATEMENT ENTRIES -->
        <section class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm sm:p-6">
            <div class="mb-4 flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Linked Statement Movements</h2>
                    <p class="text-xs font-semibold text-slate-500">Bank and cash account movements matched or reconciled against this entry.</p>
                </div>
            </div>

            @if($journalEntry->statementEntries->isNotEmpty())
                <div class="space-y-3">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                                    <th class="px-3 py-2.5">Date</th>
                                    <th class="px-3 py-2.5">Account</th>
                                    <th class="px-3 py-2.5">Direction</th>
                                    <th class="px-3 py-2.5">Reference / Narration</th>
                                    <th class="px-3 py-2.5 text-right">Amount</th>
                                    <th class="px-3 py-2.5">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($journalEntry->statementEntries as $stmt)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-3 py-2.5 font-mono font-bold text-slate-700">{{ $stmt->transaction_date?->format('Y-m-d') }}</td>
                                        <td class="px-3 py-2.5 font-bold text-slate-800">{{ $stmt->companyAccount?->name ?? 'Company Account' }}</td>
                                        <td class="px-3 py-2.5">
                                            <span class="rounded-full {{ $stmt->direction === 'out' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }} px-2 py-0.5 text-[9px] font-black uppercase">
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
