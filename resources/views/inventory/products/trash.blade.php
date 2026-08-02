<x-layouts.inventory title="Deleted Products">

    <x-slot:actions>
        @can('inventory.product.update')
            <a href="{{ route('inventory.products.measures.bulk') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 shadow-sm transition-colors hover:bg-emerald-100">
                Bulk Measures
            </a>
        @endcan
        @can('inventory.category.view')
            <a href="{{ route('inventory.categories.index') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                Categories
            </a>
        @endcan
        <a href="{{ route('inventory.products.create') }}"
           id="add-product-btn"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Product
        </a>
    </x-slot:actions>

    {{-- Tabs --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-6">
            <a href="{{ route('inventory.products.index') }}"
               class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm">
                All Products
            </a>
            <a href="{{ route('inventory.products.index', ['status' => 'active']) }}"
               class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm">
                Active
            </a>
            <a href="{{ route('inventory.products.index', ['status' => 'inactive']) }}"
               class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm">
                Inactive
            </a>
            @if(auth()->user()?->hasRole('admin'))
                <a href="{{ route('inventory.products.trash') }}"
                   class="border-red-500 text-red-600 whitespace-nowrap pb-3 px-1 border-b-2 font-semibold text-sm flex items-center gap-2">
                    Deleted Products
                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700 font-bold">
                        {{ $products->total() }}
                    </span>
                </a>
            @endif
        </nav>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('inventory.products.trash') }}" class="flex flex-wrap items-center gap-3 mb-6">
        <div class="relative flex-1 min-w-48">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input id="search-input" type="text" name="search" value="{{ request('search') }}" placeholder="Search deleted products by name or SKU…"
                   class="w-full border border-gray-200 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
        </div>
        <button type="submit" class="px-4 py-2 text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">Search</button>
        @if(request()->filled('search'))
            <a href="{{ route('inventory.products.trash') }}" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">Clear</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Deleted Products Trash</h2>
            <span class="text-xs text-gray-500">{{ $products->total() }} deleted products</span>
        </div>

        @if($products->isEmpty())
        <div class="py-16 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900">No deleted products</p>
            <p class="text-xs text-gray-500 mt-1">Accidentally deleted products will appear here and can be restored.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Base Unit</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Deleted Date</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($products as $product)
                    @php
                        $hasHistory = $product->stockBatches()->exists()
                            || $product->stockMovements()->exists()
                            || $product->dailyPrices()->exists()
                            || $product->wastageEntries()->exists();
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                @if($product->image)
                                    <img src="{{ $product->getImageUrl() }}" class="w-8 h-8 rounded-lg object-cover shrink-0 grayscale opacity-60" alt="{{ $product->name }}">
                                @else
                                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                                        <span class="text-gray-500 text-xs font-bold">{{ strtoupper(substr($product->name, 0, 1)) }}</span>
                                    </div>
                                @endif
                                <div>
                                    <p class="font-medium text-gray-900 line-through text-gray-500">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-400 sm:hidden">{{ $product->sku }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 hidden sm:table-cell">
                            <code class="text-xs font-mono text-gray-600 bg-gray-100 px-2 py-0.5 rounded">{{ $product->sku }}</code>
                        </td>
                        <td class="px-6 py-4 hidden md:table-cell text-gray-600">
                            {{ $product->category?->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 hidden sm:table-cell">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                {{ strtoupper($product->unit) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500">
                            {{ $product->deleted_at?->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('inventory.products.restore', $product->getRouteKey()) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-colors"
                                            title="Restore Product">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                        Restore
                                    </button>
                                </form>

                                @if(auth()->user()?->hasRole('admin'))
                                    <form method="POST" action="{{ route('inventory.products.force-delete', $product->getRouteKey()) }}"
                                          onsubmit="return confirm('This permanently deletes the product. Restore is recommended when the product has previous orders, prices, inventory or accounting records.\n\nAre you sure you want to permanently delete {{ addslashes($product->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                @disabled($hasHistory)
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg {{ $hasHistory ? 'bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed' : 'bg-red-50 text-red-700 border border-red-200 hover:bg-red-100' }} transition-colors"
                                                title="{{ $hasHistory ? 'Cannot delete product with transaction history' : 'Permanently Delete Product' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                            Delete Permanently
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($products->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $products->links() }}
        </div>
        @endif
        @endif
    </div>

</x-layouts.inventory>
