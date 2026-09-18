@php
    $dashboardPeriod = $filters['period_mode'] ?? $filters['period'] ?? 'month';
    $selectedProductFilter = $filters['product_filter'] ?? null;
    $dashboardContext = [
        'month' => $filters['month'] ?? today('Asia/Kolkata')->format('Y-m'),
        'period_mode' => $dashboardPeriod,
    ];
    if ($selectedProductFilter) {
        $dashboardContext['product_filter'] = $selectedProductFilter;
    }
    if ($dashboardPeriod === 'day') {
        $dashboardContext['date'] = $filters['start_date'] ?? null;
    } elseif ($dashboardPeriod === 'custom') {
        $dashboardContext += [
            'from' => $filters['start_date'] ?? null,
            'to' => $filters['end_date'] ?? null,
        ];
    }
    $dashboardTabs = [
        'overview' => ['Overview', 'admin.cashbook.finance.purchase', 'layout-dashboard'],
        'purchasers' => ['Purchasers', 'admin.cashbook.finance.purchase.purchasers', 'users'],
        'vendors' => ['Vendors', 'admin.cashbook.finance.purchase.vendors', 'truck'],
        'categories' => ['Categories', 'admin.cashbook.finance.purchase.categories', 'tags'],
        'invoices' => ['Invoices', 'admin.cashbook.finance.purchase.invoices', 'receipt'],
    ];
@endphp
<div class="overflow-x-auto pb-1">
    <nav class="flex min-w-max gap-1.5 p-1 bg-slate-100 rounded-2xl border border-slate-200/80 w-fit" aria-label="Purchase dashboard sections">
        @foreach($dashboardTabs as $tab => [$label, $routeName, $icon])
            <a href="{{ route($routeName, $dashboardContext) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-black transition {{ $activePurchaseTab === $tab ? 'bg-emerald-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}">
                <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5"></i>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
</div>

