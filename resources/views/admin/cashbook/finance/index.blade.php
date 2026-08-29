@extends('admin.cashbook.layouts.app')

@section('title', 'Company Finance - Cashbook')

@section('header_title')
    <i data-lucide="badge-dollar-sign" class="w-5 h-5 text-emerald-600"></i> Company Finance
@endsection

@section('header_subtitle')
    Bank, liquid cash, floating shop payments, and admin reconciliation control.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.finance.cheque-submission') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 text-xs font-bold text-amber-800 shadow-sm hover:bg-amber-100">
            <i data-lucide="file-check-2" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Cheques</span>
        </a>
        <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-800 shadow-sm hover:bg-emerald-100">
            <i data-lucide="git-compare-arrows" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Reconcile</span>
        </a>
        <a href="{{ route('admin.cashbook.finance.journal') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
            <i data-lucide="book-open-check" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Journal</span>
        </a>
        <a href="{{ route('admin.cashbook.bank-accounts.create') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
            <i data-lucide="landmark" class="h-4 w-4"></i>
            <span class="hidden sm:inline">Accounts</span>
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

        {{-- ── 1. COMPANY MONEY POSITION HERO ────────────────────────────────── --}}
        <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Verified Company Money --}}
            <div class="rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-500/10 via-emerald-500/5 to-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm">
                            <i data-lucide="shield-check" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Verified Company Money</span>
                            <h2 class="text-xs font-bold text-slate-500">Bank Accounts + Company Cash Box</h2>
                        </div>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-black uppercase text-emerald-800">Real Money</span>
                </div>
                <div class="mt-4 flex items-baseline justify-between gap-4">
                    <div class="font-mono text-3xl font-black text-emerald-700">₹{{ number_format($moneyPosition['verified_company_money'] ?? $totals['current_balance'], 2) }}</div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 border-t border-emerald-200/60 pt-3 text-xs">
                    <div class="rounded-xl bg-white/80 p-2.5 shadow-sm">
                        <span class="block text-[10px] font-bold uppercase text-slate-400">Verified Bank</span>
                        <strong class="font-mono text-sm font-extrabold text-slate-900">₹{{ number_format($moneyPosition['bank_accounts']['total_verified'] ?? $totals['bank_balance'], 2) }}</strong>
                    </div>
                    <div class="rounded-xl bg-white/80 p-2.5 shadow-sm">
                        <span class="block text-[10px] font-bold uppercase text-slate-400">Verified Cash Box</span>
                        <strong class="font-mono text-sm font-extrabold text-emerald-700">₹{{ number_format($moneyPosition['company_cash']['total_verified'] ?? $totals['liquid_cash'], 2) }}</strong>
                    </div>
                </div>
            </div>

            {{-- Expected / In-Transit Funds --}}
            <div class="rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-600 text-white shadow-sm">
                            <i data-lucide="clock" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-amber-800">Expected / In-Transit Funds</span>
                            <h2 class="text-xs font-bold text-slate-500">Pending Verification + Cash With Shops + Floating Cheques</h2>
                        </div>
                    </div>
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase text-amber-800">Unverified</span>
                </div>
                <div class="mt-4 flex items-baseline justify-between gap-4">
                    <div class="font-mono text-3xl font-black text-amber-700">₹{{ number_format($moneyPosition['expected_in_transit_money'] ?? 0, 2) }}</div>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 border-t border-amber-200/60 pt-3 text-xs">
                    <div class="rounded-xl bg-white/80 p-2 shadow-sm">
                        <span class="block text-[10px] font-bold uppercase text-slate-400">Bank Pending</span>
                        <strong class="font-mono text-xs font-extrabold text-amber-700">₹{{ number_format($moneyPosition['bank_accounts']['total_pending'] ?? 0, 2) }}</strong>
                    </div>
                    <div class="rounded-xl bg-white/80 p-2 shadow-sm">
                        <span class="block text-[10px] font-bold uppercase text-slate-400">With Shops</span>
                        <strong class="font-mono text-xs font-extrabold text-rose-700">₹{{ number_format($moneyPosition['cash_with_shops']['total_cash_with_shops'] ?? 0, 2) }}</strong>
                    </div>
                    <div class="rounded-xl bg-white/80 p-2 shadow-sm">
                        <span class="block text-[10px] font-bold uppercase text-slate-400">Floating Chq</span>
                        <strong class="font-mono text-xs font-extrabold text-cyan-700">₹{{ number_format($moneyPosition['floating_cheques']['total_floating'] ?? 0, 2) }}</strong>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── 2. BANK ACCOUNTS POSITION & RECONCILIATION ─────────────────────── --}}
        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Company Bank & Wallet Accounts</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Verified balance, pending in-flight collections, and reconciliation coverage.</p>
                </div>
                <a href="{{ route('admin.cashbook.bank-accounts.create') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                    <i data-lucide="plus-circle" class="h-4 w-4"></i> Manage Accounts
                </a>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse($moneyPosition['bank_accounts']['accounts'] ?? [] as $item)
                    @php $acc = $item['account']; @endphp
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5 flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="break-words text-sm font-black text-slate-950">{{ $acc->name }}</h3>
                                        <span class="rounded-md bg-white px-2 py-0.5 text-[10px] font-black uppercase text-slate-600">{{ $acc->account_type }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $acc->bank_name ?: $acc->account_number ?: 'Bank account' }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="text-[10px] font-bold uppercase text-slate-400">Verified</div>
                                    <div class="font-mono text-sm font-extrabold text-emerald-700">₹{{ number_format($item['verified_balance'], 2) }}</div>
                                </div>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-xl bg-white p-2 border border-slate-100">
                                    <span class="block text-[10px] font-bold text-amber-700">Pending Verify</span>
                                    <strong class="font-mono text-xs font-extrabold text-amber-800">₹{{ number_format($item['net_pending'], 2) }}</strong>
                                </div>
                                <div class="rounded-xl bg-white p-2 border border-slate-100">
                                    <span class="block text-[10px] font-bold text-slate-400">Projected</span>
                                    <strong class="font-mono text-xs font-extrabold text-slate-900">₹{{ number_format($item['projected_position'], 2) }}</strong>
                                </div>
                            </div>

                            {{-- Reconciliation Progress --}}
                            <div class="mt-3">
                                <div class="flex items-center justify-between text-[10px] font-bold text-slate-500 mb-1">
                                    <span>Reconciled</span>
                                    <span>{{ $item['reconciliation_percentage'] }}%</span>
                                </div>
                                <div class="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $item['reconciliation_percentage'] }}%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <a href="{{ route('admin.cashbook.bank-accounts.show', $acc) }}" class="inline-flex min-h-9 items-center justify-center gap-1 rounded-lg bg-white px-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-100">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i> Details
                            </a>
                            <a href="{{ route('admin.cashbook.bank-accounts.statement', $acc) }}" class="inline-flex min-h-9 items-center justify-center gap-1 rounded-lg bg-emerald-50 px-2 text-xs font-bold text-emerald-700 hover:bg-emerald-100">
                                <i data-lucide="list-checks" class="h-3.5 w-3.5"></i> Statement
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-400 md:col-span-2 xl:col-span-3">
                        No bank accounts found.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- ── 3. CASH WITH SHOPS (PHYSICAL CASH AT SHOPS) ───────────────────── --}}
        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                        <i data-lucide="store" class="h-4 w-4"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Cash With Shops (Physical Cash)</h2>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">Unverified customer cash collected at retail shops awaiting company physical handover.</p>
                    </div>
                </div>
                <div class="font-mono text-sm font-extrabold text-rose-700">Total: ₹{{ number_format($moneyPosition['cash_with_shops']['total_cash_with_shops'] ?? 0, 2) }}</div>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-3 py-3">Shop</th>
                            <th class="px-3 py-3 text-right">Cash With Shop</th>
                            <th class="px-3 py-3 text-right">Closing Position</th>
                            <th class="px-3 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($moneyPosition['cash_with_shops']['shops'] ?? [] as $shopRow)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 font-bold text-slate-900">{{ $shopRow['shop_name'] }}</td>
                                <td class="px-3 py-3 text-right font-mono font-extrabold text-rose-700">₹{{ number_format($shopRow['cash_with_shop'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-600">₹{{ number_format($shopRow['closing_position'], 2) }}</td>
                                <td class="px-3 py-3 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase {{ $shopRow['cash_with_shop'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $shopRow['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-xs font-bold text-slate-400">No active shops.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[1.25fr_0.75fr]">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Pending Shop Payments</h2>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">Payments waiting for the statement-first reconciliation queue.</p>
                    </div>
                    <span class="font-mono text-xs font-bold text-slate-400">{{ $pendingPaymentRequests->count() }} open</span>
                </div>

                <div class="space-y-3">
                    @forelse($pendingPaymentRequests as $paymentRequest)
                        @php
                            $floatingAmount = (float) $paymentRequest->floating_amount > 0
                                ? (float) $paymentRequest->floating_amount
                                : max(0, (float) $paymentRequest->requested_amount - (float) $paymentRequest->reconciled_amount);
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 sm:p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('admin.cashbook.finance.journal.secure-show', $paymentRequest->secureRouteKey()) }}" class="text-sm font-black text-slate-950 hover:text-emerald-700">{{ $paymentRequest->shop?->name ?? 'Shop' }}</a>
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-black uppercase text-amber-700">{{ $paymentRequest->reconciliationStatusLabel() }}</span>
                                    </div>
                                    <div class="mt-2 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                        <div>
                                            <span class="block font-bold text-slate-400">Submitted</span>
                                            <strong class="font-mono text-slate-900">₹{{ number_format($paymentRequest->requested_amount, 2) }}</strong>
                                        </div>
                                        <div>
                                            <span class="block font-bold text-slate-400">Reconciled</span>
                                            <strong class="font-mono text-emerald-700">₹{{ number_format($paymentRequest->reconciled_amount, 2) }}</strong>
                                        </div>
                                        <div>
                                            <span class="block font-bold text-slate-400">Floating</span>
                                            <strong class="font-mono text-amber-700">₹{{ number_format($floatingAmount, 2) }}</strong>
                                        </div>
                                        <div>
                                            <span class="block font-bold text-slate-400">Advance</span>
                                            <strong class="font-mono text-cyan-700">₹{{ number_format($paymentRequest->shop_advance_amount, 2) }}</strong>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs font-medium text-slate-500">
                                        {{ $paymentRequest->paymentMethodLabel() }}
                                        @if($paymentRequest->payment_reference)
                                            / {{ $paymentRequest->payment_reference }}
                                        @endif
                                    </p>
                                </div>

                                <div class="flex w-full flex-col gap-2 text-xs sm:w-auto sm:flex-row lg:shrink-0">
                                    <a href="{{ route('admin.cashbook.finance.reconciliation', ['month' => ($paymentRequest->payment_date ?: $paymentRequest->created_at)->format('Y-m')]) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 font-bold text-white hover:bg-emerald-500">
                                        <i data-lucide="git-compare-arrows" class="h-4 w-4"></i> Match Statement
                                    </a>
                                    <a href="{{ route('admin.cashbook.finance.journal.secure-show', $paymentRequest->secureRouteKey()) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 font-bold text-slate-700 hover:bg-slate-50">
                                        <i data-lucide="eye" class="h-4 w-4"></i> Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">
                            No pending shop payments waiting for reconciliation.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <h2 class="text-base font-extrabold text-slate-950">Add Statement Entry</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Manual entry now; PDF import can plug into this same table later.</p>
                </div>

                <form method="POST" action="{{ route('admin.cashbook.finance.statement-entries.store') }}" class="space-y-3 text-xs">
                    @csrf
                    <select name="company_account_id" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                        <option value="">Select account</option>
                        @foreach($companyAccounts as $account)
                            <option value="{{ $account->id }}" @selected(App\Models\Cashbook\CompanyAccount::isSelected($account, old('company_account_id'), $companyAccounts))>{{ $account->name }}</option>
                        @endforeach
                    </select>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <input type="date" name="transaction_date" value="{{ today()->toDateString() }}" required class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                        <select name="direction" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                            <option value="in">Money In</option>
                            <option value="out">Money Out</option>
                        </select>
                    </div>
                    <input type="number" step="0.01" min="0.01" name="amount" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800" placeholder="Amount">
                    <input type="text" name="reference" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800" placeholder="Reference number">
                    <textarea name="narration" rows="3" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800" placeholder="Bank narration or cash receipt detail"></textarea>
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 font-bold text-white hover:bg-emerald-500">
                        <i data-lucide="plus-circle" class="h-4 w-4"></i> Add Statement Entry
                    </button>
                </form>
            </div>
        </section>

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Recent Reconciliations</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Admin trace of where money went and which account received it.</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $recentReconciliations->count() }} records</span>
            </div>

            <div class="space-y-3 lg:hidden">
                @forelse($recentReconciliations as $reconciliation)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-black text-slate-950">
                                    @if($reconciliation->paymentRequest)
                                        <a href="{{ route('admin.cashbook.finance.journal.secure-show', $reconciliation->paymentRequest->secureRouteKey()) }}" class="hover:text-emerald-700">{{ $reconciliation->paymentRequest?->shop?->name ?? 'Shop' }}</a>
                                    @else
                                        Shop
                                    @endif
                                </div>
                                <div class="mt-1 text-xs font-semibold text-slate-500">{{ $reconciliation->companyAccount?->name }}</div>
                                <div class="mt-1 font-mono text-[11px] font-bold text-slate-400">{{ $reconciliation->reconciled_at?->format('Y-m-d') }} / {{ $reconciliation->reconciledBy?->name ?? '-' }}</div>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase text-emerald-700">{{ $reconciliation->status }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div class="rounded-xl bg-white p-2">
                                <span class="block font-bold text-slate-400">Statement</span>
                                <strong class="font-mono text-slate-800">₹{{ number_format($reconciliation->statement_amount, 2) }}</strong>
                            </div>
                            <div class="rounded-xl bg-white p-2">
                                <span class="block font-bold text-slate-400">Cleared</span>
                                <strong class="font-mono text-emerald-700">₹{{ number_format($reconciliation->cleared_amount, 2) }}</strong>
                            </div>
                            <div class="rounded-xl bg-white p-2">
                                <span class="block font-bold text-slate-400">Diff</span>
                                <strong class="font-mono text-amber-700">₹{{ number_format($reconciliation->difference_amount, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">No reconciliations recorded yet.</div>
                @endforelse
            </div>

            <div class="hidden overflow-x-auto custom-scrollbar lg:block">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-3 py-3">Date</th>
                            <th class="px-3 py-3">Shop</th>
                            <th class="px-3 py-3">Account</th>
                            <th class="px-3 py-3 text-right">Statement</th>
                            <th class="px-3 py-3 text-right">Cleared</th>
                            <th class="px-3 py-3 text-right">Difference</th>
                            <th class="px-3 py-3">Admin</th>
                            <th class="px-3 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentReconciliations as $reconciliation)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 font-mono font-bold text-slate-700">{{ $reconciliation->reconciled_at?->format('Y-m-d') }}</td>
                                <td class="px-3 py-3 font-bold text-slate-900">
                                    @if($reconciliation->paymentRequest)
                                        <a href="{{ route('admin.cashbook.finance.journal.secure-show', $reconciliation->paymentRequest->secureRouteKey()) }}" class="hover:text-emerald-700">{{ $reconciliation->paymentRequest?->shop?->name ?? 'Shop' }}</a>
                                    @else
                                        Shop
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-700">{{ $reconciliation->companyAccount?->name }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-800">₹{{ number_format($reconciliation->statement_amount, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-emerald-700">₹{{ number_format($reconciliation->cleared_amount, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-amber-700">₹{{ number_format($reconciliation->difference_amount, 2) }}</td>
                                <td class="px-3 py-3 font-semibold text-slate-600">{{ $reconciliation->reconciledBy?->name ?? '-' }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase text-emerald-700">{{ $reconciliation->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center font-bold text-slate-400">No reconciliations recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
