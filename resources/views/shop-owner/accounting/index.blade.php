@extends('shop-owner.layouts.app')

@php
    $accountingPageMeta = match ($tab ?? 'bills') {
        'cashbook' => ['title' => 'Cashbook', 'description' => 'Daily receipt and closing balance.'],
        'create' => ['title' => 'Create Entry', 'description' => 'Income and expense entry for the day.'],
        'loan' => ['title' => 'Others', 'description' => 'Other shop payments and adjustments.'],
        default => ['title' => 'Bills', 'description' => 'Daily delivery bills and payment status.'],
    };
@endphp
@section('title', 'Accounting')
@section('page_title', $accountingPageMeta['title'])
@section('page_description', $accountingPageMeta['description'])
@section('page_back_url', route('shop.dashboard'))
@php
    $breadcrumbs = [];
@endphp

@section('content')
    @php
        $hasEntry = $entry instanceof \App\Models\ShopAccountingEntry;
        $canEdit = ! $hasEntry || $entry->canBeEditedByShopOwner();
        $loanCategoryIds = $loanCategoryIds ?? collect();
        $recheckLines = $hasEntry
            ? $entry->lines->filter(fn ($line) => $line->review_status === 'recheck_required')->values()
            : collect();
        $loanSettingsByCategory = ($loanCategorySettings ?? collect())->keyBy('shop_accounting_category_id');
        $loanDefaultLines = ! $hasEntry && ! old('lines')
            ? $loanSettingsByCategory
                ->filter(fn ($setting) => (float) $setting->default_daily_amount > 0 && $setting->category?->type === 'expense')
                ->map(fn ($setting) => [
                    'shop_accounting_category_id' => (string) $setting->shop_accounting_category_id,
                    'amount' => (string) $setting->default_daily_amount,
                    'description' => 'Auto paid from Petty',
                    'is_loan_entry' => '1',
                    'funding_source' => 'petty',
                ])
                ->values()
                ->all()
            : [];
        $cashbookInitialLines = collect(old('lines', $hasEntry
            ? $entry->lines->map(fn ($line) => [
                'shop_accounting_category_id' => (string) $line->shop_accounting_category_id,
                'amount' => (string) $line->amount,
                'description' => (string) ($line->description ?? ''),
                'is_loan_entry' => (string) (int) ((bool) $line->is_loan_entry),
                'funding_source' => (string) ($line->funding_source ?: (((bool) $line->is_loan_entry) ? 'petty' : ($line->type === 'expense' ? 'sales' : 'sales'))),
            ])->all()
            : $loanDefaultLines))
            ->filter(fn ($line) => is_array($line))
            ->values();
        $cashbookCategories = $availableCategories->map(fn ($category) => [
            'id' => (int) $category->id,
            'type' => (string) $category->type,
            'cash_effect' => (bool) $category->cash_effect,
            'purpose' => (string) $category->purpose,
            'name' => (string) $category->name,
            'is_loan_category' => $loanCategoryIds->contains((int) $category->id),
            'loan_default_daily_amount' => (float) ($loanSettingsByCategory->get($category->id)?->default_daily_amount ?? 0),
        ])->values();
        $calculatedClosing = (float) ($receiptSummary['entered_closing'] ?? $receiptSummary['expected_closing']);
        $calculatedClosingTone = $calculatedClosing < 0 ? 'rose' : 'emerald';
        $shopAccountingModeLabel = $shop->isOwnedAccountingEnabled() ? 'Manager Managed' : 'Owner Managed';
    @endphp

    <div class="space-y-6">
        @if ($tab === 'bills')
            @php
                $approvedBillInvoices = $selectedBillInvoices->filter(
                    fn (\App\Models\ShopInvoice $invoice): bool => in_array((string) $invoice->delivery_status, ['received_full', 'approved_after_discrepancy'], true)
                        || in_array((string) $invoice->status, ['finalized', 'payment_pending', 'paid'], true)
                        || in_array((string) $invoice->payment_status, ['partially_paid', 'paid'], true)
                );
                $approvedBillDebitTotal = round((float) $approvedBillInvoices->sum('final_total'), 2);
                $dailyBillLines = $selectedBillInvoices
                    ->flatMap(fn (\App\Models\ShopInvoice $invoice) => $invoice->items->map(fn ($item) => [
                        'invoice_number' => $invoice->invoice_number,
                        'product_name' => $item->product_name ?: ($item->product?->name ?? 'Unknown Product'),
                        'unit' => $item->price_unit ?: $item->unit,
                        'approved_qty' => (float) ($item->price_quantity ?: $item->approved_qty),
                        'unit_price' => (float) $item->unit_price,
                        'line_total' => (float) $item->final_line_total,
                    ]))
                    ->values();
            @endphp

            <section class="hidden rounded-[1.5rem] border border-slate-200 bg-white p-3 shadow-sm sm:block sm:p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">{{ $shop->isOwnedAccountingEnabled() ? 'Client: '.($shop->client?->name ?? 'Aishwarya Veg') : 'Shop Bill' }}</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Daily Delivery Bill</h2>
                    </div>

                    <form method="GET" action="{{ route('shop-owner.accounting.index') }}" class="grid gap-2 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-2 sm:grid-cols-3">
                        <input type="hidden" name="tab" value="bills">
                        <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Bill Date</span>
                            <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                        </label>
                        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800">Show Bill</button>
                        <a href="{{ route('shop-owner.accounting.index', ['tab' => 'bills', 'date' => today()->toDateString()]) }}" class="inline-flex h-14 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-800 transition hover:bg-slate-50">Today</a>
                    </form>
                </div>
            </section>

            <form method="GET" action="{{ route('shop-owner.accounting.index') }}" class="grid grid-cols-[1fr_auto_auto] items-center gap-2 sm:hidden">
                <input type="hidden" name="tab" value="bills">
                <label class="min-w-0">
                    <span class="sr-only">Bill Date</span>
                    <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-black text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                </label>
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-black text-white">Show</button>
                <a href="{{ route('shop-owner.accounting.index', ['tab' => 'bills', 'date' => today()->toDateString()]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-800 shadow-sm">Today</a>
            </form>

            <section class="-mx-4 overflow-hidden border-y border-slate-200 bg-white p-3 text-slate-950 shadow-none sm:mx-0 sm:rounded-[1.6rem] sm:border sm:p-5 sm:shadow-sm">
                <div class="font-mono">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-base font-black leading-tight sm:text-lg">Green Leaf Traders - Delivery Bill</p>
                            <p class="mt-1 text-sm font-bold leading-tight text-slate-800">{{ $shop->name }} | {{ $selectedDate->format('d/m/Y') }}</p>
                        </div>
                        <p class="text-sm font-bold text-slate-700">{{ $selectedBillInvoices->count() }} bill{{ $selectedBillInvoices->count() === 1 ? '' : 's' }}</p>
                    </div>

                    <div class="my-4 border-t border-dashed border-slate-300"></div>

                    @if ($dailyBillLines->isEmpty())
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
                            <p class="text-sm font-black text-slate-800">No delivery bill for this date.</p>
                        </div>
                    @else
                        <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-slate-50 sm:block">
                            <table class="min-w-full text-left text-sm">
                                <thead class="border-b border-slate-200 text-[11px] font-black uppercase tracking-[0.16em] text-slate-700">
                                    <tr>
                                        <th class="px-3 py-3">Item</th>
                                        <th class="px-3 py-3 text-right">Qty</th>
                                        <th class="px-3 py-3 text-right">Rate</th>
                                        <th class="px-3 py-3 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @foreach ($dailyBillLines as $line)
                                        <tr>
                                            <td class="px-3 py-3 font-bold">
                                                {{ $line['product_name'] }}
                                                <span class="block text-[11px] font-bold text-slate-600">{{ $line['invoice_number'] }}</span>
                                            </td>
                                            <td class="px-3 py-3 text-right font-black tabular-nums">{{ number_format($line['approved_qty'], 2) }} {{ $line['unit'] }}</td>
                                            <td class="px-3 py-3 text-right font-black tabular-nums">{{ number_format($line['unit_price'], 2) }}</td>
                                            <td class="px-3 py-3 text-right font-black tabular-nums">{{ number_format($line['line_total'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-3 sm:hidden">
                            @foreach ($dailyBillLines as $line)
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-black">{{ $line['product_name'] }}</p>
                                            <p class="mt-1 text-xs font-bold text-slate-600">{{ $line['invoice_number'] }}</p>
                                        </div>
                                        <p class="text-right text-sm font-black tabular-nums">Rs. {{ number_format($line['line_total'], 2) }}</p>
                                    </div>
                                    <p class="mt-2 text-xs font-bold text-slate-700">{{ number_format($line['approved_qty'], 2) }} {{ $line['unit'] }} x Rs. {{ number_format($line['unit_price'], 2) }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="my-4 border-t border-dashed border-slate-300"></div>

                    <div class="space-y-2 text-sm sm:text-base">
                        <div class="grid grid-cols-[1fr_auto] items-center gap-3">
                            <p class="font-bold">Bill Total</p>
                            <p class="font-black tabular-nums">Rs. {{ number_format($dailyBillingSummary['total_billed'], 2) }}</p>
                        </div>
                        <div class="grid grid-cols-[1fr_auto] items-center gap-3">
                            <p class="font-bold">{{ $shop->isOwnedAccountingEnabled() ? 'Approved Debit To Shop' : 'Paid' }}</p>
                            <p class="font-black tabular-nums {{ $shop->isOwnedAccountingEnabled() ? 'text-rose-900' : 'text-emerald-900' }}">
                                Rs. {{ number_format($shop->isOwnedAccountingEnabled() ? $approvedBillDebitTotal : $dailyBillingSummary['total_paid'], 2) }}
                            </p>
                        </div>
                        <div class="grid grid-cols-[1fr_auto] items-center gap-3">
                            <p class="font-bold">{{ $shop->isOwnedAccountingEnabled() ? 'Pending Approval' : 'Balance' }}</p>
                            <p class="font-black tabular-nums {{ $shop->isOwnedAccountingEnabled() ? 'text-amber-900' : 'text-rose-900' }}">
                                Rs. {{ number_format($shop->isOwnedAccountingEnabled() ? max(0, $dailyBillingSummary['total_billed'] - $approvedBillDebitTotal) : $dailyBillingSummary['total_balance'], 2) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    @if ($shop->isOwnedAccountingEnabled())
                        <p class="text-sm font-black text-slate-900">Approved bills are posted as client-shop debit for this bill date.</p>
                        <p class="mt-1 text-sm font-semibold text-slate-700">Green Leaf invoice payment is handled from Finance > Payments. Bills do not create duplicate manual expenses.</p>
                    @else
                        <p class="text-sm font-black text-slate-900">Regular shop bills stay as customer receivable until payment is approved.</p>
                        <p class="mt-1 text-sm font-semibold text-slate-700">Submit bill payment from Finance > Payments.</p>
                    @endif
                </div>
            </section>

            <section class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">All Bills</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950">Recent bill history</h3>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('shop-owner.payments.index') }}" class="inline-flex h-10 items-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800">Open Payments</a>
                        <a href="{{ route('shop-owner.accounting.history', ['tab' => 'bills']) }}" class="inline-flex h-10 items-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-800 transition hover:bg-slate-50">Full History</a>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($invoices as $invoice)
                        <a href="{{ route('shop-owner.finance.show', $invoice) }}" class="block rounded-[1.35rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-200 hover:bg-emerald-50/40">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-950">{{ $invoice->invoice_number }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->business_date?->format('d M Y') }} | {{ $invoice->items->count() }} item{{ $invoice->items->count() === 1 ? '' : 's' }}</p>
                                </div>
                                <p class="whitespace-nowrap text-right text-sm font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
                            </div>
                        </a>
                    @empty
                        @include('shop-owner.components.empty-state', ['title' => 'No delivery bills yet', 'description' => 'Bills will appear here after delivery invoices are generated.'])
                    @endforelse
                </div>

                @if ($invoices->hasPages())
                    <div class="mt-5">{{ $invoices->withQueryString()->links() }}</div>
                @endif
            </section>
        @elseif ($tab === 'loan')
            <section class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <nav class="inline-flex w-fit flex-wrap gap-1 rounded-2xl bg-slate-100 p-1">
                    <a
                        href="{{ route('shop-owner.accounting.index', ['tab' => 'loan', 'others' => 'petty']) }}"
                        class="{{ ($othersSubtab ?? 'petty') === 'petty' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900' }} inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold transition"
                    >
                        Petty
                    </a>
                    <a
                        href="{{ route('shop-owner.accounting.index', ['tab' => 'loan', 'others' => 'company']) }}"
                        class="{{ ($othersSubtab ?? 'petty') === 'company' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900' }} inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold transition"
                    >
                        Company
                    </a>
                </nav>

                @if (($othersSubtab ?? 'petty') === 'petty')
                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Others</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Petty movements</h2>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Cash given, repayments, and cashbook categories paid from loan.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-right">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">{{ $loanBalance < 0 ? 'Overused Balance' : 'Available Balance' }}</p>
                        <p class="mt-1 text-xl font-black {{ $loanBalance < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($loanBalance, 2) }}</p>
                    </div>
                </div>

                <div class="mt-5 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Title</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($loanRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $row['category'] }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-700">{{ $row['title'] }}</p>
                                        @if($row['description'])
                                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['description'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ in_array($row['status'], ['approved', 'finalized'], true) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ str_replace('_', ' ', $row['status']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black {{ (float) $row['signed_amount'] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ (float) $row['signed_amount'] < 0 ? '-' : '+' }} Rs. {{ number_format((float) $row['amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">
                                        Rs. {{ number_format((float) $row['balance'], 2) }}
                                        @if($row['pending_balance'] !== null)
                                            <span class="block text-xs font-semibold text-amber-700">After approval Rs. {{ number_format((float) $row['pending_balance'], 2) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center font-bold text-slate-500">No loan movements yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @else
                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Others</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">Company-funded expenses</h2>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Expenses submitted for Green Leaf review and settlement.</p>
                    </div>
                </div>

                <div class="mt-5 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-right">Settled</th>
                                <th class="px-4 py-3 text-right">Remaining</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($companyPayableLines as $line)
                                @php
                                    $remaining = $line->remainingCompanyPayableAmount();
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $line->entry?->business_date?->format('d M Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $line->category?->name ?? 'Expense' }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-700">{{ $line->description ?: 'Company expense' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ in_array($line->company_payable_status, ['approved'], true) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($line->company_payable_status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">{{ str($line->company_payable_status)->replace('_', ' ')->title() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) ($line->company_payable_amount ?? $line->amount), 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-700">Rs. {{ number_format((float) ($line->company_settled_amount ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($remaining, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center font-bold text-slate-500">No company-funded expenses yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif
            </section>
        @else
            <section @class([
                'rounded-[1.5rem] border border-slate-200 bg-white p-3 shadow-sm sm:p-4',
                'hidden sm:block' => in_array($tab, ['cashbook', 'create'], true),
            ])>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">{{ $shopAccountingModeLabel }}</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Daily Shop Receipt</h2>
                    </div>

                    <form method="GET" action="{{ route('shop-owner.accounting.index') }}" class="grid gap-2 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-2 sm:grid-cols-3">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</span>
                            <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                        </label>
                        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800">Update Receipt</button>
                        <a href="{{ route('shop-owner.accounting.index', ['tab' => $tab, 'date' => today()->toDateString()]) }}" class="inline-flex h-14 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-800 transition hover:bg-slate-50">Today</a>
                    </form>
                </div>
            </section>

            @if (in_array($tab, ['cashbook', 'create'], true))
                <form method="GET" action="{{ route('shop-owner.accounting.index') }}" class="grid grid-cols-[1fr_auto_auto] items-center gap-2 sm:hidden">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <label class="min-w-0">
                        <span class="sr-only">Business Date</span>
                        <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-black text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </label>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-black text-white">Show</button>
                    <a href="{{ route('shop-owner.accounting.index', ['tab' => $tab, 'date' => today()->toDateString()]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-800 shadow-sm">Today</a>
                </form>
            @endif

            @if ($tab === 'cashbook')
            <section class="-mx-4 overflow-hidden border-y border-slate-200 bg-white text-slate-950 shadow-none sm:mx-0 sm:rounded-2xl sm:border sm:shadow-sm">
                <div class="border-b border-dashed border-slate-300 px-3 py-3 sm:px-5 sm:py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-[0.16em] text-slate-500 sm:text-[10px]">Cashbook Receipt</p>
                            <h2 class="mt-0.5 truncate text-base font-black uppercase tracking-wide text-slate-950 sm:text-xl">{{ $shop->name }}</h2>
                            <p class="mt-1 text-xs font-semibold text-slate-600">Opening + cash in - cash out = closing</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-xs font-black text-slate-950">{{ $selectedDate->format('d/m/Y') }}</p>
                            <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">CB-{{ $selectedDate->format('Ymd') }}</p>
                            <a href="{{ route('shop-owner.accounting.cashbook.pdf', ['date' => $selectedDate->toDateString()]) }}" target="_blank" class="mt-2 inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700 hover:bg-slate-100">
                                PDF
                            </a>
                        </div>
                    </div>
                </div>

                <div class="px-3 py-3 sm:px-5 sm:py-4">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                            <p class="truncate text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px]">Opening</p>
                            <p class="mt-1 truncate text-sm font-black text-slate-950 sm:text-xl">Rs. {{ number_format($receiptSummary['opening_balance'], 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                            <p class="truncate text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px]">Income</p>
                            <p class="mt-1 truncate text-sm font-black text-emerald-700 sm:text-xl">Rs. {{ number_format($receiptSummary['total_income'], 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                            <p class="truncate text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px]">Closing</p>
                            <p data-cashbook-closing-display class="mt-1 truncate text-sm font-black sm:text-xl {{ $calculatedClosingTone === 'rose' ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($calculatedClosing, 2) }}</p>
                        </div>
                    </div>

                    <div class="mt-3 overflow-hidden rounded-xl border border-slate-200">
                        <div class="grid grid-cols-[1fr_auto] border-b border-slate-100 bg-slate-50 px-3 py-2 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">
                            <span>Particulars</span>
                            <span class="text-right">Amount</span>
                        </div>
                        <div class="divide-y divide-slate-100 text-sm">
                            @foreach ([
                                ['label' => 'Cash From Sales', 'value' => $receiptSummary['cash_credit'], 'tone' => 'text-emerald-700'],
                                ['label' => 'Cash From Company', 'value' => $receiptSummary['cash_given_to_shop'], 'tone' => 'text-emerald-700'],
                                ['label' => 'Online Payment', 'value' => $receiptSummary['non_cash_income'], 'tone' => 'text-cyan-700'],
                                ['label' => 'Cash Paid To Company', 'value' => $receiptSummary['payment_to_company'], 'tone' => 'text-amber-700'],
                                ['label' => 'Cash Debit', 'value' => $receiptSummary['cash_debit'], 'tone' => 'text-rose-700'],
                            ] as $receiptLine)
                                <div class="grid grid-cols-[1fr_auto] items-center gap-3 px-3 py-2">
                                    <p class="font-bold text-slate-800">{{ $receiptLine['label'] }}</p>
                                    <p class="font-black tabular-nums {{ $receiptLine['tone'] }}">Rs. {{ number_format((float) $receiptLine['value'], 2) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-[1fr_auto] items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Net Sale Balance</p>
                            <p class="mt-0.5 text-xs font-semibold text-slate-600">Daily income minus daily expense</p>
                        </div>
                        <p data-cashbook-net-sale-display class="text-right text-sm font-black tabular-nums sm:text-base {{ (float) $receiptSummary['daily_net_sale'] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format((float) $receiptSummary['daily_net_sale'], 2) }}</p>
                    </div>

                    @if ($receiptSummary['owner_funded'] > 0)
                        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-black text-rose-900">
                            Owner funded Rs. {{ number_format($receiptSummary['owner_funded'], 2) }}
                        </div>
                    @endif
                </div>

                @if ($receiptSummary['difference'] !== null && abs((float) $receiptSummary['difference']) > 0.009)
                    <div class="mt-4 rounded-[1.35rem] border border-amber-300 bg-amber-50 px-4 py-4">
                        <p class="text-sm font-black text-amber-900">Difference: Rs. {{ number_format((float) $receiptSummary['difference'], 2) }}</p>
                        <p class="mt-1 text-sm font-semibold text-amber-800">Entered closing does not match calculated closing. Add a note before submitting if this is expected.</p>
                    </div>
                @endif
            </section>

            @php
                $ledgerStatusTabs = [
                    'draft' => 'Draft / Today',
                    'submitted' => 'Submitted',
                    'approved' => 'Approved',
                    'recheck' => 'Recheck Required',
                ];
            @endphp
            <section id="shop-owner-ledger-status-tabs" class="rounded-[1.25rem] border border-slate-200 bg-white p-2 shadow-sm sm:rounded-[2rem] sm:p-4">
                <div class="flex flex-col gap-2 xl:flex-row xl:items-center xl:justify-between">
                    <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 sm:flex sm:flex-wrap sm:rounded-2xl">
                    @foreach ($ledgerStatusTabs as $statusKey => $statusLabel)
                        <a href="{{ route('shop-owner.accounting.index', array_filter(['tab' => 'cashbook', 'ledger_status' => $statusKey, 'date' => $selectedDate->format('Y-m-d'), 'start_date' => request('start_date'), 'end_date' => request('end_date'), 'ledger_source' => $ledgerSourceFilter === 'greenleaf_direct' ? 'greenleaf_direct' : null])) }}" data-ledger-status-trigger="{{ $statusKey }}" aria-selected="{{ $ledgerStatusTab === $statusKey ? 'true' : 'false' }}" class="inline-flex h-8 items-center justify-center rounded-lg px-2 text-[11px] font-black transition sm:h-10 sm:rounded-xl sm:px-4 sm:text-sm {{ $ledgerStatusTab === $statusKey ? 'bg-slate-950 text-white' : 'text-slate-700 hover:bg-white' }}">
                            {{ $statusLabel }}
                        </a>
                    @endforeach
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1 sm:rounded-2xl">
                            <a href="{{ route('shop-owner.accounting.index', array_filter(['tab' => 'cashbook', 'ledger_status' => $ledgerStatusTab, 'date' => $selectedDate->format('Y-m-d'), 'start_date' => request('start_date'), 'end_date' => request('end_date')])) }}" class="inline-flex h-8 items-center rounded-lg px-3 text-xs font-black transition sm:h-10 sm:rounded-xl sm:px-4 sm:text-sm {{ $ledgerSourceFilter === 'all' ? 'bg-slate-950 text-white' : 'text-slate-700 hover:bg-white' }}">
                                All
                            </a>
                            <a href="{{ route('shop-owner.accounting.index', array_filter(['tab' => 'cashbook', 'ledger_status' => $ledgerStatusTab, 'date' => $selectedDate->format('Y-m-d'), 'start_date' => request('start_date'), 'end_date' => request('end_date'), 'ledger_source' => 'greenleaf_direct'])) }}" class="inline-flex h-8 items-center rounded-lg px-3 text-xs font-black transition sm:h-10 sm:rounded-xl sm:px-4 sm:text-sm {{ $ledgerSourceFilter === 'greenleaf_direct' ? 'bg-cyan-600 text-white' : 'text-cyan-700 hover:bg-white' }}">
                                GreenLeaf Direct
                            </a>
                        </div>
                        <a href="{{ route('shop-owner.accounting.history', ['tab' => 'cashbook']) }}" class="text-xs font-black text-emerald-700 sm:text-sm">Open full history</a>
                    </div>
                </div>

                <form method="GET" action="{{ route('shop-owner.accounting.index') }}" class="mt-2 grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2 sm:mt-4 sm:grid-cols-4 sm:rounded-[1.5rem]">
                    <input type="hidden" name="tab" value="cashbook">
                    <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                    <input type="hidden" name="ledger_status" value="{{ $ledgerStatusTab }}">
                    @if ($ledgerSourceFilter === 'greenleaf_direct')
                        <input type="hidden" name="ledger_source" value="greenleaf_direct">
                    @endif
                    <label class="rounded-xl bg-white px-3 py-2 text-slate-900 shadow-sm sm:rounded-2xl sm:px-4">
                        <span class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-400 sm:text-[10px] sm:tracking-[0.18em]">From</span>
                        <input type="date" name="start_date" value="{{ request('start_date') }}" class="mt-0.5 w-full border-0 bg-transparent p-0 text-xs font-black focus:outline-none focus:ring-0 sm:mt-1 sm:text-sm">
                    </label>
                    <label class="rounded-xl bg-white px-3 py-2 text-slate-900 shadow-sm sm:rounded-2xl sm:px-4">
                        <span class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-400 sm:text-[10px] sm:tracking-[0.18em]">To</span>
                        <input type="date" name="end_date" value="{{ request('end_date') }}" class="mt-0.5 w-full border-0 bg-transparent p-0 text-xs font-black focus:outline-none focus:ring-0 sm:mt-1 sm:text-sm">
                    </label>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-black text-white transition hover:bg-slate-800 sm:h-14 sm:rounded-2xl sm:px-4 sm:text-sm">Filter</button>
                    <a href="{{ route('shop-owner.accounting.index', ['tab' => 'cashbook', 'ledger_status' => $ledgerStatusTab, 'date' => $selectedDate->format('Y-m-d')]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-800 transition hover:bg-slate-50 sm:h-14 sm:rounded-2xl sm:px-4 sm:text-sm">Clear</a>
                </form>

                <div class="mt-4">
                    @foreach ($ledgerStatusTabs as $statusKey => $statusLabel)
                        @php $statusLedgerEntries = $ledgerEntriesByStatus->get($statusKey, collect()); @endphp
                        <div data-ledger-status-panel="{{ $statusKey }}" @class(['space-y-3', 'hidden' => $ledgerStatusTab !== $statusKey])>
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Ledger Status</p>
                                    <h3 class="mt-1 text-lg font-black text-slate-950">{{ $statusLabel }}</h3>
                                </div>
                                @php($ledgerDayCount = $statusLedgerEntries instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $statusLedgerEntries->total() : $statusLedgerEntries->count())
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">{{ $ledgerDayCount }} day{{ $ledgerDayCount === 1 ? '' : 's' }}</p>
                            </div>

                            <div class="space-y-3 md:hidden">
                                @forelse ($statusLedgerEntries as $ledgerDay)
                                    <a href="{{ route('shop-owner.accounting.index', ['tab' => 'cashbook', 'ledger_status' => $statusKey, 'date' => $ledgerDay['date'], 'start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="block rounded-[1.35rem] border border-slate-200 bg-white p-4 shadow-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($ledgerDay['date'])->format('d M Y') }}</p>
                                                <div class="mt-2">
                                                    @include('shop-owner.components.status-badge', ['label' => $ledgerDay['status_label'], 'tone' => $ledgerDay['status_tone']])
                                                </div>
                                            </div>
                                            <p class="text-right text-sm font-black {{ $ledgerDay['closing'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($ledgerDay['closing'], 2) }}</p>
                                        </div>
                                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                                            <div class="rounded-xl bg-slate-50 p-3">
                                                <p class="font-black uppercase tracking-[0.12em] text-slate-400">Income</p>
                                                <p class="mt-1 font-black text-slate-950">Rs. {{ number_format($ledgerDay['income'], 2) }}</p>
                                            </div>
                                            <div class="rounded-xl bg-emerald-50 p-3">
                                                <p class="font-black uppercase tracking-[0.12em] text-emerald-700">Cash From Company</p>
                                                <p class="mt-1 font-black text-emerald-800">Rs. {{ number_format($ledgerDay['cash_given_to_shop'], 2) }}</p>
                                            </div>
                                            <div class="rounded-xl bg-amber-50 p-3">
                                                <p class="font-black uppercase tracking-[0.12em] text-amber-700">Cash Paid To Company</p>
                                                <p class="mt-1 font-black text-amber-800">Rs. {{ number_format($ledgerDay['payment_to_company'], 2) }}</p>
                                            </div>
                                            <div class="rounded-xl bg-rose-50 p-3">
                                                <p class="font-black uppercase tracking-[0.12em] text-rose-700">Cash Debit</p>
                                                <p class="mt-1 font-black text-rose-800">Rs. {{ number_format($ledgerDay['warehouse_expense'] + $ledgerDay['manual_expense'], 2) }}</p>
                                            </div>
                                        </div>
                                    </a>
                                @empty
                                    <div class="rounded-[1.35rem] border border-slate-200 bg-white p-5 text-center">
                                        <p class="font-bold text-slate-500">No {{ strtolower($statusLabel) }} ledger days found.</p>
                                    </div>
                                @endforelse
                            </div>

                            <div class="hidden overflow-x-auto rounded-[1.5rem] border border-slate-200 md:block">
                                <table class="min-w-[58rem] text-left">
                                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3">Date</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3 text-right">Income</th>
                                            <th class="px-4 py-3 text-right">Cash From Company</th>
                                            <th class="px-4 py-3 text-right">Cash Paid To Company</th>
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

                            @if ($statusLedgerEntries instanceof \Illuminate\Contracts\Pagination\Paginator && $statusLedgerEntries->hasPages())
                                <div class="mt-5">{{ $statusLedgerEntries->links() }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <a href="{{ route('shop-owner.accounting.index', ['tab' => 'create', 'date' => $selectedDate->format('Y-m-d'), 'open' => 'line']) }}" aria-label="Create cashbook entry" class="fixed bottom-24 left-4 z-40 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-xl shadow-emerald-900/20 transition hover:bg-emerald-500 lg:bottom-8 lg:left-8">
                <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h14" />
                    <path d="M12 5v14" />
                </svg>
            </a>

            @endif

            @if ($tab === 'create')
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

            <section class="space-y-3 sm:space-y-6">
                <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:rounded-[2rem] sm:p-6">
                    @if ($hasEntry && $entry->status === 'approved')
                        <div class="mb-5 rounded-[1.5rem] border border-cyan-200 bg-cyan-50 px-4 py-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-black text-cyan-950">This day is already approved.</p>
                                    <p class="mt-2 text-sm font-semibold text-cyan-900">Approved entries are read-only. Add any extra income or expense as an adjustment request.</p>
                                </div>
                                <button type="button" id="cashbook-open-adjustment-modal" class="inline-flex h-11 shrink-0 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                                    Add Adjustment
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
                    <form method="POST" action="{{ route('shop-owner.accounting.entries.store') }}" class="space-y-3 sm:space-y-5">
                        @csrf
                        <input type="hidden" name="business_date" value="{{ $selectedDate->format('Y-m-d') }}">

                        <div class="hidden gap-4 sm:grid md:grid-cols-3">
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
                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</span>
                                <p data-cashbook-net-sale-display class="mt-2 text-lg font-black {{ (float) $receiptSummary['daily_net_sale'] < 0 ? 'text-rose-700' : 'text-emerald-700' }} tabular-nums">Rs. {{ number_format((float) $receiptSummary['daily_net_sale'], 2) }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">Includes loan expenses</p>
                            </div>
                        </div>

                        <label class="block sm:rounded-[1.5rem] sm:border sm:border-slate-200 sm:bg-slate-50 sm:p-4">
                            <span class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">Daily Note</span>
                            <input type="text" name="notes" value="{{ old('notes', $entry?->notes) }}" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-950 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 sm:mt-2 sm:border-0 sm:bg-transparent sm:p-0 sm:text-sm sm:focus:ring-0" placeholder="Optional">
                        </label>

                        @if ($hasEntry && in_array($entry->status, ['recheck_required', 'approved', 'submitted'], true))
                            <label class="block rounded-[1.5rem] border border-red-200 bg-red-50 p-4">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-red-700">{{ $entry->status === 'recheck_required' ? 'Reply To Admin Recheck' : 'Update Note For Accounting' }}</span>
                                <textarea name="shop_reply_note" rows="4" class="mt-3 w-full rounded-2xl border border-red-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-red-400 focus:outline-none">{{ old('shop_reply_note', $entry?->shop_reply_note) }}</textarea>
                            </label>
                        @endif

                        <div class="rounded-xl border border-slate-200 bg-white p-3 sm:rounded-[1.5rem] sm:bg-slate-50 sm:p-5">
                            <div class="flex items-center justify-between gap-3 sm:items-start">
                                <div>
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500 sm:text-[10px] sm:tracking-[0.16em]">Ledger Items</p>
                            <h3 class="mt-0.5 text-base font-black text-slate-950 sm:mt-2 sm:text-lg">Add receipt lines</h3>
	                            <p class="mt-2 hidden text-sm font-semibold text-slate-600 sm:block">Cash-effect income is cash from sales. Company cash and petty movements are tracked separately. Approved delivery bills are added automatically as Cash Debit.</p>
                        </div>
                                <button
                                    type="button"
                                    id="cashbook-open-modal"
                                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-slate-950 px-3 text-[11px] font-black uppercase tracking-[0.12em] text-white transition hover:bg-slate-800 sm:h-11 sm:rounded-2xl sm:px-5 sm:text-sm sm:normal-case sm:tracking-normal"
                                >
                                    Add
                                </button>
                            </div>

                            <div id="cashbook-lines-list" class="mt-3 space-y-2 sm:mt-5 sm:space-y-3"></div>
                            <div class="mt-4 hidden rounded-[1.35rem] border border-rose-200 bg-rose-50 px-4 py-4 sm:block">
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
                                    <input type="hidden" name="lines[{{ $index }}][funding_source]" value="{{ $line['funding_source'] ?? '' }}">
                                    <input type="hidden" name="lines[{{ $index }}][is_loan_entry]" value="{{ $line['is_loan_entry'] ?? '0' }}">
                                @endforeach
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <button type="submit" name="submission_action" value="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-emerald-600 px-4 text-[11px] font-black uppercase tracking-[0.12em] text-white transition hover:bg-emerald-500 sm:h-11 sm:rounded-2xl sm:px-5 sm:text-sm sm:normal-case sm:tracking-normal">
                                {{ $hasEntry ? 'Submit Update To Admin' : 'Submit To Admin Approval' }}
                            </button>
                            <p class="hidden text-xs font-bold text-slate-500 sm:block">This sends the daily receipt to accounting approval.</p>
                        </div>
                    </form>
                    @elseif ($hasEntry)
                        <div class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-4">
                                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Opening Cash</p>
                                    <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format($receiptSummary['opening_balance'], 2) }}</p>
                                </div>
                                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Closing Cash</p>
                                    <p class="mt-2 text-lg font-black {{ $calculatedClosingTone === 'rose' ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($calculatedClosing, 2) }}</p>
                                </div>
                                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                                    <p class="mt-2 text-lg font-black {{ (float) $receiptSummary['daily_net_sale'] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format((float) $receiptSummary['daily_net_sale'], 2) }}</p>
                                </div>
                                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Approved By</p>
                                    <p class="mt-2 text-sm font-black text-slate-950">{{ $entry->reviewedBy?->name ?? 'Accounting' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $entry->reviewed_at?->format('d M Y h:i A') }}</p>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-[1.5rem] border border-slate-200">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
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
                                                <td class="px-4 py-3 font-black text-slate-950">
                                                    {{ $line->category?->name ?? 'Category removed' }}
                                                    @if($line->type === 'expense' && in_array((string) $line->funding_source, ['sales', 'petty', 'company'], true))
                                                        <span class="mt-1 block w-fit rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.14em] text-violet-700">
                                                            {{ match ((string) $line->funding_source) {
                                                                'petty' => 'Petty Cash',
                                                                'company' => 'Cash From Company',
                                                                default => 'Cash From Sales',
                                                            } }}
                                                        </span>
                                                    @elseif((bool) $line->is_loan_entry && $line->type === 'expense')
                                                        <span class="mt-1 block w-fit rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.14em] text-violet-700">Petty Cash</span>
                                                    @endif
                                                    @if($line->company_payable_status === 'rejected' && filled($line->company_rejection_reason))
                                                        <span class="mt-1 block text-xs font-semibold text-rose-700">Rejected: {{ $line->company_rejection_reason }}</span>
                                                    @endif
                                                </td>
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

            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:hidden">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">{{ $shopAccountingModeLabel }}</p>
                        <h3 class="mt-0.5 truncate text-sm font-black text-slate-950">{{ $shop->name }}</h3>
                    </div>
                    <a href="{{ route('shop-owner.accounting.history', ['tab' => $tab]) }}" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700" title="History" aria-label="History">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 3v5h5" />
                            <path d="M3.05 13A9 9 0 1 0 6 5.3L3 8" />
                            <path d="M12 7v5l4 2" />
                        </svg>
                    </a>
                </div>

                <form method="GET" action="{{ route('shop-owner.accounting.index') }}" class="mt-3 grid grid-cols-[1fr_auto_auto] gap-2">
                    <input type="hidden" name="tab" value="create">
                    <label class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5">
                        <span class="block text-[8px] font-black uppercase tracking-[0.12em] text-slate-500">Date</span>
                        <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="mt-0.5 w-full border-0 bg-transparent p-0 text-[11px] font-black text-slate-950 focus:outline-none focus:ring-0">
                    </label>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-slate-950 px-3 text-[10px] font-black uppercase tracking-[0.12em] text-white">Show</button>
                    <a href="{{ route('shop-owner.accounting.index', ['tab' => 'create', 'date' => today()->toDateString()]) }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">Today</a>
                </form>
            </section>

            @if ($canEdit)
                <div id="cashbook-line-modal" class="fixed inset-0 z-[80] hidden items-center justify-center overflow-y-auto bg-slate-950/50 px-3 py-4 sm:px-4 sm:py-8">
                    <div class="mx-auto max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white p-3 shadow-2xl sm:rounded-[2rem] sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-emerald-700 sm:text-[10px] sm:tracking-[0.18em]">Daily Shop Receipt</p>
                                <h3 id="cashbook-modal-title" class="mt-0.5 truncate text-base font-black text-slate-950 sm:mt-2 sm:text-xl">Add credit or debit</h3>
                                <p class="mt-2 hidden text-sm font-semibold text-slate-600 sm:block">Select a category. Cash-effect categories change the closing balance; online categories do not.</p>
                            </div>
                            <button type="button" id="cashbook-close-modal" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 sm:h-11 sm:w-11 sm:rounded-2xl" aria-label="Close">
                                <svg class="h-4 w-4 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M18 6 6 18" />
                                    <path d="m6 6 12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-3 space-y-3 sm:mt-5 sm:space-y-4">
                            <label class="block">
                                <span class="block text-[9px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-[10px] sm:tracking-[0.16em]">Type</span>
                                <input id="cashbook-line-type" type="hidden" value="income">
                                <div class="relative mt-1 sm:mt-2">
                                    <button id="cashbook-line-type-trigger" type="button" class="flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-2 text-left text-xs font-black text-slate-900 transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 sm:h-auto sm:rounded-[1.6rem] sm:bg-slate-50 sm:px-5 sm:py-3.5 sm:text-base sm:shadow-[inset_0_1px_0_rgba(255,255,255,0.9)] sm:focus:bg-white sm:focus:ring-4 sm:focus:ring-emerald-500/10" aria-haspopup="listbox" aria-expanded="false">
                                        <span id="cashbook-line-type-label">Income</span>
                                        <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                        </svg>
                                    </button>
                                    <div id="cashbook-line-type-panel" class="absolute inset-x-0 top-[calc(100%+0.25rem)] z-20 hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl sm:top-[calc(100%+0.6rem)] sm:rounded-[1.45rem] sm:p-2 sm:shadow-[0_20px_45px_rgba(15,23,42,0.16)]" role="listbox" aria-label="Cashbook type">
                                        <button type="button" data-cashbook-type-option data-value="income" data-label="Income" class="flex w-full items-center rounded-lg px-2.5 py-2 text-left text-xs font-black text-slate-900 transition hover:bg-emerald-50 hover:text-emerald-700 sm:rounded-[1rem] sm:px-4 sm:py-3 sm:text-sm">
                                            Income / Credit
                                        </button>
                                        <button type="button" data-cashbook-type-option data-value="expense" data-label="Expense" class="flex w-full items-center rounded-lg px-2.5 py-2 text-left text-xs font-black text-slate-900 transition hover:bg-amber-50 hover:text-amber-700 sm:rounded-[1rem] sm:px-4 sm:py-3 sm:text-sm">
                                            Expense / Debit
                                        </button>
                                    </div>
                                </div>
                            </label>
                            <label class="block">
                                <span class="block text-[9px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-[10px] sm:tracking-[0.16em]">Category</span>
                                <input id="cashbook-line-category" type="hidden" value="">
                                <div class="relative mt-1 sm:mt-2">
                                    <button id="cashbook-line-category-trigger" type="button" class="flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-2 text-left text-xs font-black text-slate-900 transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 sm:h-auto sm:rounded-[1.6rem] sm:bg-slate-50 sm:px-5 sm:py-3.5 sm:text-base sm:shadow-[inset_0_1px_0_rgba(255,255,255,0.9)] sm:focus:bg-white sm:focus:ring-4 sm:focus:ring-emerald-500/10" aria-haspopup="listbox" aria-expanded="false">
                                        <span id="cashbook-line-category-label" class="text-slate-400">Select category</span>
                                        <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                        </svg>
                                    </button>
                                    <div id="cashbook-line-category-panel" class="absolute inset-x-0 top-[calc(100%+0.25rem)] z-20 hidden max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl sm:top-[calc(100%+0.6rem)] sm:max-h-72 sm:rounded-[1.45rem] sm:p-2 sm:shadow-[0_20px_45px_rgba(15,23,42,0.16)]" role="listbox" aria-label="Cashbook category"></div>
                                </div>
                            </label>
                            <label class="block">
                                <span class="block text-[9px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-[10px] sm:tracking-[0.16em]">Amount</span>
                                <input id="cashbook-line-amount" type="number" min="0.01" step="0.01" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-right text-sm font-black text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 sm:mt-2 sm:h-auto sm:rounded-2xl sm:bg-slate-50 sm:px-4 sm:py-3 sm:font-semibold sm:focus:ring-0" placeholder="0.00">
                            </label>
                            <label class="block" id="cashbook-funding-wrap">
                                <span class="block text-[9px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-[10px] sm:tracking-[0.16em]">Paid From</span>
                                <select id="cashbook-line-funding" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs font-black text-slate-950 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-100 sm:mt-2 sm:h-auto sm:rounded-2xl sm:border-violet-200 sm:bg-violet-50 sm:px-4 sm:py-3 sm:text-sm sm:text-violet-950 sm:focus:border-violet-500 sm:focus:ring-0">
                                    <option value="sales">Cash From Sales</option>
                                    <option value="petty">Petty Cash</option>
                                    <option value="company">Company</option>
                                </select>
                                <span id="cashbook-line-loan-help" class="mt-1 block text-[10px] font-semibold text-slate-500 sm:text-xs sm:text-violet-700">This expense will be deducted from cash from sales.</span>
                                <input id="cashbook-line-loan" type="checkbox" class="hidden">
                            </label>
                            <label class="block">
                                <span id="cashbook-line-description-label" class="block text-[9px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-[10px] sm:tracking-[0.16em]">Notes</span>
                                <textarea id="cashbook-line-description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 sm:mt-2 sm:rounded-2xl sm:bg-slate-50 sm:px-4 sm:py-3 sm:text-sm sm:focus:ring-0" placeholder="Add notes"></textarea>
                            </label>
                            <p id="cashbook-line-help" class="text-[10px] font-semibold text-slate-500 sm:text-xs">Other needs notes so admin can understand the entry.</p>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 sm:mt-6 sm:flex sm:flex-row sm:gap-3">
                            <button type="button" id="cashbook-save-line" class="inline-flex h-10 items-center justify-center rounded-lg bg-emerald-600 px-3 text-[11px] font-black uppercase tracking-[0.12em] text-white transition hover:bg-emerald-500 sm:h-11 sm:rounded-2xl sm:px-5 sm:text-sm sm:normal-case sm:tracking-normal">
                                Save Item
                            </button>
                            <button type="button" id="cashbook-cancel-line" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-[11px] font-black uppercase tracking-[0.12em] text-slate-800 transition hover:bg-slate-50 sm:h-11 sm:rounded-2xl sm:px-5 sm:text-sm sm:normal-case sm:tracking-normal">
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
                                <h3 class="mt-2 text-xl font-black text-slate-950">Add adjustment income or expense</h3>
                                <p class="mt-2 text-sm font-semibold text-slate-600">This creates a separate pending adjustment for {{ $selectedDate->format('d M Y') }}. Use it only for transactions missed from the approved daily cashbook.</p>
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
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Adjustment Note</span>
                                <textarea name="lines[0][description]" rows="3" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Explain why this was not in the approved daily cashbook"></textarea>
                            </label>

                            <label class="block">
                                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Request Note</span>
                                <input type="text" name="notes" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none" placeholder="Optional note for accounting">
                            </label>

                            <div class="flex flex-col gap-3 sm:flex-row">
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                    Send Adjustment To Admin
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
        @endif
    </div>
@endsection

@if (in_array($tab, ['cashbook', 'create'], true))
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
            const loanInput = document.getElementById('cashbook-line-loan');
            const fundingInput = document.getElementById('cashbook-line-funding');
            const fundingWrap = document.getElementById('cashbook-funding-wrap');
            const loanHelp = document.getElementById('cashbook-line-loan-help');
            const descriptionInput = document.getElementById('cashbook-line-description');
            const descriptionLabel = document.getElementById('cashbook-line-description-label');
            const helpText = document.getElementById('cashbook-line-help');
            const modalTitle = document.getElementById('cashbook-modal-title');
            const openingDisplay = document.getElementById('cashbook-opening-display');
            const closingDisplays = document.querySelectorAll('[data-cashbook-closing-display]');
            const netSaleDisplays = document.querySelectorAll('[data-cashbook-net-sale-display]');

            if (!listEl || !inputsEl || !modalEl || !openButton || !closeButton || !cancelButton || !saveButton || !typeInput || !categoryInput || !typeTrigger || !typeLabel || !typePanel || !categoryTrigger || !categoryLabel || !categoryPanel || !amountInput || !loanInput || !fundingInput || !fundingWrap || !loanHelp || !descriptionInput || !descriptionLabel || !helpText || !modalTitle) {
                return;
            }

            let editIndex = null;
            let lines = Array.isArray(initialLines)
                ? initialLines
                    .filter(line => line && line.shop_accounting_category_id && line.amount)
                    .map(line => ({
                        ...line,
                        funding_source: line.funding_source || (['1', 1, true, 'true'].includes(line.is_loan_entry) ? 'petty' : 'sales'),
                        is_loan_entry: ['1', 1, true, 'true'].includes(line.is_loan_entry) || line.funding_source === 'petty',
                    }))
                : [];
            const openingCash = Number(openingDisplay?.dataset.openingCash ?? 0);

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const categoryMeta = (categoryId) => categories.find((category) => String(category.id) === String(categoryId)) ?? null;
            const fundingSourceOf = (line) => line?.funding_source || (['1', 1, true, 'true'].includes(line?.is_loan_entry) ? 'petty' : 'sales');
            const isLoanLine = (line) => {
                const meta = categoryMeta(line?.shop_accounting_category_id);

                return meta?.type === 'expense' && (fundingSourceOf(line) === 'petty' || ['1', 1, true, 'true'].includes(line?.is_loan_entry));
            };
            const fundingHelpText = (source) => {
                if (source === 'petty') {
                    return 'This expense will be deducted from the shop petty cash balance.';
                }
                if (source === 'company') {
                    return 'This expense will be submitted as cash from company for review and settlement.';
                }

                return 'This expense will be deducted from cash from sales.';
            };
            const cashbookLabel = (meta, line = null) => {
                if (!meta) {
                    return 'Entry';
                }

                if (meta.type === 'expense') {
                    const source = fundingSourceOf(line);
                    if (source === 'petty') {
                        return 'Petty Cash';
                    }
                    if (source === 'company') {
                        return 'Cash From Company';
                    }

                    return 'Cash From Sales';
                }

                if (meta.type === 'income') {
                    return meta.cash_effect ? 'Cash From Sales' : 'Online Payment';
                }

                return meta.cash_effect ? 'Cash Debit' : 'Online Payment Debit';
            };
            const formatMoney = (amount) => `Rs. ${Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const renderClosingPreview = () => {
                if (!closingDisplays.length && !netSaleDisplays.length) {
                    return;
                }

                const totals = lines.reduce((carry, line) => {
                    const meta = categoryMeta(line.shop_accounting_category_id);
                    const amount = Number(line.amount);

                    if (!meta || !Number.isFinite(amount)) {
                        return carry;
                    }

                    carry.netSale += meta.type === 'income' ? amount : -amount;

                    if (!meta.cash_effect) {
                        return carry;
                    }

                    if (isLoanLine(line) || fundingSourceOf(line) === 'company') {
                        return carry;
                    }

                    carry.cashMovement += meta.type === 'income' ? amount : -amount;

                    return carry;
                }, { cashMovement: 0, netSale: 0 });

                const closingBalance = openingCash + totals.cashMovement;

                closingDisplays.forEach((display) => {
                    display.textContent = formatMoney(closingBalance);
                });
                netSaleDisplays.forEach((display) => {
                    display.textContent = formatMoney(totals.netSale);
                    display.classList.toggle('text-rose-700', totals.netSale < 0);
                    display.classList.toggle('text-rose-900', totals.netSale < 0);
                    display.classList.toggle('text-emerald-700', totals.netSale >= 0);
                    display.classList.toggle('text-emerald-900', totals.netSale >= 0);
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
                        class="flex w-full items-center rounded-lg px-2.5 py-2 text-left text-xs font-black transition sm:rounded-[1rem] sm:px-4 sm:py-3 sm:text-sm ${
                            String(category.id) === String(selectedId)
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'text-slate-900 hover:bg-slate-100'
                        }"
                    >
                        <span>${escapeHtml(category.name)}</span>
                        <span class="ml-auto text-[8px] font-black uppercase tracking-[0.1em] text-slate-400 sm:text-[10px] sm:tracking-[0.12em]">${category.is_loan_category ? 'Petty default' : escapeHtml(cashbookLabel(category))}</span>
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
                refreshLoanState();
            };

            const refreshLoanState = () => {
                const meta = categoryMeta(categoryInput.value);
                const canUseFunding = meta?.type === 'expense';
                fundingWrap.classList.toggle('hidden', !canUseFunding);
                fundingInput.disabled = !canUseFunding;

                if (!canUseFunding) {
                    fundingInput.value = 'sales';
                    loanInput.checked = false;
                    loanHelp.textContent = 'Paid From applies to expense lines.';
                    return;
                }

                loanHelp.textContent = fundingHelpText(fundingInput.value);
                loanInput.checked = fundingInput.value === 'petty';
            };

            fundingInput?.addEventListener('change', refreshLoanState);

            const renderInputs = () => {
                inputsEl.innerHTML = lines.map((line, index) => `
                    <input type="hidden" name="lines[${index}][shop_accounting_category_id]" value="${escapeHtml(line.shop_accounting_category_id)}">
                    <input type="hidden" name="lines[${index}][amount]" value="${escapeHtml(line.amount)}">
                    <input type="hidden" name="lines[${index}][description]" value="${escapeHtml(line.description ?? '')}">
                    <input type="hidden" name="lines[${index}][funding_source]" value="${escapeHtml(fundingSourceOf(line))}">
                    <input type="hidden" name="lines[${index}][is_loan_entry]" value="${isLoanLine(line) ? '1' : '0'}">
                `).join('');
            };

            const renderList = () => {
                if (lines.length === 0) {
                    listEl.innerHTML = `
                        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-6 text-center sm:rounded-[1.5rem] sm:px-4 sm:py-8">
                            <p class="text-xs font-black text-slate-900 sm:text-sm">No items added yet.</p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-500 sm:mt-2 sm:text-sm">Use Add to create income or expense.</p>
                        </div>
                    `;
                    renderInputs();
                    return;
                }

                listEl.innerHTML = lines.map((line, index) => {
                    const meta = categoryMeta(line.shop_accounting_category_id);
                    const typeTone = isLoanLine(line)
                        ? 'border-violet-200 bg-violet-50 text-violet-700'
                        : meta?.type === 'income' && meta?.cash_effect
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : meta?.type === 'income'
                                ? 'border-cyan-200 bg-cyan-50 text-cyan-700'
                                : 'border-amber-200 bg-amber-50 text-amber-700';

                    return `
                        <div class="rounded-xl border border-slate-200 bg-white p-2.5 sm:rounded-[1.5rem] sm:p-4">
                            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2 sm:flex sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                <div class="min-w-0">
                                    <div class="flex min-w-0 flex-wrap items-center gap-1.5 sm:gap-2">
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[8px] font-black uppercase tracking-[0.1em] sm:px-3 sm:py-1 sm:text-[10px] sm:tracking-[0.16em] ${typeTone}">
                                            ${escapeHtml(cashbookLabel(meta, line))}
                                        </span>
                                        <span class="truncate text-xs font-black text-slate-950 sm:text-sm">${escapeHtml(meta?.name ?? 'Category')}</span>
                                    </div>
                                    <p class="mt-1 text-sm font-black text-slate-950 sm:mt-3 sm:text-2xl">Rs. ${Number(line.amount).toFixed(2)}</p>
                                    ${line.description ? `<p class="mt-1 truncate text-[10px] font-semibold text-slate-600 sm:mt-2 sm:text-sm">${escapeHtml(line.description)}</p>` : ''}
                                </div>
                                <div class="flex gap-1 sm:gap-2">
                                    <button type="button" data-edit-index="${index}" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-700 transition hover:bg-slate-100 sm:h-10 sm:w-auto sm:rounded-2xl sm:px-4 sm:text-xs sm:font-black sm:uppercase sm:tracking-[0.16em]" aria-label="Edit">
                                        <svg class="h-3.5 w-3.5 sm:hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                        <span class="hidden sm:inline">Edit</span>
                                    </button>
                                    <button type="button" data-remove-index="${index}" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 sm:h-10 sm:w-auto sm:rounded-2xl sm:border-rose-200 sm:bg-rose-50 sm:px-4 sm:text-xs sm:font-black sm:uppercase sm:tracking-[0.16em] sm:text-rose-700 sm:hover:bg-rose-100" aria-label="Remove">
                                        <svg class="h-3.5 w-3.5 sm:hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        <span class="hidden sm:inline">Remove</span>
                                    </button>
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
                modalEl.classList.remove('flex');
                closeDropdowns();
                editIndex = null;
                amountInput.value = '';
                loanInput.checked = false;
                fundingInput.value = 'sales';
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
                    loanInput.checked = false;
                    fundingInput.value = 'sales';
                    descriptionInput.value = '';
                    modalTitle.textContent = 'Add credit or debit';
                } else {
                    const line = lines[index];
                    const meta = categoryMeta(line.shop_accounting_category_id);
                    setTypeValue(meta?.type ?? 'income', meta?.type === 'expense' ? 'Expense' : 'Income');
                    fillCategoryOptions(typeInput.value, line.shop_accounting_category_id);
                    amountInput.value = line.amount;
                    fundingInput.value = fundingSourceOf(line);
                    loanInput.checked = isLoanLine(line);
                    descriptionInput.value = line.description ?? '';
                    modalTitle.textContent = 'Update receipt line';
                }

                refreshLoanState();
                modalEl.classList.remove('hidden');
                modalEl.classList.add('flex');
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
                    funding_source: meta?.type === 'expense' ? fundingInput.value : null,
                    is_loan_entry: meta?.type === 'expense' && fundingInput.value === 'petty',
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

            if (new URLSearchParams(window.location.search).get('open') === 'line') {
                window.requestAnimationFrame(() => openModal());
            }
        })();
    </script>
    @endpush
@endif
