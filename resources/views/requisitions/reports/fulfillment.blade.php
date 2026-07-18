<x-layouts.inventory title="Fulfillment & Delivery Analytics Report">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Dashboard Intro -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">
                        Fulfillment &amp; Delivery Report
                        @if(auth()->user()->hasRole('shop') && auth()->user()->shop)
                            <span class="text-emerald-600 font-bold text-sm block md:inline md:ml-2">— {{ auth()->user()->shop->name }}</span>
                        @endif
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">Analyze order completion rates, physical dispatches vs. requisitions, shortages, and cash variance reconciliations.</p>
                </div>
            </div>
            
            <!-- Filters -->
            <form method="GET" action="{{ route('inventory.reports.fulfillment') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-6 border-t border-slate-100">
                <div>
                    <label for="start_date" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate->format('Y-m-d') }}"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white shadow-sm">
                </div>
                <div>
                    <label for="end_date" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate->format('Y-m-d') }}"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white shadow-sm">
                </div>
                
                @if(!auth()->user()->hasRole('shop'))
                    <div>
                        <label for="shop_id" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">Shop</label>
                        <select name="shop_id" id="shop_id"
                                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white shadow-sm">
                            <option value="">All Shops</option>
                            @foreach($shops as $shop)
                                <option value="{{ $shop->id }}" @selected($selectedShopId === $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold py-2.5 rounded-xl transition-all shadow-sm cursor-pointer flex items-center justify-center gap-1.5 border-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A50.06 50.06 0 0112 3z" /></svg>
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Approval Fulfillment Card -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Approval Fulfillment</span>
                <div class="flex items-baseline justify-between mt-1">
                    <span class="text-2xl font-black text-slate-900">{{ number_format($approvalFulfillmentRate, 1) }}%</span>
                    <span class="text-[10px] font-bold text-slate-500 uppercase">Deliv. / Appr.</span>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-3 overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ min(100, $approvalFulfillmentRate) }}%;"></div>
                </div>
            </div>

            <!-- Request Fulfillment Card -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Requisition Fulfillment</span>
                <div class="flex items-baseline justify-between mt-1">
                    <span class="text-2xl font-black text-slate-900">{{ number_format($requestFulfillmentRate, 1) }}%</span>
                    <span class="text-[10px] font-bold text-slate-500 uppercase">Deliv. / Req.</span>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-3 overflow-hidden">
                    <div class="h-full bg-indigo-500 rounded-full" style="width: {{ min(100, $requestFulfillmentRate) }}%;"></div>
                </div>
            </div>

            <!-- Total Shortage Value Card -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Shortage Discrepancy</span>
                <div class="flex items-baseline justify-between mt-1">
                    <span class="text-2xl font-black text-red-600">Rs. {{ number_format($totalShortageValue, 2) }}</span>
                    <span class="text-[10px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded uppercase tracking-wider">
                        {{ number_format($totalShortageQty, 1) }} kg short
                    </span>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-3 overflow-hidden">
                    <div class="h-full bg-red-500 rounded-full" style="width: {{ $totalShortageValue > 0 ? 100 : 0 }}%;"></div>
                </div>
            </div>

            <!-- Net Cash Variance Card -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Net Cash Variance</span>
                <div class="flex items-baseline justify-between mt-1">
                    <span class="text-2xl font-black {{ $totalCashDiscrepancy > 0.01 ? 'text-amber-600' : ($totalCashDiscrepancy < -0.01 ? 'text-blue-600' : 'text-emerald-600') }}">
                        Rs. {{ number_format(abs($totalCashDiscrepancy), 2) }}
                    </span>
                    <span class="text-[9px] font-black uppercase tracking-wider border rounded px-1.5 py-0.5 {{ $totalCashDiscrepancy > 0.01 ? 'bg-amber-50 text-amber-700 border-amber-100' : ($totalCashDiscrepancy < -0.01 ? 'bg-blue-50 text-blue-700 border-blue-100' : 'bg-emerald-50 text-emerald-700 border-emerald-100') }}">
                        @if(abs($totalCashDiscrepancy) < 0.01)
                            Balanced
                        @elseif($totalCashDiscrepancy > 0)
                            Shortage
                        @else
                            Surplus
                        @endif
                    </span>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-3 overflow-hidden">
                    <div class="h-full {{ $totalCashDiscrepancy > 0.01 ? 'bg-amber-500' : ($totalCashDiscrepancy < -0.01 ? 'bg-blue-500' : 'bg-emerald-500') }} rounded-full" style="width: {{ abs($totalCashDiscrepancy) > 0 ? 100 : 0 }}%;"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 1 Column: Fulfillment by Category -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100">Fulfillment by Category</h2>
                <div class="space-y-4">
                    @forelse($categoryStats as $cId => $cStat)
                        @php
                            $cRate = $cStat['approved'] > 0 ? ($cStat['delivered'] / $cStat['approved']) * 100 : 0;
                        @endphp
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-700">{{ $cStat['name'] }}</span>
                                <span class="font-black text-slate-900">{{ number_format($cRate, 1) }}%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-300 {{ $cRate >= 98 ? 'bg-emerald-500' : ($cRate >= 90 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ min(100, $cRate) }}%;"></div>
                            </div>
                            <div class="flex justify-between text-[9px] text-slate-400 font-bold">
                                <span>Delivered: {{ number_format($cStat['delivered'], 1) }} kg</span>
                                <span>Approved: {{ number_format($cStat['approved'], 1) }} kg</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 font-medium italic">No category statistics available.</p>
                    @endforelse
                </div>
            </div>

            <!-- Right 2 Columns: Overall Quantities Summary -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100 mb-4">Requisition Quantities Aggregation</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
                        <div class="p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                            <span class="block text-[9px] font-black uppercase text-slate-400 tracking-wider">Total Requested</span>
                            <span class="block text-xl font-black text-slate-700 mt-1">{{ number_format($totalRequestedQty, 2) }}</span>
                            <span class="text-[9px] font-bold text-slate-400">kilograms</span>
                        </div>
                        <div class="p-4 bg-emerald-50/30 rounded-2xl border border-emerald-100/40">
                            <span class="block text-[9px] font-black uppercase text-emerald-600 tracking-wider">Total Approved</span>
                            <span class="block text-xl font-black text-emerald-700 mt-1">{{ number_format($totalApprovedQty, 2) }}</span>
                            <span class="text-[9px] font-bold text-emerald-500">kilograms</span>
                        </div>
                        <div class="p-4 bg-indigo-50/30 rounded-2xl border border-indigo-100/40">
                            <span class="block text-[9px] font-black uppercase text-indigo-600 tracking-wider">Total Delivered</span>
                            <span class="block text-xl font-black text-indigo-700 mt-1">{{ number_format($totalDeliveredQty, 2) }}</span>
                            <span class="text-[9px] font-bold text-indigo-500">kilograms</span>
                        </div>
                        <div class="p-4 bg-red-50/30 rounded-2xl border border-red-100/40">
                            <span class="block text-[9px] font-black uppercase text-red-600 tracking-wider">Total Shortage</span>
                            <span class="block text-xl font-black text-red-700 mt-1">{{ number_format($totalShortageQty, 2) }}</span>
                            <span class="text-[9px] font-bold text-red-500">kilograms</span>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs">
                    <div class="text-slate-500 font-semibold leading-relaxed">
                        Total orders queried: <span class="font-bold text-slate-700">{{ $totalOrders }}</span> | 
                        Delivered &amp; checked-in orders: <span class="font-bold text-slate-700">{{ $deliveredOrdersCount }}</span>
                    </div>
                    <div class="text-slate-500 font-semibold">
                        Awaiting delivery: <span class="font-bold text-red-600">{{ $totalOrders - $deliveredOrdersCount }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Fulfillment Breakdown Table -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Fulfillment by Product</h2>
                    <p class="text-xs text-slate-400 mt-1">Lists all ordered products during this period, sorted with the lowest fulfillment rates first.</p>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                            <th class="py-3 px-6">Product</th>
                            <th class="py-3 px-6 text-right">Total Requested</th>
                            <th class="py-3 px-6 text-right">Total Approved</th>
                            <th class="py-3 px-6 text-right">Total Delivered</th>
                            <th class="py-3 px-6 text-right">Shortage Qty</th>
                            <th class="py-3 px-6 text-right">Shortage Value</th>
                            <th class="py-3 px-6 text-center">Fulfillment Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        @forelse($productStats as $pStat)
                            @php
                                $pRate = $pStat['approved'] > 0 ? ($pStat['delivered'] / $pStat['approved']) * 100 : 100.00;
                            @endphp
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    {{ $pStat['product'] ? $pStat['product']->name : 'N/A' }}
                                    <span class="block text-[10px] text-slate-400 font-normal mt-0.5">{{ $pStat['product'] ? $pStat['product']->sku : 'N/A' }}</span>
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-slate-600">
                                    {{ number_format($pStat['requested'], 2) }} {{ $pStat['product'] ? $pStat['product']->unit : 'kg' }}
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-slate-700">
                                    {{ number_format($pStat['approved'], 2) }} {{ $pStat['product'] ? $pStat['product']->unit : 'kg' }}
                                </td>
                                <td class="py-4 px-6 text-right font-black text-slate-900">
                                    {{ number_format($pStat['delivered'], 2) }} {{ $pStat['product'] ? $pStat['product']->unit : 'kg' }}
                                </td>
                                <td class="py-4 px-6 text-right font-bold text-red-600">
                                    {{ number_format($pStat['shortage'], 2) }} {{ $pStat['product'] ? $pStat['product']->unit : 'kg' }}
                                </td>
                                <td class="py-4 px-6 text-right font-black text-red-600">
                                    Rs. {{ number_format($pStat['shortage_value'], 2) }}
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black border {{ $pRate >= 98 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($pRate >= 90 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-red-50 text-red-700 border-red-200') }}">
                                        {{ number_format($pRate, 1) }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400 font-medium italic bg-slate-50/10">
                                    No product fulfillment stats recorded during this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detailed Requisitions & Delivery Logs -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Detailed Delivery Logs</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                            <th class="py-3 px-6">Delivery Date</th>
                            <th class="py-3 px-6">Order ID</th>
                            @if(!auth()->user()->hasRole('shop'))
                                <th class="py-3 px-6">Shop</th>
                            @endif
                            <th class="py-3 px-6 text-center">Delivery Status</th>
                            <th class="py-3 px-6 text-right">Shortage Value</th>
                            <th class="py-3 px-6 text-right">Cash Collected</th>
                            <th class="py-3 px-6 text-right">Cash Variance</th>
                            <th class="py-3 px-6">Checked-in By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        @forelse($orders as $order)
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    {{ $order->business_date->format('d M Y') }}
                                </td>
                                <td class="py-4 px-6 font-mono text-[10px] font-bold text-slate-500">
                                    <a href="{{ route('requisitions.show', $order->order_number) }}" class="text-emerald-600 hover:text-emerald-700 underline font-black">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                @if(!auth()->user()->hasRole('shop'))
                                    <td class="py-4 px-6 text-slate-700 font-semibold">
                                        {{ $order->shop?->name ?? 'Direct Purchase' }}
                                    </td>
                                @endif
                                <td class="py-4 px-6 text-center">
                                    @if($order->is_delivered)
                                        <span class="inline-flex items-center gap-1 bg-teal-50 text-teal-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-teal-100">
                                            Delivered
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 bg-slate-50 text-slate-400 px-2.5 py-0.5 rounded-full text-[9px] font-bold border border-slate-200">
                                            Awaiting Delivery
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-right font-bold text-red-600">
                                    Rs. {{ number_format((float) $order->total_shortage_value, 2) }}
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-slate-800">
                                    Rs. {{ number_format((float) $order->cash_collected, 2) }}
                                </td>
                                <td class="py-4 px-6 text-right font-black">
                                    @if($order->is_delivered)
                                        @php
                                            $v = (float) $order->cash_discrepancy;
                                        @endphp
                                        <span class="{{ $v > 0.01 ? 'text-amber-600' : ($v < -0.01 ? 'text-blue-600' : 'text-emerald-600') }}">
                                            Rs. {{ number_format(abs($v), 2) }}
                                            <small class="text-[9px] font-bold">({{ $v > 0 ? 'Short' : 'Surp' }})</small>
                                        </span>
                                    @else
                                        <span class="text-slate-300 italic">-</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-slate-500">
                                    {{ $order->deliveredBy?->name ?? 'N/A' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->hasRole('shop') ? 7 : 8 }}" class="py-12 text-center text-slate-400 font-medium italic bg-slate-50/10">
                                    No delivery logs found during this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.inventory>
