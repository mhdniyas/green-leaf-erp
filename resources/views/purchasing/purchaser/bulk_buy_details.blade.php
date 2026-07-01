<x-layouts.app title="Bulk Purchase Details" :show-mobile-nav="false">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_16px_36px_rgba(15,23,42,0.18)] lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.28),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#134e4a_100%)] px-4 py-4 sm:px-5 lg:px-4 lg:py-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-teal-200 sm:text-[11px] sm:tracking-[0.22em]">Purchaser Flow</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight sm:mt-2 sm:text-2xl">Bulk Purchase (Step 2)</h1>
                        <p class="mt-2 max-w-2xl text-sm font-medium text-slate-200">Enter quantities and prices for each selected product, choose a cart, and submit.</p>
                    </div>
                    <div class="shrink-0">
                        <span class="rounded-xl bg-white/10 px-3.5 py-2 text-sm font-bold text-white block lg:rounded-2xl">
                            {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <form action="{{ route('purchaser.carts.bulk-store') }}" method="POST" class="space-y-4 pb-24 sm:pb-28">
            @csrf
            <input type="hidden" name="business_date" value="{{ $date }}">

            {{-- Cart selector card --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
                <label class="block text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Select Target Cart</label>
                <div class="relative custom-select-container w-full max-w-md">
                    <button type="button" class="custom-select-trigger flex h-11 w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-left text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                        <span class="custom-select-label truncate">New cart</span>
                        <svg class="h-4 w-4 shrink-0 text-slate-500 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <input type="hidden" name="cart_id" value="" class="custom-select-input">
                    <div class="custom-select-options hidden absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                        <button type="button" data-value="" class="custom-select-option flex w-full items-center justify-between px-4 py-2.5 text-left text-sm font-bold text-slate-900 hover:bg-slate-100">
                            <span>New cart</span>
                            <span class="checkmark text-teal-600">✓</span>
                        </button>
                        @foreach ($draftCarts as $cart)
                            <button type="button" data-value="{{ $cart->id }}" data-supplier="{{ $cart->supplier?->name ?: '' }}" class="custom-select-option flex w-full items-center justify-between px-4 py-2.5 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                <span>{{ $cart->cart_number }}</span>
                                <span class="checkmark hidden text-teal-600">✓</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Products list --}}
            <div class="space-y-4">
                @foreach ($dailySummary as $summary)
                    @php
                        $step = $summary['unit'] === 'kg' ? '0.5' : '1';
                        $defaultQty = max(0.01, (float) $summary['remaining_qty'] - (float) $summary['draft_qty']);
                    @endphp
                    <input type="hidden" name="product_ids[]" value="{{ $summary['product_id'] }}">

                    <div class="product-row rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5" data-product-id="{{ $summary['product_id'] }}">
                        {{-- Hidden inputs for form submission --}}
                        <input type="hidden" name="items[{{ $summary['product_id'] }}][quantity]" id="submit-qty-{{ $summary['product_id'] }}" value="{{ number_format($defaultQty, 2, '.', '') }}">
                        <input type="hidden" name="items[{{ $summary['product_id'] }}][unit_price]" id="submit-price-{{ $summary['product_id'] }}" value="0.00">
                        <input type="hidden" id="basis-{{ $summary['product_id'] }}" value="kg">
                        <input type="hidden" id="unit-{{ $summary['product_id'] }}" value="{{ $summary['unit'] }}">

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            {{-- Info --}}
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="min-w-0 break-words font-black text-slate-955 text-base">{{ $summary['product_name'] }}</h3>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-600">{{ $summary['category_name'] ?: 'Other' }}</span>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold text-slate-500">
                                    <span>Need: {{ number_format($summary['total_approved_qty'], 1) }} {{ $summary['unit'] }}</span>
                                    <span>Bought: {{ number_format($summary['bought_qty'], 1) }}</span>
                                    <span>In Cart: {{ number_format($summary['draft_qty'], 1) }}</span>
                                </div>
                                <p id="prev-price-hint-{{ $summary['product_id'] }}" class="mt-2 text-[11px] font-black text-amber-700">
                                    @php
                                        $fallbackHint = (float) ($bulkFallbackPriceHints[$summary['product_id']] ?? 0);
                                    @endphp
                                    {{ $fallbackHint > 0 ? 'Last purchase ₹'.number_format($fallbackHint, 2) : 'No recent purchase price yet' }}
                                </p>

                                @if ($summary['unit'] === 'kg')
                                    <div class="mt-3">
                                        <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-0.5 w-max">
                                            <button type="button" id="basis-kg-btn-{{ $summary['product_id'] }}" onclick="setRowBasis({{ $summary['product_id'] }}, 'kg')" class="rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all bg-white text-slate-955 shadow-xs">
                                                Per Kg
                                            </button>
                                            <button type="button" id="basis-box-btn-{{ $summary['product_id'] }}" onclick="setRowBasis({{ $summary['product_id'] }}, 'box')" class="rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all text-slate-600 hover:bg-slate-50">
                                                Per Box
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Inputs --}}
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                {{-- Kg Inputs --}}
                                <div id="kg-inputs-{{ $summary['product_id'] }}" class="grid grid-cols-2 gap-2 sm:w-72">
                                    {{-- Qty --}}
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Qty ({{ $summary['unit'] }})</label>
                                        <input type="number" 
                                               step="any" 
                                               min="0.01" 
                                               id="qty-kg-{{ $summary['product_id'] }}"
                                               value="{{ number_format($defaultQty, 2, '.', '') }}" 
                                               class="qty-input-kg w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                                    </div>
                                    {{-- Price --}}
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Price (Per {{ $summary['unit'] }})</label>
                                        <input type="number" 
                                               step="0.01" 
                                               min="0" 
                                               id="price-kg-{{ $summary['product_id'] }}"
                                               value="" 
                                               placeholder="0.00" 
                                               class="price-input-kg w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                                    </div>
                                </div>

                                {{-- Box Inputs --}}
                                @if ($summary['unit'] === 'kg')
                                    <div id="box-inputs-{{ $summary['product_id'] }}" class="hidden grid grid-cols-3 gap-2 sm:w-80">
                                        {{-- Boxes --}}
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Boxes</label>
                                            <input type="number" 
                                                   step="1" 
                                                   min="1" 
                                                   id="qty-box-{{ $summary['product_id'] }}"
                                                   value="1" 
                                                   class="qty-input-box w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                                        </div>
                                        {{-- kg/Box Conversion --}}
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400">kg/Box</label>
                                            <input type="number" 
                                                   step="0.1" 
                                                   min="0.1" 
                                                   id="conv-box-{{ $summary['product_id'] }}"
                                                   value="15" 
                                                   class="conv-input-box w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                                        </div>
                                        {{-- Price --}}
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Price/Box</label>
                                            <input type="number" 
                                                   step="0.01" 
                                                   min="0" 
                                                   id="price-box-{{ $summary['product_id'] }}"
                                                   value="" 
                                                   placeholder="0.00" 
                                                   class="price-input-box w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                                        </div>
                                    </div>
                                @endif

                                {{-- Row Total --}}
                                <div class="flex items-center justify-between border-t border-slate-100 pt-2.5 sm:border-0 sm:pt-0 sm:w-28 sm:justify-end">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 sm:hidden">Total</span>
                                    <span class="row-total text-sm font-black text-slate-900">₹ 0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Sticky bottom bar --}}
            <div class="sticky bottom-3 z-40 mt-4 px-1 sm:px-0">
                <div class="mx-auto flex max-w-4xl flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-[0_12px_28px_rgba(15,23,42,0.12)] backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:rounded-[1.75rem] sm:px-4 sm:py-3">
                    <div class="min-w-0">
                        <p class="text-xs font-black text-slate-500 uppercase">Estimated Grand Total</p>
                        <p class="text-lg font-black text-teal-600" id="grand-total-display">₹ 0.00</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center sm:justify-end">
                        <button type="button" onclick="history.back()" class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-100 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-200 sm:w-auto">
                            Back
                        </button>
                        <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-teal-600 px-5 text-sm font-black text-white transition hover:bg-teal-500 sm:w-auto">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bulkPriceHintsByCart = @json($bulkPriceHintsByCart);
            const bulkFallbackPriceHints = @json($bulkFallbackPriceHints);

            function updateBulkPriceHints(cartId) {
                document.querySelectorAll('.product-row').forEach((row) => {
                    const productId = row.getAttribute('data-product-id');
                    const hintNode = document.getElementById(`prev-price-hint-${productId}`);
                    if (!hintNode) {
                        return;
                    }

                    const cartHint = cartId && bulkPriceHintsByCart[cartId] ? Number(bulkPriceHintsByCart[cartId][productId] || 0) : 0;
                    const fallbackHint = Number(bulkFallbackPriceHints[productId] || 0);
                    const hintValue = cartHint > 0 ? cartHint : fallbackHint;

                    hintNode.textContent = hintValue > 0
                        ? `${cartHint > 0 ? 'Vendor last' : 'Last purchase'} ₹${hintValue.toFixed(2)}`
                        : 'No recent purchase price yet';
                });
            }

            // Dropdown Toggle
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('.custom-select-trigger');
                if (trigger) {
                    const container = trigger.closest('.custom-select-container');
                    const optionsList = container.querySelector('.custom-select-options');
                    
                    document.querySelectorAll('.custom-select-options').forEach(el => {
                        if (el !== optionsList) el.classList.add('hidden');
                    });
                    
                    optionsList.classList.toggle('hidden');
                    
                    const arrow = trigger.querySelector('svg');
                    if (arrow) {
                        arrow.classList.toggle('rotate-180', !optionsList.classList.contains('hidden'));
                    }
                    return;
                }

                const option = e.target.closest('.custom-select-option');
                if (option) {
                    const container = option.closest('.custom-select-container');
                    const input = container.querySelector('.custom-select-input');
                    const label = container.querySelector('.custom-select-label');
                    const optionsList = container.querySelector('.custom-select-options');
                    
                    const val = option.getAttribute('data-value');
                    const text = option.querySelector('span').textContent;
                    
                    input.value = val;
                    label.textContent = text;
                    updateBulkPriceHints(val);
                    
                    container.querySelectorAll('.custom-select-option').forEach(opt => {
                        const check = opt.querySelector('.checkmark');
                        if (opt === option) {
                            check.classList.remove('hidden');
                            opt.classList.add('font-bold');
                            opt.classList.remove('font-semibold', 'text-slate-700');
                        } else {
                            check.classList.add('hidden');
                            opt.classList.remove('font-bold');
                            opt.classList.add('font-semibold', 'text-slate-700');
                        }
                    });
                    
                    optionsList.classList.add('hidden');
                    const arrow = container.querySelector('.custom-select-trigger svg');
                    if (arrow) arrow.classList.remove('rotate-180');
                    return;
                }

                if (!e.target.closest('.custom-select-container')) {
                    document.querySelectorAll('.custom-select-options').forEach(el => {
                        el.classList.add('hidden');
                        const container = el.closest('.custom-select-container');
                        const arrow = container.querySelector('.custom-select-trigger svg');
                        if (arrow) arrow.classList.remove('rotate-180');
                    });
                }
            });

            // Bind Row Basis / Calculations Functions Globals
            window.setRowBasis = function(productId, basis) {
                const basisInput = document.getElementById(`basis-${productId}`);
                if (!basisInput) return;
                basisInput.value = basis;

                const btnKg = document.getElementById(`basis-kg-btn-${productId}`);
                const btnBox = document.getElementById(`basis-box-btn-${productId}`);
                const kgInputs = document.getElementById(`kg-inputs-${productId}`);
                const boxInputs = document.getElementById(`box-inputs-${productId}`);

                if (basis === 'box') {
                    if (btnKg) btnKg.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all text-slate-600 hover:bg-slate-50';
                    if (btnBox) btnBox.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all bg-white text-slate-955 shadow-xs';
                    if (kgInputs) kgInputs.classList.add('hidden');
                    if (boxInputs) boxInputs.classList.remove('hidden');
                } else {
                    if (btnBox) btnBox.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all text-slate-600 hover:bg-slate-50';
                    if (btnKg) btnKg.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all bg-white text-slate-955 shadow-xs';
                    if (boxInputs) boxInputs.classList.add('hidden');
                    if (kgInputs) kgInputs.classList.remove('hidden');
                }

                calculateGrandTotal();
            }

            window.calculateRow = function(productId) {
                const basisInput = document.getElementById(`basis-${productId}`);
                if (!basisInput) return 0;
                const basis = basisInput.value;

                const submitQty = document.getElementById(`submit-qty-${productId}`);
                const submitPrice = document.getElementById(`submit-price-${productId}`);
                const rowTotalDisplay = document.querySelector(`.product-row[data-product-id="${productId}"] .row-total`);

                let qty = 0;
                let price = 0;
                let rowTotal = 0;

                if (basis === 'box') {
                    const qtyBox = parseFloat(document.getElementById(`qty-box-${productId}`).value) || 0;
                    const convBox = parseFloat(document.getElementById(`conv-box-${productId}`).value) || 1;
                    const priceBox = parseFloat(document.getElementById(`price-box-${productId}`).value) || 0;

                    qty = qtyBox * convBox;
                    price = convBox > 0 ? (priceBox / convBox) : 0;
                    rowTotal = qtyBox * priceBox;
                } else {
                    const qtyKgInput = document.getElementById(`qty-kg-${productId}`);
                    const priceKgInput = document.getElementById(`price-kg-${productId}`);
                    qty = parseFloat(qtyKgInput ? qtyKgInput.value : 0) || 0;
                    price = parseFloat(priceKgInput ? priceKgInput.value : 0) || 0;
                    rowTotal = qty * price;
                }

                if (submitQty) submitQty.value = qty.toFixed(3);
                if (submitPrice) submitPrice.value = price.toFixed(4);

                if (rowTotalDisplay) {
                    rowTotalDisplay.textContent = '₹ ' + rowTotal.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }

                return rowTotal;
            }

            window.calculateGrandTotal = function() {
                let grandTotal = 0;
                const rows = document.querySelectorAll('.product-row');
                rows.forEach(row => {
                    const productId = row.getAttribute('data-product-id');
                    grandTotal += calculateRow(productId);
                });

                const grandTotalDisplay = document.getElementById('grand-total-display');
                if (grandTotalDisplay) {
                    grandTotalDisplay.textContent = '₹ ' + grandTotal.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }

            // Bind input event listeners for calculations
            const rows = document.querySelectorAll('.product-row');
            rows.forEach(row => {
                const productId = row.getAttribute('data-product-id');
                
                const qtyKg = document.getElementById(`qty-kg-${productId}`);
                const priceKg = document.getElementById(`price-kg-${productId}`);
                if (qtyKg) qtyKg.addEventListener('input', calculateGrandTotal);
                if (priceKg) priceKg.addEventListener('input', calculateGrandTotal);

                const qtyBox = document.getElementById(`qty-box-${productId}`);
                const convBox = document.getElementById(`conv-box-${productId}`);
                const priceBox = document.getElementById(`price-box-${productId}`);
                if (qtyBox) qtyBox.addEventListener('input', calculateGrandTotal);
                if (convBox) convBox.addEventListener('input', calculateGrandTotal);
                if (priceBox) priceBox.addEventListener('input', calculateGrandTotal);
            });

            calculateGrandTotal();
            updateBulkPriceHints(document.querySelector('.custom-select-input')?.value || '');
        });
    </script>
</x-layouts.app>
