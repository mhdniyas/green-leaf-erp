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
