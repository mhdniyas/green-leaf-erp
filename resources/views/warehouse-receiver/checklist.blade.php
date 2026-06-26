<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Warehouse Receive · Green Leaf</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f1f5f9; }

        /* ── Top Bar ── */
        .wr-top-bar {
            position: sticky;
            top: 0;
            z-index: 50;
            padding: 8px 16px 4px;
            background: transparent;
        }
        .wr-top-inner {
            max-width: 480px;
            margin: 0 auto;
            background: rgba(255,255,255,0.96);
            border: 1px solid rgba(203,213,225,0.8);
            border-radius: 28px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.04);
            backdrop-filter: blur(16px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
        }

        /* ── Bottom Nav ── */
        .wr-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 50;
            padding: 0 16px 8px;
            background: transparent;
        }
        .wr-nav-inner {
            max-width: 480px;
            margin: 0 auto;
            background: rgba(255,255,255,0.96);
            border: 1px solid rgba(203,213,225,0.8);
            border-radius: 28px;
            box-shadow: 0 -8px 32px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.04);
            backdrop-filter: blur(16px);
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding: 8px 12px;
            gap: 4px;
        }
        .wr-nav-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            padding: 8px 16px;
            border-radius: 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #94a3b8;
            text-decoration: none;
            min-width: 56px;
        }
        .wr-nav-btn.active {
            background: #4f46e5;
            color: #fff;
            box-shadow: 0 4px 12px rgba(79,70,229,0.35);
        }
        .wr-nav-btn svg { width: 22px; height: 22px; }
        .wr-nav-btn span {
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            display: none;
        }
        .wr-nav-btn.active span { display: block; }

        /* ── Tab Panels ── */
        .wr-tab { display: none; }
        .wr-tab.active { display: block; }

        /* ── Product Cards ── */
        .batch-card {
            background: #fff;
            border-radius: 20px;
            border: 1.5px solid #fde68a;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(251,191,36,0.08);
            transition: box-shadow 0.2s;
        }
        .batch-card.confirmed {
            border-color: #bbf7d0;
            box-shadow: 0 2px 8px rgba(52,211,153,0.06);
            opacity: 0.75;
        }
        .confirm-btn {
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 10px 14px;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s, transform 0.1s;
            letter-spacing: 0.04em;
        }
        .confirm-btn:active { transform: scale(0.95); }
        .confirm-btn:hover { background: #059669; }
        .confirm-all-btn {
            width: 100%;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #fff;
            border: none;
            border-radius: 18px;
            padding: 16px 20px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 24px rgba(79,70,229,0.3);
            transition: opacity 0.15s, transform 0.1s;
            letter-spacing: 0.02em;
        }
        .confirm-all-btn:active { transform: scale(0.98); opacity: 0.9; }

        /* ── Toast ── */
        .wr-toast {
            position: fixed;
            top: 16px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;
            background: #10b981;
            color: #fff;
            border-radius: 16px;
            padding: 12px 20px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            box-shadow: 0 8px 24px rgba(16,185,129,0.35);
            animation: slideDown 0.3s ease;
        }
        .wr-toast.error { background: #ef4444; box-shadow: 0 8px 24px rgba(239,68,68,0.35); }
        @keyframes slideDown {
            from { transform: translateX(-50%) translateY(-20px); opacity: 0; }
            to   { transform: translateX(-50%) translateY(0);    opacity: 1; }
        }
        .hidden { display: none !important; }
        .inv-subtab-panel { animation: fadeIn 0.2s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="h-full">

{{-- Toast Notifications --}}
@if(session('success'))
    <div class="wr-toast" id="wr-toast">✓ {{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="wr-toast error" id="wr-toast">
        @foreach($errors->all() as $e){{ $e }}@endforeach
    </div>
@endif

{{-- Top Bar --}}
<div class="wr-top-bar">
    <div class="wr-top-inner">
        <div>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-indigo-600 leading-none mb-1">Warehouse Receiver</p>
            <h1 id="wr-page-heading" class="text-base font-black tracking-tight text-slate-950">Receive Checklist</h1>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('warehouse.receiver.checklist') }}" method="GET">
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                    class="cursor-pointer rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-indigo-600 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </form>
            <a href="{{ route('logout') }}" 
               onclick="event.preventDefault(); document.getElementById('logout-form').submit()"
               class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-rose-600 shadow-sm transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </a>
        </div>
    </div>
</div>

<div class="mx-auto max-w-lg px-4 pb-36 pt-4">



    {{-- TAB: Pending --}}
    <div id="tab-pending" class="wr-tab active">

        @if($pendingGrns->isEmpty() && $pendingBatches->isEmpty())
            <div class="rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 border border-emerald-100">
                    <svg class="h-7 w-7 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900">All Clear!</h3>
                <p class="mt-1 text-xs text-slate-500">No pending sheets or batches for {{ $date }}.<br>All stock is in inventory.</p>
            </div>
        @else
            {{-- Pending GRNs / Vendor Sheets --}}
            @if(!$pendingGrns->isEmpty())
                <div class="mb-6 space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Pending Vendor Sheets</h3>
                    @foreach($pendingGrns as $grn)
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-indigo-700">
                                    {{ $grn->purchaseOrder?->supplier?->name ?? 'Vendor' }}
                                </span>
                                <h4 class="text-sm font-black text-slate-900 mt-1.5">{{ $grn->grn_number }}</h4>
                                <p class="text-[10px] text-slate-400 font-medium">Purchased by: {{ $grn->purchaseOrder?->purchaserCart?->user?->name ?? 'Purchaser' }}</p>
                                <p class="text-[10px] text-slate-500 font-bold mt-1">
                                    Items: {{ $grn->items->count() }} · {{ number_format((float) $grn->items->sum('received_qty'), 2) }} kg
                                </p>
                            </div>
                            <a href="{{ route('warehouse.receiver.receive-grn', $grn) }}" class="shrink-0 inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl px-3.5 py-2 text-xs font-bold shadow-sm transition-colors text-decoration-none">
                                <span>Open Sheet</span>
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Simple Pending Batches (Compatibility / Fallback) --}}
            @if(!$pendingBatches->isEmpty())
                <div class="space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Pending Batches</h3>
                    
                    {{-- Confirm All --}}
                    <form action="{{ route('warehouse.receiver.confirm-all') }}" method="POST" class="mb-4 warehouse-confirm-form"
                        data-confirm-title="Confirm all batches"
                        data-confirm-message="Confirm ALL {{ $pendingBatches->count() }} batch(es) as received? This will move them into active inventory."
                        data-confirm-button="Confirm all">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <button type="submit" class="confirm-all-btn">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                            Confirm All {{ $pendingBatches->count() }} into Inventory
                        </button>
                    </form>

                    @foreach($pendingBatches as $batch)
                        <div class="batch-card flex-col items-stretch gap-3 shadow-sm border border-slate-200">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-100 border border-amber-200">
                                    <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="truncate text-sm font-bold text-slate-900">{{ $batch->product->name }}</h4>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded-md">
                                            {{ number_format((float) $batch->total_kg, 2) }} {{ $batch->product->unit }}
                                        </span>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ $batch->reference }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <form action="{{ route('warehouse.receiver.confirm', $batch) }}" method="POST" class="flex items-center gap-2 pt-2.5 border-t border-dashed border-slate-100">
                                @csrf
                                <div class="flex-1 min-w-0">
                                    <select name="warehouse_id" required
                                        class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                        @foreach($warehouses as $wh)
                                            <option value="{{ $wh->id }}" @selected(old('warehouse_id', $batch->product->default_warehouse_id) == $wh->id)>
                                                {{ $wh->name }} ({{ $wh->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="confirm-btn">✓ Received</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    {{-- TAB: Confirmed (now Delivery) --}}
    <div id="tab-confirmed" class="wr-tab">
        {{-- Sub-tab header / switcher --}}
        <div class="mb-4 flex rounded-2xl bg-slate-200/60 p-1">
            <button type="button" onclick="switchDeliverySubTab('orders')" id="del-subtab-btn-orders" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all bg-white text-indigo-600 shadow-sm border-none cursor-pointer">
                Orders
            </button>
            <button type="button" onclick="switchDeliverySubTab('items')" id="del-subtab-btn-items" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all text-slate-500 hover:text-slate-900 border-none cursor-pointer">
                Items List
            </button>
        </div>

        {{-- Sub-tab: Orders --}}
        <div id="del-subtab-orders" class="del-subtab-panel space-y-3">
            @if($shopOrders->isEmpty())
                <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-xs text-slate-500">No shop orders for {{ $date }}.</p>
                </div>
            @else
                @foreach($shopOrders as $order)
                    @php
                        $fulfillmentColor = 'rose';
                        $fulfillmentIcon = '✗';
                        if ($order->fulfillment_percentage === 100) {
                            $fulfillmentColor = 'emerald';
                            $fulfillmentIcon = '✓';
                        } elseif ($order->fulfillment_percentage > 0) {
                            $fulfillmentColor = 'amber';
                            $fulfillmentIcon = '⚠';
                        }

                        $loadingColor = match($order->loading_status) {
                            'Loaded' => 'emerald',
                            'Partially Loaded' => 'amber',
                            default => 'slate',
                        };
                    @endphp
                    
                    {{-- Hidden template container for this order's items --}}
                    <div id="order-items-template-{{ $order->id }}" class="hidden">
                        <div class="mb-4 rounded-2xl bg-slate-50 border border-slate-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-500 mb-1">
                                        {{ $order->shop->warehouse_tag ?? 'NO TAG' }}
                                    </span>
                                    <h4 class="text-sm font-black text-slate-900">{{ $order->shop->name }}</h4>
                                    <p class="text-[10px] text-slate-400 font-medium">Order: <span class="font-mono">{{ $order->order_number }}</span></p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-{{ $fulfillmentColor }}-50 border border-{{ $fulfillmentColor }}-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-{{ $fulfillmentColor }}-700">
                                        {{ $fulfillmentIcon }} {{ $order->fulfillment_percentage }}% Stock Available
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 mb-6">
                            @foreach($order->items as $item)
                                @php
                                    $isLoaded = $item->sorting_status === 'loaded';
                                    $appQty = $item->approved_qty > 0 ? (float)$item->approved_qty : (float)$item->requested_qty;
                                    $lodQty = (float)$item->loaded_qty;
                                @endphp
                                <div class="rounded-xl border border-slate-200 bg-white p-3 flex items-center justify-between gap-3 shadow-xs">
                                    <div class="min-w-0">
                                        <h5 class="truncate text-xs font-black text-slate-900">{{ $item->product->name }}</h5>
                                        <p class="text-[10px] font-bold text-slate-500 mt-0.5">
                                            Required: <span class="text-indigo-600 font-black">{{ number_format($appQty, 2) }}</span> {{ $item->unit }}
                                            @if($isLoaded)
                                                · Loaded: <span class="text-emerald-600 font-black">{{ number_format($lodQty, 2) }}</span> {{ $item->unit }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="shrink-0">
                                        @if($isLoaded)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-emerald-700">
                                                Loaded ✓
                                            </span>
                                        @else
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-amber-700">
                                                Pending
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Deliver Button form --}}
                        @if($order->delivery_status === 'pending')
                            @if($order->loaded_items_count === $order->total_items_count)
                                <form action="{{ route('warehouse.receiver.loadout.order.dispatch', $order) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-md transition-all active:scale-98 flex items-center justify-center gap-2 border-none cursor-pointer">
                                        Deliver Order
                                    </button>
                                </form>
                            @else
                                <button type="button" onclick="confirmPartialDispatch({{ $order->id }}, '{{ $order->order_number }}')" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-md transition-all active:scale-98 flex items-center justify-center gap-2 border-none cursor-pointer">
                                    Deliver Order
                                </button>
                                <form id="partial-dispatch-form-{{ $order->id }}" action="{{ route('warehouse.receiver.loadout.order.dispatch-partial', $order) }}" method="POST" class="hidden">
                                    @csrf
                                </form>
                            @endif
                        @else
                            <div class="text-center py-3 bg-slate-100 rounded-xl">
                                <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-indigo-700">
                                    Status: In Transit (Dispatched)
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Clickable order card --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 flex flex-col gap-3 shadow-sm hover:border-indigo-200 transition-colors cursor-pointer"
                         onclick="selectOrder({{ $order->id }})">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-500 mb-1">
                                    {{ $order->shop->warehouse_tag ?? 'NO TAG' }}
                                </span>
                                <h4 class="truncate text-sm font-black text-slate-900">{{ $order->shop->name }}</h4>
                                <p class="text-[10px] text-slate-400 font-medium">Order: <span class="font-mono">{{ $order->order_number }}</span></p>
                            </div>
                            <div class="text-right shrink-0 flex flex-col items-end gap-1.5">
                                <span class="rounded-full bg-{{ $loadingColor }}-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-{{ $loadingColor }}-700">
                                    {{ $order->loading_status }}
                                </span>
                                @if($order->delivery_status === 'in_transit')
                                    <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-indigo-700">
                                        In Transit
                                    </span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between pt-2.5 border-t border-dashed border-slate-100">
                            <span class="text-[10px] font-bold text-slate-500">
                                Items: {{ $order->loaded_items_count }} / {{ $order->total_items_count }} loaded
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-{{ $fulfillmentColor }}-50 border border-{{ $fulfillmentColor }}-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-{{ $fulfillmentColor }}-700">
                                {{ $fulfillmentIcon }} {{ $order->fulfillment_percentage }}% Stock Available
                            </span>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Sub-tab: Items --}}
        <div id="del-subtab-items" class="del-subtab-panel hidden space-y-3">
            <div id="selected-order-items-container">
                <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-xs text-slate-500">Select a shop order from the Orders tab to view its items and dispatch.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB: Inventory --}}
    <div id="tab-inventory" class="wr-tab">
        {{-- Sub-tab header / switcher --}}
        <div class="mb-4 flex rounded-2xl bg-slate-200/60 p-1">
            <button type="button" onclick="switchInvSubTab('in')" id="subtab-in" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all bg-white text-indigo-600 shadow-sm border-none cursor-pointer">IN</button>
            <button type="button" onclick="switchInvSubTab('out')" id="subtab-out" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all text-slate-500 hover:text-slate-900 border-none cursor-pointer">OUT</button>
            <button type="button" onclick="switchInvSubTab('stock')" id="subtab-stock" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all text-slate-500 hover:text-slate-900 border-none cursor-pointer">STOCK</button>
        </div>

        {{-- IN Sub-tab Panel --}}
        <div id="inv-subtab-in" class="inv-subtab-panel space-y-3">
            @if($inflows->isEmpty())
                <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-xs text-slate-500">No recent inflows logged.</p>
                </div>
            @else
                @foreach($inflows as $mov)
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-4 flex items-center justify-between gap-3 shadow-sm">
                        <div class="min-w-0">
                            <h4 class="truncate text-sm font-black text-slate-950">{{ $mov->product_name }}</h4>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Grade: <span class="text-slate-700 font-black">{{ $mov->grade_label }}</span>
                            </p>
                            @if($mov->reference)
                                <p class="text-[9px] font-mono text-slate-400 mt-0.5">Ref: {{ $mov->reference }}</p>
                            @endif
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-black text-emerald-600">+{{ number_format((float) $mov->quantity, 2) }} {{ $mov->unit }}</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ $mov->time_formatted }}</p>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- OUT Sub-tab Panel --}}
        <div id="inv-subtab-out" class="inv-subtab-panel hidden space-y-3">
            @if($outMovements->isEmpty())
                <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-xs text-slate-500">No recent outflows logged.</p>
                </div>
            @else
                @foreach($outMovements as $mov)
                    <div class="rounded-2xl border border-rose-100 bg-rose-50/40 p-4 flex items-center justify-between gap-3 shadow-sm">
                        <div class="min-w-0">
                            <h4 class="truncate text-sm font-black text-slate-950">{{ $mov->product->name }}</h4>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Grade: <span class="text-slate-700 font-black">{{ $mov->grade?->label() ?? 'Unsorted' }}</span>
                            </p>
                            <p class="text-[9px] font-medium text-rose-600/80 mt-0.5 uppercase tracking-wider">{{ $mov->type->label() }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-black text-rose-600">-{{ number_format((float) $mov->quantity, 2) }} {{ $mov->product->unit }}</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ $mov->created_at->format('H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- STOCK Sub-tab Panel --}}
        <div id="inv-subtab-stock" class="inv-subtab-panel hidden space-y-3">
            @if($stockLevels->isEmpty())
                <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-xs text-slate-500">No stock currently in inventory.</p>
                </div>
            @else
                @foreach($stockLevels as $level)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 flex items-center justify-between gap-3 shadow-sm">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 border border-indigo-100">
                                @if(!empty($level->product_image))
                                    <img src="{{ $level->product_image }}" class="h-8 w-8 object-cover rounded-xl" alt="{{ $level->product_name }}">
                                @else
                                    <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <h4 class="truncate text-sm font-black text-slate-900">{{ $level->product_name }}</h4>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-600 mt-1">
                                    {{ is_string($level->grade) ? $level->grade : ($level->grade?->label() ?? 'Unsorted') }}
                                </span>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-black text-slate-950">{{ number_format((float) $level->current_stock, 2) }} kg</p>
                            @if($level->latest_activity)
                                <p class="text-[9px] text-slate-400 mt-0.5">Updated: {{ \Carbon\Carbon::parse($level->latest_activity)->format('Y-m-d H:i') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- TAB: Loadout --}}
    <div id="tab-loadout" class="wr-tab">
        @if($approvedOrders->isEmpty())
            <div class="rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 border border-slate-200">
                    <svg class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 011 1v9M17 16h2a1 1 0 001-1v-4a1 1 0 00-.3-.7l-3-3a1 1 0 00-.7-.3h-2m4 9H9m0-9h8" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900">No Approved Orders</h3>
                <p class="mt-1 text-xs text-slate-500">There are no approved orders to loadout for {{ $date }}.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($approvedOrders as $order)
                    @php
                        $color = match($order->loading_status) {
                            'Loaded' => 'emerald',
                            'Partially Loaded' => 'amber',
                            default => 'slate',
                        };
                    @endphp
                    <a href="{{ route('warehouse.receiver.loadout.show', $order) }}" class="block text-decoration-none">
                        <div class="rounded-2xl border border-{{ $color }}-100 bg-white p-4 flex items-center justify-between gap-3 shadow-sm hover:border-indigo-200 transition-colors">
                            <div class="min-w-0">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-500 mb-1">
                                    {{ $order->shop->warehouse_tag ?? 'NO TAG' }}
                                </span>
                                <h4 class="truncate text-sm font-black text-slate-900">{{ $order->shop->name }}</h4>
                                <p class="text-[10px] text-slate-400 font-medium">Order: <span class="font-mono">{{ $order->order_number }}</span></p>
                                <p class="text-[10px] text-slate-500 font-bold mt-1">
                                    Progress: {{ $order->loaded_items_count }} / {{ $order->total_items_count }} items loaded
                                </p>
                            </div>
                            <div class="text-right shrink-0 flex flex-col items-end gap-2">
                                <span class="rounded-full bg-{{ $color }}-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-{{ $color }}-700">
                                    {{ $order->loading_status }}
                                </span>
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</div>

{{-- Bottom Nav --}}
<div class="wr-bottom-nav">
    <div class="wr-nav-inner">
        <button type="button" id="nav-pending" class="wr-nav-btn active" onclick="switchTab('pending')">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <span>Receive</span>
        </button>

        <button type="button" id="nav-inventory" class="wr-nav-btn" onclick="switchTab('inventory')">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span>Inventory</span>
        </button>

        <button type="button" id="nav-loadout" class="wr-nav-btn" onclick="switchTab('loadout')">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.75A1.125 1.125 0 012.625 17.625V4.625L13.5 4.625v14.125m.125-14.125H16.5a1.5 1.5 0 011.06.44l2.625 2.625a1.5 1.5 0 01.44 1.06V17.625a1.125 1.125 0 01-1.125 1.125H18m0 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
            </svg>
            <span>Loadout</span>
        </button>

        <button type="button" id="nav-confirmed" class="wr-nav-btn" onclick="switchTab('confirmed')">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Delivery</span>
        </button>


    </div>
</div>

<form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>

@include('components.app-dialogs')

<script>
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

    function switchTab(name) {
        // Hide all tabs
        document.querySelectorAll('.wr-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.wr-nav-btn').forEach(b => b.classList.remove('active'));
        // Show selected
        document.getElementById('tab-' + name)?.classList.add('active');
        document.getElementById('nav-' + name)?.classList.add('active');

        // Update heading dynamically
        const headings = {
            'pending': 'Receive Checklist',
            'inventory': 'Inventory Status',
            'loadout': 'Loadout Checklist',
            'confirmed': 'Delivery Status'
        };
        const headingElement = document.getElementById('wr-page-heading');
        if (headingElement && headings[name]) {
            headingElement.textContent = headings[name];
        }
    }

    function switchInvSubTab(subName) {
        // Hide all subtab panels
        document.querySelectorAll('.inv-subtab-panel').forEach(p => p.classList.add('hidden'));
        // Remove active styles from all subtab buttons
        document.querySelectorAll('[id^="subtab-"]').forEach(btn => {
            btn.classList.remove('bg-white', 'text-indigo-600', 'shadow-sm');
            btn.classList.add('text-slate-500', 'hover:text-slate-900');
        });
        // Show selected panel
        document.getElementById('inv-subtab-' + subName)?.classList.remove('hidden');
        // Add active styles to clicked button
        const activeBtn = document.getElementById('subtab-' + subName);
        if (activeBtn) {
            activeBtn.classList.remove('text-slate-500', 'hover:text-slate-900');
            activeBtn.classList.add('bg-white', 'text-indigo-600', 'shadow-sm');
        }
    }

    function switchDeliverySubTab(subName) {
        // Hide all subtab panels
        document.querySelectorAll('.del-subtab-panel').forEach(p => p.classList.add('hidden'));
        // Remove active styles from all subtab buttons
        document.querySelectorAll('[id^="del-subtab-btn-"]').forEach(btn => {
            btn.classList.remove('bg-white', 'text-indigo-600', 'shadow-sm');
            btn.classList.add('text-slate-500', 'hover:text-slate-900');
        });
        // Show selected panel
        document.getElementById('del-subtab-' + subName)?.classList.remove('hidden');
        // Add active styles to clicked button
        const activeBtn = document.getElementById('del-subtab-btn-' + subName);
        if (activeBtn) {
            activeBtn.classList.remove('text-slate-500', 'hover:text-slate-900');
            activeBtn.classList.add('bg-white', 'text-indigo-600', 'shadow-sm');
        }
    }

    function selectOrder(orderId) {
        // Copy the pre-rendered html template into the active area of items tab
        const template = document.getElementById('order-items-template-' + orderId);
        const activeContainer = document.getElementById('selected-order-items-container');
        if (template && activeContainer) {
            activeContainer.innerHTML = template.innerHTML;
        }
        
        // Switch tab to items
        switchDeliverySubTab('items');
    }

    function confirmPartialDispatch(orderId, orderNum) {
        window.showAppConfirm({
            title: 'Partial Delivery Confirmation',
            message: 'Are you sure you want to dispatch Order ' + orderNum + ' as a partial delivery? All remaining items will be marked as not loaded.',
            confirmLabel: 'Yes, Dispatch Partial',
            cancelLabel: 'Cancel',
            tone: 'danger',
            onConfirm: () => {
                const form = document.getElementById('partial-dispatch-form-' + orderId);
                if (form) {
                    form.submit();
                }
            }
        });
    }

    // Auto-dismiss toast after 4s
    const toast = document.getElementById('wr-toast');
    if (toast) {
        setTimeout(() => {
            toast.style.transition = 'opacity 0.4s';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }

    // Switch tab on load if present in query parameter
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab && ['pending', 'inventory', 'loadout', 'confirmed'].includes(activeTab)) {
        switchTab(activeTab);
    }
</script>
</body>
</html>
