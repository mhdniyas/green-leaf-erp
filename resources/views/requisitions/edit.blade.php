@php($cutoffLabel = app(\App\Services\Purchasing\PurchaserBusinessDayService::class)->cutoffLabel())

<x-layouts.app title="Edit Requisition — {{ $order->order_number }}">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('requisitions.show', $order->order_number) }}" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1.5 transition-colors mb-2">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                Back to Details
            </a>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Edit Requisition: <span class="text-emerald-600">{{ $order->order_number }}</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Enforcing {{ $cutoffLabel }} cutoff deadline for tomorrow's deliveries.</p>
        </div>

        <form action="{{ route('requisitions.update', $order->order_number) }}" method="POST" class="space-y-6">
            @csrf
            
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Modify Item Quantities</h2>
                    <p class="text-[10px] text-slate-400 mt-0.5">Note: Setting an item quantity to 0 or leaving it blank will remove it from the requisition.</p>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach($order->items as $item)
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <span class="text-xs font-bold text-slate-900">{{ $item->product->name }}</span>
                                <span class="block text-[10px] text-slate-400 mt-0.5">{{ $item->product->sku }}</span>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="relative rounded-xl shadow-sm max-w-[150px]">
                                    <input type="number" name="items[{{ $item->id }}]" value="{{ floatval($item->requested_qty) }}" step="0.01" min="0" required class="w-full text-xs font-bold text-slate-900 bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 focus:outline-none focus:border-slate-300 text-right pr-12">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-[10px] font-black text-slate-400 uppercase">{{ $item->unit }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('requisitions.show', $order->order_number) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-6 py-2.5 rounded-xl transition-all cursor-pointer focus:outline-none border border-slate-200">
                    Cancel
                </a>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-6 py-2.5 rounded-xl shadow-sm transition-all cursor-pointer focus:outline-none">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
