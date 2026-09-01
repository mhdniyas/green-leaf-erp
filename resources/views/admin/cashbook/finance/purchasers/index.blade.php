@extends('admin.cashbook.layouts.app')

@section('title', 'Purchaser Finance - Cashbook')

@section('header_title')
    <i data-lucide="wallet-cards" class="h-5 w-5 text-emerald-600"></i> Purchaser Finance
@endsection

@section('header_subtitle')
    Purchaser advances, cash bill utilization, and separate credit purchase exposure.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
            <i data-lucide="truck" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Vendor Credit</span>
        </a>
        <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
            <i data-lucide="git-compare-arrows" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Reconciliation</span>
        </a>
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-5">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Cash Given</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($kpi['cash_given'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Cash Used</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-amber-700">₹{{ number_format($kpi['cash_used'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Remaining Advance</span>
                <div class="mt-2 font-mono text-2xl font-extrabold {{ $kpi['remaining_advance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format($kpi['remaining_advance'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Credit Purchases</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-rose-700">₹{{ number_format($kpi['credit_purchases'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Purchasers</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-slate-900">{{ number_format($kpi['purchaser_count']) }}</div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_0.8fr]">
            <div id="record-funding" class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Purchaser Advance Summary</h2>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">Cash advance balance excludes vendor-credit bills.</p>
                    </div>
                    <span class="font-mono text-xs font-bold text-slate-400">{{ $purchasers->total() }} rows</span>
                </div>

                <form method="GET" action="{{ route('admin.cashbook.finance.purchasers') }}" class="mb-4 grid gap-3 md:grid-cols-[auto_1fr_1fr_1.5fr_auto]">
                    <x-cashbook.previous-month-button mode="range" size="sm" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
                    <input type="date" name="start_date" value="{{ $startDate }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <input type="date" name="end_date" value="{{ $endDate }}" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search purchaser" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <button class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-500">
                        <i data-lucide="filter" class="h-4 w-4"></i> Filter
                    </button>
                </form>

                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3">Purchaser</th>
                                <th class="px-3 py-3 text-right">Cash Given</th>
                                <th class="px-3 py-3 text-right">Cash Used</th>
                                <th class="px-3 py-3 text-right">Remaining</th>
                                <th class="px-3 py-3 text-right">Credit Purchases</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($purchasers as $purchaser)
                                @php
                                    $cashGiven = round((float) $purchaser->cash_given, 2);
                                    $cashUsed = round((float) $purchaser->cash_used, 2);
                                    $remaining = round($cashGiven - $cashUsed, 2);
                                    $creditPurchases = round((float) $purchaser->credit_purchases, 2);
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3">
                                        <div class="font-extrabold text-slate-900">{{ $purchaser->name }}</div>
                                        <div class="text-[11px] font-semibold text-slate-400">{{ $purchaser->email }}</div>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono font-bold text-slate-900">₹{{ number_format($cashGiven, 2) }}</td>
                                    <td class="px-3 py-3 text-right font-mono font-bold text-amber-700">₹{{ number_format($cashUsed, 2) }}</td>
                                    <td class="px-3 py-3 text-right font-mono font-extrabold {{ $remaining >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format($remaining, 2) }}</td>
                                    <td class="px-3 py-3 text-right font-mono font-bold text-rose-700">₹{{ number_format($creditPurchases, 2) }}</td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase {{ $remaining >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                            {{ $remaining >= 0 ? 'Funded' : 'Overdrawn' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.cashbook.finance.purchasers.details', $purchaser->public_uuid) }}" class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800">
                                            View Splits
                                            <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm font-bold text-slate-400">No purchaser finance rows found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $purchasers->links() }}</div>
            </div>

            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <h2 class="text-base font-extrabold text-slate-950">Record Funding</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Creates purchaser advance and outgoing cashbook movement.</p>
                </div>

                <form method="POST" action="{{ $selectedPurchaser ? route('admin.cashbook.finance.purchasers.funding.store', $selectedPurchaser->public_uuid) : '#' }}" class="space-y-3" data-purchaser-funding-form>
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-600">Purchaser</label>
                        <select class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" onchange="this.form.action=this.options[this.selectedIndex].dataset.action">
                            @foreach($purchaserOptions as $option)
                                <option value="{{ $option->id }}" data-action="{{ route('admin.cashbook.finance.purchasers.funding.store', $option->public_uuid) }}" @selected($selectedPurchaser?->id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-600">Amount</label>
                            <input name="amount" type="number" step="0.01" min="0.01" required class="min-h-11 w-full rounded-xl border border-slate-300 px-3 font-mono text-sm font-bold text-slate-900">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-600">Date</label>
                            <input name="business_date" type="date" value="{{ today()->toDateString() }}" required class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-xs font-bold text-slate-800">
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-600">Source</label>
                            <select name="payment_source" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                                <option value="Bank">Bank</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-600">Company Account</label>
                            <select name="company_account_id" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                                <option value="">No statement row</option>
                                @foreach($companyAccounts as $account)
                                    <option value="{{ $account->id }}" @selected(App\Models\Cashbook\CompanyAccount::isSelected($account, old('company_account_id'), $companyAccounts))>{{ $account->name }} / {{ strtoupper($account->account_type) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input name="reference" type="text" placeholder="Reference / UTR / voucher" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                    <input name="description" type="text" placeholder="Note" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white hover:bg-emerald-500">
                        <i data-lucide="plus-circle" class="h-4 w-4"></i> Record Funding
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection
