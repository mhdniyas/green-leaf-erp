<x-layouts.app title="Warehouse Products">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-4 py-3 lg:max-w-6xl lg:px-6 lg:py-4">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_12px_28px_rgba(15,23,42,0.16)] lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.24),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#064e3b_100%)] px-4 py-4 sm:px-5 lg:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-200">Warehouse Flow</p>
                <h1 class="mt-1 text-xl font-black tracking-tight">Products</h1>
                <p class="mt-1.5 max-w-xl text-sm font-medium leading-6 text-slate-200">Search products and manage active status when admin has given you permission.</p>
            </div>
        </section>

        <form method="GET" action="{{ route('warehouse.receiver.products.index') }}" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="grid gap-2 md:grid-cols-[1fr_220px_160px_auto] md:items-end">
                <div>
                    <label for="product-search" class="mb-1 block text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Search Product</label>
                    <input id="product-search" type="search" name="search" value="{{ request('search') }}" placeholder="Product, SKU, category..." class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>
                <div class="relative">
                    <label for="product-category" class="mb-1 block text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Category</label>
                    <select id="product-category" name="category_id" class="h-11 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-9 text-xs font-black text-slate-700 focus:border-emerald-500 focus:bg-white focus:outline-none">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) request('category_id') === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 top-5 flex items-center pr-3 text-slate-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                <div class="relative">
                    <label for="product-status" class="mb-1 block text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Status</label>
                    <select id="product-status" name="status" class="h-11 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-9 text-xs font-black text-slate-700 focus:border-emerald-500 focus:bg-white focus:outline-none">
                        <option value="">All Status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 top-5 flex items-center pr-3 text-slate-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                <div class="flex gap-2">
                    @if(request()->hasAny(['search', 'category_id', 'status']))
                        <a href="{{ route('warehouse.receiver.products.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-600 hover:bg-slate-50">Clear</a>
                    @endif
                    <button type="submit" class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-emerald-600 px-4 text-xs font-black text-white transition-colors hover:bg-emerald-700 md:flex-none">Filter</button>
                </div>
            </div>
        </form>

        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-800">Warehouse Grocery List</p>
                    <p class="mt-1 text-xs font-bold text-emerald-900"><span data-selected-count>0</span> selected for WhatsApp sharing.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-clear-grocery class="inline-flex h-9 items-center rounded-xl border border-emerald-200 bg-white px-3 text-xs font-black text-emerald-700 hover:bg-emerald-100">Clear</button>
                    <button type="button" data-share-grocery class="inline-flex h-9 items-center rounded-xl bg-emerald-700 px-3 text-xs font-black text-white hover:bg-emerald-600">WhatsApp</button>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h2 class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Product List</h2>
                <span class="text-xs font-bold text-slate-500">{{ $products->total() }} products</span>
            </div>

            @forelse($products as $product)
                @php
                    $orderUnits = $product->orderUnits->where('is_orderable', true)->pluck('label')->filter()->values();
                    $unitLabel = strtoupper((string) $product->unit);
                @endphp
                <article data-grocery-row data-code="{{ $product->sku }}" data-name="{{ $product->name }}" data-unit="{{ $unitLabel }}" data-category="{{ $product->category?->name ?? 'No Category' }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <label class="flex min-w-0 flex-1 items-start gap-3">
                            <input type="checkbox" data-grocery-check class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <div class="min-w-0">
                            <span class="block truncate text-sm font-black text-slate-950">{{ $product->name }}</span>
                            <p class="mt-1 text-[11px] font-bold text-slate-500">
                                <span class="font-mono">{{ $product->sku }}</span>
                                &middot; {{ $product->category?->name ?? 'No Category' }}
                                &middot; {{ strtoupper($product->unit) }}
                            </p>
                            <p class="mt-1 text-[11px] font-bold text-slate-400">
                                Order units: {{ $orderUnits->isNotEmpty() ? $orderUnits->join(' / ') : $unitLabel }}
                            </p>
                            <p class="mt-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                Status changed:
                                @if($product->statusChangedBy)
                                    {{ $product->statusChangedBy->name }} · {{ $product->status_changed_at?->format('d M, h:i A') }}
                                @else
                                    Not recorded
                                @endif
                            </p>
                            <input type="text" data-grocery-qty placeholder="Qty for WhatsApp" class="mt-3 h-9 w-full max-w-xs rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                            </div>
                        </label>
                        @can('inventory.product.status.update')
                            <form method="POST" action="{{ route('inventory.products.status.update', $product) }}" class="shrink-0">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $product->is_active ? '0' : '1' }}">
                                <button type="submit" class="inline-flex min-w-28 items-center justify-center gap-2 rounded-xl px-3 py-2 text-xs font-black {{ $product->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    <span class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $product->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                        <span class="inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform {{ $product->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                    </span>
                                    <span>{{ $product->is_active ? 'Active' : 'Inactive' }}</span>
                                </button>
                            </form>
                        @else
                            <span class="shrink-0 rounded-xl px-3 py-2 text-xs font-black {{ $product->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        @endcan
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm font-bold text-slate-500">
                    No products match these filters.
                </div>
            @endforelse
        </section>

        @if($products->hasPages())
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                {{ $products->withQueryString()->links() }}
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = Array.from(document.querySelectorAll('[data-grocery-row]'));
            const countNodes = Array.from(document.querySelectorAll('[data-selected-count]'));
            const selectedRows = () => rows.filter((row) => row.querySelector('[data-grocery-check]')?.checked);
            const updateCount = () => countNodes.forEach((node) => node.textContent = selectedRows().length);

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

                    const lines = ['Warehouse Grocery List', ''];
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
