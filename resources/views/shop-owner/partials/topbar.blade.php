@php
    $activeShopResolver = app(\App\Support\ShopOwner\ActiveShopResolver::class);
    $authorizedShops = $activeShopResolver->authorizedShops(auth()->user());
    $activeShop = $authorizedShops->isNotEmpty() ? $activeShopResolver->resolve(request()) : auth()->user()?->shop;
    $shopOwnerInitial = strtoupper(substr(auth()->user()->name, 0, 1));
@endphp

<header class="fixed top-4 inset-x-4 z-30 mx-auto max-w-md bg-white/95 backdrop-blur-md shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-slate-100 rounded-[1.5rem] px-4 py-2.5 flex items-center justify-between lg:sticky lg:top-0 lg:left-0 lg:right-0 lg:inset-x-0 lg:max-w-none lg:mx-0 lg:rounded-none lg:border-0 lg:border-b lg:border-slate-200 lg:bg-white/95 lg:backdrop-blur lg:shadow-none lg:px-8 lg:py-4">
    <div class="flex items-center justify-between w-full gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                id="shop-owner-mobile-sidebar-open"
                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:border-slate-300 hover:bg-white lg:hidden"
                aria-label="Open shop owner sidebar"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Green Leaf Traders</p>
                <p class="mt-0.5 text-xs sm:text-sm font-bold text-slate-800 truncate max-w-[150px] sm:max-w-none" title="{{ $activeShop?->name ?? 'Shop Owner' }}">
                    {{ $activeShop?->name ?? 'Shop Owner' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @if($authorizedShops->count() > 1)
                <form method="GET" action="{{ url()->current() }}" class="hidden lg:block">
                    @foreach(request()->except('shop') as $key => $value)
                        @if(is_array($value))
                            @foreach($value as $nestedValue)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $nestedValue }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach

                    <label class="block">
                        <span class="sr-only">Active shop</span>
                        <select
                            name="shop"
                            onchange="this.form.submit()"
                            class="h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-800 outline-none transition hover:border-slate-300 hover:bg-white"
                        >
                            @foreach($authorizedShops as $shop)
                                <option value="{{ $shop->code }}" @selected($activeShop?->id === $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
            @endif

            <div class="text-right">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</p>
                <p class="mt-0.5 text-xs sm:text-sm font-bold text-slate-800">
                    {{ now()->format('d F Y') }}
                </p>
            </div>

            <details class="relative">
                <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-800 transition hover:border-slate-300 hover:bg-white">
                    {{ $shopOwnerInitial }}
                </summary>

                <div class="absolute right-0 top-14 w-52 rounded-3xl border border-slate-200 bg-white p-2 shadow-xl">
                    <div class="border-b border-slate-100 px-3 py-2">
                        <p class="truncate text-sm font-black text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="mt-1 truncate text-[11px] font-semibold text-slate-500">{{ auth()->user()->email }}</p>
                    </div>

                    <div class="mt-2 space-y-1">
                        <a href="{{ route('profile.show') }}" class="flex items-center rounded-2xl px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100">
                            Profile Update
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center rounded-2xl px-3 py-2 text-left text-sm font-bold text-red-600 transition hover:bg-red-50">
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </details>
        </div>
    </div>
</header>
