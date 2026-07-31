@php
    $isPendingApproval = $entry->status === 'submitted';
    $statusLabel = match ($entry->status) {
        'submitted' => 'Ready for review',
        'recheck_required' => 'Needs recheck',
        'approved' => 'Approved',
        default => str($entry->status)->replace('_', ' ')->title()->toString(),
    };
@endphp

<form method="POST" action="{{ route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]) }}" class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
    @csrf
    @method('PATCH')

    <div class="border-b border-slate-200 bg-slate-950 px-5 py-5 text-white sm:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Approval Check</p>
                <h3 class="mt-2 text-2xl font-black tracking-tight">{{ $entry->business_date->format('d M Y') }}</h3>
                <p class="mt-2 text-sm font-semibold text-slate-300">
                    {{ $isPendingApproval
                        ? 'Check each item, then approve all or send back what needs fixing.'
                        : 'This day is read-only in the approval queue.' }}
                </p>
                <p class="mt-2 text-xs font-semibold text-slate-400">
                    Submitted by {{ $entry->submittedBy?->name ?? 'Admin entry' }}
                    @if ($entry->submitted_at)
                        · {{ $entry->submitted_at->format('d M Y h:i A') }}
                    @endif
                </p>
            </div>
            <span class="inline-flex h-fit rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-white">
                {{ $statusLabel }} · {{ $entry->lines->count() }} item{{ $entry->lines->count() === 1 ? '' : 's' }}
            </span>
        </div>
    </div>

    <div class="space-y-4 p-5 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Opening</p>
                <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format($receiptSummary['opening_balance'], 2) }}</p>
            </div>
            <div class="rounded-[1rem] border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Cash In</p>
                <p class="mt-1 text-lg font-black text-emerald-900">Rs. {{ number_format($receiptSummary['cash_credit'], 2) }}</p>
            </div>
            <div class="rounded-[1rem] border border-rose-200 bg-rose-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Cash Out</p>
                <p class="mt-1 text-lg font-black text-rose-900">Rs. {{ number_format($receiptSummary['cash_debit'], 2) }}</p>
            </div>
            <div class="rounded-[1rem] border {{ ($receiptSummary['entered_closing'] ?? 0) < 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }} px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] {{ ($receiptSummary['entered_closing'] ?? 0) < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Closing</p>
                <p class="mt-1 text-lg font-black {{ ($receiptSummary['entered_closing'] ?? 0) < 0 ? 'text-rose-900' : 'text-emerald-900' }}">
                    {{ $receiptSummary['entered_closing'] === null ? 'None' : 'Rs. '.number_format($receiptSummary['entered_closing'], 2) }}
                </p>
            </div>
        </div>

        <div class="space-y-2">
            @foreach ($entry->lines as $line)
                <div class="rounded-[1.15rem] border border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">
                                    {{ $line->type === 'income' ? 'Income' : 'Expense' }}
                                    @if ($line->cash_effect)
                                        · cash
                                    @endif
                                </span>
                                <span class="text-sm font-black text-slate-950">{{ $line->category?->name ?? 'Category removed' }}</span>
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $line->reviewStatusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($line->reviewStatusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">
                                    {{ $line->reviewStatusLabel() }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm font-semibold text-slate-600">{{ $line->description ?: 'No note added' }}</p>
                        </div>
                        <p class="shrink-0 text-base font-black text-slate-950">Rs. {{ number_format((float) $line->amount, 2) }}</p>
                    </div>

                    @if ($isPendingApproval && $line->review_status !== 'approved')
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="line-review-open inline-flex h-10 items-center rounded-xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-[0.12em] text-white transition hover:bg-emerald-500"
                                data-line-id="{{ $line->id }}"
                                data-line-action="approve"
                                data-line-title="Approve This Item"
                                data-line-label="{{ $line->category?->name ?? 'Category removed' }}"
                                data-line-description="{{ $line->description ?: 'No note added' }}"
                            >
                                Approve
                            </button>
                            <button
                                type="button"
                                class="line-review-open inline-flex h-10 items-center rounded-xl bg-red-600 px-4 text-xs font-black uppercase tracking-[0.12em] text-white transition hover:bg-red-500"
                                data-line-id="{{ $line->id }}"
                                data-line-action="recheck"
                                data-line-title="Send Item For Recheck"
                                data-line-label="{{ $line->category?->name ?? 'Category removed' }}"
                                data-line-description="{{ $line->description ?: 'No note added' }}"
                            >
                                Send back
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-[1rem] border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Income Total</p>
                <p class="mt-1 text-sm font-black text-emerald-900">Rs. {{ number_format($entryIncomeTotal, 2) }}</p>
            </div>
            <div class="rounded-[1rem] border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Expense Total</p>
                <p class="mt-1 text-sm font-black text-amber-900">Rs. {{ number_format($entryExpenseTotal, 2) }}</p>
            </div>
        </div>

        @if ($entry->reviewed_at || $entry->reviewedBy)
            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">
                {{ $statusLabel }}{{ $entry->reviewedBy ? ' by '.$entry->reviewedBy->name : '' }}{{ $entry->reviewed_at ? ' on '.$entry->reviewed_at->format('d M Y h:i A') : '' }}.
            </div>
        @endif

        @if ($isPendingApproval)
            <textarea name="admin_note" rows="3" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none" placeholder="Add a note when approval or recheck needs context.">{{ old('admin_note', $entry->admin_note) }}</textarea>
            <div class="flex flex-col gap-3 sm:flex-row">
                <button type="button" id="approve-entry-open-modal" class="inline-flex h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-500">
                    Approve all
                </button>
                <button type="submit" name="decision" value="recheck" class="inline-flex h-11 items-center justify-center rounded-xl bg-red-600 px-5 text-sm font-semibold text-white transition hover:bg-red-500">
                    Send back for recheck
                </button>
                <button type="button" id="review-details-open-modal" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    View request
                </button>
            </div>
        @endif
    </div>
</form>
