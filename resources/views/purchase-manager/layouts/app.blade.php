@push('styles')
    @php
        $purchaseManagerAssets = app()->runningUnitTests()
            ? ['resources/css/app.css', 'resources/js/app.js']
            : ['resources/css/purchase-manager/app.css', 'resources/js/purchase-manager/app.js'];
    @endphp
    @vite($purchaseManagerAssets)
@endpush

<x-layouts.app :title="trim($__env->yieldContent('title', 'Purchase Manager'))">
    <div class="px-4 py-6 sm:px-0">
        @include('purchase-manager.partials.breadcrumbs')
        @include('purchase-manager.partials.page-header')

        @yield('content')
    </div>
</x-layouts.app>
