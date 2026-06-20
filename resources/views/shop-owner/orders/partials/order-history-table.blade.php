@if ($orders instanceof \Illuminate\Contracts\Pagination\Paginator ? $orders->isNotEmpty() : $orders->isNotEmpty())
    <div class="space-y-4">
        @foreach ($orders as $order)
            @php
                $hasHistory = $order->reviewed_at !== null || $order->revisions->whereIn('status', ['applied', 'rejected', 'blocked'])->isNotEmpty();
            @endphp
            <article class="rounded-[2rem] border border-slate-200/80 bg-slate-50/70 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-sm font-black text-slate-900">{{ $order->business_date->format('d M Y') }}</p>
                        <p class="mt-1 font-mono text-xs font-bold text-slate-600">{{ $order->order_number }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            @include('shop-owner.orders.partials.order-status-badge', ['order' => $order])
                            <span class="text-sm font-semibold text-slate-600">{{ $order->items->count() }} items</span>
                            <span class="text-sm font-semibold text-slate-600">{{ str(($order->delivery_status ?? ($order->is_delivered ? 'delivered' : 'pending_delivery')))->replace('_', ' ')->title() }}</span>
                        </div>
                    </div>
                    <a href="{{ route('shop-owner.orders.show', $order->order_number) }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-900">Open</a>
                </div>
                @if ($hasHistory)
                    <div class="mt-4 border-t border-slate-200/80 pt-4">
                        @include('requisitions.partials.review-history', ['order' => $order])
                    </div>
                @else
                    <p class="mt-5 text-sm font-medium text-slate-500">No purchase manager review history yet for this cart.</p>
                @endif
            </article>
        @endforeach
    </div>

    @if ($orders instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="mt-5">{{ $orders->links() }}</div>
    @endif
@else
    @include('shop-owner.components.empty-state', ['title' => 'No approval history', 'description' => 'Submitted daily carts will appear here once the shop starts using the marketplace flow.'])
@endif
