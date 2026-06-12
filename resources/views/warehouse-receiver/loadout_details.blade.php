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
            align-items: center;
            justify-content: space-between;
            gap: 12px;
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

<div class="mx-auto max-w-lg px-4 pb-24 pt-4">

    @php
        $pendingItems = $order->items->where('sorting_status', '!=', 'loaded');
    @endphp

    {{-- Load All Button --}}
    @if($pendingItems->isNotEmpty())
        <form action="{{ route('warehouse.receiver.loadout.order-all', $order) }}" method="POST" class="mb-5"
            onsubmit="return confirm('Mark all {{ $pendingItems->count() }} remaining items as loaded? This will reduce them from active inventory.')">
            @csrf
            <button type="submit" class="load-all-btn">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                </svg>
                Load All {{ $pendingItems->count() }} Item(s)
            </button>
        </form>
    @endif

    {{-- Items List --}}
    <div class="space-y-3">
        @foreach($order->items as $item)
            @php
                $isLoaded = $item->sorting_status === 'loaded';
            @endphp
            <div class="item-card {{ $isLoaded ? 'loaded' : '' }}">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 border border-slate-200">
                        <svg class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="truncate text-sm font-black text-slate-900">{{ $item->product->name }}</h4>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            Qty: <span class="text-slate-800">{{ number_format((float) ($item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty), 2) }} {{ $item->unit }}</span>
                        </p>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-600 mt-1">
                            Grade: {{ $item->product_grade ?? 'A' }}
                        </span>
                    </div>
                </div>
                
                @if($isLoaded)
                    <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-emerald-700">
                        Loaded ✓
                    </span>
                @else
                    <form action="{{ route('warehouse.receiver.loadout.item', $item) }}" method="POST" class="shrink-0"
                        onsubmit="return confirm('Mark {{ $item->product->name }} as loaded? This will reduce the quantity from active stock.')">
                        @csrf
                        <button type="submit" class="load-btn">✓ Load</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

</div>

<script>
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
