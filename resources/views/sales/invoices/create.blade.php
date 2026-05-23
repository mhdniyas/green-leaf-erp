<x-layouts.app title="Create Invoice">

    <x-slot:actions>
        @if($order)
        <a href="{{ route('sales.orders.show', $order) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
            ← Back to Order
        </a>
        @else
        <a href="{{ route('sales.invoices.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
            ← Back to Invoices
        </a>
        @endif
    </x-slot:actions>

    <div class="max-w-2xl mx-auto">
        @if($order)
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Create Invoice for {{ $order->so_number }}</h2>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 space-y-1">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Customer</span>
                    <span class="font-semibold text-gray-900">{{ $order->customer->name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Payment Terms</span>
                    <span class="text-gray-700">{{ $order->customer->payment_terms }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Order Total</span>
                    <span class="font-bold text-gray-900">INR {{ number_format($order->total_amount, 2) }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('sales.invoices.store') }}" class="p-6">
                @csrf
                <input type="hidden" name="sales_order_id" value="{{ $order->id }}">

                <p class="text-sm text-gray-600 mb-6">
                    This will generate an invoice for <strong>INR {{ number_format($order->total_amount, 2) }}</strong>
                    with due date calculated from the customer's <strong>{{ $order->customer->payment_terms }}</strong> payment terms.
                </p>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('sales.orders.show', $order) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</a>
                    <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition-colors shadow-sm">
                        Generate Invoice
                    </button>
                </div>
            </form>
        </div>
        @else
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <p class="text-sm text-gray-600">Please create an invoice from a specific dispatched sales order.</p>
        </div>
        @endif
    </div>

</x-layouts.app>
