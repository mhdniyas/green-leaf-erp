@extends('admin.cashbook.layouts.app')

@section('title', 'Company Money — Cashbook')

@section('header_title')
    <i data-lucide="badge-dollar-sign" class="w-5 h-5 text-emerald-600"></i> Company Money
@endsection

@section('header_subtitle')
    Where is company money now? Verified balances, in-transit funds, physical shop cash, and floating cheques.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.money-flow') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            <span>Money Flow</span>
        </a>
        <a href="{{ route('admin.cashbook.finance.cheque-submission') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 text-xs font-bold text-amber-800 shadow-xs hover:bg-amber-100 transition">
            <i data-lucide="file-check-2" class="h-4 w-4"></i>
            <span>Cheques</span>
        </a>
        <a href="{{ route('admin.cashbook.bank-accounts.create') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-xs hover:bg-slate-800 transition">
            <i data-lucide="plus-circle" class="h-4 w-4"></i>
            <span>Add Account</span>
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

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                <span>{{ $errors->first() }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 font-bold">&times;</button>
        </div>
    @endif

    <!-- ── 1. COMPANY MONEY HERO BANNER (4 Core Cards) ──────────────────────── -->
    <section>
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-400 mb-3">
            COMPANY MONEY POSITION
        </h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            
            <!-- 1. Verified Company Money -->
            <div class="rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-50/80 via-emerald-50/30 to-white p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Verified Company Money</span>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">Real Money</span>
                    </div>
                    <div class="mt-3 font-mono text-2xl sm:text-3xl font-black text-emerald-950">
                        ₹{{ number_format($moneyPosition['verified_company_money'] ?? $totals['current_balance'], 2) }}
                    </div>
                    <p class="mt-1 text-[11px] font-bold text-slate-500">
                        Confirmed bank accounts + verified cash box
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-emerald-100 flex items-center justify-between text-xs font-bold text-emerald-900">
                    <span>Bank: ₹{{ number_format($moneyPosition['bank_accounts']['total_verified'] ?? $totals['bank_balance'], 2) }}</span>
                    <span>Cash: ₹{{ number_format($moneyPosition['company_cash']['total_verified'] ?? $totals['liquid_cash'], 2) }}</span>
                </div>
            </div>

            <!-- 2. Pending Verification -->
            <div class="rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50/80 via-amber-50/30 to-white p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-800">Pending Verification</span>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">In Transit</span>
                    </div>
                    <div class="mt-3 font-mono text-2xl sm:text-3xl font-black text-amber-950">
                        ₹{{ number_format($moneyPosition['bank_accounts']['total_pending'] ?? 0, 2) }}
                    </div>
                    <p class="mt-1 text-[11px] font-bold text-slate-500">
                        Expected / In-Transit Funds awaiting bank verification
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-amber-100 text-xs font-bold text-amber-800">
                    Not included in verified balance
                </div>
            </div>

            <!-- 3. Cash With Shops -->
            <div class="rounded-3xl border border-sky-200 bg-gradient-to-br from-sky-50/80 via-sky-50/30 to-white p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-sky-800">Cash With Shops</span>
                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[9px] font-black uppercase text-sky-800">Outside Company</span>
                    </div>
                    <div class="mt-3 font-mono text-2xl sm:text-3xl font-black text-sky-950">
                        ₹{{ number_format($moneyPosition['cash_with_shops']['total_cash_with_shops'] ?? 0, 2) }}
                    </div>
                    <p class="mt-1 text-[11px] font-bold text-slate-500">
                        Physical cash still retained at retail stores
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-sky-100 text-xs font-bold text-sky-800">
                    Separate from verified company cash
                </div>
            </div>

            <!-- 4. Floating Cheques -->
            <div class="rounded-3xl border border-indigo-200 bg-gradient-to-br from-indigo-50/80 via-indigo-50/30 to-white p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-800">Floating Cheques</span>
                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[9px] font-black uppercase text-indigo-800">Uncleared</span>
                    </div>
                    <div class="mt-3 font-mono text-2xl sm:text-3xl font-black text-indigo-950">
                        ₹{{ number_format($moneyPosition['floating_cheques']['total_floating'] ?? 0, 2) }}
                    </div>
                    <p class="mt-1 text-[11px] font-bold text-slate-500">
                        Cheques pending bank clearance
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-indigo-100 text-xs font-bold text-indigo-800">
                    {{ $moneyPosition['floating_cheques']['floating_count'] ?? 0 }} uncleared cheques
                </div>
            </div>

        </div>
    </section>

    <!-- ── 2. ACCOUNTS POSITION GRID ────────────────────────────────────────── -->
    <section class="space-y-4">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-400">
            ACCOUNTS (Company Bank, Cash Box & Cheques)
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- Bank Accounts -->
            @forelse($moneyPosition['bank_accounts']['accounts'] ?? [] as $item)
                @php $acc = $item['account']; @endphp
                <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition space-y-4">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-black text-slate-900">{{ $acc->name }}</h3>
                                    @if($acc->is_default)
                                        <span class="rounded-md bg-emerald-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-emerald-700">Default</span>
                                    @endif
                                </div>
                                <p class="text-xs font-bold text-slate-500 mt-0.5">
                                    {{ $acc->bank_name ?: 'Bank Account' }} &bull; <span class="font-mono">{{ $acc->account_number ?: '—' }}</span>
                                </p>
                            </div>
                            <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600">
                                {{ $acc->account_type }}
                            </span>
                        </div>

                        <!-- 3-Metrics Block -->
                        <div class="mt-4 grid grid-cols-3 gap-2 text-xs">
                            <div class="p-2.5 rounded-2xl bg-emerald-50/70 border border-emerald-100">
                                <span class="block text-[9px] font-black uppercase tracking-wider text-emerald-700">Verified</span>
                                <span class="font-mono text-xs font-black text-emerald-950 mt-1 block">₹{{ number_format($item['verified_balance'], 2) }}</span>
                            </div>
                            <div class="p-2.5 rounded-2xl bg-amber-50/70 border border-amber-100">
                                <span class="block text-[9px] font-black uppercase tracking-wider text-amber-700">Pending</span>
                                <span class="font-mono text-xs font-black text-amber-950 mt-1 block">₹{{ number_format($item['net_pending'], 2) }}</span>
                            </div>
                            <div class="p-2.5 rounded-2xl bg-slate-50 border border-slate-100">
                                <span class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Projected</span>
                                <span class="font-mono text-xs font-black text-slate-900 mt-1 block">₹{{ number_format($item['projected_position'], 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Single Action: View -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400">
                            {{ $item['pending_count'] }} in-flight
                        </span>
                        <a href="{{ route('admin.cashbook.bank-accounts.show', $acc) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-xs transition cursor-pointer">
                            <span>View</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-6 rounded-3xl border border-dashed border-slate-200 text-center text-xs font-bold text-slate-400 col-span-full">
                    No bank accounts configured.
                </div>
            @endforelse

            <!-- Company Cash Box Card -->
            @foreach($moneyPosition['company_cash']['accounts'] ?? [] as $cashItem)
                @php $cashAcc = $cashItem['account']; @endphp
                <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition space-y-4">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-black text-slate-900">{{ $cashAcc->name }}</h3>
                                <p class="text-xs font-bold text-slate-500 mt-0.5">
                                    {{ $cashAcc->bank_name ?: 'Company Vault' }} &bull; <span class="font-mono">{{ $cashAcc->account_number ?: 'CASH' }}</span>
                                </p>
                            </div>
                            <span class="rounded-lg bg-sky-100 px-2 py-1 text-[10px] font-black uppercase text-sky-800">
                                CASH
                            </span>
                        </div>

                        <!-- 2-Metrics: Verified Cash vs Cash With Shops -->
                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                            <div class="p-2.5 rounded-2xl bg-emerald-50/70 border border-emerald-100">
                                <span class="block text-[9px] font-black uppercase tracking-wider text-emerald-700">Verified Cash</span>
                                <span class="font-mono text-xs font-black text-emerald-950 mt-1 block">₹{{ number_format($cashItem['verified_balance'], 2) }}</span>
                            </div>
                            <div class="p-2.5 rounded-2xl bg-sky-50/70 border border-sky-100">
                                <span class="block text-[9px] font-black uppercase tracking-wider text-sky-700">Cash With Shops</span>
                                <span class="font-mono text-xs font-black text-sky-950 mt-1 block">₹{{ number_format($moneyPosition['cash_with_shops']['total_cash_with_shops'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Single Action: View -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400">
                            Physical Vault
                        </span>
                        <a href="{{ route('admin.cashbook.bank-accounts.show', $cashAcc) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-xs transition cursor-pointer">
                            <span>View</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>
            @endforeach

            <!-- Floating Cheques Card -->
            <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition space-y-4">
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Floating Cheques</h3>
                            <p class="text-xs font-bold text-slate-500 mt-0.5">
                                Cheques collected awaiting bank deposit/clearing
                            </p>
                        </div>
                        <span class="rounded-lg bg-indigo-100 px-2 py-1 text-[10px] font-black uppercase text-indigo-800">
                            CHEQUES
                        </span>
                    </div>

                    <div class="mt-4 p-2.5 rounded-2xl bg-indigo-50/70 border border-indigo-100 text-xs">
                        <span class="block text-[9px] font-black uppercase tracking-wider text-indigo-700">Floating Amount</span>
                        <span class="font-mono text-base font-black text-indigo-950 mt-1 block">₹{{ number_format($moneyPosition['floating_cheques']['total_floating'] ?? 0, 2) }}</span>
                    </div>
                </div>

                <!-- Single Action: View -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400">
                        {{ $moneyPosition['floating_cheques']['floating_count'] ?? 0 }} cheques
                    </span>
                    <a href="{{ route('admin.cashbook.finance.cheque-submission') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-xs transition cursor-pointer">
                        <span>View</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>

        </div>
    </section>

    <!-- ── 3. PHYSICAL CASH WITH SHOPS BREAKDOWN ────────────────────────────── -->
    <section class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <h3 class="text-sm font-black text-slate-900">Cash With Shops (Physical Breakdown)</h3>
                <p class="text-xs font-bold text-slate-500 mt-0.5">Unverified customer cash at retail shops not yet handed over to company.</p>
            </div>
            <div class="font-mono text-sm font-black text-sky-800">
                Total: ₹{{ number_format($moneyPosition['cash_with_shops']['total_cash_with_shops'] ?? 0, 2) }}
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            @forelse($moneyPosition['cash_with_shops']['shops'] ?? [] as $shopRow)
                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="font-extrabold text-xs text-slate-900 block">{{ $shopRow['shop_name'] }}</span>
                        <span class="text-[10px] font-bold text-slate-400">Physical shop till</span>
                    </div>
                    <div class="text-right">
                        <span class="font-mono text-xs font-black text-sky-900 block">₹{{ number_format($shopRow['cash_with_shop'], 2) }}</span>
                        <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded-md {{ $shopRow['cash_with_shop'] > 0 ? 'bg-sky-100 text-sky-800' : 'bg-slate-200 text-slate-600' }}">
                            {{ $shopRow['status'] }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-xs font-bold text-slate-400 col-span-full">No active shops.</div>
            @endforelse
        </div>
    </section>

</div>
@endsection
