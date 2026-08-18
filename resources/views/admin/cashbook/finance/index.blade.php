@extends('admin.cashbook.layouts.app')

@section('title', 'Company Finance - Cashbook')

@section('header_title')
    <i data-lucide="badge-dollar-sign" class="w-5 h-5 text-emerald-600"></i> Company Finance
@endsection

@section('header_subtitle')
    Bank, liquid cash, floating shop payments, and admin reconciliation control.
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

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="white-card rounded-3xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Bank Balance</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($totals['bank_balance'], 2) }}</div>
            </div>
            <div class="white-card rounded-3xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Liquid Cash</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-emerald-700">₹{{ number_format($totals['liquid_cash'], 2) }}</div>
            </div>
            <div class="white-card rounded-3xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Wallet / UPI</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-cyan-700">₹{{ number_format($totals['wallet_balance'], 2) }}</div>
            </div>
            <div class="white-card rounded-3xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Floating Payments</span>
                <div class="mt-2 font-mono text-2xl font-extrabold text-amber-700">₹{{ number_format($totals['floating_payments'], 2) }}</div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[1.25fr_0.75fr]">
            <div class="white-card rounded-3xl border border-slate-200 p-4 shadow-xl sm:p-6">
                <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Pending Shop Payments</h2>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">Reconcile fully or partially against bank, wallet, cheque, or liquid cash.</p>
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
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-sm font-black text-slate-950">{{ $paymentRequest->shop?->name ?? 'Shop' }}</h3>
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

                                <form method="POST" action="{{ route('admin.cashbook.finance.payments.reconcile', $paymentRequest) }}" class="grid w-full grid-cols-1 gap-2 text-xs lg:max-w-2xl lg:grid-cols-6">
                                    @csrf
                                    <select name="company_account_id" required class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 lg:col-span-2">
                                        <option value="">Account</option>
                                        @foreach($companyAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="statement_entry_id" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 lg:col-span-2">
                                        <option value="">Liquid cash / no statement</option>
                                        @foreach($statementEntries as $entry)
                                            <option value="{{ $entry->id }}">
                                                {{ $entry->companyAccount?->name }} / {{ $entry->transaction_date?->format('d M') }} / ₹{{ number_format($entry->amount - $entry->matched_amount, 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" min="0.01" name="cleared_amount" value="{{ number_format($floatingAmount ?: $paymentRequest->requested_amount, 2, '.', '') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800" placeholder="Cleared">
                                    <input type="number" step="0.01" min="0" name="statement_amount" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800" placeholder="Bank amt">
                                    <select name="difference_action" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 lg:col-span-2">
                                        <option value="none">No difference</option>
                                        <option value="keep_floating">Keep floating</option>
                                        <option value="shop_expense">Add shop expense</option>
                                        <option value="shop_income">Add shop income</option>
                                    </select>
                                    <input type="number" step="0.01" min="0" name="difference_amount" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono font-bold text-slate-800" placeholder="Difference">
                                    <select name="difference_entry_type_id" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 lg:col-span-2">
                                        <option value="">Default category</option>
                                        @foreach($reconciliationEntryTypes as $entryType)
                                            <option value="{{ $entryType->id }}">{{ $entryType->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="date" name="business_date" value="{{ today()->toDateString() }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                                    <input type="text" name="admin_note" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800 lg:col-span-4" placeholder="Admin note">
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 font-bold text-white hover:bg-slate-800 lg:col-span-2">
                                        <i data-lucide="check-circle-2" class="h-4 w-4"></i> Reconcile
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">
                            No pending shop payments waiting for reconciliation.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="white-card rounded-3xl border border-slate-200 p-4 shadow-xl sm:p-6">
                <div class="mb-4 border-b border-slate-200 pb-3">
                    <h2 class="text-base font-extrabold text-slate-950">Add Statement Entry</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Manual entry now; PDF import can plug into this same table later.</p>
                </div>

                <form method="POST" action="{{ route('admin.cashbook.finance.statement-entries.store') }}" class="space-y-3 text-xs">
                    @csrf
                    <select name="company_account_id" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800">
                        <option value="">Select account</option>
                        @foreach($companyAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    <div class="grid grid-cols-2 gap-2">
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

        <section class="white-card rounded-3xl border border-slate-200 p-4 shadow-xl sm:p-6">
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Recent Reconciliations</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Admin trace of where money went and which account received it.</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $recentReconciliations->count() }} records</span>
            </div>

            <div class="overflow-x-auto">
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
                                <td class="px-3 py-3 font-bold text-slate-900">{{ $reconciliation->paymentRequest?->shop?->name ?? 'Shop' }}</td>
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
