<x-layouts.inventory title="Bulk Product Measures">
    <x-slot:actions>
        <a href="{{ route('inventory.products.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
            Back to Products
        </a>
    </x-slot:actions>

    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex flex-col gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Measures Only</p>
                    <h2 class="mt-1 text-lg font-black text-slate-950">Bulk Product Unit Measures</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">BOX requires KG per box. PIECE can be enabled without KG conversion.</p>
                </div>

                <form method="GET" action="{{ route('inventory.products.measures.bulk') }}" class="grid grid-cols-1 gap-2 md:grid-cols-6">
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search product or SKU" class="h-10 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 md:col-span-2">
                    <select name="category_id" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) request('category_id') === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">All Status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                    <select name="base_unit" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold uppercase text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">Any Base</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit }}" @selected(request('base_unit') === $unit)>{{ strtoupper($unit) }}</option>
                        @endforeach
                    </select>
                    <select name="measure_status" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">All Measures</option>
                        <option value="missing_box" @selected(request('measure_status') === 'missing_box')>Missing Box</option>
                        <option value="missing_piece" @selected(request('measure_status') === 'missing_piece')>Missing Piece</option>
                        <option value="has_multiple" @selected(request('measure_status') === 'has_multiple')>Has Multiple</option>
                    </select>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-xs font-black uppercase tracking-[0.12em] text-white hover:bg-slate-800 md:col-start-6">
                        Filter
                    </button>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('inventory.products.measures.bulk.update', request()->query()) }}" data-bulk-measures-form>
            @csrf
            @method('PUT')

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-[1180px] w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="bg-slate-100 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                                <th class="sticky left-0 z-20 border-b border-slate-200 bg-slate-100 px-3 py-3 text-left">Product</th>
                                <th class="border-b border-slate-200 px-3 py-3 text-left">Base</th>
                                <th class="border-b border-slate-200 px-3 py-3 text-center">Box</th>
                                <th class="border-b border-slate-200 px-3 py-3 text-right">KG per Box</th>
                                <th class="border-b border-slate-200 px-3 py-3 text-center">Piece</th>
                                <th class="border-b border-slate-200 px-3 py-3 text-right">KG per Piece</th>
                                @foreach(['bag', 'bunch', 'packet', 'crate', 'tray'] as $unit)
                                    <th class="border-b border-slate-200 px-2 py-3 text-right">{{ strtoupper($unit) }}</th>
                                @endforeach
                                <th class="border-b border-slate-200 px-3 py-3 text-left">Shop Owner Preview</th>
                                <th class="border-b border-slate-200 px-3 py-3 text-right">Row</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($products as $product)
                                @php
                                    $rowIndex = $loop->index;
                                    $baseUnit = old("products.{$rowIndex}.base_unit", $product->unit);
                                    $unitMap = $product->orderUnits->keyBy('unit');
                                    $boxUnit = $unitMap->get('box');
                                    $pieceUnit = $unitMap->get('piece');
                                @endphp
                                <tr data-measure-row data-product-name="{{ $product->name }}" data-category="{{ $product->category?->name ?? 'No category' }}" class="group hover:bg-emerald-50/40">
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
                                        <select name="products[{{ $rowIndex }}][base_unit]" data-base-unit-select class="h-9 w-24 rounded-lg border border-slate-200 bg-white px-2 text-xs font-black uppercase text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                            @foreach($units as $unit)
                                                <option value="{{ $unit }}" @selected($baseUnit === $unit)>{{ strtoupper($unit) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="border-b border-slate-100 px-3 py-2 text-center">
                                        <input type="hidden" name="products[{{ $rowIndex }}][enabled_units][box]" value="0">
                                        <input type="checkbox" name="products[{{ $rowIndex }}][enabled_units][box]" value="1" data-unit-enabled data-unit="box" @checked($boxUnit || $baseUnit === 'box') class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    </td>
                                    <td class="border-b border-slate-100 px-3 py-2">
                                        <input type="number" step="0.0001" min="0.0001" name="products[{{ $rowIndex }}][units][box]" value="{{ old("products.{$rowIndex}.units.box", $baseUnit === 'box' ? 1 : ($boxUnit?->conversion_to_base ?? '')) }}" data-measure-input data-unit="box" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-right text-xs font-black text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:border-emerald-200 disabled:bg-emerald-50 disabled:text-emerald-800" placeholder="Required">
                                    </td>
                                    <td class="border-b border-slate-100 px-3 py-2 text-center">
                                        <input type="hidden" name="products[{{ $rowIndex }}][enabled_units][piece]" value="0">
                                        <input type="checkbox" name="products[{{ $rowIndex }}][enabled_units][piece]" value="1" data-unit-enabled data-unit="piece" @checked($pieceUnit || $baseUnit === 'piece') class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    </td>
                                    <td class="border-b border-slate-100 px-3 py-2">
                                        <input type="number" step="0.0001" min="0.0001" name="products[{{ $rowIndex }}][units][piece]" value="{{ old("products.{$rowIndex}.units.piece", $baseUnit === 'piece' ? 1 : ($pieceUnit?->conversion_to_base ?? '')) }}" data-measure-input data-unit="piece" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-right text-xs font-black text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:border-emerald-200 disabled:bg-emerald-50 disabled:text-emerald-800" placeholder="Optional">
                                    </td>
                                    @foreach(['bag', 'bunch', 'packet', 'crate', 'tray'] as $unit)
                                        @php($measureUnit = $unitMap->get($unit))
                                        <td class="border-b border-slate-100 px-2 py-2">
                                            <input type="number" step="0.0001" min="0.0001" name="products[{{ $rowIndex }}][units][{{ $unit }}]" value="{{ old("products.{$rowIndex}.units.{$unit}", $baseUnit === $unit ? 1 : ($measureUnit?->conversion_to_base ?? '')) }}" data-measure-input data-unit="{{ $unit }}" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-right text-xs font-black text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:border-emerald-200 disabled:bg-emerald-50 disabled:text-emerald-800">
                                        </td>
                                    @endforeach
                                    <td class="border-b border-slate-100 px-3 py-2">
                                        <div class="w-72 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                                            <div class="grid grid-cols-[2rem_minmax(0,1fr)_4.25rem_minmax(4.5rem,5.5rem)] items-center gap-1.5">
                                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-[11px] font-black text-slate-600">{{ $product->sku }}</div>
                                                <div class="min-w-0">
                                                    <p class="truncate text-[13px] font-black leading-4 text-slate-950">{{ $product->name }}</p>
                                                    <p class="truncate text-[11px] font-semibold leading-3 text-slate-500">{{ $product->category?->name ?? 'No category' }}</p>
                                                </div>
                                                <div data-preview-units class="flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-1.5 text-[11px] font-black uppercase text-slate-800">KG</div>
                                                <div class="flex h-8 items-center justify-end rounded-lg border border-slate-200 bg-white px-2 text-sm font-black text-slate-400">0</div>
                                            </div>
                                            <p data-preview-conversion class="mt-2 hidden rounded-lg border border-emerald-100 bg-emerald-50 px-2 py-1 text-[11px] font-black uppercase tracking-[0.08em] text-emerald-700"></p>
                                            <p data-row-rule-error class="mt-2 hidden rounded-lg border border-rose-100 bg-rose-50 px-2 py-1 text-[11px] font-black text-rose-700"></p>
                                        </div>
                                    </td>
                                    <td class="border-b border-slate-100 px-3 py-2 text-right">
                                        <button type="submit" name="save_row" value="{{ $rowIndex }}" data-save-row class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[11px] font-black uppercase tracking-[0.12em] text-slate-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700">
                                            Save
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No products found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs font-bold text-slate-500"><span data-changed-count>0</span> changed • {{ $products->count() }} products loaded</p>
                    <button type="submit" data-save-changed class="inline-flex justify-center rounded-xl bg-emerald-600 px-5 py-3 text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm hover:bg-emerald-700">
                        Save All Changed
                    </button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            const changedCount = document.querySelector('[data-changed-count]');
            const form = document.querySelector('[data-bulk-measures-form]');

            function formatMeasure(value) {
                const number = Number.parseFloat(String(value));
                if (!Number.isFinite(number)) return '';

                const rounded = Math.round((number + Number.EPSILON) * 100) / 100;
                return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
            }

            function syncChangedCount() {
                const count = document.querySelectorAll('[data-measure-row].is-dirty').length;
                if (changedCount) changedCount.textContent = String(count);
            }

            document.querySelectorAll('[data-measure-row]').forEach((row) => {
                const select = row.querySelector('[data-base-unit-select]');
                const markDirty = () => {
                    row.classList.add('is-dirty', 'bg-amber-50');
                    syncChangedCount();
                };

                const unitEnabled = (unit) => {
                    if (select?.value === unit) return true;

                    const checkbox = row.querySelector(`[data-unit-enabled][data-unit="${unit}"]`);
                    const input = row.querySelector(`[data-measure-input][data-unit="${unit}"]`);

                    return Boolean(checkbox?.checked || input?.value);
                };

                const syncPreview = () => {
                    const baseUnit = select?.value || 'kg';
                    const previewUnits = row.querySelector('[data-preview-units]');
                    const previewConversion = row.querySelector('[data-preview-conversion]');
                    const error = row.querySelector('[data-row-rule-error]');
                    const saveButton = row.querySelector('[data-save-row]');
                    const boxInput = row.querySelector('[data-measure-input][data-unit="box"]');
                    const pieceInput = row.querySelector('[data-measure-input][data-unit="piece"]');
                    const enabledUnits = ['box', 'piece', 'bag', 'bunch', 'packet', 'crate', 'tray']
                        .filter(unitEnabled);

                    if (!enabledUnits.includes(baseUnit)) {
                        enabledUnits.unshift(baseUnit);
                    }

                    if (previewUnits) {
                        previewUnits.textContent = enabledUnits.map((unit) => unit.toUpperCase()).join(' / ');
                    }

                    const boxEnabled = unitEnabled('box') && baseUnit !== 'box';
                    const boxMissing = boxEnabled && !boxInput?.value;
                    const pieceEnabled = unitEnabled('piece') && baseUnit !== 'piece';
                    const pieceConversion = pieceInput?.value;

                    if (boxMissing) {
                        error.textContent = 'KG per box required';
                        error.classList.remove('hidden');
                        saveButton?.setAttribute('disabled', 'disabled');
                        saveButton?.classList.add('opacity-50', 'cursor-not-allowed');
                    } else {
                        error?.classList.add('hidden');
                        saveButton?.removeAttribute('disabled');
                        saveButton?.classList.remove('opacity-50', 'cursor-not-allowed');
                    }

                    if (boxEnabled && boxInput?.value) {
                        previewConversion.textContent = `1 BOX = ${formatMeasure(boxInput.value)} ${baseUnit.toUpperCase()}`;
                        previewConversion.classList.remove('hidden');
                        return;
                    }

                    if (pieceEnabled && pieceConversion) {
                        previewConversion.textContent = `1 PIECE = ${formatMeasure(pieceConversion)} ${baseUnit.toUpperCase()}`;
                        previewConversion.classList.remove('hidden');
                        return;
                    }

                    if (pieceEnabled) {
                        previewConversion.textContent = 'PIECE shows as count only';
                        previewConversion.classList.remove('hidden');
                        return;
                    }

                    previewConversion?.classList.add('hidden');
                };

                const syncBaseUnit = () => {
                    const baseUnit = select?.value;
                    row.querySelectorAll('[data-measure-input]').forEach((input) => {
                        const unit = input.getAttribute('data-unit');
                        const isBase = unit === baseUnit;
                        input.disabled = isBase;
                        if (isBase) input.value = '1';
                    });

                    row.querySelectorAll('[data-unit-enabled]').forEach((input) => {
                        if (input.getAttribute('data-unit') === baseUnit) {
                            input.checked = true;
                            input.disabled = true;
                        } else {
                            input.disabled = false;
                        }
                    });

                    syncPreview();
                };

                row.querySelectorAll('input, select').forEach((input) => {
                    input.addEventListener('change', () => {
                        markDirty();
                        syncBaseUnit();
                    });
                    input.addEventListener('input', () => {
                        markDirty();
                        syncPreview();
                    });
                });

                syncBaseUnit();
            });

            form?.addEventListener('submit', (event) => {
                if (event.submitter?.matches('[data-save-row]')) {
                    return;
                }

                const dirtyRows = document.querySelectorAll('[data-measure-row].is-dirty');
                if (dirtyRows.length === 0) {
                    event.preventDefault();
                    window.alert('No changed rows to save.');
                    return;
                }

                document.querySelectorAll('[data-measure-row]:not(.is-dirty)').forEach((row) => {
                    row.querySelectorAll('input, select, button').forEach((input) => {
                        input.disabled = true;
                    });
                });
            });
        </script>
    @endpush
</x-layouts.inventory>
