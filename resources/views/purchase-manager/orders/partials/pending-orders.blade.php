<section data-order-panel="pending" class="hidden">
    <div class="purchase-manager-panel overflow-hidden">
        @if ($pendingOrders->isEmpty())
            <div class="p-5">
                <x-purchase-manager.components.empty-state
                    title="No pending approvals"
                    description="All draft purchase orders have already been reviewed."
                />
            </div>
        @else
            <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                <table class="min-w-[760px] text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">PO Number</th>
                            <th class="px-5 py-4">Supplier</th>
                            <th class="px-5 py-4">Requested By</th>
                            <th class="px-5 py-4">Date</th>
                            <th class="px-5 py-4 text-right">Amount</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach ($pendingOrders as $order)
                            <tr>
                                <td class="px-5 py-4 font-mono font-bold text-cyan-700">{{ $order->po_number }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-900">{{ $order->supplier?->name ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $order->createdBy?->name ?? 'System' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $order->order_date->format('d M Y') }}</td>
                                <td class="px-5 py-4 text-right font-bold text-slate-950">₹{{ number_format($order->total_amount, 2) }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('purchasing.orders.approve', $order) }}">
                                            @csrf
                                            <input type="hidden" name="remarks" value="Stock Required">
                                            <x-purchase-manager.components.action-button type="submit" variant="success">Approve</x-purchase-manager.components.action-button>
                                        </form>
                                        <form method="POST" action="{{ route('purchasing.orders.reject', $order) }}">
                                            @csrf
                                            <input type="hidden" name="remarks" value="Duplicate Order">
                                            <x-purchase-manager.components.action-button type="submit" variant="soft-danger">Reject</x-purchase-manager.components.action-button>
                                        </form>
                                        <x-purchase-manager.components.action-button :href="route('purchasing.orders.show', $order)" variant="secondary">Review</x-purchase-manager.components.action-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
