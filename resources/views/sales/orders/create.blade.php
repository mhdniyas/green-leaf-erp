<x-layouts.app title="New Sales Order">

    <x-slot:actions>
        <a href="{{ route('sales.orders.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
            ← Back to Orders
        </a>
    </x-slot:actions>

    <div class="max-w-4xl mx-auto space-y-6">

        {{-- Order Header --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Order Details</h2>
            </div>
            <form id="sales-order-form" method="POST" action="{{ route('sales.orders.store') }}" class="p-6 space-y-5">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="customer_id" class="block text-xs font-semibold text-gray-700 mb-1.5">Customer <span class="text-red-500">*</span></label>
                        <select name="customer_id" id="customer_id" required class="w-full rounded-lg border @error('customer_id') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none">
                            <option value="">Select customer…</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }} ({{ $c->type }})</option>
                            @endforeach
                        </select>
                        @error('customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="order_date" class="block text-xs font-semibold text-gray-700 mb-1.5">Order Date <span class="text-red-500">*</span></label>
                        <input type="date" name="order_date" id="order_date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required
                               class="w-full rounded-lg border @error('order_date') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        @error('order_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold text-gray-700 mb-1.5">Notes</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Optional notes…"
                              class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100 resize-none">{{ old('notes') }}</textarea>
                </div>

                {{-- Order Items --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Order Items</h3>
                        <button type="button" id="add-item-btn"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700">
                            + Add Item
                        </button>
                    </div>

                    @error('items') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror

                    <div id="items-container" class="space-y-3">
                        {{-- Items injected by JS --}}
                    </div>
                </div>

                {{-- Total --}}
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <span class="text-sm font-semibold text-gray-700">Total</span>
                    <span id="order-total" class="text-lg font-bold text-gray-900">INR 0.00</span>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('sales.orders.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</a>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
                        Save Draft
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    const products = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'unit' => $p->unit ?? 'kg']));
    const grades = [
        { value: 'A', label: 'Grade A — Premium' },
        { value: 'B', label: 'Grade B — Standard' },
        { value: 'C', label: 'Grade C — Economy' },
    ];

    let itemCount = 0;

    function buildProductOptions() {
        return products.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
    }

    function buildGradeOptions() {
        return grades.map(g => `<option value="${g.value}">${g.label}</option>`).join('');
    }

    function addItem() {
        const idx = itemCount++;
        const html = `
            <div class="item-row grid grid-cols-12 gap-2 items-end" data-idx="${idx}">
                <div class="col-span-4">
                    ${idx === 0 ? '<label class="block text-xs font-medium text-gray-500 mb-1">Product</label>' : ''}
                    <select name="items[${idx}][product_id]" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                        <option value="">Select product…</option>
                        ${buildProductOptions()}
                    </select>
                </div>
                <div class="col-span-3">
                    ${idx === 0 ? '<label class="block text-xs font-medium text-gray-500 mb-1">Grade</label>' : ''}
                    <select name="items[${idx}][grade]" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                        ${buildGradeOptions()}
                    </select>
                </div>
                <div class="col-span-2">
                    ${idx === 0 ? '<label class="block text-xs font-medium text-gray-500 mb-1">Qty (kg)</label>' : ''}
                    <input type="number" name="items[${idx}][quantity]" min="0.001" step="0.001" required placeholder="0.000"
                           class="item-qty w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                </div>
                <div class="col-span-2">
                    ${idx === 0 ? '<label class="block text-xs font-medium text-gray-500 mb-1">Price/kg</label>' : ''}
                    <input type="number" name="items[${idx}][unit_price]" min="0" step="0.0001" required placeholder="0.00"
                           class="item-price w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                </div>
                <div class="col-span-1 flex justify-end pb-0.5">
                    <button type="button" onclick="removeItem(this)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>`;

        document.getElementById('items-container').insertAdjacentHTML('beforeend', html);
        attachTotalListeners();
    }

    function removeItem(btn) {
        btn.closest('.item-row').remove();
        recalcTotal();
    }

    function recalcTotal() {
        let total = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
            const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
            total += qty * price;
        });
        document.getElementById('order-total').textContent = 'INR ' + total.toFixed(2);
    }

    function attachTotalListeners() {
        document.querySelectorAll('.item-qty, .item-price').forEach(el => {
            el.removeEventListener('input', recalcTotal);
            el.addEventListener('input', recalcTotal);
        });
    }

    document.getElementById('add-item-btn').addEventListener('click', addItem);

    // Add first item on load
    addItem();
    </script>
    @endpush

</x-layouts.app>
