<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
    <form method="POST" action="{{ $formAction }}" class="purchase-manager-panel overflow-hidden">
        @csrf
        @if ($formMethod !== 'POST')
            @method($formMethod)
        @endif

        <div class="border-b border-slate-200 px-5 py-5">
            <h2 class="text-lg font-black text-slate-950">{{ $order ? 'Edit Draft Order' : 'New Draft Order' }}</h2>
            <p class="mt-1 text-sm text-slate-500">Keep the supplier and item lines clear for fast approval and purchasing follow-up.</p>
        </div>

        <div class="space-y-6 px-5 py-5">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label for="supplier_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Supplier</label>
                    <select id="supplier_id" name="supplier_id" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none @error('supplier_id') border-rose-300 @enderror">
                        <option value="">Select supplier</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $order?->supplier_id) == $supplier->id)>{{ $supplier->name }} ({{ $supplier->type }})</option>
                        @endforeach
                    </select>
                    @error('supplier_id')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="order_date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Order Date</label>
                    <input id="order_date" type="date" name="order_date" required value="{{ old('order_date', $order?->order_date?->toDateString() ?? today()->toDateString()) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none @error('order_date') border-rose-300 @enderror">
                    @error('order_date')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="fulfillment_type" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Fulfillment</label>
                    <select id="fulfillment_type" name="fulfillment_type" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none @error('fulfillment_type') border-rose-300 @enderror">
                        <option value="warehouse" @selected(old('fulfillment_type', $order?->fulfillment_type ?? 'warehouse') === 'warehouse')>Warehouse (Bulk)</option>
                        <option value="selection" @selected(old('fulfillment_type', $order?->fulfillment_type) === 'selection')>Selection (Packet)</option>
                    </select>
                    @error('fulfillment_type')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="notes" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">{{ old('notes', $order?->notes) }}</textarea>
            </div>

            <div class="border-t border-slate-200 pt-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-black text-slate-950">Order Items</h3>
                        <p class="mt-1 text-sm text-slate-500">Add products, quantities, and price per kg.</p>
                    </div>
                    <x-purchase-manager.components.action-button type="button" variant="secondary" data-add-po-item>Add Item</x-purchase-manager.components.action-button>
                </div>
                @error('items')<p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror

                <div data-po-items-list class="mt-5 space-y-4">
                    @foreach ($poItems as $index => $item)
                        <div data-po-item-row class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4">
                            <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_120px_160px_90px] md:items-end">
                                <div>
                                    <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Product</label>
                                    <select data-field="product_id" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                        <option value="">Select product</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" @selected(($item['product_id'] ?? '') == $product->id)>{{ $product->name }} ({{ $product->sku }})</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" data-field="price_basis" value="{{ $item['price_basis'] ?? 'per_kg' }}">
                                </div>
                                <div>
                                    <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Qty (kg)</label>
                                    <input data-field="quantity" type="number" step="0.001" min="0.001" value="{{ $item['quantity'] ?? '' }}" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Price / kg</label>
                                    <div class="relative mt-2">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">INR</span>
                                        <input data-field="unit_price" type="number" step="0.0001" min="0.0001" value="{{ $item['unit_price'] ?? '' }}" required class="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-14 pr-4 text-right text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 md:justify-end">
                                    <button type="button" data-remove-po-item class="w-full rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700 md:w-auto">Remove</button>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-sm">
                                <span class="font-bold uppercase tracking-[0.14em] text-slate-500">Subtotal</span>
                                <span class="font-black text-slate-950">INR <span data-po-subtotal>0.00</span></span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template id="purchase-manager-po-item-template">
                    <div data-po-item-row class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4">
                        <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_120px_160px_90px] md:items-end">
                            <div>
                                <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Product</label>
                                <select data-field="product_id" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                    <option value="">Select product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                    @endforeach
                                </select>
                                <input type="hidden" data-field="price_basis" value="per_kg">
                            </div>
                            <div>
                                <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Qty (kg)</label>
                                <input data-field="quantity" type="number" step="0.001" min="0.001" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Price / kg</label>
                                <div class="relative mt-2">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">INR</span>
                                    <input data-field="unit_price" type="number" step="0.0001" min="0.0001" required class="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-14 pr-4 text-right text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                </div>
                            </div>
                            <div class="flex items-center gap-2 md:justify-end">
                                <button type="button" data-remove-po-item class="w-full rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700 md:w-auto">Remove</button>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-sm">
                            <span class="font-bold uppercase tracking-[0.14em] text-slate-500">Subtotal</span>
                            <span class="font-black text-slate-950">INR <span data-po-subtotal>0.00</span></span>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-6">
                <x-purchase-manager.components.action-button type="submit" variant="primary">{{ $submitLabel }}</x-purchase-manager.components.action-button>
                <x-purchase-manager.components.action-button :href="$cancelHref" variant="secondary">Cancel</x-purchase-manager.components.action-button>
            </div>
        </div>
    </form>

    <aside class="space-y-5">
        <div class="purchase-manager-panel p-5">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Order Summary</p>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Line Items</span>
                    <span class="font-black text-slate-950">{{ count($poItems) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Grand Total</span>
                    <span class="text-xl font-black text-slate-950">INR <span data-po-grand-total>0.00</span></span>
                </div>
            </div>
        </div>
        <div class="purchase-manager-panel p-5">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Workflow Note</p>
            <p class="mt-3 text-sm leading-6 text-slate-600">Draft orders stay editable until approval. Keep product quantities and pricing clean here so the supplier and receipt teams work from one source of truth.</p>
        </div>
    </aside>
</div>
