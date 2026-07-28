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

                <div class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                    <div>
                        <p class="text-xs font-black text-slate-900">JSON backup and bulk update</p>
                        <p class="mt-1 text-[11px] font-semibold text-slate-500">Export the currently filtered products, edit measures in JSON, then import to update existing products by UUID or SKU.</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <a href="{{ route('inventory.products.measures.bulk.export-json', request()->query()) }}"
                           class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.12em] text-slate-700 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700">
                            Export JSON
                        </a>
                        <form method="POST" action="{{ route('inventory.products.measures.bulk.import-json', request()->query()) }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            @csrf
                            <label class="inline-flex h-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black uppercase tracking-[0.12em] text-slate-600 shadow-sm transition hover:border-emerald-200 hover:bg-white hover:text-emerald-700">
                                <span data-import-file-label>Choose JSON</span>
                                <input type="file" name="import_file" accept="application/json,.json" data-import-file-input class="sr-only">
                            </label>
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-[0.12em] text-white shadow-sm transition hover:bg-emerald-700">
                                Import Update
                            </button>
                        </form>
                    </div>
                    @error('import_file')
                        <p class="rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-xs font-black text-rose-700 lg:col-span-2">{{ $message }}</p>
                    @enderror
                </div>
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
                                <th class="border-b border-slate-200 px-3 py-3 text-left">Extra Box KG</th>
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
                                    $boxUnits = $product->orderUnits->where('unit', 'box')->values();
                                    $boxUnit = $boxUnits->first();
                                    $extraBoxValues = $boxUnits->slice(1)->pluck('conversion_to_base')->filter()->map(fn ($value) => rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.'))->implode(', ');
                                    $pieceUnit = $unitMap->get('piece');
                                    $visibilityUnits = $product->orderUnits->values();
                                    if ($visibilityUnits->isEmpty()) {
                                        $visibilityUnits = collect([(object) [
                                            'label' => strtoupper((string) $baseUnit),
                                            'is_orderable' => true,
                                        ]]);
                                    }
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
                                    <td class="border-b border-slate-100 px-3 py-2">
                                        <input type="text" name="products[{{ $rowIndex }}][box_variants]" value="{{ old("products.{$rowIndex}.box_variants", $extraBoxValues) }}" data-box-variants class="h-9 w-32 rounded-lg border border-slate-200 bg-white px-2 text-xs font-black text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" placeholder="5, 10, 15">
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
                                            <button type="button" data-open-visibility-popup class="mt-2 inline-flex w-full items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] text-slate-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700">
                                                Show Units
                                            </button>
                                            <div data-visibility-popup class="fixed inset-0 z-[80] hidden p-4">
                                                <div data-close-visibility-popup class="absolute inset-0 bg-slate-950/40"></div>
                                                <div class="relative mx-auto mt-24 w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Shop Owner Units</p>
                                                            <h3 class="mt-1 truncate text-sm font-black text-slate-950">{{ $product->name }}</h3>
                                                        </div>
                                                        <button type="button" data-close-visibility-popup class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-black text-slate-600">Close</button>
                                                    </div>
                                                    <div class="mt-4 grid gap-2">
                                                        @foreach($visibilityUnits as $visibilityUnit)
                                                            @php($visibilityLabel = (string) $visibilityUnit->label)
                                                            <label class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black text-slate-800">
                                                                <span class="truncate">{{ $visibilityLabel }}</span>
                                                                <span class="relative inline-flex">
                                                                    <input type="hidden" name="products[{{ $rowIndex }}][visible_labels][{{ $visibilityLabel }}]" value="0">
                                                                    <input type="checkbox" name="products[{{ $rowIndex }}][visible_labels][{{ $visibilityLabel }}]" value="1" data-visible-label="{{ $visibilityLabel }}" @checked((bool) $visibilityUnit->is_orderable) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                                                </span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    <p data-visibility-error class="mt-3 hidden rounded-lg border border-rose-100 bg-rose-50 px-2 py-1 text-[11px] font-black text-rose-700">Select at least one unit.</p>
                                                    <button type="button" data-close-visibility-popup class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-white hover:bg-emerald-700">
                                                        Done
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border-b border-slate-100 px-3 py-2 text-right">
                                        <button type="submit" name="save_row" value="{{ $rowIndex }}" data-save-row class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[11px] font-black uppercase tracking-[0.12em] text-slate-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700">
                                            Save
                                        </button>
                                        <p data-row-save-status class="mt-1 text-[11px] font-black text-slate-400"></p>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No products found.</td>
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
            const saveChangedButton = document.querySelector('[data-save-changed]');
            const importFileInput = document.querySelector('[data-import-file-input]');
            const importFileLabel = document.querySelector('[data-import-file-label]');

            importFileInput?.addEventListener('change', () => {
                importFileLabel.textContent = importFileInput.files?.[0]?.name || 'Choose JSON';
            });

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

            function setRowStatus(row, message, tone = 'muted') {
                const status = row.querySelector('[data-row-save-status]');
                if (!status) return;

                status.textContent = message;
                status.classList.remove('text-slate-400', 'text-emerald-700', 'text-rose-700');
                status.classList.add(tone === 'success' ? 'text-emerald-700' : (tone === 'error' ? 'text-rose-700' : 'text-slate-400'));
            }

            function appendControl(formData, control) {
                if (!control.name || control.disabled || control.type === 'button' || control.type === 'submit') return;
                if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) return;

                formData.append(control.name, control.value);
            }

            function payloadForRows(rows, saveRow = null) {
                const formData = new FormData();
                formData.append('_token', form.querySelector('input[name="_token"]')?.value || '');
                formData.append('_method', 'PUT');

                rows.forEach((row) => {
                    row.querySelectorAll('input, select, textarea').forEach((control) => appendControl(formData, control));
                });

                if (saveRow !== null) {
                    formData.append('save_row', saveRow);
                }

                return formData;
            }

            async function saveRows(rows, saveRow = null) {
                rows.forEach((row) => setRowStatus(row, 'Saving...'));
                saveChangedButton?.setAttribute('disabled', 'disabled');
                form.querySelectorAll('[data-save-row]').forEach((button) => button.setAttribute('disabled', 'disabled'));

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: payloadForRows(rows, saveRow),
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const message = Object.values(payload.errors || {})?.flat()?.[0] || payload.message || 'Could not save measures.';
                        rows.forEach((row) => setRowStatus(row, message, 'error'));
                        return false;
                    }

                    rows.forEach((row) => {
                        row.classList.remove('is-dirty', 'bg-amber-50');
                        setRowStatus(row, 'Saved', 'success');
                    });
                    syncChangedCount();

                    return true;
                } catch {
                    rows.forEach((row) => setRowStatus(row, 'Network error', 'error'));
                    return false;
                } finally {
                    saveChangedButton?.removeAttribute('disabled');
                    form.querySelectorAll('[data-save-row]').forEach((button) => {
                        if (!button.closest('[data-measure-row]')?.querySelector('[data-row-rule-error]:not(.hidden)')) {
                            button.removeAttribute('disabled');
                        }
                    });
                }
            }

            document.querySelectorAll('[data-measure-row]').forEach((row) => {
                const select = row.querySelector('[data-base-unit-select]');
                const markDirty = () => {
                    row.classList.add('is-dirty', 'bg-amber-50');
                    setRowStatus(row, 'Changed');
                    syncChangedCount();
                };

                const visibleLabels = () => Array.from(row.querySelectorAll('[data-visible-label]:checked'))
                    .map((input) => input.getAttribute('data-visible-label'))
                    .filter(Boolean);

                const syncVisibility = () => {
                    const labels = visibleLabels();
                    const error = row.querySelector('[data-visibility-error]');
                    const saveButton = row.querySelector('[data-save-row]');
                    const hasVisible = labels.length > 0;

                    error?.classList.toggle('hidden', hasVisible);
                    if (!hasVisible) {
                        saveButton?.setAttribute('disabled', 'disabled');
                        saveButton?.classList.add('opacity-50', 'cursor-not-allowed');
                    }

                    return labels;
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
                        const labels = syncVisibility();
                        previewUnits.textContent = labels.length > 0
                            ? labels.map((label) => label.toUpperCase()).join(' / ')
                            : enabledUnits.map((unit) => unit.toUpperCase()).join(' / ');
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
                    } else if (visibleLabels().length > 0) {
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

                row.querySelector('[data-open-visibility-popup]')?.addEventListener('click', () => {
                    row.querySelector('[data-visibility-popup]')?.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                });

                row.querySelectorAll('[data-close-visibility-popup]').forEach((button) => {
                    button.addEventListener('click', () => {
                        row.querySelector('[data-visibility-popup]')?.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                        syncPreview();
                    });
                });

                syncBaseUnit();
            });

            form?.addEventListener('submit', (event) => {
                event.preventDefault();

                if (event.submitter?.matches('[data-save-row]')) {
                    const row = event.submitter.closest('[data-measure-row]');
                    if (row) {
                        saveRows([row], event.submitter.value);
                    }
                    return;
                }

                const dirtyRows = Array.from(document.querySelectorAll('[data-measure-row].is-dirty'));
                if (dirtyRows.length === 0) {
                    window.alert('No changed rows to save.');
                    return;
                }

                saveRows(dirtyRows);
            });
        </script>
    @endpush
</x-layouts.inventory>
