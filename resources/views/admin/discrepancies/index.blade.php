<x-layouts.admin title="Discrepancy & Wastage Reports — Green Leaf">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Breadcrumbs & Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <a href="{{ route('dashboard') }}" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1.5 transition-colors mb-2">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Back to Control Center
                </a>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    Discrepancy & Wastage Reports
                </h1>
                <p class="text-xs text-slate-500 mt-1">Monitor discrepancies during physical receiving and shop delivery check-ins.</p>
            </div>
            
            <div class="flex items-center gap-2">
                <form action="{{ route('admin.discrepancies.index') }}" method="GET" class="flex items-center gap-2">
                    <label for="date" class="text-xs font-bold text-slate-500">Business Date:</label>
                    <input type="date" name="date" id="date" value="{{ $date }}" onchange="this.form.submit()"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-indigo-600 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </form>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold px-4 py-3.5 rounded-2xl flex items-center gap-2.5">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="space-y-8">
            {{-- 1. Stock-In Discrepancies (Purchaser Product vs Warehouse Receiver) --}}
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Stock-In Discrepancies (Vendor Carts)</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Purchaser purchased qty vs warehouse receiver checked-in qty.</p>
                    </div>
                    <span class="inline-flex items-center bg-indigo-50 text-indigo-700 px-2.5 py-0.5 rounded-full text-[10px] font-black border border-indigo-100">
                        {{ $goodsReceivedItems->count() }} Discrepancy Item(s)
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                                <th class="py-3 px-6">Product</th>
                                <th class="py-3 px-6">Vendor (Supplier)</th>
                                <th class="py-3 px-6">Purchased By</th>
                                <th class="py-3 px-6 text-right">Purchased Qty</th>
                                <th class="py-3 px-6 text-right">Received Qty</th>
                                <th class="py-3 px-6 text-right">Variance</th>
                                <th class="py-3 px-6 text-center">Action / Type</th>
                                <th class="py-3 px-6">Receiver Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @forelse($goodsReceivedItems as $item)
                                @php
                                    $variance = (float) $item->variance;
                                    $purchased = (float) ($item->purchased_qty ?? 0.0);
                                    $received = (float) $item->received_qty;
                                    $diff = $received - $purchased;
                                @endphp
                                <tr class="hover:bg-slate-50/10">
                                    <td class="py-4 px-6 font-semibold text-slate-900">
                                        {{ $item->product->name }}
                                        <span class="block text-[10px] text-slate-400 font-normal mt-0.5">Ref: {{ $item->goodsReceived?->grn_number }}</span>
                                    </td>
                                    <td class="py-4 px-6 font-bold text-slate-800">
                                        {{ $item->goodsReceived?->purchaseOrder?->supplier?->name ?? 'N/A' }}
                                    </td>
                                    <td class="py-4 px-6 text-slate-500">
                                        {{ $item->goodsReceived?->purchaseOrder?->purchaserCart?->user?->name ?? 'Purchaser' }}
                                    </td>
                                    <td class="py-4 px-6 text-right font-semibold text-slate-600">
                                        {{ number_format($purchased, 2) }} kg
                                    </td>
                                    <td class="py-4 px-6 text-right font-black text-slate-900">
                                        {{ number_format($received, 2) }} kg
                                    </td>
                                    <td class="py-4 px-6 text-right font-black {{ $diff < -0.001 ? 'text-red-600' : 'text-emerald-600' }}">
                                        {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2) }} kg
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        @if($item->discrepancy_type === 'wastage')
                                            <span class="inline-flex items-center gap-1 bg-red-50 text-red-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-red-100">
                                                Wastage
                                            </span>
                                        @elseif($item->discrepancy_type === 'other')
                                            <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-amber-100">
                                                Adjustment
                                            </span>
                                        @else
                                            <span class="text-slate-400 italic">None</span>
                                        @endif
                                    </td>
                                    <td class="py-4 px-6 text-slate-500 font-medium italic">
                                        {{ $item->discrepancy_note ?? 'No notes' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-slate-400 font-medium italic bg-slate-50/10">No stock-in discrepancies recorded for this date.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 2. Stock-Out & Delivery Discrepancies (Warehouse Loadout & Shop Delivery) --}}
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Stock-Out & Delivery Discrepancies</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Approved Requisition vs. Warehouse Loaded vs. Shop Owner Delivery Check-In.</p>
                    </div>
                    <span class="inline-flex items-center bg-indigo-50 text-indigo-700 px-2.5 py-0.5 rounded-full text-[10px] font-black border border-indigo-100">
                        {{ $shopOrderItems->count() }} Discrepancy Item(s)
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                                <th class="py-3 px-6">Product</th>
                                <th class="py-3 px-6">Destination Shop</th>
                                <th class="py-3 px-6 text-right">Approved</th>
                                <th class="py-3 px-6 text-right">Loaded</th>
                                <th class="py-3 px-6 text-right">Checked-In</th>
                                <th class="py-3 px-6 text-center">Loadout Discrepancy</th>
                                <th class="py-3 px-6 text-center">Delivery Discrepancy</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @forelse($shopOrderItems as $item)
                                @php
                                    $approved = (float) $item->approved_qty;
                                    $loaded = $item->loaded_qty !== null ? (float) $item->loaded_qty : $approved;
                                    $delivered = $item->delivered_qty !== null ? (float) $item->delivered_qty : $loaded;
                                    
                                    $loadoutDiff = $loaded - $approved;
                                    $deliveryDiff = $delivered - $loaded;
                                @endphp
                                <tr class="hover:bg-slate-50/10">
                                    <td class="py-4 px-6 font-semibold text-slate-900">
                                        {{ $item->product->name }}
                                        <span class="block text-[10px] text-slate-400 font-normal mt-0.5">Order: {{ $item->order?->order_number }}</span>
                                    </td>
                                    <td class="py-4 px-6 font-bold text-slate-800">
                                        {{ $item->order?->shop?->name ?? 'N/A' }}
                                    </td>
                                    <td class="py-4 px-6 text-right font-semibold text-slate-600">
                                        {{ number_format($approved, 2) }} kg
                                    </td>
                                    <td class="py-4 px-6 text-right font-semibold text-slate-600">
                                        {{ $item->loaded_qty !== null ? number_format($loaded, 2).' kg' : 'N/A' }}
                                    </td>
                                    <td class="py-4 px-6 text-right font-black text-slate-900">
                                        {{ $item->delivered_qty !== null ? number_format($delivered, 2).' kg' : 'N/A' }}
                                    </td>
                                    
                                    {{-- Loadout Discrepancy Details --}}
                                    <td class="py-4 px-6">
                                        @if(abs($loadoutDiff) > 0.001)
                                            <div class="flex flex-col items-center gap-1">
                                                <span class="font-black text-red-600">
                                                    {{ number_format($loadoutDiff, 2) }} kg
                                                </span>
                                                @if($item->loadout_discrepancy_type === 'wastage')
                                                    <span class="inline-flex items-center bg-red-50 text-red-700 px-2 py-0.5 rounded text-[8px] font-black border border-red-100">
                                                        Wastage
                                                    </span>
                                                @elseif($item->loadout_discrepancy_type === 'other')
                                                    <span class="inline-flex items-center bg-amber-50 text-amber-700 px-2 py-0.5 rounded text-[8px] font-black border border-amber-100">
                                                        Adjustment
                                                    </span>
                                                @endif
                                                @if($item->loadout_discrepancy_note)
                                                    <span class="text-[9px] text-slate-400 italic text-center max-w-[120px] truncate" title="{{ $item->loadout_discrepancy_note }}">
                                                        "{{ $item->loadout_discrepancy_note }}"
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="text-center text-slate-400 font-bold">Balanced</div>
                                        @endif
                                    </td>

                                    {{-- Delivery Discrepancy Details --}}
                                    <td class="py-4 px-6">
                                        @if(abs($deliveryDiff) > 0.001)
                                            <div class="flex flex-col items-center gap-1">
                                                <span class="font-black text-red-600">
                                                    {{ number_format($deliveryDiff, 2) }} kg
                                                </span>
                                                @if($item->delivery_discrepancy_type === 'wastage')
                                                    <span class="inline-flex items-center bg-red-50 text-red-700 px-2 py-0.5 rounded text-[8px] font-black border border-red-100">
                                                        Wastage
                                                    </span>
                                                @elseif($item->delivery_discrepancy_type === 'other')
                                                    <span class="inline-flex items-center bg-amber-50 text-amber-700 px-2 py-0.5 rounded text-[8px] font-black border border-amber-100">
                                                        Adjustment
                                                    </span>
                                                @endif
                                                @if($item->delivery_discrepancy_note)
                                                    <span class="text-[9px] text-slate-400 italic text-center max-w-[120px] truncate" title="{{ $item->delivery_discrepancy_note }}">
                                                        "{{ $item->delivery_discrepancy_note }}"
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="text-center text-slate-400 font-bold">Balanced</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-400 font-medium italic bg-slate-50/10">No stock-out or delivery discrepancies recorded for this date.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
