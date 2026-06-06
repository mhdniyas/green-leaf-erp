<x-layouts.app title="Daily Delivery Dashboard">
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
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">Daily Delivery & Check-in Dashboard</h1>
                    <p class="text-xs text-slate-500 mt-1">Track physical dispatches, load completion, shortage analytics, and shop cash reconciliation for the target date.</p>
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

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Delivery Check-in Progress Card -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Delivery Completion</span>
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="text-2xl font-black text-slate-900">{{ $deliveredCount }} / {{ $totalOrdersCount }}</span>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-lg border border-emerald-100">
                            {{ $totalOrdersCount > 0 ? round(($deliveredCount / $totalOrdersCount) * 100) : 0 }}% Checked-in
                        </span>
                    </div>
                    <!-- Local Progress Bar -->
                    @php
                        $delPercentage = $totalOrdersCount > 0 ? ($deliveredCount / $totalOrdersCount) * 100 : 0;
                    @endphp
                    <div class="w-full bg-slate-100 h-2 rounded-full mt-4 overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all duration-300" style="width: {{ $delPercentage }}%;"></div>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-50">
                    <span>Awaiting Check-in: <strong class="text-slate-800">{{ $awaitingDeliveryCount }}</strong></span>
                    <span>Allocated & Loaded: <strong class="text-slate-800">{{ $allocationCompletedCount }}</strong></span>
                </div>
            </div>

            <!-- Spoilages & Shortages Card -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Total Shortage Value</span>
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="text-2xl font-black text-red-600">Rs. {{ number_format($totalShortageValue, 2) }}</span>
                        <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-lg border border-red-100">
                            {{ $shortageItems->count() }} items shorted
                        </span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full mt-4 overflow-hidden">
                        <div class="h-full bg-red-500 rounded-full" style="width: {{ $totalShortageValue > 0 ? 100 : 0 }}%;"></div>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-50">
                    <span>Expected order items value: <span class="font-bold text-slate-700">Rs. {{ number_format($orders->sum(fn($o) => $o->items->sum(fn($i) => ($i->approved_qty ?? 0.00) * ($i->unit_cost ?? 0.00))), 2) }}</span></span>
                </div>
            </div>

            <!-- Cash Discrepancies Summary Card -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Net Cash Variance</span>
                    <div class="flex items-baseline justify-between mt-1">
                        @php
                            $absDisc = abs($totalCashDiscrepancy);
                        @endphp
                        <span class="text-2xl font-black {{ $totalCashDiscrepancy > 0.01 ? 'text-amber-600' : ($totalCashDiscrepancy < -0.01 ? 'text-blue-600' : 'text-emerald-600') }}">
                            Rs. {{ number_format($absDisc, 2) }}
                        </span>
                        <span class="text-xs font-black uppercase tracking-wider border rounded-lg px-2 py-0.5 {{ $totalCashDiscrepancy > 0.01 ? 'bg-amber-50 text-amber-700 border-amber-100' : ($totalCashDiscrepancy < -0.01 ? 'bg-blue-50 text-blue-700 border-blue-100' : 'bg-emerald-50 text-emerald-700 border-emerald-100') }}">
                            @if(abs($totalCashDiscrepancy) < 0.01)
                                Balanced
                            @elseif($totalCashDiscrepancy > 0)
                                Cash Shortage
                            @else
                                Cash Surplus
                            @endif
                        </span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full mt-4 overflow-hidden">
                        <div class="h-full {{ $totalCashDiscrepancy > 0.01 ? 'bg-amber-500' : ($totalCashDiscrepancy < -0.01 ? 'bg-blue-500' : 'bg-emerald-500') }} rounded-full" style="width: {{ abs($totalCashDiscrepancy) > 0 ? 100 : 0 }}%;"></div>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-50">
                    <span>Collected Cash: <strong class="text-slate-800">Rs. {{ number_format($totalCashCollected, 2) }}</strong></span>
                </div>
            </div>
        </div>

        <!-- Main Deliveries Status Table -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Shop Deliveries Status</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                            <th class="py-3 px-6">Shop</th>
                            <th class="py-3 px-6">Order Ref</th>
                            <th class="py-3 px-6 text-center">Dispatch Status</th>
                            <th class="py-3 px-6 text-center">Delivery Status</th>
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
                                    @if($order->is_allocation_completed)
                                        <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-emerald-100">
                                            Loaded & Shipped
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-amber-100">
                                            Sorting ({{ $sorted }}/{{ $total }})
                                        </span>
                                    @endif
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
                                        <a href="{{ route('requisitions.show', $order->order_number) }}" class="inline-flex items-center bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-extrabold px-3 py-1.5 rounded-lg border border-slate-200 transition-all cursor-pointer">
                                            Details
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
</x-layouts.app>
