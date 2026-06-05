<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Purchase Manager') — Green Leaf ERP</title>
    @php
        $purchaseManagerAssets = app()->runningUnitTests()
            ? ['resources/css/app.css', 'resources/js/app.js']
            : ['resources/css/purchase-manager/app.css', 'resources/js/purchase-manager/app.js'];
    @endphp
    @vite($purchaseManagerAssets)
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        @include('purchase-manager.partials.sidebar')

        <div class="flex min-h-screen flex-1 flex-col lg:pl-72">
            @include('purchase-manager.partials.topbar')

            <main class="flex-1 px-4 pb-24 pt-6 sm:px-6 lg:px-8 lg:pb-8">
                @include('purchase-manager.partials.breadcrumbs')
                @include('purchase-manager.partials.flash-messages')
                @include('purchase-manager.partials.page-header')

                @yield('content')
            </main>
        </div>
    </div>

    @include('purchase-manager.partials.mobile-nav')
</body>
</html>
