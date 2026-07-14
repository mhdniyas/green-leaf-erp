@props(['title' => 'Accounting Dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Green Leaf Traders</title>
    <meta name="description" content="Green Leaf Traders — Accounting Dashboard">
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
    $navDate = request('date', today()->toDateString());
    $currentShop = request()->route('shop');
    $canManageOwnedShops = \App\Support\AccountingAccess::canManageOwnedShops($currentUser);
    $canManagePurchaserCash = \App\Support\AccountingAccess::canManagePurchaserCash($currentUser);
    $sidebarItems = [
        [
            'label' => 'Dashboard',
            'href' => route('admin.accounting.index', ['date' => $navDate]),
            'active' => request()->routeIs('admin.accounting.index'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h7.5v7.5h-7.5v-7.5Zm9 0h7.5v10.5h-7.5V3.75Zm0 12h7.5v4.5h-7.5v-4.5Zm-9-3h7.5v7.5h-7.5v-7.5Z" /></svg>',
        ],
        [
            'label' => 'Daily Sale Report',
            'href' => route('admin.accounting.daily-sales', ['date' => request('date', today()->toDateString())]),
            'active' => request()->routeIs('admin.accounting.daily-sales'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 2 5-6" /></svg>',
        ],
        [
            'label' => 'Cash Flow Report',
            'href' => route('admin.accounting.cash-flow', ['date' => request('date', today()->toDateString())]),
            'active' => request()->routeIs('admin.accounting.cash-flow'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15l3-3 3 2 4-6M8 8h.01M8 12h.01" /></svg>',
        ],
        [
            'label' => 'Calendar',
            'href' => route('admin.accounting.cash-flow.calendar', ['date' => request('date', today()->toDateString())]),
            'active' => request()->routeIs('admin.accounting.cash-flow.calendar'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v11.5a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V6.75a1.5 1.5 0 0 1 1.5-1.5Z" /></svg>',
        ],
        [
            'label' => 'Vendor Reports',
            'href' => route('admin.accounting.vendor-reports', ['date' => request('date', today()->toDateString())]),
            'active' => request()->routeIs('admin.accounting.vendor-reports'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8m4-4H8m-4 8h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" /></svg>',
        ],
    ];

    if ($canManageOwnedShops) {
        $sidebarItems[] = [
            'label' => 'Owned Shops',
            'href' => route('admin.accounting.owned-shops.index'),
            'active' => request()->routeIs('admin.accounting.owned-shops.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M4.5 21V8.25m15 12.75V8.25M9 21V3.75h6V21M7.5 6h9" /></svg>',
        ];
    }

    if ($canManagePurchaserCash) {
        $sidebarItems[] = [
            'label' => 'Purchasers',
            'href' => route('admin.accounting.purchasers.index'),
            'active' => request()->routeIs('admin.accounting.purchasers.*'),
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>',
        ];
    }

    if ($canManageOwnedShops && $currentShop instanceof \App\Models\Shop) {
        $sidebarItems[] = [
            'label' => 'Current Shop',
            'href' => route('admin.accounting.owned-shops.show', ['shop' => $currentShop, 'date' => $navDate]),
            'active' => false,
            'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a8.966 8.966 0 0 1-5.657-2.004C4.233 17.228 3 14.76 3 12s1.233-5.228 3.343-6.996A8.966 8.966 0 0 1 12 3c2.212 0 4.236.8 5.657 2.004C19.767 6.772 21 9.24 21 12s-1.233 5.228-3.343 6.996A8.966 8.966 0 0 1 12 21Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75h4.5v4.5h-4.5z" /></svg>',
        ];
    }

    $mobileItems = [
        ['label' => 'Home', 'href' => route('admin.accounting.index', ['date' => $navDate]), 'active' => request()->routeIs('admin.accounting.index')],
        ['label' => 'Sales', 'href' => route('admin.accounting.daily-sales', ['date' => request('date', today()->toDateString())]), 'active' => request()->routeIs('admin.accounting.daily-sales')],
        ['label' => 'Cash', 'href' => route('admin.accounting.cash-flow', ['date' => request('date', today()->toDateString())]), 'active' => request()->routeIs('admin.accounting.cash-flow')],
        ['label' => 'Vendor', 'href' => route('admin.accounting.vendor-reports', ['date' => request('date', today()->toDateString())]), 'active' => request()->routeIs('admin.accounting.vendor-reports')],
    ];

    if ($canManageOwnedShops) {
        $mobileItems[] = ['label' => 'Shops', 'href' => route('admin.accounting.owned-shops.index'), 'active' => request()->routeIs('admin.accounting.owned-shops.*')];
    }
@endphp

<div id="accounting-layout-shell" class="min-h-screen lg:flex" data-sidebar-state="expanded">
    <aside id="accounting-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-[width,transform] duration-300 lg:translate-x-0">
        <div class="border-b border-slate-200 px-5 py-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m0 0c-2.21 0-4-1.343-4-3s1.79-3 4-3 4-1.343 4-3-1.79-3-4-3m0 12c2.21 0 4-1.343 4-3" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p data-accounting-sidebar-label class="truncate text-base font-black text-slate-950">Accounting</p>
                    <p data-accounting-sidebar-label class="mt-1 text-[11px] font-black uppercase tracking-[0.2em] text-emerald-700">Admin Desk</p>
                </div>
                <button id="accounting-sidebar-collapse" type="button" class="hidden rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:inline-flex" aria-label="Collapse accounting sidebar" title="Collapse sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <button id="accounting-sidebar-close" class="ml-auto rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <a href="{{ route('admin.overview') }}" class="mt-5 flex items-center justify-between rounded-[1.35rem] bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800">
                <span data-accounting-sidebar-label>Admin Panel</span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5H19.5V10.5M10.5 13.5 19.5 4.5M18 13.5V19.5H4.5V6H10.5" />
                </svg>
            </a>

            <div class="mt-4 rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Signed In</p>
                <p data-accounting-sidebar-label class="mt-2 truncate text-sm font-black text-slate-950">{{ $currentUser?->name }}</p>
                <p data-accounting-sidebar-label class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $currentUser?->email }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-2 overflow-y-auto px-4 py-5">
            @foreach($sidebarItems as $item)
                <a href="{{ $item['href'] }}" title="{{ $item['label'] }}" class="flex items-center gap-3 rounded-[1.2rem] px-4 py-3 text-sm font-black transition {{ $item['active'] ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">
                    <span class="{{ $item['active'] ? 'text-emerald-700' : 'text-slate-400' }}">{!! $item['icon'] !!}</span>
                    <span data-accounting-sidebar-label>{{ $item['label'] }}</span>
                </a>
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

    <div id="accounting-sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/45 lg:hidden"></div>

    <div id="accounting-main" class="flex min-h-screen flex-1 flex-col lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-md">
            <div class="flex items-center gap-3 px-4 py-5 sm:px-6 lg:px-8">
                <button id="accounting-sidebar-open" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <button id="accounting-sidebar-toggle" type="button" class="hidden h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:bg-slate-100 lg:inline-flex" aria-label="Toggle accounting sidebar" title="Toggle sidebar">
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
        @foreach($mobileItems as $item)
            <a href="{{ $item['href'] }}" class="flex min-h-[52px] items-center justify-center rounded-2xl px-2 text-center text-[11px] font-black uppercase tracking-[0.12em] transition {{ $item['active'] ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</div>

@include('components.app-dialogs')

<script>
    (() => {
        const storageKey = 'accounting-sidebar-state';
        const shell = document.getElementById('accounting-layout-shell');
        const sidebar = document.getElementById('accounting-sidebar');
        const main = document.getElementById('accounting-main');
        const overlay = document.getElementById('accounting-sidebar-overlay');
        const openButton = document.getElementById('accounting-sidebar-open');
        const closeButton = document.getElementById('accounting-sidebar-close');
        const collapseButton = document.getElementById('accounting-sidebar-collapse');
        const toggleButton = document.getElementById('accounting-sidebar-toggle');
        const labels = document.querySelectorAll('[data-accounting-sidebar-label]');

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
                return;
            }

            setDesktopState(shell.dataset.sidebarState === 'collapsed' ? 'expanded' : 'collapsed');
        };

        collapseButton?.addEventListener('click', toggleDesktopSidebar);
        toggleButton?.addEventListener('click', toggleDesktopSidebar);

        syncDesktopState(localStorage.getItem(storageKey) === 'collapsed' ? 'collapsed' : 'expanded');
        window.addEventListener('resize', () => {
            syncDesktopState(localStorage.getItem(storageKey) === 'collapsed' ? 'collapsed' : 'expanded');
        });
    })();
</script>
</body>
</html>
