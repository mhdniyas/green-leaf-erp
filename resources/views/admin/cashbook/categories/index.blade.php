@extends('admin.cashbook.layouts.app')

@section('title', 'Cashbook Categories — Management')

@section('content')
<div class="space-y-6">
    <!-- Session Messages -->
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-xs flex items-center gap-3">
            <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800 shadow-xs space-y-1">
            <div class="flex items-center gap-2 font-bold text-rose-900">
                <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600 shrink-0"></i>
                <span>Please check the errors below:</span>
            </div>
            <ul class="list-disc list-inside text-xs font-medium space-y-0.5 ml-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <a href="{{ route('admin.cashbook.settings') }}" class="hover:text-slate-600 transition">Settings</a>
                <span>/</span>
                <span class="text-indigo-600">Categories</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight mt-1">Cashbook Category Architecture</h1>
            <p class="text-xs font-semibold text-slate-500 mt-1">
                Configure Income & Expense Headers and assign categories. Categories can belong to multiple headers seamlessly.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <!-- View Mode Switcher -->
            <div class="flex items-center rounded-2xl bg-slate-100 p-1 border border-slate-200/80 text-xs font-bold">
                <a href="{{ route('admin.cashbook.categories.index', array_filter(['shop' => $currentShop?->id, 'client_id' => $selectedClientId, 'view' => 'headers'])) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl transition {{ ($activeView ?? 'headers') === 'headers' ? 'bg-white text-indigo-700 shadow-xs font-black' : 'text-slate-600 hover:text-slate-900' }}">
                    <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                    <span>Header Hierarchy</span>
                </a>
                <a href="{{ route('admin.cashbook.categories.index', array_filter(['shop' => $currentShop?->id, 'client_id' => $selectedClientId, 'view' => 'catalog'])) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl transition {{ ($activeView ?? 'headers') === 'catalog' ? 'bg-white text-indigo-700 shadow-xs font-black' : 'text-slate-600 hover:text-slate-900' }}">
                    <i data-lucide="list" class="w-3.5 h-3.5"></i>
                    <span>Global Catalog</span>
                </a>
            </div>

            @if($currentShop)
                <button type="button" onclick="openAddHeaderModal()" class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white shadow-xs hover:bg-slate-800 transition cursor-pointer">
                    <i data-lucide="folder-plus" class="w-4 h-4"></i>
                    <span>New Header</span>
                </button>
            @endif

            <a href="{{ route('admin.cashbook.categories.create') }}" class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black text-white shadow-xs hover:bg-indigo-700 transition">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Create Category</span>
            </a>
        </div>
    </div>    @if(($activeView ?? 'headers') === 'headers')
        <!-- Client, Shop & Search Control Bar -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
            <!-- Row 1: Context & Outlets Selection + Stats -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <form method="GET" action="{{ route('admin.cashbook.categories.index') }}" id="shop-filter-form" class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="view" value="headers">

                    <!-- Client Selector -->
                    <div class="flex items-center gap-2">
                        <label for="client-select" class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5 shrink-0">
                            <i data-lucide="building-2" class="w-4 h-4 text-indigo-600"></i>
                            <span>Client:</span>
                        </label>
                        <select name="client_id" id="client-select" onchange="this.form.submit()" class="h-10 px-3.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-slate-50 hover:bg-white focus:bg-white focus:outline-hidden focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 cursor-pointer shadow-2xs transition">
                            <option value="">All Clients ({{ $clients->count() }})</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}" @selected((int)($selectedClientId ?? 0) === (int)$c->id)>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Active Shop Selector -->
                    <div class="flex items-center gap-2">
                        <label for="shop-select" class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5 shrink-0">
                            <i data-lucide="store" class="w-4 h-4 text-indigo-600"></i>
                            <span>Shop:</span>
                        </label>
                        <select name="shop" id="shop-select" onchange="this.form.submit()" class="h-10 px-3.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-slate-50 hover:bg-white focus:bg-white focus:outline-hidden focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 cursor-pointer shadow-2xs max-w-xs truncate transition">
                            @forelse($allShops as $shopItem)
                                <option value="{{ $shopItem->id }}" {{ $currentShop && $currentShop->id === $shopItem->id ? 'selected' : '' }}>
                                    {{ $shopItem->name }} ({{ $shopItem->code ?? 'Shop #'.$shopItem->id }}){{ $shopItem->client ? ' — '.$shopItem->client->name : '' }}
                                </option>
                            @empty
                                <option value="">No client shops available</option>
                            @endforelse
                        </select>
                    </div>
                </form>

                <!-- Stats Badges -->
                <div class="flex items-center gap-2.5 text-xs font-bold text-slate-500 shrink-0 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 bg-slate-50 border border-slate-200/80 px-3 py-1.5 rounded-xl">
                        <i data-lucide="store" class="w-3.5 h-3.5 text-indigo-500"></i>
                        <span>Client Shops:</span> <strong class="text-slate-800">{{ $allShops->count() }}</strong>
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-emerald-50 border border-emerald-200/80 px-3 py-1.5 rounded-xl text-emerald-800">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span>Income:</span> <strong class="font-black">{{ $incomeHeaders->count() }}</strong>
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-rose-50 border border-rose-200/80 px-3 py-1.5 rounded-xl text-rose-800">
                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                        <span>Expense:</span> <strong class="font-black">{{ $expenseHeaders->count() }}</strong>
                    </span>
                </div>
            </div>

            <!-- Row 2: Search Tools (Shop Search & In-Page Category Live Filter) -->
            <div class="pt-3 border-t border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <!-- Shop Search Form Controls -->
                <form method="GET" action="{{ route('admin.cashbook.categories.index') }}" class="flex items-center gap-2 flex-1 max-w-lg">
                    <input type="hidden" name="view" value="headers">
                    @if($selectedClientId)
                        <input type="hidden" name="client_id" value="{{ $selectedClientId }}">
                    @endif
                    @if($currentShop)
                        <input type="hidden" name="shop" value="{{ $currentShop->id }}">
                    @endif

                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </div>
                        <input type="text"
                               name="shop_search"
                               id="shopSearchInput"
                               value="{{ request('shop_search') }}"
                               placeholder="Search client shops..."
                               class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-9 text-xs font-bold text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-2xs transition">
                        @if(request('shop_search'))
                            <a href="{{ route('admin.cashbook.categories.index', array_filter(['view' => 'headers', 'client_id' => $selectedClientId, 'shop' => $currentShop?->id])) }}"
                               class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                               title="Clear search">
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </a>
                        @endif
                    </div>

                    <button type="submit" class="h-10 px-4 rounded-xl bg-slate-900 text-white text-xs font-extrabold hover:bg-slate-800 transition shadow-xs cursor-pointer shrink-0 flex items-center gap-1.5">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                        <span>Search</span>
                    </button>

                    @if(request()->anyFilled(['client_id', 'shop_search']))
                        <a href="{{ route('admin.cashbook.categories.index', ['view' => 'headers']) }}" class="h-10 px-3 rounded-xl border border-slate-200 text-xs font-bold text-slate-500 hover:text-rose-600 hover:border-rose-200 transition flex items-center gap-1 shrink-0">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                            <span>Reset</span>
                        </a>
                    @endif
                </form>

                <!-- In-Page Live Category Filter -->
                @if($currentShop)
                    <div class="flex items-center gap-2 flex-1 max-w-md">
                        <div class="relative w-full">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-indigo-500">
                                <i data-lucide="filter" class="w-4 h-4"></i>
                            </div>
                            <input type="text"
                                   id="categoryLiveFilterInput"
                                   oninput="liveFilterCategoriesInHeaders(this.value)"
                                   placeholder="Filter categories in {{ $currentShop->name }}..."
                                   class="h-10 w-full rounded-xl border border-indigo-200 bg-indigo-50/20 pl-10 pr-9 text-xs font-bold text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-2xs transition">
                            <button type="button"
                                    id="clearCategoryLiveFilter"
                                    onclick="clearLiveFilter()"
                                    class="hidden absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                                    title="Clear category filter">
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <div id="categoryFilterCounter" class="text-[11px] font-bold text-slate-500 hidden items-center gap-1 shrink-0 bg-indigo-50 border border-indigo-100 px-2.5 py-2 rounded-xl whitespace-nowrap">
                            <span>Filtered:</span> <span id="categoryFilterTerm" class="text-indigo-700 font-black"></span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if(! $currentShop)
            <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center space-y-3">
                <i data-lucide="store" class="w-12 h-12 text-slate-300 mx-auto"></i>
                <h3 class="text-base font-black text-slate-800">No Active Client Shops Found</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    @if(request()->filled('shop_search') || request()->filled('client_id'))
                        No client shops matched your filter criteria.
                    @else
                        No active client shops currently configured.
                    @endif
                </p>
                @if(request()->anyFilled(['shop_search', 'client_id']))
                    <div class="pt-2">
                        <a href="{{ route('admin.cashbook.categories.index', ['view' => 'headers']) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition">
                            Clear Filters
                        </a>
                    </div>
                @endif
            </div>
        @else
            <!-- ── SECTION: INCOME ────────────────────────────────────── -->
            <div class="space-y-4">
                <div class="flex items-center gap-2 px-1">
                    <div class="h-3 w-3 rounded-full bg-emerald-500"></div>
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Income Headers & Categories</h2>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    @forelse($incomeHeaders as $header)
                        @include('admin.cashbook.categories.partials.header-card', [
                            'header' => $header,
                            'shop' => $currentShop,
                            'multiHeaderCodes' => $multiHeaderCodes,
                            'type' => 'income'
                        ])
                    @empty
                        <div class="col-span-full bg-white p-8 rounded-2xl border border-dashed border-slate-200 text-center space-y-2">
                            <p class="text-xs font-bold text-slate-400">No income headers configured for {{ $currentShop->name }}.</p>
                            <button type="button" onclick="openAddHeaderModal('income')" class="inline-flex items-center gap-1.5 text-xs font-black text-emerald-600 hover:text-emerald-700">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                <span>Create Income Header</span>
                            </button>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- ── SECTION: EXPENSE ───────────────────────────────────── -->
            <div class="space-y-4 pt-4">
                <div class="flex items-center gap-2 px-1">
                    <div class="h-3 w-3 rounded-full bg-rose-500"></div>
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Expense Headers & Categories</h2>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    @forelse($expenseHeaders as $header)
                        @include('admin.cashbook.categories.partials.header-card', [
                            'header' => $header,
                            'shop' => $currentShop,
                            'multiHeaderCodes' => $multiHeaderCodes,
                            'type' => 'expense'
                        ])
                    @empty
                        <div class="col-span-full bg-white p-8 rounded-2xl border border-dashed border-slate-200 text-center space-y-2">
                            <p class="text-xs font-bold text-slate-400">No expense headers configured for {{ $currentShop->name }}.</p>
                            <button type="button" onclick="openAddHeaderModal('expense')" class="inline-flex items-center gap-1.5 text-xs font-black text-rose-600 hover:text-rose-700">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                <span>Create Expense Header</span>
                            </button>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

    @else
        <!-- ── GLOBAL CATALOG VIEW ──────────────────────────────────── -->
        <!-- Filter & Search Bar -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('admin.cashbook.categories.index') }}" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <input type="hidden" name="view" value="catalog">
                @if($currentShop)
                    <input type="hidden" name="shop" value="{{ $currentShop->id }}">
                @endif
                <div class="relative min-w-[240px]">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search category or code..." class="h-10 w-full pl-10 pr-4 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-slate-50 focus:bg-white focus:outline-hidden focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-2xs transition">
                </div>
                <select name="client_id" onchange="this.form.submit()" class="h-10 px-3.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-slate-50 focus:bg-white focus:outline-hidden focus:border-indigo-500 cursor-pointer shadow-2xs transition">
                    <option value="">All Clients</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected((int)($selectedClientId ?? 0) === (int)$c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <select name="category" onchange="this.form.submit()" class="h-10 px-3.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-slate-50 focus:bg-white focus:outline-hidden focus:border-indigo-500 cursor-pointer shadow-2xs transition">
                    <option value="">All Base Types</option>
                    <option value="income" {{ request('category') === 'income' ? 'selected' : '' }}>Income</option>
                    <option value="expense" {{ request('category') === 'expense' ? 'selected' : '' }}>Expense</option>
                    <option value="transfer" {{ request('category') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                    <option value="settlement" {{ request('category') === 'settlement' ? 'selected' : '' }}>Settlement</option>
                </select>
                <button type="submit" class="h-10 px-4 rounded-xl bg-slate-800 text-white text-xs font-extrabold hover:bg-slate-900 transition shadow-xs cursor-pointer">Filter</button>
                @if(request()->anyFilled(['search', 'category', 'client_id']))
                    <a href="{{ route('admin.cashbook.categories.index', ['view' => 'catalog', 'shop' => $currentShop?->id]) }}" class="h-10 px-3 rounded-xl border border-slate-200 text-xs font-bold text-slate-500 hover:text-rose-600 flex items-center transition">Clear</a>
                @endif
            </form>
            <div class="text-xs font-bold text-slate-500">
                Total Categories: <span class="text-slate-900 font-extrabold">{{ $categories->count() }}</span>
            </div>
        </div>

        <!-- Table List -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-black uppercase text-slate-500 tracking-wider">
                        <tr>
                            <th class="py-3.5 px-4">Category</th>
                            <th class="py-3.5 px-4">Type</th>
                            <th class="py-3.5 px-4">Shops</th>
                            <th class="py-3.5 px-4">Header</th>
                            <th class="py-3.5 px-4">Settlement</th>
                            <th class="py-3.5 px-4">Company Relation</th>
                            <th class="py-3.5 px-4">Vendor</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @forelse($categories as $cat)
                            @php
                                $sum = $categorySummaries[$cat->id] ?? [];
                                $typeBadgeClass = match(strtolower((string)$cat->category)) {
                                    'income' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'expense' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    'transfer' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'settlement' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                    default => 'bg-slate-50 text-slate-700 border-slate-200',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="py-3.5 px-4 font-extrabold text-slate-900">
                                    <div class="flex flex-col">
                                        <span class="text-slate-900">{{ $cat->name }}</span>
                                        <span class="text-[10px] font-mono text-slate-400 font-normal">{{ $cat->code }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-1 rounded-lg border text-[10px] font-extrabold uppercase {{ $typeBadgeClass }}">
                                        {{ $cat->category }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="font-bold text-slate-800">{{ $sum['shop_count'] ?? 0 }} / {{ $sum['total_shops'] ?? 0 }} Shops</span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="text-xs font-bold {{ ($sum['header'] ?? '') === 'Varies' ? 'text-amber-700 font-black' : 'text-slate-600' }}">
                                        {{ $sum['header'] ?? 'None' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="text-xs font-bold {{ ($sum['settlement'] ?? '') === 'Varies' ? 'text-amber-700 font-black' : 'text-slate-600' }}">
                                        {{ $sum['settlement'] ?? 'None' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="text-xs font-bold text-slate-600">
                                        {{ $sum['company'] ?? 'None' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="text-xs font-bold text-slate-600">
                                        {{ $sum['vendor'] ?? 'None' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($cat->active)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-black">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 border border-slate-200 text-[10px] font-black">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <a href="{{ route('admin.cashbook.categories.show', $cat->code) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 font-black text-xs hover:bg-indigo-100 transition">
                                        <span>View / Edit</span>
                                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-8 text-center text-slate-400 font-bold">
                                    No categories found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<!-- ── MODAL: ADD HEADER ──────────────────────────────────────── -->
<div id="add-header-modal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <i data-lucide="folder-plus" class="w-5 h-5 text-indigo-600"></i>
                <h3 class="text-base font-black text-slate-900">Create New Header</h3>
            </div>
            <button type="button" onclick="closeAddHeaderModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.cashbook.categories.headers.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="shop_id" value="{{ $currentShop?->id }}">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Header Name</label>
                <input type="text" name="name" required placeholder="e.g. Daily Cash Expenditure" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Section Type</label>
                <select name="type" id="add-header-type" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500">
                    <option value="expense">Expense Header</option>
                    <option value="income">Income Header</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Cash Flow Destination / Source</label>
                <select name="cash_flow_mode" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500">
                    <option value="shop_cash">Shop Cash (Daily Register)</option>
                    <option value="petty">Petty Cash</option>
                    <option value="company">Company Account</option>
                    <option value="none">None (Non-cash movement)</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeAddHeaderModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-xs font-black text-white hover:bg-indigo-700 transition shadow-xs">Create Header</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL: ADD CATEGORY UNDER HEADER ───────────────────────── -->
<div id="add-category-modal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-5 h-5 text-indigo-600"></i>
                    <span>Add Category to Header</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Target: <strong id="modal-target-header-name" class="text-slate-800 font-extrabold">Header</strong></p>
            </div>
            <button type="button" onclick="closeAddCategoryModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="add-category-form" method="POST" action="#" class="space-y-4">
            @csrf

            <!-- Mode Switcher -->
            <div class="grid grid-cols-2 gap-2 bg-slate-100 p-1 rounded-2xl border border-slate-200/80">
                <button type="button" id="tab-btn-existing" onclick="switchCategoryModalTab('existing')" class="py-2 text-xs font-black rounded-xl transition bg-white text-indigo-700 shadow-xs flex items-center justify-center gap-1.5">
                    <i data-lucide="link" class="w-3.5 h-3.5"></i>
                    <span>Attach Existing Category</span>
                </button>
                <button type="button" id="tab-btn-new" onclick="switchCategoryModalTab('new')" class="py-2 text-xs font-bold rounded-xl transition text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>Create New Category</span>
                </button>
            </div>
            <input type="hidden" name="mode" id="category-modal-mode" value="existing">

            <!-- TAB 1: Existing Category -->
            <div id="pane-existing-category" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        Select Existing Category
                        <span class="text-[11px] font-normal text-slate-400 block">Search and choose an existing category to attach under this header (e.g. Vehicle can be attached to both Daily Cash and Petty Cash).</span>
                    </label>

                    <input type="hidden" name="category_id" id="modal-category-id">

                    <!-- Selected Category Pill (shows when an item is selected) -->
                    <div id="category-selected-display" class="hidden p-3 rounded-2xl bg-indigo-50/80 border border-indigo-200/80 flex items-center justify-between gap-3 animate-in fade-in duration-100">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-2xs">
                                <i data-lucide="check" class="w-4 h-4"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span id="selected-category-name" class="text-xs font-black text-slate-900 truncate"></span>
                                    <span id="selected-category-type-badge" class="px-1.5 py-0.2 rounded text-[10px] font-bold border"></span>
                                </div>
                                <span id="selected-category-code" class="text-[11px] font-mono text-slate-500"></span>
                            </div>
                        </div>
                        <button type="button" onclick="clearSelectedCategory()" class="text-xs font-bold text-indigo-700 hover:text-indigo-900 px-3 py-1.5 rounded-xl bg-white border border-indigo-200 hover:bg-indigo-100/50 transition shrink-0">
                            Change
                        </button>
                    </div>

                    <!-- Search Input & Scrollable Options List -->
                    <div id="category-picker-body" class="space-y-2">
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                            <input type="text" id="category-search-input"
                                   oninput="filterCategoryList(this.value)"
                                   placeholder="Search by category name or code (e.g. Vehicle, Tea)..."
                                   autocomplete="off"
                                   class="w-full pl-10 pr-9 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 placeholder-slate-400 focus:outline-hidden focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-slate-50/50 focus:bg-white transition">
                            <button type="button" id="category-search-clear" onclick="clearCategorySearch()" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1">
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>

                        <!-- Error Message if none selected on submit -->
                        <div id="category-selection-error" class="hidden text-[11px] font-bold text-rose-600 px-1">
                            Please select a category from the list below.
                        </div>

                        <!-- Scrollable Category List -->
                        <div id="category-options-list" class="max-h-52 overflow-y-auto divide-y divide-slate-100 rounded-xl border border-slate-200/80 bg-white shadow-2xs">
                            @foreach($availableCategories ?? [] as $avCat)
                                @php
                                    $catType = strtolower((string)$avCat->category);
                                    $typeBadgeClass = match($catType) {
                                        'income' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'expense' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        'transfer' => 'bg-sky-50 text-sky-700 border-sky-200',
                                        'settlement' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        default => 'bg-slate-50 text-slate-700 border-slate-200'
                                    };
                                @endphp
                                <button type="button"
                                        onclick="selectCategoryItem({{ $avCat->id }}, '{{ addslashes($avCat->name) }}', '{{ $avCat->code }}', '{{ strtoupper($catType) }}', '{{ $typeBadgeClass }}')"
                                        data-category-id="{{ $avCat->id }}"
                                        data-category-name="{{ strtolower($avCat->name) }}"
                                        data-category-code="{{ strtolower($avCat->code) }}"
                                        data-category-type="{{ $catType }}"
                                        class="category-option-item w-full px-3.5 py-2.5 text-left flex items-center justify-between gap-3 hover:bg-indigo-50/60 transition group cursor-pointer">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-xs font-black text-slate-800 group-hover:text-indigo-700">
                                                {{ $avCat->name }}
                                            </span>
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold border {{ $typeBadgeClass }}">
                                                {{ strtoupper($avCat->category) }}
                                            </span>
                                        </div>
                                        <span class="text-[11px] font-mono text-slate-400 group-hover:text-slate-500">
                                            {{ $avCat->code }}
                                        </span>
                                    </div>
                                    <div class="shrink-0 w-6 h-6 rounded-lg border border-slate-200 group-hover:border-indigo-400 group-hover:bg-white flex items-center justify-center transition">
                                        <i data-lucide="plus" class="w-3.5 h-3.5 text-slate-400 group-hover:text-indigo-600 transition"></i>
                                    </div>
                                </button>
                            @endforeach
                            <div id="category-search-no-results" class="hidden p-6 text-center text-xs font-bold text-slate-400">
                                <i data-lucide="search-x" class="w-6 h-6 text-slate-300 mx-auto mb-1"></i>
                                <span>No categories matching your search.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Custom Display Name for this Header (Optional)</label>
                    <input type="text" name="display_name" placeholder="Leave blank to use category's default name" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500">
                </div>
            </div>

            <!-- TAB 2: New Category -->
            <div id="pane-new-category" class="space-y-3 hidden">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Category Name</label>
                    <input type="text" name="name" id="modal-new-category-name" placeholder="e.g. Tea & Snacks" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Custom Display Name (Optional)</label>
                    <input type="text" name="display_name_new" placeholder="Leave blank to use category name" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeAddCategoryModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-xs font-black text-white hover:bg-indigo-700 transition shadow-xs">Add to Header</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL: RENAME CATEGORY ─────────────────────────────────── -->
<div id="rename-category-modal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-sm w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                <i data-lucide="edit-3" class="w-4 h-4 text-indigo-600"></i>
                <span>Rename Category</span>
            </h3>
            <button type="button" onclick="closeRenameModal()" class="text-slate-400 hover:text-slate-600 p-1">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form id="rename-category-form" method="POST" action="#" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">New Name</label>
                <input type="text" name="name" id="rename-category-input" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-hidden focus:border-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeRenameModal()" class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-xs font-black text-white hover:bg-indigo-700">Save Name</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddHeaderModal(defaultType = 'expense') {
        const modal = document.getElementById('add-header-modal');
        const typeSelect = document.getElementById('add-header-type');
        if (typeSelect && defaultType) {
            typeSelect.value = defaultType;
        }
        if (modal) modal.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function closeAddHeaderModal() {
        const modal = document.getElementById('add-header-modal');
        if (modal) modal.classList.add('hidden');
    }

    function openAddCategoryModal(headerId, headerName, headerType) {
        const modal = document.getElementById('add-category-modal');
        const form = document.getElementById('add-category-form');
        const titleEl = document.getElementById('modal-target-header-name');

        if (titleEl) titleEl.textContent = headerName + ' (' + (headerType || '').toUpperCase() + ')';
        if (form) {
            form.action = `{{ url('admin/cashbook/categories/headers') }}/${headerId}/categories`;
        }

        clearSelectedCategory();
        switchCategoryModalTab('existing');

        // Prioritize/group categories matching headerType if possible
        if (headerType) {
            const hType = headerType.toLowerCase();
            const list = document.getElementById('category-options-list');
            if (list) {
                const items = Array.from(list.querySelectorAll('.category-option-item'));
                items.sort((a, b) => {
                    const aType = a.getAttribute('data-category-type') === hType ? 0 : 1;
                    const bType = b.getAttribute('data-category-type') === hType ? 0 : 1;
                    return aType - bType;
                });
                items.forEach(el => list.appendChild(el));
            }
        }

        if (modal) modal.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();

        setTimeout(() => {
            const searchInput = document.getElementById('category-search-input');
            if (searchInput) searchInput.focus();
        }, 120);
    }

    function closeAddCategoryModal() {
        const modal = document.getElementById('add-category-modal');
        if (modal) modal.classList.add('hidden');
    }

    function filterCategoryList(query) {
        const q = (query || '').toLowerCase().trim();
        const items = document.querySelectorAll('.category-option-item');
        const noResults = document.getElementById('category-search-no-results');
        const clearBtn = document.getElementById('category-search-clear');
        let visibleCount = 0;

        if (clearBtn) {
            if (q.length > 0) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }
        }

        items.forEach(item => {
            const name = item.getAttribute('data-category-name') || '';
            const code = item.getAttribute('data-category-code') || '';
            const type = item.getAttribute('data-category-type') || '';

            if (!q || name.includes(q) || code.includes(q) || type.includes(q)) {
                item.classList.remove('hidden');
                visibleCount++;
            } else {
                item.classList.add('hidden');
            }
        });

        if (noResults) {
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }
    }

    function clearCategorySearch() {
        const input = document.getElementById('category-search-input');
        if (input) {
            input.value = '';
            filterCategoryList('');
            input.focus();
        }
    }

    function selectCategoryItem(id, name, code, type, badgeClass) {
        const idInput = document.getElementById('modal-category-id');
        if (idInput) idInput.value = id;

        const nameEl = document.getElementById('selected-category-name');
        if (nameEl) nameEl.textContent = name;

        const codeEl = document.getElementById('selected-category-code');
        if (codeEl) codeEl.textContent = code;

        const badge = document.getElementById('selected-category-type-badge');
        if (badge) {
            badge.textContent = type;
            badge.className = 'px-1.5 py-0.2 rounded text-[10px] font-bold border ' + badgeClass;
        }

        const pickerBody = document.getElementById('category-picker-body');
        if (pickerBody) pickerBody.classList.add('hidden');

        const selectedDisplay = document.getElementById('category-selected-display');
        if (selectedDisplay) selectedDisplay.classList.remove('hidden');

        const errEl = document.getElementById('category-selection-error');
        if (errEl) errEl.classList.add('hidden');

        if (window.lucide) lucide.createIcons();
    }

    function clearSelectedCategory() {
        const idInput = document.getElementById('modal-category-id');
        if (idInput) idInput.value = '';

        const selectedDisplay = document.getElementById('category-selected-display');
        if (selectedDisplay) selectedDisplay.classList.add('hidden');

        const pickerBody = document.getElementById('category-picker-body');
        if (pickerBody) pickerBody.classList.remove('hidden');

        const errEl = document.getElementById('category-selection-error');
        if (errEl) errEl.classList.add('hidden');

        const searchInput = document.getElementById('category-search-input');
        if (searchInput) {
            searchInput.value = '';
            filterCategoryList('');
        }
    }

    function switchCategoryModalTab(tab) {
        const modeInput = document.getElementById('category-modal-mode');
        const btnExisting = document.getElementById('tab-btn-existing');
        const btnNew = document.getElementById('tab-btn-new');
        const paneExisting = document.getElementById('pane-existing-category');
        const paneNew = document.getElementById('pane-new-category');
        const newNameInput = document.getElementById('modal-new-category-name');

        if (modeInput) modeInput.value = tab;

        if (tab === 'existing') {
            btnExisting.className = 'py-2 text-xs font-black rounded-xl transition bg-white text-indigo-700 shadow-xs flex items-center justify-center gap-1.5';
            btnNew.className = 'py-2 text-xs font-bold rounded-xl transition text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5';
            paneExisting.classList.remove('hidden');
            paneNew.classList.add('hidden');
            if (newNameInput) newNameInput.removeAttribute('required');
        } else {
            btnNew.className = 'py-2 text-xs font-black rounded-xl transition bg-white text-indigo-700 shadow-xs flex items-center justify-center gap-1.5';
            btnExisting.className = 'py-2 text-xs font-bold rounded-xl transition text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5';
            paneNew.classList.remove('hidden');
            paneExisting.classList.add('hidden');
            if (newNameInput) newNameInput.setAttribute('required', 'required');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const addCategoryForm = document.getElementById('add-category-form');
        if (addCategoryForm) {
            addCategoryForm.addEventListener('submit', function(e) {
                const modeInput = document.getElementById('category-modal-mode');
                if (modeInput && modeInput.value === 'existing') {
                    const catId = document.getElementById('modal-category-id')?.value;
                    if (!catId) {
                        e.preventDefault();
                        const errEl = document.getElementById('category-selection-error');
                        if (errEl) errEl.classList.remove('hidden');
                        const searchInput = document.getElementById('category-search-input');
                        if (searchInput) searchInput.focus();
                        return false;
                    }
                }
            });
        }
    });

    function openRenameModal(settingId, currentName) {
        const modal = document.getElementById('rename-category-modal');
        const form = document.getElementById('rename-category-form');
        const input = document.getElementById('rename-category-input');

        if (form) form.action = `{{ url('admin/cashbook/categories/settings') }}/${settingId}/rename`;
        if (input) input.value = currentName;

        if (modal) modal.classList.remove('hidden');
        if (input) input.focus();
        if (window.lucide) lucide.createIcons();
    }

    function closeRenameModal() {
        const modal = document.getElementById('rename-category-modal');
        if (modal) modal.classList.add('hidden');
    }

    function moveCategoryOrder(formId, direction) {
        const form = document.getElementById(formId);
        if (!form) return;
        const input = form.querySelector('input[name="direction"]');
        if (input) input.value = direction;
        form.submit();
    }

    function liveFilterCategoriesInHeaders(query) {
        const q = (query || '').trim().toLowerCase();
        const clearBtn = document.getElementById('clearCategoryLiveFilter');
        const counter = document.getElementById('categoryFilterCounter');
        const termSpan = document.getElementById('categoryFilterTerm');

        if (clearBtn) clearBtn.classList.toggle('hidden', q === '');
        if (counter) counter.classList.toggle('hidden', q === '');
        if (termSpan) termSpan.textContent = `"${q}"`;

        const cards = document.querySelectorAll('.header-card-container');
        cards.forEach(card => {
            const headerName = card.getAttribute('data-header-name') || '';
            const rows = card.querySelectorAll('.category-item-row');
            let cardHasMatch = false;

            if (q === '') {
                rows.forEach(r => r.style.display = '');
                card.style.display = '';
                return;
            }

            const headerMatches = headerName.includes(q);

            rows.forEach(r => {
                const catName = r.getAttribute('data-category-name') || '';
                const catCode = r.getAttribute('data-category-code') || '';
                const baseName = r.getAttribute('data-base-name') || '';

                if (headerMatches || catName.includes(q) || catCode.includes(q) || baseName.includes(q)) {
                    r.style.display = '';
                    cardHasMatch = true;
                } else {
                    r.style.display = 'none';
                }
            });

            card.style.display = (headerMatches || cardHasMatch) ? '' : 'none';
        });
    }

    function clearLiveFilter() {
        const input = document.getElementById('categoryLiveFilterInput');
        if (input) {
            input.value = '';
            liveFilterCategoriesInHeaders('');
        }
    }
</script>
@endsection
