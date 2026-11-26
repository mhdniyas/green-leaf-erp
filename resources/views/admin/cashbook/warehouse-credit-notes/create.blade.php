@extends('admin.cashbook.layouts.app')

@section('title', ($isEdit ? 'Edit Draft — Warehouse Credit Note' : 'New Warehouse Credit Note') . ' — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="{{ $isEdit ? 'file-edit' : 'file-plus-2' }}" class="h-5 w-5 text-emerald-600"></i>
    <span>{{ $isEdit ? 'Edit Warehouse Credit Note Draft' : 'Create Warehouse Credit Note' }}</span>
@endsection

@section('header_subtitle')
    Record warehouse Stock IN, exact-grade FIFO Stock OUT, or Damage adjustments. Drafts have zero inventory or ledger impact.
@endsection

@section('content')
@php
    $oldItems = old('items');
    $initialRows = [];

    if (is_array($oldItems) && count($oldItems) > 0) {
        foreach ($oldItems as $row) {
            $productModel = ! empty($row['product_id'])
                ? \App\Models\Product::with('orderUnits')->find((int) $row['product_id'])
                : null;

            $units = [];
            if ($productModel) {
                $baseUnit = $productModel->unit ?: 'kg';
                $units[] = ['unit' => $baseUnit, 'label' => $baseUnit, 'conversion_to_base' => 1.0];
                foreach ($productModel->orderUnits as $pu) {
                    if (strcasecmp((string) $pu->unit, $baseUnit) !== 0 && (float) $pu->conversion_to_base > 0) {
                        $units[] = [
                            'unit' => $pu->unit,
                            'label' => $pu->label ?: $pu->unit,
                            'conversion_to_base' => round((float) $pu->conversion_to_base, 4),
                        ];
                    }
                }
            }

            $initialRows[] = [
                'product_id' => $row['product_id'] ?? '',
                'product_name' => $productModel?->name ?? '',
                'product_sku' => $productModel?->sku ?? '',
                'grade' => $row['grade'] ?? 'A',
                'inventory_action' => $row['inventory_action'] ?? 'out',
                'unit' => $row['unit'] ?? ($productModel?->unit ?: 'kg'),
                'units' => $units,
                'quantity' => $row['quantity'] ?? '',
                'valuation_rate' => $row['valuation_rate'] ?? '0',
                'notes' => $row['notes'] ?? '',
                'available_qty' => null,
                'loading_stock' => false,
                'stock_error' => '',
                'stock_req_seq' => 0,
            ];
        }
    } elseif ($isEdit && $creditNote) {
        foreach ($creditNote->items as $item) {
            $productModel = $item->product;
            $baseUnit = $productModel?->unit ?: 'kg';
            $units = [['unit' => $baseUnit, 'label' => $baseUnit, 'conversion_to_base' => 1.0]];
            if ($productModel) {
                foreach ($productModel->orderUnits as $pu) {
                    if (strcasecmp((string) $pu->unit, $baseUnit) !== 0 && (float) $pu->conversion_to_base > 0) {
                        $units[] = [
                            'unit' => $pu->unit,
                            'label' => $pu->label ?: $pu->unit,
                            'conversion_to_base' => round((float) $pu->conversion_to_base, 4),
                        ];
                    }
                }
            }

            $initialRows[] = [
                'product_id' => $item->product_id,
                'product_name' => $productModel?->name ?? "Product #{$item->product_id}",
                'product_sku' => $productModel?->sku ?? '',
                'grade' => $item->grade instanceof \App\Enums\Inventory\ProductGrade ? $item->grade->value : (string) $item->grade,
                'inventory_action' => $item->inventory_action instanceof \App\Enums\Warehouse\WarehouseCreditInventoryAction ? $item->inventory_action->value : (string) $item->inventory_action,
                'unit' => $item->unit ?: $baseUnit,
                'units' => $units,
                'quantity' => (float) $item->quantity,
                'valuation_rate' => (float) $item->valuation_rate,
                'notes' => $item->notes ?? '',
                'available_qty' => null,
                'loading_stock' => false,
                'stock_error' => '',
                'stock_req_seq' => 0,
            ];
        }
    }

    // Determine saved direction for edit mode, or default to 'out' on create
    $actionsInRows = collect($initialRows)->pluck('inventory_action')->unique()->values();
    $hasIn = $actionsInRows->contains('in');
    $hasOutOrDamage = $actionsInRows->contains('out') || $actionsInRows->contains('damage');

    $defaultDirection = 'out';
    $isMixedDraft = false;
    if (old('movement_direction')) {
        $defaultDirection = (string) old('movement_direction');
        $isMixedDraft = $defaultDirection === 'mixed';
    } elseif ($isEdit && $creditNote) {
        if ($hasIn && $hasOutOrDamage) {
            $defaultDirection = 'mixed';
            $isMixedDraft = true;
        } elseif ($hasIn) {
            $defaultDirection = 'in';
        } else {
            $defaultDirection = 'out';
        }
    }

    if (empty($initialRows)) {
        $initialRows[] = [
            'product_id' => '',
            'product_name' => '',
            'product_sku' => '',
            'grade' => 'A',
            'inventory_action' => 'out',
            'unit' => 'kg',
            'units' => [['unit' => 'kg', 'label' => 'kg', 'conversion_to_base' => 1.0]],
            'quantity' => '',
            'valuation_rate' => '0',
            'notes' => '',
            'available_qty' => null,
            'loading_stock' => false,
            'stock_error' => '',
            'stock_req_seq' => 0,
        ];
    }

    $selectedWarehouseId = old('warehouse_id', $isEdit && $creditNote ? $creditNote->warehouse_id : ($warehouses->first()?->id ?? ''));
    $selectedReason = old('reason', $isEdit && $creditNote ? $creditNote->reason->value : 'shortage');
    $selectedBusinessDate = old('business_date', $defaultDate);
    $selectedRemarks = old('remarks', $isEdit && $creditNote ? ($creditNote->remarks ?? '') : '');
@endphp

<div class="mx-auto max-w-[96rem] space-y-6"
     x-data="warehouseCreditNoteForm({
         warehouseId: '{{ $selectedWarehouseId }}',
         movementDirection: '{{ $defaultDirection }}',
         isMixedDraft: {{ $isMixedDraft ? 'true' : 'false' }},
         reason: '{{ $selectedReason }}',
         searchUrl: '{{ route('admin.cashbook.warehouse-credit-notes.search-products') }}',
         stockUrl: '{{ route('admin.cashbook.warehouse-credit-notes.stock-availability') }}',
         rows: @js($initialRows)
     })"
     x-init="init()">

    {{-- Top Navigation --}}
    <div class="flex items-center justify-between border-b border-slate-200/80 pb-4">
        <a href="{{ route('admin.cashbook.warehouse-credit-notes.index') }}"
           class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5 text-slate-400"></i>
            <span>Back to Warehouse Credit Notes</span>
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800">
            <div class="flex items-center gap-2 font-bold">
                <i data-lucide="alert-triangle" class="h-4 w-4 text-rose-600"></i>
                <span>Please resolve the following errors:</span>
            </div>
            <ul class="mt-2 list-disc space-y-1 pl-5 font-medium">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="wcn-form"
          method="POST"
          action="{{ $isEdit ? route('admin.cashbook.warehouse-credit-notes.update', ['creditNote' => $creditNote->public_uuid]) : route('admin.cashbook.warehouse-credit-notes.store') }}"
          @submit="handleFormSubmit($event)">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <input type="hidden" name="save_action" x-model="saveAction">
        <input type="hidden" name="movement_direction" x-model="movementDirection">

        {{-- Primary Stock Movement Direction Radio Group --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs space-y-3">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">1. Stock Movement Direction</h2>
                <span class="text-[11px] font-semibold text-slate-500">
                    Controls inventory direction, reason options, and valuation mode for all rows.
                </span>
            </div>

            <div x-show="isMixedDraft" x-cloak
                 class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-xs font-semibold text-amber-800">
                <i data-lucide="info" class="h-4 w-4 shrink-0 text-amber-600"></i>
                <span>This existing draft contains mixed Stock IN and Stock OUT/Damage rows. Saved row actions are preserved unless you select Stock IN or Stock OUT below.</span>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {{-- Stock IN Radio Card --}}
                <button type="button"
                        @click="switchDirection('in')"
                        :class="movementDirection === 'in'
                            ? 'border-emerald-500 bg-emerald-50/90 ring-2 ring-emerald-500/20 shadow-xs'
                            : 'border-slate-200 bg-white hover:border-emerald-300 hover:bg-emerald-50/30'"
                        class="flex items-center gap-4 rounded-2xl border-2 p-4 text-left transition">
                    <span :class="movementDirection === 'in' ? 'bg-emerald-600 text-white' : 'bg-emerald-100 text-emerald-700'"
                          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl transition">
                        <i data-lucide="package-plus" class="h-6 w-6"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-black text-emerald-900">Stock IN</span>
                            <span x-show="movementDirection === 'in'"
                                  class="inline-flex items-center gap-1 rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-white">
                                Selected
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-emerald-800/80">Add stock to warehouse with manual valuation rate.</p>
                    </div>
                </button>

                {{-- Stock OUT Radio Card --}}
                <button type="button"
                        @click="switchDirection('out')"
                        :class="movementDirection === 'out'
                            ? 'border-rose-500 bg-rose-50/90 ring-2 ring-rose-500/20 shadow-xs'
                            : 'border-slate-200 bg-white hover:border-rose-300 hover:bg-rose-50/30'"
                        class="flex items-center gap-4 rounded-2xl border-2 p-4 text-left transition">
                    <span :class="movementDirection === 'out' ? 'bg-rose-600 text-white' : 'bg-rose-100 text-rose-700'"
                          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl transition">
                        <i data-lucide="package-minus" class="h-6 w-6"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-black text-rose-900">Stock OUT</span>
                            <span x-show="movementDirection === 'out'"
                                  class="inline-flex items-center gap-1 rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-white">
                                Selected
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-rose-800/80">Deduct exact-grade stock (or Damage/Spoilage) at FIFO batch cost.</p>
                    </div>
                </button>
            </div>
        </div>

        {{-- Header Details --}}
        <div class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">2. Credit Note Details</h2>
                <span x-show="movementDirection !== 'in'"
                      class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800">
                    Automatic FIFO valuation (computed from batches on Finalize)
                </span>
                <span x-show="movementDirection === 'in'" x-cloak
                      class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-800">
                    Manual valuation enabled for Stock IN
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-700">Warehouse <span class="text-rose-500">*</span></label>
                    <select name="warehouse_id" x-model="warehouseId" @change="refreshAllStock()" required
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                        <option value="">Select Warehouse</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" @selected((string) $selectedWarehouseId === (string) $wh->id)>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-700">Business Date <span class="text-rose-500">*</span></label>
                    <input type="date" name="business_date" value="{{ $selectedBusinessDate }}" required
                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-700">Reason <span class="text-rose-500">*</span></label>
                    <select name="reason" x-model="reason" @change="syncRowActions()" required
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                        <template x-for="opt in availableReasons()" :key="opt.value">
                            <option :value="opt.value" :selected="opt.value === reason" x-text="opt.label"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-700">Remarks</label>
                    <input type="text" name="remarks" value="{{ $selectedRemarks }}" placeholder="Optional audit remarks..."
                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                </div>
            </div>
        </div>

        {{-- Product Line Items --}}
        <div class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">3. Product Line Items</h2>
                    <span x-show="!isMixedDraft"
                          :class="resolvedAction() === 'in'
                              ? 'bg-emerald-100 text-emerald-800'
                              : (resolvedAction() === 'damage' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800')"
                          class="rounded-full px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider"
                          x-text="actionBadgeLabel(resolvedAction())"></span>
                </div>
                <button type="button" @click="addRow()"
                        class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-700 hover:bg-emerald-100">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                    <span>Add Product Row</span>
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3.5">
                        <input type="hidden" :name="`items[${index}][inventory_action]`" :value="rowEffectiveAction(row)">

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                            {{-- Product Search --}}
                            <div class="relative md:col-span-4">
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Product</label>
                                <input type="hidden" :name="`items[${index}][product_id]`" x-model="row.product_id">
                                <div class="relative">
                                    <input type="text"
                                           x-model="row.product_name"
                                           @input="onProductSearchInput(index, $event.target.value)"
                                           @focus="openSearchDropdown(index)"
                                           placeholder="Search product name or SKU..."
                                           autocomplete="off"
                                           class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                                    <div x-show="activeSearchIndex === index"
                                         @click.outside="activeSearchIndex = null"
                                         class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                                        <div x-show="searchingProducts" class="px-3 py-2 text-xs text-slate-500">Searching...</div>
                                        <template x-for="prod in searchResults" :key="prod.id">
                                            <button type="button"
                                                    @click="selectProduct(index, prod)"
                                                    class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-xs hover:bg-emerald-50">
                                                <span class="font-semibold text-slate-800" x-text="prod.name"></span>
                                                <span class="font-mono text-[11px] text-slate-400" x-text="prod.sku"></span>
                                            </button>
                                        </template>
                                        <div x-show="!searchingProducts && searchResults.length === 0" class="px-3 py-2 text-xs text-slate-400">
                                            No matching products.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Grade --}}
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Grade</label>
                                <select :name="`items[${index}][grade]`" x-model="row.grade" @change="fetchRowStock(index)"
                                        class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-800">
                                    @foreach ($grades as $g)
                                        <option value="{{ $g->value }}">{{ $g === \App\Enums\Inventory\ProductGrade::Unsorted ? 'Mixed (U)' : 'Grade ' . $g->value }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Unit --}}
                            <div class="md:col-span-1">
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Unit</label>
                                <select :name="`items[${index}][unit]`" x-model="row.unit"
                                        class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-800">
                                    <template x-for="u in row.units" :key="u.unit">
                                        <option :value="u.unit" x-text="u.label"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- Available Stock --}}
                            <div class="md:col-span-1">
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Available</label>
                                <div class="flex h-9 items-center rounded-xl border border-slate-200 bg-slate-100 px-2.5 text-xs font-mono font-bold text-slate-700">
                                    <span x-show="row.loading_stock">...</span>
                                    <span x-show="!row.loading_stock" x-text="row.available_qty !== null ? Number(row.available_qty).toFixed(3) : '—'"></span>
                                </div>
                            </div>

                            {{-- Quantity --}}
                            <div class="md:col-span-1">
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Qty</label>
                                <input type="number" step="0.001" min="0.001" :name="`items[${index}][quantity]`" x-model="row.quantity" required
                                       class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-mono font-semibold text-slate-800">
                            </div>

                            {{-- Rate --}}
                            <div class="md:col-span-1">
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500"
                                       x-text="rowEffectiveAction(row) === 'in' ? 'Rate (₹)' : 'Est. FIFO'"></label>
                                <input type="number" step="0.0001" min="0"
                                       :name="`items[${index}][valuation_rate]`"
                                       x-model="row.valuation_rate"
                                       :readonly="rowEffectiveAction(row) !== 'in'"
                                       :class="rowEffectiveAction(row) !== 'in' ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : 'bg-white text-slate-900'"
                                       class="h-9 w-full rounded-xl border border-slate-200 px-2.5 text-xs font-mono font-semibold">
                            </div>

                            {{-- Amount + Remove --}}
                            <div class="md:col-span-2 flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        <span x-show="isMixedDraft" class="mr-1 text-[10px] font-extrabold uppercase text-slate-400" x-text="`[${row.inventory_action}]`"></span>
                                        Est. Amount
                                    </label>
                                    <div class="flex h-9 items-center rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-mono font-bold text-slate-900">
                                        ₹<span x-text="lineAmount(row).toFixed(2)"></span>
                                    </div>
                                </div>
                                <button type="button" @click="removeRow(index)" x-show="rows.length > 1"
                                        title="Remove Row"
                                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>

                        <div x-show="row.stock_error" class="mt-2 text-[11px] font-semibold text-rose-600" x-text="row.stock_error"></div>
                    </div>
                </template>
            </div>

            {{-- Footer Totals & Actions --}}
            <div class="flex flex-col gap-4 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-xs text-slate-600">
                    Estimated Document Total:
                    <span class="ml-1 text-lg font-black font-mono text-slate-900">₹<span x-text="grandTotal().toFixed(2)"></span></span>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <button type="submit"
                            @click="saveAction = 'draft'"
                            :disabled="isSubmitting"
                            class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                        <i data-lucide="save" class="h-4 w-4 text-slate-500"></i>
                        <span x-text="isSubmitting && saveAction === 'draft' ? 'Saving Draft...' : 'Save Draft'"></span>
                    </button>

                    <button type="button"
                            @click="showFinalizeConfirm = true"
                            :disabled="isSubmitting"
                            class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 disabled:opacity-50">
                        <i data-lucide="check-check" class="h-4 w-4"></i>
                        <span>Save &amp; Finalize</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Finalize Confirmation Modal --}}
        <div x-show="showFinalizeConfirm" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center gap-2 text-slate-900">
                    <i data-lucide="shield-check" class="h-5 w-5 text-emerald-600"></i>
                    <h3 class="text-base font-black">Confirm Finalization</h3>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-slate-600">
                    Finalizing will immediately post exact-grade stock movements, compute authoritative FIFO batch valuation, and post a balanced double-entry journal entry. Once finalized, the credit note cannot be edited.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="showFinalizeConfirm = false"
                            class="inline-flex h-9 items-center rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" @click="confirmFinalize()" :disabled="isSubmitting"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                        <i data-lucide="check" class="h-3.5 w-3.5"></i>
                        <span x-text="isSubmitting ? 'Finalizing...' : 'Confirm &amp; Post'"></span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function warehouseCreditNoteForm(config) {
    const IN_REASONS = [
        { value: 'excess_found', label: 'Excess Stock Found' },
        { value: 'stock_return', label: 'Stock Return / Adjustment' },
        { value: 'other', label: 'Other' },
    ];
    const OUT_REASONS = [
        { value: 'shortage', label: 'Weight / Quantity Shortage' },
        { value: 'stock_return', label: 'Stock Adjustment' },
        { value: 'damage_wastage', label: 'Damage / Wastage' },
        { value: 'spoilage', label: 'Spoilage / Expiry' },
        { value: 'other', label: 'Other' },
    ];

    return {
        warehouseId: config.warehouseId || '',
        movementDirection: config.movementDirection || 'out',
        isMixedDraft: Boolean(config.isMixedDraft),
        reason: config.reason || 'shortage',
        searchUrl: config.searchUrl,
        stockUrl: config.stockUrl,
        rows: config.rows || [],
        saveAction: 'draft',
        isSubmitting: false,
        showFinalizeConfirm: false,
        activeSearchIndex: null,
        searchingProducts: false,
        searchResults: [],
        searchTimer: null,

        init() {
            if (!this.isMixedDraft) {
                const valid = this.availableReasons().some(r => r.value === this.reason);
                if (!valid) {
                    this.reason = this.availableReasons()[0].value;
                }
                this.syncRowActions();
            }

            this.rows.forEach((row, index) => {
                if (row.product_id && this.warehouseId) {
                    this.fetchRowStock(index);
                }
            });
        },

        availableReasons() {
            if (this.movementDirection === 'in') {
                return IN_REASONS;
            }
            if (this.movementDirection === 'mixed') {
                return [...OUT_REASONS, ...IN_REASONS.filter(r => !OUT_REASONS.some(o => o.value === r.value))];
            }
            return OUT_REASONS;
        },

        resolvedAction() {
            if (this.movementDirection === 'in') {
                return 'in';
            }
            if (this.reason === 'damage_wastage' || this.reason === 'spoilage') {
                return 'damage';
            }
            return 'out';
        },

        rowEffectiveAction(row) {
            if (this.isMixedDraft) {
                return row.inventory_action || 'out';
            }
            return this.resolvedAction();
        },

        actionBadgeLabel(action) {
            if (action === 'in') return 'Stock IN';
            if (action === 'damage') return 'Damage / Wastage (FIFO)';
            return 'Stock OUT (FIFO)';
        },

        hasRowData() {
            return this.rows.some(r => Boolean(r.product_id) || Number(r.quantity || 0) > 0);
        },

        switchDirection(target) {
            if (this.movementDirection === target && !this.isMixedDraft) {
                return;
            }

            if (this.hasRowData()) {
                const label = target === 'in' ? 'Stock IN' : 'Stock OUT';
                if (!window.confirm(`Switching to ${label} will update all existing product rows. Continue?`)) {
                    return;
                }
            }

            this.isMixedDraft = false;
            this.movementDirection = target;

            const validReasons = this.availableReasons();
            if (!validReasons.some(r => r.value === this.reason)) {
                this.reason = validReasons[0].value;
            }

            this.syncRowActions();
            this.refreshAllStock();
        },

        syncRowActions() {
            if (this.isMixedDraft) {
                return;
            }
            const action = this.resolvedAction();
            this.rows.forEach((row, index) => {
                row.inventory_action = action;
                if (action !== 'in' && row.product_id && this.warehouseId) {
                    this.fetchRowStock(index);
                }
            });
        },

        addRow() {
            this.rows.push({
                product_id: '',
                product_name: '',
                product_sku: '',
                grade: 'A',
                inventory_action: this.resolvedAction(),
                unit: 'kg',
                units: [{ unit: 'kg', label: 'kg', conversion_to_base: 1.0 }],
                quantity: '',
                valuation_rate: '0',
                notes: '',
                available_qty: null,
                loading_stock: false,
                stock_error: '',
                stock_req_seq: 0,
            });
            this.$nextTick(() => window.lucide?.createIcons());
        },

        removeRow(index) {
            if (this.rows.length > 1) {
                this.rows.splice(index, 1);
            }
        },

        openSearchDropdown(index) {
            this.activeSearchIndex = index;
            this.performProductSearch(this.rows[index].product_name || '');
        },

        onProductSearchInput(index, query) {
            this.activeSearchIndex = index;
            this.rows[index].product_id = '';
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                this.performProductSearch(query);
            }, 250);
        },

        async performProductSearch(query) {
            this.searchingProducts = true;
            try {
                const url = `${this.searchUrl}?q=${encodeURIComponent(query)}&limit=25`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.searchResults = data.items || [];
            } catch (e) {
                this.searchResults = [];
            } finally {
                this.searchingProducts = false;
            }
        },

        selectProduct(index, product) {
            const row = this.rows[index];
            row.product_id = product.id;
            row.product_name = product.name;
            row.product_sku = product.sku || '';
            row.units = product.units || [{ unit: product.base_unit || 'kg', label: product.base_unit || 'kg', conversion_to_base: 1.0 }];
            row.unit = row.units[0].unit;
            this.activeSearchIndex = null;
            this.fetchRowStock(index);
        },

        refreshAllStock() {
            this.rows.forEach((row, index) => {
                if (row.product_id) {
                    this.fetchRowStock(index);
                }
            });
        },

        async fetchRowStock(index) {
            const row = this.rows[index];
            if (!this.warehouseId || !row.product_id) {
                row.available_qty = null;
                return;
            }

            const seq = ++row.stock_req_seq;
            row.loading_stock = true;
            row.stock_error = '';

            try {
                const url = `${this.stockUrl}?warehouse_id=${encodeURIComponent(this.warehouseId)}&product_id=${encodeURIComponent(row.product_id)}&grade=${encodeURIComponent(row.grade)}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) {
                    throw new Error('Failed to load stock availability');
                }
                const data = await res.json();

                if (seq !== row.stock_req_seq) {
                    return;
                }

                row.available_qty = data.available_qty ?? 0;
                const action = this.rowEffectiveAction(row);
                if (action !== 'in' || !row.valuation_rate || Number(row.valuation_rate) === 0) {
                    row.valuation_rate = data.estimated_fifo_rate ?? 0;
                }
            } catch (e) {
                if (seq === row.stock_req_seq) {
                    row.stock_error = 'Unable to fetch stock availability.';
                }
            } finally {
                if (seq === row.stock_req_seq) {
                    row.loading_stock = false;
                }
            }
        },

        lineAmount(row) {
            const unitObj = (row.units || []).find(u => u.unit === row.unit);
            const conv = unitObj ? Number(unitObj.conversion_to_base || 1) : 1;
            const qty = Number(row.quantity || 0);
            const rate = Number(row.valuation_rate || 0);
            return Math.max(0, qty * conv * rate);
        },

        grandTotal() {
            return this.rows.reduce((sum, row) => sum + this.lineAmount(row), 0);
        },

        confirmFinalize() {
            this.saveAction = 'finalize';
            this.showFinalizeConfirm = false;
            this.isSubmitting = true;
            document.getElementById('wcn-form').submit();
        },

        handleFormSubmit(event) {
            if (this.isSubmitting) {
                event.preventDefault();
                return;
            }
            this.isSubmitting = true;
        }
    };
}
</script>
@endpush
@endsection
