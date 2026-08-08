<x-layouts.app title="Loadout Details">
    <div class="mx-auto flex w-full max-w-xl min-w-0 flex-col gap-4 py-3 lg:px-4 lg:py-4">
        
        {{-- Hero Header Box --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_12px_28px_rgba(15,23,42,0.16)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.25),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#312e81_100%)] px-4 py-4 sm:px-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d')]) }}" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all border border-white/10 shadow-sm cursor-pointer text-decoration-none">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-base font-black tracking-tight text-white">{{ $order->loadoutDisplayName() }}</h1>
                            <p class="text-[9px] font-semibold text-indigo-300">Order: <span class="font-mono">{{ $order->order_number }}</span></p>
                        </div>
                    </div>
                    <span class="rounded-full bg-white/10 border border-white/10 px-2.5 py-1 text-[10px] font-black text-indigo-200">
                        {{ $order->shop->warehouse_tag ?? 'NO TAG' }}
                    </span>
                </div>
            </div>
        </section>

        {{-- Shop Details & Contact Card --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Shop Information</span>
                @if($order->shop?->code)
                    <span class="rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-[9px] font-black text-slate-700 font-mono">
                        {{ $order->shop->code }}
                    </span>
                @endif
            </div>
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <h3 class="text-base font-black text-slate-950">{{ $order->shop?->name ?? 'Direct Customer' }}</h3>
                    @if($order->shop?->contact_name)
                        <p class="text-xs font-semibold text-slate-700 mt-1">
                            Contact Person: <span class="font-bold text-slate-900">{{ $order->shop->contact_name }}</span>
                        </p>
                    @endif
                    @if($order->shop?->address)
                        <p class="text-xs text-slate-500 mt-1">
                            {{ $order->shop->address }}
                        </p>
                    @endif
                </div>
                @if($order->shop?->contact_phone)
                    <a href="tel:{{ $order->shop->contact_phone }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-black text-white hover:bg-emerald-500 shadow-sm transition-colors text-decoration-none border-none">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-2.828-1.41-5.183-3.765-6.593-6.593l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                        </svg>
                        Call: {{ $order->shop->contact_phone }}
                    </a>
                @endif
            </div>
        </section>

        @php
            $pendingItems = $order->items->where('sorting_status', '!=', 'loaded');
            $loadedItems = $order->items->where('sorting_status', 'loaded');
        @endphp

        @if($loadedItems->isNotEmpty())
            {{-- Loaded Items Manifest Table --}}
            <section class="rounded-2xl border border-emerald-100 bg-emerald-50/10 p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h3 class="text-xs font-black uppercase tracking-[0.14em] text-emerald-800 flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Loaded Items Manifest
                    </h3>
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-700">
                        {{ $loadedItems->count() }} Item(s)
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead>
                            <tr class="border-b border-slate-200/60 text-[10px] font-black uppercase tracking-wider text-slate-400">
                                <th class="py-2">Product</th>
                                <th class="py-2 text-right">Qty</th>
                                <th class="py-2 text-center">Discrepancy</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($loadedItems as $lItem)
                                <tr>
                                    <td class="py-2.5 font-bold text-slate-900">{{ $lItem->product->name }}</td>
                                    <td class="py-2.5 text-right font-mono font-black text-slate-900">
                                        {{ number_format((float) $lItem->loaded_qty, 2) }} <span class="text-[10px] font-bold text-slate-400">{{ $lItem->unit }}</span>
                                    </td>
                                    <td class="py-2.5 text-center">
                                        @if($lItem->loadout_discrepancy_type && $lItem->loadout_discrepancy_type !== 'none')
                                            <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-100 px-2 py-0.5 text-[8px] font-black uppercase text-amber-700" title="{{ $lItem->loadout_discrepancy_note }}">
                                                {{ $lItem->loadout_discrepancy_type }}
                                            </span>
                                        @else
                                            <span class="text-slate-400 font-bold">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($order->delivery_status === 'pending_delivery' || $order->delivery_status === 'in_transit')
                    <div class="pt-2 border-t border-emerald-200/60">
                        <form action="{{ route('warehouse.receiver.loadout.order.dispatch', $order) }}" method="POST" class="warehouse-confirm-form"
                            data-confirm-title="Move to Delivery"
                            data-confirm-message="Are you sure you want to move the loaded items to delivery? Remaining items will stay pending for split loadout."
                            data-confirm-button="Move to Delivery">
                            @csrf
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl py-2.5 text-[10px] font-black uppercase tracking-wider shadow-sm transition-all active:scale-98 flex items-center justify-center gap-1.5 border-none cursor-pointer">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                Move to Delivery
                            </button>
                        </form>
                    </div>
                @endif
            </section>
        @endif

        {{-- Filter & Actions Bar --}}
        <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between px-1">
            <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Items Checklist</h3>
            <div class="flex items-center gap-3">
                <button type="button" 
                        onclick="toggleHideLoaded()" 
                        id="toggle-loaded-btn" 
                        class="text-[10px] font-black uppercase tracking-wider text-slate-600 bg-slate-100 hover:bg-slate-200 border-none rounded-xl px-3 py-2 cursor-pointer transition-all">
                    Hide Loaded
                </button>
                @if($pendingItems->isNotEmpty())
                    <form action="{{ route('warehouse.receiver.loadout.order-all', $order) }}" method="POST" class="warehouse-confirm-form flex items-center gap-2"
                        data-confirm-title="Load all items"
                        data-confirm-message="Mark all {{ $pendingItems->count() }} remaining items as loaded? This will reduce them from active inventory."
                        data-confirm-button="Load all">
                        @csrf
                        <div class="flex items-center gap-1">
                            <input type="checkbox" name="skip_unavailable" id="skip-unavailable-top" value="1" checked class="h-3.5 w-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                            <label for="skip-unavailable-top" class="text-[9px] font-black uppercase tracking-wider text-slate-400 cursor-pointer select-none">Skip Unavailable</label>
                        </div>
                        <button type="submit" class="text-[10px] font-black uppercase tracking-wider text-white bg-emerald-600 hover:bg-emerald-700 border-none rounded-xl px-3 py-2 cursor-pointer transition-all flex items-center gap-1 shadow-sm">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Load All ({{ $pendingItems->count() }})
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Items List --}}
        <div class="space-y-3">
            @php
                $sortedItems = $order->items->sortBy(fn($item) => $item->sorting_status === 'loaded' ? 1 : 0);
            @endphp
            @foreach($sortedItems as $item)
                @php
                    $isLoaded = $item->sorting_status === 'loaded';
                    $approvedQty = (float) ($item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty);
                    $availableQty = (float) ($item->inventory_stock ?? 0.0);
                    $maxLoadableQty = $approvedQty;
                @endphp
                <div class="relative overflow-hidden rounded-2xl border bg-white p-4 shadow-sm transition hover:border-slate-300 flex flex-col gap-3 {{ $isLoaded ? 'border-emerald-200 bg-emerald-50/10' : 'border-slate-200' }}" data-item-id="{{ $item->id }}">
                    <div class="flex items-center justify-between gap-3 min-w-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 border border-slate-200">
                                <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h4 class="truncate text-sm font-black text-slate-900">{{ $item->product->name }}</h4>
                                <p class="text-[11px] font-bold text-slate-500 mt-0.5">
                                    Load: <span class="text-indigo-600 font-black">{{ number_format($approvedQty, 2) }}</span> / 
                                    <span class="{{ ($item->inventory_stock ?? 0.0) < $approvedQty ? 'text-amber-600 font-black' : 'text-slate-700 font-extrabold' }}">
                                        Avail: {{ number_format($item->inventory_stock ?? 0.0, 2) }}
                                    </span> {{ $item->unit }}
                                </p>

                            </div>
                        </div>
                        
                        @if($isLoaded)
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">
                                    Loaded ✓
                                </span>
                                <span class="text-[10px] text-slate-500 font-bold">
                                    Loaded: {{ number_format((float) $item->loaded_qty, 2) }} {{ $item->unit }}
                                </span>
                            </div>
                        @else
                            {{-- Quick Load / Adjust Actions --}}
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" 
                                        onclick="toggleAdjustForm({{ $item->id }})" 
                                        class="text-xs font-black text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 border-none rounded-xl px-2.5 py-2 cursor-pointer transition-all">
                                    Adjust
                                </button>
                                <form action="{{ route('warehouse.receiver.loadout.item', $item) }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="loaded_qty" value="{{ number_format($approvedQty, 2, '.', '') }}">
                                    <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-500 hover:bg-emerald-600 cursor-pointer active:scale-95 text-white border-none shadow-sm transition-colors">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>

                    @if(!$isLoaded)
                        {{-- Detailed adjustment form (hidden by default) --}}
                        <form action="{{ route('warehouse.receiver.loadout.item', $item) }}" 
                              method="POST" 
                              id="adjust-form-{{ $item->id }}" 
                              class="adjust-form mt-2 pt-2 border-t border-dashed border-slate-100 hidden flex-col gap-2">
                            @csrf
                            <div class="flex items-center gap-2">
                                <div class="flex-1 min-w-0">
                                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-400 mb-0.5">Physical Loaded Qty</label>
                                    <input type="number" 
                                           step="0.01" 
                                           name="loaded_qty" 
                                           value="{{ number_format($maxLoadableQty, 2, '.', '') }}"
                                           data-approved="{{ $approvedQty }}"
                                           data-max-loadable="{{ $maxLoadableQty }}"
                                           max="{{ $maxLoadableQty }}"
                                           class="loaded-qty-input w-full rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-black text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                           required>
                                </div>
                                <div class="shrink-0 pt-3.5">
                                    <button type="submit" class="shrink-0 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-2 text-xs font-black shadow-sm transition-colors border-none cursor-pointer">
                                        ✓ Save & Load
                                    </button>
                                </div>
                            </div>

                            <div class="loadout-discrepancy-panel bg-slate-50 border border-slate-100 rounded-xl p-2.5 hidden">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[9px] font-black uppercase tracking-wider text-slate-400 mb-0.5">Discrepancy Action</label>
                                        <div class="relative w-full">
                                             <select name="discrepancy_type" class="loadout-discrepancy-type w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-8 py-2 text-xs font-semibold text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none cursor-pointer transition-colors hover:bg-slate-50">
                                                 <option value="none">Choose...</option>
                                                 <option value="wastage">Wastage</option>
                                                 <option value="other">Other (Adjustment)</option>
                                             </select>
                                             <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                                 <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                     <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                 </svg>
                                             </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-black uppercase tracking-wider text-slate-400 mb-0.5">Discrepancy Note</label>
                                        <input type="text" name="discrepancy_note" placeholder="Reason..." class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Footer Action Card --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm flex flex-col gap-3">
            <div class="flex flex-col gap-2">
                @if($pendingItems->isNotEmpty())
                    <form action="{{ route('warehouse.receiver.loadout.order-all', $order) }}" method="POST" class="warehouse-confirm-form space-y-2.5"
                        data-confirm-title="Load all items"
                        data-confirm-message="Mark all {{ $pendingItems->count() }} remaining items as loaded? This will reduce them from active inventory."
                        data-confirm-button="Load all">
                        @csrf
                        <div class="flex items-center gap-1.5 px-0.5">
                            <input type="checkbox" name="skip_unavailable" id="skip-unavailable-bottom" value="1" checked class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                            <label for="skip-unavailable-bottom" class="text-xs font-black uppercase tracking-wider text-slate-500 cursor-pointer select-none">Skip Unavailable Items</label>
                        </div>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-sm transition-all active:scale-98 flex items-center justify-center gap-2 border-none cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                            Load All {{ $pendingItems->count() }} Item(s)
                        </button>
                    </form>

                    @if($order->delivery_status === 'pending_delivery' || $order->delivery_status === 'in_transit')
                        <form action="{{ route('warehouse.receiver.loadout.order.dispatch-partial', $order) }}" method="POST" class="warehouse-confirm-form"
                            data-confirm-title="Move to Delivery (Partial)"
                            data-confirm-message="Are you sure you want to move this order to delivery as a partial delivery? All remaining {{ $pendingItems->count() }} items will be marked as not loaded (discrepancy)."
                            data-confirm-button="Move to Delivery">
                            @csrf
                            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-sm transition-all active:scale-98 flex items-center justify-center gap-2 border-none cursor-pointer">
                                Move to Delivery (Partial)
                            </button>
                        </form>
                    @endif
                @else
                    @if($order->delivery_status === 'pending_delivery' || $order->delivery_status === 'in_transit')
                        @if($order->delivery_status === 'in_transit')
                            <div class="text-center py-1 mb-2">
                                <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-amber-700">
                                    Status: Moved to Delivery (Split Shipment)
                                </span>
                            </div>
                        @endif
                        <form action="{{ route('warehouse.receiver.loadout.order.dispatch', $order) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-sm transition-all active:scale-98 flex items-center justify-center gap-2 border-none cursor-pointer">
                                Move to Delivery (Complete)
                            </button>
                        </form>
                    @else
                        <div class="text-center py-1">
                            <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700">
                                Status: {{ ucfirst($order->delivery_status) }}
                            </span>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Inline navigation shortcuts --}}
            <div class="flex items-center justify-around gap-1 border-t border-slate-100 pt-3 mt-1">
                <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'pending']) }}" class="flex flex-col items-center gap-1 text-[10px] font-black uppercase text-slate-400 hover:text-indigo-600 transition-colors text-decoration-none">
                    Receive
                </a>
                <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'inventory']) }}" class="flex flex-col items-center gap-1 text-[10px] font-black uppercase text-slate-400 hover:text-indigo-600 transition-colors text-decoration-none">
                    Inventory
                </a>
                <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'loadout']) }}" class="flex flex-col items-center gap-1 text-[10px] font-black uppercase text-indigo-600 transition-colors text-decoration-none">
                    Loadout
                </a>
                <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'confirmed']) }}" class="flex flex-col items-center gap-1 text-[10px] font-black uppercase text-slate-400 hover:text-indigo-600 transition-colors text-decoration-none">
                    Delivery
                </a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let hideLoaded = false;

        function toggleHideLoaded() {
            hideLoaded = !hideLoaded;
            const loadedCards = document.querySelectorAll('[data-item-id]');
            const btn = document.getElementById('toggle-loaded-btn');
            
            loadedCards.forEach(card => {
                if (card.classList.contains('border-emerald-200')) {
                    if (hideLoaded) {
                        card.classList.add('hidden');
                    } else {
                        card.classList.remove('hidden');
                    }
                }
            });
            
            if (btn) {
                btn.textContent = hideLoaded ? 'Show Loaded' : 'Hide Loaded';
            }
        }

        function toggleAdjustForm(itemId) {
            const form = document.getElementById('adjust-form-' + itemId);
            if (form) {
                form.classList.toggle('hidden');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const qtyInputs = document.querySelectorAll('.loaded-qty-input');

            qtyInputs.forEach(input => {
                input.addEventListener('input', () => {
                    const approved = parseFloat(input.dataset.approved);
                    const maxLoadable = parseFloat(input.dataset.maxLoadable || input.dataset.approved);
                    let loaded = parseFloat(input.value) || 0;
                    const container = input.closest('[data-item-id]');
                    const panel = container.querySelector('.loadout-discrepancy-panel');
                    const select = container.querySelector('.loadout-discrepancy-type');

                    if (loaded > maxLoadable) {
                        loaded = maxLoadable;
                        input.value = maxLoadable.toFixed(2);
                    }

                    if (loaded < approved) {
                        panel.classList.remove('hidden');
                        select.required = true;
                        if (select.value === 'none') {
                            select.value = 'wastage'; // default to wastage
                        }
                    } else {
                        panel.classList.add('hidden');
                        select.required = false;
                        select.value = 'none';
                    }
                });
            });
     
            document.querySelectorAll('.warehouse-confirm-form').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (form.dataset.appConfirmBypass === 'true') {
                        form.dataset.appConfirmBypass = 'false';
                        return;
                    }

                    event.preventDefault();

                    window.showAppConfirm({
                        title: form.dataset.confirmTitle || 'Confirm action',
                        message: form.dataset.confirmMessage || 'Are you sure you want to continue?',
                        confirmLabel: form.dataset.confirmButton || 'Confirm',
                        cancelLabel: 'Cancel',
                        tone: 'danger',
                        onConfirm: () => {
                            form.dataset.appConfirmBypass = 'true';
                            HTMLFormElement.prototype.submit.call(form);
                        },
                    });
                });
            });
        });
    </script>
    @endpush
</x-layouts.app>
