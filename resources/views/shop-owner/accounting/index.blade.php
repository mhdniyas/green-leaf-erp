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
            'name' => (string) $category->name,
        ])->values();
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
                                    @elseif ($hasPendingRequest)
                                        <p class="text-sm font-black text-amber-800">A payment request for Rs. {{ number_format((float) $latestRequest->requested_amount, 2) }} is already waiting for approval.</p>
                                        @if ($latestRequest->shop_note)
                                            <p class="mt-2 text-sm font-semibold text-slate-600">{{ $latestRequest->shop_note }}</p>
                                        @endif
                                    @else
                                        <form method="POST" action="{{ route('shop-owner.accounting.payment-requests.store') }}" class="space-y-3">
                                            @csrf
                                            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                                    <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Full Bill Due</span>
                                                    <input type="radio" name="amount_mode" value="balance_due" checked class="mt-3 h-4 w-4 border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                                    <span class="mt-2 block text-sm font-black text-slate-950">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</span>
                                                </label>
                                                <label class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                                    <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Custom Amount</span>
                                                    <input type="radio" name="amount_mode" value="custom" class="mt-3 h-4 w-4 border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                                    <input type="number" step="0.01" min="0.01" max="{{ number_format((float) $invoice->balance_amount, 2, '.', '') }}" name="amount" value="{{ old('amount') }}" placeholder="Enter amount" class="mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                                </label>
                                            </div>
                                            <label class="block">
                                                <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Shop Note</span>
                                                <textarea name="shop_note" rows="3" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">{{ old('shop_note') }}</textarea>
                                            </label>
                                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                                Send Payment Request
                                            </button>
                                        </form>
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
                        <h2 class="mt-2 text-xl font-black text-slate-950">Daily shop ledger</h2>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Use the selected day to record manual income and expense. Warehouse delivery invoices are shown as a system expense so the ledger stays complete.</p>
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

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Sales Income</p>
                    <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($incomeTotal, 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Admin Cash</p>
                    <p class="mt-2 text-3xl font-black {{ $selectedShopCredit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($selectedShopCredit, 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Warehouse Invoice</p>
                    <p class="mt-2 text-3xl font-black text-rose-700">Rs. {{ number_format($selectedDeliveryExpense, 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total Expense</p>
                    <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($expenseTotal, 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Result</p>
                    <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($netAmount, 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Petty Cash Balance</p>
                    <p class="mt-2 text-3xl font-black {{ $pettyCashBalance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($pettyCashBalance, 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Status</p>
                    <div class="mt-3">
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
            <section class="rounded-[2rem] border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <div class="flex flex-wrap gap-2 rounded-2xl bg-slate-100 p-1">
                    @foreach ($ledgerStatusTabs as $statusKey => $statusLabel)
                        <a href="{{ route('shop-owner.accounting.index', array_filter(['tab' => 'cashbook', 'ledger_status' => $statusKey, 'date' => $selectedDate->format('Y-m-d'), 'start_date' => request('start_date'), 'end_date' => request('end_date'), 'ledger_source' => request('ledger_source')])) }}" class="inline-flex h-10 items-center rounded-xl px-4 text-sm font-black transition {{ $ledgerStatusTab === $statusKey ? 'bg-slate-950 text-white' : 'text-slate-700 hover:bg-white' }}">
                            {{ $statusLabel }}
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Petty Cash</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950">Daily petty cash table</h3>
                        <p class="mt-2 text-sm font-semibold {{ $pettyCashBalance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $pettyCashBalance < 0 ? 'Petty cash pending' : 'Petty cash balance' }} Rs. {{ number_format(abs($pettyCashBalance), 2) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="sales-petty-open-modal" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-4 text-sm font-black text-white transition hover:bg-emerald-500">
                            Sales to Petty
                        </button>
                        <button type="button" id="petty-cash-open-modal" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800">
                            Petty Expense
                        </button>
                    </div>
                </div>

                <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Credit</th>
                                <th class="px-4 py-3 text-right">EXP</th>
                                <th class="px-4 py-3 text-right">BAL</th>
                                <th class="px-4 py-3 text-right">Last Update</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($pettyCashRows as $pettyRow)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($pettyRow['date'])->format('d M Y') }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-600">{{ $pettyRow['admin_cash_label'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right font-black text-rose-700">
                                        Rs. {{ number_format((float) $pettyRow['expense'], 2) }}
                                        @if ($pettyRow['expense_source'])
                                            <span class="ml-2 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">{{ $pettyRow['expense_source'] }}</span>
                                        @endif
                                        @if (! empty($pettyRow['payroll_expense_label']))
                                            <span class="mt-1 block text-xs font-bold text-slate-500">{{ $pettyRow['payroll_expense_label'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-black {{ (float) $pettyRow['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format((float) $pettyRow['balance'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-500">
                                        {{ $pettyRow['expense_updated_at'] ? $pettyRow['expense_updated_at']->format('d M Y h:i A') : '—' }}
                                        @if ($pettyRow['amount_change_label'])
                                            <span class="mt-1 block text-xs font-bold text-amber-700">{{ $pettyRow['amount_change_label'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" class="petty-cash-row-edit inline-flex h-9 items-center rounded-xl border border-slate-200 px-4 text-xs font-black uppercase tracking-[0.14em] text-slate-700 transition hover:bg-slate-50" data-date="{{ $pettyRow['date'] }}" data-amount="{{ number_format((float) $pettyRow['expense'], 2, '.', '') }}">
                                            Update
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center font-bold text-slate-500">No petty cash rows found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div id="petty-cash-modal" class="fixed inset-0 z-[80] hidden overflow-y-auto bg-slate-950/50 px-4 py-8">
                <div class="mx-auto w-full max-w-md rounded-[2rem] border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Petty Cash</p>
                            <h3 class="mt-2 text-xl font-black text-slate-950">Daily petty expense</h3>
                        </div>
                        <button type="button" class="petty-cash-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-xl font-black text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">×</button>
                    </div>

                    <form method="POST" action="{{ route('shop-owner.accounting.petty-cash-expenses.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Date</span>
                            <input id="petty-cash-date-input" type="date" name="business_date" value="{{ old('business_date', $selectedDate->format('Y-m-d')) }}" class="mt-2 h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Cash / Amount</span>
                            <input id="petty-cash-amount-input" type="number" name="amount" step="0.01" min="0" value="{{ old('amount', $selectedPettyCashExpense ? number_format((float) $selectedPettyCashExpense->amount, 2, '.', '') : number_format((float) $shop->default_petty_cash_amount, 2, '.', '')) }}" class="mt-2 h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </label>
                        <div class="flex justify-end gap-3">
                            <button type="button" class="petty-cash-close inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 transition hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="inline-flex h-11 items-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="sales-petty-modal" class="fixed inset-0 z-[80] hidden overflow-y-auto bg-slate-950/50 px-4 py-8">
                <div class="mx-auto w-full max-w-md rounded-[2rem] border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Petty Cash Credit</p>
                            <h3 class="mt-2 text-xl font-black text-slate-950">Move sales income to petty cash</h3>
                        </div>
                        <button type="button" class="sales-petty-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-xl font-black text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">×</button>
                    </div>

                    <form method="POST" action="{{ route('shop-owner.accounting.sales-to-petty-cash.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Date</span>
                            <input type="date" name="business_date" value="{{ old('business_date', $selectedDate->format('Y-m-d')) }}" class="mt-2 h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Amount</span>
                            <input type="number" name="amount" step="0.01" min="0.01" class="mt-2 h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Note</span>
                            <input type="text" name="description" value="Sales income moved to petty cash" class="mt-2 h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </label>
                        <div class="flex justify-end gap-3">
                            <button type="button" class="sales-petty-close inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 transition hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="inline-flex h-11 items-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                (() => {
                    const modal = document.getElementById('petty-cash-modal');
                    const salesPettyModal = document.getElementById('sales-petty-modal');
                    const openButton = document.getElementById('petty-cash-open-modal');
                    const salesPettyOpenButton = document.getElementById('sales-petty-open-modal');
                    const closeButtons = document.querySelectorAll('.petty-cash-close');
                    const salesPettyCloseButtons = document.querySelectorAll('.sales-petty-close');
                    const rowEditButtons = document.querySelectorAll('.petty-cash-row-edit');
                    const dateInput = document.getElementById('petty-cash-date-input');
                    const amountInput = document.getElementById('petty-cash-amount-input');
                    const defaultAmount = {{ \Illuminate\Support\Js::from(number_format((float) $shop->default_petty_cash_amount, 2, '.', '')) }};

                    openButton?.addEventListener('click', () => {
                        if (dateInput) {
                            dateInput.value = {{ \Illuminate\Support\Js::from($selectedDate->format('Y-m-d')) }};
                        }
                        if (amountInput && !amountInput.value) {
                            amountInput.value = defaultAmount;
                        }
                        modal?.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    });

                    salesPettyOpenButton?.addEventListener('click', () => {
                        salesPettyModal?.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    });

                    rowEditButtons.forEach((button) => button.addEventListener('click', () => {
                        if (dateInput) {
                            dateInput.value = button.dataset.date ?? '';
                        }
                        if (amountInput) {
                            amountInput.value = button.dataset.amount ?? defaultAmount;
                        }
                        modal?.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    }));

                    closeButtons.forEach((button) => button.addEventListener('click', () => {
                        modal?.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    }));

                    salesPettyCloseButtons.forEach((button) => button.addEventListener('click', () => {
                        salesPettyModal?.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    }));
                })();
            </script>

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
                            <p class="text-sm font-black text-cyan-950">This day is already approved.</p>
                            <p class="mt-2 text-sm font-semibold text-cyan-900">Approved entries are read-only. Any new correction must be sent through the recheck workflow by accounting.</p>
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
                            <label class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Opening Cash</span>
                                <input type="number" step="0.01" min="0" name="opening_cash" value="{{ old('opening_cash', $entry?->opening_cash ?? number_format($reserveAmount, 2, '.', '')) }}" class="mt-2 w-full border-0 bg-transparent p-0 text-lg font-black text-slate-950 focus:outline-none focus:ring-0">
                            </label>
                            <label class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Closing Cash</span>
                                <input type="number" step="0.01" min="0" name="closing_cash" value="{{ old('closing_cash', $entry?->closing_cash) }}" class="mt-2 w-full border-0 bg-transparent p-0 text-lg font-black text-slate-950 focus:outline-none focus:ring-0">
                            </label>
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
                            <h3 class="mt-2 text-lg font-black text-slate-950">Add one item at a time</h3>
                            <p class="mt-2 text-sm font-semibold text-slate-600">Track payment-mode sales, cash purchases, and other manual ledger items here. Warehouse delivery invoices are added below as a system expense.</p>
                        </div>
                                <button
                                    type="button"
                                    id="cashbook-open-modal"
                                    class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800"
                                >
                                    Add Income / Expense
                                </button>
                            </div>

                            <div id="cashbook-lines-list" class="mt-5 space-y-3"></div>
                            <div class="mt-4 rounded-[1.35rem] border border-rose-200 bg-rose-50 px-4 py-4">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">System Expense</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-black text-slate-950">Warehouse Delivery Invoice</p>
                                            @if ($selectedDeliveryExpense > 0)
                                                <span class="inline-flex rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">GreenLeaf Direct</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm font-semibold text-rose-900">Automatically included from daily warehouse bills for {{ $selectedDate->format('d M Y') }}.</p>
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

                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button type="submit" name="submission_action" value="save_draft" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                                Save Draft
                            </button>
                            <button type="submit" name="submission_action" value="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                {{ $hasEntry ? 'Submit Updated Ledger Day' : 'Submit Ledger Day' }}
                            </button>
                        </div>
                    </form>
                    @elseif ($hasEntry)
                        <div class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Opening Cash</p>
                                    <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format((float) $entry->opening_cash, 2) }}</p>
                                </div>
                                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Closing Cash</p>
                                    <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format((float) $entry->closing_cash, 2) }}</p>
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
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Shop Credits</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950">Cash movements from admin</h3>
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
                                <th class="px-4 py-3 text-right">Ledger Amount</th>
                                <th class="px-4 py-3 text-right">Added By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($shopCredits as $credit)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $credit->business_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $credit->isAccountingOut() ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                            {{ $credit->accountingLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-600">{{ $credit->description }}</td>
                                    <td class="px-4 py-3 text-right font-black {{ $credit->isAccountingOut() ? 'text-rose-700' : 'text-emerald-700' }}">{{ $credit->isAccountingOut() ? '-' : '+' }} Rs. {{ number_format((float) $credit->amount, 2) }}</td>
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

            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Ledger History</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950">
                            @if ($ledgerSourceFilter === 'greenleaf_direct')
                                GreenLeaf Direct ledger days
                            @else
                                {{ $ledgerDateFilterActive ? 'Date-filtered ledger table' : 'All ledger days' }}
                            @endif
                        </h3>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Browse saved ledger days. Admin cash movements and warehouse delivery bills are included as separate columns.</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
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

                <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Income</th>
                                <th class="px-4 py-3 text-right">Admin Cash</th>
                                <th class="px-4 py-3 text-right">Manual Expense</th>
                                <th class="px-4 py-3 text-right">Warehouse Invoice</th>
                                <th class="px-4 py-3 text-right">Net</th>
                                <th class="px-4 py-3 text-right">Items</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse ($ledgerEntries as $ledgerEntry)
                                @php
                                    $ledgerIncome = (float) $ledgerEntry->lines->where('type', 'income')->sum('amount');
                                    $ledgerCredit = (float) ($shopCreditByDate->get($ledgerEntry->business_date->toDateString()) ?? 0);
                                    $ledgerManualExpense = (float) $ledgerEntry->lines->where('type', 'expense')->sum('amount');
                                    $ledgerWarehouseExpense = (float) ($deliveryExpenseByDate->get($ledgerEntry->business_date->toDateString()) ?? 0);
                                    $ledgerNet = $ledgerIncome + $ledgerCredit - $ledgerManualExpense - $ledgerWarehouseExpense;
                                @endphp
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('shop-owner.accounting.index', ['tab' => 'cashbook', 'ledger_status' => $ledgerStatusTab, 'date' => $ledgerEntry->business_date->format('Y-m-d'), 'start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="font-black text-slate-950">
                                            {{ $ledgerEntry->business_date->format('d M Y') }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        @include('shop-owner.components.status-badge', ['label' => $ledgerEntry->statusLabel(), 'tone' => $ledgerEntry->statusTone()])
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($ledgerIncome, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format($ledgerCredit, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($ledgerManualExpense, 2) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($ledgerWarehouseExpense > 0)
                                            <span class="mb-1 inline-flex rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">GreenLeaf Direct</span>
                                        @endif
                                        <p class="font-black text-rose-700">Rs. {{ number_format($ledgerWarehouseExpense, 2) }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black {{ $ledgerNet >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($ledgerNet, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">{{ $ledgerEntry->lines->count() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center font-bold text-slate-500">No ledger days found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($ledgerEntries->hasPages())
                    <div class="mt-5">{{ $ledgerEntries->withQueryString()->links() }}</div>
                @endif
            </section>

            @if ($canEdit)
                <div id="cashbook-line-modal" class="fixed inset-0 z-[80] hidden overflow-y-auto bg-slate-950/50 px-4 py-8">
                    <div class="mx-auto w-full max-w-lg rounded-[2rem] border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Cashbook Item</p>
                                <h3 id="cashbook-modal-title" class="mt-2 text-xl font-black text-slate-950">Add income or expense</h3>
                                <p class="mt-2 text-sm font-semibold text-slate-600">Select a category. If you choose Other, add a clear note.</p>
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
                                            Income
                                        </button>
                                        <button type="button" data-cashbook-type-option data-value="expense" data-label="Expense" class="flex w-full items-center rounded-[1rem] px-4 py-3 text-left text-sm font-black text-slate-900 transition hover:bg-amber-50 hover:text-amber-700">
                                            Expense
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
        @endif
    </div>
@endsection

@if ($tab === 'cashbook')
    @push('scripts')
    <script>
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

            if (!listEl || !inputsEl || !modalEl || !openButton || !closeButton || !cancelButton || !saveButton || !typeInput || !categoryInput || !typeTrigger || !typeLabel || !typePanel || !categoryTrigger || !categoryLabel || !categoryPanel || !amountInput || !descriptionInput || !descriptionLabel || !helpText || !modalTitle) {
                return;
            }

            let editIndex = null;
            let lines = Array.isArray(initialLines) ? initialLines.filter(line => line && line.shop_accounting_category_id && line.amount) : [];

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const categoryMeta = (categoryId) => categories.find((category) => String(category.id) === String(categoryId)) ?? null;
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
                        ${escapeHtml(category.name)}
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
                            <p class="mt-2 text-sm font-semibold text-slate-500">Use Add Income / Expense to build the daily ledger.</p>
                        </div>
                    `;
                    renderInputs();
                    return;
                }

                listEl.innerHTML = lines.map((line, index) => {
                    const meta = categoryMeta(line.shop_accounting_category_id);
                    const typeTone = meta?.type === 'income'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-amber-200 bg-amber-50 text-amber-700';

                    return `
                        <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] ${typeTone}">
                                            ${escapeHtml(meta?.type ?? 'entry')}
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
            };

            const closeModal = () => {
                modalEl.classList.add('hidden');
                closeDropdowns();
                editIndex = null;
                amountInput.value = '';
                descriptionInput.value = '';
                setTypeValue('income', 'Income');
                fillCategoryOptions('income');
                modalTitle.textContent = 'Add income or expense';
            };

            const openModal = (index = null) => {
                editIndex = index;

                if (index === null) {
                    setTypeValue('income', 'Income');
                    fillCategoryOptions('income');
                    amountInput.value = '';
                    descriptionInput.value = '';
                    modalTitle.textContent = 'Add income or expense';
                } else {
                    const line = lines[index];
                    const meta = categoryMeta(line.shop_accounting_category_id);
                    setTypeValue(meta?.type ?? 'income', meta?.type === 'expense' ? 'Expense' : 'Income');
                    fillCategoryOptions(typeInput.value, line.shop_accounting_category_id);
                    amountInput.value = line.amount;
                    descriptionInput.value = line.description ?? '';
                    modalTitle.textContent = 'Update ledger item';
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
