<x-layouts.app title="Purchaser Daily">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

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

        {{-- Single-column layout: product list only --}}
        <div class="flex min-w-0 flex-col gap-4">
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
                        <p class="text-xs font-semibold text-slate-600">{{ $dailySummary->count() }} pending products · {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
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

                <div class="space-y-2">
                    @forelse ($dailySummary as $summary)
                        @include('purchasing.purchaser.partials.daily_item', ['summary' => $summary, 'currentDate' => $date])
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4 lg:py-12">
                            No pending demand for this date.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

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
                <input id="add-to-cart-purchase-grade" type="hidden" name="purchase_grade" value="{{ $purchaseGrade }}">

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

    {{-- Product Demand Details Modal (Loaded Asynchronously) --}}
    <div id="info-product-modal" class="fixed inset-0 z-[90] hidden p-4 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center" onclick="if (event.target === this) closeDemandDetailsModal()">
        <div class="relative mx-auto w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-4 shadow-xl lg:rounded-[2rem] lg:p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Demand Details</p>
                    <h3 class="mt-1 text-lg font-black text-slate-955 truncate" id="info-modal-product-name">Loading...</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-600" id="info-modal-needed-label"></p>
                </div>
                <button type="button" onclick="closeDemandDetailsModal()" class="rounded-xl bg-slate-100 px-3 py-2 text-[11px] font-black text-slate-700 hover:bg-slate-200 transition-colors">Close</button>
            </div>
            
            {{-- Loading spinner --}}
            <div id="info-modal-loading" class="py-12 text-center text-sm font-bold text-slate-500">
                <div class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-teal-600 border-t-transparent"></div>
                <p class="mt-2 text-xs">Loading demand details...</p>
            </div>

            <div id="info-modal-body" class="hidden">
                {{-- Pending/In-Cart/Submitted Status Badges --}}
                <div id="info-modal-status-badges" class="mt-3 flex flex-wrap items-center gap-2">
                    <span id="info-modal-pending-badge" class="rounded-full bg-rose-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-rose-700 hidden">
                        Pending
                    </span>
                    <span id="info-modal-cart-qty-badge" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-800 hidden">
                        0.00 in cart
                    </span>
                    <span id="info-modal-submitted-qty-badge" class="rounded-full bg-cyan-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-cyan-800 hidden">
                        0.00 submitted today
                    </span>
                    <span id="info-modal-not-in-cart-badge" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-700 hidden">
                        Not in cart
                    </span>
                </div>

                {{-- Stat Cards --}}
                <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs font-semibold text-slate-600">
                    <div class="min-w-0 rounded-xl bg-slate-50 p-2">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Need</span>
                        <span class="mt-0.5 block truncate font-black text-slate-955" id="info-modal-need-qty">0.00</span>
                    </div>
                    <div class="min-w-0 rounded-xl bg-amber-50 p-2">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-600">Bought</span>
                        <span class="mt-0.5 block truncate font-black text-amber-700" id="info-modal-bought-qty">0.00</span>
                    </div>
                    <div class="min-w-0 rounded-xl bg-emerald-50 p-2">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-600">Left</span>
                        <span class="mt-0.5 block truncate font-black text-emerald-700" id="info-modal-left-qty">0.00</span>
                    </div>
                </div>

                {{-- Buckets and breakdowns container --}}
                <div id="info-modal-aggregates-container" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 hidden">
                    <div id="info-modal-buckets-container" class="flex flex-wrap items-center gap-2"></div>
                    <div id="info-modal-breakdown-container" class="mt-2 flex flex-wrap gap-1.5 hidden"></div>
                </div>

                {{-- Shop Details --}}
                <div class="mt-4 space-y-2 max-h-60 overflow-y-auto" id="info-modal-shops-container"></div>
            </div>
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

        function closeAddToCartModal() {
            document.getElementById('add-to-cart-modal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        async function openDemandDetailsModal(productId) {
            const modal = document.getElementById('info-product-modal');
            const loading = document.getElementById('info-modal-loading');
            const body = document.getElementById('info-modal-body');
            
            document.getElementById('info-modal-product-name').textContent = 'Loading...';
            document.getElementById('info-modal-needed-label').textContent = '';
            loading.classList.remove('hidden');
            body.classList.add('hidden');
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');

            try {
                const date = @js($date);
                const grade = @js($purchaseGrade);
                const url = `/purchaser/daily/products/${productId}/demand?date=${encodeURIComponent(date)}&purchase_grade=${encodeURIComponent(grade)}`;
                const response = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                
                if (!response.ok) {
                    throw new Error('Failed to load demand details');
                }
                
                const summary = await response.json();
                renderDemandModalContent(summary);
                loading.classList.add('hidden');
                body.classList.remove('hidden');
            } catch (err) {
                loading.innerHTML = `<p class="text-rose-600 text-xs">Failed to load demand details. Please try again.</p>`;
            }
        }

        function renderDemandModalContent(summary) {
            document.getElementById('info-modal-product-name').textContent = summary.product_name;
            document.getElementById('info-modal-needed-label').textContent = `${formatQuantity(summary.total_approved_qty)} ${summary.unit.toUpperCase()} total needed`;
            
            document.getElementById('info-modal-need-qty').textContent = `${formatQuantity(summary.total_approved_qty)} ${summary.unit}`;
            document.getElementById('info-modal-bought-qty').textContent = `${formatQuantity(summary.bought_qty)} ${summary.unit}`;
            document.getElementById('info-modal-left-qty').textContent = `${formatQuantity(summary.remaining_qty)} ${summary.unit}`;

            const pendingBadge = document.getElementById('info-modal-pending-badge');
            const cartQtyBadge = document.getElementById('info-modal-cart-qty-badge');
            const submittedQtyBadge = document.getElementById('info-modal-submitted-qty-badge');
            const notInCartBadge = document.getElementById('info-modal-not-in-cart-badge');

            pendingBadge.classList.add('hidden');
            cartQtyBadge.classList.add('hidden');
            submittedQtyBadge.classList.add('hidden');
            notInCartBadge.classList.add('hidden');

            const currentDate = @js($date);
            if (summary.order_date_ymd && summary.order_date_ymd !== currentDate) {
                pendingBadge.textContent = `Pending (${summary.order_date_formatted})`;
                pendingBadge.classList.remove('hidden');
            }

            if (summary.draft_qty > 0) {
                let label = `${formatQuantity(summary.draft_qty)} ${summary.unit} in cart`;
                if (summary.draft_purchasers && summary.draft_purchasers.length > 0) {
                    label += ` (by ${summary.draft_purchasers.join(', ')})`;
                }
                cartQtyBadge.textContent = label;
                cartQtyBadge.classList.remove('hidden');
            }

            if (summary.bought_qty > 0 && summary.remaining_qty > 0) {
                submittedQtyBadge.textContent = `${formatQuantity(summary.bought_qty)} ${summary.unit} submitted today`;
                submittedQtyBadge.classList.remove('hidden');
            }

            if (summary.draft_qty <= 0 && summary.bought_qty <= 0) {
                notInCartBadge.classList.remove('hidden');
            }

            // Populate Buckets
            const bucketsContainer = document.getElementById('info-modal-buckets-container');
            bucketsContainer.innerHTML = '';
            if (summary.quantity_buckets && summary.quantity_buckets.length > 0) {
                summary.quantity_buckets.forEach(bucket => {
                    const span = document.createElement('span');
                    span.className = 'inline-flex rounded-full bg-cyan-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-cyan-700';
                    span.textContent = `${bucket.formatted} x ${bucket.count}`;
                    bucketsContainer.appendChild(span);
                });
            }

            // Populate Breakdown
            const breakdownContainer = document.getElementById('info-modal-breakdown-container');
            breakdownContainer.innerHTML = '';
            if (summary.measure_breakdown && summary.measure_breakdown.length > 0) {
                summary.measure_breakdown.forEach(measure => {
                    const span = document.createElement('span');
                    span.className = 'inline-flex rounded-full bg-white px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-600 shadow-sm';
                    let label = `${formatQuantity(measure.requested_qty)} ${measure.label}`;
                    if (measure.label.toUpperCase() !== summary.unit.toUpperCase()) {
                        label += ` / ${formatQuantity(measure.approved_qty)} ${summary.unit}`;
                    }
                    span.textContent = label;
                    breakdownContainer.appendChild(span);
                });
                breakdownContainer.classList.remove('hidden');
            } else {
                breakdownContainer.classList.add('hidden');
            }

            const aggregatesContainer = document.getElementById('info-modal-aggregates-container');
            if ((summary.quantity_buckets && summary.quantity_buckets.length > 0) || (summary.measure_breakdown && summary.measure_breakdown.length > 0)) {
                aggregatesContainer.classList.remove('hidden');
            } else {
                aggregatesContainer.classList.add('hidden');
            }

            // Populate Shop Details
            const shopsContainer = document.getElementById('info-modal-shops-container');
            shopsContainer.innerHTML = '';
            if (summary.shop_details && summary.shop_details.length > 0) {
                summary.shop_details.forEach(detail => {
                    const div = document.createElement('div');
                    const isDirect = detail.is_direct_purchase;
                    div.className = `flex min-w-0 items-center justify-between gap-3 rounded-xl border ${isDirect ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50'} px-3 py-3 text-sm font-semibold text-slate-700 lg:rounded-2xl`;
                    
                    const leftCol = document.createElement('div');
                    leftCol.className = 'min-w-0';
                    const namePara = document.createElement('p');
                    namePara.className = `truncate font-black ${isDirect ? 'text-emerald-800' : 'text-slate-900'}`;
                    namePara.textContent = detail.shop_name;
                    const orderPara = document.createElement('p');
                    orderPara.className = 'truncate text-xs text-slate-500';
                    orderPara.textContent = detail.order_number;
                    leftCol.appendChild(namePara);
                    leftCol.appendChild(orderPara);

                    const rightCol = document.createElement('div');
                    rightCol.className = 'shrink-0 text-right';
                    const qtySpan = document.createElement('span');
                    qtySpan.className = 'block';
                    qtySpan.textContent = `${formatQuantity(detail.approved_qty)} ${detail.unit}`;
                    rightCol.appendChild(qtySpan);

                    if (detail.requested_measure_label) {
                        const measureSpan = document.createElement('span');
                        measureSpan.className = 'block text-[10px] font-black uppercase tracking-[0.1em] text-slate-400';
                        measureSpan.textContent = detail.requested_measure_label;
                        rightCol.appendChild(measureSpan);
                    }

                    div.appendChild(leftCol);
                    div.appendChild(rightCol);
                    shopsContainer.appendChild(div);
                });
            }
        }

        function formatQuantity(val) {
            const num = parseFloat(val) || 0;
            return num.toFixed(2);
        }

        function closeDemandDetailsModal() {
            document.getElementById('info-product-modal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    </script>
</x-layouts.app>
