@php
    $purchasePeriod = $filters['period_mode'] ?? $filters['period'] ?? 'month';
    $selectedProductFilter = $filters['product_filter'] ?? null;
    $purchaseContext = [
        'month' => $filters['month'] ?? today('Asia/Kolkata')->format('Y-m'),
        'period_mode' => $purchasePeriod,
    ];
    if ($selectedProductFilter) {
        $purchaseContext['product_filter'] = $selectedProductFilter;
    }
    if ($purchasePeriod === 'day') {
        $purchaseContext['date'] = $filters['start_date'] ?? null;
    } elseif ($purchasePeriod === 'custom') {
        $purchaseContext += [
            'from' => $filters['start_date'] ?? null,
            'to' => $filters['end_date'] ?? null,
        ];
    }
    $purchaseTabs = [
        'overview' => ['Purchase Dashboard', 'admin.cashbook.finance.purchase', 'shopping-basket'],
        'reports' => ['Purchase Reports', 'admin.cashbook.finance.purchase.reports', 'file-spreadsheet'],
    ];
    $activeMainPurchaseTab = $activePurchaseTab === 'reports' ? 'reports' : 'overview';
@endphp
<div class="overflow-x-auto pb-1">
    <nav class="flex min-w-max gap-1.5 p-1 bg-slate-200/70 rounded-2xl border border-slate-300/60 w-fit" aria-label="Purchase sections">
        @foreach($purchaseTabs as $tab => [$label, $routeName, $icon])
            <a href="{{ route($routeName, $purchaseContext) }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition {{ $activeMainPurchaseTab === $tab ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-700 hover:text-slate-950 hover:bg-white/60' }}">
                <i data-lucide="{{ $icon }}" class="w-4 h-4"></i>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
</div>

