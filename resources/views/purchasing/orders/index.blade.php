<x-layouts.app title="Purchase Orders">
    <x-slot:actions>
        @can('purchasing.order.create')
        <a href="{{ route('purchasing.orders.create') }}"
           id="create-order-btn"
           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 transition-all shadow-md hover:shadow-lg">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Create Order
        </a>
        @endcan
    </x-slot:actions>

    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Module Header -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Purchase Orders Control</h1>
                    <p class="text-xs text-slate-500 mt-1">Review orders requested by managers, manage suppliers, approve procurement requisitions, and audit spending trends.</p>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex overflow-x-auto border border-slate-200 bg-slate-50/50 p-1.5 rounded-2xl gap-2 w-full shadow-sm select-none scrollbar-none">
            <button type="button" onclick="switchTab('all')" id="tab-btn-all" 
                    class="tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none bg-white text-slate-900 border border-slate-200 shadow-sm whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" /></svg>
                All Orders
            </button>
            <button type="button" onclick="switchTab('pending')" id="tab-btn-pending" 
                    class="tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                Pending Approval
                @if(count($pendingOrders) > 0)
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold ml-1">{{ count($pendingOrders) }}</span>
                @endif
            </button>
            <button type="button" onclick="switchTab('history')" id="tab-btn-history" 
                    class="tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                Approval History
            </button>
            <button type="button" onclick="switchTab('analytics')" id="tab-btn-analytics" 
                    class="tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                Order Analytics
            </button>
        </div>

        <!-- 1. TAB: All Orders -->
        <div id="tab-panel-all" class="tab-panel space-y-6">
            <!-- Filters -->
            <div class="bg-white rounded-3xl border border-slate-200 p-5">
                <form method="GET" action="{{ route('purchasing.orders.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <input type="hidden" name="tab" value="all">
                    
                    <div>
                        <label for="supplier_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Supplier</label>
                        <select name="supplier_id" id="supplier_id"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}" {{ request('supplier_id') == $sup->id ? 'selected' : '' }}>
                                    {{ $sup->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="date_filter" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Period</label>
                        <select name="date_filter" id="date_filter" onchange="toggleCustomDates(this.value)"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="" {{ !request()->filled('date_filter') ? 'selected' : '' }}>All Time</option>
                            <option value="this_month" {{ request('date_filter') === 'this_month' ? 'selected' : '' }}>This Month</option>
                            <option value="last_month" {{ request('date_filter') === 'last_month' ? 'selected' : '' }}>Last Month</option>
                            <option value="custom" {{ request('date_filter') === 'custom' ? 'selected' : '' }}>Custom Range</option>
                        </select>
                    </div>

                    <div id="custom-date-inputs" class="{{ request('date_filter') === 'custom' ? 'grid' : 'hidden' }} grid-cols-2 gap-2 col-span-2">
                        <div>
                            <label for="start_date" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="{{ request('start_date') }}"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                        </div>
                        <div>
                            <label for="end_date" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                        </div>
                    </div>

                    <div class="flex gap-2 {{ request('date_filter') === 'custom' ? 'col-span-4 justify-end' : '' }}">
                        <button type="submit" class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2.5 text-xs font-bold text-white transition-all shadow-md flex items-center justify-center min-w-[120px]">
                            Apply Filters
                        </button>
                        @if(request()->filled('supplier_id') || request('date_filter') !== 'this_month')
                        <a href="{{ route('purchasing.orders.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all flex items-center justify-center">
                            Reset
                        </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                @if($allOrders->isEmpty())
                <div class="py-16 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">No Purchase Orders Found</h3>
                    <p class="text-xs text-slate-500 mt-1">Try adjusting the filters or create a new purchase order.</p>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-6">PO Number</th>
                                <th class="py-3 px-6">Order Date</th>
                                <th class="py-3 px-6">Supplier</th>
                                <th class="py-3 px-6 text-right">Amount</th>
                                <th class="py-3 px-6 text-center">Status</th>
                                <th class="py-3 px-6 text-center">Delivery</th>
                                <th class="py-3 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @foreach($allOrders as $order)
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-4 px-6 font-mono font-bold text-emerald-600">
                                    <a href="{{ route('purchasing.orders.show', $order) }}" class="hover:underline">
                                        {{ $order->po_number }}
                                    </a>
                                </td>
                                <td class="py-4 px-6 text-slate-600">
                                    {{ $order->order_date->format('d M Y') }}
                                </td>
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    {{ $order->supplier?->name ?? '—' }}
                                </td>
                                <td class="py-4 px-6 text-right font-bold text-slate-900">
                                    ₹{{ number_format($order->total_amount, 2) }}
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center text-[10px] font-bold border px-2.5 py-0.5 rounded-full {{ $order->status->color() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    @if($order->status->value === 'draft')
                                        <span class="inline-flex items-center text-[10px] font-semibold text-slate-400 bg-slate-50 px-2.5 py-0.5 rounded-full border border-slate-200">Awaiting Approval</span>
                                    @elseif(in_array($order->status->value, ['received', 'closed']))
                                        <span class="inline-flex items-center text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-100">Delivered</span>
                                    @elseif($order->goodsReceiveds->isNotEmpty())
                                        <span class="inline-flex items-center text-[10px] font-bold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-100">Partially Recv</span>
                                    @else
                                        <span class="inline-flex items-center text-[10px] font-bold text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-100">In Transit</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <a href="{{ route('purchasing.orders.show', $order) }}" class="text-emerald-600 hover:text-emerald-800 font-bold hover:underline">View</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($allOrders->hasPages())
                <div class="px-6 py-4 border-t border-slate-100">
                    {{ $allOrders->links() }}
                </div>
                @endif
                @endif
            </div>
        </div>

        <!-- 2. TAB: Pending Approval -->
        <div id="tab-panel-pending" class="tab-panel hidden space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h2 class="text-sm font-bold text-slate-800 tracking-tight">Orders Awaiting Owner Approval</h2>
                </div>

                @if($pendingOrders->isEmpty())
                <div class="py-16 text-center text-slate-400 font-medium italic bg-slate-50/10">
                    No orders currently awaiting approval. Great job!
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-6">PO Number</th>
                                <th class="py-3 px-6">Supplier</th>
                                <th class="py-3 px-6">Requested By</th>
                                <th class="py-3 px-6">Date</th>
                                <th class="py-3 px-6 text-right">Amount</th>
                                <th class="py-3 px-6 text-center w-[250px]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @foreach($pendingOrders as $order)
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-4 px-6 font-mono font-bold text-emerald-600">
                                    <a href="{{ route('purchasing.orders.show', $order) }}" class="hover:underline">
                                        {{ $order->po_number }}
                                    </a>
                                </td>
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    {{ $order->supplier?->name ?? '—' }}
                                </td>
                                <td class="py-4 px-6 text-slate-600">
                                    {{ $order->createdBy?->name ?? 'System' }}
                                </td>
                                <td class="py-4 px-6 text-slate-500">
                                    {{ $order->order_date->format('d M Y') }}
                                </td>
                                <td class="py-4 px-6 text-right font-bold text-slate-900">
                                    ₹{{ number_format($order->total_amount, 2) }}
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <form method="POST" action="{{ route('purchasing.orders.approve', $order) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="remarks" value="Stock Required">
                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black px-3.5 py-1.5 rounded-xl shadow-sm transition-all cursor-pointer">
                                                ✓ Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('purchasing.orders.reject', $order) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="remarks" value="Duplicate Order">
                                            <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 text-[10px] font-black px-3.5 py-1.5 rounded-xl border border-red-200 transition-all cursor-pointer">
                                                ✗ Reject
                                            </button>
                                        </form>
                                        <a href="{{ route('purchasing.orders.show', $order) }}" class="text-slate-500 hover:text-slate-800 text-[10px] font-bold px-2.5 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition-all">
                                            Review
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        <!-- 3. TAB: Approval History -->
        <div id="tab-panel-history" class="tab-panel hidden space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h2 class="text-sm font-bold text-slate-800 tracking-tight">Complete Audit Trail of Owner Decisions</h2>
                </div>

                @if($approvalHistory->isEmpty())
                <div class="py-16 text-center text-slate-400 font-medium italic bg-slate-50/10">
                    No approval or rejection actions have been recorded yet.
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-6">Date</th>
                                <th class="py-3 px-6">PO Number</th>
                                <th class="py-3 px-6">Action</th>
                                <th class="py-3 px-6">User</th>
                                <th class="py-3 px-6">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @foreach($approvalHistory as $history)
                            @php
                                $statusVal = $history->properties['status'] ?? '';
                                $remarksVal = $history->properties['remarks'] ?? 'No remarks provided';
                            @endphp
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-4 px-6 text-slate-500">
                                    {{ $history->created_at->format('d M Y h:i A') }}
                                </td>
                                <td class="py-4 px-6 font-mono font-bold text-emerald-600">
                                    @if($history->subject)
                                        <a href="{{ route('purchasing.orders.show', $history->subject) }}" class="hover:underline">
                                            {{ $history->subject->po_number }}
                                        </a>
                                    @else
                                        {{ $history->properties['po_number'] ?? 'Deleted PO' }}
                                    @endif
                                </td>
                                <td class="py-4 px-6">
                                    @if($statusVal === 'approved' || $history->description === 'Approved')
                                        <span class="inline-flex items-center text-[10px] font-bold text-green-700 bg-green-50 px-2 py-0.5 rounded-lg border border-green-100">Approved</span>
                                    @else
                                        <span class="inline-flex items-center text-[10px] font-bold text-red-700 bg-red-50 px-2 py-0.5 rounded-lg border border-red-100">Rejected</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    {{ $history->causer?->name ?? 'System' }}
                                </td>
                                <td class="py-4 px-6 text-slate-600 italic">
                                    {{ $remarksVal }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        <!-- 4. TAB: Order Analytics -->
        <div id="tab-panel-analytics" class="tab-panel hidden space-y-6">
            <!-- Metrics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Orders This Month</span>
                        <div class="text-3xl font-black text-slate-900 mt-1">{{ $thisMonthOrdersCount }}</div>
                    </div>
                    <div class="text-[10px] text-slate-500 font-semibold mt-4 pt-2 border-t border-slate-50">Active procurement contracts</div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Total Spend This Month</span>
                        <div class="text-3xl font-black text-emerald-600 mt-1">₹{{ number_format($thisMonthSpend, 2) }}</div>
                    </div>
                    <div class="text-[10px] text-slate-500 font-semibold mt-4 pt-2 border-t border-slate-50">Landed cost from external suppliers</div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Average Order Value</span>
                        <div class="text-3xl font-black text-indigo-600 mt-1">₹{{ number_format($avgOrderValue, 2) }}</div>
                    </div>
                    <div class="text-[10px] text-slate-500 font-semibold mt-4 pt-2 border-t border-slate-50">Weighted invoice amount</div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Top Supplier</span>
                        <div class="text-2xl font-black text-slate-900 mt-1 truncate" title="{{ $topSupplier }}">{{ $topSupplier }}</div>
                    </div>
                    <div class="text-[10px] text-slate-500 font-semibold mt-4 pt-2 border-t border-slate-50">Partner with highest trade volume</div>
                </div>
            </div>

            <!-- Monthly Trend Graph -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                <div class="pb-4 border-b border-slate-100 mb-6">
                    <h3 class="text-base font-bold text-slate-900 tracking-tight">Monthly Purchase Trend</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Consolidated spending analytics across months for the current year.</p>
                </div>
                
                <div class="space-y-4 max-w-2xl">
                    @foreach($monthlyTrend as $month => $amount)
                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                            <span>{{ $month }}</span>
                            <span class="font-bold">₹{{ number_format($amount, 2) }}</span>
                        </div>
                        @php
                            $maxAmount = max(array_values($monthlyTrend)) ?: 1.0;
                            $percent = min(100, max(4, round(($amount / $maxAmount) * 100)));
                        @endphp
                        <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden shadow-inner">
                            <div class="bg-emerald-500 h-full rounded-full transition-all duration-700 ease-out" style="width: {{ $percent }}%;"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function switchTab(tabId) {
            // Hide all tab panels
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.add('hidden');
            });
            // Show target panel
            document.getElementById('tab-panel-' + tabId).classList.remove('hidden');

            // Reset all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.className = "tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0";
            });
            // Select active button
            const activeBtn = document.getElementById('tab-btn-' + tabId);
            if (activeBtn) {
                activeBtn.className = "tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none bg-white text-slate-900 border border-slate-200 shadow-sm whitespace-nowrap shrink-0";
            }

            // Sync with URL query string
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }

        function toggleCustomDates(value) {
            const customDatesDiv = document.getElementById('custom-date-inputs');
            if (value === 'custom') {
                customDatesDiv.classList.remove('hidden');
                customDatesDiv.classList.add('grid');
            } else {
                customDatesDiv.classList.add('hidden');
                customDatesDiv.classList.remove('grid');
            }
        }

        // On page load, switch to active tab from query param
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'all';
            switchTab(activeTab);
        });
    </script>
    @endpush
</x-layouts.app>
