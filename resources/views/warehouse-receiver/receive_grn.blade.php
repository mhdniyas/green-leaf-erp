<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Receive Vendor Sheet · Green Leaf</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }
        
        .toast {
            position: fixed;
            top: 16px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;
            background: #ef4444;
            color: #fff;
            border-radius: 16px;
            padding: 12px 20px;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 8px 24px rgba(239,68,68,0.35);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { transform: translateX(-50%) translateY(-20px); opacity: 0; }
            to   { transform: translateX(-50%) translateY(0);    opacity: 1; }
        }
    </style>
</head>
<body class="h-full">

@if($errors->any())
    <div class="toast">
        @foreach($errors->all() as $e){{ $e }}@endforeach
    </div>
@endif

{{-- Header --}}
<div class="sticky top-0 z-50 bg-white border-b border-slate-200 px-4 py-4 shadow-sm">
    <div class="max-w-xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('warehouse.receiver.checklist', ['date' => \Carbon\Carbon::parse($grn->received_at)->format('Y-m-d')]) }}" class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-indigo-600 leading-none mb-1">Vendor Sheet Receive</p>
                <h1 class="text-base font-black tracking-tight text-slate-900">{{ $grn->grn_number }}</h1>
            </div>
        </div>
        <span class="rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-1 text-[10px] font-bold text-indigo-700">
            {{ $grn->purchaseOrder?->supplier?->name ?? 'Vendor' }}
        </span>
    </div>
</div>

<div class="mx-auto max-w-xl px-4 py-6 pb-32">
    {{-- GRN Meta Card --}}
    <div class="mb-6 rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 mb-3">Sheet Information</h3>
        <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-xs">
            <div>
                <span class="text-slate-400">Purchaser</span>
                <p class="font-bold text-slate-800 mt-0.5">{{ $grn->purchaseOrder?->purchaserCart?->user?->name ?? 'Purchaser' }}</p>
            </div>
            <div>
                <span class="text-slate-400">Date</span>
                <p class="font-bold text-slate-800 mt-0.5">{{ \Carbon\Carbon::parse($grn->received_at)->format('d M Y') }}</p>
            </div>
            <div>
                <span class="text-slate-400">Total Purchase Qty</span>
                <p class="font-bold text-slate-800 mt-0.5">
                    {{ number_format((float) $grn->items->sum(fn($item) => $item->purchaseOrderItem?->quantity ?? $item->received_qty), 2) }} kg
                </p>
            </div>
            <div>
                <span class="text-slate-400">Vendor Code</span>
                <p class="font-mono font-bold text-indigo-600 mt-0.5">{{ $grn->purchaseOrder?->supplier?->code ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    {{-- Receive Form --}}
    <form action="{{ route('warehouse.receiver.process-receive-grn', $grn) }}" method="POST" id="grn-receive-form">
        @csrf

        {{-- Warehouse Selection --}}
        <div class="mb-6 rounded-2xl bg-indigo-50 border border-indigo-100 p-4 shadow-sm">
            <label class="block text-xs font-black uppercase tracking-wider text-indigo-700 mb-2">Default Target Warehouse</label>
            <select name="warehouse_id" id="default-warehouse-select" required class="w-full rounded-xl border border-indigo-200 bg-white px-3 py-2 text-sm font-bold text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}">
                        {{ $wh->name }} ({{ $wh->code }})
                    </option>
                @endforeach
            </select>
            <p class="text-[10px] text-indigo-600 font-medium mt-1.5">Select a default warehouse. You can override this for individual products below.</p>
        </div>

        {{-- Grouped Items --}}
        <div class="space-y-6">
            @foreach($groupedItems as $categoryName => $items)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2 mb-4">
                        {{ $categoryName }}
                    </h2>
                    <div class="divide-y divide-slate-100">
                        @foreach($items as $item)
                            @php
                                $purchasedQty = (float) ($item->purchaseOrderItem?->quantity ?? $item->received_qty);
                            @endphp
                            <div class="py-4 first:pt-0 last:pb-0" data-item-id="{{ $item->id }}">
                                <div class="flex items-start justify-between gap-3 min-w-0">
                                    <div class="min-w-0 flex-1">
                                        <h4 class="truncate text-sm font-black text-slate-950">{{ $item->product->name }}</h4>
                                        <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-700 mt-1">
                                            Purchased: {{ number_format($purchasedQty, 2) }} kg
                                        </span>
                                    </div>
                                    <div class="shrink-0 flex items-center gap-1.5">
                                        <input type="number" 
                                               step="0.001" 
                                               name="items[{{ $item->id }}][received_qty]" 
                                               value="{{ $purchasedQty }}" 
                                               data-purchased="{{ $purchasedQty }}"
                                               class="received-qty-input w-24 text-right rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-black text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                               required>
                                        <span class="text-xs text-slate-400 font-bold">kg</span>
                                    </div>
                                </div>

                                {{-- Warehouse override dropdown --}}
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Target Warehouse</span>
                                    <div class="w-48 shrink-0">
                                        <select name="items[{{ $item->id }}][warehouse_id]" 
                                                class="product-warehouse-select w-full rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                                data-default-warehouse-id="{{ $item->product->default_warehouse_id }}"
                                                data-manual="false">
                                            @foreach($warehouses as $wh)
                                                <option value="{{ $wh->id }}" @selected(old("items.{$item->id}.warehouse_id", $item->product->default_warehouse_id) == $wh->id)>
                                                    {{ $wh->name }} ({{ $wh->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                {{-- Discrepancy details --}}
                                <div class="discrepancy-panel mt-3 bg-slate-50 border border-slate-100 rounded-xl p-3 hidden">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Discrepancy Action</label>
                                            <select name="items[{{ $item->id }}][discrepancy_type]" class="discrepancy-type-select w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                                <option value="none">Choose Action...</option>
                                                <option value="wastage">Move to Wastage</option>
                                                <option value="other">Other (Adjustment)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Variance Note</label>
                                            <input type="text" 
                                                   name="items[{{ $item->id }}][discrepancy_note]" 
                                                   placeholder="Reason for shortage..." 
                                                   class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Sticky Footer Confirm --}}
        <div class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-slate-200 p-4 shadow-[0_-8px_30px_rgba(0,0,0,0.06)]">
            <div class="max-w-xl mx-auto">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-sm font-black tracking-wide shadow-md transition-colors">
                    Confirm Receipt & Update Inventory
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const qtyInputs = document.querySelectorAll('.received-qty-input');

        qtyInputs.forEach(input => {
            input.addEventListener('input', () => {
                const purchased = parseFloat(input.dataset.purchased);
                const received = parseFloat(input.value) || 0;
                const container = input.closest('[data-item-id]');
                const panel = container.querySelector('.discrepancy-panel');
                const select = container.querySelector('.discrepancy-type-select');

                if (received < purchased) {
                    panel.classList.remove('hidden');
                    select.required = true;
                    if (select.value === 'none') {
                        select.value = 'wastage'; // default to wastage shortage
                    }
                } else {
                    panel.classList.add('hidden');
                    select.required = false;
                    select.value = 'none';
                }
            });
        });

        // Warehouse synchronization logic
        const defaultWhSelect = document.getElementById('default-warehouse-select');
        const productWhSelects = document.querySelectorAll('.product-warehouse-select');

        // Set initial values based on defaultWhSelect for selects without a product default
        productWhSelects.forEach(select => {
            const productDefault = select.dataset.defaultWarehouseId;
            if (!productDefault) {
                select.value = defaultWhSelect.value;
            }
            
            // If user changes individual select, mark it as manual
            select.addEventListener('change', () => {
                select.dataset.manual = 'true';
            });
        });

        // When default target warehouse changes, update all non-manually-changed selects
        defaultWhSelect.addEventListener('change', () => {
            productWhSelects.forEach(select => {
                if (select.dataset.manual !== 'true') {
                    select.value = defaultWhSelect.value;
                }
            });
        });

        // Form submission confirmation
        const form = document.getElementById('grn-receive-form');
        form.addEventListener('submit', (e) => {
            let hasUnresolvedDiscrepancy = false;
            qtyInputs.forEach(input => {
                const purchased = parseFloat(input.dataset.purchased);
                const received = parseFloat(input.value) || 0;
                const container = input.closest('[data-item-id]');
                const select = container.querySelector('.discrepancy-type-select');

                if (received < purchased && select.value === 'none') {
                    hasUnresolvedDiscrepancy = true;
                }
            });

            if (hasUnresolvedDiscrepancy) {
                e.preventDefault();
                alert('Please select a discrepancy action (Wastage or Other) for all items with short quantities.');
            }
        });
    });
</script>
</body>
</html>
