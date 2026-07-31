<x-layouts.inventory title="Assign Products: {{ $category->name }}">

    <x-slot:actions>
        <a href="{{ route('inventory.categories.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
            Back to Categories
        </a>
    </x-slot:actions>

    <div class="max-w-6xl space-y-6">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-gray-100 pb-5 mb-5">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Manage Products for "{{ $category->name }}"</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Check products to assign them to this category, or uncheck them to remove them.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="selectAll(true)"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 cursor-pointer">
                        Select All
                    </button>
                    <button type="button" onclick="selectAll(false)"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 cursor-pointer">
                        Clear All
                    </button>
                </div>
            </div>

            {{-- Client-side search input --}}
            <div class="relative mb-6">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input type="text" id="product-search" placeholder="Search products by name or SKU to filter the list below…"
                       class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
            </div>

            <form method="POST" action="{{ route('inventory.categories.products.update', $category) }}" class="space-y-6">
                @csrf

                {{-- Products Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 max-h-[500px] overflow-y-auto p-1 border border-slate-100 rounded-xl bg-slate-50/50">
                    @forelse($products as $product)
                        <label data-product-row
                               data-product-name="{{ $product->name }}"
                               data-product-sku="{{ $product->sku }}"
                               class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-200 bg-white hover:border-brand-300 hover:shadow-sm transition-all cursor-pointer select-none">
                            <input type="checkbox" name="product_ids[]" value="{{ $product->id }}"
                                   data-product-checkbox
                                   @checked($product->category_id === $category->id)
                                   class="h-4.5 w-4.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500 focus:ring-offset-0 mt-0.5 cursor-pointer">
                            <div class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-gray-900">{{ $product->name }}</span>
                                <span class="block text-[10px] font-mono text-gray-400 mt-0.5">{{ $product->sku }}</span>

                                {{-- Category assignment status --}}
                                @if($product->category_id === $category->id)
                                    <span class="inline-flex mt-1.5 items-center rounded-full bg-green-50 px-1.5 py-0.5 text-[9px] font-bold text-green-700 border border-green-200">
                                        In this category
                                    </span>
                                @elseif($product->category)
                                    <span class="inline-flex mt-1.5 items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold text-amber-700 border border-amber-200">
                                        In: {{ $product->category->name }}
                                    </span>
                                @else
                                    <span class="inline-flex mt-1.5 items-center rounded-full bg-slate-50 px-1.5 py-0.5 text-[9px] font-semibold text-slate-500 border border-slate-200">
                                        Unassigned
                                    </span>
                                @endif
                            </div>
                        </label>
                    @empty
                        <div class="col-span-full py-12 text-center text-gray-400 italic">
                            No products found in catalog.
                        </div>
                    @endforelse
                </div>

                {{-- Form Actions --}}
                <div class="flex items-center gap-3 pt-5 border-t border-gray-100">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm cursor-pointer font-bold">
                        Save Category Products
                    </button>
                    <a href="{{ route('inventory.categories.index') }}"
                       class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('product-search');
            if (searchInput) {
                searchInput.addEventListener('input', function (e) {
                    const search = e.target.value.toLowerCase();
                    document.querySelectorAll('[data-product-row]').forEach(function (row) {
                        const name = row.getAttribute('data-product-name').toLowerCase();
                        const sku = row.getAttribute('data-product-sku').toLowerCase();
                        if (name.includes(search) || sku.includes(search)) {
                            row.classList.remove('hidden');
                        } else {
                            row.classList.add('hidden');
                        }
                    });
                });
            }
        });

        function selectAll(checked) {
            document.querySelectorAll('[data-product-row]:not(.hidden) [data-product-checkbox]').forEach(function (cb) {
                cb.checked = checked;
            });
        }
    </script>
    @endpush

</x-layouts.inventory>
