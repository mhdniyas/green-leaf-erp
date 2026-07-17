@php
    $businessDayService = app(\App\Services\Purchasing\PurchaserBusinessDayService::class);
    $navDate = request('date', $businessDayService->operationalDate()->toDateString());
    $purchaseManagerNavItems = [];

    $purchaseManagerNavItems[] = [
        'label' => 'Dashboard',
        'route' => 'purchasing.dashboard',
        'active' => request()->routeIs('purchasing.dashboard') || request()->routeIs('purchasing.orders.index'),
    ];

    if (auth()->user()->hasRole('purchase') || auth()->user()->can('purchasing.order.approve')) {
        $purchaseManagerNavItems[] = ['label' => 'Approve Shop Orders', 'route' => 'requisitions.board', 'params' => ['date' => $navDate], 'active' => request()->routeIs('requisitions.board')];
        $purchaseManagerNavItems[] = ['label' => 'Approved Board', 'route' => 'requisitions.approved_board', 'params' => ['date' => $navDate], 'active' => request()->routeIs('requisitions.approved_board') && ! request()->boolean('settings')];
        $purchaseManagerNavItems[] = ['label' => 'Settings', 'route' => 'requisitions.approved_board', 'params' => ['date' => $navDate, 'settings' => 1], 'active' => request()->routeIs('requisitions.approved_board') && request()->boolean('settings')];
    }

    $purchaseManagerNavItems[] = ['label' => 'Purchase Orders', 'route' => 'purchasing.orders.index', 'active' => request()->routeIs('purchasing.orders.*') && ! request()->routeIs('purchasing.orders.index')];
    $purchaseManagerNavItems[] = ['label' => 'Goods Receipts', 'route' => 'purchasing.grns.index', 'active' => request()->routeIs('purchasing.grns.*')];
    $purchaseManagerNavItems[] = ['label' => 'Shop Daily Invoices', 'route' => 'purchasing.shop-invoices.index', 'active' => request()->routeIs('purchasing.shop-invoices.*')];
    $purchaseManagerNavItems[] = ['label' => 'Daily Prices', 'route' => 'purchasing.prices.index', 'active' => request()->routeIs('purchasing.prices.*')];
@endphp

<aside class="hidden border-r border-slate-200 bg-white text-slate-900 shadow-sm lg:fixed lg:inset-y-0 lg:flex lg:w-72 lg:flex-col">
    <div class="border-b border-slate-200 px-6 py-6">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-sky-700">Purchasing Dashboard</p>
        <h1 class="mt-2 text-xl font-black text-slate-950">{{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm font-semibold text-slate-500">Approvals, vendor buying, receipts, and invoice control.</p>
    </div>

    <nav class="flex-1 space-y-2 px-4 py-6">
        @foreach ($purchaseManagerNavItems as $item)
            <a
                href="{{ route($item['route'], $item['params'] ?? []) }}"
                @class([
                    'block rounded-2xl px-4 py-3 text-sm font-bold transition',
                    'bg-sky-50 text-sky-800 shadow-sm' => $item['active'],
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-950' => ! $item['active'],
                ])
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="border-t border-slate-200 px-4 py-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-bold text-slate-700 transition hover:bg-slate-100 hover:text-slate-950">
                Sign Out
            </button>
        </form>
    </div>
</aside>
