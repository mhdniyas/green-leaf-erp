@php
    $reportTabs = [
        ['Credit Purchase Report', 'admin.cashbook.finance.purchase.reports.credit-purchases'],
        ['Purchaser Report', 'admin.cashbook.finance.purchase.reports.purchasers'],
        ['Price Report', 'admin.cashbook.finance.purchase.reports.prices'],
        ['Changed Items', 'admin.cashbook.finance.purchase.reports.changed-items'],
        ['Purchaser Price', 'admin.cashbook.finance.purchase.reports.purchaser-prices'],
    ];
@endphp
<div class="overflow-x-auto pb-1">
    <nav class="flex min-w-max gap-2 text-xs font-black" aria-label="Purchase reports">
        @foreach($reportTabs as [$label, $routeName])
            <a href="{{ route($routeName) }}" class="rounded-lg border px-3 py-2 {{ request()->routeIs($routeName) || (str_ends_with($routeName, '.prices') && request()->routeIs($routeName.'.product')) ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700' }}">{{ $label }}</a>
        @endforeach
    </nav>
</div>
