@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Payment Journal - Cashbook')

@section('header_title')
    <i data-lucide="book-open-check" class="h-5 w-5 text-emerald-600"></i> Payment Journal
@endsection

@section('header_subtitle')
    All shop payment transactions with reconciliation status and details.
@endsection

@section('header_actions')
    <a href="{{ route('admin.cashbook.finance') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
        <i data-lucide="badge-dollar-sign" class="h-4 w-4"></i>
        <span class="hidden sm:inline">Finance</span>
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Requested</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($totals['requested'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Reconciled</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-emerald-700">₹{{ number_format($totals['reconciled'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Floating</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-amber-700">₹{{ number_format($totals['floating'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pending</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-rose-700">₹{{ number_format($totals['pending'], 2) }}</div>
            </div>
        </section>

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <form method="GET" action="{{ route('admin.cashbook.finance.journal') }}" class="grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Status</label>
                    <select name="status" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        @foreach(['all' => 'All', 'pending' => 'Pending', 'floating' => 'Floating', 'partially_reconciled' => 'Partially Reconciled', 'reconciled' => 'Reconciled'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Payment Method</label>
                    <select name="payment_method" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        @foreach(['all' => 'All', 'cash' => 'Cash', 'online_upi' => 'Online UPI', 'cheque' => 'Cheque'] as $value => $label)
                            <option value="{{ $value }}" @selected($method === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white hover:bg-emerald-500">
                    <i data-lucide="filter" class="h-4 w-4"></i> Filter
                </button>
            </form>
        </section>

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Shop Payment Transactions</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Click a row to see payment, statement, account, and reconciliation details.</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $paymentRequests->total() }} rows</span>
            </div>

            <div class="space-y-3 lg:hidden">
                @forelse($paymentRequests as $payment)
                    @php
                        $floating = (float) $payment->floating_amount > 0 ? (float) $payment->floating_amount : max(0, (float) $payment->requested_amount - (float) $payment->reconciled_amount);
                    @endphp
                    <a href="{{ route('admin.cashbook.finance.journal.secure-show', $payment->secureRouteKey()) }}" class="block rounded-2xl border border-slate-200 bg-slate-50 p-3 transition hover:bg-slate-100">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="break-words text-sm font-black text-slate-950">{{ $payment->shop?->name ?? 'Shop' }}</div>
                                <div class="mt-1 text-xs font-semibold text-slate-500">{{ $payment->paymentMethodLabel() }} / {{ $payment->payment_reference ?: 'No reference' }}</div>
                            </div>
                            <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $payment->reconciliationStatusLabel() }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div class="rounded-xl bg-white p-2">
                                <span class="block font-bold text-slate-400">Requested</span>
                                <strong class="font-mono text-slate-800">₹{{ number_format($payment->requested_amount, 2) }}</strong>
                            </div>
                            <div class="rounded-xl bg-white p-2">
                                <span class="block font-bold text-slate-400">Cleared</span>
                                <strong class="font-mono text-emerald-700">₹{{ number_format($payment->reconciled_amount, 2) }}</strong>
                            </div>
                            <div class="rounded-xl bg-white p-2">
                                <span class="block font-bold text-slate-400">Floating</span>
                                <strong class="font-mono text-amber-700">₹{{ number_format($floating, 2) }}</strong>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-400">No shop payment journal rows found.</div>
                @endforelse
            </div>

            <div class="hidden overflow-x-auto custom-scrollbar lg:block">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-3 py-3">Date</th>
                            <th class="px-3 py-3">Shop</th>
                            <th class="px-3 py-3">Method</th>
                            <th class="px-3 py-3">Reference</th>
                            <th class="px-3 py-3 text-right">Requested</th>
                            <th class="px-3 py-3 text-right">Cleared</th>
                            <th class="px-3 py-3 text-right">Floating</th>
                            <th class="px-3 py-3">Account</th>
                            <th class="px-3 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($paymentRequests as $payment)
                            @php
                                $floating = (float) $payment->floating_amount > 0 ? (float) $payment->floating_amount : max(0, (float) $payment->requested_amount - (float) $payment->reconciled_amount);
                                $accountNames = $payment->reconciliations->pluck('companyAccount.name')->filter()->unique()->join(', ');
                            @endphp
                            <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location.href='{{ route('admin.cashbook.finance.journal.secure-show', $payment->secureRouteKey()) }}'">
                                <td class="px-3 py-3 font-mono font-bold text-slate-700">{{ $payment->payment_date?->format('Y-m-d') ?: $payment->created_at?->format('Y-m-d') }}</td>
                                <td class="px-3 py-3 font-bold text-slate-900">{{ $payment->shop?->name ?? 'Shop' }}</td>
                                <td class="px-3 py-3 font-semibold text-slate-700">{{ $payment->paymentMethodLabel() }}</td>
                                <td class="px-3 py-3 font-mono font-bold text-slate-600">{{ $payment->payment_reference ?: '-' }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-800">₹{{ number_format($payment->requested_amount, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-emerald-700">₹{{ number_format($payment->reconciled_amount, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-amber-700">₹{{ number_format($floating, 2) }}</td>
                                <td class="px-3 py-3 font-semibold text-slate-600">{{ $accountNames ?: '-' }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $payment->reconciliationStatusLabel() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-sm font-bold text-slate-400">No shop payment journal rows found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $paymentRequests->links() }}
            </div>
        </section>
    </div>
@endsection
