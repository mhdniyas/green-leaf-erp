@php
/** @var \Illuminate\Database\Eloquent\Collection $categories */
$units = [
    'kg' => 'Kilogram (kg)',
    'box' => 'Box',
    'piece' => 'Piece',
    'bag' => 'Bag',
    'bunch' => 'Bunch',
    'full_bunch' => 'Full Bunch',
    'packet' => 'Packet',
    'crate' => 'Crate',
    'tray' => 'Tray',
];
$baseUnit = old('unit', $product->unit ?? 'kg');
$existingUnitRows = isset($product)
    ? $product->orderUnits->map(fn ($unit) => [
        'id' => $unit->id,
        'public_uuid' => $unit->public_uuid,
        'unit' => $unit->unit,
        'label' => $unit->label,
        'conversion_to_base' => $unit->conversion_to_base !== null ? (float) $unit->conversion_to_base : null,
        'is_base' => (bool) $unit->is_base,
        'is_orderable' => (bool) $unit->is_orderable,
    ])->values()->all()
    : [[
        'unit' => $baseUnit,
        'label' => strtoupper((string) $baseUnit),
        'conversion_to_base' => 1,
        'is_base' => true,
        'is_orderable' => true,
    ]];
$unitRows = old('units', $existingUnitRows);
@endphp

<x-layouts.inventory title="{{ isset($product) ? 'Edit Product' : 'Add Product' }}">

    <div class="max-w-5xl">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">{{ isset($product) ? 'Edit Product' : 'New Product' }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ isset($product) ? 'Update product details.' : 'Add a new vegetable to the product catalog.' }}</p>
            </div>

            <form
                method="POST"
                action="{{ isset($product) ? route('inventory.products.update', $product) : route('inventory.products.store') }}"
                class="p-6 space-y-5"
            >
                @csrf
                @isset($product) @method('PUT') @endisset

                {{-- Product Image Upload Area --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Product Image</label>
                    <div class="flex items-center gap-6">
                        {{-- Image Preview --}}
                        <div class="relative w-24 h-24 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden shrink-0 group">
                            @if(isset($product) && $product->image)
                                <img id="preview-image" src="{{ $product->getImageUrl() }}" class="w-full h-full object-cover">
                            @else
                                <img id="preview-image" src="" class="w-full h-full object-cover hidden">
                                <div id="preview-placeholder" class="text-gray-400 text-center p-2">
                                    <svg class="w-8 h-8 mx-auto opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375 0 11-.75 0 .375 0 01.75 0z" />
                                    </svg>
                                    <span class="text-[10px] block mt-1 font-medium">No Image</span>
                                </div>
                            @endif
                        </div>

                        {{-- Upload controls --}}
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" onclick="document.getElementById('image_input').click()" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition-colors cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                    </svg>
                                    Choose Photo
                                </button>
                                <button type="button" id="remove-btn" onclick="removeSelectedImage()" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-red-600 bg-white border border-red-200 rounded-lg shadow-sm hover:bg-red-50 hover:border-red-300 transition-colors {{ (isset($product) && $product->image) ? '' : 'hidden' }}">
                                    Remove
                                </button>
                            </div>
                            <p class="text-xs text-gray-400">JPEG, PNG or WebP. Aspect ratio will be cropped to square 1:1.</p>
                        </div>
                    </div>

                    {{-- Hidden inputs --}}
                    <input type="file" id="image_input" accept="image/*" class="hidden" onchange="handleImageSelect(event)">
                    <input type="hidden" id="image_data" name="image_data">
                    <input type="hidden" id="remove_image" name="remove_image" value="0">
                </div>

                {{-- Name + Category row --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label for="name" class="block text-sm font-medium text-gray-700">Product Name <span class="text-red-500">*</span></label>
                        <input id="name" name="name" type="text" required
                               value="{{ old('name', $product->name ?? '') }}"
                               placeholder="e.g. Tomato"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('name') border-red-300 @enderror">
                        @error('name') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="category_id" class="block text-sm font-medium text-gray-700">Category <span class="text-red-500">*</span></label>
                        <select id="category_id" name="category_id" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('category_id') border-red-300 @enderror">
                            <option value="">Select category…</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id ?? null) == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                        <p class="text-[11px] text-slate-400 mt-1">To manage categories, visit the <a href="{{ route('inventory.categories.index') }}" class="text-emerald-600 hover:text-emerald-700 hover:underline font-semibold">Product Categories</a> page.</p>
                    </div>
                </div>

                {{-- SKU + Unit row --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label for="sku" class="block text-sm font-medium text-gray-700">
                            SKU <span class="text-red-500">*</span>
                            <span class="text-gray-400 font-normal text-xs ml-1">(letters, numbers, hyphens only)</span>
                        </label>
                        <input id="sku" name="sku" type="text" required
                               value="{{ old('sku', $product->sku ?? '') }}"
                               placeholder="e.g. TOMATO-001"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('sku') border-red-300 @enderror">
                        @error('sku') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="unit" class="block text-sm font-medium text-gray-700">Base Unit <span class="text-red-500">*</span></label>
                        <div class="relative" data-product-select data-select-target="unit">
                            <input id="unit" type="hidden" name="unit" value="{{ $baseUnit }}" data-product-select-input>
                            <button type="button" data-product-select-trigger class="flex w-full items-center justify-between gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-left text-sm font-bold text-slate-900 transition hover:border-emerald-300 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 @error('unit') border-red-300 @enderror" aria-haspopup="listbox" aria-expanded="false">
                                <span data-product-select-label>{{ $units[$baseUnit] ?? strtoupper((string) $baseUnit) }}</span>
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div data-product-select-menu class="absolute left-0 top-[calc(100%+0.35rem)] z-50 hidden w-full overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl shadow-slate-900/15" role="listbox">
                                @foreach($units as $val => $label)
                                    <button type="button" data-product-select-option data-value="{{ $val }}" data-label="{{ $label }}" @class([
                                        'flex h-9 w-full items-center gap-2 rounded-lg px-2 text-left text-xs font-black uppercase transition',
                                        'bg-emerald-600 text-white' => $baseUnit === $val,
                                        'text-slate-700 hover:bg-slate-100' => $baseUnit !== $val,
                                    ]) role="option" aria-selected="{{ $baseUnit === $val ? 'true' : 'false' }}">
                                        <span data-product-select-check class="{{ $baseUnit === $val ? '' : 'invisible' }}">✓</span>
                                        <span>{{ $label }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @error('unit') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-sm font-black text-slate-950">Units & Measures</h3>
                            <p class="mt-1 text-xs font-semibold text-slate-500">Keep one base unit for stock and billing. Add box, piece, bag, or other order units with their base-unit conversion.</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-[11px] font-black">
                                <span class="rounded-lg border border-emerald-100 bg-emerald-50 px-2 py-1 text-emerald-700">BOX needs KG conversion</span>
                                <span class="rounded-lg border border-sky-100 bg-sky-50 px-2 py-1 text-sky-700">PIECE conversion optional</span>
                            </div>
                        </div>
                        <button type="button" id="add-product-unit-row" class="inline-flex w-fit items-center justify-center rounded-xl bg-slate-950 px-3 py-2 text-xs font-black text-white hover:bg-slate-800">
                            Add Unit
                        </button>
                    </div>

                    @error('units') <p class="mt-3 text-xs font-bold text-red-600">{{ $message }}</p> @enderror

                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div class="grid grid-cols-[1.2fr_1fr_1fr_2.5rem] gap-2 border-b border-slate-100 bg-slate-100 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">
                            <span>Unit</span>
                            <span>Per Unit</span>
                            <span>Orderable</span>
                            <span></span>
                        </div>
                        <div id="product-unit-rows" class="divide-y divide-slate-100">
                            @foreach($unitRows as $index => $unitRow)
                                @php
                                    $rowUnit = $unitRow['unit'] ?? $baseUnit;
                                    $isBaseRow = $rowUnit === $baseUnit || (bool) ($unitRow['is_base'] ?? false);
                                @endphp
                                <div data-product-unit-row class="grid grid-cols-[1.2fr_1fr_1fr_2.5rem] items-center gap-2 px-3 py-2">
                                    @if(! empty($unitRow['id']))
                                        <input type="hidden" name="units[{{ $index }}][id]" value="{{ $unitRow['id'] }}">
                                    @endif
                                    @if(! empty($unitRow['public_uuid']))
                                        <input type="hidden" name="units[{{ $index }}][public_uuid]" value="{{ $unitRow['public_uuid'] }}">
                                    @endif
                                    <input type="hidden" name="units[{{ $index }}][is_base]" value="{{ $isBaseRow ? '1' : '0' }}" data-unit-is-base>
                                    <div class="relative" data-product-select>
                                        <input type="hidden" name="units[{{ $index }}][unit]" value="{{ $rowUnit }}" data-product-select-input data-unit-select>
                                        <button type="button" data-product-select-trigger class="flex h-9 w-full items-center justify-between gap-1 rounded-lg border border-slate-200 bg-white px-2 text-left text-xs font-black uppercase text-slate-900 transition hover:border-emerald-300 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" aria-haspopup="listbox" aria-expanded="false">
                                            <span data-product-select-label>{{ strtoupper((string) $rowUnit) }}</span>
                                            <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                            </svg>
                                        </button>
                                        <div data-product-select-menu class="absolute left-0 top-[calc(100%+0.25rem)] z-50 hidden w-28 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl shadow-slate-900/15" role="listbox">
                                            @foreach($units as $val => $label)
                                                <button type="button" data-product-select-option data-value="{{ $val }}" data-label="{{ strtoupper($val) }}" @class([
                                                    'flex h-8 w-full items-center gap-1.5 rounded-lg px-2 text-left text-[11px] font-black uppercase transition',
                                                    'bg-emerald-600 text-white' => $rowUnit === $val,
                                                    'text-slate-700 hover:bg-slate-100' => $rowUnit !== $val,
                                                ]) role="option" aria-selected="{{ $rowUnit === $val ? 'true' : 'false' }}">
                                                    <span data-product-select-check class="{{ $rowUnit === $val ? '' : 'invisible' }}">✓</span>
                                                    <span>{{ strtoupper($val) }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <input name="units[{{ $index }}][conversion_to_base]" data-unit-conversion type="number" step="0.0001" min="0.0001" value="{{ old("units.{$index}.conversion_to_base", $isBaseRow ? 1 : ($unitRow['conversion_to_base'] ?? '')) }}" class="h-9 w-full rounded-lg border border-slate-200 px-2 text-right text-xs font-bold text-slate-900 focus:border-brand-500 focus:outline-none" @readonly($isBaseRow)>
                                        <span data-base-unit-label class="shrink-0 text-[10px] font-black uppercase text-slate-400">{{ $baseUnit }}</span>
                                    </div>
                                    <label class="flex items-center justify-center gap-2 text-xs font-bold text-slate-600">
                                        <input type="hidden" name="units[{{ $index }}][is_orderable]" value="0">
                                        <input type="checkbox" name="units[{{ $index }}][is_orderable]" value="1" @checked((bool) ($unitRow['is_orderable'] ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                        Yes
                                    </label>
                                    <button type="button" data-remove-unit-row class="h-9 rounded-lg text-xs font-black text-slate-400 hover:bg-red-50 hover:text-red-600" @disabled($isBaseRow)>
                                        X
                                    </button>
                                    <input type="hidden" name="units[{{ $index }}][label]" value="{{ $unitRow['label'] ?? strtoupper((string) $rowUnit) }}" data-unit-label>
                                    <p data-unit-row-hint class="col-span-4 hidden rounded-lg px-2 py-1 text-[11px] font-black"></p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Default Warehouse --}}
                <div class="space-y-1.5">
                    <label for="default_warehouse_id" class="block text-sm font-medium text-gray-700">Default Warehouse <span class="text-gray-400 font-normal">(optional)</span></label>
                    <select id="default_warehouse_id" name="default_warehouse_id"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('default_warehouse_id') border-red-300 @enderror">
                        <option value="">None (No Default)</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" @selected(old('default_warehouse_id', $product->default_warehouse_id ?? null) == $wh->id)>{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                    @error('default_warehouse_id') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label for="buffer_qty" class="block text-sm font-medium text-gray-700">Buffer Qty</label>
                        <input id="buffer_qty" name="buffer_qty" type="number" step="0.01" min="0"
                               value="{{ old('buffer_qty', $product->buffer_qty ?? 0) }}"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('buffer_qty') border-red-300 @enderror">
                        @error('buffer_qty') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-3">
                        <input type="hidden" name="carryover_enabled" value="0">
                        <input id="carryover_enabled" name="carryover_enabled" type="checkbox" value="1"
                               @checked(old('carryover_enabled', $product->carryover_enabled ?? false))
                               class="w-4 h-4 rounded border-cyan-300 text-cyan-600 focus:ring-cyan-500/30 cursor-pointer">
                        <label for="carryover_enabled" class="text-sm font-semibold text-cyan-800 cursor-pointer">Allow daily carryover</label>
                    </div>
                </div>

                {{-- Description --}}
                <div class="space-y-1.5">
                    <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea id="description" name="description" rows="2"
                              placeholder="Product notes, varieties, storage tips…"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 resize-none">{{ old('description', $product->description ?? '') }}</textarea>
                </div>

                @isset($product)
                {{-- Active toggle (edit only) --}}
                <div class="flex items-center gap-3">
                    <input type="hidden" name="is_active" value="0">
                    <input id="is_active" name="is_active" type="checkbox" value="1"
                           @checked(old('is_active', $product->is_active ?? true))
                           class="w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/30 cursor-pointer">
                    <label for="is_active" class="text-sm text-gray-700 cursor-pointer">Active — visible in sales and inventory</label>
                </div>
                @endisset

                {{-- Actions --}}
                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button type="submit" id="save-product-btn"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors">
                        {{ isset($product) ? 'Save Changes' : 'Create Product' }}
                    </button>
                    <a href="{{ route('inventory.products.index') }}"
                       class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Cropper Modal --}}
    <div id="crop-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div class="fixed inset-0 bg-black/60 transition-opacity" aria-hidden="true" onclick="closeCropModal()"></div>

            <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-gray-100 flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900" id="modal-title">Crop Product Photo</h3>
                    <button type="button" onclick="closeCropModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                {{-- Cropper Container --}}
                <div class="flex-1 overflow-hidden p-6 bg-gray-950 flex items-center justify-center min-h-[380px] max-h-[500px]">
                    <div style="width: 100%; max-width: 450px; height: 350px; position: relative;">
                        <img id="cropper-image" style="display: block; max-width: 100%; max-height: 100%;">
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-3">
                    <button type="button" onclick="closeCropModal()" class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="button" onclick="applyCrop()" class="px-4 py-2 text-xs font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700">Apply Crop</button>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    @endpush

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
        <script>
            let cropper = null;
            let selectedFile = null;
            const productUnitOptions = @json($units);
            let previousBaseUnit = document.getElementById('unit')?.value || 'kg';

            function formatMeasureNumber(value) {
                const number = Number.parseFloat(String(value));
                if (!Number.isFinite(number)) return '';

                const rounded = Math.round((number + Number.EPSILON) * 100) / 100;
                return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
            }

            function unitLabel(unit, conversion = null, baseUnit = null) {
                const normalizedUnit = String(unit || '').toUpperCase();
                const formattedConversion = formatMeasureNumber(conversion);

                if (!formattedConversion || Number.parseFloat(String(conversion)) === 1) {
                    return normalizedUnit;
                }

                return `${normalizedUnit} ${formattedConversion} ${String(baseUnit || 'kg').toUpperCase()}`;
            }

            function productSelectOptionClasses(isSelected) {
                return [
                    'flex h-8 w-full items-center gap-1.5 rounded-lg px-2 text-left text-[11px] font-black uppercase transition',
                    isSelected ? 'bg-emerald-600 text-white' : 'text-slate-700 hover:bg-slate-100',
                ].join(' ');
            }

            function closeProductSelects(except = null) {
                document.querySelectorAll('[data-product-select]').forEach((picker) => {
                    if (picker === except) return;

                    picker.querySelector('[data-product-select-menu]')?.classList.add('hidden');
                    picker.querySelector('[data-product-select-trigger]')?.setAttribute('aria-expanded', 'false');
                });
            }

            function syncProductSelect(picker, value, label = null) {
                const input = picker.querySelector('[data-product-select-input]');
                const displayLabel = picker.querySelector('[data-product-select-label]');

                if (input) {
                    input.value = value;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (displayLabel) {
                    displayLabel.textContent = label || unitLabel(value);
                }

                picker.querySelectorAll('[data-product-select-option]').forEach((option) => {
                    const isSelected = option.getAttribute('data-value') === value;
                    option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    option.classList.toggle('bg-emerald-600', isSelected);
                    option.classList.toggle('text-white', isSelected);
                    option.classList.toggle('text-slate-700', !isSelected);
                    option.classList.toggle('hover:bg-slate-100', !isSelected);
                    option.querySelector('[data-product-select-check]')?.classList.toggle('invisible', !isSelected);
                });
            }

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-product-select-trigger]');
                if (trigger) {
                    const picker = trigger.closest('[data-product-select]');
                    const menu = picker?.querySelector('[data-product-select-menu]');
                    const willOpen = menu?.classList.contains('hidden') ?? false;

                    closeProductSelects(picker);
                    menu?.classList.toggle('hidden', !willOpen);
                    trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

                    return;
                }

                const option = event.target.closest('[data-product-select-option]');
                if (option) {
                    const picker = option.closest('[data-product-select]');
                    const value = option.getAttribute('data-value');
                    const label = option.getAttribute('data-label');

                    if (picker && value) {
                        syncProductSelect(picker, value, label);
                        picker.querySelector('[data-product-select-menu]')?.classList.add('hidden');
                        picker.querySelector('[data-product-select-trigger]')?.setAttribute('aria-expanded', 'false');
                    }

                    return;
                }

                closeProductSelects();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeProductSelects();
                }
            });

            function unitRowTemplate(index, unit = 'box', conversion = '1', isOrderable = true) {
                const options = Object.keys(productUnitOptions)
                    .map((value) => `
                        <button type="button" data-product-select-option data-value="${value}" data-label="${value.toUpperCase()}" class="${productSelectOptionClasses(value === unit)}" role="option" aria-selected="${value === unit ? 'true' : 'false'}">
                            <span data-product-select-check class="${value === unit ? '' : 'invisible'}">✓</span>
                            <span>${value.toUpperCase()}</span>
                        </button>
                    `)
                    .join('');

                return `
                    <div data-product-unit-row class="grid grid-cols-[1.2fr_1fr_1fr_2.5rem] items-center gap-2 px-3 py-2">
                        <input type="hidden" name="units[${index}][is_base]" value="0" data-unit-is-base>
                        <div class="relative" data-product-select>
                            <input type="hidden" name="units[${index}][unit]" value="${unit}" data-product-select-input data-unit-select>
                            <button type="button" data-product-select-trigger class="flex h-9 w-full items-center justify-between gap-1 rounded-lg border border-slate-200 bg-white px-2 text-left text-xs font-black uppercase text-slate-900 transition hover:border-emerald-300 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" aria-haspopup="listbox" aria-expanded="false">
                                <span data-product-select-label>${unit.toUpperCase()}</span>
                                <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div data-product-select-menu class="absolute left-0 top-[calc(100%+0.25rem)] z-50 hidden w-28 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl shadow-slate-900/15" role="listbox">
                                ${options}
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <input name="units[${index}][conversion_to_base]" data-unit-conversion type="number" step="0.0001" min="0.0001" value="${conversion}" class="h-9 w-full rounded-lg border border-slate-200 px-2 text-right text-xs font-bold text-slate-900 focus:border-brand-500 focus:outline-none">
                            <span data-base-unit-label class="shrink-0 text-[10px] font-black uppercase text-slate-400">${document.getElementById('unit')?.value || 'kg'}</span>
                        </div>
                        <label class="flex items-center justify-center gap-2 text-xs font-bold text-slate-600">
                            <input type="hidden" name="units[${index}][is_orderable]" value="0">
                            <input type="checkbox" name="units[${index}][is_orderable]" value="1" ${isOrderable ? 'checked' : ''} class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            Yes
                        </label>
                        <button type="button" data-remove-unit-row class="h-9 rounded-lg text-xs font-black text-slate-400 hover:bg-red-50 hover:text-red-600">X</button>
                        <input type="hidden" name="units[${index}][label]" value="${unitLabel(unit, conversion, document.getElementById('unit')?.value || 'kg')}" data-unit-label>
                        <p data-unit-row-hint class="col-span-4 hidden rounded-lg px-2 py-1 text-[11px] font-black"></p>
                    </div>
                `;
            }

            function syncProductUnitRows() {
                const baseUnit = document.getElementById('unit')?.value || 'kg';
                const rows = Array.from(document.querySelectorAll('[data-product-unit-row]'));
                let hasBaseRow = false;

                rows.forEach((row, index) => {
                    const unitSelect = row.querySelector('[data-unit-select]');
                    const conversionInput = row.querySelector('[data-unit-conversion]');
                    const baseInput = row.querySelector('[data-unit-is-base]');
                    const labelInput = row.querySelector('[data-unit-label]');
                    const removeButton = row.querySelector('[data-remove-unit-row]');
                    const hint = row.querySelector('[data-unit-row-hint]');
                    const isBase = unitSelect?.value === baseUnit;

                    if (isBase) {
                        hasBaseRow = true;
                    }

                    row.querySelectorAll('[name]').forEach((input) => {
                        input.name = input.name.replace(/units\[\d+\]/, `units[${index}]`);
                    });

                    if (baseInput) baseInput.value = isBase ? '1' : '0';
                    if (conversionInput) {
                        conversionInput.readOnly = isBase;
                        if (isBase) conversionInput.value = '1';
                    }
                    if (labelInput && unitSelect) labelInput.value = unitLabel(unitSelect.value, conversionInput?.value, baseUnit);
                    if (removeButton) {
                        removeButton.disabled = isBase;
                        removeButton.classList.toggle('opacity-40', isBase);
                    }
                    if (hint && unitSelect) {
                        hint.className = 'col-span-4 hidden rounded-lg px-2 py-1 text-[11px] font-black';

                        if (!isBase && unitSelect.value === 'box') {
                            hint.textContent = 'BOX needs KG conversion. Example: 1 BOX = 12 ' + baseUnit.toUpperCase();
                            hint.classList.remove('hidden');
                            hint.classList.add('border', 'border-emerald-100', 'bg-emerald-50', 'text-emerald-700');
                        } else if (!isBase && unitSelect.value === 'piece') {
                            hint.textContent = conversionInput?.value
                                ? 'Shop owner sees PIECE and conversion info.'
                                : 'Shop owner sees PIECE as count only.';
                            hint.classList.remove('hidden');
                            hint.classList.add('border', 'border-sky-100', 'bg-sky-50', 'text-sky-700');
                        }
                    }
                    row.querySelectorAll('[data-base-unit-label]').forEach((label) => {
                        label.textContent = baseUnit.toUpperCase();
                    });
                });

                if (!hasBaseRow) {
                    const container = document.getElementById('product-unit-rows');
                    container?.insertAdjacentHTML('afterbegin', unitRowTemplate(0, baseUnit, '1', true));
                    syncProductUnitRows();
                }
            }

            function handleBaseUnitChange() {
                const baseUnit = document.getElementById('unit')?.value || 'kg';
                const rows = Array.from(document.querySelectorAll('[data-product-unit-row]'));
                const previousBaseRow = rows.find((row) => row.querySelector('[data-unit-select]')?.value === previousBaseUnit);
                const newBaseRow = rows.find((row) => row.querySelector('[data-unit-select]')?.value === baseUnit);

                if (!newBaseRow && previousBaseRow) {
                    const picker = previousBaseRow.querySelector('[data-product-select]');
                    if (picker) {
                        syncProductSelect(picker, baseUnit, unitLabel(baseUnit));
                    }
                } else if (newBaseRow && previousBaseRow && newBaseRow !== previousBaseRow) {
                    previousBaseRow.remove();
                }

                previousBaseUnit = baseUnit;
                syncProductUnitRows();
            }

            document.getElementById('add-product-unit-row')?.addEventListener('click', () => {
                const container = document.getElementById('product-unit-rows');
                if (!container) return;

                const usedUnits = Array.from(document.querySelectorAll('[data-unit-select]')).map((select) => select.value);
                const nextUnit = usedUnits.includes('box')
                    ? 'box'
                    : (Object.keys(productUnitOptions).find((unit) => !usedUnits.includes(unit)) || 'box');
                container.insertAdjacentHTML('beforeend', unitRowTemplate(container.children.length, nextUnit, '1', true));
                syncProductUnitRows();
            });

            document.getElementById('product-unit-rows')?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-unit-row]');
                if (!button || button.disabled) return;

                button.closest('[data-product-unit-row]')?.remove();
                syncProductUnitRows();
            });

            document.getElementById('product-unit-rows')?.addEventListener('change', (event) => {
                if (event.target.matches('[data-unit-select]')) {
                    syncProductUnitRows();
                }
            });
            document.getElementById('product-unit-rows')?.addEventListener('input', (event) => {
                if (event.target.matches('[data-unit-conversion]')) {
                    syncProductUnitRows();
                }
            });

            document.getElementById('unit')?.addEventListener('change', handleBaseUnitChange);
            syncProductUnitRows();

            function handleImageSelect(event) {
                const files = event.target.files;
                if (!files || files.length === 0) return;

                selectedFile = files[0];
                const reader = new FileReader();
                reader.onload = function (e) {
                    const cropperImage = document.getElementById('cropper-image');
                    
                    // Show modal first so all elements have non-zero layout dimensions
                    document.getElementById('crop-modal').classList.remove('hidden');

                    // Setup onload handler
                    cropperImage.onload = function () {
                        // Destroy previous cropper if any
                        if (cropper) {
                            cropper.destroy();
                            cropper = null;
                        }

                        // Delay initialization slightly to let modal animation/reflow finish
                        setTimeout(() => {
                            try {
                                cropper = new Cropper(cropperImage, {
                                    aspectRatio: 1,
                                    viewMode: 1,
                                    dragMode: 'move',
                                    autoCropArea: 1,
                                    restore: false,
                                    guides: true,
                                    center: true,
                                    highlight: false,
                                    cropBoxMovable: true,
                                    cropBoxResizable: true,
                                    toggleDragModeOnDblclick: false,
                                });
                            } catch (err) {
                                console.error('Failed to initialize Cropper:', err);
                            }
                        }, 100);

                        // Clear onload to prevent loops
                        cropperImage.onload = null;
                    };

                    cropperImage.src = e.target.result;
                };
                reader.readAsDataURL(selectedFile);
            }

            function closeCropModal() {
                document.getElementById('crop-modal').classList.add('hidden');
                document.getElementById('image_input').value = ''; // Reset input
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
            }

            function applyCrop() {
                if (!cropper) {
                    alert('Cropper is not initialized.');
                    return;
                }

                // Get cropped canvas at 400x400
                const canvas = cropper.getCroppedCanvas({
                    width: 400,
                    height: 400,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                if (!canvas) {
                    alert('Could not crop image. Please try again.');
                    return;
                }

                // Convert to base64 DataURL (JPEG for efficiency)
                const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

                // Update hidden input & preview
                document.getElementById('image_data').value = dataUrl;
                document.getElementById('remove_image').value = '0';

                const previewImg = document.getElementById('preview-image');
                previewImg.src = dataUrl;
                previewImg.classList.remove('hidden');

                const placeholder = document.getElementById('preview-placeholder');
                if (placeholder) {
                    placeholder.classList.add('hidden');
                }

                // Show remove button
                document.getElementById('remove-btn').classList.remove('hidden');

                closeCropModal();
            }

            function removeSelectedImage() {
                // Set remove flag
                document.getElementById('remove_image').value = '1';
                document.getElementById('image_data').value = '';
                document.getElementById('image_input').value = '';

                // Update preview and buttons
                const previewImg = document.getElementById('preview-image');
                previewImg.src = '';
                previewImg.classList.add('hidden');

                // Find or dynamically create placeholder if not present
                let placeholder = document.getElementById('preview-placeholder');
                if (!placeholder) {
                    const container = previewImg.parentElement;
                    container.insertAdjacentHTML('beforeend', `
                        <div id="preview-placeholder" class="text-gray-400 text-center p-2">
                            <svg class="w-8 h-8 mx-auto opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375 0 11-.75 0 .375 0 01.75 0z" />
                            </svg>
                            <span class="text-[10px] block mt-1 font-medium">No Image</span>
                        </div>
                    `);
                } else {
                    placeholder.classList.remove('hidden');
                }

                document.getElementById('remove-btn').classList.add('hidden');
            }
        </script>
    @endpush

</x-layouts.inventory>
