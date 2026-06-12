@props(['title' => 'Green Leaf ERP'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Green Leaf ERP</title>
    <meta name="description" content="Green Leaf ERP — Vegetable Trading & Distribution Management System">
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
<body class="h-full bg-slate-100 font-sans antialiased">
@php
    $currentUser = auth()->user();
    $currentUserInitial = $currentUser ? strtoupper(substr($currentUser->name, 0, 1)) : 'U';
    $showAdminMobileNav = $currentUser &&
        ($currentUser->hasRole('admin') || $currentUser->can('admin.user.view') || $currentUser->can('admin.daily-progress.view') || $currentUser->can('admin.activity-log.view'));
    $showWarehouseMobileNav = $currentUser &&
        ! $showAdminMobileNav &&
        ($currentUser->hasRole('warehouse') || $currentUser->can('warehouse.checklist.view'));
    $showPurchaserMobileNav = $currentUser &&
        ! $showAdminMobileNav &&
        ! $showWarehouseMobileNav &&
        $currentUser->hasRole('purchaser');
    $showPurchaseMobileNav = $currentUser &&
        ! $showAdminMobileNav &&
        ! $showWarehouseMobileNav &&
        ! $showPurchaserMobileNav &&
        ($currentUser->hasRole('purchase') || $currentUser->can('purchasing.order.approve'));
    $showWarehouseReceiverMobileNav = $currentUser &&
        ! $showAdminMobileNav &&
        ! $showWarehouseMobileNav &&
        ! $showPurchaserMobileNav &&
        ! $showPurchaseMobileNav &&
        ($currentUser->hasRole('warehouse_receiver') || $currentUser->can('warehouse.receive.confirm'));

    $warehouseMobileNavItems = [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'active' => request()->routeIs('dashboard'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Receive',
            'route' => 'inventory.sorting.checklist',
            'active' => request()->routeIs('inventory.sorting.checklist'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5l9 4.5 9-4.5M3 7.5l9-4.5 9 4.5M3 7.5V16.5L12 21m0-9 9-4.5V16.5L12 21m0 0V12" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Shop Cards',
            'route' => 'inventory.sorting.shop-orders',
            'active' => request()->routeIs('inventory.sorting.shop-orders'),
            'icon' => '<svg class="h-7 w-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"/><path d="M7 4h10"/><path d="M6 11h12"/><path d="M8 15h8"/><path d="M10 19h4"/></svg>',
            'type' => 'center',
        ],
        [
            'label' => 'Worker Sort',
            'route' => 'inventory.sorting.shop-sorting',
            'active' => request()->routeIs('inventory.sorting.shop-sorting*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12m-12 5.25h12m-12 5.25h12M3.75 6.75h.008v.008H3.75zm0 5.25h.008v.008H3.75zm0 5.25h.008v.008H3.75z"/></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Transit',
            'route' => 'inventory.deliveries.dashboard',
            'active' => request()->routeIs('inventory.deliveries.dashboard'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 17h8M7 7h8l3 4v6h-2m-9 0H5V9a2 2 0 012-2Z"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Report',
            'route' => 'inventory.reports.fulfillment',
            'active' => request()->routeIs('inventory.reports.fulfillment'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-3M5 21h14M5 3h14"/></svg>',
            'type' => 'link',
        ],
    ];
    $adminMobileNavItems = [
        [
            'label' => 'Overview',
            'route' => 'admin.overview',
            'active' => request()->routeIs('admin.overview'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5h8.25V3H3v10.5Zm0 7.5h8.25v-4.5H3V21Zm9.75 0H21V10.5h-8.25V21Zm0-12h8.25V3h-8.25v6Z"/></svg>',
        ],
        [
            'label' => 'Users',
            'route' => 'admin.users.index',
            'active' => request()->routeIs('admin.users.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z"/></svg>',
        ],
        [
            'label' => 'Progress',
            'route' => 'admin.daily-progress',
            'active' => request()->routeIs('admin.daily-progress'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 2 4-6"/></svg>',
        ],
        [
            'label' => 'Activity',
            'route' => 'admin.activity-logs.index',
            'active' => request()->routeIs('admin.activity-logs.index'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l3 8 4-16 3 8h4"/></svg>',
        ],
        [
            'label' => 'Finance',
            'route' => 'finance.index',
            'active' => request()->routeIs('finance.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m0 0c-2.21 0-4-1.343-4-3s1.79-3 4-3 4-1.343 4-3-1.79-3-4-3m0 12c2.21 0 4-1.343 4-3"/></svg>',
        ],
    ];
    $purchaseMobileNavItems = [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'active' => request()->routeIs('dashboard'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Approvals',
            'route' => 'requisitions.board',
            'active' => request()->routeIs('requisitions.board'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6M9 9.75h6M9 14.25h6M5.25 5.25h.008v.008H5.25V5.25zm0 4.5h.008v.008H5.25V9.75zm0 4.5h.008v.008H5.25V14.25zm-1.5-9A2.25 2.25 0 016 3h12a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25V5.25z" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Approved',
            'route' => 'requisitions.approved_board',
            'active' => request()->routeIs('requisitions.approved_board'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 11-18 0 9 9 0 0118 0Z" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Orders',
            'route' => 'purchasing.orders.index',
            'active' => request()->routeIs('purchasing.orders.*'),
            'icon' => '<svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0z" /></svg>',
            'type' => 'center',
        ],
        [
            'label' => 'Receipts',
            'route' => 'purchasing.grns.index',
            'active' => request()->routeIs('purchasing.grns.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3.75A.75.75 0 013.75 3h10.5a.75.75 0 01.53.22l5 5a.75.75 0 01.22.53v11.5a.75.75 0 01-.75.75H3.75a.75.75 0 01-.75-.75V3.75z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 12h7.5m-7.5 3h4.5" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Prices',
            'route' => 'purchasing.prices.index',
            'active' => request()->routeIs('purchasing.prices.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m3.75-9.75h-6a2.25 2.25 0 100 4.5h4.5a2.25 2.25 0 100 4.5h-6" /></svg>',
            'type' => 'link',
        ],
    ];
    $purchaserMobileNavItems = [
        [
            'label' => 'Purchases',
            'route' => 'purchaser.dashboard',
            'active' => request()->routeIs('purchaser.dashboard'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>',
            'type' => 'link',
        ],
    ];
    $warehouseReceiverMobileNavItems = [
        [
            'label' => 'Receive',
            'route' => 'warehouse.receiver.checklist',
            'active' => request()->routeIs('warehouse.receiver.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'type' => 'link',
        ],
    ];
    $showMobileBottomNav = $showAdminMobileNav || $showWarehouseMobileNav || $showPurchaseMobileNav || $showPurchaserMobileNav || $showWarehouseReceiverMobileNav;
@endphp

<div id="app-container" class="flex h-full">

    {{-- ── Sidebar ─────────────────────────────────────────────── --}}
    <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-slate-950 text-white transition-transform duration-300 lg:translate-x-0 -translate-x-full"
        aria-label="Sidebar navigation"
    >
        {{-- Logo --}}
        <div class="border-b border-white/10 px-6 py-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-cyan-400 text-slate-950 shadow-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.25-9H21M3 12h.75m13.364 6.364.53.53M6.106 6.106l.53.53m10.728-.53-.53.53M6.106 17.894l-.53.53M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-black leading-none text-white">Green Leaf ERP</p>
                    <p class="mt-1 text-[10px] font-black uppercase tracking-[0.2em] text-cyan-300">Operations Hub</p>
                </div>
                <button id="sidebar-close" class="ml-auto text-slate-400 transition hover:text-white lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Signed In</p>
                <p class="mt-1 truncate text-sm font-bold text-white">{{ auth()->user()->name }}</p>
                <p class="mt-1 truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 space-y-3 overflow-y-auto px-4 py-6">

            {{-- Dashboard --}}
            <x-nav-item href="{{ route('dashboard') }}" icon="squares-2x2" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-nav-item>

            @if(auth()->user()->hasRole('shop'))
                <x-nav-item href="{{ route('purchasing.orders.index') }}" icon="shopping-cart" :active="request()->routeIs('purchasing.orders.*')">
                    Purchase Orders
                </x-nav-item>

                <x-nav-item href="{{ route('inventory.deliveries.dashboard') }}" icon="truck" :active="request()->routeIs('inventory.deliveries.*')">
                    Deliveries
                </x-nav-item>

                <x-nav-item href="{{ route('finance.index') }}" icon="document-currency-dollar" :active="request()->routeIs('finance.*')">
                    Finance
                </x-nav-item>
            @elseif(auth()->user()->hasRole('purchaser'))
                <x-nav-item href="{{ route('purchaser.dashboard') }}" icon="squares-2x2" :active="request()->routeIs('purchaser.dashboard') && !request()->has('tab')">
                    Purchaser Dashboard
                </x-nav-item>
                <div class="space-y-1 pl-3 pr-1">
                    <x-nav-item id="sidebar-tab-daily-order" href="{{ route('purchaser.dashboard', ['tab' => 'daily-order']) }}" :active="request()->routeIs('purchaser.dashboard') && request()->input('tab', 'daily-order') === 'daily-order'" :sub="true" class="purchaser-sidebar-tab-btn" data-tab="daily-order">
                        Daily Order
                    </x-nav-item>
                    <x-nav-item id="sidebar-tab-bought" href="{{ route('purchaser.dashboard', ['tab' => 'bought']) }}" :active="request()->routeIs('purchaser.dashboard') && request()->input('tab') === 'bought'" :sub="true" class="purchaser-sidebar-tab-btn" data-tab="bought">
                        Bought Items
                    </x-nav-item>
                    <x-nav-item id="sidebar-tab-remaining" href="{{ route('purchaser.dashboard', ['tab' => 'remaining']) }}" :active="request()->routeIs('purchaser.dashboard') && request()->input('tab') === 'remaining'" :sub="true" class="purchaser-sidebar-tab-btn" data-tab="remaining">
                        Remaining to Buy
                    </x-nav-item>
                    <x-nav-item id="sidebar-tab-history" href="{{ route('purchaser.dashboard', ['tab' => 'history']) }}" :active="request()->routeIs('purchaser.dashboard') && request()->input('tab') === 'history'" :sub="true" class="purchaser-sidebar-tab-btn" data-tab="history">
                        History
                    </x-nav-item>
                </div>
            @else
                {{-- Inventory Group --}}
                @if(
                    auth()->user()->can('inventory.product.view') ||
                    auth()->user()->can('inventory.stock.view') ||
                    auth()->user()->can('inventory.sorting.view') ||
                    auth()->user()->can('inventory.wastage.view') ||
                    auth()->user()->can('warehouse.checklist.view')
                )
                @php
                    $isInventoryActive = request()->routeIs('inventory.*');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isInventoryActive ? 'bg-white/5 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}"
                        aria-expanded="{{ $isInventoryActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                            <span>Inventory</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isInventoryActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items space-y-1 pl-3 pr-1 transition-all duration-200 {{ $isInventoryActive ? '' : 'hidden' }}">
                        @can('inventory.product.view')
                        <x-nav-item href="{{ route('inventory.products.index') }}" :active="request()->routeIs('inventory.products.*')" :sub="true">
                            Products
                        </x-nav-item>
                        @endcan
                        @can('inventory.stock.view')
                        <x-nav-item href="{{ route('inventory.stock.index') }}" :active="request()->routeIs('inventory.stock.*')" :sub="true">
                            Stock Levels
                        </x-nav-item>
                        @endcan
                        @can('inventory.sorting.view')
                        <x-nav-item href="{{ route('inventory.batches.index') }}" :active="request()->routeIs('inventory.batches.*')" :sub="true">
                            Batches & Sorting
                        </x-nav-item>
                        @endcan
                        @can('inventory.wastage.view')
                        <x-nav-item href="{{ route('inventory.wastage.index') }}" :active="request()->routeIs('inventory.wastage.*')" :sub="true">
                            Wastage Log
                        </x-nav-item>
                        @endcan
                        @can('warehouse.checklist.view')
                        <x-nav-item href="{{ route('inventory.sorting.checklist') }}" :active="request()->routeIs('inventory.sorting.checklist')" :sub="true">
                            Sorting Checklist
                        </x-nav-item>
                        <x-nav-item href="{{ route('inventory.sorting.shop-orders') }}" :active="request()->routeIs('inventory.sorting.shop-orders')" :sub="true">
                            Shop Orders
                        </x-nav-item>
                        <x-nav-item href="{{ route('inventory.sorting.shop-sorting') }}" :active="request()->routeIs('inventory.sorting.shop-sorting*')" :sub="true">
                            Worker Sorting
                        </x-nav-item>
                        @endcan
                        <x-nav-item href="{{ route('inventory.deliveries.dashboard') }}" :active="request()->routeIs('inventory.deliveries.dashboard')" :sub="true">
                            Delivery Dashboard
                        </x-nav-item>
                        <x-nav-item href="{{ route('inventory.reports.fulfillment') }}" :active="request()->routeIs('inventory.reports.fulfillment')" :sub="true">
                            Fulfillment Report
                        </x-nav-item>
                    </div>
                </div>
                @endif

                {{-- Sort Sheet --}}
                @can('sort.sheet.view')
                <x-nav-item href="{{ route('sort-sheet.index') }}" :active="request()->routeIs('sort-sheet.*')" icon="table-cells">
                    Sort Sheet
                </x-nav-item>
                @endcan

                {{-- Purchasing Group --}}
                @if(
                    auth()->user()->hasRole('purchase') ||
                    auth()->user()->can('purchasing.supplier.view') ||
                    auth()->user()->can('purchasing.order.view') ||
                    auth()->user()->can('purchasing.grn.view') ||
                    auth()->user()->can('viewAny', \App\Models\PurchaseInvoice::class)
                )
                @php
                    $isPurchasingActive = request()->routeIs('purchasing.*') || request()->routeIs('requisitions.board') || request()->routeIs('requisitions.approved_board') || request()->routeIs('purchaser.dashboard');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isPurchasingActive ? 'bg-white/5 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}"
                        aria-expanded="{{ $isPurchasingActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                            </svg>
                            <span>Purchasing</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isPurchasingActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                             <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items space-y-1 pl-3 pr-1 transition-all duration-200 {{ $isPurchasingActive ? '' : 'hidden' }}">
                        @if(auth()->user()->hasRole('purchaser'))
                        <x-nav-item href="{{ route('purchaser.dashboard') }}" :active="request()->routeIs('purchaser.dashboard') && !request()->has('tab')" :sub="true">
                            Purchaser Desk
                        </x-nav-item>
                        <div class="space-y-1 pl-3 pr-1">
                            <x-nav-item id="sidebar-tab-daily-order-pm" href="{{ route('purchaser.dashboard', ['tab' => 'daily-order']) }}" :active="request()->routeIs('purchaser.dashboard') && request()->input('tab', 'daily-order') === 'daily-order'" :sub="true" class="purchaser-sidebar-tab-btn" data-tab="daily-order">
                                Daily Order
                            </x-nav-item>
                            <x-nav-item id="sidebar-tab-bought-pm" href="{{ route('purchaser.dashboard', ['tab' => 'bought']) }}" :active="request()->routeIs('purchaser.dashboard') && request()->input('tab') === 'bought'" :sub="true" class="purchaser-sidebar-tab-btn" data-tab="bought">
                                Bought Items
                            </x-nav-item>
                            <x-nav-item id="sidebar-tab-remaining-pm" href="{{ route('purchaser.dashboard', ['tab' => 'remaining']) }}" :active="request()->routeIs('purchaser.dashboard') && request()->input('tab') === 'remaining'" :sub="true" class="purchaser-sidebar-tab-btn" data-tab="remaining">
                                Remaining to Buy
                            </x-nav-item>
                            <x-nav-item id="sidebar-tab-history-pm" href="{{ route('purchaser.dashboard', ['tab' => 'history']) }}" :active="request()->routeIs('purchaser.dashboard') && request()->input('tab') === 'history'" :sub="true" class="purchaser-sidebar-tab-btn" data-tab="history">
                                History
                            </x-nav-item>
                        </div>
                        @endif
                        @if(auth()->user()->hasRole('purchase') || auth()->user()->can('purchasing.order.approve'))
                        <x-nav-item href="{{ route('requisitions.board') }}" :active="request()->routeIs('requisitions.board')" :sub="true">
                            Requisition Board
                        </x-nav-item>
                        <x-nav-item href="{{ route('requisitions.approved_board') }}" :active="request()->routeIs('requisitions.approved_board')" :sub="true">
                            Approved Board
                        </x-nav-item>
                        @endif
                        @if(auth()->user()->hasRole('purchase') || auth()->user()->hasRole('admin'))
                        <x-nav-item href="{{ route('purchasing.prices.index') }}" :active="request()->routeIs('purchasing.prices.*')" :sub="true">
                            Daily Price Board
                        </x-nav-item>
                        @if(auth()->user()->hasRole('admin'))
                        <x-nav-item href="{{ route('purchasing.price-groups.index') }}" :active="request()->routeIs('purchasing.price-groups.*')" :sub="true">
                            Shop Price Categories
                        </x-nav-item>
                        @endif
                        <x-nav-item href="{{ route('purchasing.shop-invoices.index') }}" :active="request()->routeIs('purchasing.shop-invoices.*')" :sub="true">
                            Shop Daily Invoices
                        </x-nav-item>
                        @endif
                        @can('purchasing.order.view')
                        <x-nav-item href="{{ route('purchasing.orders.index') }}" :active="request()->routeIs('purchasing.orders.*')" :sub="true">
                            Purchase Orders
                        </x-nav-item>
                        @endcan
                        @can('purchasing.grn.view')
                        <x-nav-item href="{{ route('purchasing.grns.index') }}" :active="request()->routeIs('purchasing.grns.*')" :sub="true">
                            Goods Receipts
                        </x-nav-item>
                        @endcan
                    </div>
                </div>
                @endif

                {{-- Sales Group --}}
                @if(
                    auth()->user()->can('sales.customer.view') ||
                    auth()->user()->can('sales.order.view') ||
                    auth()->user()->can('sales.invoice.view')
                )
                @php
                    $isSalesActive = request()->routeIs('sales.*');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isSalesActive ? 'bg-white/5 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}"
                        aria-expanded="{{ $isSalesActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                            <span>Sales</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isSalesActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items space-y-1 pl-3 pr-1 transition-all duration-200 {{ $isSalesActive ? '' : 'hidden' }}">
                        @can('sales.customer.view')
                        <x-nav-item href="{{ route('sales.customers.index') }}" :active="request()->routeIs('sales.customers.*')" :sub="true">
                            Customers
                        </x-nav-item>
                        @endcan
                        @can('sales.order.view')
                        <x-nav-item href="{{ route('sales.orders.index') }}" :active="request()->routeIs('sales.orders.*')" :sub="true">
                            Sales Orders
                        </x-nav-item>
                        @endcan
                        @can('sales.invoice.view')
                        <x-nav-item href="{{ route('sales.invoices.index') }}" :active="request()->routeIs('sales.invoices.*')" :sub="true">
                            Sales Invoices
                        </x-nav-item>
                        @endcan
                    </div>
                </div>
                @endif

                {{-- Finance Group --}}
                @if(
                    auth()->user()->can('accounting.ledger.view') ||
                    auth()->user()->can('accounting.report.view') ||
                    auth()->user()->can('accounting.entry.create')
                )
                @php
                    $isFinanceActive = request()->routeIs('finance.*');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isFinanceActive ? 'bg-white/5 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}"
                        aria-expanded="{{ $isFinanceActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.03 0 1.9.693 2.166 1.638m-7.377 0A48.536 48.536 0 0112 3.75c0 .08-.004.16-.01.238m-2.886 0c.385.023.77.05 1.154.08m-3.456 0A48.108 48.108 0 002.25 6.11v10.39a2.25 2.25 0 002.25 2.25h3" />
                            </svg>
                            <span>Finance</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isFinanceActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items space-y-1 pl-3 pr-1 transition-all duration-200 {{ $isFinanceActive ? '' : 'hidden' }}">
                        @can('accounting.ledger.view')
                        <x-nav-item href="{{ route('finance.accounts.index') }}" :active="request()->routeIs('finance.accounts.*')" :sub="true">
                            Chart of Accounts
                        </x-nav-item>
                        <x-nav-item href="{{ route('finance.ledger.index') }}" :active="request()->routeIs('finance.ledger.*')" :sub="true">
                            General Ledger
                        </x-nav-item>
                        @endcan
                        @if(auth()->user()->can('accounting.report.view') || auth()->user()->can('accounting.entry.create'))
                        <x-nav-item href="{{ route('finance.expenses.index') }}" :active="request()->routeIs('finance.expenses.*')" :sub="true">
                            Expenses
                        </x-nav-item>
                        @endif
                        @can('accounting.report.view')
                        <x-nav-item href="{{ route('finance.reports.pnl') }}" :active="request()->routeIs('finance.reports.pnl')" :sub="true">
                            P&L Statement
                        </x-nav-item>
                        <x-nav-item href="{{ route('finance.reports.balance-sheet') }}" :active="request()->routeIs('finance.reports.balance-sheet')" :sub="true">
                            Balance Sheet
                        </x-nav-item>
                        <x-nav-item href="{{ route('finance.reports.cash-flow') }}" :active="request()->routeIs('finance.reports.cash-flow')" :sub="true">
                            Cash Flow
                        </x-nav-item>
                        @endcan
                    </div>
                </div>
                @endif

                {{-- Admin Group --}}
                @if(
                    auth()->user()->hasRole('admin') ||
                    auth()->user()->can('admin.user.view') ||
                    auth()->user()->can('admin.daily-progress.view') ||
                    auth()->user()->can('admin.activity-log.view')
                )
                @php
                    $isAdminActive = request()->routeIs('admin.*');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isAdminActive ? 'bg-white/5 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}"
                        aria-expanded="{{ $isAdminActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            <span>Admin</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isAdminActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items space-y-1 pl-3 pr-1 transition-all duration-200 {{ $isAdminActive ? '' : 'hidden' }}">
                        @can('admin.user.view')
                        <x-nav-item href="{{ route('admin.overview') }}" :active="request()->routeIs('admin.overview')" :sub="true">
                            Admin Overview
                        </x-nav-item>
                        @endcan
                        @can('admin.user.view')
                        <x-nav-item href="{{ route('admin.users.index') }}" :active="request()->routeIs('admin.users.*')" :sub="true">
                            Users & Roles
                        </x-nav-item>
                        @endcan
                        @can('admin.user.view')
                        <x-nav-item href="{{ route('admin.warehouses.index') }}" :active="request()->routeIs('admin.warehouses.*')" :sub="true">
                            Warehouses
                        </x-nav-item>
                        @endcan
                        @can('admin.daily-progress.view')
                        <x-nav-item href="{{ route('admin.daily-progress') }}" :active="request()->routeIs('admin.daily-progress')" :sub="true">
                            Daily Progress
                        </x-nav-item>
                        @endcan
                        @can('admin.activity-log.view')
                        <x-nav-item href="{{ route('admin.activity-logs.index') }}" :active="request()->routeIs('admin.activity-logs.index')" :sub="true">
                            Activity Log
                        </x-nav-item>
                        @endcan
                        @can('admin.user.view')
                        <x-nav-item href="{{ route('admin.price-approvals.index') }}" :active="request()->routeIs('admin.price-approvals.*')" :sub="true">
                            Daily Price Approvals
                        </x-nav-item>
                        @endcan
                    </div>
                </div>
                @endif
            @endif

        </nav>

        {{-- User footer --}}
        <div class="border-t border-white/10 px-4 py-4">
            <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-800">
                    <span class="text-xs font-bold text-cyan-300">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold text-white">{{ auth()->user()->name }}</p>
                    <p class="truncate text-[10px] text-slate-400">{{ auth()->user()->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sign out" class="rounded-xl p-2 text-slate-400 transition hover:bg-white/5 hover:text-white">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Sidebar overlay for mobile --}}
    <div id="sidebar-overlay" class="fixed inset-0 z-40 bg-black/50 hidden lg:hidden" aria-hidden="true"></div>

    {{-- ── Main content ─────────────────────────────────────────── --}}
    <div class="main-content-wrapper flex min-w-0 flex-1 flex-col transition-all duration-300 lg:ml-72">

        {{-- Top bar --}}
        <header class="fixed inset-x-4 top-4 z-30 mx-auto max-w-md rounded-[1.5rem] border border-slate-100 bg-white/95 px-4 py-3 shadow-[0_8px_30px_rgb(0,0,0,0.08)] backdrop-blur-md sm:px-5 lg:sticky lg:inset-x-0 lg:top-0 lg:left-0 lg:right-0 lg:mx-0 lg:max-w-none lg:rounded-none lg:border-0 lg:border-b lg:border-slate-200 lg:bg-white/95 lg:px-6 lg:py-0 lg:shadow-none">
            <div class="flex items-center gap-4 lg:h-16">
                {{-- Collapse / open toggle --}}
                <button id="sidebar-open" class="-ml-1 rounded-2xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                {{-- Page breadcrumb / title --}}
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Green Leaf ERP</p>
                    <h1 class="truncate text-xs font-bold text-slate-900 sm:text-sm">{{ $title }}</h1>
                </div>

                {{-- Header Actions Container --}}
                <div class="ml-auto flex items-center gap-3 shrink-0">
                    {{-- Theme Toggle Switch --}}
                    <button
                        id="theme-toggle"
                        type="button"
                        class="rounded-2xl p-2 text-slate-500 transition-colors duration-200 cursor-pointer hover:bg-slate-100 hover:text-slate-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                        title="Toggle dark/light theme"
                    >
                        {{-- Moon Icon (for Light Mode) --}}
                        <svg id="theme-toggle-moon" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75C12.365 15.75 8 11.385 8 5.75c0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                        {{-- Sun Icon (for Dark Mode) --}}
                        <svg id="theme-toggle-sun" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m-9-9h1.5m15 0H21m-2.121-6.879l-1.061 1.061m-10.606 10.606l-1.061 1.061M6.343 6.343l1.061 1.061m10.606 10.606l1.061 1.061M12 7.5a4.5 4.5 0 100 9 4.5 4.5 0 000-9z" />
                        </svg>
                    </button>

                    <details class="relative">
                        <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-800 transition hover:border-slate-300 hover:bg-white">
                            {{ $currentUserInitial }}
                        </summary>

                        <div class="absolute right-0 top-14 w-52 rounded-3xl border border-slate-200 bg-white p-2 shadow-xl">
                            <div class="border-b border-slate-100 px-3 py-2">
                                <p class="truncate text-sm font-black text-slate-900">{{ $currentUser?->name }}</p>
                                <p class="mt-1 truncate text-[11px] font-semibold text-slate-500">{{ $currentUser?->email }}</p>
                            </div>

                            <div class="mt-2 space-y-1">
                                <a href="{{ route('profile.show') }}" class="flex items-center rounded-2xl px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100">
                                    Profile Update
                                </a>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center rounded-2xl px-3 py-2 text-left text-sm font-bold text-red-600 transition hover:bg-red-50">
                                        Sign Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </details>
                </div>
            </div>

            @if(isset($actions))
                <div class="mt-3 border-t border-slate-100 pt-3 lg:mt-0 lg:border-t-0 lg:pt-0">
                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                        {{ $actions }}
                    </div>
                </div>
            @endif
        </header>

        {{-- Flash messages --}}
        @if (session('success'))
        <div class="mx-4 mt-4 flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 sm:mx-6" role="alert">
            <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-green-800 text-sm">{{ session('success') }}</p>
        </div>
        @endif

        @if (session('error'))
        <div class="mx-4 mt-4 flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 sm:mx-6" role="alert">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <p class="text-red-800 text-sm">{{ session('error') }}</p>
        </div>
        @endif

        {{-- Page content --}}
        <main class="flex-1 px-4 pb-24 {{ isset($actions) ? 'pt-32' : 'pt-24' }} sm:px-6 lg:p-6 lg:pt-6 {{ $showMobileBottomNav ? 'lg:pb-6' : '' }}">
            {{ $slot }}
        </main>
    </div>
</div>

@if($showAdminMobileNav || $showWarehouseMobileNav || $showPurchaseMobileNav || $showPurchaserMobileNav || $showWarehouseReceiverMobileNav)
<div id="layout-mobile-nav" class="fixed inset-x-0 bottom-4 z-40 px-4 lg:hidden">
    <nav class="mx-auto flex h-16 max-w-md items-center justify-around rounded-[2rem] border border-slate-100 bg-white/95 px-4 shadow-[0_-8px_30px_rgb(0,0,0,0.08),0_4px_20px_rgb(0,0,0,0.04)] backdrop-blur-md">
        @php
            $mobileNavItems = match(true) {
                $showAdminMobileNav => $adminMobileNavItems,
                $showWarehouseMobileNav => $warehouseMobileNavItems,
                $showPurchaseMobileNav => $purchaseMobileNavItems,
                $showPurchaserMobileNav => $purchaserMobileNavItems,
                $showWarehouseReceiverMobileNav => $warehouseReceiverMobileNavItems,
                default => [],
            };
        @endphp
        @foreach ($mobileNavItems as $item)
            @php
                $isActive = $item['active'] ?? false;
            @endphp
            @if(($item['type'] ?? 'link') === 'center')
                <div class="relative -mt-10">
                    <a href="{{ route($item['route']) }}" @class([
                        'relative flex h-16 w-16 items-center justify-center rounded-full border-[6px] border-slate-100 shadow-lg transition-all duration-300',
                        'bg-cyan-500 text-white hover:bg-cyan-600 hover:scale-105 active:scale-95' => $isActive,
                        'bg-cyan-500 text-white/95 hover:bg-cyan-600 hover:scale-105 active:scale-95' => ! $isActive,
                    ])>{!! $item['icon'] !!}</a>
                    @if ($isActive)
                        <div class="absolute -bottom-4 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rounded-full bg-cyan-500"></div>
                    @endif
                </div>
            @else
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'relative flex flex-col items-center justify-center rounded-2xl transition-all duration-200',
                        'text-cyan-600 w-14 h-14' => $isActive,
                        'text-slate-400 hover:text-slate-600 w-12 h-12' => ! $isActive,
                    ])
                    title="{{ $item['label'] }}"
                >
                    <div class="transition-transform duration-200 {{ $isActive ? '-translate-y-1 scale-90' : '' }}">
                        {!! $item['icon'] !!}
                    </div>
                    @if ($isActive)
                        <span class="text-[9px] font-bold tracking-tight absolute bottom-1 transition-all duration-200">{{ $item['label'] }}</span>
                    @endif
                </a>
            @endif
        @endforeach
    </nav>
</div>
@endif

@include('components.app-dialogs')

<script>
    // Sidebar toggle and collapse logic
    const appContainer = document.getElementById('app-container');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const openBtn = document.getElementById('sidebar-open');
    const closeBtn = document.getElementById('sidebar-close');

    // On load, restore desktop sidebar collapsed state
    if (window.innerWidth >= 1024 && localStorage.getItem('sidebar-collapsed') === 'true') {
        appContainer?.classList.add('sidebar-collapsed');
    }

    function openSidebar() {
        sidebar?.classList.remove('-translate-x-full');
        overlay?.classList.remove('hidden');
    }
    function closeSidebar() {
        sidebar?.classList.add('-translate-x-full');
        overlay?.classList.add('hidden');
    }

    openBtn?.addEventListener('click', () => {
        if (window.innerWidth >= 1024) {
            // Desktop: toggle collapse
            appContainer?.classList.toggle('sidebar-collapsed');
            const isCollapsed = appContainer?.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed ? 'true' : 'false');
        } else {
            // Mobile: open side drawer
            openSidebar();
        }
    });

    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    // Collapsible group logic
    document.querySelectorAll('.sidebar-group-toggle').forEach(button => {
        button.addEventListener('click', () => {
            const group = button.closest('.sidebar-group');
            const items = group.querySelector('.sidebar-group-items');
            const chevron = button.querySelector('.chevron-icon');
            
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            
            if (isExpanded) {
                button.setAttribute('aria-expanded', 'false');
                items.classList.add('hidden');
                chevron.classList.remove('rotate-90', 'opacity-100');
                chevron.classList.add('opacity-50');
            } else {
                button.setAttribute('aria-expanded', 'true');
                items.classList.remove('hidden');
                chevron.classList.add('rotate-90', 'opacity-100');
                chevron.classList.remove('opacity-50');
            }
        });
    });

    // Theme Toggle Functionality
    const themeToggleBtn = document.getElementById('theme-toggle');
    const moonIcon = document.getElementById('theme-toggle-moon');
    const sunIcon = document.getElementById('theme-toggle-sun');

    if (themeToggleBtn && moonIcon && sunIcon) {
        // Initialize visible icon
        if (document.documentElement.classList.contains('dark')) {
            sunIcon.classList.remove('hidden');
        } else {
            moonIcon.classList.remove('hidden');
        }

        // Toggle theme on click
        themeToggleBtn.addEventListener('click', () => {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                sunIcon.classList.add('hidden');
                moonIcon.classList.remove('hidden');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                moonIcon.classList.add('hidden');
                sunIcon.classList.remove('hidden');
            }
        });
    }
</script>
@stack('scripts')
</body>
</html>
