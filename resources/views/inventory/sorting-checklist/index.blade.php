<x-layouts.app title="Warehouse Sorting Checklist">

    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('inventory.sorting.checklist', ['date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d')]) }}" class="p-2 bg-white rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors shadow-xs" title="Previous Day">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </a>
            <form id="date-form" method="GET" action="{{ route('inventory.sorting.checklist') }}" class="flex items-center gap-2">
                <input id="date-select" type="date" name="date" value="{{ $date }}" onchange="document.getElementById('date-form').submit();"
                       class="border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white shadow-xs">
            </form>
            <a href="{{ route('inventory.sorting.checklist', ['date' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d')]) }}" class="p-2 bg-white rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors shadow-xs" title="Next Day">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>
            <a href="{{ route('inventory.sorting.checklist', ['date' => \Carbon\Carbon::today()->format('Y-m-d')]) }}" class="px-3 py-1.5 bg-brand-50 text-brand-700 border border-brand-200 rounded-xl text-xs font-bold hover:bg-brand-100 transition-colors shadow-xs">
                Today
            </a>
        </div>
    </x-slot:actions>

    {{-- Global Progress Summary --}}
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-black text-slate-800 tracking-tight">Warehouse Dispatch Sorting Progress</h2>
                <p class="text-xs text-slate-400 mt-1">Warehouse receives supplier deliveries, submits GRN reports for manager approval, then sorts approved stock into shop dispatch points.</p>
            </div>
            <div class="flex items-center gap-4 shrink-0">
                <div class="text-right">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Allocation Progress</p>
                    <p id="global-stats-text" class="text-2xl font-black text-brand-600 mt-0.5">
                        {{ $globalPercentage }}% <span class="text-sm font-semibold text-gray-500">({{ $sortedItems }}/{{ $totalItems }} items sorted/loaded)</span>
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-brand-50 flex items-center justify-center text-brand-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Progress Bar --}}
        <div class="w-full bg-slate-100 h-3 rounded-full mt-5 overflow-hidden">
            <div id="global-progress-bar" class="h-full bg-brand-500 transition-all duration-500 ease-out rounded-full" style="width: {{ $globalPercentage }}%;"></div>
        </div>
    </div>

    {{-- Main Workspace: Split-pane Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- Left Pane: Goods Receipt & Daily Stock Batches --}}
        <div class="lg:col-span-4 space-y-6">

            {{-- 1. Goods Receipt Panel --}}
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 bg-slate-50/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Warehouse Receiving Queue</h3>
                        <p class="text-[10px] text-slate-400 mt-0.5">Purchase orders waiting for warehouse quantity and quality reporting</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-blue-50 text-blue-700 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider border border-blue-200">
                        {{ $purchaseOrders->count() }} Awaiting
                    </span>
                </div>

                <div class="p-5 space-y-4 max-h-[350px] overflow-y-auto divide-y divide-slate-100">
                    @if($purchaseOrders->isEmpty())
                        <div class="py-8 text-center">
                            <div class="w-10 h-10 rounded-2xl bg-slate-50 flex items-center justify-center mx-auto mb-2 text-slate-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            </div>
                            <p class="text-xs font-bold text-slate-600">All POs Received</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">No supplier deliveries are waiting for a warehouse GRN report.</p>
                        </div>
                    @else
                        @foreach($purchaseOrders as $po)
                            <div class="pt-4 first:pt-0 flex flex-col gap-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <h4 class="text-xs font-black text-slate-800">{{ $po->po_number }}</h4>
                                        <p class="text-[10px] text-slate-500 font-semibold mt-0.5">{{ $po->supplier->name }}</p>
                                    </div>
                                    <button onclick="openGrnModal(this)"
                                            data-id="{{ $po->id }}"
                                            data-po-number="{{ $po->po_number }}"
                                            data-supplier-name="{{ $po->supplier->name }}"
                                            data-items="{{ $po->items->toJson() }}"
                                            class="px-2.5 py-1 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-[10px] font-bold shadow-xs transition-colors cursor-pointer">
                                        Create GRN Report
                                    </button>
                                </div>
                                <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100 flex flex-col gap-1.5">
                                    @foreach($po->items->take(3) as $poItem)
                                        <div class="flex items-center justify-between text-[10px]">
                                            <span class="font-bold text-slate-600 truncate max-w-[120px]">{{ $poItem->product->name }}</span>
                                            <span class="font-mono text-slate-500">{{ number_format((float)$poItem->quantity, 2) }} {{ $poItem->purchase_unit }}</span>
                                        </div>
                                    @endforeach
                                    @if($po->items->count() > 3)
                                        <div class="text-[9px] font-bold text-slate-400 text-right">+{{ $po->items->count() - 3 }} more items</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 bg-slate-50/50">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Submitted GRN Reports</h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">Warehouse reports sent to purchase manager for approval or correction</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-700 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider border border-slate-200">
                            {{ $submittedGrns->count() }} Total
                        </span>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2">
                            <p class="text-[9px] font-black uppercase tracking-wider text-amber-700">Pending</p>
                            <p class="mt-1 text-lg font-black text-amber-900">{{ $pendingApprovalGrnCount }}</p>
                        </div>
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2">
                            <p class="text-[9px] font-black uppercase tracking-wider text-rose-700">Rejected</p>
                            <p class="mt-1 text-lg font-black text-rose-900">{{ $rejectedGrnCount }}</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                            <p class="text-[9px] font-black uppercase tracking-wider text-emerald-700">Approved</p>
                            <p class="mt-1 text-lg font-black text-emerald-900">{{ $approvedGrnCount }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-5 space-y-4 max-h-[360px] overflow-y-auto divide-y divide-slate-100">
                    @if($submittedGrns->isEmpty())
                        <div class="py-10 text-center">
                            <div class="w-10 h-10 rounded-2xl bg-slate-50 flex items-center justify-center mx-auto mb-2 text-slate-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5h15m-15-15h15M6.75 16.5h10.5a.75.75 0 00.75-.75V8.25a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v7.5a.75.75 0 00.75.75z" /></svg>
                            </div>
                            <p class="text-xs font-bold text-slate-600">No GRN reports yet</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Create a warehouse report once a supplier delivery reaches the warehouse.</p>
                        </div>
                    @else
                        @foreach($submittedGrns as $grn)
                            @php
                                $statusClasses = match ($grn->status) {
                                    'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    default => 'bg-amber-50 text-amber-700 border-amber-200',
                                };
                                $statusMessage = match ($grn->status) {
                                    'approved' => 'Approved by purchase manager. Stock is now available for allocation.',
                                    'rejected' => $grn->rejection_remarks ?: 'Returned by purchase manager for warehouse correction.',
                                    default => 'Awaiting purchase manager review before inventory is updated.',
                                };
                            @endphp
                            <div class="pt-4 first:pt-0 space-y-2.5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-black text-slate-800">{{ $grn->grn_number }}</p>
                                        <p class="mt-0.5 text-[10px] font-semibold text-slate-500">
                                            {{ $grn->purchaseOrder->po_number }} · {{ $grn->purchaseOrder->supplier?->name ?? 'Unknown Supplier' }}
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $statusClasses }}">
                                        {{ str($grn->status)->replace('_', ' ') }}
                                    </span>
                                </div>
                                <p class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-semibold leading-5 text-slate-600">
                                    {{ $statusMessage }}
                                </p>
                                <div class="flex items-center justify-between gap-3 text-[10px] text-slate-500">
                                    <span>Reported by {{ $grn->receivedBy?->name ?? 'Warehouse' }}</span>
                                    <span>{{ $grn->received_at->format('Y-m-d') }}</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('purchasing.grns.show', $grn) }}" class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-1.5 text-[10px] font-black text-slate-700 hover:bg-slate-50 transition-colors">
                                        View Report
                                    </a>
                                    @if($grn->status === 'rejected')
                                        <a href="{{ route('purchasing.grns.edit', $grn) }}" class="inline-flex items-center rounded-xl bg-rose-600 px-3 py-1.5 text-[10px] font-black text-white hover:bg-rose-700 transition-colors">
                                            Correct & Resubmit
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- 2. Daily Stock Batches (Carry-over / Wastage) --}}
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 bg-slate-50/50">
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Today's Received Stock Batches</h3>
                    <p class="text-[10px] text-slate-400 mt-0.5">Manage carry over and wastage for received stock</p>
                </div>

                <div class="p-5 space-y-4 max-h-[450px] overflow-y-auto divide-y divide-slate-100">
                    @if($stockBatches->isEmpty())
                        <div class="py-12 text-center">
                            <div class="w-10 h-10 rounded-2xl bg-slate-50 flex items-center justify-center mx-auto mb-2 text-slate-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                            </div>
                            <p class="text-xs font-bold text-slate-600">No Batches Checked In</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Record Goods Receipts to populate stock batches for today.</p>
                        </div>
                    @else
                        @foreach($stockBatches as $batch)
                            @php
                                $wastedQty = (float)$batch->wastageEntries->sum('quantity');
                                $availableQty = (float)$batch->total_kg - $wastedQty;
                            @endphp
                            <div class="pt-4 first:pt-0 flex flex-col gap-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h4 class="text-xs font-black text-slate-800 truncate" title="{{ $batch->product->name }}">{{ $batch->product->name }}</h4>
                                        <div class="flex items-center gap-1.5 mt-0.5 text-[9px] font-semibold text-slate-400">
                                            <span class="font-mono bg-slate-100 px-1 py-0.5 rounded">{{ $batch->reference }}</span>
                                            <span>·</span>
                                            <span>{{ number_format($availableQty, 2) }} kg left</span>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $batch->status->value === 'pending' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-green-50 text-green-700 border border-green-200' }}">
                                        {{ $batch->status->label() }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="carryOverBatch({{ $batch->id }}, '{{ $batch->reference }}')"
                                            class="flex-1 py-1 px-2 border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg text-[10px] font-bold transition-colors cursor-pointer flex items-center justify-center gap-1">
                                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12.75 15l3-3m0 0l-3-3m3 3h-7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Carry Over
                                    </button>
                                    <button onclick="openWastageModal({{ $batch->id }}, '{{ $batch->product->name }}', {{ $availableQty }})"
                                            class="flex-1 py-1 px-2 border border-red-200 hover:bg-red-50 text-red-700 rounded-lg text-[10px] font-bold transition-colors cursor-pointer flex items-center justify-center gap-1">
                                        <svg class="w-3 h-3 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                                        Wastage
                                    </button>
                                </div>
                                @if($wastedQty > 0)
                                    <div class="text-[9px] font-semibold text-red-600 bg-red-50 border border-red-100 rounded-lg px-2 py-1">
                                        ⚠️ Written off: {{ number_format($wastedQty, 2) }} kg wastage logged today
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- Right Pane: Shop Cards Checklist --}}
        <div class="lg:col-span-8 space-y-6">
            @if($orders->isEmpty())
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-16 text-center">
                    <div class="w-16 h-16 rounded-3xl bg-slate-50 flex items-center justify-center mx-auto mb-4 text-slate-400">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.03 0 1.9.693 2.166 1.638m-7.377 0A48.536 48.536 0 0112 3.75c0 .08-.004.16-.01.238m-2.886 0c.385.023.77.05 1.154.08m-3.456 0A48.108 48.108 0 002.25 6.11v10.39a2.25 2.25 0 002.25 2.25h3" /></svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-900">No approved orders found</h3>
                    <p class="text-sm text-slate-500 mt-1">There are no approved shop requisitions to be sorted for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($orders as $order)
                        @php
                            $orderTotal = $order->items->count();
                            $orderSorted = $order->items->where('is_sorted', true)->count();
                            $orderPercentage = $orderTotal > 0 ? (int) round(($orderSorted / $orderTotal) * 100) : 0;
                        @endphp
                        <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden flex flex-col h-full {{ $order->is_allocation_completed ? 'ring-2 ring-emerald-500/20' : '' }}" id="shop-card-{{ $order->shop_id }}">
                            
                            {{-- Card Header --}}
                            <div class="p-5 border-b border-gray-100 bg-slate-50/50">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-black text-slate-800 tracking-tight">{{ $order->shop ? $order->shop->name : 'Casio Hypermarket' }}</h3>
                                        <p class="text-[10px] font-mono font-bold text-slate-400 mt-0.5">POINT #{{ $loop->iteration }} · {{ $order->order_number }}</p>
                                    </div>
                                    <div class="flex flex-col items-end gap-1.5">
                                        <span id="shop-badge-{{ $order->shop_id }}" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $orderPercentage === 100 ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-brand-50 text-brand-700 border border-brand-200' }}">
                                            <span id="shop-percentage-text-{{ $order->shop_id }}">{{ $orderPercentage }}%</span>
                                        </span>
                                        @if($order->is_allocation_completed)
                                            <span class="inline-flex items-center gap-0.5 text-[8px] font-extrabold uppercase bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded-sm">
                                                ✓ Finalized
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Shop Local Progress Bar --}}
                                <div class="w-full bg-slate-200 h-1.5 rounded-full mt-4 overflow-hidden">
                                    <div id="shop-progress-bar-{{ $order->shop_id }}" class="h-full bg-brand-500 transition-all duration-300 rounded-full" style="width: {{ $orderPercentage }}%;"></div>
                                </div>
                                <p id="shop-ratio-text-{{ $order->shop_id }}" class="text-[10px] font-bold text-slate-400 mt-1.5">
                                    {{ $orderSorted }} of {{ $orderTotal }} items sorted
                                </p>
                            </div>

                            {{-- Card Body: Items List --}}
                            <div class="p-5 flex-1 divide-y divide-slate-100 overflow-y-auto">
                                @foreach($order->items as $item)
                                    <div class="py-3.5 first:pt-0 last:pb-0 flex flex-col gap-1.5 transition-all duration-300 {{ $item->is_sorted ? 'opacity-85' : '' }}" id="item-row-{{ $item->id }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <span class="text-xs font-bold text-slate-800 block select-none {{ $item->sorting_status === 'loaded' ? 'text-indigo-900' : ($item->is_sorted ? 'text-emerald-900' : '') }}" id="label-{{ $item->id }}">
                                                    {{ $item->product->name }}
                                                </span>
                                                <div class="flex items-center gap-1.5 mt-0.5">
                                                    <span class="text-[9px] font-bold text-slate-400">{{ $item->product->sku }}</span>
                                                    <span class="text-[9px] font-bold text-slate-400">·</span>
                                                    <span class="text-[9px] font-bold text-slate-500 bg-slate-100 px-1 py-0.5 rounded uppercase">{{ ucfirst($item->fulfillment_type ?? 'warehouse') }}</span>
                                                    <span class="text-[9px] font-bold text-slate-400">·</span>
                                                    <span class="text-[9px] font-bold text-slate-500 font-mono">Ordered: {{ number_format((float) $item->requested_qty, 2) }}</span>
                                                </div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <p class="text-xs font-black text-slate-800">{{ number_format((float) $item->approved_qty, 2) }}</p>
                                                <p class="text-[9px] font-bold text-slate-400 uppercase mt-0.5">{{ $item->unit }}</p>
                                            </div>
                                        </div>

                                        {{-- 3-State Status Pill Selector --}}
                                        <div class="flex items-center justify-between gap-2 mt-1">
                                            <div class="inline-flex rounded-xl p-0.5 bg-slate-100 border border-slate-200 shrink-0 select-none">
                                                <button onclick="updateItemStatus({{ $item->id }}, 'pending')" id="btn-pending-{{ $item->id }}" 
                                                        class="px-2 py-0.5 rounded-lg text-[9px] font-black transition-all cursor-pointer {{ $item->sorting_status === 'pending' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-400 hover:text-slate-700' }}">
                                                    Pending
                                                </button>
                                                <button onclick="updateItemStatus({{ $item->id }}, 'allocated')" id="btn-allocated-{{ $item->id }}" 
                                                        class="px-2 py-0.5 rounded-lg text-[9px] font-black transition-all cursor-pointer {{ $item->sorting_status === 'allocated' ? 'bg-emerald-500 text-white shadow-xs' : 'text-slate-400 hover:text-slate-700' }}">
                                                    Allocated
                                                </button>
                                                <button onclick="updateItemStatus({{ $item->id }}, 'loaded')" id="btn-loaded-{{ $item->id }}" 
                                                        class="px-2 py-0.5 rounded-lg text-[9px] font-black transition-all cursor-pointer {{ $item->sorting_status === 'loaded' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-400 hover:text-slate-700' }}">
                                                    Loaded
                                                </button>
                                            </div>

                                            {{-- Sorting Meta Info --}}
                                            <div id="meta-container-{{ $item->id }}" class="{{ $item->is_sorted ? '' : 'hidden' }}">
                                                <p id="meta-text-{{ $item->id }}" class="text-[8px] font-bold text-slate-500 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-lg inline-flex items-center gap-1">
                                                    <span>{{ $item->is_sorted && $item->sortedBy ? '✓ ' . $item->sortedBy->name : '' }}</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Card Footer: Finalize Allocation --}}
                            <div class="p-5 border-t border-gray-100 bg-slate-50/50">
                                @if($order->is_allocation_completed)
                                    <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-3 text-emerald-800 flex flex-col gap-1">
                                        <p class="text-[10px] font-black flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Allocation finalized
                                        </p>
                                        @if($order->sorting_notes)
                                            <p class="text-[9px] text-emerald-600 bg-white/60 p-1.5 rounded-lg font-semibold mt-1">
                                                <span class="font-bold uppercase block text-[8px] text-emerald-700 mb-0.5">Sorting Notes:</span>
                                                {{ $order->sorting_notes }}
                                            </p>
                                        @else
                                            <p class="text-[9px] text-emerald-500 italic mt-0.5">Successful 100% as per approved order.</p>
                                        @endif
                                    </div>
                                @else
                                    <form onsubmit="finalizeOrderSheet(event, {{ $order->id }})" class="space-y-3">
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="success-100-{{ $order->id }}" checked onchange="toggleNotesField({{ $order->id }})"
                                                   class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/20 cursor-pointer">
                                            <label for="success-100-{{ $order->id }}" class="text-[10px] font-bold text-slate-700 cursor-pointer select-none">
                                                Order successful 100% as per approved order
                                            </label>
                                        </div>

                                        <div id="notes-container-{{ $order->id }}" class="hidden transition-all duration-300">
                                            <label for="sorting-notes-{{ $order->id }}" class="block text-[9px] font-bold text-slate-400 uppercase">Specify Changes / Notes</label>
                                            <textarea id="sorting-notes-{{ $order->id }}" rows="2" placeholder="Detail any discrepancy or allocation shifts..."
                                                      class="w-full mt-1 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white"></textarea>
                                        </div>

                                        <button type="submit" class="w-full py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-black shadow-xs transition-colors cursor-pointer text-center">
                                            Finalize Shop Allocation Sheet
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── Goods Receipt Modal ── --}}
    <div id="grn-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900/60 transition-opacity" aria-hidden="true" onclick="closeGrnModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="relative z-10 inline-block align-middle bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-slate-100">
                <form id="grn-form" onsubmit="submitGrn(event)">
                    @csrf
                    <input type="hidden" name="purchase_order_id" id="grn-po-id">
                    
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-black text-slate-800">Warehouse GRN Report</h3>
                                <p class="text-xs text-slate-400 mt-1" id="grn-modal-subtitle"></p>
                            </div>
                            <button type="button" onclick="closeGrnModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase">Received Date</label>
                                <input type="date" name="received_at" value="{{ $date }}" required class="w-full mt-1 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase">Notes</label>
                                <input type="text" name="notes" placeholder="Optional notes..." class="w-full mt-1 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase">Transport Cost</label>
                                <input type="number" step="0.01" min="0" name="transport_cost" value="0.00" class="w-full mt-1 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase">Labour Cost</label>
                                <input type="number" step="0.01" min="0" name="labour_cost" value="0.00" class="w-full mt-1 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                            </div>
                        </div>

                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-[11px] font-semibold leading-5 text-amber-800">
                            Submit the warehouse report after checking quantity and quality. Inventory will be added only after purchase manager approval.
                        </div>
                        
                        <div class="mt-6">
                            <h4 class="text-xs font-bold text-slate-700 mb-3 uppercase tracking-wider">Discrepancy Check</h4>
                            <div class="divide-y divide-slate-100 border border-slate-200 rounded-2xl overflow-hidden bg-slate-50/20" id="grn-items-container">
                                <!-- Populated dynamically by javascript -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3">
                        <button type="button" onclick="closeGrnModal()" class="px-4 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 transition-colors cursor-pointer">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold shadow-sm transition-colors cursor-pointer">Submit for Manager Approval</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Wastage Modal ── --}}
    <div id="wastage-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900/60 transition-opacity" aria-hidden="true" onclick="closeWastageModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="relative z-10 inline-block align-middle bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-100">
                <form id="wastage-form" onsubmit="submitWastage(event)">
                    @csrf
                    <input type="hidden" id="wastage-batch-id">
                    
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-black text-slate-800">Record Product Wastage</h3>
                                <p class="text-xs text-slate-400 mt-1" id="wastage-modal-subtitle"></p>
                            </div>
                            <button type="button" onclick="closeWastageModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase">Wasted Quantity (kg)</label>
                            <input type="number" step="0.01" min="0.01" id="wastage-quantity" required class="w-full mt-1 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                            <p class="text-[10px] text-slate-400 mt-1" id="wastage-max-qty-label"></p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase">Reason</label>
                            <select id="wastage-reason" required class="w-full mt-1 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                                @foreach($wastageReasons as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase">Notes / Explanations</label>
                            <textarea id="wastage-notes" placeholder="Specify write-off notes..." rows="3" class="w-full mt-1 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white"></textarea>
                        </div>
                    </div>
                    
                    <div class="p-6 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3">
                        <button type="button" onclick="closeWastageModal()" class="px-4 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 transition-colors cursor-pointer">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-sm transition-colors cursor-pointer">Record Wastage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Dynamic Toast Notification Container --}}
    <div id="toast-container" class="fixed bottom-5 right-5 z-55 flex flex-col gap-2"></div>

    @push('scripts')
        <script>
            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `flex items-center gap-3 rounded-2xl border px-4 py-3 shadow-lg transform transition-all duration-300 translate-y-2 opacity-0 bg-white ${
                    type === 'success' ? 'border-green-200 text-green-800' : 'border-red-200 text-red-800'
                }`;
                
                const icon = type === 'success' 
                    ? `<svg class="w-5 h-5 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
                    : `<svg class="w-5 h-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>`;
                
                toast.innerHTML = `
                    ${icon}
                    <p class="text-xs font-bold leading-none">${message}</p>
                `;
                
                const container = document.getElementById('toast-container');
                if (container) {
                    container.appendChild(toast);
                    setTimeout(() => {
                        toast.classList.remove('translate-y-2', 'opacity-0');
                    }, 10);
                    setTimeout(() => {
                        toast.classList.add('translate-y-2', 'opacity-0');
                        setTimeout(() => toast.remove(), 300);
                    }, 4000);
                }
            }

            // ── 3-State Status Toggler ──
            function updateItemStatus(itemId, status) {
                const btnPending = document.getElementById(`btn-pending-${itemId}`);
                const btnAllocated = document.getElementById(`btn-allocated-${itemId}`);
                const btnLoaded = document.getElementById(`btn-loaded-${itemId}`);
                const label = document.getElementById(`label-${itemId}`);
                const itemRow = document.getElementById(`item-row-${itemId}`);
                const metaContainer = document.getElementById(`meta-container-${itemId}`);

                // Send request
                fetch(`/inventory/sorting-checklist/toggle/${itemId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status: status })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Action unauthorized or error.');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Reset all buttons styling
                        btnPending.className = "px-2 py-0.5 rounded-lg text-[9px] font-black transition-all cursor-pointer text-slate-400 hover:text-slate-700";
                        btnAllocated.className = "px-2 py-0.5 rounded-lg text-[9px] font-black transition-all cursor-pointer text-slate-400 hover:text-slate-700";
                        btnLoaded.className = "px-2 py-0.5 rounded-lg text-[9px] font-black transition-all cursor-pointer text-slate-400 hover:text-slate-700";

                        // Set active styling based on response status
                        if (data.item.sorting_status === 'pending') {
                            btnPending.className = "px-2 py-0.5 rounded-lg text-[9px] font-black transition-all cursor-pointer bg-white text-slate-800 shadow-xs";
                            label.className = "text-xs font-bold text-slate-800 block select-none";
                            itemRow.classList.remove('opacity-85');
                            metaContainer.classList.add('hidden');
                        } else if (data.item.sorting_status === 'allocated') {
                            btnAllocated.className = "px-2 py-0.5 rounded-lg text-[9px] font-black transition-all cursor-pointer bg-emerald-500 text-white shadow-xs";
                            label.className = "text-xs font-bold text-emerald-900 block select-none";
                            itemRow.classList.add('opacity-85');
                            metaContainer.classList.remove('hidden');
                            metaContainer.querySelector('span').textContent = `✓ Allocated by ${data.item.sorted_by_name} at ${data.item.sorted_at_formatted}`;
                        } else if (data.item.sorting_status === 'loaded') {
                            btnLoaded.className = "px-2 py-0.5 rounded-lg text-[9px] font-black transition-all cursor-pointer bg-indigo-600 text-white shadow-xs";
                            label.className = "text-xs font-bold text-indigo-900 block select-none";
                            itemRow.classList.add('opacity-85');
                            metaContainer.classList.remove('hidden');
                            metaContainer.querySelector('span').textContent = `🚚 Loaded by ${data.item.sorted_by_name} at ${data.item.sorted_at_formatted}`;
                        }

                        // Update shop points stats
                        const shopId = data.shop_progress.shop_id;
                        const percentage = data.shop_progress.percentage;
                        
                        const shopPercentageText = document.getElementById(`shop-percentage-text-${shopId}`);
                        const shopProgressBar = document.getElementById(`shop-progress-bar-${shopId}`);
                        const shopRatioText = document.getElementById(`shop-ratio-text-${shopId}`);
                        const shopBadge = document.getElementById(`shop-badge-${shopId}`);

                        if (shopPercentageText) shopPercentageText.textContent = `${percentage}%`;
                        if (shopProgressBar) shopProgressBar.style.width = `${percentage}%`;
                        if (shopRatioText) shopRatioText.textContent = `${data.shop_progress.sorted} of ${data.shop_progress.total} items sorted`;
                        
                        if (shopBadge) {
                            if (percentage === 100) {
                                shopBadge.className = "inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider bg-green-50 text-green-700 border border-green-200";
                            } else {
                                shopBadge.className = "inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider bg-brand-50 text-brand-700 border border-brand-200";
                            }
                        }

                        // Update global progress bar
                        const globalPercentage = data.global_progress.percentage;
                        const globalProgressBar = document.getElementById('global-progress-bar');
                        const globalStatsText = document.getElementById('global-stats-text');

                        if (globalProgressBar) globalProgressBar.style.width = `${globalPercentage}%`;
                        if (globalStatsText) {
                            globalStatsText.innerHTML = `${globalPercentage}% <span class="text-sm font-semibold text-gray-500">(${data.global_progress.sorted}/${data.global_progress.total} items sorted/loaded)</span>`;
                        }

                        showToast(`Status updated to ${data.item.sorting_status}.`);
                    }
                })
                .catch(error => {
                    showToast('Failed to update status. Action unauthorized or database error.', 'error');
                    console.error(error);
                });
            }

            // ── Goods Receipt (GRN) Modal Handlers ──
            function openGrnModal(btn) {
                const poId = btn.getAttribute('data-id');
                const poNumber = btn.getAttribute('data-po-number');
                const supplierName = btn.getAttribute('data-supplier-name');
                const items = JSON.parse(btn.getAttribute('data-items'));

                document.getElementById('grn-po-id').value = poId;
                document.getElementById('grn-modal-subtitle').textContent = `PO: ${poNumber} · Supplier: ${supplierName} · Record the warehouse check before manager approval.`;
                
                const container = document.getElementById('grn-items-container');
                container.innerHTML = '';
                
                items.forEach((item, index) => {
                    const row = document.createElement('div');
                    row.className = "grn-item-row p-4 flex items-center justify-between gap-4";
                    
                    const qty = parseFloat(item.quantity);
                    const unit = item.purchase_unit || 'kg';
                    
                    row.innerHTML = `
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-bold text-slate-800 block truncate">${item.product.name}</span>
                            <span class="text-[10px] text-slate-400 font-bold uppercase mt-0.5">Ordered: ${qty.toFixed(2)} ${unit}</span>
                            <input type="hidden" name="items[${index}][purchase_order_item_id]" value="${item.id}">
                            <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <div>
                                <input type="number" step="0.01" min="0" name="items[${index}][received_qty]" value="${qty.toFixed(2)}"
                                       oninput="calculateVariance(this, ${qty}, 'variance-${index}')"
                                       class="w-20 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white text-center">
                            </div>
                            <span id="variance-${index}" class="inline-flex items-center rounded px-2 py-1 text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-500 border border-slate-200">
                                Match
                            </span>
                        </div>
                    `;
                    container.appendChild(row);
                });
                
                document.getElementById('grn-modal').classList.remove('hidden');
            }

            function closeGrnModal() {
                document.getElementById('grn-modal').classList.add('hidden');
            }

            function calculateVariance(inputEl, orderedQty, varianceElId) {
                const receivedVal = parseFloat(inputEl.value) || 0;
                const variance = receivedVal - orderedQty;
                const badge = document.getElementById(varianceElId);
                
                if (variance === 0) {
                    badge.className = "inline-flex items-center rounded px-2 py-1 text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-500 border border-slate-200";
                    badge.textContent = "Match";
                } else if (variance < 0) {
                    badge.className = "inline-flex items-center rounded px-2 py-1 text-[10px] font-black uppercase tracking-wider bg-red-50 text-red-700 border border-red-200";
                    badge.textContent = `${variance.toFixed(2)} Short`;
                } else {
                    badge.className = "inline-flex items-center rounded px-2 py-1 text-[10px] font-black uppercase tracking-wider bg-green-50 text-green-700 border border-green-200";
                    badge.textContent = `+${variance.toFixed(2)} Extra`;
                }
            }

            function submitGrn(event) {
                event.preventDefault();
                const form = document.getElementById('grn-form');
                const formData = new FormData(form);
                
                // Construct JSON structure
                const payload = {
                    purchase_order_id: formData.get('purchase_order_id'),
                    received_at: formData.get('received_at'),
                    notes: formData.get('notes'),
                    transport_cost: formData.get('transport_cost'),
                    labour_cost: formData.get('labour_cost'),
                    items: []
                };
                
                const container = document.getElementById('grn-items-container');
                const rows = container.querySelectorAll('.grn-item-row');
                rows.forEach((row, index) => {
                    const poItemId = row.querySelector(`input[name="items[${index}][purchase_order_item_id]"]`).value;
                    const productId = row.querySelector(`input[name="items[${index}][product_id]"]`).value;
                    const receivedQty = row.querySelector(`input[name="items[${index}][received_qty]"]`).value;
                    
                    payload.items.push({
                        purchase_order_item_id: parseInt(poItemId),
                        product_id: parseInt(productId),
                        received_qty: parseFloat(receivedQty)
                    });
                });

                fetch('/inventory/sorting-checklist/grn', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        closeGrnModal();
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast(data.message || 'Failed to check in goods.', 'error');
                    }
                })
                .catch(error => {
                    showToast('Connection error, try again.', 'error');
                    console.error(error);
                });
            }

            // ── Carry Over ──
            function carryOverBatch(batchId, reference) {
                if(!confirm(`Are you sure you want to carry over stock batch ${reference} to tomorrow?`)) {
                    return;
                }
                
                fetch(`/inventory/sorting-checklist/carry-over/${batchId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast(data.message || 'Failed to carry over stock.', 'error');
                    }
                })
                .catch(error => {
                    showToast('Connection error.', 'error');
                    console.error(error);
                });
            }

            // ── Wastage Modal Handlers ──
            function openWastageModal(batchId, productName, maxQty) {
                document.getElementById('wastage-batch-id').value = batchId;
                document.getElementById('wastage-modal-subtitle').textContent = `Product: ${productName}`;
                document.getElementById('wastage-quantity').max = maxQty;
                document.getElementById('wastage-quantity').value = '';
                document.getElementById('wastage-notes').value = '';
                document.getElementById('wastage-max-qty-label').textContent = `Max write-off quantity: ${maxQty.toFixed(2)} kg`;
                document.getElementById('wastage-modal').classList.remove('hidden');
            }

            function closeWastageModal() {
                document.getElementById('wastage-modal').classList.add('hidden');
            }

            function submitWastage(event) {
                event.preventDefault();
                const batchId = document.getElementById('wastage-batch-id').value;
                const quantity = parseFloat(document.getElementById('wastage-quantity').value);
                const reason = document.getElementById('wastage-reason').value;
                const notes = document.getElementById('wastage-notes').value;

                fetch(`/inventory/sorting-checklist/wastage/${batchId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        quantity: quantity,
                        reason: reason,
                        notes: notes
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        closeWastageModal();
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast(data.message || 'Failed to log wastage.', 'error');
                    }
                })
                .catch(error => {
                    showToast('Connection error.', 'error');
                    console.error(error);
                });
            }

            // ── Finalize Allocation Form ──
            function toggleNotesField(orderId) {
                const checkbox = document.getElementById(`success-100-${orderId}`);
                const container = document.getElementById(`notes-container-${orderId}`);
                if (checkbox.checked) {
                    container.classList.add('hidden');
                } else {
                    container.classList.remove('hidden');
                }
            }

            function finalizeOrderSheet(event, orderId) {
                event.preventDefault();
                const checkbox = document.getElementById(`success-100-${orderId}`);
                const notesField = document.getElementById(`sorting-notes-${orderId}`);
                const notesValue = checkbox.checked ? 'Successful 100% as per approved order.' : notesField.value;

                fetch(`/inventory/sorting-checklist/complete-order/${orderId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        sorting_notes: notesValue
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast(data.message || 'Failed to finalize allocation sheet.', 'error');
                    }
                })
                .catch(error => {
                    showToast('Connection error.', 'error');
                    console.error(error);
                });
            }
        </script>
    @endpush

</x-layouts.app>
