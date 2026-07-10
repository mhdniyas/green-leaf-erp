<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Shop Owner') — Green Leaf Traders</title>
    @php
        $shopOwnerAssets = app()->runningUnitTests()
            ? ['resources/css/app.css', 'resources/js/app.js']
            : ['resources/css/shop-owner/app.css', 'resources/js/shop-owner/app.js'];
    @endphp
    @vite($shopOwnerAssets)
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        @include('shop-owner.partials.sidebar')

        <div class="flex min-h-screen flex-1 flex-col lg:pl-72">
            @include('shop-owner.partials.topbar')

            <main class="flex-1 px-4 pb-24 pt-24 sm:px-6 lg:px-8 lg:pb-8 lg:pt-6">
                @include('shop-owner.partials.breadcrumbs')
                @include('shop-owner.partials.flash-messages')
                @include('shop-owner.partials.page-header')

                @yield('content')
            </main>
        </div>
    </div>

    <x-page-jump-controls bottom-class="bottom-24 lg:bottom-6" />

    @include('shop-owner.partials.mobile-nav')
    @include('components.app-dialogs')
    <x-global-footer />
    <script>
        (() => {
            const sidebar = document.getElementById('shop-owner-mobile-sidebar');
            const openButton = document.getElementById('shop-owner-mobile-sidebar-open');
            const closeButton = document.getElementById('shop-owner-mobile-sidebar-close');
            const overlay = document.getElementById('shop-owner-mobile-sidebar-overlay');

            if (!sidebar || !openButton) {
                return;
            }

            const openSidebar = () => {
                sidebar.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            };

            const closeSidebar = () => {
                sidebar.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            openButton.addEventListener('click', openSidebar);
            closeButton?.addEventListener('click', closeSidebar);
            overlay?.addEventListener('click', closeSidebar);

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !sidebar.classList.contains('hidden')) {
                    closeSidebar();
                }
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
