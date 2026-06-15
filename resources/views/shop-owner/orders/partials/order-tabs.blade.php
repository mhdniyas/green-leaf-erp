@php
    $hasTomorrowOrder = isset($tomorrowOrder) && $tomorrowOrder;
    $tomorrowLabel = $hasTomorrowOrder ? 'Request Items' : 'Marketplace';
@endphp
<div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
    @include('shop-owner.components.action-button', [
        'href' => route('shop-owner.orders.index'),
        'label' => 'Cart',
        'classes' => (request()->routeIs('shop-owner.orders.index') ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800') . ' justify-center w-full sm:w-auto text-center'
    ])
    @include('shop-owner.components.action-button', [
        'href' => route('shop-owner.orders.create'),
        'label' => $tomorrowLabel,
        'classes' => (request()->routeIs('shop-owner.orders.create') ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800') . ' justify-center w-full sm:w-auto text-center'
    ])
    <div class="col-span-2 sm:col-span-1">
        @include('shop-owner.components.action-button', [
            'href' => route('shop-owner.orders.history'),
            'label' => 'Approval History',
            'classes' => (request()->routeIs('shop-owner.orders.history') ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800') . ' justify-center w-full sm:w-auto text-center'
        ])
    </div>
</div>
