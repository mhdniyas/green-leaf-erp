@extends('purchase-manager.layouts.app')

@section('title', 'Shop Daily Invoices')
@section('page_title', 'Shop Daily Invoices')
@section('page_description', 'Review generated shop invoices, delivery impact, and payment balances.')

@section('content')
    @php
        $switchDate = $selectedDate ?? $todayDate;
        $switchCarbonDate = \Illuminate\Support\Carbon::parse($switchDate);
        $prevDate = $switchCarbonDate->copy()->subDay()->toDateString();
        $nextDate = $switchCarbonDate->copy()->addDay()->toDateString();
    @endphp
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Purchasing</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Shop Daily Invoices</h1>
                <p class="mt-1 text-sm text-slate-600">Review generated shop invoices, delivery impact, and payment balances.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                {{ $invoices->total() }} invoices
            </span>
        </div>

        <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
            <form method="GET" action="{{ route('purchasing.shop-invoices.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <a href="{{ route('purchasing.shop-invoices.index', ['tab' => $tab, 'date' => $prevDate]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">
                    Prev
                </a>
                <input type="date" name="date" value="{{ $switchDate }}" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-900">
                <a href="{{ route('purchasing.shop-invoices.index', ['tab' => $tab, 'date' => $todayDate]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 hover:bg-emerald-50">
                    Today
                </a>
                <a href="{{ route('purchasing.shop-invoices.index', ['tab' => $tab, 'date' => $nextDate]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">
                    Next
                </a>
                <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">
                    {{ $switchCarbonDate->format('d M Y') }}
                </span>
            </form>
        </div>

        <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
            <div class="grid grid-cols-2 gap-2 rounded-[1.5rem] bg-slate-100 p-1.5">
                <a
                    href="{{ route('purchasing.shop-invoices.index', ['tab' => 'all', 'date' => $selectedDate]) }}"
                    class="{{ $tab === 'all' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500' }} rounded-[1.1rem] px-4 py-3 text-center text-xs font-black uppercase tracking-[0.16em] transition"
                >
                    All Invoices
                    <span class="mt-1 block text-[11px]">{{ $allInvoicesCount }}</span>
                </a>
                <a
                    href="{{ route('purchasing.shop-invoices.index', ['tab' => 'delivery-review', 'date' => $selectedDate]) }}"
                    class="{{ $tab === 'delivery-review' ? 'bg-white text-amber-700 shadow-sm' : 'text-slate-500' }} rounded-[1.1rem] px-4 py-3 text-center text-xs font-black uppercase tracking-[0.16em] transition"
                >
                    Delivery Review
                    <span class="mt-1 block text-[11px]">{{ $deliveryReviewCount }}</span>
                </a>
            </div>
        </div>

        @if ($invoices->isEmpty())
            <div class="px-5 py-16 text-center text-sm text-slate-500">
                {{ $tab === 'delivery-review' ? 'No delivery reviews are waiting for approval.' : 'No shop invoices have been generated yet for this date.' }}
            </div>
        @else
            <div class="space-y-3 p-4 md:hidden">
                @foreach ($invoices as $invoice)
                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-xs font-black text-cyan-700">{{ $invoice->invoice_number }}</p>
                                <h2 class="mt-1 text-base font-black text-slate-950">{{ $invoice->shop?->name }}</h2>
                                <p class="mt-1 text-xs text-slate-500">{{ $invoice->business_date->format('d M Y') }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="text-sm font-bold text-slate-900">Open</a>
                                <a href="{{ route('purchasing.shop-invoices.pdf', $invoice) }}" target="_blank" class="text-[11px] font-black uppercase tracking-[0.14em] text-cyan-700">PDF</a>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-white px-2.5 py-1 font-black text-slate-700">{{ str($invoice->delivery_status)->replace('_', ' ')->title() }}</span>
                            <span class="rounded-full bg-white px-2.5 py-1 font-black text-slate-700">{{ str($invoice->payment_status)->replace('_', ' ')->title() }}</span>
                            @if ($invoice->order?->hasPendingDeliveryReview())
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 font-black text-amber-800">Needs Admin Review</span>
                            @endif
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Final</p>
                                <p class="mt-1 font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                                <p class="mt-1 font-black text-red-600">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Invoice</th>
                            <th class="px-5 py-4">Shop</th>
                            <th class="px-5 py-4">Date</th>
                            <th class="px-5 py-4">Delivery</th>
                            <th class="px-5 py-4">Payment</th>
                            <th class="px-5 py-4 text-right">Final</th>
                            <th class="px-5 py-4 text-right">Balance</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="px-5 py-4 font-mono font-black text-cyan-700">{{ $invoice->invoice_number }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-950">{{ $invoice->shop?->name }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $invoice->business_date->format('d M Y') }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ str($invoice->delivery_status)->replace('_', ' ')->title() }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ str($invoice->payment_status)->replace('_', ' ')->title() }}</td>
                                <td class="px-5 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</td>
                                <td class="px-5 py-4 text-right font-black text-red-600">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('purchasing.shop-invoices.pdf', $invoice) }}" target="_blank" class="font-black uppercase tracking-[0.14em] text-slate-500 hover:text-slate-700">PDF</a>
                                        <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="font-bold text-cyan-700 hover:text-cyan-900">Open</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $invoices->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
