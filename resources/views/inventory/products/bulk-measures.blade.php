<x-layouts.inventory title="Bulk Product Measures">
    <x-slot:actions>
        <a href="{{ route('inventory.products.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
            Back to Products
        </a>
    </x-slot:actions>

    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Measures Only</p>
                    <h2 class="mt-1 text-lg font-black text-slate-950">Bulk Product Unit Measures</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Update base unit and order-unit conversions. Empty conversion cells remove that unit from ordering.</p>
                </div>
                <form method="GET" action="{{ route('inventory.products.measures.bulk') }}" class="flex w-full gap-2 lg:w-auto">
                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search products..."
                        class="h-10 min-w-0 flex-1 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 lg:w-72"
                    >
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.12em] text-white hover:bg-slate-800">
                        Search
                    </button>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('inventory.products.measures.bulk.update', request()->only('search')) }}" data-bulk-measures-form>
            @csrf
            @method('PUT')

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-[1120px] w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="bg-slate-100 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                                <th class="sticky left-0 z-20 border-b border-slate-200 bg-slate-100 px-3 py-3 text-left">Product</th>
                                <th class="border-b border-slate-200 px-3 py-3 text-left">Base</th>
                                @foreach($units as $unit)
                                    <th class="border-b border-slate-200 px-2 py-3 text-right">{{ strtoupper($unit) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($products as $product)
                                @php
                                    $rowIndex = $loop->index;
                                    $baseUnit = old("products.{$rowIndex}.base_unit", $product->unit);
                                    $unitMap = $product->orderUnits->keyBy('unit');
                                @endphp
                                <tr data-measure-row class="group hover:bg-emerald-50/40">
                                    <td class="sticky left-0 z-10 border-b border-slate-100 bg-white px-3 py-2 group-hover:bg-emerald-50">
                                        <input type="hidden" name="products[{{ $rowIndex }}][public_uuid]" value="{{ $product->public_uuid }}">
                                        <div class="flex items-center gap-2">
                                            <code class="flex h-7 min-w-8 items-center justify-center rounded-lg bg-slate-100 px-2 text-[11px] font-black text-slate-600">{{ $product->sku }}</code>
                                            <div class="min-w-0">
                                                <p class="truncate text-xs font-black text-slate-950">{{ $product->name }}</p>
                                                <p class="truncate text-[11px] font-semibold text-slate-400">{{ $product->category?->name ?? 'No category' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border-b border-slate-100 px-3 py-2">
                                        <select
                                            name="products[{{ $rowIndex }}][base_unit]"
                                            data-base-unit-select
                                            class="h-9 w-24 rounded-lg border border-slate-200 bg-white px-2 text-xs font-black uppercase text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                        >
                                            @foreach($units as $unit)
                                                <option value="{{ $unit }}" @selected($baseUnit === $unit)>{{ strtoupper($unit) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    @foreach($units as $unit)
                                        @php
                                            $isBase = $baseUnit === $unit;
                                            $conversion = old(
                                                "products.{$rowIndex}.units.{$unit}",
                                                $isBase ? 1 : ($unitMap->get($unit)?->conversion_to_base ?? '')
                                            );
                                        @endphp
                                        <td class="border-b border-slate-100 px-2 py-2">
                                            <input
                                                type="number"
                                                step="0.0001"
                                                min="0.0001"
                                                name="products[{{ $rowIndex }}][units][{{ $unit }}]"
                                                value="{{ $conversion }}"
                                                data-measure-input
                                                data-unit="{{ $unit }}"
                                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-right text-xs font-black text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:border-emerald-200 disabled:bg-emerald-50 disabled:text-emerald-800"
                                                @disabled($isBase)
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($units) + 2 }}" class="px-4 py-12 text-center text-sm font-bold text-slate-500">
                                        No products found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs font-bold text-slate-500">{{ $products->count() }} products loaded</p>
                    <button type="submit" class="inline-flex justify-center rounded-xl bg-emerald-600 px-5 py-3 text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm hover:bg-emerald-700">
                        Update Measures
                    </button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-measure-row]').forEach((row) => {
                const select = row.querySelector('[data-base-unit-select]');

                const syncBaseUnit = () => {
                    const baseUnit = select?.value;
                    row.querySelectorAll('[data-measure-input]').forEach((input) => {
                        const isBase = input.getAttribute('data-unit') === baseUnit;
                        input.disabled = isBase;
                        if (isBase) {
                            input.value = '1';
                        }
                    });
                };

                select?.addEventListener('change', syncBaseUnit);
                syncBaseUnit();
            });
        </script>
    @endpush
</x-layouts.inventory>
