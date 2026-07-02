@extends('shop-owner.layouts.app')

@section('title', 'Accounting')
@section('page_title', 'Shop Accounting')
@section('page_description', 'Track delivery bills, request payment approvals, and for owned shops keep daily income and expense records in one mobile-friendly workflow.')
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
                                    <p class="mt-1 text-sm font-semibold text-slate-600">Requested Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</p>
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
                        <h2 class="mt-2 text-xl font-black text-slate-950">Daily cashbook and expenses</h2>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Record daily income and expense. Reserve cash is provided by admin and stays visible here for reference.</p>
                    </div>

                    <form method="GET" action="{{ route('shop-owner.accounting.index') }}" class="flex flex-wrap items-center gap-2 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-2">
                        <input type="hidden" name="tab" value="cashbook">
                        <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</span>
                            <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                        </label>
                    </form>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Income</p>
                    <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($incomeTotal, 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Expense</p>
                    <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($expenseTotal, 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Result</p>
                    <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($netAmount, 2) }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Reserve Cash</p>
                    <p class="mt-2 text-3xl font-black text-cyan-700">Rs. {{ number_format($reserveAmount, 2) }}</p>
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

            <section class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
                <article class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    @if (! $canEdit)
                        <div class="mb-5 rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-4">
                            <p class="text-sm font-black text-amber-900">This day is locked while admin review is pending or completed.</p>
                            <p class="mt-2 text-sm font-semibold text-amber-800">If admin marks this day for recheck, it will return here with notes for correction.</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('shop-owner.accounting.entries.store') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="business_date" value="{{ $selectedDate->format('Y-m-d') }}">

                        <div class="grid gap-4 md:grid-cols-3">
                            <label class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Opening Cash</span>
                                <input type="number" step="0.01" min="0" name="opening_cash" value="{{ old('opening_cash', $entry?->opening_cash ?? number_format($reserveAmount, 2, '.', '')) }}" @disabled(! $canEdit) class="mt-2 w-full border-0 bg-transparent p-0 text-lg font-black text-slate-950 focus:outline-none focus:ring-0 disabled:text-slate-400">
                            </label>
                            <label class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Closing Cash</span>
                                <input type="number" step="0.01" min="0" name="closing_cash" value="{{ old('closing_cash', $entry?->closing_cash) }}" @disabled(! $canEdit) class="mt-2 w-full border-0 bg-transparent p-0 text-lg font-black text-slate-950 focus:outline-none focus:ring-0 disabled:text-slate-400">
                            </label>
                            <label class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Daily Note</span>
                                <input type="text" name="notes" value="{{ old('notes', $entry?->notes) }}" @disabled(! $canEdit) class="mt-2 w-full border-0 bg-transparent p-0 text-sm font-semibold text-slate-950 focus:outline-none focus:ring-0 disabled:text-slate-400">
                            </label>
                        </div>

                        @if ($hasEntry && $entry->status === 'recheck_required')
                            <label class="block rounded-[1.5rem] border border-red-200 bg-red-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-red-700">Reply To Admin Recheck</span>
                                <textarea name="shop_reply_note" rows="4" @disabled(! $canEdit) class="mt-3 w-full rounded-2xl border border-red-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-red-400 focus:outline-none disabled:text-slate-400">{{ old('shop_reply_note', $entry?->shop_reply_note) }}</textarea>
                            </label>
                        @endif

                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Income And Expense Items</p>
                                    <h3 class="mt-2 text-lg font-black text-slate-950">Add one item at a time</h3>
                                    <p class="mt-2 text-sm font-semibold text-slate-600">Choose income or expense, select a category, and use notes when you pick Other.</p>
                                </div>
                                @if ($canEdit)
                                    <button
                                        type="button"
                                        id="cashbook-open-modal"
                                        class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800"
                                    >
                                        Add Income / Expense
                                    </button>
                                @endif
                            </div>

                            <div id="cashbook-lines-list" class="mt-5 space-y-3"></div>
                            <div id="cashbook-lines-inputs">
                                @foreach ($cashbookInitialLines as $index => $line)
                                    <input type="hidden" name="lines[{{ $index }}][shop_accounting_category_id]" value="{{ $line['shop_accounting_category_id'] ?? '' }}">
                                    <input type="hidden" name="lines[{{ $index }}][amount]" value="{{ $line['amount'] ?? '' }}">
                                    <input type="hidden" name="lines[{{ $index }}][description]" value="{{ $line['description'] ?? '' }}">
                                @endforeach
                            </div>
                        </div>

                        @if ($canEdit)
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <button type="submit" name="submission_action" value="save_draft" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                                    Save Cashbook
                                </button>
                                <button type="submit" name="submission_action" value="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                    {{ $hasEntry && $entry->status === 'recheck_required' ? 'Resubmit' : 'Submit' }}
                                </button>
                            </div>
                        @endif
                    </form>
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
                                <select id="cashbook-line-type" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                    <option value="income">Income</option>
                                    <option value="expense">Expense</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Category</span>
                                <select id="cashbook-line-category" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none"></select>
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

@if ($tab === 'cashbook' && $canEdit)
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
            const amountInput = document.getElementById('cashbook-line-amount');
            const descriptionInput = document.getElementById('cashbook-line-description');
            const descriptionLabel = document.getElementById('cashbook-line-description-label');
            const helpText = document.getElementById('cashbook-line-help');
            const modalTitle = document.getElementById('cashbook-modal-title');

            if (!listEl || !inputsEl || !modalEl || !openButton || !closeButton || !cancelButton || !saveButton || !typeInput || !categoryInput || !amountInput || !descriptionInput || !descriptionLabel || !helpText || !modalTitle) {
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

            const fillCategoryOptions = (type, selectedId = '') => {
                const filtered = categories.filter((category) => category.type === type);
                categoryInput.innerHTML = filtered.map((category) => `
                    <option value="${category.id}" ${String(category.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(category.name)}</option>
                `).join('');
                refreshDescriptionState();
            };

            const refreshDescriptionState = () => {
                const meta = categoryMeta(categoryInput.value);
                const isOther = meta && meta.name === 'Other';
                descriptionLabel.textContent = isOther ? 'Notes Required' : 'Notes';
                helpText.textContent = isOther
                    ? 'Other needs notes so admin can understand the entry.'
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
                            <p class="mt-2 text-sm font-semibold text-slate-500">Use Add Income / Expense to build the daily cashbook.</p>
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
                editIndex = null;
                amountInput.value = '';
                descriptionInput.value = '';
                typeInput.value = 'income';
                fillCategoryOptions('income');
                modalTitle.textContent = 'Add income or expense';
            };

            const openModal = (index = null) => {
                editIndex = index;

                if (index === null) {
                    typeInput.value = 'income';
                    fillCategoryOptions('income');
                    amountInput.value = '';
                    descriptionInput.value = '';
                    modalTitle.textContent = 'Add income or expense';
                } else {
                    const line = lines[index];
                    const meta = categoryMeta(line.shop_accounting_category_id);
                    typeInput.value = meta?.type ?? 'income';
                    fillCategoryOptions(typeInput.value, line.shop_accounting_category_id);
                    amountInput.value = line.amount;
                    descriptionInput.value = line.description ?? '';
                    modalTitle.textContent = 'Update cashbook item';
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

            typeInput?.addEventListener('change', () => fillCategoryOptions(typeInput.value));
            categoryInput?.addEventListener('change', refreshDescriptionState);

            saveButton?.addEventListener('click', () => {
                const categoryId = categoryInput.value;
                const amount = amountInput.value;
                const description = descriptionInput.value.trim();
                const meta = categoryMeta(categoryId);

                if (! categoryId || ! amount || Number(amount) <= 0) {
                    window.showAppAlert?.('Select a category and enter a valid amount.', 'warning');
                    return;
                }

                if (meta?.name === 'Other' && description === '') {
                    window.showAppAlert?.('Add notes when you choose Other.', 'warning');
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

            fillCategoryOptions('income');
            renderList();
        })();
    </script>
    @endpush
@endif
