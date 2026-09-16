{{-- Shared Purchase Bill / GRN Form Partial for Business Days --}}
@php
    $isEdit = isset($grn);
    $formAction = $isEdit
        ? route('purchasing.business-days.bills.update', ['uuid' => $day->uuid, 'grn' => $grn->getRouteKey()])
        : route('purchasing.business-days.bills.store', $day->uuid);
    $itemsToRender = old('items', $prefilledItems ?? []);
    if (empty($itemsToRender) && $isEdit && isset($grn)) {
        $itemsToRender = $grn->items->map(fn($item) => [
            'product_id' => $item->product_id,
            'received_qty' => (float) $item->received_qty,
            'received_unit' => $item->unit ?? 'kg',
            'unit_price' => (float) $item->unit_price,
            'pending_qty' => null,
        ])->toArray();
    }
@endphp

<div class="space-y-4">
    {{-- ── 1. COMPACT BUSINESS DAY CONTEXT STRIP ───────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-teal-100 bg-teal-50/90 px-4 py-3 text-xs">
        <div class="flex flex-wrap items-center gap-3 font-bold text-teal-950">
            <span class="flex items-center gap-1.5">
                <svg class="h-4 w-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <span>Business Day: <strong class="font-black text-teal-900">{{ $day->business_date->format('d M Y') }}</strong></span>
            </span>
            <span class="hidden text-teal-300 sm:inline">•</span>
            <span class="flex items-center gap-1.5">
                <svg class="h-4 w-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.25m16.5 0V15m-16.5 6v-6.75A2.25 2.25 0 0 1 4.5 6h15a2.25 2.25 0 0 1 2.25 2.25V21" />
                </svg>
                <span>Warehouse: <strong class="font-black text-teal-900">{{ $warehouse->name }}</strong></span>
            </span>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">
                {{ $day->status }}
            </span>
            <a href="{{ route('purchasing.business-days.show', $day->uuid) }}" class="text-xs font-bold text-teal-700 hover:text-teal-900 hover:underline">
                ← Day Detail
            </a>
        </div>
    </div>

    {{-- ── 2. SHARED PURCHASER BILL ENTRY FORM ─────────────────────────── --}}
    <form method="POST" action="{{ $formAction }}" id="bill-form" class="space-y-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif
        <input type="hidden" name="carry_forward_uuid" value="{{ old('carry_forward_uuid', request('carry_forward', $carryRecord->uuid ?? '')) }}">

        {{-- Main Form Fields Container (Matching standard purchaser PO/GRN card) --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-5">
            <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">{{ $isEdit ? 'Edit Purchase Bill' : 'Record Purchase Bill' }}</h3>
                    <p class="text-xs text-slate-500 font-medium">Enter actual bill details, quantities received, and unit prices.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Business Day Assignment --}}
                @if (isset($openBusinessDays))
                    <div>
                        <label for="business_day_id" class="block text-xs font-semibold text-slate-700">Business Day Target <span class="text-rose-500">*</span></label>
                        <select id="business_day_id" name="business_day_id" required class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                            @foreach ($openBusinessDays as $obd)
                                <option value="{{ $obd->id }}" @selected((int) old('business_day_id', $day->id) === (int) $obd->id)>
                                    {{ $obd->warehouse?->name ?? 'Warehouse' }} — {{ $obd->business_date->format('d M Y') }} ({{ strtoupper($obd->status) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="business_day_id" value="{{ $day->id }}">
                @endif

                {{-- Supplier / Vendor (Searchable Dropdown — vanilla JS) --}}
                @if ($isEdit)
                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Supplier / Vendor</label>
                        <input type="text" readonly disabled value="{{ $grn->purchaseOrder?->supplier?->name ?? 'Supplier' }}" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-100 px-3 text-xs font-bold text-slate-600">
                        <input type="hidden" name="supplier_id" value="{{ $grn->supplier_id }}">
                    </div>
                @else
                    <div class="relative" id="supplier-dropdown-wrapper">
                        <label for="supplier-toggle-btn" class="block text-xs font-semibold text-slate-700">
                            Supplier / Vendor <span class="text-rose-500">*</span>
                        </label>

                        <input type="hidden" name="supplier_id" id="supplier_id_hidden" value="{{ old('supplier_id', '') }}" required>

                        <div class="relative mt-1.5">
                            {{-- Toggle button --}}
                            <button type="button" id="supplier-toggle-btn"
                                    class="flex h-10 w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 transition hover:bg-slate-50 focus:border-teal-500 focus:outline-none">
                                <span id="supplier-label" class="text-slate-400 font-semibold">
                                    @php
                                        $oldSupplierId = old('supplier_id', '');
                                        $oldSupplierName = $oldSupplierId ? ($suppliers->firstWhere('id', (int) $oldSupplierId)?->name ?? '') : '';
                                    @endphp
                                    {{ $oldSupplierName ?: 'Select Vendor...' }}
                                </span>
                                <div class="flex items-center gap-1.5 text-slate-400">
                                    <span id="supplier-clear-btn"
                                          class="p-0.5 text-xs font-black hover:text-rose-600 cursor-pointer {{ $oldSupplierName ? '' : 'hidden' }}"
                                          onclick="clearSupplierSelection(event)">✕</span>
                                    <svg class="h-4 w-4 shrink-0 transition-transform duration-200" id="supplier-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </button>

                            {{-- Dropdown panel --}}
                            <div id="supplier-dropdown-panel"
                                 class="absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                                <div class="border-b border-slate-100 bg-slate-50/50 p-2">
                                    <div class="relative">
                                        <svg class="pointer-events-none absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                        </svg>
                                        <input type="text" id="supplier-search-input"
                                               placeholder="Type to search vendor..."
                                               class="h-8 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-xs font-semibold text-slate-900 placeholder-slate-400 focus:border-teal-500 focus:outline-none"
                                               oninput="filterSupplierOptions(this.value)">
                                    </div>
                                </div>
                                <div class="max-h-48 overflow-y-auto p-1" id="supplier-options-list">
                                    @foreach ($suppliers as $supplier)
                                        <button type="button"
                                                data-id="{{ $supplier->id }}"
                                                data-name="{{ $supplier->name }}"
                                                data-nameLower="{{ strtolower($supplier->name) }}"
                                                onclick="selectSupplierOption(this)"
                                                class="supplier-option flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-bold transition hover:bg-teal-50 hover:text-teal-900 text-slate-800 {{ old('supplier_id') == $supplier->id ? 'bg-teal-600 text-white hover:bg-teal-600 hover:text-white' : '' }}">
                                            <span>{{ $supplier->name }}</span>
                                            <span class="supplier-check text-xs {{ old('supplier_id') == $supplier->id ? '' : 'hidden' }}">✓</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Invoice / Bill Number --}}
                <div>
                    <label for="bill_number" class="block text-xs font-semibold text-slate-700">Invoice / Bill Number</label>
                    <input id="bill_number" type="text" name="bill_number" value="{{ old('bill_number', $grn->bill_number ?? '') }}" placeholder="e.g. INV-10482" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                </div>

                {{-- Received Date / Timestamp --}}
                <div>
                    <label for="received_at" class="block text-xs font-semibold text-slate-700">Received Timestamp <span class="text-rose-500">*</span></label>
                    @if ($isEdit)
                        <input type="text" readonly disabled value="{{ $grn->received_at?->format('d M Y, h:i A') }}" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-100 px-3 text-xs font-bold text-slate-600">
                    @else
                        <input id="received_at" type="datetime-local" name="received_at" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}" required class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                    @endif
                </div>
            </div>

            <div>
                <label for="notes" class="block text-xs font-semibold text-slate-700">Notes / Remarks</label>
                <input id="notes" type="text" name="notes" value="{{ old('notes', $grn->notes ?? '') }}" placeholder="Optional notes..." class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
            </div>
        </div>

        {{-- ── 3. PRODUCT ITEMS VERIFICATION TABLE ───────────────────────── --}}
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs">
            <div class="border-b border-slate-100 px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Bill Products Verification</h3>
                    <p class="text-xs text-slate-500 font-medium">Verify received quantities against pending worklist and enter actual unit prices.</p>
                </div>
                <button type="button" onclick="addProductRow()" class="inline-flex h-9 items-center justify-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
                    + Add Product
                </button>
            </div>

            <div class="overflow-x-auto p-4">
                <table class="w-full text-left border-collapse" id="items-table">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wide bg-slate-50/50">
                            <th class="py-2.5 px-3">Product</th>
                            <th class="py-2.5 px-3 text-right">Pending Qty</th>
                            <th class="py-2.5 px-3 text-right w-32">Bill Qty</th>
                            <th class="py-2.5 px-3 w-24">Unit</th>
                            <th class="py-2.5 px-3 text-right w-32">Rate / Unit (₹)</th>
                            <th class="py-2.5 px-3 text-right w-36">Subtotal (₹)</th>
                            <th class="py-2.5 px-3 text-center w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-800" id="items-body">
                        @forelse ($itemsToRender as $index => $item)
                            <tr class="item-row hover:bg-slate-50/50" data-row-index="{{ $index }}">
                                <td class="py-2.5 px-3">
                                    <select name="items[{{ $index }}][product_id]" required class="item-product-select h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none" onchange="onProductChange(this)">
                                        <option value="">Select Product...</option>
                                        @foreach ($allProducts as $p)
                                            <option value="{{ $p->id }}" @selected((int) ($item['product_id'] ?? 0) === (int) $p->id) data-unit="{{ $p->unit ?? 'kg' }}">
                                                {{ $p->name }} ({{ $p->sku }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-rose-600 item-pending-qty">
                                    {{ isset($item['pending_qty']) && (float) $item['pending_qty'] > 0 ? number_format((float) $item['pending_qty'], 2).' '.($item['received_unit'] ?? 'kg') : '--' }}
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <input type="number" step="0.001" min="0.001" name="items[{{ $index }}][received_qty]" value="{{ $item['received_qty'] ?? '1.0' }}" required class="item-qty-input h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-right text-xs font-black text-slate-900 focus:border-teal-500 focus:outline-none" oninput="calculateTotals()">
                                </td>
                                <td class="py-2.5 px-3">
                                    <input type="text" name="items[{{ $index }}][received_unit]" value="{{ $item['received_unit'] ?? 'kg' }}" required class="item-unit-input h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? '0.00' }}" placeholder="0.00" class="item-price-input h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-right text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none" oninput="calculateTotals()">
                                </td>
                                <td class="py-2.5 px-3 text-right font-black text-slate-900 item-line-total">
                                    ₹0.00
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <button type="button" onclick="removeProductRow(this)" class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr class="item-row hover:bg-slate-50/50" data-row-index="0">
                                <td class="py-2.5 px-3">
                                    <select name="items[0][product_id]" required class="item-product-select h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none" onchange="onProductChange(this)">
                                        <option value="">Select Product...</option>
                                        @foreach ($allProducts as $p)
                                            <option value="{{ $p->id }}" data-unit="{{ $p->unit ?? 'kg' }}">{{ $p->name }} ({{ $p->sku }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-slate-400 item-pending-qty">--</td>
                                <td class="py-2.5 px-3 text-right">
                                    <input type="number" step="0.001" min="0.001" name="items[0][received_qty]" value="1.0" required class="item-qty-input h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-right text-xs font-black text-slate-900 focus:border-teal-500 focus:outline-none" oninput="calculateTotals()">
                                </td>
                                <td class="py-2.5 px-3">
                                    <input type="text" name="items[0][received_unit]" value="kg" required class="item-unit-input h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <input type="number" step="0.01" min="0" name="items[0][unit_price]" value="0.00" placeholder="0.00" class="item-price-input h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-right text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none" oninput="calculateTotals()">
                                </td>
                                <td class="py-2.5 px-3 text-right font-black text-slate-900 item-line-total">₹0.00</td>
                                <td class="py-2.5 px-3 text-center">
                                    <button type="button" onclick="removeProductRow(this)" class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200 bg-slate-50/70 font-bold text-slate-900">
                            <td colspan="5" class="py-3 px-3 text-right text-xs text-slate-500 uppercase tracking-wider">Grand Total Amount:</td>
                            <td class="py-3 px-3 text-right text-sm font-black text-teal-700" id="grand-total">₹0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- ── 4. ACTION CONTROLS ───────────────────────────────────────── --}}
        <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-xs font-semibold text-slate-500 hidden sm:block">
                Saving will update warehouse inventory and execute auto-matching against Business Day {{ $day->business_date->format('d M Y') }}.
            </p>
            <div class="flex items-center gap-3 ml-auto sm:ml-0">
                <a href="{{ route('purchasing.business-days.show', $day->uuid) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button type="submit" class="rounded-xl bg-teal-600 px-5 py-2.5 text-xs font-black text-white hover:bg-teal-700 shadow-sm transition">
                    {{ $isEdit ? 'Save Changes & Rerun Match' : 'Record & Match Purchase Bill' }}
                </button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
    // ── Vanilla JS Supplier Dropdown ─────────────────────────────────────────
    (function () {
        var wrapper, toggleBtn, panel, label, hidden, clearBtn, chevron, searchInput;

        function init() {
            wrapper     = document.getElementById('supplier-dropdown-wrapper');
            if (!wrapper) return; // edit mode — no dropdown
            toggleBtn   = document.getElementById('supplier-toggle-btn');
            panel       = document.getElementById('supplier-dropdown-panel');
            label       = document.getElementById('supplier-label');
            hidden      = document.getElementById('supplier_id_hidden');
            clearBtn    = document.getElementById('supplier-clear-btn');
            chevron     = document.getElementById('supplier-chevron');
            searchInput = document.getElementById('supplier-search-input');

            toggleBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = !panel.classList.contains('hidden');
                closeDropdown();
                if (!isOpen) openDropdown();
            });

            document.addEventListener('click', function (e) {
                if (wrapper && !wrapper.contains(e.target)) closeDropdown();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeDropdown();
            });
        }

        function openDropdown() {
            panel.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';
            chevron.style.color = '#0d9488';
            if (searchInput) {
                searchInput.value = '';
                filterSupplierOptions('');
                setTimeout(function () { searchInput.focus(); }, 50);
            }
        }

        function closeDropdown() {
            if (!panel) return;
            panel.classList.add('hidden');
            if (chevron) {
                chevron.style.transform = '';
                chevron.style.color = '';
            }
        }

        document.addEventListener('DOMContentLoaded', init);

        // expose globals for inline handlers
        window.selectSupplierOption = function (btn) {
            var id   = btn.dataset.id;
            var name = btn.dataset.name;

            hidden.value = id;
            label.textContent = name;
            label.classList.remove('text-slate-400', 'font-semibold');
            label.classList.add('text-slate-900', 'font-bold');

            clearBtn.classList.remove('hidden');

            // highlight selected option
            wrapper.querySelectorAll('.supplier-option').forEach(function (el) {
                el.classList.remove('bg-teal-600', 'text-white');
                el.classList.add('text-slate-800');
                el.querySelector('.supplier-check').classList.add('hidden');
            });
            btn.classList.remove('text-slate-800');
            btn.classList.add('bg-teal-600', 'text-white');
            btn.querySelector('.supplier-check').classList.remove('hidden');

            closeDropdown();
        };

        window.clearSupplierSelection = function (e) {
            e.stopPropagation();
            hidden.value = '';
            label.textContent = 'Select Vendor...';
            label.classList.add('text-slate-400', 'font-semibold');
            label.classList.remove('text-slate-900', 'font-bold');
            clearBtn.classList.add('hidden');
            wrapper.querySelectorAll('.supplier-option').forEach(function (el) {
                el.classList.remove('bg-teal-600', 'text-white');
                el.classList.add('text-slate-800');
                el.querySelector('.supplier-check').classList.add('hidden');
            });
        };

        window.filterSupplierOptions = function (query) {
            var q = (query || '').toLowerCase();
            wrapper.querySelectorAll('.supplier-option').forEach(function (el) {
                var match = !q || el.dataset.namelower.includes(q);
                el.style.display = match ? '' : 'none';
            });
        };
    }());
    // ── End Supplier Dropdown ────────────────────────────────────────────────

    let rowIndex = {{ count($itemsToRender ?? [1]) }};
    const productsData = @json($allProducts);

    function addProductRow() {
        const tbody = document.getElementById('items-body');
        const tr = document.createElement('tr');
        tr.className = 'item-row hover:bg-slate-50/50';
        tr.dataset.rowIndex = rowIndex;

        let optionsHtml = '<option value="">Select Product...</option>';
        productsData.forEach(p => {
            optionsHtml += `<option value="${p.id}" data-unit="${p.unit || 'kg'}">${p.name} (${p.sku})</option>`;
        });

        tr.innerHTML = `
            <td class="py-2.5 px-3">
                <select name="items[${rowIndex}][product_id]" required class="item-product-select h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none" onchange="onProductChange(this)">
                    ${optionsHtml}
                </select>
            </td>
            <td class="py-2.5 px-3 text-right font-bold text-slate-400 item-pending-qty">--</td>
            <td class="py-2.5 px-3 text-right">
                <input type="number" step="0.001" min="0.001" name="items[${rowIndex}][received_qty]" value="1.0" required class="item-qty-input h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-right text-xs font-black text-slate-900 focus:border-teal-500 focus:outline-none" oninput="calculateTotals()">
            </td>
            <td class="py-2.5 px-3">
                <input type="text" name="items[${rowIndex}][received_unit]" value="kg" required class="item-unit-input h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none">
            </td>
            <td class="py-2.5 px-3 text-right">
                <input type="number" step="0.01" min="0" name="items[${rowIndex}][unit_price]" value="0.00" placeholder="0.00" class="item-price-input h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-right text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none" oninput="calculateTotals()">
            </td>
            <td class="py-2.5 px-3 text-right font-black text-slate-900 item-line-total">₹0.00</td>
            <td class="py-2.5 px-3 text-center">
                <button type="button" onclick="removeProductRow(this)" class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        rowIndex++;
        calculateTotals();
    }

    function removeProductRow(btn) {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length <= 1) {
            alert('At least one product item is required.');
            return;
        }
        const tr = btn.closest('tr');
        tr.remove();
        calculateTotals();
    }

    function onProductChange(select) {
        const selectedOption = select.options[select.selectedIndex];
        const unit = selectedOption ? selectedOption.getAttribute('data-unit') : 'kg';
        const tr = select.closest('tr');
        const unitInput = tr.querySelector('.item-unit-input');
        if (unitInput && unit) {
            unitInput.value = unit;
        }
    }

    function calculateTotals() {
        let grandTotal = 0;
        const rows = document.querySelectorAll('.item-row');

        rows.forEach(tr => {
            const qtyInput = tr.querySelector('.item-qty-input');
            const priceInput = tr.querySelector('.item-price-input');
            const lineTotalEl = tr.querySelector('.item-line-total');

            const qty = parseFloat(qtyInput?.value) || 0;
            const price = parseFloat(priceInput?.value) || 0;
            const lineTotal = qty * price;

            if (lineTotalEl) {
                lineTotalEl.textContent = '₹' + lineTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            grandTotal += lineTotal;
        });

        const grandTotalEl = document.getElementById('grand-total');
        if (grandTotalEl) {
            grandTotalEl.textContent = '₹' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        calculateTotals();
    });
</script>
@endpush
