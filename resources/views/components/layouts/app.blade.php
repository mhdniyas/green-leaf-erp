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
<body class="h-full bg-gray-50 font-sans antialiased">

<div id="app-container" class="flex h-full">

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
        <nav class="flex-1 overflow-y-auto py-4 space-y-3 px-3">

            {{-- Dashboard --}}
            <x-nav-item href="{{ route('dashboard') }}" icon="squares-2x2" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-nav-item>

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
                    class="sidebar-group-toggle w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all group cursor-pointer {{ $isInventoryActive ? 'text-white bg-brand-800/30' : 'text-brand-300 hover:bg-brand-800/60 hover:text-white' }}"
                    aria-expanded="{{ $isInventoryActive ? 'true' : 'false' }}"
                >
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                        </svg>
                        <span>Inventory</span>
                    </span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-200 chevron-icon {{ $isInventoryActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
                <div class="sidebar-group-items pl-4 pr-1 space-y-1 transition-all duration-200 {{ $isInventoryActive ? '' : 'hidden' }}">
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

            {{-- Purchasing Group --}}
            @if(
                auth()->user()->hasRole('purchasing-manager') ||
                auth()->user()->hasRole('purchase') ||
                auth()->user()->can('purchasing.supplier.view') ||
                auth()->user()->can('purchasing.order.view') ||
                auth()->user()->can('purchasing.grn.view') ||
                auth()->user()->can('viewAny', \App\Models\PurchaseInvoice::class)
            )
            @php
                $isPurchasingActive = request()->routeIs('purchasing.*') || request()->routeIs('requisitions.board') || request()->routeIs('requisitions.approved_board');
            @endphp
            <div class="sidebar-group space-y-1">
                <button
                    type="button"
                    class="sidebar-group-toggle w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all group cursor-pointer {{ $isPurchasingActive ? 'text-white bg-brand-800/30' : 'text-brand-300 hover:bg-brand-800/60 hover:text-white' }}"
                    aria-expanded="{{ $isPurchasingActive ? 'true' : 'false' }}"
                >
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                        </svg>
                        <span>Purchasing</span>
                    </span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-200 chevron-icon {{ $isPurchasingActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
                <div class="sidebar-group-items pl-4 pr-1 space-y-1 transition-all duration-200 {{ $isPurchasingActive ? '' : 'hidden' }}">
                    @if(auth()->user()->hasRole('purchasing-manager') || auth()->user()->hasRole('purchase') || auth()->user()->can('purchasing.order.approve'))
                    <x-nav-item href="{{ route('requisitions.board') }}" :active="request()->routeIs('requisitions.board')" :sub="true">
                        Requisition Board
                    </x-nav-item>
                    <x-nav-item href="{{ route('requisitions.approved_board') }}" :active="request()->routeIs('requisitions.approved_board')" :sub="true">
                        Approved Board
                    </x-nav-item>
                    @endif
                    @can('purchasing.supplier.view')
                    <x-nav-item href="{{ route('purchasing.suppliers.index') }}" :active="request()->routeIs('purchasing.suppliers.*')" :sub="true">
                        Suppliers
                    </x-nav-item>
                    @endcan
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
                    @can('viewAny', \App\Models\PurchaseInvoice::class)
                    <x-nav-item href="{{ route('purchasing.invoices.index') }}" :active="request()->routeIs('purchasing.invoices.*')" :sub="true">
                        Purchase Invoices
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
                    class="sidebar-group-toggle w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all group cursor-pointer {{ $isSalesActive ? 'text-white bg-brand-800/30' : 'text-brand-300 hover:bg-brand-800/60 hover:text-white' }}"
                    aria-expanded="{{ $isSalesActive ? 'true' : 'false' }}"
                >
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                        <span>Sales</span>
                    </span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-200 chevron-icon {{ $isSalesActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
                <div class="sidebar-group-items pl-4 pr-1 space-y-1 transition-all duration-200 {{ $isSalesActive ? '' : 'hidden' }}">
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
                    class="sidebar-group-toggle w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all group cursor-pointer {{ $isFinanceActive ? 'text-white bg-brand-800/30' : 'text-brand-300 hover:bg-brand-800/60 hover:text-white' }}"
                    aria-expanded="{{ $isFinanceActive ? 'true' : 'false' }}"
                >
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.03 0 1.9.693 2.166 1.638m-7.377 0A48.536 48.536 0 0112 3.75c0 .08-.004.16-.01.238m-2.886 0c.385.023.77.05 1.154.08m-3.456 0A48.108 48.108 0 002.25 6.11v10.39a2.25 2.25 0 002.25 2.25h3" />
                        </svg>
                        <span>Finance</span>
                    </span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-200 chevron-icon {{ $isFinanceActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
                <div class="sidebar-group-items pl-4 pr-1 space-y-1 transition-all duration-200 {{ $isFinanceActive ? '' : 'hidden' }}">
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
                    class="sidebar-group-toggle w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all group cursor-pointer {{ $isAdminActive ? 'text-white bg-brand-800/30' : 'text-brand-300 hover:bg-brand-800/60 hover:text-white' }}"
                    aria-expanded="{{ $isAdminActive ? 'true' : 'false' }}"
                >
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        <span>Admin</span>
                    </span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-200 chevron-icon {{ $isAdminActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
                <div class="sidebar-group-items pl-4 pr-1 space-y-1 transition-all duration-200 {{ $isAdminActive ? '' : 'hidden' }}">
                    @can('admin.user.view')
                    <x-nav-item href="{{ route('admin.users.index') }}" :active="request()->routeIs('admin.users.*')" :sub="true">
                        Users & Roles
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
                </div>
            </div>
            @endif

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
    <div class="flex-1 flex flex-col lg:ml-64 min-w-0 main-content-wrapper transition-all duration-300">

        {{-- Top bar --}}
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 h-14 flex items-center px-4 sm:px-6 gap-4">
            {{-- Collapse / open toggle --}}
            <button id="sidebar-open" class="-ml-1 p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg cursor-pointer">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            {{-- Page breadcrumb / title --}}
            <div class="min-w-0 flex-1">
                <h1 class="text-sm font-semibold text-gray-900 truncate">{{ $title }}</h1>
            </div>

            {{-- Header Actions Container --}}
            <div class="flex items-center gap-3 shrink-0 ml-auto">
                {{-- Theme Toggle Switch --}}
                <button
                    id="theme-toggle"
                    type="button"
                    class="p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-100 dark:hover:bg-gray-800 rounded-lg cursor-pointer focus:outline-none transition-colors duration-200"
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

                @if(isset($actions))
                    <div class="flex items-center gap-2">
                        {{ $actions }}
                    </div>
                @endif
            </div>
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
