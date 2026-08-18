@extends('admin.cashbook.layouts.app')

@section('title', 'Cheque Bank Submission - Cashbook')

@section('header_title')
    <i data-lucide="file-check-2" class="h-5 w-5 text-emerald-600"></i> Cheque Bank Submit
@endsection

@section('header_subtitle')
    Daily cheque list and print form for bank submission.
@endsection

@section('header_actions')
    <button type="button" onclick="window.print()" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
        <i data-lucide="printer" class="h-4 w-4"></i>
        <span class="hidden sm:inline">Print</span>
    </button>
@endsection

@push('styles')
    <style>
        @media print {
            body { background: #fff !important; }
            #main-sidebar,
            header,
            #global-page-loader,
            #toast-container,
            .no-print { display: none !important; }
            #cashbook-main { padding-left: 0 !important; }
            main { padding: 0 !important; }
            .print-sheet { box-shadow: none !important; border: 0 !important; }
        }
    </style>
@endpush

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="no-print white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <form method="GET" action="{{ route('admin.cashbook.finance.cheque-submission') }}" class="grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Submission Date</label>
                    <input type="date" name="date" value="{{ $date }}" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Deposit To Account</label>
                    <select name="company_account_id" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        @foreach($companyAccounts as $account)
                            <option value="{{ $account->id }}" @selected($selectedAccount && $selectedAccount->id === $account->id)>
                                {{ $account->name }} / {{ $account->bank_name ?: strtoupper($account->account_type) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white hover:bg-emerald-500">
                    <i data-lucide="filter" class="h-4 w-4"></i> Show
                </button>
            </form>
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Cheques To Submit</span>
                <div class="mt-2 font-mono text-3xl font-extrabold text-slate-950">{{ $totals['count'] }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Cheque Amount</span>
                <div class="mt-2 break-words font-mono text-3xl font-extrabold text-emerald-700">₹{{ number_format($totals['amount'], 2) }}</div>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Floating Until Bank Clears</span>
                <div class="mt-2 break-words font-mono text-3xl font-extrabold text-amber-700">₹{{ number_format($totals['floating'], 2) }}</div>
            </div>
        </section>

        <section class="print-sheet white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-6">
            <div class="mb-5 flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Bank Submission Form</p>
                    <h2 class="mt-1 text-xl font-extrabold text-slate-950">{{ config('greenleaf.name', 'Green Leaf') }}</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Submission date: {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs">
                    <div class="font-black text-slate-950">{{ $selectedAccount?->name ?? 'Select bank account' }}</div>
                    <div class="mt-1 font-semibold text-slate-600">{{ $selectedAccount?->bank_name ?: 'Bank/provider not set' }}</div>
                    <div class="mt-1 font-mono font-bold text-slate-600">{{ $selectedAccount?->account_number ?: 'Account number not set' }}</div>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-3 py-3">No</th>
                            <th class="px-3 py-3">Shop</th>
                            <th class="px-3 py-3">Cheque Bank</th>
                            <th class="px-3 py-3">Cheque Date</th>
                            <th class="px-3 py-3">Reference</th>
                            <th class="px-3 py-3 text-right">Amount</th>
                            <th class="px-3 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($chequePayments as $payment)
                            <tr>
                                <td class="px-3 py-3 font-mono font-bold text-slate-600">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3 font-bold text-slate-900">
                                    <a href="{{ route('admin.cashbook.finance.journal.show', $payment) }}" class="no-print hover:text-emerald-700">{{ $payment->shop?->name ?? 'Shop' }}</a>
                                    <span class="hidden print:inline">{{ $payment->shop?->name ?? 'Shop' }}</span>
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-700">{{ $payment->cheque_bank_name ?: '-' }}</td>
                                <td class="px-3 py-3 font-mono font-bold text-slate-700">{{ $payment->cheque_date?->format('Y-m-d') ?: '-' }}</td>
                                <td class="px-3 py-3 font-mono font-bold text-slate-600">{{ $payment->payment_reference ?: '-' }}</td>
                                <td class="px-3 py-3 text-right font-mono font-extrabold text-emerald-700">₹{{ number_format($payment->requested_amount, 2) }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-black uppercase text-amber-700">{{ $payment->chequeStatusLabel() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-sm font-bold text-slate-400">No cheque payments waiting for bank submission.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-300 text-xs font-black text-slate-950">
                            <td colspan="5" class="px-3 py-3 text-right">Total</td>
                            <td class="px-3 py-3 text-right font-mono">₹{{ number_format($totals['amount'], 2) }}</td>
                            <td class="px-3 py-3">{{ $totals['count'] }} cheques</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-8 text-xs font-semibold text-slate-600 sm:grid-cols-3">
                <div class="border-t border-slate-300 pt-2">Prepared By</div>
                <div class="border-t border-slate-300 pt-2">Bank Received Seal</div>
                <div class="border-t border-slate-300 pt-2">Verified By</div>
            </div>
        </section>
    </div>
@endsection
