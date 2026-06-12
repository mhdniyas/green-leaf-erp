@php
    $mobileNavItems = [
        [
            'label' => 'Dashboard',
            'route' => 'shop-owner.dashboard',
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>',
        ],
        [
            'label' => 'Orders',
            'route' => 'shop-owner.orders.index',
            'icon' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22V12" /><path d="M12 12c-2-2.67-4.5-4-7.5-4s-3.5 1-3.5 3c0 3 2.5 5 7.5 5h3.5Z" /><path d="M12 12c2-2.67 4.5-4 7.5-4s-3.5 1-3.5 3c0 3-2.5 5-7.5 5H12Z" /><path d="M12 12c-1-3.5-2-6.5-.5-9.5 2.5 1.5 2.5 5.5.5 9.5Z" /></svg>',
        ],
        [
            'label' => 'Deliveries',
            'route' => 'shop-owner.deliveries.index',
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0" /></svg>',
        ],
        [
            'label' => 'Finance',
            'route' => 'shop-owner.finance.index',
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>',
        ],
    ];
@endphp

<div class="fixed inset-x-0 bottom-5 z-50 px-5 lg:hidden">
    <nav class="mx-auto flex h-[60px] max-w-md items-center gap-1 rounded-[2rem] border border-slate-100 bg-white/96 px-2 shadow-[0_8px_40px_rgba(0,0,0,0.10),0_2px_12px_rgba(0,0,0,0.06)] backdrop-blur-xl">
        @foreach ($mobileNavItems as $item)
            @php
                $isActive = request()->routeIs($item['route']);
                if ($item['route'] === 'shop-owner.orders.index') {
                    $isActive = request()->routeIs('shop-owner.orders.*') && !request()->routeIs('shop-owner.orders.history');
                }
            @endphp

            <a
                href="{{ route($item['route']) }}"
                @class([
                    'relative flex h-11 flex-1 items-center justify-center gap-1.5 rounded-[1.25rem] px-2 font-bold transition-all duration-200',
                    'bg-cyan-500 text-white shadow-sm' => $isActive,
                    'bg-transparent text-slate-400 hover:text-slate-600' => ! $isActive,
                ])
                title="{{ $item['label'] }}"
            >
                <span class="shrink-0 [&_svg]:h-[18px] [&_svg]:w-[18px]">
                    {!! $item['icon'] !!}
                </span>
                <span @class([
                    'whitespace-nowrap text-[10px] font-black',
                    'hidden' => ! $isActive,
                ])>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
