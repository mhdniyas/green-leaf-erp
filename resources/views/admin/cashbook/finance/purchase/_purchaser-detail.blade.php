@php
    $purchaserTabs = [
        'overview' => 'Overview',
        'purchases' => 'Purchases',
        'vendors' => 'Vendors',
        'categories' => 'Categories',
        'finance' => 'Finance',
        'funding' => 'Funding & Cash Movement',
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
    {{-- ── 1. Concise Purchaser Cash Position Entry Point Card ──────────── --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-950 p-6 text-white shadow-xl">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-emerald-500/20 border border-emerald-500/30 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-300">Company ↔ Purchaser Movement</span>
                    <span class="text-xs text-slate-400">Cumulative as of today</span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-white">Purchaser Cash Position</h2>
                <div class="flex flex-wrap items-baseline gap-3 pt-1">
                    <span class="font-mono text-3xl font-black text-emerald-400">₹{{ number_format((float) $financeSummary['remaining_advance'], 2) }}</span>
                    <span class="text-xs font-bold text-slate-300">Expected Cash With Purchaser</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="grid grid-cols-3 gap-2 rounded-xl bg-white/5 p-3 border border-white/10 text-xs">
                    <div>
                        <div class="text-[10px] uppercase font-bold text-slate-400">Company Given</div>
                        <div class="font-mono font-bold text-emerald-400">₹{{ number_format((float) $financeSummary['cash_given'], 2) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-bold text-slate-400">Returned</div>
                        <div class="font-mono font-bold text-indigo-300">₹{{ number_format((float) ($financeSummary['cash_returned'] ?? 0), 2) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-bold text-slate-400">Cash Used</div>
                        <div class="font-mono font-bold text-slate-200">₹{{ number_format((float) ($financeSummary['cash_used_invoices'] ?? $financeSummary['cash_used']), 2) }}</div>
                    </div>
                </div>
                <a href="{{ $purchaserTabUrl('funding') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-emerald-500 px-5 text-xs font-black text-slate-950 hover:bg-emerald-400 transition shadow-lg shadow-emerald-900/30">
                    <span>View Funding &amp; Cash Movement</span>
                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
            </div>
        </div>
    </section>

    {{-- ── 2. Classic Finance Overview Summary ──────────────────────────── --}}
    <section>
        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-black text-slate-950 text-base">Finance Overview</h2>
                <p class="text-xs font-semibold text-slate-500">Current balance is cumulative as of today; history below reflects selected filter range.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $purchaserTabUrl('funding') }}#record-funding" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg bg-emerald-700 px-3 text-xs font-black text-white hover:bg-emerald-600 transition shadow-sm"><i data-lucide="plus-circle" class="h-4 w-4"></i> Give Funding</a>
                <a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-black text-slate-700 hover:bg-slate-50 transition shadow-sm"><i data-lucide="truck" class="h-4 w-4"></i> Vendor Credit Payments</a>
                <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-black text-slate-700 hover:bg-slate-50 transition shadow-sm"><i data-lucide="git-compare-arrows" class="h-4 w-4"></i> Reconcile</a>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase text-slate-400">Reconciled Funding</span>
                <strong class="mt-2 block font-mono text-lg font-black text-emerald-700">₹{{ number_format((float) $finance['reconciliation']['reconciled_amount'], 2) }}</strong>
                <span class="mt-1 block text-[11px] font-semibold text-slate-500">Matched to statements</span>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase text-slate-400">Pending Reconciliation</span>
                <strong class="mt-2 block font-mono text-lg font-black text-amber-700">₹{{ number_format((float) $finance['reconciliation']['pending_reconciliation'], 2) }}</strong>
                <span class="mt-1 block text-[11px] font-semibold text-slate-500">Unmatched movements</span>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase text-slate-400">Purchaser Funding</span>
                <strong class="mt-2 block font-mono text-lg font-black text-slate-950">₹{{ number_format((float) $financeSummary['cash_given'], 2) }}</strong>
                <span class="mt-1 block text-[11px] font-semibold text-slate-500">Total given funding</span>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase text-slate-400">Cash Used</span>
                <strong class="mt-2 block font-mono text-lg font-black text-slate-950">₹{{ number_format((float) $financeSummary['cash_used'], 2) }}</strong>
                <span class="mt-1 block text-[11px] font-semibold text-slate-500">Purchases &amp; uses</span>
            </div>
            <div class="rounded-xl border {{ $financeSummary['remaining_advance'] > 0 ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase text-slate-400">Current Advance / Balance</span>
                <strong class="mt-2 block font-mono text-lg font-black {{ $financeSummary['remaining_advance'] > 0 ? 'text-emerald-900' : 'text-slate-950' }}">₹{{ number_format((float) $financeSummary['remaining_advance'], 2) }}</strong>
                <span class="mt-1 block text-[11px] font-semibold text-slate-500">Net unspent advance</span>
            </div>
        </div>
    </section>

    <section id="payment-history" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div><h2 class="font-black text-slate-950 text-base">Cash and Credit History</h2><p class="mt-0.5 text-xs text-slate-500">Cash rows consume purchaser advance. Credit rows remain in Vendor Credit.</p></div>
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
                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-600 shadow-sm"><i data-lucide="filter" class="h-4 w-4"></i> Apply</button>
                @if(in_array($filters['period'], ['custom', 'between', 'range'], true))
                    <label class="text-[10px] font-black uppercase text-slate-500">From<input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold"></label>
                    <label class="text-[10px] font-black uppercase text-slate-500">To<input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold"></label>
                @endif
            </form>
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[58rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-bold border-b border-slate-200"><tr><th class="p-3">Date</th><th class="p-3">Supplier</th><th class="p-3">Invoice / Bill</th><th class="p-3">Payment Type</th><th class="p-3 text-right">Amount</th><th class="p-3">Funding / Utilization Reference</th><th class="p-3">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($finance['history'] as $split)
                        <tr class="hover:bg-slate-50/75 transition-colors"><td class="p-3 font-mono">{{ $split->row_date }}</td><td class="p-3 font-bold">@if($split->payment_type === 'Credit' && $split->supplier_public_uuid)<a href="{{ route('admin.cashbook.finance.vendor-credit.show', $split->supplier_public_uuid) }}" class="text-emerald-700 hover:underline">{{ $split->supplier_name }}</a>@else{{ $split->supplier_name }}@endif</td><td class="p-3 font-mono font-bold">{{ $split->invoice_number }}</td><td class="p-3"><span class="rounded-full px-2 py-1 text-[9px] font-black uppercase {{ $split->payment_type === 'Cash' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800' }}">{{ $split->payment_type }}</span></td><td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $split->amount, 2) }}</td><td class="p-3">{{ $split->movement_reference ?: '—' }}</td><td class="p-3 uppercase font-semibold text-slate-600">{{ str_replace('_', ' ', (string) $split->status) }}</td></tr>
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
@elseif($tab === 'funding')
    {{-- ── Dedicated Purchaser Funding / Cash Movement Section ──────────── --}}
    <section class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-black text-slate-950 text-xl tracking-tight">Purchaser Funding</h2>
                <p class="text-xs font-bold text-slate-500">Company ↔ Purchaser Cash Movement &amp; Advance Position</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="#record-funding" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-600 transition shadow-sm">
                    <i data-lucide="plus-circle" class="h-4 w-4"></i> Record Cash Movement
                </a>
                <a href="{{ $purchaserTabUrl('finance') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                    <i data-lucide="file-text" class="h-4 w-4"></i> Finance Overview
                </a>
                <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                    <i data-lucide="git-compare-arrows" class="h-4 w-4"></i> Reconciliation
                </a>
            </div>
        </div>

        {{-- ── Top 5 Clickable Position Summary Cards ────────────────────────── --}}
        <div>
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Purchaser Cash Position (Cumulative As of Today)</h3>
                <span class="text-[11px] font-bold text-emerald-700">Click any card to inspect exact record split</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                {{-- Card 1: Company → Purchaser Given --}}
                <button type="button" onclick="openFundingSplitModal('given')" class="group text-left rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-500 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    <div class="flex items-center justify-between gap-2 text-[10px] font-black uppercase text-slate-400 group-hover:text-emerald-700">
                        <span>Company → Purchaser Given</span>
                        <i data-lucide="arrow-down-right" class="h-4 w-4 text-emerald-600"></i>
                    </div>
                    <strong class="mt-2 block font-mono text-xl font-black text-slate-950 group-hover:text-emerald-700">
                        ₹{{ number_format((float) ($fundingSplits['cumulative']['cash_given'] ?? $financeSummary['cash_given']), 2) }}
                    </strong>
                    <div class="mt-1 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500">Total company funding</span>
                        <span class="font-bold text-emerald-700 opacity-0 group-hover:opacity-100 transition">Split →</span>
                    </div>
                </button>

                {{-- Card 2: Purchaser → Company Returned --}}
                <button type="button" onclick="openFundingSplitModal('returned')" class="group text-left rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-indigo-500 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-600">
                    <div class="flex items-center justify-between gap-2 text-[10px] font-black uppercase text-slate-400 group-hover:text-indigo-700">
                        <span>Purchaser → Company Returned</span>
                        <i data-lucide="arrow-up-left" class="h-4 w-4 text-indigo-600"></i>
                    </div>
                    <strong class="mt-2 block font-mono text-xl font-black text-indigo-950 group-hover:text-indigo-700">
                        ₹{{ number_format((float) ($fundingSplits['cumulative']['cash_returned'] ?? ($financeSummary['cash_returned'] ?? 0)), 2) }}
                    </strong>
                    <div class="mt-1 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500">Refunds back to company</span>
                        <span class="font-bold text-indigo-700 opacity-0 group-hover:opacity-100 transition">Split →</span>
                    </div>
                </button>

                {{-- Card 3: Net Company Funding --}}
                <button type="button" onclick="openFundingSplitModal('net_funding')" class="group text-left rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-500 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-600">
                    <div class="flex items-center justify-between gap-2 text-[10px] font-black uppercase text-slate-400 group-hover:text-blue-700">
                        <span>Net Company Funding</span>
                        <i data-lucide="wallet" class="h-4 w-4 text-blue-600"></i>
                    </div>
                    <strong class="mt-2 block font-mono text-xl font-black text-blue-950 group-hover:text-blue-700">
                        ₹{{ number_format((float) ($fundingSplits['cumulative']['net_funding'] ?? ($financeSummary['net_funding'] ?? ($financeSummary['cash_given'] - ($financeSummary['cash_returned'] ?? 0)))), 2) }}
                    </strong>
                    <div class="mt-1 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500">Given minus returned</span>
                        <span class="font-bold text-blue-700 opacity-0 group-hover:opacity-100 transition">Breakdown →</span>
                    </div>
                </button>

                {{-- Card 4: Purchases / Approved Uses --}}
                <button type="button" onclick="openFundingSplitModal('used')" class="group text-left rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-amber-500 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-amber-600">
                    <div class="flex items-center justify-between gap-2 text-[10px] font-black uppercase text-slate-400 group-hover:text-amber-700">
                        <span>Purchases / Uses From Funding</span>
                        <i data-lucide="shopping-bag" class="h-4 w-4 text-amber-600"></i>
                    </div>
                    <strong class="mt-2 block font-mono text-xl font-black text-slate-950 group-hover:text-amber-700">
                        ₹{{ number_format((float) ($fundingSplits['cumulative']['cash_used_invoices'] ?? ($financeSummary['cash_used_invoices'] ?? $financeSummary['cash_used'])), 2) }}
                    </strong>
                    <div class="mt-1 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500">Cash purchase bills paid</span>
                        <span class="font-bold text-amber-700 opacity-0 group-hover:opacity-100 transition">Split →</span>
                    </div>
                </button>

                {{-- Card 5: Expected Cash With Purchaser --}}
                <button type="button" onclick="openFundingSplitModal('expected_cash')" class="group text-left rounded-2xl border {{ ($fundingSplits['cumulative']['remaining_advance'] ?? $financeSummary['remaining_advance']) > 0 ? 'border-emerald-300 bg-emerald-50/50 hover:border-emerald-600' : 'border-slate-200 bg-white hover:border-slate-400' }} p-4 shadow-sm transition hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    <div class="flex items-center justify-between gap-2 text-[10px] font-black uppercase {{ ($fundingSplits['cumulative']['remaining_advance'] ?? $financeSummary['remaining_advance']) > 0 ? 'text-emerald-800' : 'text-slate-500' }}">
                        <span>Expected Cash With Purchaser</span>
                        <i data-lucide="coins" class="h-4 w-4"></i>
                    </div>
                    <strong class="mt-2 block font-mono text-2xl font-black {{ ($fundingSplits['cumulative']['remaining_advance'] ?? $financeSummary['remaining_advance']) > 0 ? 'text-emerald-900' : 'text-slate-950' }}">
                        ₹{{ number_format((float) ($fundingSplits['cumulative']['remaining_advance'] ?? $financeSummary['remaining_advance']), 2) }}
                    </strong>
                    <div class="mt-1 flex items-center justify-between text-[11px]">
                        <span class="font-semibold {{ ($fundingSplits['cumulative']['remaining_advance'] ?? $financeSummary['remaining_advance']) > 0 ? 'text-emerald-700' : 'text-slate-500' }}">
                            ● Cash in hand with purchaser
                        </span>
                        <span class="font-bold text-emerald-800 opacity-0 group-hover:opacity-100 transition">Equation →</span>
                    </div>
                </button>
            </div>
        </div>

        {{-- ── Cumulative vs Selected Period Activity Comparison Strip ───────── --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs font-semibold text-slate-700">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                <span class="font-bold text-slate-900 uppercase text-[10px] tracking-wider">Activity in Selected Period ({{ $filters['start_date'] }} to {{ $filters['end_date'] }}):</span>
                <span>Given: <strong class="font-mono text-emerald-800 font-bold">₹{{ number_format((float) ($fundingSplits['period']['cash_given'] ?? $finance['activity']['cash_given']), 2) }}</strong></span>
                <span>Returned: <strong class="font-mono text-indigo-800 font-bold">₹{{ number_format((float) ($fundingSplits['period']['cash_returned'] ?? ($finance['activity']['cash_returned'] ?? 0)), 2) }}</strong></span>
                <span>Net: <strong class="font-mono text-blue-900 font-bold">₹{{ number_format((float) ($fundingSplits['period']['net_funding'] ?? ($finance['activity']['net_funding'] ?? 0)), 2) }}</strong></span>
                <span>Used: <strong class="font-mono text-slate-900 font-bold">₹{{ number_format((float) ($fundingSplits['period']['cash_used_invoices'] ?? ($finance['activity']['cash_used_invoices'] ?? $finance['activity']['cash_used'])), 2) }}</strong></span>
            </div>
            <div class="flex items-center gap-4 text-slate-500 text-[11px]">
                <span>Prior Period Carry Forward: <strong class="font-mono text-slate-800 font-bold">₹{{ number_format((float) (($fundingSplits['cumulative']['remaining_advance'] ?? $financeSummary['remaining_advance']) - ($fundingSplits['period']['net_funding'] ?? 0) + ($fundingSplits['period']['cash_used_invoices'] ?? 0)), 2) }}</strong></span>
            </div>
        </div>

        {{-- ── Record Company ↔ Purchaser Movement Form ──────────────────────── --}}
        <section id="record-funding" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
            <div class="mb-4 border-b border-slate-200 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h3 class="font-black text-slate-950 text-base">Record Company ↔ Purchaser Movement</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Record actual physical cash or bank movements between Green Leaf and {{ $record->name }}. Reuses canonical funding journal entries.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.cashbook.finance.purchasers.funding.store', $record->public_uuid) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-slate-700">Movement Direction <span class="text-rose-600">*</span></label>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-300 p-3 hover:bg-slate-50 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/50 has-[:checked]:text-emerald-950 transition">
                            <input type="radio" name="direction" value="company_to_purchaser" checked class="h-4 w-4 text-emerald-600 focus:ring-emerald-500" onchange="updateMovementFormLabels(this.value)">
                            <div>
                                <div class="text-xs font-black text-slate-900">Company → Purchaser (Funding Given)</div>
                                <div class="text-[11px] text-slate-500">Increases purchaser cash advance in hand</div>
                            </div>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-300 p-3 hover:bg-slate-50 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/50 has-[:checked]:text-indigo-950 transition">
                            <input type="radio" name="direction" value="purchaser_to_company" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500" onchange="updateMovementFormLabels(this.value)">
                            <div>
                                <div class="text-xs font-black text-slate-900">Purchaser → Company (Refund / Returned)</div>
                                <div class="text-[11px] text-slate-500">Decreases purchaser advance &amp; deposits back to company</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="funding-amount" class="mb-1 block text-xs font-bold text-slate-600">Amount (₹) <span class="text-rose-600">*</span></label>
                        <input id="funding-amount" name="amount" type="number" step="0.01" min="0.01" required class="min-h-11 w-full rounded-lg border border-slate-300 px-3 font-mono text-sm font-bold text-slate-900">
                    </div>
                    <div>
                        <label for="funding-date" class="mb-1 block text-xs font-bold text-slate-600">Date <span class="text-rose-600">*</span></label>
                        <input id="funding-date" name="business_date" type="date" value="{{ today()->toDateString() }}" required class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-800">
                    </div>
                    <div>
                        <label for="funding-source" class="mb-1 block text-xs font-bold text-slate-600">Source / Mode <span class="text-rose-600">*</span></label>
                        <select id="funding-source" name="payment_source" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                            <option value="Bank">Bank</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>
                    <div>
                        <label for="funding-account" class="mb-1 block text-xs font-bold text-slate-600">Company Account</label>
                        <select id="funding-account" name="company_account_id" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                            <option value="">Select account (Optional)</option>
                            @foreach($companyAccounts as $account)
                                <option value="{{ $account->id }}" @selected(App\Models\Cashbook\CompanyAccount::isSelected($account, old('company_account_id'), $companyAccounts))>{{ $account->name }} / {{ strtoupper($account->account_type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-1 xl:col-span-2">
                        <label for="funding-reference" class="mb-1 block text-xs font-bold text-slate-600">Reference / UTR / Voucher</label>
                        <input id="funding-reference" name="reference" type="text" maxlength="160" placeholder="UTR or voucher number" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                    </div>
                    <div class="md:col-span-1 xl:col-span-2">
                        <label for="funding-description" class="mb-1 block text-xs font-bold text-slate-600">Notes / Description</label>
                        <input id="funding-description" name="description" type="text" maxlength="255" placeholder="Optional notes" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                    </div>
                </div>

                <div>
                    <button id="btnRecordMovementSubmit" type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-5 text-xs font-black text-white hover:bg-emerald-600 transition shadow-sm">
                        <i data-lucide="plus-circle" class="h-4 w-4"></i> Record Company Funding
                    </button>
                </div>
            </form>
        </section>

        {{-- ── Main Company ↔ Purchaser Movement Ledger ──────────────────────── --}}
        <section id="finance-transactions" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 sm:p-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="font-black text-slate-950 text-base">Company ↔ Purchaser Cash Movement</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Complete audit ledger showing money movement direction, running cash balance, reconciliation status, and actions.</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $finance['transactions']->total() }} movements</span>
            </div>
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[64rem] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-bold border-b border-slate-200">
                        <tr>
                            <th class="p-3.5">Date &amp; Time</th>
                            <th class="p-3.5">Direction</th>
                            <th class="p-3.5 text-right">Amount</th>
                            <th class="p-3.5">Method &amp; Account</th>
                            <th class="p-3.5">Reference &amp; Note</th>
                            <th class="p-3.5">Created By</th>
                            <th class="p-3.5 text-right">Running Balance</th>
                            <th class="p-3.5">Status</th>
                            <th class="p-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($finance['transactions'] as $movement)
                            <tr class="hover:bg-slate-50/75 transition-colors">
                                <td class="p-3.5 font-mono text-slate-700 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">{{ $movement->business_date }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $movement->created_at ? \Carbon\Carbon::parse($movement->created_at)->format('H:i') : '—' }}</div>
                                </td>
                                <td class="p-3.5 whitespace-nowrap">
                                    @if($movement->type === 'in')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-[10px] font-black uppercase text-emerald-800">
                                            <i data-lucide="arrow-down-right" class="h-3 w-3"></i> Company → Purchaser
                                        </span>
                                    @elseif($movement->type === 'out' && $movement->purchase_invoice_id === null)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-2.5 py-1 text-[10px] font-black uppercase text-indigo-800">
                                            <i data-lucide="arrow-up-left" class="h-3 w-3"></i> Purchaser → Company
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase text-slate-700">
                                            <i data-lucide="shopping-cart" class="h-3 w-3 text-slate-500"></i> Cash Purchase Spend
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3.5 text-right font-mono font-bold whitespace-nowrap text-sm {{ $movement->type === 'in' ? 'text-emerald-700' : ($movement->purchase_invoice_id === null ? 'text-indigo-700' : 'text-slate-900') }}">
                                    {{ $movement->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $movement->amount, 2) }}
                                </td>
                                <td class="p-3.5 text-slate-700 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">{{ $movement->company_account ?: ($movement->payment_source ?: '—') }}</div>
                                    <div class="text-[10px] text-slate-500">{{ $movement->payment_source ? strtoupper($movement->payment_source) : '' }}</div>
                                </td>
                                <td class="p-3.5 text-slate-600 max-w-[12rem] truncate">
                                    <div class="font-medium text-slate-900 truncate">{{ $movement->movement_reference ?: '—' }}</div>
                                    @if($movement->funding_description && $movement->funding_description !== $movement->movement_reference)
                                        <div class="text-[10px] text-slate-500 truncate">{{ $movement->funding_description }}</div>
                                    @endif
                                </td>
                                <td class="p-3.5 text-slate-600 whitespace-nowrap font-medium">
                                    {{ $movement->created_by_name ?? '—' }}
                                </td>
                                <td class="p-3.5 text-right font-mono font-black whitespace-nowrap text-sm {{ (float)$movement->running_balance > 0 ? 'text-emerald-900' : ((float)$movement->running_balance < 0 ? 'text-rose-900' : 'text-slate-700') }}">
                                    ₹{{ number_format((float) $movement->running_balance, 2) }}
                                </td>
                                <td class="p-3.5">
                                    @if($movement->type === 'out' && $movement->purchase_invoice_id !== null)
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">advance utilized</span>
                                    @elseif($movement->status === 'unmatched')
                                        <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">UNMATCHED</span>
                                    @elseif($movement->status === 'matched')
                                        <div class="space-y-0.5">
                                           <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">✓ MATCHED</span>
                                           <div class="text-[10px] text-slate-500 font-medium">{{ $movement->statement_account_name }} · {{ $movement->statement_date }}</div>
                                        </div>
                                    @elseif($movement->status === 'manual_cash')
                                        <div class="space-y-0.5">
                                           <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-300 px-2 py-0.5 text-[9px] font-black uppercase text-amber-900">✓ MANUAL CASH</span>
                                           <div class="text-[10px] text-slate-500 font-medium">{{ $movement->statement_account_name ?: 'Cash Account' }} · {{ $movement->statement_date }}</div>
                                        </div>
                                    @elseif($movement->status === 'manual_statement')
                                        <div class="space-y-0.5">
                                           <span class="inline-flex items-center rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[9px] font-black uppercase text-blue-900">✓ MANUAL STATEMENT</span>
                                           <div class="text-[10px] text-slate-500 font-medium">{{ $movement->statement_account_name }} · {{ $movement->statement_date }}</div>
                                        </div>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">{{ str_replace('_', ' ', $movement->status) }}</span>
                                    @endif
                                </td>
                                <td class="p-3.5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                        <button type="button" onclick="openMovementDetailsModal({{ json_encode($movement) }})" class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition">
                                            <i data-lucide="info" class="h-3 w-3 text-slate-500"></i> Details
                                        </button>
                                        @if($movement->purchase_invoice_id === null)
                                            @if(!$movement->funding_action_blocked && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                                                <button type="button" onclick="openEditFundingModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ $movement->payment_source }}', '{{ $movement->company_account_id }}', '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ addslashes($movement->funding_description ?? '') }}', '{{ $movement->status }}', '{{ $movement->type }}')" class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition">
                                                    <i data-lucide="pencil" class="h-3 w-3"></i> Edit
                                                </button>
                                                <button type="button" onclick="openDeleteFundingModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->company_account ?: ($movement->payment_source ?: '—')) }}', '{{ $movement->status }}', '{{ $movement->type }}')" class="inline-flex items-center gap-1 rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700 hover:bg-rose-100 shadow-sm transition">
                                                    <i data-lucide="trash-2" class="h-3 w-3"></i> Delete
                                                </button>
                                            @endif
                                            @if($movement->status === 'unmatched')
                                                <button type="button" onclick="openMatchStatementModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ addslashes($movement->company_account ?? '') }}')" class="inline-flex items-center gap-1 rounded bg-emerald-700 px-2 py-1 text-[10px] font-bold text-white hover:bg-emerald-600 shadow-sm transition">
                                                    <i data-lucide="git-merge" class="h-3 w-3"></i> Match Statement
                                                </button>
                                                <button type="button" onclick="openManualEntryModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ $movement->company_account_id }}')" class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition">
                                                    <i data-lucide="plus-circle" class="h-3 w-3"></i> Add Cash/Statement
                                                </button>
                                            @elseif(in_array($movement->status, ['matched', 'manual_cash', 'manual_statement'], true))
                                                <button type="button" onclick="openViewMatchModal({{ $movement->id }}, '{{ $record->public_uuid }}')" class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition">
                                                    <i data-lucide="eye" class="h-3 w-3"></i> Trace
                                                </button>
                                                @if($movement->status === 'matched')
                                                    <button type="button" onclick="openUnmatchModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->statement_account_name ?? '') }}')" class="inline-flex items-center gap-1 rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700 hover:bg-rose-100 shadow-sm transition">
                                                        <i data-lucide="unlink" class="h-3 w-3"></i> Unmatch
                                                    </button>
                                                @endif
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-8 text-center text-slate-400">No finance cash movements recorded in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-100 lg:hidden">
                @forelse($finance['transactions'] as $movement)
                    <article class="p-4 space-y-3 text-xs">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                @if($movement->type === 'in')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">
                                        <i data-lucide="arrow-down-right" class="h-3 w-3"></i> Company → Purchaser
                                    </span>
                                @elseif($movement->type === 'out' && $movement->purchase_invoice_id === null)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[9px] font-black uppercase text-indigo-800">
                                        <i data-lucide="arrow-up-left" class="h-3 w-3"></i> Purchaser → Company
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">
                                        <i data-lucide="shopping-cart" class="h-3 w-3 text-slate-500"></i> Spend
                                    </span>
                                @endif
                                <div class="mt-1 font-mono text-[11px] text-slate-500">{{ $movement->business_date }}</div>
                            </div>
                            <div class="text-right">
                                <strong class="font-mono text-base font-black {{ $movement->type === 'in' ? 'text-emerald-700' : ($movement->purchase_invoice_id === null ? 'text-indigo-700' : 'text-slate-900') }}">
                                    {{ $movement->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $movement->amount, 2) }}
                                </strong>
                                <div class="mt-0.5 font-mono text-[11px] font-bold text-slate-600">Bal: ₹{{ number_format((float) $movement->running_balance, 2) }}</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                            <div>Account: <strong class="text-slate-800">{{ $movement->company_account ?: ($movement->payment_source ?: '—') }}</strong></div>
                            <div>Created by: <strong class="text-slate-800">{{ $movement->created_by_name ?? '—' }}</strong></div>
                            <div class="col-span-2">Ref: <span class="font-mono text-slate-700">{{ $movement->movement_reference ?: '—' }}</span></div>
                        </div>
                        <div class="flex items-center justify-between gap-2 flex-wrap pt-1">
                            <div>
                                @if($movement->type === 'out' && $movement->purchase_invoice_id !== null)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">advance utilized</span>
                                @elseif($movement->status === 'unmatched')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">UNMATCHED</span>
                                @elseif(in_array($movement->status, ['matched', 'manual_cash', 'manual_statement'], true))
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">{{ str_replace('_', ' ', $movement->status) }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <button type="button" onclick="openMovementDetailsModal({{ json_encode($movement) }})" class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700">Details</button>
                                @if($movement->purchase_invoice_id === null)
                                    @if(!$movement->funding_action_blocked && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                                        <button type="button" onclick="openEditFundingModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ $movement->payment_source }}', '{{ $movement->company_account_id }}', '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ addslashes($movement->funding_description ?? '') }}', '{{ $movement->status }}', '{{ $movement->type }}')" class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700">Edit</button>
                                        <button type="button" onclick="openDeleteFundingModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->company_account ?: ($movement->payment_source ?: '—')) }}', '{{ $movement->status }}', '{{ $movement->type }}')" class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700">Delete</button>
                                    @endif
                                    @if($movement->status === 'unmatched')
                                        <button type="button" onclick="openMatchStatementModal({{ $movement->id }}, '{{ $record->public_uuid }}', '{{ $movement->business_date }}', {{ (float) $movement->amount }}, '{{ addslashes($movement->funding_reference ?? $movement->movement_reference ?? '') }}', '{{ addslashes($movement->company_account ?? '') }}')" class="rounded bg-emerald-700 px-2 py-1 text-[10px] font-bold text-white">Match Statement</button>
                                    @elseif(in_array($movement->status, ['matched', 'manual_cash', 'manual_statement'], true))
                                        <button type="button" onclick="openViewMatchModal({{ $movement->id }}, '{{ $record->public_uuid }}')" class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700">Trace</button>
                                    @endif
                                @endif
                            </div>
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
    </section>
@endif

@if(in_array($tab, ['finance', 'funding'], true))
    {{-- ── Funding Split & Drill-Down Modal / Drawer ─────────────────────── --}}
    <div id="fundingSplitModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div id="splitModalIconContainer" class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800">
                        <i id="splitModalIcon" data-lucide="arrow-down-right" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 id="splitModalTitle" class="font-black text-slate-900 text-base">Company → Purchaser Given Split</h3>
                        <p id="splitModalSubtitle" class="text-xs text-slate-500 font-semibold">Exact audit records comprising this amount</p>
                    </div>
                </div>
                <button type="button" onclick="closeFundingSplitModal()" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            {{-- Modal Summary Banner --}}
            <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-baseline gap-3">
                    <span class="text-xs font-bold uppercase text-slate-400">Total Calculated:</span>
                    <strong id="splitModalTotalAmount" class="font-mono text-2xl font-black text-emerald-800">₹0.00</strong>
                    <span id="splitModalCountBadge" class="rounded-full bg-slate-200 px-2.5 py-0.5 text-[10px] font-bold text-slate-700">0 records</span>
                </div>
                {{-- Scope Switcher: Cumulative vs Selected Period --}}
                <div class="flex items-center rounded-lg border border-slate-200 bg-white p-1 shadow-xs text-xs font-bold">
                    <button type="button" id="btnSplitScopeCumulative" onclick="setFundingSplitScope('cumulative')" class="rounded-md px-3 py-1 bg-emerald-700 text-white shadow-xs transition">
                        All Records (Cumulative As of Today)
                    </button>
                    <button type="button" id="btnSplitScopePeriod" onclick="setFundingSplitScope('period')" class="rounded-md px-3 py-1 text-slate-600 hover:text-slate-900 transition">
                        Selected Period ({{ $filters['start_date'] }} to {{ $filters['end_date'] }})
                    </button>
                </div>
            </div>

            {{-- Optional Sub-Tabs (for Net Funding and Expected Cash) --}}
            <div id="splitSubTabsContainer" class="hidden border-b border-slate-200 bg-white px-6 pt-2 pb-0 flex gap-2">
                <button type="button" id="subTabAllBtn" onclick="setFundingSplitSubTab('all')" class="border-b-2 border-emerald-700 pb-2 px-3 text-xs font-black text-emerald-800">All Movements</button>
                <button type="button" id="subTabGivenBtn" onclick="setFundingSplitSubTab('given')" class="border-b-2 border-transparent pb-2 px-3 text-xs font-bold text-slate-500 hover:text-slate-800">Given (+)</button>
                <button type="button" id="subTabReturnedBtn" onclick="setFundingSplitSubTab('returned')" class="border-b-2 border-transparent pb-2 px-3 text-xs font-bold text-slate-500 hover:text-slate-800">Returned (-)</button>
                <button type="button" id="subTabUsedBtn" onclick="setFundingSplitSubTab('used')" class="border-b-2 border-transparent pb-2 px-3 text-xs font-bold text-slate-500 hover:text-slate-800">Purchases Used</button>
            </div>

            {{-- Equation Breakdown Card (when viewing net funding or expected cash) --}}
            <div id="splitEquationCard" class="hidden mx-6 mt-4 p-3.5 rounded-xl border border-blue-200 bg-blue-50/60 text-xs text-blue-950">
                <div class="flex items-center gap-2 font-bold mb-1">
                    <i data-lucide="calculator" class="h-4 w-4 text-blue-700"></i>
                    <span>Formula Verification:</span>
                </div>
                <div id="splitEquationText" class="font-mono text-[11px] text-blue-900 font-bold">
                    Given (₹0.00) - Returned (₹0.00) = Net Funding (₹0.00)
                </div>
            </div>

            {{-- Modal Body: Split Table / List --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-4 text-xs">
                {{-- Desktop Table --}}
                <div class="hidden md:block overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-bold border-b border-slate-200">
                            <tr>
                                <th class="p-3">Date &amp; Time</th>
                                <th class="p-3">Direction / Type</th>
                                <th class="p-3 text-right">Amount</th>
                                <th class="p-3">Method &amp; Account</th>
                                <th class="p-3">Ref &amp; Description</th>
                                <th class="p-3">Created By</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="splitTableBody" class="divide-y divide-slate-100">
                            {{-- Dynamically populated via JS --}}
                        </tbody>
                    </table>
                </div>

                {{-- Mobile List --}}
                <div id="splitMobileList" class="divide-y divide-slate-100 md:hidden rounded-xl border border-slate-200">
                    {{-- Dynamically populated via JS --}}
                </div>

                {{-- Empty State --}}
                <div id="splitEmptyState" class="hidden p-8 text-center text-slate-400">
                    <i data-lucide="inbox" class="h-8 w-8 mx-auto mb-2 text-slate-300"></i>
                    <p class="text-sm font-semibold">No records found for this drill-down selection.</p>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-3.5">
                <span class="text-[11px] font-semibold text-slate-500">All drill-down records are directly synchronized with canonical accounting source of truth.</span>
                <button type="button" onclick="closeFundingSplitModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-xs">
                    Close
                </button>
            </div>
        </div>
    </div>

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
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="period" value="{{ $filters['period'] ?? 'month' }}">
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
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="period" value="{{ $filters['period'] ?? 'month' }}">
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

    {{-- ── Movement Details Modal ───────────────────────────────────────── --}}
    <div id="movementDetailsModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="file-text" class="h-5 w-5 text-emerald-700"></i>
                    <h3 class="font-black text-slate-900">Cash Movement Details</h3>
                </div>
                <button type="button" onclick="closeMovementDetailsModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="p-5 space-y-4 text-xs overflow-y-auto max-h-[80vh]">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span id="detailDirectionBadge" class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider">—</span>
                        <span id="detailStatusBadge" class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">—</span>
                    </div>
                    <div class="flex items-baseline justify-between pt-1">
                        <div>
                            <span class="text-[11px] font-semibold text-slate-500">Movement Amount</span>
                            <div id="detailAmount" class="font-mono text-2xl font-black text-slate-900">₹0.00</div>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] font-semibold text-slate-500">Running Balance</span>
                            <div id="detailRunningBalance" class="font-mono text-lg font-bold text-slate-800">₹0.00</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-slate-700">
                    <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3 space-y-1">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Business Date</span>
                        <div id="detailBusinessDate" class="font-mono font-bold text-slate-900">—</div>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3 space-y-1">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Recorded At</span>
                        <div id="detailRecordedAt" class="font-mono font-bold text-slate-900">—</div>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3 space-y-1">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Created By</span>
                        <div id="detailCreatedBy" class="font-bold text-slate-900">—</div>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3 space-y-1">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Method &amp; Account</span>
                        <div id="detailAccount" class="font-bold text-slate-900">—</div>
                    </div>
                    <div class="col-span-2 rounded-lg border border-slate-100 bg-slate-50/50 p-3 space-y-1">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Reference / UTR</span>
                        <div id="detailReference" class="font-mono font-bold text-slate-900 truncate">—</div>
                    </div>
                    <div class="col-span-2 rounded-lg border border-slate-100 bg-slate-50/50 p-3 space-y-1">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Notes / Description</span>
                        <div id="detailDescription" class="font-medium text-slate-800">—</div>
                    </div>
                </div>

                <div id="detailReconciliationSection" class="hidden rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 space-y-2">
                    <span class="font-bold text-emerald-800 uppercase text-[10px] tracking-wider">Statement Reconciliation</span>
                    <div class="grid grid-cols-2 gap-2 text-slate-600">
                        <div>Account: <strong id="detailStmtAccount" class="text-slate-900 font-bold">—</strong></div>
                        <div>Date: <strong id="detailStmtDate" class="text-slate-900">—</strong></div>
                        <div>Amount: <strong id="detailStmtAmount" class="font-mono text-emerald-800 font-black">—</strong></div>
                        <div>Ref: <strong id="detailStmtRef" class="font-mono text-slate-900">—</strong></div>
                        <div class="col-span-2">Narration: <span id="detailStmtNarration" class="text-slate-700 font-medium">—</span></div>
                        <div class="col-span-2 text-[11px] text-slate-500 pt-1 border-t border-emerald-100">
                            Reconciled By: <strong id="detailReconciledBy" class="text-slate-700">—</strong> at <strong id="detailReconciledAt" class="text-slate-700">—</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end border-t border-slate-200 bg-slate-50 px-5 py-3.5">
                <button type="button" onclick="closeMovementDetailsModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Close</button>
            </div>
        </div>
    </div>

    <script>
        const fundingSplitsData = @json($fundingSplits ?? null);
        let activeSplitCard = 'given';
        let activeSplitScope = 'cumulative';
        let activeSplitSubTab = 'all';

        function openFundingSplitModal(cardType) {
            activeSplitCard = cardType;
            activeSplitScope = 'cumulative';
            activeSplitSubTab = 'all';
            renderFundingSplitView();
            const modal = document.getElementById('fundingSplitModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
            if (window.lucide) lucide.createIcons();
        }

        function closeFundingSplitModal() {
            const modal = document.getElementById('fundingSplitModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function setFundingSplitScope(scope) {
            activeSplitScope = scope;
            renderFundingSplitView();
        }

        function setFundingSplitSubTab(subTab) {
            activeSplitSubTab = subTab;
            renderFundingSplitView();
        }

        function renderFundingSplitView() {
            if (!fundingSplitsData) return;

            const isCumulative = activeSplitScope === 'cumulative';
            const btnCumulative = document.getElementById('btnSplitScopeCumulative');
            const btnPeriod = document.getElementById('btnSplitScopePeriod');
            if (btnCumulative && btnPeriod) {
                if (isCumulative) {
                    btnCumulative.className = 'rounded-md px-3 py-1 bg-emerald-700 text-white shadow-xs transition';
                    btnPeriod.className = 'rounded-md px-3 py-1 text-slate-600 hover:text-slate-900 transition';
                } else {
                    btnPeriod.className = 'rounded-md px-3 py-1 bg-emerald-700 text-white shadow-xs transition';
                    btnCumulative.className = 'rounded-md px-3 py-1 text-slate-600 hover:text-slate-900 transition';
                }
            }

            const titleEl = document.getElementById('splitModalTitle');
            const subtitleEl = document.getElementById('splitModalSubtitle');
            const iconContainerEl = document.getElementById('splitModalIconContainer');
            const totalEl = document.getElementById('splitModalTotalAmount');
            const countEl = document.getElementById('splitModalCountBadge');
            const subTabsContainer = document.getElementById('splitSubTabsContainer');
            const equationCard = document.getElementById('splitEquationCard');
            const equationText = document.getElementById('splitEquationText');
            const tableBody = document.getElementById('splitTableBody');
            const mobileList = document.getElementById('splitMobileList');
            const emptyState = document.getElementById('splitEmptyState');

            const scopeTotals = isCumulative ? (fundingSplitsData.cumulative || {}) : (fundingSplitsData.period || {});
            let records = [];
            let totalAmount = 0;
            let cardTitle = '';
            let cardSubtitle = '';
            let iconClass = 'arrow-down-right';
            let iconBg = 'bg-emerald-100 text-emerald-800';

            if (subTabsContainer) subTabsContainer.classList.add('hidden');
            if (equationCard) equationCard.classList.add('hidden');

            const rawGiven = Array.isArray(fundingSplitsData.given) ? fundingSplitsData.given : Object.values(fundingSplitsData.given || {});
            const rawReturned = Array.isArray(fundingSplitsData.returned) ? fundingSplitsData.returned : Object.values(fundingSplitsData.returned || {});
            const rawUsed = Array.isArray(fundingSplitsData.used) ? fundingSplitsData.used : Object.values(fundingSplitsData.used || {});

            const allGiven = isCumulative ? rawGiven : rawGiven.filter(m => Boolean(m.in_period));
            const allReturned = isCumulative ? rawReturned : rawReturned.filter(m => Boolean(m.in_period));
            const allUsed = isCumulative ? rawUsed : rawUsed.filter(m => Boolean(m.in_period));

            if (activeSplitCard === 'given') {
                cardTitle = 'Company → Purchaser Given Split';
                cardSubtitle = isCumulative ? 'All cumulative funding given by company to purchaser' : 'Funding given within selected period';
                iconClass = 'arrow-down-right';
                iconBg = 'bg-emerald-100 text-emerald-800';
                totalAmount = scopeTotals.cash_given || 0;
                records = allGiven;
            } else if (activeSplitCard === 'returned') {
                cardTitle = 'Purchaser → Company Returned Split';
                cardSubtitle = isCumulative ? 'All cumulative refunds & deposits returned to company' : 'Refunds returned within selected period';
                iconClass = 'arrow-up-left';
                iconBg = 'bg-indigo-100 text-indigo-800';
                totalAmount = scopeTotals.cash_returned || 0;
                records = allReturned;
            } else if (activeSplitCard === 'net_funding') {
                cardTitle = 'Net Company Funding Breakdown';
                cardSubtitle = 'Company Given minus Purchaser Returned';
                iconClass = 'wallet';
                iconBg = 'bg-blue-100 text-blue-800';
                totalAmount = scopeTotals.net_funding || 0;
                if (subTabsContainer) subTabsContainer.classList.remove('hidden');
                if (equationCard) equationCard.classList.remove('hidden');
                if (equationText) {
                    equationText.textContent = `Given (₹${Number(scopeTotals.cash_given || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}) - Returned (₹${Number(scopeTotals.cash_returned || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}) = Net Funding (₹${Number(scopeTotals.net_funding || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})})`;
                }

                ['all', 'given', 'returned'].forEach(tab => {
                    const btn = document.getElementById(`subTab${tab.charAt(0).toUpperCase() + tab.slice(1)}Btn`);
                    if (btn) {
                        btn.className = activeSplitSubTab === tab 
                            ? 'border-b-2 border-emerald-700 pb-2 px-3 text-xs font-black text-emerald-800'
                            : 'border-b-2 border-transparent pb-2 px-3 text-xs font-bold text-slate-500 hover:text-slate-800';
                    }
                });
                const usedTabBtn = document.getElementById('subTabUsedBtn');
                if (usedTabBtn) usedTabBtn.classList.add('hidden');

                if (activeSplitSubTab === 'given') {
                    records = allGiven;
                } else if (activeSplitSubTab === 'returned') {
                    records = allReturned;
                } else {
                    records = [...allGiven, ...allReturned].sort((a, b) => (b.business_date || '').localeCompare(a.business_date || ''));
                }
            } else if (activeSplitCard === 'used') {
                cardTitle = 'Purchases / Uses From Funding Split';
                cardSubtitle = isCumulative ? 'All cumulative cash purchase invoice spends against funding' : 'Cash purchase spends within selected period';
                iconClass = 'shopping-bag';
                iconBg = 'bg-amber-100 text-amber-800';
                totalAmount = scopeTotals.cash_used_invoices || 0;
                records = allUsed;
            } else if (activeSplitCard === 'expected_cash') {
                cardTitle = 'Expected Cash With Purchaser Breakdown';
                cardSubtitle = 'Net Funding minus Cash Purchases/Uses (Advance in hand)';
                iconClass = 'coins';
                iconBg = 'bg-emerald-100 text-emerald-800';
                totalAmount = scopeTotals.remaining_advance || 0;
                if (subTabsContainer) subTabsContainer.classList.remove('hidden');
                if (equationCard) equationCard.classList.remove('hidden');
                if (equationText) {
                    equationText.textContent = `Net Funding (₹${Number(scopeTotals.net_funding || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}) - Purchases Used (₹${Number(scopeTotals.cash_used_invoices || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}) = Expected Cash (₹${Number(scopeTotals.remaining_advance || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})})`;
                }

                const usedTabBtn = document.getElementById('subTabUsedBtn');
                if (usedTabBtn) usedTabBtn.classList.remove('hidden');
                ['all', 'given', 'returned', 'used'].forEach(tab => {
                    const btn = document.getElementById(`subTab${tab.charAt(0).toUpperCase() + tab.slice(1)}Btn`);
                    if (btn) {
                        btn.className = activeSplitSubTab === tab 
                            ? 'border-b-2 border-emerald-700 pb-2 px-3 text-xs font-black text-emerald-800'
                            : 'border-b-2 border-transparent pb-2 px-3 text-xs font-bold text-slate-500 hover:text-slate-800';
                    }
                });

                if (activeSplitSubTab === 'given') {
                    records = allGiven;
                } else if (activeSplitSubTab === 'returned') {
                    records = allReturned;
                } else if (activeSplitSubTab === 'used') {
                    records = allUsed;
                } else {
                    records = [...allGiven, ...allReturned, ...allUsed].sort((a, b) => (b.business_date || '').localeCompare(a.business_date || ''));
                }
            }

            if (titleEl) titleEl.textContent = cardTitle;
            if (subtitleEl) subtitleEl.textContent = cardSubtitle;
            if (iconContainerEl) iconContainerEl.className = `flex h-9 w-9 items-center justify-center rounded-xl ${iconBg}`;
            const iconEl = document.getElementById('splitModalIcon');
            if (iconEl) iconEl.setAttribute('data-lucide', iconClass);
            if (totalEl) totalEl.textContent = '₹' + Number(totalAmount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            if (countEl) countEl.textContent = `${records.length} records`;

            if (records.length === 0) {
                if (tableBody) tableBody.innerHTML = '';
                if (mobileList) mobileList.innerHTML = '';
                if (emptyState) emptyState.classList.remove('hidden');
                if (window.lucide) lucide.createIcons();
                return;
            }

            if (emptyState) emptyState.classList.add('hidden');

            const renderRow = (m) => {
                const isGiven = m.type === 'in';
                const isRet = m.type === 'out' && !m.purchase_invoice_id;
                const isUsed = m.type === 'out' && m.purchase_invoice_id;
                const formattedAmt = Number(m.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const dirBadge = isGiven
                    ? '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800"><i data-lucide="arrow-down-right" class="h-3 w-3"></i> Company → Purchaser</span>'
                    : (isRet
                        ? '<span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[9px] font-black uppercase text-indigo-800"><i data-lucide="arrow-up-left" class="h-3 w-3"></i> Purchaser → Company</span>'
                        : '<span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700"><i data-lucide="shopping-cart" class="h-3 w-3 text-slate-500"></i> Cash Spend</span>');

                const statusBadge = isUsed
                    ? '<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600">advance utilized</span>'
                    : (m.status === 'unmatched'
                        ? '<span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">UNMATCHED</span>'
                        : (['matched', 'manual_cash', 'manual_statement'].includes(m.status)
                            ? `<span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800">✓ ${(m.status || '').replace('_', ' ').toUpperCase()}</span>`
                            : `<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">${(m.status || 'RECORDED').replace('_', ' ').toUpperCase()}</span>`));

                const colorClass = isGiven ? 'text-emerald-700' : (isRet ? 'text-indigo-700' : 'text-slate-900');
                const sign = isGiven ? '+' : '-';

                const actions = `
                    <div class="flex items-center justify-end gap-1 flex-wrap">
                        <button type="button" onclick='openMovementDetailsModal(${JSON.stringify(m).replace(/'/g, "&apos;")})' class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 shadow-xs">
                            Details
                        </button>
                        ${!isUsed ? `
                            ${(!m.funding_action_blocked) ? `
                                <button type="button" onclick="openEditFundingModal(${m.id}, '{{ $record->public_uuid }}', '${m.business_date}', ${Number(m.amount)}, '${m.payment_source || 'Bank'}', '${m.company_account_id || ''}', '${(m.funding_reference || m.movement_reference || '').replace(/'/g, "\\'")}', '${(m.funding_description || '').replace(/'/g, "\\'")}', '${m.status}', '${m.type}')" class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 shadow-xs">
                                    Edit
                                </button>
                                <button type="button" onclick="openDeleteFundingModal(${m.id}, '{{ $record->public_uuid }}', '${m.business_date}', ${Number(m.amount)}, '${(m.company_account || m.payment_source || '—').replace(/'/g, "\\'")}', '${m.status}', '${m.type}')" class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700 hover:bg-rose-100 shadow-xs">
                                    Delete
                                </button>
                            ` : ''}
                            ${m.status === 'unmatched' ? `
                                <button type="button" onclick="openMatchStatementModal(${m.id}, '{{ $record->public_uuid }}', '${m.business_date}', ${Number(m.amount)}, '${(m.funding_reference || m.movement_reference || '').replace(/'/g, "\\'")}', '${(m.company_account || '').replace(/'/g, "\\'")}')" class="rounded bg-emerald-700 px-2 py-1 text-[10px] font-bold text-white hover:bg-emerald-600 shadow-xs">
                                    Match
                                </button>
                            ` : ''}
                        ` : ''}
                    </div>
                `;

                return `
                    <tr class="hover:bg-slate-50/75 transition-colors">
                        <td class="p-3 font-mono text-slate-700 whitespace-nowrap">
                            <div class="font-bold text-slate-900">${m.business_date || '—'}</div>
                            <div class="text-[10px] text-slate-400">${m.created_at ? m.created_at.substring(11, 16) : '—'}</div>
                        </td>
                        <td class="p-3 whitespace-nowrap">${dirBadge}</td>
                        <td class="p-3 text-right font-mono font-black whitespace-nowrap text-sm ${colorClass}">
                            ${sign}₹${formattedAmt}
                        </td>
                        <td class="p-3 text-slate-700 whitespace-nowrap">
                            <div class="font-bold text-slate-900">${m.company_account || m.payment_source || '—'}</div>
                            <div class="text-[10px] text-slate-500">${m.payment_source ? m.payment_source.toUpperCase() : ''}</div>
                        </td>
                        <td class="p-3 text-slate-600 max-w-[12rem] truncate">
                            <div class="font-medium text-slate-900 truncate">${m.movement_reference || m.funding_reference || '—'}</div>
                            ${m.funding_description ? `<div class="text-[10px] text-slate-500 truncate">${m.funding_description}</div>` : ''}
                        </td>
                        <td class="p-3 text-slate-600 whitespace-nowrap font-medium">${m.created_by_name || 'System / Admin'}</td>
                        <td class="p-3">${statusBadge}</td>
                        <td class="p-3 text-right whitespace-nowrap">${actions}</td>
                    </tr>
                `;
            };

            const renderMobileCard = (m) => {
                const isGiven = m.type === 'in';
                const isRet = m.type === 'out' && !m.purchase_invoice_id;
                const isUsed = m.type === 'out' && m.purchase_invoice_id;
                const formattedAmt = Number(m.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const dirBadge = isGiven
                    ? '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-800"><i data-lucide="arrow-down-right" class="h-3 w-3"></i> Company → Purchaser</span>'
                    : (isRet
                        ? '<span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[9px] font-black uppercase text-indigo-800"><i data-lucide="arrow-up-left" class="h-3 w-3"></i> Purchaser → Company</span>'
                        : '<span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[9px] font-black uppercase text-slate-700">Cash Spend</span>');
                const colorClass = isGiven ? 'text-emerald-700' : (isRet ? 'text-indigo-700' : 'text-slate-900');
                const sign = isGiven ? '+' : '-';

                return `
                    <article class="p-4 space-y-2.5 text-xs">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                ${dirBadge}
                                <div class="mt-1 font-mono text-[11px] text-slate-500">${m.business_date}</div>
                            </div>
                            <strong class="font-mono text-base font-black ${colorClass}">
                                ${sign}₹${formattedAmt}
                            </strong>
                        </div>
                        <div class="text-slate-600 bg-slate-50 p-2 rounded-lg border border-slate-100">
                            <div>Account: <strong class="text-slate-800">${m.company_account || m.payment_source || '—'}</strong></div>
                            <div>Ref: <span class="font-mono text-slate-700">${m.movement_reference || m.funding_reference || '—'}</span></div>
                        </div>
                        <div class="flex items-center justify-end gap-1.5 pt-1">
                            <button type="button" onclick='openMovementDetailsModal(${JSON.stringify(m).replace(/'/g, "&apos;")})' class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700">Details</button>
                            ${!isUsed && !m.funding_action_blocked ? `
                                <button type="button" onclick="openEditFundingModal(${m.id}, '{{ $record->public_uuid }}', '${m.business_date}', ${Number(m.amount)}, '${m.payment_source || 'Bank'}', '${m.company_account_id || ''}', '${(m.funding_reference || m.movement_reference || '').replace(/'/g, "\\'")}', '${(m.funding_description || '').replace(/'/g, "\\'")}', '${m.status}', '${m.type}')" class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700">Edit</button>
                                <button type="button" onclick="openDeleteFundingModal(${m.id}, '{{ $record->public_uuid }}', '${m.business_date}', ${Number(m.amount)}, '${(m.company_account || m.payment_source || '—').replace(/'/g, "\\'")}', '${m.status}', '${m.type}')" class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700">Delete</button>
                            ` : ''}
                        </div>
                    </article>
                `;
            };

            if (tableBody) tableBody.innerHTML = records.map(renderRow).join('');
            if (mobileList) mobileList.innerHTML = records.map(renderMobileCard).join('');
            if (window.lucide) lucide.createIcons();
        }

        function updateMovementFormLabels(direction) {
            const submitBtn = document.getElementById('btnRecordMovementSubmit');
            if (!submitBtn) return;
            if (direction === 'purchaser_to_company') {
                submitBtn.innerHTML = '<i data-lucide="plus-circle" class="h-4 w-4"></i> Record Purchaser Return';
                submitBtn.className = 'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg bg-indigo-700 px-5 text-xs font-black text-white hover:bg-indigo-600 transition shadow-sm';
            } else {
                submitBtn.innerHTML = '<i data-lucide="plus-circle" class="h-4 w-4"></i> Record Company Funding';
                submitBtn.className = 'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-5 text-xs font-black text-white hover:bg-emerald-600 transition shadow-sm';
            }
            if (window.lucide) lucide.createIcons();
        }

        function openMovementDetailsModal(movement) {
            const modal = document.getElementById('movementDetailsModal');
            if (!modal || !movement) return;

            const isFundingGiven = movement.type === 'in';
            const isReturn = movement.type === 'out' && !movement.purchase_invoice_id;
            const formattedAmount = Number(movement.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            const formattedBalance = Number(movement.running_balance || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            const directionBadge = document.getElementById('detailDirectionBadge');
            if (isFundingGiven) {
                directionBadge.className = 'rounded-full bg-emerald-100 border border-emerald-300 text-emerald-900 px-2.5 py-1 text-[10px] font-black uppercase';
                directionBadge.textContent = 'Company → Purchaser (Given)';
            } else if (isReturn) {
                directionBadge.className = 'rounded-full bg-indigo-100 border border-indigo-300 text-indigo-900 px-2.5 py-1 text-[10px] font-black uppercase';
                directionBadge.textContent = 'Purchaser → Company (Returned)';
            } else {
                directionBadge.className = 'rounded-full bg-slate-100 border border-slate-300 text-slate-800 px-2.5 py-1 text-[10px] font-black uppercase';
                directionBadge.textContent = 'Cash Purchase Spend';
            }

            const statusBadge = document.getElementById('detailStatusBadge');
            if (movement.status === 'unmatched') {
                statusBadge.className = 'rounded-full bg-amber-100 border border-amber-300 text-amber-900 px-2 py-0.5 text-[9px] font-black uppercase';
                statusBadge.textContent = 'UNMATCHED';
            } else if (['matched', 'manual_cash', 'manual_statement'].includes(movement.status)) {
                statusBadge.className = 'rounded-full bg-emerald-100 border border-emerald-300 text-emerald-900 px-2 py-0.5 text-[9px] font-black uppercase';
                statusBadge.textContent = '✓ ' + movement.status.replace('_', ' ').toUpperCase();
            } else {
                statusBadge.className = 'rounded-full bg-slate-100 text-slate-700 px-2 py-0.5 text-[9px] font-black uppercase';
                statusBadge.textContent = (movement.status || 'RECORDED').replace('_', ' ').toUpperCase();
            }

            const amountEl = document.getElementById('detailAmount');
            amountEl.textContent = (isFundingGiven ? '+' : '-') + '₹' + formattedAmount;
            amountEl.className = 'font-mono text-2xl font-black ' + (isFundingGiven ? 'text-emerald-700' : (isReturn ? 'text-indigo-700' : 'text-slate-900'));

            document.getElementById('detailRunningBalance').textContent = '₹' + formattedBalance;
            document.getElementById('detailBusinessDate').textContent = movement.business_date || '—';
            document.getElementById('detailRecordedAt').textContent = movement.created_at ? movement.created_at.substring(0, 16).replace('T', ' ') : '—';
            document.getElementById('detailCreatedBy').textContent = movement.created_by_name || 'System / Admin';
            document.getElementById('detailAccount').textContent = (movement.company_account || '—') + (movement.payment_source ? ' (' + movement.payment_source + ')' : '');
            document.getElementById('detailReference').textContent = movement.movement_reference || movement.funding_reference || '—';
            document.getElementById('detailDescription').textContent = movement.funding_description || '—';

            const reconSection = document.getElementById('detailReconciliationSection');
            if (movement.statement_entry_id && ['matched', 'manual_cash', 'manual_statement'].includes(movement.status)) {
                reconSection.classList.remove('hidden');
                document.getElementById('detailStmtAccount').textContent = movement.statement_account_name || '—';
                document.getElementById('detailStmtDate').textContent = movement.statement_date || '—';
                document.getElementById('detailStmtAmount').textContent = '₹' + Number(movement.statement_amount || movement.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('detailStmtRef').textContent = movement.statement_reference || '—';
                document.getElementById('detailStmtNarration').textContent = movement.statement_narration || '—';
                document.getElementById('detailReconciledBy').textContent = movement.reconciled_by_name || '—';
                document.getElementById('detailReconciledAt').textContent = movement.reconciled_at || '—';
            } else {
                reconSection.classList.add('hidden');
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            if (window.lucide) lucide.createIcons();
        }

        function closeMovementDetailsModal() {
            const modal = document.getElementById('movementDetailsModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function openEditFundingModal(creditId, purchaserUuid, date, amount, source, accountId, ref, desc, status, type) {
            const modal = document.getElementById('editFundingModal');
            if (!modal) return;
            const amountInput = document.getElementById('editFundingAmount');
            const dateInput = document.getElementById('editFundingDate');
            const sourceSelect = document.getElementById('editFundingSource');
            const accountSelect = document.getElementById('editFundingAccount');
            const titleEl = modal.querySelector('h3');

            if (titleEl) {
                titleEl.textContent = type === 'out' ? 'Edit Purchaser Return' : 'Edit Purchaser Funding';
            }

            amountInput.value = Number(amount).toFixed(2);
            dateInput.value = date;
            sourceSelect.value = source || 'Bank';
            accountSelect.value = accountId || '';
            document.getElementById('editFundingReference').value = ref || '';
            document.getElementById('editFundingDescription').value = desc || '';

            const warningEl = document.getElementById('editFundingMatchedWarning');
            const isReconciled = ['matched', 'manual_cash', 'manual_statement'].includes(status);
            if (isReconciled && warningEl) {
                warningEl.classList.remove('hidden');
            } else if (warningEl) {
                warningEl.classList.add('hidden');
            }

            const form = document.getElementById('editFundingForm');
            form.action = `/admin/cashbook/finance/purchasers/${purchaserUuid}/funding/${creditId}/update`;
            form.dataset.movementType = type === 'out' ? 'return' : 'funding';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            if (window.lucide) lucide.createIcons();
        }

        function closeEditFundingModal() {
            const modal = document.getElementById('editFundingModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function confirmEditFundingSave() {
            const form = document.getElementById('editFundingForm');
            const amountVal = document.getElementById('editFundingAmount').value;
            const formattedAmount = Number(amountVal || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            const label = form.dataset.movementType === 'return' ? 'purchaser return' : 'purchaser funding';
            return confirm(`Save changes to ₹${formattedAmount} ${label}?`);
        }

        function openDeleteFundingModal(creditId, purchaserUuid, date, amount, account, status, type) {
            const modal = document.getElementById('deleteFundingModal');
            const formattedAmount = Number(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            const label = type === 'out' ? 'purchaser return' : 'purchaser funding';
            document.getElementById('deleteFundingTitle').textContent = `Delete ₹${formattedAmount} ${label}?`;
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
            form.dataset.movementType = label;

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
            const label = form.dataset.movementType || 'movement';
            return confirm(
                `Delete ₹${form.dataset.confirmAmount || '0.00'} ${label}?\n\n` +
                'This removes the transaction and its corresponding safe journal movement. Reconciled, used, or historical records will not be deleted.'
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
