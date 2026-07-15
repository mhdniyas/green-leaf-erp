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

<aside class="hidden border-r border-slate-200 bg-slate-950 text-white lg:fixed lg:inset-y-0 lg:flex lg:w-72 lg:flex-col">
    <div class="border-b border-white/10 px-6 py-6">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-cyan-300">Purchasing Dashboard</p>
        <h1 class="mt-2 text-xl font-black">{{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-slate-400">Requisition review first, then approvals, vendor buying, receipts, and invoice control.</p>
    </div>

    <nav class="flex-1 space-y-2 px-4 py-6">
        @foreach ($purchaseManagerNavItems as $item)
            <a
                href="{{ route($item['route'], $item['params'] ?? []) }}"
                @class([
                    'block rounded-2xl px-4 py-3 text-sm font-bold transition',
                    'bg-cyan-400 text-slate-950 shadow-sm' => $item['active'],
                    'text-slate-300 hover:bg-white/5 hover:text-white' => ! $item['active'],
                ])
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="border-t border-white/10 px-4 py-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-2xl border border-white/10 px-4 py-3 text-left text-sm font-bold text-slate-300 transition hover:bg-white/5 hover:text-white">
                Sign Out
            </button>
        </form>
    </div>
</aside>
