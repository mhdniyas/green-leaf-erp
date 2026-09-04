@extends('admin.cashbook.layouts.app')

@section('title', ucfirst($entry->type) . ' Details — Green Leaf')

@section('content')
<div class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
    <!-- Breadcrumb & Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.cashbook.finance.income-expense', ['type' => $entry->type]) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-900 transition bg-white px-3 py-1.5 rounded-xl border border-slate-200 shadow-xs">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Back to Income &amp; Expense</span>
        </a>

        <div class="flex items-center gap-2">
            @if($entry->status === \App\Models\CompanyAccountingEntry::StatusReversed)
                <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-extrabold rounded-full bg-rose-50 text-rose-700 border border-rose-200">
                    <i data-lucide="rotate-ccw" class="w-3 h-3"></i> Reversed / Cancelled
                </span>
            @else
                <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-extrabold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Active
                </span>
            @endif
        </div>
    </div>

    <!-- Main Card -->
    <div class="white-card rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-2xl flex items-center justify-center {{ $entry->type === 'income' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                    <i data-lucide="{{ $entry->type === 'income' ? 'arrow-down-left' : 'arrow-up-right' }}" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-950">Company {{ ucfirst($entry->type) }} Details</h1>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">Reference: {{ $entry->reference ?: $entry->public_uuid }}</p>
                </div>
            </div>

            <div class="text-right">
                <span class="text-xs font-black uppercase tracking-wider text-slate-400">Total Amount</span>
                <p class="text-2xl sm:text-3xl font-black font-mono {{ $entry->type === 'income' ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $entry->type === 'income' ? '+' : '-' }}₹{{ number_format((float) $entry->amount, 2) }}
                </p>
            </div>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-400">Category</dt>
                <dd class="text-sm font-extrabold text-slate-900">{{ $entry->category?->name ?? '—' }}</dd>
                @if($entry->category?->account)
                    <p class="text-[11px] text-slate-500 font-mono">{{ $entry->category->account->code }} · {{ $entry->category->account->name }}</p>
                @endif
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-400">Company Account</dt>
                <dd class="text-sm font-extrabold text-slate-900">{{ $entry->companyAccount?->name ?? '—' }}</dd>
                @if($entry->companyAccount)
                    <p class="text-[11px] text-slate-500 uppercase font-mono font-bold">{{ $entry->companyAccount->account_type }} Account</p>
                @endif
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-400">Business Date</dt>
                <dd class="text-sm font-extrabold text-slate-900 font-mono">{{ $entry->business_date->format('d M Y') }}</dd>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-400">Payment Mode</dt>
                <dd class="text-sm font-extrabold text-slate-900 uppercase font-mono">{{ $entry->payment_mode }}</dd>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-400">Cashbook Statement Status</dt>
                <dd class="text-sm font-extrabold {{ $entry->cashbookMovement?->is_finalized ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $entry->cashbookMovement?->is_finalized ? 'Finalized / Reconciled' : 'Pending Bank Statement Reconciliation' }}
                </dd>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-400">Canonical Journal Entry</dt>
                <dd class="text-sm font-mono font-bold text-slate-900">{{ $entry->journalEntry?->formatted_reference ?? ($entry->journalEntry?->reference ?? '—') }}</dd>
            </div>

            <div class="sm:col-span-2 p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-400">Description / Narration</dt>
                <dd class="text-xs font-medium text-slate-700 leading-relaxed">{{ $entry->description ?: 'No additional notes recorded.' }}</dd>
            </div>

            @if($entry->status === \App\Models\CompanyAccountingEntry::StatusReversed)
                <div class="sm:col-span-2 p-4 rounded-2xl bg-rose-50 border border-rose-200 space-y-1">
                    <dt class="text-[10px] font-black uppercase tracking-wider text-rose-700">Reversal Information</dt>
                    <dd class="text-xs font-semibold text-rose-900">
                        Reversed at {{ $entry->reversed_at?->format('d M Y H:i:s') }}
                        @if($entry->reversedBy)
                            by {{ $entry->reversedBy->name }}
                        @endif
                    </dd>
                    @if($entry->reversal_note)
                        <dd class="text-xs font-medium text-rose-800">Reason: {{ $entry->reversal_note }}</dd>
                    @endif
                </div>
            @endif
        </dl>
    </div>
</div>
@endsection
