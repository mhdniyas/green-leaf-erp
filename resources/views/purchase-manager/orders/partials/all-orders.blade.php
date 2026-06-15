<section data-order-panel="all" class="space-y-5">
    <div class="purchase-manager-panel p-5">
        <form method="GET" action="{{ route('purchasing.orders.index') }}" class="grid gap-4 md:grid-cols-4">
            <input type="hidden" name="tab" value="all">
            <div>
                <label for="supplier_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Supplier</label>
                <select id="supplier_id" name="supplier_id" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date_filter" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Period</label>
                <select id="date_filter" name="date_filter" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                    <option value="" @selected(! request()->filled('date_filter'))>All Time</option>
                    <option value="this_month" @selected(request('date_filter') === 'this_month')>This Month</option>
                    <option value="last_month" @selected(request('date_filter') === 'last_month')>Last Month</option>
                    <option value="custom" @selected(request('date_filter') === 'custom')>Custom Range</option>
                </select>
            </div>
            <div id="custom-date-inputs" @class(['grid gap-3 sm:grid-cols-2 md:col-span-2', 'hidden' => request('date_filter') !== 'custom'])>
                <div>
                    <label for="start_date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Start</label>
                    <input id="start_date" type="date" name="start_date" value="{{ request('start_date') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label for="end_date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">End</label>
                    <input id="end_date" type="date" name="end_date" value="{{ request('end_date') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                </div>
            </div>
            <div class="flex flex-wrap items-end gap-2 md:col-span-4">
                <x-purchase-manager.components.action-button type="submit" variant="primary">Apply Filters</x-purchase-manager.components.action-button>
                <x-purchase-manager.components.action-button :href="route('purchasing.orders.index')" variant="secondary">Reset</x-purchase-manager.components.action-button>
            </div>
        </form>
    </div>

    <div class="purchase-manager-panel overflow-hidden">
        @if ($allOrders->isEmpty())
            <div class="p-5">
                <x-purchase-manager.components.empty-state
                    title="No purchase orders found"
                    description="Adjust the filters or create a new purchase order to start the buying cycle."
                    :actionHref="route('purchasing.orders.create')"
                    actionLabel="Create Order"
                />
            </div>
        @else
            <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                <table class="min-w-[960px] text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">PO Number</th>
                            <th class="px-5 py-4">Date</th>
                            <th class="px-5 py-4">Supplier</th>
                            <th class="px-5 py-4 text-right">Amount</th>
                            <th class="px-5 py-4 text-center">Status</th>
                            <th class="px-5 py-4 text-center">Delivery</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach ($allOrders as $order)
                            <tr class="bg-white">
                                <td class="px-5 py-4 font-mono font-bold text-cyan-700">
                                    <a href="{{ route('purchasing.orders.show', $order) }}">{{ $order->po_number }}</a>
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $order->order_date->format('d M Y') }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-900">{{ $order->supplier?->name ?? '—' }}</td>
                                <td class="px-5 py-4 text-right font-bold text-slate-950">₹{{ number_format($order->total_amount, 2) }}</td>
                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] {{ $order->status->color() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    @if ($order->status->value === 'draft')
                                        <x-purchase-manager.components.status-badge label="Awaiting Approval" tone="slate" />
                                    @elseif (in_array($order->status->value, ['received', 'closed']))
                                        <x-purchase-manager.components.status-badge label="Delivered" tone="emerald" />
                                    @elseif ($order->goodsReceiveds->isNotEmpty())
                                        <x-purchase-manager.components.status-badge label="Partially Received" tone="cyan" />
                                    @else
                                        <x-purchase-manager.components.status-badge label="In Transit" tone="blue" />
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('purchasing.orders.show', $order) }}" class="font-bold text-cyan-700">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($allOrders->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $allOrders->links() }}
                </div>
            @endif
        @endif
    </div>
</section>
