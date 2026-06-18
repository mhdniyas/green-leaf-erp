<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">{{ isset($isUpdateRequest) && $isUpdateRequest ? 'Request Items' : 'Marketplace' }}</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $tomorrowDate->format('d F Y') }}</h2>
            <p class="mt-2 text-sm text-slate-600">Select products, add quantity or box packs, then submit the daily order from your cart before 9:30 PM.</p>
        </div>
        @if ($presets->isNotEmpty())
            <div class="flex items-center gap-2 mt-2 md:mt-0 shrink-0">
                <select
                    data-preset-select
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:outline-none"
                >
                    <option value="">Load Custom List...</option>
                    @foreach ($presets as $preset)
                        <option value="{{ $preset->id }}">{{ $preset->name }}</option>
                    @endforeach
                </select>
                <button type="button" data-apply-preset class="rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-bold text-white transition hover:bg-slate-800 active:scale-95">
                    Load
                </button>
            </div>
        @endif
    </div>

    @if (isset($isUpdateRequest) && $isUpdateRequest)
        <!-- Alert Banner at the top -->
        <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 flex gap-3 text-amber-800">
            <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="text-xs sm:text-sm">
                <h4 class="font-black text-amber-900">Cart Locked After Cutoff</h4>
                <p class="mt-1 font-medium leading-relaxed text-amber-800/95">Direct submission is closed. You can still change quantities, add products, or remove lines below and send an item request to the Purchase Manager for approval.</p>
            </div>
        </div>

        <form action="{{ route('requisitions.update-request', $tomorrowOrder->order_number) }}" method="POST" class="mt-5 space-y-5" id="shop-owner-order-form">
            @csrf
            
            <!-- Hidden master reason input to sync with drawers and fields -->
            <textarea name="reason" id="hidden-reason-input" class="hidden">{{ old('reason', $tomorrowOrder->state === 'update_requested' ? $tomorrowOrder->update_reason : '') }}</textarea>

            @include('shop-owner.orders.partials.product-selection-table', [
                'productsByCategory' => $productsByCategory,
                'frequentProducts' => $frequentProducts,
                'tomorrowOrder' => $tomorrowOrder,
                'yesterdayOrder' => $yesterdayOrder,
                'presets' => $presets,
                'isUpdateRequest' => true
            ])

            <!-- Change Note Area -->
            <div class="space-y-2.5 border-t border-slate-100 pt-5">
                <label for="visible-reason-page" class="block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Change Note / Reason</label>
                <textarea
                    id="visible-reason-page"
                    rows="3"
                    placeholder="Provide a reason for modifying this order (e.g. customer requested extra items)..."
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-amber-500 focus:outline-none transition"
                    required
                >{{ old('reason', $tomorrowOrder->state === 'update_requested' ? $tomorrowOrder->update_reason : '') }}</textarea>
            </div>

            <div class="flex flex-col gap-4 border-t border-slate-100 pt-5 md:flex-row md:items-center md:justify-between">
                @include('shop-owner.orders.partials.order-summary-card', ['order' => $tomorrowOrder, 'yesterdayOrder' => $yesterdayOrder, 'isDraft' => true])
                <button type="button" data-open-cart-submit class="rounded-xl bg-amber-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-amber-700 transition active:scale-95 duration-150">
                    Open Cart
                </button>
            </div>
        </form>
    @elseif ($tomorrowOrder && $tomorrowOrder->state === 'approved' && $purchaseOrdersLockedForTomorrow)
        <div class="mt-5 rounded-3xl border border-rose-200 bg-rose-50 p-5">
            <h3 class="text-base font-black text-rose-900">Order Update Locked</h3>
            <p class="mt-2 text-sm text-rose-800">This order can no longer be updated because goods receipt has already started for its linked purchase order.</p>
        </div>
    @elseif (! $cutoffPassed || $tomorrowOrder?->canEditDirectly() || ! $tomorrowOrder)
        @if ($cutoffPassed)
            <!-- Late Submission Alert Banner -->
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 flex gap-3 text-amber-800">
                <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="text-xs sm:text-sm">
                    <h4 class="font-black text-amber-900">Late Daily Order Submission</h4>
                    <p class="mt-1 font-medium leading-relaxed text-amber-800/95">The 9:30 PM cutoff deadline has passed. Submitting this cart will file it as a late item request pending Purchase Manager approval.</p>
                </div>
            </div>
        @endif

        <form action="{{ route('requisitions.store') }}" method="POST" class="mt-5 space-y-5" id="shop-owner-order-form">
            @csrf
            @include('shop-owner.orders.partials.product-selection-table', ['productsByCategory' => $productsByCategory, 'frequentProducts' => $frequentProducts, 'tomorrowOrder' => $tomorrowOrder, 'yesterdayOrder' => $yesterdayOrder])

            <div class="flex flex-col gap-4 border-t border-slate-100 pt-5 md:flex-row md:items-center md:justify-between">
                @include('shop-owner.orders.partials.order-summary-card', ['order' => $tomorrowOrder, 'yesterdayOrder' => $yesterdayOrder, 'isDraft' => true])
                <button type="button" data-open-cart-submit @class([
                    'rounded-xl px-6 py-3 text-sm font-bold text-white shadow-sm transition active:scale-95 duration-150 border-0 cursor-pointer',
                    'bg-amber-600 hover:bg-amber-700' => $cutoffPassed,
                    'bg-emerald-600 hover:bg-emerald-700' => ! $cutoffPassed,
                ])>
                    Open Cart
                </button>
            </div>
        </form>
    @endif
</section>
