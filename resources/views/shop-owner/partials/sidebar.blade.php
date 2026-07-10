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

<div id="shop-owner-mobile-sidebar" class="fixed inset-0 z-50 hidden lg:hidden">
    <button
        type="button"
        id="shop-owner-mobile-sidebar-overlay"
        class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
        aria-label="Close shop owner sidebar"
    ></button>

    <aside class="relative flex h-full w-[18.5rem] max-w-[85vw] flex-col border-r border-white/10 bg-slate-950 text-white shadow-[0_24px_80px_rgba(15,23,42,0.55)]">
        <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-5">
            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-300">Shop Owner Portal</p>
                <h2 class="mt-2 truncate text-lg font-black">{{ auth()->user()->shop?->name ?? 'Green Leaf Traders' }}</h2>
                <p class="mt-1 text-xs font-semibold text-slate-400">Orders, deliveries, finance, and staff.</p>
            </div>

            <button
                type="button"
                id="shop-owner-mobile-sidebar-close"
                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10 hover:text-white"
                aria-label="Close shop owner sidebar"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-2 overflow-y-auto px-4 py-5">
            @foreach ($shopOwnerNavItems as $item)
                @php
                    $isActive = request()->routeIs($item['route'])
                        || ($item['route'] === 'shop-owner.orders.index' && request()->routeIs('shop-owner.orders.create', 'shop-owner.orders.show'))
                        || ($item['route'] === 'shop-owner.accounting.index' && request()->routeIs('shop-owner.accounting.*'));
                @endphp

                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'block rounded-2xl px-4 py-3.5 text-sm font-black transition',
                        'bg-emerald-500 text-slate-950 shadow-sm' => $isActive,
                        'text-slate-300 hover:bg-white/5 hover:text-white' => ! $isActive,
                    ])
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="border-t border-white/10 px-4 py-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-2xl border border-white/10 px-4 py-3 text-left text-sm font-black text-slate-300 transition hover:bg-white/5 hover:text-white">
                    Sign Out
                </button>
            </form>
        </div>
    </aside>
</div>
