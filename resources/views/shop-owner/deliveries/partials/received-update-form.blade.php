<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <h3 class="text-lg font-black text-slate-950">Received Update</h3>
    <p class="mt-2 text-sm text-slate-600">Use the delivery verification flow to submit actual received quantities.</p>

    <div class="mt-5 space-y-3">
        @foreach ($order->items as $item)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex items-start justify-between gap-3">
                    <p class="font-bold text-slate-900">{{ $item->product->name }}</p>
                    @include('shop-owner.components.status-badge', ['label' => $item->warehouseWorkflowLabel(), 'tone' => $item->warehouseWorkflowTone()])
                </div>
                <p class="mt-1 text-sm text-slate-600">Approved: {{ number_format((float) ($item->approved_qty ?? 0), 2) }} {{ $item->unit }}</p>
                <p class="text-sm text-slate-600">Received: {{ number_format((float) ($item->delivered_qty ?? 0), 2) }} {{ $item->unit }}</p>
            </div>
        @endforeach
    </div>

    @if ($order->is_allocation_completed && ! $order->is_delivered)
        <div class="mt-5">
            @include('shop-owner.components.action-button', ['href' => route('shop-owner.deliveries.show', $order->order_number), 'label' => 'Open Delivery Update Form', 'classes' => 'bg-indigo-600 text-white'])
        </div>
    @endif
</section>
