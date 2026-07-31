@php
    $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $purchaseManagerAssets = app()->runningUnitTests()
        ? ['resources/css/app.css', 'resources/js/app.js']
        : ['resources/css/purchase-manager/app.css', 'resources/js/purchase-manager/app.js'];

    $currentUser = auth()->user();
    $businessDayService = app(\App\Services\Purchasing\PurchaserBusinessDayService::class);
    $navDate = request('date', $businessDayService->operationalDate()->toDateString());
    $notificationCounts = app(\App\Services\DashboardNotificationService::class)->counts(\Illuminate\Support\Carbon::parse($navDate));
    $canAccessAdminOverview = $currentUser &&
        ($currentUser->hasRole('admin') ||
            $currentUser->can('admin.user.view') ||
            $currentUser->can('admin.daily-progress.view') ||
            $currentUser->can('admin.activity-log.view'));

    $sidebarItems = [
        [
            'label' => 'Dashboard',
            'href' => route('purchasing.dashboard'),
            'active' => request()->routeIs('purchasing.dashboard') || request()->routeIs('purchasing.orders.index'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h7.5v7.5h-7.5v-7.5Zm9 0h7.5v10.5h-7.5V3.75Zm0 12h7.5v4.5h-7.5v-4.5Zm-9-3h7.5v7.5h-7.5v-7.5Z" /></svg>',
            'badge' => $notificationCounts['purchasing_total'],
        ],
    ];

    if ($currentUser && ($currentUser->hasRole('admin') || $currentUser->hasRole('purchase') || $currentUser->can('purchasing.order.approve'))) {
        $sidebarItems[] = [
            'label' => 'Approve Shop Orders',
            'href' => route('requisitions.board', ['date' => $navDate]),
            'active' => request()->routeIs('requisitions.board'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6M9 9.75h6M9 14.25h6M5.25 5.25h.008v.008H5.25V5.25zm0 4.5h.008v.008H5.25V9.75zm0 4.5h.008v.008H5.25V14.25zm-1.5-9A2.25 2.25 0 016 3h12a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25V5.25z" /></svg>',
            'badge' => $notificationCounts['shop_orders_pending'],
        ];
        $sidebarItems[] = [
            'label' => 'Edit Shop Orders',
            'href' => route('purchasing.shop-orders.index', ['date' => $navDate]),
            'active' => request()->routeIs('purchasing.shop-orders.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>',
        ];
        $sidebarItems[] = [
            'label' => 'Approved Board',
            'href' => route('requisitions.approved_board', ['date' => $navDate]),
            'active' => request()->routeIs('requisitions.approved_board') && ! request()->boolean('settings'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 11-18 0 9 9 0 0118 0Z" /></svg>',
        ];
        $sidebarItems[] = [
            'label' => 'Settings',
            'href' => route('requisitions.approved_board', ['date' => $navDate, 'settings' => 1]),
            'active' => request()->routeIs('requisitions.approved_board') && request()->boolean('settings'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.094c.55 0 1.02.398 1.11.94l.149.894c.07.424.349.78.746.944.397.164.85.104 1.198-.148l.735-.535a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.535.735c-.252.348-.312.801-.148 1.198.164.397.52.676.944.746l.894.149c.542.09.94.56.94 1.11v1.094c0 .55-.398 1.02-.94 1.11l-.894.149c-.424.07-.78.349-.944.746-.164.397-.104.85.148 1.198l.535.735c.32.448.27 1.061-.12 1.45l-.773.774a1.125 1.125 0 0 1-1.45.12l-.735-.535c-.348-.252-.801-.312-1.198-.148-.397.164-.676.52-.746.944l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.02-.398-1.11-.94l-.149-.894c-.07-.424-.349-.78-.746-.944-.397-.164-.85-.104-1.198.148l-.735.535a1.125 1.125 0 0 1-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.535-.735c.252-.348.312-.801.148-1.198-.164-.397-.52-.676-.944-.746l-.894-.149a1.125 1.125 0 0 1-.94-1.11v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.78-.349.944-.746.164-.397.104-.85-.148-1.198l-.535-.735a1.125 1.125 0 0 1 .12-1.45l.773-.774a1.125 1.125 0 0 1 1.45-.12l.735.535c.348.252.801.312 1.198.148.397-.164.676-.52.746-.944l.149-.894Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>',
        ];
    }

    $sidebarItems[] = [
        'label' => 'Daily Price Board',
        'href' => route('purchasing.prices.index', ['date' => $navDate]),
        'active' => request()->routeIs('purchasing.prices.*'),
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m3.75-9.75h-6a2.25 2.25 0 100 4.5h4.5a2.25 2.25 0 110 4.5h-6" /></svg>',
    ];
    $sidebarItems[] = [
        'label' => 'Shop Price Categories',
        'href' => route('purchasing.price-groups.index'),
        'active' => request()->routeIs('purchasing.price-groups.*'),
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15m-15 5.25h15m-15 5.25h15" /></svg>',
    ];
    $sidebarItems[] = [
        'label' => 'Shop Daily Invoices',
        'href' => route('purchasing.shop-invoices.index'),
        'active' => request()->routeIs('purchasing.shop-invoices.*'),
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h9A2.25 2.25 0 0118.75 6v12A2.25 2.25 0 0116.5 20.25h-9A2.25 2.25 0 015.25 18V6A2.25 2.25 0 017.5 3.75Zm2.25 4.5h4.5m-4.5 3h4.5m-4.5 3h3" /></svg>',
        'badge' => $notificationCounts['delivery_reviews_pending'],
        'badge_tone' => 'danger',
    ];
    $sidebarItems[] = [
        'label' => 'Purchase Orders',
        'href' => route('purchasing.orders.index'),
        'active' => request()->routeIs('purchasing.orders.*') && ! request()->routeIs('purchasing.orders.index'),
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>',
    ];
    $sidebarItems[] = [
        'label' => 'Goods Receipts',
        'href' => route('purchasing.grns.index', ['date' => $navDate]),
        'active' => request()->routeIs('purchasing.grns.*'),
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3.75A.75.75 0 013.75 3h10.5a.75.75 0 01.53.22l5 5a.75.75 0 01.22.53v11.5a.75.75 0 01-.75.75H3.75a.75.75 0 01-.75-.75V3.75z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 12h7.5m-7.5 3h4.5" /></svg>',
        'badge' => $notificationCounts['grn_approvals_pending'],
    ];
    $sidebarItems[] = [
        'label' => 'Supplier Bills',
        'href' => route('purchasing.invoices.index'),
        'active' => request()->routeIs('purchasing.invoices.*'),
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h9A2.25 2.25 0 0118.75 6v12A2.25 2.25 0 0116.5 20.25h-9A2.25 2.25 0 015.25 18V6A2.25 2.25 0 017.5 3.75Zm2.25 4.5h4.5m-4.5 3h4.5m-4.5 3h4.5" /></svg>',
        'badge' => $notificationCounts['supplier_invoices_pending'],
    ];

    $mobileItems = [
        ['label' => 'Home', 'href' => route('purchasing.dashboard'), 'active' => request()->routeIs('purchasing.dashboard') || request()->routeIs('purchasing.orders.index')],
        ['label' => 'Board', 'href' => route('requisitions.board', ['date' => $navDate]), 'active' => request()->routeIs('requisitions.board') || (request()->routeIs('requisitions.approved_board') && ! request()->boolean('settings'))],
        ['label' => 'Settings', 'href' => route('requisitions.approved_board', ['date' => $navDate, 'settings' => 1]), 'active' => request()->routeIs('requisitions.approved_board') && request()->boolean('settings')],
        ['label' => 'Orders', 'href' => route('purchasing.orders.index'), 'active' => request()->routeIs('purchasing.orders.*') && ! request()->routeIs('purchasing.orders.index')],
        ['label' => 'Bills', 'href' => route('purchasing.invoices.index'), 'active' => request()->routeIs('purchasing.invoices.*')],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ trim($__env->yieldContent('title', 'Purchasing Dashboard')) }} — Green Leaf Traders</title>
    <meta name="description" content="Green Leaf Traders — Purchasing Dashboard">
    <script>
        localStorage.setItem('theme', 'light');
        document.documentElement.classList.remove('dark');
    </script>
    @vite($purchaseManagerAssets)
    @stack('styles')
</head>
<body class="min-h-full bg-slate-100 font-sans antialiased text-slate-900">
<div id="purchasing-layout-shell" class="min-h-screen lg:flex" data-sidebar-state="expanded">
    <aside id="purchasing-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-slate-100 transition-[width,transform] duration-300 lg:translate-x-0">
        <div class="border-b border-slate-200 px-5 py-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-500 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p data-purchasing-sidebar-label class="truncate text-base font-black text-slate-950">Purchasing</p>
                    <p data-purchasing-sidebar-label class="mt-1 text-[11px] font-black uppercase tracking-[0.2em] text-sky-700">Purchase Manager Desk</p>
                </div>
                <button id="purchasing-sidebar-collapse" type="button" class="hidden rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:inline-flex" aria-label="Collapse purchasing sidebar" title="Collapse sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <button id="purchasing-sidebar-close" class="ml-auto rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            @if ($canAccessAdminOverview)
                <a href="{{ route('admin.overview') }}" class="mt-5 flex items-center justify-between rounded-[1.35rem] bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800">
                    <span data-purchasing-sidebar-label>Admin Panel</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5H19.5V10.5M10.5 13.5 19.5 4.5M18 13.5V19.5H4.5V6H10.5" />
                    </svg>
                </a>
            @endif

            <div class="mt-4 rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Signed In</p>
                <p data-purchasing-sidebar-label class="mt-2 truncate text-sm font-black text-slate-950">{{ $currentUser?->name }}</p>
                <p data-purchasing-sidebar-label class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $currentUser?->email }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-2 overflow-y-auto px-4 py-5">
            @foreach ($sidebarItems as $item)
                <x-sidebar-link :item="$item" label-attribute="data-purchasing-sidebar-label" />
            @endforeach
        </nav>

        <div class="border-t border-slate-200 px-4 py-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-100">
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    <div id="purchasing-sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/45 lg:hidden"></div>

    <div id="purchasing-main" class="flex min-h-screen flex-1 flex-col lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-md">
            <div class="flex items-center gap-3 px-4 py-5 sm:px-6 lg:px-8">
                <button id="purchasing-sidebar-open" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <button id="purchasing-sidebar-toggle" type="button" class="hidden h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:bg-slate-100 lg:inline-flex" aria-label="Toggle purchasing sidebar" title="Toggle sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-[0.32em] text-slate-400">Green Leaf Traders</p>
                    <h1 class="mt-1 truncate text-2xl font-black tracking-[-0.04em] text-slate-950 sm:text-[2rem]">{{ trim($__env->yieldContent('title', 'Purchasing Dashboard')) }}</h1>
                </div>

                <div class="hidden rounded-[1.35rem] border border-slate-200 bg-slate-50/90 px-5 py-3 text-right shadow-sm sm:block">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Today</p>
                    <p class="mt-1 text-sm font-black text-slate-950">{{ today()->format('d M Y') }}</p>
                </div>
            </div>

            <div class="border-t border-slate-100 px-4 py-4 sm:px-6 lg:px-8">
                @include('components.admin-dashboard-switcher')
            </div>
        </header>

        <main class="flex-1 px-4 pb-24 pt-5 sm:px-6 lg:px-8 lg:pb-8 lg:pt-6">
            @if (session('success'))
                <div class="mb-4 rounded-[1.35rem] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-[1.35rem] border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if ($viewErrors->any())
                <div class="mb-4 rounded-[1.35rem] border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                    {{ $viewErrors->first() }}
                </div>
            @endif

            @include('purchase-manager.partials.breadcrumbs')
            @include('purchase-manager.partials.page-header')

            @yield('content')
        </main>
    </div>
</div>

<x-global-footer />

<div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 pb-[max(env(safe-area-inset-bottom),0.5rem)] pt-2 backdrop-blur lg:hidden">
    <nav class="mx-auto grid max-w-xl grid-cols-4 gap-2">
        @foreach ($mobileItems as $item)
            <a href="{{ $item['href'] }}" class="flex min-h-[52px] items-center justify-center rounded-2xl px-2 text-center text-[11px] font-black uppercase tracking-[0.12em] transition {{ $item['active'] ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</div>

@include('components.app-dialogs')

<x-sidebar-state-script
    storage-key="purchasing-sidebar-state"
    shell-id="purchasing-layout-shell"
    sidebar-id="purchasing-sidebar"
    main-id="purchasing-main"
    overlay-id="purchasing-sidebar-overlay"
    open-button-id="purchasing-sidebar-open"
    close-button-id="purchasing-sidebar-close"
    collapse-button-id="purchasing-sidebar-collapse"
    toggle-button-id="purchasing-sidebar-toggle"
    label-selector="[data-purchasing-sidebar-label]"
/>
@stack('scripts')
</body>
</html>
