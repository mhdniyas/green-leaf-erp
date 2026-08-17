@extends('admin.cashbook.layouts.app')

@section('title', 'Products Marketplace & Daily Prices — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="store" class="w-5 h-5 text-emerald-600"></i> Products Marketplace
@endsection

@section('header_subtitle')
    Daily product prices, board rates &amp; marketplace catalog for shops.
@endsection

@php
    $sortLabels = [
        'code_asc' => 'Code wise',
        'price_desc' => 'Price High to Low',
        'price_asc' => 'Low to High',
    ];
    $currentSortLabel = $sortLabels[$sort ?? 'code_asc'] ?? 'Code wise';
@endphp

@section('content')
    <div class="mx-auto max-w-5xl space-y-4">
        <!-- Top Fintech Header & Shop Switcher -->
        <div class="flex items-center justify-between gap-2 pt-1 border-b border-slate-100 pb-3">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5 sm:gap-2">
                    <h1 class="text-xl sm:text-3xl font-black tracking-tight text-slate-900 truncate">Products</h1>
                    <span class="px-1.5 py-0.5 text-[9px] sm:text-[10px] font-black uppercase tracking-wider rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200 shrink-0">Group {{ $shopGroup }}</span>
                </div>
                <p class="text-[10px] sm:text-xs font-bold text-slate-500 mt-0.5 truncate">Daily approved selling prices</p>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                <x-export-toolbar
                    title="Products Marketplace"
                    align="right"
                />

                <!-- Custom Tailwind Shop Selector Dropdown -->
                <div x-data="{ open: false }" class="relative inline-block text-left">
                    <button @click="open = !open" type="button" class="inline-flex items-center justify-between gap-1.5 sm:gap-2 h-9 px-2.5 sm:px-3.5 rounded-2xl bg-white border border-slate-200/90 text-xs font-black text-slate-800 shadow-2xs hover:bg-slate-50 focus:outline-none transition-all cursor-pointer">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <i data-lucide="store" class="w-3.5 h-3.5 text-emerald-600 shrink-0"></i>
                            <span class="truncate max-w-[80px] sm:max-w-[140px]">{{ $currentShop ? ($currentShop->name ?: 'Shop #'.$currentShop->shop_id) : 'All Outlets' }}</span>
                        </div>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': open }"></i>
                    </button>

                    <div x-show="open" @click.away="open = false" x-cloak
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-56 rounded-2xl bg-white p-1.5 shadow-xl ring-1 ring-black/5 z-50 space-y-0.5">
                        <div class="px-3 py-1.5 text-[10px] font-black uppercase text-slate-400 tracking-wider">Select Outlet Pricing</div>
                        @foreach($shops as $s)
                            <a href="{{ route('admin.cashbook.reports.products', array_merge(request()->except('shop_id'), ['shop_id' => $s->shop_id])) }}"
                                class="block rounded-xl px-3 py-2 text-xs font-bold transition-all {{ ($currentShop->shop_id ?? null) == $s->shop_id ? 'bg-slate-900 text-white font-black' : 'text-slate-700 hover:bg-slate-100' }}">
                                {{ $s->name ?: 'Shop #'.$s->shop_id }} {{ $s->code ? '('.$s->code.')' : '' }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <section class="rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 shadow-xs">
            <form id="admin-product-filter-form" method="GET" action="{{ route('admin.cashbook.reports.products') }}" class="space-y-2.5">
                @if($currentShop)
                    <input type="hidden" name="shop_id" value="{{ $currentShop->shop_id }}">
                @endif

                <!-- Row 1: Search Bar -->
                <div class="relative w-full">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </div>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search product name or SKU..."
                        class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-24 text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none transition shadow-2xs"
                    >
                    <div class="absolute inset-y-0 right-1 flex items-center gap-1 pr-1">
                        @if ($search || $categoryId || $selectedDate !== today()->toDateString() || ($sort ?? 'code_asc') !== 'code_asc')
                            <a href="{{ route('admin.cashbook.reports.products', $currentShop ? ['shop_id' => $currentShop->shop_id] : []) }}" class="h-7 px-2 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white text-[10px] font-bold text-slate-500 hover:bg-slate-100 transition" title="Reset filters">
                                Reset
                            </a>
                        @endif
                        <button type="submit" class="h-7 px-3 rounded-lg bg-slate-900 text-[10px] font-black text-white hover:bg-slate-800 transition cursor-pointer">
                            Search
                        </button>
                    </div>
                </div>

                <!-- Row 2: Date, Category, Sort Dropdowns -->
                <div class="grid grid-cols-3 gap-2 items-center">
                    <!-- Business Date -->
                    <div class="relative">
                        <input
                            type="date"
                            id="date"
                            name="date"
                            value="{{ $selectedDate }}"
                            onchange="this.form.submit()"
                            class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-[11px] font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none transition shadow-2xs cursor-pointer"
                        >
                    </div>

                    <!-- Category Select Dropdown -->
                    <div x-data="{
                        open: false,
                        selectedId: '{{ $categoryId ?? '' }}',
                        selectedName: '{{ $categories->firstWhere('id', $categoryId)?->name ?? 'All Categories' }}',
                        selectCategory(id, name) {
                            this.selectedId = id;
                            this.selectedName = name;
                            this.open = false;
                            $nextTick(() => {
                                document.getElementById('admin-product-filter-form').submit();
                            });
                        }
                    }" class="relative w-full">
                        <input type="hidden" name="category_id" :value="selectedId">
                        
                        <button
                            type="button"
                            @click="open = !open"
                            class="w-full h-9 px-2.5 rounded-xl bg-slate-50 border border-slate-200 text-[11px] font-bold text-slate-800 shadow-2xs hover:bg-slate-100/70 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all flex items-center justify-between gap-1 cursor-pointer"
                        >
                            <span class="truncate" x-text="selectedName">{{ $categories->firstWhere('id', $categoryId)?->name ?? 'All Categories' }}</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div
                            x-show="open"
                            @click.away="open = false"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute left-0 mt-1.5 w-48 sm:w-56 rounded-2xl bg-white p-1.5 shadow-xl ring-1 ring-black/5 z-50 space-y-0.5 max-h-60 overflow-y-auto custom-scrollbar"
                            style="display: none;"
                        >
                            <button
                                type="button"
                                @click="selectCategory('', 'All Categories')"
                                class="w-full text-left rounded-xl px-3 py-2 text-xs font-bold transition-all flex items-center justify-between cursor-pointer"
                                :class="selectedId === '' ? 'bg-slate-900 text-white font-black' : 'text-slate-700 hover:bg-slate-100'"
                            >
                                <span>All Categories</span>
                            </button>
                            @foreach($categories as $cat)
                                <button
                                    type="button"
                                    @click="selectCategory('{{ $cat->id }}', '{{ e($cat->name) }}')"
                                    class="w-full text-left rounded-xl px-3 py-2 text-xs font-bold transition-all flex items-center justify-between cursor-pointer"
                                    :class="selectedId == '{{ $cat->id }}' ? 'bg-slate-900 text-white font-black' : 'text-slate-700 hover:bg-slate-100'"
                                >
                                    <span>{{ $cat->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Sort Dropdown -->
                    <div x-data="{
                        open: false,
                        selectedSort: '{{ $sort ?? 'code_asc' }}',
                        selectedSortLabel: '{{ $currentSortLabel }}',
                        selectSort(value, label) {
                            this.selectedSort = value;
                            this.selectedSortLabel = label;
                            this.open = false;
                            $nextTick(() => {
                                document.getElementById('admin-product-filter-form').submit();
                            });
                        }
                    }" class="relative w-full">
                        <input type="hidden" name="sort" :value="selectedSort">
                        
                        <button
                            type="button"
                            @click="open = !open"
                            class="w-full h-9 px-2.5 rounded-xl bg-slate-50 border border-slate-200 text-[11px] font-bold text-slate-800 shadow-2xs hover:bg-slate-100/70 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all flex items-center justify-between gap-1 cursor-pointer"
                        >
                            <span class="truncate" x-text="selectedSortLabel">{{ $currentSortLabel }}</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div
                            x-show="open"
                            @click.away="open = false"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 sm:left-0 mt-1.5 w-48 sm:w-56 origin-top-right sm:origin-top-left rounded-2xl bg-white p-1.5 shadow-xl ring-1 ring-black/5 z-50 space-y-0.5"
                            style="display: none;"
                        >
                            <button
                                type="button"
                                @click="selectSort('code_asc', 'Code wise')"
                                class="w-full text-left rounded-xl px-3 py-2 text-xs font-bold transition-all flex items-center justify-between cursor-pointer"
                                :class="selectedSort === 'code_asc' ? 'bg-slate-900 text-white font-black' : 'text-slate-700 hover:bg-slate-100'"
                            >
                                <span>Code wise</span>
                            </button>
                            <button
                                type="button"
                                @click="selectSort('price_desc', 'Price High to Low')"
                                class="w-full text-left rounded-xl px-3 py-2 text-xs font-bold transition-all flex items-center justify-between cursor-pointer"
                                :class="selectedSort === 'price_desc' ? 'bg-slate-900 text-white font-black' : 'text-slate-700 hover:bg-slate-100'"
                            >
                                <span>Price High to Low</span>
                            </button>
                            <button
                                type="button"
                                @click="selectSort('price_asc', 'Low to High')"
                                class="w-full text-left rounded-xl px-3 py-2 text-xs font-bold transition-all flex items-center justify-between cursor-pointer"
                                :class="selectedSort === 'price_asc' ? 'bg-slate-900 text-white font-black' : 'text-slate-700 hover:bg-slate-100'"
                            >
                                <span>Low to High</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <!-- Publication Status Alert Banner -->
        @if ($isPublished)
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-3 sm:p-4 text-xs font-bold text-emerald-900 flex items-center justify-between gap-2 shadow-2xs">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex h-6 w-6 sm:h-7 sm:w-7 items-center justify-center rounded-full bg-emerald-500 text-white shrink-0 shadow-xs">
                        <i data-lucide="check" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="font-black text-emerald-950 text-xs sm:text-sm leading-tight">
                            Prices Published for {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('d M Y') }}
                        </p>
                        <p class="text-[10px] font-semibold text-emerald-700 mt-0.5 leading-tight">
                            Official price list published by purchasing department.
                        </p>
                    </div>
                </div>
                <span class="rounded-full bg-emerald-200/90 px-2.5 py-1 text-[9px] sm:text-[10px] font-black uppercase text-emerald-950 shrink-0">
                    Published
                </span>
            </div>
        @else
            <div class="rounded-2xl border border-amber-200 bg-amber-50/90 p-3 sm:p-4 text-xs font-bold text-amber-900 flex items-center justify-between gap-2 shadow-2xs">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex h-6 w-6 sm:h-7 sm:w-7 items-center justify-center rounded-full bg-amber-500 text-white shrink-0 shadow-xs">
                        <i data-lucide="clock" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="font-black text-amber-950 text-xs sm:text-sm leading-tight">
                            Daily Prices Updating for {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('d M Y') }}
                        </p>
                        <p class="text-[10px] font-semibold text-amber-800 mt-0.5 leading-tight">
                            Purchasing is currently finalizing today's prices. Prices below are carried forward/reference.
                        </p>
                    </div>
                </div>
                <span class="rounded-full bg-amber-200/90 px-2.5 py-1 text-[9px] sm:text-[10px] font-black uppercase text-amber-950 shrink-0">
                    Draft / Updating
                </span>
            </div>
        @endif

        <!-- Products Grid (3 cards per row matching Zepto/Blinkit UI) -->
        <section class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <p class="text-xs font-black uppercase tracking-wider text-slate-500">
                    Products Catalog ({{ $products->total() }})
                </p>
            </div>

            @if ($products->isEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center">
                    <p class="text-sm font-bold text-slate-600">No products found matching your filter criteria.</p>
                </div>
            @else
                <div class="grid grid-cols-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-2 sm:gap-3">
                    @foreach ($products as $p)
                        <div class="rounded-2xl border border-slate-200/90 bg-white p-2.5 shadow-2xs hover:shadow-xs transition flex flex-col justify-between relative group">
                            <div>
                                <!-- Image Box -->
                                <div class="aspect-square w-full rounded-xl bg-gradient-to-b from-slate-50 to-emerald-50/20 overflow-hidden flex items-center justify-center relative border border-slate-100 p-1.5">
                                    @if ($p['image'])
                                        <img src="{{ asset('storage/' . $p['image']) }}" alt="{{ $p['name'] }}" class="h-full w-full object-contain">
                                    @else
                                        <!-- Default Green Leaf Logo when no product image -->
                                        <img src="{{ asset('images/logo.png') }}" alt="Green Leaf Logo" class="h-full w-full object-contain p-2 opacity-90">
                                    @endif

                                    <!-- Top Right Category Badge -->
                                    <span class="absolute top-1 right-1 rounded-md bg-slate-900/80 backdrop-blur-xs text-white px-1.5 py-0.2 text-[7px] sm:text-[8px] font-black uppercase tracking-tight max-w-[80%] truncate">
                                        {{ $p['category_name'] }}
                                    </span>
                                </div>

                                <!-- Product Title -->
                                <h3 class="text-[11px] sm:text-xs font-black text-slate-900 line-clamp-2 leading-tight mt-1.5 min-h-[2.1rem]" title="{{ $p['name'] }}">
                                    {{ $p['name'] }}
                                </h3>
                            </div>

                            <div class="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between gap-1">
                                <div class="min-w-0">
                                    <span class="text-[8px] sm:text-[9px] font-bold text-slate-400 truncate block">
                                        {{ $p['sku'] ?: '#' . $p['id'] }}
                                    </span>
                                    <span class="text-[7.5px] sm:text-[8px] font-black text-emerald-700/80 block truncate">
                                        {{ $p['price_date'] }}
                                    </span>
                                    @if(!empty($p['updated_by_name']))
                                        <span class="text-[7.5px] sm:text-[8px] font-bold text-slate-500 block truncate" title="Last updated by {{ $p['updated_by_name'] }}">
                                            By {{ $p['updated_by_name'] }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    @if ($isPublished || $p['selling_price'] > 0)
                                        <span class="text-xs sm:text-sm font-black text-emerald-700">
                                            ₹{{ number_format($p['selling_price'], 2) }}
                                        </span>
                                    @else
                                        <span class="text-[10px] font-extrabold text-amber-600">
                                            Updating
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
