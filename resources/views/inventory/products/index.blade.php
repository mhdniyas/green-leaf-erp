<x-layouts.inventory title="Products">

    <x-slot:actions>
        @if(auth()->user()?->hasRole('admin'))
            <button type="button"
                    onclick="document.getElementById('status-permission-modal')?.classList.remove('hidden')"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                Status Permissions
            </button>
        @endif
        @can('inventory.product.update')
            <a href="{{ route('inventory.products.measures.bulk') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 shadow-sm transition-colors hover:bg-emerald-100">
                Bulk Measures
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

    {{-- Filters --}}
    <form method="GET" action="{{ route('inventory.products.index') }}" class="flex flex-wrap items-center gap-3 mb-6">
        <div class="relative flex-1 min-w-48">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input id="search-input" type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or SKU…"
                   class="w-full border border-gray-200 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
        </div>
        <select id="category-filter" name="category_id"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select id="status-filter" name="status"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button type="submit" class="px-4 py-2 text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">Filter</button>
        @if(request()->hasAny(['search', 'category_id', 'status']))
            <a href="{{ route('inventory.products.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">Clear</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Product Catalog</h2>
            <span class="text-xs text-gray-500">{{ $products->total() }} products</span>
        </div>

        @if($products->isEmpty())
        <div class="py-16 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900">No products found</p>
            <p class="text-xs text-gray-500 mt-1">Add your first product to get started.</p>
            <a href="{{ route('inventory.products.create') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm text-brand-600 font-medium hover:underline">
                + Add Product
            </a>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Unit</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Last Status Change</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($products as $product)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                @if($product->image)
                                    <img src="{{ $product->getImageUrl() }}" class="w-8 h-8 rounded-lg object-cover shrink-0" alt="{{ $product->name }}">
                                @else
                                    <div class="w-8 h-8 rounded-lg bg-brand-100 flex items-center justify-center shrink-0">
                                        <span class="text-brand-700 text-xs font-bold">{{ strtoupper(substr($product->name, 0, 1)) }}</span>
                                    </div>
                                @endif
                                <div>
                                    <p class="font-medium text-gray-900">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-400 sm:hidden">{{ $product->sku }}</p>
                                    <p class="mt-1 text-[11px] font-medium text-gray-400 lg:hidden">
                                        Status:
                                        @if($product->statusChangedBy)
                                            {{ $product->statusChangedBy->name }} · {{ $product->status_changed_at?->format('d M, h:i A') }}
                                        @else
                                            Not recorded
                                        @endif
                                    </p>
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
                            @php
                                $baseUnit = $product->orderUnits->firstWhere('is_base', true);
                                $orderableUnits = $product->orderUnits
                                    ->where('is_orderable', true)
                                    ->pluck('unit')
                                    ->map(fn ($unit) => strtoupper((string) $unit))
                                    ->values();
                            @endphp
                            <div class="flex flex-wrap items-center gap-1">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ strtoupper($baseUnit?->unit ?? $product->unit) }}</span>
                                @if($orderableUnits->count() > 1)
                                    <span class="text-[11px] font-semibold text-gray-400">{{ $orderableUnits->join(' / ') }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if(auth()->user()?->hasRole('admin') || auth()->user()?->can('inventory.product.status.update'))
                                <form method="POST" action="{{ route('inventory.products.status.update', $product) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $product->is_active ? '0' : '1' }}">
                                    <button type="submit" class="group inline-flex items-center gap-2 rounded-full border px-2 py-1 text-xs font-black transition-colors {{ $product->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200' }}" title="Toggle product status">
                                        <span class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $product->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                            <span class="inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform {{ $product->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                        </span>
                                        <span>{{ $product->is_active ? 'Active' : 'Inactive' }}</span>
                                    </button>
                                </form>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $product->is_active ? 'border border-green-200 bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $product->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 hidden lg:table-cell">
                            @if($product->statusChangedBy)
                                <p class="text-xs font-semibold text-gray-900">{{ $product->statusChangedBy->name }}</p>
                                <p class="mt-0.5 text-[11px] font-medium text-gray-400">{{ $product->status_changed_at?->format('d M Y, h:i A') }}</p>
                            @else
                                <span class="text-xs font-medium text-gray-400">Not recorded</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('inventory.products.edit', $product) }}"
                                   class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </a>
                                <form method="POST" action="{{ route('inventory.products.destroy', $product) }}"
                                      onsubmit="return confirm('Delete {{ $product->name }}? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
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

        {{-- Pagination --}}
        @if($products->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $products->withQueryString()->links() }}
        </div>
        @endif
        @endif
    </div>

    @if(auth()->user()?->hasRole('admin'))
        <div id="status-permission-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 px-4 py-6">
            <div class="mx-auto w-full max-w-xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-700">Product Status</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950">Warehouse Receiver Permissions</h3>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Select receiver users who can activate or deactivate products.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('status-permission-modal')?.classList.add('hidden')" class="rounded-2xl border border-slate-200 px-3 py-2 text-sm font-black text-slate-500 hover:bg-slate-50">Close</button>
                </div>
                <form method="POST" action="{{ route('inventory.products.status-permissions.update', request()->only(['search', 'category_id', 'status'])) }}" class="p-6">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-2">
                        @forelse($warehouseReceivers as $receiver)
                            <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-black text-slate-950">{{ $receiver->name }}</span>
                                    <span class="block truncate text-xs font-semibold text-slate-500">{{ $receiver->email }}</span>
                                </span>
                                <input type="checkbox" name="user_ids[]" value="{{ $receiver->id }}" @checked($receiver->can('inventory.product.status.update')) class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            </label>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">
                                No warehouse receiver users found.
                            </div>
                        @endforelse
                    </div>
                    <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" onclick="document.getElementById('status-permission-modal')?.classList.add('hidden')" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white hover:bg-emerald-700">Save Permissions</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</x-layouts.inventory>
