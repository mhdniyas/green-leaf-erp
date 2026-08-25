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
            <p class="mt-1 text-xs text-slate-500">Record company cash or bank funding for {{ $record->name }}.</p>
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
                <label for="funding-account" class="mb-1 block text-xs font-bold text-slate-600">Company Account</label>
                <select id="funding-account" name="company_account_id" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="">No statement row</option>
                    @foreach($companyAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }} / {{ strtoupper($account->account_type) }}</option>
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
        <div class="border-b border-slate-200 p-4"><h2 class="font-black text-slate-950">Finance Transactions</h2><p class="mt-1 text-xs text-slate-500">Existing purchaser funding and advance-utilization movements.</p></div>
        <div class="hidden overflow-x-auto md:block"><table class="w-full min-w-[48rem] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-3">Date</th><th class="p-3">Type</th><th class="p-3 text-right">Amount</th><th class="p-3">Company Account</th><th class="p-3">Reference</th><th class="p-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($finance['transactions'] as $movement)<tr><td class="p-3 font-mono">{{ $movement->business_date }}</td><td class="p-3 font-bold">{{ $movement->movement_type }}</td><td class="p-3 text-right font-mono font-bold {{ $movement->type === 'in' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $movement->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $movement->amount, 2) }}</td><td class="p-3">{{ $movement->company_account ?: ($movement->payment_source ?: '—') }}</td><td class="p-3">{{ $movement->movement_reference ?: '—' }}</td><td class="p-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-black uppercase text-slate-700">{{ str_replace('_', ' ', $movement->status) }}</span></td></tr>@empty<tr><td colspan="6" class="p-6 text-center text-slate-400">No finance activity in this period.</td></tr>@endforelse</tbody></table></div>
        <div class="divide-y divide-slate-100 md:hidden">@forelse($finance['transactions'] as $movement)<article class="space-y-2 p-4 text-xs"><div class="flex items-start justify-between gap-3"><strong>{{ $movement->movement_type }}</strong><strong class="font-mono {{ $movement->type === 'in' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $movement->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $movement->amount, 2) }}</strong></div><div class="flex justify-between gap-3 text-slate-500"><span>{{ $movement->business_date }}</span><span class="uppercase">{{ str_replace('_', ' ', $movement->status) }}</span></div><p>{{ $movement->company_account ?: ($movement->payment_source ?: '—') }}</p><p class="text-slate-500">{{ $movement->movement_reference ?: '—' }}</p></article>@empty<p class="p-6 text-center text-sm text-slate-400">No finance activity in this period.</p>@endforelse</div>
        @if($finance['transactions']->hasPages())<div class="border-t border-slate-200 p-4">{{ $finance['transactions']->links() }}</div>@endif
    </section>

    <section id="payment-history" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div><h2 class="font-black text-slate-950">Cash and Credit History</h2><p class="mt-1 text-xs text-slate-500">Cash rows consume purchaser advance. Credit rows remain in Vendor Credit.</p></div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $finance['history']->total() }} rows</span>
            </div>
            <form method="GET" action="{{ route('admin.cashbook.finance.purchase.purchasers.show', $record->public_uuid).'#payment-history' }}" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-[10rem_10rem_minmax(12rem,1fr)_auto] lg:items-end">
                <input type="hidden" name="tab" value="finance">
                <input type="hidden" name="produce_type" value="{{ $filters['warehouse_code'] === 'VEG-WH' ? 'vegetables' : ($filters['warehouse_code'] === 'FRT-WH' ? 'fruits' : 'all') }}">
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
@endif
