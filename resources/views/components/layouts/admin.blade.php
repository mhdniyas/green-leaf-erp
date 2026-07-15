@props(['title' => 'Admin Dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Green Leaf Traders</title>
    <meta name="description" content="Green Leaf Traders — Admin Dashboard">
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
@php
    $currentUser = auth()->user();
    $navDate = request('date', app(\App\Services\Purchasing\PurchaserBusinessDayService::class)->operationalDate()->toDateString());
    $sidebarSections = [];

    $workspaceItems = [];

    if (\App\Support\AccountingAccess::canViewDashboard($currentUser)) {
        $workspaceItems[] = [
            'label' => 'Accounting Dashboard',
            'href' => route('admin.accounting.index', ['date' => $navDate]),
            'active' => request()->routeIs('admin.accounting.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m0 0c-2.21 0-4-1.343-4-3s1.79-3 4-3 4-1.343 4-3-1.79-3-4-3m0 12c2.21 0 4-1.343 4-3" /></svg>',
        ];
    }

    if (
        $currentUser?->hasRole('purchase') ||
        $currentUser?->can('purchasing.supplier.view') ||
        $currentUser?->can('purchasing.order.view') ||
        $currentUser?->can('purchasing.grn.view') ||
        $currentUser?->can('viewAny', \App\Models\PurchaseInvoice::class)
    ) {
        $workspaceItems[] = [
            'label' => 'Purchasing Dashboard',
            'href' => route('purchasing.dashboard'),
            'active' => request()->routeIs('purchasing.*') || request()->routeIs('requisitions.board') || request()->routeIs('requisitions.approved_board'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 0 1 1.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 0 1 1.5 0z" /></svg>',
        ];
    }

    if (
        $currentUser?->can('inventory.product.view') ||
        $currentUser?->can('inventory.stock.view') ||
        $currentUser?->can('inventory.sorting.view') ||
        $currentUser?->can('inventory.wastage.view')
    ) {
        $workspaceItems[] = [
            'label' => 'Inventory Dashboard',
            'href' => route('inventory.dashboard', ['date' => $navDate]),
            'active' => request()->routeIs('inventory.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5 12 2.25 3 7.5m18 0V16.5L12 21.75 3 16.5V7.5m9 5.25V21.75" /></svg>',
        ];
    }

    if ($currentUser?->can('sort.sheet.view')) {
        $workspaceItems[] = [
            'label' => 'Sort Sheet',
            'href' => route('sort-sheet.index'),
            'active' => request()->routeIs('sort-sheet.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>',
        ];
    }

    if (count($workspaceItems) > 0) {
        $sidebarSections[] = [
            'label' => 'Workspace',
            'items' => $workspaceItems,
        ];
    }

    $salesItems = [];

    if ($currentUser?->can('sales.customer.view')) {
        $salesItems[] = [
            'label' => 'Customers',
            'href' => route('sales.customers.index'),
            'active' => request()->routeIs('sales.customers.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>',
        ];
    }

    if ($currentUser?->can('sales.order.view')) {
        $salesItems[] = [
            'label' => 'Sales Orders',
            'href' => route('sales.orders.index'),
            'active' => request()->routeIs('sales.orders.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3h16.5A2.25 2.25 0 0 1 22.5 5.25v13.5A2.25 2.25 0 0 1 20.25 21H3.75A2.25 2.25 0 0 1 1.5 18.75V5.25A2.25 2.25 0 0 1 3.75 3Zm3.75 4.5h9m-9 4.5h6" /></svg>',
        ];
    }

    if ($currentUser?->can('sales.invoice.view')) {
        $salesItems[] = [
            'label' => 'Sales Invoices',
            'href' => route('sales.invoices.index'),
            'active' => request()->routeIs('sales.invoices.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h9A2.25 2.25 0 0 1 18.75 6v12A2.25 2.25 0 0 1 16.5 20.25h-9A2.25 2.25 0 0 1 5.25 18V6A2.25 2.25 0 0 1 7.5 3.75Zm2.25 4.5h4.5m-4.5 3h4.5m-4.5 3h3" /></svg>',
        ];
    }

    if (count($salesItems) > 0) {
        $sidebarSections[] = [
            'label' => 'Sales',
            'items' => $salesItems,
        ];
    }

    $adminItems = [];

    if ($currentUser?->can('admin.user.view') || $currentUser?->hasRole('admin')) {
        $adminItems[] = [
            'label' => 'Overview',
            'href' => route('admin.overview', ['date' => $navDate]),
            'active' => request()->routeIs('admin.overview'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h7.5v7.5h-7.5v-7.5Zm9 0h7.5v7.5h-7.5v-7.5Zm-9 9h7.5v7.5h-7.5v-7.5Zm9 3h7.5v4.5h-7.5v-4.5Z" /></svg>',
        ];
        $adminItems[] = [
            'label' => 'Users & Roles',
            'href' => route('admin.users.index'),
            'active' => request()->routeIs('admin.users.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z"/></svg>',
        ];
        $adminItems[] = [
            'label' => 'Warehouses',
            'href' => route('admin.warehouses.index'),
            'active' => request()->routeIs('admin.warehouses.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 3l9 4.5M4.5 8.25v8.25A2.25 2.25 0 0 0 6.75 18.75h10.5A2.25 2.25 0 0 0 19.5 16.5V8.25M9 12h6" /></svg>',
        ];
    }

    if ($currentUser?->can('admin.daily-progress.view') || $currentUser?->hasRole('admin')) {
        $adminItems[] = [
            'label' => 'Daily Progress',
            'href' => route('admin.daily-progress', ['date' => $navDate]),
            'active' => request()->routeIs('admin.daily-progress'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 2 5-6" /></svg>',
        ];
    }

    if ($currentUser?->can('admin.activity-log.view') || $currentUser?->hasRole('admin')) {
        $adminItems[] = [
            'label' => 'Activity Log',
            'href' => route('admin.activity-logs.index'),
            'active' => request()->routeIs('admin.activity-logs.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l3 8 4-16 3 8h4" /></svg>',
        ];
    }

    if ($currentUser?->hasRole('admin')) {
        $adminItems[] = [
            'label' => 'Enquiries',
            'href' => route('admin.enquiries.index'),
            'active' => request()->routeIs('admin.enquiries.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5A2.25 2.25 0 0 1 19.5 19.5h-15A2.25 2.25 0 0 1 2.25 17.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15A2.25 2.25 0 0 0 2.25 6.75m19.5 0-9.214 6.142a1 1 0 0 1-1.072 0L2.25 6.75" /></svg>',
        ];
    }

    if ($currentUser?->can('admin.discrepancies.view') || $currentUser?->hasRole('admin')) {
        $adminItems[] = [
            'label' => 'Discrepancies',
            'href' => route('admin.discrepancies.index', ['date' => $navDate]),
            'active' => request()->routeIs('admin.discrepancies.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm9-3.758c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9Z" /></svg>',
        ];
    }

    if (count($adminItems) > 0) {
        $sidebarSections[] = [
            'label' => 'Administration',
            'items' => $adminItems,
        ];
    }

    $mobileItems = collect($sidebarSections)
        ->pluck('items')
        ->flatten(1)
        ->take(4)
        ->values()
        ->all();
@endphp

<div id="admin-layout-shell" class="min-h-screen lg:flex" data-sidebar-state="expanded">
    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-[width,transform] duration-300 lg:translate-x-0">
        <div class="border-b border-slate-200 px-5 py-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-950 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0Z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p data-admin-sidebar-label class="truncate text-base font-black text-slate-950">Admin Panel</p>
                    <p data-admin-sidebar-label class="mt-1 text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Control Desk</p>
                </div>
                <button id="admin-sidebar-collapse" type="button" class="hidden rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:inline-flex" aria-label="Collapse admin sidebar" title="Collapse sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <button id="admin-sidebar-close" class="ml-auto rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Signed In</p>
                <p data-admin-sidebar-label class="mt-2 truncate text-sm font-black text-slate-950">{{ $currentUser?->name }}</p>
                <p data-admin-sidebar-label class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $currentUser?->email }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto px-4 py-5">
            @foreach ($sidebarSections as $section)
                <div class="space-y-2">
                    <p data-admin-sidebar-label class="px-3 text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">{{ $section['label'] }}</p>
                    @foreach ($section['items'] as $item)
                        <a href="{{ $item['href'] }}" title="{{ $item['label'] }}" class="flex items-center gap-3 rounded-[1.2rem] px-4 py-3 text-sm font-black transition {{ $item['active'] ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="{{ $item['active'] ? 'text-white' : 'text-slate-400' }}">{!! $item['icon'] !!}</span>
                            <span data-admin-sidebar-label>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
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

    <div id="admin-sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/45 lg:hidden"></div>

    <div id="admin-main" class="flex min-h-screen flex-1 flex-col lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-md">
            <div class="flex items-center gap-3 px-4 py-5 sm:px-6 lg:px-8">
                <button id="admin-sidebar-open" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <button id="admin-sidebar-toggle" type="button" class="hidden h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:bg-slate-100 lg:inline-flex" aria-label="Toggle admin sidebar" title="Toggle sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-[0.32em] text-slate-400">Green Leaf Traders</p>
                    <h1 class="mt-1 truncate text-2xl font-black tracking-[-0.04em] text-slate-950 sm:text-[2rem]">{{ $title }}</h1>
                </div>

                <div class="hidden rounded-[1.35rem] border border-slate-200 bg-slate-50/90 px-5 py-3 text-right shadow-sm sm:block">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Today</p>
                    <p class="mt-1 text-sm font-black text-slate-950">{{ today()->format('d M Y') }}</p>
                </div>
            </div>

            <div class="border-t border-slate-100 px-4 py-4 sm:px-6 lg:px-8">
                @include('components.admin-dashboard-switcher')
            </div>

            @isset($actions)
                <div class="border-t border-slate-100 px-4 py-3 sm:px-6 lg:px-8">
                    <div class="flex flex-wrap items-center gap-2">
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

@if (count($mobileItems) > 0)
    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 pb-[max(env(safe-area-inset-bottom),0.5rem)] pt-2 backdrop-blur lg:hidden">
        <nav class="mx-auto grid max-w-xl grid-cols-4 gap-2">
            @foreach ($mobileItems as $item)
                <a href="{{ $item['href'] }}" class="flex min-h-[52px] items-center justify-center rounded-2xl px-2 text-center text-[11px] font-black uppercase tracking-[0.12em] transition {{ $item['active'] ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ \Illuminate\Support\Str::of($item['label'])->replace(' & ', ' ')->limit(10, '') }}
                </a>
            @endforeach
        </nav>
    </div>
@endif

@include('components.app-dialogs')

<script>
    (() => {
        const storageKey = 'admin-sidebar-state';
        const shell = document.getElementById('admin-layout-shell');
        const sidebar = document.getElementById('admin-sidebar');
        const main = document.getElementById('admin-main');
        const overlay = document.getElementById('admin-sidebar-overlay');
        const openButton = document.getElementById('admin-sidebar-open');
        const closeButton = document.getElementById('admin-sidebar-close');
        const collapseButton = document.getElementById('admin-sidebar-collapse');
        const toggleButton = document.getElementById('admin-sidebar-toggle');
        const labels = document.querySelectorAll('[data-admin-sidebar-label]');

        if (!shell || !sidebar || !main || !overlay || !openButton || !closeButton) {
            return;
        }

        const syncDesktopState = (state) => {
            const isCollapsed = state === 'collapsed';
            shell.dataset.sidebarState = state;

            if (window.innerWidth >= 1024) {
                sidebar.classList.toggle('lg:w-72', !isCollapsed);
                sidebar.classList.toggle('lg:w-24', isCollapsed);
                main.classList.toggle('lg:pl-72', !isCollapsed);
                main.classList.toggle('lg:pl-24', isCollapsed);
                labels.forEach((label) => {
                    label.classList.toggle('hidden', isCollapsed);
                });
            } else {
                sidebar.classList.remove('lg:w-24');
                sidebar.classList.add('lg:w-72');
                main.classList.remove('lg:pl-24');
                main.classList.add('lg:pl-72');
                labels.forEach((label) => {
                    label.classList.remove('hidden');
                });
            }
        };

        const setDesktopState = (state) => {
            localStorage.setItem(storageKey, state);
            syncDesktopState(state);
        };

        const openSidebar = () => {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        };

        const closeSidebar = () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        };

        openButton.addEventListener('click', openSidebar);
        closeButton.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);

        const toggleDesktopSidebar = () => {
            if (window.innerWidth < 1024) {
                openSidebar();

                return;
            }

            const nextState = shell.dataset.sidebarState === 'collapsed' ? 'expanded' : 'collapsed';
            setDesktopState(nextState);
        };

        collapseButton?.addEventListener('click', toggleDesktopSidebar);
        toggleButton?.addEventListener('click', toggleDesktopSidebar);

        window.addEventListener('resize', () => {
            syncDesktopState(localStorage.getItem(storageKey) === 'collapsed' ? 'collapsed' : 'expanded');

            if (window.innerWidth >= 1024) {
                overlay.classList.add('hidden');
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        });

        syncDesktopState(localStorage.getItem(storageKey) === 'collapsed' ? 'collapsed' : 'expanded');

        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('-translate-x-full');
        }
    })();
</script>
</body>
</html>
