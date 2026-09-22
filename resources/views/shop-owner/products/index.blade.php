@extends('shop-owner.layouts.app')

@section('title', 'Products & Daily Prices')
@section('page_title', 'Products & Daily Prices')
@section('page_description', 'View daily selling prices for products. Price updates are published daily by purchasing.')
@php
    $breadcrumbs = [['label' => 'Products']];
    $sortLabels = [
        'code_asc' => 'Code wise',
        'price_desc' => 'Price High to Low',
        'price_asc' => 'Low to High',
    ];
    $currentSortLabel = $sortLabels[$sort ?? 'code_asc'] ?? 'Code wise';
@endphp

@section('content')
    <div class="space-y-4">
        <!-- Date Selector & Search Filters (Max 2 Rows) -->
        <section class="rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 shadow-xs">
            <form id="product-filter-form" method="GET" action="{{ route('shop-owner.products.index') }}" class="space-y-2.5">
                <!-- Row 1: Full-width Search Bar with embedded buttons -->
                <div class="relative w-full">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
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
                            <a href="{{ route('shop-owner.products.index') }}" class="h-7 px-2 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white text-[10px] font-bold text-slate-500 hover:bg-slate-100 transition" title="Reset filters">
                                Reset
                            </a>
                        @endif
                        <button type="submit" class="h-7 px-3 rounded-lg bg-slate-900 text-[10px] font-black text-white hover:bg-slate-800 transition cursor-pointer">
                            Search
                        </button>
                    </div>
                </div>

                <!-- Row 2: Date, Category Dropdown, Sort Dropdown (3 equal columns on 1 row) -->
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

                    <!-- Category Select -->
                    <div class="relative w-full">
                        <select
                            id="category_id"
                            name="category_id"
                            onchange="this.form.submit()"
                            class="w-full h-9 appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-2.5 pr-7 text-[11px] font-bold text-slate-800 shadow-2xs hover:bg-slate-100/70 focus:border-emerald-500 focus:bg-white focus:outline-none transition cursor-pointer truncate"
                        >
                            <option value="">All Categories</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" {{ (string) $categoryId === (string) $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>

                    <!-- Sort Select -->
                    <div class="relative w-full">
                        <select
                            id="sort"
                            name="sort"
                            onchange="this.form.submit()"
                            class="w-full h-9 appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-2.5 pr-7 text-[11px] font-bold text-slate-800 shadow-2xs hover:bg-slate-100/70 focus:border-emerald-500 focus:bg-white focus:outline-none transition cursor-pointer truncate"
                        >
                            <option value="code_asc" {{ ($sort ?? 'code_asc') === 'code_asc' ? 'selected' : '' }}>1. Code wise</option>
                            <option value="price_desc" {{ ($sort ?? '') === 'price_desc' ? 'selected' : '' }}>2. Price High to Low</option>
                            <option value="price_asc" {{ ($sort ?? '') === 'price_asc' ? 'selected' : '' }}>3. Low to High</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
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
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="font-black text-emerald-950 text-xs sm:text-sm leading-tight">
                            Prices Published for {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('d M Y') }}
                        </p>
                        <p class="text-[10px] font-semibold text-emerald-700 mt-0.5 leading-tight">
                            Daily price list for today is official and live.
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
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="font-black text-amber-950 text-xs sm:text-sm leading-tight">
                            Daily Prices Updating for {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('d M Y') }}
                        </p>
                        <p class="text-[10px] font-semibold text-amber-800 mt-0.5 leading-tight">
                            Purchasing is currently finalizing today's prices. Prices below are carried forward/reference until published.
                        </p>
                    </div>
                </div>
                <span class="rounded-full bg-amber-200/90 px-2.5 py-1 text-[9px] sm:text-[10px] font-black uppercase text-amber-950 shrink-0">
                    Draft / Updating
                </span>
            </div>
        @endif

        <!-- Products Grid (Must be 3 cards per row matching Zepto/Blinkit UI) -->
        <section class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <p class="text-xs font-black uppercase tracking-wider text-slate-500">
                    Products ({{ $products->total() }})
                </p>
            </div>

            @if ($products->isEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center">
                    <p class="text-sm font-bold text-slate-600">No products found matching your search.</p>
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

                            <div class="mt-2 pt-1.5 border-t border-slate-100 flex flex-col gap-1.5">
                                <div class="flex items-center justify-between gap-1 text-[8px] sm:text-[9px]">
                                    <span class="font-bold text-slate-400 truncate">
                                        {{ $p['sku'] ?: '#' . $p['id'] }}
                                    </span>
                                    <span class="font-black text-emerald-700/80 truncate">
                                        {{ $p['price_date'] }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-1 pt-1 border-t border-slate-50 text-[9px] sm:text-[10px] items-start">
                                    <div class="min-w-0">
                                        @if ($isPublished || $p['selling_price'] > 0)
                                            <span class="text-xs sm:text-sm font-black text-emerald-700 block truncate">
                                                ₹{{ number_format($p['selling_price'], 2) }}
                                            </span>
                                        @else
                                            <span class="text-[10px] font-extrabold text-amber-600 block leading-tight">
                                                Updating
                                            </span>
                                        @endif
                                        <span class="text-[7px] sm:text-[8px] font-bold text-slate-500 uppercase tracking-tight block leading-tight mt-0.5">
                                            Supply Price
                                        </span>
                                    </div>
                                    <div class="text-right min-w-0">
                                        @if (!empty($p['minimum_mrp']) && $p['minimum_mrp'] > 0)
                                            <span class="text-xs sm:text-sm font-black text-slate-900 block truncate">
                                                ₹{{ number_format($p['minimum_mrp'], 2) }}
                                            </span>
                                        @else
                                            <span class="text-xs sm:text-sm font-bold text-slate-400 block">
                                                -
                                            </span>
                                        @endif
                                        <span class="text-[7px] sm:text-[8px] font-bold text-slate-500 uppercase tracking-tight block leading-tight mt-0.5">
                                            Min Retail Price
                                        </span>
                                    </div>
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
