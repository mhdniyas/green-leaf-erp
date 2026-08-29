@extends('admin.cashbook.layouts.app')

@section('title', $account->name . ' — Account Statement')

@section('header_title')
    <i data-lucide="list-checks" class="h-5 w-5 text-emerald-600"></i> {{ $account->name }} Statement
@endsection

@section('header_subtitle')
    Statement activity and verified transactions for {{ $account->name }}.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.bank-accounts.show', $account) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            <span>Account Details</span>
        </a>
    </div>
@endsection

@section('content')
@php
    $moneyIn = (float) ($statementSummary?->money_in ?? 0);
    $moneyOut = (float) ($statementSummary?->money_out ?? 0);
    $matchedTotal = (float) ($statementSummary?->matched_total ?? 0);
    $currentTab = $selectedTab ?? 'all';
@endphp

<div class="mx-auto max-w-[96rem] space-y-6">

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 font-bold">&times;</button>
        </div>
    @endif

    <!-- Top Account Header Card -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900">{{ $account->name }}</h1>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">
                        {{ $account->account_type }}
                    </span>
                </div>
                <p class="text-xs font-bold text-slate-500 mt-1">
                    {{ $monthStart->format('d M Y') }} to {{ $monthEnd->format('d M Y') }}
                </p>
            </div>

            <!-- 4 Metrics -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                <div class="p-3 rounded-2xl bg-emerald-50/70 border border-emerald-100">
                    <span class="block text-[9px] font-black uppercase text-emerald-700">Verified Balance</span>
                    <strong class="font-mono text-sm font-black text-emerald-950 mt-1 block">₹{{ number_format($account->current_balance, 2) }}</strong>
                </div>
                <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100">
                    <span class="block text-[9px] font-black uppercase text-slate-500">Money In</span>
                    <strong class="font-mono text-sm font-black text-slate-900 mt-1 block">₹{{ number_format($moneyIn, 2) }}</strong>
                </div>
                <div class="p-3 rounded-2xl bg-rose-50/70 border border-rose-100">
                    <span class="block text-[9px] font-black uppercase text-rose-700">Money Out</span>
                    <strong class="font-mono text-sm font-black text-rose-950 mt-1 block">₹{{ number_format($moneyOut, 2) }}</strong>
                </div>
                <div class="p-3 rounded-2xl bg-amber-50/70 border border-amber-100">
                    <span class="block text-[9px] font-black uppercase text-amber-700">Flags</span>
                    <strong class="font-mono text-sm font-black text-amber-950 mt-1 block">{{ $duplicateFlagCount }}</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Statement Section with 4 Simplified Tabs -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs space-y-5">
        
        <!-- Controls: Tabs & Month Picker -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            
            <!-- 4 Simplified Tabs -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <a href="{{ route('admin.cashbook.bank-accounts.statement', ['account' => $account, 'month' => $statementMonth, 'tab' => 'all']) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-extrabold transition {{ $currentTab === 'all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    All
                </a>
                <a href="{{ route('admin.cashbook.bank-accounts.statement', ['account' => $account, 'month' => $statementMonth, 'tab' => 'needs_verification']) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-extrabold transition {{ $currentTab === 'needs_verification' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100' }}">
                    Needs Verification
                </a>
                <a href="{{ route('admin.cashbook.bank-accounts.statement', ['account' => $account, 'month' => $statementMonth, 'tab' => 'verified']) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-extrabold transition {{ $currentTab === 'verified' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100' }}">
                    Verified
                </a>
                <a href="{{ route('admin.cashbook.bank-accounts.statement', ['account' => $account, 'month' => $statementMonth, 'tab' => 'needs_attention']) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-extrabold transition {{ $currentTab === 'needs_attention' ? 'bg-rose-700 text-white shadow-xs' : 'bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100' }}">
                    Needs Attention
                </a>
            </div>

            <!-- Month Filter -->
            <form method="GET" action="{{ route('admin.cashbook.bank-accounts.statement', $account) }}" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ $currentTab }}">
                <input type="month" name="month" value="{{ $statementMonth }}" class="min-h-9 rounded-xl border border-slate-300 bg-white px-3 py-1 text-xs font-bold text-slate-800">
                <button type="submit" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white hover:bg-slate-800 transition">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    <span>Show</span>
                </button>
            </form>
        </div>

        <!-- Statement Rows List -->
        <div class="space-y-2.5">
            @forelse($statementEntries as $entry)
                @php
                    $isTx = $entry->source_type === 'App\Models\Cashbook\ShopLedgerTransaction' && $entry->source_id;
                    $shopName = $entry->sourceRecord?->shop?->name ?? 'Company Entry';
                    $methodName = $entry->sourceRecord?->entryType?->name ?? ($entry->direction === 'in' ? 'Money In' : 'Money Out');
                    $detailUrl = $isTx
                        ? route('admin.cashbook.transaction.show', $entry->source_id)
                        : ($entry->duplicate_status === 'possible_duplicate'
                            ? route('admin.cashbook.finance.reconciliation', ['statementRef' => $entry->secureRouteKey()])
                            : null);

                    $displayStatus = match(true) {
                        $entry->is_finalized && $entry->status === 'reconciled' => 'VERIFIED',
                        ! $entry->is_finalized && $entry->direction === 'in' => 'NEEDS VERIFICATION',
                        $entry->duplicate_status === 'possible_duplicate' => 'NEEDS ATTENTION',
                        default => strtoupper(str_replace('_', ' ', $entry->status)),
                    };
                @endphp
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-2xl bg-slate-50 border border-slate-100 hover:border-slate-200 transition">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-extrabold text-xs text-slate-900">{{ $shopName }}</span>
                            <span class="text-slate-300">&bull;</span>
                            <span class="text-xs font-bold text-slate-600">{{ $methodName }}</span>
                            <span class="text-slate-300">&bull;</span>
                            <span class="font-mono text-[11px] font-bold text-slate-400">{{ $entry->transaction_date?->format('d M Y') }}</span>
                        </div>
                        <p class="text-xs font-medium text-slate-500 truncate mt-0.5">
                            {{ $entry->narration ?: $entry->reference ?: 'Statement record' }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-4">
                        <div class="text-left sm:text-right">
                            <div class="font-mono text-sm font-black {{ $entry->direction === 'in' ? 'text-slate-900' : 'text-rose-700' }}">
                                ₹{{ number_format($entry->amount, 2) }}
                            </div>
                            <div>
                                @if($displayStatus === 'VERIFIED')
                                    <span class="inline-flex items-center gap-1 text-[9px] font-black text-emerald-800 bg-emerald-100 px-1.5 py-0.5 rounded-md">
                                        <i data-lucide="check" class="w-3 h-3"></i> VERIFIED
                                    </span>
                                @elseif($displayStatus === 'NEEDS VERIFICATION')
                                    <span class="inline-flex items-center gap-1 text-[9px] font-black text-amber-800 bg-amber-100 px-1.5 py-0.5 rounded-md">
                                        <i data-lucide="alert-triangle" class="w-3 h-3"></i> NEEDS VERIFICATION
                                    </span>
                                @elseif($displayStatus === 'NEEDS ATTENTION')
                                    <span class="inline-flex items-center gap-1 text-[9px] font-black text-rose-800 bg-rose-100 px-1.5 py-0.5 rounded-md">
                                        <i data-lucide="alert-circle" class="w-3 h-3"></i> NEEDS ATTENTION
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-[9px] font-black text-slate-600 bg-slate-200 px-1.5 py-0.5 rounded-md">
                                        {{ $displayStatus }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Single Action: View -->
                        @if($detailUrl)
                            <a href="{{ $detailUrl }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-bold transition border border-slate-200 shadow-xs cursor-pointer flex-shrink-0">
                                <span>View</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-xs font-bold text-slate-400 border border-dashed border-slate-200 rounded-2xl">
                    No statement entries found for this filter.
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        <div class="pt-4 border-t border-slate-100">
            {{ $statementEntries->links() }}
        </div>
    </div>

</div>
@endsection
