@php
    $purchaseManagerMobileNavItems = [
        [
            'label' => 'Req Board',
            'route' => 'requisitions.board',
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6M9 9.75h6M9 14.25h6M5.25 5.25h.008v.008H5.25V5.25zm0 4.5h.008v.008H5.25V9.75zm0 4.5h.008v.008H5.25V14.25zm-1.5-9A2.25 2.25 0 016 3h12a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25V5.25z" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Approved',
            'route' => 'requisitions.approved_board',
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 11-18 0 9 9 0 0118 0Z" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Orders',
            'route' => 'purchasing.orders.index',
            'icon' => '<svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>',
            'type' => 'center',
        ],
        [
            'label' => 'Receipts',
            'route' => 'purchasing.grns.index',
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3.75A.75.75 0 013.75 3h10.5a.75.75 0 01.53.22l5 5a.75.75 0 01.22.53v11.5a.75.75 0 01-.75.75H3.75a.75.75 0 01-.75-.75V3.75z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 12h7.5m-7.5 3h4.5" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Shop Inv',
            'route' => 'purchasing.shop-invoices.index',
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h6a2.25 2.25 0 012.25 2.25v12a2.25 2.25 0 01-2.25 2.25h-6A2.25 2.25 0 015.25 18V6A2.25 2.25 0 017.5 3.75z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 8.25h7.5M8.25 12h7.5m-7.5 3.75h4.5" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Prices',
            'route' => 'purchasing.prices.index',
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m3.75-9.75h-6a2.25 2.25 0 100 4.5h4.5a2.25 2.25 0 110 4.5h-6" /></svg>',
            'type' => 'link',
        ],
    ];
@endphp

<div class="fixed inset-x-0 bottom-4 z-40 px-4 lg:hidden">
    <nav class="relative mx-auto flex h-16 max-w-md items-center justify-around rounded-[2rem] border border-slate-100 bg-white/95 px-4 shadow-[0_-8px_30px_rgb(0,0,0,0.08),0_4px_20px_rgb(0,0,0,0.04)] backdrop-blur-md">
        @foreach ($purchaseManagerMobileNavItems as $item)
            @php
                $isActive = request()->routeIs($item['route']);

                if ($item['route'] === 'purchasing.orders.index') {
                    $isActive = request()->routeIs('purchasing.orders.*');
                }
            @endphp

            @if ($item['type'] === 'center')
                <div class="relative -mt-10">
                    <a
                        href="{{ route($item['route']) }}"
                        @class([
                            'relative flex h-16 w-16 items-center justify-center rounded-full border-[6px] border-slate-100 shadow-lg transition-all duration-300',
                            'bg-cyan-500 text-white hover:scale-105 hover:bg-cyan-600 active:scale-95' => $isActive,
                            'bg-cyan-500 text-white/95 hover:scale-105 hover:bg-cyan-500 active:scale-95' => ! $isActive,
                        ])
                    >
                        {!! $item['icon'] !!}
                    </a>
                    @if ($isActive)
                        <div class="absolute -bottom-4 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rounded-full bg-cyan-500"></div>
                    @endif
                </div>
            @else
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'relative flex h-12 w-12 flex-col items-center justify-center rounded-2xl transition-all duration-200',
                        'text-cyan-600' => $isActive,
                        'text-slate-400 hover:text-slate-600' => ! $isActive,
                    ])
                    title="{{ $item['label'] }}"
                >
                    {!! $item['icon'] !!}
                    @if ($isActive)
                        <div class="absolute bottom-0 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rounded-full bg-cyan-500"></div>
                    @endif
                </a>
            @endif
        @endforeach
    </nav>
</div>
