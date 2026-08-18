<x-layouts.admin title="Warehouses">

    <x-slot:actions>
        <a href="{{ route('admin.warehouses.create') }}"
           id="add-warehouse-btn"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Warehouse
        </a>
    </x-slot:actions>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-xs">
            <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- Tabs Navigation --}}
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.warehouses.index') }}"
           class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-xs font-bold transition-all {{ $tab === 'warehouses' ? 'border-brand-300 bg-brand-50 text-brand-700 shadow-xs ring-2 ring-brand-500/10' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900' }}">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
            </svg>
            <span>All Warehouses</span>
            <span class="rounded-full px-2 py-0.5 text-[11px] font-extrabold {{ $tab === 'warehouses' ? 'bg-brand-200/70 text-brand-800' : 'bg-gray-100 text-gray-600' }}">
                {{ $warehouses->count() }}
            </span>
        </a>

        <a href="{{ route('admin.warehouses.index', ['tab' => 'unallocated']) }}"
           class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-xs font-bold transition-all {{ $tab === 'unallocated' ? 'border-amber-300 bg-amber-50 text-amber-900 shadow-xs ring-2 ring-amber-500/10' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900' }}">
            <svg class="h-4 w-4 {{ $unallocatedCount > 0 ? 'text-amber-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <span>Not Allocated</span>
            <span class="rounded-full px-2 py-0.5 text-[11px] font-extrabold {{ $unallocatedCount > 0 ? 'bg-amber-200 text-amber-900' : 'bg-gray-100 text-gray-600' }}">
                {{ $unallocatedCount }}
            </span>
        </a>
    </div>

    @if($tab === 'warehouses')
        {{-- Notice Banner if unallocated items exist --}}
        @if($unallocatedCount > 0)
            <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-linear-to-r from-amber-50 to-orange-50 p-4 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-amber-950">
                            {{ $unallocatedCount }} {{ Str::plural('item', $unallocatedCount) }} not allocated to any warehouse
                        </p>
                        <p class="text-xs text-amber-700">
                            These products are missing a default warehouse assignment and will not show up in warehouse-specific picking sheets.
                        </p>
                    </div>
                </div>
                <a href="{{ route('admin.warehouses.index', ['tab' => 'unallocated']) }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-amber-700 transition-colors shrink-0 shadow-xs">
                    <span>View Unallocated Items</span>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>
        @endif

        {{-- Warehouses Table --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-xs">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">All Warehouses</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Manage storage locations and their allocated product lines.</p>
                </div>
                <span class="text-xs font-semibold text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full">{{ $warehouses->count() }} warehouses</span>
            </div>

            @if($warehouses->isEmpty())
            <div class="py-16 text-center">
                <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-900">No warehouses found</p>
                <p class="text-xs text-gray-500 mt-1">Get started by adding your first warehouse.</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Warehouse</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Allocated Items</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($warehouses as $wh)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-bold text-gray-900 block">{{ $wh->name }}</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs font-bold text-slate-500">
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-slate-700">{{ $wh->code }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700 border border-brand-200/60">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                    </svg>
                                    {{ $wh->products_count }} {{ Str::plural('product', $wh->products_count) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium border {{ $wh->is_active ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                                    {{ $wh->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.warehouses.edit', $wh) }}"
                                       class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                                       title="Edit Warehouse">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </a>

                                    <form method="POST" action="{{ route('admin.warehouses.destroy', $wh) }}"
                                          onsubmit="return confirm('Are you sure you want to delete warehouse {{ $wh->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete Warehouse">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    @else
        {{-- Unallocated Items Filter Bar --}}
        <div class="mb-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-xs">
            <form method="GET" action="{{ route('admin.warehouses.index') }}" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="unallocated">

                <div class="flex-1 min-w-[200px]">
                    <label for="search" class="sr-only">Search items</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                               placeholder="Search unallocated item by name or SKU…"
                               class="w-full rounded-lg border border-gray-200 bg-gray-50/50 pl-9 pr-3 py-2 text-xs text-gray-800 placeholder-gray-400 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    </div>
                </div>

                <div class="w-48">
                    <label for="category_id" class="sr-only">Category</label>
                    <select name="category_id" id="category_id"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50/50 px-3 py-2 text-xs text-gray-800 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-36">
                    <label for="status" class="sr-only">Status</label>
                    <select name="status" id="status"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50/50 px-3 py-2 text-xs text-gray-800 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active Only</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive Only</option>
                    </select>
                </div>

                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-3.5 py-2 text-xs font-semibold text-white hover:bg-slate-800 transition-colors shadow-xs">
                    Filter
                </button>

                @if(request('search') || request('category_id') || request('status'))
                    <a href="{{ route('admin.warehouses.index', ['tab' => 'unallocated']) }}"
                       class="text-xs font-semibold text-gray-500 hover:text-gray-800 transition-colors">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Bulk Allocation Bar --}}
        @if($activeWarehouses->isNotEmpty() && $unallocatedProducts && $unallocatedProducts->isNotEmpty())
            <form id="bulk-allocate-form" method="POST" action="{{ route('admin.warehouses.bulk-allocate') }}"
                  class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand-200 bg-brand-50/70 p-3.5 shadow-xs">
                @csrf
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-xs font-bold text-brand-900 cursor-pointer">
                        <input type="checkbox" id="select-all-checkbox"
                               class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
                        <span>Select All on Page</span>
                    </label>
                    <span id="selected-count-badge" class="rounded-full bg-brand-200 px-2.5 py-0.5 text-[11px] font-extrabold text-brand-900 hidden">
                        0 selected
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <select name="warehouse_id" required
                            class="rounded-lg border border-brand-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                        <option value="" disabled selected>Select Destination Warehouse…</option>
                        @foreach($activeWarehouses as $targetWh)
                            <option value="{{ $targetWh->id }}">{{ $targetWh->name }} ({{ $targetWh->code }})</option>
                        @endforeach
                    </select>
                    <button type="submit" id="bulk-allocate-btn" disabled
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-brand-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-xs cursor-pointer">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <span>Allocate Selected</span>
                    </button>
                </div>
            </form>
        @endif

        {{-- Unallocated Products Table --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-xs">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">Items Not Allocated in Any Warehouse</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Showing products with no default warehouse assigned.</p>
                </div>
                <span class="text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 px-3 py-1 rounded-full">
                    {{ $unallocatedProducts ? $unallocatedProducts->total() : 0 }} items
                </span>
            </div>

            @if(!$unallocatedProducts || $unallocatedProducts->isEmpty())
                <div class="py-16 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-sm font-bold text-gray-900">
                        @if(request('search') || request('category_id') || request('status'))
                            No matching unallocated items found
                        @else
                            All items are allocated to warehouses!
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        @if(request('search') || request('category_id') || request('status'))
                            Try clearing your search or filters to see other products.
                        @else
                            Every active product is currently assigned to a default warehouse.
                        @endif
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="w-10 px-4 py-3 text-center">
                                    <span class="sr-only">Select</span>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Unit</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Assign Warehouse</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($unallocatedProducts as $product)
                            <tr class="hover:bg-amber-50/20 transition-colors">
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" name="product_ids[]" value="{{ $product->id }}"
                                           form="bulk-allocate-form"
                                           class="product-row-checkbox h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="min-w-0">
                                            <a href="{{ route('inventory.products.edit', $product) }}"
                                               class="font-bold text-gray-900 hover:text-brand-600 transition-colors block truncate max-w-xs sm:max-w-md">
                                                {{ $product->name }}
                                            </a>
                                            <span class="font-mono text-[11px] font-bold text-slate-500">
                                                {{ $product->sku }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                        {{ $product->category?->name ?? 'No Category' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs font-bold text-slate-600 uppercase">
                                    {{ $product->unit }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium border {{ $product->is_active ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($activeWarehouses->isNotEmpty())
                                        <form method="POST" action="{{ route('admin.warehouses.allocate-product') }}" class="inline-flex items-center gap-1.5 justify-end">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                            <select name="warehouse_id" required
                                                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-700 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                                <option value="" disabled selected>Choose Warehouse…</option>
                                                @foreach($activeWarehouses as $wh)
                                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit"
                                                    class="rounded-lg bg-brand-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-brand-700 transition-colors shadow-xs cursor-pointer">
                                                Assign
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400 italic">No active warehouses</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($unallocatedProducts->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $unallocatedProducts->links() }}
                    </div>
                @endif
            @endif
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const selectAll = document.getElementById('select-all-checkbox');
                const rowCheckboxes = document.querySelectorAll('.product-row-checkbox');
                const bulkBtn = document.getElementById('bulk-allocate-btn');
                const countBadge = document.getElementById('selected-count-badge');

                function updateBulkState() {
                    const checked = document.querySelectorAll('.product-row-checkbox:checked');
                    const count = checked.length;

                    if (bulkBtn) {
                        bulkBtn.disabled = count === 0;
                    }
                    if (countBadge) {
                        if (count > 0) {
                            countBadge.textContent = count + ' selected';
                            countBadge.classList.remove('hidden');
                        } else {
                            countBadge.classList.add('hidden');
                        }
                    }
                    if (selectAll) {
                        selectAll.checked = count > 0 && count === rowCheckboxes.length;
                        selectAll.indeterminate = count > 0 && count < rowCheckboxes.length;
                    }
                }

                if (selectAll) {
                    selectAll.addEventListener('change', (e) => {
                        rowCheckboxes.forEach(cb => cb.checked = e.target.checked);
                        updateBulkState();
                    });
                }

                rowCheckboxes.forEach(cb => {
                    cb.addEventListener('change', updateBulkState);
                });
            });
        </script>
    @endif

</x-layouts.admin>
