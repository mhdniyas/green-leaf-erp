@php
    $purchaseManagerNavItems = [];

    if (auth()->user()->hasRole('purchase') || auth()->user()->can('purchasing.order.approve')) {
        $purchaseManagerNavItems[] = ['label' => 'Requisition Board', 'route' => 'requisitions.board', 'active' => request()->routeIs('requisitions.board')];
        $purchaseManagerNavItems[] = ['label' => 'Approved Board', 'route' => 'requisitions.approved_board', 'active' => request()->routeIs('requisitions.approved_board')];
    }

    $purchaseManagerNavItems[] = ['label' => 'Purchase Orders', 'route' => 'purchasing.orders.index', 'active' => request()->routeIs('purchasing.orders.*')];
    $purchaseManagerNavItems[] = ['label' => 'Goods Receipts', 'route' => 'purchasing.grns.index', 'active' => request()->routeIs('purchasing.grns.*')];
    $purchaseManagerNavItems[] = ['label' => 'Invoices', 'route' => 'purchasing.invoices.index', 'active' => request()->routeIs('purchasing.invoices.*')];
    $purchaseManagerNavItems[] = ['label' => 'Suppliers', 'route' => 'purchasing.suppliers.index', 'active' => request()->routeIs('purchasing.suppliers.*')];
    $purchaseManagerNavItems[] = ['label' => 'Daily Prices', 'route' => 'purchasing.prices.index', 'active' => request()->routeIs('purchasing.prices.*')];
@endphp

<aside class="hidden border-r border-slate-200 bg-slate-950 text-white lg:fixed lg:inset-y-0 lg:flex lg:w-72 lg:flex-col">
    <div class="border-b border-white/10 px-6 py-6">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-cyan-300">Purchase Manager</p>
        <h1 class="mt-2 text-xl font-black">{{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-slate-400">Requisition review first, then approvals, vendor buying, receipts, and invoice control.</p>
    </div>

    <nav class="flex-1 space-y-2 px-4 py-6">
        @foreach ($purchaseManagerNavItems as $item)
            <a
                href="{{ route($item['route']) }}"
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
