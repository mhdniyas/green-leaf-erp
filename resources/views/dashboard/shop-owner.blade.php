@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $shopName = $user->shop?->name ?? 'Shop';
    $cutoffService = app(\App\Services\Purchasing\PurchaserBusinessDayService::class);
    $cutoffTime = $cutoffService->rolloverStartsAt(now());
    $cutoffLabel = $cutoffService->cutoffLabel();
    $tomorrowDate = now()->addDay();
    $orderForm = collect();

    foreach (($productsByCategory ?? collect()) as $category) {
        foreach ($category->products as $product) {
            $existingQuantity = (float) optional($tomorrowOrder?->items->firstWhere('product_id', $product->id))->requested_qty;
            $suggestedQuantity = (float) optional($yesterdayOrder?->items->firstWhere('product_id', $product->id))->requested_qty;

            $orderForm->push([
                'category' => $category->name,
                'product' => $product,
                'requested_qty' => old("items.{$product->sku}", $existingQuantity > 0 ? $existingQuantity : $suggestedQuantity),
                'suggested_qty' => $suggestedQuantity,
            ]);
        }
    }

    $pendingApprovalCount = ($recentRequisitions ?? collect())->whereIn('state', ['submitted', 'update_requested'])->count();
    $deliveredCount = ($recentRequisitions ?? collect())->where('is_delivered', true)->count();
    $pendingDeliveryCount = ($recentRequisitions ?? collect())
        ->filter(fn ($order) => $order->is_allocation_completed && ! $order->is_delivered)
        ->count();
@endphp

<x-layouts.app title="Shop Dashboard">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[linear-gradient(135deg,#0f172a_0%,#14532d_100%)] px-6 py-7 text-white shadow-xl">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-2">
                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-[11px] font-black uppercase tracking-[0.2em] text-emerald-200">
                        Daily Progress
                    </span>
                    <h1 class="text-3xl font-black tracking-tight">{{ $shopName }}</h1>
                    <p class="max-w-2xl text-sm text-emerald-50/80">
                        Submit tomorrow's order, track manager approval, verify today's delivery, and review your daily order history from one place.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/8 px-4 py-3">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-100/80">Pending Approval</p>
                        <p class="mt-2 text-2xl font-black">{{ $pendingApprovalCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/8 px-4 py-3">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-100/80">Pending Delivery</p>
                        <p class="mt-2 text-2xl font-black">{{ $pendingDeliveryCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/8 px-4 py-3">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-100/80">Delivered Orders</p>
                        <p class="mt-2 text-2xl font-black">{{ $deliveredCount }}</p>
                    </div>
                </div>
            </div>
        </section>

        <nav class="flex flex-wrap gap-3">
            <a href="#tomorrow-order" class="rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-800">Tomorrow's Order</a>
            <a href="#approval-status" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Approval Status</a>
            <a href="#today-delivery" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Today's Delivery</a>
            <a href="#daily-report" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Daily Report</a>
        </nav>

        @if (session('success'))
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-3xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
                <p class="font-black uppercase tracking-wider text-[11px]">Please correct the highlighted issue.</p>
                <p class="mt-1">{{ $errors->first() }}</p>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.65fr_1fr]">
            <section id="tomorrow-order" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-slate-900">Tomorrow's Order</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Delivery date: <span class="font-bold text-slate-700">{{ $tomorrowDate->format('d F Y') }}</span>
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Cutoff Time</p>
                        <p class="mt-1 font-bold text-slate-900">{{ $cutoffLabel }}</p>
                        <p class="text-xs text-slate-500">
                            {{ now()->greaterThan($cutoffTime) ? 'Cutoff passed for new submission.' : 'Submission window is open.' }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Current Status</p>
                            @if ($tomorrowOrder)
                                <p class="mt-2 text-lg font-black text-slate-900">{{ $tomorrowOrder->displayStateLabel() }}</p>
                                <p class="text-sm text-slate-500">
                                    {{ $tomorrowOrder->items->count() }} items, {{ number_format((float) $tomorrowOrder->items->sum('requested_qty'), 2) }} total qty
                                </p>
                            @else
                                <p class="mt-2 text-lg font-black text-slate-900">Not Submitted</p>
                                <p class="text-sm text-slate-500">Prepare tomorrow's order below and submit it before cutoff.</p>
                            @endif
                        </div>

                        @if ($tomorrowOrder)
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('requisitions.show', $tomorrowOrder->order_number) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-800">
                                    View Order
                                </a>
                                @if ($tomorrowOrder->canEditDirectly())
                                    <a href="{{ route('requisitions.edit', $tomorrowOrder->order_number) }}" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-800">
                                        Edit Before Cutoff
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                @if ($tomorrowOrder && ! $tomorrowOrder->canEditDirectly() && in_array($tomorrowOrder->state, ['submitted', 'update_requested'], true))
                    <div class="mt-5 rounded-3xl border border-amber-200 bg-amber-50 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-base font-black text-amber-900">Request Order Modification</h3>
                                <p class="mt-1 text-sm text-amber-800">
                                    The cutoff has passed. You can no longer edit quantities directly, but you can request a change from the Purchase Manager.
                                </p>
                            </div>
                        </div>

                        <form action="{{ route('requisitions.update-request', $tomorrowOrder->order_number) }}" method="POST" class="mt-4 space-y-3">
                            @csrf
                            <label for="reason" class="block text-[11px] font-black uppercase tracking-[0.18em] text-amber-900">Reason for Change</label>
                            <textarea id="reason" name="reason" rows="3" class="w-full rounded-2xl border border-amber-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-amber-400 focus:outline-none">{{ old('reason', $tomorrowOrder->state === 'update_requested' ? $tomorrowOrder->update_reason : '') }}</textarea>
                            <button type="submit" class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-amber-700">
                                Request Modification
                            </button>
                        </form>
                    </div>
                @elseif (! $tomorrowOrder || $tomorrowOrder->canEditDirectly())
                    <form action="{{ route('requisitions.store') }}" method="POST" class="mt-5 space-y-5">
                        @csrf
                        <div class="grid gap-5">
                            @foreach (($productsByCategory ?? collect()) as $category)
                                <section class="rounded-3xl border border-slate-200">
                                    <div class="border-b border-slate-100 bg-slate-50 px-5 py-3">
                                        <h3 class="text-sm font-black uppercase tracking-[0.18em] text-slate-700">{{ $category->name }}</h3>
                                    </div>

                                    <div class="divide-y divide-slate-100">
                                        @foreach ($category->products as $product)
                                            @php
                                                $existingItem = $tomorrowOrder?->items->firstWhere('product_id', $product->id);
                                                $yesterdayItem = $yesterdayOrder?->items->firstWhere('product_id', $product->id);
                                                $quantityValue = old("items.{$product->sku}", $existingItem?->requested_qty ?? $yesterdayItem?->requested_qty ?? '');
                                            @endphp
                                            <div class="grid gap-3 px-5 py-4 md:grid-cols-[1.7fr_0.7fr_0.8fr_0.8fr] md:items-center">
                                                <div>
                                                    <p class="text-sm font-bold text-slate-900">{{ $product->name }}</p>
                                                    <p class="text-xs text-slate-500">{{ $product->sku }}</p>
                                                </div>
                                                <div class="text-sm">
                                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Yesterday</p>
                                                    <p class="mt-1 font-bold text-slate-700">{{ number_format((float) ($yesterdayItem?->requested_qty ?? 0), 2) }} {{ $product->unit }}</p>
                                                </div>
                                                <div class="text-sm">
                                                    <label for="item-{{ $product->id }}" class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Tomorrow Qty</label>
                                                    <div class="mt-1 flex items-center gap-2">
                                                        <input id="item-{{ $product->id }}" type="number" step="0.01" min="0" name="items[{{ $product->sku }}]" value="{{ $quantityValue }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-right font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                                        <span class="text-xs font-bold uppercase text-slate-500">{{ $product->unit }}</span>
                                                    </div>
                                                </div>
                                                <div class="text-sm">
                                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Suggested</p>
                                                    <p class="mt-1 font-bold text-slate-700">{{ number_format((float) ($yesterdayItem?->requested_qty ?? 0), 2) }} {{ $product->unit }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-slate-500">
                                Fill only the items needed for tomorrow. Use `0` or leave blank to skip a product.
                            </p>
                            <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">
                                {{ $tomorrowOrder ? 'Update Tomorrow Order' : 'Submit Tomorrow Order' }}
                            </button>
                        </div>
                    </form>
                @else
                    <div class="mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
                        This order is locked because it has already been reviewed. Use the delivery section below to continue the workflow.
                    </div>
                @endif
            </section>

            <div class="space-y-6">
                <section id="approval-status" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black tracking-tight text-slate-900">Approval Status</h2>
                    @if ($tomorrowOrder)
                        <div class="mt-4 space-y-3">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Manager Status</p>
                                <p class="mt-2 text-lg font-black text-slate-900">{{ $tomorrowOrder->displayStateLabel() }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Approved Quantity</p>
                                <p class="mt-2 text-lg font-black text-slate-900">{{ number_format((float) $tomorrowOrder->items->sum('approved_qty'), 2) }}</p>
                            </div>
                            <a href="{{ route('requisitions.show', $tomorrowOrder->order_number) }}" class="inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">
                                Open Approval Details
                            </a>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-500">No tomorrow order has been submitted yet.</p>
                    @endif
                </section>

                <section id="today-delivery" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black tracking-tight text-slate-900">Today's Delivery</h2>
                    @if ($todayOrder)
                        <div class="mt-4 space-y-3">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Delivery Status</p>
                                <p class="mt-2 text-lg font-black text-slate-900">{{ str(($todayOrder->delivery_status ?? ($todayOrder->is_delivered ? 'delivered' : 'pending_delivery')))->replace('_', ' ')->title() }}</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 px-4 py-3">
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Items</p>
                                    <p class="mt-2 text-lg font-black text-slate-900">{{ $todayOrder->items->count() }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 px-4 py-3">
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shortage Value</p>
                                    <p class="mt-2 text-lg font-black text-red-600">Rs. {{ number_format((float) $todayOrder->total_shortage_value, 2) }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('requisitions.show', $todayOrder->order_number) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-800">
                                    View Delivery
                                </a>
                                @if ($todayOrder->is_allocation_completed && ! $todayOrder->is_delivered)
                                    <a href="{{ route('requisitions.delivery.show', $todayOrder->order_number) }}" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">
                                        Check Delivery Now
                                    </a>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-500">No delivery is scheduled for today yet.</p>
                    @endif
                </section>
            </div>
        </div>

        <section id="daily-report" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-black tracking-tight text-slate-900">Daily Report</h2>
                    <p class="mt-1 text-sm text-slate-500">Track the latest shop orders, delivery outcomes, and payment progress.</p>
                </div>
                <a href="{{ route('inventory.deliveries.dashboard') }}" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-bold text-slate-700">
                    Open Delivery Dashboard
                </a>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                            <th class="py-3 pr-4">Date</th>
                            <th class="py-3 pr-4">Order</th>
                            <th class="py-3 pr-4 text-right">Items</th>
                            <th class="py-3 pr-4">Order Status</th>
                            <th class="py-3 pr-4">Delivery</th>
                            <th class="py-3 pr-4">Payment</th>
                            <th class="py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                        @forelse (($recentRequisitions ?? collect()) as $order)
                            <tr class="hover:bg-slate-50/70">
                                <td class="py-3 pr-4 font-bold text-slate-900">{{ $order->business_date->format('d M Y') }}</td>
                                <td class="py-3 pr-4">
                                    <span class="font-mono text-xs font-bold text-slate-600">{{ $order->order_number }}</span>
                                </td>
                                <td class="py-3 pr-4 text-right font-bold text-slate-900">{{ $order->items->count() }}</td>
                                <td class="py-3 pr-4">{{ str($order->state)->replace('_', ' ')->title() }}</td>
                                <td class="py-3 pr-4">{{ str(($order->delivery_status ?? ($order->is_delivered ? 'delivered' : 'pending_delivery')))->replace('_', ' ')->title() }}</td>
                                <td class="py-3 pr-4">{{ str($order->payment_status ?? 'unpaid')->replace('_', ' ')->title() }}</td>
                                <td class="py-3 text-right">
                                    <a href="{{ route('requisitions.show', $order->order_number) }}" class="font-bold text-emerald-700 hover:text-emerald-900">
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-sm text-slate-500">No daily report data is available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
