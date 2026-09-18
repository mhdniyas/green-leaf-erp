@php
    $reportTabs = [
        ['Daily', 'admin.cashbook.finance.purchase.reports.daily', 'calendar'],
        ['Credit Purchase', 'admin.cashbook.finance.purchase.reports.credit-purchases', 'credit-card'],
        ['Purchaser Expenses', 'admin.cashbook.finance.purchase.purchaser-expenses', 'receipt'],
        ['Purchaser Overview', 'admin.cashbook.finance.purchase.reports.purchasers', 'users'],
        ['Price Report', 'admin.cashbook.finance.purchase.reports.prices', 'trending-up'],
        ['Changed Items', 'admin.cashbook.finance.purchase.reports.changed-items', 'refresh-cw'],
        ['Purchaser Prices', 'admin.cashbook.finance.purchase.reports.purchaser-prices', 'tag'],
        ['Product Allotments', 'admin.cashbook.finance.purchase.product-allotments.index', 'boxes'],
        ['Business Days', 'admin.cashbook.purchaser-business-days.index', 'clock'],
    ];
@endphp
<div class="overflow-x-auto pb-1">
    <nav class="flex min-w-max gap-1.5 p-1 bg-slate-100 rounded-2xl border border-slate-200/80 w-fit" aria-label="Purchase reports">
        @foreach($reportTabs as [$label, $routeName, $icon])
            @php
                $isActive = request()->routeIs($routeName) || (str_ends_with($routeName, '.prices') && request()->routeIs($routeName.'.product'));
            @endphp
            <a href="{{ route($routeName) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-black transition {{ $isActive ? 'bg-emerald-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}">
                <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5"></i>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
</div>
