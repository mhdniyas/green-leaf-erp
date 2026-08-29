@extends('admin.cashbook.layouts.app')

@section('title', $account->name . ' — Account Details')

@section('header_title')
    <i data-lucide="landmark" class="h-5 w-5 text-emerald-600"></i> {{ $account->name }}
@endsection

@section('header_subtitle')
    @if($account->account_type === 'cash')
        Company physical cash vault and shop cash collection position.
    @else
        Verified bank balance, pending collections, and account transactions.
    @endif
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.finance') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            <span>Company Money</span>
        </a>
        <a href="{{ route('admin.cashbook.bank-accounts.statement', $account) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-xs hover:bg-slate-800 transition">
            <i data-lucide="list-checks" class="h-4 w-4"></i>
            <span>Statement</span>
        </a>
    </div>
@endsection

@section('content')
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
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900">{{ $account->name }}</h1>
                    @if($account->is_default)
                        <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-700">Default</span>
                    @endif
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">
                        {{ $account->account_type }}
                    </span>
                </div>
                <p class="text-xs font-bold text-slate-500 mt-1">
                    {{ $account->bank_name ?: ($account->account_type === 'cash' ? 'Company Cash Vault' : 'Bank') }}
                    &bull; <span class="font-mono text-slate-700">{{ $account->account_number ?: 'CASH' }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cashbook.bank-accounts.create') }}" class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                    <i data-lucide="settings-2" class="w-3.5 h-3.5"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </div>

    <!-- KPI Balance Banner -->
    <section>
        @if($account->account_type === 'cash')
            <!-- CASH BOX BANNER (2 Main Metrics) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white rounded-3xl border border-emerald-200 p-5 shadow-xs">
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Verified Company Cash</span>
                    <div class="mt-2 font-mono text-3xl font-black text-emerald-950">
                        ₹{{ number_format($accountPosition['verified_balance'] ?? $account->current_balance, 2) }}
                    </div>
                    <p class="mt-1 text-xs font-bold text-slate-500">
                        Physical money verified and present in company vault.
                    </p>
                </div>

                <div class="bg-white rounded-3xl border border-sky-200 p-5 shadow-xs">
                    <span class="text-[10px] font-black uppercase tracking-wider text-sky-700">Cash With Shops</span>
                    <div class="mt-2 font-mono text-3xl font-black text-sky-950">
                        ₹{{ number_format($cashWithShops['total_cash_with_shops'] ?? 0, 2) }}
                    </div>
                    <p class="mt-1 text-xs font-bold text-slate-500">
                        Customer cash retained at retail shops awaiting handover.
                    </p>
                </div>
            </div>
        @else
            <!-- BANK ACCOUNT BANNER (3 Main Metrics) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-3xl border border-emerald-200 p-5 shadow-xs">
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Verified Balance</span>
                    <div class="mt-2 font-mono text-3xl font-black text-emerald-950">
                        ₹{{ number_format($accountPosition['verified_balance'] ?? $account->current_balance, 2) }}
                    </div>
                    <p class="mt-1 text-xs font-bold text-slate-500">
                        Real money confirmed in bank statement.
                    </p>
                </div>

                <div class="bg-white rounded-3xl border border-amber-200 p-5 shadow-xs">
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-700">Needs Verification</span>
                    <div class="mt-2 font-mono text-3xl font-black text-amber-950">
                        ₹{{ number_format($accountPosition['pending_in'] ?? 0, 2) }}
                    </div>
                    <p class="mt-1 text-xs font-bold text-slate-500">
                        Pending Verification: {{ $accountPosition['pending_count'] ?? 0 }} in-flight collections.
                    </p>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-xs">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Projected Position</span>
                    <div class="mt-2 font-mono text-3xl font-black text-slate-900">
                        ₹{{ number_format($accountPosition['projected_position'] ?? $account->current_balance, 2) }}
                    </div>
                    <p class="mt-1 text-xs font-bold text-slate-500">
                        Verified balance + net pending funds.
                    </p>
                </div>
            </div>
        @endif
    </section>

    <!-- Recent Account Collections & Transactions -->
    <section class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
            <div>
                <h2 class="text-sm font-black text-slate-900">Recent Collections & Statement Rows</h2>
                <p class="text-xs font-bold text-slate-500 mt-0.5">Shop-linked collections and statement entries for this account.</p>
            </div>
            <a href="{{ route('admin.cashbook.bank-accounts.statement', $account) }}"
               class="text-xs font-bold text-emerald-700 hover:text-emerald-800">
                View Full Statement &rarr;
            </a>
        </div>

        <div class="space-y-2.5">
            @forelse($recentStatementEntries as $entry)
                @php
                    $isTx = $entry->source_type === 'App\Models\Cashbook\ShopLedgerTransaction' && $entry->source_id;
                    $shopName = $entry->sourceRecord?->shop?->name ?? 'Company';
                    $methodName = $entry->sourceRecord?->entryType?->name ?? ($entry->direction === 'in' ? 'Deposit' : 'Payment');
                    $detailUrl = $isTx
                        ? route('admin.cashbook.transaction.show', $entry->source_id)
                        : route('admin.cashbook.bank-accounts.statement', ['account' => $account, 'month' => $entry->transaction_date?->format('Y-m')]);

                    $statusBadge = match(true) {
                        $entry->is_finalized && $entry->status === 'reconciled' => 'RECEIVED',
                        $account->account_type === 'cash' && ! $entry->is_finalized => 'CASH WITH SHOP',
                        ! $entry->is_finalized => 'NEEDS VERIFICATION',
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
                            {{ $entry->narration ?: $entry->reference ?: 'Statement entry' }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-4">
                        <div class="text-left sm:text-right">
                            <div class="font-mono text-sm font-black {{ $entry->direction === 'in' ? 'text-slate-900' : 'text-rose-700' }}">
                                ₹{{ number_format($entry->amount, 2) }}
                            </div>
                            <div>
                                @if($statusBadge === 'RECEIVED')
                                    <span class="inline-flex items-center gap-1 text-[9px] font-black text-emerald-800 bg-emerald-100 px-1.5 py-0.5 rounded-md">
                                        <i data-lucide="check" class="w-3 h-3"></i> RECEIVED
                                    </span>
                                @elseif($statusBadge === 'NEEDS VERIFICATION')
                                    <span class="inline-flex items-center gap-1 text-[9px] font-black text-amber-800 bg-amber-100 px-1.5 py-0.5 rounded-md">
                                        <i data-lucide="alert-triangle" class="w-3 h-3"></i> NEEDS VERIFICATION
                                    </span>
                                @elseif($statusBadge === 'CASH WITH SHOP')
                                    <span class="inline-flex items-center gap-1 text-[9px] font-black text-sky-800 bg-sky-100 px-1.5 py-0.5 rounded-md">
                                        <i data-lucide="store" class="w-3 h-3"></i> CASH WITH SHOP
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-[9px] font-black text-slate-600 bg-slate-200 px-1.5 py-0.5 rounded-md">
                                        {{ $statusBadge }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Single Action: View -->
                        <a href="{{ $detailUrl }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-bold transition border border-slate-200 shadow-xs cursor-pointer flex-shrink-0">
                            <span>View</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-xs font-bold text-slate-400 border border-dashed border-slate-200 rounded-2xl">
                    No transactions recorded for this account yet.
                </div>
            @endforelse
        </div>
    </section>

</div>
@endsection
