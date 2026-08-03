@extends('purchase-manager.layouts.app')

@section('title', 'Price Proposal Board')
@section('page_title', 'Price Proposal Board')
@section('page_description', 'Purchase manager proposes category prices from purchased-product cost. Admin approval publishes prices and generates shop invoices.')

@section('content')
    @php
        $isAdminViewer = auth()->user()?->hasRole('admin');
        $pendingApprovals = $pendingApprovals ?? collect();
        $approvedApprovals = $approvedApprovals ?? collect();
        $allApprovals = $pendingApprovals->concat($approvedApprovals);
        $movementOptions = [
            'changed' => 'Changed',
            'up' => 'Up',
            'down' => 'Down',
            'all' => 'All',
        ];
        $sortOptions = [
            'code' => 'Code',
            'name' => 'Name',
            'status' => 'Status',
            'movement' => 'Movement',
        ];
    @endphp
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <form method="GET" action="{{ route('purchasing.prices.index') }}" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 items-end">
                    <div>
                        <label for="search" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Product Search</label>
                        <div class="relative mt-2">
                            <input
                                id="search"
                                name="search"
                                value="{{ $search }}"
                                placeholder="Search product, SKU..."
                                autocomplete="off"
                                class="w-full rounded-2xl border border-slate-200 bg-white pl-10 pr-4 py-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:outline-none"
                            >
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label for="category_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Category Filter</label>
                        <select
                            id="category_id"
                            name="category_id"
                            onchange="this.form.submit()"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-cyan-500 focus:outline-none"
                        >
                            <option value="">All Categories</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((string) $categoryId === (string) $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Purchase Date</label>
                        <input
                            id="date"
                            type="date"
                            name="date"
                            value="{{ $purchaseDate }}"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-cyan-700 focus:border-cyan-500 focus:outline-none"
                        >
                    </div>
                    <div>
                        <label for="sort" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Sort By</label>
                        <select
                            id="sort"
                            name="sort"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-cyan-500 focus:outline-none"
                        >
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-slate-100">
                    <div>
                        <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500 block mb-2">Price Movement</label>
                        <div class="inline-flex flex-wrap gap-1.5">
                            @foreach ($movementOptions as $value => $label)
                                <button
                                    type="submit"
                                    name="movement"
                                    value="{{ $value }}"
                                    class="inline-flex min-h-10 items-center justify-center rounded-xl border px-3 text-center text-xs font-black transition {{ $movement === $value ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-2 sm:pt-0">
                        <button type="submit" name="movement" value="{{ $movement }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white hover:bg-slate-800">
                            Search
                        </button>
                        <button type="button" onclick="openPriceBoardSettingsModal()" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 hover:bg-slate-50">
                            Settings
                            @if ($autoApproveSamePurchasePrice)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">Auto</span>
                            @endif
                        </button>
                        <button type="submit" form="refresh-prices-form" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white hover:bg-indigo-500 shadow-sm transition-all border-none cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Refresh Prices
                        </button>
                        <span id="auto-refresh-badge" class="inline-flex items-center gap-1.5 rounded-2xl border border-cyan-200 bg-cyan-50 px-3 py-2.5 text-xs font-bold text-cyan-800 shadow-sm">
                            <span class="h-2 w-2 rounded-full bg-cyan-500 animate-pulse"></span>
                            Auto-refreshing in <span id="refresh-timer-seconds" class="font-black">30</span>s
                        </span>
                        <button type="button" onclick="shareOnWhatsApp('A')" class="inline-flex items-center justify-center gap-1.5 rounded-2xl bg-emerald-600 px-4 py-3 text-xs font-black text-white hover:bg-emerald-500 shadow-sm transition-all border-none cursor-pointer">
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                            </svg>
                            Share A
                        </button>
                        <button type="button" onclick="shareOnWhatsApp('B')" class="inline-flex items-center justify-center gap-1.5 rounded-2xl bg-teal-600 px-4 py-3 text-xs font-black text-white hover:bg-teal-500 shadow-sm transition-all border-none cursor-pointer">
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                            </svg>
                            Share B
                        </button>
                        <button type="button" onclick="shareOnWhatsApp('C')" class="inline-flex items-center justify-center gap-1.5 rounded-2xl bg-cyan-600 px-4 py-3 text-xs font-black text-white hover:bg-cyan-500 shadow-sm transition-all border-none cursor-pointer">
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                            </svg>
                            Share C
                        </button>
                    </div>
                </div>
            </form>
            <form id="refresh-prices-form" method="POST" action="{{ route('purchasing.prices.refresh') }}" class="hidden">
                @csrf
                <input type="hidden" name="date" value="{{ $purchaseDate }}">
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="movement" value="{{ $movement }}">
                <input type="hidden" name="sort" value="{{ $sort }}">
            </form>
            <p class="mt-3 text-sm text-slate-500">
                Purchase date {{ \Illuminate\Support\Carbon::parse($purchaseDate)->format('d M Y') }} publishes selling proposals for
                {{ \Illuminate\Support\Carbon::parse($targetBusinessDate)->format('d M Y') }}.
            </p>
            <form method="POST" action="{{ route('purchasing.prices.products.store') }}" class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                @csrf
                <input type="hidden" name="date" value="{{ $purchaseDate }}">
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="movement" value="{{ $movement }}">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <div>
                    <label for="product_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Add Inventory Product</label>
                    <select id="product_id" name="product_id" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-cyan-500 focus:outline-none">
                        <option value="">Select product</option>
                        @foreach ($inventoryProducts as $inventoryProduct)
                            @php
                                $inventoryUnit = strtolower((string) $inventoryProduct->unit) === 'piece' ? 'PCE' : strtoupper((string) $inventoryProduct->unit);
                            @endphp
                            <option value="{{ $inventoryProduct->id }}">
                                {{ $inventoryProduct->sku ?: 'NA' }} - {{ $inventoryProduct->name }} ({{ $inventoryUnit }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-cyan-600 px-5 py-3 text-sm font-black text-white hover:bg-cyan-500">
                    Add Product
                </button>
            </form>
        </section>

        @if (session('success'))
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-bold text-amber-800">
                {{ session('warning') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Pending Admin Approval</p>
                <p class="mt-3 text-3xl font-black text-slate-950">{{ $pendingApprovals->count() }}</p>
                <p class="mt-2 text-sm text-slate-500">Rows waiting for admin publish, including products not purchased today.</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Already Approved</p>
                <p class="mt-3 text-3xl font-black text-slate-950">{{ $approvedApprovals->count() }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $isAdminViewer ? 'Editing any approved row will publish the update immediately.' : 'Editing any approved row will send it back for approval.' }}</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Invoice Trigger</p>
                <p class="mt-3 text-lg font-black text-slate-950">{{ $isAdminViewer ? 'Instant Publish' : 'Admin Publish' }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $isAdminViewer ? 'Saving as admin publishes live prices immediately and reprices shop invoices.' : 'When admin approves, selling prices go live and shop-owner invoices are generated or repriced.' }}</p>
            </article>
        </section>

        <form method="POST" action="{{ route('purchasing.prices.update') }}" class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            @csrf
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="movement" value="{{ $movement }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="date" value="{{ $purchaseDate }}">
            <input id="confirm_publish" type="hidden" name="confirm_publish" value="1">

            <div class="border-b border-slate-200 px-5 py-5">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Proposal Update</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Proposed Shop Category Prices</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $isAdminViewer ? 'As admin, saving here publishes the live selling prices directly.' : 'This page no longer updates live selling prices directly. It only edits admin approval proposals.' }}</p>
                    </div>
                    <div>
                        <label for="reason" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Reason</label>
                        <input id="reason" name="reason" value="{{ old('reason') }}" placeholder="Purchase cost changed / resubmit for admin" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>
            @if ($allApprovals->isEmpty())
                <div class="px-5 py-12 text-center">
                    <p class="text-base font-black text-slate-900">No products found.</p>
                    <p class="mt-2 text-sm text-slate-500">Inventory products appear here so prices can be updated even when there is no purchaser activity for the selected date.</p>
                </div>
            @else
                <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                    <table class="min-w-[1120px] text-left">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4">Product</th>
                                <th class="px-5 py-4 text-center">Status</th>
                                <th class="px-5 py-4 text-center">Movement</th>
                                <th class="px-5 py-4 text-right">Today Price</th>
                                <th class="px-5 py-4">Price Unit</th>
                                <th class="px-5 py-4 text-right">A Price</th>
                                <th class="px-5 py-4 text-right">B Price</th>
                                <th class="px-5 py-4 text-right">C Price</th>
                                <th class="px-5 py-4">Admin Check</th>
                                <th class="px-5 py-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($allApprovals as $approval)
                                @php
                                    $product = $approval->product;
                                    $tone = $approval->status === 'approved' ? 'emerald' : 'amber';
                                    $movementClass = match ($approval->movement_status) {
                                        'same' => 'border-sky-200 bg-sky-50 text-sky-700',
                                        'up' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                        'down' => 'border-rose-200 bg-rose-50 text-rose-700',
                                        default => 'border-cyan-200 bg-cyan-50 text-cyan-700',
                                    };
                                    $unitLabel = strtolower((string) ($product?->unit ?? '')) === 'piece' ? 'PCE' : strtoupper($product?->unit ?? 'NA');
                                    $priceUnit = \App\Models\ProductUnit::normalizeUnit((string) ($approval->price_unit ?: $product?->unit));
                                    $priceUnitOptions = collect([(string) ($product?->unit ?? '')])
                                        ->merge($product?->orderUnits?->pluck('unit') ?? collect())
                                        ->map(fn ($unit): string => \App\Models\ProductUnit::normalizeUnit((string) $unit))
                                        ->filter()
                                        ->unique()
                                        ->values();
                                    $movementLabel = match ($approval->movement_status) {
                                        'same' => 'Same',
                                        'up' => '+ INR '.number_format(abs((float) $approval->purchase_price - (float) $approval->comparison_purchase_price), 2),
                                        'down' => '- INR '.number_format(abs((float) $approval->purchase_price - (float) $approval->comparison_purchase_price), 2),
                                        default => 'Changed',
                                    };
                                @endphp
                                <tr class="price-row"
                                    data-search-text="{{ strtolower(($product?->sku ?? '') . ' ' . ($product?->name ?? '') . ' ' . ($product?->category?->name ?? '')) }}"
                                    data-name="{{ $product?->name }}"
                                    data-sku="{{ $product?->sku }}"
                                    data-unit="{{ $unitLabel }}"
                                    data-price="{{ number_format((float) $approval->purchase_price, 2) }}"
                                    data-price-a="{{ number_format((float) $approval->price_a, 2) }}"
                                    data-price-b="{{ number_format((float) $approval->price_b, 2) }}"
                                    data-price-c="{{ number_format((float) $approval->price_c, 2) }}">
                                    <td class="px-5 py-4">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-0.5 inline-flex min-w-12 justify-center rounded-xl border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-black text-slate-700">
                                                {{ $product?->sku ?: 'NA' }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-bold text-slate-950">{{ $product?->name ?? 'Unknown Product' }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-400">{{ $unitLabel }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <x-purchase-manager.components.status-badge :label="str($approval->status)->replace('_', ' ')->title()" :tone="$tone" />
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] {{ $movementClass }}">
                                            {{ $movementLabel }}
                                        </span>
                                        @if ($approval->comparison_purchase_price !== null)
                                            <p class="mt-1 text-[10px] font-semibold text-slate-400">Prev INR {{ number_format($approval->comparison_purchase_price, 2) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right font-black text-slate-950">
                                        INR {{ number_format((float) $approval->purchase_price, 2) }}
                                        <p class="mt-1 text-[10px] font-semibold text-slate-400">Purchase basis {{ $unitLabel }}</p>
                                        @if (! (bool) $approval->getAttribute('purchased_today'))
                                            <p class="mt-1 text-[10px] font-semibold text-amber-600">No purchase today</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <select
                                            name="prices[{{ $approval->id }}][price_unit]"
                                            class="block w-28 rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                        >
                                            @foreach ($priceUnitOptions as $unitOption)
                                                @php
                                                    $unitOptionLabel = $unitOption === 'piece' ? 'PCE' : strtoupper(str_replace('_', ' ', $unitOption));
                                                @endphp
                                                <option value="{{ $unitOption }}" @selected(old("prices.{$approval->id}.price_unit", $priceUnit) === $unitOption)>
                                                    {{ $unitOptionLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-5 py-4">
                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            inputmode="decimal"
                                            name="prices[{{ $approval->id }}][price_a]"
                                            value="{{ old("prices.{$approval->id}.price_a", number_format((float) $approval->price_a, 2, '.', '')) }}"
                                            class="ml-auto block w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                        >
                                        <p class="mt-1 text-right text-[10px] font-semibold text-slate-400">/{{ $priceUnit === 'piece' ? 'PCE' : strtoupper(str_replace('_', ' ', $priceUnit)) }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            inputmode="decimal"
                                            name="prices[{{ $approval->id }}][price_b]"
                                            value="{{ old("prices.{$approval->id}.price_b", number_format((float) $approval->price_b, 2, '.', '')) }}"
                                            class="ml-auto block w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                        >
                                        <p class="mt-1 text-right text-[10px] font-semibold text-slate-400">/{{ $priceUnit === 'piece' ? 'PCE' : strtoupper(str_replace('_', ' ', $priceUnit)) }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            inputmode="decimal"
                                            name="prices[{{ $approval->id }}][price_c]"
                                            value="{{ old("prices.{$approval->id}.price_c", number_format((float) $approval->price_c, 2, '.', '')) }}"
                                            class="ml-auto block w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                        >
                                        <p class="mt-1 text-right text-[10px] font-semibold text-slate-400">/{{ $priceUnit === 'piece' ? 'PCE' : strtoupper(str_replace('_', ' ', $priceUnit)) }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-xs font-semibold text-slate-500">
                                        @if ($approval->approved_at)
                                            Approved {{ $approval->approved_at->format('d M, h:i A') }}
                                        @elseif ($isAdminViewer)
                                            Ready for admin publish
                                        @else
                                            Waiting for admin approval
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <button
                                            type="button"
                                            onclick="saveSinglePriceRow({{ $approval->id }})"
                                            id="row-save-btn-{{ $approval->id }}"
                                            class="inline-flex items-center justify-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-black uppercase tracking-wider transition-all border-none cursor-pointer shadow-sm active:scale-95"
                                        >
                                            Save
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-semibold text-slate-600">{{ $isAdminViewer ? 'Saving updates daily prices and shop invoices directly.' : 'Saving updates price proposals for admin review.' }}</p>
                    <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-cyan-600 px-6 py-3 text-sm font-black text-white hover:bg-cyan-500 shadow-md transition-all border-none cursor-pointer">
                        {{ $isAdminViewer ? 'Save And Publish Prices' : 'Save And Send To Admin' }}
                    </button>
                </div>
            @endif
        </form>

        <div id="price-board-settings-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/45 px-4">
            <div class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Price Board Settings</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950">Approval controls</h3>
                        <p class="mt-2 text-sm text-slate-600">Same-price products can skip admin review while changed prices keep the publish workflow.</p>
                    </div>
                    <button type="button" onclick="closePriceBoardSettingsModal()" class="rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('purchasing.prices.settings.update') }}" class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
                    @csrf
                    <input type="hidden" name="date" value="{{ $purchaseDate }}">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="movement" value="{{ $movement }}">
                    <input type="hidden" name="sort" value="{{ $sort }}">

                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Automatic Approval</p>
                    <h4 class="mt-1 text-sm font-black text-emerald-950">Same purchase price</h4>
                    <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-2xl border border-emerald-200 bg-white px-4 py-3">
                        <input type="checkbox" name="auto_approve_same_purchase_price" value="1" @checked($autoApproveSamePurchasePrice) class="mt-1 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                        <span>
                            <span class="block text-sm font-black text-slate-950">Approve automatically</span>
                            <span class="mt-1 block text-xs font-semibold leading-5 text-slate-500">Products with the same average purchase price as the previous approved day will be marked approved and keep the previous selling prices.</span>
                        </span>
                    </label>
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" onclick="closePriceBoardSettingsModal()" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                            Close
                        </button>
                        <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white transition hover:bg-emerald-700">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                function openPriceBoardSettingsModal() {
                    const modal = document.getElementById('price-board-settings-modal');
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    }
                }

                function closePriceBoardSettingsModal() {
                    const modal = document.getElementById('price-board-settings-modal');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                }

                function openPriceBoardReviewSection() {
                    const reviewSection = document.getElementById('price-board-review-section');
                    if (reviewSection) {
                        reviewSection.classList.remove('hidden');
                        reviewSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    togglePriceBoardFinalSubmit();
                }

                async function saveSinglePriceRow(approvalId) {
                    const btn = document.getElementById('row-save-btn-' + approvalId);
                    const row = btn?.closest('tr');
                    if (!btn || !row) return;

                    const priceA = row.querySelector('input[name*="[price_a]"]')?.value || '0';
                    const priceB = row.querySelector('input[name*="[price_b]"]')?.value || priceA;
                    const priceC = row.querySelector('input[name*="[price_c]"]')?.value || priceA;
                    const priceUnit = row.querySelector('select[name*="[price_unit]"]')?.value || '';
                    const csrfToken = document.querySelector('input[name="_token"]')?.value || '';
                    const purchaseDate = "{{ $purchaseDate }}";

                    const originalText = btn.textContent;
                    btn.disabled = true;
                    btn.textContent = 'Saving...';
                    btn.className = 'inline-flex items-center justify-center rounded-xl bg-slate-400 text-white px-3.5 py-2 text-xs font-black uppercase tracking-wider opacity-70 border-none cursor-not-allowed';

                    try {
                        const response = await fetch("{{ url('purchasing/prices') }}/" + approvalId + "/save-row", {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                price_a: priceA,
                                price_b: priceB,
                                price_c: priceC,
                                price_unit: priceUnit,
                                date: purchaseDate,
                            }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            btn.textContent = 'Saved ✓';
                            btn.className = 'inline-flex items-center justify-center rounded-xl bg-emerald-600 text-white px-3.5 py-2 text-xs font-black uppercase tracking-wider border-none shadow-md';
                            row.classList.add('bg-emerald-50/40');
                            setTimeout(() => {
                                btn.disabled = false;
                                btn.textContent = 'Save';
                                btn.className = 'inline-flex items-center justify-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-black uppercase tracking-wider transition-all border-none cursor-pointer shadow-sm active:scale-95';
                                row.classList.remove('bg-emerald-50/40');
                            }, 2000);
                        } else {
                            alert(data.message || 'Failed to save price row.');
                            btn.disabled = false;
                            btn.textContent = originalText;
                            btn.className = 'inline-flex items-center justify-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-black uppercase tracking-wider transition-all border-none cursor-pointer shadow-sm active:scale-95';
                        }
                    } catch (err) {
                        alert('Network error while saving price row.');
                        btn.disabled = false;
                        btn.textContent = originalText;
                        btn.className = 'inline-flex items-center justify-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-black uppercase tracking-wider transition-all border-none cursor-pointer shadow-sm active:scale-95';
                    }
                }

                document.addEventListener('DOMContentLoaded', () => {
                    if (@json(request()->boolean('settings'))) {
                        openPriceBoardSettingsModal();
                    }

                    // Auto-copy Price A to Price B and Price C if unedited
                    document.querySelectorAll('input[name*="[price_a]"]').forEach(inputA => {
                        inputA.addEventListener('input', function() {
                            const row = this.closest('tr');
                            if (!row) return;
                            const inputB = row.querySelector('input[name*="[price_b]"]');
                            const inputC = row.querySelector('input[name*="[price_c]"]');
                            const val = this.value;
                            if (inputB && (!inputB.value || inputB.value === '0.00' || inputB.dataset.autoCopied === 'true')) {
                                inputB.value = val;
                                inputB.dataset.autoCopied = 'true';
                            }
                            if (inputC && (!inputC.value || inputC.value === '0.00' || inputC.dataset.autoCopied === 'true')) {
                                inputC.value = val;
                                inputC.dataset.autoCopied = 'true';
                            }
                        });
                    });

                    const searchInput = document.getElementById('search');
                    if (searchInput) {
                        searchInput.addEventListener('input', function() {
                            const query = this.value.toLowerCase().trim();
                            const rows = document.querySelectorAll('.price-row');
                            rows.forEach(row => {
                                const text = row.getAttribute('data-search-text') || '';
                                row.style.display = text.includes(query) ? '' : 'none';
                            });
                        });
                    }

                    // 30-second auto-refresh timer (pauses while user is typing/editing)
                    let countdownSeconds = 30;
                    let isUserTyping = false;

                    const updateTimerUI = () => {
                        const timerSpan = document.getElementById('refresh-timer-seconds');
                        if (!timerSpan) return;
                        if (isUserTyping) {
                            timerSpan.textContent = '30 (paused)';
                        } else {
                            timerSpan.textContent = String(countdownSeconds);
                        }
                    };

                    document.addEventListener('focusin', (e) => {
                        if (e.target && ['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
                            isUserTyping = true;
                            updateTimerUI();
                        }
                    });

                    document.addEventListener('focusout', () => {
                        setTimeout(() => {
                            const activeTag = document.activeElement ? document.activeElement.tagName : '';
                            if (!['INPUT', 'TEXTAREA'].includes(activeTag)) {
                                isUserTyping = false;
                                updateTimerUI();
                            }
                        }, 300);
                    });

                    setInterval(() => {
                        if (isUserTyping) {
                            countdownSeconds = 30;
                            updateTimerUI();
                            return;
                        }

                        countdownSeconds--;
                        updateTimerUI();

                        if (countdownSeconds <= 0) {
                            window.location.reload();
                        }
                    }, 1000);
                });

                function shareOnWhatsApp(group = 'A') {
                    const rows = Array.from(document.querySelectorAll('.price-row')).filter(r => r.style.display !== 'none' && r.offsetParent !== null);
                    if (rows.length === 0) {
                        alert('No products available to share.');
                        return;
                    }

                    let dateStr = "{{ \Illuminate\Support\Carbon::parse($purchaseDate)->format('d M Y') }}";
                    let text = "*GL FRESH - UPDATED DAILY PRICES (" + dateStr + ")*\n";
                    text += "-----------------------------------------\n\n";

                    rows.forEach((row, idx) => {
                        let name = row.getAttribute('data-name') || '';
                        let unit = row.getAttribute('data-unit') || '';
                        let attrKey = 'data-price-' + group.toLowerCase();
                        let targetPrice = row.getAttribute(attrKey) || row.getAttribute('data-price') || '0.00';

                        text += (idx + 1) + ". *" + name + "* (" + unit + ") - ₹" + targetPrice + "\n";
                    });

                    let url = "https://api.whatsapp.com/send?text=" + encodeURIComponent(text);
                    window.open(url, '_blank');
                }
            </script>
        @endpush
    @endonce
@endsection
