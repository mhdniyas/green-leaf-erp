<x-layouts.app title="Purchaser Daily">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)]">
            <div class="bg-[linear-gradient(135deg,_#0f172a_0%,_#111827_58%,_#134e4a_100%)] px-3 py-2.5 sm:px-4">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                        <div class="min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-[0.16em] text-teal-200">{{ $purchaseGrade === 'B' ? 'Grade B Purchaser Flow' : 'Purchaser Flow' }}</p>
                            <h1 class="text-base font-black tracking-tight sm:text-lg">{{ $purchaseGrade === 'B' ? 'B Grade Purchase' : 'Daily demand' }}</h1>
                        </div>
                        @if ($purchaseGrade === 'A')
                        <div class="grid grid-cols-4 gap-1.5 text-center">
                            <div class="rounded-lg bg-white/10 px-2 py-1">
                                <p class="text-[8px] font-black uppercase text-slate-300">Need</p>
                                <p class="text-sm font-black">{{ number_format($dailyFulfillment['approved_qty'], 0) }}</p>
                            </div>
                            <div class="rounded-lg bg-amber-400/15 px-2 py-1">
                                <p class="text-[8px] font-black uppercase text-amber-100">Bought</p>
                                <p class="text-sm font-black text-amber-200">{{ number_format($dailyFulfillment['bought_qty'], 0) }}</p>
                            </div>
                            <div class="rounded-lg bg-emerald-400/15 px-2 py-1">
                                <p class="text-[8px] font-black uppercase text-emerald-100">Left</p>
                                <p class="text-sm font-black text-emerald-200">{{ number_format($dailyFulfillment['remaining_qty'], 0) }}</p>
                            </div>
                            <div class="rounded-lg bg-cyan-400/15 px-2 py-1">
                                <p class="text-[8px] font-black uppercase text-cyan-100">Carts</p>
                                <p class="text-sm font-black text-cyan-200">{{ $dailyFulfillment['draft_carts'] }}</p>
                            </div>
                        </div>
                        @else
                            <span class="rounded-full bg-blue-400/15 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-blue-100">Direct add-on purchase</span>
                        @endif
                    </div>
                    <form action="{{ $purchaseGrade === 'B' ? route('purchaser.b-grade') : route('purchaser.daily') }}" method="GET" class="w-full lg:w-auto">
                        <input id="business-date" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-9 w-full rounded-lg border border-white/10 bg-white/10 px-3 text-xs font-bold text-white outline-none ring-0 lg:w-40">
                    </form>
                </div>
                <details class="mt-1 text-xs text-slate-300">
                    <summary class="cursor-pointer list-none text-[10px] font-black uppercase tracking-[0.14em] text-teal-100">Details</summary>
                    <p class="mt-1 leading-5">Select today&apos;s products, add them into carts, and move from market demand to purchase.</p>
                </details>
            </div>
        </section>

        {{-- Two-column layout: product list + aside --}}
        <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start">

            {{-- LEFT: product list --}}
            <div class="min-w-0 flex-1 space-y-4">
                <form action="{{ $purchaseGrade === 'B' ? route('purchaser.b-grade') : route('purchaser.daily') }}" method="GET" id="purchaser-daily-filter-form" class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm lg:p-3">
                    <input type="hidden" name="date" value="{{ $date }}">
                    <input type="hidden" name="chip" id="daily-chip-input" value="{{ $selectedChip }}">
                    <div class="flex min-w-0 gap-2">
                        <input type="search" name="search" value="{{ $search }}" placeholder="Search product..." class="h-10 min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                        <div class="relative w-32 shrink-0 sm:w-44">
                            <select onchange="document.getElementById('daily-chip-input').value=this.value; this.form.submit()" class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-8 text-xs font-black text-slate-700 focus:border-teal-500 focus:bg-white focus:outline-none">
                                @foreach ($quickFilters as $filter)
                                    <option value="{{ $filter }}" {{ $selectedChip === $filter ? 'selected' : '' }}>
                                        {{ $filter }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        <button type="submit" class="inline-flex h-10 shrink-0 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-black text-white sm:px-5">Search</button>
                    </div>
                </form>

                <div class="flex min-w-0 flex-col gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Daily queue</p>
                        <p class="text-xs font-semibold text-slate-600">{{ $dailySummary->count() }} products · {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
                    </div>
                    <div class="grid w-full shrink-0 grid-cols-3 gap-2 sm:w-auto">
                        <a href="{{ $purchaseGrade === 'B' ? route('purchaser.b-grade', ['date' => $date]) : route('purchaser.add-ons.create', ['date' => $date]) }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-black text-white sm:w-24">
                            Add-on
                        </a>
                        <a href="{{ route('purchaser.bulk-buy', ['date' => $date, 'purchase_grade' => $purchaseGrade]) }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-teal-600 px-3 text-xs font-black text-white sm:w-24">
                            Bulk
                        </a>
                        <a href="{{ route('purchaser.daily.share', ['date' => $date, 'purchase_grade' => $purchaseGrade]) }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-emerald-600 px-3 text-xs font-black text-white sm:w-24">
                            Share
                        </a>
                    </div>
                </div>

                @php
                    $pendingSummary = $purchaseGrade === 'B'
                        ? $dailySummary
                        : $dailySummary->filter(function($summary) {
                            return ((float) $summary['remaining_qty'] - (float) $summary['draft_qty']) > 0;
                        });
                    $completedSummary = $purchaseGrade === 'B'
                        ? collect()
                        : $dailySummary->filter(function($summary) {
                            return ((float) $summary['remaining_qty'] - (float) $summary['draft_qty']) <= 0;
                        });
                @endphp

                <div class="flex gap-2 rounded-xl bg-slate-100 p-1">
                    <button type="button" id="tab-pending" onclick="switchDailyTab('pending')" class="flex-1 rounded-lg py-2.5 text-center text-xs font-black transition-all">
                        Pending Demand ({{ $pendingSummary->count() }})
                    </button>
                    <button type="button" id="tab-completed" onclick="switchDailyTab('completed')" class="flex-1 rounded-lg py-2.5 text-center text-xs font-black transition-all">
                        Completed / In Cart ({{ $completedSummary->count() }})
                    </button>
                </div>

                <div class="space-y-6">
                    <div id="section-pending" class="space-y-2 hidden">
                        @forelse ($pendingSummary as $summary)
                            @include('purchasing.purchaser.partials.daily_item', ['summary' => $summary, 'currentDate' => $date])
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4 lg:py-12">
                                No pending demand for this date.
                            </div>
                        @endforelse
                    </div>

                    <div id="section-completed" class="space-y-2 hidden">
                        @forelse ($completedSummary as $summary)
                            <div class="opacity-80 hover:opacity-100 transition-opacity duration-200">
                                @include('purchasing.purchaser.partials.daily_item', ['summary' => $summary, 'currentDate' => $date])
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4 lg:py-12">
                                No completed items for this date.
                            </div>
                        @endforelse
                    </div>

                    @if ($dailySummary->isEmpty())
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4 lg:py-12">
                            No demand for this date. Try a different date or create an add-on.
                        </div>
                    @endif
                </div>
            </div>

            {{-- RIGHT: aside (hidden on mobile, shown on lg+) --}}
            <aside class="w-full space-y-4 lg:w-88 lg:shrink-0">
                <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Active carts</p>
                    <div class="mt-3 space-y-3">
                        @forelse ($draftCarts as $cart)
                            <a href="{{ route('purchaser.vendors', ['date' => $date, 'purchase_grade' => $purchaseGrade]) }}" class="block rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 lg:rounded-2xl lg:px-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-black text-slate-900">{{ $cart->cart_number }}</p>
                                    <span class="rounded-full {{ $cart->whatsapp_sent_at ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-700' }} px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em]">
                                        {{ $cart->whatsapp_sent_at ? 'WhatsApp Sent' : 'Draft' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-slate-600">{{ $cart->items->count() }} items</p>
                            </a>
                        @empty
                            <p class="rounded-xl bg-slate-50 px-3 py-4 text-sm font-semibold text-slate-500 lg:rounded-2xl lg:px-4">No draft carts yet.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            setDailyPurchaseGrade(@js($purchaseGrade));
            // Toggle dropdown on click of trigger
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('.custom-select-trigger');
                if (trigger) {
                    const container = trigger.closest('.custom-select-container');
                    const optionsList = container.querySelector('.custom-select-options');
                    
                    // Close all other dropdowns first
                    document.querySelectorAll('.custom-select-options').forEach(el => {
                        if (el !== optionsList) el.classList.add('hidden');
                    });
                    
                    optionsList.classList.toggle('hidden');
                    
                    // Rotate arrow
                    const arrow = trigger.querySelector('svg');
                    if (arrow) {
                        if (optionsList.classList.contains('hidden')) {
                            arrow.classList.remove('rotate-180');
                        } else {
                            arrow.classList.add('rotate-180');
                        }
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
                    
                    // Update value & label
                    input.value = val;
                    label.textContent = text;
                    
                    // Update checkmarks
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
                    
                    // Reset arrow rotation
                    const triggerBtn = container.querySelector('.custom-select-trigger');
                    const arrow = triggerBtn ? triggerBtn.querySelector('svg') : null;
                    if (arrow) arrow.classList.remove('rotate-180');
                    return;
                }

                // Clicked outside, close all dropdowns & reset arrows
                if (!e.target.closest('.custom-select-container')) {
                    document.querySelectorAll('.custom-select-options').forEach(el => {
                        el.classList.add('hidden');
                        const container = el.closest('.custom-select-container');
                        const triggerBtn = container.querySelector('.custom-select-trigger');
                        const arrow = triggerBtn ? triggerBtn.querySelector('svg') : null;
                        if (arrow) arrow.classList.remove('rotate-180');
                    });
                }
            });
        });
    </script>

    {{-- Add to Cart Modal --}}
    <div id="add-to-cart-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden" onclick="if(event.target === this) closeAddToCartModal()">
        <div class="relative w-full max-w-xs rounded-2xl border border-slate-200 bg-white p-4 shadow-xl flex flex-col">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 mb-3">
                <div class="min-w-0 flex-1">
                    <h3 class="text-xs font-black text-slate-950 truncate" id="add-to-cart-product-name">Product Name</h3>
                    <p class="text-[9px] font-semibold text-slate-500 mt-0.5 flex flex-wrap items-center gap-1">
                        <span>Add to draft cart</span>
                        <span id="add-to-cart-in-cart-label" class="text-amber-600 font-bold hidden"></span>
                    </p>
                </div>
                <button type="button" onclick="closeAddToCartModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form action="{{ route('purchaser.cart-items.store') }}" method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="business_date" value="{{ $date }}">
                <input type="hidden" id="add-to-cart-product-id" name="product_id" value="">
                <input type="hidden" id="add-to-cart-purchase-source" name="purchase_source" value="shop_order">
                <input type="hidden" name="return_to" value="daily">
                <input type="hidden" name="chip" value="{{ $selectedChip }}">
                <input type="hidden" name="search" value="{{ $search }}">

                <div class="space-y-1">
                    <input id="add-to-cart-purchase-grade" type="hidden" name="purchase_grade" value="{{ $purchaseGrade }}">
                </div>

                {{-- Cart Selector --}}
                <div class="space-y-1">
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Select Cart</label>
                    <div class="relative custom-select-container w-full min-w-0">
                        <button type="button" class="custom-select-trigger flex h-8 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-left text-[10px] font-semibold text-slate-955 focus:outline-none">
                            <span class="custom-select-label truncate">New cart</span>
                            <svg class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <input type="hidden" name="cart_id" value="" class="custom-select-input">
                        <div class="custom-select-options hidden absolute left-0 right-0 z-50 mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                            <button type="button" data-value="" class="custom-select-option flex w-full items-center justify-between px-3 py-1.5 text-left text-xs font-bold text-slate-950 hover:bg-slate-50">
                                <span>New cart</span>
                                <span class="checkmark text-teal-600">✓</span>
                            </button>
                            @foreach ($draftCarts as $cart)
                                <button type="button" data-value="{{ $cart->id }}" data-grade="{{ $cart->purchase_grade ?? 'A' }}" class="custom-select-option flex w-full items-center justify-between px-3 py-1.5 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    <span>{{ $cart->cart_number }} · Grade {{ $cart->purchase_grade ?? 'A' }}</span>
                                    <span class="checkmark hidden text-teal-600">✓</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Price Basis Selector --}}
                <div id="modal-basis-container" class="space-y-1 hidden">
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Price Basis</label>
                    <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-0.5 w-max">
                        <button type="button" id="modal-basis-kg-btn" onclick="setModalBasis('kg')" class="rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all bg-white text-slate-955 shadow-xs">
                            Per Kg
                        </button>
                        <button type="button" id="modal-basis-box-btn" onclick="setModalBasis('box')" class="rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all text-slate-600 hover:bg-slate-50">
                            Per Box
                        </button>
                    </div>
                </div>

                {{-- Quantity input --}}
                <div class="space-y-1">
                    <label for="add-to-cart-quantity" class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        <span id="add-to-cart-qty-label">Quantity (kg)</span> <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="add-to-cart-quantity" name="quantity" required class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-955 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>

                {{-- Box Conversion input --}}
                <div id="modal-conversion-container" class="space-y-1 hidden">
                    <label for="add-to-cart-conversion" class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        kg/Box <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" step="0.1" min="0.1" id="add-to-cart-conversion" value="15" class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-955 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>

                {{-- Price input --}}
                <div class="space-y-1">
                    <label for="add-to-cart-price" class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        <span id="add-to-cart-price-label">Price (Per kg)</span>
                    </label>
                    <input type="number" step="0.01" min="0.01" id="add-to-cart-price" name="unit_price" class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-955 focus:border-teal-500 focus:bg-white focus:outline-none" placeholder="Price">
                </div>

                {{-- Calculated Total --}}
                <div class="space-y-1">
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        Calculated Total
                    </label>
                    <div id="add-to-cart-total-display" class="flex h-8 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-100 px-2.5 text-xs font-bold text-slate-700">
                        ₹ 0.00
                    </div>
                </div>

                <div class="pt-1">
                    <button type="submit" class="w-full h-8 rounded-lg bg-teal-600 text-xs font-black text-white hover:bg-teal-500 shadow-sm transition-colors">
                        Add to Cart
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentModalBasis = 'kg';
        let currentProductUnit = 'kg';

        function setModalBasis(basis) {
            currentModalBasis = basis;
            const btnKg = document.getElementById('modal-basis-kg-btn');
            const btnBox = document.getElementById('modal-basis-box-btn');
            const qtyLabel = document.getElementById('add-to-cart-qty-label');
            const priceLabel = document.getElementById('add-to-cart-price-label');
            const convContainer = document.getElementById('modal-conversion-container');
            const qtyInput = document.getElementById('add-to-cart-quantity');

            if (basis === 'box') {
                btnKg.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all text-slate-600 hover:bg-slate-50';
                btnBox.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all bg-white text-slate-955 shadow-xs';
                qtyLabel.textContent = 'Boxes';
                priceLabel.textContent = 'Price (Per box)';
                convContainer.classList.remove('hidden');
                
                qtyInput.step = 'any';
                qtyInput.min = '0.01';
            } else {
                btnBox.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all text-slate-600 hover:bg-slate-50';
                btnKg.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all bg-white text-slate-955 shadow-xs';
                qtyLabel.textContent = `Quantity (${currentProductUnit})`;
                priceLabel.textContent = `Price (Per ${currentProductUnit})`;
                convContainer.classList.add('hidden');
                
                qtyInput.step = 'any';
                qtyInput.min = '0.01';
            }
            updateModalTotalPrice();
        }

        function updateModalTotalPrice() {
            const quantity = parseFloat(document.getElementById('add-to-cart-quantity').value) || 0;
            const price = parseFloat(document.getElementById('add-to-cart-price').value) || 0;
            const total = quantity * price;
            document.getElementById('add-to-cart-total-display').textContent = '₹ ' + total.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        document.getElementById('add-to-cart-quantity').addEventListener('input', updateModalTotalPrice);
        document.getElementById('add-to-cart-price').addEventListener('input', updateModalTotalPrice);
        document.getElementById('add-to-cart-conversion').addEventListener('input', updateModalTotalPrice);

        // Bind form conversion on submit
        document.addEventListener('DOMContentLoaded', () => {
            const modalForm = document.querySelector('#add-to-cart-modal form');
            if (modalForm) {
                modalForm.addEventListener('submit', (e) => {
                    if (currentProductUnit === 'kg' && currentModalBasis === 'box') {
                        const qtyInput = document.getElementById('add-to-cart-quantity');
                        const priceInput = document.getElementById('add-to-cart-price');
                        const convInput = document.getElementById('add-to-cart-conversion');

                        const boxes = parseFloat(qtyInput.value) || 0;
                        const kgPerBox = parseFloat(convInput.value) || 1;
                        const pricePerBox = parseFloat(priceInput.value) || 0;

                        const submitQty = boxes * kgPerBox;
                        const submitPrice = kgPerBox > 0 ? (pricePerBox / kgPerBox) : 0;

                        qtyInput.value = submitQty.toFixed(3);
                        priceInput.value = submitPrice.toFixed(4);
                    }
                });
            }
        });

        function openAddToCartModal(productId, productName, productUnit, remainingQty, draftQty, step, draftPurchasers, purchaseSource = 'shop_order') {
            syncDailyGradeSelection();
            document.getElementById('add-to-cart-product-id').value = productId;
            document.getElementById('add-to-cart-purchase-source').value = purchaseSource;
            document.getElementById('add-to-cart-product-name').textContent = productName;
            currentProductUnit = productUnit;

            const basisContainer = document.getElementById('modal-basis-container');
            if (productUnit === 'kg') {
                basisContainer.classList.remove('hidden');
            } else {
                basisContainer.classList.add('hidden');
            }
            
            const inCartLabel = document.getElementById('add-to-cart-in-cart-label');
            if (parseFloat(draftQty) > 0) {
                let labelText = `${parseFloat(draftQty).toFixed(2)} ${productUnit} in cart`;
                if (draftPurchasers) {
                    labelText += ` (by ${draftPurchasers})`;
                }
                inCartLabel.textContent = `(${labelText})`;
                inCartLabel.classList.remove('hidden');
            } else {
                inCartLabel.classList.add('hidden');
            }
            
            const defaultQty = Math.max(0, parseFloat(remainingQty) - parseFloat(draftQty));
            
            const qtyInput = document.getElementById('add-to-cart-quantity');
            qtyInput.value = defaultQty > 0 ? defaultQty.toFixed(2) : parseFloat(step).toFixed(2);
            
            document.getElementById('add-to-cart-price').value = '';
            
            setModalBasis('kg');
            
            document.getElementById('add-to-cart-modal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => qtyInput.focus(), 50);
        }

        function setDailyPurchaseGrade(grade) {
            syncDailyGradeSelection();
        }

        function syncDailyGradeSelection() {
            const modalGrade = document.getElementById('add-to-cart-purchase-grade');
            if (modalGrade) modalGrade.value = @js($purchaseGrade);
        }

        function closeAddToCartModal() {
            document.getElementById('add-to-cart-modal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function toggleDailyItemDetails(id) {
            const details = document.getElementById(id);
            const icon = document.getElementById(`${id}-icon`);

            if (!details) return;

            const willOpen = details.classList.contains('hidden');
            details.classList.toggle('hidden', !willOpen);
            icon?.classList.toggle('rotate-180', willOpen);
        }

        function switchDailyTab(tab) {
            const tabPending = document.getElementById('tab-pending');
            const tabCompleted = document.getElementById('tab-completed');
            const secPending = document.getElementById('section-pending');
            const secCompleted = document.getElementById('section-completed');

            if (!tabPending || !tabCompleted || !secPending || !secCompleted) return;

            if (tab === 'pending') {
                tabPending.className = 'flex-1 rounded-lg py-2.5 text-center text-xs font-black bg-white text-slate-955 shadow-sm transition-all';
                tabCompleted.className = 'flex-1 rounded-lg py-2.5 text-center text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all';
                secPending.classList.remove('hidden');
                secCompleted.classList.add('hidden');
                localStorage.setItem('daily_active_tab', 'pending');
            } else {
                tabCompleted.className = 'flex-1 rounded-lg py-2.5 text-center text-xs font-black bg-white text-slate-955 shadow-sm transition-all';
                tabPending.className = 'flex-1 rounded-lg py-2.5 text-center text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all';
                secCompleted.classList.remove('hidden');
                secPending.classList.add('hidden');
                localStorage.setItem('daily_active_tab', 'completed');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const cachedTab = localStorage.getItem('daily_active_tab');
            const hasPending = {{ $pendingSummary->isNotEmpty() ? 'true' : 'false' }};
            const defaultTab = cachedTab || (hasPending ? 'pending' : 'completed');
            switchDailyTab(defaultTab);
        });
    </script>
</x-layouts.app>
