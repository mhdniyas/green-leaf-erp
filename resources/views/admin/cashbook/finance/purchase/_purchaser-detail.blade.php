@php
    $purchaserTabs = [
        'overview' => 'Overview',
        'purchases' => 'Purchases',
        'vendors' => 'Vendors',
        'categories' => 'Categories',
        'finance' => 'Finance',
    ];
    $purchaserRouteParameters = ['purchaser' => $record->public_uuid] + $detailContext;
    $purchaserTabUrl = fn (string $targetTab, array $extra = []): string => route(
        'admin.cashbook.finance.purchase.purchasers.show',
        array_merge($purchaserRouteParameters, ['tab' => $targetTab], $extra),
    );
@endphp

<header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <nav class="flex flex-wrap items-center gap-1 text-xs font-bold text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('admin.cashbook.index') }}" class="hover:text-emerald-700">Cashbook</a><span>/</span>
            <a href="{{ route('admin.cashbook.finance.purchase') }}" class="hover:text-emerald-700">Purchase</a><span>/</span>
            <a href="{{ $listRoute }}" class="hover:text-emerald-700">Purchasers</a><span>/</span>
            <span>{{ $record->name }}</span><span>/</span><span class="text-slate-900">{{ $purchaserTabs[$tab] }}</span>
        </nav>
        <h1 class="mt-2 text-2xl font-black text-slate-950">{{ $record->name }}</h1>
        <p class="mt-1 text-xs font-bold text-slate-500">{{ $filters['start_date'] }} to {{ $filters['end_date'] }}</p>
    </div>
</header>

@if(session('success'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-800">{{ $errors->first() }}</div>
@endif

<nav class="flex gap-2 overflow-x-auto pb-1 text-xs font-black" aria-label="Purchaser detail sections">
    @foreach($purchaserTabs as $tabKey => $tabLabel)
        <a href="{{ $purchaserTabUrl($tabKey) }}" class="min-w-max rounded-lg border px-3 py-2 {{ $tab === $tabKey ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300' }}">{{ $tabLabel }}</a>
    @endforeach
</nav>

@if($tab === 'overview')
    <section>
        <div class="mb-3 flex items-center justify-between gap-3">
            <div><h2 class="font-black text-slate-950">Purchase Summary</h2><p class="text-xs font-semibold text-slate-500">Selected period</p></div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Total Purchase', $summary->total_purchase, true, $purchaserTabUrl('purchases', ['payment' => 'all'])],
                ['Cash Purchase', $summary->cash_purchase, true, $purchaserTabUrl('purchases', ['payment' => 'cash'])],
                ['Credit Purchase', $summary->credit_purchase, true, $purchaserTabUrl('purchases', ['payment' => 'credit'])],
                ['Invoices', $summary->invoice_count, false, $purchaserTabUrl('purchases', ['payment' => 'all'])],
            ] as [$label, $value, $money, $cardUrl])
                <a href="{{ $cardUrl }}" class="group rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-600"><span class="flex items-center justify-between gap-2 text-[10px] font-black uppercase text-slate-400"><span>{{ $label }}</span><i data-lucide="arrow-up-right" class="h-3.5 w-3.5 text-slate-300 group-hover:text-emerald-600"></i></span><strong class="mt-2 block font-mono text-lg text-slate-950">{{ $money ? '₹'.number_format((float) $value, 2) : number_format((int) $value) }}</strong></a>
            @endforeach
        </div>
    </section>

    <section>
        <div class="mb-3 flex items-center justify-between gap-3">
            <div><h2 class="font-black text-slate-950">Finance Summary</h2><p class="text-xs font-semibold text-slate-500">Current cumulative balance</p></div>
            <a href="{{ $purchaserTabUrl('finance') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg bg-emerald-700 px-3 text-xs font-black text-white">View Finance <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach([
                ['Company Funding', $financeSummary['cash_given'], $purchaserTabUrl('finance').'#finance-transactions'],
                ['Cash Used', $financeSummary['cash_used'], $purchaserTabUrl('finance', ['finance_payment' => 'cash']).'#payment-history'],
                ['Current Advance / Balance', $financeSummary['remaining_advance'], $purchaserTabUrl('finance').'#finance-transactions'],
            ] as [$label, $value, $cardUrl])
                <a href="{{ $cardUrl }}" class="group rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-600"><span class="flex items-center justify-between gap-2 text-[10px] font-black uppercase text-slate-400"><span>{{ $label }}</span><i data-lucide="arrow-up-right" class="h-3.5 w-3.5 text-slate-300 group-hover:text-emerald-600"></i></span><strong class="mt-2 block font-mono text-lg text-slate-950">₹{{ number_format((float) $value, 2) }}</strong></a>
            @endforeach
        </div>
    </section>
@elseif($tab === 'purchases')
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4"><h2 class="font-black text-slate-950">Purchases</h2><p class="mt-1 text-xs text-slate-500">Purchaser-scoped records for the selected period.</p></div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[52rem] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-3">Date</th><th class="p-3">Invoice</th><th class="p-3">Vendor</th><th class="p-3">Payment</th><th class="p-3">Categories</th><th class="p-3 text-right">Amount</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($detail['invoices'] as $invoice)<tr><td class="p-3 font-mono">{{ $invoice->business_date }}</td><td class="p-3"><a class="font-black text-emerald-700" href="{{ route('purchasing.invoices.show', $invoice->invoice_public_uuid) }}">{{ $invoice->invoice_number }}</a></td><td class="p-3">{{ $invoice->supplier_name }}</td><td class="p-3 uppercase">{{ $invoice->payment_class }}</td><td class="p-3">{{ $invoice->categories }}</td><td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $invoice->total_purchase, 2) }}</td></tr>@empty<tr><td colspan="6" class="p-6 text-center text-slate-400">No purchases in this period.</td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="divide-y divide-slate-100 md:hidden">@forelse($detail['invoices'] as $invoice)<article class="space-y-2 p-4 text-xs"><div class="flex items-start justify-between gap-3"><a class="font-black text-emerald-700" href="{{ route('purchasing.invoices.show', $invoice->invoice_public_uuid) }}">{{ $invoice->invoice_number }}</a><strong class="font-mono">₹{{ number_format((float) $invoice->total_purchase, 2) }}</strong></div><div class="flex justify-between gap-3 text-slate-500"><span>{{ $invoice->business_date }}</span><span class="uppercase">{{ $invoice->payment_class }}</span></div><p class="font-semibold text-slate-700">{{ $invoice->supplier_name }}</p><p class="text-slate-500">{{ $invoice->categories }}</p></article>@empty<p class="p-6 text-center text-sm text-slate-400">No purchases in this period.</p>@endforelse</div>
        @if($detail['invoices']->hasPages())<div class="border-t border-slate-200 p-4">{{ $detail['invoices']->links() }}</div>@endif
    </section>
@elseif($tab === 'vendors')
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4"><h2 class="font-black text-slate-950">Vendors</h2><p class="mt-1 text-xs text-slate-500">Suppliers used during the selected period.</p></div>
        <div class="hidden overflow-x-auto md:block"><table class="w-full min-w-[44rem] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-3">Vendor</th><th class="p-3">Categories</th><th class="p-3 text-right">Cash</th><th class="p-3 text-right">Credit</th><th class="p-3 text-right">Total</th><th class="p-3 text-right">Invoices</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($detail['vendors'] as $vendor)<tr><td class="p-3"><a href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $vendor->supplier_public_uuid] + $detailContext) }}" class="font-black text-emerald-700">{{ $vendor->supplier_name }}</a></td><td class="p-3">{{ collect(explode(',', (string) $vendor->category_tags))->map(fn ($tag) => explode('|', $tag, 2)[1] ?? $tag)->join(', ') }}</td><td class="p-3 text-right font-mono">₹{{ number_format((float) $vendor->cash_purchase, 2) }}</td><td class="p-3 text-right font-mono">₹{{ number_format((float) $vendor->credit_purchase, 2) }}</td><td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $vendor->total_purchase, 2) }}</td><td class="p-3 text-right font-mono">{{ number_format((int) $vendor->invoice_count) }}</td></tr>@empty<tr><td colspan="6" class="p-6 text-center text-slate-400">No vendors in this period.</td></tr>@endforelse</tbody></table></div>
        <div class="divide-y divide-slate-100 md:hidden">@forelse($detail['vendors'] as $vendor)<a href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $vendor->supplier_public_uuid] + $detailContext) }}" class="block space-y-2 p-4 text-xs"><div class="flex justify-between gap-3"><strong class="text-emerald-700">{{ $vendor->supplier_name }}</strong><strong class="font-mono">₹{{ number_format((float) $vendor->total_purchase, 2) }}</strong></div><p class="text-slate-500">{{ collect(explode(',', (string) $vendor->category_tags))->map(fn ($tag) => explode('|', $tag, 2)[1] ?? $tag)->join(', ') }}</p><div class="grid grid-cols-3 gap-2 text-slate-600"><span>Cash<br><b class="font-mono">₹{{ number_format((float) $vendor->cash_purchase, 2) }}</b></span><span>Credit<br><b class="font-mono">₹{{ number_format((float) $vendor->credit_purchase, 2) }}</b></span><span>Invoices<br><b class="font-mono">{{ number_format((int) $vendor->invoice_count) }}</b></span></div></a>@empty<p class="p-6 text-center text-sm text-slate-400">No vendors in this period.</p>@endforelse</div>
    </section>
@elseif($tab === 'categories')
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4"><h2 class="font-black text-slate-950">Categories</h2><p class="mt-1 text-xs text-slate-500">Item-level purchase mix for the selected period.</p></div>
        <div class="hidden overflow-x-auto md:block"><table class="w-full min-w-[40rem] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-3">Category</th><th class="p-3 text-right">Purchase Value</th><th class="p-3 text-right">Cash</th><th class="p-3 text-right">Credit</th><th class="p-3 text-right">Vendors</th><th class="p-3 text-right">Invoices</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($detail['categories'] as $category)<tr><td class="p-3"><a href="{{ route('admin.cashbook.finance.purchase.categories.show', ['category' => $category->category_id] + $detailContext) }}" class="font-black text-emerald-700">{{ $category->category_name }}</a></td><td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $category->total_purchase, 2) }}</td><td class="p-3 text-right font-mono">₹{{ number_format((float) $category->cash_purchase, 2) }}</td><td class="p-3 text-right font-mono">₹{{ number_format((float) $category->credit_purchase, 2) }}</td><td class="p-3 text-right font-mono">{{ number_format((int) $category->vendor_count) }}</td><td class="p-3 text-right font-mono">{{ number_format((int) $category->invoice_count) }}</td></tr>@empty<tr><td colspan="6" class="p-6 text-center text-slate-400">No categories in this period.</td></tr>@endforelse</tbody></table></div>
        <div class="divide-y divide-slate-100 md:hidden">@forelse($detail['categories'] as $category)<a href="{{ route('admin.cashbook.finance.purchase.categories.show', ['category' => $category->category_id] + $detailContext) }}" class="block space-y-2 p-4 text-xs"><div class="flex justify-between gap-3"><strong class="text-emerald-700">{{ $category->category_name }}</strong><strong class="font-mono">₹{{ number_format((float) $category->total_purchase, 2) }}</strong></div><div class="grid grid-cols-4 gap-2 text-slate-600"><span>Cash<br><b class="font-mono">₹{{ number_format((float) $category->cash_purchase, 2) }}</b></span><span>Credit<br><b class="font-mono">₹{{ number_format((float) $category->credit_purchase, 2) }}</b></span><span>Vendors<br><b>{{ $category->vendor_count }}</b></span><span>Invoices<br><b>{{ $category->invoice_count }}</b></span></div></a>@empty<p class="p-6 text-center text-sm text-slate-400">No categories in this period.</p>@endforelse</div>
    </section>
@elseif($tab === 'finance')
    <section>
        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 class="font-black text-slate-950">Purchaser Finance</h2><p class="text-xs font-semibold text-slate-500">Current balance is cumulative; activity is for the selected period.</p></div>
            <div class="flex flex-wrap gap-2">
                <a href="#record-funding" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg bg-emerald-700 px-3 text-xs font-black text-white"><i data-lucide="plus-circle" class="h-4 w-4"></i> Give Funding</a>
                <a href="#payment-history" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-black text-slate-700"><i data-lucide="history" class="h-4 w-4"></i> Payment History</a>
                <a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-black text-slate-700"><i data-lucide="truck" class="h-4 w-4"></i> Vendor Credit Payments</a>
                <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-black text-slate-700"><i data-lucide="git-compare-arrows" class="h-4 w-4"></i> Reconcile</a>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach([
                ['Current Advance / Balance', $financeSummary['remaining_advance'], '#finance-transactions'],
                ['Total Funding', $financeSummary['cash_given'], '#finance-transactions'],
                ['Cash Used', $financeSummary['cash_used'], $purchaserTabUrl('finance', ['finance_payment' => 'cash']).'#payment-history'],
                ['Credit Purchases', $financeSummary['credit_purchases'], $purchaserTabUrl('finance', ['finance_payment' => 'credit']).'#payment-history'],
                ['Reconciled Funding', $finance['reconciliation']['reconciled_amount'], route('admin.cashbook.finance.reconciliation')],
                ['Pending Reconciliation', $finance['reconciliation']['pending_reconciliation'], route('admin.cashbook.finance.reconciliation')],
            ] as [$label, $value, $cardUrl])
                <a href="{{ $cardUrl }}" class="group rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-600"><span class="flex items-center justify-between gap-2 text-[10px] font-black uppercase text-slate-400"><span>{{ $label }}</span><i data-lucide="arrow-up-right" class="h-3.5 w-3.5 text-slate-300 group-hover:text-emerald-600"></i></span><strong class="mt-2 block font-mono text-lg text-slate-950">₹{{ number_format((float) $value, 2) }}</strong></a>
            @endforeach
        </div>
        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs font-semibold text-slate-600">Selected-period activity: Funding ₹{{ number_format($finance['activity']['cash_given'], 2) }} · Cash Used ₹{{ number_format($finance['activity']['cash_used'], 2) }} · Credit Purchases ₹{{ number_format($finance['activity']['credit_purchases'], 2) }}</div>
    </section>

    <section id="record-funding" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-4 border-b border-slate-200 pb-3">
            <h2 class="font-black text-slate-950">Give Funding</h2>
            <p class="mt-1 text-xs text-slate-500">Record company cash or bank funding for {{ $record->name }}. Starts in UNMATCHED state until reconciled against a statement row or manual counterpart.</p>
        </div>
        <form method="POST" action="{{ route('admin.cashbook.finance.purchasers.funding.store', $record->public_uuid) }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @csrf
            <div>
                <label for="funding-amount" class="mb-1 block text-xs font-bold text-slate-600">Amount</label>
                <input id="funding-amount" name="amount" type="number" step="0.01" min="0.01" required class="min-h-11 w-full rounded-lg border border-slate-300 px-3 font-mono text-sm font-bold text-slate-900">
            </div>
            <div>
                <label for="funding-date" class="mb-1 block text-xs font-bold text-slate-600">Date</label>
                <input id="funding-date" name="business_date" type="date" value="{{ today()->toDateString() }}" required class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-800">
            </div>
            <div>
                <label for="funding-source" class="mb-1 block text-xs font-bold text-slate-600">Source</label>
                <select id="funding-source" name="payment_source" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="Bank">Bank</option>
                    <option value="Cash">Cash</option>
                </select>
            </div>
            <div>
                <label for="funding-account" class="mb-1 block text-xs font-bold text-slate-600">Expected Company Account</label>
                <select id="funding-account" name="company_account_id" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="">Select account (Optional)</option>
                    @foreach($companyAccounts as $account)
                        <option value="{{ $account->id }}" @selected(App\Models\Cashbook\CompanyAccount::isSelected($account, old('company_account_id'), $companyAccounts))>{{ $account->name }} / {{ strtoupper($account->account_type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-1 xl:col-span-2">
                <label for="funding-reference" class="mb-1 block text-xs font-bold text-slate-600">Reference</label>
                <input id="funding-reference" name="reference" type="text" maxlength="160" placeholder="UTR or voucher" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-800">
            </div>
            <div class="md:col-span-1 xl:col-span-2">
                <label for="funding-description" class="mb-1 block text-xs font-bold text-slate-600">Note</label>
                <input id="funding-description" name="description" type="text" maxlength="255" placeholder="Funding note" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-800">
            </div>
            <div class="md:col-span-2 xl:col-span-4">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-600">
                    <i data-lucide="plus-circle" class="h-4 w-4"></i> Record Funding
                </button>
            </div>
        </form>
    </section>

    <section id="finance-transactions" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4">
            <h2 class="font-black text-slate-950">Finance Transactions</h2>
            <p class="mt-1 text-xs text-slate-500">Purchaser funding reconciliation trace and cash-movement transactions.</p>
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[56rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Type</th>
                        <th class="p-3 text-right">Amount</th>
                        <th class="p-3">Source / Account</th>
                        <th class="p-3">Reference</th>
                        <th class="p-3">Reconciliation Trace</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($finance['transactions'] as $movement)
                        <tr class="hover:bg-slate-50/75 transition-colors">
                            <td class="p-3 font-mono text-slate-600">{{ $movement->business_date }}</td>
                            <td class="p-3 font-bold text-slate-900">{{ $movement->movement_type }}</td>
                            <td class="p-3 text-right font-mono font-bold {{ $movement->type === 'in' ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $movement->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $movement->amount, 2) }}
                            </td>
                            <td class="p-3 text-slate-700">{{ $movement->company_account ?: ($movement->payment_source ?: '—') }}</td>
                            <td class="p-3 text-slate-600">{{ $movement->movement_reference ?: '—' }}</td>
                            <td class="p-3">
                                @if($movement->type === 'out')
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">advance utilized</span>
                                @elseif($movement->status === 'unmatched')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">UNMATCHED</span>
                                @elseif($movement->status === 'matched')
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">✓ MATCHED</span>
                                        <div class="text-[10px] text-slate-500 font-medium">{{ $movement->statement_account_name }} · {{ $movement->statement_date }} · Ref: {{ $movement->statement_reference ?: '—' }}</div>
                                    </div>
                                @elseif($movement->status === 'manual_cash')
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-300 px-2 py-0.5 text-[9px] font-black uppercase text-amber-900">✓ MANUAL CASH</span>
                                        <div class="text-[10px] text-slate-500 font-medium">{{ $movement->statement_account_name ?: 'Cash Account' }} · {{ $movement->statement_date }} · Ref: {{ $movement->statement_reference ?: '—' }}</div>
                                    </div>
                                @elseif($movement->status === 'manual_statement')
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[9px] font-black uppercase text-blue-900">✓ MANUAL STATEMENT</span>
                                        <div class="text-[10px] text-slate-500 font-medium">{{ $movement->statement_account_name }} · {{ $movement->statement_date }} · Ref: {{ $movement->statement_reference ?: '—' }}</div>
                                    </div>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">{{ str_replace('_', ' ', $movement->status) }}</span>
                                @endif
                            </td>
                            <td class="p-3 text-right">
                                @if($movement->type === 'in')
                                    <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                @if($movement->funding_action_blocked)
                                    <span class="text-[10px] text-slate-500">{{ $movement->funding_action_block_reason ?: 'Editing is protected for this funding.' }}</span>
                                @endif
                                @if(!$movement->funding_action_blocked && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                                        <button type="button" onclick="openEditFundingModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ $movement->payment_source }}', '{{ $movement->company_account_id }}', '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ addslashes($movement->funding_description ?? '') }}', '{{ $movement->status }}')" class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2.5 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition">
                                            <i data-lucide="pencil" class="h-3 w-3"></i> Edit
                                        </button>
                                @endif
                                @if(!$movement->funding_action_blocked && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                                        <button type="button" onclick="openDeleteFundingModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->company_account ?: ($movement->payment_source ?: '—')) }}', '{{ $movement->status }}')" class="inline-flex items-center gap-1 rounded border border-rose-200 bg-rose-50 px-2.5 py-1 text-[10px] font-bold text-rose-700 hover:bg-rose-100 shadow-sm transition">
                                            <i data-lucide="trash-2" class="h-3 w-3"></i> Delete
                                        </button>
                                @endif
                                        @if($movement->status === 'unmatched')
                                            <button type="button" onclick="openMatchStatementModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ addslashes($movement->company_account ?? '') }}')" class="inline-flex items-center gap-1 rounded bg-emerald-700 px-2.5 py-1 text-[10px] font-bold text-white hover:bg-emerald-600 shadow-sm transition">
                                                <i data-lucide="git-merge" class="h-3 w-3"></i> Match Statement
                                            </button>
                                            <button type="button" onclick="openManualEntryModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ $movement->company_account_id }}')" class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2.5 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition">
                                                <i data-lucide="plus-circle" class="h-3 w-3"></i> Add Cash/Statement
                                            </button>
                                        @elseif(in_array($movement->status, ['matched', 'manual_cash', 'manual_statement'], true))
                                            <button type="button" onclick="openViewMatchModal({{ $movement->id }}, '{{ $record->public_uuid }}')" class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2.5 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition">
                                                <i data-lucide="eye" class="h-3 w-3"></i> View Match
                                            </button>
                                            @if($movement->status === 'matched')
                                                <button type="button" onclick="openUnmatchModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->statement_account_name ?? '') }}')" class="inline-flex items-center gap-1 rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700 hover:bg-rose-100 shadow-sm transition">
                                                    <i data-lucide="unlink" class="h-3 w-3"></i> Unmatch
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-slate-400">No finance activity in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($finance['transactions'] as $movement)
                <article class="space-y-2 p-4 text-xs">
                    <div class="flex items-start justify-between gap-3">
                        <strong class="text-slate-900">{{ $movement->movement_type }}</strong>
                        <strong class="font-mono {{ $movement->type === 'in' ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $movement->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $movement->amount, 2) }}
                        </strong>
                    </div>
                    <div class="flex justify-between gap-3 text-slate-500">
                        <span>{{ $movement->business_date }}</span>
                        <span>{{ $movement->company_account ?: ($movement->payment_source ?: '—') }}</span>
                    </div>
                    <p class="text-slate-500 font-mono">{{ $movement->movement_reference ?: '—' }}</p>
                    <div class="pt-1 flex items-center justify-between gap-2 flex-wrap">
                        @if($movement->type === 'out')
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">advance utilized</span>
                        @else
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @if($movement->status === 'unmatched')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">UNMATCHED</span>
                                @elseif(in_array($movement->status, ['matched', 'manual_cash', 'manual_statement'], true))
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">{{ str_replace('_', ' ', $movement->status) }}</span>
                                @endif
                                @if($movement->funding_action_blocked)
                                    <span class="text-[10px] text-slate-500">{{ $movement->funding_action_block_reason ?: 'Editing is protected for this funding.' }}</span>
                                @endif
                                @if(!$movement->funding_action_blocked && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                                <button type="button" onclick="openEditFundingModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ $movement->payment_source }}', '{{ $movement->company_account_id }}', '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ addslashes($movement->funding_description ?? '') }}', '{{ $movement->status }}')" class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700">Edit</button>
                                @endif
                                @if(!$movement->funding_action_blocked && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                                <button type="button" onclick="openDeleteFundingModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->company_account ?: ($movement->payment_source ?: '—')) }}', '{{ $movement->status }}')" class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700">Delete</button>
                                @endif
                                @if($movement->status === 'unmatched')
                                    <button type="button" onclick="openMatchStatementModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ addslashes($movement->company_account ?? '') }}')" class="rounded bg-emerald-700 px-2 py-1 text-[10px] font-bold text-white">Match</button>
                                    <button type="button" onclick="openManualEntryModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ $movement->company_account_id }}')" class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700">Add Manual</button>
                                @elseif(in_array($movement->status, ['matched', 'manual_cash', 'manual_statement'], true))
                                    <button type="button" onclick="openViewMatchModal({{ $movement->id }}, '{{ $record->public_uuid }}')" class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700">View Match</button>
                                    @if($movement->status === 'matched')
                                        <button type="button" onclick="openUnmatchModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->statement_account_name ?? '') }}')" class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700">Unmatch</button>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="p-6 text-center text-sm text-slate-400">No finance activity in this period.</p>
            @endforelse
        </div>
        @if($finance['transactions']->hasPages())
            <div class="border-t border-slate-200 p-4">{{ $finance['transactions']->links() }}</div>
        @endif
    </section>

    <section id="payment-history" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div><h2 class="font-black text-slate-950">Cash and Credit History</h2><p class="mt-1 text-xs text-slate-500">Cash rows consume purchaser advance. Credit rows remain in Vendor Credit.</p></div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $finance['history']->total() }} rows</span>
            </div>
            <form method="GET" action="{{ route('admin.cashbook.finance.purchase.purchasers.show', $record->public_uuid).'#payment-history' }}" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-[10rem_10rem_minmax(12rem,1fr)_auto] lg:items-end">
                <input type="hidden" name="tab" value="finance">
                @if(!empty($filters['product_filter']))
                    <input type="hidden" name="product_filter" value="{{ $filters['product_filter'] }}">
                @endif
                <label class="text-[10px] font-black uppercase text-slate-500">Period
                    <select name="period" onchange="if(this.value !== 'custom') this.form.submit()" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        @foreach(['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['period'] === $value || ($value === 'custom' && in_array($filters['period'], ['between', 'range'], true)))>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-[10px] font-black uppercase text-slate-500">Payment Type
                    <select name="finance_payment" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="all" @selected($financePayment === 'all')>All</option>
                        <option value="cash" @selected($financePayment === 'cash')>Cash</option>
                        <option value="credit" @selected($financePayment === 'credit')>Credit</option>
                    </select>
                </label>
                <label class="text-[10px] font-black uppercase text-slate-500">Search
                    <input name="finance_search" type="search" value="{{ $financeSearch }}" maxlength="100" placeholder="Supplier, invoice, reference..." class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                </label>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-600"><i data-lucide="filter" class="h-4 w-4"></i> Apply</button>
                @if(in_array($filters['period'], ['custom', 'between', 'range'], true))
                    <label class="text-[10px] font-black uppercase text-slate-500">From<input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold"></label>
                    <label class="text-[10px] font-black uppercase text-slate-500">To<input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold"></label>
                @endif
            </form>
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[58rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-3">Date</th><th class="p-3">Supplier</th><th class="p-3">Invoice / Bill</th><th class="p-3">Payment Type</th><th class="p-3 text-right">Amount</th><th class="p-3">Funding / Utilization Reference</th><th class="p-3">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($finance['history'] as $split)
                        <tr><td class="p-3 font-mono">{{ $split->row_date }}</td><td class="p-3 font-bold">@if($split->payment_type === 'Credit' && $split->supplier_public_uuid)<a href="{{ route('admin.cashbook.finance.vendor-credit.show', $split->supplier_public_uuid) }}" class="text-emerald-700 hover:underline">{{ $split->supplier_name }}</a>@else{{ $split->supplier_name }}@endif</td><td class="p-3 font-mono font-bold">{{ $split->invoice_number }}</td><td class="p-3"><span class="rounded-full px-2 py-1 text-[9px] font-black uppercase {{ $split->payment_type === 'Cash' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800' }}">{{ $split->payment_type }}</span></td><td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $split->amount, 2) }}</td><td class="p-3">{{ $split->movement_reference ?: '—' }}</td><td class="p-3 uppercase">{{ str_replace('_', ' ', (string) $split->status) }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center text-slate-400">No purchaser payment history in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($finance['history'] as $split)
                <article class="space-y-2 p-4 text-xs">
                    <div class="flex items-start justify-between gap-3"><strong class="break-words">{{ $split->invoice_number }}</strong><strong class="shrink-0 font-mono">₹{{ number_format((float) $split->amount, 2) }}</strong></div>
                    <div class="flex justify-between gap-3 text-slate-500"><span>{{ $split->row_date }}</span><span class="uppercase">{{ $split->payment_type }}</span></div>
                    <p class="font-semibold text-slate-700">@if($split->payment_type === 'Credit' && $split->supplier_public_uuid)<a href="{{ route('admin.cashbook.finance.vendor-credit.show', $split->supplier_public_uuid) }}" class="text-emerald-700 hover:underline">{{ $split->supplier_name }} · Pay Vendor</a>@else{{ $split->supplier_name }}@endif</p>
                    <p class="break-words text-slate-500">{{ $split->movement_reference ?: '—' }}</p>
                    <p class="text-[10px] font-black uppercase text-slate-500">{{ str_replace('_', ' ', (string) $split->status) }}</p>
                </article>
            @empty
                <p class="p-6 text-center text-sm text-slate-400">No purchaser payment history in this period.</p>
            @endforelse
        </div>
        @if($finance['history']->hasPages())<div class="border-t border-slate-200 p-4">{{ $finance['history']->links() }}</div>@endif
    </section>

    {{-- ── Match Statement Modal ────────────────────────────────────────── --}}
    <div id="matchStatementModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="git-merge" class="h-5 w-5 text-emerald-700"></i>
                    <h3 class="font-black text-slate-900">Match With Statement</h3>
                </div>
                <button type="button" onclick="closeMatchStatementModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            
            <form id="matchStatementForm" method="POST" action="" class="flex flex-col flex-1 overflow-hidden">
                @csrf
                <input type="hidden" id="matchStatementIdInput" name="statement_entry_id" value="">
                
                <div class="p-5 space-y-4 overflow-y-auto flex-1 text-xs">
                    <!-- Funding Summary Card -->
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-500 uppercase text-[10px] tracking-wider">Purchaser Funding</span>
                            <span id="matchFundingAmount" class="font-mono font-black text-emerald-800 text-sm">₹0.00</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-slate-600">
                            <div>Purchaser: <strong class="text-slate-900" id="matchFundingPurchaser">{{ $record->name }}</strong></div>
                            <div class="text-right">Date: <strong class="text-slate-900" id="matchFundingDate">2026-08-27</strong></div>
                            <div>Account: <strong class="text-slate-900" id="matchFundingAccount">—</strong></div>
                            <div class="text-right">Ref: <strong class="font-mono text-slate-900" id="matchFundingRef">—</strong></div>
                        </div>
                    </div>

                    <!-- Tabs: Pending vs Already Reconciled -->
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <div class="flex gap-2">
                            <button type="button" id="tabPendingBtn" onclick="switchMatchTab('pending')" class="rounded-lg px-3 py-1.5 font-black text-xs bg-emerald-700 text-white shadow-sm transition">
                                Pending (<span id="pendingCountBadge">0</span>)
                            </button>
                            <button type="button" id="tabReconciledBtn" onclick="switchMatchTab('reconciled')" class="rounded-lg px-3 py-1.5 font-bold text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                                Already Reconciled (<span id="reconciledCountBadge">0</span>)
                            </button>
                        </div>
                        <!-- Local search filter -->
                        <div class="relative w-44">
                            <input type="text" id="matchLocalSearch" oninput="filterCandidateList()" placeholder="Filter ref/narration..." class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[11px] font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="matchCandidatesLoading" class="py-12 text-center text-xs text-slate-400">
                        <i data-lucide="loader-2" class="h-6 w-6 animate-spin mx-auto mb-2 text-emerald-600"></i>
                        Loading candidate statement entries...
                    </div>

                    <!-- Error State -->
                    <div id="matchCandidatesError" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-4 text-center text-xs text-rose-800 font-medium">
                        Failed to load candidates. Please try again.
                    </div>

                    <!-- Pending Section -->
                    <div id="pendingCandidatesSection" class="hidden space-y-2">
                        <div id="pendingCandidatesList" class="space-y-2 max-h-72 overflow-y-auto pr-1"></div>
                        <div id="pendingCandidatesEmpty" class="hidden rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-400">
                            No unmatched OUT statement entries of exact amount found.
                        </div>
                    </div>

                    <!-- Reconciled Section -->
                    <div id="reconciledCandidatesSection" class="hidden space-y-2">
                        <div id="reconciledCandidatesList" class="space-y-2 max-h-72 overflow-y-auto pr-1"></div>
                        <div id="reconciledCandidatesEmpty" class="hidden rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-400">
                            No reconciled OUT statement entries of exact amount found.
                        </div>
                    </div>
                </div>

                <!-- Footer & Action Confirmation Area -->
                <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-5 py-3.5 shrink-0">
                    <div id="selectedStatementSummary" class="text-xs text-slate-500 font-medium truncate max-w-sm">
                        Select a candidate above to proceed
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="closeMatchStatementModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button id="confirmMatchBtn" type="button" onclick="submitMatchForm()" disabled class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-black text-white hover:bg-emerald-600 disabled:opacity-40 disabled:cursor-not-allowed">Confirm Match</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Edit Funding Modal ────────────────────────────────────────── --}}
    <div id="editFundingModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="pencil" class="h-5 w-5 text-emerald-700"></i>
                    <h3 class="font-black text-slate-900">Edit Purchaser Funding</h3>
                </div>
                <button type="button" onclick="closeEditFundingModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            
            <form id="editFundingForm" method="POST" action="" class="flex flex-col flex-1 overflow-hidden" onsubmit="return confirmEditFundingSave()">
                @csrf
                <div class="p-5 space-y-3.5 overflow-y-auto flex-1 text-xs">
                    <div id="editFundingMatchedWarning" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                        <i data-lucide="alert-triangle" class="inline h-4 w-4 mr-1 text-amber-700"></i>
                        <strong>Note:</strong> This funding is currently matched to a statement. Changing the amount will unlink the statement and return it to Pending Reconciliation.
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="editFundingAmount" class="mb-1 block font-bold text-slate-600">Amount (₹)</label>
                            <input id="editFundingAmount" name="amount" type="number" step="0.01" min="0.01" required class="min-h-11 w-full rounded-lg border border-slate-300 px-3 font-mono text-sm font-bold text-slate-900">
                        </div>
                        <div>
                            <label for="editFundingDate" class="mb-1 block font-bold text-slate-600">Date</label>
                            <input id="editFundingDate" name="business_date" type="date" required class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-800">
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="editFundingSource" class="mb-1 block font-bold text-slate-600">Source</label>
                            <select id="editFundingSource" name="payment_source" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                                <option value="Bank">Bank</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                        <div>
                            <label for="editFundingAccount" class="mb-1 block font-bold text-slate-600">Company Account</label>
                            <select id="editFundingAccount" name="company_account_id" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                                <option value="">Select account (Optional)</option>
                                @foreach($companyAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }} / {{ strtoupper($account->account_type) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="editFundingReference" class="mb-1 block font-bold text-slate-600">Reference</label>
                        <input id="editFundingReference" name="reference" type="text" maxlength="160" placeholder="UTR or voucher" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                    </div>

                    <div>
                        <label for="editFundingDescription" class="mb-1 block font-bold text-slate-600">Note / Description</label>
                        <input id="editFundingDescription" name="description" type="text" maxlength="255" placeholder="Funding note" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3.5 shrink-0">
                    <button type="button" onclick="closeEditFundingModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-black text-white hover:bg-emerald-600 shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Delete Purchaser Funding Modal ─────────────────────────────── --}}
    <div id="deleteFundingModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-rose-100 bg-rose-50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="h-5 w-5 text-rose-700"></i>
                    <h3 id="deleteFundingTitle" class="font-black text-rose-950">Delete purchaser funding?</h3>
                </div>
                <button type="button" onclick="closeDeleteFundingModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            
            <form id="deleteFundingForm" method="POST" action="" class="p-5 space-y-4 text-xs" onsubmit="return confirmDeleteFundingSubmit()">
                @csrf
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-1.5 text-slate-700">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Purchaser:</span>
                        <span class="font-bold text-slate-900">{{ $record->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Amount:</span>
                        <span id="deleteFundingAmount" class="font-mono font-black text-rose-700 text-sm">₹0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Date:</span>
                        <span id="deleteFundingDate" class="font-mono font-bold text-slate-800">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Account:</span>
                        <span id="deleteFundingAccount" class="font-semibold text-slate-800">—</span>
                    </div>
                </div>

                <div id="deleteFundingMatchedWarning" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900">
                    <i data-lucide="alert-circle" class="inline h-4 w-4 mr-1 text-rose-700"></i>
                    <strong>Safe delete only:</strong> This removes the funding and its corresponding safe journal movement. Reconciled, used, or historical funding is never deleted.
                </div>

                <div>
                    <label for="deleteFundingReason" class="mb-1 block font-bold text-slate-700">Reason for Deletion <span class="text-rose-600">*</span></label>
                    <select id="deleteFundingReason" name="reason" required class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="Duplicate Entry">Duplicate Entry (accidental double entry)</option>
                        <option value="Wrong Entry">Wrong Entry / Incorrect purchaser</option>
                        <option value="Other">Other Reason</option>
                    </select>
                </div>

                <div>
                    <label for="deleteFundingNotes" class="mb-1 block font-bold text-slate-700">Audit Notes (Optional)</label>
                    <textarea id="deleteFundingNotes" name="notes" rows="2" placeholder="Provide extra context for audit trail" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                    <button type="button" onclick="closeDeleteFundingModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button id="btnConfirmDeleteFunding" type="submit" class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-black text-white hover:bg-rose-600 shadow-sm">Delete Funding</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Add Cash / Manual Statement Modal ─────────────────────────────── --}}
    <div id="manualEntryModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="plus-circle" class="h-5 w-5 text-emerald-700"></i>
                    <h3 class="font-black text-slate-900">Add Cash / Statement Row</h3>
                </div>
                <button type="button" onclick="closeManualEntryModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <form id="manualEntryForm" method="POST" action="" class="p-5 space-y-3.5 text-xs">
                @csrf
                <div>
                    <label class="mb-1 block font-bold text-slate-600">Company Account</label>
                    <select id="manualCompanyAccount" name="company_account_id" required class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        @foreach($companyAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }} / {{ strtoupper($account->account_type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block font-bold text-slate-600">Amount (₹)</label>
                        <input id="manualAmount" name="amount" type="number" step="0.01" min="0.01" required readonly class="min-h-11 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 font-mono text-xs font-bold text-slate-800 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="mb-1 block font-bold text-slate-600">Date</label>
                        <input id="manualDate" name="transaction_date" type="date" required class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-800">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block font-bold text-slate-600">Reference / Voucher</label>
                    <input id="manualRef" name="reference" type="text" maxlength="160" placeholder="Voucher or counter ref" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                </div>
                <div>
                    <label class="mb-1 block font-bold text-slate-600">Narration / Note</label>
                    <input id="manualNarration" name="narration" type="text" maxlength="255" placeholder="Cash disbursement note" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200">
                    <button type="button" onclick="closeManualEntryModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-black text-white hover:bg-emerald-600 shadow-sm">Add &amp; Match</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── View Match Modal ─────────────────────────────────────────────── --}}
    <div id="viewMatchModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="eye" class="h-5 w-5 text-emerald-700"></i>
                    <h3 class="font-black text-slate-900">Reconciliation Trace</h3>
                </div>
                <button type="button" onclick="closeViewMatchModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="p-5 text-xs">
                <div id="viewTraceLoading" class="py-12 text-center text-xs text-slate-400">
                    <i data-lucide="loader-2" class="h-6 w-6 animate-spin mx-auto mb-2 text-emerald-600"></i>
                    Loading reconciliation trace...
                </div>
                <div id="viewTraceContent" class="hidden space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-2">
                        <span class="font-bold text-slate-500 uppercase text-[10px] tracking-wider">Funding Details</span>
                        <div class="grid grid-cols-2 gap-2 text-slate-600">
                            <div>Amount: <strong id="traceFundingAmount" class="font-mono text-slate-900 font-bold">—</strong></div>
                            <div>Date: <strong id="traceFundingDate" class="text-slate-900">—</strong></div>
                            <div class="col-span-2">Ref: <strong id="traceFundingRef" class="font-mono text-slate-900">—</strong></div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3.5 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-emerald-800 uppercase text-[10px] tracking-wider">Matched Statement Movement</span>
                            <span id="traceSourceBadge" class="rounded-full bg-emerald-100 border border-emerald-300 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">—</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-slate-600">
                            <div>Account: <strong id="traceAccountName" class="text-slate-900 font-bold">—</strong></div>
                            <div>Statement Date: <strong id="traceStmtDate" class="text-slate-900">—</strong></div>
                            <div>Amount: <strong id="traceStmtAmount" class="font-mono text-emerald-800 font-black">—</strong></div>
                            <div>Statement Ref: <strong id="traceStmtRef" class="font-mono text-slate-900">—</strong></div>
                            <div class="col-span-2">Narration: <span id="traceStmtNarration" class="text-slate-700 font-medium">—</span></div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-[11px] text-slate-600 flex justify-between">
                        <span>Matched By: <strong id="traceAuditActor" class="text-slate-800">—</strong></span>
                        <span>Matched At: <strong id="traceAuditTime" class="text-slate-800">—</strong></span>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end border-t border-slate-200 bg-slate-50 px-5 py-3.5">
                <button type="button" onclick="closeViewMatchModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Close</button>
            </div>
        </div>
    </div>

    {{-- ── Unmatch Confirmation Modal ────────────────────────────────────── --}}
    <div id="unmatchModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="unlink" class="h-5 w-5 text-rose-700"></i>
                    <h3 class="font-black text-slate-900">Unmatch Statement</h3>
                </div>
                <button type="button" onclick="closeUnmatchModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <form id="unmatchForm" method="POST" action="" class="p-5 space-y-3.5 text-xs">
                @csrf
                <p class="text-slate-600 leading-relaxed">
                    Are you sure you want to unmatch this purchaser funding transaction?
                </p>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-700 space-y-1">
                    <div>Funding Amount: <strong id="unmatchAmount" class="font-mono text-slate-900 font-bold">₹0.00</strong></div>
                    <div>Business Date: <strong id="unmatchDate" class="text-slate-900">—</strong></div>
                    <div>Matched Account: <strong id="unmatchAccount" class="text-slate-900">—</strong></div>
                </div>
                <p class="text-[11px] text-slate-500">
                    The statement movement will safely return to <strong class="text-slate-700">UNMATCHED</strong> in the cashbook reconciliation queue.
                </p>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200">
                    <button type="button" onclick="closeUnmatchModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-black text-white hover:bg-rose-600 shadow-sm">Confirm Unmatch</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditFundingModal(creditId, purchaserUuid, date, amount, source, accountId, ref, desc, status) {
            const modal = document.getElementById('editFundingModal');
            const amountInput = document.getElementById('editFundingAmount');
            const dateInput = document.getElementById('editFundingDate');
            const sourceSelect = document.getElementById('editFundingSource');
            const accountSelect = document.getElementById('editFundingAccount');

            amountInput.value = Number(amount).toFixed(2);
            dateInput.value = date;
            sourceSelect.value = source || 'Bank';
            accountSelect.value = accountId || '';
            document.getElementById('editFundingReference').value = ref || '';
            document.getElementById('editFundingDescription').value = desc || '';

            const warningEl = document.getElementById('editFundingMatchedWarning');
            warningEl.classList.add('hidden');

            const form = document.getElementById('editFundingForm');
            form.action = `/admin/cashbook/finance/purchasers/${purchaserUuid}/funding/${creditId}/update`;
            form.dataset.confirmAmount = Number(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            if (window.lucide) lucide.createIcons();
        }

        function closeEditFundingModal() {
            const modal = document.getElementById('editFundingModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function confirmEditFundingSave() {
            const form = document.getElementById('editFundingForm');
            return confirm(`Save changes to ₹${form.dataset.confirmAmount || '0.00'} purchaser funding?`);
        }

        function openDeleteFundingModal(creditId, purchaserUuid, date, amount, account, status) {
            const modal = document.getElementById('deleteFundingModal');
            const formattedAmount = Number(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('deleteFundingTitle').textContent = `Delete ₹${formattedAmount} purchaser funding?`;
            document.getElementById('deleteFundingAmount').textContent = '₹' + formattedAmount;
            document.getElementById('deleteFundingDate').textContent = date;
            document.getElementById('deleteFundingAccount').textContent = account || '—';

            const warningEl = document.getElementById('deleteFundingMatchedWarning');
            const confirmBtn = document.getElementById('btnConfirmDeleteFunding');
            const isReconciled = ['matched', 'manual_cash', 'manual_statement'].includes(status);

            if (isReconciled) {
                warningEl.classList.remove('hidden');
                confirmBtn.disabled = true;
                confirmBtn.classList.add('opacity-40', 'cursor-not-allowed');
            } else {
                warningEl.classList.add('hidden');
                confirmBtn.disabled = false;
                confirmBtn.classList.remove('opacity-40', 'cursor-not-allowed');
            }

            const form = document.getElementById('deleteFundingForm');
            form.action = `/admin/cashbook/finance/purchasers/${purchaserUuid}/funding/${creditId}/delete`;
            form.dataset.confirmAmount = formattedAmount;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            if (window.lucide) lucide.createIcons();
        }

        function closeDeleteFundingModal() {
            const modal = document.getElementById('deleteFundingModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function confirmDeleteFundingSubmit() {
            const form = document.getElementById('deleteFundingForm');
            return confirm(
                `Delete ₹${form.dataset.confirmAmount || '0.00'} purchaser funding?\n\n` +
                'This removes the funding and its corresponding safe journal movement. Reconciled, used, or historical funding will not be deleted.'
            );
        }
        let currentCreditId = null;
        let currentPurchaserUuid = null;
        let currentActiveTab = 'pending';
        let currentSelectionType = 'pending';
        let cachedData = null;
        let selectedCandidate = null;

        function openMatchStatementModal(creditId, purchaserUuid, date, amount, ref, account) {
            currentCreditId = creditId;
            currentPurchaserUuid = purchaserUuid;
            currentActiveTab = 'pending';
            currentSelectionType = 'pending';
            cachedData = null;
            selectedCandidate = null;

            const modal = document.getElementById('matchStatementModal');
            document.getElementById('matchFundingAmount').textContent = '₹' + Number(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('matchFundingDate').textContent = date;
            document.getElementById('matchFundingRef').textContent = ref || '—';
            document.getElementById('matchFundingAccount').textContent = account || '—';
            document.getElementById('matchStatementIdInput').value = '';
            document.getElementById('matchLocalSearch').value = '';
            document.getElementById('selectedStatementSummary').textContent = 'Select a candidate above to proceed';
            
            const confirmBtn = document.getElementById('confirmMatchBtn');
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Confirm Match';
            confirmBtn.className = 'rounded-lg bg-emerald-700 px-4 py-2 text-xs font-black text-white hover:bg-emerald-600 disabled:opacity-40 disabled:cursor-not-allowed';

            document.getElementById('matchCandidatesLoading').classList.remove('hidden');
            document.getElementById('matchCandidatesError').classList.add('hidden');
            document.getElementById('pendingCandidatesSection').classList.add('hidden');
            document.getElementById('reconciledCandidatesSection').classList.add('hidden');

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            fetch(`/admin/cashbook/finance/purchasers/${purchaserUuid}/funding/${creditId}/candidates`)
                .then(res => {
                    if (!res.ok) throw new Error('Network error');
                    return res.json();
                })
                .then(data => {
                    cachedData = data;
                    document.getElementById('matchCandidatesLoading').classList.add('hidden');
                    document.getElementById('pendingCountBadge').textContent = data.counts?.pending ?? 0;
                    document.getElementById('reconciledCountBadge').textContent = data.counts?.reconciled ?? 0;
                    
                    if (data.funding) {
                        document.getElementById('matchFundingAmount').textContent = data.funding.formatted_amount;
                        document.getElementById('matchFundingDate').textContent = data.funding.business_date;
                        document.getElementById('matchFundingAccount').textContent = data.funding.account_name;
                        document.getElementById('matchFundingRef').textContent = data.funding.reference;
                    }

                    renderCandidates();
                    switchMatchTab(data.counts?.pending > 0 ? 'pending' : (data.counts?.reconciled > 0 ? 'reconciled' : 'pending'));
                    if (window.lucide) lucide.createIcons();
                })
                .catch(err => {
                    document.getElementById('matchCandidatesLoading').classList.add('hidden');
                    document.getElementById('matchCandidatesError').classList.remove('hidden');
                });
        }

        function switchMatchTab(tab) {
            currentActiveTab = tab;
            const pendingBtn = document.getElementById('tabPendingBtn');
            const reconciledBtn = document.getElementById('tabReconciledBtn');
            const pendingSection = document.getElementById('pendingCandidatesSection');
            const reconciledSection = document.getElementById('reconciledCandidatesSection');

            if (tab === 'pending') {
                pendingBtn.className = 'rounded-lg px-3 py-1.5 font-black text-xs bg-emerald-700 text-white shadow-sm transition';
                reconciledBtn.className = 'rounded-lg px-3 py-1.5 font-bold text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition';
                pendingSection.classList.remove('hidden');
                reconciledSection.classList.add('hidden');
            } else {
                reconciledBtn.className = 'rounded-lg px-3 py-1.5 font-black text-xs bg-rose-700 text-white shadow-sm transition';
                pendingBtn.className = 'rounded-lg px-3 py-1.5 font-bold text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition';
                reconciledSection.classList.remove('hidden');
                pendingSection.classList.add('hidden');
            }
        }

        function renderCandidates() {
            if (!cachedData) return;
            const query = document.getElementById('matchLocalSearch').value.toLowerCase().trim();

            const renderCard = (item, type) => {
                const isSelected = selectedCandidate?.id === item.id;
                const isReconciled = type === 'reconciled';
                const isExact = item.date_match === 'exact';

                const borderClass = isSelected
                    ? (isReconciled ? 'border-rose-600 bg-rose-50/50' : 'border-emerald-600 bg-emerald-50/50')
                    : (isReconciled ? 'border-slate-200 bg-white hover:border-rose-500 hover:bg-slate-50' : 'border-slate-200 bg-white hover:border-emerald-500 hover:bg-slate-50');

                const radioClass = isReconciled ? 'text-rose-700 focus:ring-rose-500' : 'text-emerald-700 focus:ring-emerald-500';
                const amountClass = isReconciled ? 'text-rose-700' : 'text-emerald-700';

                const dateBadgeClass = isExact
                    ? 'rounded-full bg-emerald-100 border border-emerald-300 px-2 py-0.5 text-[9px] font-black text-emerald-800 uppercase tracking-wide'
                    : 'rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[9px] font-bold text-slate-600 uppercase tracking-wide';

                const actionBtn = isReconciled
                    ? `<button type="button" onclick="selectCandidate(${item.id}, 'reconciled')" class="rounded-lg bg-rose-600 px-2.5 py-1 text-[11px] font-black text-white hover:bg-rose-700">Replace Match</button>`
                    : `<button type="button" onclick="selectCandidate(${item.id}, 'pending')" class="rounded-lg bg-emerald-100 px-2.5 py-1 text-[11px] font-black text-emerald-800 hover:bg-emerald-200">Select</button>`;

                return `
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border ${borderClass} cursor-pointer transition shadow-xs">
                        <input type="radio" name="candidate_radio" value="${item.id}" ${isSelected ? 'checked' : ''} onchange="selectCandidate(${item.id}, '${type}')" class="mt-1 ${radioClass}">
                        <div class="flex-1 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <strong class="text-slate-900 font-bold">${item.account_name} <span class="text-[10px] text-slate-500 uppercase font-normal">(${item.account_type})</span></strong>
                                <span class="font-mono font-black ${amountClass} text-sm">${item.formatted_amount}</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-500 text-[11px]">
                                <div class="flex items-center gap-2">
                                    <span>Date: <strong class="text-slate-800">${item.transaction_date}</strong></span>
                                    <span class="${dateBadgeClass}">${item.date_badge_text}</span>
                                </div>
                                <span>Ref: <strong class="font-mono text-slate-800">${item.reference}</strong></span>
                            </div>
                            ${item.narration && item.narration !== '—' ? `<p class="text-[11px] text-slate-600 truncate">${item.narration}</p>` : ''}
                            
                            ${isReconciled ? `
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-2 text-[11px] text-amber-950 space-y-0.5">
                                    <div>Currently Matched To: <strong>${item.matched_to}</strong></div>
                                    <div class="text-[10px] text-amber-800">Matched On: ${item.matched_date} by ${item.matched_by}</div>
                                </div>
                            ` : ''}

                            <div class="pt-1 flex items-center justify-between">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black text-slate-600">${item.status}</span>
                                ${actionBtn}
                            </div>
                        </div>
                    </label>
                `;
            };

            const renderGroupedList = (items, type, listEl, emptyEl) => {
                const filtered = (items || []).filter(item => {
                    if (!query) return true;
                    return (item.reference && item.reference.toLowerCase().includes(query)) ||
                           (item.narration && item.narration.toLowerCase().includes(query)) ||
                           (item.account_name && item.account_name.toLowerCase().includes(query)) ||
                           (item.matched_to && item.matched_to.toLowerCase().includes(query));
                });

                if (filtered.length === 0) {
                    listEl.innerHTML = '';
                    emptyEl.classList.remove('hidden');
                    return;
                }

                emptyEl.classList.add('hidden');

                const exactItems = filtered.filter(item => item.date_match === 'exact');
                const otherItems = filtered.filter(item => item.date_match !== 'exact');

                if (exactItems.length > 0 && otherItems.length > 0) {
                    listEl.innerHTML = `
                        <div class="space-y-2">
                            <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-emerald-800 px-1 pt-1">
                                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                                BEST MATCH (EXACT DATE)
                            </div>
                            <div class="space-y-2">
                                ${exactItems.map(item => renderCard(item, type)).join('')}
                            </div>
                        </div>
                        <div class="space-y-2 pt-2 border-t border-slate-100">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-1">
                                OTHER SAME-AMOUNT MATCHES
                            </div>
                            <div class="space-y-2">
                                ${otherItems.map(item => renderCard(item, type)).join('')}
                            </div>
                        </div>
                    `;
                } else if (exactItems.length > 0) {
                    listEl.innerHTML = `
                        <div class="space-y-2">
                            <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-emerald-800 px-1 pt-1">
                                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                                BEST MATCH (EXACT DATE)
                            </div>
                            <div class="space-y-2">
                                ${exactItems.map(item => renderCard(item, type)).join('')}
                            </div>
                        </div>
                    `;
                } else {
                    listEl.innerHTML = `
                        <div class="space-y-2">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-1">
                                SAME-AMOUNT MATCHES
                            </div>
                            <div class="space-y-2">
                                ${otherItems.map(item => renderCard(item, type)).join('')}
                            </div>
                        </div>
                    `;
                }
            };

            renderGroupedList(cachedData.pending, 'pending', document.getElementById('pendingCandidatesList'), document.getElementById('pendingCandidatesEmpty'));
            renderGroupedList(cachedData.reconciled, 'reconciled', document.getElementById('reconciledCandidatesList'), document.getElementById('reconciledCandidatesEmpty'));
            if (window.lucide) lucide.createIcons();
        }

        function filterCandidateList() {
            renderCandidates();
        }

        function selectCandidate(id, type) {
            currentSelectionType = type;
            const pool = type === 'pending' ? (cachedData?.pending || []) : (cachedData?.reconciled || []);
            selectedCandidate = pool.find(item => item.id === id);
            if (!selectedCandidate) return;

            document.getElementById('matchStatementIdInput').value = id;
            const confirmBtn = document.getElementById('confirmMatchBtn');
            const summaryEl = document.getElementById('selectedStatementSummary');
            confirmBtn.disabled = false;

            if (type === 'pending') {
                confirmBtn.textContent = 'Confirm Match';
                confirmBtn.className = 'rounded-lg bg-emerald-700 px-4 py-2 text-xs font-black text-white hover:bg-emerald-600 shadow-sm';
                summaryEl.innerHTML = `Selected: <strong>${selectedCandidate.account_name}</strong> · ${selectedCandidate.transaction_date} · <span class="font-mono font-bold">${selectedCandidate.formatted_amount}</span>`;
            } else {
                confirmBtn.textContent = 'Replace Match';
                confirmBtn.className = 'rounded-lg bg-rose-700 px-4 py-2 text-xs font-black text-white hover:bg-rose-600 shadow-sm';
                summaryEl.innerHTML = `<span class="text-rose-700 font-bold">Replace Match:</span> Unlinks ${selectedCandidate.matched_to} (returns to Unmatched)`;
            }

            renderCandidates();
        }

        function submitMatchForm() {
            if (!selectedCandidate || !currentCreditId || !currentPurchaserUuid) return;

            const form = document.getElementById('matchStatementForm');

            if (currentSelectionType === 'reconciled') {
                const confirmed = confirm(
                    `Replace Existing Match?\n\n` +
                    `Statement: ${selectedCandidate.account_name} (${selectedCandidate.formatted_amount})\n` +
                    `Current Match: ${selectedCandidate.matched_to}\n` +
                    `New Match: Purchaser Funding #${currentCreditId}\n\n` +
                    `The previously matched transaction will safely return to UNMATCHED in reconciliation.\n\nProceed?`
                );
                if (!confirmed) return;

                form.action = `/admin/cashbook/finance/purchasers/${currentPurchaserUuid}/funding/${currentCreditId}/replace-match`;
            } else {
                form.action = `/admin/cashbook/finance/purchasers/${currentPurchaserUuid}/funding/${currentCreditId}/match-statement`;
            }

            form.submit();
        }

        function closeMatchStatementModal() {
            const modal = document.getElementById('matchStatementModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openManualEntryModal(creditId, purchaserUuid, date, amount, ref, accountId) {
            const modal = document.getElementById('manualEntryModal');
            document.getElementById('manualAmount').value = Number(amount).toFixed(2);
            document.getElementById('manualDate').value = date;
            document.getElementById('manualRef').value = ref || '';
            if (accountId) {
                document.getElementById('manualCompanyAccount').value = accountId;
            } else {
                const defaultAccountId = '{{ App\Models\Cashbook\CompanyAccount::resolveSelectedId(null, $companyAccounts) ?? '' }}';
                if (defaultAccountId) {
                    document.getElementById('manualCompanyAccount').value = defaultAccountId;
                }
            }
            
            const form = document.getElementById('manualEntryForm');
            form.action = `/admin/cashbook/finance/purchase/purchasers/${purchaserUuid}/funding/${creditId}/match-manual`;
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeManualEntryModal() {
            const modal = document.getElementById('manualEntryModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openViewMatchModal(creditId, purchaserUuid) {
            const modal = document.getElementById('viewMatchModal');
            const loadingEl = document.getElementById('viewTraceLoading');
            const contentEl = document.getElementById('viewTraceContent');
            
            loadingEl.classList.remove('hidden');
            contentEl.classList.add('hidden');
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            fetch(`/admin/cashbook/finance/purchase/purchasers/${purchaserUuid}/funding/${creditId}/trace`)
                .then(res => res.json())
                .then(data => {
                    loadingEl.classList.add('hidden');
                    if (!data.reconciled) return;
                    
                    document.getElementById('traceFundingAmount').textContent = '₹' + Number(data.funding.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('traceFundingDate').textContent = data.funding.business_date;
                    document.getElementById('traceFundingRef').textContent = data.funding.reference || '—';
                    
                    document.getElementById('traceSourceBadge').textContent = data.statement.source_classification;
                    document.getElementById('traceStmtAmount').textContent = '₹' + Number(data.statement.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('traceAccountName').textContent = data.matched_account.name + ' (' + data.matched_account.account_type.toUpperCase() + ')';
                    document.getElementById('traceStmtDate').textContent = data.statement.transaction_date;
                    document.getElementById('traceStmtRef').textContent = data.statement.reference;
                    document.getElementById('traceStmtNarration').textContent = data.statement.narration || data.statement.notes || '—';
                    
                    document.getElementById('traceAuditActor').textContent = data.audit.matched_by;
                    document.getElementById('traceAuditTime').textContent = data.audit.matched_at;
                    
                    contentEl.classList.remove('hidden');
                    if (window.lucide) lucide.createIcons();
                });
        }

        function closeViewMatchModal() {
            const modal = document.getElementById('viewMatchModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openUnmatchModal(creditId, purchaserUuid, date, amount, account) {
            const modal = document.getElementById('unmatchModal');
            document.getElementById('unmatchAmount').textContent = '₹' + Number(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('unmatchDate').textContent = date;
            document.getElementById('unmatchAccount').textContent = account || 'Company Account';
            
            const form = document.getElementById('unmatchForm');
            form.action = `/admin/cashbook/finance/purchase/purchasers/${purchaserUuid}/funding/${creditId}/unmatch`;
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeUnmatchModal() {
            const modal = document.getElementById('unmatchModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
@endif
