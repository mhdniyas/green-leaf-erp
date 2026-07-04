<x-layouts.admin title="Daily Operational Progress">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header & Date Navigation -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">Daily Operational Progress</h1>
                    <p class="text-xs text-slate-500 mt-1">Real-time status tracker for the complete supply chain—from purchasing orders to physical warehouse sorting and shop branch deliveries.</p>
                </div>
                
                <!-- Date Selector Navigation -->
                <form method="GET" action="{{ route('admin.daily-progress') }}" class="flex items-center gap-2 bg-slate-100 p-1.5 rounded-2xl border border-slate-200 shadow-sm self-start">
                    @php
                        $carbonDate = \Illuminate\Support\Carbon::parse($date);
                        $prevDate = $carbonDate->copy()->subDay()->format('Y-m-d');
                        $nextDate = $carbonDate->copy()->addDay()->format('Y-m-d');
                        $todayDate = \Illuminate\Support\Carbon::today()->format('Y-m-d');
                    @endphp
                    <a href="{{ route('admin.daily-progress', ['date' => $prevDate]) }}" class="p-2 bg-white hover:bg-slate-50 rounded-xl text-slate-700 transition shadow-sm" title="Previous Day">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                    </a>
                    
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                           class="border-0 bg-transparent text-xs font-black text-slate-800 focus:outline-none focus:ring-0 px-3 cursor-pointer py-1">
                    
                    @if($date !== $todayDate)
                        <a href="{{ route('admin.daily-progress', ['date' => $todayDate]) }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 rounded-xl text-[10px] font-black uppercase text-emerald-600 transition shadow-sm tracking-wider border border-slate-200">
                            Today
                        </a>
                    @endif
                    
                    <a href="{{ route('admin.daily-progress', ['date' => $nextDate]) }}" class="p-2 bg-white hover:bg-slate-50 rounded-xl text-slate-700 transition shadow-sm" title="Next Day">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </form>
            </div>
        </div>

        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Shop Orders -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Approved Requisitions</span>
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="text-2xl font-black text-slate-900">{{ $approvedOrdersCount }}</span>
                        <span class="text-[10px] font-bold text-slate-400">of {{ $totalOrdersCount }} orders</span>
                    </div>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-4 overflow-hidden">
                    @php $reqPercent = $totalOrdersCount > 0 ? ($approvedOrdersCount / $totalOrdersCount) * 100 : 0; @endphp
                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $reqPercent }}%;"></div>
                </div>
            </div>

            <!-- Items Sorting Progress -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Picking &amp; Sorting</span>
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="text-2xl font-black text-slate-900">{{ $allocatedItemsCount }}</span>
                        <span class="text-[10px] font-bold text-slate-400">of {{ $totalItemsCount }} items sorted</span>
                    </div>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-4 overflow-hidden">
                    @php $sortPercent = $totalItemsCount > 0 ? ($allocatedItemsCount / $totalItemsCount) * 100 : 0; @endphp
                    <div class="h-full bg-indigo-500 rounded-full" style="width: {{ $sortPercent }}%;"></div>
                </div>
            </div>

            <!-- Ready for Dispatch -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Loaded (Ready to Shipped)</span>
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="text-2xl font-black text-slate-900">{{ $loadedItemsCount }}</span>
                        <span class="text-[10px] font-bold text-slate-400">of {{ $totalItemsCount }} items loaded</span>
                    </div>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-4 overflow-hidden">
                    @php $loadPercent = $totalItemsCount > 0 ? ($loadedItemsCount / $totalItemsCount) * 100 : 0; @endphp
                    <div class="h-full bg-sky-500 rounded-full" style="width: {{ $loadPercent }}%;"></div>
                </div>
            </div>

            <!-- Delivery Completions -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Checked-in Deliveries</span>
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="text-2xl font-black text-slate-900">{{ $deliveredOrdersCount }}</span>
                        <span class="text-[10px] font-bold text-slate-400">of {{ $totalOrdersCount }} delivered</span>
                    </div>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-4 overflow-hidden">
                    @php $delPercent = $totalOrdersCount > 0 ? ($deliveredOrdersCount / $totalOrdersCount) * 100 : 0; @endphp
                    <div class="h-full bg-teal-500 rounded-full" style="width: {{ $delPercent }}%;"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Workflow Checklist (Timeline Board) -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4 flex flex-col">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100">Workflow Checklist</h2>
                
                <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-100">
                    @foreach($stages as $key => $stage)
                        <div class="relative flex gap-3 items-start">
                            <!-- Bullet Icon Indicator -->
                            @if($stage['completed'])
                                <div class="absolute -left-[22px] w-5 h-5 rounded-full bg-emerald-500 border-2 border-white flex items-center justify-center text-white shadow-sm shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                </div>
                            @else
                                <div class="absolute -left-[22px] w-5 h-5 rounded-full bg-slate-100 border-2 border-slate-300 flex items-center justify-center text-slate-400 shadow-sm shrink-0">
                                    <div class="w-1.5 h-1.5 rounded-full bg-slate-300"></div>
                                </div>
                            @endif
                            
                            <div class="min-w-0">
                                <span class="block text-xs font-black {{ $stage['completed'] ? 'text-slate-800' : 'text-slate-500 font-semibold' }}">{{ $stage['name'] }}</span>
                                <span class="block text-[10px] text-slate-400 mt-0.5 font-semibold">{{ $stage['description'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Right Columns: Active Requisitions Tracker Table -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Active Requisitions</h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                                    <th class="py-3 px-6">Shop</th>
                                    <th class="py-3 px-6">Requisition ID</th>
                                    <th class="py-3 px-6 text-center">Picking Status</th>
                                    <th class="py-3 px-6 text-right">Shortage</th>
                                    <th class="py-3 px-6 text-right">Collected</th>
                                    <th class="py-3 px-6 text-right">Variance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                                @forelse($orders as $order)
                                    <tr class="hover:bg-slate-50/10">
                                        <td class="py-4 px-6 font-semibold text-slate-900">
                                            {{ $order->shop ? $order->shop->name : 'N/A' }}
                                        </td>
                                        <td class="py-4 px-6 font-mono text-[10px] font-bold text-slate-500">
                                            <a href="{{ route('requisitions.show', $order->order_number) }}" class="text-emerald-600 hover:text-emerald-700 underline font-black">
                                                {{ $order->order_number }}
                                            </a>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            @if($order->is_delivered)
                                                <span class="inline-flex items-center bg-teal-50 text-teal-700 px-2 py-0.5 rounded-full text-[9px] font-black border border-teal-200">
                                                    Delivered
                                                </span>
                                            @elseif($order->is_allocation_completed)
                                                <span class="inline-flex items-center bg-sky-50 text-sky-700 px-2 py-0.5 rounded-full text-[9px] font-black border border-sky-200">
                                                    Shipped
                                                </span>
                                            @elseif($order->items->count() > 0 && $order->items->where('sorting_status', 'loaded')->count() === $order->items->count())
                                                <span class="inline-flex items-center bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full text-[9px] font-black border border-indigo-200">
                                                    Loaded
                                                </span>
                                            @elseif($order->items->count() > 0 && $order->items->where('is_sorted', true)->count() > 0)
                                                <span class="inline-flex items-center bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full text-[9px] font-black border border-amber-200">
                                                    Picking
                                                </span>
                                            @else
                                                <span class="inline-flex items-center bg-slate-50 text-slate-500 px-2 py-0.5 rounded-full text-[9px] font-bold border border-slate-200">
                                                    Approved
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
                                                @php $v = (float) $order->cash_discrepancy; @endphp
                                                <span class="{{ $v > 0.01 ? 'text-amber-600' : ($v < -0.01 ? 'text-blue-600' : 'text-emerald-600') }}">
                                                    Rs. {{ number_format(abs($v), 2) }}
                                                    <small class="text-[9px] font-bold">({{ $v > 0 ? 'Short' : 'Surp' }})</small>
                                                </span>
                                            @else
                                                <span class="text-slate-300 italic">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-12 text-center text-slate-400 font-medium italic bg-slate-50/10">
                                            No active requisitions recorded on this date.
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
</x-layouts.app>
