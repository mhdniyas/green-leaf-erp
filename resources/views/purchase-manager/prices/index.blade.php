@extends('purchase-manager.layouts.app')

@section('title', 'Daily Price Board')
@section('page_title', 'Daily Price Board')
@section('page_description', 'Simple daily selling price update by shop category group.')

@section('content')
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('purchasing.prices.index') }}" class="grid gap-3 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <label for="search" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Product Search</label>
                    <div class="relative mt-2">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            id="search"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Search by product, SKU, or category"
                            autocomplete="off"
                            class="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-11 pr-12 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:outline-none"
                        >
                        <button
                            type="button"
                            id="clear-search"
                            class="{{ $search === '' ? 'hidden ' : '' }}absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 transition hover:text-slate-700"
                            aria-label="Clear product search"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <p class="mt-2 text-xs font-semibold text-slate-400">Products are sorted by today&apos;s order quantity for {{ \Illuminate\Support\Carbon::parse($targetBusinessDate)->format('d M Y') }}.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white">
                        Search
                    </button>
                    <a href="{{ route('purchasing.price-groups.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">
                        Shop Price Categories
                    </a>
                </div>
            </form>
        </section>

        <form method="POST" action="{{ route('purchasing.prices.update') }}" class="purchase-manager-panel overflow-hidden">
            @csrf
            <input type="hidden" name="search" value="{{ $search }}">

            <div class="border-b border-slate-200 px-5 py-5">
                <div class="grid gap-4 lg:grid-cols-[1fr_360px] lg:items-end">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Final Selling Price</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Daily Price Board</h2>
                        <p class="mt-1 text-sm text-slate-500">Shop owners only see the value from their assigned category.</p>
                    </div>
                    <div>
                        <label for="reason" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Reason</label>
                        <input id="reason" name="reason" value="{{ old('reason') }}" placeholder="Daily purchase / market update" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Product</th>
                            <th class="px-5 py-4">SKU / Unit</th>
                            <th class="px-5 py-4 text-right">Today&apos;s Order</th>
                            @foreach ($selectedGroups as $group)
                                <th class="px-5 py-4 text-right">{{ $group->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody id="price-board-products" class="divide-y divide-slate-100 text-sm">
                        @forelse ($products as $product)
                            @php
                                $productPrices = $sellingPrices->get($product->id, collect())->keyBy('shop_price_group_id');
                                $searchIndex = strtolower(implode(' ', array_filter([
                                    $product->name,
                                    $product->sku,
                                    $product->unit,
                                    $product->category?->name,
                                ])));
                            @endphp
                            <tr data-search="{{ $searchIndex }}">
                                <td class="px-5 py-4">
                                    <p class="font-bold text-slate-950">{{ $product->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-400">{{ $product->category?->name }}</p>
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-500">{{ $product->sku }} · {{ strtoupper($product->unit) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <span class="inline-flex min-w-24 justify-end rounded-2xl bg-slate-100 px-3 py-2 text-sm font-black text-slate-800">
                                        {{ number_format((float) ($product->today_order_qty ?? 0), 2) }}
                                    </span>
                                </td>
                                @foreach ($selectedGroups as $group)
                                    @php
                                        $price = $productPrices->get($group->id);
                                        $value = old("simple_prices.{$product->id}.{$group->id}", $price ? number_format((float) $price->selling_price, 2, '.', '') : '');
                                    @endphp
                                    <td class="px-5 py-4">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            name="simple_prices[{{ $product->id }}][{{ $group->id }}]"
                                            value="{{ $value }}"
                                            placeholder="0.00"
                                            class="ml-auto block w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                        >
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr id="server-empty-row">
                                <td colspan="{{ 3 + $selectedGroups->count() }}" class="px-5 py-10 text-center text-sm font-semibold text-slate-500">
                                    No products matched this search.
                                </td>
                            </tr>
                        @endforelse
                        <tr id="dynamic-empty-row" class="hidden">
                            <td colspan="{{ 3 + $selectedGroups->count() }}" class="px-5 py-10 text-center text-sm font-semibold text-slate-500">
                                No products matched this search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end border-t border-slate-200 px-5 py-5">
                <x-purchase-manager.components.action-button type="submit" variant="primary">Save Daily Prices</x-purchase-manager.components.action-button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('search');
            const clearButton = document.getElementById('clear-search');
            const productTableBody = document.getElementById('price-board-products');
            const emptyRow = document.getElementById('dynamic-empty-row');
            const serverEmptyRow = document.getElementById('server-empty-row');

            if (!searchInput || !productTableBody || !emptyRow) {
                return;
            }

            const productRows = Array.from(productTableBody.querySelectorAll('tr[data-search]'));

            const syncProductSearch = () => {
                const query = searchInput.value.trim().toLowerCase();
                let visibleRows = 0;

                productRows.forEach((row) => {
                    const matches = query === '' || (row.dataset.search || '').includes(query);

                    row.classList.toggle('hidden', !matches);

                    if (matches) {
                        visibleRows += 1;
                    }
                });

                emptyRow.classList.toggle('hidden', visibleRows !== 0);
                serverEmptyRow?.classList.add('hidden');

                if (clearButton) {
                    clearButton.classList.toggle('hidden', query === '');
                }
            };

            searchInput.addEventListener('input', syncProductSearch);

            clearButton?.addEventListener('click', () => {
                searchInput.value = '';
                syncProductSearch();
                searchInput.focus();
            });

            syncProductSearch();
        });
    </script>
@endpush
