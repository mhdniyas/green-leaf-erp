@props(['title' => 'Inventory Dashboard'])

@php
    $currentUser = auth()->user();
    $navDate = request('date', app(\App\Services\Purchasing\PurchaserBusinessDayService::class)->operationalDate()->toDateString());
    $sidebarItems = [
        [
            'label' => 'Dashboard',
            'href' => route('inventory.dashboard', ['date' => $navDate]),
            'active' => request()->routeIs('inventory.dashboard') || request()->routeIs('inventory.deliveries.dashboard'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h7.5v7.5h-7.5v-7.5Zm9 0h7.5v10.5h-7.5V3.75Zm0 12h7.5v4.5h-7.5v-4.5Zm-9-3h7.5v7.5h-7.5v-7.5Z" /></svg>',
        ],
    ];

    if ($currentUser?->can('inventory.product.view')) {
        $sidebarItems[] = [
            'label' => 'Products',
            'href' => route('inventory.products.index'),
            'active' => request()->routeIs('inventory.products.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5 12 2.25 3 7.5m18 0V16.5L12 21.75 3 16.5V7.5m9 5.25V21.75" /></svg>',
        ];
    }

    if ($currentUser?->can('inventory.category.view')) {
        $sidebarItems[] = [
            'label' => 'Categories',
            'href' => route('inventory.categories.index'),
            'active' => request()->routeIs('inventory.categories.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581a2.25 2.25 0 0 0 3.181 0l4.318-4.318a2.25 2.25 0 0 0 0-3.181l-9.58-9.581A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>',
        ];
    }

    if ($currentUser?->can('inventory.stock.view')) {
        $sidebarItems[] = [
            'label' => 'Stock Levels',
            'href' => route('inventory.stock.index', ['date' => $navDate]),
            'active' => request()->routeIs('inventory.stock.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>',
        ];
    }

    if ($currentUser?->can('inventory.sorting.view')) {
        $sidebarItems[] = [
            'label' => 'Batches',
            'href' => route('inventory.batches.index', ['date' => $navDate]),
            'active' => request()->routeIs('inventory.batches.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 3l9 4.5M4.5 8.25v8.25A2.25 2.25 0 006.75 18.75h10.5A2.25 2.25 0 0019.5 16.5V8.25" /></svg>',
        ];
        $sidebarItems[] = [
            'label' => 'Sorting Checklist',
            'href' => route('inventory.sorting.checklist', ['date' => $navDate]),
            'active' => request()->routeIs('inventory.sorting.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6M9 9.75h6M9 14.25h6M5.25 5.25h.008v.008H5.25V5.25zm0 4.5h.008v.008H5.25V9.75zm0 4.5h.008v.008H5.25V14.25zm-1.5-9A2.25 2.25 0 016 3h12a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25V5.25z" /></svg>',
        ];
    }

    if ($currentUser?->can('inventory.wastage.view')) {
        $sidebarItems[] = [
            'label' => 'Wastage Log',
            'href' => route('inventory.wastage.index'),
            'active' => request()->routeIs('inventory.wastage.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c1.714 0 3.106 1.392 3.106 3.106 0 .666-.21 1.283-.567 1.788l4.86 8.411A2.25 2.25 0 0117.45 19.5H6.55a2.25 2.25 0 01-1.949-3.395l4.86-8.41A3.087 3.087 0 018.894 6.106C8.894 4.392 10.286 3 12 3Z" /></svg>',
        ];
    }

    $sidebarItems[] = [
        'label' => 'Delivery Dashboard',
        'href' => route('inventory.deliveries.dashboard', ['date' => $navDate]),
        'active' => request()->routeIs('inventory.deliveries.dashboard'),
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m3 0h7.5m3-3h-1.5m1.5 3a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m0 0h-9m11.25-3V9.75a1.5 1.5 0 0 0-1.5-1.5H14.25V6.75A1.5 1.5 0 0 0 12.75 5.25h-6A1.5 1.5 0 0 0 5.25 6.75v9" /></svg>',
    ];
    $sidebarItems[] = [
        'label' => 'Fulfillment Report',
        'href' => route('inventory.reports.fulfillment'),
        'active' => request()->routeIs('inventory.reports.fulfillment'),
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v17.25h16.5M7.5 15l3-3 2.25 2.25L16.5 9" /></svg>',
    ];

    $mobileItems = [
        ['label' => 'Home', 'href' => route('inventory.dashboard', ['date' => $navDate]), 'active' => request()->routeIs('inventory.dashboard') || request()->routeIs('inventory.deliveries.dashboard')],
        ['label' => 'Stock', 'href' => route('inventory.stock.index', ['date' => $navDate]), 'active' => request()->routeIs('inventory.stock.*')],
        ['label' => 'Batches', 'href' => route('inventory.batches.index', ['date' => $navDate]), 'active' => request()->routeIs('inventory.batches.*') || request()->routeIs('inventory.sorting.*')],
        ['label' => 'Report', 'href' => route('inventory.reports.fulfillment'), 'active' => request()->routeIs('inventory.reports.fulfillment')],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Green Leaf Traders</title>
    <meta name="description" content="Green Leaf Traders — Inventory Dashboard">
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-full bg-slate-100 font-sans antialiased text-slate-900">
<div id="inventory-layout-shell" class="min-h-screen lg:flex" data-sidebar-state="expanded">
    <aside id="inventory-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-slate-100 transition-[width,transform] duration-300 lg:translate-x-0">
        <div class="border-b border-slate-200 px-5 py-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5 12 2.25 3 7.5m18 0V16.5L12 21.75 3 16.5V7.5m9 5.25V21.75" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p data-inventory-sidebar-label class="truncate text-base font-black text-slate-950">Inventory</p>
                    <p data-inventory-sidebar-label class="mt-1 text-[11px] font-black uppercase tracking-[0.2em] text-emerald-700">Operations Desk</p>
                </div>
                <button id="inventory-sidebar-collapse" type="button" class="hidden rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:inline-flex" aria-label="Collapse inventory sidebar" title="Collapse sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <button id="inventory-sidebar-close" class="ml-auto rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Signed In</p>
                <p data-inventory-sidebar-label class="mt-2 truncate text-sm font-black text-slate-950">{{ $currentUser?->name }}</p>
                <p data-inventory-sidebar-label class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $currentUser?->email }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-2 overflow-y-auto px-4 py-5">
            @foreach ($sidebarItems as $item)
                <x-sidebar-link :item="$item" label-attribute="data-inventory-sidebar-label" />
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

    <div id="inventory-sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/45 lg:hidden"></div>

    <div id="inventory-main" class="flex min-h-screen flex-1 flex-col lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-md">
            <div class="flex items-center gap-3 px-4 py-5 sm:px-6 lg:px-8">
                <button id="inventory-sidebar-open" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <button id="inventory-sidebar-toggle" type="button" class="hidden h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:bg-slate-100 lg:inline-flex" aria-label="Toggle inventory sidebar" title="Toggle sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-[0.32em] text-slate-400">Green Leaf Traders</p>
                    <h1 class="mt-1 truncate text-2xl font-black tracking-[-0.04em] text-slate-950 sm:text-[2rem]">{{ $title }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden rounded-[1.35rem] border border-slate-200 bg-slate-50/90 px-5 py-3 text-right shadow-sm sm:block">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Today</p>
                        <p class="mt-1 text-sm font-black text-slate-950">{{ today()->format('d M Y') }}</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 px-4 py-4 sm:px-6 lg:px-8">
                @include('components.admin-dashboard-switcher')
            </div>
            @isset($actions)
                <div class="border-t border-slate-100 px-4 py-3">
                    <div class="flex flex-wrap gap-2">
                        {{ $actions }}
                    </div>
                </div>
            @endisset
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

            @if ($errors->any())
                <div class="mb-4 rounded-[1.35rem] border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                    {{ $errors->first() }}
                </div>
            @endif

            {{ $slot }}
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
    storage-key="inventory-sidebar-state"
    shell-id="inventory-layout-shell"
    sidebar-id="inventory-sidebar"
    main-id="inventory-main"
    overlay-id="inventory-sidebar-overlay"
    open-button-id="inventory-sidebar-open"
    close-button-id="inventory-sidebar-close"
    collapse-button-id="inventory-sidebar-collapse"
    toggle-button-id="inventory-sidebar-toggle"
    label-selector="[data-inventory-sidebar-label]"
/>
@stack('scripts')
</body>
</html>
