<x-layouts.inventory title="Delivery Operations Dashboard">
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('inventory.deliveries.dashboard', ['date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d')]) }}" class="p-2 bg-white rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors shadow-sm" title="Previous Day">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </a>
            
            <form id="date-form" method="GET" action="{{ route('inventory.deliveries.dashboard') }}" class="flex items-center gap-2">
                <input id="date-select" type="date" name="date" value="{{ $date }}" onchange="document.getElementById('date-form').submit();"
                       class="border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white shadow-sm">
            </form>

            <a href="{{ route('inventory.deliveries.dashboard', ['date' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d')]) }}" class="p-2 bg-white rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors shadow-sm" title="Next Day">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>

            <a href="{{ route('inventory.deliveries.dashboard', ['date' => \Carbon\Carbon::today()->format('Y-m-d')]) }}" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-bold hover:bg-emerald-100 transition-colors shadow-sm">
                Today
            </a>
        </div>
    </x-slot:actions>

    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Dashboard Intro -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">Delivery Operations Dashboard</h1>
                    <p class="text-xs text-slate-500 mt-1">Run the warehouse flow in order: receive supplier goods, submit GRN updates, pack approved shop products, move orders to transit, then complete delivery check-in.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3.5 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Live data refreshes every 30s
                    </span>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3.5 py-1 text-xs font-bold text-slate-700 border border-slate-200">
                        Date: {{ \Carbon\Carbon::parse($date)->format('d F Y') }}
                    </span>
                    <span class="inline-flex items-center rounded-full bg-white px-3.5 py-1 text-xs font-bold text-slate-700 border border-slate-200">
                        Last update:
                        <span id="delivery-dashboard-last-updated" class="ml-1 text-slate-900">
                            {{ $lastUpdatedAt ? $lastUpdatedAt->setTimezone(config('app.timezone'))->format('Y-m-d h:i:s A') : 'No activity yet' }}
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[1.3fr_0.7fr] gap-6">
            <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Daily Warehouse Progress</p>
                        <h2 class="mt-2 text-lg font-black text-slate-900">What the warehouse team needs to do today</h2>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 border border-slate-200">
                        {{ $totalOrdersCount }} shop orders
                    </span>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">1. Receive Goods</p>
                        <p class="mt-3 text-3xl font-black text-slate-900">{{ $receiveQueueCount }}</p>
                        <p class="mt-2 text-xs font-semibold text-slate-500">purchase orders still waiting for warehouse receipt updates.</p>
                    </article>
                    <article class="rounded-3xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">GRN Approval</p>
                        <p class="mt-3 text-3xl font-black text-amber-900">{{ $pendingGrnApprovalCount }}</p>
                        <p class="mt-2 text-xs font-semibold text-amber-700">submitted GRN reports are waiting for purchase manager approval.</p>
                    </article>
                    <article class="rounded-3xl border border-cyan-200 bg-cyan-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-cyan-700">2. Packing</p>
                        <p class="mt-3 text-3xl font-black text-cyan-900">{{ $packingCount }}</p>
                        <p class="mt-2 text-xs font-semibold text-cyan-700">shop orders are being packed or allocated for dispatch.</p>
                    </article>
                    <article class="rounded-3xl border border-indigo-200 bg-indigo-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-indigo-700">3. Transit</p>
                        <p class="mt-3 text-3xl font-black text-indigo-900">{{ $inTransitCount }}</p>
                        <p class="mt-2 text-xs font-semibold text-indigo-700">shop orders are loaded and moving toward final delivery.</p>
                    </article>
                </div>
            </section>

            <section class="bg-slate-950 rounded-3xl border border-slate-900 shadow-sm p-6 text-white">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-300">Ease Access</p>
                <h2 class="mt-2 text-lg font-black">Open the correct warehouse board fast</h2>
                <div class="mt-5 space-y-3">
                    <a href="{{ route('inventory.sorting.checklist', ['date' => $date]) }}" class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 transition hover:border-cyan-300/40 hover:bg-white/10">
                        <div>
                            <p class="text-sm font-black text-white">Receive Goods & GRN</p>
                            <p class="mt-1 text-xs font-semibold text-slate-300">Check supplier deliveries, receive quantities, and send GRN updates.</p>
                        </div>
                        <span class="rounded-full bg-cyan-400 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-950">Open</span>
                    </a>
                    <a href="{{ route('inventory.sorting.shop-orders', ['date' => $date]) }}" class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 transition hover:border-cyan-300/40 hover:bg-white/10">
                        <div>
                            <p class="text-sm font-black text-white">Shop Dispatch Cards</p>
                            <p class="mt-1 text-xs font-semibold text-slate-300">Pack approved products shop by shop and mark items in transit.</p>
                        </div>
                        <span class="rounded-full bg-cyan-400 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-950">Open</span>
                    </a>
                    <a href="#dispatch-status-board" class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 transition hover:border-cyan-300/40 hover:bg-white/10">
                        <div>
                            <p class="text-sm font-black text-white">Load & Delivery Check-in</p>
                            <p class="mt-1 text-xs font-semibold text-slate-300">Review dispatch readiness, delivery status, shortages, and cash updates.</p>
                        </div>
                        <span class="rounded-full bg-cyan-400 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-950">View</span>
                    </a>
                    <a href="{{ route('inventory.daily-close.index', ['date' => $date]) }}" class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 transition hover:border-cyan-300/40 hover:bg-white/10">
                        <div>
                            <p class="text-sm font-black text-white">Daily Inventory Close</p>
                            <p class="mt-1 text-xs font-semibold text-slate-300">Remove wastage, carry over stock, and resolve negative product notes.</p>
                        </div>
                        <span class="rounded-full bg-cyan-400 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-950">Close</span>
                    </a>
                </div>
            </section>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="{{ route('inventory.daily-close.index', ['date' => $date]) }}" class="rounded-3xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-rose-700">Negative Stock</p>
                <p class="mt-2 text-3xl font-black text-rose-950">{{ $negativeProductCount }}</p>
                <p class="mt-1 text-xs font-bold text-rose-700">products need a discrepancy note or purchase correction.</p>
            </a>
            <a href="{{ route('inventory.daily-close.index', ['date' => $date]) }}" class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">Below Buffer</p>
                <p class="mt-2 text-3xl font-black text-amber-950">{{ $belowBufferProductCount }}</p>
                <p class="mt-1 text-xs font-bold text-amber-700">products are below the configured buffer quantity.</p>
            </a>
            <a href="{{ route('inventory.daily-close.index', ['date' => $date]) }}" class="rounded-3xl border border-cyan-200 bg-cyan-50 p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-cyan-700">Carryover Products</p>
                <p class="mt-2 text-3xl font-black text-cyan-950">{{ $carryoverProductCount }}</p>
                <p class="mt-1 text-xs font-bold text-cyan-700">products can be retained during daily close.</p>
            </a>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Approved For Warehouse</span>
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="text-2xl font-black text-slate-900">{{ $warehouseApprovedCount }}</span>
                        <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-lg border border-slate-200">
                            waiting to pack
                        </span>
                    </div>
                    @php
                        $warehouseApprovedPercentage = $totalOrdersCount > 0 ? ($warehouseApprovedCount / $totalOrdersCount) * 100 : 0;
                    @endphp
                    <div class="w-full bg-slate-100 h-2 rounded-full mt-4 overflow-hidden">
                        <div class="h-full bg-slate-500 rounded-full transition-all duration-300" style="width: {{ $warehouseApprovedPercentage }}%;"></div>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-50">
                    <span>Total approved: <strong class="text-slate-800">{{ $totalOrdersCount }}</strong></span>
                    <span>Still to start: <strong class="text-slate-800">{{ $awaitingAllocationCount }}</strong></span>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Packing & Allocation</span>
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="text-2xl font-black text-cyan-700">{{ $packingCount }}</span>
                        <span class="text-xs font-bold text-cyan-700 bg-cyan-50 px-2 py-0.5 rounded-lg border border-cyan-100">
                            active packing
                        </span>
                    </div>
                    @php
                        $packingPercentage = $totalOrdersCount > 0 ? ($packingCount / $totalOrdersCount) * 100 : 0;
                    @endphp
                    <div class="w-full bg-slate-100 h-2 rounded-full mt-4 overflow-hidden">
                        <div class="h-full bg-cyan-500 rounded-full" style="width: {{ $packingPercentage }}%;"></div>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-50">
                    <span>Finalized sheets: <strong class="text-slate-800">{{ $allocationCompletedCount }}</strong></span>
                    <span>In progress: <strong class="text-slate-800">{{ max($packingCount, 0) }}</strong></span>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Transit & Loading</span>
                    <div class="flex items-baseline justify-between mt-1">
                        @php
                            $inTransitPercentage = $totalOrdersCount > 0 ? ($inTransitCount / $totalOrdersCount) * 100 : 0;
                        @endphp
                        <span class="text-2xl font-black text-indigo-700">
                            {{ $inTransitCount }}
                        </span>
                        <span class="text-xs font-black uppercase tracking-wider border rounded-lg px-2 py-0.5 bg-indigo-50 text-indigo-700 border-indigo-100">
                            moving now
                        </span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full mt-4 overflow-hidden">
                        <div class="h-full bg-indigo-500 rounded-full" style="width: {{ $inTransitPercentage }}%;"></div>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-50">
                    <span>Awaiting delivery: <strong class="text-slate-800">{{ $awaitingDeliveryCount }}</strong></span>
                    <span>Delivered: <strong class="text-slate-800">{{ $deliveredCount }}</strong></span>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Shortage & Cash Review</span>
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="text-2xl font-black text-red-600">Rs. {{ number_format($totalShortageValue, 2) }}</span>
                        <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-lg border border-red-100">
                            {{ $shortageItems->count() }} shortage items
                        </span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full mt-4 overflow-hidden">
                        <div class="h-full bg-red-500 rounded-full" style="width: {{ $totalShortageValue > 0 ? 100 : 0 }}%;"></div>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-50">
                    <span>Cash collected: <strong class="text-slate-800">Rs. {{ number_format($totalCashCollected, 2) }}</strong></span>
                    <span>Variance: <strong class="text-slate-800">Rs. {{ number_format(abs($totalCashDiscrepancy), 2) }}</strong></span>
                </div>
            </div>
        </div>

        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">2. Sort & Allocate</p>
                    <h2 class="mt-2 text-lg font-black text-slate-900">Shop dispatch cards</h2>
                    <p class="mt-1 text-sm text-slate-500">Open a shop card, check each approved product, update packing progress, and move items to transit. Shop owners see these product-level updates on their delivery screen.</p>
                </div>
                <a href="{{ route('inventory.sorting.shop-orders', ['date' => $date]) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">
                    Open All Shop Cards
                </a>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse($shopCards as $card)
                    @php
                        $toneClasses = match ($card['status_tone']) {
                            'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'info' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                            'danger' => 'bg-red-50 text-red-700 border-red-200',
                            default => 'bg-slate-100 text-slate-700 border-slate-200',
                        };
                    @endphp
                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-black text-slate-900">{{ $card['shop_name'] }}</h3>
                                <p class="mt-1 text-[11px] font-mono font-bold text-slate-500">{{ $card['order_number'] }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $toneClasses }}">
                                {{ $card['status_label'] }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-2xl bg-white px-3 py-3 border border-slate-200">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Packed</p>
                                <p class="mt-2 text-xl font-black text-slate-900">{{ $card['packed_items'] }}</p>
                            </div>
                            <div class="rounded-2xl bg-white px-3 py-3 border border-slate-200">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Transit</p>
                                <p class="mt-2 text-xl font-black text-indigo-700">{{ $card['in_transit_items'] }}</p>
                            </div>
                            <div class="rounded-2xl bg-white px-3 py-3 border border-slate-200">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Delivered</p>
                                <p class="mt-2 text-xl font-black text-emerald-700">{{ $card['delivered_items'] }}</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="flex items-center justify-between text-[11px] font-bold text-slate-500">
                                <span>Warehouse progress</span>
                                <span>{{ $card['progress_percentage'] }}%</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-cyan-500" style="width: {{ $card['progress_percentage'] }}%;"></div>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-slate-500">{{ $card['packed_items'] }} of {{ $card['total_items'] }} approved products packed or moved to transit.</p>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            <a href="{{ $card['details_url'] }}" class="inline-flex flex-1 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-700 transition hover:bg-slate-100">
                                Open Shop Card
                            </a>
                            @if($card['check_in_url'])
                                <a href="{{ $card['check_in_url'] }}" class="inline-flex flex-1 items-center justify-center rounded-2xl bg-emerald-600 px-4 py-2 text-xs font-black text-white transition hover:bg-emerald-700">
                                    Delivery Check-in
                                </a>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="md:col-span-2 xl:col-span-3 rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                        <p class="text-base font-black text-slate-700">No approved shop orders for this date.</p>
                        <p class="mt-2 text-sm text-slate-500">The warehouse cards will appear here as soon as purchase approvals are ready for dispatch.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <!-- Main Deliveries Status Table -->
        <div id="dispatch-status-board" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">3. Load & Delivery Status</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                            <th class="py-3 px-6">Shop</th>
                            <th class="py-3 px-6">Order Ref</th>
                            <th class="py-3 px-6 text-center">Warehouse Stage</th>
                            <th class="py-3 px-6 text-center">Delivery Check-in</th>
                            <th class="py-3 px-6 text-right">Items</th>
                            <th class="py-3 px-6 text-right">Shortages</th>
                            <th class="py-3 px-6 text-right">Cash Collected</th>
                            <th class="py-3 px-6 text-right">Cash Variance</th>
                            <th class="py-3 px-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        @forelse($orders as $order)
                            @php
                                $total = $order->items->count();
                                $sorted = $order->items->where('is_sorted', true)->count();
                            @endphp
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    {{ $order->shop->name }}
                                </td>
                                <td class="py-4 px-6 font-mono text-[10px] font-bold text-slate-500">
                                    {{ $order->order_number }}
                                </td>
                                <td class="py-4 px-6 text-center">
                                    @php
                                        $warehouseToneClasses = match ($order->warehouseWorkflowTone()) {
                                            'success' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                            'warning' => 'bg-amber-50 text-amber-700 border-amber-100',
                                            'info' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                                            'danger' => 'bg-red-50 text-red-700 border-red-100',
                                            default => 'bg-slate-50 text-slate-600 border-slate-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-black border {{ $warehouseToneClasses }}">
                                        {{ $order->warehouseWorkflowLabel() }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    @if($order->is_delivered)
                                        @php
                                            $deliveryStatus = $order->delivery_status ?? 'delivered';
                                            $deliveryClasses = match ($deliveryStatus) {
                                                'partially_delivered' => 'bg-amber-50 text-amber-700 border-amber-100',
                                                'delivery_issue' => 'bg-red-50 text-red-700 border-red-100',
                                                default => 'bg-teal-50 text-teal-700 border-teal-100',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-black border {{ $deliveryClasses }}">
                                            {{ str($deliveryStatus)->replace('_', ' ')->title() }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 bg-slate-50 text-slate-400 px-2.5 py-0.5 rounded-full text-[9px] font-bold border border-slate-200">
                                            Awaiting Delivery
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-slate-600">
                                    {{ $total }} items
                                </td>
                                <td class="py-4 px-6 text-right font-black {{ $order->total_shortage_value > 0 ? 'text-red-600' : 'text-slate-400' }}">
                                    Rs. {{ number_format((float) $order->total_shortage_value, 2) }}
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-slate-800">
                                    Rs. {{ number_format((float) $order->cash_collected, 2) }}
                                </td>
                                <td class="py-4 px-6 text-right font-black">
                                    @if($order->is_delivered)
                                        @php
                                            $cVariance = (float) $order->cash_discrepancy;
                                        @endphp
                                        <span class="{{ $cVariance > 0.01 ? 'text-amber-600' : ($cVariance < -0.01 ? 'text-blue-600' : 'text-emerald-600') }}">
                                            @if(abs($cVariance) < 0.01)
                                                Rs. 0.00
                                            @else
                                                Rs. {{ number_format(abs($cVariance), 2) }} 
                                                <small class="text-[9px] font-bold uppercase">({{ $cVariance > 0 ? 'Short' : 'Surp' }})</small>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-slate-300 italic">-</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('inventory.sorting.shop-orders', ['date' => $date]) }}#shop-card-{{ $order->id }}" class="inline-flex items-center bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-extrabold px-3 py-1.5 rounded-lg border border-slate-200 transition-all cursor-pointer">
                                            Shop Card
                                        </a>
                                        @if($order->is_allocation_completed && !$order->is_delivered)
                                            <a href="{{ route('requisitions.delivery.show', $order->order_number) }}" class="inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black px-3 py-1.5 rounded-lg transition-all cursor-pointer">
                                                Check-in
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400 font-medium italic bg-slate-50/10">
                                    No shop orders or deliveries generated for this date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Daily Shortages Analysis -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Itemized Shortages Analysis</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 text-[9px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                                    <th class="py-2.5 px-6">Product</th>
                                    <th class="py-2.5 px-6">Shop</th>
                                    <th class="py-2.5 px-6 text-right">Short Qty</th>
                                    <th class="py-2.5 px-6 text-right">Unit Cost</th>
                                    <th class="py-2.5 px-6 text-right">Shortage Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                                @forelse($shortageItems as $item)
                                    <tr class="hover:bg-slate-50/20">
                                        <td class="py-3.5 px-6 font-semibold text-slate-900">
                                            {{ $item->product->name }}
                                            <span class="block text-[9px] text-slate-400 font-normal mt-0.5">{{ $item->product->sku }}</span>
                                        </td>
                                        <td class="py-3.5 px-6 text-slate-600 font-medium">
                                            {{ $item->order->shop->name }}
                                        </td>
                                        <td class="py-3.5 px-6 text-right font-bold text-red-600">
                                            {{ number_format((float) $item->shortage_qty, 2) }} {{ $item->unit }}
                                        </td>
                                        <td class="py-3.5 px-6 text-right text-slate-500">
                                            Rs. {{ number_format((float) $item->unit_cost, 2) }}
                                        </td>
                                        <td class="py-3.5 px-6 text-right font-black text-red-600">
                                            Rs. {{ number_format((float) $item->shortage_value, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-12 text-center text-slate-400 font-medium italic bg-slate-50/10">
                                            No item shortages reported on this date.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Cash Discrepancies Board -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Cash Discrepancy Board</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 text-[9px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                                    <th class="py-2.5 px-6">Shop</th>
                                    <th class="py-2.5 px-6 text-right">Expected Value</th>
                                    <th class="py-2.5 px-6 text-right">Collected Cash</th>
                                    <th class="py-2.5 px-6 text-right">Variance</th>
                                    <th class="py-2.5 px-6">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                                @forelse($discrepancyOrders as $dOrder)
                                    @php
                                        $expValue = $dOrder->items->sum(fn($i) => (float) $i->delivered_qty * (float) $i->unit_cost);
                                        $variance = (float) $dOrder->cash_discrepancy;
                                    @endphp
                                    <tr class="hover:bg-slate-50/20">
                                        <td class="py-3.5 px-6 font-semibold text-slate-900">
                                            {{ $dOrder->shop->name }}
                                        </td>
                                        <td class="py-3.5 px-6 text-right text-slate-600 font-medium">
                                            Rs. {{ number_format($expValue, 2) }}
                                        </td>
                                        <td class="py-3.5 px-6 text-right text-slate-800 font-black">
                                            Rs. {{ number_format((float) $dOrder->cash_collected, 2) }}
                                        </td>
                                        <td class="py-3.5 px-6 text-right font-black">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black border uppercase tracking-wider {{ $variance > 0 ? 'bg-red-50 text-red-700 border-red-100' : 'bg-blue-50 text-blue-700 border-blue-100' }}">
                                                Rs. {{ number_format(abs($variance), 2) }} ({{ $variance > 0 ? 'Short' : 'Surp' }})
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-6 text-slate-500 font-medium italic truncate max-w-xs" title="{{ $dOrder->delivery_notes }}">
                                            {{ $dOrder->delivery_notes ?: 'No remarks' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-12 text-center text-slate-400 font-medium italic bg-slate-50/10">
                                            No shops with cash discrepancies on this date.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            setInterval(() => {
                const refreshUrl = new URL(window.location.href);
                refreshUrl.searchParams.set('_refresh', Date.now().toString());
                window.location.replace(refreshUrl.toString());
            }, 30000);
        </script>
    @endpush
</x-layouts.inventory>
