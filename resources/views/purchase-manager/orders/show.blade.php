@extends('purchase-manager.layouts.app')

@section('title', 'Purchase Order Details')
@section('page_title', $order->po_number)
@section('page_description', 'Review ordered items, supplier pricing, status, and receiving actions from one order screen.')

@section('content')
    @php($status = $order->status)

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
            @can('updateItems', $order)
                <form method="POST" action="{{ route('purchasing.orders.items.update', $order) }}" class="purchase-manager-panel overflow-hidden">
                    @csrf
                    @method('PUT')
            @else
                <div class="purchase-manager-panel overflow-hidden">
            @endcan
                <div class="border-b border-slate-200 px-5 py-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-slate-950">Ordered Items</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $order->items->count() }} items with pricing and expected weight.</p>
                        </div>
                        <span class="inline-flex rounded-full border px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] {{ $status->color() }}">
                            {{ $status->label() }}
                        </span>
                    </div>
                </div>

                <div data-po-show-table class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4 text-center">SL No</th>
                                <th class="px-5 py-4">Product</th>
                                <th class="px-5 py-4">Unit</th>
                                <th class="px-5 py-4 text-right">Price</th>
                                <th class="px-5 py-4 text-right">Qty / Packets</th>
                                <th class="px-5 py-4 text-right">Expected</th>
                                <th class="px-5 py-4 text-right">Actual</th>
                                <th class="px-5 py-4 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($order->items as $item)
                                <tr data-po-show-row>
                                    <td class="px-5 py-4 text-center align-top font-black text-slate-500">{{ $loop->iteration }}</td>
                                    <td class="px-5 py-4 align-top">
                                        <div class="font-bold text-slate-900">{{ $item->product->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $item->product->sku }}</div>
                                        @can('updateItems', $order)
                                            <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                                            <select data-po-product name="items[{{ $loop->index }}][product_id]" class="mt-3 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                                @foreach ($products as $product)
                                                    <option value="{{ $product->id }}" @selected($item->product_id === $product->id)>{{ $product->name }} ({{ $product->sku }})</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <p data-po-previous-price class="mt-2 text-[11px] font-semibold text-amber-700">
                                            @if(isset($previousPrices[$item->product_id]))
                                                Prev. Price: INR {{ number_format($previousPrices[$item->product_id], 4) }}
                                            @else
                                                Prev. Price: None
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        @can('updateItems', $order)
                                            <select data-po-unit name="items[{{ $loop->index }}][purchase_unit]" class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                                <option value="kg" @selected($item->purchase_unit === 'kg')>kg</option>
                                                <option value="packet" @selected($item->purchase_unit === 'packet')>packet</option>
                                                <option value="bag" @selected($item->purchase_unit === 'bag')>bag</option>
                                                <option value="box" @selected($item->purchase_unit === 'box')>box</option>
                                            </select>
                                        @else
                                            <x-purchase-manager.components.status-badge :label="$item->purchase_unit" tone="slate" />
                                        @endcan
                                    </td>
                                    <td class="px-5 py-4 align-top text-right">
                                        @can('updateItems', $order)
                                            <input data-po-unit-price type="number" step="0.0001" min="0" name="items[{{ $loop->index }}][unit_price]" value="{{ $item->unit_price }}" class="w-28 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-right text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            <select data-po-price-basis name="items[{{ $loop->index }}][price_basis]" class="mt-2 w-28 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                                <option value="per_kg" @selected($item->price_basis === 'per_kg')>per kg</option>
                                                <option value="per_unit" @selected($item->price_basis === 'per_unit')>per {{ $item->purchase_unit === 'kg' ? 'kg' : $item->purchase_unit }}</option>
                                            </select>
                                        @else
                                            <div class="font-bold text-slate-950">INR {{ number_format($item->unit_price, 4) }}</div>
                                        @endcan
                                    </td>
                                    <td class="px-5 py-4 align-top text-right">
                                        @can('updateItems', $order)
                                            <div data-po-packet-fields class="space-y-2 {{ $item->purchase_unit === 'kg' ? 'hidden' : '' }}">
                                                <input data-po-packet-qty type="number" step="0.01" min="0" name="items[{{ $loop->index }}][packet_qty]" value="{{ number_format((float) $item->packet_qty, 2, '.', '') }}" placeholder="Qty" class="w-24 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-right text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                                <input data-po-packet-weight type="number" step="0.01" min="0" name="items[{{ $loop->index }}][weight_per_packet]" value="{{ number_format((float) $item->weight_per_packet, 2, '.', '') }}" placeholder="kg" class="w-24 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-right text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            </div>
                                            <input data-po-quantity type="number" step="0.01" min="0" name="items[{{ $loop->index }}][quantity]" value="{{ number_format((float) $item->quantity, 2, '.', '') }}" class="mt-2 w-24 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-right text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none {{ $item->purchase_unit !== 'kg' ? 'hidden' : '' }}">
                                        @else
                                            <div class="font-semibold text-slate-900">{{ number_format((float) $item->quantity, 2) }} kg</div>
                                        @endcan
                                    </td>
                                    <td class="px-5 py-4 align-top text-right font-bold text-slate-900">
                                        <span data-po-expected>{{ number_format((float) $item->quantity, 2) }} kg</span>
                                    </td>
                                    <td class="px-5 py-4 align-top text-right">
                                        @can('updateItems', $order)
                                            <input data-po-actual-weight type="number" step="0.01" min="0" name="items[{{ $loop->index }}][actual_weight]" value="{{ $item->actual_weight !== null ? number_format((float) $item->actual_weight, 2, '.', '') : '' }}" placeholder="Weight" class="w-24 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-right text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            <div data-po-discrepancy class="mt-1 text-[11px] font-semibold"></div>
                                        @else
                                            <div class="font-semibold text-slate-900">{{ $item->actual_weight !== null ? number_format((float) $item->actual_weight, 2).' kg' : '—' }}</div>
                                        @endcan
                                    </td>
                                    <td class="px-5 py-4 align-top text-right font-bold text-slate-950">
                                        <span data-po-line-total>INR {{ number_format($item->subtotal, 2) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50">
                                <td colspan="7" class="px-5 py-4 text-right text-sm font-black uppercase tracking-[0.16em] text-slate-500">Grand Total</td>
                                <td class="px-5 py-4 text-right text-base font-black text-slate-950"><span data-po-show-total>INR {{ number_format($order->total_amount, 2) }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @can('updateItems', $order)
                    <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
                        <p class="text-sm text-slate-500">Use per kg for weighed purchases or per unit for packet, bag, and box buying.</p>
                        <x-purchase-manager.components.action-button type="submit" variant="primary">Save Changes</x-purchase-manager.components.action-button>
                    </div>
                @endcan
            @can('updateItems', $order)
                </form>
            @else
                </div>
            @endcan

            @if ($order->notes)
                <div class="purchase-manager-panel p-5">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Order Notes</p>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $order->notes }}</p>
                </div>
            @endif
        </div>

        <aside class="space-y-5">
            <div class="purchase-manager-panel p-5">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Order Actions</p>
                <div class="mt-4 flex flex-col gap-2">
                    @if ($status->value === 'draft')
                        @can('approve', $order)
                            <form method="POST" action="{{ route('purchasing.orders.approve', $order) }}">
                                @csrf
                                <x-purchase-manager.components.action-button type="submit" variant="success" class="w-full">Approve Order</x-purchase-manager.components.action-button>
                            </form>
                        @endcan
                        @can('update', $order)
                            <x-purchase-manager.components.action-button :href="route('purchasing.orders.edit', $order)" variant="secondary">Edit Order</x-purchase-manager.components.action-button>
                        @endcan
                        @can('delete', $order)
                            <form method="POST" action="{{ route('purchasing.orders.destroy', $order) }}" onsubmit="return confirm('Delete Purchase Order?')">
                                @csrf
                                @method('DELETE')
                                <x-purchase-manager.components.action-button type="submit" variant="soft-danger" class="w-full">Delete Order</x-purchase-manager.components.action-button>
                            </form>
                        @endcan
                    @endif

                    @if ($status->value === 'approved')
                        @can('send', $order)
                            <form method="POST" action="{{ route('purchasing.orders.send', $order) }}">
                                @csrf
                                <x-purchase-manager.components.action-button type="submit" variant="primary" class="w-full">Send to Supplier</x-purchase-manager.components.action-button>
                            </form>
                        @endcan
                    @endif

                    @if (in_array($status->value, ['sent_to_supplier', 'partially_received', 'received']))
                        @can('purchasing.grn.create')
                            <x-purchase-manager.components.action-button :href="route('purchasing.grns.create', ['purchase_order' => $order])" variant="success">Receive Goods</x-purchase-manager.components.action-button>
                        @endcan
                    @endif

                    <x-purchase-manager.components.action-button :href="route('purchasing.orders.index')" variant="secondary">Back to Orders</x-purchase-manager.components.action-button>
                </div>
            </div>

            <div class="purchase-manager-panel p-5">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Order Details</p>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between"><span class="text-slate-500">Order Date</span><span class="font-bold text-slate-950">{{ $order->order_date->format('Y-m-d') }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">PO Number</span><span class="font-mono font-bold text-slate-950">{{ $order->po_number }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Created By</span><span class="font-semibold text-slate-950">{{ $order->createdBy?->name ?? '—' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Fulfillment</span><span class="font-semibold text-slate-950">{{ $order->fulfillment_type === 'selection' ? 'Selection' : 'Warehouse' }}</span></div>
                </div>
            </div>

            <div class="purchase-manager-panel p-5">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Supplier</p>
                <div class="mt-4">
                    <p class="text-lg font-black text-slate-950">{{ $order->supplier->name }}</p>
                    <p class="text-sm font-semibold text-slate-500">{{ $order->supplier->type }}</p>
                </div>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between"><span class="text-slate-500">Payment Terms</span><span class="font-semibold text-slate-950">{{ $order->supplier->payment_terms }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Quality Score</span><span class="font-bold text-slate-950">{{ number_format((float) $order->supplier->quality_score, 2) }}</span></div>
                </div>
            </div>
        </aside>
    </div>

    <script id="purchase-manager-previous-prices" type="application/json">{!! collect($previousPrices)->toJson() !!}</script>
@endsection
