@php
    $isPendingApproval = $entry->status === 'submitted';
@endphp

<form method="POST" action="{{ route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]) }}" class="rounded-3xl border {{ $isPendingApproval ? 'border-amber-200 bg-amber-50/70' : ($entry->status === 'approved' ? 'border-emerald-200 bg-emerald-50/70' : 'border-red-200 bg-red-50/70') }} p-4 shadow-[0_12px_34px_rgba(15,23,42,0.05)]">
    @csrf
    @method('PATCH')
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase text-amber-700">Admin Approval</p>
            <h3 class="mt-1 text-xl font-semibold text-slate-950">Review submitted shop entry</h3>
            <p class="mt-1 text-sm font-medium text-slate-600">
                {{ $isPendingApproval ? 'Approve all at once, or review each item separately when some lines need recheck.' : 'This entry is now read-only in this workflow tab.' }}
            </p>
        </div>
        <p class="w-fit rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-semibold uppercase text-amber-700">
            {{ $entry->lines->count() }} item{{ $entry->lines->count() === 1 ? '' : 's' }}
        </p>
    </div>

    <div class="mt-4 rounded-2xl border border-amber-100 bg-white p-3">
        <div class="mb-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                <p class="text-[10px] font-semibold uppercase text-slate-500">Opening</p>
                <p class="mt-1 text-sm font-semibold text-slate-950">Rs. {{ number_format($receiptSummary['opening_balance'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-3">
                <p class="text-[10px] font-semibold uppercase text-emerald-700">Cash Credit</p>
                <p class="mt-1 text-sm font-semibold text-emerald-900">Rs. {{ number_format($receiptSummary['cash_credit'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-cyan-200 bg-cyan-50 px-3 py-3">
                <p class="text-[10px] font-semibold uppercase text-cyan-700">Non-Cash</p>
                <p class="mt-1 text-sm font-semibold text-cyan-900">Rs. {{ number_format($receiptSummary['non_cash_income'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-3">
                <p class="text-[10px] font-semibold uppercase text-rose-700">Cash Debit</p>
                <p class="mt-1 text-sm font-semibold text-rose-900">Rs. {{ number_format($receiptSummary['cash_debit'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                <p class="text-[10px] font-semibold uppercase text-slate-500">Expected</p>
                <p class="mt-1 text-sm font-semibold text-slate-950">Rs. {{ number_format($receiptSummary['expected_closing'], 2) }}</p>
            </div>
            <div class="rounded-2xl border {{ ($receiptSummary['entered_closing'] ?? 0) < 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }} px-3 py-3">
                <p class="text-[10px] font-semibold uppercase {{ ($receiptSummary['entered_closing'] ?? 0) < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Closing</p>
                <p class="mt-1 text-sm font-semibold {{ ($receiptSummary['entered_closing'] ?? 0) < 0 ? 'text-rose-900' : 'text-emerald-900' }}">
                    {{ $receiptSummary['entered_closing'] === null ? 'None' : 'Rs. '.number_format($receiptSummary['entered_closing'], 2) }}
                </p>
            </div>
        </div>
        <div class="space-y-2">
            @foreach ($entry->lines as $line)
                @php
                    $lineLabel = $line->type === 'income'
                        ? ((bool) $line->cash_effect ? 'Cash Credit' : 'Non-Cash Income')
                        : ((bool) $line->cash_effect ? 'Cash Debit' : 'Non-Cash Debit');
                    $lineTone = $line->type === 'income' && (bool) $line->cash_effect
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : ($line->type === 'income'
                            ? 'border-cyan-200 bg-cyan-50 text-cyan-700'
                            : 'border-amber-200 bg-amber-50 text-amber-700');
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase {{ $lineTone }}">
                                    {{ $lineLabel }}
                                </span>
                                <span class="text-sm font-semibold text-slate-950">{{ $line->category?->name ?? 'Category removed' }}</span>
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase {{ $line->reviewStatusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($line->reviewStatusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">
                                    {{ $line->reviewStatusLabel() }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm font-medium text-slate-600">{{ $line->description ?: 'No note added' }}</p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold text-slate-950">Rs. {{ number_format((float) $line->amount, 2) }}</p>
                    </div>
                    @if ($isPendingApproval && $line->review_status !== 'approved')
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="line-review-open inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white transition hover:bg-emerald-500"
                                data-line-id="{{ $line->id }}"
                                data-line-action="approve"
                                data-line-title="Approve This Item"
                                data-line-label="{{ $line->category?->name ?? 'Category removed' }}"
                                data-line-description="{{ $line->description ?: 'No note added' }}"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.7">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="line-review-open inline-flex h-10 w-10 items-center justify-center rounded-xl bg-red-600 text-white transition hover:bg-red-500"
                                data-line-id="{{ $line->id }}"
                                data-line-action="recheck"
                                data-line-title="Send Item For Recheck"
                                data-line-label="{{ $line->category?->name ?? 'Category removed' }}"
                                data-line-description="{{ $line->description ?: 'No note added' }}"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.7">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                            <span class="text-xs font-medium text-slate-500">Tick approves this line. X sends only this item for recheck.</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-3">
                <p class="text-[10px] font-semibold uppercase text-emerald-700">Income Total</p>
                <p class="mt-1 text-sm font-semibold text-emerald-900">Rs. {{ number_format($entryIncomeTotal, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-3">
                <p class="text-[10px] font-semibold uppercase text-amber-700">Expense Total</p>
                <p class="mt-1 text-sm font-semibold text-amber-900">Rs. {{ number_format($entryExpenseTotal, 2) }}</p>
            </div>
        </div>
    </div>

    @if ($entry->reviewed_at || $entry->reviewedBy)
        <div class="mt-3 rounded-2xl border border-white/80 bg-white px-4 py-3 text-sm font-semibold text-slate-600">
            {{ $entry->statusLabel() }}{{ $entry->reviewedBy ? ' by '.$entry->reviewedBy->name : '' }}{{ $entry->reviewed_at ? ' on '.$entry->reviewed_at->format('d M Y h:i A') : '' }}.
        </div>
    @endif

    @if ($isPendingApproval)
        <textarea name="admin_note" rows="3" class="mt-3 w-full rounded-2xl border border-amber-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 focus:border-amber-400 focus:outline-none" placeholder="Add a note when approval or recheck needs context.">{{ old('admin_note', $entry->admin_note) }}</textarea>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <button type="button" id="approve-entry-open-modal" class="inline-flex h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-500">
                Approve All Items
            </button>
            <button type="submit" name="decision" value="recheck" class="inline-flex h-11 items-center justify-center rounded-xl bg-red-600 px-5 text-sm font-semibold text-white transition hover:bg-red-500">
                Send Recheck
            </button>
            <button type="button" id="review-details-open-modal" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                View Request
            </button>
        </div>
    @endif
</form>
