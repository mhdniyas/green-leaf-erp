@php
    $purchaseManagerMobileNavItems = [
        ['label' => 'Orders', 'route' => 'purchasing.orders.index'],
        ['label' => 'Receipts', 'route' => 'purchasing.grns.index'],
        ['label' => 'Invoices', 'route' => 'purchasing.invoices.index'],
        ['label' => 'Vendors', 'route' => 'purchasing.suppliers.index'],
    ];
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white lg:hidden">
    <div class="grid grid-cols-4 gap-1 px-2 py-2">
        @foreach ($purchaseManagerMobileNavItems as $item)
            <a
                href="{{ route($item['route']) }}"
                @class([
                    'rounded-2xl px-2 py-3 text-center text-[11px] font-black transition',
                    'bg-cyan-50 text-cyan-800' => request()->routeIs($item['route']) || ($item['route'] === 'purchasing.orders.index' && request()->routeIs('purchasing.orders.create', 'purchasing.orders.edit', 'purchasing.orders.show')),
                    'text-slate-500 hover:bg-slate-50 hover:text-slate-800' => ! (request()->routeIs($item['route']) || ($item['route'] === 'purchasing.orders.index' && request()->routeIs('purchasing.orders.create', 'purchasing.orders.edit', 'purchasing.orders.show'))),
                ])
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
