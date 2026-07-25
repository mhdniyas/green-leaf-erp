@extends('shop-owner.layouts.app')

@section('title', 'Accounting History')
@section('page_title', 'Accounting History')
@section('page_description', 'Review previous bill approvals, balances, payment requests, and for owned shops the cashbook approval history.')
@php
    $breadcrumbs = [['label' => 'Accounting', 'url' => route('shop-owner.accounting.index', ['tab' => $tab])], ['label' => 'History']];
@endphp

@section('page_actions')
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.accounting.index', ['tab' => $tab]), 'label' => 'Back', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
@endsection

@section('content')
    <div class="space-y-6">
        @include('shop-owner.accounting.partials.tabs', ['shop' => $shop, 'tab' => $tab])

        @include('shop-owner.partials.date-range-filter', [
            'action' => route('shop-owner.accounting.history'),
            'hidden' => ['tab' => $tab],
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
            'clearUrl' => route('shop-owner.accounting.history', ['tab' => $tab]),
        ])

        @php($totals = $moneyReport['totals'])
        <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Shop Money Report</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Cash bills and cashbook together</h2>
                </div>
                <div @class([
                    'rounded-2xl px-4 py-3 text-right',
                    'bg-emerald-50 text-emerald-700' => $totals['combined_net'] >= 0,
                    'bg-rose-50 text-rose-700' => $totals['combined_net'] < 0,
                ])>
                    <p class="text-[10px] font-black uppercase tracking-[0.16em]">Net</p>
                    <p class="mt-1 text-lg font-black">Rs. {{ number_format(abs($totals['combined_net']), 2) }}</p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ([
                    ['label' => 'Cash Bills', 'value' => $totals['bill_total'], 'caption' => 'Total bills', 'tone' => 'text-rose-700'],
                    ['label' => 'Bill Paid', 'value' => $totals['bill_paid'], 'caption' => 'Approved paid', 'tone' => 'text-emerald-700'],
                    ['label' => 'Shop Cash In', 'value' => $totals['shop_cash_in'], 'caption' => 'Given to shop', 'tone' => 'text-emerald-700'],
                    ['label' => 'Cashbook In', 'value' => $totals['cashbook_income'], 'caption' => 'Approved income', 'tone' => 'text-emerald-700'],
                    ['label' => 'Cashbook Out', 'value' => $totals['cashbook_expense'], 'caption' => 'Approved expense', 'tone' => 'text-rose-700'],
                ] as $card)
                    <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-2 text-lg font-black {{ $card['tone'] }}">Rs. {{ number_format((float) $card['value'], 2) }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $card['caption'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">All Transactions</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Money in and out to shop</h2>
                </div>
                <p class="text-sm font-bold text-slate-500">{{ number_format($moneyReport['transactions'] instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $moneyReport['transactions']->total() : $moneyReport['transactions']->count()) }} transaction(s)</p>
            </div>

            <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Transaction</th>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">In</th>
                            <th class="px-4 py-3 text-right">Out</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($moneyReport['transactions'] as $transaction)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-600">{{ \Illuminate\Support\Carbon::parse($transaction['date'])->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-black text-slate-950">{{ $transaction['label'] }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $transaction['detail'] }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">
                                        {{ str($transaction['source'])->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-600">{{ $transaction['status'] }}</td>
                                <td class="px-4 py-3 text-right font-black text-emerald-700">
                                    @if ($transaction['direction'] === 'IN')
                                        Rs. {{ number_format($transaction['amount'], 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-black text-rose-700">
                                    @if ($transaction['direction'] === 'OUT')
                                        Rs. {{ number_format($transaction['amount'], 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center font-bold text-slate-500">No shop transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($moneyReport['transactions'] instanceof \Illuminate\Contracts\Pagination\Paginator && $moneyReport['transactions']->hasPages())
                <div class="mt-5">{{ $moneyReport['transactions']->links() }}</div>
            @endif
        </section>

        @if ($tab === 'bills')
            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="overflow-x-auto rounded-[1.5rem] border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Invoice</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Bill</th>
                                <th class="px-4 py-3 text-right">Paid</th>
                                <th class="px-4 py-3 text-right">Due</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($invoiceHistory as $invoice)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $invoice->invoice_number }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-600">{{ $invoice->business_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-rose-700">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</td>
                                    <td class="px-4 py-3">@include('shop-owner.components.status-badge', ['label' => str($invoice->payment_status)->replace('_', ' ')->title(), 'tone' => (float) $invoice->balance_amount > 0 ? 'warning' : 'success'])</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center font-bold text-slate-500">No bill history found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($invoiceHistory->hasPages())
                    <div class="mt-5">{{ $invoiceHistory->withQueryString()->links() }}</div>
                @endif
            </section>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="overflow-x-auto rounded-[1.5rem] border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Invoice</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3">Requested On</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Admin Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($paymentRequestHistory as $paymentRequest)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $paymentRequest->invoice?->invoice_number }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">
                                        <span class="block text-[10px] uppercase tracking-[0.14em] text-slate-500">{{ $paymentRequest->request_type === 'admin_manual' ? 'Admin paid' : 'Requested' }}</span>
                                        Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-600">{{ $paymentRequest->created_at?->format('d M Y h:i A') }}</td>
                                    <td class="px-4 py-3">@include('shop-owner.components.status-badge', ['label' => $paymentRequest->statusLabel(), 'tone' => $paymentRequest->statusTone()])</td>
                                    <td class="px-4 py-3 font-semibold text-slate-600">{{ $paymentRequest->admin_note ?: 'No note' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center font-bold text-slate-500">No payment request history found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($paymentRequestHistory->hasPages())
                    <div class="mt-5">{{ $paymentRequestHistory->withQueryString()->links() }}</div>
                @endif
            </section>
        @else
            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                @include('shop-owner.accounting.partials.history-table', ['entries' => $entries])
            </section>
        @endif
    </div>
@endsection
