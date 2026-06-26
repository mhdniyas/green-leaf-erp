<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Loadout Details · Green Leaf</title>
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

        /* ── Product Cards ── */
        .item-card {
            background: #fff;
            border-radius: 20px;
            border: 1.5px solid #e2e8f0;
            padding: 16px;
            display: flex;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: all 0.2s;
        }
        .item-card.loaded {
            border-color: #bbf7d0;
            box-shadow: 0 2px 8px rgba(52,211,153,0.06);
            opacity: 0.85;
        }
        .load-btn {
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 10px 16px;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s, transform 0.1s;
            letter-spacing: 0.04em;
        }
        .load-btn:active { transform: scale(0.95); }
        .load-btn:hover { background: #4338ca; }
        
        .load-all-btn {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            box-shadow: 0 8px 24px rgba(16,185,129,0.25);
            transition: opacity 0.15s, transform 0.1s;
            letter-spacing: 0.02em;
        }
        .load-all-btn:active { transform: scale(0.98); opacity: 0.9; }

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
        .wr-footer-inner {
            max-width: 480px;
            margin: 0 auto;
            background: rgba(255,255,255,0.96);
            border: 1px solid rgba(203,213,225,0.8);
            border-radius: 28px;
            box-shadow: 0 -8px 32px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.04);
            backdrop-filter: blur(16px);
            display: flex;
            flex-direction: column;
            padding: 16px 16px 8px;
            gap: 12px;
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
        <div class="flex items-center gap-3">
            <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d')]) }}" 
               class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </a>
            <div>
                <h1 class="text-sm font-black tracking-tight text-slate-900">{{ $order->shop->name }}</h1>
                <p class="text-[9px] font-semibold text-slate-400">Order: <span class="font-mono">{{ $order->order_number }}</span></p>
            </div>
        </div>
        <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-indigo-600">
            {{ $order->shop->warehouse_tag ?? 'NO TAG' }}
        </span>
    </div>
</div>
 
<div class="mx-auto max-w-lg px-4 pb-48 pt-4">
 
    @php
        $pendingItems = $order->items->where('sorting_status', '!=', 'loaded');
    @endphp
 

 
    {{-- Filter bar --}}
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Items Checklist</h3>
        <button type="button" 
                onclick="toggleHideLoaded()" 
                id="toggle-loaded-btn" 
                class="text-[10px] font-black uppercase tracking-wider text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border-none rounded-xl px-2.5 py-1.5 cursor-pointer transition-all">
            Hide Loaded
        </button>
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
            @endphp
            <div class="item-card flex-col items-stretch gap-3 {{ $isLoaded ? 'loaded' : '' }}" data-item-id="{{ $item->id }}">
                <div class="flex items-center justify-between gap-3 min-w-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 border border-slate-200">
                            <svg class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="truncate text-sm font-black text-slate-900">{{ $item->product->name }}</h4>
                            <p class="text-[11px] font-bold text-slate-500 mt-0.5">
                                Load: <span class="text-indigo-600 font-black">{{ number_format($approvedQty, 2) }}</span> / 
                                <span class="{{ ($item->inventory_stock ?? 0.0) < $approvedQty ? 'text-rose-600 font-black' : 'text-slate-700 font-extrabold' }}">
                                    {{ number_format($item->inventory_stock ?? 0.0, 2) }}
                                </span> {{ $item->unit }}
                            </p>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-600 mt-1">
                                Grade: {{ $item->product_grade ?? 'A' }}
                            </span>
                        </div>
                    </div>
                    
                    @if($isLoaded)
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-emerald-700">
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
                                <input type="hidden" name="loaded_qty" value="{{ $approvedQty }}">
                                <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-500 hover:bg-emerald-600 text-white border-none shadow-sm cursor-pointer transition-colors active:scale-95">
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
                                       value="{{ $approvedQty }}" 
                                       data-approved="{{ $approvedQty }}"
                                       class="loaded-qty-input w-full rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-black text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                       required>
                            </div>
                            <div class="shrink-0 pt-3.5">
                                <button type="submit" class="load-btn">✓ Save & Load</button>
                            </div>
                        </div>

                        <div class="loadout-discrepancy-panel bg-slate-50 border border-slate-100 rounded-xl p-2.5 hidden">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-400 mb-0.5">Discrepancy Action</label>
                                    <select name="discrepancy_type" class="loadout-discrepancy-type w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                        <option value="none">Choose...</option>
                                        <option value="wastage">Wastage</option>
                                        <option value="other">Other (Adjustment)</option>
                                    </select>
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
 
</div>

{{-- Sticky Bottom Footer & Nav Bar --}}
<div class="wr-bottom-nav">
    <div class="wr-footer-inner">
        {{-- Action Button (Save / Load All / Dispatch) --}}
        <div class="flex flex-col gap-2">
            @if($pendingItems->isNotEmpty())
                <form action="{{ route('warehouse.receiver.loadout.order-all', $order) }}" method="POST" class="warehouse-confirm-form"
                    data-confirm-title="Load all items"
                    data-confirm-message="Mark all {{ $pendingItems->count() }} remaining items as loaded? This will reduce them from active inventory."
                    data-confirm-button="Load all">
                    @csrf
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-md transition-all active:scale-98 flex items-center justify-center gap-2 border-none cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                        </svg>
                        Load All {{ $pendingItems->count() }} Item(s)
                    </button>
                </form>

                @if($order->delivery_status === 'pending')
                    <form action="{{ route('warehouse.receiver.loadout.order.dispatch-partial', $order) }}" method="POST" class="warehouse-confirm-form"
                        data-confirm-title="Dispatch partial order"
                        data-confirm-message="Are you sure you want to dispatch this order as a partial delivery? All remaining {{ $pendingItems->count() }} items will be marked as not loaded (discrepancy) and the order will be sent."
                        data-confirm-button="Dispatch Partial">
                        @csrf
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-md transition-all active:scale-98 flex items-center justify-center gap-2 border-none cursor-pointer">
                            Dispatch Partial Order
                        </button>
                    </form>
                @endif
            @else
                @if($order->delivery_status === 'pending')
                    <form action="{{ route('warehouse.receiver.loadout.order.dispatch', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-md transition-all active:scale-98 flex items-center justify-center gap-2 border-none cursor-pointer">
                            Dispatch / Mark Out for Delivery
                        </button>
                    </form>
                @else
                    <div class="text-center py-1">
                        <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-indigo-700">
                            Status: In Transit (Dispatched)
                        </span>
                    </div>
                @endif
            @endif
        </div>

        {{-- Bottom Nav Tabs --}}
        <div class="flex items-center justify-around gap-1 border-t border-slate-100 pt-2">
            <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'pending']) }}" class="wr-nav-btn">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span class="text-[9px] font-black uppercase tracking-wider">Receive</span>
            </a>
            <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'inventory']) }}" class="wr-nav-btn">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span class="text-[9px] font-black uppercase tracking-wider">Inventory</span>
            </a>
            <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'loadout']) }}" class="wr-nav-btn active">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.75A1.125 1.125 0 012.625 17.625V4.625L13.5 4.625v14.125m.125-14.125H16.5a1.5 1.5 0 011.06.44l2.625 2.625a1.5 1.5 0 01.44 1.06V17.625a1.125 1.125 0 01-1.125 1.125H18m0 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                </svg>
                <span class="text-[9px] font-black uppercase tracking-wider">Loadout</span>
            </a>
            <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'confirmed']) }}" class="wr-nav-btn">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-[9px] font-black uppercase tracking-wider">Delivery</span>
            </a>
        </div>
    </div>
</div>

@include('components.app-dialogs')
 
<script>
    let hideLoaded = false;

    function toggleHideLoaded() {
        hideLoaded = !hideLoaded;
        const loadedCards = document.querySelectorAll('.item-card.loaded');
        const btn = document.getElementById('toggle-loaded-btn');
        
        loadedCards.forEach(card => {
            if (hideLoaded) {
                card.classList.add('hidden');
            } else {
                card.classList.remove('hidden');
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
                const loaded = parseFloat(input.value) || 0;
                const container = input.closest('[data-item-id]');
                const panel = container.querySelector('.loadout-discrepancy-panel');
                const select = container.querySelector('.loadout-discrepancy-type');

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
 
        // Form submission confirmation on confirm-all / etc
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
 
    // Auto-dismiss toast after 4s
    const toast = document.getElementById('wr-toast');
    if (toast) {
        setTimeout(() => {
            toast.style.transition = 'opacity 0.4s';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }
</script>
</body>
</html>
