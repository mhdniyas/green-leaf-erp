<x-layouts.app title="Edit Purchase Order — {{ $order->po_number }}">

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden max-w-4xl">
        <div class="px-6 py-5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Edit Purchase Order: {{ $order->po_number }}</h2>
            <p class="text-xs text-gray-500 mt-0.5 font-medium">Update draft purchase order details and items.</p>
        </div>

        <form method="POST" action="{{ route('purchasing.orders.update', $order) }}" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                    <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier <span class="text-red-500">*</span></label>
                    <select id="supplier_id" name="supplier_id" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('supplier_id') border-red-300 @enderror">
                        <option value="">Select supplier…</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $order->supplier_id) == $supplier->id)>{{ $supplier->name }} ({{ $supplier->type }})</option>
                        @endforeach
                    </select>
                    @error('supplier_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="order_date" class="block text-sm font-medium text-gray-700">Order Date <span class="text-red-500">*</span></label>
                    <input id="order_date" name="order_date" type="date" required
                           value="{{ old('order_date', $order->order_date->toDateString()) }}"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('order_date') border-red-300 @enderror">
                    @error('order_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="notes" class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                <textarea id="notes" name="notes" rows="2"
                          placeholder="e.g. Delivery instructions, quality expectations..."
                          class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 resize-none">{{ old('notes', $order->notes) }}</textarea>
                @error('notes') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- PO Items Section --}}
            <div class="space-y-3 pt-4 border-t border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Order Items</h3>
                    <button type="button" onclick="addItemRow()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-brand-700 bg-brand-50 hover:bg-brand-100 rounded-lg transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Item
                    </button>
                </div>
                @error('items') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror

                <div class="border border-gray-200 rounded-xl overflow-hidden bg-gray-50/50">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-100/50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-4 py-3 text-left w-1/2">Product <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3 text-right">Qty (kg) <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3 text-right">Price / kg <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3 text-right">Subtotal</th>
                                <th class="px-4 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="po-items-tbody" class="divide-y divide-gray-200 bg-white">
                            @php
                                $poItems = old('items', $order->items->toArray());
                            @endphp
                            @foreach($poItems as $index => $item)
                            <tr class="item-row" data-index="{{ $index }}">
                                <td class="p-3">
                                    <select name="items[{{ $index }}][product_id]" required
                                            class="product-select w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                                        <option value="">Select vegetable…</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}" @selected(($item['product_id'] ?? '') == $prod->id)>{{ $prod->name }} ({{ $prod->sku }})</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$index}.product_id") <p class="text-red-600 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                </td>
                                <td class="p-3">
                                    <input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" required
                                           value="{{ $item['quantity'] ?? '' }}"
                                           placeholder="0.000"
                                           class="qty-input w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
                                    @error("items.{$index}.quantity") <p class="text-red-600 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                </td>
                                <td class="p-3">
                                    <div class="relative">
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">INR</span>
                                        <input name="items[{{ $index }}][unit_price]" type="number" step="0.0001" min="0.0001" required
                                               value="{{ $item['unit_price'] ?? '' }}"
                                               placeholder="0.00"
                                               class="price-input w-full border border-gray-200 rounded-lg pl-8 pr-2 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
                                    </div>
                                    @error("items.{$index}.unit_price") <p class="text-red-600 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                </td>
                                <td class="p-3 text-right font-semibold text-gray-700 select-none">
                                    INR <span class="row-total">0.00</span>
                                </td>
                                <td class="p-3 text-center">
                                    <button type="button" onclick="removeItemRow(this)"
                                            class="remove-row-btn text-gray-400 hover:text-red-500 p-1 rounded hover:bg-red-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50/50 border-t border-gray-200 font-semibold">
                                <td colspan="3" class="px-4 py-3 text-right text-gray-500">Grand Total</td>
                                <td class="px-4 py-3 text-right text-brand-700 text-base">
                                    INR <span id="grand-total">0.00</span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors">
                    Save Changes
                </button>
                <a href="{{ route('purchasing.orders.show', $order) }}"
                   class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">Cancel</a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        let rowCount = {{ count($poItems) }};
        const productOptions = `@foreach($products as $prod)<option value="{{ $prod->id }}">{{ $prod->name }} ({{ $prod->sku }})</option>@endforeach`;

        function addItemRow() {
            const tbody = document.getElementById('po-items-tbody');
            const newIndex = rowCount++;
            
            const tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.setAttribute('data-index', newIndex);
            tr.innerHTML = `
                <td class="p-3">
                    <select name="items[\${newIndex}][product_id]" required
                            class="product-select w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                        <option value="">Select vegetable…</option>
                        \${productOptions}
                    </select>
                </td>
                <td class="p-3">
                    <input name="items[\${newIndex}][quantity]" type="number" step="0.001" min="0.001" required
                           placeholder="0.000"
                           class="qty-input w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
                </td>
                <td class="p-3">
                    <div class="relative">
                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">INR</span>
                        <input name="items[\${newIndex}][unit_price]" type="number" step="0.0001" min="0.0001" required
                               placeholder="0.00"
                               class="price-input w-full border border-gray-200 rounded-lg pl-8 pr-2 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
                    </div>
                </td>
                <td class="p-3 text-right font-semibold text-gray-700 select-none">
                    INR <span class="row-total">0.00</span>
                </td>
                <td class="p-3 text-center">
                    <button type="button" onclick="removeItemRow(this)"
                            class="remove-row-btn text-gray-400 hover:text-red-500 p-1 rounded hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </td>
            `;
            
            tbody.appendChild(tr);
            bindRowCalculations(tr);
            toggleRemoveButtons();
        }

        function removeItemRow(button) {
            const row = button.closest('tr');
            row.remove();
            calculateGrandTotal();
            toggleRemoveButtons();
        }

        function toggleRemoveButtons() {
            const rows = document.querySelectorAll('.item-row');
            const btns = document.querySelectorAll('.remove-row-btn');
            if (rows.length <= 1) {
                btns.forEach(btn => btn.classList.add('hidden'));
            } else {
                btns.forEach(btn => btn.classList.remove('hidden'));
            }
        }

        function bindRowCalculations(row) {
            const qtyInput = row.querySelector('.qty-input');
            const priceInput = row.querySelector('.price-input');
            const rowTotalSpan = row.querySelector('.row-total');

            function updateRowTotal() {
                const qty = parseFloat(qtyInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;
                const total = qty * price;
                rowTotalSpan.textContent = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                calculateGrandTotal();
            }

            qtyInput.addEventListener('input', updateRowTotal);
            priceInput.addEventListener('input', updateRowTotal);
        }

        function calculateGrandTotal() {
            let grandTotal = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                grandTotal += qty * price;
            });
            document.getElementById('grand-total').textContent = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Initialize calculations for existing rows
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.item-row').forEach(row => {
                bindRowCalculations(row);
                // Trigger initial calculation
                const qtyInput = row.querySelector('.qty-input');
                const priceInput = row.querySelector('.price-input');
                if (qtyInput.value || priceInput.value) {
                    const event = new Event('input');
                    qtyInput.dispatchEvent(event);
                }
            });
            toggleRemoveButtons();
        });
    </script>
    @endpush

</x-layouts.app>
