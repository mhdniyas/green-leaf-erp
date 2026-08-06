<x-layouts.app title="Daily Prices">
    <style>
        .daily-price-list {
            width: 100%;
            overflow-x: hidden;
            padding: 0;
            box-sizing: border-box;
        }

        .daily-price-row {
            display: grid;
            grid-template-columns: minmax(105px, 1fr) 56px 84px 82px;
            align-items: center;
            gap: 6px;

            width: 100%;
            min-height: 56px;
            padding: 8px 10px;

            background: #fff;
            border: 1px solid #e5edf5;
            border-radius: 12px;
            box-sizing: border-box;
            overflow: hidden;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .daily-price-row:hover {
            border-color: #cbd5e1;
        }

        .product-cell {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .product-name {
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;

            font-size: 13px;
            font-weight: 700;
            color: #172033;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            text-align: left;
        }

        .product-name:hover {
            text-decoration: underline;
            color: #0f766e;
        }

        .product-unit {
            flex-shrink: 0;
            font-size: 11px;
            font-weight: 600;
            color: #8da0b8;
            white-space: nowrap;
        }

        .previous-price {
            width: 56px;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: right;
        }

        .price-status {
            justify-self: start;
            white-space: nowrap;
            padding: 4px 6px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            max-width: 84px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .price-status.increase {
            color: #047857;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
        }

        .price-status.decrease {
            color: #be123c;
            background: #fff1f2;
            border: 1px solid #fecdd3;
        }

        .price-status.not-set {
            color: #c25b00;
            background: #fff8e6;
            border: 1px solid #f8d98a;
        }

        .price-status.no-change {
            color: #64748b;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }

        .price-input-wrap {
            width: 82px;
            height: 38px;
            display: flex;
            align-items: center;

            border: 1px solid #d9e2ec;
            border-radius: 9px;
            background: #f8fafc;
            overflow: hidden;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }

        .price-input-wrap:focus-within {
            border-color: #0f766e;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1);
        }

        .price-currency {
            padding-left: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            user-select: none;
            flex-shrink: 0;
        }

        .price-input {
            width: 58px;
            min-width: 0;
            padding: 0 7px 0 2px;

            border: 0;
            outline: 0;
            background: transparent;
            text-align: right;

            font-size: 13px;
            font-weight: 700;
            color: #172033;
        }
    </style>

    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-2 lg:max-w-4xl lg:gap-3 lg:px-4 lg:py-3">
        @include('purchasing.purchaser.partials.feedback')

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-4 py-2.5 text-xs font-bold text-emerald-900 shadow-2xs">
                {{ session('success') }}
            </div>
        @endif

        {{-- Header section --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-md">
            <div class="bg-[linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#0f766e_100%)] px-4 py-3 sm:px-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-teal-300">Purchaser Pricing</p>
                        <h1 class="text-base sm:text-lg font-black tracking-tight text-white">Daily Prices</h1>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold text-teal-100 border border-white/10 backdrop-blur-xs">
                            <span class="h-1.5 w-1.5 rounded-full bg-teal-400 animate-pulse"></span>
                            {{ $operationalDate->format('d M Y') }} &middot; {{ $cutoffLabel }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Search section --}}
        <form action="{{ route('purchaser.daily-prices') }}" method="GET" class="rounded-xl border border-slate-200/80 bg-white p-1.5 shadow-2xs">
            <div class="flex min-w-0 gap-2">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input type="search" name="search" value="{{ $searchQuery }}" placeholder="Search product name or SKU..." class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50/80 pl-9 pr-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none focus:ring-3 focus:ring-teal-500/10 transition-all">
                </div>
                <button type="submit" class="inline-flex h-9 shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800 transition-colors shadow-2xs">Search</button>
            </div>
        </form>

        {{-- 4 Fixed Columns Product List --}}
        @if ($products->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-xs font-bold text-slate-500">
                No products found. Try adjusting your search query.
            </div>
        @else
            <div class="daily-price-list flex flex-col gap-1.5">
                {{-- Column Header Title Bar --}}
                <div class="grid grid-cols-[minmax(105px,1fr)_56px_84px_82px] items-center gap-[6px] px-[10px] pb-1 pt-0.5 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400 select-none">
                    <div>PRODUCT</div>
                    <div class="text-right">PREV</div>
                    <div>CHANGE</div>
                    <div class="text-right">TODAY</div>
                </div>

                @foreach ($products as $product)
                    <div id="product-row-{{ $product['id'] }}" class="daily-price-row">
                        
                        {{-- Col 1: Product Cell --}}
                        <div class="product-cell">
                            <button type="button"
                                    class="product-name"
                                    onclick="openProductModal({{ json_encode($product) }})">
                                {{ $product['name'] }}
                            </button>
                            <span class="product-unit">· {{ $product['unit'] }}</span>
                        </div>

                        {{-- Col 2: Compact Previous Price --}}
                        <div class="previous-price" id="row-prev-{{ $product['id'] }}">
                            @if ($product['previous_price'])
                                ₹{{ $product['previous_price'] == (int) $product['previous_price'] ? number_format($product['previous_price'], 0) : number_format($product['previous_price'], 2) }}
                            @else
                                —
                            @endif
                        </div>

                        {{-- Col 3: Price Status / Change Badge --}}
                        <div id="row-badge-{{ $product['id'] }}">
                            @if ($product['price_state'] === 'not_set')
                                <div class="price-status not-set">Not set</div>
                            @elseif ($product['price_state'] === 'no_previous')
                                <div class="price-status increase">₹{{ number_format($product['price_today'], 0) }}</div>
                            @elseif ($product['price_state'] === 'increased')
                                <div class="price-status increase">▲ ₹{{ number_format($product['diff_amount'], 0) }} · {{ $product['diff_percentage'] }}%</div>
                            @elseif ($product['price_state'] === 'decreased')
                                <div class="price-status decrease">▼ ₹{{ number_format(abs($product['diff_amount']), 0) }} · {{ $product['diff_percentage'] }}%</div>
                            @elseif ($product['price_state'] === 'no_change')
                                <div class="price-status no-change">— 0%</div>
                            @endif
                        </div>

                        {{-- Col 4: Price Input Wrap (82px width, 58px input) --}}
                        <div class="price-input-wrap">
                            <span class="price-currency">₹</span>
                            <input type="text"
                                   inputmode="decimal"
                                   id="price-{{ $product['id'] }}"
                                   value="{{ $product['price_today'] ? number_format($product['price_today'], 2, '.', '') : '' }}"
                                   placeholder="—"
                                   oninput="handlePriceInput(this, {{ $product['id'] }})"
                                   class="price-input">
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Details Popup Modal (Matching PRD Spec) --}}
    <div id="product-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs hidden transition-opacity">
        <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white p-5 shadow-2xl border border-slate-100 transition-all max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            {{-- Header --}}
            <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 id="modal-product-name" class="text-base font-black text-slate-900">Product Name</h2>
                    <p id="modal-product-unit" class="text-xs font-semibold text-slate-400 mt-0.5">Purchase Unit: 1 KG</p>
                </div>
                <button type="button" onclick="closeProductModal()" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 space-y-4">
                {{-- Price Summary Grid --}}
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">TODAY'S PRICE</p>
                            <p id="modal-today-price" class="text-base font-black text-slate-900 mt-0.5">₹0.00</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">PREVIOUS PRICE</p>
                            <p id="modal-previous-price" class="text-base font-black text-slate-900 mt-0.5">₹0.00</p>
                        </div>
                    </div>

                    {{-- Change Badge --}}
                    <div class="border-t border-slate-200/60 pt-2.5">
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400 mb-1">CHANGE</p>
                        <div id="modal-change-badge">
                            <!-- Populated dynamically by JS -->
                        </div>
                    </div>
                </div>

                {{-- Recent Price History (Latest 5 Valid Records) --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 space-y-2.5 shadow-2xs">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">RECENT PRICE HISTORY</p>
                    <div id="modal-history-list" class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        <!-- Populated dynamically by JS -->
                    </div>
                </div>

                {{-- Price Input Section inside Popup --}}
                <div class="rounded-2xl bg-[linear-gradient(135deg,_#0f172a_0%,_#111827_60%,_#0f766e_100%)] p-4 text-white shadow-lg space-y-3">
                    <div class="flex items-center justify-between">
                        <label for="modal-price-input" class="block text-[10px] font-black uppercase tracking-wider text-teal-300">SET TODAY'S PRICE</label>
                        <span id="modal-save-status" class="text-[10px] font-bold text-teal-300"></span>
                    </div>
                    <div class="relative flex items-center">
                        <span class="absolute left-3 text-sm font-black text-teal-200">₹</span>
                        <input type="text"
                               inputmode="decimal"
                               id="modal-price-input"
                               placeholder="—"
                               oninput="handleModalPriceInput(this)"
                               class="h-11 w-full rounded-xl border border-white/20 bg-white/10 pl-7 pr-3 text-right text-base font-black text-white focus:bg-white/20 focus:border-teal-300 focus:outline-none focus:ring-3 focus:ring-teal-400/20 transition-all">
                    </div>
                    <button type="button"
                            onclick="triggerModalSave()"
                            class="w-full rounded-xl bg-teal-500 hover:bg-teal-400 text-slate-950 font-black text-xs uppercase tracking-wider py-2.5 transition-all shadow-md cursor-pointer">
                        SAVE PRICE
                    </button>
                </div>

                {{-- Audit Footer --}}
                <div id="modal-audit-info" class="rounded-2xl bg-emerald-50/80 border border-emerald-200/60 p-3 text-xs text-emerald-950 hidden space-y-0.5">
                    <p id="modal-audit-username" class="font-black text-emerald-950">Updated by User</p>
                    <p id="modal-audit-timestamp" class="text-[11px] font-medium text-emerald-800">Timestamp</p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const debounceTimers = {};
        const csrfToken = '{{ csrf_token() }}';
        const updateUrl = '{{ route("purchaser.daily-prices.update") }}';
        let activeModalProduct = null;
        let lastScrollY = 0;

        function handlePriceInput(inputEl, productId) {
            const val = parseFloat(inputEl.value);

            if (debounceTimers[productId]) {
                clearTimeout(debounceTimers[productId]);
            }

            // Sync bi-directionally with modal input
            if (activeModalProduct && activeModalProduct.id === productId) {
                const modalInput = document.getElementById('modal-price-input');
                if (modalInput && modalInput !== document.activeElement) {
                    modalInput.value = inputEl.value;
                }
            }

            if (isNaN(val) || val <= 0) {
                return;
            }

            debounceTimers[productId] = setTimeout(() => {
                savePriceAuto(inputEl, productId, val);
            }, 600);
        }

        function handleModalPriceInput(modalInputEl) {
            if (!activeModalProduct) return;

            const mainInputEl = document.getElementById('price-' + activeModalProduct.id);
            if (mainInputEl) {
                mainInputEl.value = modalInputEl.value;
                handlePriceInput(mainInputEl, activeModalProduct.id);
            }
        }

        function triggerModalSave() {
            if (!activeModalProduct) return;
            const modalInput = document.getElementById('modal-price-input');
            const val = parseFloat(modalInput.value);
            if (isNaN(val) || val <= 0) return;

            const mainInput = document.getElementById('price-' + activeModalProduct.id);
            savePriceAuto(mainInput, activeModalProduct.id, val);
        }

        function savePriceAuto(inputEl, productId, priceValue) {
            const modalStatusEl = document.getElementById('modal-save-status');

            if (modalStatusEl && activeModalProduct && activeModalProduct.id === productId) {
                modalStatusEl.textContent = 'Saving...';
            }

            fetch(updateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    prices: [
                        {
                            product_id: productId,
                            purchase_price: priceValue
                        }
                    ]
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update DOM elements on row
                    updateRowDOM(data);

                    // If modal is open for this product, update modal DOM
                    if (activeModalProduct && activeModalProduct.id === productId) {
                        activeModalProduct.price_today = data.today_price;
                        activeModalProduct.previous_price = data.previous_price;
                        activeModalProduct.diff_amount = data.diff_amount;
                        activeModalProduct.diff_percentage = data.diff_percentage;
                        activeModalProduct.price_state = data.price_state;
                        activeModalProduct.updated_by_name = data.updated_by_name;
                        activeModalProduct.updated_time = data.updated_time;
                        activeModalProduct.updated_at_formatted = data.updated_at_formatted;
                        activeModalProduct.history = data.history;

                        populateModalContent(activeModalProduct);

                        if (modalStatusEl) {
                            modalStatusEl.textContent = '✓ Saved';
                            setTimeout(() => { modalStatusEl.textContent = ''; }, 2000);
                        }
                    }

                    if (inputEl) {
                        const wrap = inputEl.closest('.price-input-wrap');
                        if (wrap) {
                            wrap.style.borderColor = '#10b981';
                            wrap.style.backgroundColor = '#ecfdf5';
                            setTimeout(() => {
                                wrap.style.borderColor = '#d9e2ec';
                                wrap.style.backgroundColor = '#f8fafc';
                            }, 1500);
                        }
                    }
                }
            })
            .catch(err => {
                console.error('Auto-save error:', err);
            });
        }

        function updateRowDOM(data) {
            const pId = data.product_id;
            const prevEl = document.getElementById('row-prev-' + pId);
            const badgeEl = document.getElementById('row-badge-' + pId);

            if (prevEl) {
                if (data.previous_price) {
                    const formatted = data.previous_price % 1 === 0 ? data.previous_price.toFixed(0) : data.previous_price.toFixed(2);
                    prevEl.textContent = `₹${formatted}`;
                } else {
                    prevEl.textContent = `—`;
                }
            }

            if (badgeEl) {
                let bHtml = '';
                if (data.price_state === 'not_set') {
                    bHtml = `<div class="price-status not-set">Not set</div>`;
                } else if (data.price_state === 'no_previous') {
                    bHtml = `<div class="price-status increase">₹${data.today_price.toFixed(0)}</div>`;
                } else if (data.price_state === 'increased') {
                    bHtml = `<div class="price-status increase">▲ ₹${Math.round(data.diff_amount)} · ${data.diff_percentage}%</div>`;
                } else if (data.price_state === 'decreased') {
                    bHtml = `<div class="price-status decrease">▼ ₹${Math.round(Math.abs(data.diff_amount))} · ${data.diff_percentage}%</div>`;
                } else if (data.price_state === 'no_change') {
                    bHtml = `<div class="price-status no-change">— 0%</div>`;
                }
                badgeEl.innerHTML = bHtml;
            }
        }

        function populateModalContent(product) {
            document.getElementById('modal-product-name').textContent = product.name;
            document.getElementById('modal-product-unit').textContent = 'Purchase Unit: 1 ' + product.unit;

            document.getElementById('modal-today-price').textContent = product.price_today ? '₹' + product.price_today.toFixed(2) : 'Not set';
            document.getElementById('modal-previous-price').textContent = product.previous_price ? '₹' + product.previous_price.toFixed(2) : 'No previous price';

            // Change badge in modal
            const changeBadgeEl = document.getElementById('modal-change-badge');
            if (product.price_state === 'increased') {
                changeBadgeEl.innerHTML = `<span class="inline-flex items-center gap-1 font-extrabold text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200 text-xs">▲ ₹${product.diff_amount.toFixed(2)} &middot; ${product.diff_percentage}%</span>`;
            } else if (product.price_state === 'decreased') {
                changeBadgeEl.innerHTML = `<span class="inline-flex items-center gap-1 font-extrabold text-rose-800 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200 text-xs">▼ ₹${Math.abs(product.diff_amount).toFixed(2)} &middot; ${product.diff_percentage}%</span>`;
            } else if (product.price_state === 'no_change') {
                changeBadgeEl.innerHTML = `<span class="font-bold text-slate-500 text-xs">&mdash; No change</span>`;
            } else if (product.price_state === 'no_previous') {
                changeBadgeEl.innerHTML = `<span class="font-semibold text-slate-400 text-xs">No previous price available</span>`;
            } else {
                changeBadgeEl.innerHTML = `<span class="font-bold text-amber-600 text-xs">Price not set</span>`;
            }

            // Sync main input value to modal input
            const mainInput = document.getElementById('price-' + product.id);
            const modalInput = document.getElementById('modal-price-input');
            if (mainInput && modalInput) {
                modalInput.value = mainInput.value;
            }

            // Populate recent 5 price history
            const historyListEl = document.getElementById('modal-history-list');
            if (product.history && product.history.length > 0) {
                let hHtml = '';
                product.history.forEach(item => {
                    hHtml += `
                        <div class="flex items-center justify-between py-2">
                            <span class="font-bold text-slate-600">${item.date}</span>
                            <span class="font-black text-slate-900">₹${item.price.toFixed(2)}</span>
                            <span class="font-semibold text-slate-500">${item.updated_by}</span>
                        </div>
                    `;
                });
                historyListEl.innerHTML = hHtml;
            } else {
                historyListEl.innerHTML = `<div class="py-3 text-center text-slate-400 italic">No previous price history available</div>`;
            }

            // Audit Info Footer
            const auditInfo = document.getElementById('modal-audit-info');
            if (product.updated_by_name) {
                document.getElementById('modal-audit-username').textContent = 'Updated by ' + product.updated_by_name;
                document.getElementById('modal-audit-timestamp').textContent = product.updated_at_formatted || (product.updated_time || '');
                auditInfo.classList.remove('hidden');
            } else {
                auditInfo.classList.add('hidden');
            }
        }

        function openProductModal(product) {
            activeModalProduct = product;
            lastScrollY = window.scrollY;

            populateModalContent(product);

            const modal = document.getElementById('product-modal');
            modal.classList.remove('hidden');
        }

        function closeProductModal() {
            activeModalProduct = null;
            const modal = document.getElementById('product-modal');
            modal.classList.add('hidden');
            // Return purchaser to exact scroll position
            window.scrollTo(0, lastScrollY);
        }

        // Close modal on backdrop click
        document.getElementById('product-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductModal();
            }
        });
    </script>
    @endpush
</x-layouts.app>
