@extends('shop-owner.layouts.app')

@section('title', 'Accounting')
@section('page_title', 'Shop Accounting')
@section('page_description', 'Track delivery bills, request payment approvals, and for owned shops keep a daily ledger with sales modes, warehouse invoice expenses, and manual spend in one workflow.')
@php
    $breadcrumbs = [['label' => 'Accounting']];
@endphp

@section('page_actions')
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.accounting.history', ['tab' => $tab]), 'label' => 'History', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
@endsection

@section('content')
    @php
        $hasEntry = $entry instanceof \App\Models\ShopAccountingEntry;
        $canEdit = ! $hasEntry || $entry->canBeEditedByShopOwner();
        $recheckLines = $hasEntry
            ? $entry->lines->filter(fn ($line) => $line->review_status === 'recheck_required')->values()
            : collect();
        $cashbookInitialLines = collect(old('lines', $hasEntry
            ? $entry->lines->map(fn ($line) => [
                'shop_accounting_category_id' => (string) $line->shop_accounting_category_id,
                'amount' => (string) $line->amount,
                'description' => (string) ($line->description ?? ''),
            ])->all()
            : []))
            ->filter(fn ($line) => is_array($line))
            ->values();
        $cashbookCategories = $availableCategories->map(fn ($category) => [
            'id' => (int) $category->id,
            'type' => (string) $category->type,
            'cash_effect' => (bool) $category->cash_effect,
            'purpose' => (string) $category->purpose,
            'name' => (string) $category->name,
        ])->values();
        $calculatedClosing = (float) ($receiptSummary['entered_closing'] ?? $receiptSummary['expected_closing']);
        $calculatedClosingTone = $calculatedClosing < 0 ? 'rose' : 'emerald';
    @endphp

    <div class="space-y-6">
        @include('shop-owner.accounting.partials.tabs', ['shop' => $shop, 'tab' => $tab])

        @if ($tab === 'bills')
            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">Daily Delivery Bills</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Bills and balance to be paid</h2>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Each delivered bill shows the final amount, paid amount, and pending balance. Request full due or send a custom payment amount for approval.</p>
                    </div>
                    <p class="rounded-full bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                        {{ $billingSummary['open_bills'] }} open bill{{ $billingSummary['open_bills'] === 1 ? '' : 's' }}
                    </p>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total Billed</p>
                    <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($billingSummary['total_billed'], 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Paid</p>
                    <p class="mt-2 text-3xl font-black text-emerald-700">Rs. {{ number_format($billingSummary['total_paid'], 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                    <p class="mt-2 text-3xl font-black text-rose-700">Rs. {{ number_format($billingSummary['total_balance'], 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Requests</p>
                    <p class="mt-2 text-3xl font-black text-slate-950">{{ $paymentRequests->total() }}</p>
                </div>
            </section>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Current Bills</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950">Daily bill table</h3>
                    </div>
                    <a href="{{ route('shop-owner.accounting.history', ['tab' => 'bills']) }}" class="text-sm font-black text-emerald-700">Full history</a>
                </div>

                <div class="mt-5 space-y-4">
                    @forelse ($invoices as $invoice)
                        @php
                            $latestRequest = $invoice->paymentRequests->first();
                            $hasPendingRequest = $latestRequest && $latestRequest->status === 'pending';
                        @endphp
                        <article class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.18em] text-cyan-700">{{ $invoice->invoice_number }}</p>
                                    <h4 class="mt-2 text-lg font-black text-slate-950">{{ $invoice->business_date->format('d M Y') }}</h4>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @include('shop-owner.components.status-badge', ['label' => str($invoice->payment_status)->replace('_', ' ')->title(), 'tone' => (float) $invoice->balance_amount > 0 ? 'warning' : 'success'])
                                        @if ($latestRequest)
                                            @include('shop-owner.components.status-badge', ['label' => $latestRequest->statusLabel(), 'tone' => $latestRequest->statusTone()])
                                        @endif
                                    </div>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Bill</p>
                                            <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Paid</p>
                                            <p class="mt-1 text-sm font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Due</p>
                                            <p class="mt-1 text-sm font-black text-rose-700">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="w-full max-w-xl rounded-[1.5rem] border border-slate-200 bg-white p-4">
                                    @if ((float) $invoice->balance_amount <= 0)
                                        <p class="text-sm font-black text-emerald-700">This bill is already fully settled.</p>
                                    @elseif (! $shop->isOwnedAccountingEnabled())
                                        <p class="text-sm font-black text-slate-800">Submit payment from Finance > Payments.</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-600">Accounting approval updates this bill and posts the journal entry.</p>
                                        <a href="{{ route('shop-owner.finance.index', ['tab' => 'payments']) }}" class="mt-3 inline-flex h-10 items-center rounded-2xl bg-emerald-600 px-4 text-sm font-black text-white transition hover:bg-emerald-500">
                                            Open Payments
                                        </a>
                                    @else
                                        <p class="text-sm font-black text-cyan-800">Approved bills are included automatically in Cash Debit.</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-600">Pay the company from Finance > Payments after checking the latest closing balance.</p>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        @include('shop-owner.components.empty-state', ['title' => 'No delivery bills yet', 'description' => 'Bills will appear here after delivery invoices are generated.'])
                    @endforelse
                </div>

                @if ($invoices->hasPages())
                    <div class="mt-5">{{ $invoices->withQueryString()->links() }}</div>
                @endif
            </section>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Payment Requests</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950">Latest approval updates</h3>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($paymentRequests as $paymentRequest)
                        <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-black text-slate-950">{{ $paymentRequest->invoice?->invoice_number }}</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-600">
                                        {{ $paymentRequest->request_type === 'admin_manual' ? 'Admin recorded paid' : 'Requested' }}
                                        Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}
                                    </p>
                                    @if ($paymentRequest->shop_note)
                                        <p class="mt-2 text-sm font-semibold text-slate-600">{{ $paymentRequest->shop_note }}</p>
                                    @endif
                                    @if ($paymentRequest->admin_note)
                                        <p class="mt-2 text-sm font-semibold text-slate-700">Admin: {{ $paymentRequest->admin_note }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-col items-start gap-2 sm:items-end">
                                    @include('shop-owner.components.status-badge', ['label' => $paymentRequest->statusLabel(), 'tone' => $paymentRequest->statusTone()])
                                    <p class="text-xs font-semibold text-slate-500">{{ $paymentRequest->created_at?->format('d M Y h:i A') }}</p>
                                </div>
                            </div>
                        </article>
                    @empty
                        @include('shop-owner.components.empty-state', ['title' => 'No payment requests yet', 'description' => 'Send a payment request from any unpaid bill to start the approval flow.'])
                    @endforelse
                </div>
            </section>
        @else
            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">{{ strtoupper($shop->accounting_mode) }} Shop</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Daily Shop Receipt</h2>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Record the day like a bill book: opening balance, cash credits, cash debits, non-cash income, and closing balance.</p>
                    </div>

                    <form method="GET" action="{{ route('shop-owner.accounting.index') }}" class="grid gap-2 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-2 sm:grid-cols-5">
                        <input type="hidden" name="tab" value="cashbook">
                        <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</span>
                            <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                        </label>
                        <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">From Optional</span>
                            <input type="date" name="start_date" value="{{ request('start_date') }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                        </label>
                        <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">To Optional</span>
                            <input type="date" name="end_date" value="{{ request('end_date') }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                        </label>
                        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800">Update Ledger</button>
                        <a href="{{ route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => today()->toDateString()]) }}" class="inline-flex h-14 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-800 transition hover:bg-slate-50">Today</a>
                    </form>
                </div>
            </section>

            <section class="grid grid-cols-3 gap-2 sm:gap-3 xl:grid-cols-3">
                <div class="min-w-0 rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[9px]">Total Income</p>
                    <p class="mt-2 truncate text-[13px] font-black leading-none text-slate-950 tabular-nums sm:text-base">Rs. {{ number_format($incomeTotal, 2) }}</p>
                </div>
                <div class="min-w-0 rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[9px]">Cash Sales</p>
                    <p class="mt-2 truncate text-[13px] font-black leading-none text-emerald-700 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['cash_credit'], 2) }}</p>
                </div>
                <div class="min-w-0 rounded-[1.1rem] border border-emerald-200 bg-emerald-50 p-3 shadow-sm">
                    <p class="text-[8px] font-black uppercase tracking-[0.1em] text-emerald-700 sm:text-[9px]">Cash Given To Shop</p>
                    <p class="mt-2 truncate text-[13px] font-black leading-none text-emerald-800 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['cash_given_to_shop'], 2) }}</p>
                </div>
                <div class="min-w-0 rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[9px]">Non-Cash</p>
                    <p class="mt-2 truncate text-[13px] font-black leading-none text-cyan-700 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['non_cash_income'], 2) }}</p>
                </div>
                <div class="min-w-0 rounded-[1.1rem] border border-amber-200 bg-amber-50 p-3 shadow-sm">
                    <p class="text-[8px] font-black uppercase tracking-[0.1em] text-amber-700 sm:text-[9px]">Payment To Company</p>
                    <p class="mt-2 truncate text-[13px] font-black leading-none text-amber-800 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['payment_to_company'], 2) }}</p>
                </div>
                <div class="min-w-0 rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[9px]">Cash Debit</p>
                    <p class="mt-2 truncate text-[13px] font-black leading-none text-rose-700 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['cash_debit'], 2) }}</p>
                </div>
                <div class="min-w-0 rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[9px]">Expected</p>
                    <p class="mt-2 truncate text-[13px] font-black leading-none text-slate-950 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['expected_closing'], 2) }}</p>
                </div>
                <div class="min-w-0 rounded-[1.1rem] border {{ $calculatedClosingTone === 'rose' ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }} p-3 shadow-sm">
                    <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[9px]">Closing</p>
                    <p data-cashbook-closing-display class="mt-2 truncate text-[13px] font-black leading-none tabular-nums {{ $calculatedClosingTone === 'rose' ? 'text-rose-700' : 'text-emerald-700' }} sm:text-base">
                        Rs. {{ number_format($calculatedClosing, 2) }}
                    </p>
                </div>
                <div class="min-w-0 rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[9px]">Status</p>
                    <div class="mt-2">
                        @if ($hasEntry)
                            @include('shop-owner.components.status-badge', ['label' => $entry->statusLabel(), 'tone' => $entry->statusTone()])
                        @else
                            @include('shop-owner.components.status-badge', ['label' => 'No Entry', 'tone' => 'neutral'])
                        @endif
                    </div>
                </div>
            </section>

            @php
                $ledgerStatusTabs = [
                    'draft' => 'Draft / Today',
                    'submitted' => 'Submitted',
                    'approved' => 'Approved',
                    'recheck' => 'Recheck Required',
                ];
            @endphp
            <section id="shop-owner-ledger-status-tabs" class="rounded-[2rem] border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap gap-2 rounded-2xl bg-slate-100 p-1">
                    @foreach ($ledgerStatusTabs as $statusKey => $statusLabel)
                        <button type="button" data-ledger-status-trigger="{{ $statusKey }}" aria-selected="{{ $ledgerStatusTab === $statusKey ? 'true' : 'false' }}" class="inline-flex h-10 items-center rounded-xl px-4 text-sm font-black transition {{ $ledgerStatusTab === $statusKey ? 'bg-slate-950 text-white' : 'text-slate-700 hover:bg-white' }}">
                            {{ $statusLabel }}
                        </button>
                    @endforeach
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-2xl border border-slate-200 bg-slate-50 p-1">
                            <a href="{{ route('shop-owner.accounting.index', array_filter(['tab' => 'cashbook', 'ledger_status' => $ledgerStatusTab, 'date' => $selectedDate->format('Y-m-d'), 'start_date' => request('start_date'), 'end_date' => request('end_date')])) }}" class="inline-flex h-10 items-center rounded-xl px-4 text-sm font-black transition {{ $ledgerSourceFilter === 'all' ? 'bg-slate-950 text-white' : 'text-slate-700 hover:bg-white' }}">
                                All
                            </a>
                            <a href="{{ route('shop-owner.accounting.index', array_filter(['tab' => 'cashbook', 'ledger_status' => $ledgerStatusTab, 'date' => $selectedDate->format('Y-m-d'), 'start_date' => request('start_date'), 'end_date' => request('end_date'), 'ledger_source' => 'greenleaf_direct'])) }}" class="inline-flex h-10 items-center rounded-xl px-4 text-sm font-black transition {{ $ledgerSourceFilter === 'greenleaf_direct' ? 'bg-cyan-600 text-white' : 'text-cyan-700 hover:bg-white' }}">
                                GreenLeaf Direct
                            </a>
                        </div>
                        <a href="{{ route('shop-owner.accounting.history', ['tab' => 'cashbook']) }}" class="text-sm font-black text-emerald-700">Open full history</a>
                    </div>
                </div>

                <div class="mt-4">
                    @foreach ($ledgerStatusTabs as $statusKey => $statusLabel)
                        @php $statusLedgerEntries = $ledgerEntriesByStatus->get($statusKey, collect()); @endphp
                        <div data-ledger-status-panel="{{ $statusKey }}" @class(['space-y-3', 'hidden' => $ledgerStatusTab !== $statusKey])>
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Ledger Status</p>
                                    <h3 class="mt-1 text-lg font-black text-slate-950">{{ $statusLabel }}</h3>
                                </div>
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">{{ $statusLedgerEntries->count() }} day{{ $statusLedgerEntries->count() === 1 ? '' : 's' }}</p>
                            </div>

                            <div class="overflow-x-auto rounded-[1.5rem] border border-slate-200">
                                <table class="min-w-full text-left">
                                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                        <tr>
                                            <th class="px-4 py-3">Date</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3 text-right">Income</th>
                                            <th class="px-4 py-3 text-right">Cash Given</th>
                                            <th class="px-4 py-3 text-right">Paid Company</th>
                                            <th class="px-4 py-3 text-right">Manual Expense</th>
                                            <th class="px-4 py-3 text-right">Warehouse Invoice</th>
                                            <th class="px-4 py-3 text-right">Closing</th>
                                            <th class="px-4 py-3 text-right">Items</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-sm">
                                        @forelse ($statusLedgerEntries as $ledgerDay)
                                            <tr class="transition hover:bg-slate-50">
                                                <td class="px-4 py-3">
                                                    <a href="{{ route('shop-owner.accounting.index', ['tab' => 'cashbook', 'ledger_status' => $statusKey, 'date' => $ledgerDay['date'], 'start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="font-black text-slate-950">
                                                        {{ \Illuminate\Support\Carbon::parse($ledgerDay['date'])->format('d M Y') }}
                                                    </a>
                                                </td>
                                                <td class="px-4 py-3">
                                                    @include('shop-owner.components.status-badge', ['label' => $ledgerDay['status_label'], 'tone' => $ledgerDay['status_tone']])
                                                </td>
                                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($ledgerDay['income'], 2) }}</td>
                                                <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format($ledgerDay['cash_given_to_shop'], 2) }}</td>
                                                <td class="px-4 py-3 text-right font-black text-amber-700">Rs. {{ number_format($ledgerDay['payment_to_company'], 2) }}</td>
                                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($ledgerDay['manual_expense'], 2) }}</td>
                                                <td class="px-4 py-3 text-right">
                                                    @if ($ledgerDay['warehouse_expense'] > 0)
                                                        <span class="mb-1 inline-flex rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">GreenLeaf Direct</span>
                                                    @endif
                                                    <p class="font-black text-rose-700">Rs. {{ number_format($ledgerDay['warehouse_expense'], 2) }}</p>
                                                </td>
                                                <td class="px-4 py-3 text-right font-black {{ $ledgerDay['closing'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($ledgerDay['closing'], 2) }}</td>
                                                <td class="px-4 py-3 text-right font-black text-slate-950">{{ $ledgerDay['items'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="px-4 py-8 text-center font-bold text-slate-500">No {{ strtolower($statusLabel) }} ledger days found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Daily Shop Receipt</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950">Opening + credit - debit = closing</h3>
                    </div>
                    @if ($receiptSummary['owner_funded'] > 0)
                        <p class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700">
                            Owner funded Rs. {{ number_format($receiptSummary['owner_funded'], 2) }}
                        </p>
                    @endif
                </div>

                <div class="mt-5 grid grid-cols-3 gap-2 sm:gap-3 xl:grid-cols-8">
                    <div class="min-w-0 rounded-[1.1rem] border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[9px]">Opening</p>
                        <p class="mt-2 truncate text-[13px] font-black leading-none text-slate-950 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['opening_balance'], 2) }}</p>
                    </div>
                    <div class="min-w-0 rounded-[1.1rem] border border-emerald-200 bg-emerald-50 p-3">
                        <p class="text-[8px] font-black uppercase tracking-[0.1em] text-emerald-700 sm:text-[9px]">Cash Sales</p>
                        <p class="mt-2 truncate text-[13px] font-black leading-none text-emerald-800 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['cash_credit'], 2) }}</p>
                    </div>
                    <div class="min-w-0 rounded-[1.1rem] border border-emerald-200 bg-emerald-50 p-3">
                        <p class="text-[8px] font-black uppercase tracking-[0.1em] text-emerald-700 sm:text-[9px]">Cash Given</p>
                        <p class="mt-2 truncate text-[13px] font-black leading-none text-emerald-800 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['cash_given_to_shop'], 2) }}</p>
                    </div>
                    <div class="min-w-0 rounded-[1.1rem] border border-cyan-200 bg-cyan-50 p-3">
                        <p class="text-[8px] font-black uppercase tracking-[0.1em] text-cyan-700 sm:text-[9px]">Non-Cash</p>
                        <p class="mt-2 truncate text-[13px] font-black leading-none text-cyan-800 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['non_cash_income'], 2) }}</p>
                    </div>
                    <div class="min-w-0 rounded-[1.1rem] border border-amber-200 bg-amber-50 p-3">
                        <p class="text-[8px] font-black uppercase tracking-[0.1em] text-amber-700 sm:text-[9px]">Paid Company</p>
                        <p class="mt-2 truncate text-[13px] font-black leading-none text-amber-800 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['payment_to_company'], 2) }}</p>
                    </div>
                    <div class="min-w-0 rounded-[1.1rem] border border-rose-200 bg-rose-50 p-3">
                        <p class="text-[8px] font-black uppercase tracking-[0.1em] text-rose-700 sm:text-[9px]">Cash Debit</p>
                        <p class="mt-2 truncate text-[13px] font-black leading-none text-rose-800 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['cash_debit'], 2) }}</p>
                    </div>
                    <div class="min-w-0 rounded-[1.1rem] border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[9px]">Expected</p>
                        <p class="mt-2 truncate text-[13px] font-black leading-none text-slate-950 tabular-nums sm:text-base">Rs. {{ number_format($receiptSummary['expected_closing'], 2) }}</p>
                    </div>
                    <div class="min-w-0 rounded-[1.1rem] border {{ $calculatedClosingTone === 'rose' ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }} p-3">
                        <p class="text-[8px] font-black uppercase tracking-[0.1em] {{ $calculatedClosingTone === 'rose' ? 'text-rose-700' : 'text-emerald-700' }} sm:text-[9px]">Closing</p>
                        <p data-cashbook-closing-display class="mt-2 truncate text-[13px] font-black leading-none tabular-nums {{ $calculatedClosingTone === 'rose' ? 'text-rose-800' : 'text-emerald-800' }} sm:text-base">
                            Rs. {{ number_format($calculatedClosing, 2) }}
                        </p>
                    </div>
                </div>

                @if ($receiptSummary['difference'] !== null && abs((float) $receiptSummary['difference']) > 0.009)
                    <div class="mt-4 rounded-[1.35rem] border border-amber-200 bg-amber-50 px-4 py-4">
                        <p class="text-sm font-black text-amber-900">Difference: Rs. {{ number_format((float) $receiptSummary['difference'], 2) }}</p>
                        <p class="mt-1 text-sm font-semibold text-amber-800">Entered closing does not match calculated closing. Add a note before submitting if this is expected.</p>
                    </div>
                @endif
            </section>

            @if ($hasEntry && ($entry->admin_note || $entry->shop_reply_note))
                <section class="grid gap-4 lg:grid-cols-2">
                    @if ($entry->admin_note)
                        <article class="rounded-[1.75rem] border {{ $entry->status === 'recheck_required' ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] {{ $entry->status === 'recheck_required' ? 'text-red-700' : 'text-slate-500' }}">Admin Note</p>
                            <p class="mt-3 text-sm font-semibold leading-6 text-slate-700">{{ $entry->admin_note }}</p>
                        </article>
                    @endif
                    @if ($entry->shop_reply_note)
                        <article class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Last Reply Sent</p>
                            <p class="mt-3 text-sm font-semibold leading-6 text-slate-700">{{ $entry->shop_reply_note }}</p>
                        </article>
                    @endif
                </section>
            @endif

            @if ($hasEntry && $recheckLines->isNotEmpty())
                <section class="rounded-[2rem] border border-red-200 bg-red-50 p-4 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-700">Recheck Needed</p>
                            <h3 class="mt-2 text-lg font-black text-slate-950">These ledger items need correction</h3>
                            <p class="mt-2 text-sm font-semibold text-red-900">Update the marked items below and resubmit the ledger day.</p>
                        </div>
                        <div class="shrink-0">
                            @include('shop-owner.components.status-badge', ['label' => 'Action Needed', 'tone' => 'danger'])
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach ($recheckLines as $line)
                            <article class="rounded-[1.5rem] border border-red-200 bg-white px-4 py-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $line->type === 'income' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                {{ $line->type }}
                                            </span>
                                            <p class="text-sm font-black text-slate-950">{{ $line->category?->name ?? 'Category removed' }}</p>
                                        </div>
                                        <p class="mt-2 text-sm font-semibold text-slate-600">{{ $line->description ?: 'No note added' }}</p>
                                        @if ($line->review_note)
                                            <p class="mt-3 text-sm font-black text-red-700">Accounting note: {{ $line->review_note }}</p>
                                        @endif
                                    </div>
                                    <p class="text-sm font-black text-slate-950">Rs. {{ number_format((float) $line->amount, 2) }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
                <article class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    @if ($hasEntry && $entry->status === 'approved')
                        <div class="mb-5 rounded-[1.5rem] border border-cyan-200 bg-cyan-50 px-4 py-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-black text-cyan-950">This day is already approved.</p>
                                    <p class="mt-2 text-sm font-semibold text-cyan-900">Approved entries are read-only. Add any extra income or expense as a new approval request.</p>
                                </div>
                                <button type="button" id="cashbook-open-adjustment-modal" class="inline-flex h-11 shrink-0 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                                    Add New Entry
                                </button>
                            </div>
                        </div>
                    @elseif ($hasEntry && $entry->status === 'submitted')
                        <div class="mb-5 rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-4">
                            <p class="text-sm font-black text-amber-900">This day is waiting for accounting review.</p>
                            <p class="mt-2 text-sm font-semibold text-amber-800">You can still update the selected day and resubmit the latest ledger items if something changes.</p>
                        </div>
                    @endif

                    @if ($canEdit)
                    <form method="POST" action="{{ route('shop-owner.accounting.entries.store') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="business_date" value="{{ $selectedDate->format('Y-m-d') }}">

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Opening Balance</span>
                                <p id="cashbook-opening-display" data-opening-cash="{{ number_format($receiptSummary['opening_balance'], 2, '.', '') }}" class="mt-2 text-lg font-black text-slate-950 tabular-nums">Rs. {{ number_format($receiptSummary['opening_balance'], 2) }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">Previous day closing balance</p>
                            </div>
                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Closing Balance</span>
                                <p id="cashbook-closing-display" data-cashbook-closing-display class="mt-2 text-lg font-black text-slate-950 tabular-nums">Rs. {{ number_format($calculatedClosing, 2) }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">Auto calculated from cash credits and debits</p>
                            </div>
                            <label class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Daily Note</span>
                                <input type="text" name="notes" value="{{ old('notes', $entry?->notes) }}" class="mt-2 w-full border-0 bg-transparent p-0 text-sm font-semibold text-slate-950 focus:outline-none focus:ring-0">
                            </label>
                        </div>

                        @if ($hasEntry && in_array($entry->status, ['recheck_required', 'approved', 'submitted'], true))
                            <label class="block rounded-[1.5rem] border border-red-200 bg-red-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-red-700">{{ $entry->status === 'recheck_required' ? 'Reply To Admin Recheck' : 'Update Note For Accounting' }}</span>
                                <textarea name="shop_reply_note" rows="4" class="mt-3 w-full rounded-2xl border border-red-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-red-400 focus:outline-none">{{ old('shop_reply_note', $entry?->shop_reply_note) }}</textarea>
                            </label>
                        @endif

                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Ledger Items</p>
                            <h3 class="mt-2 text-lg font-black text-slate-950">Add receipt lines</h3>
	                            <p class="mt-2 text-sm font-semibold text-slate-600">Cash-effect income is Cash Sales. Approved delivery bills are added automatically as Cash Debit. Do not add the same delivery bill manually.</p>
                        </div>
                                <button
                                    type="button"
                                    id="cashbook-open-modal"
                                    class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800"
                                >
                                    Add Credit / Debit
                                </button>
                            </div>

                            <div id="cashbook-lines-list" class="mt-5 space-y-3"></div>
                            <div class="mt-4 rounded-[1.35rem] border border-rose-200 bg-rose-50 px-4 py-4">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">System Expense</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-black text-slate-950">Daily Delivery Bill</p>
                                            @if ($selectedDeliveryExpense > 0)
                                                <span class="inline-flex rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">GreenLeaf Direct</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm font-semibold text-rose-900">Approved delivery bills for this day are automatically included in Cash Debit and closing balance.</p>
                                    </div>
                                    <p class="text-2xl font-black text-rose-700">Rs. {{ number_format($selectedDeliveryExpense, 2) }}</p>
                                </div>
                            </div>
                            <div id="cashbook-lines-inputs">
                                @foreach ($cashbookInitialLines as $index => $line)
                                    <input type="hidden" name="lines[{{ $index }}][shop_accounting_category_id]" value="{{ $line['shop_accounting_category_id'] ?? '' }}">
                                    <input type="hidden" name="lines[{{ $index }}][amount]" value="{{ $line['amount'] ?? '' }}">
                                    <input type="hidden" name="lines[{{ $index }}][description]" value="{{ $line['description'] ?? '' }}">
                                @endforeach
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <button type="submit" name="submission_action" value="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                {{ $hasEntry ? 'Submit Update To Admin' : 'Submit To Admin Approval' }}
                            </button>
                            <p class="text-xs font-bold text-slate-500">This sends the daily receipt to accounting approval.</p>
                        </div>
                    </form>
                    @elseif ($hasEntry)
                        <div class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Opening Cash</p>
                                    <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format($receiptSummary['opening_balance'], 2) }}</p>
                                </div>
                                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Closing Cash</p>
                                    <p class="mt-2 text-lg font-black {{ $calculatedClosingTone === 'rose' ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($calculatedClosing, 2) }}</p>
                                </div>
                                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Approved By</p>
                                    <p class="mt-2 text-sm font-black text-slate-950">{{ $entry->reviewedBy?->name ?? 'Accounting' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $entry->reviewed_at?->format('d M Y h:i A') }}</p>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-[1.5rem] border border-slate-200">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                        <tr>
                                            <th class="px-4 py-3">Type</th>
                                            <th class="px-4 py-3">Category</th>
                                            <th class="px-4 py-3">Notes</th>
                                            <th class="px-4 py-3 text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($entry->lines as $line)
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $line->type === 'income' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ $line->type }}</span>
                                                </td>
                                                <td class="px-4 py-3 font-black text-slate-950">{{ $line->category?->name ?? 'Category removed' }}</td>
                                                <td class="px-4 py-3 font-semibold text-slate-600">{{ $line->description ?: 'No note added' }}</td>
                                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $line->amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </article>

                <article class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Recent Days</p>
                            <h3 class="mt-2 text-lg font-black text-slate-950">History snapshot</h3>
                        </div>
                        <a href="{{ route('shop-owner.accounting.history', ['tab' => 'cashbook']) }}" class="text-sm font-black text-emerald-700">Open</a>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($recentEntries as $recentEntry)
                            @php
                                $recentIncome = (float) $recentEntry->lines->where('type', 'income')->sum('amount');
                                $recentExpense = (float) $recentEntry->lines->where('type', 'expense')->sum('amount');
                            @endphp
                            <a href="{{ route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => $recentEntry->business_date->format('Y-m-d')]) }}" class="block rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-200 hover:bg-emerald-50">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-950">{{ $recentEntry->business_date->format('d M Y') }}</p>
                                        <div class="mt-2">
                                            @include('shop-owner.components.status-badge', ['label' => $recentEntry->statusLabel(), 'tone' => $recentEntry->statusTone()])
                                        </div>
                                    </div>
                                    <p class="text-sm font-black text-slate-950">Rs. {{ number_format($recentIncome - $recentExpense, 2) }}</p>
                                </div>
                                @if ($recentEntry->admin_note)
                                    <p class="mt-3 line-clamp-2 text-sm font-semibold text-slate-600">{{ $recentEntry->admin_note }}</p>
                                @endif
                            </a>
                        @empty
                            @include('shop-owner.components.empty-state', ['title' => 'No cashbook history yet', 'description' => 'Save the first daily accounting sheet to start the approval flow.'])
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Shop Cash Credit</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950">Working cash received from company</h3>
                    </div>
                    <p class="text-sm font-black text-emerald-700">Latest {{ $shopCredits->count() }}</p>
                </div>

                <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 text-right">Shop Cash Amount</th>
                                <th class="px-4 py-3 text-right">Added By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($shopCredits as $credit)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $credit->business_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $credit->isShopCashIn() ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
                                            {{ $credit->shopCashLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-600">{{ $credit->description }}</td>
                                    <td class="px-4 py-3 text-right font-black {{ $credit->isShopCashIn() ? 'text-emerald-700' : 'text-rose-700' }}">{{ $credit->shopSignedAmount() >= 0 ? '+' : '-' }} Rs. {{ number_format(abs($credit->shopSignedAmount()), 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-500">{{ $credit->creator?->name ?? 'System' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center font-bold text-slate-500">No admin cash movements have been added yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($canEdit)
                <div id="cashbook-line-modal" class="fixed inset-0 z-[80] hidden overflow-y-auto bg-slate-950/50 px-4 py-8">
                    <div class="mx-auto w-full max-w-lg rounded-[2rem] border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Daily Shop Receipt</p>
                                <h3 id="cashbook-modal-title" class="mt-2 text-xl font-black text-slate-950">Add credit or debit</h3>
                                <p class="mt-2 text-sm font-semibold text-slate-600">Select a category. Cash-effect categories change the closing balance; online categories do not.</p>
                            </div>
                            <button type="button" id="cashbook-close-modal" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-xl font-black text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">×</button>
                        </div>

                        <div class="mt-5 space-y-4">
                            <label class="block">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Type</span>
                                <input id="cashbook-line-type" type="hidden" value="income">
                                <div class="relative mt-2">
                                    <button id="cashbook-line-type-trigger" type="button" class="flex w-full items-center justify-between rounded-[1.6rem] border border-slate-200 bg-slate-50 px-5 py-3.5 text-left text-base font-black text-slate-900 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)] transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/10" aria-haspopup="listbox" aria-expanded="false">
                                        <span id="cashbook-line-type-label">Income</span>
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                        </svg>
                                    </button>
                                    <div id="cashbook-line-type-panel" class="absolute inset-x-0 top-[calc(100%+0.6rem)] z-20 hidden rounded-[1.45rem] border border-slate-200 bg-white p-2 shadow-[0_20px_45px_rgba(15,23,42,0.16)]" role="listbox" aria-label="Cashbook type">
                                        <button type="button" data-cashbook-type-option data-value="income" data-label="Income" class="flex w-full items-center rounded-[1rem] px-4 py-3 text-left text-sm font-black text-slate-900 transition hover:bg-emerald-50 hover:text-emerald-700">
                                            Income / Credit
                                        </button>
                                        <button type="button" data-cashbook-type-option data-value="expense" data-label="Expense" class="flex w-full items-center rounded-[1rem] px-4 py-3 text-left text-sm font-black text-slate-900 transition hover:bg-amber-50 hover:text-amber-700">
                                            Expense / Debit
                                        </button>
                                    </div>
                                </div>
                            </label>
                            <label class="block">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Category</span>
                                <input id="cashbook-line-category" type="hidden" value="">
                                <div class="relative mt-2">
                                    <button id="cashbook-line-category-trigger" type="button" class="flex w-full items-center justify-between rounded-[1.6rem] border border-slate-200 bg-slate-50 px-5 py-3.5 text-left text-base font-black text-slate-900 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)] transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/10" aria-haspopup="listbox" aria-expanded="false">
                                        <span id="cashbook-line-category-label" class="text-slate-400">Select category</span>
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                        </svg>
                                    </button>
                                    <div id="cashbook-line-category-panel" class="absolute inset-x-0 top-[calc(100%+0.6rem)] z-20 hidden max-h-72 overflow-y-auto rounded-[1.45rem] border border-slate-200 bg-white p-2 shadow-[0_20px_45px_rgba(15,23,42,0.16)]" role="listbox" aria-label="Cashbook category"></div>
                                </div>
                            </label>
                            <label class="block">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Amount</span>
                                <input id="cashbook-line-amount" type="number" min="0.01" step="0.01" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Enter amount">
                            </label>
                            <label class="block">
                                <span id="cashbook-line-description-label" class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Notes</span>
                                <textarea id="cashbook-line-description" rows="4" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Add notes"></textarea>
                            </label>
                            <p id="cashbook-line-help" class="text-xs font-semibold text-slate-500">Other needs notes so admin can understand the entry.</p>
                        </div>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                            <button type="button" id="cashbook-save-line" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                Save Item
                            </button>
                            <button type="button" id="cashbook-cancel-line" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($hasEntry && $entry->status === 'approved')
                <div id="cashbook-adjustment-modal" class="fixed inset-0 z-[80] hidden overflow-y-auto bg-slate-950/50 px-4 py-8">
                    <div class="mx-auto w-full max-w-lg rounded-[2rem] border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Approved Day</p>
                                <h3 class="mt-2 text-xl font-black text-slate-950">Add additional income or expense</h3>
                                <p class="mt-2 text-sm font-semibold text-slate-600">This creates a new pending approval entry for {{ $selectedDate->format('d M Y') }}.</p>
                            </div>
                            <button type="button" id="cashbook-close-adjustment-modal" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-xl font-black text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">×</button>
                        </div>

                        <form method="POST" action="{{ route('shop-owner.accounting.entries.store') }}" class="mt-6 space-y-4">
                            @csrf
                            <input type="hidden" name="business_date" value="{{ $selectedDate->format('Y-m-d') }}">
                            <input type="hidden" name="submission_action" value="submit">
                            <input type="hidden" name="create_adjustment" value="1">

	                            <label class="block">
	                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Entry Type</span>
	                                <select id="cashbook-adjustment-type" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black text-slate-900 focus:border-emerald-500 focus:outline-none">
	                                    <option value="income">Income</option>
	                                    <option value="expense">Expense</option>
	                                </select>
	                            </label>

	                            <label class="block">
	                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Category</span>
	                                <select id="cashbook-adjustment-category" name="lines[0][shop_accounting_category_id]" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black text-slate-900 focus:border-emerald-500 focus:outline-none">
	                                    @foreach ($availableCategories as $category)
	                                        <option value="{{ $category->id }}" data-type="{{ $category->type }}">
	                                            {{ $category->name }}
	                                        </option>
	                                    @endforeach
	                                </select>
	                            </label>

                            <label class="block">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Amount</span>
                                <input type="number" step="0.01" min="0.01" name="lines[0][amount]" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black text-slate-900 focus:border-emerald-500 focus:outline-none">
                            </label>

                            <label class="block">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Note</span>
                                <textarea name="lines[0][description]" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Explain this additional entry"></textarea>
                            </label>

                            <label class="block">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Request Note</span>
                                <input type="text" name="notes" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Optional note for accounting">
                            </label>

                            <div class="flex flex-col gap-3 sm:flex-row">
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                    Send To Admin Approval
                                </button>
                                <button type="button" id="cashbook-cancel-adjustment-modal" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection

@if ($tab === 'cashbook')
    @push('scripts')
    <script>
        (() => {
            const root = document.getElementById('shop-owner-ledger-status-tabs');

            if (!root) {
                return;
            }

            const triggers = root.querySelectorAll('[data-ledger-status-trigger]');
            const panels = root.querySelectorAll('[data-ledger-status-panel]');

            const setLedgerStatus = (status) => {
                triggers.forEach((trigger) => {
                    const isActive = trigger.dataset.ledgerStatusTrigger === status;

                    trigger.classList.toggle('bg-slate-950', isActive);
                    trigger.classList.toggle('text-white', isActive);
                    trigger.classList.toggle('text-slate-700', !isActive);
                    trigger.classList.toggle('hover:bg-white', !isActive);
                    trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                panels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.ledgerStatusPanel !== status);
                });

                const url = new URL(window.location.href);
                url.searchParams.set('tab', 'cashbook');
                url.searchParams.set('ledger_status', status);
                window.history.replaceState({}, '', url);
            };

            triggers.forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    setLedgerStatus(trigger.dataset.ledgerStatusTrigger ?? 'draft');
                });
            });
        })();

        (() => {
            const modal = document.getElementById('cashbook-adjustment-modal');
            const openButton = document.getElementById('cashbook-open-adjustment-modal');
            const closeButton = document.getElementById('cashbook-close-adjustment-modal');
            const cancelButton = document.getElementById('cashbook-cancel-adjustment-modal');
            const typeSelect = document.getElementById('cashbook-adjustment-type');
            const categorySelect = document.getElementById('cashbook-adjustment-category');

            if (!modal || !openButton || !closeButton || !cancelButton || !typeSelect || !categorySelect) {
                return;
            }

            const closeModal = () => modal.classList.add('hidden');
            const allCategoryOptions = Array.from(categorySelect.options).map((option) => ({
                value: option.value,
                label: option.textContent ?? '',
                type: option.dataset.type ?? '',
            }));
            const filterCategories = () => {
                const selectedType = typeSelect.value;
                const options = allCategoryOptions.filter((option) => option.type === selectedType);

                categorySelect.innerHTML = options.map((option) => `
                    <option value="${option.value}" data-type="${option.type}">${option.label}</option>
                `).join('');
            };

            openButton.addEventListener('click', () => {
                filterCategories();
                modal.classList.remove('hidden');
                typeSelect.focus();
            });
            typeSelect.addEventListener('change', filterCategories);
            closeButton.addEventListener('click', closeModal);
            cancelButton.addEventListener('click', closeModal);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        })();

        (() => {
            const categories = @json($cashbookCategories);
            const initialLines = @json($cashbookInitialLines);
            const listEl = document.getElementById('cashbook-lines-list');
            const inputsEl = document.getElementById('cashbook-lines-inputs');
            const modalEl = document.getElementById('cashbook-line-modal');
            const openButton = document.getElementById('cashbook-open-modal');
            const closeButton = document.getElementById('cashbook-close-modal');
            const cancelButton = document.getElementById('cashbook-cancel-line');
            const saveButton = document.getElementById('cashbook-save-line');
            const typeInput = document.getElementById('cashbook-line-type');
            const categoryInput = document.getElementById('cashbook-line-category');
            const typeTrigger = document.getElementById('cashbook-line-type-trigger');
            const typeLabel = document.getElementById('cashbook-line-type-label');
            const typePanel = document.getElementById('cashbook-line-type-panel');
            const categoryTrigger = document.getElementById('cashbook-line-category-trigger');
            const categoryLabel = document.getElementById('cashbook-line-category-label');
            const categoryPanel = document.getElementById('cashbook-line-category-panel');
            const amountInput = document.getElementById('cashbook-line-amount');
            const descriptionInput = document.getElementById('cashbook-line-description');
            const descriptionLabel = document.getElementById('cashbook-line-description-label');
            const helpText = document.getElementById('cashbook-line-help');
            const modalTitle = document.getElementById('cashbook-modal-title');
            const openingDisplay = document.getElementById('cashbook-opening-display');
            const closingDisplays = document.querySelectorAll('[data-cashbook-closing-display]');

            if (!listEl || !inputsEl || !modalEl || !openButton || !closeButton || !cancelButton || !saveButton || !typeInput || !categoryInput || !typeTrigger || !typeLabel || !typePanel || !categoryTrigger || !categoryLabel || !categoryPanel || !amountInput || !descriptionInput || !descriptionLabel || !helpText || !modalTitle) {
                return;
            }

            let editIndex = null;
            let lines = Array.isArray(initialLines) ? initialLines.filter(line => line && line.shop_accounting_category_id && line.amount) : [];
            const openingCash = Number(openingDisplay?.dataset.openingCash ?? 0);

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const categoryMeta = (categoryId) => categories.find((category) => String(category.id) === String(categoryId)) ?? null;
            const cashbookLabel = (meta) => {
                if (!meta) {
                    return 'Entry';
                }

                if (meta.type === 'income') {
                    return meta.cash_effect ? 'Cash Sales' : 'Non-Cash Income';
                }

                return meta.cash_effect ? 'Cash Debit' : 'Non-Cash Debit';
            };
            const formatMoney = (amount) => `Rs. ${Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const renderClosingPreview = () => {
                if (!closingDisplays.length) {
                    return;
                }

                const cashMovement = lines.reduce((total, line) => {
                    const meta = categoryMeta(line.shop_accounting_category_id);

                    if (!meta?.cash_effect) {
                        return total;
                    }

                    const amount = Number(line.amount);

                    if (!Number.isFinite(amount)) {
                        return total;
                    }

                    return total + (meta.type === 'income' ? amount : -amount);
                }, 0);

                closingDisplays.forEach((display) => {
                    display.textContent = formatMoney(openingCash + cashMovement);
                });
            };
            const requiresDescription = (meta) => Boolean(meta && String(meta.name).toLowerCase().startsWith('other'));
            const setDropdownOpen = (trigger, panel, isOpen) => {
                panel.classList.toggle('hidden', !isOpen);
                trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            };
            const closeDropdowns = () => {
                setDropdownOpen(typeTrigger, typePanel, false);
                setDropdownOpen(categoryTrigger, categoryPanel, false);
            };
            const setTypeValue = (value, label) => {
                typeInput.value = value;
                typeLabel.textContent = label;
                typeLabel.classList.remove('text-slate-400');
            };
            const setCategoryValue = (value, label) => {
                categoryInput.value = value;
                categoryLabel.textContent = label;
                categoryLabel.classList.toggle('text-slate-400', value === '');
            };

            const fillCategoryOptions = (type, selectedId = '') => {
                const filtered = categories.filter((category) => category.type === type);
                categoryPanel.innerHTML = filtered.map((category) => `
                    <button
                        type="button"
                        data-cashbook-category-option
                        data-value="${category.id}"
                        data-label="${escapeHtml(category.name)}"
                        class="flex w-full items-center rounded-[1rem] px-4 py-3 text-left text-sm font-black transition ${
                            String(category.id) === String(selectedId)
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'text-slate-900 hover:bg-slate-100'
                        }"
                    >
                        <span>${escapeHtml(category.name)}</span>
                        <span class="ml-auto text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">${escapeHtml(cashbookLabel(category))}</span>
                    </button>
                `).join('');

                const selectedCategory = filtered.find((category) => String(category.id) === String(selectedId)) ?? null;
                setCategoryValue(selectedCategory ? String(selectedCategory.id) : '', selectedCategory ? selectedCategory.name : 'Select category');
                refreshDescriptionState();
            };

            const refreshDescriptionState = () => {
                const meta = categoryMeta(categoryInput.value);
                const isOther = requiresDescription(meta);
                descriptionLabel.textContent = isOther ? 'Notes Required' : 'Notes';
                helpText.textContent = isOther
                    ? 'Other income or expense needs notes so admin can understand the entry.'
                    : 'Add any short detail if this entry needs context.';
            };

            const renderInputs = () => {
                inputsEl.innerHTML = lines.map((line, index) => `
                    <input type="hidden" name="lines[${index}][shop_accounting_category_id]" value="${escapeHtml(line.shop_accounting_category_id)}">
                    <input type="hidden" name="lines[${index}][amount]" value="${escapeHtml(line.amount)}">
                    <input type="hidden" name="lines[${index}][description]" value="${escapeHtml(line.description ?? '')}">
                `).join('');
            };

            const renderList = () => {
                if (lines.length === 0) {
                    listEl.innerHTML = `
                        <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-white px-4 py-8 text-center">
                            <p class="text-sm font-black text-slate-900">No items added yet.</p>
                            <p class="mt-2 text-sm font-semibold text-slate-500">Use Add Credit / Debit to build the daily receipt.</p>
                        </div>
                    `;
                    renderInputs();
                    return;
                }

                listEl.innerHTML = lines.map((line, index) => {
                    const meta = categoryMeta(line.shop_accounting_category_id);
                    const typeTone = meta?.type === 'income' && meta?.cash_effect
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : meta?.type === 'income'
                            ? 'border-cyan-200 bg-cyan-50 text-cyan-700'
                            : 'border-amber-200 bg-amber-50 text-amber-700';

                    return `
                        <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] ${typeTone}">
                                            ${escapeHtml(cashbookLabel(meta))}
                                        </span>
                                        <span class="text-sm font-black text-slate-950">${escapeHtml(meta?.name ?? 'Category')}</span>
                                    </div>
                                    <p class="mt-3 text-2xl font-black text-slate-950">Rs. ${Number(line.amount).toFixed(2)}</p>
                                    ${line.description ? `<p class="mt-2 text-sm font-semibold text-slate-600">${escapeHtml(line.description)}</p>` : ''}
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" data-edit-index="${index}" class="inline-flex h-10 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-100">Edit</button>
                                    <button type="button" data-remove-index="${index}" class="inline-flex h-10 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-rose-700 transition hover:bg-rose-100">Remove</button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                renderInputs();
                renderClosingPreview();
            };

            const closeModal = () => {
                modalEl.classList.add('hidden');
                closeDropdowns();
                editIndex = null;
                amountInput.value = '';
                descriptionInput.value = '';
                setTypeValue('income', 'Income');
                fillCategoryOptions('income');
                modalTitle.textContent = 'Add credit or debit';
            };

            const openModal = (index = null) => {
                editIndex = index;

                if (index === null) {
                    setTypeValue('income', 'Income');
                    fillCategoryOptions('income');
                    amountInput.value = '';
                    descriptionInput.value = '';
                    modalTitle.textContent = 'Add credit or debit';
                } else {
                    const line = lines[index];
                    const meta = categoryMeta(line.shop_accounting_category_id);
                    setTypeValue(meta?.type ?? 'income', meta?.type === 'expense' ? 'Expense' : 'Income');
                    fillCategoryOptions(typeInput.value, line.shop_accounting_category_id);
                    amountInput.value = line.amount;
                    descriptionInput.value = line.description ?? '';
                    modalTitle.textContent = 'Update receipt line';
                }

                modalEl.classList.remove('hidden');
                amountInput.focus();
            };

            openButton?.addEventListener('click', () => openModal());
            closeButton?.addEventListener('click', closeModal);
            cancelButton?.addEventListener('click', closeModal);
            modalEl?.addEventListener('click', (event) => {
                if (event.target === modalEl) {
                    closeModal();
                }
            });

            typeTrigger?.addEventListener('click', () => {
                const isOpen = typePanel.classList.contains('hidden');
                closeDropdowns();
                setDropdownOpen(typeTrigger, typePanel, isOpen);
            });

            categoryTrigger?.addEventListener('click', () => {
                const isOpen = categoryPanel.classList.contains('hidden');
                closeDropdowns();
                setDropdownOpen(categoryTrigger, categoryPanel, isOpen);
            });

            typePanel?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }

                const option = target.closest('[data-cashbook-type-option]');
                if (!(option instanceof HTMLElement)) {
                    return;
                }

                setTypeValue(option.dataset.value ?? 'income', option.dataset.label ?? 'Income');
                fillCategoryOptions(typeInput.value);
                closeDropdowns();
            });

            categoryPanel?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }

                const option = target.closest('[data-cashbook-category-option]');
                if (!(option instanceof HTMLElement)) {
                    return;
                }

                setCategoryValue(option.dataset.value ?? '', option.dataset.label ?? 'Select category');
                refreshDescriptionState();
                closeDropdowns();
            });

            saveButton?.addEventListener('click', () => {
                const categoryId = categoryInput.value;
                const amount = amountInput.value;
                const description = descriptionInput.value.trim();
                const meta = categoryMeta(categoryId);

                if (! categoryId || ! amount || Number(amount) <= 0) {
                    window.showAppAlert?.('Select a category and enter a valid amount.', 'warning');
                    return;
                }

                if (requiresDescription(meta) && description === '') {
                    window.showAppAlert?.('Add notes when you choose Other income or expense.', 'warning');
                    return;
                }

                const nextLine = {
                    shop_accounting_category_id: categoryId,
                    amount,
                    description,
                };

                if (editIndex === null) {
                    lines.push(nextLine);
                } else {
                    lines[editIndex] = nextLine;
                }

                renderList();
                closeModal();
            });

            listEl?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }

                if (target.dataset.editIndex !== undefined) {
                    openModal(Number(target.dataset.editIndex));
                }

                if (target.dataset.removeIndex !== undefined) {
                    lines.splice(Number(target.dataset.removeIndex), 1);
                    renderList();
                }
            });

            document.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Node)) {
                    return;
                }

                if (!typeTrigger.contains(target) && !typePanel.contains(target) && !categoryTrigger.contains(target) && !categoryPanel.contains(target)) {
                    closeDropdowns();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeDropdowns();
                }
            });

            setTypeValue('income', 'Income');
            fillCategoryOptions('income');
            renderList();
        })();
    </script>
    @endpush
@endif
