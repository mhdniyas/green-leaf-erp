<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#059669">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Green Leaf Shop">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>@yield('title', 'Shop Owner') — Green Leaf Traders</title>
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    @php
        $shopOwnerAssets = app()->runningUnitTests()
            ? ['resources/css/app.css', 'resources/js/app.js']
            : ['resources/css/shop-owner/app.css', 'resources/js/shop-owner/app.js'];
    @endphp
    @vite($shopOwnerAssets)
    <style>
        [x-cloak] { display: none !important; }
        /* Remove browser default spinner arrows on number input fields */
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none !important;
            margin: 0 !important;
        }
        input[type="number"] {
            -moz-appearance: textfield !important;
            appearance: textfield !important;
        }
    </style>
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased">
    <div id="shop-owner-layout-shell" class="min-h-screen lg:flex" data-sidebar-state="expanded">
        @include('shop-owner.partials.sidebar')

        <div id="shop-owner-main" class="flex min-h-screen flex-1 flex-col lg:pl-72">
            @include('shop-owner.partials.topbar')

            <main class="flex-1 px-4 pb-24 pt-24 sm:px-6 lg:px-8 lg:pb-8 lg:pt-6">
                @include('shop-owner.partials.breadcrumbs')
                @include('shop-owner.partials.flash-messages')
                <x-impersonation-banner />
                @include('shop-owner.partials.page-header')

                @yield('content')
            </main>
        </div>
    </div>

    <x-page-jump-controls bottom-class="bottom-24 lg:bottom-6" />

    @include('shop-owner.partials.mobile-nav')
    <button
        type="button"
        data-pwa-install-button
        hidden
        class="fixed bottom-24 left-1/2 z-[70] inline-flex -translate-x-1/2 items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-2.5 text-xs font-black text-emerald-700 shadow-xl shadow-slate-900/10 transition hover:border-emerald-300 hover:bg-emerald-50 lg:bottom-6"
    >
        <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" />
        </svg>
        <span data-pwa-install-label>Add to Home Screen</span>
    </button>
    @include('components.app-dialogs')
    <x-global-footer />
    <x-sidebar-state-script
        storage-key="shop-owner-sidebar-state"
        shell-id="shop-owner-layout-shell"
        sidebar-id="shop-owner-sidebar"
        main-id="shop-owner-main"
        overlay-id="shop-owner-mobile-sidebar-overlay"
        open-button-id="shop-owner-mobile-sidebar-open"
        close-button-id="shop-owner-mobile-sidebar-close"
    />
    @stack('scripts')
    <x-global-loader />
</body>
</html>
