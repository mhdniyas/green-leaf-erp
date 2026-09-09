@extends('admin.cashbook.layouts.app')

@section('title', 'Admin Inventory Action Center — Green Leaf')

@section('header_title')
    <i data-lucide="boxes" class="w-5 h-5 text-emerald-600"></i> Inventory Action Center
@endsection

@section('header_subtitle')
    Authoritative warehouse sellable stock, bill receiving, unbilled advance reconciliation, shop returns, damage, and physical counts.
@endsection

@php
    $currentPageProducts = collect($currentInventory?->items() ?? [])->map(fn ($r) => [
        'product_id' => $r['product_id'],
        'name' => $r['name'],
        'sku' => $r['sku'],
        'unit' => $r['unit'] ?? 'KG',
        'available_qty' => (float) ($r['current_sellable'] ?? 0.0),
        'quantity' => (float) max(0.01, (float) ($r['current_sellable'] ?? 0.0)),
        'reason' => 'transit_damage',
        'grade' => 'U',
    ])->values()->all();

    $currentPageReturns = collect($shopReturns?->items() ?? [])->map(function ($ret) use ($currentStockByProduct) {
        $shop = $ret->shopOrderItem?->shopOrder?->shop;
        $order = $ret->shopOrderItem?->shopOrder;
        $prod = $ret->product;
        $retQty = (float) $ret->quantity;
        $sellable = max(0.0, (float) ($currentStockByProduct[$ret->product_id] ?? 0.0));
        $avail = min($retQty, $sellable);
        return [
            'product_id' => $ret->product_id,
            'name' => $prod?->name ?? ('Product #' . $ret->product_id),
            'sku' => $prod?->sku ?? '',
            'unit' => $prod?->unit ?? 'KG',
            'available_qty' => $avail,
            'quantity' => $avail > 0 ? $avail : 0.0,
            'reason' => 'transit_damage',
            'grade' => 'U',
            'shop_name' => $shop?->name ?? '',
            'order_ref' => $order?->invoice_number ?? $order?->order_number ?? '',
        ];
    })->values()->all();
@endphp

@section('content')
    <script>
        window.inventoryActionCenterConfig = {
            csrfToken: '{{ csrf_token() }}',
            currentTab: '{{ $tab }}',
            currentDate: '{{ $selectedDate }}',
            currentWarehouseId: '{{ $selectedWarehouseId ?? '' }}',
            currentWarehouseName: @json($selectedWarehouseId && $availableWarehouses->firstWhere('id', $selectedWarehouseId) ? $availableWarehouses->firstWhere('id', $selectedWarehouseId)->name : 'All Warehouses'),
            currentSearch: @json($search ?? ''),
            autoPlanUrl: '{{ route('admin.cashbook.inventory.auto-clear-plan') }}',
            autoExecuteUrl: '{{ route('admin.cashbook.inventory.auto-clear-execute') }}',
            pendingBillsDaysUrl: '{{ route('admin.cashbook.inventory.pending-bills-days') }}',
            pendingBillsDayDetailsUrl: '{{ route('admin.cashbook.inventory.pending-bills-day-details') }}',
            acceptPendingBillsUrl: '{{ route('admin.cashbook.inventory.accept-pending-bills') }}',
            manualMatchUrlPrefix: '/admin/cashbook/inventory/manual-match-suggestions/',
            manualExecuteUrlPrefix: '/admin/cashbook/inventory/manual-match/',
            resolveUnitDiffUrl: '{{ route('admin.cashbook.inventory.resolve-unit-difference') }}',
            fixAdvanceUnitsUrl: '{{ route('admin.cashbook.inventory.fix-advance-units') }}',
            currentPageProducts: @json($currentPageProducts ?? []),
            currentPageReturns: @json($currentPageReturns ?? []),
            currentPageAdvances: @json($stockWithoutBill ? $stockWithoutBill->items() : ($unbilledAdvGrns ?? [])),
            damageProducts: @json($damageProducts ?? []),
            unbilledAdvGrns: @json($unbilledAdvGrns ?? []),
            unbilledRows: @json($unbilledAdvRows ?? [])
        };
    </script>

    <div class="mx-auto max-w-7xl space-y-6"
         x-data="inventoryActionCenter(window.inventoryActionCenterConfig)">

        <!-- Top Header & Actions Bar -->
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl flex items-center gap-2">
                        <span>Inventory Action Center</span>
                    </h1>
                    <p class="text-xs font-bold text-slate-500 mt-0.5">Physical sellable balances, bill processing, shop returns, damage write-offs, and count reconciliations</p>
                </div>

                <!-- Top Right Action Controls -->
                <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                    <button type="button"
                            @click="openDamageModal()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 text-xs font-black transition-all shadow-xs cursor-pointer">
                        <i data-lucide="trash-2" class="w-4 h-4 text-rose-600"></i>
                        <span>Move to Damage</span>
                    </button>

                    <button type="button"
                            @click="openAutoClear()"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-700 text-white text-xs font-black hover:bg-emerald-800 transition-all shadow-md hover:shadow-lg cursor-pointer">
                        <i data-lucide="sparkles" class="w-4 h-4 text-emerald-200"></i>
                        <span>Match &amp; Clear Bills</span>
                    </button>
                </div>
            </div>

            <!-- Date, Warehouse & Search Controls -->
            <div class="rounded-3xl border border-slate-200/90 bg-white p-4 shadow-xs space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <!-- Left: Warehouse Switcher & Date Controls -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Warehouse Switcher -->
                        @if($availableWarehouses->count() > 1)
                            <form method="GET" action="{{ route('admin.cashbook.inventory') }}" class="inline-flex items-center">
                                <input type="hidden" name="tab" value="{{ $tab }}">
                                <input type="hidden" name="date" value="{{ $selectedDate }}">
                                @if($search) <input type="hidden" name="search" value="{{ $search }}"> @endif
                                @if($sort) <input type="hidden" name="sort" value="{{ $sort }}"> @endif
                                @if($direction) <input type="hidden" name="direction" value="{{ $direction }}"> @endif

                                <div class="inline-flex items-center gap-1.5 rounded-2xl bg-slate-100 p-1 border border-slate-200/60">
                                    <label for="inventory-warehouse-select" class="pl-2 text-xs font-black text-slate-600 flex items-center gap-1 cursor-pointer select-none">
                                        <i data-lucide="warehouse" class="w-3.5 h-3.5 text-slate-500"></i>
                                        <span>Warehouse:</span>
                                    </label>
                                    <select id="inventory-warehouse-select"
                                            name="warehouse_id"
                                            onchange="this.form.submit()"
                                            class="px-2.5 py-1 text-xs font-black rounded-xl bg-white border border-slate-200 text-slate-900 shadow-2xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer">
                                        <option value="">All Warehouses</option>
                                        @foreach($availableWarehouses as $wh)
                                            <option value="{{ $wh->id }}" {{ (string) $selectedWarehouseId === (string) $wh->id ? 'selected' : '' }}>
                                                {{ $wh->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </form>
                        @elseif($availableWarehouses->isNotEmpty())
                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-2xl bg-slate-100 text-slate-700 text-xs font-black border border-slate-200/80">
                                <i data-lucide="warehouse" class="w-3.5 h-3.5 text-slate-500"></i>
                                <span class="text-slate-500 font-bold">Warehouse:</span>
                                <span>{{ $availableWarehouses->first()->name }}</span>
                            </div>
                        @endif

                        <!-- Day Navigation: Previous / Today / Date / Next -->
                        <div class="inline-flex items-center gap-1 rounded-2xl bg-slate-100 p-1">
                            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => $tab, 'date' => $prevDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search, 'sort' => $sort, 'direction' => $direction], fn ($v) => $v !== null && $v !== '')) }}"
                               title="Previous Day ({{ $prevDate }})"
                               class="inline-flex items-center justify-center w-7 h-7 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-white transition">
                                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            </a>

                            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => $tab, 'date' => today()->toDateString(), 'warehouse_id' => $selectedWarehouseId, 'search' => $search, 'sort' => $sort, 'direction' => $direction], fn ($v) => $v !== null && $v !== '')) }}"
                               class="rounded-xl px-2.5 py-1 text-xs font-black transition-all {{ $isToday ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                                Today
                            </a>

                            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => $tab, 'date' => $nextDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search, 'sort' => $sort, 'direction' => $direction], fn ($v) => $v !== null && $v !== '')) }}"
                               title="Next Day ({{ $nextDate }})"
                               class="inline-flex items-center justify-center w-7 h-7 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-white transition">
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </a>
                        </div>

                        <!-- Datepicker Input -->
                        <form method="GET" action="{{ route('admin.cashbook.inventory') }}" class="inline-flex items-center">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            @if($selectedWarehouseId !== null && $selectedWarehouseId !== '') <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}"> @endif
                            @if($search) <input type="hidden" name="search" value="{{ $search }}"> @endif
                            @if($sort) <input type="hidden" name="sort" value="{{ $sort }}"> @endif
                            @if($direction) <input type="hidden" name="direction" value="{{ $direction }}"> @endif

                            <div class="relative flex items-center">
                                <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400 absolute left-3 pointer-events-none"></i>
                                <input type="date"
                                       name="date"
                                       value="{{ $selectedDate }}"
                                       onchange="this.form.submit()"
                                       class="pl-8 pr-3 py-1.5 text-xs font-bold rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer">
                            </div>
                        </form>
                    </div>

                    <!-- Right: Search Bar -->
                    <form method="GET" action="{{ route('admin.cashbook.inventory') }}" class="flex items-center gap-1.5 w-full sm:w-auto">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <input type="hidden" name="date" value="{{ $selectedDate }}">
                        @if($selectedWarehouseId !== null && $selectedWarehouseId !== '') <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}"> @endif
                        @if($sort) <input type="hidden" name="sort" value="{{ $sort }}"> @endif
                        @if($direction) <input type="hidden" name="direction" value="{{ $direction }}"> @endif

                        <div class="relative flex-1 sm:w-64">
                            <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search product, SKU, GRN..."
                                   class="w-full pl-8 pr-3 py-1.5 text-xs font-medium rounded-xl bg-slate-50 border border-slate-200 shadow-2xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        </div>

                        <button type="submit" class="px-3.5 py-1.5 text-xs font-black rounded-xl bg-slate-900 text-white shadow-xs hover:bg-slate-800 transition-all cursor-pointer">
                            Filter
                        </button>

                        @if($search)
                            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => $tab, 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'sort' => $sort, 'direction' => $direction], fn ($v) => $v !== null && $v !== '')) }}"
                               title="Clear search"
                               class="p-1.5 text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </a>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- DAILY SUMMARY GRID (SECTION 7)                                     -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        <div class="space-y-2">
            <div class="flex items-center justify-between px-1">
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">
                    Daily Summary ({{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }})
                </span>
                <span class="text-[11px] font-bold text-slate-400">
                    Warehouse Scope: {{ $selectedWarehouseId && $availableWarehouses->firstWhere('id', $selectedWarehouseId) ? $availableWarehouses->firstWhere('id', $selectedWarehouseId)->name : 'All Warehouses' }}
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-9 gap-2.5">
                <!-- 1. Bills Received -->
                <div class="rounded-2xl border border-slate-200/80 bg-white p-3 shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Bills Received</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-base font-black text-slate-900">{{ $summary['bills_received_count'] }} <span class="text-[10px] text-slate-400 font-bold">GRNs</span></span>
                        <span class="text-[11px] font-mono font-bold text-slate-600">{{ number_format($summary['bills_received_qty'], 1) }}</span>
                    </div>
                </div>

                <!-- 2. Advance Receives -->
                <div class="rounded-2xl border border-purple-200/80 bg-purple-50/40 p-3 shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-purple-700 block">Advance Receives</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-base font-black text-purple-950">{{ $summary['advance_receives_count'] }} <span class="text-[10px] text-purple-600 font-bold">GRNs</span></span>
                        <span class="text-[11px] font-mono font-bold text-purple-800">{{ number_format($summary['advance_receives_qty'], 1) }}</span>
                    </div>
                </div>

                <!-- 3. Advance Matched -->
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-3 shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-emerald-700 block">Advance Matched</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-base font-black text-emerald-950">{{ number_format($summary['advance_matched_qty'], 1) }}</span>
                        <span class="text-[10px] font-bold text-emerald-700">KG Matched</span>
                    </div>
                </div>

                <!-- 4. New Physical Receive -->
                <div class="rounded-2xl border border-teal-200/80 bg-teal-50/40 p-3 shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-teal-700 block">New Phys. Receive</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-base font-black text-teal-950">{{ number_format($summary['new_physical_receive_qty'], 1) }}</span>
                        <span class="text-[10px] font-bold text-teal-700">KG Direct</span>
                    </div>
                </div>

                <!-- 5. Shop Returns -->
                <div class="rounded-2xl border border-blue-200/80 bg-blue-50/40 p-3 shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-blue-700 block">Shop Returns</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-base font-black text-blue-950">{{ number_format($summary['shop_returns_qty'], 1) }}</span>
                        <span class="text-[10px] font-bold text-blue-700">{{ $summary['shop_returns_count'] }} items</span>
                    </div>
                </div>

                <!-- 6. Loadout Qty -->
                <div class="rounded-2xl border border-sky-200/80 bg-sky-50/40 p-3 shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-sky-700 block">Loadout Dispatched</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-base font-black text-sky-950">{{ number_format($summary['loadout_qty'], 1) }}</span>
                        <span class="text-[10px] font-bold text-sky-700">KG Loaded</span>
                    </div>
                </div>

                <!-- 7. Damage Qty -->
                <div class="rounded-2xl border border-rose-200/80 bg-rose-50/40 p-3 shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-rose-700 block">Damage / Wastage</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-base font-black text-rose-950">{{ number_format($summary['damage_qty'], 1) }}</span>
                        <span class="text-[10px] font-bold text-rose-700">KG Wrote-off</span>
                    </div>
                </div>

                <!-- 8. Physical Adjustments -->
                <div class="rounded-2xl border border-amber-200/80 bg-amber-50/40 p-3 shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-amber-700 block">Physical Adjust.</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-base font-black {{ $summary['physical_adjustment_qty'] >= 0 ? 'text-amber-950' : 'text-rose-950' }}">
                            {{ $summary['physical_adjustment_qty'] > 0 ? '+' : '' }}{{ number_format($summary['physical_adjustment_qty'], 1) }}
                        </span>
                        <span class="text-[10px] font-bold text-amber-700">KG Diff</span>
                    </div>
                </div>

                <!-- 9. Closing Inventory -->
                <div class="rounded-2xl border border-emerald-300 bg-emerald-100/70 p-3 shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-emerald-900 block">Closing Inventory</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-base font-black text-emerald-950">{{ number_format($summary['closing_inventory_qty'], 1) }}</span>
                        <span class="text-[10px] font-bold text-emerald-800">KG Stock</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- ACTION CENTER NAVIGATION TABS (SECTION 8)                          -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        <div class="flex flex-wrap gap-1 rounded-2xl bg-slate-100 p-1 shadow-inner self-start">
            <!-- 1. Current Inventory -->
            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'current_inventory', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search], fn ($v) => $v !== null && $v !== '')) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $tab === 'current_inventory' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="boxes" class="w-3.5 h-3.5 {{ $tab === 'current_inventory' ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Current Inventory</span>
            </a>

            <!-- 2. Receive Bills -->
            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'receive_bills', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search], fn ($v) => $v !== null && $v !== '')) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $tab === 'receive_bills' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="file-clock" class="w-3.5 h-3.5 {{ $tab === 'receive_bills' ? 'text-amber-600' : 'text-slate-400' }}"></i>
                <span>Receive Bills</span>
                @if($summary['pending_bills_count'] > 0)
                    <span class="px-1.5 py-0.5 text-[10px] rounded-md font-bold bg-amber-100 text-amber-800">
                        {{ $summary['pending_bills_count'] }}
                    </span>
                @endif
            </a>

            <!-- 3. Stock Without Bill -->
            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'stock_without_bill', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search], fn ($v) => $v !== null && $v !== '')) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $tab === 'stock_without_bill' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="package-search" class="w-3.5 h-3.5 {{ $tab === 'stock_without_bill' ? 'text-indigo-600' : 'text-slate-400' }}"></i>
                <span>Stock Without Bill</span>
                @if($summary['unbilled_inventory_count'] > 0)
                    <span class="px-1.5 py-0.5 text-[10px] rounded-md font-bold bg-indigo-100 text-indigo-800">
                        {{ $summary['unbilled_inventory_count'] }}
                    </span>
                @endif
            </a>

            <!-- 4. Shop Returns -->
            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'shop_returns', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search], fn ($v) => $v !== null && $v !== '')) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $tab === 'shop_returns' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="undo-2" class="w-3.5 h-3.5 {{ $tab === 'shop_returns' ? 'text-blue-600' : 'text-slate-400' }}"></i>
                <span>Shop Returns</span>
                @if($summary['shop_returns_count'] > 0)
                    <span class="px-1.5 py-0.5 text-[10px] rounded-md font-bold bg-blue-100 text-blue-800">
                        {{ $summary['shop_returns_count'] }}
                    </span>
                @endif
            </a>

            <!-- 5. Damage -->
            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'damage', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search], fn ($v) => $v !== null && $v !== '')) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $tab === 'damage' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="trash-2" class="w-3.5 h-3.5 {{ $tab === 'damage' ? 'text-rose-600' : 'text-slate-400' }}"></i>
                <span>Damage</span>
                @if($summary['damage_qty'] > 0)
                    <span class="px-1.5 py-0.5 text-[10px] rounded-md font-bold bg-rose-100 text-rose-800">
                        {{ number_format($summary['damage_qty'], 1) }} KG
                    </span>
                @endif
            </a>

            <!-- 6. Physical Check -->
            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'physical_check', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search], fn ($v) => $v !== null && $v !== '')) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $tab === 'physical_check' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="clipboard-check" class="w-3.5 h-3.5 {{ $tab === 'physical_check' ? 'text-amber-600' : 'text-slate-400' }}"></i>
                <span>Physical Check</span>
            </a>

            <!-- 7. Exception: Unit Differences (Only shown when differences exist) -->
            @if(($summary['unit_differences_count'] ?? 0) > 0)
                <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'unit_differences', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search], fn ($v) => $v !== null && $v !== '')) }}"
                   class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $tab === 'unit_differences' ? 'bg-white text-slate-900 shadow-sm' : 'text-rose-600 hover:text-rose-800' }}">
                    <i data-lucide="scale" class="w-3.5 h-3.5 text-rose-600"></i>
                    <span>Unit Issues</span>
                    <span class="px-1.5 py-0.5 text-[10px] rounded-md font-bold bg-rose-100 text-rose-800">
                        {{ $summary['unit_differences_count'] }}
                    </span>
                </a>
            @endif
        </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 1: CURRENT INVENTORY (MAIN TAB)                                -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @if($tab === 'current_inventory')
            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden space-y-4">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="boxes" class="w-4 h-4 text-emerald-600"></i>
                            <span>Current Sellable Warehouse Inventory</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">
                            Current physical stock split into <strong>With Bill</strong> and <strong>Without Bill (Unbilled Advance)</strong>
                        </p>
                    </div>

                    @if($currentInventory)
                        <span class="text-xs font-bold text-slate-400 self-start sm:self-auto">
                            Showing {{ $currentInventory->firstItem() ?? 0 }}–{{ $currentInventory->lastItem() ?? 0 }} of {{ $currentInventory->total() }} products
                        </span>
                    @endif
                </div>

                <!-- Multi-select Action Bar -->
                <div x-show="selectedCurrentProducts.length > 0"
                     x-cloak
                     class="mx-4 sm:mx-5 p-3 rounded-2xl bg-rose-50 border border-rose-200 flex flex-wrap items-center justify-between gap-3 transition-all">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-600 text-white text-xs font-black" x-text="selectedCurrentProducts.length"></span>
                        <span class="text-xs font-black text-rose-950">
                            <span x-text="selectedCurrentProducts.length"></span> product(s) selected
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="selectedCurrentProducts = []"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-rose-100/60 transition cursor-pointer">
                            Clear Selection
                        </button>
                        <button type="button"
                                @click="openDamageModalForSelection('current')"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-black shadow-xs transition cursor-pointer">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            <span>Move Selected to Damage</span>
                        </button>
                    </div>
                </div>

                @if(!$currentInventory || $currentInventory->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="inbox" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No Inventory Found</h3>
                        <p class="text-xs text-slate-500 mt-1">No active products match your search or warehouse filter.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        @php
                            $buildSortUrl = function (string $col) use ($tab, $selectedDate, $selectedWarehouseId, $search, $sort, $direction): string {
                                $nextDir = ($sort === $col && $direction === 'asc') ? 'desc' : 'asc';
                                return route('admin.cashbook.inventory', array_filter([
                                    'tab' => $tab,
                                    'date' => $selectedDate,
                                    'warehouse_id' => $selectedWarehouseId,
                                    'search' => $search,
                                    'sort' => $col,
                                    'direction' => $nextDir,
                                ], fn ($v) => $v !== null && $v !== ''));
                            };
                        @endphp
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5 w-10 text-center">
                                        <input type="checkbox"
                                               @change="toggleSelectAllCurrent($event)"
                                               :checked="isAllCurrentSelected()"
                                               class="w-4 h-4 rounded text-rose-600 border-slate-300 focus:ring-rose-500 cursor-pointer">
                                    </th>
                                    <th class="p-3.5">
                                        <a href="{{ $buildSortUrl('product') }}" class="group inline-flex items-center gap-1 font-black {{ $sort === 'product' ? 'text-slate-900' : 'text-slate-600' }} hover:text-slate-900 transition">
                                            <span>Product</span>
                                            @if($sort === 'product')
                                                <i data-lucide="{{ $direction === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="w-3.5 h-3.5 text-slate-900 stroke-[2.5]"></i>
                                            @else
                                                <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5 text-slate-300 group-hover:text-slate-500"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="p-3.5">
                                        <a href="{{ $buildSortUrl('category') }}" class="group inline-flex items-center gap-1 font-black {{ $sort === 'category' ? 'text-slate-900' : 'text-slate-600' }} hover:text-slate-900 transition">
                                            <span>Category</span>
                                            @if($sort === 'category')
                                                <i data-lucide="{{ $direction === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="w-3.5 h-3.5 text-slate-900 stroke-[2.5]"></i>
                                            @else
                                                <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5 text-slate-300 group-hover:text-slate-500"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="p-3.5 text-right">
                                        <a href="{{ $buildSortUrl('current_sellable') }}" class="group inline-flex items-center justify-end gap-1 font-black {{ $sort === 'current_sellable' ? 'text-slate-900' : 'text-slate-700' }} hover:text-slate-900 transition w-full">
                                            <span>Current Sellable</span>
                                            @if($sort === 'current_sellable')
                                                <i data-lucide="{{ $direction === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="w-3.5 h-3.5 text-slate-900 stroke-[2.5]"></i>
                                            @else
                                                <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5 text-slate-300 group-hover:text-slate-500"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="p-3.5 text-right">
                                        <a href="{{ $buildSortUrl('with_bill') }}" class="group inline-flex items-center justify-end gap-1 font-black {{ $sort === 'with_bill' ? 'text-emerald-950 font-black' : 'text-emerald-800' }} hover:text-emerald-950 transition w-full">
                                            <span>With Bill</span>
                                            @if($sort === 'with_bill')
                                                <i data-lucide="{{ $direction === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="w-3.5 h-3.5 text-emerald-800 stroke-[2.5]"></i>
                                            @else
                                                <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5 text-emerald-300 group-hover:text-emerald-600"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="p-3.5 text-right">
                                        <a href="{{ $buildSortUrl('without_bill') }}" class="group inline-flex items-center justify-end gap-1 font-black {{ $sort === 'without_bill' ? 'text-indigo-950 font-black' : 'text-indigo-800' }} hover:text-indigo-950 transition w-full">
                                            <span>Without Bill</span>
                                            @if($sort === 'without_bill')
                                                <i data-lucide="{{ $direction === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="w-3.5 h-3.5 text-indigo-800 stroke-[2.5]"></i>
                                            @else
                                                <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5 text-indigo-300 group-hover:text-indigo-600"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="p-3.5 text-right">
                                        <a href="{{ $buildSortUrl('pending_vendor_bill') }}" class="group inline-flex items-center justify-end gap-1 font-black {{ $sort === 'pending_vendor_bill' ? 'text-purple-950 font-black' : 'text-purple-800' }} hover:text-purple-950 transition w-full">
                                            <span>Pending Vendor Bill</span>
                                            @if($sort === 'pending_vendor_bill')
                                                <i data-lucide="{{ $direction === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="w-3.5 h-3.5 text-purple-800 stroke-[2.5]"></i>
                                            @else
                                                <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5 text-purple-300 group-hover:text-purple-600"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="p-3.5 text-right">
                                        <a href="{{ $buildSortUrl('stock_deficit') }}" class="group inline-flex items-center justify-end gap-1 font-black {{ $sort === 'stock_deficit' ? 'text-rose-950 font-black' : 'text-rose-800' }} hover:text-rose-950 transition w-full">
                                            <span>Stock Deficit</span>
                                            @if($sort === 'stock_deficit')
                                                <i data-lucide="{{ $direction === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="w-3.5 h-3.5 text-rose-800 stroke-[2.5]"></i>
                                            @else
                                                <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5 text-rose-300 group-hover:text-rose-600"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="p-3.5 text-center pr-5">Quick Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($currentInventory as $row)
                                    @php
                                        $currSellable = (float) $row['current_sellable'];
                                        $withoutBillOnHand = (float) $row['without_bill'];
                                        $withBillOnHand = (float) $row['with_bill'];
                                        $pendingVendorBill = (float) $row['pending_vendor_bill'];
                                        $stockDeficit = (float) $row['stock_deficit'];
                                        $unit = $row['unit'] ?? 'KG';
                                        $hasException = $stockDeficit > 0.001 || $pendingVendorBill > 0.001;
                                        $rowPayload = [
                                            'product_id' => $row['product_id'],
                                            'name' => $row['name'],
                                            'sku' => $row['sku'],
                                            'unit' => $unit,
                                            'available_qty' => $currSellable,
                                            'quantity' => $currSellable > 0 ? $currSellable : 0.0,
                                            'reason' => 'transit_damage',
                                            'grade' => 'U',
                                        ];
                                    @endphp
                                    <tr class="hover:bg-slate-50/60 transition-colors {{ $currSellable > 0 || $hasException ? 'bg-white' : 'bg-slate-50/20 opacity-75' }}">
                                        <td class="p-3.5 pl-5 text-center">
                                            <input type="checkbox"
                                                   @change="toggleCurrentProduct({{ json_encode($rowPayload) }}, $event)"
                                                   :checked="isCurrentSelected({{ $row['product_id'] }})"
                                                   {{ $currSellable <= 0 ? 'disabled' : '' }}
                                                   class="w-4 h-4 rounded text-rose-600 border-slate-300 focus:ring-rose-500 cursor-pointer disabled:opacity-25 disabled:cursor-not-allowed">
                                        </td>
                                        <td class="p-3.5 font-black text-slate-900">
                                            <span>{{ $row['name'] }}</span>
                                            <span class="block text-[10px] font-mono text-slate-400">{{ $row['sku'] }}</span>
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-600">
                                            {{ $row['category'] }}
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-black text-sm {{ $currSellable > 0 ? 'text-slate-900' : 'text-slate-400' }}">
                                            {{ number_format($currSellable, 2) }} <span class="text-[10px] text-slate-400 font-sans">{{ $unit }}</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-emerald-700">
                                            {{ number_format($withBillOnHand, 2) }} <span class="text-[10px] text-emerald-400 font-sans">{{ $unit }}</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-black {{ $withoutBillOnHand > 0 ? 'text-indigo-700' : 'text-slate-400' }}">
                                            {{ number_format($withoutBillOnHand, 2) }} <span class="text-[10px] text-indigo-400 font-sans">{{ $unit }}</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold {{ $pendingVendorBill > 0 ? 'text-purple-700 font-black' : 'text-slate-400' }}">
                                            {{ number_format($pendingVendorBill, 2) }} <span class="text-[10px] {{ $pendingVendorBill > 0 ? 'text-purple-400' : 'text-slate-400' }} font-sans">{{ $unit }}</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold">
                                            @if($stockDeficit > 0.001)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 font-black text-xs">
                                                    <i data-lucide="alert-triangle" class="w-3 h-3 text-rose-600"></i>
                                                    <span>{{ number_format($stockDeficit, 2) }} {{ $unit }}</span>
                                                </span>
                                            @else
                                                <span class="text-slate-400">0.00 <span class="text-[10px] font-sans">{{ $unit }}</span></span>
                                            @endif
                                        </td>
                                        <td class="p-3.5 text-center pr-5 whitespace-nowrap space-x-1">
                                            @if($currSellable > 0)
                                                <button type="button"
                                                        @click="openDamageModalSingle({{ $row['product_id'] }}, '{{ addslashes($row['name']) }}', '{{ $unit }}', {{ $currSellable }}, '{{ addslashes($row['sku']) }}')"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 text-[11px] font-bold border border-rose-200 transition cursor-pointer">
                                                    <i data-lucide="trash-2" class="w-3 h-3 text-rose-600"></i>
                                                    <span>Damage</span>
                                                </button>
                                            @else
                                                <button type="button"
                                                        disabled
                                                        title="No sellable stock available to move to damage"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 text-slate-400 text-[11px] font-bold border border-slate-200 opacity-50 cursor-not-allowed">
                                                    <i data-lucide="trash-2" class="w-3 h-3 text-slate-400"></i>
                                                    <span>Damage</span>
                                                </button>
                                            @endif
                                            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'physical_check', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $row['sku']])) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold border border-slate-200 transition">
                                                <i data-lucide="clipboard-check" class="w-3 h-3 text-slate-600"></i>
                                                <span>Count</span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-slate-100">
                        {{ $currentInventory->links() }}
                    </div>
                @endif
            </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 2: RECEIVE BILLS (SECTION 3)                                   -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @elseif($tab === 'receive_bills' || $tab === 'pending_bills')
            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="file-clock" class="w-4 h-4 text-amber-600"></i>
                            <span>Pending Purchaser Bills Awaiting Reconciliation</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Approved vendor bills ready for advance matching and physical intake</p>
                    </div>
                    <div class="flex items-center gap-3 self-start sm:self-auto">
                        @if($pendingBills)
                            <span class="text-xs font-bold text-slate-400">
                                Showing {{ $pendingBills->firstItem() ?? 0 }}–{{ $pendingBills->lastItem() ?? 0 }} of {{ $pendingBills->total() }} pending bills
                            </span>
                        @endif
                        @if(($summary['awaiting_approval_count'] ?? 0) > 0)
                            <button type="button"
                                    @click="openPendingBillsModal()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-black transition-all shadow-xs cursor-pointer">
                                <i data-lucide="check-check" class="w-3.5 h-3.5"></i>
                                <span>Accept All Pending Bills ({{ $summary['awaiting_approval_count'] }})</span>
                            </button>
                        @endif
                    </div>
                </div>

                @if(!$pendingBills || $pendingBills->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">All Bills Reconciled</h3>
                        <p class="text-xs text-slate-500 mt-1">There are no pending approved purchase bills requiring match or warehouse receiving.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5">Bill / PO</th>
                                    <th class="p-3.5">Supplier</th>
                                    <th class="p-3.5">Products</th>
                                    <th class="p-3.5 text-right">Bill Qty</th>
                                    <th class="p-3.5 text-right">Already Matched</th>
                                    <th class="p-3.5 text-right">Still To Receive</th>
                                    <th class="p-3.5 text-center">Match Status</th>
                                    <th class="p-3.5 text-center pr-5">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($pendingBills as $row)
                                    @php
                                        $poId = $row['id'];
                                        $billBase = (float) ($row['total_bill_base_qty'] ?? $row['required_base_qty'] ?? $row['quantity'] ?? 0);
                                        $alreadyMatched = (float) ($row['total_matched_base_qty'] ?? $row['already_matched_base_qty'] ?? 0);
                                        $remainingBase = max(0.0, round($billBase - $alreadyMatched, 2));
                                        $rawStatus = $row['match_status'] ?? $row['reconciliation_status'] ?? 'NO ADVANCE';
                                        $matchStatus = match($rawStatus) {
                                            'FULLY_MATCHED' => 'FULL MATCH',
                                            'PARTIALLY_MATCHED' => 'PARTIAL MATCH',
                                            'UNIT_DIFFERENCE' => 'UNIT ISSUE',
                                            'NO_ADVANCE', 'UNMATCHED' => 'NO ADVANCE',
                                            default => $rawStatus,
                                        };
                                        $statusClass = match($matchStatus) {
                                            'FULL MATCH' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'PARTIAL MATCH' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'UNIT ISSUE' => 'bg-rose-100 text-rose-800 border-rose-200',
                                            default => 'bg-slate-100 text-slate-600 border-slate-200',
                                        };
                                    @endphp
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="p-3.5 pl-5 font-mono font-black text-slate-900">
                                            <span>{{ $row['po_number'] }}</span>
                                            <span class="block text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($row['order_date'])->format('d M Y') }}</span>
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-800">
                                            {{ $row['supplier_name'] }}
                                        </td>
                                        <td class="p-3.5">
                                            <div class="flex flex-wrap gap-1 max-w-xs">
                                                @if(!empty($row['match_summary_items']))
                                                    @foreach(array_slice($row['match_summary_items'], 0, 3) as $it)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-[10px] font-bold text-slate-800">
                                                            {{ $it['product_name'] }} ({{ (float) ($it['quantity'] ?? $it['ordered_qty'] ?? $it['qty'] ?? 0) }} {{ $it['unit'] }})
                                                        </span>
                                                    @endforeach
                                                    @if(count($row['match_summary_items']) > 3)
                                                        <span class="text-[10px] font-bold text-slate-400 self-center">
                                                            +{{ count($row['match_summary_items']) - 3 }} more
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="text-slate-400 font-bold">{{ $row['item_count'] ?? 1 }} item(s)</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-700">
                                            {{ number_format($billBase, 2) }} <span class="text-[10px] text-slate-400">KG</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-purple-700">
                                            {{ number_format($alreadyMatched, 2) }} <span class="text-[10px] text-slate-400">KG</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-black text-slate-900">
                                            {{ number_format($remainingBase, 2) }} <span class="text-[10px] text-slate-400">KG</span>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-[10px] font-black uppercase border {{ $statusClass }}">
                                                {{ $matchStatus }}
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-center pr-5 whitespace-nowrap space-x-1">
                                            <button type="button"
                                                    @click="openManualMatch({{ $poId }}, '{{ $row['po_number'] }}', '{{ addslashes($row['supplier_name']) }}')"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-900 text-white text-[11px] font-black hover:bg-slate-800 transition shadow-2xs cursor-pointer">
                                                <i data-lucide="link" class="w-3 h-3 text-emerald-400"></i>
                                                <span>Match Bill</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-slate-100">
                        {{ $pendingBills->links() }}
                    </div>
                @endif
            </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 3: STOCK WITHOUT BILL (SECTION 2)                              -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @elseif($tab === 'stock_without_bill' || $tab === 'unbilled_inventory')
            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden space-y-4">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="package-search" class="w-4 h-4 text-indigo-600"></i>
                            <span>Physical Stock Without Vendor Bill (Open Advances)</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">
                            Advance physical stock received in warehouse where vendor bill has not yet arrived or matched
                        </p>
                    </div>

                    <div class="flex items-center gap-2 self-start sm:self-auto">
                        <button type="button"
                                @click="openShareMissingBillsModal()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-indigo-700 hover:bg-indigo-800 text-white text-xs font-black transition-all shadow-sm cursor-pointer">
                            <i data-lucide="share-2" class="w-4 h-4 text-indigo-200"></i>
                            <span>Share Missing Bills</span>
                        </button>
                    </div>
                </div>

                <!-- Multi-select Action Bar for Advance GRN Manual Clear -->
                <div x-show="selectedClearAdvanceIds.length > 0"
                     x-cloak
                     class="mx-4 sm:mx-5 p-3 rounded-2xl bg-amber-50 border border-amber-200 flex flex-wrap items-center justify-between gap-3 transition-all">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-600 text-white text-xs font-black" x-text="selectedClearAdvanceIds.length"></span>
                        <span class="text-xs font-black text-amber-950">
                            <span x-text="selectedClearAdvanceIds.length"></span> Advance GRN(s) selected
                        </span>
                        <span class="text-xs text-amber-700 font-semibold">
                            (Total Missing Qty: <span class="font-mono font-bold" x-text="getSelectedAdvancesTotalQty().toFixed(2)"></span> KG)
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="selectedClearAdvanceIds = []"
                                class="px-3 py-1.5 rounded-xl border border-amber-300 text-amber-800 hover:bg-amber-100 text-xs font-bold transition cursor-pointer">
                            Clear Selection
                        </button>
                        <button type="button"
                                @click="openClearAdvancesModal()"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-black transition shadow-xs cursor-pointer">
                            <i data-lucide="check-check" class="w-3.5 h-3.5"></i>
                            <span>Clear Selected Advances (<span x-text="selectedClearAdvanceIds.length"></span>)</span>
                        </button>
                    </div>
                </div>

                @if(!$stockWithoutBill || $stockWithoutBill->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No Missing Bills</h3>
                        <p class="text-xs text-slate-500 mt-1">All advance physical intake in this warehouse has been matched with vendor bills.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5 w-10 text-center">
                                        <input type="checkbox"
                                               @change="toggleSelectAllAdvances($event)"
                                               :checked="isAllAdvancesSelected()"
                                               class="w-4 h-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                                    </th>
                                    <th class="p-3.5">Advance GRN & Date</th>
                                    <th class="p-3.5">Product Line Items</th>
                                    <th class="p-3.5 text-right font-black text-indigo-800">Total Missing Qty</th>
                                    <th class="p-3.5 text-center">Age</th>
                                    <th class="p-3.5 text-center">Status</th>
                                    <th class="p-3.5 text-right pr-5">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($stockWithoutBill as $grn)
                                    @php
                                        $grnId = (int) $grn['id'];
                                        $grnNum = $grn['grn_number'] ?? "GRN #{$grnId}";
                                        $recDate = $grn['received_at'] ? \Carbon\Carbon::parse($grn['received_at'])->format('d M Y') : 'N/A';
                                        $age = (int) ($grn['age_days'] ?? 0);
                                        $missingTotal = (float) ($grn['total_missing_qty'] ?? 0.0);
                                        $items = $grn['items'] ?? [];
                                    @endphp
                                    <tr class="hover:bg-slate-50/60 transition-colors" :class="isAdvanceSelected({{ $grnId }}) ? 'bg-amber-50/40' : ''">
                                        <td class="p-3.5 pl-5 text-center align-top">
                                            <input type="checkbox"
                                                   :checked="isAdvanceSelected({{ $grnId }})"
                                                   @change="toggleAdvanceSelection({{ $grnId }})"
                                                   class="w-4 h-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                                        </td>
                                        <td class="p-3.5 align-top">
                                            <div class="font-mono font-black text-sm text-indigo-900 flex items-center gap-1.5">
                                                <span>{{ $grnNum }}</span>
                                            </div>
                                            <div class="font-bold text-slate-500 text-[11px] mt-0.5">
                                                {{ $recDate }}
                                            </div>
                                        </td>
                                        <td class="p-3.5 align-top">
                                            <div class="space-y-1.5">
                                                @foreach($items as $it)
                                                    <div class="flex items-center justify-between gap-4 p-1.5 rounded-xl bg-slate-50 border border-slate-100">
                                                        <div>
                                                            <span class="font-black text-slate-900">{{ $it['name'] }}</span>
                                                            @if(!empty($it['sku']))
                                                                <span class="font-mono text-[10px] text-slate-400 ml-1">({{ $it['sku'] }})</span>
                                                            @endif
                                                        </div>
                                                        <div class="text-right whitespace-nowrap text-[11px]">
                                                            <span class="font-bold text-slate-500">Rec: {{ number_format((float)$it['received_qty'], 2) }}</span>
                                                            <span class="text-slate-300 mx-1">|</span>
                                                            <span class="font-bold text-purple-700">Matched: {{ number_format((float)$it['matched_qty'], 2) }}</span>
                                                            <span class="text-slate-300 mx-1">|</span>
                                                            <span class="font-black text-indigo-700">Missing: {{ number_format((float)$it['missing_qty'], 2) }} {{ $it['unit'] }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-black text-sm text-indigo-700 align-top whitespace-nowrap">
                                            {{ number_format($missingTotal, 2) }} <span class="text-[10px] text-indigo-400">KG</span>
                                        </td>
                                        <td class="p-3.5 text-center font-bold align-top whitespace-nowrap {{ $age > 3 ? 'text-rose-600' : 'text-slate-600' }}">
                                            {{ $age }} {{ Str::plural('day', $age) }}
                                        </td>
                                        <td class="p-3.5 text-center align-top whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-[10px] font-black uppercase bg-indigo-50 text-indigo-800 border border-indigo-200">
                                                <span>AWAITING BILL</span>
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-right pr-5 align-top whitespace-nowrap">
                                            <button type="button"
                                                    @click="openClearSingleAdvance({{ $grnId }})"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 text-xs font-bold transition shadow-2xs cursor-pointer">
                                                <i data-lucide="check" class="w-3 h-3"></i>
                                                <span>Clear GRN</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-slate-100">
                        {{ $stockWithoutBill->links() }}
                    </div>
                @endif
            </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 4: SHOP RETURNS (SECTION 4)                                    -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @elseif($tab === 'shop_returns')
            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden space-y-4">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="undo-2" class="w-4 h-4 text-blue-600"></i>
                            <span>Returns From Shops (Sale Reversals)</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">
                            Stock returned back to warehouse sellable stock on <strong class="text-slate-800">{{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</strong>
                        </p>
                    </div>

                    @if($shopReturns)
                        <span class="text-xs font-bold text-slate-400 self-start sm:self-auto">
                            Showing {{ $shopReturns->firstItem() ?? 0 }}–{{ $shopReturns->lastItem() ?? 0 }} of {{ $shopReturns->total() }} returns
                        </span>
                    @endif
                </div>

                <!-- Multi-select Action Bar for Shop Returns -->
                <div x-show="selectedReturnProducts.length > 0"
                     x-cloak
                     class="mx-4 sm:mx-5 p-3 rounded-2xl bg-rose-50 border border-rose-200 flex flex-wrap items-center justify-between gap-3 transition-all">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-600 text-white text-xs font-black" x-text="selectedReturnProducts.length"></span>
                        <span class="text-xs font-black text-rose-950">
                            <span x-text="selectedReturnProducts.length"></span> returned item(s) selected
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="selectedReturnProducts = []"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-rose-100/60 transition cursor-pointer">
                            Clear Selection
                        </button>
                        <button type="button"
                                @click="openDamageModalForSelection('returns')"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-black shadow-xs transition cursor-pointer">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            <span>Move Selected to Damage</span>
                        </button>
                    </div>
                </div>

                @if(!$shopReturns || $shopReturns->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No Shop Returns Today</h3>
                        <p class="text-xs text-slate-500 mt-1">There are no shop return movements recorded for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5 w-10 text-center">
                                        <input type="checkbox"
                                               @change="toggleSelectAllReturns($event)"
                                               :checked="isAllReturnsSelected()"
                                               class="w-4 h-4 rounded text-rose-600 border-slate-300 focus:ring-rose-500 cursor-pointer">
                                    </th>
                                    <th class="p-3.5">Shop</th>
                                    <th class="p-3.5">Product</th>
                                    <th class="p-3.5 text-right text-blue-900 font-black">Returned Qty</th>
                                    <th class="p-3.5">Order / Invoice Ref</th>
                                    <th class="p-3.5 text-center">Return Time</th>
                                    <th class="p-3.5 text-center pr-5">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($shopReturns as $ret)
                                    @php
                                        $shop = $ret->shopOrderItem?->shopOrder?->shop;
                                        $order = $ret->shopOrderItem?->shopOrder;
                                        $prod = $ret->product;
                                        $unit = $prod?->unit ?? 'KG';
                                        $retQty = (float) $ret->quantity;
                                        $sellable = max(0.0, (float) ($currentStockByProduct[$ret->product_id] ?? 0.0));
                                        $avail = min($retQty, $sellable);
                                        $retPayload = [
                                            'product_id' => $ret->product_id,
                                            'name' => $prod?->name ?? ('Product #' . $ret->product_id),
                                            'sku' => $prod?->sku ?? '',
                                            'unit' => $unit,
                                            'available_qty' => $avail,
                                            'quantity' => $avail > 0 ? $avail : 0.0,
                                            'reason' => 'transit_damage',
                                            'grade' => 'U',
                                            'shop_name' => $shop?->name ?? '',
                                            'order_ref' => $order?->invoice_number ?? $order?->order_number ?? '',
                                        ];
                                    @endphp
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="p-3.5 pl-5 text-center">
                                            <input type="checkbox"
                                                   @change="toggleReturnProduct({{ json_encode($retPayload) }}, $event)"
                                                   :checked="isReturnSelected({{ $ret->product_id }})"
                                                   {{ $avail <= 0 ? 'disabled' : '' }}
                                                   class="w-4 h-4 rounded text-rose-600 border-slate-300 focus:ring-rose-500 cursor-pointer disabled:opacity-25 disabled:cursor-not-allowed">
                                        </td>
                                        <td class="p-3.5 font-black text-slate-900">
                                            <span>{{ $shop?->name ?? 'Shop' }}</span>
                                            <span class="block text-[10px] font-mono text-slate-400">{{ $shop?->code }}</span>
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-900">
                                            <span>{{ $prod?->name ?? 'Product #' . $ret->product_id }}</span>
                                            <span class="block text-[10px] font-mono text-slate-400">{{ $prod?->sku }}</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-black text-blue-900">
                                            +{{ number_format($retQty, 2) }} <span class="text-[10px] text-blue-400">{{ $unit }}</span>
                                            @if($avail < $retQty)
                                                <span class="block text-[10px] text-amber-600 font-sans font-bold" title="Current sellable stock is lower than returned quantity">
                                                    ({{ number_format($avail, 2) }} avail)
                                                </span>
                                            @endif
                                        </td>
                                        <td class="p-3.5 font-mono text-slate-700">
                                            <span>{{ $order?->invoice_number ?? $order?->order_number ?? ('Item #' . $ret->shop_order_item_id) }}</span>
                                        </td>
                                        <td class="p-3.5 text-center font-medium text-slate-500 whitespace-nowrap">
                                            {{ $ret->created_at?->format('H:i') ?? '—' }}
                                        </td>
                                        <td class="p-3.5 text-center pr-5 whitespace-nowrap">
                                            @if($avail > 0)
                                                <button type="button"
                                                        @click="openDamageModalSingle({{ $ret->product_id }}, '{{ addslashes($prod?->name ?? '') }}', '{{ $unit }}', {{ $avail }}, '{{ addslashes($prod?->sku ?? '') }}')"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 text-[11px] font-bold border border-rose-200 transition cursor-pointer">
                                                    <i data-lucide="trash-2" class="w-3 h-3 text-rose-600"></i>
                                                    <span>Move to Damage</span>
                                                </button>
                                            @else
                                                <button type="button"
                                                        disabled
                                                        title="No sellable stock currently available to move to damage"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 text-slate-400 text-[11px] font-bold border border-slate-200 opacity-50 cursor-not-allowed">
                                                    <i data-lucide="trash-2" class="w-3 h-3 text-slate-400"></i>
                                                    <span>Move to Damage</span>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-slate-100">
                        {{ $shopReturns->links() }}
                    </div>
                @endif
            </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 5: DAMAGE (SECTION 5)                                          -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @elseif($tab === 'damage')
            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden space-y-4">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="trash-2" class="w-4 h-4 text-rose-600"></i>
                            <span>Daily Damage &amp; Wastage Write-Offs</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">
                            Damaged items written off from sellable warehouse stock on <strong class="text-slate-800">{{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</strong>
                        </p>
                    </div>

                    <div class="flex items-center gap-2 self-start sm:self-auto">
                        <button type="button"
                                @click="openDamageModal()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-black transition-all shadow-sm cursor-pointer">
                            <i data-lucide="plus" class="w-4 h-4 text-rose-200"></i>
                            <span>Record Damage</span>
                        </button>
                    </div>
                </div>

                @if(!$damageEntries || $damageEntries->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No Damage Entries Today</h3>
                        <p class="text-xs text-slate-500 mt-1">There is zero wastage/damage recorded on {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5">Product</th>
                                    <th class="p-3.5 text-right text-rose-900 font-black">Damaged Qty</th>
                                    <th class="p-3.5">Reason</th>
                                    <th class="p-3.5">Date</th>
                                    <th class="p-3.5">Source / Batch</th>
                                    <th class="p-3.5 text-center pr-5">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($damageEntries as $entry)
                                    @php
                                        $prod = $entry->product;
                                        $unit = $prod?->unit ?? 'KG';
                                        $reasonLabel = $entry->reason instanceof \App\Enums\Inventory\WastageReason ? $entry->reason->label() : (string) $entry->reason;
                                    @endphp
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="p-3.5 pl-5 font-black text-slate-900">
                                            <span>{{ $prod?->name ?? 'Product #' . $entry->product_id }}</span>
                                            <span class="block text-[10px] font-mono text-slate-400">{{ $prod?->sku }}</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-black text-rose-900">
                                            -{{ number_format((float) $entry->quantity, 2) }} <span class="text-[10px] text-rose-400">{{ $unit }}</span>
                                        </td>
                                        <td class="p-3.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 font-bold text-[11px]">
                                                {{ $reasonLabel }}
                                            </span>
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-600 whitespace-nowrap">
                                            {{ $entry->wastage_date?->format('d M Y') ?? '—' }}
                                        </td>
                                        <td class="p-3.5 text-slate-600 font-medium">
                                            <span>{{ $entry->batch?->reference ?? 'Direct Warehouse Stock' }}</span>
                                            @if($entry->notes)
                                                <span class="block text-[10px] text-slate-400 truncate max-w-xs">{{ $entry->notes }}</span>
                                            @endif
                                        </td>
                                        <td class="p-3.5 text-center pr-5 font-bold text-slate-700">
                                            {{ $entry->recordedBy?->name ?? 'Staff' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-slate-100">
                        {{ $damageEntries->links() }}
                    </div>
                @endif
            </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 6: PHYSICAL CHECK (SECTION 6)                                  -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @elseif($tab === 'physical_check')
            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden space-y-4">
                    <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i data-lucide="clipboard-check" class="w-4 h-4 text-amber-600"></i>
                                <span>Physical Count Reconciliation &amp; Stock Audit</span>
                            </h2>
                            <p class="text-xs text-slate-500 font-semibold mt-0.5">
                                Enter physical counts to reconcile variances via canonical StockAdjustmentService
                            </p>
                        </div>

                        @if($physicalCheckProducts)
                            <span class="text-xs font-bold text-slate-400 self-start sm:self-auto">
                                Showing {{ $physicalCheckProducts->firstItem() ?? 0 }}–{{ $physicalCheckProducts->lastItem() ?? 0 }} of {{ $physicalCheckProducts->total() }} products
                            </span>
                        @endif
                    </div>

                    @if(!$physicalCheckProducts || $physicalCheckProducts->isEmpty())
                        <div class="p-12 text-center">
                            <div class="w-12 h-12 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                <i data-lucide="inbox" class="w-6 h-6"></i>
                            </div>
                            <h3 class="text-base font-black text-slate-900">No Products Found</h3>
                            <p class="text-xs text-slate-500 mt-1">No active products match your search.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                        <th class="p-3.5 pl-5">Product</th>
                                        <th class="p-3.5">Category</th>
                                        <th class="p-3.5 text-right font-black text-slate-900">ERP Balance</th>
                                        <th class="p-3.5 text-center w-40">Physical Count</th>
                                        <th class="p-3.5 text-right font-black">Variance</th>
                                        <th class="p-3.5 text-center pr-5">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-xs">
                                    @foreach($physicalCheckProducts as $row)
                                        @php
                                            $prodId = $row['product_id'];
                                            $erpBal = (float) $row['erp_balance'];
                                            $unit = $row['unit'] ?? 'KG';
                                        @endphp
                                        <tr class="hover:bg-slate-50/60 transition-colors"
                                            x-data="{
                                                erpBal: {{ $erpBal }},
                                                counted: '{{ $erpBal }}',
                                                get diff() {
                                                    const c = parseFloat(this.counted);
                                                    if (isNaN(c)) return 0;
                                                    return Math.round((c - this.erpBal) * 1000) / 1000;
                                                }
                                            }">
                                            <td class="p-3.5 pl-5 font-black text-slate-900">
                                                <span>{{ $row['name'] }}</span>
                                                <span class="block text-[10px] font-mono text-slate-400">{{ $row['sku'] }}</span>
                                            </td>
                                            <td class="p-3.5 font-bold text-slate-600">
                                                {{ $row['category'] }}
                                            </td>
                                            <td class="p-3.5 text-right font-mono font-black text-slate-900">
                                                {{ number_format($erpBal, 2) }} <span class="text-[10px] text-slate-400 font-sans">{{ $unit }}</span>
                                            </td>
                                            <td class="p-3.5 text-center">
                                                <div class="relative inline-block w-32">
                                                    <input type="number"
                                                           step="0.01"
                                                           min="0"
                                                           x-model="counted"
                                                           class="w-full text-center px-2 py-1 text-xs font-mono font-black rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                                </div>
                                            </td>
                                            <td class="p-3.5 text-right font-mono font-black"
                                                :class="diff === 0 ? 'text-slate-400' : (diff > 0 ? 'text-emerald-700' : 'text-rose-700')">
                                                <span x-text="(diff > 0 ? '+' : '') + diff.toFixed(2)"></span>
                                                <span class="text-[10px] font-sans text-slate-400">{{ $unit }}</span>
                                            </td>
                                            <td class="p-3.5 text-center pr-5 whitespace-nowrap">
                                                <form method="POST" action="{{ route('inventory.stock.adjustments.store', $row['product'] ?? $prodId) }}" class="inline-block" onsubmit="return confirm('Confirm physical stock adjustment?');">
                                                    @csrf
                                                    <input type="hidden" name="system_qty" value="{{ $erpBal }}">
                                                    <input type="hidden" name="counted_qty" :value="counted">
                                                    <input type="hidden" name="business_date" value="{{ $selectedDate }}">
                                                    @if($selectedWarehouseId)
                                                        <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                                                    @endif
                                                    <input type="hidden" name="notes" value="Admin Inventory Action Center count check">

                                                    <button type="submit"
                                                            :disabled="diff === 0"
                                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-black transition disabled:opacity-30 cursor-pointer"
                                                            :class="diff === 0 ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : (diff > 0 ? 'bg-emerald-700 hover:bg-emerald-800 text-white shadow-2xs' : 'bg-rose-700 hover:bg-rose-800 text-white shadow-2xs')">
                                                        <i data-lucide="check" class="w-3 h-3"></i>
                                                        <span>Reconcile</span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="p-4 border-t border-slate-100">
                            {{ $physicalCheckProducts->links() }}
                        </div>
                    @endif
                </div>

                <!-- Recent Adjustments on Date -->
                @if($recentAdjustments && $recentAdjustments->isNotEmpty())
                    <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden space-y-3">
                        <div class="p-4 sm:p-5 border-b border-slate-100">
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i data-lucide="history" class="w-4 h-4 text-amber-600"></i>
                                <span>Adjustments Recorded on {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</span>
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                        <th class="p-3 pl-5">Product</th>
                                        <th class="p-3 text-right">System Qty</th>
                                        <th class="p-3 text-right">Counted Qty</th>
                                        <th class="p-3 text-right">Variance</th>
                                        <th class="p-3">Category / Reason</th>
                                        <th class="p-3 text-center pr-5">Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($recentAdjustments as $adj)
                                        <tr>
                                            <td class="p-3 pl-5 font-bold text-slate-900">{{ $adj->product?->name }}</td>
                                            <td class="p-3 text-right font-mono">{{ number_format((float) $adj->system_qty, 2) }}</td>
                                            <td class="p-3 text-right font-mono">{{ number_format((float) $adj->counted_qty, 2) }}</td>
                                            <td class="p-3 text-right font-mono font-black {{ (float) $adj->variance_qty >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                                {{ (float) $adj->variance_qty > 0 ? '+' : '' }}{{ number_format((float) $adj->variance_qty, 2) }}
                                            </td>
                                            <td class="p-3 font-semibold text-slate-600">{{ $adj->notes ?: $adj->category }}</td>
                                            <td class="p-3 text-center pr-5 text-slate-500">{{ $adj->createdBy?->name ?? 'Admin' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 7: UNIT DIFFERENCES (EXCEPTION TAB)                            -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @elseif($tab === 'unit_differences')
            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="scale" class="w-4 h-4 text-sky-600"></i>
                            <span>Unit Mismatch &amp; Conversion Review</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">
                            Products with mismatched purchase and stock units requiring conversion setup
                        </p>
                    </div>
                </div>

                @if(!$unitDifferences || $unitDifferences->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No Unit Differences Found</h3>
                        <p class="text-xs text-slate-500 mt-1">All purchase and advance product units match standard conversions.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5">Product</th>
                                    <th class="p-3.5">Category</th>
                                    <th class="p-3.5">Stock Unit</th>
                                    <th class="p-3.5">Purchase Unit</th>
                                    <th class="p-3.5 text-center">Details</th>
                                    <th class="p-3.5 text-center pr-5">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($unitDifferences as $row)
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="p-3.5 pl-5 font-black text-slate-900">
                                            {{ $row['product_name'] }}
                                            <span class="block text-[10px] font-mono text-slate-400">{{ $row['product_sku'] }}</span>
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-700">
                                            {{ $row['category_name'] ?? 'General' }}
                                        </td>
                                        <td class="p-3.5 font-mono font-bold text-emerald-800">
                                            {{ $row['base_unit'] }}
                                        </td>
                                        <td class="p-3.5 font-mono font-bold text-purple-800">
                                            {{ $row['received_unit'] }}
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                {{ $row['reason'] ?? 'Unit conversion missing' }}
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-center pr-5">
                                            <button type="button"
                                                    @click="openResolveUnitModal({{ $row['product_id'] }}, '{{ addslashes($row['product_name']) }}', '{{ $row['base_unit'] }}', '{{ $row['received_unit'] }}')"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-sky-700 text-white text-[11px] font-black hover:bg-sky-800 transition shadow-2xs cursor-pointer">
                                                <i data-lucide="wrench" class="w-3 h-3 text-sky-200"></i>
                                                <span>Resolve Unit</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-slate-100">
                        {{ $unitDifferences->links() }}
                    </div>
                @endif
            </div>
        @endif

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- MODALS & ALPINES                                                   -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <!-- 1. SHARE MISSING BILLS MODAL (SECTION 2) -->
        <div x-show="shareMissingBillsModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
            <div @click.away="closeShareMissingBillsModal()"
                 class="relative w-full max-w-xl bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-indigo-50/50">
                    <div>
                        <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="share-2" class="w-5 h-5 text-indigo-600"></i>
                            <span>Share Missing Bills Report</span>
                        </h3>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Quick WhatsApp or text summary of unbilled advance items</p>
                    </div>
                    <button type="button" @click="closeShareMissingBillsModal()" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 flex-1 overflow-y-auto space-y-3">
                    <label class="text-xs font-black text-slate-700 uppercase tracking-wider block">Generated Message Preview:</label>
                    <textarea x-model="shareReportText"
                              rows="10"
                              readonly
                              class="w-full p-3 font-mono text-xs rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 select-all focus:bg-white focus:outline-none"></textarea>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <button type="button"
                            @click="copyShareReport()"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-black bg-slate-900 text-white hover:bg-slate-800 transition shadow-xs cursor-pointer">
                        <i data-lucide="copy" class="w-4 h-4"></i>
                        <span x-text="copiedShareReport ? 'Copied to Clipboard!' : 'Copy Text'"></span>
                    </button>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="closeShareMissingBillsModal()" class="px-4 py-2 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-200 transition">
                            Close
                        </button>
                        <a :href="'https://api.whatsapp.com/send?text=' + encodeURIComponent(shareReportText)"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm cursor-pointer">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            <span>Open WhatsApp</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1B. CLEAR ADVANCES MODAL (PHASE 3 ADMIN MANUAL ADVANCE CLEAR) -->
        <div x-show="clearAdvancesModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
            <div @click.away="closeClearAdvancesModal()"
                 class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-amber-50/60">
                    <div>
                        <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="check-check" class="w-5 h-5 text-amber-600"></i>
                            <span>Admin Manual Advance Clear</span>
                        </h3>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">
                            Administratively close selected Advance GRN(s) from the Missing Bill queue
                        </p>
                    </div>
                    <button type="button" @click="closeClearAdvancesModal()" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.cashbook.inventory.clear-advances') }}" @submit="isSubmittingClearAdvances = true" class="flex flex-col flex-1 overflow-hidden">
                    @csrf
                    <input type="hidden" name="warehouse_id" :value="currentWarehouseId">
                    <input type="hidden" name="date" :value="currentDate">
                    <input type="hidden" name="tab" value="stock_without_bill">
                    
                    <template x-for="advId in selectedClearAdvanceIds" :key="advId">
                        <input type="hidden" name="advance_ids[]" :value="advId">
                    </template>

                    <input type="hidden" name="reason" :value="getEffectiveClearReason()">

                    <!-- Body -->
                    <div class="p-6 flex-1 overflow-y-auto space-y-4">
                        <!-- Warning Notice -->
                        <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2.5">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                            <div class="space-y-1">
                                <span class="font-black block">Administrative Paperwork Clear Only</span>
                                <p class="text-amber-800 leading-relaxed">
                                    This closes the selected Advances from the Missing Bill queue. 
                                    <strong>It does NOT change warehouse stock, does NOT create a vendor bill, and does NOT generate an accounting journal.</strong>
                                </p>
                            </div>
                        </div>

                        <!-- Selected Advances Summary List -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-black text-slate-700 uppercase tracking-wider">
                                    Selected Advances (<span x-text="selectedClearAdvanceIds.length"></span>)
                                </label>
                                <span class="text-xs font-mono font-bold text-slate-500">
                                    Total Missing: <span x-text="getSelectedAdvancesTotalQty().toFixed(2)"></span> KG
                                </span>
                            </div>

                            <div class="max-h-48 overflow-y-auto space-y-2 rounded-2xl border border-slate-200 p-3 bg-slate-50/50 divide-y divide-slate-100">
                                <template x-for="adv in getSelectedAdvancesList()" :key="adv.id">
                                    <div class="pt-2 first:pt-0 flex items-start justify-between gap-3 text-xs">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-black text-indigo-900" x-text="adv.grn_number"></span>
                                                <span class="text-[10px] text-slate-400" x-text="adv.received_at"></span>
                                            </div>
                                            <p class="text-[11px] text-slate-600 mt-0.5" x-text="adv.items_summary"></p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="font-mono font-black text-indigo-700" x-text="adv.total_missing_qty.toFixed(2) + ' KG'"></span>
                                            <span class="block text-[10px] text-slate-400">missing</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Reason Selection -->
                        <div class="space-y-3">
                            <label class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-1">
                                <span>Reason for Manual Clear</span>
                                <span class="text-rose-500">*</span>
                            </label>

                            <select x-model="clearAdvancesReasonChoice"
                                    class="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 bg-white text-xs font-bold text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none cursor-pointer">
                                <option value="Bill not required">Bill not required</option>
                                <option value="Vendor settlement handled separately">Vendor settlement handled separately</option>
                                <option value="Old / legacy Advance">Old / legacy Advance</option>
                                <option value="Data correction">Data correction</option>
                                <option value="Other">Other (specify note below)</option>
                            </select>

                            <div x-show="clearAdvancesReasonChoice === 'Other'" class="space-y-1">
                                <textarea x-model="clearAdvancesCustomReason"
                                          placeholder="Enter specific mandatory reason for clearing these advances..."
                                          rows="2"
                                          class="w-full p-3 text-xs rounded-2xl border border-slate-200 bg-white text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none placeholder:text-slate-400"></textarea>
                            </div>

                            <div class="space-y-1">
                                <label class="text-[11px] font-bold text-slate-500">Additional Notes (Optional):</label>
                                <input type="text"
                                       x-model="clearAdvancesNotes"
                                       placeholder="e.g., cleared per vendor reconciliation agreement"
                                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none placeholder:text-slate-400">
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <button type="button" @click="closeClearAdvancesModal()" class="px-4 py-2 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-200 transition cursor-pointer">
                            Cancel
                        </button>

                        <button type="submit"
                                :disabled="isSubmittingClearAdvances || selectedClearAdvanceIds.length === 0 || getEffectiveClearReason().trim() === ''"
                                class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs font-black bg-amber-600 hover:bg-amber-700 text-white disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm cursor-pointer">
                            <i data-lucide="check-check" class="w-4 h-4"></i>
                            <span x-show="!isSubmittingClearAdvances">Clear <span x-text="selectedClearAdvanceIds.length"></span> Advance(s)</span>
                            <span x-show="isSubmittingClearAdvances">Clearing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. MOVE TO DAMAGE MODAL (MULTI-PRODUCT SUPPORT) -->
        <div x-show="damageModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
            <div @click.away="closeDamageModal()"
                 class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                <form method="POST" action="{{ route('admin.cashbook.inventory.move-to-damage') }}" class="flex flex-col h-full">
                    @csrf
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-rose-50/50">
                        <div>
                            <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <i data-lucide="trash-2" class="w-5 h-5 text-rose-600"></i>
                                <span>Move Selected to Damage</span>
                            </h3>
                            <p class="text-xs text-slate-500 font-semibold mt-0.5">Reduces sellable warehouse stock and writes off damage entries in one safe transaction</p>
                        </div>
                        <button type="button" @click="closeDamageModal()" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6 flex-1 overflow-y-auto space-y-4 text-xs">
                        <!-- Common Controls -->
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="font-black text-slate-700 block mb-1">Common Reason *</label>
                                    <select name="common_reason"
                                            x-model="damageCommonReason"
                                            @change="updateCommonReason($event.target.value)"
                                            required
                                            class="w-full px-3 py-2 text-xs font-bold rounded-xl bg-white border border-slate-200 text-slate-900 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500">
                                        <option value="transit_damage">Transit Damage</option>
                                        <option value="rotten">Rotten / Spoiled</option>
                                        <option value="sorting_damage">Sorting / Grading Damage</option>
                                        <option value="expired">Expired</option>
                                        <option value="unsold">Unsold / Aged Out</option>
                                        <option value="shrinkage">Weight Shrinkage</option>
                                    </select>
                                    <span class="text-[10px] text-slate-400 font-semibold">Applies to all selected items unless overridden below</span>
                                </div>
                                <div>
                                    <label class="font-black text-slate-700 block mb-1">Wastage Date *</label>
                                    <input type="date"
                                           name="wastage_date"
                                           x-model="damageDate"
                                           max="{{ today()->toDateString() }}"
                                           required
                                           class="w-full px-3 py-2 text-xs font-bold rounded-xl bg-white border border-slate-200 text-slate-900 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500">
                                </div>
                            </div>
                        </div>

                        <!-- Selected Products List -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                                    <i data-lucide="package" class="w-3.5 h-3.5 text-rose-600"></i>
                                    <span>Selected Products (<span x-text="damageModalItems.length"></span>)</span>
                                </label>
                            </div>

                            <template x-if="damageModalItems.length === 0">
                                <div class="p-6 rounded-2xl border border-dashed border-slate-300 text-center text-slate-400 bg-slate-50/50">
                                    <p class="font-bold">No products selected for damage write-off.</p>
                                    <p class="text-[11px] mt-1">Select products from the table or add below.</p>
                                </div>
                            </template>

                            <template x-if="damageModalItems.length > 0">
                                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                                <th class="p-2.5 pl-3.5">Product</th>
                                                <th class="p-2.5 text-right w-24">Avail / Source</th>
                                                <th class="p-2.5 text-right w-28">Qty to Move *</th>
                                                <th class="p-2.5 w-36">Reason (Override)</th>
                                                <th class="p-2.5 text-center w-10 pr-3.5"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-xs">
                                            <template x-for="(item, idx) in damageModalItems" :key="item.product_id + '_' + idx">
                                                <tr class="hover:bg-slate-50/60">
                                                    <td class="p-2.5 pl-3.5">
                                                        <span class="font-black text-slate-900 block" x-text="item.name"></span>
                                                        <span class="text-[10px] font-mono text-slate-400" x-text="item.sku"></span>
                                                        <input type="hidden" :name="'items[' + idx + '][product_id]'" :value="item.product_id">
                                                        <input type="hidden" :name="'items[' + idx + '][grade]'" :value="item.grade || 'U'">
                                                    </td>
                                                    <td class="p-2.5 text-right font-mono font-bold text-slate-600 text-[11px]">
                                                        <span x-text="Number(item.available_qty || 0).toFixed(2)"></span>
                                                        <span class="text-[10px] text-slate-400 font-sans" x-text="item.unit || 'KG'"></span>
                                                    </td>
                                                    <td class="p-2.5 text-right">
                                                        <div class="flex items-center justify-end gap-1">
                                                            <input type="number"
                                                                   step="0.01"
                                                                   min="0.01"
                                                                   required
                                                                   :name="'items[' + idx + '][quantity]'"
                                                                   x-model.number="item.quantity"
                                                                   class="w-20 px-2 py-1 text-xs font-mono font-bold text-right rounded-lg bg-slate-50 border border-slate-200 text-slate-900 focus:bg-white focus:outline-none focus:ring-1 focus:ring-rose-500">
                                                            <span class="text-[10px] text-slate-400 font-bold" x-text="item.unit || 'KG'"></span>
                                                        </div>
                                                    </td>
                                                    <td class="p-2.5">
                                                        <select :name="'items[' + idx + '][reason]'"
                                                                x-model="item.reason"
                                                                class="w-full px-2 py-1 text-[11px] font-semibold rounded-lg bg-slate-50 border border-slate-200 text-slate-900 focus:bg-white focus:outline-none focus:ring-1 focus:ring-rose-500">
                                                            <option value="transit_damage">Transit Damage</option>
                                                            <option value="rotten">Rotten / Spoiled</option>
                                                            <option value="sorting_damage">Sorting / Grading</option>
                                                            <option value="expired">Expired</option>
                                                            <option value="unsold">Unsold / Aged</option>
                                                            <option value="shrinkage">Shrinkage</option>
                                                        </select>
                                                    </td>
                                                    <td class="p-2.5 text-center pr-3.5">
                                                        <button type="button"
                                                                @click="removeDamageItem(idx)"
                                                                class="p-1 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer">
                                                            <i data-lucide="x" class="w-4 h-4"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </div>

                        <!-- Add More Products -->
                        <div class="p-3 rounded-2xl bg-slate-50/80 border border-slate-200 flex flex-col sm:flex-row items-center gap-2">
                            <select x-model="selectedAddProductId"
                                    class="w-full sm:flex-1 px-3 py-1.5 text-xs font-bold rounded-xl bg-white border border-slate-200 text-slate-900 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500">
                                <option value="">-- Add another product to write-off --</option>
                                @foreach($damageProducts ?? [] as $p)
                                    @php
                                        $pId = is_array($p) ? $p['id'] : $p->id;
                                        $pName = is_array($p) ? $p['name'] : $p->name;
                                        $pSku = is_array($p) ? $p['sku'] : $p->sku;
                                    @endphp
                                    <option value="{{ $pId }}">{{ $pName }} ({{ $pSku }})</option>
                                @endforeach
                            </select>
                            <button type="button"
                                    @click="addDamageProduct(selectedAddProductId); selectedAddProductId = ''"
                                    :disabled="!selectedAddProductId"
                                    class="w-full sm:w-auto px-3.5 py-1.5 rounded-xl text-xs font-black bg-slate-800 hover:bg-slate-900 text-white disabled:opacity-40 transition shadow-xs cursor-pointer flex items-center justify-center gap-1">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                <span>Add Product</span>
                            </button>
                        </div>

                        <div>
                            <label class="font-black text-slate-700 block mb-1">Notes / Discard Details (Optional)</label>
                            <textarea name="notes"
                                      x-model="damageNotes"
                                      rows="2"
                                      placeholder="Optional reason details or batch reference..."
                                      class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500"></textarea>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between gap-2 bg-slate-50/50">
                        <div class="text-xs text-slate-500 font-bold">
                            <span x-text="damageModalItems.length"></span> product(s) | Total: <span class="font-mono font-black text-rose-900" x-text="totalDamageModalQty().toFixed(2)"></span> KG
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="closeDamageModal()" class="px-4 py-2 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-200 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="damageModalItems.length === 0 || totalDamageModalQty() <= 0"
                                    class="px-5 py-2 rounded-xl text-xs font-black bg-rose-700 hover:bg-rose-800 text-white disabled:opacity-50 transition shadow-sm cursor-pointer">
                                Confirm Damage Write-off
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 3. AUTO MATCH PREVIEW & EXECUTE MODAL -->
        <div x-show="autoClearModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
            <div @click.away="closeAutoClear()"
                 class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="sparkles" class="w-5 h-5 text-emerald-600"></i>
                            <span>Auto Match &amp; Clear Preview</span>
                        </h3>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">FIFO matching between approved pending bills and open advances</p>
                    </div>
                    <button type="button" @click="closeAutoClear()" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 flex-1 overflow-y-auto space-y-4">
                    <template x-if="isLoadingAutoClear">
                        <div class="py-12 text-center space-y-3">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-emerald-600 border-t-transparent"></div>
                            <p class="text-xs font-bold text-slate-600">Generating deterministic auto-clear preview...</p>
                        </div>
                    </template>

                    <template x-if="!isLoadingAutoClear && autoClearPlan">
                        <div class="space-y-4">
                            <!-- Plan Summary Cards -->
                            <div class="grid grid-cols-3 gap-3">
                                <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-center">
                                    <span class="text-[10px] font-black uppercase text-emerald-800">Ready Bills</span>
                                    <h4 class="text-xl font-black text-emerald-950 mt-0.5" x-text="autoClearPlan.summary.ready_bills"></h4>
                                </div>
                                <div class="p-3.5 rounded-2xl bg-indigo-50 border border-indigo-200 text-center">
                                    <span class="text-[10px] font-black uppercase text-indigo-800">Matched Base Qty</span>
                                    <h4 class="text-xl font-black text-indigo-950 mt-0.5" x-text="Number(autoClearPlan.summary.matched_base_qty || 0).toFixed(2) + ' KG'"></h4>
                                </div>
                                <div class="p-3.5 rounded-2xl bg-purple-50 border border-purple-200 text-center">
                                    <span class="text-[10px] font-black uppercase text-purple-800">Advances Cleared</span>
                                    <h4 class="text-xl font-black text-purple-950 mt-0.5" x-text="(autoClearPlan.summary.advances_fully_cleared || 0) + ' Full, ' + (autoClearPlan.summary.advances_partially_cleared || 0) + ' Part'"></h4>
                                </div>
                            </div>

                            <!-- Ready Bills List -->
                            <div class="space-y-2">
                                <h4 class="text-xs font-black uppercase tracking-wider text-slate-800">Ready Bills to Reconcile</h4>
                                <template x-if="autoClearPlan.ready_bills.length === 0">
                                    <div class="p-6 rounded-2xl bg-slate-50 text-center text-xs font-bold text-slate-500">
                                        No bills are currently matchable with open advance stock.
                                    </div>
                                </template>
                                <template x-for="rb in autoClearPlan.ready_bills" :key="rb.purchase_order_id">
                                    <div class="p-3 rounded-2xl border border-emerald-200 bg-emerald-50/30 flex items-center justify-between text-xs">
                                        <div>
                                            <span class="font-mono font-black text-slate-900" x-text="rb.reference"></span>
                                            <span class="font-bold text-slate-700 ml-2" x-text="rb.supplier_name"></span>
                                        </div>
                                        <span class="font-mono font-black text-emerald-800" x-text="Number(rb.matched_base_qty).toFixed(2) + ' KG'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-2 bg-slate-50/50">
                    <button type="button" @click="closeAutoClear()" class="px-4 py-2 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-200 transition">
                        Cancel
                    </button>
                    <button type="button"
                            @click="executeAutoClear()"
                            :disabled="isLoadingAutoClear || isExecutingAutoClear || !autoClearPlan || autoClearPlan.ready_bills.length === 0"
                            class="px-5 py-2 rounded-xl text-xs font-black bg-emerald-700 text-white hover:bg-emerald-800 disabled:opacity-50 transition shadow-sm cursor-pointer">
                        <span x-show="!isExecutingAutoClear">Confirm &amp; Execute Auto Match</span>
                        <span x-show="isExecutingAutoClear">Executing Match...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 4. ACCEPT ALL PENDING BILLS MODAL (DAY GROUPED) -->
        <div x-show="pendingBillsModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
            <div @click.away="closePendingBillsModal()"
                 class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-amber-50/50">
                    <div>
                        <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="check-check" class="w-5 h-5 text-amber-600"></i>
                            <span>Pending Bills</span>
                        </h3>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Select days of pending bills to approve</p>
                    </div>
                    <button type="button" @click="closePendingBillsModal()" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 flex-1 overflow-y-auto space-y-3">
                    <template x-if="isLoadingPendingDays">
                        <div class="py-12 text-center space-y-3">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-amber-600 border-t-transparent"></div>
                            <p class="text-xs font-bold text-slate-600">Loading pending bills by day...</p>
                        </div>
                    </template>

                    <template x-if="!isLoadingPendingDays && pendingDays.length > 0">
                        <div class="space-y-3">
                            <template x-for="day in pendingDays" :key="day.date">
                                <div class="rounded-2xl border border-slate-200 bg-white p-3.5 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                               :value="day.date"
                                               x-model="selectedDays"
                                               class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500 cursor-pointer">
                                        <div>
                                            <span class="font-black text-slate-900 block" x-text="day.formatted_date"></span>
                                            <span class="text-xs text-slate-500 font-bold" x-text="day.bill_count + ' Pending Bills • ' + day.total_qty + ' KG'"></span>
                                        </div>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-black" x-text="day.bill_count + ' Bills'"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <span class="text-xs font-bold text-slate-600" x-text="'Selected: ' + selectedDays.length + ' Days'"></span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="closePendingBillsModal()" class="px-4 py-2 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-200 transition">
                            Cancel
                        </button>
                        <button type="button"
                                @click="submitAcceptBills()"
                                :disabled="isAcceptingBills || selectedDays.length === 0"
                                class="px-5 py-2 rounded-xl text-xs font-black bg-amber-600 hover:bg-amber-700 text-white disabled:opacity-50 transition shadow-sm cursor-pointer">
                            <span x-show="!isAcceptingBills">Accept Selected Bills</span>
                            <span x-show="isAcceptingBills">Approving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. MANUAL MATCH MODAL -->
        <div x-show="manualMatchModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
            <div @click.away="closeManualMatch()"
                 class="relative w-full max-w-3xl bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                            <i data-lucide="link" class="w-5 h-5 text-emerald-600"></i>
                            <span x-text="'Match Bill: ' + (manualPoNumber || '')"></span>
                        </h3>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5" x-text="manualSupplierName || ''"></p>
                    </div>
                    <button type="button" @click="closeManualMatch()" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 flex-1 overflow-y-auto space-y-4">
                    <template x-if="isLoadingManualMatch">
                        <div class="py-12 text-center space-y-3">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-emerald-600 border-t-transparent"></div>
                            <p class="text-xs font-bold text-slate-600">Loading advance match candidates...</p>
                        </div>
                    </template>

                    <template x-if="!isLoadingManualMatch && manualSuggestions">
                        <div class="space-y-4">
                            <template x-for="item in (manualSuggestions.items || [])" :key="item.product_id">
                                <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="font-black text-slate-900" x-text="item.product_name"></span>
                                        <span class="text-xs font-mono font-bold text-slate-600" x-text="'Bill Qty: ' + item.ordered_qty + ' ' + item.unit"></span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 text-xs">
                                        <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                                            <span class="text-[10px] text-slate-400 font-bold block">Advance Available</span>
                                            <span class="font-mono font-black text-purple-800" x-text="item.total_advance_available_qty + ' ' + item.unit"></span>
                                        </div>
                                        <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                                            <span class="text-[10px] text-slate-400 font-bold block">Proposed Match</span>
                                            <span class="font-mono font-black text-emerald-800" x-text="item.total_proposed_match_qty + ' ' + item.unit"></span>
                                        </div>
                                        <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                                            <span class="text-[10px] text-slate-400 font-bold block">New Physical Receive</span>
                                            <span class="font-mono font-black text-slate-800" x-text="item.new_receive_qty + ' ' + item.unit"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-2 bg-slate-50/50">
                    <button type="button" @click="closeManualMatch()" class="px-4 py-2 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-200 transition">
                        Cancel
                    </button>
                    <button type="button"
                            @click="executeManualMatch()"
                            :disabled="isExecutingManualMatch || !manualSuggestions"
                            class="px-5 py-2 rounded-xl text-xs font-black bg-slate-900 hover:bg-slate-800 text-white disabled:opacity-50 transition shadow-sm cursor-pointer">
                        <span x-show="!isExecutingManualMatch">Confirm Match</span>
                        <span x-show="isExecutingManualMatch">Matching...</span>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- Alpine.js Component Logic -->
    <script>
        function inventoryActionCenter(config) {
            return {
                csrfToken: config.csrfToken,
                currentTab: config.currentTab,
                currentDate: config.currentDate,
                currentWarehouseId: config.currentWarehouseId,
                currentWarehouseName: config.currentWarehouseName,
                currentSearch: config.currentSearch,

                // Auto Match Modal State
                autoClearModalOpen: false,
                isLoadingAutoClear: false,
                isExecutingAutoClear: false,
                autoClearPlan: null,

                // Accept Pending Bills Modal State
                pendingBillsModalOpen: false,
                isLoadingPendingDays: false,
                isAcceptingBills: false,
                pendingDays: [],
                selectedDays: [],

                // Manual Match Modal State
                manualMatchModalOpen: false,
                isLoadingManualMatch: false,
                isExecutingManualMatch: false,
                manualPoId: null,
                manualPoNumber: '',
                manualSupplierName: '',
                manualSuggestions: null,

                // Multi-select for Current Inventory
                selectedCurrentProducts: [],
                currentPageProducts: config.currentPageProducts || [],
                toggleSelectAllCurrent(event) {
                    if (event.target.checked) {
                        this.selectedCurrentProducts = JSON.parse(JSON.stringify(this.currentPageProducts.filter(p => parseFloat(p.available_qty || 0) > 0)));
                    } else {
                        this.selectedCurrentProducts = [];
                    }
                },
                isAllCurrentSelected() {
                    const selectable = this.currentPageProducts.filter(p => parseFloat(p.available_qty || 0) > 0);
                    return selectable.length > 0 && this.selectedCurrentProducts.length === selectable.length;
                },
                isCurrentSelected(productId) {
                    return this.selectedCurrentProducts.some(p => p.product_id == productId);
                },
                toggleCurrentProduct(product, event) {
                    if (event.target.checked) {
                        if (parseFloat(product.available_qty || 0) <= 0) {
                            event.target.checked = false;
                            return;
                        }
                        if (!this.isCurrentSelected(product.product_id)) {
                            const avail = parseFloat(product.available_qty || 0);
                            this.selectedCurrentProducts.push({
                                product_id: product.product_id,
                                name: product.name,
                                sku: product.sku,
                                unit: product.unit,
                                available_qty: avail,
                                quantity: avail > 0 ? avail : 0.01,
                                reason: this.damageCommonReason,
                                grade: 'U'
                            });
                        }
                    } else {
                        this.selectedCurrentProducts = this.selectedCurrentProducts.filter(p => p.product_id != product.product_id);
                    }
                },

                // Multi-select for Shop Returns
                selectedReturnProducts: [],
                currentPageReturns: config.currentPageReturns || [],
                toggleSelectAllReturns(event) {
                    if (event.target.checked) {
                        this.selectedReturnProducts = JSON.parse(JSON.stringify(this.currentPageReturns.filter(p => parseFloat(p.available_qty || 0) > 0)));
                    } else {
                        this.selectedReturnProducts = [];
                    }
                },
                isAllReturnsSelected() {
                    const selectable = this.currentPageReturns.filter(p => parseFloat(p.available_qty || 0) > 0);
                    return selectable.length > 0 && this.selectedReturnProducts.length === selectable.length;
                },
                isReturnSelected(productId) {
                    return this.selectedReturnProducts.some(p => p.product_id == productId);
                },
                toggleReturnProduct(ret, event) {
                    if (event.target.checked) {
                        if (parseFloat(ret.available_qty || 0) <= 0) {
                            event.target.checked = false;
                            return;
                        }
                        if (!this.isReturnSelected(ret.product_id)) {
                            const avail = parseFloat(ret.available_qty || 0);
                            this.selectedReturnProducts.push({
                                product_id: ret.product_id,
                                name: ret.name,
                                sku: ret.sku,
                                unit: ret.unit,
                                available_qty: avail,
                                quantity: avail > 0 ? avail : 0.01,
                                reason: this.damageCommonReason,
                                grade: 'U'
                            });
                        }
                    } else {
                        this.selectedReturnProducts = this.selectedReturnProducts.filter(p => p.product_id != ret.product_id);
                    }
                },

                // Multi-select & State for Advance GRN Manual Clear (Phase 3)
                selectedClearAdvanceIds: [],
                currentPageAdvances: config.currentPageAdvances || [],
                unbilledAdvGrns: config.unbilledAdvGrns || [],
                clearAdvancesModalOpen: false,
                clearAdvancesReasonChoice: 'Bill not required',
                clearAdvancesCustomReason: '',
                clearAdvancesNotes: '',
                isSubmittingClearAdvances: false,

                toggleAdvanceSelection(grnId) {
                    const id = parseInt(grnId, 10);
                    if (this.selectedClearAdvanceIds.includes(id)) {
                        this.selectedClearAdvanceIds = this.selectedClearAdvanceIds.filter(i => i !== id);
                    } else {
                        this.selectedClearAdvanceIds.push(id);
                    }
                },

                isAdvanceSelected(grnId) {
                    return this.selectedClearAdvanceIds.includes(parseInt(grnId, 10));
                },

                toggleSelectAllAdvances(event) {
                    if (event.target.checked) {
                        const pageIds = (this.currentPageAdvances || []).map(g => parseInt(g.id, 10));
                        this.selectedClearAdvanceIds = Array.from(new Set([...this.selectedClearAdvanceIds, ...pageIds]));
                    } else {
                        const pageIds = (this.currentPageAdvances || []).map(g => parseInt(g.id, 10));
                        this.selectedClearAdvanceIds = this.selectedClearAdvanceIds.filter(id => !pageIds.includes(id));
                    }
                },

                isAllAdvancesSelected() {
                    const pageIds = (this.currentPageAdvances || []).map(g => parseInt(g.id, 10));
                    return pageIds.length > 0 && pageIds.every(id => this.selectedClearAdvanceIds.includes(id));
                },

                getSelectedAdvancesList() {
                    const allGrns = this.unbilledAdvGrns || [];
                    return allGrns.filter(g => this.selectedClearAdvanceIds.includes(parseInt(g.id, 10)));
                },

                getSelectedAdvancesTotalQty() {
                    const list = this.getSelectedAdvancesList();
                    return list.reduce((acc, g) => acc + (parseFloat(g.total_missing_qty) || 0), 0);
                },

                getEffectiveClearReason() {
                    if (this.clearAdvancesReasonChoice === 'Other') {
                        const custom = (this.clearAdvancesCustomReason || '').trim();
                        const notes = (this.clearAdvancesNotes || '').trim();
                        if (!custom) return '';
                        return notes ? `${custom} (${notes})` : custom;
                    }
                    const base = this.clearAdvancesReasonChoice;
                    const notes = (this.clearAdvancesNotes || '').trim();
                    return notes ? `${base} (${notes})` : base;
                },

                openClearAdvancesModal() {
                    if (this.selectedClearAdvanceIds.length === 0) return;
                    this.clearAdvancesModalOpen = true;
                },

                openClearSingleAdvance(grnId) {
                    const id = parseInt(grnId, 10);
                    this.selectedClearAdvanceIds = [id];
                    this.clearAdvancesModalOpen = true;
                },

                closeClearAdvancesModal() {
                    this.clearAdvancesModalOpen = false;
                },

                // Move to Damage Modal State
                damageModalOpen: false,
                damageModalItems: [],
                damageCommonReason: 'transit_damage',
                damageDate: config?.currentDate || '{{ $selectedDate }}',
                damageNotes: '',
                selectedAddProductId: '',
                allAvailableProducts: config?.damageProducts || [],

                // Share Missing Bills Modal State
                shareMissingBillsModalOpen: false,
                shareReportText: '',
                copiedShareReport: false,
                unbilledRows: config?.unbilledRows || [],

                openDamageModalForSelection(source = 'current') {
                    if (source === 'current') {
                        this.damageModalItems = JSON.parse(JSON.stringify(this.selectedCurrentProducts));
                    } else if (source === 'returns') {
                        this.damageModalItems = JSON.parse(JSON.stringify(this.selectedReturnProducts));
                    }
                    this.damageModalOpen = true;
                },

                openDamageModalSingle(productId, productName, unit, availableQty, sku = '') {
                    const avail = parseFloat(availableQty || 0);
                    if (avail <= 0) return;
                    this.damageModalItems = [{
                        product_id: productId,
                        name: productName,
                        sku: sku,
                        unit: unit || 'KG',
                        available_qty: avail,
                        quantity: avail > 0 ? avail : 0.01,
                        reason: this.damageCommonReason,
                        grade: 'U'
                    }];
                    this.damageModalOpen = true;
                },

                openDamageModal() {
                    if (this.selectedCurrentProducts.length > 0) {
                        this.openDamageModalForSelection('current');
                    } else if (this.selectedReturnProducts.length > 0) {
                        this.openDamageModalForSelection('returns');
                    } else {
                        this.damageModalItems = [];
                        this.damageModalOpen = true;
                    }
                },

                closeDamageModal() {
                    this.damageModalOpen = false;
                },

                addDamageProduct(productId) {
                    if (!productId) return;
                    const prod = this.allAvailableProducts.find(p => p.id == productId);
                    if (!prod) return;
                    const exists = this.damageModalItems.find(i => i.product_id == productId);
                    if (!exists) {
                        this.damageModalItems.push({
                            product_id: prod.id,
                            name: prod.name,
                            sku: prod.sku,
                            unit: prod.unit || 'KG',
                            available_qty: 0,
                            quantity: 1,
                            reason: this.damageCommonReason,
                            grade: 'U'
                        });
                    }
                },

                removeDamageItem(index) {
                    this.damageModalItems.splice(index, 1);
                },

                updateCommonReason(newReason) {
                    this.damageCommonReason = newReason;
                    this.damageModalItems.forEach(item => {
                        item.reason = newReason;
                    });
                },

                totalDamageModalQty() {
                    return this.damageModalItems.reduce((acc, item) => acc + (parseFloat(item.quantity) || 0), 0);
                },

                openShareMissingBillsModal() {
                    const lines = [];
                    lines.push(`📋 *Green Leaf ERP — Missing Bills Report*`);
                    lines.push(`🏢 Warehouse: ${this.currentWarehouseName}`);
                    lines.push(`📅 Date: ${this.currentDate}`);
                    lines.push(`----------------------------------------`);

                    let totalQty = 0;
                    if (this.unbilledRows && this.unbilledRows.length > 0) {
                        this.unbilledRows.forEach((r, idx) => {
                            const pName = r.product?.name || `Product #${r.item?.product_id}`;
                            const sku = r.product?.sku || '';
                            const unit = r.product?.unit || 'KG';
                            const missing = parseFloat(r.missing_qty || 0);
                            totalQty += missing;
                            lines.push(`${idx + 1}. *${pName}* (${sku})`);
                            lines.push(`   Missing: ${missing.toFixed(2)} ${unit} | GRN: ${r.grn_number} | Age: ${r.age_days}d`);
                        });
                    } else {
                        lines.push(`No unbilled advance stock found.`);
                    }

                    lines.push(`----------------------------------------`);
                    lines.push(`Total Missing Items: ${this.unbilledRows ? this.unbilledRows.length : 0} | Total Qty: ${totalQty.toFixed(2)} KG`);
                    lines.push(`_Please send vendor bills for the items listed above._`);

                    this.shareReportText = lines.join('\n');
                    this.copiedShareReport = false;
                    this.shareMissingBillsModalOpen = true;
                },

                closeShareMissingBillsModal() {
                    this.shareMissingBillsModalOpen = false;
                    this.copiedShareReport = false;
                },

                copyShareReport() {
                    if (!navigator.clipboard) {
                        alert('Clipboard API not supported on your browser');
                        return;
                    }
                    navigator.clipboard.writeText(this.shareReportText)
                        .then(() => {
                            this.copiedShareReport = true;
                            setTimeout(() => { this.copiedShareReport = false; }, 3000);
                        })
                        .catch(err => {
                            alert('Failed to copy text: ' + err);
                        });
                },

                openAutoClear() {
                    this.autoClearModalOpen = true;
                    this.isLoadingAutoClear = true;
                    const whId = this.currentWarehouseId || 1;
                    fetch(`${config.autoPlanUrl}?warehouse_id=${whId}`, {
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken }
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.autoClearPlan = data.data || data;
                        this.isLoadingAutoClear = false;
                    })
                    .catch(err => {
                        alert('Failed to load auto-match preview');
                        this.isLoadingAutoClear = false;
                        this.autoClearModalOpen = false;
                    });
                },

                closeAutoClear() {
                    this.autoClearModalOpen = false;
                    this.autoClearPlan = null;
                },

                executeAutoClear() {
                    if (!this.autoClearPlan || !this.autoClearPlan.plan_hash) return;
                    this.isExecutingAutoClear = true;
                    const whId = this.currentWarehouseId || 1;
                    const clientSubId = 'web-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9);
                    fetch(config.autoExecuteUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify({
                            warehouse_id: whId,
                            plan_hash: this.autoClearPlan.plan_hash,
                            client_submission_id: clientSubId
                        })
                    })
                    .then(res => res.json())
                    .then(res => {
                        this.isExecutingAutoClear = false;
                        this.closeAutoClear();
                        window.location.reload();
                    })
                    .catch(err => {
                        alert('Execution failed: ' + (err.message || 'Unknown error'));
                        this.isExecutingAutoClear = false;
                    });
                },

                openPendingBillsModal() {
                    this.pendingBillsModalOpen = true;
                    this.isLoadingPendingDays = true;
                    const url = config.pendingBillsDaysUrl + (this.currentWarehouseId ? `?warehouse_id=${this.currentWarehouseId}` : '');
                    fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken }
                    })
                    .then(res => res.json())
                    .then(res => {
                        this.pendingDays = res.data?.days || [];
                        this.selectedDays = this.pendingDays.map(d => d.date);
                        this.isLoadingPendingDays = false;
                    })
                    .catch(err => {
                        alert('Failed to load pending bill dates');
                        this.isLoadingPendingDays = false;
                        this.pendingBillsModalOpen = false;
                    });
                },

                closePendingBillsModal() {
                    this.pendingBillsModalOpen = false;
                    this.pendingDays = [];
                    this.selectedDays = [];
                },

                submitAcceptBills() {
                    if (this.selectedDays.length === 0) return;
                    this.isAcceptingBills = true;
                    fetch(config.acceptPendingBillsUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify({
                            warehouse_id: this.currentWarehouseId || null,
                            dates: this.selectedDays
                        })
                    })
                    .then(res => res.json())
                    .then(res => {
                        this.isAcceptingBills = false;
                        this.closePendingBillsModal();
                        window.location.reload();
                    })
                    .catch(err => {
                        alert('Failed to accept bills');
                        this.isAcceptingBills = false;
                    });
                },

                openManualMatch(poId, poNumber, supplierName) {
                    this.manualPoId = poId;
                    this.manualPoNumber = poNumber;
                    this.manualSupplierName = supplierName;
                    this.manualMatchModalOpen = true;
                    this.isLoadingManualMatch = true;
                    const whParam = this.currentWarehouseId ? `?warehouse_id=${this.currentWarehouseId}` : '';
                    fetch(config.manualMatchUrlPrefix + poId + whParam, {
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken }
                    })
                    .then(res => res.json())
                    .then(res => {
                        this.manualSuggestions = res.data || res;
                        this.isLoadingManualMatch = false;
                    })
                    .catch(err => {
                        alert('Failed to fetch match suggestions');
                        this.isLoadingManualMatch = false;
                        this.manualMatchModalOpen = false;
                    });
                },

                closeManualMatch() {
                    this.manualMatchModalOpen = false;
                    this.manualPoId = null;
                    this.manualSuggestions = null;
                },

                executeManualMatch() {
                    if (!this.manualPoId || !this.manualSuggestions) return;
                    this.isExecutingManualMatch = true;
                    const matches = [];
                    (this.manualSuggestions.items || []).forEach(item => {
                        (item.suggested_matches || []).forEach(m => {
                            matches.push({
                                advance_goods_received_id: m.advance_goods_received_id,
                                advance_goods_received_item_id: m.advance_goods_received_item_id,
                                purchase_order_item_id: item.purchase_order_item_id,
                                product_id: item.product_id,
                                matched_qty: m.proposed_match_qty,
                                unit: m.unit
                            });
                        });
                    });

                    fetch(config.manualExecuteUrlPrefix + this.manualPoId, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify({
                            warehouse_id: this.currentWarehouseId || null,
                            matches: matches
                        })
                    })
                    .then(res => res.json())
                    .then(res => {
                        this.isExecutingManualMatch = false;
                        this.closeManualMatch();
                        window.location.reload();
                    })
                    .catch(err => {
                        alert('Manual match execution failed');
                        this.isExecutingManualMatch = false;
                    });
                }
            };
        }
    </script>
@endsection
