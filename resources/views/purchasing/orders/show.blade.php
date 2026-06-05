<x-layouts.app title="Purchase Order Details — {{ $order->po_number }}">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left column - items --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Items Card --}}
            @can('updateItems', $order)
            <form id="po-items-form" method="POST" action="{{ route('purchasing.orders.items.update', $order) }}">
                @csrf
                @method('PUT')
            @endcan

            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Ordered Items</h2>
                    <span class="text-xs text-gray-500 font-mono">{{ $order->items->count() }} items</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Unit</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Price</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Qty / Packets</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Expected Wt</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actual Wt</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($order->items as $item)
                            <tr class="po-item-row">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-brand-100 flex items-center justify-center shrink-0">
                                            <span class="text-brand-700 text-xs font-bold">{{ strtoupper(substr($item->product->name, 0, 1)) }}</span>
                                        </div>
                                        @can('updateItems', $order)
                                            <div class="min-w-52">
                                                <select name="items[{{ $loop->index }}][product_id]" class="po-product-select w-full border-gray-200 rounded-lg text-xs p-1.5 focus:border-brand-500 focus:ring-brand-500 bg-gray-50 font-semibold text-gray-700">
                                                    @foreach($products as $product)
                                                        <option value="{{ $product->id }}" {{ $item->product_id === $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->sku }})</option>
                                                    @endforeach
                                                </select>
                                                <p class="po-previous-price text-[11px] text-amber-700 font-medium mt-1">
                                                    @if(isset($previousPrices[$item->product_id]))
                                                        Prev. Price: INR {{ number_format($previousPrices[$item->product_id], 4) }}
                                                    @else
                                                        Prev. Price: None
                                                    @endif
                                                </p>
                                            </div>
                                        @else
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $item->product->name }}</p>
                                                <code class="text-[10px] font-mono text-gray-400">{{ $item->product->sku }}</code>
                                                <p class="text-[11px] text-amber-700 font-medium mt-0.5">
                                                    @if(isset($previousPrices[$item->product_id]))
                                                        Prev. Price: INR {{ number_format($previousPrices[$item->product_id], 4) }}
                                                    @else
                                                        Prev. Price: None
                                                    @endif
                                                </p>
                                            </div>
                                        @endcan
                                    </div>
                                    @can('updateItems', $order)
                                        <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                                    @endcan
                                </td>

                                <td class="px-4 py-4">
                                    @can('updateItems', $order)
                                        <select name="items[{{ $loop->index }}][purchase_unit]" class="po-unit-select border-gray-200 rounded-lg text-xs p-1.5 focus:border-brand-500 focus:ring-brand-500 bg-gray-50 font-semibold text-gray-700">
                                            <option value="kg" {{ $item->purchase_unit === 'kg' ? 'selected' : '' }}>kg (Kilograms)</option>
                                            <option value="packet" {{ $item->purchase_unit === 'packet' ? 'selected' : '' }}>packet</option>
                                            <option value="bag" {{ $item->purchase_unit === 'bag' ? 'selected' : '' }}>bag</option>
                                            <option value="box" {{ $item->purchase_unit === 'box' ? 'selected' : '' }}>box</option>
                                        </select>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $item->purchase_unit }}
                                        </span>
                                    @endcan
                                </td>

                                <td class="px-4 py-4 text-right">
                                    @can('updateItems', $order)
                                        <div class="flex items-center justify-end gap-1">
                                            <span class="text-xs text-gray-400 font-medium">INR</span>
                                            <input type="number" name="items[{{ $loop->index }}][unit_price]" step="0.0001" min="0" value="{{ $item->unit_price }}" class="po-unit-price-input w-24 text-right border-gray-200 rounded-lg text-xs p-1.5 focus:border-brand-500 focus:ring-brand-500 font-semibold text-gray-900">
                                        </div>
                                        <select name="items[{{ $loop->index }}][price_basis]" class="po-price-basis-select mt-1 ml-auto block border-gray-200 rounded-lg text-[11px] p-1.5 focus:border-brand-500 focus:ring-brand-500 bg-gray-50 font-semibold text-gray-600">
                                            <option value="per_kg" {{ $item->price_basis === 'per_kg' ? 'selected' : '' }}>per kg</option>
                                            <option value="per_unit" {{ $item->price_basis === 'per_unit' ? 'selected' : '' }}>per {{ $item->purchase_unit === 'kg' ? 'kg' : $item->purchase_unit }}</option>
                                        </select>
                                    @else
                                        <span class="text-gray-950 font-medium">INR {{ number_format($item->unit_price, 4) }}</span>
                                        <div class="text-[11px] text-gray-400 font-semibold">
                                            {{ $item->price_basis === 'per_unit' ? 'per '.$item->purchase_unit : 'per kg' }}
                                        </div>
                                    @endcan
                                </td>

                                <td class="px-4 py-4 text-right">
                                    @can('updateItems', $order)
                                        <div class="flex flex-col items-end gap-1">
                                            <div class="po-packet-fields flex items-center gap-1 {{ $item->purchase_unit === 'kg' ? 'hidden' : '' }}">
                                                <input type="number" name="items[{{ $loop->index }}][packet_qty]" step="0.01" min="0" value="{{ number_format((float) $item->packet_qty, 2, '.', '') }}" placeholder="Qty" class="po-packet-qty-input w-16 text-right border-gray-200 rounded-lg text-xs p-1.5 focus:border-brand-500 focus:ring-brand-500 font-medium text-gray-900">
                                                <span class="text-gray-400 text-xs">x</span>
                                                <input type="number" name="items[{{ $loop->index }}][weight_per_packet]" step="0.01" min="0" value="{{ number_format((float) $item->weight_per_packet, 2, '.', '') }}" placeholder="kg" class="po-weight-per-packet-input w-16 text-right border-gray-200 rounded-lg text-xs p-1.5 focus:border-brand-500 focus:ring-brand-500 font-medium text-gray-900">
                                                <span class="text-gray-400 text-xs">kg</span>
                                            </div>
                                            <input type="number" name="items[{{ $loop->index }}][quantity]" step="0.01" min="0" value="{{ number_format((float) $item->quantity, 2, '.', '') }}" class="po-quantity-input w-24 text-right border-gray-200 rounded-lg text-xs p-1.5 focus:border-brand-500 focus:ring-brand-500 font-medium text-gray-900 {{ $item->purchase_unit !== 'kg' ? 'hidden' : '' }}" {{ $item->purchase_unit !== 'kg' ? 'readonly' : '' }}>
                                        </div>
                                    @else
                                        @if($item->purchase_unit !== 'kg')
                                            <div class="text-right">
                                                <span class="font-medium text-gray-900">{{ number_format((float) $item->packet_qty, 2) }}</span>
                                                <span class="text-gray-400 text-xs">x</span>
                                                <span class="font-medium text-gray-900">{{ number_format((float) $item->weight_per_packet, 2) }} kg</span>
                                            </div>
                                        @else
                                            <span class="font-medium text-gray-900">{{ number_format((float) $item->quantity, 2) }} kg</span>
                                        @endif
                                    @endcan
                                </td>

                                <td class="px-4 py-4 text-right text-gray-900 font-medium po-expected-wt-display">
                                    {{ number_format((float) $item->quantity, 2) }} kg
                                </td>

                                <td class="px-4 py-4 text-right">
                                    @can('updateItems', $order)
                                        <div class="flex flex-col items-end">
                                            <input type="number" name="items[{{ $loop->index }}][actual_weight]" step="0.01" min="0" value="{{ $item->actual_weight !== null ? number_format((float) $item->actual_weight, 2, '.', '') : '' }}" placeholder="Enter weight" class="po-actual-weight-input w-24 text-right border-gray-200 rounded-lg text-xs p-1.5 focus:border-brand-500 focus:ring-brand-500 font-medium text-gray-900">
                                            <div class="po-discrepancy-badge text-xs mt-1 text-right font-medium"></div>
                                        </div>
                                    @else
                                        @if($item->actual_weight !== null)
                                            <span class="font-semibold text-gray-950">{{ number_format((float) $item->actual_weight, 2) }} kg</span>
                                            @if($item->actual_weight != $item->quantity)
                                                @php
                                                    $diff = $item->actual_weight - $item->quantity;
                                                @endphp
                                                <div class="text-xs font-bold mt-0.5 {{ $diff >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    Diff: {{ $diff >= 0 ? '+' : '' }}{{ number_format($diff, 2) }} kg
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    @endcan
                                </td>

                                <td class="px-4 py-4 text-right font-semibold text-gray-955 po-subtotal-display">
                                    INR {{ number_format($item->subtotal, 2) }}
                                </td>
                            </tr>
                            @endforeach
                            {{-- Totals --}}
                            <tr class="bg-gray-50/50 border-t border-gray-100 total-row">
                                <td colspan="6" class="px-4 py-4 text-right font-medium text-gray-500">Total Amount</td>
                                <td class="px-4 py-4 text-right text-base font-bold text-brand-700 po-grand-total-display">
                                    INR {{ number_format($order->total_amount, 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Footer Save Button --}}
                @can('updateItems', $order)
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-500">
                        Use per kg for weighed purchases, or per unit when the supplier prices each packet, bag, or box. Warehouse receipt remains in kg.
                    </p>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Save Changes
                    </button>
                </div>
                @endcan
            </div>
            @can('updateItems', $order)
            </form>
            @endcan

            {{-- Notes Card --}}
            @if($order->notes)
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Order Notes</h3>
                <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $order->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Right column - metadata and actions --}}
        <div class="space-y-6">
            {{-- Status & Actions Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Order Status</h3>
                    <span class="inline-flex items-center text-sm font-semibold border px-3 py-1 rounded-full {{ $status->color() }}">
                        {{ $status->label() }}
                    </span>
                </div>

                {{-- Action buttons --}}
                <div class="flex flex-col gap-2 pt-4 border-t border-gray-100">
                    @if($status->value === 'draft')
                        @can('approve', $order)
                        <form method="POST" action="{{ route('purchasing.orders.approve', $order) }}">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Approve Order
                            </button>
                        </form>
                        @endcan

                        @can('update', $order)
                        <a href="{{ route('purchasing.orders.edit', $order) }}" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-white border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                            Edit Order
                        </a>
                        @endcan

                        @can('delete', $order)
                        <form method="POST" action="{{ route('purchasing.orders.destroy', $order) }}" onsubmit="return confirm('Delete Purchase Order?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-red-50 border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-100 transition-colors cursor-pointer">
                                Delete Order
                            </button>
                        </form>
                        @endcan
                    @endif

                    @if($status->value === 'approved')
                        @can('send', $order)
                        <form method="POST" action="{{ route('purchasing.orders.send', $order) }}">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                </svg>
                                Send to Supplier
                            </button>
                        </form>
                        @endcan
                    @endif

                    @if(in_array($status->value, ['sent_to_supplier', 'partially_received', 'received']))
                        @can('purchasing.grn.create')
                        <a href="{{ route('purchasing.grns.create', ['purchase_order_id' => $order->id]) }}" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124l-.318-5.085a1.5 1.5 0 0 0-1.496-1.408h-2.483c-.767 0-1.42.545-1.5 1.3L12.5 14.25m0 0v-4.5m0 4.5h6.75m-6.75-4.5H8.25M6.75 8.25h.008v.008H6.75V8.25Zm.375 0a.375 0 1 1-.75 0 .375 0 0 1 .75 0Z" />
                            </svg>
                            Receive Goods (GRN)
                        </a>
                        @endcan
                    @endif

                    <a href="{{ route('purchasing.orders.index') }}" class="text-xs text-center text-gray-500 hover:text-gray-700 transition-colors mt-2">
                        ← Back to orders log
                    </a>
                </div>
            </div>

            {{-- Order Summary Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Order Details</h3>
                <div class="grid grid-cols-2 gap-y-3 text-sm">
                    <span class="text-gray-500">Order Date</span>
                    <span class="text-gray-900 font-medium text-right">{{ $order->order_date->format('Y-m-d') }}</span>

                    <span class="text-gray-500">PO Number</span>
                    <span class="text-gray-900 font-mono font-bold text-right">{{ $order->po_number }}</span>

                    <span class="text-gray-500">Created By</span>
                    <span class="text-gray-900 text-right">{{ $order->createdBy?->name ?? '—' }}</span>

                    <span class="text-gray-500">Fulfillment</span>
                    <span class="text-gray-900 font-semibold text-right">
                        {{ $order->fulfillment_type === 'selection' ? 'Selection (Packet)' : 'Warehouse (Bulk)' }}
                    </span>
                </div>
            </div>

            {{-- Supplier summary Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Supplier</h3>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                        <span class="text-amber-700 text-sm font-bold">{{ strtoupper(substr($order->supplier->name, 0, 1)) }}</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-gray-900">{{ $order->supplier->name }}</p>
                        <p class="text-xs text-gray-400">{{ $order->supplier->type }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-y-3 pt-3 border-t border-gray-100 text-xs">
                    <span class="text-gray-500">Payment Terms</span>
                    <span class="text-gray-800 font-medium text-right">{{ $order->supplier->payment_terms }}</span>

                    <span class="text-gray-500">Quality Score</span>
                    @php
                        $supplierScore = (float) $order->supplier->quality_score;
                        if ($supplierScore >= 90) {
                            $supplierColor = 'text-green-700';
                        } elseif ($supplierScore >= 75) {
                            $supplierColor = 'text-amber-700';
                        } else {
                            $supplierColor = 'text-red-700';
                        }
                    @endphp
                    <span class="font-bold text-right {{ $supplierColor }}">{{ number_format($supplierScore, 2) }} / 100</span>
                </div>
            </div>
        </div>

    </div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('table');
    if (!table) return;

    const previousPrices = @json($previousPrices);

    function recalculateRow(row) {
        const productSelect = row.querySelector('.po-product-select');
        const unitSelect = row.querySelector('.po-unit-select');
        const unitPriceInput = row.querySelector('.po-unit-price-input');
        const priceBasisSelect = row.querySelector('.po-price-basis-select');
        const previousPriceDisplay = row.querySelector('.po-previous-price');
        const packetFields = row.querySelector('.po-packet-fields');
        const packetQtyInput = row.querySelector('.po-packet-qty-input');
        const weightPerPacketInput = row.querySelector('.po-weight-per-packet-input');
        const quantityInput = row.querySelector('.po-quantity-input');
        const expectedWtDisplay = row.querySelector('.po-expected-wt-display');
        const actualWeightInput = row.querySelector('.po-actual-weight-input');
        const discrepancyBadge = row.querySelector('.po-discrepancy-badge');
        const subtotalDisplay = row.querySelector('.po-subtotal-display');

        if (!unitSelect) return;

        const unit = unitSelect.value;
        const unitPrice = parseFloat(unitPriceInput.value) || 0;
        const priceBasis = priceBasisSelect ? priceBasisSelect.value : 'per_kg';

        if (productSelect && previousPriceDisplay) {
            const previousPrice = previousPrices[productSelect.value];
            previousPriceDisplay.textContent = previousPrice
                ? `Prev. Price: INR ${Number(previousPrice).toFixed(4)}`
                : 'Prev. Price: None';
        }

        if (priceBasisSelect) {
            const perUnitOption = priceBasisSelect.querySelector('option[value="per_unit"]');
            if (perUnitOption) {
                perUnitOption.textContent = 'per ' + (unit === 'kg' ? 'kg' : unit);
            }
        }

        let expectedQuantity = 0;
        if (unit === 'kg') {
            if (packetFields) packetFields.classList.add('hidden');
            if (quantityInput) {
                quantityInput.classList.remove('hidden');
                quantityInput.readOnly = false;
                expectedQuantity = parseFloat(quantityInput.value) || 0;
            }
        } else {
            if (packetFields) packetFields.classList.remove('hidden');
            if (quantityInput) {
                quantityInput.classList.add('hidden');
                quantityInput.readOnly = true;
            }
            const packetQty = parseFloat(packetQtyInput.value) || 0;
            const weightPerPacket = parseFloat(weightPerPacketInput.value) || 0;
            expectedQuantity = packetQty * weightPerPacket;
            if (quantityInput) {
                quantityInput.value = expectedQuantity.toFixed(2);
            }
        }

        if (expectedWtDisplay) {
            expectedWtDisplay.textContent = expectedQuantity.toFixed(2) + ' kg';
        }

        const actualWeightVal = actualWeightInput ? actualWeightInput.value.trim() : '';
        const actualWeight = actualWeightVal !== '' ? parseFloat(actualWeightVal) : null;

        // Discrepancy
        if (discrepancyBadge) {
            if (actualWeight !== null && actualWeight !== expectedQuantity) {
                const diff = actualWeight - expectedQuantity;
                const sign = diff >= 0 ? '+' : '';
                discrepancyBadge.textContent = `Diff: ${sign}${diff.toFixed(2)} kg`;
                discrepancyBadge.className = `po-discrepancy-badge text-[11px] mt-1 text-right font-semibold ${diff >= 0 ? 'text-green-600' : 'text-rose-600'}`;
            } else {
                discrepancyBadge.textContent = '';
            }
        }

        const finalWeight = actualWeight !== null ? actualWeight : expectedQuantity;
        const pricedUnits = unit === 'kg' ? finalWeight : (parseFloat(packetQtyInput?.value) || 0);
        const subtotal = (priceBasis === 'per_unit' ? pricedUnits : finalWeight) * unitPrice;
        if (subtotalDisplay) {
            subtotalDisplay.textContent = 'INR ' + subtotal.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        return subtotal;
    }

    function recalculateAll() {
        let grandTotal = 0;
        const rows = table.querySelectorAll('tbody tr:not(.total-row)');
        rows.forEach(row => {
            const subtotal = recalculateRow(row);
            if (typeof subtotal === 'number') {
                grandTotal += subtotal;
            }
        });

        const grandTotalDisplay = document.querySelector('.po-grand-total-display');
        if (grandTotalDisplay) {
            grandTotalDisplay.textContent = 'INR ' + grandTotal.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    }

    table.addEventListener('input', function (e) {
        if (e.target.matches('.po-unit-price-input, .po-packet-qty-input, .po-weight-per-packet-input, .po-quantity-input, .po-actual-weight-input')) {
            recalculateAll();
        }
    });

    table.addEventListener('change', function (e) {
        if (e.target.matches('.po-unit-select, .po-price-basis-select, .po-product-select')) {
            const row = e.target.closest('tr');
            recalculateRow(row);
            recalculateAll();
        }
    });

    // Run initial calculations on load
    const rows = table.querySelectorAll('tbody tr:not(.total-row)');
    rows.forEach(row => {
        recalculateRow(row);
    });
    recalculateAll();
});
</script>
@endpush

</x-layouts.app>
