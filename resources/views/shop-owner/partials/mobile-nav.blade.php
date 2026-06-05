@php
    $mobileNavItems = [
        ['label' => 'Dashboard', 'route' => 'shop-owner.dashboard'],
        ['label' => 'Orders', 'route' => 'shop-owner.orders.index'],
        ['label' => 'Deliveries', 'route' => 'shop-owner.deliveries.index'],
        ['label' => 'Finance', 'route' => 'shop-owner.finance.index'],
        ['label' => 'Order History', 'route' => 'shop-owner.orders.history'],
    ];
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white lg:hidden">
    <div class="grid grid-cols-5 gap-1 px-2 py-2">
        @foreach ($mobileNavItems as $item)
            <a
                href="{{ route($item['route']) }}"
                @class([
                    'rounded-2xl px-2 py-3 text-center text-[11px] font-black transition',
                    'bg-emerald-50 text-emerald-800' => request()->routeIs($item['route']),
                    'text-slate-500 hover:bg-slate-50 hover:text-slate-800' => ! request()->routeIs($item['route']),
                ])
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
