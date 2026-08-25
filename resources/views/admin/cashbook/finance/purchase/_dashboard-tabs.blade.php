@php
    $dashboardPeriod = $filters['period'] ?? 'month';
    $selectedProductFilter = $filters['product_filter'] ?? null;
    $dashboardContext = ['period' => $dashboardPeriod];
    if ($selectedProductFilter) {
        $dashboardContext['product_filter'] = $selectedProductFilter;
    }
    if (in_array($dashboardPeriod, ['custom', 'between', 'range'], true)) {
        $dashboardContext += ['start_date' => $filters['start_date'] ?? null, 'end_date' => $filters['end_date'] ?? null];
    }
    $dashboardTabs = [
        'overview' => ['Overview', 'admin.cashbook.finance.purchase'],
        'purchasers' => ['Purchasers', 'admin.cashbook.finance.purchase.purchasers'],
        'vendors' => ['Vendors', 'admin.cashbook.finance.purchase.vendors'],
        'categories' => ['Categories', 'admin.cashbook.finance.purchase.categories'],
        'invoices' => ['Invoices', 'admin.cashbook.finance.purchase.invoices'],
    ];
@endphp
<div class="overflow-x-auto pb-1">
    <nav class="flex min-w-max gap-2 text-xs font-black" aria-label="Purchase dashboard sections">
        @foreach($dashboardTabs as $tab => [$label, $routeName])
            <a href="{{ route($routeName, $dashboardContext) }}" class="rounded-lg border px-3 py-2 {{ $activePurchaseTab === $tab ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700' }}">{{ $label }}</a>
        @endforeach
    </nav>
</div>
