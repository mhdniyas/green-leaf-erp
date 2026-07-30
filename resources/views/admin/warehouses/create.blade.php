<x-layouts.admin title="Add Warehouse">

    <div class="max-w-7xl">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs">
            <h2 class="text-sm font-semibold text-gray-900 mb-6">Create New Warehouse</h2>

            <form method="POST" action="{{ route('admin.warehouses.store') }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_220px_160px] md:items-end">
                    <div>
                        <label for="name" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Warehouse Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                        @error('name')
                            <p class="text-red-600 text-xs mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="code" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Warehouse Code</label>
                        <input type="text" name="code" id="code" value="{{ old('code') }}" required placeholder="e.g. VEG-WH"
                               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                        @error('code')
                            <p class="text-red-600 text-xs mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-2.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                               class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <label for="is_active" class="text-xs font-bold text-gray-700 uppercase tracking-wide">Is Active</label>
                    </div>
                </div>

                <div class="pt-2">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide">Product Categories</label>
                        <span class="text-[10px] font-bold text-gray-400">{{ $categories->count() }} available</span>
                    </div>
                    <div class="max-h-72 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                            @foreach($categories as $category)
                                <label for="category-{{ $category->id }}" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-700">
                                    <input
                                        type="checkbox"
                                        name="category_ids[]"
                                        id="category-{{ $category->id }}"
                                        value="{{ $category->id }}"
                                        @checked(in_array((string) $category->id, old('category_ids', []), true))
                                        class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                    >
                                    <span>{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @error('category_ids')
                        <p class="text-red-600 text-xs mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                    @error('category_ids.*')
                        <p class="text-red-600 text-xs mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                @php
                    $selectedProductIds = collect(old('product_ids', []))->map(fn ($id) => (string) $id)->all();
                @endphp
                <div class="pt-2">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide">Default Warehouse Products</label>
                        <span class="text-[10px] font-bold text-gray-400">{{ count($selectedProductIds) }} selected</span>
                    </div>
                    <div class="max-h-96 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            @foreach($products as $product)
                                <label for="product-{{ $product->id }}" class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700">
                                    <input
                                        type="checkbox"
                                        name="product_ids[]"
                                        id="product-{{ $product->id }}"
                                        value="{{ $product->id }}"
                                        @checked(in_array((string) $product->id, $selectedProductIds, true))
                                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                    >
                                    <span class="min-w-0">
                                        <span class="block truncate font-black text-slate-900">{{ $product->name }}</span>
                                        <span class="mt-0.5 block text-[11px] font-bold text-slate-500">
                                            <span class="font-mono">{{ $product->sku }}</span>
                                            &middot; {{ $product->category?->name ?? 'No Category' }}
                                            &middot; {{ strtoupper($product->unit) }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @error('product_ids')
                        <p class="text-red-600 text-xs mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                    @error('product_ids.*')
                        <p class="text-red-600 text-xs mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 pt-6 border-t border-gray-100">
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm cursor-pointer">
                        Create Warehouse
                    </button>
                    <a href="{{ route('admin.warehouses.index') }}"
                       class="inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

</x-layouts.admin>
