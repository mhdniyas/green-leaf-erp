@php
    $purchasePeriod = $filters['period'] ?? 'month';
    $purchaseWarehouse = $filters['warehouse_code'] ?? null;
    $purchaseContext = ['period' => $purchasePeriod, 'produce_type' => $purchaseWarehouse === 'VEG-WH' ? 'vegetables' : ($purchaseWarehouse === 'FRT-WH' ? 'fruits' : 'all')];
    if (in_array($purchasePeriod, ['custom', 'between', 'range'], true)) {
        $purchaseContext += ['start_date' => $filters['start_date'] ?? null, 'end_date' => $filters['end_date'] ?? null];
    }
    $purchaseTabs = [
        'overview' => ['Dashboard', 'admin.cashbook.finance.purchase'],
        'reports' => ['Reports', 'admin.cashbook.finance.purchase.reports'],
    ];
    $activeMainPurchaseTab = $activePurchaseTab === 'reports' ? 'reports' : 'overview';
@endphp
<div class="overflow-x-auto pb-1"><nav class="flex min-w-max gap-2 text-xs font-black" aria-label="Purchase sections">
    @foreach($purchaseTabs as $tab => [$label, $routeName])<a href="{{ route($routeName, $purchaseContext) }}" class="rounded-lg border px-3 py-2 {{ $activeMainPurchaseTab === $tab ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700' }}">{{ $label }}</a>@endforeach
</nav></div>
