@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — '. \Illuminate\Support\Carbon::parse($businessDate)->format('d M Y'))

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-12">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="p-2 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <i data-lucide="store" class="w-5 h-5"></i>
                </span>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 uppercase">
                    {{ $currentShop->name }} &mdash; {{ \Illuminate\Support\Carbon::parse($businessDate)->format('d M Y') }}
                </h1>
            </div>
            <p class="text-xs text-slate-500 font-medium mt-1">
                Daily operational collection and settlement position for 
                <span class="font-bold text-slate-800">{{ $currentShop->name }} ({{ $currentShop->code }})</span>
            </p>
        </div>

        <!-- Date & Shop Filter Controls -->
        <form method="GET" action="{{ route('admin.cashbook.shop.show', $currentShop->slug ?: $currentShop->shop_id) }}" id="shop-day-filter-form" class="flex items-center gap-2 flex-wrap">
            
            <!-- Quick Day Buttons -->
            <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200 text-xs font-bold">
                <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'date' => today()->toDateString()]) }}"
                   class="px-3 py-1.5 rounded-lg transition {{ $businessDate === today()->toDateString() ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900' }}">
                    Today
                </a>
                <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'date' => today()->subDay()->toDateString()]) }}"
                   class="px-3 py-1.5 rounded-lg transition {{ $businessDate === today()->subDay()->toDateString() ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900' }}">
                    Yesterday
                </a>
            </div>

            <div class="flex items-center gap-1.5 bg-slate-100 px-3 py-1.5 rounded-xl border border-slate-200">
                <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                <input type="date" name="date" value="{{ $businessDate }}" onchange="document.getElementById('shop-day-filter-form').submit()"
                       class="bg-transparent text-xs font-mono font-bold text-slate-800 border-none focus:outline-none cursor-pointer">
            </div>

            <!-- Shop Dropdown Selector -->
            <div class="flex items-center">
                <select onchange="window.location.href='/admin/cashbook/shops/' + this.value + '?date={{ $businessDate }}'"
                        class="bg-slate-50 text-xs font-bold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-600 cursor-pointer">
                    @foreach($shops as $shopOption)
                        <option value="{{ $shopOption->slug ?: $shopOption->shop_id }}" {{ $currentShop->shop_id == $shopOption->shop_id ? 'selected' : '' }}>
                            {{ $shopOption->name }} ({{ $shopOption->code }})
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <!-- 1. TODAY'S SETTLEMENT KPI BANNER -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">TODAY'S SETTLEMENT</h2>
            <span class="text-xs font-bold text-slate-500 font-mono">{{ \Illuminate\Support\Carbon::parse($businessDate)->format('d M Y') }}</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <!-- Gross Sales -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Gross Sales</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 mt-1">
                    ₹{{ number_format($dailySettlement['gross_sales'] ?? 0, 2) }}
                </div>
            </div>

            <!-- Company Received -->
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Company Received</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-emerald-900 mt-1">
                    ₹{{ number_format($dailySettlement['company_receipt_status']['verified_received'] ?? 0, 2) }}
                </div>
            </div>

            <!-- Needs Verification -->
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-100">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700">Needs Verification</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-amber-900 mt-1">
                    ₹{{ number_format($dailySettlement['company_receipt_status']['pending_verification'] ?? 0, 2) }}
                </div>
            </div>

            <!-- Cash With Shop -->
            <div class="p-4 rounded-2xl bg-sky-50 border border-sky-100">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-sky-700">Cash With Shop</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-sky-900 mt-1">
                    ₹{{ number_format($dailySettlement['company_receipt_status']['cash_still_with_shop'] ?? 0, 2) }}
                </div>
            </div>

            <!-- Floating Cheques -->
            <div class="p-4 rounded-2xl bg-purple-50 border border-purple-100">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-purple-700">Floating Cheques</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-purple-900 mt-1">
                    ₹{{ number_format($dailySettlement['company_receipt_status']['floating_cheques'] ?? 0, 2) }}
                </div>
            </div>

            <!-- Still To Settle -->
            <div class="p-4 rounded-2xl bg-slate-900 text-white border border-slate-800">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-300">Still To Settle</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-emerald-400 mt-1">
                    ₹{{ number_format($dailySettlement['settlement_summary']['outstanding_to_settle'] ?? 0, 2) }}
                </div>
            </div>
        </div>
    </div>

    <!-- 2-COLUMN MAIN CONTENT: HOW SALES WERE COLLECTED & ADJUSTMENTS -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left 7 Cols: HOW SALES WERE COLLECTED -->
        <div class="lg:col-span-7 space-y-4">
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-extrabold uppercase tracking-tight text-slate-900">
                        HOW SALES WERE COLLECTED
                    </h2>
                    <span class="text-xs font-bold text-slate-500 font-mono">
                        {{ count($dailySettlement['collections'] ?? []) }} entries
                    </span>
                </div>

                @if(empty($dailySettlement['collections']))
                    <div class="py-10 text-center text-slate-400 text-xs font-bold">
                        No collections recorded for this business date.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($dailySettlement['collections'] as $col)
                            <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/60 hover:bg-slate-50 transition-all flex items-center justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-sm text-slate-900">
                                            {{ $col['payment_method'] ?? $col['category_name'] }}
                                        </span>
                                    </div>
                                    <div class="text-xs font-medium text-slate-500">
                                        @if(!empty($col['destination_name']))
                                            <span class="font-bold text-slate-700">{{ $col['destination_name'] }}</span>
                                        @elseif(!empty($col['location_name']))
                                            <span class="font-bold text-slate-700">{{ $col['location_name'] }}</span>
                                        @elseif(!empty($col['destination_account']))
                                            <span class="font-bold text-slate-700">→ {{ $col['destination_account'] }}</span>
                                        @else
                                            <span>📍 {{ $currentShop->name }} Shop</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-base font-black font-mono text-slate-900">
                                            ₹{{ number_format($col['amount'], 2) }}
                                        </div>
                                        <div>
                                            @if($col['status'] === 'VERIFIED')
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                                    <i data-lucide="check" class="w-3 h-3"></i> RECEIVED
                                                </span>
                                            @elseif($col['status'] === 'NEEDS VERIFICATION')
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">
                                                    <i data-lucide="alert-triangle" class="w-3 h-3"></i> NEEDS VERIFICATION
                                                </span>
                                            @elseif($col['status'] === 'CASH WITH SHOP')
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-200">
                                                    <i data-lucide="store" class="w-3 h-3"></i> CASH WITH SHOP
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                                                    {{ $col['status'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Single allowed action: View -->
                                    <a href="{{ route('admin.cashbook.transaction.show', $col['id']) }}"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-bold transition border border-slate-200 shadow-xs cursor-pointer flex-shrink-0">
                                        <span>View</span>
                                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- SETTLEMENT ADJUSTMENTS -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-extrabold uppercase tracking-tight text-slate-900">
                        SETTLEMENT ADJUSTMENTS
                    </h2>
                    <span class="text-xs font-bold text-slate-500 font-mono">
                        {{ count($dailySettlement['settlement_adjustments'] ?? []) }} entries
                    </span>
                </div>

                @if(empty($dailySettlement['settlement_adjustments']))
                    <div class="py-6 text-center text-slate-400 text-xs font-bold">
                        No settlement adjustments recorded for this business date.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($dailySettlement['settlement_adjustments'] as $adj)
                            <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/60 flex items-center justify-between gap-3">
                                <div>
                                    <span class="font-extrabold text-sm text-slate-900">{{ $adj['name'] }}</span>
                                    <p class="text-xs font-semibold text-slate-400 mt-0.5">
                                        @if($adj['funding_source'] === 'sales')
                                            Deducted from sales payable
                                        @elseif($adj['funding_source'] === 'company')
                                            No payable effect
                                        @else
                                            Funded via {{ $adj['funding_source'] }}
                                        @endif
                                    </p>
                                </div>

                                <div class="text-right">
                                    <div class="text-base font-black font-mono {{ ($adj['effect_on_payable'] ?? 0) < 0 ? 'text-rose-600' : 'text-slate-800' }}">
                                        {{ ($adj['effect_on_payable'] ?? 0) < 0 ? '-' : '' }}₹{{ number_format($adj['amount'], 2) }}
                                    </div>
                                    <span class="inline-block text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase {{ $adj['status'] === 'FROM SALES' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                        {{ $adj['status'] }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Right 5 Cols: SETTLEMENT SUMMARY & BREAKDOWN -->
        <div class="lg:col-span-5 space-y-6">

            <!-- SETTLEMENT SUMMARY -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-extrabold uppercase tracking-tight text-slate-900">
                        SETTLEMENT SUMMARY
                    </h2>
                </div>

                <div class="space-y-3 text-xs font-bold">
                    <div class="flex items-center justify-between text-slate-700">
                        <span>Gross Sales</span>
                        <span class="font-mono text-sm font-extrabold text-slate-900">
                            ₹{{ number_format($dailySettlement['settlement_summary']['gross_sales'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-rose-600">
                        <span>Less: Sales-funded deductions</span>
                        <span class="font-mono text-sm font-extrabold">
                            -₹{{ number_format($dailySettlement['settlement_summary']['settlement_deductions'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="border-t border-slate-200 pt-3 flex items-center justify-between text-slate-900 font-extrabold">
                        <span>Expected Payable</span>
                        <span class="font-mono text-base font-black">
                            ₹{{ number_format($dailySettlement['settlement_summary']['expected_payable'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-emerald-700">
                        <span>Less: Company Received</span>
                        <span class="font-mono text-sm font-extrabold">
                            -₹{{ number_format($dailySettlement['settlement_summary']['verified_company_received'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="border-t-2 border-slate-900 pt-3 flex items-center justify-between text-slate-900 font-black">
                        <span class="text-sm uppercase tracking-wide">Still To Settle</span>
                        <span class="font-mono text-lg font-black text-emerald-700">
                            ₹{{ number_format($dailySettlement['settlement_summary']['outstanding_to_settle'] ?? 0, 2) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- BREAKDOWN OF STILL TO SETTLE -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-extrabold uppercase tracking-tight text-slate-900">
                        BREAKDOWN OF STILL TO SETTLE
                    </h2>
                </div>

                <div class="space-y-3 text-xs font-bold">
                    <div class="flex items-center justify-between text-amber-800 bg-amber-50/80 p-2.5 rounded-xl border border-amber-100">
                        <span>Needs Verification</span>
                        <span class="font-mono text-sm font-black text-amber-900">
                            ₹{{ number_format($dailySettlement['company_receipt_status']['pending_verification'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-sky-800 bg-sky-50/80 p-2.5 rounded-xl border border-sky-100">
                        <span>Cash With Shop</span>
                        <span class="font-mono text-sm font-black text-sky-900">
                            ₹{{ number_format($dailySettlement['company_receipt_status']['cash_still_with_shop'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-purple-800 bg-purple-50/80 p-2.5 rounded-xl border border-purple-100">
                        <span>Floating Cheques</span>
                        <span class="font-mono text-sm font-black text-purple-900">
                            ₹{{ number_format($dailySettlement['company_receipt_status']['floating_cheques'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="border-t border-slate-200 pt-3 flex items-center justify-between text-slate-900 font-black">
                        <span>Total</span>
                        <span class="font-mono text-base font-black text-slate-900">
                            ₹{{ number_format(($dailySettlement['company_receipt_status']['pending_verification'] ?? 0) + ($dailySettlement['company_receipt_status']['cash_still_with_shop'] ?? 0) + ($dailySettlement['company_receipt_status']['floating_cheques'] ?? 0), 2) }}
                        </span>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Collapsible Monthly Financial Overview (Preserves Backward Compatibility) -->
    <details class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4" {{ request()->has('month') ? 'open' : '' }}>
        <summary class="cursor-pointer text-xs font-black uppercase tracking-wider text-slate-500 hover:text-slate-900 flex items-center justify-between">
            <span>Shop Financial Overview &amp; Monthly Summary ({{ \Illuminate\Support\Carbon::parse($monthStart)->format('F Y') }})</span>
            <span class="text-[10px] font-bold px-2 py-1 rounded-lg bg-slate-100 text-slate-600">Toggle Monthly Overview</span>
        </summary>

        <div class="pt-4 border-t border-slate-100 space-y-6">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $currentShop->uuid, 'month' => $month]) }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800">
                    <i data-lucide="wallet" class="h-4 w-4"></i> Receive Payment
                </a>
                <a href="{{ route('admin.cashbook.reports.mobile-ledger', ['shop' => $currentShop->uuid, 'timeframe' => 'monthly', 'date' => $monthEnd]) }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-xs font-black text-slate-700 hover:bg-slate-50">
                    <i data-lucide="book-open" class="h-4 w-4"></i> View Ledger
                </a>
            </div>

            @php
                $shopPosition = (float) ($position->closing_shop_position ?? 0);
                $companyPending = (float) ($position->closing_company_pending ?? 0);
                $pettyBalance = (float) ($position->closing_petty ?? 0);
                $netBalance = (float) ($position->total_sales ?? 0) - (float) ($position->total_expense ?? 0);
                $awaitingBank = $floatingPayments;
                $awaitingSettlement = max(0, $cashBankReceived - $ledgerSettled);
            @endphp

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-slate-300 bg-slate-950 p-4 text-white">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pending Payable to Company</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black">₹{{ number_format($pendingPayable, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Payment Received</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-amber-950">₹{{ number_format($cashBankReceived, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-violet-700">Company → Shop Pending</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-violet-950">₹{{ number_format($companyPending, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-sky-700">GL Bill Pending</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-sky-950">₹{{ number_format($glBillPending, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-rose-700">Current Net Balance</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-rose-950">₹{{ number_format($netBalance, 2) }}</p>
                </article>
            </div>

            <!-- Recent Payments Bound Listing -->
            <div class="border border-slate-100 rounded-2xl p-4 divide-y divide-slate-100">
                @foreach($recentPayments as $payment)
                    <div class="py-2 flex items-center justify-between text-xs font-mono">
                        <span class="font-bold text-slate-800">{{ $payment->payment_reference }}</span>
                        <span>₹{{ number_format((float) $payment->requested_amount, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </details>

</div>
@endsection
