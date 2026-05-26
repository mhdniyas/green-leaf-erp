<x-layouts.app title="Edit Requisition Preset — {{ $preset->name }}">
    <div class="max-w-4xl mx-auto px-4 py-8 animate-fade-in">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('requisitions.presets.index') }}" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1.5 transition-colors mb-2">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                Back to Presets
            </a>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Edit Preset: <span class="text-emerald-600">{{ $preset->name }}</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Modify preset list name and update default quantities or items in the template list.</p>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50/50 p-4 flex flex-col gap-1.5 animate-fade-in">
                <div class="text-xs font-bold text-red-800 flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                    Please fix the following validation errors:
                </div>
                <ul class="list-disc list-inside text-[11px] text-red-700 space-y-0.5 pl-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('requisitions.presets.update', $preset->id) }}" method="POST" id="preset-form" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Preset Name Input -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div class="space-y-1.5">
                    <label for="preset-name" class="block text-xs font-bold text-slate-700 uppercase tracking-wide">Preset List Name</label>
                    <input
                        type="text"
                        name="name"
                        id="preset-name"
                        value="{{ old('name', $preset->name) }}"
                        required
                        placeholder="e.g. Weekend Specials, Monday Basics..."
                        class="w-full text-sm rounded-xl border border-slate-200 bg-slate-50/20 px-4 py-3 placeholder-slate-400 focus:bg-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/10 transition-all font-bold text-slate-800"
                    >
                </div>
            </div>

            <!-- Preset Builder (Add Items) -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100 mb-5">Add Products & Quantities</h2>
                
                <!-- Fuzzy Search -->
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 mb-6 relative">
                    <div class="relative w-full">
                        <input
                            type="text"
                            id="catalog-search"
                            oninput="searchProducts()"
                            placeholder="Fuzzy search products to add..."
                            class="w-full text-sm rounded-xl border border-slate-200 bg-white pl-10 pr-4 py-2.5 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/10 transition-all"
                        >
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        
                        <!-- Autocomplete Dropdown List -->
                        <div id="search-autocomplete-dropdown" class="hidden absolute left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-slate-200 rounded-xl shadow-lg z-30 divide-y divide-slate-100">
                            <!-- Injected dynamically -->
                        </div>
                    </div>
                </div>

                <!-- Selected Products Table -->
                <div class="space-y-4">
                    <!-- Empty State -->
                    <div id="preset-empty-state" class="text-center py-12 border-2 border-dashed border-slate-200 rounded-3xl">
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">No items added to this preset list</h3>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Use the fuzzy search box above to search for catalog products and add them to this preset template.</p>
                    </div>

                    <!-- Products Table -->
                    <div id="preset-table-wrapper" class="hidden overflow-x-auto border border-slate-200 rounded-2xl">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    <th class="py-3 px-4">Product</th>
                                    <th class="py-3 px-4 text-center w-[180px]">Default Qty</th>
                                    <th class="py-3 px-4 text-left w-[80px]">Unit</th>
                                    <th class="py-3 px-4 text-center w-[80px]">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700" id="preset-table-body">
                                <!-- Injected rows -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('requisitions.presets.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-6 py-2.5 rounded-xl transition-all cursor-pointer focus:outline-none border border-slate-200">
                    Cancel
                </a>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-6 py-2.5 rounded-xl shadow-md transition-all cursor-pointer focus:outline-none">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Frontend Script for Catalog Search & Table Population -->
    <script>
        const PRODUCTS_CATALOG = @json($products);
        let selectedProducts = {};

        // Prefill existing preset items
        @foreach($preset->items as $item)
            selectedProducts[{{ $item->product_id }}] = {
                id: {{ $item->product_id }},
                name: "{{ $item->product->name }}",
                sku: "{{ $item->product->sku }}",
                unit: "{{ $item->product->unit }}",
                quantity: {{ floatval($item->quantity) }}
            };
        @endforeach

        // Initial render on load
        document.addEventListener('DOMContentLoaded', () => {
            renderTable();
        });

        function searchProducts() {
            const query = document.getElementById('catalog-search').value.toLowerCase().trim();
            const dropdown = document.getElementById('search-autocomplete-dropdown');
            if (!dropdown) return;

            if (query.length === 0) {
                dropdown.classList.add('hidden');
                dropdown.innerHTML = '';
                return;
            }

            const matches = PRODUCTS_CATALOG.filter(p => 
                p.name.toLowerCase().includes(query) || 
                p.sku.toLowerCase().includes(query)
            ).slice(0, 8);

            if (matches.length === 0) {
                dropdown.innerHTML = '<div class="px-4 py-3 text-xs text-slate-500 italic">No matching products found</div>';
            } else {
                dropdown.innerHTML = matches.map(p => {
                    const isAdded = selectedProducts[p.id] !== undefined;
                    return `
                        <button type="button" onclick="${isAdded ? '' : `addProduct(${JSON.stringify(p).replace(/"/g, '&quot;')})`}" class="w-full text-left px-4 py-3 hover:bg-slate-50 flex items-center justify-between transition-colors focus:outline-none border-b border-slate-100 last:border-0 cursor-pointer border-0">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-slate-800">${p.name}</span>
                                <span class="text-[10px] text-slate-400 font-medium">${p.sku}</span>
                            </div>
                            <div class="flex items-center">
                                ${isAdded 
                                    ? '<span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded font-bold">Already Added</span>'
                                    : '<span class="text-[10px] text-emerald-600 font-extrabold">+ Add to Preset</span>'
                                }
                            </div>
                        </button>
                    `;
                }).join('');
            }

            dropdown.classList.remove('hidden');
        }

        function addProduct(product) {
            if (selectedProducts[product.id] === undefined) {
                selectedProducts[product.id] = {
                    id: product.id,
                    name: product.name,
                    sku: product.sku,
                    unit: product.unit,
                    quantity: 10 // Default preset quantity
                };
            }
            renderTable();
            document.getElementById('catalog-search').value = '';
            document.getElementById('search-autocomplete-dropdown').classList.add('hidden');
            
            // Auto focus new input
            setTimeout(() => {
                const input = document.getElementById(`qty-${product.id}`);
                if (input) {
                    input.focus();
                    input.select();
                }
            }, 50);
        }

        function removeProduct(id) {
            delete selectedProducts[id];
            renderTable();
        }

        function updateQty(id, value) {
            if (selectedProducts[id]) {
                selectedProducts[id].quantity = parseFloat(value) || 0;
            }
        }

        function renderTable() {
            const tbody = document.getElementById('preset-table-body');
            const wrapper = document.getElementById('preset-table-wrapper');
            const emptyState = document.getElementById('preset-empty-state');
            
            const productIds = Object.keys(selectedProducts);

            if (productIds.length === 0) {
                wrapper.classList.add('hidden');
                emptyState.classList.remove('hidden');
                tbody.innerHTML = '';
                return;
            }

            emptyState.classList.add('hidden');
            wrapper.classList.remove('hidden');

            tbody.innerHTML = productIds.map(id => {
                const p = selectedProducts[id];
                return `
                    <tr class="hover:bg-slate-50/20">
                        <td class="py-4 px-4">
                            <span class="font-bold text-slate-800 text-xs">${p.name}</span>
                            <span class="block text-[10px] text-slate-400 font-mono mt-0.5">${p.sku}</span>
                            <input type="hidden" name="items[${p.id}][product_id]" value="${p.id}">
                        </td>
                        <td class="py-3 px-4 text-center">
                            <div class="relative rounded-xl shadow-sm max-w-[150px] mx-auto">
                                <input
                                    type="number"
                                    name="items[${p.id}][quantity]"
                                    id="qty-${p.id}"
                                    value="${p.quantity}"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    oninput="updateQty(${p.id}, this.value)"
                                    class="w-full text-xs font-bold text-slate-900 bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 focus:outline-none focus:border-emerald-500 text-right pr-12 focus:bg-white focus:ring-2 focus:ring-emerald-500/10 transition-all"
                                >
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-[9px] font-black text-slate-400 uppercase">${p.unit}</span>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 text-left font-black text-slate-400 uppercase text-[10px]">
                            ${p.unit}
                        </td>
                        <td class="py-4 px-4 text-center">
                            <button type="button" onclick="removeProduct(${p.id})" class="text-red-500 hover:text-red-700 transition-colors focus:outline-none bg-transparent border-0 cursor-pointer">
                                <svg class="w-4 h-4 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Close autocomplete dropdown on click outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('search-autocomplete-dropdown');
            const searchInput = document.getElementById('catalog-search');
            if (dropdown && !dropdown.contains(e.target) && e.target !== searchInput) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</x-layouts.app>
