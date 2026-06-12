@php
    $allProductsForOrder = collect();

    foreach ($productsByCategory as $category) {
        foreach ($category->products as $product) {
            $existingItem = $tomorrowOrder?->items->firstWhere('product_id', $product->id);
            $yesterdayItem = $yesterdayOrder?->items->firstWhere('product_id', $product->id);
            $currentQuantity = old("items.{$product->sku}", $existingItem?->requested_qty ?? '');
            $suggestedQuantity = (float) ($yesterdayItem?->requested_qty ?? 0);

            $allProductsForOrder->push([
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
                'category' => $category->name,
                'current_qty' => $currentQuantity,
                'suggested_qty' => $suggestedQuantity,
                'yesterday_qty' => $suggestedQuantity,
                'price' => (float) ($product->effective_price ?? $product->base_price ?? 0),
            ]);
        }
    }
@endphp

<div class="space-y-6">
    <!-- Search and Filter Bar -->
    <div class="space-y-3">
        <div class="relative">
            <input
                id="order-product-search"
                type="search"
                data-product-search
                placeholder="Search products by name, SKU or category..."
                class="w-full rounded-2xl border border-slate-200 bg-white py-3.5 pl-11 pr-4 text-sm font-semibold text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>

        <!-- Horizontal scrollable category pills -->
        <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-none snap-x snap-mandatory -mx-6 px-6 sm:mx-0 sm:px-0">
            @if ($frequentProducts->isNotEmpty())
                <button
                    type="button"
                    data-category-pill="frequent"
                    class="snap-start shrink-0 rounded-full px-4 py-2 text-xs font-black uppercase tracking-wider bg-emerald-600 text-white shadow-sm transition active:scale-95 duration-150"
                >
                    Frequent
                </button>
            @endif
            <button
                type="button"
                data-category-pill="all"
                class="snap-start shrink-0 rounded-full px-4 py-2 text-xs font-black uppercase tracking-wider {{ $frequentProducts->isEmpty() ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }} transition active:scale-95 duration-150"
            >
                All
            </button>
            @foreach ($productsByCategory as $category)
                <button
                    type="button"
                    data-category-pill="{{ $category->name }}"
                    class="snap-start shrink-0 rounded-full px-4 py-2 text-xs font-black uppercase tracking-wider bg-slate-100 text-slate-600 hover:bg-slate-200 transition active:scale-95 duration-150"
                >
                    {{ $category->name }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Product list display -->
    <div class="space-y-2">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
            <h3 id="current-list-title" class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">
                Frequently Ordered
            </h3>
            <span id="list-results-count" class="text-[10px] font-black uppercase tracking-wider text-slate-400 bg-slate-50 px-2.5 py-1 rounded-lg">
                0 products
            </span>
        </div>

        <div id="no-search-results" class="hidden rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-8 text-center text-xs text-slate-400">
            No matching products found. Try typing another search query.
        </div>

        <div id="product-list-container" class="divide-y divide-slate-100 bg-white rounded-3xl border border-slate-100 px-2 shadow-sm">
            @foreach ($allProductsForOrder as $productData)
                @php
                    $isFrequent = $frequentProducts->contains(fn ($item) => (int) $item['product']->id === (int) $productData['id']);
                @endphp
                <article
                    data-product-card
                    data-product-id="{{ $productData['id'] }}"
                    data-sku="{{ $productData['sku'] }}"
                    data-name="{{ $productData['name'] }}"
                    data-unit="{{ $productData['unit'] }}"
                    data-price="{{ $productData['price'] }}"
                    data-suggested-qty="{{ $productData['suggested_qty'] }}"
                    data-category="{{ $productData['category'] }}"
                    data-is-frequent="{{ $isFrequent ? 'true' : 'false' }}"
                    data-search-text="{{ \Illuminate\Support\Str::lower($productData['name'].' '.$productData['sku'].' '.$productData['category']) }}"
                    class="flex items-center justify-between p-3.5 cursor-pointer hover:bg-slate-50 active:bg-slate-100 rounded-2xl transition duration-150 {{ $isFrequent ? '' : 'hidden' }}"
                >
                    <div class="min-w-0 flex-1">
                        <h4 class="font-bold text-slate-900 text-sm truncate">{{ $productData['name'] }}</h4>
                        <p class="text-[11px] text-slate-500 truncate mt-1">
                            <span class="bg-slate-100 px-1.5 py-0.5 rounded font-black text-[10px] text-slate-500 mr-1.5 uppercase">{{ $productData['sku'] }}</span>
                            {{ $productData['category'] }}
                        </p>
                    </div>
                    <div class="flex items-center gap-4 shrink-0 pl-3">
                        <div data-badge-container="{{ $productData['id'] }}">
                            @if ((float) $productData['current_qty'] > 0)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700 border border-emerald-100">
                                    {{ number_format((float) $productData['current_qty'], 2) }} {{ $productData['unit'] }}
                                </span>
                            @else
                                <div class="w-8 h-8 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 transition hover:border-emerald-500 hover:text-emerald-500">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    <!-- Hidden Master Inputs (Submits the form) -->
    <div class="hidden" aria-hidden="true">
        @foreach ($allProductsForOrder as $productData)
            <input
                type="number"
                step="0.01"
                min="0"
                name="items[{{ $productData['sku'] }}]"
                value="{{ $productData['current_qty'] }}"
                data-order-qty
                data-master-qty
                data-product-id="{{ $productData['id'] }}"
                data-effective-price="{{ number_format((float) $productData['price'], 2, '.', '') }}"
            >
        @endforeach
    </div>

    <!-- Serialized JSON Catalog and Presets -->
    <script id="shop-owner-product-catalog" type="application/json">
        {!! $allProductsForOrder->values()->toJson() !!}
    </script>

    <!-- Bottom Sheet Modal Backdrop -->
    <div id="qty-modal-backdrop" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300"></div>

    <!-- Bottom Sheet Quantity Modal -->
    <div id="qty-modal-sheet" class="fixed bottom-0 inset-x-0 z-50 bg-white rounded-t-[2.5rem] shadow-[0_-15px_40px_rgba(0,0,0,0.15)] border-t border-slate-100 max-h-[85vh] overflow-y-auto hidden transform translate-y-full transition-transform duration-300 ease-out">
        <div class="mx-auto my-3.5 h-1.5 w-16 rounded-full bg-slate-200"></div>
        
        <div class="px-6 pb-8 pt-2">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <span id="modal-product-sku" class="inline-block bg-slate-100 px-1.5 py-0.5 rounded font-black text-[10px] text-slate-500 uppercase">SKU</span>
                    <h3 id="modal-product-name" class="text-lg font-black text-slate-900 mt-1.5 truncate">Product Name</h3>
                    <p id="modal-product-price-label" class="text-sm font-black text-emerald-700 mt-1 hidden">INR 0.00 / kg</p>
                </div>
                <button type="button" id="qty-modal-close" class="rounded-full bg-slate-100 p-2 text-slate-500 hover:bg-slate-200 transition">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-6 space-y-5">
                <!-- Unit Toggle Container -->
                <div id="modal-unit-toggle-container" class="space-y-2">
                    <label class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Ordering Unit</label>
                    <div class="grid grid-cols-2 gap-2 bg-slate-50 p-1.5 rounded-2xl border border-slate-100">
                        <button type="button" id="modal-unit-btn-standard" class="rounded-xl py-2.5 text-xs font-black tracking-wider uppercase bg-white text-slate-900 shadow-sm transition duration-150">
                            KG
                        </button>
                        <button type="button" id="modal-unit-btn-box" class="rounded-xl py-2.5 text-xs font-black tracking-wider uppercase text-slate-400 hover:text-slate-600 transition duration-150">
                            BOX
                        </button>
                    </div>
                </div>

                <!-- Quantity input field -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <label for="modal-qty-input" class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Enter Quantity</label>
                        <span id="modal-suggested-badge" class="text-[10px] font-black uppercase tracking-wider text-slate-500 bg-slate-50 px-2 py-0.5 rounded-md">Sug: 0.00</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 bg-slate-50 rounded-2xl p-2 border border-slate-100">
                        <button type="button" id="modal-stepper-minus" class="flex h-12 w-12 items-center justify-center rounded-xl bg-white border border-slate-200 text-lg font-black text-slate-700 shadow-sm hover:bg-slate-50 active:scale-95 transition duration-150">-</button>
                        <div class="flex-1 text-center flex items-center justify-center">
                            <input
                                type="number"
                                id="modal-qty-input"
                                step="0.01"
                                min="0"
                                class="w-full bg-transparent text-center text-xl font-black text-slate-900 focus:outline-none placeholder-slate-300"
                                placeholder="0.00"
                            >
                            <span id="modal-qty-unit-label" class="text-sm font-black text-slate-500 ml-1.5 uppercase">KG</span>
                        </div>
                        <button type="button" id="modal-stepper-plus" class="flex h-12 w-12 items-center justify-center rounded-xl bg-white border border-slate-200 text-lg font-black text-slate-700 shadow-sm hover:bg-slate-50 active:scale-95 transition duration-150">+</button>
                    </div>
                    <p id="modal-conversion-helper" class="text-xs text-slate-500 text-center font-bold mt-1.5 hidden">
                        1 Box = <span id="modal-conversion-factor-text">10</span> Kg. Calculated: <span id="modal-conversion-calc" class="font-black text-emerald-700">0.00 Kg</span>
                    </p>
                </div>

                <!-- Quick selection buttons -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Quick Select</label>
                    <div id="modal-quick-pills" class="flex flex-wrap gap-2"></div>
                </div>

                <!-- Footer / Action Buttons -->
                <div class="border-t border-slate-100 pt-5 space-y-4">
                    <div class="flex items-center justify-between hidden">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Line Subtotal</span>
                        <span id="modal-subtotal" class="text-lg font-black text-slate-900">INR 0.00</span>
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="button" id="modal-remove-btn" class="flex-1 rounded-2xl border border-rose-200 bg-rose-50 text-rose-700 py-3.5 text-xs font-black uppercase tracking-wider hover:bg-rose-100 active:scale-95 transition duration-150">
                            Remove
                        </button>
                        <button type="button" id="modal-add-btn" class="flex-[2] rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white py-3.5 text-xs font-black uppercase tracking-wider shadow-md shadow-emerald-600/10 transition duration-150">
                            Add to Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Persistent Cart Bar -->
    <div id="floating-cart-bar" class="fixed bottom-20 inset-x-4 z-45 max-w-md mx-auto hidden transform translate-y-6 opacity-0 transition-all duration-300 ease-out">
        <div class="bg-slate-900 text-white rounded-3xl p-4 flex items-center justify-between shadow-[0_12px_36px_rgba(0,0,0,0.25)] border border-slate-800">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white">
                    <svg class="h-5.5 w-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <div>
                    <p id="cart-bar-items-count" class="text-[10px] font-black uppercase tracking-[0.12em] text-emerald-400">0 Items Selected</p>
                    <p id="cart-bar-total-value" class="text-sm font-black hidden">INR 0.00</p>
                </div>
            </div>
            <button type="button" id="cart-bar-review-btn" class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider px-4.5 py-3 shadow-md shadow-emerald-700/20 active:scale-95 transition duration-150">
                Review Order
            </button>
        </div>
    </div>

    <!-- Cart Review Drawer Backdrop -->
    <div id="cart-review-backdrop" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300"></div>

    <!-- Cart Review Drawer -->
    <div id="cart-review-drawer" class="fixed bottom-0 inset-x-0 z-50 bg-white rounded-t-[2.5rem] shadow-[0_-15px_40px_rgba(0,0,0,0.15)] border-t border-slate-100 max-h-[80vh] overflow-hidden hidden transform translate-y-full transition-transform duration-300 ease-out flex flex-col">
        <div class="mx-auto my-3 h-1.5 w-16 shrink-0 rounded-full bg-slate-200"></div>
        
        <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between shrink-0">
            <div>
                <h3 class="text-base font-black text-slate-900">Review Requisition</h3>
                <p id="review-items-count" class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">0 items</p>
            </div>
            <button type="button" id="cart-review-close" class="rounded-full bg-slate-100 p-2 text-slate-500 hover:bg-slate-200 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Scrollable Content Container -->
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
            <!-- Review Items List -->
            <div id="review-items-list" class="divide-y divide-slate-100 space-y-0.5"></div>

            <!-- Extra Options (Save as Custom List & Reason for Change) -->
            <div class="border-t border-slate-100 pt-4 space-y-4">
                <!-- Save Preset Form Container (inline trigger & input) -->
                <div class="pb-2 space-y-2">
                    <button type="button" id="review-save-preset-trigger" class="text-xs font-black uppercase tracking-wider text-emerald-700 hover:text-emerald-800 flex items-center gap-1.5 transition active:scale-95 duration-100">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        Save as Custom List
                    </button>
                    
                    <div id="review-save-preset-form-container" class="hidden flex gap-2">
                        <input
                            type="text"
                            id="review-preset-name-input"
                            placeholder="List Name (e.g. Monday List)"
                            class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none"
                        >
                        <button type="button" id="review-save-preset-btn" class="rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-bold text-white hover:bg-slate-800 transition active:scale-95 duration-150">
                            Save
                        </button>
                    </div>
                </div>

                @if (isset($isUpdateRequest) && $isUpdateRequest)
                    <div class="space-y-2 pb-2">
                        <label for="visible-reason-drawer" class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Reason for Change</label>
                        <textarea
                            id="visible-reason-drawer"
                            rows="2"
                            placeholder="Why are you modifying this order?"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-amber-500 focus:outline-none transition"
                            required
                        >{{ old('reason', $tomorrowOrder?->update_reason) }}</textarea>
                    </div>
                @endif
            </div>
        </div>

        <!-- Drawer Footer Actions -->
        <div class="p-4 bg-slate-50 border-t border-slate-150 shrink-0">
            <div class="flex gap-3">
                <button type="button" id="review-add-more-btn" class="flex-1 rounded-2xl border border-slate-200 bg-white text-slate-700 py-3 text-xs font-black uppercase tracking-wider hover:bg-slate-50 active:scale-95 transition duration-150">
                    Add Items
                </button>
                <button type="button" id="review-submit-btn" class="flex-[2] rounded-2xl {{ isset($isUpdateRequest) && $isUpdateRequest ? 'bg-amber-600 hover:bg-amber-700 shadow-amber-600/10' : 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-600/10' }} active:scale-95 text-white py-3 text-xs font-black uppercase tracking-wider shadow-md transition duration-150">
                    {{ isset($isUpdateRequest) && $isUpdateRequest ? 'Submit Update' : 'Submit Order' }}
                </button>
            </div>
        </div>
    </div>
    
    <!-- Hidden Preset Save Form -->
    <form method="POST" action="{{ route('requisitions.presets.store') }}" data-save-preset-form class="hidden" aria-hidden="true">
        @csrf
        <input type="hidden" name="redirect_to" value="shop-owner-orders-create">
        <input type="text" name="name" data-preset-name-input>
    </form>

    <!-- Serialized Presets Data -->
    <script id="shop-owner-presets-data" type="application/json">
        {!! $presets->map(fn ($preset) => [
            'id' => $preset->id,
            'name' => $preset->name,
            'items' => $preset->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
            ])->values()->all(),
        ])->values()->toJson() !!}
    </script>
</div>
