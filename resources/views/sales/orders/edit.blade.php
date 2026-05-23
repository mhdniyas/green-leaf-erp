<x-layouts.app title="Edit Order {{ $order->so_number }}">

    <x-slot:actions>
        <a href="{{ route('sales.orders.show', $order) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
            ← Back to Order
        </a>
    </x-slot:actions>

    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Edit Order — {{ $order->so_number }}</h2>
            </div>

            <form method="POST" action="{{ route('sales.orders.update', $order) }}" class="p-6 space-y-5">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="customer_id" class="block text-xs font-semibold text-gray-700 mb-1.5">Customer <span class="text-red-500">*</span></label>
                        <select name="customer_id" id="customer_id" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none">
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected(old('customer_id', $order->customer_id) == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="order_date" class="block text-xs font-semibold text-gray-700 mb-1.5">Order Date <span class="text-red-500">*</span></label>
                        <input type="date" name="order_date" id="order_date" value="{{ old('order_date', $order->order_date->format('Y-m-d')) }}" required
                               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold text-gray-700 mb-1.5">Notes</label>
                    <textarea name="notes" id="notes" rows="2"
                              class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none resize-none">{{ old('notes', $order->notes) }}</textarea>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Order Items</h3>
                        <button type="button" id="add-item-btn" class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700">
                            + Add Item
                        </button>
                    </div>
                    <div id="items-container" class="space-y-3"></div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <span class="text-sm font-semibold text-gray-700">Total</span>
                    <span id="order-total" class="text-lg font-bold text-gray-900">INR 0.00</span>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('sales.orders.show', $order) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</a>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    const products = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name]));
    const grades = [
        { value: 'A', label: 'Grade A — Premium' },
        { value: 'B', label: 'Grade B — Standard' },
        { value: 'C', label: 'Grade C — Economy' },
    ];
    const existingItems = @json($order->items->map(fn($i) => [
        'product_id' => $i->product_id,
        'grade'      => $i->grade->value,
        'quantity'   => (float) $i->quantity,
        'unit_price' => (float) $i->unit_price,
    ]));

    let itemCount = 0;

    function buildProductOptions(selectedId) {
        return products.map(p => `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${p.name}</option>`).join('');
    }

    function buildGradeOptions(selected) {
        return grades.map(g => `<option value="${g.value}" ${g.value == selected ? 'selected' : ''}>${g.label}</option>`).join('');
    }

    function addItem(item = {}) {
        const idx = itemCount++;
        const html = `
            <div class="item-row grid grid-cols-12 gap-2 items-end" data-idx="${idx}">
                <div class="col-span-4">
                    ${idx === 0 ? '<label class="block text-xs font-medium text-gray-500 mb-1">Product</label>' : ''}
                    <select name="items[${idx}][product_id]" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                        <option value="">Select…</option>
                        ${buildProductOptions(item.product_id)}
                    </select>
                </div>
                <div class="col-span-3">
                    ${idx === 0 ? '<label class="block text-xs font-medium text-gray-500 mb-1">Grade</label>' : ''}
                    <select name="items[${idx}][grade]" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                        ${buildGradeOptions(item.grade || 'A')}
                    </select>
                </div>
                <div class="col-span-2">
                    ${idx === 0 ? '<label class="block text-xs font-medium text-gray-500 mb-1">Qty (kg)</label>' : ''}
                    <input type="number" name="items[${idx}][quantity]" value="${item.quantity || ''}" min="0.001" step="0.001" required
                           class="item-qty w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                </div>
                <div class="col-span-2">
                    ${idx === 0 ? '<label class="block text-xs font-medium text-gray-500 mb-1">Price/kg</label>' : ''}
                    <input type="number" name="items[${idx}][unit_price]" value="${item.unit_price || ''}" min="0" step="0.0001" required
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

    function removeItem(btn) { btn.closest('.item-row').remove(); recalcTotal(); }

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

    document.getElementById('add-item-btn').addEventListener('click', () => addItem());
    existingItems.forEach(item => addItem(item));
    recalcTotal();
    </script>
    @endpush

</x-layouts.app>
