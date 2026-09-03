@extends('admin.cashbook.layouts.app')

@section('title', 'Cashbook Inventory Operational Reconciliation — Green Leaf')

@section('header_title')
    <i data-lucide="boxes" class="w-5 h-5 text-emerald-600"></i> Cashbook Inventory
@endsection

@section('header_subtitle')
    Daily billed vs physical position, bill pending orders, open advances, and Loadout Without Bill reconciliation.
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6"
         x-data="inventoryReconciliation({
             csrfToken: '{{ csrf_token() }}',
             currentTab: '{{ $tab }}',
             currentDate: '{{ $selectedDate }}',
             currentWarehouseId: '{{ $selectedWarehouseId ?? '' }}',
             currentSearch: '{{ addslashes($search) }}',
             autoPlanUrl: '{{ route('admin.cashbook.inventory.auto-clear-plan') }}',
             autoExecuteUrl: '{{ route('admin.cashbook.inventory.auto-clear-execute') }}',
             manualMatchUrlPrefix: '/admin/cashbook/inventory/manual-match-suggestions/',
             manualExecuteUrlPrefix: '/admin/cashbook/inventory/manual-match/',
             resolveUnitDiffUrl: '{{ route('admin.cashbook.inventory.resolve-unit-difference') }}',
             fixAdvanceUnitsUrl: '{{ route('admin.cashbook.inventory.fix-advance-units') }}'
         })">

        <!-- Top Header & Actions Bar -->
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl flex items-center gap-2">
                        <span>Cashbook Inventory</span>
                    </h1>
                    <p class="text-xs font-bold text-slate-500 mt-0.5">Authoritative billed vs physical inventory carry-forward, bill pending orders &amp; advance clearing</p>
                </div>

                <!-- Top Right Main Action -->
                <div class="flex items-center gap-2 self-start sm:self-auto">
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
                        @if($availableWarehouses->count() > 1)
                            <form method="GET" action="{{ route('admin.cashbook.inventory') }}" class="inline-flex">
                                <input type="hidden" name="tab" value="{{ $tab }}">
                                <input type="hidden" name="date" value="{{ $selectedDate }}">
                                @if($search) <input type="hidden" name="search" value="{{ $search }}"> @endif
                                <select name="warehouse_id"
                                        onchange="this.form.submit()"
                                        class="px-3 py-1.5 text-xs font-black rounded-xl bg-slate-100 border border-slate-200 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer">
                                    <option value="">All Warehouses</option>
                                    @foreach($availableWarehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ (string) $selectedWarehouseId === (string) $wh->id ? 'selected' : '' }}>
                                            {{ $wh->name }} ({{ $wh->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @elseif($availableWarehouses->isNotEmpty())
                            <div class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-black border border-slate-200/80">
                                <i data-lucide="warehouse" class="w-3.5 h-3.5 text-slate-500"></i>
                                <span>{{ $availableWarehouses->first()->name }}</span>
                            </div>
                        @endif

                        <!-- Day Navigation: Previous / Today / Date / Next -->
                        <div class="inline-flex items-center gap-1 rounded-2xl bg-slate-100 p-1">
                            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => $tab, 'date' => $prevDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search])) }}"
                               title="Previous Day ({{ $prevDate }})"
                               class="inline-flex items-center justify-center w-7 h-7 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-white transition">
                                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            </a>

                            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => $tab, 'date' => today()->toDateString(), 'warehouse_id' => $selectedWarehouseId, 'search' => $search])) }}"
                               class="rounded-xl px-2.5 py-1 text-xs font-black transition-all {{ $isToday ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                                Today
                            </a>

                            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => $tab, 'date' => $nextDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search])) }}"
                               title="Next Day ({{ $nextDate }})"
                               class="inline-flex items-center justify-center w-7 h-7 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-white transition">
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </a>
                        </div>

                        <!-- Datepicker Input -->
                        <form method="GET" action="{{ route('admin.cashbook.inventory') }}" class="inline-flex items-center">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            @if($selectedWarehouseId) <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}"> @endif
                            @if($search) <input type="hidden" name="search" value="{{ $search }}"> @endif

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
                        @if($selectedWarehouseId) <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}"> @endif

                        <div class="relative flex-1 sm:w-64">
                            <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search product, SKU, PO, GRN..."
                                   class="w-full pl-8 pr-3 py-1.5 text-xs font-medium rounded-xl bg-slate-50 border border-slate-200 shadow-2xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        </div>

                        <button type="submit" class="px-3.5 py-1.5 text-xs font-black rounded-xl bg-slate-900 text-white shadow-xs hover:bg-slate-800 transition-all cursor-pointer">
                            Filter
                        </button>

                        @if($search)
                            <a href="{{ route('admin.cashbook.inventory', ['tab' => $tab, 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId]) }}"
                               title="Clear search"
                               class="p-1.5 text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </a>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <!-- 6 Summary Cards Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <!-- Products With Excess -->
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Products Excess</span>
                    <h3 class="text-2xl font-black text-emerald-950 mt-0.5">{{ $summary['excess_count'] }}</h3>
                    <p class="text-[11px] font-bold text-emerald-700 mt-0.5">Unbilled physical stock</p>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="trending-up" class="w-5 h-5"></i>
                </div>
            </div>

            <!-- Products Short -->
            <div class="rounded-3xl border border-rose-200 bg-rose-50/50 p-4 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-800">Products Short</span>
                    <h3 class="text-2xl font-black text-rose-950 mt-0.5">{{ $summary['short_count'] }}</h3>
                    <p class="text-[11px] font-bold text-rose-700 mt-0.5">Billed &gt; physical stock</p>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-800 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="trending-down" class="w-5 h-5"></i>
                </div>
            </div>

            <!-- Balanced -->
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Balanced</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-0.5">{{ $summary['balanced_count'] }}</h3>
                    <p class="text-[11px] font-bold text-slate-500 mt-0.5">Billed = physical intake</p>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-slate-100 text-slate-700 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                </div>
            </div>

            <!-- Pending Bills -->
            <div class="rounded-3xl border border-amber-200 bg-amber-50/50 p-4 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-800">Pending Bills</span>
                    <h3 class="text-2xl font-black text-amber-950 mt-0.5">{{ $summary['pending_bills_count'] }}</h3>
                    <p class="text-[11px] font-bold text-amber-700 mt-0.5">Awaiting match/receive</p>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="file-clock" class="w-5 h-5"></i>
                </div>
            </div>

            <!-- Open Advances -->
            <div class="rounded-3xl border border-purple-200 bg-purple-50/50 p-4 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-purple-800">Open Advances</span>
                    <h3 class="text-2xl font-black text-purple-950 mt-0.5">{{ $summary['open_advances_count'] }}</h3>
                    <p class="text-[11px] font-bold text-purple-700 mt-0.5">Available for auto-clear</p>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-purple-100 text-purple-800 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
            </div>

            <!-- Unit Differences -->
            <div class="rounded-3xl border border-sky-200 bg-sky-50/50 p-4 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-sky-800">Unit Differences</span>
                    <h3 class="text-2xl font-black text-sky-950 mt-0.5">{{ $summary['unit_differences_count'] ?? 0 }}</h3>
                    <p class="text-[11px] font-bold text-sky-700 mt-0.5">Requires manual review</p>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-sky-100 text-sky-800 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="scale" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <!-- Section Navigation Tabs -->
        <div class="flex flex-wrap gap-1 rounded-2xl bg-slate-100 p-1 shadow-inner self-start">
            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'daily_inventory', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search])) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $tab === 'daily_inventory' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="table" class="w-3.5 h-3.5 {{ $tab === 'daily_inventory' ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Daily Inventory</span>
                <span class="px-1.5 py-0.5 text-[10px] rounded-md font-bold {{ $tab === 'daily_inventory' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                    {{ $summary['total_products'] }}
                </span>
            </a>

            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'pending_bills', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search])) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ in_array($tab, ['pending_bills', 'bill_pending'], true) ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="file-text" class="w-3.5 h-3.5 {{ in_array($tab, ['pending_bills', 'bill_pending'], true) ? 'text-amber-600' : 'text-slate-400' }}"></i>
                <span>Pending Bills (Bill Pending)</span>
                @if($summary['pending_bills_count'] > 0)
                    <span class="px-1.5 py-0.5 text-[10px] rounded-md font-bold bg-amber-100 text-amber-800">
                        {{ $summary['pending_bills_count'] }}
                    </span>
                @endif
            </a>

            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'advance_bills', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search])) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $tab === 'advance_bills' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="receipt" class="w-3.5 h-3.5 {{ $tab === 'advance_bills' ? 'text-purple-600' : 'text-slate-400' }}"></i>
                <span>Advance Bills</span>
                @if($summary['open_advances_count'] > 0)
                    <span class="px-1.5 py-0.5 text-[10px] rounded-md font-bold bg-purple-100 text-purple-800">
                        {{ $summary['open_advances_count'] }}
                    </span>
                @endif
            </a>

            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'unit_differences', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search])) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $tab === 'unit_differences' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="scale" class="w-3.5 h-3.5 {{ $tab === 'unit_differences' ? 'text-sky-600' : 'text-slate-400' }}"></i>
                <span>Unit Differences</span>
                @if(($summary['unit_differences_count'] ?? 0) > 0)
                    <span class="px-1.5 py-0.5 text-[10px] rounded-md font-bold bg-sky-100 text-sky-800">
                        {{ $summary['unit_differences_count'] }}
                    </span>
                @endif
            </a>

            <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'invoice_adjustments', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId, 'search' => $search])) }}"
               class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ in_array($tab, ['invoice_adjustments', 'bill_notes'], true) ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 {{ in_array($tab, ['invoice_adjustments', 'bill_notes'], true) ? 'text-blue-600' : 'text-slate-400' }}"></i>
                <span>Invoice Adjustments</span>
            </a>
        </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 1: DAILY INVENTORY POSITION TABLE                              -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @if($tab === 'daily_inventory')
            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="scale" class="w-4 h-4 text-emerald-600"></i>
                            <span>Daily Billed vs Physical Position</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">
                            As of <strong class="text-slate-800">{{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</strong> (includes historical carry-forward)
                        </p>
                    </div>
                    @if($dailyInventory)
                        <span class="text-xs font-bold text-slate-400 self-start sm:self-auto">
                            Showing {{ $dailyInventory->firstItem() ?? 0 }}–{{ $dailyInventory->lastItem() ?? 0 }} of {{ $dailyInventory->total() }} products
                        </span>
                    @endif
                </div>

                @if(!$dailyInventory || $dailyInventory->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="inbox" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No Product Records Found</h3>
                        <p class="text-xs text-slate-500 mt-1">There are no stock intakes or billed reconciliations recorded on or prior to this date.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5">Product</th>
                                    <th class="p-3.5">SKU</th>
                                    <th class="p-3.5 text-right">Billed (Base)</th>
                                    <th class="p-3.5 text-right">Physical Intake</th>
                                    <th class="p-3.5 text-right">Advance / Difference</th>
                                    <th class="p-3.5 text-center pr-5">Reconciliation Position</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($dailyInventory as $row)
                                    @php
                                        $diffType = $row['difference_type'] ?? 'balanced';
                                        $diffQty = (float) ($row['difference_base_qty'] ?? 0.0);
                                        $unit = $row['base_unit'] ?? 'KG';
                                        $diffFormatted = match($diffType) {
                                            'excess' => '+' . number_format($diffQty, 2) . ' ' . $unit,
                                            'short' => number_format($diffQty, 2) . ' ' . $unit,
                                            default => '0.00 ' . $unit,
                                        };
                                        $diffBadgeClass = match($diffType) {
                                            'excess' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'short' => 'bg-rose-50 text-rose-800 border-rose-200',
                                            default => 'bg-slate-50 text-slate-700 border-slate-200',
                                        };
                                        $diffLabel = match($diffType) {
                                            'excess' => 'EXCESS / UNBILLED',
                                            'short' => 'SHORTAGE',
                                            default => 'BALANCED',
                                        };
                                    @endphp
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="p-3.5 pl-5 font-black text-slate-900">
                                            {{ $row['product_name'] }}
                                        </td>
                                        <td class="p-3.5 font-mono font-bold text-slate-500 text-[11px]">
                                            {{ $row['product_sku'] ?: '—' }}
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-700">
                                            {{ number_format((float) $row['billed_base_qty'], 2) }} <span class="text-[10px] text-slate-400">{{ $unit }}</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-900">
                                            {{ number_format((float) $row['physical_base_qty'], 2) }} <span class="text-[10px] text-slate-400">{{ $unit }}</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-black text-sm {{ $diffType === 'excess' ? 'text-emerald-700' : ($diffType === 'short' ? 'text-rose-700' : 'text-slate-500') }}">
                                            {{ $diffFormatted }}
                                        </td>
                                        <td class="p-3.5 text-center pr-5">
                                            <span class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[10px] font-black uppercase border {{ $diffBadgeClass }}">
                                                @if($diffType === 'excess')
                                                    <i data-lucide="plus-circle" class="w-3 h-3 text-emerald-600"></i>
                                                @elseif($diffType === 'short')
                                                    <i data-lucide="minus-circle" class="w-3 h-3 text-rose-600"></i>
                                                @else
                                                    <i data-lucide="check" class="w-3 h-3 text-slate-500"></i>
                                                @endif
                                                <span>{{ $diffLabel }}</span>
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-slate-100">
                        {{ $dailyInventory->links() }}
                    </div>
                @endif
            </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 2: PENDING BILLS TABLE                                         -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @elseif($tab === 'pending_bills')
            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="file-clock" class="w-4 h-4 text-amber-600"></i>
                            <span>Pending Purchase Bills</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Authoritative purchasing orders awaiting advance reconciliation or warehouse receiving</p>
                    </div>
                    @if($pendingBills)
                        <span class="text-xs font-bold text-slate-400 self-start sm:self-auto">
                            Showing {{ $pendingBills->firstItem() ?? 0 }}–{{ $pendingBills->lastItem() ?? 0 }} of {{ $pendingBills->total() }} pending bills
                        </span>
                    @endif
                </div>

                @if(!$pendingBills || $pendingBills->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">All Purchase Bills Reconciled</h3>
                        <p class="text-xs text-slate-500 mt-1">There are no pending purchase orders requiring match or warehouse receiving.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5">PO Number</th>
                                    <th class="p-3.5">Date</th>
                                    <th class="p-3.5">Supplier</th>
                                    <th class="p-3.5">Products / Items</th>
                                    <th class="p-3.5 text-right">Bill Qty (Base)</th>
                                    <th class="p-3.5 text-right">Remaining Qty</th>
                                    <th class="p-3.5 text-center">Match Status</th>
                                    <th class="p-3.5 text-center pr-5">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($pendingBills as $row)
                                    @php
                                        $poId = $row['id'] ?? $row['purchase_order_id'];
                                        $billBase = (float) ($row['total_bill_base_qty'] ?? 0.0);
                                        $matchedBase = (float) ($row['total_matched_base_qty'] ?? 0.0);
                                        $remainingBase = max(0.0, round($billBase - $matchedBase, 2));
                                        $statusBadge = match($row['reconciliation_status'] ?? 'unmatched') {
                                            'exact' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'partial' => 'bg-amber-50 text-amber-800 border-amber-200',
                                            default => 'bg-slate-100 text-slate-700 border-slate-200',
                                        };
                                        $statusLabel = match($row['reconciliation_status'] ?? 'unmatched') {
                                            'exact' => 'EXACT MATCH READY',
                                            'partial' => 'PARTIAL ADVANCE',
                                            default => 'UNMATCHED',
                                        };
                                    @endphp
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="p-3.5 pl-5 font-mono font-black text-slate-900">
                                            <div>{{ $row['po_number'] }}</div>
                                            @if(!empty($row['goods_received_numbers']))
                                                @foreach($row['goods_received_numbers'] as $grnNum)
                                                    <span class="text-[10px] text-slate-500 font-semibold block font-mono">{{ $grnNum }}</span>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-600 whitespace-nowrap">
                                            {{ $row['order_date'] ? \Carbon\Carbon::parse($row['order_date'])->format('d M Y') : '—' }}
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-800">
                                            {{ $row['supplier_name'] }}
                                        </td>
                                        <td class="p-3.5">
                                            <div class="flex flex-wrap gap-1 max-w-xs">
                                                @if(!empty($row['match_summary_items']))
                                                    @foreach(array_slice($row['match_summary_items'], 0, 3) as $mItem)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-[10px] font-bold text-slate-700">
                                                            {{ $mItem['product_name'] }} ({{ $mItem['ordered_qty'] }} {{ $mItem['unit'] }})
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
                                        <td class="p-3.5 text-right font-mono font-black text-slate-900">
                                            {{ number_format($remainingBase, 2) }} <span class="text-[10px] text-slate-400">KG</span>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-[10px] font-black uppercase border {{ $statusBadge }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-center pr-5 whitespace-nowrap space-x-1">
                                            <button type="button"
                                                    @click="openManualMatch({{ $poId }}, '{{ $row['po_number'] }}', '{{ addslashes($row['supplier_name']) }}')"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-900 text-white text-[11px] font-black hover:bg-slate-800 transition shadow-2xs cursor-pointer">
                                                <i data-lucide="link" class="w-3 h-3 text-emerald-400"></i>
                                                <span>Match</span>
                                            </button>

                                            @if(!empty($row['pending_receive_url']))
                                                <a href="{{ $row['pending_receive_url'] }}"
                                                   target="_blank"
                                                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-black hover:bg-amber-100 transition">
                                                    <i data-lucide="truck" class="w-3 h-3"></i>
                                                    <span>Receive</span>
                                                </a>
                                            @endif
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
        <!-- TAB 3: ADVANCE BILLS TABLE                                         -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @elseif($tab === 'advance_bills')
            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="receipt" class="w-4 h-4 text-purple-600"></i>
                            <span>Warehouse Advance Receipts (GRNs)</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">
                            Individual advance physical receipts. Cleared advances are retained operationally for 3 days.
                        </p>
                    </div>
                    @if($advanceBills)
                        <span class="text-xs font-bold text-slate-400 self-start sm:self-auto">
                            Showing {{ $advanceBills->firstItem() ?? 0 }}–{{ $advanceBills->lastItem() ?? 0 }} of {{ $advanceBills->total() }} advance GRNs
                        </span>
                    @endif
                </div>

                @if(!$advanceBills || $advanceBills->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="layers" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No Open Advance GRNs</h3>
                        <p class="text-xs text-slate-500 mt-1">There are no pending or recently cleared warehouse advance receipts.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5">GRN Number</th>
                                    <th class="p-3.5">Received Date</th>
                                    <th class="p-3.5">Warehouse</th>
                                    <th class="p-3.5">Products Received</th>
                                    <th class="p-3.5 text-right">Received (Base)</th>
                                    <th class="p-3.5 text-right">Matched (Base)</th>
                                    <th class="p-3.5 text-right">Pending (Base)</th>
                                    <th class="p-3.5 text-center pr-5">Bill Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($advanceBills as $grn)
                                    @php
                                        $recBase = $grn->received_base_qty;
                                        $matchBase = $grn->bill_matched_base_qty;
                                        $unbilledBase = $grn->unbilled_base_qty;
                                        $isCleared = ($unbilledBase <= 0.0001) || ($grn->bill_status === 'bill_available');
                                    @endphp
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="p-3.5 pl-5 font-mono font-black text-slate-900">
                                            {{ $grn->grn_number }}
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-600 whitespace-nowrap">
                                            {{ $grn->received_at?->format('d M Y') ?? '—' }}
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-700">
                                            {{ $grn->warehouse?->name ?? $grn->destinationShop?->name ?? 'Central Warehouse' }}
                                        </td>
                                        <td class="p-3.5">
                                            <div class="flex flex-wrap gap-1 max-w-xs">
                                                @foreach($grn->items as $gItem)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-[10px] font-bold text-slate-800">
                                                        {{ $gItem->product?->name ?? 'Product' }} ({{ (float) $gItem->received_qty }} {{ $gItem->received_unit }})
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-900">
                                            {{ number_format($recBase, 2) }} <span class="text-[10px] text-slate-400">KG</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-purple-700">
                                            {{ number_format($matchBase, 2) }} <span class="text-[10px] text-slate-400">KG</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-black text-sm {{ $unbilledBase > 0.0001 ? 'text-amber-700' : 'text-slate-400' }}">
                                            {{ number_format($unbilledBase, 2) }} <span class="text-[10px] text-slate-400">KG</span>
                                        </td>
                                        <td class="p-3.5 text-center pr-5">
                                            @if(!$isCleared)
                                                <span class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-0.5 text-[10px] font-black text-amber-800 border border-amber-200">
                                                    <i data-lucide="clock" class="w-3 h-3 text-amber-600"></i>
                                                    <span>BILL PENDING</span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-800 border border-emerald-200">
                                                    <i data-lucide="check-check" class="w-3 h-3 text-emerald-600"></i>
                                                    <span>CLEARED (3-DAY)</span>
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-slate-100">
                        {{ $advanceBills->links() }}
                    </div>
                @endif
            </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 4: UNIT DIFFERENCES (MANUAL RESOLUTION)                        -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @elseif($tab === 'unit_differences')
            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="scale" class="w-4 h-4 text-sky-600"></i>
                            <span>Unit Difference Exceptions</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">
                            Pending bill lines where advance stock exists, but units differ and require manual interpretation
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button"
                                id="btn-fix-advance-units"
                                @click="fixAdvanceUnits()"
                                :disabled="executingFixAdvanceUnits"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white px-3.5 py-2 text-xs font-black shadow-xs transition-all cursor-pointer">
                            <i data-lucide="wrench" class="w-3.5 h-3.5"></i>
                            <span x-text="executingFixAdvanceUnits ? 'Fixing...' : 'Fix Advance Units'">Fix Advance Units</span>
                        </button>
                        @if($unitDifferences)
                            <span class="text-xs font-bold text-slate-400 self-start sm:self-auto">
                                Showing {{ $unitDifferences->firstItem() ?? 0 }}–{{ $unitDifferences->lastItem() ?? 0 }} of {{ $unitDifferences->total() }} lines
                            </span>
                        @endif
                    </div>
                </div>

                @if(!$unitDifferences || $unitDifferences->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No Unit Differences</h3>
                        <p class="text-xs text-slate-500 mt-1">All pending bills have compatible units or have been fully reconciled.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5">PO</th>
                                    <th class="p-3.5">Product</th>
                                    <th class="p-3.5 text-right">Bill Qty</th>
                                    <th class="p-3.5">Bill Unit</th>
                                    <th class="p-3.5 text-right">Avail Advance Qty</th>
                                    <th class="p-3.5">Advance Unit</th>
                                    <th class="p-3.5 text-right">Already Matched</th>
                                    <th class="p-3.5 text-right">Remaining</th>
                                    <th class="p-3.5">Reason</th>
                                    <th class="p-3.5 text-center pr-5">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($unitDifferences as $row)
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="p-3.5 pl-5">
                                            <div class="font-mono font-black text-slate-900">{{ $row['po_number'] }}</div>
                                            <div class="text-[10px] text-slate-400 font-semibold">{{ $row['order_date'] }} · {{ $row['supplier_name'] }}</div>
                                        </td>
                                        <td class="p-3.5">
                                            <div class="font-bold text-slate-900">{{ $row['product_name'] }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono">SKU: {{ $row['product_sku'] }} · Base: {{ $row['product_base_unit'] }}</div>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-900">
                                            {{ number_format($row['bill_qty'], 2) }}
                                        </td>
                                        <td class="p-3.5 font-bold text-amber-700 uppercase">
                                            {{ $row['bill_unit'] }}
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-purple-700">
                                            {{ number_format($row['available_advance_qty'], 2) }}
                                        </td>
                                        <td class="p-3.5 font-bold text-purple-700 uppercase">
                                            {{ implode(', ', $row['advance_units']) }}
                                        </td>
                                        <td class="p-3.5 text-right font-mono text-slate-500">
                                            {{ number_format($row['already_matched_qty'], 2) }}
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-black text-amber-900">
                                            {{ number_format($row['remaining_bill_qty'], 2) }}
                                        </td>
                                        <td class="p-3.5 max-w-xs">
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                {{ $row['reason'] }}
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-center pr-5">
                                            <button type="button"
                                                    @click="openResolveUnitDiff(@js($row))"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-black rounded-xl bg-sky-600 text-white hover:bg-sky-500 shadow-xs transition-all cursor-pointer">
                                                <i data-lucide="check-square" class="w-3.5 h-3.5"></i>
                                                <span>Resolve</span>
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

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- TAB 5: INVOICE ADJUSTMENTS (AUDIT TAB)                             -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        @else
            @if($summaryAdjustments)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div class="rounded-3xl border border-slate-200/90 bg-white p-4 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Adjustments</span>
                            <h3 class="text-2xl font-black text-slate-900 mt-0.5">{{ $summaryAdjustments['total_adjustments'] }}</h3>
                            <p class="text-[11px] font-semibold text-slate-500 mt-0.5">Recorded on {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-slate-100 text-slate-700 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="file-sliders" class="w-5 h-5"></i>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-emerald-100 bg-emerald-50/40 p-4 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-800">Returned to Warehouse</span>
                            <h3 class="text-2xl font-black text-emerald-900 mt-0.5">
                                {{ rtrim(rtrim(number_format($summaryAdjustments['returned_to_warehouse_qty'], 2), '0'), '.') }} <span class="text-xs font-bold text-emerald-700">Units</span>
                            </h3>
                            <p class="text-[11px] font-semibold text-emerald-700/90 mt-0.5">{{ $summaryAdjustments['returned_to_warehouse_count'] }} item(s) restored via SaleReversal</p>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-rose-100 bg-rose-50/40 p-4 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-800">Recorded as Wastage</span>
                            <h3 class="text-2xl font-black text-rose-900 mt-0.5">
                                {{ rtrim(rtrim(number_format($summaryAdjustments['wastage_qty'], 2), '0'), '.') }} <span class="text-xs font-bold text-rose-700">Units</span>
                            </h3>
                            <p class="text-[11px] font-semibold text-rose-700/90 mt-0.5">{{ $summaryAdjustments['wastage_count'] }} item(s) written off as damaged</p>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-800 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                        </div>
                    </div>
                </div>
            @endif

            <div class="rounded-3xl border border-slate-200/90 bg-white shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="sliders-horizontal" class="w-4 h-4 text-blue-600"></i>
                            <span>Finalized Delivery Adjustments</span>
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Audited delivery review outcomes and invoice adjustments</p>
                    </div>
                </div>

                @if(!$adjustedInvoices || $adjustedInvoices->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle" class="w-6 h-6 text-emerald-500"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No Delivery Adjustments</h3>
                        <p class="text-xs text-slate-500 mt-1">No admin corrections or delivery discrepancies recorded for this date.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-3.5 pl-5">Date</th>
                                    <th class="p-3.5">Shop</th>
                                    <th class="p-3.5">Invoice #</th>
                                    <th class="p-3.5 text-right pr-5">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach($adjustedInvoices as $inv)
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="p-3.5 pl-5 font-bold text-slate-600">
                                            {{ $inv->business_date?->format('d M Y') ?? '—' }}
                                        </td>
                                        <td class="p-3.5 font-black text-slate-900">
                                            {{ $inv->shop?->name ?? 'Shop' }}
                                        </td>
                                        <td class="p-3.5 font-mono font-bold text-emerald-700">
                                            {{ $inv->invoice_number }}
                                        </td>
                                        <td class="p-3.5 text-right pr-5 font-mono font-black text-slate-900">
                                            ₹{{ number_format((float) $inv->final_total, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-slate-100">
                        {{ $adjustedInvoices->links() }}
                    </div>
                @endif
            </div>
        @endif

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- MODAL 1: AUTO MATCH & CLEAR MODAL (ALPINE.JS)                      -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        <div x-show="autoClearModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-5xl w-full flex flex-col max-h-[92vh] overflow-hidden"
                 @click.away="if(!executingAutoClear) autoClearModalOpen = false">

                <!-- Header (Sticky) -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-black shrink-0 shadow-xs">
                            <i data-lucide="sparkles" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-black text-slate-900">Auto Match &amp; Clear Bills</h3>
                                <template x-if="currentWarehouseId">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200/60">
                                        Warehouse Isolated
                                    </span>
                                </template>
                            </div>
                            <p class="text-xs text-slate-500 font-semibold">Authoritative item-level FIFO match plan generated by AutoAdvanceClearPlanningService</p>
                        </div>
                    </div>
                    <button type="button"
                            @click="autoClearModalOpen = false"
                            :disabled="executingAutoClear"
                            class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 cursor-pointer transition">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Warehouse Selection Required Notice (when !currentWarehouseId) -->
                <div x-show="!currentWarehouseId" class="p-8 text-center space-y-4 my-auto">
                    <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center mx-auto shadow-xs">
                        <i data-lucide="alert-triangle" class="w-7 h-7"></i>
                    </div>
                    <div class="space-y-1.5 max-w-md mx-auto">
                        <h4 class="text-sm font-black text-slate-900">Specific Warehouse Selection Required</h4>
                        <p class="text-xs text-slate-500 font-medium">
                            Auto Match strictly operates with single-warehouse physical isolation. Please select a specific warehouse (e.g. <strong>Vegetable Warehouse</strong> or <strong>Fruit Warehouse</strong>) from the warehouse filter at the top of the page before opening Auto Match.
                        </p>
                    </div>
                    <button type="button"
                            @click="autoClearModalOpen = false"
                            class="px-5 py-2.5 text-xs font-black rounded-xl bg-slate-900 text-white hover:bg-slate-800 transition cursor-pointer shadow-xs">
                        Close &amp; Select Warehouse
                    </button>
                </div>

                <!-- Loading State -->
                <div x-show="loadingPlan && currentWarehouseId" class="py-16 text-center space-y-3 my-auto">
                    <div class="inline-block animate-spin rounded-full h-9 w-9 border-4 border-emerald-600 border-r-transparent"></div>
                    <p class="text-xs font-black text-slate-700">Evaluating eligible pending bills against open advances in FIFO order...</p>
                </div>

                <!-- Plan Preview Display -->
                <div x-show="!loadingPlan && planData && !autoClearCompleted && currentWarehouseId" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                    <!-- Summary Stats Cards (Sticky) -->
                    <div class="p-5 border-b border-slate-100 bg-slate-50/50 shrink-0 space-y-3">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-2xs">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Ready to Clear</span>
                                <span class="text-xl font-black text-emerald-700 block" x-text="(planData.summary.full_bills ?? 0) + ' Bills'"></span>
                                <span class="text-[10px] text-emerald-600 font-bold block mt-0.5">100% Fully Matched</span>
                            </div>
                            <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-2xs">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Partial Matches</span>
                                <span class="text-xl font-black text-amber-600 block" x-text="(planData.summary.partial_bills ?? 0) + ' Bills'"></span>
                                <span class="text-[10px] text-amber-700 font-bold block mt-0.5">Partial Advance Coverage</span>
                            </div>
                            <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-2xs">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Cannot Auto Clear</span>
                                <span class="text-xl font-black text-rose-600 block" x-text="(planData.summary.skipped_bills ?? 0) + ' Bills'"></span>
                                <span class="text-[10px] text-rose-700 font-bold block mt-0.5">Needs Manual Attention</span>
                            </div>
                            <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-2xs">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Matched Qty</span>
                                <span class="text-xl font-black text-indigo-700 block" x-text="Number(planData.summary.matched_base_qty || 0).toFixed(2) + ' KG'"></span>
                                <span class="text-[10px] text-indigo-600 font-bold block mt-0.5" x-text="(planData.summary.advances_fully_cleared || 0) + ' Advances Full'"></span>
                            </div>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 text-xs font-bold">
                            <button type="button"
                                    @click="activeFilter = 'all'"
                                    :class="activeFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                                    class="px-3 py-1.5 rounded-xl transition cursor-pointer shrink-0">
                                All (<span x-text="modalCounts.all"></span>)
                            </button>
                            <button type="button"
                                    @click="activeFilter = 'ready'"
                                    :class="activeFilter === 'ready' ? 'bg-emerald-700 text-white' : 'bg-white text-emerald-800 hover:bg-emerald-50 border border-slate-200'"
                                    class="px-3 py-1.5 rounded-xl transition cursor-pointer shrink-0">
                                Ready (<span x-text="modalCounts.ready"></span>)
                            </button>
                            <button type="button"
                                    @click="activeFilter = 'partial'"
                                    :class="activeFilter === 'partial' ? 'bg-amber-600 text-white' : 'bg-white text-amber-800 hover:bg-amber-50 border border-slate-200'"
                                    class="px-3 py-1.5 rounded-xl transition cursor-pointer shrink-0">
                                Partial (<span x-text="modalCounts.partial"></span>)
                            </button>
                            <button type="button"
                                    @click="activeFilter = 'unit_diff'"
                                    :class="activeFilter === 'unit_diff' ? 'bg-sky-700 text-white' : 'bg-white text-sky-800 hover:bg-sky-50 border border-slate-200'"
                                    class="px-3 py-1.5 rounded-xl transition cursor-pointer shrink-0">
                                Unit Difference (<span x-text="modalCounts.unit_diff"></span>)
                            </button>
                            <button type="button"
                                    @click="activeFilter = 'no_advance'"
                                    :class="activeFilter === 'no_advance' ? 'bg-slate-700 text-white' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'"
                                    class="px-3 py-1.5 rounded-xl transition cursor-pointer shrink-0">
                                No Advance (<span x-text="modalCounts.no_advance"></span>)
                            </button>
                            <button type="button"
                                    @click="activeFilter = 'exhausted'"
                                    :class="activeFilter === 'exhausted' ? 'bg-orange-700 text-white' : 'bg-white text-orange-800 hover:bg-orange-50 border border-slate-200'"
                                    class="px-3 py-1.5 rounded-xl transition cursor-pointer shrink-0">
                                Advance Exhausted (<span x-text="modalCounts.exhausted"></span>)
                            </button>
                            <button type="button"
                                    @click="activeFilter = 'unconfirmed'"
                                    :class="activeFilter === 'unconfirmed' ? 'bg-indigo-700 text-white' : 'bg-white text-indigo-800 hover:bg-indigo-50 border border-slate-200'"
                                    class="px-3 py-1.5 rounded-xl transition cursor-pointer shrink-0">
                                Unconfirmed (<span x-text="modalCounts.unconfirmed"></span>)
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable Bills Container -->
                    <div class="flex-1 overflow-y-auto px-6 py-4 space-y-6 min-h-0">

                        <!-- SECTION A: READY TO MATCH BILLS -->
                        <div x-show="filteredReadyBills.length > 0" class="space-y-3">
                            <h4 class="text-xs font-black uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                                <span>Ready to Auto-Clear (<span x-text="filteredReadyBills.length"></span> Bills)</span>
                            </h4>

                            <div class="space-y-4">
                                <template x-for="bill in filteredReadyBills" :key="bill.purchase_order_id || bill.source_goods_received_id || bill.reference">
                                    <div class="rounded-2xl border border-emerald-200 bg-white overflow-hidden shadow-2xs">
                                        <!-- Bill Header -->
                                        <div class="px-4 py-2.5 bg-emerald-50/60 border-b border-emerald-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-black text-slate-900 bg-white px-2 py-0.5 rounded-md border border-emerald-200" x-text="bill.reference || ('PO #' + bill.purchase_order_id)"></span>
                                                <span class="font-bold text-slate-800" x-text="bill.supplier_name"></span>
                                                <span class="text-[11px] text-slate-500 font-medium" x-text="'• ' + (bill.bill_date || '')"></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span :class="bill.match_type === 'full_match' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-amber-100 text-amber-800 border-amber-200'"
                                                      class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase border tracking-wider"
                                                      x-text="bill.match_type === 'full_match' ? 'FULL MATCH' : 'PARTIAL MATCH'"></span>
                                                <span class="font-mono font-black text-emerald-900" x-text="Number(bill.matched_base_qty || 0).toFixed(2) + ' KG matched'"></span>
                                            </div>
                                        </div>

                                        <!-- Item Lines Table -->
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-xs">
                                                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                                    <tr>
                                                        <th class="px-4 py-2">Product</th>
                                                        <th class="px-3 py-2 text-right">Bill Remaining</th>
                                                        <th class="px-3 py-2 text-right">Advance Available</th>
                                                        <th class="px-3 py-2 text-right text-emerald-700">Match Now</th>
                                                        <th class="px-3 py-2 text-right text-slate-500">Remain After</th>
                                                        <th class="px-3 py-2 text-center">Status</th>
                                                        <th class="px-4 py-2 text-center">Allocations</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                                    <template x-for="line in (bill.lines || []).filter(l => (parseFloat(l.quantity) || 0) > 0.0001 && l.classification !== 'NO_RECONCILABLE_QUANTITY')"
                                                              :key="line.goods_received_item_id ? 'gri_' + line.goods_received_item_id : 'poi_' + line.purchase_order_item_id">
                                                        <tr class="hover:bg-slate-50/70 transition">
                                                            <td class="px-4 py-2.5">
                                                                <span class="font-black text-slate-900 block" x-text="line.product_name"></span>
                                                                <span class="text-[10px] font-mono text-slate-400" x-text="'SKU: ' + (line.product_sku || 'N/A')"></span>
                                                            </td>
                                                            <td class="px-3 py-2.5 text-right font-mono font-bold" x-text="line.quantity + ' ' + line.unit"></td>
                                                            <td class="px-3 py-2.5 text-right font-mono text-slate-600" x-text="(line.confirmed_advance_qty ?? 0) + ' ' + line.unit"></td>
                                                            <td class="px-3 py-2.5 text-right font-mono font-black text-emerald-700" x-text="line.matched_base_qty + ' ' + (line.matched_unit || line.unit)"></td>
                                                            <td class="px-3 py-2.5 text-right font-mono text-slate-500" x-text="line.remaining_unmatched_base_qty + ' ' + line.unit"></td>
                                                            <td class="px-3 py-2.5 text-center">
                                                                <template x-if="line.classification === 'FULL_MATCH'">
                                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                                        FULL MATCH
                                                                    </span>
                                                                </template>
                                                                <template x-if="line.classification === 'PARTIAL_MATCH'">
                                                                    <div>
                                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">
                                                                            PARTIAL MATCH
                                                                        </span>
                                                                        <span class="text-[9px] text-amber-700 font-bold block mt-0.5" x-text="line.remaining_unmatched_base_qty + ' ' + line.unit + ' will remain'"></span>
                                                                    </div>
                                                                </template>
                                                                <template x-if="line.classification === 'UNIT_DIFFERENCE'">
                                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-sky-100 text-sky-800 border border-sky-200">
                                                                        UNIT DIFFERENCE
                                                                    </span>
                                                                </template>
                                                                <template x-if="line.classification === 'NO_ADVANCE'">
                                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold text-slate-500 bg-slate-100">
                                                                        NO ADVANCE
                                                                    </span>
                                                                </template>
                                                                <template x-if="line.classification === 'ADVANCE_EXHAUSTED'">
                                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold text-orange-700 bg-orange-50 border border-orange-200">
                                                                        EXHAUSTED
                                                                    </span>
                                                                </template>
                                                            </td>
                                                            <td class="px-4 py-2.5 text-center">
                                                                <template x-if="line.matches && line.matches.length > 0">
                                                                    <div>
                                                                        <button type="button"
                                                                                @click="toggleAllocation(lineKey(bill, line))"
                                                                                class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-black rounded-md border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 cursor-pointer transition">
                                                                            <i data-lucide="layers" class="w-3 h-3 text-slate-500"></i>
                                                                            <span x-text="line.matches.length + ' GRN' + (line.matches.length > 1 ? 's' : '')"></span>
                                                                        </button>
                                                                    </div>
                                                                </template>
                                                                <template x-if="!line.matches || line.matches.length === 0">
                                                                    <span class="text-slate-300 font-mono">-</span>
                                                                </template>
                                                            </td>
                                                        </tr>
                                                        <!-- Expandable Nested Allocations -->
                                                        <template x-if="expandedAllocations[lineKey(bill, line)] && line.matches && line.matches.length > 0">
                                                            <tr class="bg-emerald-50/30">
                                                                <td colspan="7" class="px-4 py-2.5 border-t border-emerald-100/60">
                                                                    <div class="bg-white rounded-xl border border-emerald-200 p-3 space-y-2">
                                                                        <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800 block">
                                                                            FIFO Advance GRN Allocations for <span x-text="line.product_name"></span>
                                                                        </span>
                                                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                                                            <template x-for="alloc in line.matches" :key="alloc.advance_goods_received_item_id || alloc.advance_goods_received_id">
                                                                                <div class="p-2 rounded-lg bg-slate-50 border border-slate-200/80 text-[11px] space-y-0.5">
                                                                                    <div class="flex items-center justify-between font-mono font-bold">
                                                                                        <span class="text-slate-900" x-text="alloc.grn_number"></span>
                                                                                        <span class="text-emerald-700 font-black" x-text="alloc.matched_base_qty + ' KG'"></span>
                                                                                    </div>
                                                                                    <div class="text-[10px] text-slate-400 font-medium" x-text="'Received: ' + (alloc.received_at || 'N/A')"></div>
                                                                                </div>
                                                                            </template>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- SECTION B: NEEDS ATTENTION / UNMATCHED -->
                        <div x-show="filteredSkippedBills.length > 0" class="space-y-3">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <i data-lucide="alert-circle" class="w-4 h-4 text-slate-400"></i>
                                <span>Needs Attention / Unmatched (<span x-text="filteredSkippedBills.length"></span> Bills)</span>
                            </h4>

                            <div class="space-y-4">
                                <template x-for="bill in filteredSkippedBills" :key="bill.purchase_order_id || bill.source_goods_received_id || bill.reference">
                                    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-2xs">
                                        <!-- Bill Header -->
                                        <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-2 text-xs">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-slate-800 bg-white px-2 py-0.5 rounded-md border border-slate-200" x-text="bill.reference || ('PO #' + bill.purchase_order_id)"></span>
                                                <span class="font-bold text-slate-700" x-text="bill.supplier_name"></span>
                                                <span class="text-[11px] text-slate-400 font-medium" x-text="'• ' + (bill.bill_date || '')"></span>
                                            </div>
                                            <div>
                                                <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-slate-200 text-slate-700"
                                                      x-text="bill.reason || 'NO ADVANCE'"></span>
                                            </div>
                                        </div>

                                        <!-- Item Lines Table -->
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-xs">
                                                <thead class="bg-slate-50/60 text-[10px] font-black uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                                    <tr>
                                                        <th class="px-4 py-2">Product</th>
                                                        <th class="px-3 py-2 text-right">Bill Remaining</th>
                                                        <th class="px-3 py-2 text-right">Confirmed Advance</th>
                                                        <th class="px-3 py-2 text-right">Unconfirmed Advance</th>
                                                        <th class="px-4 py-2">Reason &amp; Details</th>
                                                        <th class="px-3 py-2 text-center">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                                    <template x-for="line in (bill.lines || []).filter(l => (parseFloat(l.quantity) || 0) > 0.0001 && l.classification !== 'NO_RECONCILABLE_QUANTITY')"
                                                              :key="line.goods_received_item_id ? 'gri_' + line.goods_received_item_id : 'poi_' + line.purchase_order_item_id">
                                                        <tr class="hover:bg-slate-50/70 transition">
                                                            <td class="px-4 py-2.5">
                                                                <span class="font-black text-slate-900 block" x-text="line.product_name"></span>
                                                                <span class="text-[10px] font-mono text-slate-400" x-text="'SKU: ' + (line.product_sku || 'N/A')"></span>
                                                            </td>
                                                            <td class="px-3 py-2.5 text-right font-mono font-bold text-slate-800" x-text="line.quantity + ' ' + line.unit"></td>
                                                            <td class="px-3 py-2.5 text-right font-mono text-slate-600" x-text="(line.confirmed_advance_qty ?? 0) + ' ' + line.unit"></td>
                                                            <td class="px-3 py-2.5 text-right font-mono text-indigo-700 font-bold" x-text="(line.unconfirmed_advance_qty ?? 0) + ' ' + line.unit"></td>
                                                            <td class="px-4 py-2.5">
                                                                <template x-if="line.classification === 'UNIT_DIFFERENCE' || line.unmatched_reason === 'UNIT_DIFFERENCE'">
                                                                    <div class="space-y-0.5">
                                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-sky-100 text-sky-800 border border-sky-200 inline-block">
                                                                            UNIT DIFFERENCE
                                                                        </span>
                                                                        <span class="text-[11px] text-slate-600 block font-medium">
                                                                            <span x-text="line.quantity + ' ' + line.unit"></span> &harr; <span x-text="(line.confirmed_advance_qty ?? 0) + ' (Advance)'"></span> &bull; Needs Unit Correction
                                                                        </span>
                                                                    </div>
                                                                </template>
                                                                <template x-if="line.classification === 'NO_ADVANCE' || line.unmatched_reason === 'NO_ADVANCE'">
                                                                    <div class="space-y-0.5">
                                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold text-slate-600 bg-slate-100 inline-block">
                                                                            NO ADVANCE
                                                                        </span>
                                                                        <span class="text-[11px] text-slate-500 block">No open advance for this product in warehouse</span>
                                                                    </div>
                                                                </template>
                                                                <template x-if="line.classification === 'ADVANCE_EXHAUSTED' || line.unmatched_reason === 'ADVANCE_EXHAUSTED'">
                                                                    <div class="space-y-0.5">
                                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold text-orange-800 bg-orange-100 border border-orange-200 inline-block">
                                                                            ADVANCE EXHAUSTED
                                                                        </span>
                                                                        <span class="text-[11px] text-orange-700 block">Advance fully consumed by earlier FIFO bills</span>
                                                                    </div>
                                                                </template>
                                                                <template x-if="line.classification === 'UNCONFIRMED_ADVANCE' || line.unmatched_reason === 'UNCONFIRMED_ADVANCE'">
                                                                    <div class="space-y-0.5">
                                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold text-indigo-800 bg-indigo-100 border border-indigo-200 inline-block">
                                                                            UNCONFIRMED ADVANCE
                                                                        </span>
                                                                        <span class="text-[11px] text-indigo-700 block" x-text="line.unconfirmed_advance_qty + ' ' + line.unit + ' pending physical confirmation'"></span>
                                                                    </div>
                                                                </template>
                                                                <template x-if="line.classification === 'PARTIAL_REMAINDER' || line.unmatched_reason === 'PARTIAL_REMAINDER'">
                                                                    <div class="space-y-0.5">
                                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold text-amber-800 bg-amber-100 border border-amber-200 inline-block">
                                                                            PARTIAL REMAINDER
                                                                        </span>
                                                                        <span class="text-[11px] text-amber-700 block">Unmatched balance from partial allocation</span>
                                                                    </div>
                                                                </template>
                                                            </td>
                                                            <td class="px-3 py-2.5 text-center">
                                                                <template x-if="line.classification === 'UNIT_DIFFERENCE' || line.unmatched_reason === 'UNIT_DIFFERENCE'">
                                                                    <a href="{{ route('admin.cashbook.inventory', array_filter(['tab' => 'unit_differences', 'date' => $selectedDate, 'warehouse_id' => $selectedWarehouseId])) }}"
                                                                       class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-black text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-lg border border-sky-200 transition">
                                                                        <span>Resolve Unit</span>
                                                                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                                                    </a>
                                                                </template>
                                                                <template x-if="line.classification !== 'UNIT_DIFFERENCE' && line.unmatched_reason !== 'UNIT_DIFFERENCE'">
                                                                    <span class="text-slate-300 font-mono">-</span>
                                                                </template>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Empty State when filtered count = 0 -->
                        <div x-show="filteredReadyBills.length === 0 && filteredSkippedBills.length === 0" class="p-12 text-center bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                            <i data-lucide="inbox" class="w-8 h-8 text-slate-300 mx-auto"></i>
                            <p class="text-xs font-black text-slate-700">No bills found in this filter category.</p>
                        </div>
                    </div>

                    <!-- Sticky Modal Footer -->
                    <div class="px-6 py-3.5 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0 bg-white">
                        <div class="text-xs text-slate-500 font-medium">
                            <span>Ready to clear: <strong class="text-slate-900 font-mono" x-text="planData.summary.ready_bills"></strong> Bills</span>
                            <span class="mx-1">&bull;</span>
                            <span>Matched Base: <strong class="text-emerald-700 font-mono" x-text="Number(planData.summary.matched_base_qty || 0).toFixed(2) + ' KG'"></strong></span>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                            <button type="button"
                                    @click="autoClearModalOpen = false"
                                    :disabled="executingAutoClear"
                                    class="px-4 py-2 text-xs font-bold rounded-xl text-slate-600 hover:bg-slate-100 cursor-pointer transition">
                                Cancel
                            </button>
                            <button type="button"
                                    @click="executeAutoClear()"
                                    :disabled="executingAutoClear || planData.summary.ready_bills === 0 || !currentWarehouseId"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-black rounded-xl bg-emerald-700 text-white hover:bg-emerald-800 transition shadow-xs disabled:opacity-50 cursor-pointer">
                                <span x-show="!executingAutoClear">Confirm &amp; Clear <span x-text="planData.summary.ready_bills"></span> Bills</span>
                                <span x-show="executingAutoClear" class="inline-flex items-center gap-2">
                                    <span class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-r-transparent rounded-full"></span>
                                    <span>Executing Reconciliation...</span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Execution Result Screen -->
                <div x-show="autoClearCompleted" class="py-12 px-6 text-center space-y-4 my-auto">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto shadow-xs">
                        <i data-lucide="check" class="w-8 h-8"></i>
                    </div>
                    <div>
                        <h4 class="text-base font-black text-slate-900">Reconciliation Executed Successfully</h4>
                        <p class="text-xs text-slate-500 mt-1" x-text="autoClearResultMsg"></p>
                    </div>

                    <div class="pt-2">
                        <button type="button"
                                @click="window.location.reload()"
                                class="px-6 py-2.5 text-xs font-black rounded-xl bg-slate-900 text-white hover:bg-slate-800 shadow-xs cursor-pointer transition">
                            Refresh Page
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- MODAL 2: MANUAL MATCH MODAL (ALPINE.JS)                            -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        <div x-show="manualMatchModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto"
                 @click.away="if(!executingManualMatch) manualMatchModalOpen = false">

                <!-- Header -->
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Manual Match Bill</h3>
                        <p class="text-xs text-slate-500 font-semibold" x-text="'PO: ' + activePoNumber + ' • ' + activeSupplier"></p>
                    </div>
                    <button type="button"
                            @click="manualMatchModalOpen = false"
                            :disabled="executingManualMatch"
                            class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Loading State -->
                <div x-show="loadingManualSuggestions" class="py-12 text-center space-y-3">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-900 border-r-transparent"></div>
                    <p class="text-xs font-black text-slate-700">Loading open advance candidates...</p>
                </div>

                <!-- Suggestions Body -->
                <div x-show="!loadingManualSuggestions && manualSuggestions" class="space-y-4">
                    <template x-for="item in (manualSuggestions.items || [])" :key="item.purchase_order_item_id">
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-black text-slate-900" x-text="item.product_name"></span>
                                    <span class="text-xs text-slate-500 ml-2" x-text="'Bill Qty: ' + item.ordered_qty + ' ' + item.unit"></span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase border"
                                      :class="item.match_status === 'exact' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : (item.match_status === 'partial' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-slate-100 text-slate-700 border-slate-200')"
                                      x-text="item.match_status">
                                </span>
                            </div>

                            <!-- Candidate Advances -->
                            <div x-show="item.suggested_matches && item.suggested_matches.length > 0" class="space-y-2">
                                <span class="text-[10px] font-black uppercase text-slate-400">Available Advance Candidates</span>
                                <template x-for="cand in item.suggested_matches" :key="cand.advance_goods_received_id">
                                    <div class="p-2 rounded-xl bg-white border border-slate-200 flex items-center justify-between text-xs">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono font-black text-purple-800" x-text="cand.grn_number"></span>
                                                <span class="text-[10px] text-slate-400" x-text="'(' + cand.received_at + ')'"></span>
                                            </div>
                                            <div class="text-[11px] text-slate-500" x-text="'Available: ' + cand.available_qty + ' ' + cand.unit"></div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-black uppercase text-slate-400">Match:</span>
                                            <input type="number"
                                                   step="0.01"
                                                   x-model.number="cand.proposed_match_qty"
                                                   :max="cand.available_qty"
                                                   min="0"
                                                   class="w-24 px-2.5 py-1 text-xs font-mono font-black rounded-lg bg-slate-50 border border-slate-200 text-right focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                            <span class="text-[10px] font-bold text-slate-500" x-text="cand.unit"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div x-show="!item.suggested_matches || item.suggested_matches.length === 0" class="text-xs text-slate-400 italic">
                                No open advance candidates available for this product.
                            </div>
                        </div>
                    </template>

                    <div x-show="manualErrorMessage" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs font-bold text-rose-800" x-text="manualErrorMessage"></div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button"
                                @click="manualMatchModalOpen = false"
                                :disabled="executingManualMatch"
                                class="px-4 py-2 text-xs font-bold rounded-xl text-slate-600 hover:bg-slate-100 cursor-pointer">
                            Cancel
                        </button>
                        <button type="button"
                                @click="executeManualMatch()"
                                :disabled="executingManualMatch || !hasAnyManualMatches()"
                                class="inline-flex items-center gap-2 px-5 py-2 text-xs font-black rounded-xl bg-slate-900 text-white hover:bg-slate-800 transition shadow-xs disabled:opacity-50 cursor-pointer">
                            <span x-show="!executingManualMatch">Confirm Match</span>
                            <span x-show="executingManualMatch" class="inline-flex items-center gap-2">
                                <span class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-r-transparent rounded-full"></span>
                                <span>Reconciling...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ────────────────────────────────────────────────────────────────── -->
        <!-- MODAL 3: RESOLVE UNIT DIFFERENCE MODAL                             -->
        <!-- ────────────────────────────────────────────────────────────────── -->
        <div x-show="unitDiffModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="if(!executingUnitDiff) unitDiffModalOpen = false"
                 class="w-full max-w-xl rounded-3xl bg-white shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">

                <!-- Modal Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/75">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-sky-100 text-sky-700 flex items-center justify-center">
                            <i data-lucide="scale" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Resolve Unit Difference</h3>
                            <p class="text-xs text-slate-500 font-semibold" x-text="activeUnitDiff ? activeUnitDiff.po_number + ' · ' + activeUnitDiff.supplier_name : ''"></p>
                        </div>
                    </div>
                    <button type="button"
                            @click="unitDiffModalOpen = false"
                            class="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4 overflow-y-auto flex-1">
                    <template x-if="activeUnitDiff">
                        <div class="space-y-4">
                            <!-- Product & Bill Info Box -->
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="text-xs font-black text-slate-900" x-text="activeUnitDiff.product_name"></div>
                                        <div class="text-[11px] font-mono text-slate-400" x-text="'SKU: ' + activeUnitDiff.product_sku + ' | Stored Base: ' + activeUnitDiff.product_base_unit"></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-black text-amber-900" x-text="activeUnitDiff.remaining_bill_qty + ' ' + activeUnitDiff.bill_unit"></div>
                                        <div class="text-[10px] text-amber-700 font-semibold">Remaining Bill Qty</div>
                                    </div>
                                </div>
                                <div class="text-[11px] text-amber-800 font-bold bg-amber-50 p-2 rounded-xl border border-amber-200/60" x-text="activeUnitDiff.reason"></div>
                            </div>

                            <!-- Advance Candidate Selection -->
                            <div>
                                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-700 mb-2">Select Open Advance GRN</label>
                                <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                                    <template x-for="cand in (activeUnitDiff.candidates || [])" :key="cand.item_id">
                                        <label class="flex items-center justify-between p-3 rounded-2xl border cursor-pointer transition-all"
                                               :class="selectedCandidateId == cand.item_id ? 'border-sky-500 bg-sky-50/50 shadow-xs ring-1 ring-sky-500' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                            <div class="flex items-center gap-3">
                                                <input type="radio"
                                                       name="unit_diff_candidate"
                                                       :value="cand.item_id"
                                                       x-model="selectedCandidateId"
                                                       @change="onCandidateChange(cand)"
                                                       class="text-sky-600 focus:ring-sky-500">
                                                <div>
                                                    <div class="text-xs font-black text-slate-900 font-mono" x-text="cand.grn_number"></div>
                                                    <div class="text-[10px] text-slate-400 font-semibold" x-text="'Received: ' + (cand.received_at || '—')"></div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-xs font-black text-purple-700 font-mono" x-text="cand.available_qty + ' ' + (cand.unit || 'KG')"></div>
                                                <div class="text-[10px] text-slate-400 font-semibold">Available</div>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </div>

                            <!-- Quantity and Conversion Factor Inputs -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-700 mb-1">
                                        Bill Qty to Match (<span x-text="activeUnitDiff.bill_unit"></span>)
                                    </label>
                                    <input type="number"
                                           step="0.001"
                                           x-model="matchedQty"
                                           class="w-full px-3.5 py-2 text-xs font-mono font-bold rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-700 mb-1">
                                        Conversion: 1 <span x-text="activeUnitDiff.bill_unit"></span> =
                                    </label>
                                    <div class="flex items-center gap-1.5">
                                        <input type="number"
                                               step="0.0001"
                                               x-model="conversionFactor"
                                               class="w-full px-3.5 py-2 text-xs font-mono font-bold rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                        <span class="text-xs font-black text-slate-500 whitespace-nowrap" x-text="activeUnitDiff.product_base_unit"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Calculation Preview -->
                            <div class="p-3.5 rounded-2xl bg-sky-50/70 border border-sky-200/80 flex items-center justify-between text-xs font-bold text-sky-950">
                                <span>Advance Stock to Deduct:</span>
                                <span class="font-mono font-black text-sky-800 text-sm">
                                    <span x-text="(parseFloat(matchedQty || 0) * parseFloat(conversionFactor || 1)).toFixed(2)"></span>
                                    <span class="text-xs" x-text="activeUnitDiff.product_base_unit"></span>
                                </span>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-700 mb-1">Resolution Notes / Reason</label>
                                <input type="text"
                                       x-model="unitDiffNotes"
                                       placeholder="e.g. Manual conversion verified with vendor"
                                       class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                            </div>

                            <!-- Error Message -->
                            <template x-if="unitDiffErrorMessage">
                                <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold" x-text="unitDiffErrorMessage"></div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/75">
                    <button type="button"
                            @click="unitDiffModalOpen = false"
                            class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-900 rounded-xl hover:bg-slate-100 transition">
                        Cancel
                    </button>
                    <button type="button"
                            @click="executeResolveUnitDiff()"
                            :disabled="executingUnitDiff || !selectedCandidateId || (parseFloat(matchedQty) || 0) <= 0"
                            class="inline-flex items-center gap-2 px-5 py-2 text-xs font-black rounded-xl bg-sky-600 text-white hover:bg-sky-500 transition shadow-xs disabled:opacity-50 cursor-pointer">
                        <span x-show="!executingUnitDiff">Confirm &amp; Match</span>
                        <span x-show="executingUnitDiff" class="inline-flex items-center gap-2">
                            <span class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-r-transparent rounded-full"></span>
                            <span>Resolving...</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Alpine.js Component Logic -->
    <script>
        function inventoryReconciliation(config) {
            return {
                csrfToken: config.csrfToken,
                currentTab: config.currentTab,
                currentDate: config.currentDate,
                currentWarehouseId: config.currentWarehouseId,
                currentSearch: config.currentSearch,

                // Auto Clear state
                autoClearModalOpen: false,
                loadingPlan: false,
                executingAutoClear: false,
                autoClearCompleted: false,
                planData: null,
                errorMessage: '',
                autoClearResultMsg: '',

                // Manual Match state
                manualMatchModalOpen: false,
                loadingManualSuggestions: false,
                executingManualMatch: false,
                activePoId: null,
                activePoNumber: '',
                activeSupplier: '',
                manualSuggestions: null,
                manualErrorMessage: '',

                // Unit Differences state
                unitDiffModalOpen: false,
                executingUnitDiff: false,
                executingFixAdvanceUnits: false,
                activeUnitDiff: null,
                selectedCandidateId: null,
                selectedCandidateObj: null,
                matchedQty: 0,
                conversionFactor: 1.0,
                unitDiffNotes: '',
                unitDiffErrorMessage: '',

                activeFilter: 'all',
                expandedAllocations: {},

                toggleAllocation(key) {
                    this.expandedAllocations[key] = !this.expandedAllocations[key];
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                },

                lineKey(bill, line) {
                    return (bill.purchase_order_id || bill.source_goods_received_id || 'b') + '_' + (line.goods_received_item_id || line.purchase_order_item_id || line.source_item_id || line.product_id);
                },

                get filteredReadyBills() {
                    if (!this.planData || !this.planData.ready_bills) return [];
                    if (['unit_diff', 'no_advance', 'exhausted', 'unconfirmed'].includes(this.activeFilter)) {
                        return [];
                    }
                    return this.planData.ready_bills.filter(bill => {
                        if (this.activeFilter === 'ready') return bill.match_type === 'full_match';
                        if (this.activeFilter === 'partial') return bill.match_type === 'partial_match';
                        return true;
                    });
                },

                get filteredSkippedBills() {
                    if (!this.planData || !this.planData.skipped_bills) return [];
                    if (['ready', 'partial'].includes(this.activeFilter)) {
                        return [];
                    }
                    if (this.activeFilter === 'all') return this.planData.skipped_bills;
                    return this.planData.skipped_bills.filter(bill => {
                        const lines = (bill.lines || []).filter(l => (parseFloat(l.quantity) || 0) > 0.0001 && l.classification !== 'NO_RECONCILABLE_QUANTITY');
                        if (this.activeFilter === 'unit_diff') {
                            return lines.some(l => l.classification === 'UNIT_DIFFERENCE' || l.unmatched_reason === 'UNIT_DIFFERENCE');
                        }
                        if (this.activeFilter === 'no_advance') {
                            return lines.some(l => l.classification === 'NO_ADVANCE' || l.unmatched_reason === 'NO_ADVANCE');
                        }
                        if (this.activeFilter === 'exhausted') {
                            return lines.some(l => l.classification === 'ADVANCE_EXHAUSTED' || l.unmatched_reason === 'ADVANCE_EXHAUSTED');
                        }
                        if (this.activeFilter === 'unconfirmed') {
                            return lines.some(l => l.classification === 'UNCONFIRMED_ADVANCE' || l.unmatched_reason === 'UNCONFIRMED_ADVANCE');
                        }
                        return true;
                    });
                },

                get modalCounts() {
                    if (!this.planData) {
                        return { all: 0, ready: 0, partial: 0, unit_diff: 0, no_advance: 0, exhausted: 0, unconfirmed: 0 };
                    }
                    let unitDiff = 0, noAdv = 0, exhausted = 0, unconfirmed = 0;
                    const allSkipped = this.planData.skipped_bills || [];
                    for (const b of allSkipped) {
                        const lines = (b.lines || []).filter(l => (parseFloat(l.quantity) || 0) > 0.0001 && l.classification !== 'NO_RECONCILABLE_QUANTITY');
                        for (const l of lines) {
                            const c = l.classification || l.unmatched_reason || '';
                            if (c === 'UNIT_DIFFERENCE') unitDiff++;
                            else if (c === 'NO_ADVANCE') noAdv++;
                            else if (c === 'ADVANCE_EXHAUSTED') exhausted++;
                            else if (c === 'UNCONFIRMED_ADVANCE') unconfirmed++;
                        }
                    }
                    return {
                        all: (this.planData.ready_bills?.length || 0) + (this.planData.skipped_bills?.length || 0),
                        ready: this.planData.summary?.full_bills || 0,
                        partial: this.planData.summary?.partial_bills || 0,
                        unit_diff: unitDiff,
                        no_advance: noAdv,
                        exhausted: exhausted,
                        unconfirmed: unconfirmed
                    };
                },

                // 1. Open Auto Clear Modal
                openAutoClear() {
                    this.autoClearModalOpen = true;
                    this.autoClearCompleted = false;
                    this.errorMessage = '';
                    this.activeFilter = 'all';
                    this.expandedAllocations = {};

                    if (!this.currentWarehouseId) {
                        this.loadingPlan = false;
                        this.planData = null;
                        this.$nextTick(() => {
                            if (window.lucide) window.lucide.createIcons();
                        });
                        return;
                    }

                    this.loadingPlan = true;
                    this.planData = null;
                    this.fetchAutoClearPlan();
                },

                async fetchAutoClearPlan() {
                    try {
                        const url = `${config.autoPlanUrl}?warehouse_id=${this.currentWarehouseId}`;
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            }
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            this.planData = json.data;
                            this.$nextTick(() => {
                                if (window.lucide) window.lucide.createIcons();
                            });
                        } else {
                            this.errorMessage = json.message || 'Could not load auto-clear plan.';
                        }
                    } catch (e) {
                        this.errorMessage = 'Failed to fetch auto-clear preview: ' + e.message;
                    } finally {
                        this.loadingPlan = false;
                    }
                },

                // 2. Execute Auto Clear
                async executeAutoClear() {
                    if (!this.planData || !this.planData.plan_hash || !this.currentWarehouseId) return;

                    this.executingAutoClear = true;
                    this.errorMessage = '';

                    const clientSubmissionId = ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
                        (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
                    );

                    try {
                        const res = await fetch(config.autoExecuteUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify({
                                warehouse_id: parseInt(this.currentWarehouseId, 10),
                                plan_hash: this.planData.plan_hash,
                                client_submission_id: clientSubmissionId
                            })
                        });

                        const json = await res.json();
                        if (res.ok && json.status === 'success') {
                            this.autoClearCompleted = true;
                            const d = json.data || {};
                            this.autoClearResultMsg = `Cleared ${d.bills_cleared ?? 0} bills (${d.matched_base_qty ?? 0} KG) across ${d.advances_fully_cleared ?? 0} full advances.`;
                            this.$nextTick(() => {
                                if (window.lucide) window.lucide.createIcons();
                            });
                        } else {
                            this.errorMessage = json.message || 'Auto reconciliation failed during execution.';
                        }
                    } catch (e) {
                        this.errorMessage = 'Network error during auto reconciliation: ' + e.message;
                    } finally {
                        this.executingAutoClear = false;
                    }
                },

                // 3. Open Manual Match Modal
                async openManualMatch(poId, poNumber, supplierName) {
                    this.activePoId = poId;
                    this.activePoNumber = poNumber;
                    this.activeSupplier = supplierName;
                    this.manualMatchModalOpen = true;
                    this.loadingManualSuggestions = true;
                    this.manualErrorMessage = '';
                    this.manualSuggestions = null;

                    try {
                        const url = `${config.manualMatchUrlPrefix}${poId}?warehouse_id=${this.currentWarehouseId || 1}`;
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            }
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            this.manualSuggestions = json.data;
                        } else {
                            this.manualErrorMessage = json.message || 'Failed to load match candidates.';
                        }
                    } catch (e) {
                        this.manualErrorMessage = 'Network error: ' + e.message;
                    } finally {
                        this.loadingManualSuggestions = false;
                    }
                },

                hasAnyManualMatches() {
                    if (!this.manualSuggestions || !this.manualSuggestions.items) return false;
                    return this.manualSuggestions.items.some(item =>
                        item.suggested_matches && item.suggested_matches.some(c => (parseFloat(c.proposed_match_qty) || 0) > 0)
                    );
                },

                // 4. Execute Manual Match
                async executeManualMatch() {
                    if (!this.activePoId || !this.manualSuggestions) return;

                    this.executingManualMatch = true;
                    this.manualErrorMessage = '';

                    const advanceMatches = [];
                    const items = [];

                    for (const item of (this.manualSuggestions.items || [])) {
                        items.push({
                            product_id: item.product_id,
                            purchase_order_item_id: item.purchase_order_item_id,
                            received_qty: item.ordered_qty,
                            unit: item.unit
                        });

                        if (item.suggested_matches) {
                            for (const cand of item.suggested_matches) {
                                const qty = parseFloat(cand.proposed_match_qty) || 0;
                                if (qty > 0) {
                                    advanceMatches.push({
                                        advance_goods_received_id: cand.advance_goods_received_id,
                                        advance_goods_received_item_id: cand.advance_goods_received_item_id,
                                        purchase_order_item_id: item.purchase_order_item_id,
                                        product_id: cand.product_id,
                                        matched_qty: qty,
                                        unit: cand.unit
                                    });
                                }
                            }
                        }
                    }

                    if (advanceMatches.length === 0) {
                        this.manualErrorMessage = 'Please specify at least one candidate match quantity.';
                        this.executingManualMatch = false;
                        return;
                    }

                    try {
                        const url = `${config.manualExecuteUrlPrefix}${this.activePoId}`;
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify({
                                warehouse_id: parseInt(this.currentWarehouseId || 1, 10),
                                items: items,
                                advance_matches: advanceMatches
                            })
                        });

                        const json = await res.json();
                        if (res.ok && json.status === 'success') {
                            window.location.reload();
                        } else {
                            this.manualErrorMessage = json.message || 'Manual match execution failed.';
                        }
                    } catch (e) {
                        this.manualErrorMessage = 'Network error: ' + e.message;
                    } finally {
                        this.executingManualMatch = false;
                    }
                },

                // 5. Open & Execute Unit Difference Resolution
                openResolveUnitDiff(row) {
                    this.activeUnitDiff = row;
                    this.unitDiffErrorMessage = '';
                    this.unitDiffNotes = 'Manual unit difference resolution';
                    const cands = row.candidates || [];
                    if (cands.length > 0) {
                        this.selectedCandidateId = cands[0].item_id;
                        this.selectedCandidateObj = cands[0];
                        this.matchedQty = Math.min(row.remaining_bill_qty, cands[0].available_qty);
                    } else {
                        this.selectedCandidateId = null;
                        this.selectedCandidateObj = null;
                        this.matchedQty = row.remaining_bill_qty;
                    }
                    this.conversionFactor = 1.0;
                    this.unitDiffModalOpen = true;
                },

                onCandidateChange(cand) {
                    this.selectedCandidateObj = cand;
                    if (this.activeUnitDiff) {
                        this.matchedQty = Math.min(this.activeUnitDiff.remaining_bill_qty, cand.available_qty);
                    }
                },

                async executeResolveUnitDiff() {
                    if (!this.activeUnitDiff || !this.selectedCandidateId) return;

                    const qty = parseFloat(this.matchedQty) || 0;
                    const factor = parseFloat(this.conversionFactor) || 0;

                    if (qty <= 0) {
                        this.unitDiffErrorMessage = 'Please enter a valid match quantity.';
                        return;
                    }

                    if (factor <= 0) {
                        this.unitDiffErrorMessage = 'Please enter a valid conversion factor.';
                        return;
                    }

                    this.executingUnitDiff = true;
                    this.unitDiffErrorMessage = '';

                    const cand = (this.activeUnitDiff.candidates || []).find(c => c.item_id == this.selectedCandidateId);

                    try {
                        const res = await fetch(config.resolveUnitDiffUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify({
                                purchase_order_id: this.activeUnitDiff.purchase_order_id,
                                purchase_order_item_id: this.activeUnitDiff.purchase_order_item_id,
                                warehouse_id: parseInt(this.activeUnitDiff.warehouse_id || this.currentWarehouseId || 1, 10),
                                advance_goods_received_id: cand ? cand.advance_goods_received_id : this.activeUnitDiff.candidates[0].advance_goods_received_id,
                                advance_goods_received_item_id: cand ? cand.item_id : null,
                                matched_qty: qty,
                                conversion_factor: factor,
                                notes: this.unitDiffNotes
                            })
                        });

                        const json = await res.json();
                        if (res.ok && json.status === 'success') {
                            window.location.reload();
                        } else {
                            this.unitDiffErrorMessage = json.message || 'Failed to resolve unit difference.';
                        }
                    } catch (e) {
                        this.unitDiffErrorMessage = 'Network error: ' + e.message;
                    } finally {
                        this.executingUnitDiff = false;
                    }
                },

                // 6. Fix Advance Units Action
                async fixAdvanceUnits() {
                    const confirmation = confirm("This will change the Advance unit to each product's default unit for the selected date. Quantities will not be changed. Continue?");
                    if (!confirmation) return;

                    this.executingFixAdvanceUnits = true;
                    try {
                        const res = await fetch(config.fixAdvanceUnitsUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify({
                                date: this.currentDate,
                                warehouse_id: this.currentWarehouseId ? parseInt(this.currentWarehouseId, 10) : null,
                                search: this.currentSearch
                            })
                        });

                        const json = await res.json();
                        if (res.ok && json.status === 'success') {
                            const data = json.data || {};
                            alert(`Advance units fixed: ${data.fixed_count ?? 0}\nAlready correct: ${data.already_correct_count ?? 0}\nSkipped: ${data.skipped_count ?? 0}`);
                            window.location.reload();
                        } else {
                            alert(json.message || 'Failed to fix advance units.');
                        }
                    } catch (e) {
                        alert('Network error while fixing advance units: ' + e.message);
                    } finally {
                        this.executingFixAdvanceUnits = false;
                    }
                }
            };
        }
    </script>
@endsection
