<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Delivery Date</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $tomorrowDate->format('d F Y') }}</h2>
            <p class="mt-2 text-sm text-slate-600">Use yesterday’s order as the suggested baseline and submit before 9:30 PM.</p>
        </div>
        @include('shop-owner.orders.partials.order-type-selector', ['presets' => $presets])
    </div>

    @if ($tomorrowOrder && ! $tomorrowOrder->canEditDirectly() && (in_array($tomorrowOrder->state, ['submitted', 'update_requested'], true) || ($tomorrowOrder->state === 'approved' && ! $purchaseOrdersGeneratedForTomorrow)))
        <div class="mt-5 rounded-3xl border border-amber-200 bg-amber-50 p-5">
            <h3 class="text-base font-black text-amber-900">Order Locked After Cutoff</h3>
            <p class="mt-2 text-sm text-amber-800">Direct submission is closed, but you can still change quantities, add products, or remove lines and send the revised request to the Purchase Manager before purchase orders are generated.</p>
            <form action="{{ route('requisitions.update-request', $tomorrowOrder->order_number) }}" method="POST" class="mt-4 space-y-5">
                @csrf
                @include('shop-owner.orders.partials.product-selection-table', ['productsByCategory' => $productsByCategory, 'frequentProducts' => $frequentProducts, 'tomorrowOrder' => $tomorrowOrder, 'yesterdayOrder' => $yesterdayOrder])
                <div class="space-y-3">
                    <label for="reason" class="block text-[11px] font-black uppercase tracking-[0.18em] text-amber-900">Change Note</label>
                    <textarea id="reason" name="reason" rows="3" class="w-full rounded-2xl border border-amber-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-amber-400 focus:outline-none">{{ old('reason', $tomorrowOrder->update_reason) }}</textarea>
                </div>
                <div class="flex flex-col gap-4 border-t border-amber-200 pt-5 md:flex-row md:items-center md:justify-between">
                    @include('shop-owner.orders.partials.order-summary-card', ['order' => $tomorrowOrder, 'yesterdayOrder' => $yesterdayOrder, 'isDraft' => true])
                    <button type="submit" class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white">Submit Modified Order Request</button>
                </div>
            </form>
        </div>
    @elseif (! $cutoffPassed || $tomorrowOrder?->canEditDirectly() || ! $tomorrowOrder)
        <form action="{{ route('requisitions.store') }}" method="POST" class="mt-5 space-y-5">
            @csrf
            @include('shop-owner.orders.partials.product-selection-table', ['productsByCategory' => $productsByCategory, 'frequentProducts' => $frequentProducts, 'tomorrowOrder' => $tomorrowOrder, 'yesterdayOrder' => $yesterdayOrder])

            <div class="flex flex-col gap-4 border-t border-slate-100 pt-5 md:flex-row md:items-center md:justify-between">
                @include('shop-owner.orders.partials.order-summary-card', ['order' => $tomorrowOrder, 'yesterdayOrder' => $yesterdayOrder, 'isDraft' => true])
                <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">
                    {{ $tomorrowOrder ? 'Resubmit Tomorrow Order' : 'Submit Tomorrow Order' }}
                </button>
            </div>
        </form>
    @endif
</section>
