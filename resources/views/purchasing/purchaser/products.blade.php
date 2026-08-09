<x-layouts.app title="Product Codes">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-2 sm:gap-4 sm:py-4 lg:max-w-6xl">
        @include('purchasing.purchaser.partials.feedback')

        {{-- Header Banner --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 p-3.5 text-white shadow-xs sm:p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-wider text-teal-400 sm:text-[10px]">Purchaser Catalog</p>
                    <h1 class="mt-0.5 text-base font-black tracking-tight sm:text-xl">Product Codes</h1>
                    @if(auth()->user()?->hasRole('admin') && $effectivePurchaser && ! $effectivePurchaser->is(auth()->user()))
                        <p class="mt-1 text-[11px] font-bold text-slate-300">Scoped to {{ $effectivePurchaser->name }}</p>
                    @endif
                </div>
                <button type="button" data-share-grocery class="inline-flex h-8 items-center justify-center rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-500 transition-colors shrink-0">
                    Share List &rarr;
                </button>
            </div>
        </section>

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('purchaser.products') }}" class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs sm:p-3">
            <div class="grid gap-2 grid-cols-2 md:grid-cols-[1fr_180px_130px_130px_auto] md:items-end">
                <div class="col-span-2 md:col-span-1">
                    <label for="product-search" class="mb-0.5 block text-[9px] font-black uppercase tracking-wider text-slate-500">Search</label>
                    <input id="product-search" type="search" name="search" value="{{ $search }}" placeholder="Product, code, category..." class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>
                <div>
                    <label for="product-category" class="mb-0.5 block text-[9px] font-black uppercase tracking-wider text-slate-500">Category</label>
                    <select id="product-category" name="category_id" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-700 focus:border-teal-500 focus:bg-white focus:outline-none">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) request('category_id') === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="product-status" class="mb-0.5 block text-[9px] font-black uppercase tracking-wider text-slate-500">Status</label>
                    <select id="product-status" name="status" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-700 focus:border-teal-500 focus:bg-white focus:outline-none">
                        <option value="">All Status</option>
                        <option value="active" @selected($selectedStatus === 'active')>Active</option>
                        <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div>
                    <label for="product-unit" class="mb-0.5 block text-[9px] font-black uppercase tracking-wider text-slate-500">Unit</label>
                    <select id="product-unit" name="unit" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-700 focus:border-teal-500 focus:bg-white focus:outline-none">
                        <option value="">All Units</option>
                        @foreach(['kg', 'box', 'piece', 'bag', 'bunch', 'full_bunch', 'packet', 'crate', 'tray', 'roll'] as $unit)
                            <option value="{{ $unit }}" @selected($selectedUnit === $unit)>{{ strtoupper($unit) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2 md:col-span-1 flex gap-1.5 pt-1 md:pt-0">
                    @if(request()->hasAny(['search', 'category_id', 'status', 'unit']))
                        <a href="{{ route('purchaser.products') }}" class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-slate-200 px-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Clear</a>
                    @endif
                    <button type="submit" class="inline-flex h-9 flex-1 items-center justify-center rounded-lg bg-teal-600 px-3 text-xs font-bold text-white transition-colors hover:bg-teal-700 md:flex-none">Filter</button>
                </div>
            </div>
        </form>

        {{-- Grocery List Action Bar --}}
        <section class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-2.5 shadow-xs sm:p-3">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-wider text-emerald-800 sm:text-[10px]">Grocery List</p>
                    <p class="text-xs font-bold text-emerald-950"><span data-selected-count>0</span> selected</p>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" data-clear-grocery class="inline-flex h-7 items-center rounded-lg border border-emerald-200 bg-white px-2.5 text-[10px] font-bold text-emerald-700 hover:bg-emerald-100 transition-colors">Clear</button>
                    <button type="button" data-share-grocery class="inline-flex h-7 items-center rounded-lg bg-emerald-700 px-3 text-[10px] font-bold text-white hover:bg-emerald-600 transition-colors">WhatsApp &rarr;</button>
                </div>
            </div>
        </section>

        {{-- Products grouped by Category --}}
        <section class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h2 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Category-wise Products</h2>
                <span class="text-xs font-bold text-slate-500">{{ $products->count() }} products</span>
            </div>

            @forelse($productsByCategory as $categoryName => $categoryProducts)
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    {{-- Category Header --}}
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-3 py-2">
                        <h3 class="text-xs font-black text-slate-950 sm:text-sm">{{ $categoryName }}</h3>
                        <span class="rounded-full bg-white border border-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $categoryProducts->count() }}</span>
                    </div>

                    {{-- Mobile View: Strict Single-Row Cards --}}
                    <div class="p-2 space-y-1.5 md:hidden">
                        @foreach($categoryProducts as $product)
                            @php
                                $unitLabel = strtoupper((string) $product->unit);
                            @endphp
                            <article data-grocery-row data-code="{{ $product->sku }}" data-name="{{ $product->name }}" data-unit="{{ $unitLabel }}" data-category="{{ $categoryName }}"
                                     class="flex items-center justify-between gap-1.5 rounded-lg border border-slate-200 bg-white p-2 shadow-xs transition hover:border-slate-300">
                                {{-- Left: Checkbox + SKU + Name --}}
                                <div class="min-w-0 flex-1 flex items-center gap-1.5">
                                    <input type="checkbox" data-grocery-check class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 shrink-0">
                                    <span class="inline-block rounded bg-slate-100 px-1 py-0.5 font-mono text-[9px] font-bold text-slate-700 shrink-0">#{{ $product->sku }}</span>
                                    <span class="font-bold text-slate-900 text-xs truncate">{{ $product->name }}</span>
                                </div>

                                {{-- Center: Qty Input --}}
                                <div class="shrink-0 w-16">
                                    <input type="text" data-grocery-qty placeholder="Qty" class="h-7 w-full rounded border border-slate-200 bg-slate-50 px-1.5 text-center text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                                </div>

                                {{-- Right: Unit & Status --}}
                                <div class="shrink-0 flex items-center gap-1">
                                    <span class="text-[9px] font-black uppercase text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">{{ $product->unit }}</span>
                                    @if(!$product->is_active)
                                        <span class="h-2 w-2 rounded-full bg-slate-300" title="Inactive"></span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    {{-- Desktop View Table --}}
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-left text-xs whitespace-nowrap">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-black uppercase tracking-wider text-slate-400">
                                    <th class="w-10 px-3 py-2">Pick</th>
                                    <th class="px-3 py-2">Code</th>
                                    <th class="px-3 py-2">Product</th>
                                    <th class="px-3 py-2">Unit</th>
                                    <th class="px-3 py-2">Order Units</th>
                                    <th class="w-28 px-3 py-2">Qty</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($categoryProducts as $product)
                                    @php
                                        $orderUnits = $product->orderUnits->where('is_orderable', true)->pluck('label')->filter()->values();
                                        $unitLabel = strtoupper((string) $product->unit);
                                    @endphp
                                    <tr data-grocery-row data-code="{{ $product->sku }}" data-name="{{ $product->name }}" data-unit="{{ $unitLabel }}" data-category="{{ $categoryName }}" class="hover:bg-slate-50/60">
                                        <td class="px-3 py-2">
                                            <input type="checkbox" data-grocery-check class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-bold text-slate-700">#{{ $product->sku }}</code>
                                        </td>
                                        <td class="px-3 py-2 font-bold text-slate-950">{{ $product->name }}</td>
                                        <td class="px-3 py-2 font-bold uppercase text-slate-600">{{ $product->unit }}</td>
                                        <td class="px-3 py-2 font-semibold text-slate-500">{{ $orderUnits->isNotEmpty() ? $orderUnits->join(' / ') : $unitLabel }}</td>
                                        <td class="px-3 py-2">
                                            <input type="text" data-grocery-qty placeholder="Qty" class="h-7 w-full rounded border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $product->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-xs font-bold text-slate-500">
                    No products match these filters.
                </div>
            @endforelse
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = Array.from(document.querySelectorAll('[data-grocery-row]'));
            const selectedCountNodes = Array.from(document.querySelectorAll('[data-selected-count]'));

            const selectedRows = () => rows.filter((row) => row.querySelector('[data-grocery-check]')?.checked);
            const updateCount = () => selectedCountNodes.forEach((node) => node.textContent = selectedRows().length);

            rows.forEach((row) => {
                row.querySelector('[data-grocery-check]')?.addEventListener('change', updateCount);
                row.querySelector('[data-grocery-qty]')?.addEventListener('input', () => {
                    const checkbox = row.querySelector('[data-grocery-check]');
                    if (checkbox && row.querySelector('[data-grocery-qty]').value.trim() !== '') {
                        checkbox.checked = true;
                        updateCount();
                    }
                });
            });

            document.querySelectorAll('[data-clear-grocery]').forEach((button) => {
                button.addEventListener('click', () => {
                    rows.forEach((row) => {
                        const checkbox = row.querySelector('[data-grocery-check]');
                        const qty = row.querySelector('[data-grocery-qty]');
                        if (checkbox) checkbox.checked = false;
                        if (qty) qty.value = '';
                    });
                    updateCount();
                });
            });

            document.querySelectorAll('[data-share-grocery]').forEach((button) => {
                button.addEventListener('click', () => {
                    const picked = selectedRows();
                    if (picked.length === 0) {
                        alert('Select products before sharing.');
                        return;
                    }

                    const lines = ['Grocery List', ''];
                    picked.forEach((row, index) => {
                        const qty = row.querySelector('[data-grocery-qty]')?.value.trim();
                        const qtyText = qty ? ` - ${qty}` : '';
                        lines.push(`${index + 1}. ${row.dataset.code} - ${row.dataset.name}${qtyText} ${row.dataset.unit}`);
                    });

                    window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(lines.join('\n'))}`, '_blank', 'noopener');
                });
            });

            updateCount();
        });
    </script>
</x-layouts.app>
