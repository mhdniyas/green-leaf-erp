<x-layouts.app title="Order {{ $order->so_number }}">

    <x-slot:actions>
        <a href="{{ route('sales.orders.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
            ← Back to Orders
        </a>
    </x-slot:actions>

    <div class="max-w-4xl mx-auto space-y-6">

        {{-- Header Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <h2 class="text-sm font-semibold text-gray-900">{{ $order->so_number }}</h2>
                    <span class="inline-flex items-center text-xs font-medium border px-2.5 py-0.5 rounded-full {{ $order->status->color() }}">
                        {{ $order->status->label() }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    {{-- Edit (draft only) --}}
                    @if($order->status->canBeConfirmed())
                    @can('update', $order)
                    <a href="{{ route('sales.orders.edit', $order) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Edit
                    </a>
                    @endcan
                    @endif

                    {{-- Confirm --}}
                    @if($order->status->canBeConfirmed())
                    @can('confirm', $order)
                    <form method="POST" action="{{ route('sales.orders.confirm', $order) }}"
                          onsubmit="return confirm('Confirm order and deduct stock?')">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition-colors">
                            Confirm Order
                        </button>
                    </form>
                    @endcan
                    @endif

                    {{-- Dispatch --}}
                    @if($order->status->canBeDispatched())
                    @can('dispatch', $order)
                    <form method="POST" action="{{ route('sales.orders.dispatch', $order) }}"
                          onsubmit="return confirm('Mark order as dispatched?')">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700 transition-colors">
                            Mark Dispatched
                        </button>
                    </form>
                    @endcan
                    @endif

                    {{-- Create Invoice --}}
                    @if($order->status->canBeInvoiced() && !$order->invoice)
                    @can('create', \App\Models\SalesInvoice::class)
                    <a href="{{ route('sales.invoices.create', ['so_id' => $order->id]) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700 transition-colors">
                        Create Invoice
                    </a>
                    @endcan
                    @endif

                    {{-- View Invoice --}}
                    @if($order->invoice)
                    <a href="{{ route('sales.invoices.show', $order->invoice) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-100 transition-colors">
                        View Invoice
                    </a>
                    @endif

                    {{-- Cancel --}}
                    @if($order->status->canBeCancelled())
                    @can('cancel', $order)
                    <form method="POST" action="{{ route('sales.orders.cancel', $order) }}"
                          onsubmit="return confirm('Cancel this order?{{ $order->status->value === 'confirmed' ? ' This will reverse the stock deductions.' : '' }}')">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors">
                            Cancel Order
                        </button>
                    </form>
                    @endcan
                    @endif
                </div>
            </div>

            {{-- Details --}}
            <div class="grid grid-cols-3 divide-x divide-gray-100 px-6 py-4">
                <div class="pr-6">
                    <p class="text-xs text-gray-400 mb-0.5">Customer</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $order->customer->name }}</p>
                    <p class="text-xs text-gray-500">{{ $order->customer->type }} · {{ $order->customer->payment_terms }}</p>
                </div>
                <div class="px-6">
                    <p class="text-xs text-gray-400 mb-0.5">Order Date</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $order->order_date->format('d M Y') }}</p>
                    <p class="text-xs text-gray-400">Created by {{ $order->createdBy?->name }}</p>
                </div>
                <div class="pl-6">
                    <p class="text-xs text-gray-400 mb-0.5">Total Value</p>
                    <p class="text-xl font-bold text-gray-900">INR {{ number_format($order->total_amount, 2) }}</p>
                </div>
            </div>

            @if($order->notes)
            <div class="px-6 pb-4">
                <p class="text-xs text-gray-400 mb-0.5">Notes</p>
                <p class="text-sm text-gray-600">{{ $order->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Line Items --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Order Items</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Grade</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Qty (kg)</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Price/kg</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($order->items as $item)
                        <tr>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $item->product->name }}</td>
                            <td class="px-6 py-3">
                                @php $badge = $item->grade->badge(); @endphp
                                <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full bg-{{ $badge }}-100 text-{{ $badge }}-700">
                                    {{ $item->grade->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right text-gray-700">{{ number_format((float) $item->quantity, 3) }}</td>
                            <td class="px-6 py-3 text-right text-gray-700">INR {{ number_format((float) $item->unit_price, 4) }}</td>
                            <td class="px-6 py-3 text-right font-semibold text-gray-900">INR {{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200">
                            <td colspan="4" class="px-6 py-3 text-right text-sm font-semibold text-gray-700">Total</td>
                            <td class="px-6 py-3 text-right text-base font-bold text-gray-900">INR {{ number_format($order->total_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</x-layouts.app>
