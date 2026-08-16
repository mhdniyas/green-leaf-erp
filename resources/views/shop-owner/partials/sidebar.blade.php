@php
    $activeShopResolver = app(\App\Support\ShopOwner\ActiveShopResolver::class);
    $authorizedShops = $activeShopResolver->authorizedShops(auth()->user());
    $activeShop = $authorizedShops->isNotEmpty() ? $activeShopResolver->resolve(request()) : auth()->user()?->shop;
    $cashbookShopId = (int) ($activeShop?->id ?? ($authorizedShops->first()?->id ?? 1));
    $hasOwnedShopStaffAccess = auth()->user()?->ownedShopAssignments()->exists() ?? false;
    $hasOwnedAccountingAccess = $activeShop?->isOwnedAccountingEnabled() ?? false;
    $accountingChildren = [
        [
            'label' => 'Bills',
            'href' => route('shop-owner.finance.index', ['tab' => 'invoices']),
            'active' => request()->routeIs('shop-owner.finance.index') && request()->query('tab', 'invoices') === 'invoices',
        ],
    ];

    if ($hasOwnedAccountingAccess) {
        $accountingChildren[] = [
            'label' => 'Cashbook',
            'href' => route('shop-owner.cashbook.show'),
            'active' => request()->routeIs('shop-owner.cashbook.show'),
        ];
        $accountingChildren[] = [
            'label' => 'Create',
            'href' => route('shop-owner.cashbook.create'),
            'active' => request()->routeIs('shop-owner.cashbook.create') || (request()->routeIs('shop-owner.cashbook.show') && request()->query('open') === 'line'),
        ];
    }

    $accountingChildren[] = [
        'label' => 'Reports',
        'href' => route('shop-owner.cashbook.reports'),
        'active' => request()->routeIs('shop-owner.cashbook.reports') || (request()->routeIs('shop-owner.cashbook.show') && request()->query('tab') === 'reports'),
    ];
    $financeChildren = [
        [
            'label' => 'Invoices',
            'href' => route('shop-owner.finance.index', ['tab' => 'invoices']),
            'active' => request()->routeIs('shop-owner.finance.index') && request()->query('tab', 'invoices') === 'invoices',
        ],
    ];

    $shopOwnerNavItems = [
        [
            'label' => 'Dashboard',
            'route' => 'shop-owner.dashboard',
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h7.5v7.5h-7.5v-7.5Zm9 0h7.5v7.5h-7.5v-7.5Zm-9 9h7.5v7.5h-7.5v-7.5Zm9 3h7.5v4.5h-7.5v-4.5Z" /></svg>',
        ],
        [
            'label' => 'Products',
            'route' => 'shop-owner.products.index',
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>',
        ],
        [
            'label' => 'Cart',
            'route' => 'shop-owner.orders.index',
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272" /></svg>',
        ],
        [
            'label' => 'Deliveries',
            'route' => 'shop-owner.deliveries.index',
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h7.5m3 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.5V9.75a1.5 1.5 0 0 0-1.5-1.5h-3L14.25 5.25H5.25v13.5h0" /></svg>',
        ],
        [
            'label' => 'Accounting',
            'route' => 'shop-owner.cashbook.show',
            'href' => route($hasOwnedAccountingAccess ? 'shop-owner.cashbook.show' : 'shop-owner.finance.index', $hasOwnedAccountingAccess ? [] : ['tab' => 'invoices']),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m0 0c-2.21 0-4-1.343-4-3s1.79-3 4-3 4-1.343 4-3-1.79-3-4-3m0 12c2.21 0 4-1.343 4-3" /></svg>',
            'children' => $accountingChildren,
        ],
        [
            'label' => 'Finance',
            'route' => 'shop-owner.finance.index',
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5m-18 3.75h16.5m-14.25 5.25h4.5m-6.75 2.25h16.5A2.25 2.25 0 0 0 22.5 17.25V6.75A2.25 2.25 0 0 0 20.25 4.5H3.75A2.25 2.25 0 0 0 1.5 6.75v10.5A2.25 2.25 0 0 0 3.75 19.5Z" /></svg>',
            'children' => $financeChildren,
        ],
        [
            'label' => 'Payments',
            'route' => 'shop-owner.payments.index',
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m0 0c-2.21 0-4-1.343-4-3s1.79-3 4-3 4-1.343 4-3-1.79-3-4-3m0 12c2.21 0 4-1.343 4-3" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5h15" /></svg>',
        ],
        [
            'label' => 'Approval History',
            'route' => 'shop-owner.orders.history',
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
        ],
    ];

    if ($hasOwnedShopStaffAccess) {
        $shopOwnerNavItems[] = [
            'label' => 'Staff',
            'route' => 'shop-owner.staff.index',
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a8.97 8.97 0 0 0 3.74-1.04 4.5 4.5 0 0 0-7.48-2.23m3.74 3.27v.28A10.94 10.94 0 0 1 12 21c-2.33 0-4.5-.73-6.28-1.98v-.29m12.56 0a5.97 5.97 0 0 0-12.56 0M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>',
        ];
    }
@endphp

<aside id="shop-owner-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-slate-100 text-slate-900 shadow-sm transition-transform duration-300 lg:translate-x-0">
    <div class="border-b border-slate-200 px-6 py-6">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-emerald-700">Shop Owner Portal</p>
                <h1 class="mt-2 truncate text-xl font-black text-slate-950">{{ $activeShop?->name ?? 'Green Leaf Traders' }}</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">Orders, deliveries, finance, and staff.</p>
            </div>

            <button
                type="button"
                id="shop-owner-mobile-sidebar-close"
                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-800 lg:hidden"
                aria-label="Close shop owner sidebar"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <nav class="flex-1 space-y-2 overflow-y-auto px-4 py-6">
        @foreach ($shopOwnerNavItems as $item)
            @php
                $isActive = request()->routeIs($item['route'])
                    || ($item['route'] === 'shop-owner.orders.index' && request()->routeIs('shop-owner.orders.create', 'shop-owner.orders.show'))
                    || ($item['route'] === 'shop-owner.cashbook.show' && request()->routeIs('shop-owner.cashbook.*'));

                $sidebarItem = [
                    'label' => $item['label'],
                    'href' => $item['href'] ?? route($item['route']),
                    'active' => $isActive,
                    'icon' => $item['icon'],
                    'children' => $item['children'] ?? [],
                ];
            @endphp

            <x-sidebar-link :item="$sidebarItem" />
        @endforeach
    </nav>

    <div class="border-t border-slate-200 px-4 py-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-black text-slate-700 transition hover:bg-slate-50 hover:text-slate-950">
                Sign Out
            </button>
        </form>
    </div>
</aside>

<button
    type="button"
    id="shop-owner-mobile-sidebar-overlay"
    class="fixed inset-0 z-40 hidden bg-slate-950/45 lg:hidden"
    aria-label="Close shop owner sidebar"
></button>
