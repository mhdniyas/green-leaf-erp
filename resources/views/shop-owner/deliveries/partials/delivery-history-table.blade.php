@if ($deliveries->isNotEmpty())
    <div class="space-y-3 md:hidden">
        @foreach ($deliveries as $order)
            <article class="rounded-3xl border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-black text-slate-900">{{ $order->business_date->format('d M Y') }}</p>
                        <p class="mt-1 font-mono text-xs font-bold text-slate-600">{{ $order->order_number }}</p>
                    </div>
                    <a href="{{ route('shop-owner.deliveries.show', $order->order_number) }}" class="text-sm font-bold text-emerald-700">Open</a>
                </div>
                <div class="mt-4">
                    @include('shop-owner.deliveries.partials.delivery-status-badge', ['order' => $order])
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Shortage</p>
                        <p class="mt-1 text-sm font-bold text-red-600">Rs. {{ number_format((float) $order->total_shortage_value, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Warehouse</p>
                        <p class="mt-1 text-sm font-bold text-slate-700">{{ $order->warehouseWorkflowLabel() }}</p>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="hidden overflow-x-auto md:block">
        <table class="min-w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-100 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <th class="py-3 pr-4">Date</th>
                    <th class="py-3 pr-4">Order</th>
                    <th class="py-3 pr-4">Status</th>
                    <th class="py-3 pr-4">Warehouse</th>
                    <th class="py-3 pr-4 text-right">Shortage</th>
                    <th class="py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                @foreach ($deliveries as $order)
                    <tr>
                        <td class="py-4 pr-4 font-bold text-slate-900">{{ $order->business_date->format('d M Y') }}</td>
                        <td class="py-4 pr-4 font-mono text-xs font-bold text-slate-600">{{ $order->order_number }}</td>
                        <td class="py-4 pr-4">@include('shop-owner.deliveries.partials.delivery-status-badge', ['order' => $order])</td>
                        <td class="py-4 pr-4 font-bold text-slate-700">{{ $order->warehouseWorkflowLabel() }}</td>
                        <td class="py-4 pr-4 text-right font-bold text-red-600">Rs. {{ number_format((float) $order->total_shortage_value, 2) }}</td>
                        <td class="py-4 text-right">
                            <a href="{{ route('shop-owner.deliveries.show', $order->order_number) }}" class="font-bold text-emerald-700 hover:text-emerald-900">Open</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($deliveries instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="mt-5">{{ $deliveries->links() }}</div>
    @endif
@else
    @include('shop-owner.components.empty-state', ['title' => 'No deliveries yet', 'description' => 'Priced invoices, allocated orders, and delivered orders will appear here.'])
@endif
