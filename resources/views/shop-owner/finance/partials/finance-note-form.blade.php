<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-xl font-black text-slate-950">Finance Note</h2>
    <p class="mt-2 text-sm text-slate-600">Finance notes are recorded during delivery check-in and shown here for reference.</p>

    <div class="mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-5">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Current Note</p>
        <p class="mt-3 text-sm text-slate-700">{{ $order->finance_note ?: 'No finance note recorded for this order.' }}</p>
    </div>

    @if ($order->is_allocation_completed && ! $order->is_delivered)
        <div class="mt-5">
            @include('shop-owner.components.action-button', ['href' => route('shop-owner.deliveries.show', $order->order_number), 'label' => 'Record Through Delivery Check', 'classes' => 'bg-indigo-600 text-white'])
        </div>
    @endif
</section>
