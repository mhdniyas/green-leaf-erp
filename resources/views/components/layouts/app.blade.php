@props(['title' => 'Green Leaf ERP'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Green Leaf ERP</title>
    <meta name="description" content="Green Leaf ERP — Vegetable Trading & Distribution Management System">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-gray-50 font-sans antialiased">

<div class="flex h-full">

    {{-- ── Sidebar ─────────────────────────────────────────────── --}}
    <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 z-50 w-64 bg-brand-900 flex flex-col transition-transform duration-300 lg:translate-x-0 -translate-x-full"
        aria-label="Sidebar navigation"
    >
        {{-- Logo --}}
        <div class="flex items-center gap-3 px-6 py-5 border-b border-brand-800">
            <div class="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-white font-bold text-sm leading-none truncate">Green Leaf ERP</p>
                <p class="text-brand-400 text-[10px] font-medium tracking-wider uppercase mt-0.5">Trading Platform</p>
            </div>
            {{-- Mobile close --}}
            <button id="sidebar-close" class="lg:hidden ml-auto text-brand-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto py-4 space-y-1 px-3">

            {{-- Dashboard --}}
            <x-nav-item href="{{ route('dashboard') }}" icon="squares-2x2" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-nav-item>

            {{-- Inventory --}}
            <div class="pt-3 pb-1 px-3">
                <p class="text-brand-500 text-[10px] font-bold tracking-widest uppercase">Inventory</p>
            </div>
            <x-nav-item href="{{ route('inventory.products.index') }}" icon="cube" :active="request()->routeIs('inventory.products.*')">
                Products
            </x-nav-item>
            <x-nav-item href="{{ route('inventory.stock.index') }}" icon="chart-bar" :active="request()->routeIs('inventory.stock.*')">
                Stock Levels
            </x-nav-item>
            <x-nav-item href="{{ route('inventory.batches.index') }}" icon="inbox-arrow-down" :active="request()->routeIs('inventory.batches.*')">
                Batches & Sorting
            </x-nav-item>
            <x-nav-item href="{{ route('inventory.wastage.index') }}" icon="trash" :active="request()->routeIs('inventory.wastage.*')">
                Wastage Log
            </x-nav-item>

            {{-- Coming Soon --}}
            <div class="pt-3 pb-1 px-3">
                <p class="text-brand-500 text-[10px] font-bold tracking-widest uppercase">Purchasing</p>
            </div>
            <x-nav-item href="#" icon="shopping-cart" :active="false" disabled>
                Purchase Orders <span class="ml-auto text-[9px] bg-brand-800 text-brand-500 px-1.5 py-0.5 rounded-full">Soon</span>
            </x-nav-item>

            <div class="pt-3 pb-1 px-3">
                <p class="text-brand-500 text-[10px] font-bold tracking-widest uppercase">Sales</p>
            </div>
            <x-nav-item href="#" icon="receipt-percent" :active="false" disabled>
                Sales Orders <span class="ml-auto text-[9px] bg-brand-800 text-brand-500 px-1.5 py-0.5 rounded-full">Soon</span>
            </x-nav-item>

            <div class="pt-3 pb-1 px-3">
                <p class="text-brand-500 text-[10px] font-bold tracking-widest uppercase">Admin</p>
            </div>
            <x-nav-item href="#" icon="users" :active="false" disabled>
                Users & Roles <span class="ml-auto text-[9px] bg-brand-800 text-brand-500 px-1.5 py-0.5 rounded-full">Soon</span>
            </x-nav-item>

        </nav>

        {{-- User footer --}}
        <div class="border-t border-brand-800 p-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-brand-700 flex items-center justify-center shrink-0">
                    <span class="text-brand-300 text-xs font-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-white text-xs font-semibold truncate">{{ auth()->user()->name }}</p>
                    <p class="text-brand-400 text-[10px] truncate">{{ auth()->user()->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sign out" class="text-brand-400 hover:text-red-400 transition-colors p-1">
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
    <div class="flex-1 flex flex-col lg:ml-64 min-w-0">

        {{-- Top bar --}}
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 h-14 flex items-center px-4 sm:px-6 gap-4">
            {{-- Mobile hamburger --}}
            <button id="sidebar-open" class="lg:hidden -ml-1 p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            {{-- Page breadcrumb / title --}}
            <div class="min-w-0 flex-1">
                <h1 class="text-sm font-semibold text-gray-900 truncate">{{ $title }}</h1>
            </div>

            {{-- Right actions slot --}}
            @if(isset($actions))
                <div class="flex items-center gap-2 shrink-0">
                    {{ $actions }}
                </div>
            @endif
        </header>

        {{-- Flash messages --}}
        @if (session('success'))
        <div class="mx-4 sm:mx-6 mt-4 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3" role="alert">
            <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-green-800 text-sm">{{ session('success') }}</p>
        </div>
        @endif

        @if (session('error'))
        <div class="mx-4 sm:mx-6 mt-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3" role="alert">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <p class="text-red-800 text-sm">{{ session('error') }}</p>
        </div>
        @endif

        {{-- Page content --}}
        <main class="flex-1 p-4 sm:p-6">
            {{ $slot }}
        </main>
    </div>
</div>

<script>
    // Sidebar toggle for mobile
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const openBtn = document.getElementById('sidebar-open');
    const closeBtn = document.getElementById('sidebar-close');

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    }
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }

    openBtn?.addEventListener('click', openSidebar);
    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);
</script>
@stack('scripts')
</body>
</html>
