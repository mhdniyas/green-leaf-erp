@if ($orders instanceof \Illuminate\Contracts\Pagination\Paginator ? $orders->isNotEmpty() : $orders->isNotEmpty())
    {{-- Mobile View: Strict Single-Row Cards --}}
    <div class="space-y-1.5 md:hidden">
        @foreach ($orders as $order)
            @php
                $tone = match ($order->state) {
                    'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'submitted', 'update_requested' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'rejected' => 'bg-red-50 text-red-700 border-red-200',
                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                };
            @endphp
            <article class="flex items-center justify-between gap-1.5 rounded-lg border border-slate-200 bg-white p-2 shadow-xs transition hover:border-slate-300">
                {{-- Date & Order Number --}}
                <div class="min-w-0 flex-1 flex items-center gap-1.5">
                    <span class="text-[11px] font-black text-slate-900 whitespace-nowrap">{{ $order->business_date->format('d M') }}</span>
                    <span class="truncate font-mono text-[9px] font-semibold text-slate-400 max-w-[65px] sm:max-w-none">{{ $order->order_number }}</span>
                    <span class="hidden text-[10px] text-slate-500 sm:inline">· {{ $order->items->count() }} items</span>
                </div>

                {{-- Status Badge --}}
                <div class="shrink-0">
                    <span class="inline-flex items-center rounded-full border px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wider whitespace-nowrap {{ $tone }}">
                        {{ $order->displayStateLabel() }}
                    </span>
                </div>

                {{-- Action Link --}}
                <div class="shrink-0">
                    <a href="{{ route('shop-owner.orders.show', $order->order_number) }}" class="inline-flex items-center justify-center rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 hover:bg-emerald-100 transition-colors">
                        Open &rarr;
                    </a>
                </div>
            </article>
        @endforeach
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden overflow-x-auto rounded-xl border border-slate-200 md:block">
        <table class="min-w-full border-collapse text-left text-xs whitespace-nowrap">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                    <th class="px-3 py-2.5">Date</th>
                    <th class="px-3 py-2.5">Order Ref</th>
                    <th class="px-3 py-2.5">Items</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5">Delivery Status</th>
                    <th class="px-3 py-2.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
                @foreach ($orders as $order)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-3 py-2 font-bold text-slate-900">{{ $order->business_date->format('d M Y') }}</td>
                        <td class="px-3 py-2 font-mono text-xs font-bold text-slate-600">{{ $order->order_number }}</td>
                        <td class="px-3 py-2 font-semibold text-slate-700">{{ $order->items->count() }} items</td>
                        <td class="px-3 py-2">
                            @include('shop-owner.orders.partials.order-status-badge', ['order' => $order])
                        </td>
                        <td class="px-3 py-2 font-bold text-slate-700">
                            {{ str(($order->delivery_status ?? ($order->is_delivered ? 'delivered' : 'pending_delivery')))->replace('_', ' ')->title() }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('shop-owner.orders.show', $order->order_number) }}" class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:text-emerald-900">
                                Open &rarr;
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination Links --}}
    @if ($orders instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="mt-3 border-t border-slate-100 pt-2">
            {{ $orders->links() }}
        </div>
    @endif
@else
    @include('shop-owner.components.empty-state', ['title' => 'No approval history', 'description' => 'Submitted daily carts will appear here once the shop starts using the marketplace flow.'])
@endif
