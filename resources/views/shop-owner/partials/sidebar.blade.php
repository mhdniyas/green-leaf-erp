@php
    $hasOwnedShopStaffAccess = auth()->user()?->ownedShopAssignments()->exists() ?? false;
    $shopOwnerNavItems = [
        ['label' => 'Dashboard', 'route' => 'shop-owner.dashboard'],
        ['label' => 'Cart', 'route' => 'shop-owner.orders.index'],
        ['label' => 'Deliveries', 'route' => 'shop-owner.deliveries.index'],
        ['label' => 'Accounting', 'route' => 'shop-owner.accounting.index'],
        ['label' => 'Finance', 'route' => 'shop-owner.finance.index'],
        ['label' => 'Approval History', 'route' => 'shop-owner.orders.history'],
    ];

    if ($hasOwnedShopStaffAccess) {
        $shopOwnerNavItems[] = ['label' => 'Staff', 'route' => 'shop-owner.staff.index'];
    }
@endphp

<aside class="hidden border-r border-slate-200 bg-slate-950 text-white lg:fixed lg:inset-y-0 lg:flex lg:w-72 lg:flex-col">
    <div class="border-b border-white/10 px-6 py-6">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-emerald-300">Shop Owner Portal</p>
        <h1 class="mt-2 text-xl font-black">{{ auth()->user()->shop?->name ?? 'Green Leaf Traders' }}</h1>
        <p class="mt-1 text-sm text-slate-400">Marketplace ordering, delivery follow-up, and finance tracking.</p>
    </div>

    <nav class="flex-1 space-y-2 px-4 py-6">
        @foreach ($shopOwnerNavItems as $item)
            <a
                href="{{ route($item['route']) }}"
                @class([
                    'block rounded-2xl px-4 py-3 text-sm font-bold transition',
                    'bg-emerald-500 text-slate-950 shadow-sm' => request()->routeIs($item['route'])
                        || ($item['route'] === 'shop-owner.orders.index' && request()->routeIs('shop-owner.orders.create', 'shop-owner.orders.show'))
                        || ($item['route'] === 'shop-owner.accounting.index' && request()->routeIs('shop-owner.accounting.*')),
                    'text-slate-300 hover:bg-white/5 hover:text-white' => ! (
                        request()->routeIs($item['route'])
                        || ($item['route'] === 'shop-owner.orders.index' && request()->routeIs('shop-owner.orders.create', 'shop-owner.orders.show'))
                        || ($item['route'] === 'shop-owner.accounting.index' && request()->routeIs('shop-owner.accounting.*'))
                    ),
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
